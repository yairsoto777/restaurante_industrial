<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\EmploymentApplication;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Support\EmploymentRoles;
use App\Support\SiteContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmploymentApplicationController extends Controller
{
    public function index(Request $request, SiteContext $siteContext): View
    {
        $currentSite = $this->currentManagedSite($request, $siteContext);
        $applications = EmploymentApplication::query()
            ->with(['user', 'site', 'requestedRole'])
            ->where('id_sede', $currentSite->getKey())
            ->where('id_usuario', '!=', $request->user()->getKey())
            ->orderByRaw("CASE estado WHEN 'pendiente' THEN 0 WHEN 'aprobada' THEN 1 ELSE 2 END")
            ->orderByDesc('fecha_solicitud')
            ->paginate(15);

        return view('manager.employment-applications.index', compact('applications', 'currentSite'));
    }

    public function show(
        Request $request,
        EmploymentApplication $application,
        SiteContext $siteContext,
    ): View {
        $currentSite = $this->currentManagedSite($request, $siteContext);
        $this->ensureApplicationBelongsToSite($application, $currentSite);
        $this->ensureManagerIsNotApplicant($request, $application);
        $application->load(['user', 'site', 'requestedRole', 'responder']);

        return view('manager.employment-applications.show', compact('application', 'currentSite'));
    }

    public function approve(
        Request $request,
        EmploymentApplication $application,
        SiteContext $siteContext,
    ): RedirectResponse {
        $data = $request->validate([
            'observaciones_respuesta' => ['nullable', 'string', 'max:1000'],
        ]);
        $currentSite = $this->currentManagedSite($request, $siteContext);
        $this->ensureApplicationBelongsToSite($application, $currentSite);
        $this->ensureManagerIsNotApplicant($request, $application);

        DB::transaction(function () use ($application, $currentSite, $data, $request): void {
            $lockedApplication = EmploymentApplication::query()
                ->whereKey($application->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureApplicationBelongsToSite($lockedApplication, $currentSite);
            $this->ensureManagerStillBelongsToSite($request, (int) $currentSite->getKey());
            $this->ensureManagerIsNotApplicant($request, $lockedApplication);
            [$user, $role] = $this->validatePendingApplication($lockedApplication);

            $assignment = [
                'id_usuario' => (int) $user->getKey(),
                'id_sede' => (int) $currentSite->getKey(),
                'id_rol' => (int) $role->getKey(),
            ];
            $assignmentCreated = false;

            if (! DB::table('usuario_sede')->where($assignment)->exists()) {
                DB::table('usuario_sede')->insert($assignment);
                $assignmentCreated = true;
            }

            $previousState = $lockedApplication->estado;
            $lockedApplication->estado = 'aprobada';
            $lockedApplication->fecha_respuesta = now();
            $lockedApplication->id_usuario_responde = (int) $request->user()->getKey();
            $lockedApplication->observaciones_respuesta = $data['observaciones_respuesta'] ?? null;
            $lockedApplication->save();

            $this->writeAuditEntry($request, $lockedApplication, $previousState, $assignmentCreated);
        });

        return redirect()->route('manager.applications.show', $application)
            ->with('status', 'Solicitud aprobada y asignación registrada correctamente.');
    }

    public function reject(
        Request $request,
        EmploymentApplication $application,
        SiteContext $siteContext,
    ): RedirectResponse {
        $data = $request->validate([
            'observaciones_respuesta' => ['required', 'string', 'max:1000'],
        ], [
            'observaciones_respuesta.required' => 'Indica el motivo del rechazo.',
            'observaciones_respuesta.max' => 'La observación no puede exceder 1000 caracteres.',
        ]);
        $currentSite = $this->currentManagedSite($request, $siteContext);
        $this->ensureApplicationBelongsToSite($application, $currentSite);
        $this->ensureManagerIsNotApplicant($request, $application);

        DB::transaction(function () use ($application, $currentSite, $data, $request): void {
            $lockedApplication = EmploymentApplication::query()
                ->whereKey($application->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureApplicationBelongsToSite($lockedApplication, $currentSite);
            $this->ensureManagerStillBelongsToSite($request, (int) $currentSite->getKey());
            $this->ensureManagerIsNotApplicant($request, $lockedApplication);
            $this->validatePendingApplication($lockedApplication);

            $previousState = $lockedApplication->estado;
            $lockedApplication->estado = 'rechazada';
            $lockedApplication->fecha_respuesta = now();
            $lockedApplication->id_usuario_responde = (int) $request->user()->getKey();
            $lockedApplication->observaciones_respuesta = $data['observaciones_respuesta'];
            $lockedApplication->save();

            $this->writeAuditEntry($request, $lockedApplication, $previousState, false);
        });

        return redirect()->route('manager.applications.show', $application)
            ->with('status', 'Solicitud rechazada.');
    }

    private function currentManagedSite(Request $request, SiteContext $siteContext): Site
    {
        $currentSite = $siteContext->currentFor($request, $request->user());

        abort_unless(
            $currentSite && $request->user()->isManagerAtSite((int) $currentSite->getKey()),
            403,
        );

        return $currentSite;
    }

    private function ensureApplicationBelongsToSite(
        EmploymentApplication $application,
        Site $site,
    ): void {
        abort_unless((int) $application->id_sede === (int) $site->getKey(), 403);
    }

    private function ensureManagerStillBelongsToSite(Request $request, int $siteId): void
    {
        abort_unless($request->user()->isManagerAtSite($siteId), 403);
    }

    private function ensureManagerIsNotApplicant(
        Request $request,
        EmploymentApplication $application,
    ): void {
        abort_if((int) $application->id_usuario === (int) $request->user()->getKey(), 403);
    }

    private function validatePendingApplication(EmploymentApplication $application): array
    {
        if ($application->estado !== 'pendiente') {
            throw ValidationException::withMessages([
                'solicitud' => 'Esta solicitud ya fue respondida.',
            ]);
        }

        $user = User::query()
            ->whereKey($application->id_usuario)
            ->lockForUpdate()
            ->first();
        $siteExists = Site::query()->whereKey($application->id_sede)->exists();
        $role = Role::query()->whereKey($application->id_rol_solicitado)->first();
        $roleIsPublic = $role
            && in_array((int) $role->getKey(), EmploymentRoles::publicRoleIds(), true)
            && EmploymentRoles::isPublic($role->nombre);

        if (! $user || ! $siteExists || ! $roleIsPublic) {
            throw ValidationException::withMessages([
                'solicitud' => 'La solicitud contiene un usuario, sede o puesto que ya no existe o no está permitido.',
            ]);
        }

        return [$user, $role];
    }

    private function writeAuditEntry(
        Request $request,
        EmploymentApplication $application,
        string $previousState,
        bool $assignmentCreated,
    ): void {
        if (! Schema::hasTable('bitacora')) {
            return;
        }

        DB::table('bitacora')->insert([
            'id_usuario' => (int) $request->user()->getKey(),
            'id_sede' => (int) $application->id_sede,
            'accion' => $application->estado === 'aprobada' ? 'aprobar_solicitud' : 'rechazar_solicitud',
            'tabla_afectada' => 'solicitudes_empleo',
            'id_registro' => (int) $application->getKey(),
            'datos_anteriores' => json_encode(['estado' => $previousState], JSON_UNESCAPED_UNICODE),
            'datos_nuevos' => json_encode([
                'estado' => $application->estado,
                'id_usuario_responde' => $application->id_usuario_responde,
                'fecha_respuesta' => (string) $application->fecha_respuesta,
                'asignacion_creada' => $assignmentCreated,
            ], JSON_UNESCAPED_UNICODE),
            'descripcion' => $application->estado === 'aprobada'
                ? 'Solicitud de empleo aprobada.'
                : 'Solicitud de empleo rechazada.',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
