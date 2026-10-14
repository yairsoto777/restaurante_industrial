<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'correo' => ['required', 'email'],
            'password' => ['required'],
        ], [
            'correo.required' => 'Ingresa tu correo electrónico.',
            'correo.email' => 'Ingresa un correo electrónico válido.',
            'password.required' => 'Ingresa tu contraseña.',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['correo' => 'El correo o la contraseña no son correctos.'])->onlyInput('correo');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function showRegister(): View
    {
        return view('auth.register', [
            'roles' => Role::query()->whereIn('nombre', ['Administrador', 'Gerente'])->orderBy('nombre')->get(),
            'sites' => Site::query()->orderBy('nombre')->get(),
            'hasStatusColumn' => false,
        ]);
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'apellido_p' => ['required', 'string', 'max:100'],
            'apellido_m' => ['nullable', 'string', 'max:100'],
            'correo' => ['required', 'email', 'max:150', Rule::unique('usuarios', 'correo')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'id_rol' => [
                'required',
                Rule::exists('roles', 'id_rol')->where(fn ($query) => $query->whereIn('nombre', ['Administrador', 'Gerente'])),
            ],
            'id_sede' => ['required', Rule::exists('sedes', 'id_sede')],
        ], [
            'nombre.required' => 'Ingresa el nombre.',
            'apellido_p.required' => 'Ingresa el apellido paterno.',
            'correo.required' => 'Ingresa el correo electrónico.',
            'correo.email' => 'Ingresa un correo electrónico válido.',
            'correo.unique' => 'El correo ya está registrado.',
            'password.required' => 'Ingresa una contraseña.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'id_rol.required' => 'Selecciona un rol.',
            'id_rol.exists' => 'Selecciona un rol válido.',
            'id_sede.required' => 'Selecciona una sede.',
            'id_sede.exists' => 'Selecciona una sede válida.',
        ]);

        try {
            DB::transaction(function () use ($data): void {
                $user = User::create([
                    'nombre' => $data['nombre'],
                    'apellido_p' => $data['apellido_p'],
                    'apellido_m' => $data['apellido_m'] ?? null,
                    'correo' => $data['correo'],
                    'password' => Hash::make($data['password']),
                ]);

                DB::table('usuario_sede')->insert([
                    'id_usuario' => $user->getKey(),
                    'id_sede' => $data['id_sede'],
                    'id_rol' => $data['id_rol'],
                ]);
            });
        } catch (QueryException) {
            return back()->withInput($request->except(['password', 'password_confirmation']))
                ->withErrors(['registro' => 'No fue posible crear la cuenta. Verifica la configuración de la tabla usuarios.']);
        }

        return redirect()->route('login')->with('status', 'Usuario registrado correctamente. Ya puedes iniciar sesión.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
