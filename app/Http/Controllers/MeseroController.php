<?php

namespace App\Http\Controllers;

use App\Support\SiteContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MeseroController extends Controller
{
    public function services(Request $request, SiteContext $context): View
    {
        $site = $this->site($request, $context);
        $search = trim((string) $request->query('q', ''));
        $state = (string) $request->query('estado', '');
        if (!in_array($state, ['Programado', 'En curso', 'Finalizado', 'Cancelado'], true)) {
            $state = '';
        }
        $base = DB::table('servicios')->where('id_sede', $site->id_sede)->where('estado_aprobacion', 'Aprobado');
        $total = (clone $base)->count();
        $today = (clone $base)->whereDate('fecha', now()->toDateString())->count();
        $active = (clone $base)->where('estado', 'En curso')->count();
        $services = (clone $base)
            ->when($search !== '', fn ($query) => $query->where('nombre', 'like', '%'.$search.'%'))
            ->when($state !== '', fn ($query) => $query->where('estado', $state))
            ->select('servicios.*')
            ->selectSub(DB::table('atenciones')->selectRaw('COUNT(*)')->whereColumn('atenciones.id_servicio', 'servicios.id_servicio')
                ->where('id_usuario_mesero', $request->user()->id_usuario), 'mis_atenciones')
            ->orderByDesc('fecha')->orderBy('inicio_hora')->paginate(15)->withQueryString();
        return view('mesero.services', compact('site', 'services', 'search', 'state', 'total', 'today', 'active'));
    }

    private function site(Request $request, SiteContext $context)
    {
        $site = $context->currentFor($request, $request->user());
        abort_unless($site && $request->user()->sites()->whereKey($site->id_sede)->exists(), 403, 'No tienes una sede asignada.');
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
        $record = $query->first(['a.estado', 's.estado as servicio_estado', 's.estado_aprobacion']);
        abort_unless($record, 404);
        abort_if($record->estado === 'Cerrada' || $record->estado_aprobacion !== 'Aprobado' || in_array($record->servicio_estado, ['Finalizado', 'Cancelado']), 422, 'La atención no admite nuevas comandas.');
        DB::transaction(function () use ($attention, $request) {
            DB::table('atenciones')->where('id_atencion', $attention)->lockForUpdate()->first();
            if (!DB::table('comandas')->where('id_atencion', $attention)->exists()) {
                DB::table('comandas')->insert(['id_atencion' => $attention, 'id_usuario_mesero' => $request->user()->id_usuario, 'fecha_apertura' => now(), 'estado' => 'Pendiente']);
            }
        });
        return back()->with('status', 'Comanda abierta y enviada a cocina.');
    }

    public function storeComandaItem(Request $request, SiteContext $context, int $attention)
    {
        $site = $this->site($request, $context);
        $data = $request->validate(['id_menu' => ['required', 'integer'], 'cantidad' => ['required', 'integer', 'min:1', 'max:100']]);
        $menu = DB::table('menus as m')->join('servicios as s', 's.id_servicio', '=', 'm.id_servicio')->where('m.id_menu', $data['id_menu'])
            ->where('s.id_sede', $site->id_sede)->where('m.publicado', 1)->where('m.disponible', 1)->whereNotNull('m.precio_venta')->first(['m.id_menu']);
        abort_unless($menu, 404);
        $comanda = DB::table('comandas as c')->join('atenciones as a', 'a.id_atencion', '=', 'c.id_atencion')
            ->where('c.id_atencion', $attention)->where('a.id_usuario_mesero', $request->user()->id_usuario)->first(['c.id_comanda']);
        abort_unless($comanda, 404);
        DB::table('comanda_detalles')->insert(['id_comanda' => $comanda->id_comanda, 'id_menu' => $menu->id_menu, 'cantidad' => $data['cantidad'], 'estado' => 'pendiente']);
        return back()->with('status', 'Platillo agregado a la comanda.');
    }
}
