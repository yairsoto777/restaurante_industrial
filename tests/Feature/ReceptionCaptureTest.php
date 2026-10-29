<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReceptionCaptureTest extends RoleAccessTest
{
    private function prepareCapture(): string
    {
        // No hay ninguna conexión MySQL disponible en estos casos de captura.
        config(['database.connections' => ['sqlite' => config('database.connections.sqlite')], 'receptions.capture_enabled' => true]);
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->createOrderSchema();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-28 12:00:00', 'America/Mexico_City'));
        Schema::create('unidades', function (Blueprint $t) {
            $t->increments('id_unidad'); $t->boolean('permite_fracciones');
        });
        Schema::table('insumos', fn (Blueprint $t) => $t->integer('id_unidad_base')->nullable());
        DB::table('unidades')->insert(['id_unidad' => 1, 'permite_fracciones' => true]);
        DB::table('insumos')->update(['id_unidad_base' => 1]);
        Schema::create('recepciones', function (Blueprint $t) {
            $t->increments('id_recepcion');
            foreach (['id_pedido', 'id_proveedor', 'id_usuario'] as $c) $t->integer($c);
            $t->dateTime('fecha_recepcion')->nullable(); $t->dateTime('fecha_confirmacion')->nullable();
            $t->string('estado', 30)->nullable(); $t->text('observaciones')->nullable();
            $t->string('idempotency_key', 36)->nullable()->unique();
        });
        Schema::create('lotes', function (Blueprint $t) {
            $t->increments('id_lote'); $t->integer('id_insumo'); $t->integer('id_proveedor')->nullable();
            $t->string('codigo_lote', 100)->nullable(); $t->date('fecha_caducidad')->nullable();
        });
        Schema::create('recepcion_detalles', function (Blueprint $t) {
            $t->increments('id_recepcion_detalle');
            foreach (['id_recepcion', 'id_detalle_pedido'] as $c) $t->integer($c);
            $t->integer('id_lote')->nullable(); $t->date('caducidad')->nullable();
            $t->decimal('cantidad_entregada', 12, 3)->nullable(); $t->decimal('cantidad_aceptada', 12, 3)->nullable();
            $t->decimal('precio_unitario', 12, 2)->nullable(); $t->string('resultado_revision', 50)->nullable(); $t->text('observaciones')->nullable();
        });
        Schema::create('incidencias', function (Blueprint $t) {
            $t->increments('id_incidencia');
            foreach (['id_sede', 'id_usuario', 'id_recepcion_detalle'] as $c) $t->integer($c);
            $t->dateTime('fecha'); $t->text('descripcion'); $t->string('estado')->nullable();
        });
        Schema::create('insumo_sedes', function (Blueprint $t) { $t->increments('id_insumo_sede'); $t->decimal('stock', 12, 3); });
        Schema::create('inventarios_finales', function (Blueprint $t) { $t->increments('id_inventario_final'); });
        DB::table('insumo_sedes')->insert(['stock' => 7]);
        $this->actingAs($this->createUser('Recepcion de insumos', 10, 1))->withSession(['current_site_id' => 1]);
        return $this->get('/recepciones/pedidos/1/nueva')->assertOk()->viewData('token');
    }

    private function payload(string $token, string $delivered = '5', string $accepted = '4'): array
    {
        return ['token' => $token, 'observaciones' => 'Entrega parcial', 'detalles' => [[
            'id' => 1, 'entregada' => $delivered, 'aceptada' => $accepted,
            'codigo_lote' => 'L-001', 'caducidad' => '2027-01-01', 'resultado_revision' => 'Revisado',
            'observaciones' => 'Envases revisados', 'incidencia' => 'Faltante y producto dañado',
        ]]];
    }

    public function test_valid_receipt_incident_detail_and_no_stock_movements(): void
    {
        $token = $this->prepareCapture();
        $this->post('/recepciones/pedidos/1/capturas', $this->payload($token))->assertRedirect('/recepciones/registradas/1');
        $this->assertDatabaseHas('recepciones', ['estado' => 'Pendiente de revisión', 'fecha_confirmacion' => null]);
        $this->assertDatabaseHas('recepcion_detalles', ['cantidad_entregada' => 5, 'cantidad_aceptada' => 4]);
        $this->assertDatabaseHas('incidencias', ['id_sede' => 1, 'id_recepcion_detalle' => 1]);
        $this->get('/recepciones/registradas/1')->assertOk()->assertSee('7.500')->assertSee('L-001')->assertSee('1.000');
        $this->assertDatabaseCount('inventarios_finales', 0);
        $this->assertDatabaseHas('insumo_sedes', ['stock' => 7]);
    }

    public function test_repeated_and_normalized_submission_is_idempotent_and_changed_payload_conflicts(): void
    {
        $token = $this->prepareCapture();
        $payload = $this->payload($token);
        $this->post('/recepciones/pedidos/1/capturas', $payload)->assertRedirect();
        $payload['detalles'][0]['entregada'] = '5.000';
        $this->post('/recepciones/pedidos/1/capturas', $payload)->assertRedirect('/recepciones/registradas/1');
        $payload['detalles'][0]['aceptada'] = '3';
        $this->post('/recepciones/pedidos/1/capturas', $payload)->assertStatus(409);
        $this->assertDatabaseCount('recepciones', 1); $this->assertDatabaseCount('lotes', 1); $this->assertDatabaseCount('incidencias', 1);
    }

    public function test_invalid_quantities_roll_back_everything(): void
    {
        $token = $this->prepareCapture();
        foreach ([['-1', '0'], ['1', '2'], ['13', '13'], ['0', '0'], ['1.0001', '1']] as [$d, $a]) {
            $this->postJson('/recepciones/pedidos/1/capturas', $this->payload($token, $d, $a))->assertUnprocessable();
        }
        $this->assertDatabaseCount('recepciones', 0); $this->assertDatabaseCount('lotes', 0);
    }

    public function test_pending_receipts_reserve_accepted_quantity_and_partial_delivery_can_continue(): void
    {
        $token = $this->prepareCapture();
        $this->post('/recepciones/pedidos/1/capturas', $this->payload($token, '5', '4'))->assertRedirect();
        $token2 = $this->get('/recepciones/pedidos/1/nueva')->assertOk()->viewData('token');
        $this->postJson('/recepciones/pedidos/1/capturas', $this->payload($token2, '9', '9'))->assertUnprocessable();
        $this->post('/recepciones/pedidos/1/capturas', $this->payload($token2, '8.5', '8.5'))->assertRedirect();
        $this->assertDatabaseCount('recepciones', 2);
        $token3 = $this->get('/recepciones/pedidos/1/nueva')->assertOk()->viewData('token');
        $this->postJson('/recepciones/pedidos/1/capturas', $this->payload($token3, '1', '1'))->assertUnprocessable();
    }

    public function test_released_states_free_quantities_and_unknown_states_block_capture(): void
    {
        $token = $this->prepareCapture();
        $this->post('/recepciones/pedidos/1/capturas', $this->payload($token, '12.5', '12.5'))->assertRedirect();
        foreach (['Anulada', 'Rechazada por almacén'] as $state) {
            DB::table('recepciones')->update(['estado' => $state]);
            $this->get('/recepciones/pedidos/1/nueva')->assertOk()->assertViewHas('details', fn ($rows) => $rows->first()->pendiente === '12.500');
        }
        DB::table('recepciones')->update(['estado' => 'Desconocido']);
        $this->get('/recepciones/pedidos/1/nueva')->assertStatus(409);
    }

    public function test_wrong_site_role_token_and_order_detail_are_rejected(): void
    {
        $token = $this->prepareCapture();
        $this->post('/recepciones/pedidos/2/capturas', $this->payload($token))->assertNotFound();
        $payload = $this->payload($token); $payload['detalles'][0]['id'] = 99;
        $this->postJson('/recepciones/pedidos/1/capturas', $payload)->assertUnprocessable();
        $payload = $this->payload('invalid');
        $this->postJson('/recepciones/pedidos/1/capturas', $payload)->assertUnprocessable();
        $this->actingAs($this->createUser('Jefe de almacen', 13, 1));
        $this->post('/recepciones/pedidos/1/capturas', $this->payload($token))->assertForbidden();
        $this->assertDatabaseCount('recepciones', 0);
    }

    public function test_discrepancy_requires_incident_and_delivered_goods_require_lot(): void
    {
        $token = $this->prepareCapture();
        $payload = $this->payload($token); $payload['detalles'][0]['incidencia'] = '';
        $this->postJson('/recepciones/pedidos/1/capturas', $payload)->assertUnprocessable();
        $payload = $this->payload($token); $payload['detalles'][0]['codigo_lote'] = '';
        $this->postJson('/recepciones/pedidos/1/capturas', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('recepciones', 0);
    }

    public function test_capture_is_unavailable_until_explicit_activation(): void
    {
        $this->prepareCapture();
        config(['receptions.capture_enabled' => false]);
        $this->get('/recepciones/pedidos/1/nueva')->assertStatus(503);
    }

    public function test_capture_requires_database_unique_index(): void
    {
        $token = $this->prepareCapture();
        Schema::table('recepciones', fn (Blueprint $table) => $table->dropUnique(['idempotency_key']));
        $this->post('/recepciones/pedidos/1/capturas', $this->payload($token))->assertStatus(503);
        $this->assertDatabaseCount('recepciones', 0);
    }

    public function test_non_fractional_unit_rejects_decimals_in_both_quantities(): void
    {
        $token = $this->prepareCapture();
        DB::table('unidades')->update(['permite_fracciones' => false]);
        foreach ([['5.5', '4'], ['5', '4.5'], ['5.000', '4']] as [$delivered, $accepted]) {
            $this->postJson('/recepciones/pedidos/1/capturas', $this->payload($token, $delivered, $accepted))
                ->assertUnprocessable()->assertJsonValidationErrors('detalles');
        }
        $this->assertDatabaseCount('recepciones', 0); $this->assertDatabaseCount('lotes', 0);
        $this->post('/recepciones/pedidos/1/capturas', $this->payload($token, '5', '4'))->assertRedirect();
    }

    public function test_fractional_unit_preserves_three_decimal_precision(): void
    {
        $token = $this->prepareCapture();
        $payload = $this->payload($token, '5.125', '4.001');
        $this->post('/recepciones/pedidos/1/capturas', $payload)->assertRedirect();
        $this->assertDatabaseHas('recepcion_detalles', ['cantidad_entregada' => 5.125, 'cantidad_aceptada' => 4.001]);
        $this->post('/recepciones/pedidos/1/capturas', $payload)->assertRedirect('/recepciones/registradas/1');
        $this->assertDatabaseCount('recepciones', 1);
    }

    public function test_expired_accepted_goods_are_rejected_but_expiring_on_receipt_day_are_allowed(): void
    {
        $token = $this->prepareCapture();
        $payload = $this->payload($token);
        $payload['detalles'][0]['caducidad'] = '2026-10-27';
        $this->postJson('/recepciones/pedidos/1/capturas', $payload)->assertUnprocessable()->assertJsonValidationErrors('detalles');
        $this->assertDatabaseCount('recepciones', 0); $this->assertDatabaseCount('lotes', 0);
        $payload['detalles'][0]['caducidad'] = '2026-10-28';
        $this->post('/recepciones/pedidos/1/capturas', $payload)->assertRedirect();
        // Un reenvío al día siguiente sigue recuperando el registro previo.
        $this->travel(1)->days();
        $this->post('/recepciones/pedidos/1/capturas', $payload)->assertRedirect('/recepciones/registradas/1');
        $this->assertDatabaseCount('recepciones', 1);
    }

    public function test_expired_fully_rejected_goods_can_be_documented_without_stock(): void
    {
        $token = $this->prepareCapture();
        $payload = $this->payload($token, '5', '0');
        $payload['detalles'][0]['caducidad'] = '2026-10-27';
        $this->post('/recepciones/pedidos/1/capturas', $payload)->assertRedirect();
        $this->assertDatabaseHas('recepcion_detalles', ['cantidad_aceptada' => 0]);
        $this->assertDatabaseCount('incidencias', 1); $this->assertDatabaseCount('inventarios_finales', 0);
        $this->assertDatabaseHas('insumo_sedes', ['stock' => 7]);
    }

    public function test_client_cannot_associate_a_lot_or_ingredient_from_another_product(): void
    {
        $token = $this->prepareCapture();
        DB::table('insumos')->insert(['id_insumo' => 2, 'nombre' => 'Otro insumo', 'id_unidad_base' => 1]);
        $lotId = DB::table('lotes')->insertGetId(['id_insumo' => 2, 'id_proveedor' => 1, 'codigo_lote' => 'AJENO', 'fecha_caducidad' => '2027-01-01']);
        $payload = $this->payload($token); $payload['detalles'][0]['id_lote'] = $lotId;
        $this->postJson('/recepciones/pedidos/1/capturas', $payload)->assertUnprocessable()->assertJsonValidationErrors('detalles.0.id_lote');
        $payload = $this->payload($token); $payload['detalles'][0]['id_insumo'] = 2;
        $this->postJson('/recepciones/pedidos/1/capturas', $payload)->assertUnprocessable()->assertJsonValidationErrors('detalles.0.id_insumo');
        $this->assertDatabaseCount('recepciones', 0);
        $this->post('/recepciones/pedidos/1/capturas', $this->payload($token))->assertRedirect();
        $lot = DB::table('lotes')->where('id_lote', DB::table('recepcion_detalles')->value('id_lote'))->first();
        $this->assertSame(1, $lot->id_insumo);
        $this->assertSame('2027-01-01', $lot->fecha_caducidad);
    }

    public function test_known_lot_requires_consistent_expiry_for_same_product_and_supplier(): void
    {
        $token = $this->prepareCapture();
        DB::table('lotes')->insert(['id_insumo' => 1, 'id_proveedor' => 1, 'codigo_lote' => 'L-001', 'fecha_caducidad' => '2027-02-01']);
        $this->postJson('/recepciones/pedidos/1/capturas', $this->payload($token))->assertUnprocessable()->assertJsonValidationErrors('detalles');
        $this->assertDatabaseCount('recepciones', 0); $this->assertDatabaseCount('lotes', 1);
        $payload = $this->payload($token); $payload['detalles'][0]['caducidad'] = '2027-02-01';
        $this->post('/recepciones/pedidos/1/capturas', $payload)->assertRedirect();
    }

    public function test_missing_unit_fails_closed_and_local_reception_date_is_used(): void
    {
        $token = $this->prepareCapture();
        DB::table('insumos')->update(['id_unidad_base' => null]);
        $this->postJson('/recepciones/pedidos/1/capturas', $this->payload($token))->assertUnprocessable();
        DB::table('insumos')->update(['id_unidad_base' => 1]);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-29 02:00:00', 'UTC'));
        $payload = $this->payload($token); $payload['detalles'][0]['caducidad'] = '2026-10-28';
        $this->post('/recepciones/pedidos/1/capturas', $payload)->assertRedirect();
        $this->assertDatabaseCount('recepciones', 1);
    }

    public function test_replay_of_confirmed_receipt_does_not_modify_it_and_normalizes_uuid(): void
    {
        $token = $this->prepareCapture();
        $payload = $this->payload($token);
        $this->post('/recepciones/pedidos/1/capturas', $payload)->assertRedirect();
        DB::table('recepciones')->update(['estado' => 'Confirmada', 'fecha_confirmacion' => '2026-10-28 12:00:00']);
        $decoded = json_decode(Crypt::decryptString($token), true);
        $decoded['key'] = strtoupper($decoded['key']);
        $payload['token'] = Crypt::encryptString(json_encode($decoded));
        $payload['detalles'][0] = array_reverse($payload['detalles'][0], true);
        $this->post('/recepciones/pedidos/1/capturas', $payload)->assertRedirect('/recepciones/registradas/1');
        $this->assertDatabaseHas('recepciones', ['estado' => 'Confirmada', 'fecha_confirmacion' => '2026-10-28 12:00:00']);
        $this->patch('/recepciones/registradas/1', [])->assertStatus(405);
        $this->assertDatabaseCount('recepciones', 1); $this->assertDatabaseCount('inventarios_finales', 0);
    }

    public function test_collision_with_another_users_key_returns_conflict(): void
    {
        $token = $this->prepareCapture();
        $this->post('/recepciones/pedidos/1/capturas', $this->payload($token))->assertRedirect();
        $other = $this->createUser('Recepcion de insumos', 10, 1);
        $this->actingAs($other);
        // Simula una colisión de UUID del generador, con un token legítimo para otro usuario.
        $decoded = json_decode(Crypt::decryptString($token), true);
        $decoded['user'] = $other->id_usuario;
        $collision = Crypt::encryptString(json_encode($decoded));
        $this->post('/recepciones/pedidos/1/capturas', $this->payload($collision))->assertStatus(409);
        $this->assertDatabaseCount('recepciones', 1); $this->assertDatabaseCount('lotes', 1);
    }

    public function test_receipt_detail_is_scoped_to_active_site_and_key_is_unique_in_database(): void
    {
        $token = $this->prepareCapture();
        $this->post('/recepciones/pedidos/1/capturas', $this->payload($token))->assertRedirect();
        $this->get('/recepciones/pedidos/1')->assertOk()->assertSee('/recepciones/registradas/1', false);
        DB::table('usuario_sede')->insert(['id_usuario' => auth()->id(), 'id_sede' => 2, 'id_rol' => 10]);
        $this->withSession(['current_site_id' => 2])->get('/recepciones/registradas/1')->assertNotFound();
        $row = (array) DB::table('recepciones')->first();
        unset($row['id_recepcion']);
        try {
            DB::table('recepciones')->insert($row);
            $this->fail('Debe existir una restricción única para la clave.');
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            $this->assertDatabaseCount('recepciones', 1);
        }
    }
}
