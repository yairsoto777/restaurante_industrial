<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Support\EmploymentRoles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $roleNames = Role::query()->get()
            ->filter(fn (Role $role): bool => EmploymentRoles::isManager($role->nombre) || in_array($role->nombre, ['Administrador', 'Super Administrador', 'Superadministrador'], true))
            ->pluck('nombre');

        return view('admin.users.index', [
            'users' => User::with(['sites', 'roles'])
                ->whereHas('roles', fn ($query) => $query->whereIn('roles.nombre', $roleNames))
                ->orderBy('nombre')->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', ['user' => new User, 'sites' => Site::orderBy('nombre')->get(), 'roles' => $this->assignableRoles(request()->user())]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($data): void {
            $user = User::create([...collect($data)->except(['id_rol', 'sites', 'password'])->all(), 'password' => Hash::make($data['password'])]);
            $this->syncAssignments($user, $data['sites'], (int) $data['id_rol']);
        });

        return redirect()->route('admin.users.index')->with('status', 'Usuario creado correctamente.');
    }

    public function edit(User $user): View
    {
        $this->authorizeTarget(request()->user(), $user);

        return view('admin.users.form', ['user' => $user->load(['sites', 'roles']), 'sites' => Site::orderBy('nombre')->get(), 'roles' => $this->assignableRoles(request()->user())]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeTarget($request->user(), $user);
        $data = $this->validated($request, $user);
        $currentRoleId = $user->roles()->value('roles.id_rol');
        if ($user->isSuperAdministrator() || $request->user()->is($user)) {
            $data['id_rol'] = $currentRoleId;
        }
        DB::transaction(function () use ($data, $user): void {
            $attributes = collect($data)->except(['id_rol', 'sites', 'password'])->all();
            if (! empty($data['password'])) {
                $attributes['password'] = Hash::make($data['password']);
            }
            $user->update($attributes);
            $this->syncAssignments($user, $data['sites'], (int) $data['id_rol']);
        });

        return redirect()->route('admin.users.index')->with('status', 'Usuario actualizado correctamente.');
    }

    public function promote(Request $request, User $user): RedirectResponse
    {
        return $this->changeRole($request, $user, 'Gerente', 'Administrador', 'Usuario promovido a Administrador.');
    }

    public function demote(Request $request, User $user): RedirectResponse
    {
        return $this->changeRole($request, $user, 'Administrador', 'Gerente', 'Administrador cambiado a Gerente.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorizeTarget($request->user(), $user);
        abort_if($request->user()->is($user), 403);

        DB::transaction(function () use ($user): void {
            DB::table('usuario_sede')->where('id_usuario', $user->id_usuario)->delete();
            $user->delete();
        });

        return redirect()->route('admin.users.index')->with('status', 'Usuario eliminado correctamente.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'apellido_p' => ['required', 'string', 'max:100'],
            'apellido_m' => ['nullable', 'string', 'max:100'],
            'correo' => ['required', 'email', 'max:150', Rule::unique('usuarios', 'correo')->ignore($user?->id_usuario, 'id_usuario')],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'id_rol' => [
                'required',
                Rule::exists('roles', 'id_rol')->where(fn ($query) => $query->whereIn('nombre', $this->assignableRoleNames($request->user(), $user))),
            ],
            'sites' => ['required', 'array', 'min:1'],
            'sites.*' => ['integer', 'exists:sedes,id_sede'],
        ]);
    }

    private function syncAssignments(User $user, array $siteIds, int $roleId): void
    {
        DB::table('usuario_sede')->where('id_usuario', $user->id_usuario)->delete();
        DB::table('usuario_sede')->insert(collect($siteIds)->unique()->map(fn ($siteId) => ['id_usuario' => $user->id_usuario, 'id_sede' => $siteId, 'id_rol' => $roleId])->all());
    }

    private function changeRole(Request $request, User $user, string $from, string $to, string $message): RedirectResponse
    {
        $this->authorizeTarget($request->user(), $user);
        abort_if($request->user()->is($user) || ! $user->hasRole($from), 403);
        $role = Role::query()->where('nombre', $to)->firstOrFail();

        DB::table('usuario_sede')->where('id_usuario', $user->id_usuario)->update(['id_rol' => $role->id_rol]);

        return redirect()->route('admin.users.index')->with('status', $message);
    }

    private function authorizeTarget(User $actor, User $target): void
    {
        abort_if($target->isSuperAdministrator() && ! $actor->isSuperAdministrator(), 403);
    }

    private function assignableRoles(User $actor)
    {
        return Role::query()->get()
            ->filter(fn (Role $role): bool => EmploymentRoles::isManager($role->nombre) || $role->nombre === 'Administrador')
            ->sortBy('nombre')
            ->values();
    }

    private function assignableRoleNames(User $actor, ?User $target = null): array
    {
        return Role::query()->get()
            ->filter(fn (Role $role): bool => EmploymentRoles::isManager($role->nombre) || $role->nombre === 'Administrador')
            ->pluck('nombre')
            ->all();
    }
}
