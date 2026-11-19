<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

// Reuses the existing SQLite :memory: fixture, never the application database.
class CaptainWorkspaceTest extends OperationsFixtureTestCase
{
    private function captain(): void { $this->actingAs(User::findOrFail(7))->withSession(['current_site_id'=>1]); }

    public function test_captain_workspace_seven_views_render_with_real_fixture_states(): void
    {
        $this->captain();
        foreach (['panel','servicios','zonas','mesas','asignaciones','comedor','incidencias'] as $module) {
            $this->get(route('capitan.index',['modulo'=>$module]))->assertOk()->assertSee('captain-workspace.css')->assertDontSee('Sede no autorizada');
        }
        $html=$this->get(route('capitan.index'))->getContent();
        preg_match('~<nav\s+class="sidebar-nav".*?</nav>~s',$html,$navigation);
        $this->assertSame(7,substr_count($navigation[0] ?? '', 'class="nav-link'));
        $this->get(route('capitan.index',['modulo'=>'comedor']))->assertSee('data-cw-pick="1"',false)->assertSee('Ocupada')->assertSee('Mantenimiento');
        $this->get(route('capitan.index',['modulo'=>'mesas']))->assertSee('Observación conservada')->assertSee('data-cw-table',false);
        $this->get(route('capitan.notifications.index'))->assertOk()->assertJsonStructure(['items','unread']);
    }

    public function test_captain_workspace_rejects_other_sites_and_unauthorized_management(): void
    {
        $this->captain();
        $this->get(route('inventory.index'))->assertForbidden();
        $this->post(route('capitan.mesas.mesas.store'),['id_zona'=>999,'numero'=>20,'capacidad'=>4])->assertNotFound();
        DB::table('servicios')->insert(['id_servicio'=>2,'id_sede'=>2,'nombre'=>'Servicio ajeno','fecha'=>now()->toDateString(),'estado_aprobacion'=>'Aprobado']);
        $this->getJson(route('capitan.index',['id_servicio'=>2]))->assertStatus(422);
    }

    public function test_captain_workspace_duplicate_interval_is_atomic(): void
    {
        $this->captain();
        $count=DB::table('mesas')->count();
        $this->post(route('capitan.mesas.mesas.store'),['id_zona'=>1,'numero'=>1,'cantidad'=>3,'capacidad'=>4])->assertSessionHasErrors('numero');
        $this->assertSame($count,DB::table('mesas')->count());
    }

    public function test_captain_workspace_assignments_are_scoped_and_overlap_is_rejected(): void
    {
        Schema::table('zona_meseros',function(Blueprint $t){$t->unsignedInteger('id_servicio')->nullable();$t->dateTime('fecha_fin')->nullable();});
        $this->captain();
        $payload=['id_zona'=>1,'id_usuario_mesero'=>6,'id_servicio'=>1];
        $this->post(route('capitan.asignaciones.store'),$payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->postJson(route('capitan.asignaciones.store'),$payload)->assertStatus(422);
        DB::table('servicios')->insert(['id_servicio'=>2,'id_sede'=>1,'nombre'=>'Jornada posterior','fecha'=>now()->addDay()->toDateString(),'inicio_hora'=>'08:00:00','fin_hora'=>'23:00:00','estado'=>'Programado','estado_aprobacion'=>'Aprobado']);
        $this->post(route('capitan.asignaciones.store'),array_replace($payload,['id_servicio'=>2]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2,DB::table('zona_meseros')->whereNotNull('id_servicio')->count());
    }

    public function test_captain_workspace_zone_upload_persists_and_keeps_previous_file(): void
    {
        Storage::fake('public');
        Schema::table('zonas',function(Blueprint $t){$t->string('imagen_url')->nullable();$t->string('color')->nullable();$t->text('descripcion')->nullable();});
        $this->captain();
        $route=route('capitan.mesas.zonas.update',1);
        $this->put($route,['nombre'=>'Zona fotografía','color'=>'#139570','imagen'=>UploadedFile::fake()->image('zona.jpg')])->assertRedirect()->assertSessionHasNoErrors();
        $first=DB::table('zonas')->value('imagen_url');Storage::disk('public')->assertExists($first);
        $this->put($route,['nombre'=>'Zona fotografía','imagen'=>UploadedFile::fake()->image('zona-nueva.webp')])->assertRedirect()->assertSessionHasNoErrors();
        $second=DB::table('zonas')->value('imagen_url');
        $this->assertNotSame($first,$second);Storage::disk('public')->assertExists([$first,$second]);
        $this->get(route('capitan.index',['modulo'=>'zonas']))->assertOk()->assertSee($second);
    }

    public function test_captain_workspace_incident_uses_existing_pending_state_and_persistent_evidence(): void
    {
        Storage::fake('public');
        Schema::table('incidencias',fn(Blueprint $t)=>$t->string('evidencia_url')->nullable());
        $this->captain();
        $this->post(route('capitan.incidencias.store'),['descripcion'=>'Mesa con una silla dañada','evidencia'=>UploadedFile::fake()->image('evidencia.png')])->assertRedirect()->assertSessionHasNoErrors();
        $record=DB::table('incidencias')->orderByDesc('id_incidencia')->first();
        $this->assertSame('Pendiente',$record->estado);Storage::disk('public')->assertExists($record->evidencia_url);
    }

    public function test_captain_workspace_waiter_cannot_open_attention_using_another_jornada_assignment(): void
    {
        Schema::table('zona_meseros',fn(Blueprint $t)=>$t->unsignedInteger('id_servicio')->nullable());
        DB::table('zona_meseros')->update(['id_servicio'=>1]);
        DB::table('servicios')->insert(['id_servicio'=>2,'id_sede'=>1,'nombre'=>'Otro turno','fecha'=>now()->toDateString(),'inicio_hora'=>'00:00:00','fin_hora'=>'07:00:00','estado'=>'Programado','estado_aprobacion'=>'Aprobado']);
        $this->actingAs(User::findOrFail(6))->withSession(['current_site_id'=>1]);
        $this->post(route('waiter.attention.store'),['id_mesa'=>3,'id_servicio'=>2,'comensales'=>2])->assertForbidden();
        $this->post(route('waiter.attention.store'),['id_mesa'=>3,'id_servicio'=>1,'comensales'=>2])->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_captain_workspace_uses_manager_employee_photo_and_checks_site(): void
    {
        Storage::fake('public');Storage::fake('local');
        Schema::create('empleados',function(Blueprint $t){$t->increments('id_empleado');$t->unsignedInteger('id_usuario');$t->unsignedInteger('id_sede');$t->string('nombre');});
        $employee=\App\Models\Employee::create(['id_usuario'=>6,'id_sede'=>1,'nombre'=>'Mesero Prueba']);
        app(\App\Support\EmployeePresentation::class)->save($employee,6,UploadedFile::fake()->image('perfil.jpg'));
        $this->captain();
        $url=\App\Support\CaptainWorkspace::photo(1,6);
        $this->assertNotNull($url);
        $this->get($url)->assertOk()->assertHeader('Content-Type','image/jpeg');
        $this->get(route('capitan.index',['modulo'=>'asignaciones']))->assertOk()->assertSee($url,false);
        $employee->update(['id_sede'=>2]);
        $this->get($url)->assertNotFound();
    }
}
