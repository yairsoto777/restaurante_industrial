<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class ServiceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        foreach (['usuarios', 'roles', 'sedes', 'usuario_sede', 'servicios'] as $table) Schema::dropIfExists($table);
        Schema::create('usuarios', fn (Blueprint $table) => [$table->increments('id_usuario'), $table->string('nombre'), $table->string('apellido_p')->nullable(), $table->string('apellido_m')->nullable(), $table->string('correo')->unique(), $table->string('password'), $table->rememberToken()]);
        Schema::create('roles', fn (Blueprint $table) => [$table->integer('id_rol')->primary(), $table->string('nombre')]);
        Schema::create('sedes', fn (Blueprint $table) => [$table->increments('id_sede'), $table->string('nombre')]);
        Schema::create('usuario_sede', fn (Blueprint $table) => [$table->increments('id_usuario_sede'), $table->integer('id_usuario'), $table->integer('id_sede')->nullable(), $table->integer('id_rol')]);
        Schema::create('servicios', function (Blueprint $table): void { $table->increments('id_servicio'); $table->integer('id_sede'); $table->string('nombre'); $table->date('fecha')->nullable(); $table->time('inicio_hora')->nullable(); $table->time('fin_hora')->nullable(); $table->integer('capacidad')->nullable(); $table->string('estado')->nullable(); });
        Schema::getConnection()->table('sedes')->insert([['id_sede' => 1, 'nombre' => 'Norte'], ['id_sede' => 2, 'nombre' => 'Sur']]);
        Schema::getConnection()->table('roles')->insert([['id_rol' => 2, 'nombre' => 'Personal de cocina'], ['id_rol' => 9, 'nombre' => 'Gerente de sede']]);
    }

    public function test_manager_can_create_and_edit_only_services_from_current_site(): void
    {
        $manager = $this->user('Gerente de sede', 9, 1);
        $this->actingAs($manager)->get(route('manager.services.index'))
            ->assertOk()
            ->assertSee('Gestión de servicios');
        $payload = ['nombre' => 'Desayuno', 'fecha' => now()->addDay()->toDateString(), 'inicio_hora' => '08:00', 'fin_hora' => '10:00', 'capacidad' => 100, 'estado' => 'Programado'];

        $this->actingAs($manager)->post(route('manager.services.store'), $payload)->assertRedirect(route('manager.services.index'));
        $this->assertDatabaseHas('servicios', ['nombre' => 'Desayuno', 'id_sede' => 1]);
        $service = Service::first();
        $this->actingAs($manager)->put(route('manager.services.update', $service), [...$payload, 'nombre' => 'Desayuno actualizado'])->assertRedirect(route('manager.services.index'));
        $this->assertDatabaseHas('servicios', ['id_servicio' => $service->id_servicio, 'nombre' => 'Desayuno actualizado']);
    }

    public function test_manager_cannot_use_foreign_site_or_invalid_values(): void
    {
        $manager = $this->user('Gerente de sede', 9, 1);
        $payload = ['nombre' => 'Inválido', 'fecha' => now()->toDateString(), 'inicio_hora' => '10:00', 'fin_hora' => '10:00', 'capacidad' => 0, 'estado' => 'Desconocido', 'id_sede' => 2];

        $this->actingAs($manager)->post(route('manager.services.store'), $payload)->assertSessionHasErrors(['fin_hora', 'capacidad', 'estado']);
        $this->assertDatabaseCount('servicios', 0);
    }

    public function test_kitchen_can_consult_own_site_but_cannot_write_services(): void
    {
        $kitchen = $this->user('Personal de cocina', 2, 1);
        Service::create(['id_sede' => 1, 'nombre' => 'Comida', 'fecha' => now()->toDateString(), 'inicio_hora' => '13:00', 'fin_hora' => '15:00', 'capacidad' => 80, 'estado' => 'Programado']);
        Service::create(['id_sede' => 2, 'nombre' => 'Ajeno', 'fecha' => now()->toDateString(), 'inicio_hora' => '13:00', 'fin_hora' => '15:00', 'capacidad' => 80, 'estado' => 'Programado']);

        $this->actingAs($kitchen)->get(route('services.index'))->assertOk()->assertSee('Comida')->assertDontSee('Ajeno');
        $this->actingAs($kitchen)->get(route('manager.services.create'))->assertForbidden();
        $this->actingAs($kitchen)->post(route('manager.services.store'), [])->assertForbidden();
    }

    private function user(string $role, int $roleId, int $siteId): User
    {
        $user = User::create(['nombre' => $role, 'apellido_p' => 'Prueba', 'correo' => uniqid().'@example.com', 'password' => Hash::make('Password1!')]);
        Schema::getConnection()->table('usuario_sede')->insert(['id_usuario' => $user->id_usuario, 'id_sede' => $siteId, 'id_rol' => $roleId]);
        return $user;
    }
}
