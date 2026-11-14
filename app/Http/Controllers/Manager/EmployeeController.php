<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Support\EmploymentRoles;
use App\Support\EmployeePresentation;
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

        $roleFilter = $request->integer('rol') ?: null;
        $search = trim((string) $request->input('buscar', ''));
        $employeesQuery = Employee::query()
            ->with([
                'user.roles' => fn ($query) => $query
                    ->where('usuario_sede.id_sede', $siteId)
                    ->whereIn('roles.id_rol', $roleIds)->orderBy('usuario_sede.id_usuario_sede'),
            ])
            ->where('id_sede', $siteId)
            ->whereNotNull('id_usuario')
            ->when($roleFilter, fn ($query) => $query->whereHas('user.roles', fn ($roleQuery) => $roleQuery
                ->where('usuario_sede.id_sede', $siteId)->where('roles.id_rol', $roleFilter)))
            ->when($search !== '', fn ($query) => $query->whereHas('user', fn ($userQuery) => $userQuery
                ->where(function ($searchQuery) use ($search): void {
                    $term = "%{$search}%";
                    $searchQuery->where('nombre', 'like', $term)->orWhere('apellido_p', 'like', $term)
                        ->orWhere('apellido_m', 'like', $term)->orWhere('correo', 'like', $term);
                })))
            ->orderBy('nombre');
        $employees = $employeesQuery->paginate(15)->withQueryString();
        $presentation = app(EmployeePresentation::class);
        $employeeProfiles = $employees->getCollection()->mapWithKeys(fn ($employee) => [
            $employee->getKey() => $presentation->profile($employee, $employee->user?->roles->pluck('id_rol')->all() ?? []),
        ]);

        $roleIds = EmploymentRoles::employeeRoleIds();
        $roleCounts = DB::table('usuario_sede as us')
            ->join('roles as r', 'r.id_rol', '=', 'us.id_rol')
            ->join('empleados as e', 'e.id_usuario', '=', 'us.id_usuario')
            ->where('e.id_sede', $siteId)
            ->where('us.id_sede', $siteId)->whereIn('us.id_rol', $roleIds)
            ->select('r.id_rol', 'r.nombre')->selectRaw('COUNT(DISTINCT e.id_empleado) as total')
            ->groupBy('r.id_rol', 'r.nombre')->get()->keyBy('id_rol');
        $totalEmployees = (int) Employee::query()->where('id_sede', $siteId)->whereNotNull('id_usuario')->distinct()->count('id_usuario');
        $roles = EmploymentRoles::employeeRoles()->map(fn (Role $role): array => [
            'id' => (int) $role->getKey(), 'name' => EmploymentRoles::displayName($role->nombre),
            'count' => (int) ($roleCounts[$role->getKey()]->total ?? 0),
        ]);

        $assignments = DB::table('usuario_sede')->where('id_sede', $siteId)->whereIn('id_rol', $roleIds)->get();
        $assignmentCount = $assignments->count();
        $multiRoleCount = $assignments->groupBy('id_usuario')->filter(fn ($rows) => $rows->pluck('id_rol')->unique()->count() > 1)->count();
        $editingEmployee = null;
        $editingUser = new User;
        $selectedRoleIds = [];
        if ($request->filled('editar')) {
            $editingEmployee = Employee::query()->findOrFail($request->integer('editar'));
            [$editingUser] = $this->authorizedEmployee($request->user(), $editingEmployee, $currentSite);
            $selectedRoleIds = $editingUser->roles()->where('usuario_sede.id_sede', $siteId)->orderBy('usuario_sede.id_usuario_sede')->pluck('roles.id_rol')->all();
        }
        $employeeProfile = $presentation->profile($editingEmployee ?? new Employee, $selectedRoleIds);
        return view('manager.employees.index', compact('employees', 'currentSite', 'roles', 'totalEmployees', 'roleFilter', 'search', 'assignmentCount', 'multiRoleCount', 'editingEmployee', 'editingUser', 'selectedRoleIds', 'employeeProfiles', 'employeeProfile'));
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
            'selectedRoleIds' => [],
            'employeeProfile' => app(EmployeePresentation::class)->profile(new Employee),
        ]);
    }

    public function store(Request $request, SiteContext $siteContext): RedirectResponse
    {
        $currentSite = $this->currentManagedSite($request, $siteContext);
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $currentSite, $request): void {
            $this->ensureManagerStillManages($request->user(), (int) $currentSite->getKey());
            $roleIds = $data['role_ids'] ?? [(int) $data['id_rol']];
            foreach ($roleIds as $roleId) $this->lockedEmployeeRole((int) $roleId);
            $user = User::create([
                'nombre' => $data['nombre'],
                'apellido_p' => $data['apellido_p'],
                'apellido_m' => $data['apellido_m'] ?? null,
                'correo' => $data['correo'],
                'password' => $data['password'],
            ]);

            $this->syncRoles($user, $currentSite, $roleIds);

            $employee = Employee::create([
                'nombre' => $this->employeeName($data),
                'id_sede' => (int) $currentSite->getKey(),
                'id_puesto' => $data['id_puesto'] ?? null,
                'id_usuario' => (int) $user->getKey(),
            ]);
            app(EmployeePresentation::class)->save($employee, isset($data['primary_role_id']) ? (int) $data['primary_role_id'] : null, $request->file('foto'));
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

        return view('manager.employees.form', [
            'employee' => $employee,
            'user' => $user,
            'roles' => EmploymentRoles::employeeRoles(),
            'currentSite' => $currentSite,
            'selectedRoleId' => $assignment ? (int) $assignment->id_rol : null,
            'selectedRoleIds' => $user->roles()->where('usuario_sede.id_sede', $currentSite->getKey())->pluck('roles.id_rol')->all(),
            'employeeProfile' => app(EmployeePresentation::class)->profile($employee, $user->roles()->where('usuario_sede.id_sede', $currentSite->getKey())->orderBy('usuario_sede.id_usuario_sede')->pluck('roles.id_rol')->all()),
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
            $roleIds = $data['role_ids'] ?? [(int) $data['id_rol']];
            foreach ($roleIds as $roleId) $this->lockedEmployeeRole((int) $roleId);
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
                'id_puesto' => array_key_exists('id_puesto', $data) ? $data['id_puesto'] : $lockedEmployee->id_puesto,
            ]);
            $this->syncRoles($lockedUser, $currentSite, $roleIds);
            app(EmployeePresentation::class)->save($lockedEmployee, isset($data['primary_role_id']) ? (int) $data['primary_role_id'] : null, $request->file('foto'));
        }, 3);

        return redirect()->route('manager.employees.index')
            ->with('status', 'Empleado actualizado correctamente.');
    }

    public function destroy(
        Request $request,
        Employee $employee,
        SiteContext $siteContext,
    ): RedirectResponse {
        $currentSite = $this->currentManagedSite($request, $siteContext);

        DB::transaction(function () use ($request, $employee, $currentSite): void {
            $lockedEmployee = Employee::query()
                ->whereKey($employee->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            [$user] = $this->authorizedEmployee(
                $request->user(),
                $lockedEmployee,
                $currentSite,
                true,
            );

            $this->ensureManagerStillManages($request->user(), (int) $currentSite->getKey());
            $query = DB::table('usuario_sede')->where('id_usuario', $user->getKey())
                ->where('id_sede', $currentSite->getKey())->whereIn('id_rol', EmploymentRoles::employeeRoleIds());
            if ($request->filled('id_rol')) {
                $roleId = $request->validate(['id_rol' => ['required', 'integer', Rule::in(EmploymentRoles::employeeRoleIds())]])['id_rol'];
                $query->where('id_rol', $roleId);
            }
            $query->delete();
        }, 3);

        return redirect()->route('manager.employees.index')
            ->with('status', 'Accesos retirados de esta sede. La cuenta y su historial se conservan.');
    }

    public function photo(Request $request, Employee $employee, SiteContext $siteContext)
    {
        $site = $this->currentManagedSite($request, $siteContext);
        $this->authorizedEmployee($request->user(), $employee, $site);
        $photo = app(EmployeePresentation::class)->photoLocation($employee);
        abort_unless($photo, 404);
        // A working URL even on Windows installations without public/storage symlinks.
        return \Illuminate\Support\Facades\Storage::disk($photo['disk'])->response($photo['path'], null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function syncRoles(User $user, Site $site, array $roleIds): void
    {
        User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
        $query = DB::table('usuario_sede')->where('id_usuario', $user->getKey())->where('id_sede', $site->getKey());
        (clone $query)->whereIn('id_rol', EmploymentRoles::employeeRoleIds())->whereNotIn('id_rol', $roleIds)->delete();
        $existing = (clone $query)->pluck('id_rol')->map(fn ($id) => (int) $id)->all();
        foreach (array_unique(array_map('intval', $roleIds)) as $roleId) {
            if (! in_array($roleId, $existing, true)) DB::table('usuario_sede')->insert([
                'id_usuario' => $user->getKey(), 'id_sede' => $site->getKey(), 'id_rol' => $roleId,
            ]);
        }
    }

    private function validated(Request $request, ?User $user = null): array
    {
        if (! $request->filled('id_puesto') && $request->filled('puesto')) {
            $legacyPosition = Position::query()->firstOrCreate([
                'nombre' => $this->singleLine($request->input('puesto')),
            ]);
            $request->merge(['id_puesto' => $legacyPosition->getKey()]);
        }

        $request->merge([
            'nombre' => $this->singleLine($request->input('nombre')),
            'apellido_p' => $this->singleLine($request->input('apellido_p')),
            'apellido_m' => $this->singleLine($request->input('apellido_m')),
            'correo' => is_string($request->input('correo'))
                ? Str::lower(trim($request->input('correo')))
                : $request->input('correo'),
        ]);

        $employeeRoleIds = EmploymentRoles::employeeRoleIds();

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'apellido_p' => ['required', 'string', 'max:100'],
            'apellido_m' => [$user ? 'nullable' : 'required', 'string', 'max:100'],
            'correo' => [
                'required',
                'email:rfc',
                'max:150',
                Rule::unique('usuarios', 'correo')->ignore($user?->getKey(), 'id_usuario'),
            ],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'max:255'],
            'id_rol' => [
                'required_without_all:role_ids,primary_role_id',
                'integer',
                Rule::in($employeeRoleIds),
                Rule::exists('roles', 'id_rol'),
            ],
            'role_ids' => ['required_without_all:id_rol,primary_role_id', 'array', 'max:7'],
            'role_ids.*' => ['required', 'integer', 'distinct', Rule::in($employeeRoleIds)],
            'primary_role_id' => ['nullable', 'integer', Rule::in($employeeRoleIds)],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'id_puesto' => ['nullable', 'integer', Rule::exists('puestos', 'id_puesto')],
        ], [
            'correo.unique' => 'El correo ya pertenece a otro usuario.',
            'password.required' => 'Ingresa una contraseña temporal.',
            'password.min' => 'La contraseña temporal debe tener al menos 8 caracteres.',
            'apellido_m.required' => 'Ingresa el apellido materno del empleado.',
            'id_rol.in' => 'El rol seleccionado no está permitido para un empleado.',
            'primary_role_id.in' => 'Selecciona un rol principal permitido para esta sede.',
            'foto.max' => 'La foto debe pesar como máximo 2 MB.',
        ]);
        if (! empty($data['primary_role_id'])) {
            $data['role_ids'] = array_values(array_unique(array_map('intval', [$data['primary_role_id'], ...($data['role_ids'] ?? [])])));
        }
        return $data;
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
