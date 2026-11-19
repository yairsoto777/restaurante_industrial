<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ServiceRecurrence;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Only the new-registration gate/payload and captain integration edge case.
// Does not rerun the previous recurrence, menu, clock or authentication suites.
class RecurrenceRegistrationTest extends OperationsFixtureTestCase
{
    public function test_registration_accepts_the_four_actual_form_payloads_only_with_complete_schema(): void
    {
        $this->assertFalse(ServiceRecurrence::ready());
        $this->actingAs(User::findOrFail(12))->withSession(['active_role_id'=>12,'current_site_id'=>1]);
        $base=['nombre'=>'Servicio aislado','capacidad'=>80,'desde'=>now()->toDateString(),'hasta'=>now()->addDays(7)->toDateString(),'days'=>[now()->dayOfWeekIso]];
        $this->post(route('chef.schedules.store'),$base+['mode'=>'diario','start'=>'08:00','end'=>'10:00'])->assertStatus(503);
        $this->assertSame(1,DB::table('servicios')->count());

        Schema::create('service_schedules',function(Blueprint $t){$t->id();$t->integer('id_sede');$t->integer('id_usuario');$t->unsignedBigInteger('active_revision_id')->nullable();$t->timestamps();});
        Schema::create('service_schedule_revisions',function(Blueprint $t){$t->id();$t->unsignedBigInteger('schedule_id');$t->integer('id_usuario');$t->string('estado');$t->string('scope');$t->date('effective_from');$t->json('settings');$t->integer('reviewer_id')->nullable();$t->text('observaciones')->nullable();$t->timestamp('reviewed_at')->nullable();$t->timestamps();});
        Schema::table('servicios',function(Blueprint $t){$t->unsignedBigInteger('schedule_id')->nullable();$t->unsignedBigInteger('schedule_revision_id')->nullable();$t->integer('id_usuario_registro')->nullable();$t->integer('id_usuario_revision')->nullable();$t->timestamp('revisado_en')->nullable();});
        $this->assertFalse(ServiceRecurrence::ready());
        Schema::table('servicios',fn(Blueprint $t)=>$t->unique(['schedule_id','fecha'],'servicios_schedule_date_unique'));
        $this->assertTrue(ServiceRecurrence::ready());

        foreach(['diario','semanal','personalizado','evento'] as $mode){
            $payload=$base+['mode'=>$mode];
            if($mode==='personalizado')$payload['hours']=[now()->dayOfWeekIso=>['start'=>'08:00','end'=>'10:00']];
            else $payload+=['start'=>'08:00','end'=>'10:00'];
            $this->post(route('chef.schedules.store'),$payload)->assertRedirect(route('chef.services'))->assertSessionHasNoErrors();
        }
        $this->assertSame(4,DB::table('service_schedules')->count());
        $this->assertSame(4,DB::table('service_schedule_revisions')->where('estado','Pendiente')->count());

        // An approved future revision must not rewrite a jornada already assigned by the captain.
        Schema::table('zona_meseros',fn(Blueprint $t)=>$t->integer('id_servicio')->nullable());
        $revision=DB::table('service_schedule_revisions')->first();
        DB::table('service_schedule_revisions')->where('id',$revision->id)->update(['estado'=>'Aprobado']);
        ServiceRecurrence::ensure(1,now()->toDateString(),now()->toDateString());
        $journey=DB::table('servicios')->where('schedule_id',$revision->schedule_id)->first();
        DB::table('zona_meseros')->insert(['id_zona'=>1,'id_usuario_mesero'=>6,'activo'=>1,'id_servicio'=>$journey->id_servicio]);
        $changed=json_decode($revision->settings,true);$changed['capacidad']=30;
        ServiceRecurrence::submit(1,12,$changed,$revision->schedule_id);
        $pending=DB::table('service_schedule_revisions')->latest('id')->value('id');
        ServiceRecurrence::review(1,2,$pending,true,null);
        $this->assertSame(80,DB::table('servicios')->where('id_servicio',$journey->id_servicio)->value('capacidad'));
    }
}
