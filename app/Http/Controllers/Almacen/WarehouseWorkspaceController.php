<?php

namespace App\Http\Controllers\Almacen;

use App\Http\Controllers\Controller;
use App\Support\SiteContext;
use App\Support\WarehouseWorkspace as W;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\LengthAwarePaginator;

class WarehouseWorkspaceController extends Controller
{
    private function page($rows, Request $request, int $size = 15)
    {
        $number = max(1, (int) $request->query('page', 1));
        return (new LengthAwarePaginator($rows->forPage($number, $size)->values(), $rows->count(), $size, $number,
            ['path'=>$request->url(), 'query'=>$request->query()]));
    }

    private function weekly($movements)
    {
        return collect(range(6,0))->map(function ($offset) use ($movements) {
            $date = now('America/Mexico_City')->subDays($offset);
            $rows = $movements->filter(fn ($m) => $m->fecha && \Illuminate\Support\Carbon::parse($m->fecha)->timezone('America/Mexico_City')->toDateString() === $date->toDateString());
            return ['label'=>$date->format('d/m'), 'in'=>$rows->where('cantidad_firmada','>',0)->count(), 'out'=>$rows->where('cantidad_firmada','<',0)->count()];
        });
    }

    public function dashboard(Request $request, SiteContext $context)
    {
        $site = W::site($request,$context);
        $stock = W::stock($site->id_sede);
        $orders = W::orders($site->id_sede)->orderBy('p.fecha_entrega')->get();
        $receivedIds = W::receivedOrderIds($site->id_sede);
        $receipts = Schema::hasTable('recepciones') ? W::receipts($site->id_sede)->get() : collect();
        $movements = W::movementAvailable() ? W::movements($site->id_sede)->orderByDesc('m.fecha')->orderByDesc('m.id_inventario_final')->get() : collect();
        $today = now('America/Mexico_City')->toDateString();
        $todayMovements = $movements->filter(fn ($m) => $m->fecha && \Illuminate\Support\Carbon::parse($m->fecha)->timezone('America/Mexico_City')->toDateString() === $today);
        $supplierCount = $orders->pluck('id_proveedor')->unique()->count();
        $orders = $orders->whereNotIn('id_pedido',$receivedIds);
        $upcoming = $orders->whereIn('estado', config('receptions.order_states',[]))->filter(fn ($o) => $o->fecha_entrega >= $today)->take(5);
        $pending = $receipts->where('estado', config('receptions.pending'))->count();
        $alerts = $stock->filter(fn ($s) => in_array($s->estado_stock,['Agotado','Bajo']) || $s->caducidad)->sortBy(fn ($s) => $s->estado_stock === 'Agotado' ? 0 : 1);
        $metrics = [
            ['Proveedores de la sede',$supplierCount,'Con pedidos registrados','users','info'],
            ['Pedidos programados',$orders->where('estado','Programado')->count(),'Órdenes de abastecimiento','calendar','info'],
            ['Recepciones por revisar',$pending,'Capturadas por Recepción','box','waiting'],
            ['Existencias bajas',$stock->where('estado_stock','Bajo')->count(),'Requieren reabastecimiento','alert','waiting'],
            ['Movimientos de hoy',$todayMovements->count(),'Entradas y salidas registradas','layers','ok'],
            ['Insumos agotados',$stock->where('estado_stock','Agotado')->count(),'Atención prioritaria','warehouse','danger'],
        ];
        $week = $this->weekly($movements);
        $distribution = $stock->groupBy('estado_stock')->map->count();
        return view('almacen.workspace.dashboard',compact('site','metrics','orders','receipts','movements','upcoming','alerts','week','distribution'));
    }

    public function suppliers(Request $request, SiteContext $context)
    {
        $site = W::site($request,$context);
        $filters = $request->validate(['q'=>'nullable|string|max:150','relacion'=>'nullable|in:con-pedidos,con-incidencias','proveedor'=>'nullable|integer']);
        $orders = W::orders($site->id_sede)->get();
        $receipts = Schema::hasTable('recepciones') ? W::receipts($site->id_sede)->get() : collect();
        $incidents = $this->incidents($site->id_sede);
        $suppliers = DB::table('proveedores')->orderBy('nombre')->get();
        $allSuppliers = $suppliers;
        $supplierCategories = collect();
        if (Schema::hasColumn('insumos','categoria')) {
            $supplierCategories = DB::table('pedidos as p')->join('detalle_pedidos as d','d.id_pedido','=','p.id_pedido')->join('insumos as i','i.id_insumo','=','d.id_insumo')
                ->where('p.id_sede',$site->id_sede)->whereNotNull('i.categoria')->get(['p.id_proveedor','i.categoria'])->groupBy('id_proveedor')->map(fn ($r) => $r->pluck('categoria')->unique());
        }
        if (filled($filters['q'] ?? null)) $suppliers = $suppliers->filter(fn ($s) => str_contains(mb_strtolower(implode(' ',[$s->nombre,$s->telefono ?? '',$s->correo ?? ''])),mb_strtolower($filters['q'])));
        if (($filters['relacion'] ?? '') === 'con-pedidos') $suppliers = $suppliers->whereIn('id_proveedor',$orders->pluck('id_proveedor'));
        if (($filters['relacion'] ?? '') === 'con-incidencias') $suppliers = $suppliers->whereIn('id_proveedor',$incidents->pluck('id_proveedor'));
        $selected = isset($filters['proveedor']) ? $allSuppliers->firstWhere('id_proveedor',(int) $filters['proveedor']) : null;
        abort_if(isset($filters['proveedor']) && ! $selected,404);
        $metrics = [
            ['Proveedores registrados',$allSuppliers->count(),'Catálogo compartido del sistema','users','info'],
            ['Entregas de la semana',$receipts->filter(fn ($r) => $r->fecha_recepcion >= now('America/Mexico_City')->startOfWeek()->utc()->format('Y-m-d H:i:s') && $r->fecha_recepcion <= now('America/Mexico_City')->endOfWeek()->utc()->format('Y-m-d H:i:s'))->count(),'Recepciones físicas de esta sede','box','ok'],
            ['Categorías cubiertas',$supplierCategories->flatten()->unique()->count(),'Según pedidos de esta sede','layers','info'],
            ['Proveedores con incidencias',$incidents->pluck('id_proveedor')->unique()->count(),'Con incidencias registradas en la sede','alert','danger'],
        ];
        $suppliers = $this->page($suppliers,$request,12);
        $supplierImageAvailable = \App\Support\WarehouseSupplierImage::column() !== null;
        $supplierCoverAvailable = \App\Support\WarehouseSupplierImage::coverColumn() !== null;
        return view('almacen.workspace.suppliers',compact('site','suppliers','filters','orders','receipts','incidents','supplierCategories','selected','metrics','supplierImageAvailable','supplierCoverAvailable'));
    }

    private function incidents(int $site)
    {
        if (! \App\Support\ReceptionWorkspace::incidentsAvailable()) return collect();
        return DB::table('incidencias as inc')->join('recepcion_detalles as rd','rd.id_recepcion_detalle','=','inc.id_recepcion_detalle')
            ->join('recepciones as r','r.id_recepcion','=','rd.id_recepcion')->join('pedidos as p','p.id_pedido','=','r.id_pedido')
            ->where('inc.id_sede',$site)->where('p.id_sede',$site)->select('inc.*','p.id_proveedor','r.id_recepcion')->get();
    }

    public function orders(Request $request, SiteContext $context)
    {
        $site = W::site($request,$context);
        $filters = $request->validate(['q'=>'nullable|string|max:150','proveedor'=>'nullable|integer','fecha'=>'nullable|date_format:Y-m-d','estado'=>'nullable|string|max:50','insumo'=>'nullable|integer']);
        $base = W::orders($site->id_sede); $all = (clone $base)->get();
        $orders = $base->when(filled($filters['q'] ?? null),fn ($q) => $q->where(fn ($q) => $q->where('pr.nombre','like','%'.$filters['q'].'%')->orWhere('p.id_pedido',ctype_digit($filters['q']) ? (int) $filters['q'] : -1)))
            ->when($filters['proveedor'] ?? null,fn ($q,$v) => $q->where('p.id_proveedor',$v))
            ->when($filters['fecha'] ?? null,fn ($q,$v) => $q->whereDate('p.fecha_entrega',$v))
            ->when($filters['estado'] ?? null,fn ($q,$v) => $q->where('p.estado',$v))->orderByDesc('p.id_pedido')->paginate(15)->withQueryString();
        $details = DB::table('detalle_pedidos as d')->join('pedidos as p','p.id_pedido','=','d.id_pedido')->leftJoin('insumos as i','i.id_insumo','=','d.id_insumo')
            ->leftJoin('unidades as u','u.id_unidad','=','i.id_unidad_base')->where('p.id_sede',$site->id_sede)->whereIn('d.id_pedido',$orders->pluck('id_pedido'))
            ->select('d.*','i.nombre','u.codigo as unidad')->get()->groupBy('id_pedido');
        $suppliers = DB::table('proveedores')->orderBy('nombre')->get();
        $ingredients = DB::table('insumo_sedes as s')->join('insumos as i','i.id_insumo','=','s.id_insumo')->join('unidades as u','u.id_unidad','=','i.id_unidad_base')->where('s.id_sede',$site->id_sede)
            ->get(['i.id_insumo','i.nombre','u.codigo as unidad_codigo','u.nombre as unidad_nombre','u.permite_fracciones']);
        $prefill = isset($filters['insumo']) ? $ingredients->firstWhere('id_insumo',(int) $filters['insumo']) : null;
        abort_if(isset($filters['insumo']) && ! $prefill,404);
        $states = $all->pluck('estado')->filter()->unique();
        $receivedIds = W::receivedOrderIds($site->id_sede);
        $pendingOrders = $all->whereIn('estado',config('receptions.order_states',[]))->whereNotIn('id_pedido',$receivedIds);
        $metrics = [ ['Pedidos programados',$pendingOrders->where('estado','Programado')->count(),'Con abastecimiento por completar','calendar','info'],
            ['Entregas próximas',$pendingOrders->filter(fn ($o) => $o->fecha_entrega >= now('America/Mexico_City')->toDateString() && $o->fecha_entrega <= now('America/Mexico_City')->addDays(7)->toDateString())->count(),'En los siguientes 7 días','box','info'],
            ['Pendientes de recepción',$pendingOrders->count(),'Con cantidades aún por confirmar','clock','waiting'],
            ['Pedidos recibidos',$receivedIds->count(),'Todas las cantidades aceptadas y confirmadas','check','ok'] ];
        return view('almacen.workspace.orders',compact('site','orders','details','suppliers','ingredients','filters','states','metrics','prefill'));
    }

    public function receptions(Request $request, SiteContext $context, ?int $receipt = null)
    {
        $site = W::site($request,$context);
        $filters = $request->validate(['q'=>'nullable|string|max:150','estado'=>'nullable|string|max:50']);
        $available = Schema::hasTable('recepciones');
        $all = $available ? W::receipts($site->id_sede)->orderByDesc('r.id_recepcion')->get() : collect();
        $incidents = $this->incidents($site->id_sede);
        $partialIds = collect();
        if ($available && Schema::hasTable('recepcion_detalles')) {
            $partialIds = DB::table('recepcion_detalles as d')->join('recepciones as r','r.id_recepcion','=','d.id_recepcion')->join('pedidos as p','p.id_pedido','=','r.id_pedido')
                ->where('p.id_sede',$site->id_sede)->get(['d.id_recepcion','d.observaciones','d.cantidad_entregada','d.cantidad_aceptada'])->filter(fn ($d) => (float) (json_decode($d->observaciones ?? '',true)['faltante_entrega'] ?? 0) > 0
                    || ((float)$d->cantidad_aceptada > 0 && (float)$d->cantidad_aceptada < (float)$d->cantidad_entregada))->pluck('id_recepcion')->unique();
        }
        $metrics = [ ['Pendientes de revisión',$all->where('estado',config('receptions.pending'))->count(),'Listas para verificar','clock','waiting'],
            ['Recepciones parciales',$partialIds->count(),'Con faltante documentado','box','waiting'],
            ['Con incidencias',$incidents->pluck('id_recepcion')->unique()->count(),'Diferencias reportadas','alert','danger'],
            ['Recepciones confirmadas',$all->where('estado','Confirmada')->count(),'Cantidades aceptadas ingresadas','check','ok'] ];
        $selected = $receipt ? $all->firstWhere('id_recepcion',$receipt) : null;
        abort_if($receipt && ! $selected,404);
        $receipts = $all->when(filled($filters['q'] ?? null),fn ($rows) => $rows->filter(fn ($r) => str_contains(mb_strtolower($r->proveedor_nombre.' '.$r->id_recepcion.' '.$r->id_pedido),mb_strtolower($filters['q']))))
            ->when($filters['estado'] ?? null,fn ($rows,$v) => $rows->where('estado',$v));
        $receipts = $this->page($receipts,$request,10);
        $selected ??= $receipts->first();
        $lines = collect(); $locations = collect();
        if ($selected) {
            $lines = DB::table('recepcion_detalles as rd')->join('detalle_pedidos as d','d.id_detalle_pedido','=','rd.id_detalle_pedido')
                ->leftJoin('insumos as i','i.id_insumo','=','d.id_insumo')->leftJoin('unidades as u','u.id_unidad','=','i.id_unidad_base')->leftJoin('lotes as l','l.id_lote','=','rd.id_lote')
                ->where('rd.id_recepcion',$selected->id_recepcion)->where('d.id_pedido',$selected->id_pedido)
                ->select('rd.*','d.cantidad as solicitada','i.nombre','l.codigo_lote','u.nombre as unidad_nombre')
                ->selectRaw(Schema::hasColumn('unidades','codigo') ? 'u.codigo as unidad' : 'NULL as unidad')
                ->selectRaw(Schema::hasColumn('insumos','categoria') ? 'i.categoria as categoria' : 'NULL as categoria')
                ->selectRaw(Schema::hasColumn('insumos','imagen_url') ? 'i.imagen_url as imagen_url' : 'NULL as imagen_url')->get();
            if (Schema::hasColumns('ubicacion_inventarios', ['id_ubicacion_inv', 'id_sede', 'nombre'])) {
                $locations = W::receivingLocations($site->id_sede)->orderBy('nombre')->get();
            }
        }
        $states = $all->pluck('estado')->filter()->unique();
        $canConfirm = W::confirmationAvailable();
        return view('almacen.workspace.receptions',compact('site','receipts','selected','lines','incidents','locations','metrics','filters','states','canConfirm'));
    }

    public function inventory(Request $request, SiteContext $context)
    {
        $site = W::site($request,$context); $all = W::stock($site->id_sede);
        $filters = $request->validate(['q'=>'nullable|string|max:150','estado'=>'nullable|string|max:30','categoria'=>'nullable|string|max:150','insumo'=>'nullable|integer']);
        $rows = $all->when(filled($filters['q'] ?? null),fn ($r) => $r->filter(fn ($s) => str_contains(mb_strtolower($s->nombre),mb_strtolower($filters['q']))))
            ->when($filters['categoria'] ?? null,fn ($r,$v) => $r->where('categoria',$v))
            ->when($filters['insumo'] ?? null,fn ($r,$v) => $r->where('id_insumo',(int) $v))
            ->when($filters['estado'] ?? null,fn ($r,$v) => $v === 'por-caducar' ? $r->whereNotNull('caducidad') : $r->filter(fn ($s) => mb_strtolower($s->estado_stock) === $v));
        $metrics = [ ['Insumos registrados',$all->count(),'Asignados a esta sede','box','info'], ['Existencias suficientes',$all->where('estado_stock','Suficiente')->count(),'Por encima del mínimo','check','ok'],
            ['Existencias bajas',$all->where('estado_stock','Bajo')->count(),'En el punto de reorden o debajo','alert','waiting'], ['Productos agotados',$all->where('estado_stock','Agotado')->count(),'Sin existencias disponibles','warehouse','danger'],
            ['Próximos a caducar',$all->whereNotNull('caducidad')->count(),'Lotes con saldo · próximos 30 días','calendar','orange'] ];
        $categories = $all->pluck('categoria')->filter()->unique();
        $distribution = $all->groupBy(fn ($r) => $r->categoria ?: 'Sin categoría')->map->count();
        $top = W::movementAvailable() ? W::movements($site->id_sede)->get()->groupBy('insumo')->map->count()->sortDesc()->take(6) : collect();
        $rows = $this->page($rows,$request);
        return view('almacen.workspace.inventory',compact('site','rows','metrics','filters','categories','distribution','top'));
    }

    public function movements(Request $request, SiteContext $context)
    {
        $site = W::site($request,$context); $available = W::movementAvailable();
        $filters = $request->validate(['desde'=>'nullable|date_format:Y-m-d','hasta'=>'nullable|date_format:Y-m-d|after_or_equal:desde','tipo'=>'nullable|string|max:50','insumo'=>'nullable|integer','responsable'=>'nullable|integer']);
        $all = $available ? W::movements($site->id_sede)->orderByDesc('m.fecha')->orderByDesc('m.id_inventario_final')->get() : collect();
        $today = $all->filter(fn ($m) => $m->fecha && \Illuminate\Support\Carbon::parse($m->fecha)->timezone('America/Mexico_City')->toDateString() === now('America/Mexico_City')->toDateString());
        $metrics = [ ['Entradas de hoy',$today->where('cantidad_firmada','>',0)->count(),'Registros de ingreso','box','ok'],['Salidas de hoy',$today->where('cantidad_firmada','<',0)->count(),'Registros de egreso','box','danger'],['Movimientos del día',$today->count(),'Operaciones trazables','layers','info'] ];
        if ($all->where('tipo','ajuste')->isNotEmpty()) $metrics[] = ['Ajustes registrados hoy',$today->where('tipo','ajuste')->count(),'Ajustes del historial autorizado','check','orange'];
        $rows = $all->filter(function ($m) use ($filters) {
            $date = $m->fecha ? \Illuminate\Support\Carbon::parse($m->fecha)->timezone('America/Mexico_City')->toDateString() : null;
            return (!isset($filters['desde']) || ($date && $date >= $filters['desde'])) && (!isset($filters['hasta']) || ($date && $date <= $filters['hasta']))
                && (empty($filters['tipo']) || $m->tipo === $filters['tipo']) && (empty($filters['insumo']) || (int) $m->id_insumo === (int) $filters['insumo'])
                && (empty($filters['responsable']) || (int) $m->id_usuario === (int) $filters['responsable']);
        });
        $types = $all->pluck('tipo')->unique(); $ingredients = $all->unique('id_insumo'); $users = $all->unique('id_usuario');
        $distribution = $rows->groupBy('tipo')->map->count(); $week = $this->weekly($rows);
        $rows = $this->page($rows,$request,20);
        return view('almacen.workspace.movements',compact('site','rows','metrics','filters','types','ingredients','users','distribution','week','available'));
    }

    public function alerts(Request $request, SiteContext $context)
    {
        $site = W::site($request,$context); $stock = W::stock($site->id_sede);
        $filters = $request->validate(['prioridad'=>'nullable|in:agotado,bajo,caducidad']);
        $groups = ['agotado'=>$stock->where('estado_stock','Agotado'), 'bajo'=>$stock->where('estado_stock','Bajo'), 'caducidad'=>$stock->whereNotNull('caducidad')];
        $metrics = [ ['Alertas críticas',$groups['agotado']->count(),'Productos agotados','alert','danger'],['Existencias bajas',$groups['bajo']->count(),'Reponer según mínimo','warehouse','waiting'],['Próximos a caducar',$groups['caducidad']->count(),'Próximos 30 días · lotes con saldo','calendar','orange'],['Requieren reabastecimiento',$groups['agotado']->merge($groups['bajo'])->unique('id_insumo')->count(),'Confirma cada pedido manualmente','box','info'] ];
        $distribution = collect(['Agotados'=>$groups['agotado']->count(),'Bajos'=>$groups['bajo']->count(),'Por caducar'=>$groups['caducidad']->count()]);
        $suppliers = DB::table('pedidos as p')->join('detalle_pedidos as d','d.id_pedido','=','p.id_pedido')->join('proveedores as pr','pr.id_proveedor','=','p.id_proveedor')
            ->where('p.id_sede',$site->id_sede)->orderByDesc('p.id_pedido')->get(['d.id_insumo','pr.nombre'])->unique('id_insumo')->keyBy('id_insumo');
        return view('almacen.workspace.alerts',compact('site','groups','metrics','distribution','suppliers','filters'));
    }

    public function notifications(Request $request, SiteContext $context)
    {
        $site = W::site($request,$context); $events = collect();
        $add = function ($id,$title,$description,$url,$tone,$date) use ($events) { $events->push(compact('id','title','description','url','tone','date')); };
        if (Schema::hasTable('recepciones')) foreach (W::receipts($site->id_sede)->where('r.estado',config('receptions.pending'))->get() as $r) {
            $add('review-'.$r->id_recepcion,'Recepción por revisar #'.$r->id_recepcion,$r->proveedor_nombre,route('receptions.review.show',$r->id_recepcion),'waiting',$r->fecha_recepcion);
            if (Schema::hasTable('recepcion_detalles')) foreach (DB::table('recepcion_detalles')->where('id_recepcion',$r->id_recepcion)->get() as $d) {
                if ((float) $d->cantidad_entregada > (float) $d->cantidad_aceptada) $add('rejected-'.$d->id_recepcion_detalle,'Mercancía rechazada','Recepción #'.$r->id_recepcion,route('receptions.review.show',$r->id_recepcion),'danger',$r->fecha_recepcion);
                if ((float) (json_decode($d->observaciones ?? '',true)['faltante_entrega'] ?? 0) > 0) $add('missing-'.$d->id_recepcion_detalle,'Faltante de mercancía','Recepción #'.$r->id_recepcion,route('receptions.review.show',$r->id_recepcion),'waiting',$r->fecha_recepcion);
            }
        }
        foreach (W::stock($site->id_sede) as $s) {
            if (in_array($s->estado_stock,['Agotado','Bajo'])) $add('stock-'.$s->id_insumo.'-'.hash('crc32b',(string) $s->stock),$s->estado_stock === 'Agotado' ? 'Producto agotado' : 'Existencias bajas',$s->nombre.' · '.W::quantity($s->stock).' '.$s->unidad,route('warehouse.alerts'),$s->tono,$s->ultimo_movimiento);
            if ($s->caducidad) $add('expiry-'.$s->id_insumo.'-'.$s->caducidad,'Caducidad próxima',$s->nombre.' · '.$s->caducidad,route('inventory.index',['estado'=>'por-caducar']),'orange',$s->caducidad);
        }
        foreach (W::orders($site->id_sede)->whereNotIn('p.id_pedido',W::receivedOrderIds($site->id_sede))->whereIn('p.estado',config('receptions.order_states',[]))->whereBetween('p.fecha_entrega',[now('America/Mexico_City')->toDateString(),now('America/Mexico_City')->addDays(7)->toDateString()])->get() as $o)
            $add('delivery-'.$o->id_pedido.'-'.$o->fecha_entrega,'Próxima entrega #'.$o->id_pedido,$o->proveedor_nombre.' · '.$o->fecha_entrega,route('orders.show',$o->id_pedido),'info',$o->fecha_entrega);
        $key = 'warehouse_notices.'.$request->user()->id_usuario.'.'.$site->id_sede.'.'.$request->user()->activeRoleId;
        $read = $request->session()->get($key,[]);
        if ($request->isMethod('post')) {
            $id = $request->validate(['id'=>'required|string|max:100'])['id'];
            $read = array_values(array_unique(array_merge($read,$id === 'all' ? $events->pluck('id')->all() : $events->where('id',$id)->pluck('id')->all())));
            $request->session()->put($key,array_slice($read,-500));
        }
        $items = $events->sortByDesc('date')->map(fn ($e) => $e + ['read'=>in_array($e['id'],$read,true)])->values();
        return response()->json(['items'=>$items,'unread'=>$items->where('read',false)->count()])->header('Cache-Control','no-store');
    }
}
