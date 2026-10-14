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

    private function createUser(string $role, int $roleId, int $siteId): User
    {
        Schema::getConnection()->table('roles')->insertOrIgnore(['id_rol' => $roleId, 'nombre' => $role]);
        $user = User::create(['nombre' => $role, 'apellido_p' => 'Prueba', 'correo' => strtolower($role).'@example.com', 'password' => Hash::make('Password1!')]);
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
