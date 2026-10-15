<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->createOperationalSchema();
    }

    public function test_administrator_sees_complete_navigation_and_empty_dashboard(): void
    {
        $admin = $this->createUserWithRole('Administrador');

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Panel principal')
            ->assertSee('Usuarios y permisos')
            ->assertSee('Gestión de sedes')
            ->assertSee('No hay actividad reciente.')
            ->assertSee('No hay servicios programados.')
            ->assertSee('No hay datos disponibles.');
    }

    public function test_manager_does_not_see_admin_navigation_or_access_admin_routes(): void
    {
        $manager = $this->createUserWithRole('Gerente');

        $this->actingAs($manager)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Usuarios y permisos')
            ->assertDontSee('Gestión de sedes');

        $this->actingAs($manager)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_manager_cannot_access_inventory(): void
    {
        $manager = $this->createUserWithRole('Gerente');

        $this->actingAs($manager)->get(route('inventory.index'))
            ->assertForbidden();
    }

    private function createUserWithRole(string $roleName): User
    {
        $roleId = $roleName === 'Administrador' ? 1 : 2;
        Schema::getConnection()->table('roles')->insertOrIgnore(['id_rol' => $roleId, 'nombre' => $roleName]);
        $user = User::create(['nombre' => $roleName, 'apellido_p' => 'Prueba', 'correo' => strtolower($roleName).'@example.com', 'password' => Hash::make('Password1!')]);
        Schema::getConnection()->table('usuario_sede')->insert(['id_usuario' => $user->id_usuario, 'id_sede' => 1, 'id_rol' => $roleId]);

        return $user;
    }

    private function createOperationalSchema(): void
    {
        foreach (['usuarios', 'roles', 'sedes', 'usuario_sede', 'servicios', 'atenciones', 'entregas', 'incidencias', 'pedidos', 'recepciones'] as $table) {
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
        Schema::create('servicios', function (Blueprint $table): void {
            $table->increments('id_servicio');
            $table->integer('id_sede');
            $table->integer('id_tipo')->default(1);
            $table->string('nombre');
            $table->date('fecha')->nullable();
            $table->time('inicio_hora')->nullable();
            $table->time('fin_hora')->nullable();
            $table->integer('capacidad')->nullable();
            $table->string('estado')->nullable();
        });
        Schema::create('atenciones', function (Blueprint $table): void {
            $table->increments('id_atencion');
            $table->integer('id_servicio');
            $table->integer('id_mesa')->default(1);
            $table->string('estado')->nullable();
            $table->integer('id_grupo')->nullable();
            $table->integer('comensales')->nullable();
            $table->time('inicio_hora')->nullable();
            $table->time('fin_hora')->nullable();
        });
        Schema::create('entregas', function (Blueprint $table): void {
            $table->increments('id_entrega');
            $table->integer('id_atencion');
            $table->integer('id_menu')->default(1);
            $table->integer('cantidad');
            $table->dateTime('fecha_entrega')->nullable();
            $table->integer('id_usuario');
        });
        Schema::create('incidencias', function (Blueprint $table): void {
            $table->increments('id_incidencia');
            $table->integer('id_sede');
            $table->integer('id_usuario');
            $table->integer('id_recepcion_detalle')->nullable();
            $table->dateTime('fecha')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('estado')->nullable();
            $table->text('respuesta')->nullable();
            $table->dateTime('fecha_cierre')->nullable();
        });
        Schema::create('pedidos', function (Blueprint $table): void {
            $table->increments('id_pedido');
            $table->integer('id_sede');
        });
        Schema::create('recepciones', function (Blueprint $table): void {
            $table->increments('id_recepcion');
            $table->integer('id_pedido');
            $table->dateTime('fecha_recepcion')->nullable();
            $table->string('folio_documento')->nullable();
            $table->string('estado')->nullable();
        });
        Schema::getConnection()->table('sedes')->insert(['id_sede' => 1, 'nombre' => 'Sede de prueba', 'capacidad' => 100]);
    }
}
