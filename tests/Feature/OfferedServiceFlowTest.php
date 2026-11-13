<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OfferedServiceFlowTest extends TestCase
{
    use RefreshDatabase;

    public function refreshDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->createSchema();
    }

    public function test_waiter_can_register_finalize_and_cashier_can_charge_offered_service_once(): void
    {
        [$waiter, $cashier] = $this->seedOperation();
        $attention = $this->startAttention($waiter);

        $this->actingAs($waiter)->withSession(['current_site_id' => 1])
            ->post(route('waiter.offered-service.store', $attention), [
                'descripcion' => 'Desayuno completo', 'comensales' => 3, 'precio_unitario' => '85.50',
            ])->assertRedirect();

        $this->assertDatabaseHas('servicios_ofrecidos', [
            'id_atencion' => $attention, 'descripcion' => 'Desayuno completo', 'comensales' => 3,
            'precio_unitario' => '85.50', 'total' => '256.50', 'estado' => 'Registrado',
        ]);

        $this->actingAs($waiter)->withSession(['current_site_id' => 1])
            ->patch(route('waiter.offered-service.finalize', $attention))->assertRedirect();
        $this->assertDatabaseHas('cuentas', ['id_atencion' => $attention, 'total' => '256.50', 'estado' => 'pendiente']);
        $this->assertDatabaseHas('atenciones', ['id_atencion' => $attention, 'estado' => 'Pendiente de pago']);
        $this->assertDatabaseHas('servicios_ofrecidos', ['id_atencion' => $attention, 'estado' => 'Pendiente de cobro']);

        $this->actingAs($cashier)->withSession(['current_site_id' => 1])->get(route('cash.index'))
            ->assertOk()->assertSee('Servicio ofrecido: Desayuno completo')->assertSee('3 comensales');

        Schema::getConnection()->table('caja_turnos')->insert([
            'id_turno_caja' => 1, 'id_sede' => 1, 'id_usuario_apertura' => $cashier->id_usuario,
            'fondo_inicial' => '100.00', 'estado' => 'abierto', 'abierta_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->post(route('cash.charge', $attention), ['metodo' => 'efectivo', 'recibido' => '300.00'])
            ->assertRedirect();

        $this->assertDatabaseCount('caja_pagos', 1);
        $this->assertDatabaseHas('caja_pagos', ['id_atencion' => $attention, 'importe' => '256.50', 'recibido' => '300.00', 'cambio' => '43.50', 'metodo' => 'efectivo']);
        $this->assertDatabaseHas('cuentas', ['id_atencion' => $attention, 'estado' => 'pagada']);
        $this->assertDatabaseHas('atenciones', ['id_atencion' => $attention, 'estado' => 'Cerrada']);
        $this->assertDatabaseHas('servicios_ofrecidos', ['id_atencion' => $attention, 'estado' => 'Pagado']);
        $this->get(route('cash.index'))->assertOk()->assertSee('Efectivo recibido')->assertSee('$300.00')->assertSee('Cambio entregado')->assertSee('$43.50')->assertSee('$356.50');
        $paymentId = Schema::getConnection()->table('caja_pagos')->value('id_pago');
        $this->get(route('cash.ticket', $paymentId))->assertOk()->assertSee('Servicio ofrecido')->assertSee('Desayuno completo')->assertSee('3 comensales');

        $this->post(route('cash.charge', $attention), ['metodo' => 'efectivo', 'recibido' => '300.00'])->assertNotFound();
        $this->assertDatabaseCount('caja_pagos', 1);
        $this->post(route('cash.close'), ['efectivo_contado' => '356.50'])->assertRedirect();
        $this->assertDatabaseHas('caja_turnos', ['id_turno_caja' => 1, 'efectivo_esperado' => '356.50', 'efectivo_contado' => '356.50', 'diferencia' => '0.00']);
    }

    public function test_cashier_can_open_close_and_reopen_a_shift_without_losing_reconciliation(): void
    {
        [, $cashier] = $this->seedOperation();
        $this->actingAs($cashier)->withSession(['current_site_id' => 1]);

        $this->post(route('cash.open'), ['fondo_inicial' => '2400.00'])
            ->assertRedirect()->assertSessionHas('status', 'Caja abierta correctamente.');
        $this->assertDatabaseHas('caja_turnos', ['id_turno_caja' => 1, 'id_sede' => 1, 'estado' => 'abierto', 'fondo_inicial' => '2400.00']);

        $this->post(route('cash.close'), ['efectivo_contado' => '2300.00'])
            ->assertRedirect()->assertSessionHas('status', 'Cierre de caja registrado.');
        $this->assertDatabaseHas('caja_turnos', [
            'id_turno_caja' => 1,
            'estado' => 'cerrado',
            'efectivo_esperado' => '2400.00',
            'efectivo_contado' => '2300.00',
            'diferencia' => '-100.00',
        ]);
        $this->assertNotNull(Schema::getConnection()->table('caja_turnos')->where('id_turno_caja', 1)->value('cerrada_at'));

        $this->post(route('cash.open'), ['fondo_inicial' => '500.00'])
            ->assertRedirect()->assertSessionHas('status', 'Caja abierta correctamente.');
        $this->assertDatabaseHas('caja_turnos', ['id_turno_caja' => 2, 'id_sede' => 1, 'estado' => 'abierto', 'fondo_inicial' => '500.00']);
    }

    public function test_closing_without_an_open_shift_returns_a_visible_validation_error(): void
    {
        [, $cashier] = $this->seedOperation();
        $this->actingAs($cashier)->withSession(['current_site_id' => 1])
            ->post(route('cash.close'), ['efectivo_contado' => '0.00'])
            ->assertRedirect()->assertSessionHasErrors('caja');
    }

    public function test_cash_history_is_limited_to_site_and_marks_surplus_and_shortage(): void
    {
        [, $cashier] = $this->seedOperation();
        $this->actingAs($cashier)->withSession(['current_site_id' => 1]);

        $this->post(route('cash.open'), ['fondo_inicial' => '2400.00'])->assertRedirect();
        $this->post(route('cash.close'), ['efectivo_contado' => '2300.00'])->assertRedirect();
        $this->post(route('cash.open'), ['fondo_inicial' => '500.00'])->assertRedirect();
        $this->post(route('cash.close'), ['efectivo_contado' => '550.00'])->assertRedirect();

        $this->get(route('cash.history'))->assertOk()
            ->assertSee('Historial de turnos')
            ->assertSee('Faltante −$100.00')
            ->assertSee('A favor +$50.00')
            ->assertSee('cash-difference loss')
            ->assertSee('cash-difference gain')
            ->assertSee('$2,400.00')
            ->assertSee('$2,300.00');
    }

    public function test_offered_service_requires_valid_attention_and_price(): void
    {
        [$waiter] = $this->seedOperation();
        $attention = $this->startAttention($waiter);
        $this->actingAs($waiter)->withSession(['current_site_id' => 1])
            ->post(route('waiter.offered-service.store', $attention), [
                'descripcion' => 'Desayuno', 'comensales' => 5, 'precio_unitario' => '0',
            ])->assertSessionHasErrors('precio_unitario');

        $this->assertDatabaseCount('servicios_ofrecidos', 0);
        $this->assertDatabaseCount('cuentas', 0);
    }

    public function test_service_cannot_be_finalized_twice_or_without_offer(): void
    {
        [$waiter] = $this->seedOperation();
        $attention = $this->startAttention($waiter);

        $this->actingAs($waiter)->withSession(['current_site_id' => 1])
            ->patch(route('waiter.offered-service.finalize', $attention))->assertStatus(422);
        $this->assertDatabaseCount('cuentas', 0);

        $this->post(route('waiter.offered-service.store', $attention), [
            'descripcion' => 'Comida', 'comensales' => 2, 'precio_unitario' => '120.00',
        ])->assertRedirect();
        $this->patch(route('waiter.offered-service.finalize', $attention))->assertRedirect();
        $this->patch(route('waiter.offered-service.finalize', $attention))->assertStatus(422);

        $this->assertDatabaseCount('cuentas', 1);
    }

    private function seedOperation(): array
    {
        $waiter = $this->createUser('Mesero', 6, 'mesero@example.test');
        $cashier = $this->createUser('Cajero', 8, 'cajero@example.test');
        Schema::getConnection()->table('zona_meseros')->insert(['id_zona' => 1, 'id_usuario_mesero' => $waiter->id_usuario, 'id_servicio' => 1, 'activo' => 1]);
        Schema::getConnection()->table('mesas')->insert(['id_mesa' => 1, 'id_zona' => 1, 'numero' => 4, 'capacidad' => 6, 'estado_fisico' => 'Disponible']);
        Schema::getConnection()->table('servicios')->insert([
            'id_servicio' => 1, 'id_sede' => 1, 'nombre' => 'Desayuno', 'fecha' => now()->toDateString(),
            'inicio_hora' => '08:00:00', 'fin_hora' => '11:00:00', 'estado' => 'En curso', 'estado_aprobacion' => 'Aprobado',
        ]);
        return [$waiter, $cashier];
    }

    private function startAttention(User $waiter): int
    {
        $this->actingAs($waiter)->withSession(['current_site_id' => 1])
            ->post(route('waiter.attention.store'), [
                'id_mesa' => 1, 'id_servicio' => 1, 'comensales' => 2,
            ])->assertRedirect();

        return (int) Schema::getConnection()->table('atenciones')->value('id_atencion');
    }

    private function createUser(string $role, int $roleId, string $email): User
    {
        Schema::getConnection()->table('roles')->insert(['id_rol' => $roleId, 'nombre' => $role]);
        $user = User::create(['nombre' => $role, 'apellido_p' => 'Prueba', 'correo' => $email, 'password' => Hash::make('Password1!')]);
        Schema::getConnection()->table('usuario_sede')->insert(['id_usuario' => $user->id_usuario, 'id_sede' => 1, 'id_rol' => $roleId]);

        return $user;
    }

    private function createSchema(): void
    {
        Schema::create('usuarios', function (Blueprint $t): void {
            $t->increments('id_usuario');
            $t->string('nombre')->nullable(); $t->string('apellido_p')->nullable(); $t->string('apellido_m')->nullable();
            $t->string('correo')->unique(); $t->string('password'); $t->rememberToken();
        });
        Schema::create('roles', function (Blueprint $t): void { $t->integer('id_rol')->primary(); $t->string('nombre'); });
        Schema::create('sedes', function (Blueprint $t): void { $t->increments('id_sede'); $t->string('nombre'); });
        Schema::create('usuario_sede', function (Blueprint $t): void { $t->increments('id_usuario_sede'); $t->integer('id_usuario'); $t->integer('id_sede'); $t->integer('id_rol'); });
        Schema::create('zonas', function (Blueprint $t): void { $t->increments('id_zona'); $t->integer('id_sede'); $t->string('nombre'); });
        Schema::create('zona_meseros', function (Blueprint $t): void { $t->integer('id_zona'); $t->integer('id_usuario_mesero'); $t->integer('id_servicio')->nullable(); $t->boolean('activo'); });
        Schema::create('mesas', function (Blueprint $t): void { $t->increments('id_mesa'); $t->integer('id_zona'); $t->integer('numero'); $t->integer('capacidad'); $t->string('estado_fisico'); });
        Schema::create('servicios', function (Blueprint $t): void { $t->increments('id_servicio'); $t->integer('id_sede'); $t->string('nombre'); $t->date('fecha'); $t->time('inicio_hora'); $t->time('fin_hora'); $t->string('estado'); $t->string('estado_aprobacion'); });
        Schema::create('atenciones', function (Blueprint $t): void { $t->increments('id_atencion'); $t->integer('id_servicio'); $t->integer('id_mesa'); $t->integer('id_usuario_mesero'); $t->string('estado'); $t->integer('comensales'); $t->time('inicio_hora')->nullable(); $t->time('fin_hora')->nullable(); });
        Schema::create('cuentas', function (Blueprint $t): void { $t->increments('id_cuenta'); $t->integer('id_atencion')->unique(); $t->decimal('subtotal', 12, 2); $t->decimal('total', 12, 2); $t->string('estado'); $t->dateTime('fecha_apertura'); $t->dateTime('fecha_cierre')->nullable(); });
        Schema::create('comandas', function (Blueprint $t): void { $t->increments('id_comanda'); $t->integer('id_atencion'); $t->string('estado'); });
        Schema::create('comanda_detalles', function (Blueprint $t): void { $t->increments('id_comanda_detalle'); $t->integer('id_comanda'); $t->integer('id_menu'); $t->integer('cantidad'); $t->decimal('precio_unitario', 12, 2); $t->string('estado')->nullable(); });
        Schema::create('menus', function (Blueprint $t): void { $t->increments('id_menu'); $t->integer('id_platillo'); });
        Schema::create('platillos', function (Blueprint $t): void { $t->increments('id_platillo'); $t->string('nombre'); });
        Schema::create('caja_turnos', function (Blueprint $t): void { $t->increments('id_turno_caja'); $t->integer('id_sede'); $t->integer('id_usuario_apertura'); $t->decimal('fondo_inicial', 12, 2); $t->decimal('efectivo_esperado', 12, 2)->nullable(); $t->decimal('efectivo_contado', 12, 2)->nullable(); $t->decimal('diferencia', 12, 2)->nullable(); $t->string('estado'); $t->dateTime('abierta_at'); $t->dateTime('cerrada_at')->nullable(); $t->timestamps(); });
        Schema::create('caja_pagos', function (Blueprint $t): void { $t->increments('id_pago'); $t->integer('id_turno_caja'); $t->integer('id_atencion')->unique(); $t->integer('id_usuario'); $t->integer('id_sede'); $t->string('metodo'); $t->decimal('importe', 12, 2); $t->decimal('recibido', 12, 2)->nullable(); $t->decimal('cambio', 12, 2); $t->string('estado'); $t->timestamps(); });

        Schema::getConnection()->table('sedes')->insert(['id_sede' => 1, 'nombre' => 'Sede prueba']);
        Schema::getConnection()->table('zonas')->insert(['id_zona' => 1, 'id_sede' => 1, 'nombre' => 'Comedor']);
        $migration = require database_path('migrations/2026_11_11_000002_create_servicios_ofrecidos_table.php');
        $migration->up();
    }
}
