<?php

namespace Tests\Feature;

use App\Models\EmploymentApplication;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmploymentApplicationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->createEmploymentSchema();
        $this->seedCatalogs();
    }

    public function test_existing_common_user_without_site_can_log_in_to_the_legacy_portal(): void
    {
        $user = $this->createUser('Juan', 'juan@email.com');
        $this->assertTrue(Hash::check('Password1!', $user->password));
        $this->assertTrue($user->isCommonUser());
        $this->assertSame('Usuario', $user->roleLabel());
        $this->assertDatabaseMissing('usuario_sede', ['id_usuario' => $user->id_usuario]);

        $this->post(route('login.store'), [
            'correo' => $user->correo,
            'password' => 'Password1!',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->get(route('dashboard'))->assertOk()->assertViewIs('user.dashboard');
    }

    public function test_common_user_can_consult_services_public_roles_and_only_their_applications(): void
    {
        DB::table('servicios')->insert([
            'id_sede' => 1,
            'nombre' => 'Servicio de desayuno',
            'fecha' => now()->addDay()->toDateString(),
            'inicio_hora' => '08:00:00',
            'fin_hora' => '10:00:00',
            'capacidad' => 120,
            'estado' => 'Programado',
        ]);
        $juan = $this->createUser('Juan', 'juan@example.com');
        $otherUser = $this->createUser('Otra', 'otra@example.com');
        $this->createEmploymentApplication($juan, 1, 6, 'Quiero colaborar como mesero.');
        $this->createEmploymentApplication($otherUser, 2, 2, 'Solicitud privada de otra persona.');

        $this->actingAs($juan)->get(route('dashboard'))
            ->assertOk()
            ->assertViewIs('user.dashboard')
            ->assertSee('Servicio de desayuno')
            ->assertSee('Sede Centro')
            ->assertSee('Mesero')
            ->assertSee('Solicitar puesto')
            ->assertDontSee('Superadministrador');

        $this->actingAs($juan)->get(route('user.services.index'))
            ->assertOk()
            ->assertSee('Servicio de desayuno');

        $this->actingAs($juan)->get(route('user.applications.index'))
            ->assertOk()
            ->assertSee('Quiero colaborar como mesero.')
            ->assertDontSee('Solicitud privada de otra persona.');

        $this->actingAs($juan)->get(route('manager.applications.index'))->assertForbidden();
    }

    public function test_services_page_shows_empty_state_and_public_opportunities(): void
    {
        $juan = $this->createUser('Juan', 'juan@example.com');

        $response = $this->actingAs($juan)->get(route('user.services.index'));

        $response
            ->assertOk()
            ->assertSee('No hay servicios programados en este momento.')
            ->assertSee('Puedes explorar los puestos disponibles mientras se publican nuevos servicios.')
            ->assertSee('Conoce las áreas en las que puedes colaborar')
            ->assertSee('7 puestos disponibles')
            ->assertSee('Ver mis solicitudes')
            ->assertDontSee('Gerente de sede')
            ->assertViewHas('opportunities', function ($opportunities): bool {
                return $opportunities->count() === 7
                    && $opportunities->pluck('nombre')->all() === [
                        'Personal de cocina',
                        'Jefe de cocina',
                        'Recepción de insumos',
                        'Jefe de almacén',
                        'Mesero',
                        'Capitán de meseros',
                        'Cajero',
                    ];
            });

        foreach ([
            'Apoya en la preparación de alimentos',
            'Coordina la operación de cocina',
            'Recibe los productos de proveedores',
            'Administra pedidos, inventarios, recepciones y movimientos de almacén',
            'Atiende mesas durante los servicios',
            'Coordina a los meseros',
            'Gestiona cuentas y cobros',
        ] as $description) {
            $response->assertSee($description);
        }

        foreach (range(2, 8) as $roleId) {
            $response->assertSee(route('user.applications.create', ['id_rol' => $roleId]), false);
        }

        $response
            ->assertDontSee(route('user.applications.create', ['id_rol' => 1]), false)
            ->assertDontSee(route('user.applications.create', ['id_rol' => 9]), false)
            ->assertDontSee(route('user.applications.create', ['id_rol' => 10]), false);
    }

    public function test_opportunity_link_preselects_the_requested_public_role(): void
    {
        $juan = $this->createUser('Juan', 'juan@example.com');

        $this->actingAs($juan)
            ->get(route('user.applications.create', ['id_rol' => 6]))
            ->assertOk()
            ->assertSee('<option value="6" selected>Mesero</option>', false)
            ->assertDontSee('Gerente de sede')
            ->assertDontSee('value="9"', false)
            ->assertDontSee('value="10"', false);
    }

    public function test_common_user_can_submit_one_pending_application_per_site_and_role(): void
    {
        $juan = $this->createUser('Juan', 'juan@example.com');
        $otherUser = $this->createUser('Pedro', 'pedro@example.com');

        $payload = [
            'id_usuario' => $otherUser->id_usuario,
            'id_sede' => 1,
            'id_rol_solicitado' => 6,
            'mensaje' => 'Tengo experiencia atendiendo mesas y clientes.',
            'estado' => EmploymentApplication::STATUS_APPROVED,
        ];

        $this->actingAs($juan)->post(route('user.applications.store'), $payload)
            ->assertRedirect(route('user.applications.index'));

        $this->assertDatabaseHas('solicitudes_empleo', [
            'id_usuario' => $juan->id_usuario,
            'id_sede' => 1,
            'id_rol_solicitado' => 6,
            'estado' => EmploymentApplication::STATUS_PENDING,
            'mensaje' => $payload['mensaje'],
        ]);
        $this->assertDatabaseMissing('solicitudes_empleo', ['id_usuario' => $otherUser->id_usuario]);
        $this->assertNotNull(DB::table('solicitudes_empleo')->value('fecha_solicitud'));

        $this->actingAs($juan)->post(route('user.applications.store'), $payload)
            ->assertSessionHasErrors('id_rol_solicitado');

        $this->actingAs($juan)->post(route('user.applications.store'), [
            ...$payload,
            'id_rol_solicitado' => 9,
        ])->assertSessionHasErrors('id_rol_solicitado');

        $this->actingAs($juan)->post(route('user.applications.store'), [
            ...$payload,
            'id_rol_solicitado' => 10,
        ])->assertSessionHasErrors('id_rol_solicitado');

        $this->assertSame(1, DB::table('solicitudes_empleo')->count());
    }

    public function test_manager_only_sees_and_manages_applications_from_their_sites(): void
    {
        $centerManager = $this->createUser('Gerente Centro', 'gerente.centro@example.com');
        $northManager = $this->createUser('Gerente Norte', 'gerente.norte@example.com');
        $this->assignRole($centerManager, 1, 9);
        $this->assignRole($northManager, 2, 9);

        $juan = $this->createUser('Juan Centro', 'juan@example.com');
        $ana = $this->createUser('Ana Norte', 'ana@example.com');
        $centerApplication = $this->createEmploymentApplication($juan, 1, 6, 'Solicitud de Juan para Centro.');
        $northApplication = $this->createEmploymentApplication($ana, 2, 2, 'Solicitud de Ana para Norte.');
        $ownApplication = $this->createEmploymentApplication($centerManager, 1, 6, 'Solicitud propia del gerente.');

        $this->actingAs($centerManager)->get(route('manager.applications.index'))
            ->assertOk()
            ->assertSee('Juan Centro')
            ->assertDontSee('Ana Norte');

        $this->actingAs($centerManager)->get(route('manager.applications.show', $centerApplication))
            ->assertOk()
            ->assertSee('Solicitud de Juan para Centro.');

        $this->actingAs($centerManager)->get(route('manager.applications.show', $northApplication))
            ->assertForbidden();

        $this->actingAs($centerManager)->patch(route('manager.applications.approve', $northApplication))
            ->assertForbidden();

        $this->actingAs($centerManager)->patch(route('manager.applications.approve', $ownApplication))
            ->assertForbidden();
        $this->assertDatabaseMissing('usuario_sede', [
            'id_usuario' => $centerManager->id_usuario,
            'id_sede' => 1,
            'id_rol' => 6,
        ]);
    }

    public function test_manager_approval_assigns_role_and_site_without_duplicates_and_logs_the_action(): void
    {
        $manager = $this->createUser('Gerente Centro', 'gerente@example.com');
        $this->assignRole($manager, 1, 9);
        $juan = $this->createUser('Juan', 'juan@example.com');
        $application = $this->createEmploymentApplication($juan, 1, 6, 'Deseo incorporarme al equipo de meseros.');

        $this->actingAs($manager)->patch(route('manager.applications.approve', $application), [
            'observaciones_respuesta' => 'Perfil aprobado para la sede.',
        ])->assertRedirect(route('manager.applications.show', $application));

        $this->assertDatabaseHas('solicitudes_empleo', [
            'id_solicitud' => $application->id_solicitud,
            'estado' => EmploymentApplication::STATUS_APPROVED,
            'id_usuario_responde' => $manager->id_usuario,
            'observaciones_respuesta' => 'Perfil aprobado para la sede.',
        ]);
        $this->assertNotNull(DB::table('solicitudes_empleo')->where('id_solicitud', $application->id_solicitud)->value('fecha_respuesta'));
        $this->assertDatabaseHas('usuario_sede', [
            'id_usuario' => $juan->id_usuario,
            'id_sede' => 1,
            'id_rol' => 6,
        ]);
        $this->assertSame(1, DB::table('usuario_sede')->where([
            'id_usuario' => $juan->id_usuario,
            'id_sede' => 1,
            'id_rol' => 6,
        ])->count());
        $this->assertDatabaseHas('bitacora', [
            'id_usuario' => $manager->id_usuario,
            'id_sede' => 1,
            'tabla_afectada' => 'solicitudes_empleo',
            'id_registro' => $application->id_solicitud,
        ]);

        $juan->unsetRelation('roles');
        $this->assertTrue($juan->hasRole('Mesero'));
        $this->assertFalse($juan->isCommonUser());

        $this->actingAs($manager)->patch(route('manager.applications.approve', $application));
        $this->assertSame(1, DB::table('usuario_sede')->where([
            'id_usuario' => $juan->id_usuario,
            'id_sede' => 1,
            'id_rol' => 6,
        ])->count());
    }

    public function test_manager_rejection_records_the_reason_without_assigning_a_role(): void
    {
        $manager = $this->createUser('Gerente Centro', 'gerente@example.com');
        $this->assignRole($manager, 1, 9);
        $juan = $this->createUser('Juan', 'juan@example.com');
        $application = $this->createEmploymentApplication($juan, 1, 6, 'Deseo incorporarme al equipo de meseros.');

        $this->actingAs($manager)->patch(route('manager.applications.reject', $application), [
            'observaciones_respuesta' => 'Por ahora no hay una vacante disponible.',
        ])->assertRedirect(route('manager.applications.show', $application));

        $this->assertDatabaseHas('solicitudes_empleo', [
            'id_solicitud' => $application->id_solicitud,
            'estado' => EmploymentApplication::STATUS_REJECTED,
            'id_usuario_responde' => $manager->id_usuario,
            'observaciones_respuesta' => 'Por ahora no hay una vacante disponible.',
        ]);
        $this->assertDatabaseMissing('usuario_sede', [
            'id_usuario' => $juan->id_usuario,
            'id_sede' => 1,
            'id_rol' => 6,
        ]);
        $this->assertTrue($juan->isCommonUser());
    }

    private function createUser(string $name, string $email): User
    {
        return User::create([
            'nombre' => $name,
            'apellido_p' => 'Prueba',
            'apellido_m' => 'Usuario',
            'correo' => $email,
            'password' => Hash::make('Password1!'),
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

    private function createEmploymentApplication(User $user, int $siteId, int $roleId, string $message): EmploymentApplication
    {
        return EmploymentApplication::create([
            'id_usuario' => $user->id_usuario,
            'id_sede' => $siteId,
            'id_rol_solicitado' => $roleId,
            'mensaje' => $message,
            'estado' => EmploymentApplication::STATUS_PENDING,
            'fecha_solicitud' => now(),
        ]);
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

    private function createEmploymentSchema(): void
    {
        foreach (['solicitudes_empleo', 'bitacora', 'servicios', 'usuario_sede', 'sedes', 'roles', 'usuarios'] as $table) {
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
            $table->string('direccion')->nullable();
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
        Schema::create('servicios', function (Blueprint $table): void {
            $table->increments('id_servicio');
            $table->integer('id_sede');
            $table->string('nombre', 100);
            $table->date('fecha')->nullable();
            $table->time('inicio_hora')->nullable();
            $table->time('fin_hora')->nullable();
            $table->integer('capacidad')->nullable();
            $table->string('estado', 30)->nullable();
        });
        Schema::create('solicitudes_empleo', function (Blueprint $table): void {
            $table->increments('id_solicitud');
            $table->integer('id_usuario');
            $table->integer('id_sede');
            $table->integer('id_rol_solicitado');
            $table->text('mensaje')->nullable();
            $table->string('estado', 20)->default(EmploymentApplication::STATUS_PENDING);
            $table->timestamp('fecha_solicitud')->nullable();
            $table->timestamp('fecha_respuesta')->nullable();
            $table->integer('id_usuario_responde')->nullable();
            $table->text('observaciones_respuesta')->nullable();
        });
        Schema::create('bitacora', function (Blueprint $table): void {
            $table->increments('id_bitacora');
            $table->integer('id_usuario')->nullable();
            $table->integer('id_sede')->nullable();
            $table->string('accion', 30);
            $table->string('tabla_afectada', 100);
            $table->integer('id_registro')->nullable();
            $table->longText('datos_anteriores')->nullable();
            $table->longText('datos_nuevos')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->dateTime('fecha')->nullable();
        });
    }
}
