<?php

namespace App\Http\Controllers\Capitan;

use App\Http\Controllers\Controller;
use App\Models\AsignacionMesero;
use App\Models\Zona;
use App\Support\SiteContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CapitanController extends Controller
{
    public function notifications(Request $request)
    {
        $site=\App\Support\CaptainWorkspace::site($request); $id=(int)$site->id_sede;
        $events=collect();
        $url=fn($module,$extra=[])=>route('capitan.index',['modulo'=>$module]+$extra);
        $services=DB::table('servicios')->where('id_sede',$id)->where('estado_aprobacion','Aprobado')->whereDate('fecha',now()->toDateString())->whereIn('estado',['Programado','En curso'])->get();
        foreach ($services as $s) {
            if($s->estado==='Programado') $events->push(['id'=>'upcoming-'.$s->id_servicio,'title'=>'Servicio próximo a iniciar','description'=>$s->nombre.' · '.$s->inicio_hora,'url'=>$url('servicios',['fecha'=>$s->fecha,'id_servicio'=>$s->id_servicio]),'tone'=>'info','date'=>$s->fecha.' '.$s->inicio_hora]);
            $staff=\App\Support\CaptainWorkspace::assignments($id,$s->id_servicio);
            foreach(DB::table('zonas')->where('id_sede',$id)->whereNotIn('id_zona',$staff->pluck('id_zona'))->get() as $z) $events->push(['id'=>'uncovered-'.$s->id_servicio.'-'.$z->id_zona,'title'=>'Zona sin mesero asignado','description'=>$z->nombre.' · '.$s->nombre,'url'=>$url('asignaciones',['fecha'=>$s->fecha,'id_servicio'=>$s->id_servicio]),'tone'=>'waiting','date'=>$s->fecha]);
        }
        foreach(DB::table('incidencias')->where('id_sede',$id)->whereNull('fecha_cierre')->whereNotIn('estado',['Resuelta','Cerrada'])->orderByDesc('id_incidencia')->limit(30)->get() as $i) $events->push(['id'=>'incident-'.$i->id_incidencia.'-'.$i->estado,'title'=>'Incidencia #'.$i->id_incidencia,'description'=>$i->descripcion,'url'=>$url('incidencias'),'tone'=>'danger','date'=>$i->fecha]);
        foreach(DB::table('mesas as m')->join('zonas as z','z.id_zona','=','m.id_zona')->where('z.id_sede',$id)->where('m.estado_fisico','Fuera de servicio')->get(['m.*','z.nombre as zona']) as $m) $events->push(['id'=>'table-'.$m->id_mesa.'-out','title'=>'Mesa fuera de servicio','description'=>$m->zona.' · Mesa '.$m->numero,'url'=>$url('mesas'),'tone'=>'danger','date'=>'Sin fecha de cambio registrada']);
        if(\App\Support\ServiceRecurrence::ready()) foreach(DB::table('service_schedule_revisions as r')->join('service_schedules as s','s.id','=','r.schedule_id')->where('s.id_sede',$id)->where('r.estado','Aprobado')->orderByDesc('r.id')->limit(20)->get(['r.*']) as $r) $events->push(['id'=>'schedule-'.$r->id.'-approved','title'=>'Programación aprobada','description'=>\App\Support\ServiceRecurrence::settings($r)['nombre'],'url'=>$url('servicios'),'tone'=>'ok','date'=>$r->reviewed_at]);
        $events=$events->unique('id');
        $key='captain_notices.'.$id.'.'.$request->user()->id_usuario;
        $read=$request->session()->get($key,[]);
        if($request->isMethod('post')) {
            $event=$request->validate(['id'=>'required|string|max:150'])['id'];
            $read=array_values(array_unique(array_merge($read,$event==='all'?$events->pluck('id')->all():$events->where('id',$event)->pluck('id')->all())));
            $request->session()->put($key,array_slice($read,-300));
        }
        $items=$events->sortByDesc('date')->map(fn($event)=>$event+['read'=>in_array($event['id'],$read,true)])->values();
        return response()->json(['items'=>$items,'unread'=>$items->where('read',false)->count()])->header('Cache-Control','no-store');
    }
    private const MODULOS = [
        'panel',
        'servicios',
        'mesas',
        'asignaciones',
        'incidencias',
        'zonas',
        'comedor',
    ];

    /*
    |--------------------------------------------------------------------------
    | VALIDAR CAPITÁN Y SEDE ACTUAL
    |--------------------------------------------------------------------------
    */

    private function sede(Request $request, SiteContext $context)
    {
        return \App\Support\CaptainWorkspace::site($request);
    }

    /*
    |--------------------------------------------------------------------------
    | PANEL PRINCIPAL Y MÓDULOS
    |--------------------------------------------------------------------------
    */

    public function index(
        Request $request,
        SiteContext $context
    ): View {
        $sede = $this->sede($request, $context);
        $id = (int) $sede->id_sede;

        $modulo = $request->query('modulo', 'panel');

        if (!in_array($modulo, self::MODULOS, true)) {
            abort(404);
        }

        /*
        | ZONAS
        */

        $zonas = Zona::query()
            ->where('id_sede', $id)
            ->orderBy('nombre')
            ->get();

        /*
        | MESAS
        */

        $mesas = DB::table('mesas as m')
            ->join(
                'zonas as z',
                'z.id_zona',
                '=',
                'm.id_zona'
            )
            ->where('z.id_sede', $id)
            ->select(
                'm.id_mesa',
                'm.id_zona',
                'm.numero',
                'm.capacidad',
                'm.estado_fisico',
                'z.nombre as zona'
            )
            ->orderBy('z.nombre')
            ->orderBy('m.numero')
            ->get();

        /*
        | MESEROS ASIGNADOS A LA SEDE
        */

        $meseros = DB::table('usuarios as u')
            ->join(
                'usuario_sede as us',
                'us.id_usuario',
                '=',
                'u.id_usuario'
            )
            ->where('us.id_sede', $id)
            ->where('us.id_rol', 6)
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('usuarios','activo'), fn ($q) => $q->where('u.activo',1))
            ->select(
                'u.id_usuario',
                'u.nombre',
                'u.apellido_p',
                'u.apellido_m'
            )
            ->distinct()
            ->orderBy('u.nombre')
            ->get();

        /*
        | ASIGNACIONES ACTIVAS
        */

        $request->validate([
            'fecha' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $fechaOperacion = $request->query('fecha') ?: now()->toDateString();

        $servicios = DB::table('servicios')
            ->where('id_sede', $id)
            ->whereDate('fecha', $fechaOperacion)
            ->where('estado_aprobacion','Aprobado')
            ->orderBy('inicio_hora')
            ->get();

        /*
        | SERVICIO EN VIVO
        | Solo se considera activo un servicio cuyo estado sea "En curso".
        | No se sustituye por un servicio programado o finalizado.
        */

        $request->validate(['id_servicio'=>['nullable','integer']]);
        $servicioEnVivo = $request->filled('id_servicio') ? $servicios->firstWhere('id_servicio',(int)$request->id_servicio) : $servicios->firstWhere('estado','En curso');
        abort_if($request->filled('id_servicio') && !$servicioEnVivo,422,'Selecciona una jornada autorizada de esta fecha.');
        $jornada = $servicioEnVivo ?? $servicios->first();

        /*
        | INVENTARIO Y ESTADO DE MESAS
        | El inventario no depende de que exista un servicio en curso.
        | Si hay servicio activo, se muestran sus atenciones; si no lo hay,
        | se consultan las atenciones abiertas de los servicios de la fecha.
        */

        $mesasDisponiblesServicio = 0;
        $mesasOcupadasServicio = 0;
        $mesasAtendidasServicio = 0;
        $mesasPendientesPagoServicio = 0;
        $resumenZonasServicio = collect();

        $atencionesActivas = DB::table('atenciones as a')
            ->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')
            ->select(
                'a.id_mesa',
                DB::raw('MAX(a.id_atencion) as id_atencion')
            )
            ->where('s.id_sede', $id)
            ->whereDate('s.fecha', $fechaOperacion)
            ->whereNotIn('a.estado', ['Cerrada', 'Cancelada'])
            ->where('a.id_servicio',$jornada->id_servicio ?? 0)
            ->groupBy('a.id_mesa');

        $ultimaComanda = DB::table('comandas')
            ->select(
                'id_atencion',
                DB::raw('MAX(id_comanda) as id_comanda')
            )
            ->groupBy('id_atencion');

        // Esta consulta siempre devuelve las mesas registradas de la sede,
        // incluso cuando todavía no hay un servicio iniciado en esa fecha.
        $mesasServicio = DB::table('mesas as m')
            ->join('zonas as z', 'z.id_zona', '=', 'm.id_zona')
            ->leftJoinSub($atencionesActivas, 'aa', function ($join) {
                $join->on('aa.id_mesa', '=', 'm.id_mesa');
            })
            ->leftJoin('atenciones as a', 'a.id_atencion', '=', 'aa.id_atencion')
            ->leftJoinSub($ultimaComanda, 'uc', function ($join) {
                $join->on('uc.id_atencion', '=', 'a.id_atencion');
            })
            ->leftJoin('comandas as c', 'c.id_comanda', '=', 'uc.id_comanda')
            ->leftJoin('usuarios as u', 'u.id_usuario', '=', 'a.id_usuario_mesero')
            ->where('z.id_sede', $id)
            ->select(
                'm.id_mesa',
                'm.id_zona',
                'm.numero',
                'm.capacidad',
                'm.estado_fisico',
                'z.nombre as zona',
                'a.id_atencion',
                'a.id_usuario_mesero',
                'a.estado as atencion_estado',
                'a.comensales',
                'm.observaciones',
                'a.inicio_hora as ocupacion_inicio',
                'c.id_comanda',
                'c.estado as comanda_estado',
                DB::raw("TRIM(CONCAT(COALESCE(u.nombre, ''), ' ', COALESCE(u.apellido_p, ''))) as mesero")
            )
            ->orderBy('z.nombre')
            ->orderBy('m.numero')
            ->get();

        // Indicadores calculados sobre el inventario completo de la sede.
        $mesasOcupadasServicio = $mesasServicio
            ->whereNotNull('id_atencion')
            ->count();

        $mesasDisponiblesServicio = $mesasServicio
            ->filter(function ($mesa) {
                return $mesa->id_atencion === null
                    && mb_strtolower(trim((string) $mesa->estado_fisico)) === 'disponible';
            })
            ->count();

        $mesasAtendidasServicio = $mesasServicio
            ->filter(function ($mesa) {
                return $mesa->id_atencion !== null
                    && $mesa->id_usuario_mesero !== null;
            })
            ->count();

        $mesasPendientesPagoServicio = $mesasServicio
            ->filter(function ($mesa) {
                return mb_strtolower(trim((string) $mesa->atencion_estado)) === 'pendiente de pago';
            })
            ->count();

        // Resumen por zona: total de mesas y estado operativo.
        $resumenZonasServicio = $mesasServicio
            ->groupBy('zona')
            ->map(function ($mesasZona, $zona) {
                return (object) [
                    'zona' => $zona,
                    'total' => $mesasZona->count(),
                    'disponibles' => $mesasZona->filter(function ($mesa) {
                        return $mesa->id_atencion === null
                            && mb_strtolower(trim((string) $mesa->estado_fisico)) === 'disponible';
                    })->count(),
                    'ocupadas' => $mesasZona->whereNotNull('id_atencion')->count(),
                    'por_cobrar' => $mesasZona->filter(function ($mesa) {
                        return mb_strtolower(trim((string) $mesa->atencion_estado)) === 'pendiente de pago';
                    })->count(),
                ];
            })
            ->values();

        // Se muestran las asignaciones activas aunque aún no haya servicio iniciado.
        $atenciones = DB::table('atenciones as a')
            ->join(
                'servicios as s',
                's.id_servicio',
                '=',
                'a.id_servicio'
            )
            ->join(
                'mesas as m',
                'm.id_mesa',
                '=',
                'a.id_mesa'
            )
            ->where('s.id_sede', $id)
            ->select(
                'a.id_atencion',
                'a.estado',
                'a.comensales',
                'a.inicio_hora',
                'a.fin_hora',
                'm.numero as mesa',
                's.nombre as servicio'
            )
            ->orderByDesc('a.id_atencion')
            ->limit(100)
            ->get();

        /*
        | COMANDAS
        | Se conservan las tablas y los nombres de las rutas.
        | La interfaz muestra el nombre "Comidas".
        */

        $incidencias = DB::table('incidencias')
            ->where('id_sede', $id)
            ->orderByDesc('id_incidencia')
            ->limit(100)
            ->get();

        $incidenciasMapa=$incidencias->filter(fn($incident)=>empty($incident->id_servicio) || (int)$incident->id_servicio===(int)($jornada->id_servicio ?? 0));
        $incidentCounts=DB::table('incidencias')->where('id_sede',$id)->select('estado')->selectRaw('COUNT(*) as total')->groupBy('estado')->get();
        foreach ($incidencias as $incident) {
            $reporter = DB::table('usuarios')->where('id_usuario',$incident->id_usuario)->first();
            $incident->responsable_nombre = $reporter ? trim($reporter->nombre.' '.$reporter->apellido_p) : null;
            if ($incident->id_servicio ?? null) {
                $service = DB::table('servicios')->where('id_servicio',$incident->id_servicio)->where('id_sede',$id)->first();
                $incident->servicio_nombre=$service->nombre ?? null;
                $incident->jornada_fecha=$service->fecha ?? null;
            }
            $incident->zona_nombre=$zonas->firstWhere('id_zona',$incident->id_zona ?? null)?->nombre;
            $incident->mesa_numero=$mesasServicio->firstWhere('id_mesa',$incident->id_mesa ?? null)?->numero;
        }

        /*
        | MÉTRICAS ORIGINALES
        */

        $metricas = [
            'servicios' => $servicios->count(),

            'mesas' => $mesas->count(),

            'meseros' => $meseros->count(),

            'atenciones' => DB::table('atenciones as a')
                ->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')
                ->where('s.id_sede', $id)
                ->where(function ($query) {
                    $query->whereNull('a.estado')
                        ->orWhereNotIn('a.estado', ['Cerrada', 'Finalizada', 'Cancelada']);
                })
                ->count(),

            'incidencias' => DB::table('incidencias')->where('id_sede',$id)->whereNull('fecha_cierre')->whereNotIn('estado',['Resuelta','Cerrada'])->count(),
        ];

        /*
        |--------------------------------------------------------------------------
        | GRÁFICA: COMENSALES POR HORA
        |--------------------------------------------------------------------------
        | Consulta compatible con ONLY_FULL_GROUP_BY.
        | No modifica registros.
        | Solo incluye servicios de la fecha actual y sede actual.
        */

        $comensalesPorHora = DB::table('atenciones as a')
            ->join(
                'servicios as s',
                's.id_servicio',
                '=',
                'a.id_servicio'
            )
            ->where('s.id_sede', $id)
            ->whereDate('s.fecha', $fechaOperacion)
            ->whereNotNull('a.inicio_hora')
            ->selectRaw("
                DATE_FORMAT(a.inicio_hora, '%H:00') AS hora,
                SUM(COALESCE(a.comensales, 0)) AS total
            ")
            ->groupByRaw(
                "DATE_FORMAT(a.inicio_hora, '%H:00')"
            )
            ->orderByRaw(
                "DATE_FORMAT(a.inicio_hora, '%H:00')"
            )
            ->get();

        /*
        | VISTA
        */

        $asignaciones = \App\Support\CaptainWorkspace::assignments($id,$jornada->id_servicio ?? null);
        \App\Support\CaptainWorkspace::enrich($id,$zonas,$mesasServicio,$meseros,$asignaciones);
        $jornadaSupported = \App\Support\CaptainWorkspace::scoped();
        $zoneMedia = \Illuminate\Support\Facades\Schema::hasColumn('zonas','imagen_url');
        $incidentMedia = \Illuminate\Support\Facades\Schema::hasColumn('incidencias','evidencia_url');
        $serviceCoverage = [];
        foreach ($servicios as $service) {
            $staff = \App\Support\CaptainWorkspace::assignments($id,$service->id_servicio);
            $covered = $mesasServicio->whereIn('id_zona',$staff->pluck('id_zona'))->count();
            $serviceCoverage[$service->id_servicio] = ['staff'=>$staff,'covered'=>$covered,'percent'=>$mesasServicio->count()?round($covered*100/$mesasServicio->count()):0];
        }
        return view('capitan.workspace', compact('incidenciasMapa','incidentCounts','jornada','jornadaSupported','zoneMedia','incidentMedia','serviceCoverage',
            'sede',
            'modulo',
            'zonas',
            'mesas',
            'meseros',
            'asignaciones',
            'servicios',
            'atenciones',
            'incidencias',
            'metricas',
            'comensalesPorHora',
            'fechaOperacion',
            'servicioEnVivo',
            'mesasServicio',
            'mesasDisponiblesServicio',
            'mesasOcupadasServicio',
            'mesasAtendidasServicio',
            'mesasPendientesPagoServicio',
            'resumenZonasServicio'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | ASIGNAR MESERO
    | Se conserva la operación que ya probamos.
    |--------------------------------------------------------------------------
    */

    public function asignar(
        Request $request,
        SiteContext $context
    ) {
        $sede = $this->sede($request, $context);
        $idSede = (int) $sede->id_sede;

        $datos = $request->validate([
            'id_zona' => [
                'required',
                'integer',
                Rule::exists('zonas', 'id_zona')
                    ->where('id_sede', $idSede),
            ],
            'id_usuario_mesero' => [
                'required',
                'integer',
                Rule::exists('usuario_sede', 'id_usuario')
                    ->where('id_sede', $idSede)
                    ->where('id_rol', 6),
            ],
        ]);

        abort_unless(\App\Support\CaptainWorkspace::scoped(),409,'La asignación por jornada requiere la migración pendiente de autorización.');
        $request->validate(['id_servicio'=>['required','integer']]);
        $service=DB::table('servicios')->where('id_servicio',$request->id_servicio)->where('id_sede',$idSede)->where('estado_aprobacion','Aprobado')->whereIn('estado',['Programado','En curso'])->first();
        abort_unless($service,422,'Selecciona una jornada autorizada vigente.');
        DB::transaction(function () use ($datos,$service) {
            $user=DB::table('usuarios')->where('id_usuario',$datos['id_usuario_mesero'])->lockForUpdate()->first();
            abort_unless($user && (!isset($user->activo) || $user->activo),422,'El empleado no está activo.');
            $duplicate=DB::table('zona_meseros as zm')->join('servicios as s','s.id_servicio','=','zm.id_servicio')->where('zm.id_usuario_mesero',$datos['id_usuario_mesero'])->where('zm.activo',1)->whereDate('s.fecha',$service->fecha)->where('s.inicio_hora','<',$service->fin_hora)->where('s.fin_hora','>',$service->inicio_hora)->exists();
            abort_if($duplicate,422,'El mesero ya tiene una asignación en esta jornada u horario incompatible. Retírala antes de cambiarla.');
            DB::table('zona_meseros')->insert($datos+['id_servicio'=>$service->id_servicio,'activo'=>1,'fecha_inicio'=>now(),'fecha_fin'=>null]);
        });

        return redirect()
            ->route('capitan.index', [
                'modulo' => 'asignaciones', 'fecha'=>$request->input('fecha',now()->toDateString()), 'id_servicio'=>$request->input('id_servicio'),
            ])
            ->with(
                'status',
                'Mesero asignado correctamente.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | RETIRAR MESERO
    | Se conserva la operación que ya probamos.
    |--------------------------------------------------------------------------
    */

    public function retirar(
        Request $request,
        SiteContext $context,
        int $asignacion
    ) {
        $sede = $this->sede($request, $context);

        $registro = AsignacionMesero::query()
            ->whereKey($asignacion)
            ->whereHas('zona', function ($query) use ($sede) {
                $query->where(
                    'id_sede',
                    $sede->id_sede
                );
            })
            ->firstOrFail();

        if (\App\Support\CaptainWorkspace::scoped() && $registro->id_servicio) {
            abort_unless(DB::table('servicios')->where('id_servicio',$registro->id_servicio)->where('id_sede',$sede->id_sede)->whereIn('estado',['Programado','En curso'])->exists(),422,'No se modifican asignaciones de jornadas finalizadas.');
        }
        $registro->update([
            'activo' => 0,
            'fecha_fin' => now(),
        ]);

        return redirect()
            ->route('capitan.index', [
                'modulo' => 'asignaciones', 'fecha'=>$request->input('fecha',now()->toDateString()), 'id_servicio'=>$request->input('id_servicio'),
            ])
            ->with(
                'status',
                'Asignación retirada correctamente.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | REGISTRAR INCIDENCIA
    | Se conserva la operación que ya probamos.
    |--------------------------------------------------------------------------
    */

    public function incidencia(
        Request $request,
        SiteContext $context
    ) {
        $sede = $this->sede($request, $context);

        $has = fn($column)=>\Illuminate\Support\Facades\Schema::hasColumn('incidencias',$column);
        $validator=\Illuminate\Support\Facades\Validator::make($request->all(),[
            'descripcion'=>['required','string','min:10','max:2000'],
            'fecha_jornada'=>['nullable','date_format:Y-m-d'],
            'id_servicio'=>$has('id_servicio') ? ['nullable','integer',Rule::exists('servicios','id_servicio')->where('id_sede',$sede->id_sede)->where('estado_aprobacion','Aprobado')] : ['prohibited'],
            'id_zona'=>$has('id_zona') ? ['nullable','integer',Rule::exists('zonas','id_zona')->where('id_sede',$sede->id_sede)] : ['prohibited'],
            'id_mesa'=>$has('id_mesa') ? ['nullable','integer'] : ['prohibited'],
            'evidencia'=>\App\Support\ChefWorkspace::mediaRules($has('evidencia_url')),
        ],[
            'id_servicio.exists'=>'Selecciona un servicio autorizado de la sede activa.',
            'id_zona.exists'=>'La zona seleccionada no pertenece a la sede activa.',
            'id_mesa.integer'=>'Selecciona una mesa válida.',
            'fecha_jornada.date_format'=>'La fecha de jornada no es válida.',
        ]);
        $mesa=null;
        $validator->after(function($validator)use($request,$sede,&$mesa){
            if($request->filled('id_mesa') && !$validator->errors()->has('id_mesa')){
                $mesa=DB::table('mesas as m')->join('zonas as z','z.id_zona','=','m.id_zona')->where('m.id_mesa',$request->input('id_mesa'))->where('z.id_sede',$sede->id_sede)->select('m.id_mesa','m.id_zona')->first();
                if(!$mesa || (!$validator->errors()->has('id_zona') && $request->filled('id_zona') && (int)$mesa->id_zona!==(int)$request->input('id_zona'))){
                    $validator->errors()->add('id_mesa','La mesa seleccionada no pertenece a la zona y sede indicadas. Selecciona nuevamente la mesa.');
                }
            }
            if($request->filled('id_servicio') && $request->filled('fecha_jornada') && !$validator->errors()->has('id_servicio') && !$validator->errors()->has('fecha_jornada')){
                if(!DB::table('servicios')->where('id_servicio',$request->input('id_servicio'))->where('id_sede',$sede->id_sede)->whereDate('fecha',$request->input('fecha_jornada'))->exists()){
                    $validator->errors()->add('id_servicio','El servicio seleccionado no corresponde a la fecha de jornada consultada.');
                }
            }
        });
        if($validator->fails()){
            // JSON callers keep the open dialog and file; ordinary forms return with old input.
            $parameters=['modulo'=>'incidencias'];
            if(!$validator->errors()->has('fecha_jornada') && $request->filled('fecha_jornada'))$parameters['fecha']=$request->input('fecha_jornada');
            throw (new \Illuminate\Validation\ValidationException($validator))->errorBag('incident')->redirectTo(route('capitan.index',$parameters));
        }
        $datos=$validator->validated();
        $extra=collect($datos)->only(['id_servicio','id_zona','id_mesa'])->filter(fn($value)=>$value!==null && $value!=='')->map(fn($value)=>(int)$value)->all();
        if($mesa && $has('id_zona'))$extra['id_zona']=(int)$mesa->id_zona;
        \App\Support\ChefWorkspace::withMedia($request,['evidencia'=>'evidencia_url'],function ($media) use ($sede,$request,$datos,$extra) {
        DB::table('incidencias')->insert($extra+$media+[
            'id_sede' => $sede->id_sede,
            'id_usuario' => $request->user()->id_usuario,
            'fecha' => now(),
            'descripcion' => $datos['descripcion'],
            'estado' => 'Pendiente',
        ]);
        });

        $parameters=['modulo'=>'incidencias'];
        if(!empty($datos['fecha_jornada']))$parameters['fecha']=$datos['fecha_jornada'];
        if(!empty($extra['id_servicio']))$parameters['id_servicio']=$extra['id_servicio'];
        if($request->expectsJson()){
            $request->session()->flash('status','Incidencia registrada correctamente.');
            return response()->json(['message'=>'Incidencia registrada correctamente.','redirect'=>route('capitan.index',$parameters)],201);
        }
        return redirect()
            ->route('capitan.index', $parameters)
            ->with(
                'status',
                'Incidencia registrada correctamente.'
            );
    }
}
