<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Support\EmploymentRoles;
use App\Support\SiteContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request, SiteContext $siteContext): View
    {
        $currentSite = $this->currentManagedSite($request, $siteContext);
        $roleIds = EmploymentRoles::employeeRoleIds();
        $siteId = (int) $currentSite->getKey();

        $employees = Employee::query()
            ->with([
                'position',
                'user.roles' => fn ($query) => $query
                    ->where('usuario_sede.id_sede', $siteId)
                    ->whereIn('roles.id_rol', $roleIds),
            ])
            ->where('id_sede', $siteId)
            ->whereNotNull('id_usuario')
            ->whereHas('user.roles', fn ($query) => $query
                ->where('usuario_sede.id_sede', $siteId)
                ->whereIn('roles.id_rol', $roleIds))
            ->orderBy('nombre')
            ->paginate(15);

        return view('manager.employees.index', compact('employees', 'currentSite'));
    }

    public function create(Request $request, SiteContext $siteContext): View
    {
        $currentSite = $this->currentManagedSite($request, $siteContext);

        return view('manager.employees.form', [
            'employee' => new Employee,
            'user' => new User,
            'roles' => EmploymentRoles::employeeRoles(),
            'currentSite' => $currentSite,
            'selectedRoleId' => null,
            'positionName' => null,
        ]);
    }

    public function store(Request $request, SiteContext $siteContext): RedirectResponse
    {
        $currentSite = $this->currentManagedSite($request, $siteContext);
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $currentSite, $request): void {
            $this->ensureManagerStillManages($request->user(), (int) $currentSite->getKey());
            $role = $this->lockedEmployeeRole((int) $data['id_rol']);
            $position = Position::query()->firstOrCreate(['nombre' => $data['puesto']]);

            $user = User::create([
                'nombre' => $data['nombre'],
                'apellido_p' => $data['apellido_p'],
                'apellido_m' => $data['apellido_m'] ?? null,
                'correo' => $data['correo'],
                'password' => $data['password'],
            ]);

            DB::table('usuario_sede')->insert([
                'id_usuario' => (int) $user->getKey(),
                'id_sede' => (int) $currentSite->getKey(),
                'id_rol' => (int) $role->getKey(),
            ]);

            Employee::create([
                'nombre' => $this->employeeName($data),
                'id_sede' => (int) $currentSite->getKey(),
                'id_puesto' => (int) $position->getKey(),
                'id_usuario' => (int) $user->getKey(),
            ]);
        }, 3);

        return redirect()->route('manager.employees.index')
            ->with('status', 'Empleado registrado correctamente.');
    }

    public function edit(
        Request $request,
        Employee $employee,
        SiteContext $siteContext,
    ): View {
        $currentSite = $this->currentManagedSite($request, $siteContext);
        [$user, $assignment] = $this->authorizedEmployee($request->user(), $employee, $currentSite);
        $employee->load('position');

        return view('manager.employees.form', [
            'employee' => $employee,
            'user' => $user,
            'roles' => EmploymentRoles::employeeRoles(),
            'currentSite' => $currentSite,
            'selectedRoleId' => (int) $assignment->id_rol,
            'positionName' => $employee->position?->nombre,
        ]);
    }

    public function update(
        Request $request,
        Employee $employee,
        SiteContext $siteContext,
    ): RedirectResponse {
        $currentSite = $this->currentManagedSite($request, $siteContext);
        [$user] = $this->authorizedEmployee($request->user(), $employee, $currentSite);
        $data = $this->validated($request, $user);

        DB::transaction(function () use ($data, $employee, $currentSite, $request): void {
            $lockedEmployee = Employee::query()
                ->whereKey($employee->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureManagerStillManages($request->user(), (int) $currentSite->getKey());
            [$lockedUser, $assignment] = $this->authorizedEmployee(
                $request->user(),
                $lockedEmployee,
                $currentSite,
                true,
            );
            $role = $this->lockedEmployeeRole((int) $data['id_rol']);
            $position = Position::query()->firstOrCreate(['nombre' => $data['puesto']]);

            $userAttributes = [
                'nombre' => $data['nombre'],
                'apellido_p' => $data['apellido_p'],
                'apellido_m' => $data['apellido_m'] ?? null,
                'correo' => $data['correo'],
            ];
            if (! empty($data['password'])) {
                $userAttributes['password'] = $data['password'];
            }

            $lockedUser->update($userAttributes);
            $lockedEmployee->update([
                'nombre' => $this->employeeName($data),
                'id_puesto' => (int) $position->getKey(),
            ]);
            DB::table('usuario_sede')
                ->where('id_usuario_sede', $assignment->id_usuario_sede)
                ->update(['id_rol' => (int) $role->getKey()]);
        }, 3);

        return redirect()->route('manager.employees.index')
            ->with('status', 'Empleado actualizado correctamente.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $request->merge([
            'nombre' => $this->singleLine($request->input('nombre')),
            'apellido_p' => $this->singleLine($request->input('apellido_p')),
            'apellido_m' => $this->singleLine($request->input('apellido_m')),
            'correo' => is_string($request->input('correo'))
                ? Str::lower(trim($request->input('correo')))
                : $request->input('correo'),
            'puesto' => $this->singleLine($request->input('puesto')),
        ]);

        $employeeRoleIds = EmploymentRoles::employeeRoleIds();

        return $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'apellido_p' => ['required', 'string', 'max:100'],
            'apellido_m' => ['nullable', 'string', 'max:100'],
            'correo' => [
                'required',
                'email:rfc',
                'max:150',
                Rule::unique('usuarios', 'correo')->ignore($user?->getKey(), 'id_usuario'),
            ],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'max:255'],
            'id_rol' => [
                'required',
                'integer',
                Rule::in($employeeRoleIds),
                Rule::exists('roles', 'id_rol'),
            ],
            'puesto' => ['required', 'string', 'max:100'],
        ], [
            'correo.unique' => 'El correo ya pertenece a otro usuario.',
            'password.required' => 'Ingresa una contraseña temporal.',
            'password.min' => 'La contraseña temporal debe tener al menos 8 caracteres.',
            'id_rol.in' => 'El rol seleccionado no está permitido para un empleado.',
        ]);
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

    private function ensureManagerStillManages(User $manager, int $siteId): void
    {
        abort_unless($manager->isManagerAtSite($siteId), 403);
    }

    private function authorizedEmployee(
        User $manager,
        Employee $employee,
        Site $currentSite,
        bool $lock = false,
    ): array {
        abort_unless((int) $employee->id_sede === (int) $currentSite->getKey(), 403);

        $userQuery = User::query()->whereKey($employee->id_usuario);
        if ($lock) {
            $userQuery->lockForUpdate();
        }
        $user = $userQuery->first();

        abort_unless($user, 403);
        abort_if($manager->is($user) || $user->isManager() || $user->canAdminister(), 403);

        $assignmentQuery = DB::table('usuario_sede')
            ->where('id_usuario', $user->getKey())
            ->where('id_sede', $currentSite->getKey())
            ->whereIn('id_rol', EmploymentRoles::employeeRoleIds())
            ->orderBy('id_usuario_sede');
        if ($lock) {
            $assignmentQuery->lockForUpdate();
        }
        $assignment = $assignmentQuery->first();

        abort_unless($assignment, 403);

        return [$user, $assignment];
    }

    private function lockedEmployeeRole(int $roleId): Role
    {
        $role = Role::query()->whereKey($roleId)->lockForUpdate()->first();

        if (! $role || ! EmploymentRoles::isEmployeeRole($role->nombre)) {
            throw ValidationException::withMessages([
                'id_rol' => 'El rol seleccionado no está permitido para un empleado.',
            ]);
        }

        return $role;
    }

    private function employeeName(array $data): string
    {
        $name = implode(' ', array_filter([
            $data['nombre'],
            $data['apellido_p'],
            $data['apellido_m'] ?? null,
        ]));

        return Str::limit($name, 150, '');
    }

    private function singleLine(mixed $value): mixed
    {
        return is_string($value) ? Str::squish(strip_tags($value)) : $value;
    }
}
