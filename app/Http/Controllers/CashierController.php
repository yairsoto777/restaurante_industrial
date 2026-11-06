<?php

namespace App\Http\Controllers;

use App\Support\SiteContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CashierController extends Controller
{
    public function index(Request $request, SiteContext $context): View
    {
        $site = $context->currentFor($request, $request->user());
        abort_unless($site && ($request->user()->canAdminister() || $request->user()->sites()->whereKey($site->id_sede)->exists()), 403);
        $accounts = DB::table('atenciones as a')->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')
            ->join('mesas as m', 'm.id_mesa', '=', 'a.id_mesa')->join('comandas as c', 'c.id_atencion', '=', 'a.id_atencion')
            ->join('comanda_detalles as d', 'd.id_comanda', '=', 'c.id_comanda')->join('menus as menu', 'menu.id_menu', '=', 'd.id_menu')
            ->join('platillos as p', 'p.id_platillo', '=', 'menu.id_platillo')
            ->where('s.id_sede', $site->id_sede)->where(function ($query) { $query->whereNull('a.estado')->orWhere('a.estado', '<>', 'Cerrada'); })
            ->select('a.id_atencion', 'm.numero as mesa', 's.nombre as servicio')->selectRaw('SUM(d.cantidad * COALESCE(menu.precio_venta, 0)) as total')
            ->selectRaw("GROUP_CONCAT(CONCAT(d.cantidad, ' x ', p.nombre) SEPARATOR ', ') as items")
            ->groupBy('a.id_atencion', 'm.numero', 's.nombre')->orderBy('m.numero')->get();
        return view('cash.index', compact('site', 'accounts'));
    }

    public function charge(Request $request, SiteContext $context, int $attention)
    {
        $site = $context->currentFor($request, $request->user());
        abort_unless($site, 403);
        $data = $request->validate(['metodo' => ['required', 'in:efectivo,tarjeta,transferencia']]);
        $account = DB::table('atenciones as a')->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')
            ->join('comandas as c', 'c.id_atencion', '=', 'a.id_atencion')->join('comanda_detalles as d', 'd.id_comanda', '=', 'c.id_comanda')
            ->join('menus as m', 'm.id_menu', '=', 'd.id_menu')->where('a.id_atencion', $attention)->where('s.id_sede', $site->id_sede)
            ->selectRaw('SUM(d.cantidad * COALESCE(m.precio_venta, 0)) as total')->first();
        abort_unless($account, 404);
        DB::table('atenciones')->where('id_atencion', $attention)->update(['estado' => 'Cerrada', 'fin_hora' => now()->format('H:i:s')]);
        return back()->with('status', 'Cobro registrado correctamente.');
    }
}
