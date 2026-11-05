<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class CashModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->createCashSchema();
    }

    public function test_cashier_can_open_register_and_charge_a_generated_account(): void
    {
        $cashier = $this->createUser('Cajero', 8);
        $this->seedBillableAttention();

        $this->actingAs($cashier)->get(route('cash.index'))
            ->assertOk()->assertSee('Cuentas pendientes');
        $account = DB::table('cuentas')->first();
        $this->assertNotNull($account);

        $this->actingAs($cashier)->post(route('cash.registers.open'), ['fondo_inicial' => '100.00'])
            ->assertRedirect();
        $this->actingAs($cashier)->post(route('cash.payments.store', $account->id_cuenta), [
            'metodo_pago' => 'efectivo',
            'monto_recibido' => '500.00',
        ])->assertRedirect();

        $this->assertDatabaseHas('pagos', ['id_cuenta' => $account->id_cuenta, 'monto' => '425.00', 'metodo_pago' => 'efectivo', 'estado' => 'confirmado']);
        $this->assertDatabaseHas('cuentas', ['id_cuenta' => $account->id_cuenta, 'estado' => 'pagada']);
        $this->assertDatabaseHas('cortes_caja', ['total_efectivo' => '425.00', 'total_sistema' => '425.00', 'efectivo_esperado' => '525.00']);
    }

    public function test_paid_account_cannot_be_charged_twice(): void
    {
        $cashier = $this->createUser('Cajero', 8);
        $this->seedBillableAttention();
        $this->actingAs($cashier)->get(route('cash.index'));
        $account = DB::table('cuentas')->first();
        $this->actingAs($cashier)->post(route('cash.registers.open'), ['fondo_inicial' => '0.00']);
        $this->actingAs($cashier)->post(route('cash.payments.store', $account->id_cuenta), ['metodo_pago' => 'tarjeta', 'monto_recibido' => '425.00']);

        $this->actingAs($cashier)->from(route('cash.accounts.show', $account->id_cuenta))
            ->post(route('cash.payments.store', $account->id_cuenta), ['metodo_pago' => 'tarjeta', 'monto_recibido' => '425.00'])
            ->assertSessionHasErrors('cuenta');
        $this->assertDatabaseCount('pagos', 1);
    }

    public function test_duplicate_open_is_rejected_and_previous_register_is_preserved(): void
    {
        $cashier = $this->createUser('Cajero', 8);

        $this->actingAs($cashier)->post(route('cash.registers.open'), ['fondo_inicial' => '500.00'])->assertRedirect();
        $this->actingAs($cashier)->from(route('cash.index'))
            ->post(route('cash.registers.open'), ['fondo_inicial' => '700.00'])
            ->assertSessionHasErrors('caja');

        $this->assertDatabaseCount('cortes_caja', 1);
        $this->assertDatabaseHas('cortes_caja', ['fondo_inicial' => '500.00', 'estado' => 'abierto']);
    }

    public function test_insufficient_cash_is_rejected_without_creating_payment(): void
    {
        $cashier = $this->createUser('Cajero', 8);
        $this->seedBillableAttention();
        $this->actingAs($cashier)->get(route('cash.index'));
        $account = DB::table('cuentas')->first();
        $this->actingAs($cashier)->post(route('cash.registers.open'), ['fondo_inicial' => '500.00']);

        $this->actingAs($cashier)->from(route('cash.accounts.show', $account->id_cuenta))
            ->post(route('cash.payments.store', $account->id_cuenta), ['metodo_pago' => 'efectivo', 'monto_recibido' => '424.99'])
            ->assertSessionHasErrors('monto_recibido');

        $this->assertDatabaseCount('pagos', 0);
        $this->assertDatabaseHas('cuentas', ['id_cuenta' => $account->id_cuenta, 'estado' => 'abierta']);
    }

    public function test_transfer_requires_reference_and_is_recorded(): void
    {
        $cashier = $this->createUser('Cajero', 8);
        $this->seedBillableAttention();
        $this->actingAs($cashier)->get(route('cash.index'));
        $account = DB::table('cuentas')->first();
        $this->actingAs($cashier)->post(route('cash.registers.open'), ['fondo_inicial' => '0.00']);

        $this->actingAs($cashier)->from(route('cash.accounts.show', $account->id_cuenta))
            ->post(route('cash.payments.store', $account->id_cuenta), ['metodo_pago' => 'transferencia', 'monto_recibido' => '425.00'])
            ->assertSessionHasErrors('referencia');

        $this->actingAs($cashier)->post(route('cash.payments.store', $account->id_cuenta), [
            'metodo_pago' => 'transferencia', 'monto_recibido' => '425.00', 'referencia' => 'TR-001',
        ])->assertRedirect();
        $this->assertDatabaseHas('pagos', ['metodo_pago' => 'transferencia', 'referencia' => 'TR-001', 'estado' => 'confirmado']);
    }

    public function test_dashboard_statistics_reflect_current_shift_after_payment(): void
    {
        $cashier = $this->createUser('Cajero', 8);
        $this->seedBillableAttention();
        $this->actingAs($cashier)->get(route('cash.index'));
        $account = DB::table('cuentas')->first();
        $this->actingAs($cashier)->post(route('cash.registers.open'), ['fondo_inicial' => '500.00']);
        $this->actingAs($cashier)->post(route('cash.payments.store', $account->id_cuenta), ['metodo_pago' => 'efectivo', 'monto_recibido' => '500.00']);

        $response = $this->actingAs($cashier)->get(route('cash.index'));
        $response->assertOk()
            ->assertViewHas('pendingCount', 0)
            ->assertViewHas('paidCount', 1)
            ->assertViewHas('revenue', fn (array $revenue): bool => $revenue['total'] === 42500
                && $revenue['efectivo'] === 42500
                && $revenue['tarjeta'] === 0
                && $revenue['transferencia'] === 0);
    }

    public function test_account_from_another_site_is_not_visible(): void
    {
        $cashier = $this->createUser('Cajero', 8);
        $this->seedBillableAttention();
        DB::table('sedes')->insert(['id_sede' => 2, 'nombre' => 'Otra sede']);
        DB::table('servicios')->insert(['id_servicio' => 2, 'id_sede' => 2, 'nombre' => 'Comida', 'fecha' => '2026-11-05', 'estado' => 'En curso', 'estado_aprobacion' => 'Aprobado']);
        DB::table('atenciones')->insert(['id_atencion' => 2, 'id_servicio' => 2, 'id_mesa' => 1, 'estado' => 'Cerrada', 'id_grupo' => null, 'comensales' => 1]);
        DB::table('cuentas')->insert(['id_cuenta' => 2, 'id_atencion' => 2, 'subtotal' => '10.00', 'descuento' => '0.00', 'total' => '10.00', 'fecha_apertura' => now(), 'estado' => 'abierta']);

        $this->actingAs($cashier)->get(route('cash.accounts.show', 2))->assertNotFound();
    }

    public function test_cash_movements_and_close_calculate_expected_cash(): void
    {
        $cashier = $this->createUser('Cajero', 8);
        $this->seedBillableAttention();
        $this->actingAs($cashier)->get(route('cash.index'));
        $account = DB::table('cuentas')->first();
        $this->actingAs($cashier)->post(route('cash.registers.open'), ['fondo_inicial' => '500.00']);
        $this->actingAs($cashier)->post(route('cash.payments.store', $account->id_cuenta), ['metodo_pago' => 'efectivo', 'monto_recibido' => '500.00']);
        $this->actingAs($cashier)->post(route('cash.movements.store'), ['tipo' => 'entrada', 'monto' => '25.00', 'concepto' => 'Ajuste autorizado']);
        $this->actingAs($cashier)->post(route('cash.movements.store'), ['tipo' => 'salida', 'monto' => '25.00', 'concepto' => 'Salida autorizada']);

        $this->assertDatabaseHas('cortes_caja', ['efectivo_esperado' => '925.00']);
        $this->assertDatabaseCount('movimientos_caja', 2);
        $this->actingAs($cashier)->post(route('cash.registers.close'), ['efectivo_contado' => '925.00'])->assertRedirect();
        $this->assertDatabaseHas('cortes_caja', ['estado' => 'cerrado', 'efectivo_contado' => '925.00', 'diferencia' => '0.00']);
    }

    public function test_non_cashier_cannot_access_cash_module(): void
    {
        $kitchenUser = $this->createUser('Personal de cocina', 2);

        $this->actingAs($kitchenUser)->get(route('cash.index'))->assertForbidden();
    }

    private function createUser(string $role, int $roleId): User
    {
        DB::table('roles')->insert(['id_rol' => $roleId, 'nombre' => $role]);
        $user = User::create(['nombre' => $role, 'apellido_p' => 'Prueba', 'correo' => $roleId.'@example.com', 'password' => Hash::make('Password1!')]);
        DB::table('usuario_sede')->insert(['id_usuario' => $user->id_usuario, 'id_sede' => 1, 'id_rol' => $roleId]);

        return $user;
    }

    private function seedBillableAttention(): void
    {
        DB::table('servicios')->insert(['id_servicio' => 1, 'id_sede' => 1, 'nombre' => 'Desayuno', 'fecha' => '2026-11-05', 'estado' => 'En curso', 'estado_aprobacion' => 'Aprobado']);
        DB::table('zonas')->insert(['id_zona' => 1, 'id_sede' => 1, 'nombre' => 'Salón']);
        DB::table('mesas')->insert(['id_mesa' => 1, 'id_zona' => 1, 'numero' => 7, 'capacidad' => 8, 'estado_fisico' => 'Disponible']);
        DB::table('grupos')->insert(['id_grupo' => 1, 'id_servicio' => 1, 'nombre' => 'Grupo A', 'inicio_hora' => '08:00', 'fin_hora' => '09:00', 'comensales_pre' => 2]);
        DB::table('atenciones')->insert(['id_atencion' => 1, 'id_servicio' => 1, 'id_mesa' => 1, 'estado' => 'Cerrada', 'id_grupo' => 1, 'comensales' => 2]);
        DB::table('comandas')->insert(['id_comanda' => 1, 'id_atencion' => 1, 'fecha_apertura' => '2026-11-05 08:00:00', 'fecha_cierre' => '2026-11-05 09:00:00', 'estado' => 'Entregada']);
        DB::table('platillos')->insert(['id_platillo' => 1, 'id_sede' => 1, 'nombre' => 'Desayuno de prueba']);
        DB::table('menus')->insert(['id_menu' => 1, 'id_servicio' => 1, 'id_platillo' => 1, 'precio_venta' => '212.50', 'publicado' => 1, 'disponible' => 1]);
        DB::table('comanda_detalles')->insert(['id_comanda_detalle' => 1, 'id_comanda' => 1, 'id_menu' => 1, 'cantidad' => 2, 'precio_unitario' => '212.50', 'estado' => 'entregado']);
    }

    private function createCashSchema(): void
    {
        foreach (['movimientos_caja', 'pagos', 'cuentas', 'cortes_caja', 'comanda_detalles', 'comandas', 'menus', 'platillos', 'grupos', 'atenciones', 'mesas', 'zonas', 'servicios', 'usuario_sede', 'roles', 'usuarios', 'sedes'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('usuarios', function (Blueprint $table): void { $table->increments('id_usuario'); $table->string('nombre'); $table->string('apellido_p')->nullable(); $table->string('apellido_m')->nullable(); $table->string('correo')->unique(); $table->string('password'); $table->rememberToken(); });
        Schema::create('roles', function (Blueprint $table): void { $table->integer('id_rol')->primary(); $table->string('nombre'); });
        Schema::create('sedes', function (Blueprint $table): void { $table->increments('id_sede'); $table->string('nombre'); });
        Schema::create('usuario_sede', function (Blueprint $table): void { $table->increments('id_usuario_sede'); $table->integer('id_usuario'); $table->integer('id_sede'); $table->integer('id_rol'); });
        Schema::create('servicios', function (Blueprint $table): void { $table->increments('id_servicio'); $table->integer('id_sede'); $table->string('nombre'); $table->date('fecha')->nullable(); $table->string('estado')->nullable(); $table->string('estado_aprobacion')->nullable(); });
        Schema::create('zonas', function (Blueprint $table): void { $table->increments('id_zona'); $table->integer('id_sede'); $table->string('nombre'); });
        Schema::create('mesas', function (Blueprint $table): void { $table->increments('id_mesa'); $table->integer('id_zona'); $table->integer('numero'); $table->integer('capacidad')->nullable(); $table->string('estado_fisico')->nullable(); });
        Schema::create('grupos', function (Blueprint $table): void { $table->increments('id_grupo'); $table->integer('id_servicio'); $table->string('nombre'); $table->time('inicio_hora')->nullable(); $table->time('fin_hora')->nullable(); $table->integer('comensales_pre')->nullable(); });
        Schema::create('atenciones', function (Blueprint $table): void { $table->increments('id_atencion'); $table->integer('id_servicio'); $table->integer('id_mesa'); $table->string('estado')->nullable(); $table->integer('id_grupo')->nullable(); $table->integer('comensales')->nullable(); });
        Schema::create('comandas', function (Blueprint $table): void { $table->increments('id_comanda'); $table->integer('id_atencion'); $table->dateTime('fecha_apertura')->nullable(); $table->dateTime('fecha_cierre')->nullable(); $table->string('estado')->nullable(); });
        Schema::create('platillos', function (Blueprint $table): void { $table->increments('id_platillo'); $table->integer('id_sede'); $table->string('nombre'); });
        Schema::create('menus', function (Blueprint $table): void { $table->increments('id_menu'); $table->integer('id_servicio'); $table->integer('id_platillo'); $table->decimal('precio_venta', 12, 2); $table->boolean('publicado'); $table->boolean('disponible'); });
        Schema::create('comanda_detalles', function (Blueprint $table): void { $table->increments('id_comanda_detalle'); $table->integer('id_comanda'); $table->integer('id_menu'); $table->integer('cantidad'); $table->decimal('precio_unitario', 12, 2)->nullable(); $table->string('estado')->nullable(); });
        Schema::create('cuentas', function (Blueprint $table): void { $table->increments('id_cuenta'); $table->integer('id_atencion'); $table->decimal('subtotal', 12, 2); $table->decimal('descuento', 12, 2); $table->decimal('total', 12, 2); $table->dateTime('fecha_apertura'); $table->dateTime('fecha_cierre')->nullable(); $table->string('estado'); $table->unique('id_atencion'); });
        Schema::create('cortes_caja', function (Blueprint $table): void { $table->increments('id_corte_caja'); $table->integer('id_sede'); $table->integer('id_usuario_cajero'); $table->dateTime('fecha_apertura'); $table->dateTime('fecha_cierre')->nullable(); $table->decimal('fondo_inicial', 12, 2); $table->decimal('total_efectivo', 12, 2)->default(0); $table->decimal('total_tarjeta', 12, 2)->default(0); $table->decimal('total_transferencia', 12, 2)->default(0); $table->decimal('total_otros', 12, 2)->default(0); $table->decimal('total_sistema', 12, 2)->default(0); $table->decimal('efectivo_esperado', 12, 2)->default(0); $table->decimal('efectivo_contado', 12, 2)->nullable(); $table->decimal('diferencia', 12, 2)->nullable(); $table->string('estado'); $table->text('observaciones')->nullable(); });
        Schema::create('pagos', function (Blueprint $table): void { $table->increments('id_pago'); $table->integer('id_cuenta'); $table->integer('id_usuario'); $table->integer('id_corte_caja')->nullable(); $table->string('metodo_pago'); $table->decimal('monto', 12, 2); $table->dateTime('fecha_pago'); $table->string('referencia')->nullable(); $table->string('estado'); });
        Schema::create('movimientos_caja', function (Blueprint $table): void { $table->increments('id_movimiento_caja'); $table->integer('id_corte_caja'); $table->integer('id_usuario'); $table->string('tipo'); $table->decimal('monto', 12, 2); $table->string('concepto'); $table->string('referencia')->nullable(); $table->dateTime('fecha'); });
        DB::table('sedes')->insert(['id_sede' => 1, 'nombre' => 'Sede de prueba']);
    }
}
