<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user?->mustChangePassword() && $user->temporary_password_expires_at->isPast()) {
            Auth::logout();

            return redirect()->route('login')->withErrors(['correo' => 'La contraseña temporal expiró. Solicita una nueva recuperación.']);
        }

        if ($user?->mustChangePassword() && ! $request->routeIs('password.change*')) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
