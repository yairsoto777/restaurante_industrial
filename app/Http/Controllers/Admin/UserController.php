<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
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
        return view('admin.users.index', ['users' => User::with(['sites', 'roles'])->orderBy('nombre')->paginate(15)]);
    }

    public function create(): View
    {
        return view('admin.users.form', ['user' => new User, 'sites' => Site::orderBy('nombre')->get(), 'roles' => Role::whereIn('nombre', ['Administrador', 'Gerente'])->orderBy('nombre')->get()]);
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
        return view('admin.users.form', ['user' => $user->load(['sites', 'roles']), 'sites' => Site::orderBy('nombre')->get(), 'roles' => Role::whereIn('nombre', ['Administrador', 'Gerente'])->orderBy('nombre')->get()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);
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

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'apellido_p' => ['required', 'string', 'max:100'],
            'apellido_m' => ['nullable', 'string', 'max:100'],
            'correo' => ['required', 'email', 'max:150', Rule::unique('usuarios', 'correo')->ignore($user?->id_usuario, 'id_usuario')],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'id_rol' => ['required', 'exists:roles,id_rol'],
            'sites' => ['required', 'array', 'min:1'],
            'sites.*' => ['integer', 'exists:sedes,id_sede'],
        ]);
    }

    private function syncAssignments(User $user, array $siteIds, int $roleId): void
    {
        DB::table('usuario_sede')->where('id_usuario', $user->id_usuario)->delete();
        DB::table('usuario_sede')->insert(collect($siteIds)->unique()->map(fn ($siteId) => ['id_usuario' => $user->id_usuario, 'id_sede' => $siteId, 'id_rol' => $roleId])->all());
    }
}
