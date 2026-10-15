<?php

namespace App\Http\Controllers;

use App\Mail\TemporaryPasswordMail;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
        return view('auth.register');
    }

    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    public function sendTemporaryPassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'correo' => ['required', 'email'],
        ], [
            'correo.required' => 'El correo es obligatorio.',
            'correo.email' => 'Ingresa un correo válido.',
        ]);

        if (config('mail.default') === 'log') {
            return back()->withInput()->withErrors([
                'correo' => 'No fue posible enviar el correo en este momento. Inténtalo nuevamente más tarde.',
            ]);
        }

        $user = User::query()->where('correo', $data['correo'])->first();
        $genericMessage = 'Si el correo está registrado, recibirás una contraseña temporal.';

        if (! $user) {
            return back()->with('status', $genericMessage);
        }

        $temporaryPassword = $this->generateTemporaryPassword();

        try {
            DB::transaction(function () use ($user, $temporaryPassword): void {
                DB::table('usuarios')
                    ->where('id_usuario', $user->getKey())
                    ->update(['password' => Hash::make($temporaryPassword)]);

                Mail::to($user->correo)->send(new TemporaryPasswordMail($user->name, $temporaryPassword));
            });
        } catch (\Throwable $exception) {
            Log::error('No fue posible enviar el correo de recuperación.', [
                'user_id' => $user->getKey(),
                'exception_type' => $exception::class,
            ]);

            return back()->withInput()->withErrors([
                'correo' => 'No fue posible enviar el correo en este momento. Inténtalo nuevamente más tarde.',
            ]);
        } finally {
            unset($temporaryPassword);
        }

        return back()->with('status', $genericMessage);
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'apellido_p' => ['required', 'string', 'max:100'],
            'apellido_m' => ['nullable', 'string', 'max:100'],
            'correo' => ['required', 'email', 'max:150', Rule::unique('usuarios', 'correo')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'nombre.required' => 'Ingresa el nombre.',
            'apellido_p.required' => 'Ingresa el apellido paterno.',
            'correo.required' => 'Ingresa el correo electrónico.',
            'correo.email' => 'Ingresa un correo electrónico válido.',
            'correo.unique' => 'El correo ya está registrado.',
            'password.required' => 'Ingresa una contraseña.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        try {
            User::create([
                'nombre' => $data['nombre'],
                'apellido_p' => $data['apellido_p'],
                'apellido_m' => $data['apellido_m'] ?? null,
                'correo' => $data['correo'],
                'password' => Hash::make($data['password']),
            ]);
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

    private function generateTemporaryPassword(): string
    {
        $groups = [
            'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'abcdefghijklmnopqrstuvwxyz',
            '0123456789',
            '!@#$%&*+-_?',
        ];
        $allCharacters = implode('', $groups);
        $characters = array_map(
            fn (string $group) => $group[random_int(0, strlen($group) - 1)],
            $groups,
        );

        while (count($characters) < 12) {
            $characters[] = $allCharacters[random_int(0, strlen($allCharacters) - 1)];
        }

        for ($index = count($characters) - 1; $index > 0; $index--) {
            $swapIndex = random_int(0, $index);
            [$characters[$index], $characters[$swapIndex]] = [$characters[$swapIndex], $characters[$index]];
        }

        return implode('', $characters);
    }
}
