<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MesaObservationsTest extends TestCase
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

    public function test_waiter_can_create_table_with_optional_observations(): void
    {
        $waiter = $this->createUser('Mesero', 6);
        $this->assignToZone($waiter);

        $this->actingAs($waiter)->withSession(['current_site_id' => 1])
            ->post(route('waiter.table.store'), [
                'id_zona' => 10,
                'numero' => 12,
                'capacidad' => 4,
                'estado_fisico' => 'Disponible',
                'observaciones' => 'bien',
            ])->assertRedirect();

        $this->assertDatabaseHas('mesas', [
            'id_zona' => 10,
            'numero' => 12,
            'observaciones' => 'bien',
        ]);
    }

    public function test_waiter_can_create_table_without_observations(): void
    {
        $waiter = $this->createUser('Mesero', 6);
        $this->assignToZone($waiter);

        $this->actingAs($waiter)->withSession(['current_site_id' => 1])
            ->post(route('waiter.table.store'), [
                'id_zona' => 10,
                'numero' => 13,
                'capacidad' => 2,
                'estado_fisico' => 'Disponible',
            ])->assertRedirect();

        $this->assertDatabaseHas('mesas', [
            'id_zona' => 10,
            'numero' => 13,
            'observaciones' => null,
        ]);
    }

    public function test_captain_can_update_table_observations(): void
    {
        $captain = $this->createUser('Capitan de meseros', 7);
        Schema::getConnection()->table('mesas')->insert([
            'id_mesa' => 20,
            'id_zona' => 10,
            'numero' => 2,
            'capacidad' => 4,
            'estado_fisico' => 'Disponible',
            'observaciones' => null,
        ]);

        $this->actingAs($captain)->withSession(['current_site_id' => 1])
            ->put(route('capitan.mesas.mesas.update', 20), [
                'id_zona' => 10,
                'numero' => 2,
                'capacidad' => 4,
                'estado_fisico' => 'Disponible',
                'observaciones' => 'Requiere silla infantil',
            ])->assertRedirect(route('capitan.mesas.index'));

        $this->assertDatabaseHas('mesas', [
            'id_mesa' => 20,
            'observaciones' => 'Requiere silla infantil',
        ]);
    }

    public function test_compatibility_migration_adds_missing_observations_column(): void
    {
        Schema::table('mesas', fn (Blueprint $table) => $table->dropColumn('observaciones'));

        $migration = require database_path('migrations/2026_11_11_000001_make_mesa_observaciones_text.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('mesas', 'observaciones'));
        $this->assertSame('text', Schema::getColumnType('mesas', 'observaciones'));
    }

    private function createUser(string $role, int $roleId): User
    {
        Schema::getConnection()->table('roles')->insert(['id_rol' => $roleId, 'nombre' => $role]);
        $user = User::create([
            'nombre' => $role,
            'apellido_p' => 'Prueba',
            'correo' => strtolower(str_replace(' ', '-', $role)).'@example.test',
            'password' => Hash::make('Password1!'),
        ]);
        Schema::getConnection()->table('usuario_sede')->insert([
            'id_usuario' => $user->id_usuario,
            'id_sede' => 1,
            'id_rol' => $roleId,
        ]);

        return $user;
    }

    private function assignToZone(User $waiter): void
    {
        Schema::getConnection()->table('zona_meseros')->insert([
            'id_zona' => 10,
            'id_usuario_mesero' => $waiter->id_usuario,
            'activo' => 1,
        ]);
    }

    private function createSchema(): void
    {
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
        });
        Schema::create('usuario_sede', function (Blueprint $table): void {
            $table->increments('id_usuario_sede');
            $table->integer('id_usuario');
            $table->integer('id_sede');
            $table->integer('id_rol');
        });
        Schema::create('zonas', function (Blueprint $table): void {
            $table->integer('id_zona')->primary();
            $table->integer('id_sede');
            $table->string('nombre');
        });
        Schema::create('zona_meseros', function (Blueprint $table): void {
            $table->integer('id_zona');
            $table->integer('id_usuario_mesero');
            $table->boolean('activo');
        });
        Schema::create('mesas', function (Blueprint $table): void {
            $table->increments('id_mesa');
            $table->integer('id_zona');
            $table->integer('numero');
            $table->integer('capacidad');
            $table->string('estado_fisico');
            $table->text('observaciones')->nullable();
        });

        Schema::getConnection()->table('sedes')->insert(['id_sede' => 1, 'nombre' => 'Sede de prueba']);
        Schema::getConnection()->table('zonas')->insert(['id_zona' => 10, 'id_sede' => 1, 'nombre' => 'Comedor']);
    }
}
