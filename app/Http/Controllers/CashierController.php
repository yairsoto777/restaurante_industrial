<?php

namespace App\Http\Controllers;

use App\Support\SiteContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CashierController extends Controller
{
    public function index(Request $request, SiteContext $context)
    {
        $site = $this->site($request, $context);
        $shift = $this->activeShift($site->id_sede, $request->user()->id_usuario);
        $accounts = $this->accounts($site->id_sede)->get();
        $payments = $this->payments($site->id_sede)->limit(10)->get();
        $shiftPayments = $shift
            ? DB::table('caja_pagos')->where('id_turno_caja', $shift->id_turno_caja)->where('estado', 'pagado')->get()
            : collect();
        $cashPayments = $shiftPayments->where('metodo', 'efectivo');
        $cashReceived = (float) $cashPayments->sum(fn ($payment) => (float) ($payment->recibido ?? $payment->importe));
        $changeGiven = (float) $cashPayments->sum(fn ($payment) => (float) $payment->cambio);
        $cashExpected = round((float) ($shift->fondo_inicial ?? 0) + $cashReceived - $changeGiven, 2);
        $otherMethods = (float) $shiftPayments->whereIn('metodo', ['tarjeta', 'transferencia'])->sum('importe');

        return view('cash.index', compact('site', 'shift', 'accounts', 'payments', 'shiftPayments', 'cashReceived', 'changeGiven', 'cashExpected', 'otherMethods'));
    }

    public function history(Request $request, SiteContext $context)
    {
        $site = $this->site($request, $context);
        $paymentTotals = DB::table('caja_pagos')
            ->select('id_turno_caja')
            ->selectRaw("SUM(CASE WHEN metodo = 'efectivo' AND estado = 'pagado' THEN COALESCE(recibido, importe) ELSE 0 END) as efectivo_ingresado")
            ->selectRaw("SUM(CASE WHEN metodo = 'efectivo' AND estado = 'pagado' THEN cambio ELSE 0 END) as cambio_entregado")
            ->groupBy('id_turno_caja');
        $userName = DB::connection()->getDriverName() === 'sqlite'
            ? "u.nombre || ' ' || COALESCE(u.apellido_p, '')"
            : "CONCAT(u.nombre, ' ', COALESCE(u.apellido_p, ''))";
        $shifts = DB::table('caja_turnos as turno')
            ->leftJoinSub($paymentTotals, 'movimientos', fn ($join) => $join->on('movimientos.id_turno_caja', '=', 'turno.id_turno_caja'))
            ->leftJoin('usuarios as u', 'u.id_usuario', '=', 'turno.id_usuario_apertura')
            ->where('turno.id_sede', $site->id_sede)
            ->select('turno.*', DB::raw($userName.' as usuario_apertura'))
            ->selectRaw('COALESCE(movimientos.efectivo_ingresado, 0) as efectivo_ingresado')
            ->selectRaw('COALESCE(movimientos.cambio_entregado, 0) as cambio_entregado')
            ->selectRaw('COALESCE(turno.efectivo_esperado, turno.fondo_inicial + COALESCE(movimientos.efectivo_ingresado, 0) - COALESCE(movimientos.cambio_entregado, 0)) as saldo_esperado')
            ->orderByDesc('turno.abierta_at')->orderByDesc('turno.id_turno_caja')->get();

        return view('cash.history', compact('site', 'shifts'));
    }

    public function charge(Request $request, SiteContext $context, int $attention)
    {
        $site = $this->site($request, $context);
        $data = $request->validate([
            'metodo' => ['required', Rule::in(['efectivo', 'tarjeta', 'transferencia'])],
            'recibido' => ['required_if:metodo,efectivo', 'nullable', 'numeric', 'min:0'],
        ]);
        $shift = $this->activeShift($site->id_sede, $request->user()->id_usuario);
        abort_unless($shift, 422, 'Debes abrir una caja antes de registrar cobros.');

        DB::transaction(function () use ($data, $site, $shift, $attention, $request): void {
            $account = $this->accounts($site->id_sede)->where('a.id_atencion', $attention)->lockForUpdate()->first();
            abort_unless($account, 404, 'La cuenta no pertenece a la sede activa.');
            abort_if(DB::table('caja_pagos')->where('id_atencion', $attention)->where('estado', 'pagado')->exists(), 422, 'La cuenta ya fue cobrada.');
            if ($account->id_servicio_ofrecido) {
                abort_unless($account->estado_ofrecido === 'Pendiente de cobro', 422, 'El servicio ofrecido ya fue liquidado o no está pendiente.');
            }
            $received = $data['metodo'] === 'efectivo'
                ? round((float) $data['recibido'], 2)
                : round((float) $account->total, 2);
            abort_if($data['metodo'] === 'efectivo' && $received < (float) $account->total, 422, 'El efectivo recibido no cubre el total.');
            DB::table('caja_pagos')->insert([
                'id_turno_caja' => $shift->id_turno_caja, 'id_atencion' => $attention,
                'id_usuario' => $request->user()->id_usuario, 'id_sede' => $site->id_sede,
                'metodo' => $data['metodo'], 'importe' => $account->total, 'recibido' => $received,
                'cambio' => max(0, round($received - $account->total, 2)), 'estado' => 'pagado', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('cuentas')->where('id_atencion', $attention)->update(['estado' => 'pagada', 'fecha_cierre' => now()]);
            DB::table('atenciones')->where('id_atencion', $attention)->update(['estado' => 'Cerrada', 'fin_hora' => now()->format('H:i:s')]);
            if ($account->id_servicio_ofrecido) {
                DB::table('servicios_ofrecidos')->where('id_servicio_ofrecido', $account->id_servicio_ofrecido)
                    ->update(['estado' => 'Pagado', 'pagado_en' => now(), 'updated_at' => now()]);
            } else {
                DB::table('comandas')->where('id_atencion', $attention)->update(['estado' => 'Pagada']);
            }
        });
        return back()->with('status', 'Cobro registrado correctamente.');
    }

    public function open(Request $request, SiteContext $context)
    {
        $site = $this->site($request, $context);
        $data = $request->validate(['fondo_inicial' => ['required', 'numeric', 'min:0']]);
        if (DB::table('caja_turnos')->where('id_sede', $site->id_sede)->where('estado', 'abierto')->exists()) {
            return back()->withErrors(['fondo_inicial' => 'Ya existe un turno abierto en esta sede.'])->withInput();
        }
        DB::table('caja_turnos')->insert(['id_sede' => $site->id_sede, 'id_usuario_apertura' => $request->user()->id_usuario, 'fondo_inicial' => $data['fondo_inicial'], 'estado' => 'abierto', 'abierta_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('status', 'Caja abierta correctamente.');
    }

    public function close(Request $request, SiteContext $context)
    {
        $site = $this->site($request, $context);
        $data = $request->validate(['efectivo_contado' => ['required', 'numeric', 'min:0']]);

        try {
            DB::transaction(function () use ($site, $data): void {
                $shift = DB::table('caja_turnos')->where('id_sede', $site->id_sede)
                    ->where('estado', 'abierto')->lockForUpdate()->first();

                if (!$shift) {
                    throw ValidationException::withMessages(['caja' => 'No hay una caja abierta para cerrar en esta sede.']);
                }

                $cashPayments = DB::table('caja_pagos')->where('id_turno_caja', $shift->id_turno_caja)
                    ->where('metodo', 'efectivo')->where('estado', 'pagado');
                $received = (float) (clone $cashPayments)->sum(DB::raw('COALESCE(recibido, importe)'));
                $change = (float) (clone $cashPayments)->sum('cambio');
                $expected = round((float) $shift->fondo_inicial + $received - $change, 2);

                // Usar la PK real de caja_turnos y actualizar dentro de la transacción
                // que protege el turno bloqueado contra cierres simultáneos.
                $updated = DB::table('caja_turnos')->where('id_turno_caja', $shift->id_turno_caja)
                    ->where('estado', 'abierto')->update([
                        'estado' => 'cerrado',
                        'efectivo_esperado' => $expected,
                        'efectivo_contado' => round((float) $data['efectivo_contado'], 2),
                        'diferencia' => round((float) $data['efectivo_contado'] - $expected, 2),
                        'cerrada_at' => now(),
                        'updated_at' => now(),
                    ]);

                if ($updated !== 1) {
                    throw ValidationException::withMessages(['caja' => 'El turno no se pudo cerrar. Actualiza la página y vuelve a intentarlo.']);
                }
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('No se pudo cerrar el turno de caja.', [
                'id_sede' => $site->id_sede,
                'id_usuario' => $request->user()->id_usuario,
                'error' => $exception->getMessage(),
            ]);

            return back()->withErrors(['caja' => 'No fue posible cerrar la caja. No se modificaron los movimientos; revisa e inténtalo de nuevo.'])->withInput();
        }

        return back()->with('status', 'Cierre de caja registrado.');
    }

    public function ticket(Request $request, SiteContext $context, int $payment)
    {
        $site = $this->site($request, $context);
        $cashierName = DB::connection()->getDriverName() === 'sqlite'
            ? "u.nombre || ' ' || COALESCE(u.apellido_p, '')"
            : "CONCAT(u.nombre, ' ', COALESCE(u.apellido_p, ''))";
        $receipt = DB::table('caja_pagos as pago')
            ->join('atenciones as a', 'a.id_atencion', '=', 'pago.id_atencion')
            ->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')
            ->join('mesas as m', 'm.id_mesa', '=', 'a.id_mesa')
            ->leftJoin('servicios_ofrecidos as ofrecido', 'ofrecido.id_atencion', '=', 'a.id_atencion')
            ->leftJoin('usuarios as u', 'u.id_usuario', '=', 'pago.id_usuario')
            ->where('pago.id_pago', $payment)->where('pago.id_sede', $site->id_sede)
            ->select('pago.*', 'a.id_atencion', 's.nombre as servicio', 'm.numero as mesa',
                'ofrecido.descripcion as ofrecido_descripcion', 'ofrecido.comensales as ofrecido_comensales',
                'ofrecido.precio_unitario as ofrecido_precio_unitario',
                DB::raw($cashierName.' as cajero'))->first();
        abort_unless($receipt, 404);
        $items = $receipt->ofrecido_descripcion ? collect() : DB::table('comandas as c')->join('comanda_detalles as d', 'd.id_comanda', '=', 'c.id_comanda')->join('menus as menu', 'menu.id_menu', '=', 'd.id_menu')->join('platillos as p', 'p.id_platillo', '=', 'menu.id_platillo')->where('c.id_atencion', $receipt->id_atencion)->select('p.nombre', 'd.cantidad', 'menu.precio_venta')->get();
        return view('cash.ticket', compact('receipt', 'items', 'site'));
    }

    private function site(Request $request, SiteContext $context) { $site = $context->currentFor($request, $request->user()); abort_unless($site && $context->userCanAccess($request->user(), $site->id_sede), 403); return $site; }
    private function activeShift(int $site, int $user) { return DB::table('caja_turnos')->where('id_sede', $site)->where('estado', 'abierto')->first(); }
    private function accounts(int $site)
    {
        $legacyItems = DB::table('comandas as c')->join('comanda_detalles as d', 'd.id_comanda', '=', 'c.id_comanda')
            ->join('menus as menu', 'menu.id_menu', '=', 'd.id_menu')->join('platillos as p', 'p.id_platillo', '=', 'menu.id_platillo')
            ->whereIn('c.estado', ['Pendiente de pago', 'Cerrada'])->whereNotIn('d.estado', ['cancelado', 'Cancelado'])
            ->select('c.id_atencion')->selectRaw('SUM(d.cantidad * d.precio_unitario) as productos_total');
        if (DB::connection()->getDriverName() === 'sqlite') {
            $legacyItems->selectRaw("GROUP_CONCAT(CAST(d.cantidad AS TEXT) || ' x ' || p.nombre, ', ') as productos");
        } else {
            $legacyItems->selectRaw("GROUP_CONCAT(CONCAT(d.cantidad, ' x ', p.nombre) SEPARATOR ', ') as productos");
        }
        $legacyItems->groupBy('c.id_atencion');

        return DB::table('cuentas as cuenta')->join('atenciones as a', 'a.id_atencion', '=', 'cuenta.id_atencion')
            ->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')->join('mesas as m', 'm.id_mesa', '=', 'a.id_mesa')
            ->join('zonas as z', 'z.id_zona', '=', 'm.id_zona')->leftJoin('servicios_ofrecidos as ofrecido', 'ofrecido.id_atencion', '=', 'a.id_atencion')
            ->leftJoinSub($legacyItems, 'productos', fn ($join) => $join->on('productos.id_atencion', '=', 'a.id_atencion'))
            ->where('s.id_sede', $site)->where('a.estado', 'Pendiente de pago')->where('cuenta.estado', 'pendiente')
            ->where(fn ($query) => $query->where('ofrecido.estado', 'Pendiente de cobro')->orWhereNotNull('productos.id_atencion'))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('caja_pagos as pago')
                ->whereColumn('pago.id_atencion', 'a.id_atencion')->where('pago.estado', 'pagado'))
            ->select('a.id_atencion', 'm.numero as mesa', 's.nombre as servicio', 'cuenta.estado as cuenta_estado',
                'ofrecido.id_servicio_ofrecido', 'ofrecido.estado as estado_ofrecido',
                'ofrecido.descripcion as ofrecido_descripcion', 'ofrecido.comensales as ofrecido_comensales',
                'ofrecido.precio_unitario as ofrecido_precio_unitario')
            ->selectRaw('COALESCE(ofrecido.total, productos.productos_total, cuenta.total) as total')
            ->selectRaw('COALESCE(ofrecido.descripcion, productos.productos, s.nombre) as items')
            ->orderBy('m.numero');
    }
    private function payments(int $site)
    {
        $name = DB::connection()->getDriverName() === 'sqlite'
            ? "u.nombre || ' ' || COALESCE(u.apellido_p, '')"
            : "CONCAT(u.nombre, ' ', COALESCE(u.apellido_p, ''))";

        return DB::table('caja_pagos as p')->leftJoin('usuarios as u', 'u.id_usuario', '=', 'p.id_usuario')
            ->where('p.id_sede', $site)->select('p.*', DB::raw($name.' as cajero'))->latest('p.created_at');
    }
}
