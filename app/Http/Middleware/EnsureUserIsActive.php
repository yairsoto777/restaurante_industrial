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
        app(\App\Support\ActiveRole::class)->apply($request);
        $user = $request->user();
        if ($user?->isManager()) {
            $site = app(\App\Support\SiteContext::class)->currentFor($request, $user);
            abort_unless(\App\Support\ManagerRouteAccess::allows((string) $request->route()?->getName(), $request->method()), 403);
            abort_if($site && ! $user->isManagerAtSite((int) $site->getKey()), 403);
        }
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
