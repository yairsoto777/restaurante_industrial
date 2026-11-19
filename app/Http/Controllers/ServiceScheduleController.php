<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Support\ChefWorkspace as W;
use App\Support\ServiceRecurrence as R;
use App\Support\SiteContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ServiceScheduleController extends Controller
{
    public function form(Request $request, SiteContext $context, ?int $schedule=null)
    {
        $site=W::site($request,$context);$record=null;$settings=[];
        if($schedule){abort_unless(R::ready(),503);$record=DB::table('service_schedules')->where('id',$schedule)->where('id_sede',$site->id_sede)->first();abort_unless($record,404);
            $revision=DB::table('service_schedule_revisions')->where('schedule_id',$schedule)->where('scope','!=','one')->latest('id')->first();$settings=R::settings($revision);}
        return view('chef.workspace.schedule-form',compact('site','record','settings'));
    }

    public function save(Request $request, SiteContext $context, ?int $schedule=null)
    {
        $site=W::site($request,$context);abort_unless(R::ready(),503,'La programación recurrente requiere autorizar su migración.');
        $data=$request->validate(['nombre'=>'required|string|max:100','descripcion'=>'nullable|string|max:2000','capacidad'=>'required|integer|min:1|max:100000','mode'=>['required',Rule::in(array_keys(R::MODES))],
            'desde'=>$schedule?'required|date_format:Y-m-d':'required|date_format:Y-m-d|after_or_equal:today','hasta'=>'nullable|date_format:Y-m-d|after_or_equal:desde','days'=>'nullable|array','days.*'=>'integer|between:1,7|distinct',
            'start'=>'required_unless:mode,personalizado|nullable|date_format:H:i','end'=>'required_unless:mode,personalizado|nullable|date_format:H:i|after:start',
            'hours'=>'nullable|array','hours.*'=>'array:start,end','hours.*.start'=>'nullable|date_format:H:i','hours.*.end'=>'nullable|date_format:H:i',
            'scope'=>'nullable|in:one,following,future','effective_from'=>'nullable|date_format:Y-m-d|after_or_equal:today','reason'=>'nullable|string|max:1000','suspended'=>'nullable|boolean','service_cover'=>W::mediaRules(true)]);
        $data['days']=$data['mode']==='diario'?range(1,7):array_values(array_unique(array_map('intval',$data['days']??[])));
        unset($data['service_cover']);
        if(in_array($data['mode'],['semanal','personalizado']) && !$data['days'])throw \Illuminate\Validation\ValidationException::withMessages(['days'=>'Selecciona al menos un día de operación.']);
        if($data['mode']==='personalizado')foreach($data['days'] as $day){$h=$data['hours'][$day]??[];if(empty($h['start']) || empty($h['end']) || $h['end']<=$h['start'])throw \Illuminate\Validation\ValidationException::withMessages(['hours'=>'Completa los horarios: la finalización debe ser posterior al inicio en cada día seleccionado.']);}
        $scope=$data['scope']??'future';$from=$data['effective_from']??$data['desde'];
        if($schedule && $scope==='future')$from=max($data['desde'],now('America/Mexico_City')->toDateString());
        if($scope==='one'){$data['desde']=$from;$data['hasta']=$from;}
        $data['suspended']=$request->boolean('suspended');
        if($schedule){$record=DB::table('service_schedules')->where('id',$schedule)->where('id_sede',$site->id_sede)->first();abort_unless($record,404);$last=DB::table('service_schedule_revisions')->where('schedule_id',$schedule)->where('scope','!=','one')->latest('id')->first();$data['cover_url']=R::settings($last)['cover_url']??null;}
        W::withMedia($request,['service_cover'=>'cover_url'],function($images)use($data,$site,$request,$schedule,$scope,$from){R::submit($site->id_sede,$request->user()->id_usuario,array_merge($data,$images),$schedule,$scope,$from);});
        return redirect()->route('chef.services')->with('status','Programación enviada a revisión. La versión autorizada se conserva hasta su aprobación.');
    }

    public function show(Request $request, SiteContext $context, int $schedule)
    {
        $site=W::site($request,$context);abort_unless(R::ready(),503);
        $record=DB::table('service_schedules')->where('id',$schedule)->where('id_sede',$site->id_sede)->first();abort_unless($record,404);
        R::ensure($site->id_sede,now()->toDateString(),now()->addDays(35)->toDateString());
        $history=DB::table('service_schedule_revisions')->where('schedule_id',$schedule)->latest('id')->get();
        $services=Service::where('schedule_id',$schedule)->where('id_sede',$site->id_sede)->orderByDesc('fecha')->paginate(35);
        return view('chef.workspace.schedule-detail',compact('site','record','history','services'));
    }

    public function review(Request $request, SiteContext $context, int $revision)
    {
        $user=$request->user();$site=$context->currentFor($request,$user);abort_unless($site && $user->isManager() && $user->isManagerAtSite($site->id_sede),403);
        abort_unless(R::ready(),503);$data=$request->validate(['decision'=>'required|in:approve,reject','observaciones'=>'required_if:decision,reject|nullable|string|max:1000']);
        R::review($site->id_sede,$user->id_usuario,$revision,$data['decision']==='approve',$data['observaciones']??null);
        R::ensure($site->id_sede,now()->toDateString(),now()->addDays(35)->toDateString());
        return redirect()->route('manager.services.index')->with('status','Revisión de programación registrada.');
    }

    public function copyMenu(Request $request, SiteContext $context, Service $service)
    {
        $site=W::site($request,$context);abort_unless((int)$service->id_sede===(int)$site->id_sede,404);
        $request->validate(['source'=>'required|integer']);
        DB::transaction(function()use($request,$service,$site){
            $target=Service::where('id_servicio',$service->id_servicio)->lockForUpdate()->first();
            abort_unless($target->estado==='Programado',422,'Solo puedes copiar a una jornada programada.');
            $source=Service::where('id_servicio',$request->integer('source'))->where('id_sede',$site->id_sede)->first();abort_unless($source,404);
            abort_if(DB::table('menus')->where('id_servicio',$target->id_servicio)->exists(),422,'La jornada ya tiene menú; edítalo para conservar sus relaciones.');
            $rows=DB::table('menus')->join('platillos','platillos.id_platillo','=','menus.id_platillo')->where('menus.id_servicio',$source->id_servicio)->where('platillos.id_sede',$site->id_sede)->get(['menus.*']);
            foreach($rows as $row){$data=(array)$row;unset($data['id_menu']);$data['id_servicio']=$target->id_servicio;$data['publicado']=0;DB::table('menus')->insert($data);}
            if(W::has('servicios','menu_cover_url'))$target->forceFill(['menu_cover_url'=>$source->menu_cover_url])->save();
        });
        return redirect()->route('chef.menus',['service'=>$service->id_servicio])->with('status','Menú copiado como borrador independiente; revisa disponibilidad y publicación.');
    }
}
