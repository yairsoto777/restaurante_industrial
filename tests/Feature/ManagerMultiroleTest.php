<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use App\Support\EmployeePresentation;

/** All fixtures use the in-memory connection guarded by RoleAccessTest; no migrations. */
class ManagerMultiroleTest extends RoleAccessTest
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_finish_employee_requirements_photo_and_roles(): void
    {
        $this->employees();
        $manager = $this->createUser('Gerente de sede', 9, 1);
        $this->actingAs($manager)->withSession(['current_site_id' => 1, 'active_role_id' => 9]);
        $this->get(route('manager.employees.create'))->assertOk()->assertDontSee('name="id_puesto"', false)->assertDontSee('Puesto y responsabilidades')->assertSee('name="apellido_m"', false);
        $data = ['nombre' => 'Laura', 'apellido_p' => 'García', 'correo' => 'final@example.com', 'password' => 'Temporal1!', 'primary_role_id' => 6, 'role_ids' => [7]];
        $this->post(route('manager.employees.store'), $data)->assertSessionHasErrors('apellido_m');
        $this->assertDatabaseMissing('usuarios', ['correo' => $data['correo']]);
        $data['apellido_m'] = 'López';
        $this->post(route('manager.employees.store'), [...$data, 'foto' => UploadedFile::fake()->image('avatar.jpg', 40, 40)])->assertRedirect();
        $employee = Employee::query()->firstOrFail();
        $user = $employee->user;
        $password = $user->password;
        $presentation = app(EmployeePresentation::class);
        $location = $presentation->photoLocation($employee);
        $this->assertSame('public', $location['disk']);
        $this->get(route('manager.employees.photo', $employee))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $profile = $presentation->profile($employee, [6, 7]);
        $this->get(route('manager.employees.index', ['editar' => $employee->getKey()]))->assertOk()->assertSee($profile['photo_url'], false)->assertSee('Principal')->assertSee('Secundario')->assertDontSee('name="id_puesto"', false);
        $this->put(route('manager.employees.update', $employee), [...$data, 'password' => '', 'primary_role_id' => 7, 'role_ids' => [6]])->assertRedirect();
        $this->assertSame($location, $presentation->photoLocation($employee));
        $this->assertSame(7, $presentation->profile($employee, [6,7])['primary_role_id']);
        $this->assertSame($password, $user->fresh()->password);
        $this->assertDatabaseCount('usuarios', 2);
        $this->post(route('manager.employees.update', $employee), [...$data, 'password' => '', '_method' => 'PUT', 'foto' => UploadedFile::fake()->image('replacement.jpg', 40, 40)])->assertRedirect();
        Storage::disk('public')->assertExists($location['path']);
        $this->assertNotSame($location['path'], $presentation->photoPath($employee));
        $this->put(route('manager.employees.update', $employee), [...$data, 'password' => '', 'apellido_m' => null])->assertRedirect();
        $this->assertDatabaseHas('empleados', ['id_empleado' => $employee->getKey()]);
        $this->put(route('manager.employees.update', $employee), [...$data, 'primary_role_id' => 9])->assertSessionHasErrors('primary_role_id');
        $this->post(route('manager.employees.update', $employee), [...$data, '_method' => 'PUT', 'foto' => UploadedFile::fake()->create('invalid.pdf', 10, 'application/pdf')])->assertSessionHasErrors('foto');
    }

    public function test_finish_manager_modules_selector_and_css_isolation(): void
    {
        $this->employees();
        $this->createOrderSchema();
        $manager = $this->createUser('Gerente de sede', 9, 1);
        $chef = $this->createUser('Jefe de cocina', 3, 1);
        Schema::create('servicios', function (Blueprint $t) {
            $t->increments('id_servicio'); $t->integer('id_sede'); $t->string('nombre'); $t->date('fecha');
            $t->time('inicio_hora'); $t->time('fin_hora'); $t->integer('capacidad'); $t->string('estado'); $t->string('estado_aprobacion');
            $t->integer('id_usuario_registro'); $t->integer('id_usuario_revision')->nullable(); $t->timestamp('revisado_en')->nullable(); $t->text('observaciones_revision')->nullable();
        });
        foreach ([1,2] as $siteId) DB::table('servicios')->insert(['id_sede' => $siteId, 'nombre' => 'Servicio sede '.$siteId, 'fecha' => now()->toDateString(), 'inicio_hora' => '08:00', 'fin_hora' => '10:00', 'capacidad' => 120, 'estado' => 'Programado', 'estado_aprobacion' => 'Pendiente', 'id_usuario_registro' => $chef->getKey()]);
        Schema::create('recepciones', function (Blueprint $t) { $t->increments('id_recepcion'); $t->integer('id_pedido'); $t->dateTime('fecha_recepcion'); $t->string('estado'); });
        DB::table('recepciones')->insert(['id_pedido' => 1, 'fecha_recepcion' => now(), 'estado' => 'Pendiente de revisión']);
        Schema::create('unidades', function (Blueprint $t) { $t->increments('id_unidad'); $t->string('nombre'); $t->string('codigo'); $t->boolean('permite_fracciones'); });
        Schema::table('insumos', fn (Blueprint $t) => $t->integer('id_unidad_base')->nullable());
        DB::table('unidades')->insert(['id_unidad' => 1, 'nombre' => 'Kilogramo', 'codigo' => 'kg', 'permite_fracciones' => true]);
        DB::table('insumos')->update(['id_unidad_base' => 1]);
        Schema::create('insumo_sedes', function (Blueprint $t) { $t->increments('id_insumo_sede'); $t->integer('id_sede'); $t->integer('id_insumo'); $t->decimal('stock',12,3); $t->decimal('punto_de_reorden',12,3); });
        DB::table('insumo_sedes')->insert(['id_sede'=>1,'id_insumo'=>1,'stock'=>2,'punto_de_reorden'=>5]);
        $this->actingAs($manager)->withSession(['current_site_id' => 1, 'active_role_id' => 9]);
        $this->get('/dashboard')->assertOk()->assertSee('¡Hola, Gerente de sede!')->assertSee('manager-finish.css');
        $this->get(route('manager.services.index'))->assertOk()->assertSee('manager-service-card')->assertSee('Servicio sede 1')->assertDontSee('Servicio sede 2');
        $this->get('/recepciones')->assertOk()->assertViewIs('manager.receptions.index')->assertSee('Historial de mercancía recibida')->assertSee('Pedidos a proveedores');
        $this->get(route('inventory.index'))->assertOk()->assertSee('Estado de las existencias')->assertSee('Bajo mínimo');
        $this->assertDatabaseHas('insumo_sedes',['id_sede'=>1,'id_insumo'=>1,'stock'=>2]);
        $this->patch(route('manager.services.approve',2))->assertForbidden();
        $this->patch(route('manager.services.approve',1))->assertRedirect();
        $this->assertDatabaseHas('servicios',['id_servicio'=>1,'estado_aprobacion'=>'Aprobado']);
        $this->get(route('roles.select'))->assertRedirect(route('dashboard'));
        DB::table('usuario_sede')->insert(['id_usuario'=>$manager->getKey(),'id_sede'=>1,'id_rol'=>6]);
        $this->get(route('roles.select'))->assertOk()->assertSee('Entrar con este rol')->assertSee('role-card-grid')->assertDontSee('Superadministrador');
        $this->post(route('roles.update'),['id_rol'=>5,'id_sede'=>1])->assertForbidden();
        Route::middleware(['web','auth','active'])->get('/test-finish-layout',fn () => view('layouts.app'));
        foreach ([2=>'Personal de cocina',3=>'Jefe de cocina',4=>'Recepcion de insumos',5=>'Jefe de almacen',6=>'Mesero',7=>'Capitan de meseros',8=>'Cajero',10=>'Superadministrador'] as $roleId=>$name) {
            $user = $this->createUser($name,$roleId,1);
            $this->actingAs($user)->withSession(['current_site_id'=>1,'active_role_id'=>$roleId])->get('/test-finish-layout')->assertOk()->assertDontSee('manager-finish.css')->assertDontSee('css/manager.css');
        }
    }

    private function employees(): void
    {
        Schema::create('puestos', function (Blueprint $t) { $t->increments('id_puesto'); $t->string('nombre'); });
        Schema::create('empleados', function (Blueprint $t) {
            $t->increments('id_empleado'); $t->string('nombre'); $t->integer('id_usuario');
            $t->integer('id_sede'); $t->integer('id_puesto')->nullable();
        });
        foreach ([2 => 'Personal de cocina', 3 => 'Jefe de cocina', 4 => 'Recepcion de insumos', 5 => 'Jefe de almacen', 6 => 'Mesero', 7 => 'Capitan de meseros', 8 => 'Cajero'] as $id => $name) {
            DB::table('roles')->insertOrIgnore(['id_rol' => $id, 'nombre' => $name]);
        }
    }

    public function test_multirole_edit_preserves_account_password_history_and_other_sites(): void
    {
        $this->employees();
        $manager = $this->createUser('Gerente de sede', 9, 1);
        $user = $this->createUser('Mesero', 6, 1);
        DB::table('usuario_sede')->insert(['id_usuario' => $user->getKey(), 'id_sede' => 2, 'id_rol' => 6]);
        $employee = Employee::create(['nombre' => $user->name, 'id_usuario' => $user->getKey(), 'id_sede' => 1]);
        $password = $user->password;
        $data = ['nombre' => $user->nombre, 'apellido_p' => $user->apellido_p, 'correo' => $user->correo, 'role_ids' => [6, 7]];
        $this->actingAs($manager)->withSession(['current_site_id' => 1, 'active_role_id' => 9]);
        $this->put(route('manager.employees.update', $employee), $data)->assertRedirect(route('manager.employees.index'));
        $this->assertSame($password, $user->fresh()->password);
        $this->assertSame($data['correo'], $user->fresh()->correo);
        $this->assertDatabaseCount('usuarios', 2);
        $this->assertDatabaseHas('usuario_sede', ['id_usuario' => $user->getKey(), 'id_sede' => 1, 'id_rol' => 7]);
        $this->get(route('manager.employees.index', ['editar' => $employee->getKey()]))->assertOk()->assertSee('role_ids[]', false)->assertSee('Capitán de meseros')->assertSee('Mesero');
        $this->delete(route('manager.employees.destroy', $employee), ['id_rol' => 7])->assertRedirect();
        $this->assertDatabaseHas('usuario_sede', ['id_usuario' => $user->getKey(), 'id_sede' => 1, 'id_rol' => 6]);
        $this->assertDatabaseHas('usuario_sede', ['id_usuario' => $user->getKey(), 'id_sede' => 2, 'id_rol' => 6]);
        $this->assertDatabaseMissing('usuario_sede', ['id_usuario' => $user->getKey(), 'id_sede' => 1, 'id_rol' => 7]);
        $this->delete(route('manager.employees.destroy', $employee))->assertRedirect();
        $this->assertDatabaseHas('empleados', ['id_empleado' => $employee->getKey()]);
        $this->assertSame($password, $user->fresh()->password);
        $this->get(route('manager.employees.index'))->assertOk()->assertSee($user->correo)->assertSee('Sin acceso');
        $this->put(route('manager.employees.update', $employee), $data)->assertRedirect();
    }

    public function test_multirole_registration_and_manager_boundaries(): void
    {
        $this->employees();
        $manager = $this->createUser('Gerente de sede', 9, 1);
        $this->createUser('Superadministrador', 10, 1);
        $this->actingAs($manager)->withSession(['current_site_id' => 1, 'active_role_id' => 9]);
        $data = ['nombre' => 'Laura', 'apellido_p' => 'García', 'apellido_m' => 'López', 'correo' => 'laura@example.com', 'password' => 'Temporal1!', 'role_ids' => [6, 7], 'id_sede' => 2];
        $this->get(route('manager.employees.create'))->assertOk()->assertSee('role_ids[]', false);
        $this->post(route('manager.employees.store'), $data)->assertRedirect();
        $id = DB::table('usuarios')->where('correo', $data['correo'])->value('id_usuario');
        $this->assertDatabaseHas('usuario_sede', ['id_usuario' => $id, 'id_sede' => 1, 'id_rol' => 6]);
        $this->assertDatabaseHas('usuario_sede', ['id_usuario' => $id, 'id_sede' => 1, 'id_rol' => 7]);
        $this->post(route('manager.employees.store'), $data)->assertSessionHasErrors('correo');
        $this->post(route('manager.employees.store'), [...$data, 'correo' => 'invalid@example.com', 'role_ids' => [10]])->assertSessionHasErrors('role_ids.0');
        $foreignUser = $this->createUser('Mesero', 6, 2);
        $foreignEmployee = Employee::create(['nombre' => 'Ajeno', 'id_usuario' => $foreignUser->getKey(), 'id_sede' => 2]);
        $this->get(route('manager.employees.index', ['editar' => $foreignEmployee->getKey()]))->assertForbidden();
        $this->put(route('manager.employees.update', $foreignEmployee), $data)->assertForbidden();
        $this->delete(route('manager.employees.destroy', $foreignEmployee))->assertForbidden();
        $this->get(route('manager.employees.index', ['buscar' => 'laura', 'rol' => 7]))->assertOk()->assertSee('laura@example.com')->assertDontSee($foreignUser->correo);
    }

    public function test_multirole_active_role_matrix_switch_and_revocation(): void
    {
        $names = ['Usuario', 'Personal de cocina', 'Jefe de cocina', 'Recepcion de insumos', 'Jefe de almacen', 'Mesero', 'Capitan de meseros', 'Cajero', 'Gerente de sede', 'Superadministrador'];
        $user = $this->createUser('Mesero', 6, 1);
        foreach ($names as $index => $name) {
            $id = $index + 1;
            DB::table('roles')->insertOrIgnore(['id_rol' => $id, 'nombre' => $name]);
            if ($id !== 6) DB::table('usuario_sede')->insert(['id_usuario' => $user->getKey(), 'id_sede' => 1, 'id_rol' => $id]);
            Route::middleware(['web', 'auth', 'active', 'role:'.$name])->get('/test-role/'.$id, fn () => response('allowed'));
        }
        $this->post(route('login.store'), ['correo' => $user->correo, 'password' => 'Password1!'])->assertRedirect(route('roles.select'));
        $this->withSession(['current_site_id' => 1]);
        $this->get(route('roles.select'))->assertOk()->assertSee('Elige cómo trabajar');
        foreach ($names as $index => $name) {
            $id = $index + 1;
            $this->post(route('roles.update'), ['id_rol' => $id, 'id_sede' => 1])->assertRedirect(route('dashboard'));
            $this->get('/test-role/'.$id)->assertOk();
            $this->get('/test-role/'.($id === 6 ? 7 : 6))->assertForbidden();
            $this->assertAuthenticatedAs($user);
        }
        $this->post(route('roles.update'), ['id_rol' => 6, 'id_sede' => 2])->assertForbidden();
        $this->post(route('roles.update'), ['id_rol' => 999, 'id_sede' => 1])->assertForbidden();
        $this->post(route('roles.update'), ['id_rol' => 7, 'id_sede' => 1])->assertRedirect();
        DB::table('usuario_sede')->where('id_usuario', $user->getKey())->where('id_rol', 7)->delete();
        $this->get('/test-role/7')->assertForbidden();
    }

    public function test_multirole_manager_dashboard_renders_real_site_data(): void
    {
        $this->employees();
        $manager = $this->createUser('Gerente de sede', 9, 1);
        $user = $this->createUser('Mesero', 6, 1);
        Employee::create(['nombre' => $user->name, 'id_usuario' => $user->getKey(), 'id_sede' => 1]);
        Schema::create('servicios', function (Blueprint $t) {
            $t->increments('id_servicio'); $t->integer('id_sede'); $t->string('nombre'); $t->date('fecha');
            $t->time('inicio_hora'); $t->time('fin_hora'); $t->integer('capacidad'); $t->string('estado'); $t->string('estado_aprobacion');
        });
        foreach ([1, 2] as $site) DB::table('servicios')->insert(['id_sede' => $site, 'nombre' => 'Servicio sede '.$site, 'fecha' => now()->toDateString(), 'inicio_hora' => '13:00', 'fin_hora' => '15:00', 'capacidad' => 120, 'estado' => 'En curso', 'estado_aprobacion' => 'Aprobado']);
        $this->actingAs($manager)->withSession(['current_site_id' => 1, 'active_role_id' => 9]);
        $this->get('/dashboard')->assertOk()->assertViewIs('manager.dashboard')->assertViewHas('activeServices', 1)->assertViewHas('employeeCount', 1)
            ->assertSee('Servicio sede 1')->assertDontSee('Servicio sede 2')->assertSee('Gestión de usuarios (Multirrol)');
    }

    public function test_multirole_manager_visual_previews(): void
    {
        $this->employees();
        $manager = $this->createUser('Gerente de sede', 9, 1);
        foreach (['Laura García', 'José Ramírez', 'Roberto Méndez', 'Ana Torres', 'Carlos López'] as $index => $name) {
            $user = $this->createUser('Mesero', 6, 1);
            [$first, $last] = explode(' ', $name);
            $user->update(['nombre' => $first, 'apellido_p' => $last]);
            DB::table('usuario_sede')->insert(['id_usuario' => $user->getKey(), 'id_sede' => 1, 'id_rol' => $index % 2 ? 5 : 7]);
            $employee = Employee::create(['nombre' => $name, 'id_usuario' => $user->getKey(), 'id_sede' => 1]);
            $firstEmployee ??= $employee;
        }
        Schema::create('servicios', function (Blueprint $t) {
            $t->increments('id_servicio'); $t->integer('id_sede'); $t->string('nombre'); $t->date('fecha');
            $t->time('inicio_hora'); $t->time('fin_hora'); $t->integer('capacidad'); $t->string('estado'); $t->string('estado_aprobacion');
        });
        foreach (['Desayuno', 'Comida', 'Cena'] as $index => $name) DB::table('servicios')->insert(['id_sede' => 1, 'nombre' => $name, 'fecha' => now()->toDateString(), 'inicio_hora' => '13:00', 'fin_hora' => '15:00', 'capacidad' => [120, 180, 95][$index], 'estado' => 'Programado', 'estado_aprobacion' => 'Aprobado']);
        DB::table('servicios')->insert(['id_sede' => 1, 'nombre' => 'Comida especial', 'fecha' => now()->toDateString(), 'inicio_hora' => '13:00', 'fin_hora' => '15:00', 'capacidad' => 90, 'estado' => 'Programado', 'estado_aprobacion' => 'Pendiente']);
        $this->actingAs($manager)->withSession(['current_site_id' => 1, 'active_role_id' => 9]);
        $directory = storage_path('app/private/ui-preview');
        if (! is_dir($directory)) mkdir($directory, 0777, true);
        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
        $fileUrl = fn ($path) => 'file:///'.str_replace('\\', '/', $path);
        foreach (['dashboard' => '/dashboard', 'users' => route('manager.employees.index', ['editar' => $firstEmployee->getKey()])] as $name => $url) {
            $response = $this->get($url)->assertOk();
            $html = str_replace(asset('css/manager.css').'?v=20261113', $fileUrl(public_path('css/manager.css')), $response->getContent());
            $html = str_replace([asset('css/manager-users.css').'?v=20261113', asset('js/manager-users.js').'?v=20261113'], [$fileUrl(public_path('css/manager-users.css')), $fileUrl(public_path('js/manager-users.js'))], $html);
            $html = str_replace('</head>', '<link rel="stylesheet" href="'.$fileUrl(public_path('build/'.$manifest['resources/css/app.css']['file'])).'"><link rel="stylesheet" href="'.$fileUrl(public_path('css/manager.css')).'"><link rel="stylesheet" href="'.$fileUrl(public_path('css/manager-users.css')).'"></head>', $html);
            file_put_contents($directory.'/manager-'.$name.'.html', $html);
        }
    }

    public function test_multirole_primary_role_position_and_private_photo_preserve_the_account(): void
    {
        $this->employees();
        DB::table('puestos')->insert(['id_puesto' => 1, 'nombre' => 'Atención de comedor']);
        $manager = $this->createUser('Gerente de sede', 9, 1);
        $user = $this->createUser('Mesero', 6, 1);
        $employee = Employee::create(['nombre' => $user->name, 'id_usuario' => $user->getKey(), 'id_sede' => 1]);
        $originalPassword = $user->password;
        $data = ['nombre' => $user->nombre, 'apellido_p' => $user->apellido_p, 'correo' => $user->correo, 'primary_role_id' => 7, 'role_ids' => [6], 'id_puesto' => 1];
        $this->actingAs($manager)->withSession(['current_site_id' => 1, 'active_role_id' => 9]);
        $this->post(route('manager.employees.update', $employee), [...$data, '_method' => 'PUT', 'foto' => UploadedFile::fake()->image('avatar.jpg', 64, 64)])->assertRedirect();
        $profile = app(EmployeePresentation::class)->profile($employee, [6, 7]);
        $this->assertSame(7, $profile['primary_role_id']);
        $this->assertTrue($profile['has_photo']);
        $this->assertSame($originalPassword, $user->fresh()->password);
        $this->assertSame($user->correo, $user->fresh()->correo);
        $this->assertDatabaseHas('empleados', ['id_empleado' => $employee->getKey(), 'id_puesto' => 1]);
        $this->assertDatabaseHas('usuario_sede', ['id_usuario' => $user->getKey(), 'id_rol' => 6, 'id_sede' => 1]);
        $this->assertDatabaseHas('usuario_sede', ['id_usuario' => $user->getKey(), 'id_rol' => 7, 'id_sede' => 1]);
        $this->get(route('manager.employees.photo', $employee))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get(route('manager.employees.index', ['editar' => $employee->getKey()]))->assertOk()->assertDontSee('name="id_puesto"', false)->assertSee('name="primary_role_id"', false)->assertSee('Principal')->assertSee('Secundario')->assertSee(route('manager.employees.photo', $employee), false);
        $this->put(route('manager.employees.update', $employee), [...$data, 'primary_role_id' => 6, 'role_ids' => [7]])->assertRedirect();
        $this->assertSame(6, app(EmployeePresentation::class)->profile($employee, [6, 7])['primary_role_id']);
        $this->assertTrue(app(EmployeePresentation::class)->profile($employee, [6, 7])['has_photo']);
        $this->put(route('manager.employees.update', $employee), [...$data, 'primary_role_id' => 9])->assertSessionHasErrors('primary_role_id');
        $this->put(route('manager.employees.update', $employee), [...$data, 'id_puesto' => 999])->assertSessionHasErrors('id_puesto');
        $this->post(route('manager.employees.update', $employee), [...$data, '_method' => 'PUT', 'foto' => UploadedFile::fake()->create('document.pdf', 10, 'application/pdf')])->assertSessionHasErrors('foto');
        $otherManager = $this->createUser('Gerente de sede', 9, 2);
        $this->actingAs($otherManager)->withSession(['current_site_id' => 2, 'active_role_id' => 9])->get(route('manager.employees.photo', $employee))->assertForbidden();
        $this->actingAs($user)->withSession(['current_site_id' => 1, 'active_role_id' => 6])->get(route('manager.employees.photo', $employee))->assertForbidden();
    }
}
