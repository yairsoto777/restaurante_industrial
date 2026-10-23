<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Support\EmploymentRoles;
use App\Support\SiteContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class KitchenWorkflowController extends Controller
{
    public function groups(Request $request, SiteContext $siteContext): View
    {
        $site = $this->activeSite($request, $siteContext);
        $groupsAvailable = $this->hasTables(['grupos', 'servicios', 'atenciones', 'entregas']);
        $attentionAvailable = $this->hasTables(['atenciones', 'mesas', 'zonas', 'servicios', 'grupos']);
        $deliveryAvailable = $this->hasTables(['entregas', 'menus', 'platillos', 'atenciones', 'servicios', 'usuarios', 'grupos']);
        $services = collect();
        $groups = collect();
        $attentions = collect();
        $deliveries = collect();
        $tables = collect();
        $waiters = collect();
        $menuItems = collect();
        $groupCount = $attentionCount = $deliveryCount = $plannedGuests = 0;

        if ($this->hasTables(['servicios'])) {
            $services = DB::table('servicios')->where('id_sede', $site->id_sede)
                ->where('estado_aprobacion', 'Aprobado')
                ->whereNotIn('estado', ['Finalizado', 'Cancelado'])
                ->orderBy('fecha')->orderBy('inicio_hora')->get();
        }

        if ($groupsAvailable) {
            $groupCount = DB::table('grupos as g')->join('servicios as s', 's.id_servicio', '=', 'g.id_servicio')
                ->where('s.id_sede', $site->id_sede)->count();
            $plannedGuests = (int) (DB::table('grupos as g')->join('servicios as s', 's.id_servicio', '=', 'g.id_servicio')
                ->where('s.id_sede', $site->id_sede)->sum('g.comensales_pre') ?? 0);

            $attentionCounts = DB::table('atenciones')->select('id_grupo')->selectRaw('COUNT(*) as total')
                ->whereNotNull('id_grupo')->groupBy('id_grupo');
            $deliveryCounts = DB::table('entregas as e')->join('atenciones as a', 'a.id_atencion', '=', 'e.id_atencion')
                ->whereNotNull('a.id_grupo')->groupBy('a.id_grupo')->select('a.id_grupo')
                ->selectRaw('COUNT(e.id_entrega) as total');
            $groups = DB::table('grupos as g')->join('servicios as s', 's.id_servicio', '=', 'g.id_servicio')
                ->leftJoinSub($attentionCounts, 'conteo_atenciones', fn ($join) => $join->on('conteo_atenciones.id_grupo', '=', 'g.id_grupo'))
                ->leftJoinSub($deliveryCounts, 'conteo_entregas', fn ($join) => $join->on('conteo_entregas.id_grupo', '=', 'g.id_grupo'))
                ->where('s.id_sede', $site->id_sede)
                ->select(['g.id_grupo', 'g.nombre', 'g.inicio_hora', 'g.fin_hora', 'g.comensales_pre', 's.id_servicio', 's.nombre as servicio_nombre', 's.fecha as servicio_fecha'])
                ->selectRaw('COALESCE(conteo_atenciones.total, 0) as total_atenciones')
                ->selectRaw('COALESCE(conteo_entregas.total, 0) as total_entregas')
                ->orderBy('s.fecha')->orderBy('g.inicio_hora')->get();
        }

        if ($attentionAvailable) {
            $attentionCount = DB::table('atenciones as a')->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')
                ->where('s.id_sede', $site->id_sede)->count();
            $tables = DB::table('mesas as m')->join('zonas as z', 'z.id_zona', '=', 'm.id_zona')
                ->where('z.id_sede', $site->id_sede)->orderBy('z.nombre')->orderBy('m.numero')
                ->get(['m.id_mesa', 'm.numero', 'm.capacidad', 'm.estado_fisico', 'z.nombre as zona']);
            $attentions = DB::table('atenciones as a')->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')
                ->join('mesas as m', 'm.id_mesa', '=', 'a.id_mesa')->join('zonas as z', 'z.id_zona', '=', 'm.id_zona')
                ->leftJoin('grupos as g', 'g.id_grupo', '=', 'a.id_grupo')
                ->where('s.id_sede', $site->id_sede)
                ->select(['a.id_atencion', 'a.id_servicio', 'a.id_grupo', 'a.comensales', 'a.estado', 's.nombre as servicio_nombre', 's.fecha as servicio_fecha', 'g.nombre as grupo_nombre', 'm.numero as mesa_numero', 'z.nombre as zona'])
                ->orderByDesc('s.fecha')->orderBy('m.numero')->get();

            if ($this->hasTables(['usuario_sede', 'usuarios', 'roles'])) {
                $waiters = DB::table('usuario_sede as us')->join('usuarios as u', 'u.id_usuario', '=', 'us.id_usuario')
                    ->join('roles as r', 'r.id_rol', '=', 'us.id_rol')->where('us.id_sede', $site->id_sede)
                    ->select(['u.id_usuario', 'u.nombre', 'u.apellido_p', 'u.apellido_m', 'r.nombre as rol'])->get()
                    ->filter(fn ($user): bool => EmploymentRoles::matches($user->rol, 'Mesero'))
                    ->unique('id_usuario')->values();
            }
        }

        if ($deliveryAvailable) {
            $deliveryCountQuery = DB::table('entregas as e')->join('atenciones as a', 'a.id_atencion', '=', 'e.id_atencion')
                ->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')->where('s.id_sede', $site->id_sede);
            $deliveryCount = (clone $deliveryCountQuery)->count();
            $deliveries = (clone $deliveryCountQuery)
                ->join('menus as m', 'm.id_menu', '=', 'e.id_menu')->join('platillos as p', 'p.id_platillo', '=', 'm.id_platillo')
                ->leftJoin('grupos as g', 'g.id_grupo', '=', 'a.id_grupo')->leftJoin('usuarios as u', 'u.id_usuario', '=', 'e.id_usuario')
                ->select(['e.id_entrega', 'e.cantidad', 'e.fecha_entrega', 's.nombre as servicio_nombre', 'g.nombre as grupo_nombre', 'p.nombre as platillo_nombre', 'u.nombre as usuario_nombre', 'u.apellido_p as usuario_apellido'])
                ->orderByDesc('e.fecha_entrega')->limit(40)->get();
            $menuItems = DB::table('menus as m')->join('servicios as s', 's.id_servicio', '=', 'm.id_servicio')
                ->join('platillos as p', 'p.id_platillo', '=', 'm.id_platillo')->where('s.id_sede', $site->id_sede)
                ->where('s.estado_aprobacion', 'Aprobado')->where('m.publicado', 1)->where('m.disponible', 1)
                ->select(['m.id_menu', 'm.id_servicio', 's.nombre as servicio_nombre', 'p.nombre as platillo_nombre'])
                ->orderBy('s.fecha')->orderBy('p.nombre')->get();
        }

        return view('modules.groups', compact(
            'site', 'groups', 'services', 'attentions', 'deliveries', 'tables', 'waiters', 'menuItems',
            'groupCount', 'attentionCount', 'deliveryCount', 'plannedGuests', 'groupsAvailable', 'attentionAvailable', 'deliveryAvailable',
        ));
    }

    public function storeGroup(Request $request, SiteContext $siteContext): RedirectResponse
    {
        $site = $this->activeSite($request, $siteContext, true);
        abort_unless($this->hasTables(['grupos', 'servicios']), 503, 'El esquema para grupos y servicios no está disponible.');
        $data = $request->validate([
            'id_servicio' => ['required', 'integer', 'exists:servicios,id_servicio'],
            'nombre' => ['required', 'string', 'max:100'],
            'inicio_hora' => ['required', 'date_format:H:i'],
            'fin_hora' => ['required', 'date_format:H:i', 'after:inicio_hora'],
            'comensales_pre' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        DB::transaction(function () use ($data, $site): void {
            $service = DB::table('servicios')->where('id_servicio', (int) $data['id_servicio'])
                ->where('id_sede', $site->id_sede)->lockForUpdate()->first();
            if (! $service || $service->estado_aprobacion !== 'Aprobado' || in_array($service->estado, ['Finalizado', 'Cancelado'], true)) {
                throw ValidationException::withMessages(['id_servicio' => 'Elige un servicio aprobado y operativo de la sede activa.']);
            }

            $start = substr((string) $data['inicio_hora'], 0, 5);
            $end = substr((string) $data['fin_hora'], 0, 5);
            if (($service->inicio_hora && $start < substr((string) $service->inicio_hora, 0, 5))
                || ($service->fin_hora && $end > substr((string) $service->fin_hora, 0, 5))) {
                throw ValidationException::withMessages(['inicio_hora' => 'El horario del grupo debe quedar dentro del horario del servicio.']);
            }
            $planned = (int) DB::table('grupos')->where('id_servicio', $service->id_servicio)->lockForUpdate()->sum('comensales_pre');
            if ($planned + (int) $data['comensales_pre'] > (int) $service->capacidad) {
                throw ValidationException::withMessages(['comensales_pre' => 'La suma de grupos supera la capacidad registrada para este servicio.']);
            }

            DB::table('grupos')->insert([
                'id_servicio' => $service->id_servicio,
                'nombre' => trim($data['nombre']),
                'inicio_hora' => $data['inicio_hora'],
                'fin_hora' => $data['fin_hora'],
                'comensales_pre' => $data['comensales_pre'],
            ]);
        }, 3);

        return redirect()->route('groups.index')->with('status', 'El grupo quedó registrado para el servicio.');
    }

    public function storeAttention(Request $request, SiteContext $siteContext): RedirectResponse
    {
        $site = $this->activeSite($request, $siteContext, true);
        abort_unless($this->hasTables(['atenciones', 'grupos', 'servicios', 'mesas', 'zonas', 'usuarios']), 503, 'Faltan tablas para asignar grupos a mesas.');
        $data = $request->validate([
            'id_servicio' => ['required', 'integer', 'exists:servicios,id_servicio'],
            'id_grupo' => ['required', 'integer', 'exists:grupos,id_grupo'],
            'id_mesa' => ['required', 'integer', 'exists:mesas,id_mesa'],
            'id_usuario_mesero' => ['nullable', 'integer', 'exists:usuarios,id_usuario'],
            'comensales' => ['required', 'integer', 'min:1', 'max:100000'],
            'inicio_hora' => ['nullable', 'date_format:H:i'],
            'fin_hora' => ['nullable', 'date_format:H:i', 'after:inicio_hora'],
        ]);

        DB::transaction(function () use ($data, $site): void {
            $service = DB::table('servicios')->where('id_servicio', (int) $data['id_servicio'])->where('id_sede', $site->id_sede)
                ->where('estado_aprobacion', 'Aprobado')->lockForUpdate()->first();
            $group = DB::table('grupos')->where('id_grupo', (int) $data['id_grupo'])->where('id_servicio', (int) $data['id_servicio'])->lockForUpdate()->first();
            $table = DB::table('mesas as m')->join('zonas as z', 'z.id_zona', '=', 'm.id_zona')
                ->where('m.id_mesa', (int) $data['id_mesa'])->where('z.id_sede', $site->id_sede)->lockForUpdate()->first(['m.id_mesa', 'm.capacidad']);

            if (! $service || in_array($service->estado, ['Finalizado', 'Cancelado'], true) || ! $group || ! $table) {
                throw ValidationException::withMessages(['id_grupo' => 'Servicio, grupo y mesa deben pertenecer a la sede activa.']);
            }
            $alreadyAssigned = (int) DB::table('atenciones')->where('id_grupo', $group->id_grupo)->lockForUpdate()->sum('comensales');
            if ($alreadyAssigned + (int) $data['comensales'] > (int) $group->comensales_pre) {
                throw ValidationException::withMessages(['comensales' => 'La suma de atenciones superaría los comensales previstos para el grupo.']);
            }
            $tableAlreadyAssigned = DB::table('atenciones')->where('id_servicio', $service->id_servicio)
                ->where('id_mesa', $table->id_mesa)->lockForUpdate()->exists();
            if ($tableAlreadyAssigned) {
                throw ValidationException::withMessages(['id_mesa' => 'Esta mesa ya tiene una atención asignada para el servicio.']);
            }
            if ($table->capacidad !== null && (int) $data['comensales'] > (int) $table->capacidad) {
                throw ValidationException::withMessages(['comensales' => 'Los comensales superan la capacidad de la mesa seleccionada.']);
            }

            $waiterId = $data['id_usuario_mesero'] ?? null;
            if ($waiterId !== null && ! $this->isSiteWaiter((int) $waiterId, $site->id_sede)) {
                throw ValidationException::withMessages(['id_usuario_mesero' => 'El responsable debe ser mesero asignado a la sede activa.']);
            }

            DB::table('atenciones')->insert([
                'id_servicio' => $service->id_servicio,
                'id_mesa' => $table->id_mesa,
                'id_usuario_mesero' => $waiterId,
                'id_reservacion' => null,
                'estado' => null,
                'id_grupo' => $group->id_grupo,
                'comensales' => $data['comensales'],
                'inicio_hora' => $data['inicio_hora'] ?? null,
                'fin_hora' => $data['fin_hora'] ?? null,
            ]);
        }, 3);

        return redirect()->route('groups.index')->with('status', 'La atención quedó asignada a la mesa seleccionada.');
    }

    public function storeDelivery(Request $request, SiteContext $siteContext): RedirectResponse
    {
        $site = $this->activeSite($request, $siteContext, true);
        abort_unless($this->hasTables(['entregas', 'atenciones', 'menus', 'servicios']), 503, 'El esquema de entregas no está disponible.');
        $data = $request->validate([
            'id_atencion' => ['required', 'integer', 'exists:atenciones,id_atencion'],
            'id_menu' => ['required', 'integer', 'exists:menus,id_menu'],
            'cantidad' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        DB::transaction(function () use ($data, $site, $request): void {
            $attention = DB::table('atenciones as a')->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')
                ->where('a.id_atencion', (int) $data['id_atencion'])->where('s.id_sede', $site->id_sede)
                ->where('s.estado_aprobacion', 'Aprobado')->whereNotIn('s.estado', ['Finalizado', 'Cancelado'])
                ->lockForUpdate()->first(['a.id_atencion', 'a.id_servicio']);
            $menu = DB::table('menus')->where('id_menu', (int) $data['id_menu'])
                ->where('id_servicio', $attention?->id_servicio)->where('publicado', 1)->where('disponible', 1)
                ->lockForUpdate()->first(['id_menu']);

            if (! $attention || ! $menu) {
                throw ValidationException::withMessages(['id_menu' => 'El menú seleccionado debe estar publicado y disponible para el servicio de esta atención.']);
            }

            DB::table('entregas')->insert([
                'id_atencion' => $attention->id_atencion,
                'id_menu' => $menu->id_menu,
                'cantidad' => $data['cantidad'],
                'fecha_entrega' => now(),
                'id_usuario' => $request->user()->id_usuario,
            ]);
        }, 3);

        return redirect()->route('groups.index')->with('status', 'La entrega quedó registrada con la cantidad indicada.');
    }

    public function kitchen(Request $request, SiteContext $siteContext): View
    {
        $site = $this->activeSite($request, $siteContext);
        $available = $this->hasTables(['comanda_detalles', 'comandas', 'atenciones', 'servicios', 'menus', 'platillos', 'mesas', 'zonas']);
        $search = trim((string) $request->query('q', ''));
        $filterState = strtolower(trim((string) $request->query('estado', '')));
        $orders = collect();
        $stateOptions = collect();
        $totalCount = $pendingCount = 0;
        $totalQuantity = 0;
        if ($available) {
            $base = $this->kitchenOrdersQuery($site->id_sede);
            $totalCount = (clone $base)->count();
            $pendingCount = (clone $base)->whereRaw('LOWER(detalle.estado) = ?', ['pendiente'])->count();
            $totalQuantity = (clone $base)->sum('detalle.cantidad') ?? 0;
            $stateOptions = DB::table('comanda_detalles as detalle')
                ->join('comandas as comanda', 'comanda.id_comanda', '=', 'detalle.id_comanda')
                ->join('atenciones as atencion', 'atencion.id_atencion', '=', 'comanda.id_atencion')
                ->join('servicios as servicio', 'servicio.id_servicio', '=', 'atencion.id_servicio')
                ->where('servicio.id_sede', $site->id_sede)
                ->where('servicio.estado_aprobacion', 'Aprobado')
                ->whereNotIn('servicio.estado', ['Cancelado', 'Finalizado'])
                ->whereNotNull('detalle.estado')
                ->selectRaw('LOWER(detalle.estado) as estado')
                ->distinct()->orderBy('estado')->pluck('estado');
            if ($filterState !== '' && ! $stateOptions->contains($filterState)) {
                $filterState = '';
            }
            $orders = (clone $base)
                ->when($search !== '', fn ($query) => $query->where(fn ($sub) => $sub->where('platillo.nombre', 'like', '%'.$search.'%')->orWhere('servicio.nombre', 'like', '%'.$search.'%')))
                ->when($filterState !== '', fn ($query) => $query->whereRaw('LOWER(detalle.estado) = ?', [$filterState]))
                ->orderBy('comanda.fecha_apertura')->paginate(25)->withQueryString();
        }

        return view('modules.kitchen', compact('site', 'orders', 'stateOptions', 'available', 'search', 'filterState', 'totalCount', 'pendingCount', 'totalQuantity'));
    }

    public function updateOrderStatus(Request $request, SiteContext $siteContext, int $detail): RedirectResponse
    {
        abort_unless($request->user()->hasRole('Personal de cocina'), 403);
        $site = $this->activeSite($request, $siteContext);
        $data = $request->validate([
            'estado' => ['required', 'string', 'in:preparando,listo,entregado'],
        ]);

        DB::transaction(function () use ($site, $detail, $data, $request): void {
            $order = $this->kitchenOrdersQuery((int) $site->id_sede)
                ->where('detalle.id_comanda_detalle', $detail)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                abort(404);
            }

            $transitions = [
                'pendiente' => 'preparando',
                'preparando' => 'listo',
                'listo' => 'entregado',
            ];
            $currentState = strtolower(trim((string) $order->detalle_estado));
            if (($transitions[$currentState] ?? null) !== $data['estado']) {
                throw ValidationException::withMessages([
                    'estado' => 'La comanda debe avanzar en orden: pendiente, preparando, listo y entregado.',
                ]);
            }

            if ($data['estado'] === 'entregado') {
                abort_unless($this->hasColumns('entregas', ['id_atencion', 'id_menu', 'cantidad', 'fecha_entrega', 'id_usuario']), 503, 'No están disponibles las columnas necesarias para registrar la entrega.');
                DB::table('entregas')->insert([
                    'id_atencion' => $order->id_atencion,
                    'id_menu' => $order->id_menu,
                    'cantidad' => $order->cantidad,
                    'fecha_entrega' => now(),
                    'id_usuario' => $request->user()->getKey(),
                ]);
            }

            DB::table('comanda_detalles')->where('id_comanda_detalle', $detail)->update(['estado' => $data['estado']]);
        }, 3);

        return redirect()->route('kitchen.index')->with('status', 'El estado del platillo quedó actualizado.');
    }

    private function kitchenOrdersQuery(int $siteId)
    {
        return DB::table('comanda_detalles as detalle')
            ->join('comandas as comanda', 'comanda.id_comanda', '=', 'detalle.id_comanda')
            ->join('atenciones as atencion', 'atencion.id_atencion', '=', 'comanda.id_atencion')
            ->join('servicios as servicio', 'servicio.id_servicio', '=', 'atencion.id_servicio')
            ->join('menus as menu', 'menu.id_menu', '=', 'detalle.id_menu')
            ->join('platillos as platillo', 'platillo.id_platillo', '=', 'menu.id_platillo')
            ->join('mesas as mesa', 'mesa.id_mesa', '=', 'atencion.id_mesa')
            ->leftJoin('zonas as zona', 'zona.id_zona', '=', 'mesa.id_zona')
            ->where('servicio.id_sede', $siteId)
            ->where('servicio.estado_aprobacion', 'Aprobado')
            ->whereNotIn('servicio.estado', ['Cancelado', 'Finalizado'])
            ->select([
                'detalle.id_comanda_detalle', 'detalle.id_menu', 'detalle.cantidad', 'detalle.estado as detalle_estado', 'detalle.observaciones as detalle_observaciones',
                'comanda.id_comanda', 'comanda.fecha_apertura', 'comanda.estado as comanda_estado', 'comanda.observaciones as comanda_observaciones',
                'atencion.id_atencion', 'atencion.comensales', 'servicio.nombre as servicio_nombre', 'servicio.fecha as servicio_fecha',
                'platillo.nombre as platillo_nombre', 'mesa.numero as mesa_numero', 'zona.nombre as zona_nombre',
            ]);
    }

    private function activeSite(Request $request, SiteContext $siteContext, bool $chiefOnly = false): Site
    {
        if ($chiefOnly) {
            abort_unless($request->user()->hasRole('Jefe de cocina'), 403);
        }

        $site = $siteContext->currentFor($request, $request->user());
        abort_unless($site && ($request->user()->canAdminister() || $request->user()->sites()->whereKey($site->id_sede)->exists()), 403);

        return $site;
    }

    private function isSiteWaiter(int $userId, int $siteId): bool
    {
        if (! $this->hasTables(['usuario_sede', 'usuarios', 'roles'])) {
            return false;
        }

        return DB::table('usuario_sede as us')->join('roles as r', 'r.id_rol', '=', 'us.id_rol')
            ->where('us.id_usuario', $userId)->where('us.id_sede', $siteId)->get(['r.nombre'])
            ->contains(fn ($role): bool => EmploymentRoles::matches($role->nombre, 'Mesero'));
    }

    private function hasTables(array $tables): bool
    {
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    private function hasColumns(string $table, array $columns): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }
}
