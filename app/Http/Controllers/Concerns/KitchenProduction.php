<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Site;
use App\Support\CommandObservations;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

trait KitchenProduction
{
    private const PRODUCTION_STATES = [
        'pendiente' => 'Pendiente', 'preparando' => 'Preparando',
        'listo' => 'Listo', 'entregado' => 'Entregado', 'cancelado' => 'Cancelado',
    ];

    private function production(Request $request, Site $site): View
    {
        $pages = ['panel', 'servicio', 'comandas', 'preparacion', 'listos', 'historial'];
        $filter = $request->validate([
            'modulo' => ['nullable', Rule::in($pages)],
            'q' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', Rule::in(array_keys(self::PRODUCTION_STATES))],
            'servicio' => ['nullable', 'integer', 'min:1'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', ...($request->filled('desde') ? ['after_or_equal:desde'] : [])],
            'comanda' => ['nullable', 'integer', 'min:1'],
        ]);
        $module = $filter['modulo'] ?? 'panel';
        $history = $module === 'historial';
        $today = now()->toDateString();
        $services = DB::table('servicios')->where('id_sede', $site->id_sede);
        $active = (clone $services)->where('estado_aprobacion', 'Aprobado')->where('estado', 'En curso')
            ->whereDate('fecha', $today)->orderByDesc('inicio_hora')->orderByDesc('id_servicio')->first();
        $states = self::PRODUCTION_STATES;
        $base = $this->kitchenOrdersQuery($site->id_sede, $history || $module === 'panel')->where('zona.id_sede', $site->id_sede);
        $lines = (clone $base)->leftJoin('usuarios as mesero', 'mesero.id_usuario', '=', 'atencion.id_usuario_mesero')
            ->addSelect('atencion.estado as atencion_estado', 'servicio.id_servicio', 'servicio.estado as servicio_estado',
                'servicio.estado_aprobacion', 'platillo.imagen_url as platillo_imagen',
                'mesero.nombre as mesero_nombre', 'mesero.apellido_p as mesero_paterno', 'mesero.apellido_m as mesero_materno');
        $tracksPreparation = $this->hasPreparationHistory();
        if ($tracksPreparation) {
            $lines->leftJoin('usuarios as preparador', 'preparador.id_usuario', '=', 'detalle.preparacion_iniciada_por')
                ->leftJoin('usuarios as finalizador', 'finalizador.id_usuario', '=', 'detalle.lista_por')
                ->addSelect('detalle.preparacion_iniciada_en', 'detalle.lista_en',
                    'preparador.nombre as preparador_nombre', 'preparador.apellido_p as preparador_paterno', 'preparador.apellido_m as preparador_materno',
                    'finalizador.nombre as finalizador_nombre', 'finalizador.apellido_p as finalizador_paterno', 'finalizador.apellido_m as finalizador_materno');
        }
        $load = fn ($ids) => $this->productionCommands((clone $lines)->whereIn('comanda.id_comanda', $ids)
            ->orderBy('detalle.id_comanda_detalle')->get());
        $selected = empty($filter['comanda']) ? null : $load([$filter['comanda']])->first();
        abort_if(!empty($filter['comanda']) && !$selected, 404);

        $data = compact('site', 'module', 'history', 'filter', 'states', 'active', 'selected', 'tracksPreparation');
        if (in_array($module, ['panel', 'servicio'], true)) {
            $operational = (clone $lines)->where('atencion.estado', 'En atención')->where('servicio.estado_aprobacion', 'Aprobado')
                ->whereNotIn('servicio.estado', ['Cancelado', 'Finalizado']);
            $currentLines = $active ? (clone $lines)->where('servicio.id_servicio', $active->id_servicio)->get() : collect();
            $current = $this->productionCommands($currentLines);
            $requested = $currentLines->reject(fn ($line) => $this->productionState($line->detalle_estado) === 'cancelado')
                ->groupBy('id_menu')->map(fn ($items) => ['name' => $items->first()->platillo_nombre,
                    'quantity' => $items->sum('cantidad'), 'image' => $items->first()->platillo_imagen])
                ->sortByDesc('quantity');
            $guests = $active ? DB::table('atenciones')->join('mesas', 'mesas.id_mesa', '=', 'atenciones.id_mesa')
                ->join('zonas', 'zonas.id_zona', '=', 'mesas.id_zona')->where('zonas.id_sede', $site->id_sede)
                ->where('atenciones.id_servicio', $active->id_servicio)->where('atenciones.estado', '<>', 'Cancelada')->sum('comensales') : 0;
            $todayLines = (clone $lines)->whereDate('comanda.fecha_apertura', $today)->get();
            $queue = $this->productionCommands($operational->whereIn(DB::raw('LOWER(TRIM(detalle.estado))'),
                ['pendiente', 'preparando', 'en preparacion', 'en preparación', 'listo'])->get());
            $received = $module === 'servicio' ? $currentLines : $todayLines;
            $hourly = $received->filter(fn ($line) => filled($line->fecha_apertura))
                ->unique('id_comanda')->countBy(fn ($line) => substr($line->fecha_apertura, 11, 2));
            $hourly = $hourly->sortKeys();
            if ($hourly->isNotEmpty()) {
                $hours = range((int) $hourly->keys()->first(), (int) $hourly->keys()->last());
                $hourly = collect($hours)->mapWithKeys(fn ($hour) => [$hour => $hourly->get(sprintf('%02d', $hour), 0)]);
            }
            $todayCommands = $this->productionCommands($todayLines);
            $distribution = $todayCommands->filter(fn ($order) => isset($states[$order->state]))->countBy('state');
            $recent = $todayCommands->sortByDesc('fecha_apertura')->take(6);
            $waiting = $queue->filter(fn ($command) => $command->state === 'pendiente' && filled($command->fecha_apertura))
                ->sortBy('fecha_apertura')->take(3);
            $alerts = $queue->filter(fn ($command) => $command->notes->isNotEmpty() || $command->pendingObservations->isNotEmpty())
                ->sortByDesc(fn ($command) => $command->pendingObservations->last()?->creada_en ?? '')->take(3);
            $metrics = [
                'pending' => $queue->filter(fn ($command) => $command->items->contains('state', 'pendiente'))->count(),
                'preparing' => $queue->filter(fn ($command) => $command->items->contains('state', 'preparando'))->count(),
                'ready' => $queue->flatMap(fn ($command) => $command->items)->where('state', 'listo')->sum('cantidad'),
                'delivered' => $module === 'panel' ? $this->productionDeliveredToday($site->id_sede, $today) : 0,
            ];
            return view('kitchen.production.index', $data + compact('current', 'requested', 'guests', 'hourly',
                'distribution', 'recent', 'waiting', 'alerts', 'metrics'));
        }

        $serviceOptions = (clone $services)->whereExists(fn ($query) => $query->from('atenciones as a')
            ->join('comandas as c', 'c.id_atencion', '=', 'a.id_atencion')->whereColumn('a.id_servicio', 'servicios.id_servicio'))
            ->orderByDesc('fecha')->orderBy('nombre')->get(['id_servicio', 'nombre', 'fecha']);
        $search = ltrim(trim($filter['q'] ?? ''), '#');
        $stage = match ($module) {
            'preparacion' => 'preparando', 'listos' => 'listo', default => $filter['estado'] ?? null,
        };
        $listing = (clone $base)->select('comanda.id_comanda', 'comanda.fecha_apertura')
            ->when(!$history, fn ($query) => $query->where('atencion.estado', 'En atención'))
            ->when($filter['servicio'] ?? null, fn ($query, $id) => $query->where('servicio.id_servicio', $id))
            ->when($history && !empty($filter['desde']), fn ($query) => $query->whereDate('comanda.fecha_apertura', '>=', $filter['desde']))
            ->when($history && !empty($filter['hasta']), fn ($query) => $query->whereDate('comanda.fecha_apertura', '<=', $filter['hasta']))
            ->when($stage, fn ($query) => $this->whereProductionState($query, $stage));
        if ($search !== '') {
            $matching = (clone $base)->select('comanda.id_comanda')->where(function ($query) use ($search) {
                $query->where('platillo.nombre', 'like', '%'.$search.'%');
                if (ctype_digit($search)) {
                    $query->orWhere('comanda.id_comanda', (int) $search)->orWhere('mesa.numero', (int) $search);
                }
            });
            $listing->whereIn('comanda.id_comanda', $matching);
        }
        $direction = $module === 'preparacion' ? 'asc' : 'desc';
        if ($module === 'preparacion' && $tracksPreparation) {
            $listing->groupBy('comanda.id_comanda', 'comanda.fecha_apertura')
                ->orderByRaw('MIN(COALESCE(detalle.preparacion_iniciada_en, comanda.fecha_apertura))');
        } else {
            $listing->distinct();
        }
        $listing->orderBy('comanda.fecha_apertura', $direction)->orderBy('comanda.id_comanda', $direction);
        $page = $listing->paginate(15)->appends(collect($filter)->except('comanda')->all());
        $orders = $load($page->getCollection()->pluck('id_comanda'));
        $page->setCollection($page->getCollection()->map(fn ($row) => $orders->get($row->id_comanda))->filter());
        return view('kitchen.production.index', $data + compact('page', 'serviceOptions', 'stage'));
    }

    private function productionState(?string $state): string
    {
        $state = mb_strtolower(trim($state ?? ''));
        return in_array($state, ['en preparacion', 'en preparación'], true) ? 'preparando' : $state;
    }

    private function whereProductionState($query, string $state)
    {
        return $query->whereIn(DB::raw('LOWER(TRIM(detalle.estado))'),
            $state === 'preparando' ? ['preparando', 'en preparacion', 'en preparación'] : [$state]);
    }

    private function hasPreparationHistory(): bool
    {
        return $this->hasColumns('comanda_detalles', [
            'preparacion_iniciada_en', 'preparacion_iniciada_por', 'lista_en', 'lista_por',
        ]);
    }

    private function productionCommands(Collection $lines): Collection
    {
        if ($lines->isEmpty()) {
            return collect();
        }
        $deliveryKey = fn ($line) => $line->id_atencion.':'.$line->id_menu;
        $observations = CommandObservations::history($lines->pluck('id_comanda')->unique()->values());
        $deliveries = collect();
        $deliveredCounts = collect();
        $delivered = $lines->filter(fn ($line) => $this->productionState($line->detalle_estado) === 'entregado');
        if ($delivered->isNotEmpty() && $this->hasColumns('entregas', ['id_atencion', 'id_menu', 'cantidad', 'fecha_entrega', 'id_usuario'])) {
            $attentions = $delivered->pluck('id_atencion')->unique();
            $deliveries = DB::table('entregas as e')->leftJoin('usuarios as u', 'u.id_usuario', '=', 'e.id_usuario')
                ->whereIn('e.id_atencion', $attentions)->get(['e.*', 'u.nombre', 'u.apellido_p', 'u.apellido_m'])->groupBy($deliveryKey);
            $deliveredCounts = DB::table('comanda_detalles as d')->join('comandas as c', 'c.id_comanda', '=', 'd.id_comanda')
                ->whereIn('c.id_atencion', $attentions)->whereRaw("LOWER(TRIM(d.estado)) = 'entregado'")
                ->get(['c.id_atencion', 'd.id_menu'])->countBy($deliveryKey);
        }

        return $lines->groupBy('id_comanda')->map(function ($items) use ($deliveries, $deliveredCounts, $deliveryKey, $observations) {
            $command = clone $items->first();
            foreach ($items as $item) {
                $item->state = $this->productionState($item->detalle_estado);
                $key = $deliveryKey($item);
                $matches = $deliveries->get($key);
                $delivery = $item->state === 'entregado' && $deliveredCounts->get($key) === 1 && $matches?->count() === 1
                    && (int) $matches->first()->cantidad === (int) $item->cantidad ? $matches->first() : null;
                $item->delivery = $delivery;
            }
            $command->items = $items;
            $command->state = collect(array_keys(self::PRODUCTION_STATES))->first(fn ($state) => $items->contains('state', $state), '');
            $command->quantity = $items->where('state', '!=', 'cancelado')->sum('cantidad');
            $command->waiter = trim(implode(' ', array_filter([$command->mesero_nombre, $command->mesero_paterno, $command->mesero_materno])));
            $command->notes = collect([$command->atencion_observaciones ?? null, $command->comanda_observaciones])
                ->merge($items->pluck('detalle_observaciones'))->filter(fn ($note) => filled($note))->unique()->values();
            $command->observations = $observations->get($command->id_comanda, collect());
            $command->pendingObservations = $command->observations->whereNull('revisada_en');
            $command->allergenHold = $command->pendingObservations->contains('prioridad', 'alergenos');
            return $command;
        });
    }

    private function productionDeliveredToday(int $site, string $today): int
    {
        if (!$this->hasColumns('entregas', ['id_atencion', 'id_menu', 'cantidad', 'fecha_entrega', 'id_usuario'])) {
            return 0;
        }
        $rows = $this->kitchenOrdersQuery($site, true)->where('zona.id_sede', $site)
            ->whereIn('atencion.id_atencion', DB::table('entregas')->whereDate('fecha_entrega', $today)->select('id_atencion'))
            ->selectRaw('NULL as mesero_nombre, NULL as mesero_paterno, NULL as mesero_materno')->get();
        return $this->productionCommands($rows)->filter(function ($command) use ($today) {
            $items = $command->items->where('state', '!=', 'cancelado');
            return $items->isNotEmpty() && $items->every(fn ($item) => !empty($item->delivery?->fecha_entrega))
                && Carbon::parse($items->max(fn ($item) => $item->delivery->fecha_entrega))->toDateString() === $today;
        })->count();
    }
}
