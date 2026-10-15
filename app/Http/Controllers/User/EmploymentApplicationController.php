<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\EmploymentApplication;
use App\Models\Role;
use App\Models\Site;
use App\Support\EmploymentRoles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmploymentApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $applications = EmploymentApplication::query()
            ->with(['site', 'requestedRole'])
            ->where('id_usuario', $request->user()->getKey())
            ->orderByDesc('fecha_solicitud')
            ->orderByDesc('id_solicitud')
            ->paginate(15);

        return view('user.employment-applications.index', compact('applications'));
    }

    public function create(): View
    {
        return view('user.employment-applications.create', [
            'sites' => Site::query()->orderBy('nombre')->get(),
            'roles' => EmploymentRoles::publicRoles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $publicRoleIds = EmploymentRoles::publicRoleIds();
        $data = $request->validate([
            'id_sede' => ['required', 'integer', Rule::exists('sedes', 'id_sede')],
            'id_rol_solicitado' => [
                'required',
                'integer',
                Rule::exists('roles', 'id_rol'),
                Rule::in($publicRoleIds),
            ],
            'mensaje' => ['required', 'string', 'max:1000'],
        ], [
            'id_sede.required' => 'Selecciona una sede.',
            'id_sede.exists' => 'La sede seleccionada no existe.',
            'id_rol_solicitado.required' => 'Selecciona un puesto.',
            'id_rol_solicitado.exists' => 'El puesto seleccionado no existe.',
            'id_rol_solicitado.in' => 'El puesto seleccionado no está disponible para solicitud.',
            'mensaje.required' => 'Escribe brevemente el motivo de tu solicitud.',
            'mensaje.max' => 'El mensaje no puede exceder 1000 caracteres.',
        ]);

        DB::transaction(function () use ($data, $request): void {
            $userId = (int) $request->user()->getKey();

            DB::table('usuarios')->where('id_usuario', $userId)->lockForUpdate()->first();

            $siteExists = Site::query()->whereKey($data['id_sede'])->exists();
            $role = Role::query()->whereKey($data['id_rol_solicitado'])->first();
            $roleIsPublic = $role
                && in_array((int) $role->getKey(), EmploymentRoles::publicRoleIds(), true)
                && EmploymentRoles::isPublic($role->nombre);

            if (! $siteExists || ! $roleIsPublic) {
                throw ValidationException::withMessages([
                    'solicitud' => 'La sede o el puesto seleccionado ya no está disponible.',
                ]);
            }

            $alreadyPending = EmploymentApplication::query()
                ->where('id_usuario', $userId)
                ->where('id_sede', $data['id_sede'])
                ->where('id_rol_solicitado', $data['id_rol_solicitado'])
                ->where('estado', 'pendiente')
                ->exists();

            if ($alreadyPending) {
                throw ValidationException::withMessages([
                    'id_rol_solicitado' => 'Ya tienes una solicitud pendiente para ese puesto y sede.',
                ]);
            }

            $application = new EmploymentApplication;
            $application->id_usuario = $userId;
            $application->id_sede = (int) $data['id_sede'];
            $application->id_rol_solicitado = (int) $data['id_rol_solicitado'];
            $application->mensaje = $data['mensaje'];
            $application->estado = 'pendiente';
            $application->fecha_solicitud = now();
            $application->save();
        });

        return redirect()->route('user.applications.index')
            ->with('status', 'Solicitud enviada correctamente.');
    }
}
