<?php

namespace App\Http\Controllers;

use App\Support\SiteContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, SiteContext $siteContext): View
    {
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
                ['label' => 'Incidencias', 'value' => $incidentsToday, 'detail' => 'Registradas hoy', 'icon' => 'alert', 'tone' => 'orange'],
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
}
