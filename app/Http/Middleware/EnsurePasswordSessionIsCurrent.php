<?php

namespace App\Http\Middleware;

use App\Support\PasswordSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordSessionIsCurrent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        // Limit credential rotation checks to accounts using the existing activation lifecycle.
        if ($user && ($user->temporary_password_expires_at !== null || $user->temporary_password_used_at !== null)
            && !PasswordSession::valid($request, $user)) {
            Auth::guard()->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->withErrors(['correo'=>'El acceso de esta cuenta cambió. Inicia sesión nuevamente.']);
        }

        if ($user?->mustChangePassword()) {
            if ($user->temporary_password_expires_at->isPast()) {
                Auth::guard()->logoutCurrentDevice();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return redirect()->route('login')->withErrors(['correo'=>'La contraseña temporal expiró. Solicita una nueva activación.']);
            }
            if (!$request->routeIs('password.change', 'password.change.store', 'logout')) {
                return redirect()->route('password.change');
            }
        }

        return $next($request);
    }
}
