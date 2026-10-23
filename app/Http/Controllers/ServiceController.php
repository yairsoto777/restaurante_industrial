<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Support\SiteContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ServiceController extends Controller
{
    private const STATES = ['Programado', 'En curso', 'Finalizado', 'Cancelado'];

    public function managerIndex(Request $request, SiteContext $siteContext): View
    {
        $site = $this->managerSite($request, $siteContext);
        $services = Service::query()->where('id_sede', $site->id_sede)
            ->orderByRaw('CASE WHEN fecha IS NULL THEN 1 ELSE 0 END')
            ->orderBy('fecha')->orderBy('inicio_hora')->paginate(15);

        return view('manager.services.index', compact('site', 'services'));
    }

    public function create(Request $request, SiteContext $siteContext): View
    {
        $site = $this->kitchenChiefSite($request, $siteContext);

        return view('manager.services.form', [
            'site' => $site,
            'service' => new Service(['estado' => 'Programado', 'estado_aprobacion' => 'Pendiente']),
        ]);
    }

    public function store(Request $request, SiteContext $siteContext): RedirectResponse
    {
        $site = $this->kitchenChiefSite($request, $siteContext);
        $service = Service::create([...$this->validated($request), 'id_sede' => $site->id_sede, 'estado' => 'Programado', 'estado_aprobacion' => 'Pendiente', 'id_usuario_registro' => $request->user()->getKey()]);

        return redirect()->route('services.index')->with('status', "El servicio {$service->nombre} fue enviado a revisión.");
    }

    public function edit(Request $request, Service $service, SiteContext $siteContext): View
    {
        $this->authorizeKitchenService($request, $service, $siteContext);

        return view('manager.services.form', [
            'site' => $service->site,
            'service' => $service,
        ]);
    }

    public function update(Request $request, Service $service, SiteContext $siteContext): RedirectResponse
    {
        $this->authorizeKitchenService($request, $service, $siteContext);
        abort_if($this->hasOperations($service), 422, 'El servicio ya tiene operaciones registradas y no puede modificarse.');

        $service->update([...$this->validated($request, $service), 'estado_aprobacion' => 'Pendiente', 'id_usuario_revision' => null, 'revisado_en' => null, 'observaciones_revision' => null]);

        return redirect()->route('services.index')->with('status', 'El servicio fue actualizado y enviado a revisión.');
    }

    public function kitchenIndex(Request $request, SiteContext $siteContext): View
    {
        $site = $this->operationalSite($request, $siteContext);
        $isKitchenStaff = $request->user()->hasRole('Personal de cocina');
        $listingDate = ($isKitchenStaff ? now('America/Mexico_City') : now())->toDateString();
        $baseQuery = Service::query()->where('id_sede', $site->id_sede);
        $visibleQuery = clone $baseQuery;
        if (! $request->user()->hasRole('Jefe de cocina')) {
            $visibleQuery->where('estado_aprobacion', 'Aprobado')
                ->whereDate('fecha', '>=', $listingDate);
            if ($isKitchenStaff) {
                $visibleQuery->where(fn ($query) => $query->whereNull('estado')->orWhere('estado', '<>', 'Cancelado'));
            }
        }
        $totalCount = (clone $visibleQuery)->count();
        $pendingCount = $request->user()->hasRole('Jefe de cocina')
            ? (clone $visibleQuery)->where('estado_aprobacion', 'Pendiente')->count()
            : 0;
        $approvedCount = (clone $visibleQuery)->where('estado_aprobacion', 'Aprobado')->count();
        $activeCount = (clone $visibleQuery)->where('estado_aprobacion', 'Aprobado')->where('estado', 'En curso')->count();
        $scheduledCount = (clone $visibleQuery)->where('estado_aprobacion', 'Aprobado')->where('estado', 'Programado')->count();
        $search = trim((string) $request->query('q', ''));
        $filterState = (string) $request->query('estado', '');
        $validFilters = $isKitchenStaff
            ? ['Programado', 'En curso', 'Finalizado']
            : ['Pendiente', 'Aprobado', 'Rechazado', 'Programado', 'En curso', 'Finalizado', 'Cancelado'];
        if (! in_array($filterState, $validFilters, true)) {
            $filterState = '';
        }

        $services = (clone $baseQuery)
            ->when(! $request->user()->hasRole('Jefe de cocina'), function ($query) use ($listingDate, $isKitchenStaff): void {
                $query->where('estado_aprobacion', 'Aprobado')->whereDate('fecha', '>=', $listingDate);
                if ($isKitchenStaff) {
                    $query->where(fn ($stateQuery) => $stateQuery->whereNull('estado')->orWhere('estado', '<>', 'Cancelado'));
                }
            })
            ->when($search !== '', fn ($query) => $query->where('nombre', 'like', '%'.$search.'%'))
            ->when($filterState !== '', function ($query) use ($filterState): void {
                if (in_array($filterState, ['Pendiente', 'Aprobado', 'Rechazado'], true)) {
                    $query->where('estado_aprobacion', $filterState);
                } else {
                    $query->where('estado', $filterState);
                }
            })
            ->orderBy('fecha')->orderBy('inicio_hora')->paginate(15);

        return view('kitchen.services.index', compact('site', 'services', 'totalCount', 'pendingCount', 'approvedCount', 'activeCount', 'scheduledCount', 'search', 'filterState'));
    }

    public function updateOperationalStatus(Request $request, Service $service, SiteContext $siteContext): RedirectResponse
    {
        $site = $this->kitchenChiefSite($request, $siteContext);
        abort_unless((int) $service->id_sede === (int) $site->id_sede, 404);

        $validated = $request->validate([
            'estado' => ['required', Rule::in(['En curso', 'Finalizado', 'Cancelado'])],
        ]);

        DB::transaction(function () use ($service, $site, $validated): void {
            $lockedService = Service::query()
                ->whereKey($service->getKey())
                ->where('id_sede', $site->id_sede)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($lockedService->estado_aprobacion === 'Aprobado', 422, 'Solo se puede operar un servicio aprobado.');
            if (! $this->validTransition($lockedService->estado, $validated['estado'])) {
                throw ValidationException::withMessages([
                    'estado' => 'Ese cambio de estado no está permitido desde el estado actual.',
                ]);
            }

            $lockedService->update(['estado' => $validated['estado']]);
        }, 3);

        return redirect()->route('services.index')->with('status', 'El estado del servicio fue actualizado.');
    }

    public function live(Request $request, SiteContext $siteContext): View
    {
        $site = $this->operationalSite($request, $siteContext);
        $isKitchenStaff = $request->user()->hasRole('Personal de cocina');
        $today = ($isKitchenStaff ? now('America/Mexico_City') : now())->toDateString();
        $active = Service::query()->where('id_sede', $site->id_sede)->where('estado_aprobacion', 'Aprobado')->whereDate('fecha', $today)
            ->whereIn('estado', ['En curso', 'en curso'])->orderBy('inicio_hora')->first();
        $todayQuery = Service::query()->where('id_sede', $site->id_sede)->where('estado_aprobacion', 'Aprobado')->whereDate('fecha', $today);
        if ($isKitchenStaff) {
            $todayQuery->where(fn ($query) => $query->whereNull('estado')->orWhere('estado', '<>', 'Cancelado'));
        }
        $todayServices = $todayQuery->orderBy('inicio_hora')->get();

        return view('kitchen.services.live', compact('site', 'active', 'todayServices'));
    }

    public function approve(Request $request, Service $service, SiteContext $siteContext): RedirectResponse
    {
        $site = $this->managerSite($request, $siteContext);
        abort_unless((int) $service->id_sede === (int) $site->id_sede, 403);
        abort_unless($service->estado_aprobacion === 'Pendiente', 422, 'El servicio ya fue revisado.');
        abort_if((int) $service->id_usuario_registro === (int) $request->user()->getKey(), 403, 'No puedes aprobar tus propios servicios.');
        $service->update(['estado_aprobacion' => 'Aprobado', 'id_usuario_revision' => $request->user()->getKey(), 'revisado_en' => now(), 'observaciones_revision' => null]);
        return back()->with('status', 'Servicio aprobado correctamente.');
    }

    public function reject(Request $request, Service $service, SiteContext $siteContext): RedirectResponse
    {
        $site = $this->managerSite($request, $siteContext);
        abort_unless((int) $service->id_sede === (int) $site->id_sede, 403);
        abort_unless($service->estado_aprobacion === 'Pendiente', 422, 'El servicio ya fue revisado.');
        abort_if((int) $service->id_usuario_registro === (int) $request->user()->getKey(), 403, 'No puedes rechazar tus propios servicios.');
        $data = $request->validate(['observaciones_revision' => ['required', 'string', 'max:1000']]);
        $service->update(['estado_aprobacion' => 'Rechazado', 'id_usuario_revision' => $request->user()->getKey(), 'revisado_en' => now(), 'observaciones_revision' => $data['observaciones_revision']]);
        return back()->with('status', 'Servicio rechazado con observaciones.');
    }

    private function validated(Request $request, ?Service $service = null): array
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'fecha' => ['required', 'date'],
            'inicio_hora' => ['required', 'date_format:H:i'],
            'fin_hora' => ['required', 'date_format:H:i', 'after:inicio_hora'],
            'capacidad' => ['required', 'integer', 'min:1'],
        ]);

        return $data;
    }

    private function validTransition(?string $from, string $to): bool
    {
        return match ($from) {
            'Programado' => in_array($to, ['En curso', 'Cancelado'], true),
            'En curso' => in_array($to, ['Finalizado', 'Cancelado'], true),
            default => false,
        };
    }

    private function hasOperations(Service $service): bool
    {
        foreach (['grupos', 'menus', 'atenciones'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'id_servicio') &&
                $service->newQuery()->getConnection()->table($table)->where('id_servicio', $service->id_servicio)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function managerSite(Request $request, SiteContext $siteContext)
    {
        abort_unless($request->user()->isManager(), 403);
        $site = $siteContext->currentFor($request, $request->user());
        abort_unless($site && $request->user()->isManagerAtSite((int) $site->id_sede), 403);

        return $site;
    }

    private function operationalSite(Request $request, SiteContext $siteContext)
    {
        abort_unless($request->user()->isAdministrator()
            || $request->user()->isManager()
            || $request->user()->hasRole('Personal de cocina')
            || $request->user()->hasRole('Jefe de cocina'), 403);
        $site = $siteContext->currentFor($request, $request->user());
        abort_unless($site && $request->user()->sites()->whereKey($site->id_sede)->exists(), 403);

        return $site;
    }

    private function kitchenChiefSite(Request $request, SiteContext $siteContext)
    {
        abort_unless($request->user()->hasRole('Jefe de cocina'), 403);
        $site = $siteContext->currentFor($request, $request->user());
        abort_unless($site && $request->user()->sites()->whereKey($site->id_sede)->exists(), 403);
        return $site;
    }

    private function authorizeKitchenService(Request $request, Service $service, SiteContext $siteContext): void
    {
        $site = $this->kitchenChiefSite($request, $siteContext);
        abort_unless((int) $service->id_sede === (int) $site->id_sede, 403);
        abort_unless(in_array($service->estado_aprobacion, ['Pendiente', 'Rechazado', null], true), 422, 'El servicio ya fue aprobado.');
    }

    private function authorizeManagerService(Request $request, Service $service, SiteContext $siteContext): void
    {
        $site = $this->managerSite($request, $siteContext);
        abort_unless((int) $service->id_sede === (int) $site->id_sede, 403);
    }
}
