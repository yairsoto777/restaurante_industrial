<?php

namespace App\Http\Controllers;

use App\Support\ReceptionWorkspace as Workspace;
use App\Support\SiteContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReceptionWorkspaceController extends Controller
{
    private function orders(int $site)
    {
        return DB::table('pedidos as p')->leftJoin('proveedores as pr', 'pr.id_proveedor', '=', 'p.id_proveedor')
            ->where('p.id_sede', $site);
    }

    public function dashboard(Request $request, SiteContext $context)
    {
        $site = Workspace::site($request, $context);
        $today = now('America/Mexico_City')->startOfDay();
        $weekStart = $today->copy()->startOfWeek();
        $ordersAvailable = Schema::hasTable('pedidos') && Schema::hasTable('proveedores');
        $scheduled = $ordersAvailable ? $this->orders($site->id_sede)->whereIn('p.estado', config('receptions.order_states', [])) : null;
        $scheduledToday = $scheduled ? (clone $scheduled)->whereDate('p.fecha_entrega', $today->toDateString())->count() : null;
        $upcoming = $scheduled ? (clone $scheduled)->whereDate('p.fecha_entrega', '>=', $today->toDateString())
            ->orderBy('p.fecha_entrega')->limit(5)->get(['p.*', 'pr.nombre as proveedor_nombre']) : collect();
        $available = Workspace::receiptsAvailable();
        $base = $available ? Workspace::receipts($site->id_sede, $request->user()->id_usuario) : null;
        $registered = $base ? (clone $base)->count() : null;
        $pending = $base ? (clone $base)->where('r.estado', config('receptions.pending'))->count() : null;
        $distribution = $base ? (clone $base)->select('r.estado')->selectRaw('COUNT(*) as total')->groupBy('r.estado')->get() : collect();
        $recent = $base ? (clone $base)->orderByDesc('r.fecha_recepcion')->orderByDesc('r.id_recepcion')->limit(5)
            ->get(['r.*', 'pr.nombre as proveedor_nombre']) : collect();
        $days = $base ? (clone $base)->whereBetween('r.fecha_recepcion', [$weekStart, $weekStart->copy()->addWeek()])
            ->selectRaw('DATE(r.fecha_recepcion) as day, COUNT(*) as total')->groupByRaw('DATE(r.fecha_recepcion)')->pluck('total', 'day') : collect();
        $week = collect(range(0, 6))->map(fn ($i) => [
            'date' => $weekStart->copy()->addDays($i)->format('d/m'),
            'label' => ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'][$i],
            'total' => $available ? (int) $days->get($weekStart->copy()->addDays($i)->toDateString(), 0) : null,
        ]);
        $withIncidents = Workspace::incidentsAvailable() ? Workspace::incidents($site->id_sede, $request->user()->id_usuario)->distinct()->count('r.id_recepcion') : null;
        $activity = $recent->map(fn ($r) => ['title' => 'Recepción #'.$r->id_recepcion, 'description' => $r->estado ?: 'Sin estado', 'date' => $r->fecha_recepcion, 'url' => route('receptions.receipts.show', $r->id_recepcion), 'tone' => Workspace::tone($r->estado)]);
        if (Workspace::incidentsAvailable()) {
            $activity = $activity->concat(Workspace::incidents($site->id_sede, $request->user()->id_usuario)->orderByDesc('i.fecha')->limit(5)
                ->get(['i.id_incidencia', 'i.fecha', 'i.descripcion'])->map(fn ($i) => [
                    'title' => 'Incidencia #'.$i->id_incidencia, 'description' => $i->descripcion, 'date' => $i->fecha,
                    'url' => route('incidents.index'), 'tone' => 'danger',
                ]));
        }
        $activity = $activity->sortByDesc('date')->take(6);
        return view('receptions.workspace.dashboard', compact('site','scheduledToday','registered','pending','withIncidents','upcoming','recent','distribution','week','activity','available'));
    }

    public function deliveries(Request $request, SiteContext $context)
    {
        $site = Workspace::site($request, $context);
        $filters = $request->validate(['q'=>'nullable|string|max:150','proveedor'=>'nullable|integer','fecha'=>'nullable|date_format:Y-m-d','estado'=>'nullable|string|max:50']);
        $registration = $request->routeIs('receptions.register');
        $available = Schema::hasTable('pedidos') && Schema::hasTable('proveedores');
        $states = $available ? $this->orders($site->id_sede)->whereNotNull('p.estado')->distinct()->pluck('p.estado') : collect();
        $providers = $available ? $this->orders($site->id_sede)->select('pr.id_proveedor','pr.nombre')->distinct()->orderBy('pr.nombre')->get() : collect();
        $orders = null; $lines = collect();
        if ($available) {
            $orders = $this->orders($site->id_sede)
                ->when($registration, fn ($q) => $q->whereIn('p.estado', config('receptions.order_states', [])))
                ->when(filled($filters['q'] ?? null), function ($q) use ($filters) {
                    $term = trim($filters['q']);
                    $q->where(function ($q) use ($term) {
                        $q->where('pr.nombre','like','%'.$term.'%');
                        if (ctype_digit(ltrim($term, '#'))) $q->orWhere('p.id_pedido', (int) ltrim($term, '#'));
                    });
                })->when($filters['proveedor'] ?? null, fn ($q,$v) => $q->where('p.id_proveedor',$v))
                ->when($filters['fecha'] ?? null, fn ($q,$v) => $q->whereDate('p.fecha_entrega',$v))
                ->when($filters['estado'] ?? null, fn ($q,$v) => $q->where('p.estado',$v))
                ->select('p.*','pr.nombre as proveedor_nombre')->orderBy('p.fecha_entrega')->orderByDesc('p.id_pedido')->paginate(12)->withQueryString();
            if (Schema::hasTable('detalle_pedidos') && Schema::hasTable('insumos')) {
                $lines = DB::table('detalle_pedidos as d')->leftJoin('insumos as i','i.id_insumo','=','d.id_insumo')
                    ->whereIn('d.id_pedido', $orders->pluck('id_pedido'))->get(['d.*','i.nombre as insumo_nombre'])->groupBy('id_pedido');
            }
        }
        $captureAvailable = Workspace::captureAvailable();
        return view('receptions.workspace.deliveries', compact('site','filters','registration','available','states','providers','orders','lines','captureAvailable'));
    }

    public function history(Request $request, SiteContext $context)
    {
        $site = Workspace::site($request, $context);
        $filters = $request->validate(['q'=>'nullable|string|max:150','estado'=>'nullable|string|max:50','fecha'=>'nullable|date_format:Y-m-d']);
        $available = Workspace::receiptsAvailable(); $receipts = null; $states = collect(); $lines = collect(); $incidents = collect();
        $summary = ['total'=>null,'pending'=>null,'confirmed'=>null];
        if ($available) {
            $base = Workspace::receipts($site->id_sede, $request->user()->id_usuario);
            $summary = ['total'=>(clone $base)->count(), 'pending'=>(clone $base)->where('r.estado',config('receptions.pending'))->count(), 'confirmed'=>(clone $base)->where('r.estado','Confirmada')->count()];
            $states = (clone $base)->whereNotNull('r.estado')->distinct()->pluck('r.estado');
            $receipts = (clone $base)->when(filled($filters['q'] ?? null), function ($q) use ($filters) {
                $term = trim($filters['q']);
                $q->where(function ($q) use ($term) {
                    $q->where('pr.nombre','like','%'.$term.'%');
                    if (ctype_digit(ltrim($term,'#'))) $q->orWhere('r.id_recepcion',(int) ltrim($term,'#'))->orWhere('r.id_pedido',(int) ltrim($term,'#'));
                });
            })->when($filters['estado'] ?? null, fn ($q,$v) => $q->where('r.estado',$v))
                ->when($filters['fecha'] ?? null, fn ($q,$v) => $q->whereDate('r.fecha_recepcion',$v))
                ->select('r.*','pr.nombre as proveedor_nombre')->orderByDesc('r.fecha_recepcion')->orderByDesc('r.id_recepcion')->paginate(12)->withQueryString();
            if (Schema::hasTable('recepcion_detalles')) {
                $lines = DB::table('recepcion_detalles as d')->leftJoin('detalle_pedidos as dp','dp.id_detalle_pedido','=','d.id_detalle_pedido')
                    ->leftJoin('insumos as ins','ins.id_insumo','=','dp.id_insumo')->whereIn('d.id_recepcion',$receipts->pluck('id_recepcion'))
                    ->where('d.cantidad_entregada','>',0)
                    ->get(['d.id_recepcion','ins.nombre'])->groupBy('id_recepcion');
            }
            if (Workspace::incidentsAvailable()) $incidents = Workspace::incidents($site->id_sede,$request->user()->id_usuario)
                ->whereIn('r.id_recepcion',$receipts->pluck('id_recepcion'))->select('r.id_recepcion')->selectRaw('COUNT(*) as total')->groupBy('r.id_recepcion')->pluck('total','id_recepcion');
        }
        return view('receptions.workspace.history', compact('site','filters','available','receipts','states','summary','lines','incidents'));
    }

    public function incidents(Request $request, SiteContext $context)
    {
        $site = Workspace::site($request, $context);
        $filters = $request->validate(['q'=>'nullable|string|max:150','estado'=>'nullable|string|max:50']);
        $available = Workspace::incidentsAvailable(); $incidents = null; $states = collect(); $details = collect();
        if ($available) {
            $base = Workspace::incidents($site->id_sede,$request->user()->id_usuario);
            $states = (clone $base)->whereNotNull('i.estado')->distinct()->pluck('i.estado');
            $incidents = (clone $base)->when(filled($filters['q'] ?? null), fn ($q) => $q->where('i.descripcion','like','%'.$filters['q'].'%'))
                ->when($filters['estado'] ?? null, fn ($q,$v) => $q->where('i.estado',$v))
                ->leftJoin('usuarios as u','u.id_usuario','=','i.id_usuario')
                ->select('i.*','r.id_recepcion','p.id_pedido','pr.nombre as proveedor_nombre','ins.nombre as insumo_nombre','u.nombre as responsable')
                ->orderByDesc('i.fecha')->paginate(12)->withQueryString();
            $details = Workspace::receipts($site->id_sede,$request->user()->id_usuario)
                ->join('recepcion_detalles as d','d.id_recepcion','=','r.id_recepcion')
                ->join('detalle_pedidos as dp','dp.id_detalle_pedido','=','d.id_detalle_pedido')
                ->join('insumos as ins','ins.id_insumo','=','dp.id_insumo')->whereColumn('dp.id_pedido','p.id_pedido')
                ->select('d.id_recepcion_detalle','r.id_recepcion','p.id_pedido','pr.nombre as proveedor_nombre','ins.nombre')
                ->orderByDesc('r.id_recepcion')->get();
        }
        return view('receptions.workspace.incidents',compact('site','filters','available','incidents','states','details'));
    }

    public function storeIncident(Request $request, SiteContext $context)
    {
        $site = Workspace::site($request,$context);
        abort_unless(Workspace::incidentsAvailable(),503);
        $data = $request->validate(['id_recepcion_detalle'=>'required|integer','descripcion'=>'required|string|max:5000']);
        $belongs = Workspace::receipts($site->id_sede,$request->user()->id_usuario)
            ->join('recepcion_detalles as d','d.id_recepcion','=','r.id_recepcion')
            ->join('detalle_pedidos as dp','dp.id_detalle_pedido','=','d.id_detalle_pedido')->whereColumn('dp.id_pedido','p.id_pedido')
            ->where('d.id_recepcion_detalle',$data['id_recepcion_detalle'])->exists();
        abort_unless($belongs,404);
        DB::table('incidencias')->insert($data + ['id_sede'=>$site->id_sede,'id_usuario'=>$request->user()->id_usuario,'fecha'=>now(),'estado'=>'Pendiente']);
        return redirect()->route('incidents.index')->with('status','Incidencia registrada para seguimiento.');
    }

    public function notifications(Request $request, SiteContext $context)
    {
        $site = Workspace::site($request,$context); $events = collect();
        $add = function ($id,$title,$description,$url,$tone,$date) use (&$events) {
            $events->push(compact('id','title','description','url','tone','date'));
        };
        if (Schema::hasTable('pedidos')) foreach ($this->orders($site->id_sede)->whereIn('p.estado',config('receptions.order_states',[]))
            ->whereDate('p.fecha_entrega','>=',now('America/Mexico_City')->toDateString())->whereDate('p.fecha_entrega','<=',now('America/Mexico_City')->addDays(7)->toDateString())
            ->orderBy('p.fecha_entrega')->limit(15)->get(['p.*','pr.nombre as proveedor_nombre']) as $o) {
            $add('order-'.$o->id_pedido,'Entrega programada #'.$o->id_pedido,$o->proveedor_nombre.' · '.$o->fecha_entrega,route('receptions.orders.show',$o->id_pedido),'waiting',$o->fecha_pedido ?: $o->fecha_entrega);
        }
        if (Workspace::receiptsAvailable()) foreach (Workspace::receipts($site->id_sede,$request->user()->id_usuario)->whereNotNull('r.estado')->where('r.estado','!=',config('receptions.pending'))
            ->orderByDesc('r.id_recepcion')->limit(15)->get(['r.*']) as $r) {
            $add('receipt-'.$r->id_recepcion.'-'.$r->estado,'Revisión de recepción #'.$r->id_recepcion,$r->estado.($r->observaciones ? ' · '.$r->observaciones : ''),route('receptions.receipts.show',$r->id_recepcion),Workspace::tone($r->estado),$r->fecha_confirmacion ?: $r->fecha_recepcion);
        }
        if (Workspace::incidentsAvailable() && Schema::hasColumn('incidencias','respuesta')) foreach (Workspace::incidents($site->id_sede,$request->user()->id_usuario)
            ->whereNotNull('i.respuesta')->where('i.respuesta','<>','')->orderByDesc('i.fecha')->limit(15)->get(['i.*']) as $i) {
            $add('incident-'.$i->id_incidencia.'-'.substr(hash('sha256',$i->respuesta),0,12),'Respuesta a incidencia #'.$i->id_incidencia,$i->respuesta,route('incidents.index'),Workspace::tone($i->estado),$i->fecha_cierre ?? $i->fecha);
        }
        $key = 'reception_notices.'.$request->user()->id_usuario.'.'.$site->id_sede.'.'.$request->user()->activeRoleId;
        $read = $request->session()->get($key,[]);
        if ($request->isMethod('post')) {
            $id = $request->validate(['id'=>'required|string|max:100'])['id'];
            $read = array_values(array_unique(array_merge($read,$id === 'all' ? $events->pluck('id')->all() : $events->where('id',$id)->pluck('id')->all())));
            $request->session()->put($key,array_slice($read,-200));
        }
        $items = $events->sortByDesc('date')->map(fn ($e) => $e + ['read'=>in_array($e['id'],$read,true)])->values();
        return response()->json(['items'=>$items,'unread'=>$items->where('read',false)->count()])->header('Cache-Control','no-store');
    }
}
