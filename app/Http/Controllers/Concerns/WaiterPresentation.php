<?php
namespace App\Http\Controllers\Concerns;

use App\Support\{WaiterWorkspace as W,ChefWorkspace as C,SiteContext};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Schema,Storage};
use Illuminate\Validation\ValidationException;

trait WaiterPresentation
{
    private function validationIf(mixed $condition,int $status,string $message='Revisa los datos de la operación.'):void
    {
        if($condition)throw ValidationException::withMessages(['operacion'=>$message]);
    }
    private function validationUnless(mixed $condition,int $status,string $message='Revisa los datos de la operación.'):void
    {
        $this->validationIf(!$condition,$status,$message);
    }
    public function workspace(Request $request,SiteContext $context)
    {
        $site=$this->site($request,$context); $uid=(int)$request->user()->id_usuario;
        $module=(string)$request->query('modulo','panel');
        abort_unless(in_array($module,['panel','servicios','mesas','atenciones','menu','historial','incidencias']),404);
        $services=W::services($site->id_sede,$uid)->orderByDesc('fecha')->orderBy('inicio_hora')->get();
        $service=$request->filled('id_servicio') ? $services->firstWhere('id_servicio',(int)$request->query('id_servicio')) : ($services->first(fn($s)=>$s->fecha===now()->toDateString() && $s->estado==='En curso') ?? $services->first());
        abort_if($request->filled('id_servicio') && !$service,403);
        $zones=$service ? W::zones($site->id_sede,$uid,$service->id_servicio)->get() : collect();
        $tables=DB::table('mesas')->whereIn('id_zona',$zones->pluck('id_zona'))->orderBy('numero')->get();
        $busy=DB::table('atenciones as a')->join('servicios as s','s.id_servicio','=','a.id_servicio')->where('s.id_sede',$site->id_sede)->whereIn('a.id_mesa',$tables->pluck('id_mesa'))->whereNotIn('a.estado',['Cerrada','Cancelada'])->select('a.*')->get()->keyBy('id_mesa');
        foreach($tables as $table){$table->active=$busy->get($table->id_mesa);$table->operativo=$table->active?'Ocupada':$table->estado_fisico;$table->zone=$zones->firstWhere('id_zona',$table->id_zona);$table->can_open=!$table->active && $table->estado_fisico==='Disponible' && $service?->estado==='En curso';}
        $base=DB::table('atenciones as a')->join('servicios as s','s.id_servicio','=','a.id_servicio')->join('mesas as m','m.id_mesa','=','a.id_mesa')->join('zonas as z','z.id_zona','=','m.id_zona')->leftJoin('servicios_ofrecidos as o','o.id_atencion','=','a.id_atencion')->where('a.id_usuario_mesero',$uid)->where('s.id_sede',$site->id_sede)->where('z.id_sede',$site->id_sede)->select('a.*','s.nombre as servicio','s.fecha','m.numero','m.capacidad','z.nombre as zona','z.id_zona','o.id_servicio_ofrecido','o.descripcion as ofrecido','o.comensales as ofrecido_comensales','o.precio_unitario','o.total','o.estado as ofrecido_estado');
        $all=(clone $base)->orderByDesc('s.fecha')->orderByDesc('a.id_atencion')->get();
        $attentions=$all->where('id_servicio',$service?->id_servicio ?? 0)->values();
        $active=$attentions->whereNotIn('estado',['Cerrada','Cancelada']);
        $historyQuery=(clone $base)->when($request->filled('desde'),fn($q)=>$q->whereDate('s.fecha','>=',$request->query('desde')))->when($request->filled('hasta'),fn($q)=>$q->whereDate('s.fecha','<=',$request->query('hasta')))->when($request->filled('id_servicio'),fn($q)=>$q->where('a.id_servicio',$service->id_servicio))->when($request->filled('id_zona'),fn($q)=>$q->where('z.id_zona',$request->integer('id_zona')))->when($request->filled('id_mesa'),fn($q)=>$q->where('m.id_mesa',$request->integer('id_mesa')))->when($request->filled('estado'),fn($q)=>$q->where('a.estado',$request->query('estado')));
        $history=(clone $historyQuery)->orderByDesc('s.fecha')->orderByDesc('a.id_atencion')->paginate(15)->withQueryString();
        $historyMetrics=(clone $historyQuery)->get();
        $incidents=DB::table('incidencias')->where('id_sede',$site->id_sede)->where('id_usuario',$uid)->orderByDesc('fecha')->get();
        $menu=collect();
        if($service && C::has('menus','publicado')){
            $menu=DB::table('menus as menu')->join('platillos as p','p.id_platillo','=','menu.id_platillo')->leftJoin('categorias as c','c.id_categoria','=','p.id_categoria')->where('menu.id_servicio',$service->id_servicio)->where('menu.publicado',1)->where('p.id_sede',$site->id_sede)->select('menu.*','p.nombre','p.descripcion','p.imagen_url','p.id_categoria','c.nombre as categoria')->orderBy('p.nombre')->get();
        }
        $notesSupported=C::has('atenciones','observaciones');
        $commandMenu = $menu->where('disponible', 1)->filter(fn ($dish) => $dish->precio_venta !== null && $dish->precio_venta >= 0)
            ->unique('id_platillo');
        $visibleAttentions = $module === 'historial' ? $history->getCollection() : $attentions;
        $commands = DB::table('comandas')->whereIn('id_atencion', $visibleAttentions->pluck('id_atencion'))
            ->orderBy('id_comanda')->get()->groupBy('id_atencion');
        $commandNotesSupported = \App\Support\CommandObservations::available();
        $commandNotes = \App\Support\CommandObservations::history($commands->map(fn ($rows) => $rows->last()->id_comanda)->values());
        $commandLines = DB::table('comanda_detalles as d')->join('comandas as c', 'c.id_comanda', '=', 'd.id_comanda')
            ->join('menus as menu', 'menu.id_menu', '=', 'd.id_menu')->join('platillos as p', 'p.id_platillo', '=', 'menu.id_platillo')
            ->whereIn('c.id_atencion', $commands->keys())->orderBy('d.id_comanda_detalle')
            ->get(['c.id_atencion', 'd.*', 'p.nombre'])->groupBy('id_atencion');
        foreach ($visibleAttentions as $attention) {
            $attention->command = $commands->get($attention->id_atencion)?->last();
            $attention->commandLines = $commandLines->get($attention->id_atencion, collect())
                ->reject(fn ($line) => strtolower($line->estado ?? '') === 'cancelado');
            $assigned = $zones->contains('id_zona', $attention->id_zona);
            $attention->commandNotes = $commandNotes->get($attention->command?->id_comanda, collect());
            $attention->canNotifyKitchen = $assigned && $attention->estado === 'En atención' && !$attention->id_servicio_ofrecido
                && $service && !in_array($service->estado, ['Cancelado', 'Finalizado'], true)
                && $attention->commandLines->contains(fn ($line) => (int) $line->id_comanda === (int) $attention->command?->id_comanda
                    && in_array(mb_strtolower(trim($line->estado ?? '')), \App\Support\CommandObservations::OPEN_STATES, true));
            $attention->canOrder = $assigned && $attention->estado === 'En atención' && !$attention->command
                && !$attention->id_servicio_ofrecido && $service?->estado === 'En curso' && $service->fecha === now()->toDateString();
            $attention->canFinalize = $attention->estado === 'En atención' && ($attention->ofrecido_estado === 'Registrado'
                || (!$attention->id_servicio_ofrecido && $assigned && $attention->commandLines->isNotEmpty()
                    && $attention->commandLines->every(fn ($line) => strtolower($line->estado ?? '') === 'entregado')));
            if ($attention->command && !$attention->id_servicio_ofrecido) {
                $attention->ofrecido = 'Comanda #'.$attention->command->id_comanda;
                $attention->total = $attention->commandLines->sum(fn ($line) => $line->cantidad * $line->precio_unitario);
            }
            $attention->commandDraft = $attention->canOrder
                ? $request->session()->get(W::commandDraftKey($site->id_sede, $uid, $attention->id_atencion))
                : null;
        }
        $employee=Schema::hasTable('empleados')?\App\Models\Employee::where('id_sede',$site->id_sede)->where('id_usuario',$uid)->first():null;
        $hasPhoto=$employee && app(\App\Support\EmployeePresentation::class)->photoLocation($employee);
        $link=fn($mod,$extra=[])=>route('waiter.index',array_merge(['modulo'=>$mod],$service?['id_servicio'=>$service->id_servicio]:[],$extra));
        return view('mesero.workspace',compact('site','module','services','service','zones','tables','attentions','active','all','history','historyMetrics','incidents','menu','commandMenu','notesSupported','commandNotesSupported','hasPhoto','link'));
    }
    public function photo(Request $request,SiteContext $context)
    {
        $site=$this->site($request,$context);
        $employee=\App\Models\Employee::where('id_sede',$site->id_sede)->where('id_usuario',$request->user()->id_usuario)->firstOrFail();
        $location=app(\App\Support\EmployeePresentation::class)->photoLocation($employee);abort_unless($location,404);
        return response()->file(Storage::disk($location['disk'])->path($location['path']),['Cache-Control'=>'private, no-store']);
    }
    public function storeIncident(Request $request,SiteContext $context)
    {
        $site=$this->site($request,$context);$uid=(int)$request->user()->id_usuario;
        $data=$request->validate(['id_servicio'=>['required','integer'],'id_zona'=>['nullable','integer'],'id_mesa'=>['nullable','integer'],'tipo'=>['nullable','in:Mobiliario dañado,Falta de limpieza,Falta de cubiertos,Demora en la atención,Problema operativo'],'descripcion'=>['required','string','min:5','max:2000'],'evidencia'=>C::mediaRules(C::has('incidencias','evidencia_url'))]);
        $service=W::services($site->id_sede,$uid)->where('s.id_servicio',$data['id_servicio'])->first();
        if(!$service)throw ValidationException::withMessages(['id_servicio'=>'El servicio no corresponde a tus asignaciones.']);
        $zone=!empty($data['id_zona'])?W::zones($site->id_sede,$uid,$service->id_servicio)->where('z.id_zona',$data['id_zona'])->first():null;
        if(!empty($data['id_zona'])&&!$zone)throw ValidationException::withMessages(['id_zona'=>'Selecciona una zona asignada en esta jornada.']);
        if(!empty($data['id_mesa'])&&(!$zone||!DB::table('mesas')->where('id_zona',$zone->id_zona)->where('id_mesa',$data['id_mesa'])->exists()))throw ValidationException::withMessages(['id_mesa'=>'La mesa no pertenece a la zona seleccionada.']);
        // Existing schema stores report details as text, including the explicitly selected type.
        $description=!empty($data['tipo'])?'['.$data['tipo'].'] '.$data['descripcion']:$data['descripcion'];
        if(mb_strlen($description)>2000)throw ValidationException::withMessages(['descripcion'=>'El tipo y la descripción deben sumar como máximo 2000 caracteres.']);
        C::withMedia($request,['evidencia'=>'evidencia_url'],function($media)use($data,$description,$site,$uid){DB::table('incidencias')->insert(array_merge(['id_sede'=>$site->id_sede,'id_usuario'=>$uid,'fecha'=>now(),'descripcion'=>$description,'estado'=>'Pendiente','id_servicio'=>$data['id_servicio'],'id_zona'=>$data['id_zona']??null,'id_mesa'=>$data['id_mesa']??null],$media));});
        return back()->with('status','Incidencia registrada correctamente.');
    }
    public function notifications(Request $request,SiteContext $context)
    {
        $site=$this->site($request,$context);$uid=(int)$request->user()->id_usuario;
        $items=W::services($site->id_sede,$uid)->whereDate('fecha',now()->toDateString())->whereIn('estado',['Programado','En curso'])->get()->map(fn($s)=>['id'=>'waiter-service-'.$s->id_servicio,'title'=>$s->nombre,'description'=>$s->estado.' · '.substr($s->inicio_hora,0,5).' · Consulta tu asignación','url'=>route('waiter.index',['modulo'=>'servicios','id_servicio'=>$s->id_servicio]),'tone'=>'info','date'=>$s->fecha,'read'=>in_array('waiter-service-'.$s->id_servicio,$request->session()->get('waiter_notification_reads',[]),true)])->values();
        return response()->json(['items'=>$items,'unread'=>$items->where('read',false)->count()]);
    }
    public function readNotifications(Request $request,SiteContext $context)
    {
        $this->site($request,$context);$data=$request->validate(['id'=>['nullable','string','max:100'],'all'=>['nullable','boolean']]);
        $ids=$request->session()->get('waiter_notification_reads',[]);
        $available=array_column($this->notifications($request,$context)->getData(true)['items'],'id');
        if($request->boolean('all')||($data['id']??'')==='all')$ids=array_merge($ids,$available);elseif(in_array($data['id']??null,$available,true))$ids[]=$data['id'];
        $request->session()->put('waiter_notification_reads',array_slice(array_values(array_unique($ids)),-200));return $this->notifications($request,$context);
    }
}
