<?php
namespace App\Http\Controllers\Concerns;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Schema,Storage};
use App\Models\Employee;
use App\Support\EmployeePresentation;
use Carbon\Carbon;

trait CashierPendingPresentation
{
    /** Read-only presentation; charging remains in the existing controller. */
    private function pendingPresentation(Request $request,int $site):array
    {
        $request->validate(['servicio'=>['nullable','integer'],'orden'=>['nullable','in:espera_desc,espera_asc,importe_desc,importe_asc,registro']]);
        $rows=$this->accounts($site)->leftJoin('usuarios as mesero','mesero.id_usuario','=','a.id_usuario_mesero')->addSelect('cuenta.subtotal','cuenta.fecha_apertura as registrado_en','ofrecido.finalizado_en as listo_en','s.fecha as jornada','a.id_servicio','a.id_usuario_mesero','a.comensales','z.nombre as zona','mesero.nombre as mesero_nombre','mesero.apellido_p as mesero_apellido','mesero.apellido_m as mesero_apellido_m')->get();
        $services=$rows->map(fn($a)=>['id'=>$a->id_servicio,'nombre'=>$a->servicio,'fecha'=>$a->jornada])->unique('id')->values();
        $legacy=DB::table('comandas as c')->join('comanda_detalles as d','d.id_comanda','=','c.id_comanda')->join('menus as menu','menu.id_menu','=','d.id_menu')->join('platillos as p','p.id_platillo','=','menu.id_platillo')->whereIn('c.id_atencion',$rows->whereNull('id_servicio_ofrecido')->pluck('id_atencion'))->whereIn('c.estado',['Pendiente de pago','Cerrada'])->whereNotIn('d.estado',['cancelado','Cancelado'])->get(['c.id_atencion','p.nombre','d.cantidad','d.precio_unitario'])->groupBy('id_atencion');
        $photos=[];
        if(Schema::hasTable('empleados'))foreach(Employee::where('id_sede',$site)->whereIn('id_usuario',$rows->pluck('id_usuario_mesero'))->get() as $employee){$location=app(EmployeePresentation::class)->photoLocation($employee);if($location&&$location['disk']==='public')$photos[$employee->id_usuario]=Storage::disk('public')->url($location['path']);}
        foreach($rows as $row){
            $row->mesero=trim($row->mesero_nombre.' '.$row->mesero_apellido.' '.$row->mesero_apellido_m);$row->iniciales=collect(explode(' ',$row->mesero))->filter()->take(2)->map(fn($n)=>mb_substr($n,0,1))->implode('');$row->foto=$photos[$row->id_usuario_mesero]??null;
            $row->espera=null;
            // Only an actual recorded handoff establishes waiting time.
            if($row->listo_en){$seconds=Carbon::parse($row->listo_en)->diffInSeconds(now(),false);if($seconds>=0)$row->espera=(int)$seconds;}
            $row->conceptos=$row->id_servicio_ofrecido?collect([(object)['cantidad'=>$row->ofrecido_comensales,'nombre'=>$row->ofrecido_descripcion,'precio_unitario'=>$row->ofrecido_precio_unitario,'importe'=>$row->total]]):$legacy->get($row->id_atencion,collect())->map(fn($i)=>(object)['cantidad'=>$i->cantidad,'nombre'=>$i->nombre,'precio_unitario'=>$i->precio_unitario,'importe'=>(float)$i->cantidad*(float)$i->precio_unitario]);
            $row->fecha_consulta=substr((string)($row->registrado_en??$row->jornada),0,10);
        }
        $rows=$rows->filter(function($a)use($request){if($request->filled('desde')&&$a->fecha_consulta<$request->query('desde'))return false;if($request->filled('hasta')&&$a->fecha_consulta>$request->query('hasta'))return false;if($request->filled('servicio')&&(int)$a->id_servicio!==$request->integer('servicio'))return false;$text=mb_strtolower($a->mesa.' '.$a->id_atencion.' '.$a->items.' '.$a->servicio.' '.$a->mesero);return !$request->filled('q')||str_contains($text,mb_strtolower($request->query('q')));});
        $rows=match($request->query('orden','espera_desc')){'importe_desc'=>$rows->sortByDesc(fn($a)=>(float)$a->total),'importe_asc'=>$rows->sortBy(fn($a)=>(float)$a->total),'espera_asc'=>$rows->sortBy(fn($a)=>$a->espera??PHP_INT_MAX),'registro'=>$rows->sortBy(fn($a)=>$a->registrado_en?-Carbon::parse($a->registrado_en)->timestamp:PHP_INT_MAX),default=>$rows->sortBy(fn($a)=>$a->espera!==null?-$a->espera:PHP_INT_MAX)};
        return ['accounts'=>$rows->values(),'services'=>$services,'knownWait'=>$rows->whereNotNull('espera')->count(),'urgent'=>$rows->filter(fn($a)=>$a->espera!==null&&$a->espera>1200)->count()];
    }
}
