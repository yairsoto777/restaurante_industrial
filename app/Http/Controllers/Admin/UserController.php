<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', ['users' => User::with('sites')->latest()->paginate(15)]);
    }

    public function create(): View
    {
        return view('admin.users.form', ['user' => new User, 'sites' => Site::orderBy('name')->get(), 'roles' => UserRole::cases()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $siteIds = $data['sites'] ?? [];
        unset($data['sites']);
        $user = User::create($data);
        $user->sites()->sync($siteIds);

        return redirect()->route('admin.users.index')->with('status', 'Usuario creado correctamente.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', ['user' => $user->load('sites'), 'sites' => Site::orderBy('name')->get(), 'roles' => UserRole::cases()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);
        $siteIds = $data['sites'] ?? [];
        unset($data['sites']);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $user->update($data);
        $user->sites()->sync($siteIds);

        return redirect()->route('admin.users.index')->with('status', 'Usuario actualizado correctamente.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'active' => ['required', 'boolean'],
            'sites' => ['nullable', 'array'],
            'sites.*' => ['integer', 'exists:sites,id'],
        ]);
    }
}
