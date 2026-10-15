<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        $allowed = collect($roles)->contains(fn (string $role) => match ($role) {
            'super-administrator' => $user?->isSuperAdministrator(),
            'administrator' => $user?->isAdministrator(),
            'manager' => $user?->isManager(),
            'user', 'common-user' => $user?->isCommonUser(),
            default => $user?->hasRole($role),
        });

        abort_unless($user && $allowed, 403);

        return $next($request);
    }
}
