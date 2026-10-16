<?php

namespace Tests\Feature;

use App\Models\CommercialContact;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommercialContactFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->createCommercialSchema();
        $this->seedCatalogs();
    }

    public function test_landing_is_public_and_public_registration_is_not_available(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertViewIs('welcome')
            ->assertSee('Gestión integral para')
            ->assertSee(route('login'), false)
            ->assertSee('name="nombre"', false)
            ->assertSee('name="correo"', false)
            ->assertSee('name="mensaje"', false)
            ->assertDontSee('name="empresa"', false)
            ->assertDontSee('name="telefono"', false)
            ->assertDontSee('name="ciudad"', false)
            ->assertDontSee('name="tipo_operacion"', false)
            ->assertDontSee('name="volumen_aproximado"', false)
            ->assertDontSee('/register', false)
            ->assertDontSee('Solicitar empleo');

        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'nombre' => 'Cuenta',
            'apellido_p' => 'Pública',
            'correo' => 'registro@example.com',
            'password' => 'Password1!',
        ])->assertNotFound();

        $this->assertDatabaseMissing('usuarios', ['correo' => 'registro@example.com']);
    }

    public function test_valid_simple_contact_is_saved_normalized_as_new_with_automatic_date(): void
    {
        $contactedAt = Carbon::parse('2026-10-15 10:30:00');
        $this->travelTo($contactedAt);

        $this->post(route('contact.store'), [
            'nombre' => '  <strong>Gustavo   Cruz</strong>  ',
            'correo' => ' GUS@GMAIL.COM ',
            'mensaje' => " <b>Quiero implementar</b> la aplicación.\n\n\n\nEn mi comedor industrial. ",
            'website' => '',
        ])->assertRedirect(route('home').'#contacto')
            ->assertSessionHas('contact_status');

        $this->assertDatabaseCount('contactos', 1);
        $contact = CommercialContact::query()->firstOrFail();

        $this->assertSame('contactos', $contact->getTable());
        $this->assertSame('Gustavo Cruz', $contact->nombre);
        $this->assertSame('gus@gmail.com', $contact->correo);
        $this->assertSame(
            'Quiero implementar la aplicación.'.PHP_EOL.PHP_EOL.'En mi comedor industrial.',
            $contact->mensaje,
        );
        $this->assertSame(CommercialContact::STATUS_NEW, $contact->estado);
        $this->assertTrue($contact->fecha_contacto->equalTo($contactedAt));
        $this->assertNull($contact->fecha_respuesta);
        $this->assertNull($contact->id_superadmin_responde);
        $this->assertNull($contact->observaciones_respuesta);
        $this->assertFalse(Schema::hasTable('contactos_comerciales'));
    }

    public function test_contact_validation_rejects_invalid_data_and_honeypot_discards_spam(): void
    {
        $this->post(route('contact.store'), [
            'nombre' => '',
            'correo' => 'correo-invalido',
            'mensaje' => '',
            'website' => '',
        ])->assertRedirect(route('home').'#contacto')
            ->assertSessionHasErrors(['nombre', 'correo', 'mensaje'], null, 'contact');

        $this->assertDatabaseCount('contactos', 0);

        $this->post(route('contact.store'), [
            ...$this->validContactPayload(),
            'website' => 'https://spam.example',
        ])->assertRedirect(route('home').'#contacto')
            ->assertSessionHas('contact_status');

        $this->assertDatabaseCount('contactos', 0);
    }

    public function test_contact_routes_require_authentication_and_reject_managers(): void
    {
        $contact = $this->createContact();

        $this->get(route('admin.contacts.index'))->assertRedirect(route('login'));

        $manager = $this->createRoleUser(9, 'gerente@example.com');

        $this->actingAs($manager)->get(route('admin.contacts.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('admin.contacts.show', $contact))->assertForbidden();
        $this->actingAs($manager)->patch(route('admin.contacts.update', $contact), [
            'estado' => CommercialContact::STATUS_CONTACTED,
        ])->assertForbidden();
        $this->actingAs($manager)->get(route('admin.contacts.convert.create', $contact))->assertForbidden();
        $this->actingAs($manager)->post(route('admin.contacts.convert.store', $contact), [
            'site_mode' => 'existing',
            'id_sede' => 1,
        ])->assertForbidden();
    }

    public function test_super_administrator_lists_contacts_and_can_mark_them_contacted_or_rejected(): void
    {
        $superAdministrator = $this->createRoleUser(10, 'superadmin@example.com');
        $contacted = $this->createContact([
            'nombre' => 'Gustavo Cruz',
            'correo' => 'gus@gmail.com',
            'mensaje' => 'Quiero implementar la aplicación en mi comedor.',
        ]);
        $rejected = $this->createContact([
            'nombre' => 'Contacto descartado',
            'correo' => 'descartado@example.com',
        ]);

        $this->actingAs($superAdministrator)
            ->get(route('admin.contacts.index', ['buscar' => 'Gustavo']))
            ->assertOk()
            ->assertSee('Gustavo Cruz')
            ->assertSee('gus@gmail.com')
            ->assertSee('Quiero implementar la aplicación en mi comedor.')
            ->assertDontSee('descartado@example.com');

        $this->from(route('admin.contacts.show', $contacted))
            ->actingAs($superAdministrator)
            ->patch(route('admin.contacts.update', $contacted), [
                'estado' => CommercialContact::STATUS_CONTACTED,
                'observaciones_respuesta' => " <b>Llamada inicial</b>\n\n\nPróximo paso acordado. ",
            ])->assertRedirect(route('admin.contacts.show', $contacted))
            ->assertSessionHas('status');

        $contacted->refresh();
        $this->assertSame(CommercialContact::STATUS_CONTACTED, $contacted->estado);
        $this->assertSame(
            'Llamada inicial'.PHP_EOL.PHP_EOL.'Próximo paso acordado.',
            $contacted->observaciones_respuesta,
        );
        $this->assertSame($superAdministrator->id_usuario, $contacted->id_superadmin_responde);
        $this->assertNotNull($contacted->fecha_respuesta);

        $this->from(route('admin.contacts.show', $rejected))
            ->actingAs($superAdministrator)
            ->patch(route('admin.contacts.update', $rejected), [
                'estado' => CommercialContact::STATUS_REJECTED,
                'observaciones_respuesta' => 'La solicitud no corresponde al servicio.',
            ])->assertRedirect(route('admin.contacts.show', $rejected));

        $this->assertDatabaseHas('contactos', [
            'id_contacto' => $rejected->id_contacto,
            'estado' => CommercialContact::STATUS_REJECTED,
            'id_superadmin_responde' => $superAdministrator->id_usuario,
            'observaciones_respuesta' => 'La solicitud no corresponde al servicio.',
        ]);
    }

    public function test_contact_cannot_be_marked_approved_without_atomic_conversion(): void
    {
        $superAdministrator = $this->createRoleUser(10, 'superadmin@example.com');
        $contact = $this->createContact();

        $this->from(route('admin.contacts.show', $contact))
            ->actingAs($superAdministrator)
            ->patch(route('admin.contacts.update', $contact), [
                'estado' => CommercialContact::STATUS_APPROVED,
                'observaciones_respuesta' => 'Intento de aprobación directa.',
            ])->assertRedirect(route('admin.contacts.show', $contact))
            ->assertSessionHasErrors('estado');

        $contact->refresh();
        $this->assertSame(CommercialContact::STATUS_NEW, $contact->estado);
        $this->assertNull($contact->id_superadmin_responde);
        $this->assertNull($contact->fecha_respuesta);
    }

    public function test_new_site_conversion_atomically_creates_site_manager_assignment_and_approval(): void
    {
        $superAdministrator = $this->createRoleUser(10, 'superadmin@example.com');
        $contact = $this->createContact();

        $this->actingAs($superAdministrator)
            ->get(route('admin.contacts.convert.create', $contact))
            ->assertOk()
            ->assertSee('Gerente de sede')
            ->assertDontSee('name="id_rol"', false);

        $this->actingAs($superAdministrator)
            ->post(route('admin.contacts.convert.store', $contact), $this->newSiteConversionPayload())
            ->assertRedirect(route('admin.contacts.show', $contact))
            ->assertSessionHas('status');

        $siteId = DB::table('sedes')->where('nombre', 'Planta ABC Toluca')->value('id_sede');
        $this->assertNotNull($siteId);
        $this->assertDatabaseHas('sedes', [
            'id_sede' => $siteId,
            'direccion' => 'Parque Industrial Toluca 100',
            'telefono' => '722 000 0000',
            'capacidad' => 650,
        ]);

        $manager = User::query()->where('correo', 'gerente@empresa.com')->firstOrFail();
        $this->assertSame('Laura', $manager->nombre);
        $this->assertSame('Ramírez', $manager->apellido_p);
        $this->assertTrue(Hash::check('Gerente1!', $manager->password));
        $this->assertDatabaseHas('usuario_sede', [
            'id_usuario' => $manager->id_usuario,
            'id_sede' => $siteId,
            'id_rol' => 9,
        ]);
        $this->assertTrue($manager->isManager());

        $contact->refresh();
        $this->assertSame(CommercialContact::STATUS_APPROVED, $contact->estado);
        $this->assertSame($superAdministrator->id_usuario, $contact->id_superadmin_responde);
        $this->assertNotNull($contact->fecha_respuesta);
        $this->assertNull($contact->observaciones_respuesta);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->post(route('login.store'), [
            'correo' => 'gerente@empresa.com',
            'password' => 'Gerente1!',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($manager);
    }

    public function test_existing_site_conversion_assigns_manager_without_creating_another_site(): void
    {
        $superAdministrator = $this->createRoleUser(10, 'superadmin@example.com');
        $contact = $this->createContact();
        $siteCount = DB::table('sedes')->count();

        $this->actingAs($superAdministrator)
            ->post(route('admin.contacts.convert.store', $contact), $this->existingSiteConversionPayload())
            ->assertRedirect(route('admin.contacts.show', $contact));

        $manager = User::query()->where('correo', 'gerente.existente@empresa.com')->firstOrFail();
        $this->assertDatabaseHas('usuario_sede', [
            'id_usuario' => $manager->id_usuario,
            'id_sede' => 1,
            'id_rol' => 9,
        ]);
        $this->assertSame($siteCount, DB::table('sedes')->count());
        $this->assertSame(CommercialContact::STATUS_APPROVED, $contact->fresh()->estado);
    }

    public function test_an_approved_contact_cannot_be_converted_a_second_time(): void
    {
        $superAdministrator = $this->createRoleUser(10, 'superadmin@example.com');
        $contact = $this->createContact();

        $this->actingAs($superAdministrator)
            ->post(route('admin.contacts.convert.store', $contact), $this->existingSiteConversionPayload())
            ->assertRedirect(route('admin.contacts.show', $contact));

        $userCount = DB::table('usuarios')->count();
        $siteCount = DB::table('sedes')->count();
        $assignmentCount = DB::table('usuario_sede')->count();

        $this->from(route('admin.contacts.show', $contact))
            ->actingAs($superAdministrator)
            ->post(route('admin.contacts.convert.store', $contact), $this->newSiteConversionPayload([
                'sede_nombre' => 'Sede duplicada',
                'gerente_correo' => 'segundo.gerente@empresa.com',
            ]))
            ->assertRedirect(route('admin.contacts.show', $contact))
            ->assertSessionHasErrors('conversion');

        $this->assertSame($userCount, DB::table('usuarios')->count());
        $this->assertSame($siteCount, DB::table('sedes')->count());
        $this->assertSame($assignmentCount, DB::table('usuario_sede')->count());
        $this->assertDatabaseMissing('usuarios', ['correo' => 'segundo.gerente@empresa.com']);
        $this->assertDatabaseMissing('sedes', ['nombre' => 'Sede duplicada']);
        $this->assertSame(CommercialContact::STATUS_APPROVED, $contact->fresh()->estado);
    }

    private function validContactPayload(array $overrides = []): array
    {
        return array_replace([
            'nombre' => 'Gustavo Cruz',
            'correo' => 'gus@gmail.com',
            'mensaje' => 'Quiero implementar la aplicación en mi comedor.',
            'website' => '',
        ], $overrides);
    }

    private function createContact(array $overrides = []): CommercialContact
    {
        return CommercialContact::create(array_replace([
            'nombre' => 'Gustavo Cruz',
            'correo' => 'gus@gmail.com',
            'mensaje' => 'Quiero implementar la aplicación en mi comedor.',
            'estado' => CommercialContact::STATUS_NEW,
            'fecha_contacto' => now(),
        ], $overrides));
    }

    private function createRoleUser(int $roleId, string $email): User
    {
        $user = User::create([
            'nombre' => $roleId === 10 ? 'Superadministrador' : 'Gerente',
            'apellido_p' => 'Prueba',
            'apellido_m' => null,
            'correo' => $email,
            'password' => 'Password1!',
        ]);

        DB::table('usuario_sede')->insert([
            'id_usuario' => $user->id_usuario,
            'id_sede' => 1,
            'id_rol' => $roleId,
        ]);

        return $user;
    }

    private function newSiteConversionPayload(array $overrides = []): array
    {
        return array_replace([
            'site_mode' => 'new',
            'sede_nombre' => ' <b>Planta ABC   Toluca</b> ',
            'sede_direccion' => ' Parque Industrial   Toluca 100 ',
            'sede_telefono' => ' 722   000 0000 ',
            'sede_capacidad' => 650,
            'gerente_nombre' => ' Laura ',
            'gerente_apellido_p' => ' Ramírez ',
            'gerente_apellido_m' => ' Soto ',
            'gerente_correo' => ' GERENTE@EMPRESA.COM ',
            'gerente_password' => 'Gerente1!',
        ], $overrides);
    }

    private function existingSiteConversionPayload(array $overrides = []): array
    {
        return array_replace([
            'site_mode' => 'existing',
            'id_sede' => 1,
            'gerente_nombre' => 'Carlos',
            'gerente_apellido_p' => 'Mendoza',
            'gerente_apellido_m' => null,
            'gerente_correo' => 'gerente.existente@empresa.com',
            'gerente_password' => 'Gerente1!',
        ], $overrides);
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
        DB::table('tipo_sedes')->insert([
            'id_tipo' => 1,
            'nombre' => 'Comedor industrial',
        ]);
        DB::table('sedes')->insert([
            'id_sede' => 1,
            'nombre' => 'Sede existente',
            'direccion' => 'Dirección existente',
            'telefono' => '722 111 1111',
            'capacidad' => 250,
            'id_tipo' => 1,
        ]);
    }

    private function createCommercialSchema(): void
    {
        foreach ([
            'contactos_comerciales',
            'contactos',
            'usuario_sede',
            'sedes',
            'tipo_sedes',
            'roles',
            'usuarios',
        ] as $table) {
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
        Schema::create('tipo_sedes', function (Blueprint $table): void {
            $table->increments('id_tipo');
            $table->string('nombre', 100)->unique();
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
        Schema::create('contactos', function (Blueprint $table): void {
            $table->increments('id_contacto');
            $table->string('nombre', 150);
            $table->string('correo', 150);
            $table->text('mensaje');
            $table->string('estado', 20)->default('nuevo');
            $table->timestamp('fecha_contacto')->nullable();
            $table->timestamp('fecha_respuesta')->nullable();
            $table->integer('id_superadmin_responde')->nullable();
            $table->text('observaciones_respuesta')->nullable();
        });
    }
}
