<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function refreshDatabase(): void
    {
        // Esta suite construye su propio esquema; nunca ejecuta migraciones MySQL.
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->createAccessSchema();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_administrator_can_manage_users_sites_and_operational_modules(): void
    {
        $admin = $this->createUser('Administrador', 1, 1);

        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.sites.index'))->assertOk();
        $this->actingAs($admin)->get(route('inventory.index'))->assertOk();
    }

    public function test_manager_cannot_access_global_administration(): void
    {
        $manager = $this->createUser('Gerente', 2, 1);

        $this->actingAs($manager)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('admin.sites.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('admin.settings'))->assertForbidden();
    }

    public function test_manager_only_sees_assigned_sites(): void
    {
        $manager = $this->createUser('Gerente', 2, 1);

        $this->actingAs($manager)->get(route('sites.select'))
            ->assertOk()
            ->assertSee('Sede Norte')
            ->assertDontSee('Sede Sur');
    }

    public function test_public_registration_routes_are_not_available(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'nombre' => 'Nueva',
            'apellido_p' => 'Cuenta',
            'apellido_m' => '',
            'correo' => 'nueva@example.com',
            'password' => 'Password1!',
        ])->assertNotFound();

        $this->assertDatabaseMissing('usuarios', ['correo' => 'nueva@example.com']);
    }

    public function test_administrator_can_promote_and_demote_manager(): void
    {
        $admin = $this->createUser('Administrador', 1, 1);
        $manager = $this->createUser('Gerente', 2, 1);

        $this->actingAs($admin)->patch(route('admin.users.promote', $manager))->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('usuario_sede', ['id_usuario' => $manager->id_usuario, 'id_rol' => 1]);

        $this->actingAs($admin)->patch(route('admin.users.demote', $manager))->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('usuario_sede', ['id_usuario' => $manager->id_usuario, 'id_rol' => 2]);
    }

    public function test_manager_cannot_call_role_change_route_directly(): void
    {
        $manager = $this->createUser('Gerente', 2, 1);
        $otherManager = $this->createUser('Gerente', 2, 1);

        $this->actingAs($manager)->patch(route('admin.users.promote', $otherManager))->assertForbidden();
    }

    public function test_administrator_cannot_modify_or_delete_super_administrator(): void
    {
        $admin = $this->createUser('Administrador', 1, 1);
        $superAdmin = $this->createUser('Super Administrador', 3, 1);

        $this->actingAs($admin)->get(route('admin.users.edit', $superAdmin))->assertForbidden();
        $this->actingAs($admin)->delete(route('admin.users.destroy', $superAdmin))->assertForbidden();
        $this->actingAs($admin)->patch(route('admin.users.demote', $superAdmin))->assertForbidden();
        $this->assertDatabaseHas('usuarios', ['id_usuario' => $superAdmin->id_usuario]);
    }

    public function test_super_administrator_cannot_create_another_super_administrator_from_ui(): void
    {
        $superAdmin = $this->createUser('Super Administrador', 3, 1);

        $this->actingAs($superAdmin)->post(route('admin.users.store'), [
            'nombre' => 'Otro',
            'apellido_p' => 'Super',
            'correo' => 'otro-super@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'id_rol' => 3,
            'sites' => [1],
        ])->assertSessionHasErrors('id_rol');

        $this->assertDatabaseMissing('usuarios', ['correo' => 'otro-super@example.com']);
    }

    public function test_super_administrator_cannot_enter_operational_routes(): void
    {
        $superAdmin = $this->createUser('Super Administrador', 3, 1);

        $this->actingAs($superAdmin)->get(route('inventory.index'))->assertForbidden();
        $this->actingAs($superAdmin)->get(route('services.index'))->assertForbidden();
        $this->actingAs($superAdmin)->get(route('cash.index'))->assertForbidden();
        $this->actingAs($superAdmin)->get(route('kitchen.index'))->assertForbidden();
    }

    public function test_reception_orders_are_read_only_and_scoped_to_active_site(): void
    {
        $this->createOrderSchema();
        $user = $this->createUser('Recepcion de insumos', 10, 1);
        $this->actingAs($user)->withSession(['current_site_id' => 1])->get('/recepciones')
            ->assertOk()->assertSee('Proveedor Norte')->assertDontSee('Proveedor Sur');
        $this->get('/recepciones/pedidos/1')->assertOk()->assertSee('Arroz prueba')->assertSee('Sacos sellados');
        $this->get('/recepciones/pedidos/2')->assertNotFound();
        $this->get('/pedidos')->assertForbidden();
        $this->post('/recepciones/pedidos/1')->assertStatus(405);
        $this->assertDatabaseCount('pedidos', 2);
    }

    public function test_reception_role_in_another_site_does_not_grant_access(): void
    {
        $user = $this->createUser('Recepcion de insumos', 10, 1);
        Schema::getConnection()->table('roles')->insert(['id_rol' => 11, 'nombre' => 'Mesero']);
        Schema::getConnection()->table('usuario_sede')->insert(['id_usuario' => $user->id_usuario, 'id_sede' => 2, 'id_rol' => 11]);
        $this->actingAs($user)->withSession(['current_site_id' => 2])->get('/recepciones')->assertForbidden();
        $this->get('/recepciones/pedidos/1')->assertForbidden();
    }

    public function test_reception_order_search_and_filters(): void
    {
        $this->createOrderSchema();
        $this->actingAs($this->createUser('Recepcion de insumos', 10, 1));
        foreach (['q=1', 'q=Proveedor', 'estado=Programado&fecha_entrega=2026-11-01'] as $query) {
            $this->get('/recepciones?'.$query)->assertOk()->assertSee('Proveedor Norte')->assertDontSee('Proveedor Sur');
        }
        $this->get('/recepciones?q=Inexistente')->assertOk()->assertSee('No hay pedidos con estos criterios');
        $this->get('/recepciones?fecha_entrega=2026-11-02')->assertOk()->assertDontSee('Proveedor Norte');
        $this->get('/recepciones?estado=Cancelado')->assertOk()->assertDontSee('Proveedor Norte');
    }

    public function test_receiving_can_consult_inventory_but_cannot_create_initial_stock(): void
    {
        $user = $this->createUser('Recepcion de insumos', 10, 1);
        $this->actingAs($user)->get('/inventario')->assertOk()
            ->assertViewHas('canRegisterIngredients', false)
            ->assertDontSee('id="inventory-catalog-dialog"', false);
        $this->post('/inventario/catalogo', ['nombre' => 'Entrada prohibida', 'cantidad_inicial' => 50])->assertForbidden();
        $this->post('/inventario/asignaciones', [])->assertForbidden();
        $this->post('/inventario/movimientos', ['tipo' => 'entrada'])->assertForbidden();
    }

    public function test_initial_stock_permission_requires_kitchen_chief_at_active_site(): void
    {
        $user = $this->createUser('Jefe de cocina', 12, 1);
        Schema::getConnection()->table('roles')->insert(['id_rol' => 10, 'nombre' => 'Recepcion de insumos']);
        Schema::getConnection()->table('usuario_sede')->insert(['id_usuario' => $user->id_usuario, 'id_sede' => 2, 'id_rol' => 10]);
        $this->actingAs($user)->withSession(['current_site_id' => 1])->get('/inventario')->assertOk()
            ->assertViewHas('canRegisterIngredients', true);
        // Sin esquema de inventario, el autorizado llega al control de disponibilidad (503), no al 403.
        $this->post('/inventario/catalogo', [])->assertStatus(503);
        $this->withSession(['current_site_id' => 2])->get('/inventario')->assertOk()
            ->assertViewHas('canRegisterIngredients', false);
        $this->post('/inventario/catalogo', ['cantidad_inicial' => 50])->assertForbidden();
    }

    public function test_warehouse_chief_does_not_gain_initial_stock_permission(): void
    {
        $this->actingAs($this->createUser('Jefe de almacen', 13, 1))
            ->get('/inventario')->assertOk()->assertViewHas('canRegisterIngredients', false);
        $this->post('/inventario/catalogo', [])->assertForbidden();
    }

    protected function createOrderSchema(): void
    {
        $db = Schema::getConnection();
        $this->assertSame('sqlite', $db->getDriverName());
        $this->assertSame(':memory:', $db->getDatabaseName());
        Schema::create('proveedores', function (Blueprint $table) {
            $table->increments('id_proveedor'); $table->string('nombre');
        });
        Schema::create('pedidos', function (Blueprint $table) {
            $table->increments('id_pedido'); $table->integer('id_sede'); $table->integer('id_proveedor');
            $table->date('fecha_pedido')->nullable(); $table->date('fecha_entrega')->nullable();
            $table->string('estado')->nullable(); $table->text('observaciones')->nullable();
        });
        Schema::create('insumos', function (Blueprint $table) {
            $table->increments('id_insumo'); $table->string('nombre');
        });
        Schema::create('detalle_pedidos', function (Blueprint $table) {
            $table->increments('id_detalle_pedido'); $table->integer('id_pedido'); $table->integer('id_insumo');
            $table->decimal('cantidad', 12, 3)->nullable(); $table->text('especificaciones')->nullable();
        });
        $db->table('proveedores')->insert([['id_proveedor' => 1, 'nombre' => 'Proveedor Norte'], ['id_proveedor' => 2, 'nombre' => 'Proveedor Sur']]);
        foreach ([1, 2] as $id) {
            $db->table('pedidos')->insert(['id_pedido' => $id, 'id_sede' => $id, 'id_proveedor' => $id, 'fecha_entrega' => '2026-11-01', 'estado' => 'Programado']);
        }
        $db->table('insumos')->insert(['id_insumo' => 1, 'nombre' => 'Arroz prueba']);
        $db->table('detalle_pedidos')->insert(['id_pedido' => 1, 'id_insumo' => 1, 'cantidad' => 12.5, 'especificaciones' => 'Sacos sellados']);
    }

    protected function createUser(string $role, int $roleId, int $siteId): User
    {
        static $sequence = 0;
        $sequence++;
        Schema::getConnection()->table('roles')->insertOrIgnore(['id_rol' => $roleId, 'nombre' => $role]);
        $user = User::create(['nombre' => $role, 'apellido_p' => 'Prueba', 'correo' => strtolower($role).$sequence.'@example.com', 'password' => Hash::make('Password1!')]);
        Schema::getConnection()->table('usuario_sede')->insert(['id_usuario' => $user->id_usuario, 'id_sede' => $siteId, 'id_rol' => $roleId]);

        return $user;
    }

    private function createAccessSchema(): void
    {
        foreach (['usuarios', 'roles', 'sedes', 'usuario_sede'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('usuarios', function (Blueprint $table): void {
            $table->increments('id_usuario');
            $table->string('nombre')->nullable();
            $table->string('apellido_p')->nullable();
            $table->string('apellido_m')->nullable();
            $table->string('correo')->unique();
            $table->string('password');
            $table->rememberToken();
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->integer('id_rol')->primary();
            $table->string('nombre');
        });
        Schema::create('sedes', function (Blueprint $table): void {
            $table->increments('id_sede');
            $table->string('nombre');
            $table->string('direccion')->nullable();
            $table->string('telefono')->nullable();
            $table->integer('capacidad')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('lema')->nullable();
            $table->string('color')->nullable();
            $table->integer('id_tipo')->nullable();
        });
        Schema::create('usuario_sede', function (Blueprint $table): void {
            $table->increments('id_usuario_sede');
            $table->integer('id_usuario');
            $table->integer('id_sede');
            $table->integer('id_rol');
        });
        Schema::getConnection()->table('sedes')->insert([
            ['id_sede' => 1, 'nombre' => 'Sede Norte', 'capacidad' => 100],
            ['id_sede' => 2, 'nombre' => 'Sede Sur', 'capacidad' => 80],
        ]);
    }
}
