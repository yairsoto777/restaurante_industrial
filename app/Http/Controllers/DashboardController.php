<?php

namespace App\Http\Controllers;

use App\Models\CommercialContact;
use App\Models\EmploymentApplication;
use App\Models\Site;
use App\Models\User;
use App\Support\EmploymentRoles;
use App\Support\SiteContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, SiteContext $siteContext): View|RedirectResponse
    {
        if ($request->user()->hasRole('Personal de cocina')) {
            return $this->kitchenDashboard($request, $siteContext);
        }

        if ($request->user()->isCommonUser()) {
            return $this->commonUserDashboard($request);
        }

        if ($request->user()->isSuperAdministrator()) {
            return $this->globalDashboard();
        }

        if ($request->user()->isManager() && $request->user()->sites()->doesntExist()) {
            return redirect()->route('manager.sites');
        }

        $contactSummary = $this->commercialContactSummary($request);
        $currentSite = $siteContext->currentFor($request, $request->user());
        $siteId = $currentSite?->id_sede;
        $today = now()->toDateString();

        $servicesToday = $siteId
            ? DB::table('servicios')->where('id_sede', $siteId)->whereDate('fecha', $today)->count()
            : 0;
        $dinersToday = $siteId
            ? (int) DB::table('atenciones as a')->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')->where('s.id_sede', $siteId)->whereDate('s.fecha', $today)->sum('a.comensales')
            : 0;
        $platesServed = $siteId
            ? (int) DB::table('entregas as e')->join('atenciones as a', 'a.id_atencion', '=', 'e.id_atencion')->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')->where('s.id_sede', $siteId)->whereDate('e.fecha_entrega', $today)->sum('e.cantidad')
            : 0;
        $incidentsToday = $siteId
            ? DB::table('incidencias')->where('id_sede', $siteId)->whereDate('fecha', $today)->count()
            : 0;
        $openIncidents = $siteId ? $this->openIncidentCount((int) $siteId) : 0;
        $pendingOrders = $siteId ? $this->statusCount('pedidos', (int) $siteId, ['pendiente', 'pendientes', 'nuevo', 'enviado']) : 0;
        $pendingRequisitions = $siteId ? $this->statusCount('requisiciones', (int) $siteId, ['pendiente', 'pendientes', 'nuevo']) : 0;
        $employeeCount = $siteId ? $this->employeeCount((int) $siteId) : 0;
        $lowStock = $siteId ? $this->lowStockCount((int) $siteId) : 0;

        $activeDiners = $siteId
            ? (int) DB::table('atenciones as a')
                ->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')
                ->where('s.id_sede', $siteId)
                ->whereDate('s.fecha', $today)
                ->where(function ($query): void {
                    $query->whereRaw("LOWER(COALESCE(s.estado, '')) IN ('en curso', 'activo')")
                        ->orWhere(function ($timeQuery): void {
                            $timeQuery->whereTime('s.inicio_hora', '<=', now()->format('H:i:s'))
                                ->whereTime('s.fin_hora', '>=', now()->format('H:i:s'));
                        });
                })
                ->sum('a.comensales')
            : 0;
        $capacity = (int) ($currentSite?->capacidad ?? 0);
        $occupationPercent = $capacity > 0 ? min(100, (int) round(($activeDiners / $capacity) * 100)) : 0;

        $services = $siteId
            ? DB::table('servicios as s')
                ->leftJoin('atenciones as a', 'a.id_servicio', '=', 's.id_servicio')
                ->where('s.id_sede', $siteId)
                ->whereDate('s.fecha', $today)
                ->groupBy('s.id_servicio', 's.nombre', 's.inicio_hora', 's.fin_hora', 's.capacidad', 's.estado')
                ->orderBy('s.inicio_hora')
                ->select('s.id_servicio', 's.nombre', 's.inicio_hora', 's.fin_hora', 's.capacidad', 's.estado', DB::raw('COALESCE(SUM(a.comensales), 0) as ocupacion'))
                ->get()
            : collect();

        $hourExpression = DB::getDriverName() === 'sqlite'
            ? 'CAST(substr(a.inicio_hora, 1, 2) AS INTEGER)'
            : 'HOUR(a.inicio_hora)';
        $hourlyDiners = $siteId
            ? DB::table('atenciones as a')
                ->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')
                ->where('s.id_sede', $siteId)
                ->whereDate('s.fecha', $today)
                ->whereNotNull('a.inicio_hora')
                ->groupByRaw($hourExpression)
                ->orderByRaw($hourExpression)
                ->selectRaw($hourExpression.' as hora, COALESCE(SUM(a.comensales), 0) as total')
                ->get()
            : collect();

        return view('dashboard', [
            'currentSite' => $currentSite,
            'kpis' => [
                ['label' => 'Servicios de hoy', 'value' => $servicesToday, 'detail' => $services->whereIn('estado', ['En curso', 'en curso', 'Activo', 'activo'])->count().' en curso', 'icon' => 'calendar', 'tone' => 'blue'],
                ['label' => 'Comensales', 'value' => $dinersToday, 'detail' => 'Registrados hoy', 'icon' => 'users', 'tone' => 'cyan'],
                ['label' => 'Platos servidos', 'value' => $platesServed, 'detail' => 'Entregados hoy', 'icon' => 'plates', 'tone' => 'green'],
                ['label' => 'Incidencias abiertas', 'value' => $openIncidents, 'detail' => $incidentsToday.' registradas hoy', 'icon' => 'alert', 'tone' => 'orange'],
                ['label' => 'Pedidos pendientes', 'value' => $pendingOrders, 'detail' => 'Por atender', 'icon' => 'box', 'tone' => 'orange'],
                ['label' => 'Requisiciones pendientes', 'value' => $pendingRequisitions, 'detail' => 'Por revisar', 'icon' => 'layers', 'tone' => 'blue'],
                ['label' => 'Empleados registrados', 'value' => $employeeCount, 'detail' => 'En esta sede', 'icon' => 'users', 'tone' => 'cyan'],
                ['label' => 'Insumos con existencias bajas', 'value' => $lowStock, 'detail' => 'Requieren atención', 'icon' => 'warehouse', 'tone' => 'orange'],
            ],
            'activity' => $this->recentActivity($siteId),
            'services' => $services,
            'hourlyDiners' => $hourlyDiners,
            'occupation' => [
                'current' => $activeDiners,
                'capacity' => $capacity,
                'available' => max(0, $capacity - $activeDiners),
                'percent' => $occupationPercent,
            ],
            'siteLogoUrl' => $currentSite?->logo_url ? \Illuminate\Support\Facades\Storage::url($currentSite->logo_url) : null,
            'recentOrders' => $siteId ? $this->recentRows('pedidos', (int) $siteId, 'id_pedido') : collect(),
            'recentIncidents' => $siteId ? $this->recentRows('incidencias', (int) $siteId, 'id_incidencia') : collect(),
            'contactSummary' => $contactSummary,
        ]);
    }

    private function kitchenDashboard(Request $request, SiteContext $siteContext): View
    {
        $site = $siteContext->currentFor($request, $request->user());
        abort_unless($site && $request->user()->sites()->whereKey($site->getKey())->exists(), 403);
        $today = Carbon::now('America/Mexico_City')->toDateString();
        $approvedToday = DB::table('servicios')->where('id_sede', $site->getKey())
            ->where('estado_aprobacion', 'Aprobado')->whereDate('fecha', $today)
            ->where(fn ($query) => $query->whereNull('estado')->orWhere('estado', '<>', 'Cancelado'));
        $services = (clone $approvedToday)->orderBy('inicio_hora')->limit(5)
            ->get(['nombre', 'inicio_hora', 'fin_hora', 'estado']);
        $activeServiceCount = (clone $approvedToday)->where('estado', 'En curso')->count();

        $pendingOrderLines = 0;
        if ($this->tablesExist(['comanda_detalles', 'comandas', 'atenciones'])) {
            $pendingOrderLines = DB::table('comanda_detalles as detalle')
                ->join('comandas as comanda', 'comanda.id_comanda', '=', 'detalle.id_comanda')
                ->join('atenciones as atencion', 'atencion.id_atencion', '=', 'comanda.id_atencion')
                ->join('servicios as servicio', 'servicio.id_servicio', '=', 'atencion.id_servicio')
                ->where('servicio.id_sede', $site->getKey())
                ->where('servicio.estado_aprobacion', 'Aprobado')
                ->whereNotIn('servicio.estado', ['Cancelado', 'Finalizado'])
                ->whereRaw('LOWER(detalle.estado) = ?', ['pendiente'])
                ->count();
        }

        $lowStock = 0;
        $depletedStock = 0;
        if (Schema::hasTable('insumo_sedes') && Schema::hasColumn('insumo_sedes', 'id_sede')) {
            $stock = DB::table('insumo_sedes')->where('id_sede', $site->getKey());
            if (Schema::hasColumn('insumo_sedes', 'stock')) {
                $depletedStock = (clone $stock)->where('stock', '<=', 0)->count();
                if (Schema::hasColumn('insumo_sedes', 'punto_de_reorden')) {
                    $lowStock = (clone $stock)->whereNotNull('punto_de_reorden')
                        ->whereColumn('stock', '<=', 'punto_de_reorden')->count();
                }
            }
        }

        $openIncidents = 0;
        if (Schema::hasTable('incidencias') && Schema::hasColumn('incidencias', 'id_sede')) {
            $incidentQuery = DB::table('incidencias')->where('id_sede', $site->getKey());
            $openIncidents = Schema::hasColumn('incidencias', 'fecha_cierre')
                ? (clone $incidentQuery)->whereNull('fecha_cierre')->count()
                : $incidentQuery->count();
        }

        return view('kitchen.dashboard', [
            'site' => $site,
            'services' => $services,
            'today' => Carbon::parse($today, 'America/Mexico_City'),
            'metrics' => [
                ['label' => 'Servicios aprobados hoy', 'value' => (clone $approvedToday)->count(), 'detail' => $activeServiceCount.' en curso', 'icon' => 'calendar', 'tone' => 'blue'],
                ['label' => 'Comandas pendientes', 'value' => $pendingOrderLines, 'detail' => 'Renglones por preparar', 'icon' => 'plates', 'tone' => 'amber'],
                ['label' => 'Existencias bajas', 'value' => $lowStock, 'detail' => $depletedStock.' agotados', 'icon' => 'warehouse', 'tone' => 'orange'],
                ['label' => 'Incidencias abiertas', 'value' => $openIncidents, 'detail' => 'Registradas en esta sede', 'icon' => 'alert', 'tone' => 'red'],
            ],
        ]);
    }

    private function tablesExist(array $tables): bool
    {
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    public function company(Site $site): View
    {
        $site->load(['users.roles']);
        $manager = $site->users->first(fn (User $user): bool => $user->isManager());
        $employees = $site->users->reject(fn (User $user): bool => $user->isManager() || $user->isSuperAdministrator());

        return view('admin.company', [
            'site' => $site,
            'manager' => $manager,
            'metrics' => [
                ['label' => 'Empleados', 'value' => $employees->count(), 'icon' => 'users', 'tone' => 'blue'],
                ['label' => 'Roles asignados', 'value' => $site->users->flatMap->roles->unique('id_rol')->count(), 'icon' => 'layers', 'tone' => 'cyan'],
                ['label' => 'Servicios', 'value' => $this->tableCount('servicios', $site->id_sede), 'icon' => 'calendar', 'tone' => 'green'],
                ['label' => 'Mesas', 'value' => $this->tableCount('mesas', $site->id_sede), 'icon' => 'map', 'tone' => 'blue'],
                ['label' => 'Inventario general', 'value' => $this->inventoryCount($site->id_sede), 'icon' => 'warehouse', 'tone' => 'cyan'],
                ['label' => 'Incidencias abiertas', 'value' => $this->openIncidentCount($site->id_sede), 'icon' => 'alert', 'tone' => 'orange'],
            ],
            'activity' => $this->recentActivity($site->id_sede),
        ]);
    }

    private function globalDashboard(): View
    {
        $sites = Site::query()->with('users')->orderBy('nombre')->get();
        $managers = User::query()->with(['sites.users.roles', 'roles'])->get()
            ->filter(fn (User $user): bool => $user->isManager())
            ->values();
        $contactsPending = Schema::hasTable('contactos')
            ? CommercialContact::query()->whereIn('estado', [CommercialContact::STATUS_NEW, CommercialContact::STATUS_CONTACTED])->count()
            : 0;

        return view('dashboard', [
            'globalDashboard' => true,
            'managers' => $managers,
            'globalKpis' => [
                ['label' => 'Clientes / gerentes', 'value' => $managers->count(), 'detail' => 'Cuentas gerenciales', 'icon' => 'users', 'tone' => 'blue'],
                ['label' => 'Total de sedes', 'value' => $sites->count(), 'detail' => 'Empresas registradas', 'icon' => 'building', 'tone' => 'cyan'],
                ['label' => 'Total de usuarios', 'value' => User::query()->count(), 'detail' => 'Cuentas del sistema', 'icon' => 'group', 'tone' => 'green'],
                ['label' => 'Contactos pendientes', 'value' => $contactsPending, 'detail' => 'Requieren seguimiento', 'icon' => 'bell', 'tone' => 'orange'],
                ['label' => 'Empresas activas', 'value' => $sites->filter(fn (Site $site): bool => $site->users->isNotEmpty())->count(), 'detail' => 'Con usuarios asignados', 'icon' => 'building', 'tone' => 'blue'],
                ['label' => 'Usuarios activos', 'value' => Schema::hasColumn('usuarios', 'activo') ? User::query()->where('activo', true)->count() : User::query()->count(), 'detail' => 'Con acceso habilitado', 'icon' => 'activity', 'tone' => 'green'],
            ],
        ]);
    }

    private function tableCount(string $table, int $siteId): int
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, 'id_sede')
            ? DB::table($table)->where('id_sede', $siteId)->count()
            : 0;
    }

    private function inventoryCount(int $siteId): int
    {
        foreach (['inventarios', 'inventario', 'existencias'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'id_sede')) {
                return DB::table($table)->where('id_sede', $siteId)->count();
            }
        }

        return 0;
    }

    private function openIncidentCount(int $siteId): int
    {
        if (! Schema::hasTable('incidencias')) {
            return 0;
        }

        return DB::table('incidencias')->where('id_sede', $siteId)
            ->where(fn ($query) => $query->whereNull('estado')->orWhereRaw("LOWER(estado) NOT IN ('cerrada', 'cerrado', 'resuelta', 'resuelto')"))
            ->count();
    }

    private function statusCount(string $table, int $siteId, array $statuses): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id_sede') || ! Schema::hasColumn($table, 'estado')) {
            return 0;
        }

        return DB::table($table)->where('id_sede', $siteId)
            ->whereIn(DB::raw('LOWER(estado)'), $statuses)->count();
    }

    private function employeeCount(int $siteId): int
    {
        if (! Schema::hasTable('empleados') || ! Schema::hasColumn('empleados', 'id_sede')) {
            return 0;
        }

        return DB::table('empleados')->where('id_sede', $siteId)->distinct('id_usuario')->count('id_usuario');
    }

    private function lowStockCount(int $siteId): int
    {
        foreach (['insumo_sedes', 'inventarios_finales'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id_sede')) {
                continue;
            }

            $columns = Schema::getColumnListing($table);
            $stock = collect(['existencia', 'existencias', 'stock', 'cantidad'])->first(fn (string $column): bool => in_array($column, $columns, true));
            $reorder = collect(['punto_reorden', 'stock_minimo', 'minimo'])->first(fn (string $column): bool => in_array($column, $columns, true));
            if ($stock && $reorder) {
                return (int) DB::table($table)->where('id_sede', $siteId)->whereColumn($stock, '<=', $reorder)->count();
            }
        }

        return 0;
    }

    private function recentRows(string $table, int $siteId, string $key): Collection
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id_sede')) {
            return collect();
        }

        $columns = Schema::getColumnListing($table);
        $date = collect(['fecha', 'fecha_pedido', 'created_at'])->first(fn (string $column): bool => in_array($column, $columns, true));
        $select = [$key];
        foreach (['proveedor', 'descripcion', 'estado', 'fecha', 'fecha_pedido', 'created_at'] as $column) {
            if (in_array($column, $columns, true)) {
                $select[] = $column;
            }
        }

        return DB::table($table)->where('id_sede', $siteId)
            ->when($date, fn ($query) => $query->orderByDesc($date))
            ->limit(5)->get($select);
    }

    private function commonUserDashboard(Request $request): View
    {
        $services = DB::table('servicios as servicio')
            ->leftJoin('sedes as sede', 'sede.id_sede', '=', 'servicio.id_sede')
            ->select('servicio.*', 'sede.nombre as sede_nombre')
            ->orderByRaw('CASE WHEN servicio.fecha IS NULL THEN 1 ELSE 0 END')
            ->orderBy('servicio.fecha')
            ->orderBy('servicio.inicio_hora')
            ->limit(6)
            ->get();
        $applications = EmploymentApplication::query()
            ->with(['site', 'requestedRole'])
            ->where('id_usuario', $request->user()->getKey())
            ->orderByDesc('fecha_solicitud')
            ->orderByDesc('id_solicitud')
            ->limit(5)
            ->get();

        return view('user.dashboard', [
            'services' => $services,
            'roles' => EmploymentRoles::publicRoles(),
            'applications' => $applications,
        ]);
    }

    private function recentActivity(?int $siteId): Collection
    {
        if (! $siteId) {
            return collect();
        }

        $receptions = DB::table('recepciones as r')
            ->join('pedidos as p', 'p.id_pedido', '=', 'r.id_pedido')
            ->where('p.id_sede', $siteId)
            ->whereNotNull('r.fecha_recepcion')
            ->orderByDesc('r.fecha_recepcion')->limit(5)
            ->get(['r.fecha_recepcion as occurred_at', 'r.folio_documento', 'r.estado'])
            ->map(fn ($item) => ['timestamp' => $item->occurred_at, 'title' => 'Recepción registrada', 'detail' => $item->folio_documento ?: ($item->estado ?: 'Sin detalle'), 'tone' => 'blue']);

        $incidents = DB::table('incidencias')->where('id_sede', $siteId)->whereNotNull('fecha')
            ->orderByDesc('fecha')->limit(5)->get(['fecha as occurred_at', 'descripcion', 'estado'])
            ->map(fn ($item) => ['timestamp' => $item->occurred_at, 'title' => 'Incidencia registrada', 'detail' => $item->descripcion ?: ($item->estado ?: 'Sin detalle'), 'tone' => 'orange']);

        $serviceEvents = DB::table('servicios')->where('id_sede', $siteId)->whereNotNull('fecha')
            ->orderByDesc('fecha')->orderByDesc('inicio_hora')->limit(5)->get(['fecha', 'inicio_hora', 'nombre', 'estado'])
            ->map(fn ($item) => ['timestamp' => Carbon::parse($item->fecha.' '.($item->inicio_hora ?: '00:00:00'))->toDateTimeString(), 'title' => 'Servicio '.$item->nombre, 'detail' => $item->estado ?: 'Programado', 'tone' => 'green']);

        return $receptions->concat($incidents)->concat($serviceEvents)->sortByDesc('timestamp')->take(5)->values();
    }

    private function commercialContactSummary(Request $request): ?array
    {
        if (! $request->user()->isSuperAdministrator() || ! Schema::hasTable('contactos')) {
            return null;
        }

        return [
            'new' => CommercialContact::query()->where('estado', CommercialContact::STATUS_NEW)->count(),
            'follow_up' => CommercialContact::query()->where('estado', CommercialContact::STATUS_CONTACTED)->count(),
            'total' => CommercialContact::query()->count(),
        ];
    }
}
