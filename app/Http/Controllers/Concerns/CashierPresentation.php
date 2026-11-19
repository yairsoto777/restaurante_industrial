<?php
namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Schema};
use App\Support\SiteContext;

/** Read-only workspace queries. Charging, opening and closing keep their existing handlers. */
trait CashierPresentation
{
    use CashierPendingPresentation;
    use CashierTicketPresentation;
    private function cashierPresentationResponse(Request $request,$response,string $module)
    {
        // AJAX keeps the flash message for the real navigation instead of consuming it in a followed GET.
        return $request->user()->hasRole('Cajero')&&$request->expectsJson()
            ? response()->json(['redirect'=>route('cash.index',['modulo'=>$module])]) : $response;
    }
    private function workspace(Request $request,SiteContext $context,?string $forcedModule=null)
    {
        $site=$this->site($request,$context);
        $module=$forcedModule??(string)$request->query('modulo','panel');
        abort_unless(in_array($module,['panel','turno','pendientes','historial','tickets','cierre'],true),404);
        $request->validate(['desde'=>['nullable','date_format:Y-m-d'],'hasta'=>array_merge(['nullable','date_format:Y-m-d'],$request->filled('desde')?['after_or_equal:desde']:[]),'metodo'=>['nullable','in:efectivo,tarjeta,transferencia'],'q'=>['nullable','string','max:120']]);
        $shift=$this->activeShift($site->id_sede,$request->user()->id_usuario);
        $accounts=$this->accounts($site->id_sede)->addSelect('cuenta.subtotal','a.comensales','z.nombre as zona')->get();
        $shiftPayments=$shift?DB::table('caja_pagos')->where('id_turno_caja',$shift->id_turno_caja)->where('id_sede',$site->id_sede)->where('estado','pagado')->get():collect();
        $cashReceived=(float)$shiftPayments->where('metodo','efectivo')->sum(fn($p)=>(float)($p->recibido??$p->importe));
        $changeGiven=(float)$shiftPayments->where('metodo','efectivo')->sum('cambio');
        $cashExpected=round((float)($shift->fondo_inicial??0)+$cashReceived-$changeGiven,2);
        $responsible=$shift?DB::table('usuarios')->where('id_usuario',$shift->id_usuario_apertura)->first():null;
        $ledger=DB::table('caja_pagos as p')->join('atenciones as a','a.id_atencion','=','p.id_atencion')->join('servicios as s','s.id_servicio','=','a.id_servicio')->join('mesas as m','m.id_mesa','=','a.id_mesa')->join('zonas as z','z.id_zona','=','m.id_zona')->leftJoin('servicios_ofrecidos as o','o.id_atencion','=','a.id_atencion')->leftJoin('usuarios as u','u.id_usuario','=','p.id_usuario')->where('p.id_sede',$site->id_sede)->where('s.id_sede',$site->id_sede)->where('z.id_sede',$site->id_sede)->select('p.*','m.numero as mesa','s.nombre as servicio','z.nombre as zona','o.descripcion as concepto','u.nombre as cajero_nombre','u.apellido_p as cajero_apellido');
        $todayPayments=(clone $ledger)->whereDate('p.created_at',now()->toDateString())->where('p.estado','pagado')->get();
        $recent=(clone $ledger)->orderByDesc('p.created_at')->orderByDesc('p.id_pago')->limit(6)->get();
        $filtered=(clone $ledger)->when($request->filled('desde'),fn($q)=>$q->whereDate('p.created_at','>=',$request->query('desde')))->when($request->filled('hasta'),fn($q)=>$q->whereDate('p.created_at','<=',$request->query('hasta')))->when($request->filled('metodo'),fn($q)=>$q->where('p.metodo',$request->query('metodo')))->when($request->filled('q'),function($q)use($request){$term='%'.$request->query('q').'%';$q->where(fn($q)=>$q->where('o.descripcion','like',$term)->orWhere('s.nombre','like',$term)->orWhere('u.nombre','like',$term)->orWhere('u.apellido_p','like',$term)->orWhere('m.numero','like',$term)->orWhere('p.id_pago','like',$term)->orWhere('a.id_atencion','like',$term));});
        if($module==='tickets'){
            $request->validate(['estado'=>['nullable','string','max:30'],'turno'=>['nullable','integer','min:1'],'orden'=>['nullable','in:reciente,antiguo,importe_desc,importe_asc']]);
            $filtered->when($request->filled('estado'),fn($q)=>$q->where('p.estado',$request->query('estado')))->when($request->filled('turno'),fn($q)=>$q->where('p.id_turno_caja',$request->integer('turno')));
        }
        $period=(clone $filtered)->where('p.estado','pagado');
        $periodTotal=(float)(clone $period)->sum('p.importe');
        $periodCount=(clone $period)->count();
        $periodMethods=(clone $period)->select('p.metodo')->selectRaw('COUNT(*) as cantidad, SUM(p.importe) as total')->groupBy('p.metodo')->get()->keyBy('metodo');
        $recordQuery=clone $filtered;
        if($module==='tickets'){
            [$sortField,$sortDirection]=match($request->query('orden','reciente')){'antiguo'=>['p.created_at','asc'],'importe_desc'=>['p.importe','desc'],'importe_asc'=>['p.importe','asc'],default=>['p.created_at','desc']};
            $recordQuery->orderBy($sortField,$sortDirection);
        }else{$recordQuery->orderByDesc('p.created_at');}
        $records=$recordQuery->orderByDesc('p.id_pago')->paginate(20)->withQueryString();
        $shifts=DB::table('caja_turnos as t')->leftJoin('usuarios as u','u.id_usuario','=','t.id_usuario_apertura')->where('t.id_sede',$site->id_sede)->select('t.*','u.nombre as cajero_nombre','u.apellido_p as cajero_apellido')->orderByDesc('t.abierta_at')->get();
        $notesSupported=Schema::hasColumn('caja_turnos','observaciones');
        $money=fn($value)=>'$'.number_format((float)$value,2);
        $date=fn($value)=>$value?\Carbon\Carbon::parse($value)->locale('es')->translatedFormat('d M Y · H:i'):'—';
        $link=fn($key,$extra=[])=>route('cash.index',array_merge(['modulo'=>$key],$extra));
        $methods=['efectivo'=>['Efectivo','green','cash'],'tarjeta'=>['Tarjeta','blue','credit-card'],'transferencia'=>['Transferencia','purple','transfer']];
        $turno=null;$turnoPayments=collect();
        if($module==='turno'){
            $turno=$request->filled('turno')?$shifts->firstWhere('id_turno_caja',$request->integer('turno')):($shifts->firstWhere('id_turno_caja',$shift?->id_turno_caja)??$shifts->first());
            abort_if($request->filled('turno')&&!$turno,404);
            if($turno)$turnoPayments=DB::table('caja_pagos as p')->leftJoin('servicios_ofrecidos as o','o.id_atencion','=','p.id_atencion')->where('p.id_sede',$site->id_sede)->where('p.id_turno_caja',$turno->id_turno_caja)->where('p.estado','pagado')->select('p.*','o.descripcion as concepto')->orderBy('p.created_at')->orderBy('p.id_pago')->get();
        }
        $pending=null;if($module==='pendientes'){$pending=$this->pendingPresentation($request,$site->id_sede);$accounts=$pending['accounts'];}
        $ticketDesk=$module==='tickets'?$this->ticketPresentation($request,$ledger,$filtered,$records,$shifts,$shift):null;
        return view('cash.workspace',compact('site','module','shift','accounts','shiftPayments','cashReceived','changeGiven','cashExpected','responsible','todayPayments','recent','periodTotal','periodCount','periodMethods','records','shifts','notesSupported','money','date','link','methods','turno','turnoPayments','pending','ticketDesk'));
    }
}
