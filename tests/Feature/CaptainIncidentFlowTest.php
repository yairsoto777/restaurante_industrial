<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CaptainIncidentFlowTest extends OperationsFixtureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::table('incidencias',function(Blueprint $t){$t->integer('id_servicio')->nullable();$t->integer('id_zona')->nullable();$t->integer('id_mesa')->nullable();$t->string('evidencia_url')->nullable();});
        DB::table('zonas')->insert([['id_zona'=>2,'id_sede'=>1,'nombre'=>'Otra zona propia'],['id_zona'=>3,'id_sede'=>2,'nombre'=>'Zona ajena']]);
        DB::table('mesas')->insert(['id_mesa'=>4,'id_zona'=>3,'numero'=>99,'capacidad'=>4,'estado_fisico'=>'Disponible']);
        $this->actingAs(User::findOrFail(7))->withSession(['current_site_id'=>1]);
    }

    public function test_wrong_zone_returns_to_open_form_with_input_instead_of_error_page(): void
    {
        $description='Una silla requiere revisión en la mesa';
        $this->post(route('capitan.incidencias.store'),['descripcion'=>$description,'id_zona'=>2,'id_mesa'=>1,'id_servicio'=>1,'fecha_jornada'=>now()->toDateString()])
            ->assertRedirect(route('capitan.index',['modulo'=>'incidencias','fecha'=>now()->toDateString()]))
            ->assertSessionHasErrorsIn('incident',['id_mesa'])->assertSessionHasInput('descripcion',$description)->assertSessionHasInput('id_zona',2);
        $this->assertSame(1,DB::table('incidencias')->count());
        $this->get(route('capitan.index',['modulo'=>'incidencias']))->assertOk()->assertSee('data-cw-reopen',false)->assertSee($description)->assertSee('La mesa seleccionada no pertenece');
    }

    public function test_json_errors_reject_foreign_relations_and_wrong_jornada_but_allow_general_incidents(): void
    {
        $base=['descripcion'=>'Incidencia de atención de la sede','fecha_jornada'=>now()->toDateString()];
        $this->postJson(route('capitan.incidencias.store'),$base+['id_zona'=>3,'id_mesa'=>4])->assertUnprocessable()->assertJsonValidationErrors(['id_zona','id_mesa']);
        DB::table('servicios')->insert(['id_servicio'=>2,'id_sede'=>1,'nombre'=>'Jornada siguiente','fecha'=>now()->addDay()->toDateString(),'estado'=>'Programado','estado_aprobacion'=>'Aprobado']);
        $this->postJson(route('capitan.incidencias.store'),$base+['id_servicio'=>2])->assertUnprocessable()->assertJsonValidationErrors('id_servicio');
        DB::table('servicios')->where('id_servicio',2)->update(['fecha'=>now()->toDateString(),'estado_aprobacion'=>'Pendiente']);
        $this->postJson(route('capitan.incidencias.store'),$base+['id_servicio'=>2])->assertUnprocessable()->assertJsonValidationErrors('id_servicio');
        $this->postJson(route('capitan.incidencias.store'),$base)->assertCreated()->assertJsonStructure(['message','redirect']);
        $this->assertDatabaseHas('incidencias',['descripcion'=>$base['descripcion'],'id_sede'=>1,'id_zona'=>null,'id_mesa'=>null,'estado'=>'Pendiente']);
    }

    public function test_valid_incident_keeps_evidence_and_appears_in_history_and_correct_live_jornada(): void
    {
        Storage::fake('public');
        $description='Mesa con evidencia y jornada vinculada';
        $response=$this->withHeader('Accept','application/json')->post(route('capitan.incidencias.store'),[
            'descripcion'=>$description,'id_zona'=>1,'id_mesa'=>1,'id_servicio'=>1,'fecha_jornada'=>now()->toDateString(),'evidencia'=>UploadedFile::fake()->image('silla.jpg'),
        ])->assertCreated();
        $incident=DB::table('incidencias')->orderByDesc('id_incidencia')->first();
        $this->assertSame(1,$incident->id_zona);$this->assertSame(1,$incident->id_mesa);
        Storage::disk('public')->assertExists($incident->evidencia_url);
        $this->get($response->json('redirect'))->assertOk()->assertSee($description)->assertSee('cw-notice-success',false)->assertSee('Incidencia registrada correctamente.');
        $this->get(route('capitan.index',['modulo'=>'comedor','id_servicio'=>1]))->assertOk()->assertSee($description)->assertViewHas('incidenciasMapa',fn($items)=>$items->contains('id_incidencia',$incident->id_incidencia));
        DB::table('servicios')->insert(['id_servicio'=>2,'id_sede'=>1,'nombre'=>'Otro servicio','fecha'=>now()->toDateString(),'estado'=>'Programado','estado_aprobacion'=>'Aprobado']);
        $this->get(route('capitan.index',['modulo'=>'comedor','id_servicio'=>2]))->assertOk()->assertDontSee($description)->assertViewHas('incidenciasMapa',fn($items)=>!$items->contains('id_incidencia',$incident->id_incidencia));
    }
}
