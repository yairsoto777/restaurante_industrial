<?php
namespace App\Http\Controllers\Concerns;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Read-only ticket presentation. No issuance, payment or reprint records are written. */
trait CashierTicketPresentation
{
    private function ticketPresentation(Request $request,$ledger,$filtered,$records,$shifts,$shift):array
    {
        $selectedShift=$request->filled('turno')?$shifts->firstWhere('id_turno_caja',$request->integer('turno')):($shift??$shifts->first());
        abort_if($request->filled('turno')&&!$selectedShift,404);
        $recent=(clone $filtered)->reorder()->orderByDesc('p.created_at')->orderByDesc('p.id_pago')->limit(4)->get();
        $previewRows=$records->getCollection()->merge($recent)->unique('id_pago')->values();
        $offered=DB::table('servicios_ofrecidos')->whereIn('id_atencion',$previewRows->pluck('id_atencion'))->get()->keyBy('id_atencion');
        $legacy=DB::table('comandas as c')->join('comanda_detalles as d','d.id_comanda','=','c.id_comanda')->join('menus as menu','menu.id_menu','=','d.id_menu')->join('platillos as p','p.id_platillo','=','menu.id_platillo')->whereIn('c.id_atencion',$previewRows->pluck('id_atencion'))
            ->where(fn ($query) => $query->whereNotIn('d.estado', ['cancelado', 'Cancelado'])->orWhereNull('d.estado'))
            ->get(['c.id_atencion','p.nombre','d.cantidad','d.precio_unitario'])->groupBy('id_atencion');
        $guests=DB::table('atenciones')->whereIn('id_atencion',$previewRows->pluck('id_atencion'))->pluck('comensales','id_atencion');
        foreach($previewRows as $payment){
            $service=$offered->get($payment->id_atencion);
            $payment->comensales=$service?->comensales??$guests->get($payment->id_atencion);
            $payment->conceptos=$service?collect([(object)['cantidad'=>$service->comensales,'nombre'=>$service->descripcion,'precio_unitario'=>$service->precio_unitario,'importe'=>$service->total]]):$legacy->get($payment->id_atencion,collect())->map(fn($i)=>(object)['cantidad'=>$i->cantidad,'nombre'=>$i->nombre,'precio_unitario'=>$i->precio_unitario,'importe'=>(float)$i->cantidad*(float)$i->precio_unitario]);
        }
        return ['states'=>(clone $ledger)->reorder()->select('p.estado')->distinct()->orderBy('p.estado')->pluck('p.estado'),'shift'=>$selectedShift,'recent'=>$recent,'previews'=>$previewRows,'shiftCount'=>$selectedShift?(clone $filtered)->where('p.id_turno_caja',$selectedShift->id_turno_caja)->count():0];
    }
}
