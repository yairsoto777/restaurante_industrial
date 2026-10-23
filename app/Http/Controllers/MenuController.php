<?php

namespace App\Http\Controllers;

use App\Models\Dish;
use App\Models\Service;
use App\Support\SiteContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(Request $request, SiteContext $context): View
    {
        $site = $context->currentFor($request, $request->user());
        abort_unless($site && ($request->user()->canAdminister() || $request->user()->sites()->whereKey($site->id_sede)->exists()), 403);

        $canManage = $request->user()->hasRole('Jefe de cocina');
        $isKitchenStaff = $request->user()->hasRole('Personal de cocina');
        $services = Service::query()->where('id_sede', $site->id_sede)
            ->when(! $canManage, fn ($query) => $query->where('estado_aprobacion', 'Aprobado'))
            ->when($isKitchenStaff, fn ($query) => $query->whereDate('fecha', '>=', now('America/Mexico_City')->toDateString())
                ->where(fn ($stateQuery) => $stateQuery->whereNull('estado')->orWhereNotIn('estado', ['Finalizado', 'Cancelado'])))
            ->orderByDesc('fecha')->orderBy('inicio_hora')->get();
        $serviceId = $request->integer('service');
        $service = $services->firstWhere('id_servicio', $serviceId) ?? $services->first();
        $menuAvailable = Schema::hasTable('menus')
            && Schema::hasColumn('menus', 'id_menu')
            && Schema::hasColumn('menus', 'id_servicio')
            && Schema::hasColumn('menus', 'id_platillo')
            && Schema::hasColumn('menus', 'precio_venta')
            && Schema::hasColumn('menus', 'publicado')
            && Schema::hasColumn('menus', 'disponible');
        $selected = collect();
        if ($service && $menuAvailable) {
            $selected = DB::table('menus as menu')->join('platillos as platillo', 'platillo.id_platillo', '=', 'menu.id_platillo')
                ->where('menu.id_servicio', $service->id_servicio)->where('platillo.id_sede', $site->id_sede)
                ->when($isKitchenStaff, fn ($query) => $query->where('menu.publicado', 1)->where('menu.disponible', 1))
                ->get(['menu.id_menu', 'menu.id_platillo', 'menu.precio_venta', 'menu.publicado', 'menu.disponible']);
        }
        $visibleDishIds = $selected->pluck('id_platillo')->all();
        $dishes = Dish::with('category')->where('id_sede', $site->id_sede)
            ->when($isKitchenStaff, fn ($query) => $query->whereIn('id_platillo', $visibleDishIds))
            ->orderBy('nombre')->get();

        return view('menus.index', [
            'site' => $site,
            'services' => $services,
            'service' => $service,
            'dishes' => $dishes,
            'selected' => $selected,
            'menuAvailable' => $menuAvailable,
            'publishedCount' => $selected->where('publicado', 1)->where('disponible', 1)->count(),
            'draftCount' => $selected->where('publicado', 0)->count(),
        ]);
    }

    public function store(Request $request, Service $service, SiteContext $context): RedirectResponse
    {
        abort_unless($request->user()->hasRole('Jefe de cocina'), 403);
        $site = $context->currentFor($request, $request->user());
        abort_unless($site && $request->user()->sites()->whereKey($site->id_sede)->exists(), 403);
        abort_unless((int) $service->id_sede === (int) $site->id_sede, 404);
        abort_unless($this->menuSchemaAvailable(), 503, 'La tabla menus no tiene las columnas necesarias para asignar platillos al servicio.');

        $validated = $request->validate([
            'platillos' => ['nullable', 'array'],
            'platillos.*' => ['required', 'integer', 'distinct', 'exists:platillos,id_platillo'],
            'precio_venta' => ['nullable', 'array'],
            'precio_venta.*' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'publicado' => ['nullable', 'array'],
            'publicado.*' => ['boolean'],
            'disponible' => ['nullable', 'array'],
            'disponible.*' => ['boolean'],
        ]);

        $selectedIds = collect($validated['platillos'] ?? [])->map(fn ($id): int => (int) $id)->unique()->values();
        $siteDishIds = Dish::query()->where('id_sede', $site->id_sede)->whereIn('id_platillo', $selectedIds)->pluck('id_platillo')->map(fn ($id): int => (int) $id);
        if ($siteDishIds->count() !== $selectedIds->count()) {
            throw ValidationException::withMessages(['platillos' => 'Todos los platillos deben pertenecer a la sede activa.']);
        }

        DB::transaction(function () use ($validated, $selectedIds, $site, $service): void {
            $lockedService = Service::query()->whereKey($service->getKey())->where('id_sede', $site->id_sede)->lockForUpdate()->firstOrFail();
            if (in_array($lockedService->estado, ['Finalizado', 'Cancelado'], true)) {
                throw ValidationException::withMessages(['service' => 'No se puede modificar el menú de un servicio finalizado o cancelado.']);
            }
            $existing = DB::table('menus as menu')
                ->join('platillos as platillo', 'platillo.id_platillo', '=', 'menu.id_platillo')
                ->where('menu.id_servicio', $lockedService->id_servicio)
                ->where('platillo.id_sede', $site->id_sede)
                ->lockForUpdate()
                ->get(['menu.id_menu', 'menu.id_platillo', 'menu.precio_venta', 'menu.publicado', 'menu.disponible']);
            $existingByDish = $existing->groupBy('id_platillo');
            $selectedLookup = $selectedIds->flip();

            foreach ($existing as $entry) {
                if (! $selectedLookup->has((int) $entry->id_platillo)) {
                    // Menu rows may already be referenced by orders or deliveries; keep them for history.
                    DB::table('menus')->where('id_menu', $entry->id_menu)->update(['publicado' => 0, 'disponible' => 0]);
                }
            }

            foreach ($selectedIds as $dishId) {
                $matches = $existingByDish->get($dishId, collect())->values();
                $menuId = $matches->first()->id_menu ?? null;
                $available = (bool) (($validated['disponible'][$dishId] ?? false));
                $published = $lockedService->estado_aprobacion === 'Aprobado'
                    && $available
                    && (bool) (($validated['publicado'][$dishId] ?? false));
                $attributes = [
                    'id_servicio' => $lockedService->id_servicio,
                    'id_platillo' => $dishId,
                    'precio_venta' => $validated['precio_venta'][$dishId] ?? null,
                    'publicado' => $published ? 1 : 0,
                    'disponible' => $available ? 1 : 0,
                ];

                if ($menuId !== null) {
                    DB::table('menus')->where('id_menu', $menuId)->update($attributes);
                    foreach ($matches->skip(1) as $duplicate) {
                        DB::table('menus')->where('id_menu', $duplicate->id_menu)->update(['publicado' => 0, 'disponible' => 0]);
                    }
                } else {
                    DB::table('menus')->insert($attributes);
                }
            }
        }, 3);

        return redirect()->route('menus.index', ['service' => $service->id_servicio])->with('status', 'El menú del servicio se guardó. Los cambios conservan el historial de comandas y entregas.');
    }

    private function menuSchemaAvailable(): bool
    {
        if (! Schema::hasTable('menus')) {
            return false;
        }
        foreach (['id_menu', 'id_servicio', 'id_platillo', 'precio_venta', 'publicado', 'disponible'] as $column) {
            if (! Schema::hasColumn('menus', $column)) {
                return false;
            }
        }

        return true;
    }
}
