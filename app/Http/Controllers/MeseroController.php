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
            ->whereIn('m.id_zona', $zones->pluck('id_zona'))->select('m.*', 'z.nombre as zona')
            ->selectSub(DB::table('atenciones as active')->join('servicios as active_service', 'active_service.id_servicio', '=', 'active.id_servicio')->select('active.id_atencion')->whereColumn('active.id_mesa', 'm.id_mesa')->whereNotIn('active.estado', ['Cerrada', 'Cancelada'])->where('active_service.id_sede', $site->id_sede)->limit(1), 'active_attention_id')
            ->orderBy('z.nombre')->orderBy('m.numero')->get();

        $attentions = DB::table('atenciones as a')->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')
            ->join('mesas as m', 'm.id_mesa', '=', 'a.id_mesa')->join('zonas as z', 'z.id_zona', '=', 'm.id_zona')
            ->leftJoin('comandas as c', 'c.id_atencion', '=', 'a.id_atencion')
            ->where('a.id_usuario_mesero', $userId)->where('s.id_sede', $site->id_sede)
            ->select('a.*', 's.nombre as servicio', 's.fecha', 'm.numero as mesa', 'z.nombre as zona', 'c.id_comanda', 'c.estado as comanda_estado')
            ->orderByDesc('s.fecha')->orderBy('m.numero')->get();

        $services = DB::table('servicios')->where('id_sede', $site->id_sede)->where('estado_aprobacion', 'Aprobado')->whereIn('estado', ['Programado', 'En curso'])->orderByDesc('fecha')->orderBy('inicio_hora')->get();
        $menus = DB::table('menus as m')->join('servicios as s', 's.id_servicio', '=', 'm.id_servicio')->join('platillos as p', 'p.id_platillo', '=', 'm.id_platillo')->where('s.id_sede', $site->id_sede)->where('m.publicado', 1)->where('m.disponible', 1)->whereNotNull('m.precio_venta')->select('m.*', 'p.nombre as platillo', 's.nombre as servicio')->get();
        return view('mesero.index', compact('site', 'zones', 'tables', 'attentions', 'services', 'menus'));
    }

    public function storeAttention(Request $request, SiteContext $context)
    {
        $site = $this->site($request, $context);
        $data = $request->validate(['id_mesa' => ['required', 'integer'], 'id_servicio' => ['required', 'integer'], 'comensales' => ['required', 'integer', 'min:1'], 'observaciones' => ['nullable', 'string', 'max:1000']]);
        DB::transaction(function () use ($data, $site, $request): void {
            $table = DB::table('mesas as m')->join('zonas as z', 'z.id_zona', '=', 'm.id_zona')->where('m.id_mesa', $data['id_mesa'])->where('z.id_sede', $site->id_sede)->whereExists(fn ($q) => $q->from('zona_meseros as zm')->whereColumn('zm.id_zona', 'z.id_zona')->where('zm.id_usuario_mesero', $request->user()->id_usuario)->where('zm.activo', 1))->lockForUpdate()->first(['m.id_mesa', 'm.capacidad']);
            abort_unless($table, 403, 'La mesa no está asignada a tu zona.');
            abort_if($table->capacidad && $data['comensales'] > $table->capacidad, 422, 'El número de comensales supera la capacidad de la mesa.');
            $service = DB::table('servicios')->where('id_servicio', $data['id_servicio'])->where('id_sede', $site->id_sede)->where('estado_aprobacion', 'Aprobado')->whereIn('estado', ['Programado', 'En curso'])->first();
            abort_unless($service, 422, 'El servicio no está disponible.');
            abort_if(DB::table('atenciones')->where('id_mesa', $table->id_mesa)->whereNotIn('estado', ['Cerrada', 'Cancelada'])->where('id_servicio', $service->id_servicio)->exists(), 422, 'La mesa ya tiene una atención activa.');
            DB::table('atenciones')->insert(['id_servicio' => $service->id_servicio, 'id_mesa' => $table->id_mesa, 'id_usuario_mesero' => $request->user()->id_usuario, 'estado' => 'En atención', 'comensales' => $data['comensales'], 'inicio_hora' => now()->format('H:i:s')]);
        });
        return back()->with('status', 'Atención abierta correctamente.');
    }

    public function storeTable(Request $request, SiteContext $context)
    {
        $site = $this->site($request, $context);
        $data = $request->validate([
            'id_zona' => ['required', 'integer'], 'numero' => ['required', 'integer', 'min:1', 'max:9999'],
            'capacidad' => ['required', 'integer', 'min:1', 'max:100'],
            'estado_fisico' => ['required', 'in:Disponible,Mantenimiento,Fuera de servicio'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ]);
        DB::transaction(function () use ($data, $site, $request): void {
            $zone = DB::table('zonas')->where('id_zona', $data['id_zona'])->where('id_sede', $site->id_sede)
                ->whereExists(fn ($query) => $query->from('zona_meseros')->whereColumn('zona_meseros.id_zona', 'zonas.id_zona')->where('id_usuario_mesero', $request->user()->id_usuario)->where('activo', 1))->lockForUpdate()->first();
            abort_unless($zone, 403, 'Solo puedes crear mesas en tus zonas asignadas.');
            abort_if(DB::table('mesas')->where('id_zona', $data['id_zona'])->where('numero', $data['numero'])->exists(), 422, 'Ya existe esa mesa en la zona.');
            DB::table('mesas')->insert(['id_zona' => $data['id_zona'], 'numero' => $data['numero'], 'capacidad' => $data['capacidad'], 'estado_fisico' => $data['estado_fisico'], 'observaciones' => $data['observaciones'] ?? null]);
        });
        return back()->with('status', 'Mesa creada correctamente.');
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
        $data = $request->validate(['id_menu' => ['required', 'integer'], 'numero_comensal' => ['required', 'integer', 'min:1'], 'cantidad' => ['required', 'integer', 'min:1', 'max:100'], 'observaciones' => ['nullable', 'string', 'max:500']]);
        $attentionRecord = DB::table('atenciones')->where('id_atencion', $attention)->first(['id_servicio', 'comensales', 'estado']);
        abort_unless($attentionRecord, 404);
        abort_if(in_array($attentionRecord->estado, ['Cerrada', 'Cancelada', 'Pendiente de pago'], true), 422, 'La atención ya no admite cambios.');
        $menu = DB::table('menus as m')->join('servicios as s', 's.id_servicio', '=', 'm.id_servicio')->where('m.id_menu', $data['id_menu'])->where('m.id_servicio', $attentionRecord->id_servicio)
            ->where('s.id_sede', $site->id_sede)->where('m.publicado', 1)->where('m.disponible', 1)->whereNotNull('m.precio_venta')->first(['m.id_menu']);
        abort_unless($menu, 404);
        $comanda = DB::table('comandas as c')->join('atenciones as a', 'a.id_atencion', '=', 'c.id_atencion')
            ->where('c.id_atencion', $attention)->where('a.id_usuario_mesero', $request->user()->id_usuario)->first(['c.id_comanda']);
        abort_unless($comanda, 404);
        abort_if($data['numero_comensal'] > (int) $attentionRecord->comensales, 422, 'El comensal no es válido.');
        DB::transaction(function () use ($data, $comanda, $attention): void {
            $price = DB::table('menus')->where('id_menu', $data['id_menu'])->value('precio_venta');
            DB::table('comanda_detalles')->insert(['id_comanda' => $comanda->id_comanda, 'id_menu' => $data['id_menu'], 'cantidad' => $data['cantidad'], 'numero_comensal' => $data['numero_comensal'], 'precio_unitario' => $price, 'estado' => 'pendiente', 'observaciones' => $data['observaciones'] ?? null]);
        });
        return back()->with('status', 'Platillo agregado a la comanda.');
    }

    public function closeComanda(Request $request, SiteContext $context, int $attention)
    {
        $site = $this->site($request, $context);
        DB::transaction(function () use ($request, $site, $attention): void {
            $record = DB::table('atenciones as a')->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')->join('comandas as c', 'c.id_atencion', '=', 'a.id_atencion')
                ->where('a.id_atencion', $attention)->where('a.id_usuario_mesero', $request->user()->id_usuario)->where('s.id_sede', $site->id_sede)->lockForUpdate()
                ->first(['a.estado as atencion_estado', 'c.id_comanda', 'c.estado as comanda_estado']);
            abort_unless($record, 404);
            abort_if(in_array($record->atencion_estado, ['Cerrada', 'Cancelada'], true), 422, 'La atención ya está cerrada.');
            abort_if(in_array($record->comanda_estado, ['Cerrada', 'Pendiente de pago'], true), 422, 'La comanda ya fue enviada a caja.');
            $total = (float) DB::table('comanda_detalles')->where('id_comanda', $record->id_comanda)->whereNotIn('estado', ['cancelado', 'Cancelado'])->sum(DB::raw('cantidad * precio_unitario'));
            abort_if($total <= 0, 422, 'No puedes cerrar una comanda sin productos.');
            $now = now();
            DB::table('comandas')->where('id_comanda', $record->id_comanda)->update(['estado' => 'Pendiente de pago', 'fecha_cierre' => $now]);
            DB::table('atenciones')->where('id_atencion', $attention)->update(['estado' => 'Pendiente de pago']);
            DB::table('cuentas')->updateOrInsert(['id_atencion' => $attention], ['subtotal' => $total, 'total' => $total, 'estado' => 'pendiente', 'fecha_apertura' => $now]);
        });
        return back()->with('status', 'Comanda cerrada y enviada a caja.');
    }
}
