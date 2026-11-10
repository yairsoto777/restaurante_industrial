<?php

namespace App\Http\Controllers;

use App\Support\SiteContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CashierController extends Controller
{
    public function index(Request $request, SiteContext $context)
    {
        $site = $this->site($request, $context);
        $shift = $this->activeShift($site->id_sede, $request->user()->id_usuario);
        $accounts = $this->accounts($site->id_sede)->get();
        $payments = $this->payments($site->id_sede)->limit(10)->get();
        $shiftPayments = $shift ? DB::table('caja_pagos')->where('id_turno_caja', $shift->id_turno_caja) : collect();

        return view('cash.index', compact('site', 'shift', 'accounts', 'payments', 'shiftPayments'));
    }

    public function charge(Request $request, SiteContext $context, int $attention)
    {
        $site = $this->site($request, $context);
        $data = $request->validate([
            'metodo' => ['required', Rule::in(['efectivo', 'tarjeta', 'transferencia'])],
            'recibido' => ['nullable', 'numeric', 'min:0'],
        ]);
        $shift = $this->activeShift($site->id_sede, $request->user()->id_usuario);
        abort_unless($shift, 422, 'Debes abrir una caja antes de registrar cobros.');

        DB::transaction(function () use ($data, $site, $shift, $attention, $request): void {
            $account = $this->accounts($site->id_sede)->where('a.id_atencion', $attention)->lockForUpdate()->first();
            abort_unless($account, 404, 'La cuenta no pertenece a la sede activa.');
            abort_if(DB::table('caja_pagos')->where('id_atencion', $attention)->where('estado', 'pagado')->exists(), 422, 'La cuenta ya fue cobrada.');
            $received = round((float) ($data['recibido'] ?? $account->total), 2);
            abort_if($data['metodo'] === 'efectivo' && $received < (float) $account->total, 422, 'El efectivo recibido no cubre el total.');
            DB::table('caja_pagos')->insert([
                'id_turno_caja' => $shift->id_turno_caja, 'id_atencion' => $attention,
                'id_usuario' => $request->user()->id_usuario, 'id_sede' => $site->id_sede,
                'metodo' => $data['metodo'], 'importe' => $account->total, 'recibido' => $received,
                'cambio' => max(0, round($received - $account->total, 2)), 'estado' => 'pagado', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('atenciones')->where('id_atencion', $attention)->update(['estado' => 'Cerrada', 'fin_hora' => now()->format('H:i:s')]);
        });
        return back()->with('status', 'Cobro registrado correctamente.');
    }

    public function open(Request $request, SiteContext $context)
    {
        $site = $this->site($request, $context);
        $data = $request->validate(['fondo_inicial' => ['required', 'numeric', 'min:0']]);
        abort_if(DB::table('caja_turnos')->where('id_sede', $site->id_sede)->where('estado', 'abierto')->exists(), 422, 'Ya existe un turno abierto en esta sede.');
        DB::table('caja_turnos')->insert(['id_sede' => $site->id_sede, 'id_usuario_apertura' => $request->user()->id_usuario, 'fondo_inicial' => $data['fondo_inicial'], 'estado' => 'abierto', 'abierta_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('status', 'Caja abierta correctamente.');
    }

    public function close(Request $request, SiteContext $context)
    {
        $site = $this->site($request, $context);
        $shift = DB::table('caja_turnos')->where('id_sede', $site->id_sede)->where('estado', 'abierto')->lockForUpdate()->first();
        abort_unless($shift, 422, 'No hay una caja abierta.');
        $data = $request->validate(['efectivo_contado' => ['required', 'numeric', 'min:0']]);
        $cash = (float) DB::table('caja_pagos')->where('id_turno_caja', $shift->id_turno_caja)->where('metodo', 'efectivo')->where('estado', 'pagado')->sum('importe');
        $expected = round((float) $shift->fondo_inicial + $cash, 2);
        DB::table('caja_turnos')->where('id_turno_caja', $shift->id_turno_caja)->update(['estado' => 'cerrado', 'efectivo_esperado' => $expected, 'efectivo_contado' => $data['efectivo_contado'], 'diferencia' => round($data['efectivo_contado'] - $expected, 2), 'cerrada_at' => now(), 'updated_at' => now()]);
        return back()->with('status', 'Cierre de caja registrado.');
    }

    public function ticket(Request $request, SiteContext $context, int $payment)
    {
        $site = $this->site($request, $context);
        $receipt = DB::table('caja_pagos as pago')
            ->join('atenciones as a', 'a.id_atencion', '=', 'pago.id_atencion')
            ->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')
            ->join('mesas as m', 'm.id_mesa', '=', 'a.id_mesa')
            ->leftJoin('usuarios as u', 'u.id_usuario', '=', 'pago.id_usuario')
            ->where('pago.id_pago', $payment)->where('pago.id_sede', $site->id_sede)
            ->select('pago.*', 'a.id_atencion', 's.nombre as servicio', 'm.numero as mesa', DB::raw("CONCAT(u.nombre, ' ', COALESCE(u.apellido_p, '')) as cajero"))->first();
        abort_unless($receipt, 404);
        $items = DB::table('comandas as c')->join('comanda_detalles as d', 'd.id_comanda', '=', 'c.id_comanda')->join('menus as menu', 'menu.id_menu', '=', 'd.id_menu')->join('platillos as p', 'p.id_platillo', '=', 'menu.id_platillo')->where('c.id_atencion', $receipt->id_atencion)->select('p.nombre', 'd.cantidad', 'menu.precio_venta')->get();
        return view('cash.ticket', compact('receipt', 'items', 'site'));
    }

    private function site(Request $request, SiteContext $context) { $site = $context->currentFor($request, $request->user()); abort_unless($site && $context->userCanAccess($request->user(), $site->id_sede), 403); return $site; }
    private function activeShift(int $site, int $user) { return DB::table('caja_turnos')->where('id_sede', $site)->where('estado', 'abierto')->first(); }
    private function accounts(int $site)
    { return DB::table('atenciones as a')->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')->join('mesas as m', 'm.id_mesa', '=', 'a.id_mesa')->join('comandas as c', 'c.id_atencion', '=', 'a.id_atencion')->join('comanda_detalles as d', 'd.id_comanda', '=', 'c.id_comanda')->join('menus as menu', 'menu.id_menu', '=', 'd.id_menu')->join('platillos as p', 'p.id_platillo', '=', 'menu.id_platillo')->where('s.id_sede', $site)->where(function ($q) { $q->whereNull('a.estado')->orWhereNotIn('a.estado', ['Cerrada', 'Cancelada']); })->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('caja_pagos as pago')->whereColumn('pago.id_atencion', 'a.id_atencion')->where('pago.estado', 'pagado'))->select('a.id_atencion','m.numero as mesa','s.nombre as servicio')->selectRaw('SUM(d.cantidad * COALESCE(menu.precio_venta, 0)) as total')->selectRaw("GROUP_CONCAT(CONCAT(d.cantidad, ' x ', p.nombre) SEPARATOR ', ') as items")->groupBy('a.id_atencion','m.numero','s.nombre')->orderBy('m.numero'); }
    private function payments(int $site) { return DB::table('caja_pagos as p')->leftJoin('usuarios as u', 'u.id_usuario', '=', 'p.id_usuario')->where('p.id_sede', $site)->select('p.*', DB::raw("CONCAT(u.nombre, ' ', COALESCE(u.apellido_p, '')) as cajero"))->latest('p.created_at'); }
}
