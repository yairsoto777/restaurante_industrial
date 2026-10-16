<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManagerEmployeeFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->createEmployeeSchema();
        $this->seedCatalogs();
    }

    public function test_employee_routes_require_a_manager(): void
    {
        $this->get(route('manager.employees.index'))->assertRedirect(route('login'));

        $employeeUser = $this->createUser('Juan', 'Pérez', 'juan@example.com');
        $this->assignRole($employeeUser, 1, 6);
        $this->createEmployeeRecord($employeeUser, 1, 'Mesero');

        $this->actingAs($employeeUser)
            ->get(route('manager.employees.index'))
            ->assertForbidden();
    }

    public function test_manager_can_create_a_mesero_for_their_site_and_employee_can_log_in(): void
    {
        $manager = $this->createUser('Gustavo', 'Cruz', 'gerente@example.com');
        $this->assignRole($manager, 1, 9);

        $this->actingAs($manager)
            ->get(route('manager.employees.create'))
            ->assertOk()
            ->assertSee('Personal de cocina')
            ->assertSee('Jefe de cocina')
            ->assertSee('Recepción de insumos')
            ->assertSee('Jefe de almacén')
            ->assertSee('Mesero')
            ->assertSee('Capitán de meseros')
            ->assertSee('Cajero')
            ->assertDontSee('<option value="9"', false)
            ->assertDontSee('<option value="10"', false)
            ->assertSee('La sede se determina con tu contexto actual');

        $this->actingAs($manager)
            ->withSession(['current_site_id' => 1])
            ->post(route('manager.employees.store'), [
                ...$this->validEmployeePayload(),
                'id_sede' => 2,
            ])->assertRedirect(route('manager.employees.index'))
            ->assertSessionHas('status');

        $employeeUser = User::query()->where('correo', 'juan@gmail.com')->firstOrFail();
        $this->assertSame('Juan', $employeeUser->nombre);
        $this->assertSame('Pérez', $employeeUser->apellido_p);
        $this->assertSame('López', $employeeUser->apellido_m);
        $this->assertTrue(Hash::check('Temporal1!', $employeeUser->password));
        $this->assertDatabaseHas('usuario_sede', [
            'id_usuario' => $employeeUser->id_usuario,
            'id_sede' => 1,
            'id_rol' => 6,
        ]);
        $this->assertDatabaseMissing('usuario_sede', [
            'id_usuario' => $employeeUser->id_usuario,
            'id_sede' => 2,
        ]);

        $positionId = DB::table('puestos')->where('nombre', 'Mesero de comedor')->value('id_puesto');
        $this->assertNotNull($positionId);
        $this->assertDatabaseHas('empleados', [
            'nombre' => 'Juan Pérez López',
            'id_sede' => 1,
            'id_puesto' => $positionId,
            'id_usuario' => $employeeUser->id_usuario,
        ]);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->post(route('login.store'), [
            'correo' => 'juan@gmail.com',
            'password' => 'Temporal1!',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($employeeUser);
        $employeeUser->unsetRelation('roles');
        $this->assertTrue($employeeUser->hasRole('Mesero'));
        $this->assertSame([1], $employeeUser->sites()->pluck('sedes.id_sede')->map(fn ($id): int => (int) $id)->all());
        $this->assertSame(
            'Mesero de comedor',
            DB::table('empleados as e')
                ->join('puestos as p', 'p.id_puesto', '=', 'e.id_puesto')
                ->where('e.id_usuario', $employeeUser->id_usuario)
                ->value('p.nombre'),
        );
    }

    public function test_manager_cannot_assign_user_manager_or_super_administrator_roles(): void
    {
        $manager = $this->createUser('Gustavo', 'Cruz', 'gerente@example.com');
        $this->assignRole($manager, 1, 9);

        foreach ([1, 9, 10] as $roleId) {
            $this->actingAs($manager)
                ->withSession(['current_site_id' => 1])
                ->post(route('manager.employees.store'), $this->validEmployeePayload([
                    'correo' => "rol-{$roleId}@example.com",
                    'id_rol' => $roleId,
                ]))
                ->assertSessionHasErrors('id_rol');
        }

        $this->assertDatabaseMissing('usuarios', ['correo' => 'rol-1@example.com']);
        $this->assertDatabaseMissing('usuarios', ['correo' => 'rol-9@example.com']);
        $this->assertDatabaseMissing('usuarios', ['correo' => 'rol-10@example.com']);
        $this->assertDatabaseCount('empleados', 0);
    }

    public function test_manager_only_lists_and_manages_employees_from_their_site(): void
    {
        $manager = $this->createUser('Gerente', 'Centro', 'gerente@example.com');
        $this->assignRole($manager, 1, 9);

        $ownUser = $this->createUser('Ana', 'Centro', 'ana.centro@example.com');
        $this->assignRole($ownUser, 1, 6);
        $ownEmployee = $this->createEmployeeRecord($ownUser, 1, 'Mesero');

        $foreignUser = $this->createUser('Pedro', 'Norte', 'pedro.norte@example.com');
        $this->assignRole($foreignUser, 2, 2);
        $foreignEmployee = $this->createEmployeeRecord($foreignUser, 2, 'Auxiliar de cocina');

        $this->actingAs($manager)
            ->withSession(['current_site_id' => 1])
            ->get(route('manager.employees.index'))
            ->assertOk()
            ->assertSee('Ana Centro')
            ->assertSee('ana.centro@example.com')
            ->assertDontSee('Pedro Norte')
            ->assertDontSee('pedro.norte@example.com');

        $this->actingAs($manager)
            ->withSession(['current_site_id' => 1])
            ->get(route('manager.employees.edit', $foreignEmployee))
            ->assertForbidden();

        $this->actingAs($manager)
            ->withSession(['current_site_id' => 1])
            ->put(route('manager.employees.update', $foreignEmployee), $this->validEmployeePayload([
                'nombre' => 'Intento',
                'correo' => 'intento@example.com',
                'id_rol' => 8,
            ]))
            ->assertForbidden();

        $this->assertDatabaseHas('empleados', [
            'id_empleado' => $ownEmployee->id_empleado,
            'id_sede' => 1,
        ]);
        $this->assertDatabaseHas('usuarios', [
            'id_usuario' => $foreignUser->id_usuario,
            'nombre' => 'Pedro',
            'correo' => 'pedro.norte@example.com',
        ]);
        $this->assertDatabaseHas('usuario_sede', [
            'id_usuario' => $foreignUser->id_usuario,
            'id_sede' => 2,
            'id_rol' => 2,
        ]);
    }

    public function test_manager_can_edit_an_employee_and_change_role_without_changing_site(): void
    {
        $manager = $this->createUser('Gerente', 'Centro', 'gerente@example.com');
        $this->assignRole($manager, 1, 9);

        $employeeUser = $this->createUser('Juan', 'Pérez', 'juan@example.com');
        $this->assignRole($employeeUser, 1, 6);
        $employee = $this->createEmployeeRecord($employeeUser, 1, 'Mesero de comedor');
        $existingPositionId = DB::table('puestos')->insertGetId(['nombre' => 'Cajero principal'], 'id_puesto');

        $this->actingAs($manager)
            ->withSession(['current_site_id' => 1])
            ->get(route('manager.employees.edit', $employee))
            ->assertOk()
            ->assertSee('juan@example.com');

        $this->actingAs($manager)
            ->withSession(['current_site_id' => 1])
            ->put(route('manager.employees.update', $employee), [
                'nombre' => 'Juan Carlos',
                'apellido_p' => 'Pérez',
                'apellido_m' => 'López',
                'correo' => 'juan.cajero@example.com',
                'password' => 'NuevaTemporal1!',
                'id_rol' => 8,
                'puesto' => 'Cajero principal',
                'id_sede' => 2,
            ])->assertRedirect(route('manager.employees.index'))
            ->assertSessionHas('status');

        $employeeUser->refresh();
        $this->assertSame('Juan Carlos', $employeeUser->nombre);
        $this->assertSame('juan.cajero@example.com', $employeeUser->correo);
        $this->assertTrue(Hash::check('NuevaTemporal1!', $employeeUser->password));
        $this->assertDatabaseMissing('usuario_sede', [
            'id_usuario' => $employeeUser->id_usuario,
            'id_rol' => 6,
        ]);
        $this->assertDatabaseHas('usuario_sede', [
            'id_usuario' => $employeeUser->id_usuario,
            'id_sede' => 1,
            'id_rol' => 8,
        ]);
        $this->assertDatabaseMissing('usuario_sede', [
            'id_usuario' => $employeeUser->id_usuario,
            'id_sede' => 2,
        ]);

        $this->assertSame(1, DB::table('puestos')->where('nombre', 'Cajero principal')->count());
        $this->assertDatabaseHas('empleados', [
            'id_empleado' => $employee->id_empleado,
            'nombre' => 'Juan Carlos Pérez López',
            'id_sede' => 1,
            'id_puesto' => $existingPositionId,
            'id_usuario' => $employeeUser->id_usuario,
        ]);

        $employeeUser->unsetRelation('roles');
        $this->assertTrue($employeeUser->hasRole('Cajero'));
        $this->assertFalse($employeeUser->hasRole('Mesero'));
    }

    private function validEmployeePayload(array $overrides = []): array
    {
        return array_replace([
            'nombre' => 'Juan',
            'apellido_p' => 'Pérez',
            'apellido_m' => 'López',
            'correo' => 'juan@gmail.com',
            'password' => 'Temporal1!',
            'id_rol' => 6,
            'puesto' => 'Mesero de comedor',
        ], $overrides);
    }

    private function createUser(string $name, string $lastName, string $email): User
    {
        return User::create([
            'nombre' => $name,
            'apellido_p' => $lastName,
            'apellido_m' => null,
            'correo' => $email,
            'password' => 'Password1!',
        ]);
    }

    private function assignRole(User $user, int $siteId, int $roleId): void
    {
        DB::table('usuario_sede')->insert([
            'id_usuario' => $user->id_usuario,
            'id_sede' => $siteId,
            'id_rol' => $roleId,
        ]);
    }

    private function createEmployeeRecord(User $user, int $siteId, string $position): Employee
    {
        $positionId = DB::table('puestos')->where('nombre', $position)->value('id_puesto')
            ?? DB::table('puestos')->insertGetId(['nombre' => $position], 'id_puesto');
        $employeeId = DB::table('empleados')->insertGetId([
            'nombre' => $user->name,
            'id_sede' => $siteId,
            'id_puesto' => $positionId,
            'id_usuario' => $user->id_usuario,
        ], 'id_empleado');

        return Employee::query()->findOrFail($employeeId);
    }

    private function seedCatalogs(): void
    {
        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre' => 'Usuario'],
            ['id_rol' => 2, 'nombre' => 'Personal de cocina'],
            ['id_rol' => 3, 'nombre' => 'Jefe de cocina'],
            ['id_rol' => 4, 'nombre' => 'Recepcion de insumos'],
            ['id_rol' => 5, 'nombre' => 'Jefe de almacen'],
            ['id_rol' => 6, 'nombre' => 'Mesero'],
            ['id_rol' => 7, 'nombre' => 'Capitan de meseros'],
            ['id_rol' => 8, 'nombre' => 'Cajero'],
            ['id_rol' => 9, 'nombre' => 'Gerente de sede'],
            ['id_rol' => 10, 'nombre' => 'Superadministrador'],
        ]);
        DB::table('sedes')->insert([
            ['id_sede' => 1, 'nombre' => 'Sede Centro', 'capacidad' => 150],
            ['id_sede' => 2, 'nombre' => 'Sede Norte', 'capacidad' => 200],
        ]);
    }

    private function createEmployeeSchema(): void
    {
        foreach (['empleados', 'puestos', 'usuario_sede', 'sedes', 'roles', 'usuarios'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('usuarios', function (Blueprint $table): void {
            $table->increments('id_usuario');
            $table->string('nombre', 100)->nullable();
            $table->string('apellido_p', 100)->nullable();
            $table->string('apellido_m', 100)->nullable();
            $table->string('correo', 150)->unique();
            $table->string('password', 250);
            $table->rememberToken();
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->integer('id_rol')->primary();
            $table->string('nombre', 100);
        });
        Schema::create('sedes', function (Blueprint $table): void {
            $table->increments('id_sede');
            $table->string('nombre', 100);
            $table->string('direccion', 255)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->integer('capacidad')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('lema')->nullable();
            $table->string('color', 30)->nullable();
            $table->integer('id_tipo')->nullable();
        });
        Schema::create('usuario_sede', function (Blueprint $table): void {
            $table->increments('id_usuario_sede');
            $table->integer('id_usuario');
            $table->integer('id_sede');
            $table->integer('id_rol');
        });
        Schema::create('puestos', function (Blueprint $table): void {
            $table->increments('id_puesto');
            $table->string('nombre', 100);
        });
        Schema::create('empleados', function (Blueprint $table): void {
            $table->increments('id_empleado');
            $table->string('nombre', 150);
            $table->integer('id_sede');
            $table->integer('id_puesto');
            $table->integer('id_usuario')->nullable();
        });
    }
}
