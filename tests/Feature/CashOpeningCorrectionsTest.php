<?php

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\User;
use App\Support\CashOpeningSafety;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Cash\CashTestCase;

class CashOpeningCorrectionsTest extends CashTestCase
{
    private function cashier(int $role = 1, int $site = 1): User
    {
        $user = User::create(['nombre' => 'Operador aislado']);
        DB::table('usuario_sede')->insert(['id_usuario' => $user->getKey(), 'id_sede' => $site, 'id_rol' => $role]);

        return $user;
    }

    private function opening(User $user, string $fund = '0.10', string $state = 'abierto', int $site = 1): CashSession
    {
        return CashSession::create([
            'id_usuario_cajero' => $user->getKey(), 'id_sede' => $site,
            'fondo_inicial' => $fund, 'efectivo_esperado' => $fund,
            'estado' => $state, 'fecha_apertura' => now(),
        ]);
    }

    private function revision(CashSession $session): string
    {
        return app(CashOpeningSafety::class)->revision($session->fresh());
    }

    private function correctionData(CashSession $session, string $amount = '150.25'): array
    {
        return ['fondo_inicial' => $amount, 'motivo' => 'Error al capturar el fondo', 'revision' => $this->revision($session)];
    }

    private function cancellationData(CashSession $session): array
    {
        return ['motivo' => 'Apertura registrada por error', 'confirmacion' => '1', 'revision' => $this->revision($session)];
    }

    private function addPayment(User $user, ?int $cut, string $state = 'confirmado'): void
    {
        // Deliberately orphan account: corrections must not hide it behind an inner join.
        DB::table('pagos')->insert([
            'id_cuenta' => 999, 'id_usuario' => $user->getKey(), 'id_corte_caja' => $cut,
            'metodo_pago' => 'efectivo', 'monto' => '1.00', 'estado' => $state,
            'fecha_pago' => now()->addSecond(),
        ]);
    }

    public function test_owner_updates_fund_expected_amount_and_audit_persistently(): void
    {
        $user = $this->cashier();
        $session = $this->opening($user);
        $openedAt = $session->fecha_apertura->format('Y-m-d H:i:s');
        $this->actingAs($user)->get(route('cash.fund.edit', $session))->assertOk()->assertSee('Fondo inicial actual');
        $this->post(route('cash.fund.update', $session), $this->correctionData($session))->assertSessionHasNoErrors()->assertRedirect(route('cash.sessions.show', $session));
        $this->assertSame('150.25', $session->fresh()->fondo_inicial);
        $this->assertSame('150.25', $session->fresh()->efectivo_esperado);
        $this->assertSame($openedAt, $session->fresh()->fecha_apertura->format('Y-m-d H:i:s'));
        $this->assertSame('0.00', $session->fresh()->total_sistema);
        $this->get(route('cash.sessions.show', $session))->assertOk()->assertSee('150.25')->assertSee('Error al capturar el fondo');
        $audit = DB::table('bitacora')->sole();
        $this->assertSame('modificar_fondo_caja', $audit->accion);
        $this->assertSame('0.10', json_decode($audit->datos_anteriores, true)['fondo_inicial']);
        $this->assertSame('150.25', json_decode($audit->datos_nuevos, true)['fondo_inicial']);
        $this->assertEquals($user->getKey(), $audit->id_usuario);
        $this->assertEquals(1, $audit->id_sede);
        $this->assertNotNull($audit->fecha);
    }

    public function test_zero_and_decimal_limit_are_valid_corrections(): void
    {
        $user = $this->cashier();
        $session = $this->opening($user);
        $this->actingAs($user);
        foreach (['0.00', '9999999999.99'] as $fund) {
            $this->post(route('cash.fund.update', $session), $this->correctionData($session, $fund))->assertSessionHasNoErrors();
            $this->assertSame($fund, $session->fresh()->fondo_inicial);
            $this->assertSame($fund, $session->fresh()->efectivo_esperado);
        }
    }

    public function test_invalid_funds_or_missing_motive_do_not_change_opening(): void
    {
        $user = $this->cashier();
        $session = $this->opening($user);
        $this->actingAs($user);
        foreach (['-0.01', '1.001', '10000000000.00', '1e2', '1,00', '', ['10']] as $amount) {
            $data = $this->correctionData($session);
            $data['fondo_inicial'] = $amount;
            $this->post(route('cash.fund.update', $session), $data)->assertSessionHasErrors('fondo_inicial');
        }
        foreach (['', '   ', str_repeat('x', 1001)] as $motive) {
            $data = $this->correctionData($session);
            $data['motivo'] = $motive;
            $this->post(route('cash.fund.update', $session), $data)->assertSessionHasErrors('motivo');
        }
        $this->assertSame('0.10', $session->fresh()->fondo_inicial);
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_browser_cannot_choose_previous_amount_owner_state_or_site(): void
    {
        $user = $this->cashier();
        $session = $this->opening($user);
        $data = $this->correctionData($session) + ['fondo_anterior' => '500.00', 'id_sede' => 2, 'id_usuario_cajero' => 999, 'estado' => 'cerrado'];
        $this->actingAs($user)->post(route('cash.fund.update', $session), $data)->assertSessionHasErrors(['fondo_anterior', 'id_sede', 'id_usuario_cajero', 'estado']);
        $this->assertSame('0.10', $session->fresh()->fondo_inicial);
    }

    public function test_foreign_owner_or_site_cannot_modify_or_cancel_even_with_valid_revision(): void
    {
        $user = $this->cashier();
        $other = $this->cashier();
        foreach ([$this->opening($other), $this->opening($user, site: 2)] as $session) {
            $this->actingAs($user)->get(route('cash.fund.edit', $session))->assertNotFound();
            $this->post(route('cash.fund.update', $session), $this->correctionData($session))->assertNotFound();
            $this->get(route('cash.cancel.confirm', $session))->assertNotFound();
            $this->post(route('cash.cancel', $session), $this->cancellationData($session))->assertNotFound();
        }
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_global_cashier_role_does_not_authorize_correction_in_other_site(): void
    {
        $user = $this->cashier();
        $session = $this->opening($user, site: 2);
        DB::table('usuario_sede')->insert(['id_usuario' => $user->getKey(), 'id_sede' => 2, 'id_rol' => 4]);
        $this->actingAs($user)->withSession(['current_site_id' => 2])->post(route('cash.fund.update', $session), $this->correctionData($session))->assertForbidden();
        $this->post(route('cash.cancel', $session), $this->cancellationData($session))->assertForbidden();
    }

    public function test_supervisors_can_read_history_but_cannot_correct_other_cashier(): void
    {
        $session = $this->opening($this->cashier());
        foreach ([2, 3] as $role) {
            $this->actingAs($this->cashier($role))->get(route('cash.sessions.show', $session))->assertOk()->assertDontSee('Modificar fondo inicial')->assertDontSee('Anular apertura');
            $this->post(route('cash.fund.update', $session), $this->correctionData($session))->assertForbidden();
            $this->post(route('cash.cancel', $session), $this->cancellationData($session))->assertForbidden();
        }
    }

    public function test_closed_or_cancelled_openings_cannot_be_changed(): void
    {
        $user = $this->cashier();
        $this->actingAs($user);
        foreach (['cerrado', 'anulado'] as $state) {
            $session = $this->opening($user, state: $state);
            $this->get(route('cash.fund.edit', $session))->assertRedirect(route('cash.sessions.show', $session))->assertSessionHasErrors('correccion');
            $this->post(route('cash.fund.update', $session), $this->correctionData($session))->assertSessionHasErrors('correccion');
            $this->post(route('cash.cancel', $session), $this->cancellationData($session))->assertSessionHasErrors('correccion');
            $this->assertSame($state, $session->fresh()->estado);
        }
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_any_associated_payment_blocks_both_operations_including_orphan_and_cancelled_payments(): void
    {
        $user = $this->cashier();
        $this->actingAs($user);
        foreach (['confirmado', 'cancelado', 'pendiente', 'anulado', 'sin_definir'] as $state) {
            $session = $this->opening($user);
            $this->addPayment($user, $session->getKey(), $state);
            $this->get(route('cash.sessions.show', $session))->assertOk()->assertDontSee('Modificar fondo inicial');
            $this->post(route('cash.fund.update', $session), $this->correctionData($session))->assertSessionHasErrors('correccion');
            $this->post(route('cash.cancel', $session), $this->cancellationData($session))->assertSessionHasErrors('correccion');
            $this->assertSame('abierto', $session->fresh()->estado);
            $this->assertSame('0.10', $session->fresh()->fondo_inicial);
        }
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_payments_without_valid_cut_require_reconciliation(): void
    {
        $user = $this->cashier();
        $session = $this->opening($user);
        $this->actingAs($user);
        $this->addPayment($user, null, 'cancelado');
        $this->post(route('cash.fund.update', $session), $this->correctionData($session))->assertSessionHasErrors('correccion');
        $this->post(route('cash.cancel', $session), $this->cancellationData($session))->assertSessionHasErrors('correccion');
        DB::table('pagos')->update(['id_corte_caja' => 99999]);
        $this->post(route('cash.cancel', $session), $this->cancellationData($session))->assertSessionHasErrors('correccion');
        $this->assertSame('abierto', $session->fresh()->estado);
    }

    public function test_old_unassigned_payment_is_still_ambiguous_without_a_valid_cut(): void
    {
        $user = $this->cashier();
        $session = $this->opening($user);
        $this->addPayment($user, null);
        DB::table('pagos')->update(['fecha_pago' => now()->subYear()]);
        $this->actingAs($user)->post(route('cash.cancel', $session), $this->cancellationData($session))->assertSessionHasErrors('correccion');
        $this->assertSame('abierto', $session->fresh()->estado);
    }

    public function test_cashier_assignment_is_revalidated_after_form_was_opened(): void
    {
        $user = $this->cashier();
        $session = $this->opening($user);
        $this->actingAs($user)->get(route('cash.fund.edit', $session))->assertOk();
        $correction = $this->correctionData($session);
        $cancellation = $this->cancellationData($session);
        DB::table('usuario_sede')->where('id_usuario', $user->getKey())->delete();
        $this->post(route('cash.fund.update', $session), $correction)->assertForbidden();
        $this->post(route('cash.cancel', $session), $cancellation)->assertForbidden();
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_additional_linked_movements_are_detected_from_schema(): void
    {
        $user = $this->cashier();
        $session = $this->opening($user);
        $this->actingAs($user);
        // Warm up the page first: the metadata check must not stay cached across requests.
        $this->get(route('cash.sessions.show', $session))->assertOk()->assertSee('Modificar fondo inicial');
        Schema::create('movimientos_caja', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('id_corte_caja');
        });
        try {
            DB::table('movimientos_caja')->insert(['id_corte_caja' => $session->getKey()]);
            $this->post(route('cash.fund.update', $session), $this->correctionData($session))->assertSessionHasErrors('correccion');
            $this->post(route('cash.cancel', $session), $this->cancellationData($session))->assertSessionHasErrors('correccion');
        } finally {
            Schema::dropIfExists('movimientos_caja');
        }
    }

    public function test_unknown_financial_link_is_blocked_conservatively(): void
    {
        $user = $this->cashier();
        $session = $this->opening($user);
        $this->actingAs($user);
        Schema::create('retiros_caja', function (Blueprint $table): void {
            $table->increments('id');
            $table->decimal('monto', 12, 2);
        });
        try {
            $this->post(route('cash.cancel', $session), $this->cancellationData($session))->assertSessionHasErrors('correccion');
        } finally {
            Schema::dropIfExists('retiros_caja');
        }
    }

    public function test_stored_totals_or_arqueo_signals_block_changes_even_without_payments(): void
    {
        $user = $this->cashier();
        $this->actingAs($user);
        foreach (['total_tarjeta' => '1.00', 'total_sistema' => '1.00', 'efectivo_esperado' => '0.11', 'efectivo_contado' => '0.00', 'diferencia' => '0.00', 'fecha_cierre' => now()] as $column => $value) {
            $session = $this->opening($user);
            $session->$column = $value;
            $session->save();
            $this->post(route('cash.fund.update', $session), $this->correctionData($session))->assertSessionHasErrors('correccion');
            $this->post(route('cash.cancel', $session), $this->cancellationData($session))->assertSessionHasErrors('correccion');
        }
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_cancellation_preserves_record_fund_and_history_without_financial_close(): void
    {
        $user = $this->cashier();
        $session = $this->opening($user);
        $this->actingAs($user)->get(route('cash.cancel.confirm', $session))->assertOk()->assertSee('Confirmar anulación de apertura');
        $this->post(route('cash.cancel', $session), $this->cancellationData($session))->assertSessionHasNoErrors()->assertRedirect(route('cash.sessions.show', $session));
        $this->assertSame('anulado', $session->fresh()->estado);
        $this->assertNotNull($session->fresh()->fecha_cierre);
        $this->assertNull($session->fresh()->efectivo_contado);
        $this->assertNull($session->fresh()->diferencia);
        $this->assertSame('0.10', $session->fresh()->fondo_inicial);
        $this->assertDatabaseCount('cortes_caja', 1);
        $audit = DB::table('bitacora')->sole();
        $this->assertSame('anular_apertura_caja', $audit->accion);
        $this->assertSame('abierto', json_decode($audit->datos_anteriores, true)['estado']);
        $this->assertSame('anulado', json_decode($audit->datos_nuevos, true)['estado']);
        $this->get(route('cash.sessions.show', $session))->assertOk()->assertSee('Apertura registrada por error')->assertSee('No constituye un corte financiero')->assertDontSee('Modificar fondo inicial');
    }

    public function test_cancellation_requires_motive_and_explicit_confirmation(): void
    {
        $user = $this->cashier();
        $session = $this->opening($user);
        $this->actingAs($user);
        $data = $this->cancellationData($session);
        unset($data['confirmacion']);
        $this->post(route('cash.cancel', $session), $data)->assertSessionHasErrors('confirmacion');
        $data = $this->cancellationData($session);
        $data['motivo'] = '';
        $this->post(route('cash.cancel', $session), $data)->assertSessionHasErrors('motivo');
        $this->assertSame('abierto', $session->fresh()->estado);
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_new_opening_after_cancellation_gets_new_id_and_keeps_old_history(): void
    {
        $user = $this->cashier();
        $old = $this->opening($user);
        $this->actingAs($user);
        $this->post(route('cash.cancel', $old), $this->cancellationData($old))->assertSessionHasNoErrors();
        $this->get(route('cash.index'))->assertOk()->assertSee('Abrir caja')->assertSee('anulado');
        $this->post(route('cash.open'), ['fondo_inicial' => '20.00'])->assertSessionHasNoErrors();
        $new = CashSession::where('estado', 'abierto')->sole();
        $this->assertGreaterThan($old->getKey(), $new->getKey());
        $this->assertSame('anulado', $old->fresh()->estado);
        $this->assertDatabaseCount('cortes_caja', 2);
        $this->post(route('cash.open'), ['fondo_inicial' => '30.00'])->assertSessionHasErrors('apertura');
        $this->get(route('cash.sessions.show', $old))->assertOk()->assertSee('Anulación de apertura');
    }

    public function test_repeated_cancellation_is_rejected_without_second_audit(): void
    {
        $user = $this->cashier();
        $session = $this->opening($user);
        $data = $this->cancellationData($session);
        $this->actingAs($user)->post(route('cash.cancel', $session), $data)->assertSessionHasNoErrors();
        $this->post(route('cash.cancel', $session), $data)->assertSessionHasErrors('correccion');
        $this->assertDatabaseCount('bitacora', 1);
    }

    public function test_audit_insert_failure_rolls_back_both_operations(): void
    {
        $user = $this->cashier();
        $session = $this->opening($user);
        $this->actingAs($user);
        // Revision lookup still works; only the mandatory INSERT will fail after updating the cut.
        Schema::table('bitacora', fn (Blueprint $table) => $table->dropColumn('descripcion'));
        $this->post(route('cash.fund.update', $session), $this->correctionData($session))->assertSessionHasErrors('correccion');
        $this->assertSame('0.10', $session->fresh()->fondo_inicial);
        $this->assertSame('0.10', $session->fresh()->efectivo_esperado);
        $this->post(route('cash.cancel', $session), $this->cancellationData($session))->assertSessionHasErrors('correccion');
        $this->assertSame('abierto', $session->fresh()->estado);
        $this->assertNull($session->fresh()->fecha_cierre);
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_stale_or_forged_revision_cannot_overwrite_new_value(): void
    {
        $user = $this->cashier();
        $session = $this->opening($user);
        $data = $this->correctionData($session);
        $this->actingAs($user)->post(route('cash.fund.update', $session), $data)->assertSessionHasNoErrors();
        $data['fondo_inicial'] = '50.00';
        $this->post(route('cash.fund.update', $session), $data)->assertSessionHasErrors('correccion');
        $data['revision'] = str_repeat('0', 64);
        $this->post(route('cash.fund.update', $session), $data)->assertSessionHasErrors('correccion');
        $this->assertSame('150.25', $session->fresh()->fondo_inicial);
        $this->assertDatabaseCount('bitacora', 1);
    }

    public function test_returning_to_old_amount_does_not_revalidate_an_old_form(): void
    {
        $user = $this->cashier();
        $session = $this->opening($user);
        $original = $this->correctionData($session);
        $this->actingAs($user)->post(route('cash.fund.update', $session), $original)->assertSessionHasNoErrors();
        $this->post(route('cash.fund.update', $session), $this->correctionData($session, '0.10'))->assertSessionHasNoErrors();
        $this->post(route('cash.fund.update', $session), $original)->assertSessionHasErrors('correccion');
        $this->assertDatabaseCount('bitacora', 2);
    }

    public function test_new_mutations_require_csrf(): void
    {
        $user = $this->cashier();
        $session = $this->opening($user);
        $this->actingAs($user);
        $this->app['env'] = 'cash-csrf-check';
        $this->post(route('cash.fund.update', $session), $this->correctionData($session))->assertStatus(419);
        $this->post(route('cash.cancel', $session), $this->cancellationData($session))->assertStatus(419);
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_observations_are_labelled_and_date_spans_the_unused_grid_cell(): void
    {
        $user = $this->cashier();
        $session = $this->opening($user);
        $session->observaciones = 'si';
        $session->save();
        $this->actingAs($user)->get(route('cash.index'))->assertOk()->assertSee('Observaciones de apertura')
            ->assertSee('<p>si</p>', false)->assertSee('style="grid-column:1 / -1"', false)
            ->assertSee('Modificar fondo inicial')->assertSee('Anular apertura');
    }

    public function test_motive_is_escaped_in_history(): void
    {
        $user = $this->cashier();
        $session = $this->opening($user);
        $data = $this->correctionData($session);
        $data['motivo'] = '<script>alert("test")</script>';
        $this->actingAs($user)->post(route('cash.fund.update', $session), $data)->assertSessionHasNoErrors();
        $this->get(route('cash.sessions.show', $session))->assertOk()->assertDontSee($data['motivo'], false)->assertSee(e($data['motivo']), false);
    }

    private function concurrentResults(User $user, CashSession $session, array $actions): array
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requires isolated MariaDB/InnoDB.');
        }
        $revision = $this->revision($session);
        $workers = [];
        DB::beginTransaction();
        DB::table('usuarios')->where('id_usuario', $user->getKey())->lockForUpdate()->first();
        try {
            foreach ($actions as $action) {
                $process = proc_open([PHP_BINARY, base_path('tests/Cash/mutation-worker.php'), $action, (string) $user->getKey(), (string) $session->getKey(), $revision], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
                $this->assertIsResource($process);
                fclose($pipes[0]);
                $workers[] = [$process, $pipes];
            }
            foreach ($workers as [$process, $pipes]) {
                stream_set_timeout($pipes[1], 15);
                $this->assertSame("READY\n", fgets($pipes[1]));
            }
            usleep(100000);
            DB::commit();
            $results = [];
            foreach ($workers as [$process, $pipes]) {
                $result = trim(stream_get_contents($pipes[1]));
                $error = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $this->assertSame(0, proc_close($process), $error);
                $this->assertSame('', $error);
                $results[] = $result;
            }
            $workers = [];

            return $results;
        } finally {
            if (DB::transactionLevel()) {
                DB::rollBack();
            }
            foreach ($workers as [$process, $pipes]) {
                if (is_resource($process)) {
                    proc_terminate($process);
                }
                foreach ($pipes as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                if (is_resource($process)) {
                    proc_close($process);
                }
            }
        }
    }

    public function test_two_concurrent_corrections_do_not_overwrite_each_other(): void
    {
        $user = $this->cashier();
        $session = $this->opening($user);
        $results = $this->concurrentResults($user, $session, ['correct-one', 'correct-two']);
        sort($results);
        $this->assertSame(['APPLIED', 'REJECTED'], $results);
        $this->assertContains($session->fresh()->fondo_inicial, ['1.11', '2.22']);
        $this->assertSame($session->fresh()->fondo_inicial, $session->fresh()->efectivo_esperado);
        $this->assertDatabaseCount('bitacora', 1);
    }

    public function test_concurrent_correction_and_cancellation_apply_only_one_form_revision(): void
    {
        $user = $this->cashier();
        $session = $this->opening($user);
        $results = $this->concurrentResults($user, $session, ['correct-one', 'cancel']);
        sort($results);
        $this->assertSame(['APPLIED', 'REJECTED'], $results);
        $this->assertDatabaseCount('bitacora', 1);
        $this->assertContains($session->fresh()->estado, ['abierto', 'anulado']);
    }

    public function test_concurrent_cancellations_do_not_duplicate_audit(): void
    {
        $user = $this->cashier();
        $session = $this->opening($user);
        $results = $this->concurrentResults($user, $session, ['cancel', 'cancel']);
        sort($results);
        $this->assertSame(['APPLIED', 'REJECTED'], $results);
        $this->assertSame('anulado', $session->fresh()->estado);
        $this->assertDatabaseCount('bitacora', 1);
    }

    public function test_concurrent_cancellation_and_opening_keep_at_most_one_open_turn(): void
    {
        $user = $this->cashier();
        $session = $this->opening($user);
        $results = $this->concurrentResults($user, $session, ['cancel', 'open']);
        $this->assertContains('APPLIED', $results);
        $this->assertSame('anulado', $session->fresh()->estado);
        $this->assertLessThanOrEqual(1, CashSession::where('estado', 'abierto')->count());
        $this->assertDatabaseHas('cortes_caja', ['id_corte_caja' => $session->getKey(), 'estado' => 'anulado']);
    }

    public function test_payment_writer_following_lock_protocol_cannot_pay_an_annulled_turn(): void
    {
        $user = $this->cashier();
        $session = $this->opening($user);
        $results = $this->concurrentResults($user, $session, ['cancel', 'payment']);
        sort($results);
        $this->assertSame(['APPLIED', 'REJECTED'], $results);
        if ($session->fresh()->estado === 'anulado') {
            $this->assertDatabaseCount('pagos', 0);
            $this->assertDatabaseCount('bitacora', 1);
        } else {
            $this->assertDatabaseCount('pagos', 1);
            $this->assertDatabaseCount('bitacora', 0);
        }
    }
}
