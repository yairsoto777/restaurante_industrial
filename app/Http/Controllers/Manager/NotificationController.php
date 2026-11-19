<?php
namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Support\SiteContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NotificationController extends Controller
{
    public function index(Request $request, SiteContext $context) { return $this->feed($request, $context); }
    public function read(Request $request, SiteContext $context) {
        $request->validate(['id' => 'required|string|max:100']);
        return $this->feed($request, $context, $request->input('id'));
    }
    private function feed(Request $request, SiteContext $context, ?string $read = null) {
        $user = $request->user();
        $site = $context->currentFor($request, $user);
        abort_unless($site && $user->activeRoleId && $user->isManager() && $user->isManagerAtSite((int) $site->getKey()), 403);
        $sid = (int) $site->getKey(); $events = [];
        $add = function ($id, $title, $description, $url, $tone, $date = null) use (&$events) {
            $events[$id] = compact('id','title','description','url','tone') + ['date' => $date ?: now()->toIso8601String(), 'read' => false];
        };
        if (\App\Support\ServiceRecurrence::ready()) foreach(DB::table('service_schedule_revisions as r')->join('service_schedules as s','s.id','=','r.schedule_id')->where('s.id_sede',$sid)->where('r.estado','Pendiente')->where('r.id_usuario','!=',$user->getKey())->get(['r.*']) as $r) {
            $settings=\App\Support\ServiceRecurrence::settings($r);
            $add('schedule-'.$r->id,'Programación pendiente de aprobación',$settings['nombre'],route('manager.services.index'),'waiting',$r->created_at);
        }
        if (Schema::hasColumn('servicios','estado_aprobacion')) foreach (DB::table('servicios')->where('id_sede',$sid)->when(\App\Support\ServiceRecurrence::ready(),fn($q)=>$q->whereNull('schedule_id'))->where('estado_aprobacion','Pendiente')->where(fn ($q) => $q->whereNull('id_usuario_registro')->orWhere('id_usuario_registro','!=',$user->getKey()))->get() as $r)
            $add('service-'.$r->id_servicio,'Servicio pendiente de aprobación',$r->nombre,route('manager.services.index',['q'=>$r->nombre]),'waiting',$r->fecha);
        if (Schema::hasTable('solicitudes_empleo')) foreach (DB::table('solicitudes_empleo')->where('id_sede',$sid)->where('estado','pendiente')->where('id_usuario','!=',$user->getKey())->get() as $r)
            $add('application-'.$r->id_solicitud,'Solicitud de empleo pendiente','Solicitud #'.$r->id_solicitud.' requiere autorización.',route('manager.applications.show',$r->id_solicitud),'waiting',$r->fecha_solicitud);
        if (Schema::hasTable('recepciones') && Schema::hasTable('pedidos')) foreach (DB::table('recepciones as r')->join('pedidos as p','p.id_pedido','=','r.id_pedido')->where('p.id_sede',$sid)->where('r.estado','Confirmada')->select('r.*')->get() as $r)
            $add('receipt-'.$r->id_recepcion,'Recepción completada','Recepción #'.$r->id_recepcion.' confirmada.',route('receptions.orders.show',$r->id_pedido),'ok',$r->fecha_recepcion);
        if (Schema::hasColumn('incidencias','id_recepcion_detalle') && Schema::hasTable('recepcion_detalles')) foreach (DB::table('incidencias as i')->join('recepcion_detalles as d','d.id_recepcion_detalle','=','i.id_recepcion_detalle')->join('recepciones as r','r.id_recepcion','=','d.id_recepcion')->join('pedidos as p','p.id_pedido','=','r.id_pedido')->where('i.id_sede',$sid)->where('p.id_sede',$sid)->select('i.*','p.id_pedido')->get() as $r)
            $add('incident-'.$r->id_incidencia,'Incidencia en recepción',$r->descripcion ?: 'Revisa el pedido.',route('receptions.orders.show',$r->id_pedido),'danger');
        if (Schema::hasColumn('insumo_sedes','punto_de_reorden')) foreach (DB::table('insumo_sedes')->where('id_sede',$sid)->where(fn ($q) => $q->where('stock','<=',0)->orWhereColumn('stock','<=','punto_de_reorden'))->get() as $r)
            $add('stock-'.$r->id_insumo_sede,'Alerta de inventario','Insumo #'.$r->id_insumo.': existencia '.$r->stock.'.',route('inventory.index'),$r->stock <= 0 ? 'danger' : 'waiting');
        // Durable private files: no writes to the recovered application database.
        $dir = storage_path('app/private/manager-notifications');
        if (!is_dir($dir) && !mkdir($dir,0750,true) && !is_dir($dir)) abort(503);
        $file = fopen($dir.'/'.hash('sha256',$user->getKey().':'.$sid.':'.$user->activeRoleId).'.json','c+');
        abort_unless($file && flock($file,LOCK_EX),503);
        try {
            $raw = stream_get_contents($file);
            $history = $raw === '' ? [] : json_decode($raw,true,512,JSON_THROW_ON_ERROR);
            foreach ($events as $id => $event) if (!isset($history[$id])) $history[$id] = $event;
            foreach ($history as $id => &$item) if ($read === 'all' || $read === $id) $item['read'] = true;
            unset($item);
            $json = json_encode($history,JSON_THROW_ON_ERROR);
            rewind($file); ftruncate($file,0);
            if (fwrite($file,$json) !== strlen($json)) abort(503);
            fflush($file);
        } finally { flock($file,LOCK_UN); fclose($file); }
        $items = array_values($history);
        usort($items,fn ($a,$b) => strcmp($b['date'],$a['date']));
        return response()->json(['items'=>$items,'unread'=>count(array_filter($items,fn ($i) => !$i['read']))])->header('Cache-Control','no-store');
    }
}
