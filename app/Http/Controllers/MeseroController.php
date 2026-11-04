<?php

namespace App\Http\Controllers;

use App\Support\SiteContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MeseroController extends Controller
{
    private function site(Request $request, SiteContext $context)
    {
        $site = $context->currentFor($request, $request->user());
        abort_unless($site, 403, 'No tienes una sede asignada.');
        return $site;
    }

    public function index(Request $request, SiteContext $context): View
    {
        $site = $this->site($request, $context);
        $userId = $request->user()->id_usuario;

        $zones = DB::table('zona_meseros as zm')
            ->join('zonas as z', 'z.id_zona', '=', 'zm.id_zona')
            ->where('zm.id_usuario_mesero', $userId)->where('zm.activo', 1)
            ->where('z.id_sede', $site->id_sede)->select('z.id_zona', 'z.nombre')->get();

        $tables = DB::table('mesas as m')->join('zonas as z', 'z.id_zona', '=', 'm.id_zona')
            ->whereIn('m.id_zona', $zones->pluck('id_zona'))->select('m.*', 'z.nombre as zona')->orderBy('z.nombre')->orderBy('m.numero')->get();

        $attentions = DB::table('atenciones as a')->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')
            ->join('mesas as m', 'm.id_mesa', '=', 'a.id_mesa')->join('zonas as z', 'z.id_zona', '=', 'm.id_zona')
            ->leftJoin('comandas as c', 'c.id_atencion', '=', 'a.id_atencion')
            ->where('a.id_usuario_mesero', $userId)->where('s.id_sede', $site->id_sede)
            ->select('a.*', 's.nombre as servicio', 's.fecha', 'm.numero as mesa', 'z.nombre as zona', 'c.id_comanda', 'c.estado as comanda_estado')
            ->orderByDesc('s.fecha')->orderBy('m.numero')->get();

        return view('mesero.index', compact('site', 'zones', 'tables', 'attentions'));
    }

    public function updateAttention(Request $request, SiteContext $context, int $attention)
    {
        $site = $this->site($request, $context);
        $data = $request->validate(['estado' => ['required', 'in:En atención,Cerrada']]);
        $record = DB::table('atenciones as a')->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')
            ->where('a.id_atencion', $attention)->where('a.id_usuario_mesero', $request->user()->id_usuario)->where('s.id_sede', $site->id_sede)->first(['a.id_atencion']);
        abort_unless($record, 404);
        DB::table('atenciones')->where('id_atencion', $attention)->update(['estado' => $data['estado'], 'inicio_hora' => $data['estado'] === 'En atención' ? now()->format('H:i:s') : DB::raw('inicio_hora'), 'fin_hora' => $data['estado'] === 'Cerrada' ? now()->format('H:i:s') : null]);
        return back()->with('status', 'Estado de la atención actualizado.');
    }

    public function storeComanda(Request $request, SiteContext $context, int $attention)
    {
        $site = $this->site($request, $context);
        $query = DB::table('atenciones as a')->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')->where('a.id_atencion', $attention)->where('a.id_usuario_mesero', $request->user()->id_usuario)->where('s.id_sede', $site->id_sede);
        abort_unless($query->exists(), 404);
        DB::table('comandas')->updateOrInsert(['id_atencion' => $attention], ['id_usuario_mesero' => $request->user()->id_usuario, 'fecha_apertura' => now(), 'estado' => 'Pendiente']);
        return back()->with('status', 'Comanda abierta y enviada a cocina.');
    }
}
