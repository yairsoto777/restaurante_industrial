<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Support\ChefWorkspace as W;
use App\Support\SiteContext;
use App\Support\WarehouseWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class ChefWorkspaceController extends Controller
{
    private function page(Request $request, SiteContext $context, string $view, array $data = [])
    {
        $site = W::site($request,$context);
        return view('chef.workspace.'.$view, $data + compact('site'));
    }

    private function services(int $site)
    {
        \App\Support\ServiceRecurrence::ensure($site,now('America/Mexico_City')->toDateString(),now('America/Mexico_City')->addDays(35)->toDateString());
        return Service::query()->where('id_sede',$site)->orderBy('fecha')->orderBy('inicio_hora');
    }

    private function dishes(int $site)
    {
        return DB::table('platillos as p')->leftJoin('categorias as c','c.id_categoria','=','p.id_categoria')
            ->where('p.id_sede',$site)->select('p.*','c.nombre as categoria')->orderBy('p.nombre');
    }

    private function menuRows(int $site)
    {
        if (!W::tables(['menus','platillos','servicios','categorias'])) return collect();
        return DB::table('menus as m')->join('platillos as p','p.id_platillo','=','m.id_platillo')
            ->join('servicios as s','s.id_servicio','=','m.id_servicio')->leftJoin('categorias as c','c.id_categoria','=','p.id_categoria')
            ->where('s.id_sede',$site)->where('p.id_sede',$site)
            ->select('m.*','p.nombre','p.imagen_url','c.nombre as categoria')
            ->selectRaw(W::has('platillos','disponible') ? 'p.disponible as catalogo_disponible' : 'NULL as catalogo_disponible')->orderBy('p.nombre')->get();
    }

    private function commands(int $site)
    {
        if (!W::tables(['comanda_detalles','comandas','atenciones','servicios','menus','platillos','mesas'])) return collect();
        return DB::table('comanda_detalles as d')->join('comandas as c','c.id_comanda','=','d.id_comanda')
            ->join('atenciones as a','a.id_atencion','=','c.id_atencion')->join('servicios as s','s.id_servicio','=','a.id_servicio')
            ->join('menus as m','m.id_menu','=','d.id_menu')->join('platillos as p','p.id_platillo','=','m.id_platillo')
            ->leftJoin('mesas as t','t.id_mesa','=','a.id_mesa')->where('s.id_sede',$site)->where('p.id_sede',$site)
            ->whereColumn('m.id_servicio','s.id_servicio')->where('s.estado_aprobacion','Aprobado')
            ->get(['d.*','c.id_comanda','c.fecha_apertura','c.observaciones as notas_comanda','s.id_servicio','s.nombre as servicio','s.fecha','t.numero as mesa','p.nombre','p.imagen_url']);
    }

    private function stock(int $site)
    {
        return W::tables(['insumo_sedes','insumos','unidades']) ? WarehouseWorkspace::stock($site) : collect();
    }

    private function incidents(int $site)
    {
        if (!W::tables(['incidencias','usuarios'])) return collect();
        $query = DB::table('incidencias as i')->leftJoin('usuarios as u','u.id_usuario','=','i.id_usuario')->where('i.id_sede',$site);
        if (W::has('incidencias','id_recepcion_detalle')) $query->whereNull('i.id_recepcion_detalle');
        $query->select('i.*','u.nombre as responsable');
        if (W::has('incidencias','id_servicio')) $query->leftJoin('servicios as s',function ($join) use ($site) {
            $join->on('s.id_servicio','=','i.id_servicio')->where('s.id_sede',$site);
        })->addSelect('s.nombre as servicio');
        return $query->orderByDesc('i.fecha')->get();
    }

    public function dashboard(Request $request, SiteContext $context)
    {
        $site = W::site($request,$context); $services = $this->services($site->id_sede)->get();
        $commands = $this->commands($site->id_sede); $stock = $this->stock($site->id_sede); $incidents = $this->incidents($site->id_sede);
        $today = now('America/Mexico_City')->toDateString();
        $metrics = [
            ['Servicios programados',$services->where('estado','Programado')->count(),'calendar','info'],
            ['Pendientes de aprobación',$services->where('estado_aprobacion','Pendiente')->count(),'clock','waiting'],
            ['Servicios en curso',$services->where('estado','En curso')->count(),'activity','purple'],
            ['Servicios finalizados',$services->where('estado','Finalizado')->count(),'check','done'],
            ['Platillos registrados',DB::table('platillos')->where('id_sede',$site->id_sede)->count(),'dish','orange'],
            ['Comandas pendientes',$commands->where('estado','pendiente')->pluck('id_comanda')->unique()->count(),'layers','danger'],
        ];
        $upcoming = $services->filter(fn ($s) => $s->fecha && $s->fecha->toDateString() >= $today && $s->estado === 'Programado')->take(5);
        $weekStart = now('America/Mexico_City')->startOfWeek();
        $week = collect(range(0,6))->map(fn ($i) => ['label'=>$weekStart->copy()->addDays($i)->format('d/m'),'total'=>$services->filter(fn ($s)=>$s->fecha?->toDateString() === $weekStart->copy()->addDays($i)->toDateString())->count()]);
        $distribution = $services->groupBy('estado')->map->count();
        $todayIds = $services->filter(fn ($s)=>$s->fecha?->toDateString() === $today && $s->estado_aprobacion === 'Aprobado' && $s->estado !== 'Cancelado')->pluck('id_servicio');
        $todayMenu = $this->menuRows($site->id_sede)->whereIn('id_servicio',$todayIds)->where('publicado',1)->where('disponible',1);
        return $this->page($request,$context,'dashboard',compact('metrics','upcoming','week','distribution','todayMenu','commands','stock','incidents','services'));
    }

    public function servicesIndex(Request $request, SiteContext $context)
    {
        $site = W::site($request,$context);
        $filters = $request->validate(['q'=>'nullable|string|max:150','fecha'=>'nullable|date_format:Y-m-d','estado'=>['nullable',Rule::in(['Programado','En curso','Finalizado','Cancelado'])],'aprobacion'=>['nullable',Rule::in(['Pendiente','Aprobado','Rechazado'])]]);
        $services = $this->services($site->id_sede)->when(\App\Support\ServiceRecurrence::ready(),fn($q)=>$q->whereNull('schedule_id'))->when($filters['q'] ?? null,fn ($q,$v)=>$q->where('nombre','like','%'.$v.'%'))
            ->when($filters['fecha'] ?? null,fn ($q,$v)=>$q->whereDate('fecha',$v))->when($filters['estado'] ?? null,fn ($q,$v)=>$q->where('estado',$v))
            ->when($filters['aprobacion'] ?? null,fn ($q,$v)=>$q->where('estado_aprobacion',$v))->paginate(12)->withQueryString();
        $counts = $this->menuRows($site->id_sede)->groupBy('id_servicio')->map(fn ($rows)=>$rows->pluck('id_platillo')->unique()->count());
        return $this->page($request,$context,'services',compact('services','filters','counts'));
    }

    public function serviceForm(Request $request, SiteContext $context, ?Service $service = null)
    {
        if(!$service)return app(ServiceScheduleController::class)->form($request,$context);
        $site = W::site($request,$context);
        if ($service) {
            abort_unless((int)$service->id_sede === (int)$site->id_sede,404);
            if($service->schedule_id)return redirect()->route('chef.schedules.edit',$service->schedule_id);
            abort_unless(in_array($service->estado_aprobacion,['Pendiente','Rechazado',null],true),422,'El servicio aprobado no se puede editar.');
        }
        $service ??= new Service(['estado'=>'Programado','estado_aprobacion'=>'Pendiente']);
        return $this->page($request,$context,'service-form',compact('service'));
    }

    public function saveService(Request $request, SiteContext $context, ?Service $service = null)
    {
        abort_if($service?->schedule_id,422,'Edita la programación o registra una excepción para esta jornada.');
        $site = W::site($request,$context);
        $data = $request->validate(['nombre'=>'required|string|max:100','fecha'=>'required|date','inicio_hora'=>'required|date_format:H:i','fin_hora'=>'required|date_format:H:i|after:inicio_hora','capacidad'=>'required|integer|min:1',
            'descripcion'=>W::has('servicios','descripcion') ? 'nullable|string|max:2000' : 'prohibited',
            'service_cover'=>W::mediaRules(W::has('servicios','cover_url'))]);
        unset($data['service_cover']);
        W::withMedia($request,['service_cover'=>'cover_url'],function ($images) use ($site,$data,$service,$request) {
            if ($service) {
                $locked = Service::whereKey($service->id_servicio)->where('id_sede',$site->id_sede)->lockForUpdate()->firstOrFail();
                abort_unless(in_array($locked->estado_aprobacion,['Pendiente','Rechazado',null],true),422,'El servicio ya fue aprobado.');
                foreach (['menus','atenciones','grupos'] as $table) {
                    abort_if(W::has($table,'id_servicio') && DB::table($table)->where('id_servicio',$locked->id_servicio)->exists(),422,'El servicio ya tiene operaciones y no puede modificarse.');
                }
                $locked->forceFill($data+$images+['estado_aprobacion'=>'Pendiente','id_usuario_revision'=>null,'revisado_en'=>null,'observaciones_revision'=>null])->save();
            } else {
                (new Service())->forceFill($data+$images+['id_sede'=>$site->id_sede,'estado'=>'Programado','estado_aprobacion'=>'Pendiente','id_usuario_registro'=>$request->user()->id_usuario])->save();
            }
        });
        return redirect()->route('chef.services')->with('status','Servicio guardado y enviado a revisión del gerente.');
    }

    public function dishesIndex(Request $request, SiteContext $context)
    {
        $site = W::site($request,$context);
        $filters = $request->validate(['q'=>'nullable|string|max:150','categoria'=>'nullable|integer','disponible'=>'nullable|in:0,1']);
        $base = $this->dishes($site->id_sede);
        $total = (clone $base)->count();
        $dishes = $base->when($filters['q'] ?? null,fn ($q,$v)=>$q->where('p.nombre','like','%'.$v.'%'))
            ->when($filters['categoria'] ?? null,fn ($q,$v)=>$q->where('p.id_categoria',$v));
        if (W::has('platillos','disponible') && isset($filters['disponible'])) $dishes->where('p.disponible',$filters['disponible']);
        $dishes = $dishes->paginate(12)->withQueryString();
        $categories = DB::table('categorias')->orderBy('nombre')->get();
        return $this->page($request,$context,'dishes',compact('dishes','categories','total','filters'));
    }

    public function dishForm(Request $request, SiteContext $context, ?int $dish = null)
    {
        $site = W::site($request,$context);
        $record = $dish ? $this->dishes($site->id_sede)->where('p.id_platillo',$dish)->first() : null;
        abort_if($dish && !$record,404);
        $categories = DB::table('categorias')->orderBy('nombre')->get();
        return $this->page($request,$context,'dish-form',compact('record','categories'));
    }

    public function dishShow(Request $request, SiteContext $context, int $dish)
    {
        $site = W::site($request,$context); $record = $this->dishes($site->id_sede)->where('p.id_platillo',$dish)->first(); abort_unless($record,404);
        $uses = $this->menuRows($site->id_sede)->where('id_platillo',$dish);
        $recipe = W::tables(['receta_detalles','insumos','unidades']) ? DB::table('receta_detalles as r')->join('insumos as i','i.id_insumo','=','r.id_insumo')->leftJoin('unidades as u','u.id_unidad','=','i.id_unidad_base')->where('r.id_platillo',$dish)->get(['i.nombre','r.cantidad_por_porcion','u.codigo as unidad']) : collect();
        return $this->page($request,$context,'dish-detail',compact('record','uses','recipe'));
    }

    public function saveDish(Request $request, SiteContext $context, ?int $dish = null)
    {
        $site = W::site($request,$context);
        if ($dish) abort_unless(DB::table('platillos')->where('id_sede',$site->id_sede)->where('id_platillo',$dish)->exists(),404);
        $data = $request->validate(['nombre'=>'required|string|max:150','id_categoria'=>'required|integer|exists:categorias,id_categoria','descripcion'=>'nullable|string|max:2000',
            'dish_photo'=>W::mediaRules(W::has('platillos','imagen_url')),
            'precio_referencia'=>W::has('platillos','precio_referencia') ? 'nullable|numeric|decimal:0,2|min:0|max:99999999.99' : 'prohibited',
            'disponible'=>W::has('platillos','disponible') ? 'nullable|boolean' : 'prohibited']);
        unset($data['dish_photo']);
        W::withMedia($request,['dish_photo'=>'imagen_url'],function ($images) use ($site,$dish,$data) {
            if ($dish) DB::table('platillos')->where('id_sede',$site->id_sede)->where('id_platillo',$dish)->update($data+$images);
            else DB::table('platillos')->insert($data+$images+['id_sede'=>$site->id_sede]);
        });
        return redirect()->route('chef.dishes')->with('status','Platillo guardado. Su fotografía y receta se conservan si no se reemplazan.');
    }

    public function categoryStore(Request $request, SiteContext $context)
    {
        W::site($request,$context);
        $data = $request->validate(['nombre'=>'required|string|max:100|unique:categorias,nombre']);
        DB::table('categorias')->insert(['nombre'=>trim($data['nombre'])]);
        return back()->with('status','Categoría registrada en el catálogo compartido.');
    }

    public function menus(Request $request, SiteContext $context)
    {
        if($request->filled('schedule')) {
            $site=W::site($request,$context);abort_unless(\App\Support\ServiceRecurrence::ready(),503);
            $request->validate(['schedule'=>'required|integer','date'=>'nullable|date_format:Y-m-d','planner'=>'nullable|in:week,month']);
            abort_unless(DB::table('service_schedules')->where('id',$request->integer('schedule'))->where('id_sede',$site->id_sede)->exists(),404);
            $date=\Illuminate\Support\Carbon::parse($request->input('date',now()->toDateString()));
            $from=$request->input('planner')==='month'?$date->copy()->startOfMonth():$date->copy()->startOfWeek();
            $until=$request->input('planner')==='month'?$date->copy()->endOfMonth():$date->copy()->endOfWeek();
            \App\Support\ServiceRecurrence::ensure($site->id_sede,$from->toDateString(),$until->toDateString());
            if(!$request->filled('service'))$request->merge(['service'=>Service::where('schedule_id',$request->integer('schedule'))->whereDate('fecha',$date)->where('id_sede',$site->id_sede)->value('id_servicio')]);
        }
        $site = W::site($request,$context); $services = $this->services($site->id_sede)->when($request->filled('schedule') && \App\Support\ServiceRecurrence::ready(),fn($q)=>$q->where('schedule_id',$request->integer('schedule')))->get();
        $request->validate(['service'=>'nullable|integer','q'=>'nullable|string|max:150','categoria'=>'nullable|integer']);
        $service = $request->filled('service') ? $services->firstWhere('id_servicio',$request->integer('service')) : ($request->filled('schedule') ? $services->first(fn($s)=>$s->fecha?->toDateString()===$request->input('date',now()->toDateString())) : $services->first());
        abort_if($request->filled('service') && !$service,404);
        $dishes = $this->dishes($site->id_sede)->get();
        $selected = $service ? $this->menuRows($site->id_sede)->where('id_servicio',$service->id_servicio)->keyBy('id_platillo') : collect();
        $categories = DB::table('categorias')->orderBy('nombre')->get();
        return $this->page($request,$context,'menus',compact('services','service','dishes','selected','categories'));
    }

    public function saveMenu(Request $request, SiteContext $context, Service $service)
    {
        $site = W::site($request,$context); abort_unless((int)$service->id_sede === (int)$site->id_sede,404);
        $request->validate(['menu_cover'=>W::mediaRules(W::has('servicios','menu_cover_url')),'especial_chef'=>'nullable|array','especial_chef.*'=>W::has('menus','especial_chef') ? 'boolean' : 'prohibited','platillos'=>'nullable|array','platillos.*'=>'integer','publicado'=>'nullable|array']);
        if (W::has('platillos','disponible')) {
            $flags = $request->input('publicado',[]);
            foreach (DB::table('platillos')->where('id_sede',$site->id_sede)->where('disponible',false)->pluck('id_platillo') as $id) $flags[$id] = 0;
            $request->merge(['publicado'=>$flags]);
        }
        W::withMedia($request,['menu_cover'=>'menu_cover_url'],function ($images) use ($request,$context,$service) {
            app(MenuController::class)->store($request,$service,$context);
            if ($images) DB::table('servicios')->where('id_servicio',$service->id_servicio)->update($images);
            if (W::has('menus','especial_chef')) {
                $ids = collect($request->input('platillos',[]))->map(fn ($id)=>(int)$id);
                DB::table('menus')->where('id_servicio',$service->id_servicio)->update(['especial_chef'=>false]);
                foreach ($ids as $id) DB::table('menus')->where('id_servicio',$service->id_servicio)->where('id_platillo',$id)->update(['especial_chef'=>(bool)$request->input('especial_chef.'.$id,false)]);
            }
        });
        return redirect()->route('chef.menus',['service'=>$service->id_servicio])->with('status','Menú guardado. La publicación sigue sujeta a la aprobación del servicio.');
    }

    public function commandsIndex(Request $request, SiteContext $context)
    {
        $site = W::site($request,$context); $request->validate(['service'=>'nullable|integer','fecha'=>'nullable|date_format:Y-m-d']);
        $commands = $this->commands($site->id_sede)->when($request->filled('service'),fn ($rows)=>$rows->where('id_servicio',$request->integer('service')))
            ->when($request->filled('fecha'),fn ($rows)=>$rows->filter(fn ($r)=>(string)$r->fecha === $request->fecha));
        $services = $this->services($site->id_sede)->where('estado_aprobacion','Aprobado')->get();
        $available = W::tables(['comanda_detalles','comandas','atenciones','servicios','menus','platillos','mesas']);
        return $this->page($request,$context,'commands',compact('commands','services','available'));
    }

    public function stockIndex(Request $request, SiteContext $context)
    {
        $site = W::site($request,$context); $request->validate(['q'=>'nullable|string|max:150','estado'=>'nullable|in:Suficiente,Bajo,Agotado','categoria'=>'nullable|string|max:100']);
        $all = $this->stock($site->id_sede); $categories = $all->pluck('categoria')->filter()->unique();
        $stock = $all->filter(fn ($r)=>(!$request->filled('q') || str_contains(mb_strtolower($r->nombre),mb_strtolower($request->q))) && (!$request->filled('estado') || $r->estado_stock === $request->estado) && (!$request->filled('categoria') || $r->categoria === $request->categoria));
        return $this->page($request,$context,'stock',compact('stock','categories','all'));
    }

    public function incidentsIndex(Request $request, SiteContext $context)
    {
        $site = W::site($request,$context); $request->validate(['q'=>'nullable|string|max:150','estado'=>'nullable|string|max:50']);
        $all = $this->incidents($site->id_sede); $states = $all->pluck('estado')->filter()->unique();
        $incidents = $all->filter(fn ($r)=>(!$request->filled('q') || str_contains(mb_strtolower($r->descripcion),mb_strtolower($request->q))) && (!$request->filled('estado') || $r->estado === $request->estado));
        $services = $this->services($site->id_sede)->get();
        return $this->page($request,$context,'incidents',compact('incidents','services','states'));
    }

    public function incidentStore(Request $request, SiteContext $context)
    {
        $site = W::site($request,$context);
        $data = $request->validate(['descripcion'=>'required|string|max:5000','id_servicio'=>W::has('incidencias','id_servicio') ? ['nullable','integer',Rule::exists('servicios','id_servicio')->where('id_sede',$site->id_sede)] : 'prohibited','evidence'=>W::mediaRules(W::has('incidencias','evidencia_url'))]);
        unset($data['evidence']);
        W::withMedia($request,['evidence'=>'evidencia_url'],fn ($images)=>DB::table('incidencias')->insert($data+$images+['id_sede'=>$site->id_sede,'id_usuario'=>$request->user()->id_usuario,'fecha'=>now(),'estado'=>'Pendiente']));
        return redirect()->route('chef.incidents')->with('status','Incidencia registrada para seguimiento en esta sede.');
    }

    private function generatorData(Request $request, int $site): array
    {
        $saved = W::tables(['kitchen_menu_designs']) ? DB::table('kitchen_menu_designs')->where('id_sede',$site)->where('id_usuario',$request->user()->id_usuario)->first() : null;
        $defaults = $saved ? (json_decode($saved->settings,true) ?: []) : [];
        $filters = $request->validate(['periodo'=>'nullable|in:diario,semanal,quincenal,mensual,personalizado','desde'=>'nullable|date_format:Y-m-d','hasta'=>'nullable|date_format:Y-m-d|after_or_equal:desde','service'=>'nullable|integer','tipo'=>'nullable|string|max:100','template'=>['nullable',Rule::in(array_keys(\App\Support\ChefMenuDocument::TEMPLATES))],'titulo'=>'nullable|string|max:150','subtitulo'=>'nullable|string|max:250','comedor'=>'nullable|string|max:150','color'=>'nullable|regex:/^#[0-9a-fA-F]{6}$/','color_secundario'=>'nullable|regex:/^#[0-9a-fA-F]{6}$/','tamano_titulo'=>'nullable|integer|between:28,64','orientacion'=>'nullable|in:vertical,horizontal','papel'=>'nullable|in:Carta,A4','fotos'=>'nullable|boolean','precios'=>'nullable|boolean','descripciones'=>'nullable|boolean','horarios'=>'nullable|boolean','destacar_especial'=>'nullable|boolean','publico'=>'nullable|boolean','dias'=>'nullable|array','dias.*'=>'integer|between:1,7','dias_presentes'=>'nullable|boolean']);
        $filters += $request->validate(['schedule'=>'nullable|integer']);
        if(!empty($filters['schedule']))abort_unless(\App\Support\ServiceRecurrence::ready() && DB::table('service_schedules')->where('id',$filters['schedule'])->where('id_sede',$site)->exists(),404);
        $settings = array_merge(['periodo'=>'semanal','desde'=>now('America/Mexico_City')->startOfWeek()->toDateString(),'template'=>'corporativa','titulo'=>'El sabor de cada día','subtitulo'=>'','color'=>'#17375e','color_secundario'=>'#c3a36a','tamano_titulo'=>44,'orientacion'=>'vertical','papel'=>'A4','fotos'=>1,'precios'=>0,'descripciones'=>1,'horarios'=>1,'destacar_especial'=>1,'publico'=>0,'dias'=>range(1,7)],$defaults,$filters);
        if($request->boolean('dias_presentes') && !$request->has('dias'))$settings['dias']=[];
        foreach(['template'=>'corporativa','periodo'=>'semanal','desde'=>now('America/Mexico_City')->startOfWeek()->toDateString(),'tamano_titulo'=>44,'orientacion'=>'vertical','papel'=>'A4'] as $key=>$default)$settings[$key] ??= $default;
        $palettes=['elegante'=>['#171813','#c3a36a'],'corporativa'=>['#17375e','#b49a70'],'gastronomica'=>['#465338','#69734b'],'contemporanea'=>['#222224','#ed4b45'],'minimalista'=>['#303d34','#8b978b'],'revista'=>['#342d27','#b45c36']];
        if(!isset($filters['color']) && !isset($defaults['color']))$settings['color']=$palettes[$settings['template']][0];
        if(!isset($filters['color_secundario']) && !isset($defaults['color_secundario']))$settings['color_secundario']=$palettes[$settings['template']][1];
        $start = \Illuminate\Support\Carbon::parse($settings['desde']);
        $preservedEnd=!array_intersect(['periodo','desde','hasta'],array_keys($filters)) ? ($defaults['hasta'] ?? null) : null;
        $customEnd = $settings['periodo'] === 'personalizado' ? ($settings['hasta'] ?? null) : $preservedEnd;
        $end = isset($filters['hasta']) || $customEnd ? \Illuminate\Support\Carbon::parse($filters['hasta'] ?? $customEnd) : match ($settings['periodo']) {
            'diario'=>$start->copy(),'quincenal'=>$start->copy()->addDays(13),'mensual'=>$start->copy()->addMonthNoOverflow()->subDay(),default=>$start->copy()->addDays(6),
        };
        abort_if($start->diffInDays($end) > 366,422,'El periodo máximo es de un año.');
        $settings['hasta'] = $end->toDateString();
        \App\Support\ServiceRecurrence::ensure($site,$start->toDateString(),$end->toDateString());
        $services = $this->services($site)->whereBetween('fecha',[$start->toDateString(),$end->toDateString()])
            ->when($settings['service'] ?? null,fn ($q,$v)=>$q->where('id_servicio',$v))->when($settings['tipo'] ?? null,fn ($q,$v)=>$q->where('nombre',$v))->get();
        if(!empty($settings['schedule']))$services=$services->where('schedule_id',(int)$settings['schedule']);
        if ($settings['publico']) $services = $services->where('estado_aprobacion','Aprobado')->whereNotIn('estado',['Cancelado']);
        $menus = $this->menuRows($site)->whereIn('id_servicio',$services->pluck('id_servicio'));
        if ($settings['publico']) $menus = $menus->where('publicado',1)->where('disponible',1)->filter(fn ($row)=>$row->catalogo_disponible === null || (bool)$row->catalogo_disponible);
        $descriptions=DB::table('platillos')->where('id_sede',$site)->whereIn('id_platillo',$menus->pluck('id_platillo'))->pluck('descripcion','id_platillo');
        $menus->each(function ($row) use ($descriptions) {$row->descripcion=$descriptions->get($row->id_platillo);});
        $days = collect();
        for ($day=$start->copy(); $day->lte($end); $day->addDay()) {
            if (!in_array($day->dayOfWeekIso,array_map('intval',$settings['dias']),true)) continue;
            $date = $day->toDateString();
            $days->push(['date'=>$day->copy(),'services'=>$services->filter(fn ($s)=>$s->fecha?->toDateString() === $date)]);
        }
        return compact('settings','days','menus','saved');
    }

    public function incidentEvidence(Request $request, SiteContext $context, int $incident)
    {
        $site = W::site($request,$context);
        abort_unless(W::has('incidencias','evidencia_url'),503,'El esquema de evidencias está pendiente de habilitación.');
        $request->validate(['evidence'=>W::mediaRules(true)]);
        W::withMedia($request,['evidence'=>'evidencia_url'],function ($images) use ($request,$site,$incident) {
            $query = DB::table('incidencias')->where('id_incidencia',$incident)->where('id_sede',$site->id_sede)->where('id_usuario',$request->user()->id_usuario);
            if (W::has('incidencias','id_recepcion_detalle')) $query->whereNull('id_recepcion_detalle');
            $record = (clone $query)->lockForUpdate()->first();
            abort_unless($record,404);
            abort_if(in_array(mb_strtolower($record->estado ?? ''),['cerrada','cerrado','resuelta','resuelto'],true),422,'La incidencia ya fue atendida.');
            if ($images) $query->update($images);
        });
        return redirect()->route('chef.incidents')->with('status','Evidencia guardada. La descripción y el estado de la incidencia se conservan.');
    }

    public function generator(Request $request, SiteContext $context)
    {
        $site = W::site($request,$context); $data = $this->generatorData($request,$site->id_sede);
        $services = $this->services($site->id_sede)->get();
        $finalRequest=$request->duplicate();$finalRequest->setLaravelSession($request->session());$finalRequest->merge(['publico'=>1]);
        $finalData=$this->generatorData($finalRequest,$site->id_sede);
        $document=\App\Support\ChefMenuDocument::build($site,$data);
        $finalDocument=\App\Support\ChefMenuDocument::build($site,$finalData);
        return view('chef.generator.editor',$data+compact('site','services','document','finalDocument'));
    }

    private function generatorImages(Request $request): array
    {
        $request->validate(['design_logo'=>W::mediaRules(true),'design_background'=>W::mediaRules(true)]);
        $images=[];
        foreach(['design_logo','design_background'] as $field)if($request->hasFile($field)) {
            $file=$request->file($field);$images[$field]='data:'.$file->getMimeType().';base64,'.base64_encode(file_get_contents($file->getRealPath()));
        }
        return $images;
    }

    public function generatorPreview(Request $request, SiteContext $context)
    {
        $site=W::site($request,$context);$images=$this->generatorImages($request);
        $data=$this->generatorData($request,$site->id_sede);
        $document=\App\Support\ChefMenuDocument::build($site,$data,$images);
        $request->merge(['publico'=>1]);
        $finalDocument=\App\Support\ChefMenuDocument::build($site,$this->generatorData($request,$site->id_sede),$images);
        $templates=[];
        foreach(\App\Support\ChefMenuDocument::TEMPLATES as $key=>$label)$templates[$key]=['preview'=>\App\Support\ChefMenuDocument::render($document,$key),'final'=>\App\Support\ChefMenuDocument::render($finalDocument,$key)];
        return response()->json(['templates'=>$templates,'pending'=>$document['pending'],'count'=>$document['dishCount'],'finalCount'=>$finalDocument['dishCount']])->header('Cache-Control','no-store');
    }

    public function generatorPrint(Request $request, SiteContext $context)
    {
        $site=W::site($request,$context);$images=$this->generatorImages($request);$request->merge(['publico'=>1]);
        $document=\App\Support\ChefMenuDocument::build($site,$this->generatorData($request,$site->id_sede),$images,true);
        abort_unless($document['dishCount'],422,'No hay platillos publicados y disponibles en servicios aprobados para este periodo.');
        return response(\App\Support\ChefMenuDocument::standalone($document))->header('Content-Type','text/html; charset=UTF-8')->header('Cache-Control','no-store');
    }

    public function generatorPdf(Request $request, SiteContext $context)
    {
        $html=$this->generatorPrint($request,$context)->getContent();
        try {$pdf=\App\Support\ChefMenuPdf::generate($html);}
        catch(\RuntimeException $error){return response()->json(['message'=>$error->getMessage()],503);}
        return response($pdf)->header('Content-Type','application/pdf')->header('Content-Disposition','attachment; filename="menu-nutriliado-'.now()->format('Y-m-d').'.pdf"')->header('Cache-Control','no-store');
    }

    public function saveDesign(Request $request, SiteContext $context)
    {
        $site = W::site($request,$context); abort_unless(W::tables(['kitchen_menu_designs']),503,'La persistencia del diseño requiere habilitar su esquema.');
        $data = $this->generatorData($request,$site->id_sede);
        $request->validate(['design_logo'=>W::mediaRules(true),'design_background'=>W::mediaRules(true)]);
        W::withMedia($request,['design_logo'=>'logo_url','design_background'=>'background_url'],function ($images) use ($request,$site,$data) {
            DB::table('kitchen_menu_designs')->updateOrInsert(['id_sede'=>$site->id_sede,'id_usuario'=>$request->user()->id_usuario],$images+['settings'=>json_encode($data['settings']),'updated_at'=>now()]);
        });
        return redirect()->route('chef.generator')->with('status','Diseño e imágenes guardados permanentemente.');
    }

    public function notifications(Request $request, SiteContext $context)
    {
        $site = W::site($request,$context); $events = collect();
        if (\App\Support\ServiceRecurrence::ready()) foreach(DB::table('service_schedule_revisions as r')->join('service_schedules as s','s.id','=','r.schedule_id')->where('s.id_sede',$site->id_sede)->whereIn('r.estado',['Aprobado','Rechazado'])->get(['r.*']) as $r) {
            $settings=\App\Support\ServiceRecurrence::settings($r);
            $events->push(['id'=>'schedule-'.$r->id.'-'.$r->estado,'title'=>'Programación '.$r->estado,'description'=>$settings['nombre'].' · '.($r->observaciones??''),'url'=>route('chef.schedules.show',$r->schedule_id),'tone'=>W::tone($r->estado),'date'=>$r->reviewed_at]);
        }
        foreach ($this->services($site->id_sede)->get() as $s) {
            if (!$s->schedule_id && in_array($s->estado_aprobacion,['Aprobado','Rechazado'],true)) $events->push(['id'=>'service-'.$s->id_servicio.'-'.$s->estado_aprobacion.'-'.$s->revisado_en,'title'=>'Servicio '.$s->estado_aprobacion,'description'=>$s->nombre.($s->observaciones_revision ? ' · '.$s->observaciones_revision : ''),'url'=>route('chef.services',['q'=>$s->nombre]),'tone'=>W::tone($s->estado_aprobacion),'date'=>$s->revisado_en ?: $s->fecha]);
            if ($s->estado === 'Programado' && $s->fecha && $s->fecha->toDateString() >= now('America/Mexico_City')->toDateString() && $s->fecha->lte(now()->addDays(2))) $events->push(['id'=>'upcoming-'.$s->id_servicio,'title'=>'Servicio próximo','description'=>$s->nombre,'url'=>route('chef.services'),'tone'=>'info','date'=>$s->fecha]);
        }
        foreach ($this->commands($site->id_sede)->where('estado','pendiente')->unique('id_comanda')->take(10) as $c) $events->push(['id'=>'command-'.$c->id_comanda,'title'=>'Comanda pendiente #'.$c->id_comanda,'description'=>$c->servicio,'url'=>route('chef.commands',['service'=>$c->id_servicio]),'tone'=>'waiting','date'=>$c->fecha_apertura]);
        foreach ($this->incidents($site->id_sede)->take(10) as $i) $events->push(['id'=>'incident-'.$i->id_incidencia.'-'.$i->estado,'title'=>'Incidencia de cocina','description'=>$i->descripcion,'url'=>route('chef.incidents'),'tone'=>'orange','date'=>$i->fecha]);
        foreach ($this->stock($site->id_sede)->whereIn('estado_stock',['Bajo','Agotado'])->take(10) as $i) $events->push(['id'=>'stock-'.$i->id_insumo.'-'.$i->estado_stock,'title'=>'Existencias '.$i->estado_stock,'description'=>$i->nombre,'url'=>route('chef.stock',['q'=>$i->nombre]),'tone'=>$i->tono,'date'=>$i->ultimo_movimiento]);
        $key = 'chef_notices.'.$site->id_sede.'.'.$request->user()->id_usuario.'.'.$request->user()->activeRoleId;
        $read = $request->session()->get($key,[]);
        if ($request->isMethod('post')) {
            $id = $request->validate(['id'=>'required|string|max:150'])['id'];
            $read = array_values(array_unique(array_merge($read,$id === 'all' ? $events->pluck('id')->all() : $events->where('id',$id)->pluck('id')->all())));
            $request->session()->put($key,array_slice($read,-200));
        }
        $items = $events->sortByDesc('date')->map(fn ($e)=>$e+['read'=>in_array($e['id'],$read,true)])->values();
        return response()->json(['items'=>$items,'unread'=>$items->where('read',false)->count()])->header('Cache-Control','no-store');
    }
}
