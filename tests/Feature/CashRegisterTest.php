<?php

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\User;
use App\Support\CashAmount;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Cash\CashTestCase;

class CashRegisterTest extends CashTestCase
{
    private function user(int $role = 1, ?int $site = 1): User
    {
        $user = User::create(['nombre' => 'Operador de prueba']);
        DB::table('usuario_sede')->insert(['id_usuario' => $user->getKey(), 'id_sede' => $site, 'id_rol' => $role]);

        return $user;
    }

    private function cashSession(User $user, int $site = 1, string $state = 'abierto'): CashSession
    {
        return CashSession::create(['id_usuario_cajero' => $user->getKey(), 'id_sede' => $site, 'fondo_inicial' => '10.00', 'efectivo_esperado' => '10.00', 'estado' => $state]);
    }

    private function payment(CashSession $session, string $amount, string $method = 'efectivo', int $site = 1, string $state = 'confirmado'): void
    {
        $service = DB::table('servicios')->insertGetId(['id_sede' => $site], 'id_servicio');
        $attention = DB::table('atenciones')->insertGetId(['id_servicio' => $service], 'id_atencion');
        $account = DB::table('cuentas')->insertGetId(['id_atencion' => $attention], 'id_cuenta');
        DB::table('pagos')->insert(['id_cuenta' => $account, 'id_usuario' => $session->id_usuario_cajero, 'id_corte_caja' => $session->getKey(), 'metodo_pago' => $method, 'monto' => $amount, 'estado' => $state]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/caja')->assertRedirect(route('login'));
        $this->post(route('cash.open'), ['fondo_inicial' => '10.00'])->assertRedirect(route('login'));
    }

    public function test_cashier_can_access_panel_without_writing_an_opening(): void
    {
        $this->actingAs($this->user())->get(route('cash.index'))->assertOk()->assertSee('Abrir caja');
        $this->assertDatabaseCount('cortes_caja', 0);
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_cashier_can_log_in_and_open_panel_through_real_authentication(): void
    {
        $user = $this->user();
        $password = bin2hex(random_bytes(12));
        $user->forceFill(['correo' => 'cashier@tests.invalid', 'password' => $password])->save();
        $this->post(route('login.store'), ['correo' => $user->correo, 'password' => $password])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->get(route('cash.index'))->assertOk()->assertSee('Abrir caja');
    }

    public function test_cash_panel_renders_with_actual_existing_vite_manifest(): void
    {
        $this->app->forgetInstance(Vite::class);
        $this->actingAs($this->user())->get(route('cash.index'))->assertOk()->assertSee('build/assets/', false);
    }

    public function test_opening_requires_valid_csrf_token(): void
    {
        $this->actingAs($this->user());
        // Laravel normally bypasses CSRF in tests; exercise the actual middleware here.
        $this->app['env'] = 'cash-csrf-check';
        $this->post(route('cash.open'), ['fondo_inicial' => '10.00'])->assertStatus(419);
        $this->assertDatabaseCount('cortes_caja', 0);
        $this->withSession(['_token' => 'cash-test-token'])->post(route('cash.open'), [
            '_token' => 'cash-test-token', 'fondo_inicial' => '10.00',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('cortes_caja', 1);
    }

    public function test_cashier_without_site_cannot_read_or_open(): void
    {
        $this->actingAs($this->user(site: null))->get(route('cash.index'))->assertForbidden();
        $this->post(route('cash.open'), ['fondo_inicial' => '10.00'])->assertForbidden();
        $this->assertDatabaseCount('cortes_caja', 0);
    }

    public function test_cashier_role_in_another_site_does_not_grant_operation_here(): void
    {
        $user = $this->user(site: 1);
        DB::table('usuario_sede')->insert(['id_usuario' => $user->getKey(), 'id_sede' => 2, 'id_rol' => 4]);
        $this->actingAs($user)->withSession(['current_site_id' => 2])->get(route('cash.index'))->assertForbidden();
        $this->post(route('cash.open'), ['fondo_inicial' => '10.00'])->assertForbidden();
    }

    public function test_opening_persists_exact_fund_owner_site_and_audit(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post(route('cash.open'), ['fondo_inicial' => '123.45', 'observaciones' => 'Inicio de turno'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $session = CashSession::sole();
        $this->assertSame('123.45', $session->fondo_inicial);
        $this->assertSame('123.45', $session->efectivo_esperado);
        $this->assertSame('0.00', $session->total_sistema);
        $this->assertEquals($user->getKey(), $session->id_usuario_cajero);
        $this->assertEquals(1, $session->id_sede);
        $this->assertNull($session->fecha_cierre);
        $this->assertDatabaseHas('bitacora', ['accion' => 'abrir_caja', 'id_registro' => $session->getKey(), 'id_usuario' => $user->getKey(), 'id_sede' => 1]);
        $audit = DB::table('bitacora')->sole();
        $this->assertNotNull($audit->fecha);
        $this->assertSame('123.45', json_decode($audit->datos_nuevos, true)['fondo_inicial']);
        $this->get(route('cash.index'))->assertOk()->assertSee('123.45')->assertDontSee('id="cash-opening-form"', false);
    }

    public function test_zero_fund_is_valid(): void
    {
        $this->actingAs($this->user())->post(route('cash.open'), ['fondo_inicial' => '0.00'])->assertSessionHasNoErrors();
        $this->assertSame('0.00', CashSession::sole()->fondo_inicial);
    }

    public function test_decimal_database_limit_can_be_opened_without_rounding(): void
    {
        $this->actingAs($this->user())->post(route('cash.open'), ['fondo_inicial' => '9999999999.99'])->assertSessionHasNoErrors();
        $this->assertSame('9999999999.99', CashSession::sole()->fondo_inicial);
    }

    public function test_invalid_funds_are_rejected_without_writes(): void
    {
        $this->actingAs($this->user());
        foreach (['-1.00', '0.001', '10000000000.00', '1e2', '1,000.00', '', 'abc', ['10.00']] as $amount) {
            $this->post(route('cash.open'), ['fondo_inicial' => $amount])->assertSessionHasErrors('fondo_inicial');
        }
        $this->assertDatabaseCount('cortes_caja', 0);
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_browser_cannot_choose_owner_site_or_state(): void
    {
        $this->actingAs($this->user())->post(route('cash.open'), [
            'fondo_inicial' => '10.00', 'id_sede' => 2, 'id_usuario_cajero' => 999, 'estado' => 'cerrado',
        ])->assertSessionHasErrors(['id_sede', 'id_usuario_cajero', 'estado']);
        $this->assertDatabaseCount('cortes_caja', 0);
    }

    public function test_duplicate_opening_does_not_duplicate_audit(): void
    {
        $this->actingAs($this->user())->post(route('cash.open'), ['fondo_inicial' => '10.00'])->assertSessionHasNoErrors();
        $this->post(route('cash.open'), ['fondo_inicial' => '20.00'])->assertSessionHasErrors('apertura');
        $this->assertDatabaseCount('cortes_caja', 1);
        $this->assertDatabaseCount('bitacora', 1);
        $this->assertSame('10.00', CashSession::sole()->fondo_inicial);
    }

    public function test_rule_allows_different_cashiers_in_same_site_and_same_cashier_in_another_site(): void
    {
        $one = $this->user();
        $two = $this->user();
        $this->actingAs($one)->post(route('cash.open'), ['fondo_inicial' => '10.00'])->assertSessionHasNoErrors();
        $this->actingAs($two)->post(route('cash.open'), ['fondo_inicial' => '20.00'])->assertSessionHasNoErrors();
        DB::table('usuario_sede')->insert(['id_usuario' => $one->getKey(), 'id_sede' => 2, 'id_rol' => 1]);
        $this->actingAs($one)->withSession(['current_site_id' => 2])->post(route('cash.open'), ['fondo_inicial' => '30.00'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('cortes_caja', 3);
    }

    public function test_previous_closed_session_does_not_prevent_opening(): void
    {
        $user = $this->user();
        $this->cashSession($user, state: 'cerrado');
        $this->actingAs($user)->post(route('cash.open'), ['fondo_inicial' => '10.00'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('cortes_caja', 2);
    }

    public function test_cashier_cannot_read_another_cashier_or_foreign_site_session(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $session = $this->cashSession($other);
        $foreign = $this->cashSession($owner, 2);
        $this->actingAs($owner)->get(route('cash.sessions.show', $session))->assertNotFound();
        $this->get(route('cash.sessions.show', $foreign))->assertNotFound();
        $this->get(route('cash.index'))->assertOk()->assertDontSee('Consultar turno');
    }

    public function test_manager_can_supervise_own_site_but_cannot_open_without_cashier_assignment(): void
    {
        $manager = $this->user(2);
        $cashier = $this->user();
        $own = $this->cashSession($cashier);
        $foreign = $this->cashSession($cashier, 2);
        $this->actingAs($manager)->get(route('cash.index'))->assertOk()->assertSee('Turnos abiertos en esta sede');
        $this->get(route('cash.sessions.show', $own))->assertOk();
        $this->get(route('cash.sessions.show', $foreign))->assertNotFound();
        $this->post(route('cash.open'), ['fondo_inicial' => '10.00'])->assertForbidden();
    }

    public function test_manager_of_another_site_does_not_get_global_supervision(): void
    {
        $manager = $this->user(2, 1);
        DB::table('usuario_sede')->insert(['id_usuario' => $manager->getKey(), 'id_sede' => 2, 'id_rol' => 4]);
        $this->actingAs($manager)->withSession(['current_site_id' => 2])->get(route('cash.index'))->assertForbidden();
    }

    public function test_administrator_supervises_selected_site_without_being_cashier(): void
    {
        $admin = $this->user(3);
        $session = $this->cashSession($this->user(site: 2), 2);
        $this->actingAs($admin)->withSession(['current_site_id' => 2])->get(route('cash.sessions.show', $session))->assertOk();
        $this->post(route('cash.open'), ['fondo_inicial' => '10.00'])->assertForbidden();
    }

    public function test_other_roles_and_superadministrator_have_no_new_operational_access(): void
    {
        foreach ([4, 5] as $role) {
            $this->actingAs($this->user($role))->get(route('cash.index'))->assertForbidden();
            $this->post(route('cash.open'), ['fondo_inicial' => '10.00'])->assertForbidden();
        }
    }

    public function test_removed_assignment_is_rechecked_even_with_cached_global_role(): void
    {
        $user = $this->user();
        $this->actingAs($user)->get(route('cash.index'))->assertOk();
        DB::table('usuario_sede')->where('id_usuario', $user->getKey())->delete();
        $this->post(route('cash.open'), ['fondo_inicial' => '10.00'])->assertForbidden();
        $this->assertDatabaseCount('cortes_caja', 0);
    }

    public function test_missing_audit_table_rolls_back_opening(): void
    {
        $this->actingAs($this->user());
        Schema::drop('bitacora');
        $this->post(route('cash.open'), ['fondo_inicial' => '10.00'])->assertSessionHasErrors('apertura');
        $this->assertDatabaseCount('cortes_caja', 0);
    }

    public function test_panel_reads_real_payments_with_exact_amounts(): void
    {
        $user = $this->user();
        $session = $this->cashSession($user);
        $this->payment($session, '0.10');
        $this->payment($session, '0.20');
        $this->payment($session, '5.00', 'tarjeta');
        $this->payment($session, '80.00', state: 'cancelado');
        $response = $this->actingAs($user)->get(route('cash.index'))->assertOk();
        $response->assertViewHas('summary', fn ($summary) => $summary['total'] === '5.30' && $summary['expected'] === '10.30' && $summary['count'] === 3);
        $this->assertDatabaseCount('pagos', 4);
    }

    public function test_panel_with_no_payments_has_real_zero_totals(): void
    {
        $user = $this->user();
        $this->cashSession($user);
        $this->actingAs($user)->get(route('cash.index'))->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['total'] === '0.00' && $summary['expected'] === '10.00' && $summary['count'] === 0);
    }

    public function test_foreign_payment_link_is_not_exposed_or_treated_as_reconciled(): void
    {
        $user = $this->user();
        $session = $this->cashSession($user);
        $this->payment($session, '987.65', site: 2);
        $this->actingAs($user)->get(route('cash.index'))->assertOk()->assertDontSee('987.65')
            ->assertViewHas('summary', fn ($summary) => $summary['inconsistent'] && $summary['expected'] === null);
    }

    public function test_unknown_payment_method_requires_reconciliation(): void
    {
        $user = $this->user();
        $session = $this->cashSession($user);
        $this->payment($session, '5.00', 'sin_definir');
        $this->actingAs($user)->get(route('cash.index'))->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['inconsistent'] && $summary['expected'] === null);
    }

    public function test_negative_confirmed_payment_never_produces_a_reconciled_estimate(): void
    {
        $user = $this->user();
        $session = $this->cashSession($user);
        $this->payment($session, '-5.00');
        $this->actingAs($user)->get(route('cash.index'))->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['inconsistent'] && $summary['expected'] === null && $summary['total'] === null);
    }

    public function test_exact_amount_utility_preserves_decimal_limit(): void
    {
        $this->assertSame(999999999999, CashAmount::cents('9999999999.99'));
        $this->assertSame('9999999999.99', CashAmount::decimal(999999999999));
        $this->assertSame('0.30', CashAmount::decimal(CashAmount::cents('0.10') + CashAmount::cents('0.20')));
    }

    public function test_closing_and_payment_endpoints_are_not_exposed(): void
    {
        $user = $this->user();
        $session = $this->cashSession($user);
        $this->actingAs($user)->get(route('cash.sessions.show', $session))->assertOk()->assertSee('Cierre de caja pendiente de habilitación');
        $this->post('/caja/turnos/'.$session->getKey().'/cerrar', ['efectivo_contado' => '10.00'])->assertNotFound();
        $this->post('/caja/pagos', ['monto' => '10.00'])->assertNotFound();
        $this->assertSame('abierto', $session->fresh()->estado);
        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_concurrent_openings_are_serialized_by_innodb(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requires isolated MariaDB: php tests/Cash/run-mariadb.php');
        }
        $user = $this->user();
        $workers = [];
        DB::beginTransaction();
        DB::table('usuarios')->where('id_usuario', $user->getKey())->lockForUpdate()->first();
        try {
            for ($i = 0; $i < 2; $i++) {
                $process = proc_open([PHP_BINARY, base_path('tests/Cash/open-worker.php'), (string) $user->getKey(), '1'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
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
                $results[] = trim(stream_get_contents($pipes[1]));
                $error = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $this->assertSame(0, proc_close($process), 'Worker failed: '.$error);
                $this->assertSame('', $error);
            }
            $workers = [];
            sort($results);
            $this->assertSame(['DUPLICATE', 'OPENED'], $results);
            $this->assertDatabaseCount('cortes_caja', 1);
            $this->assertDatabaseCount('bitacora', 1);
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
}
