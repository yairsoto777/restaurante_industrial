<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

final class ActiveRole
{
    public function assignments(User $user)
    {
        return $user->roles()->join('sedes as assignment_site', 'assignment_site.id_sede', '=', 'usuario_sede.id_sede')
            ->select('roles.*', 'assignment_site.nombre as assignment_site_name')->get()
            ->unique(fn ($role) => $role->getKey().':'.$role->pivot->id_sede)->values();
    }

    public function apply(Request $request): void
    {
        $user = $request->user();
        if (! $user) return;
        $user->activeRoleId = $user->activeSiteId = null;
        $site = app(SiteContext::class)->currentFor($request, $user);
        $roles = $user->roles()->where('usuario_sede.id_sede', $site?->getKey())->get();
        // Global administrators retain their existing cross-site access.
        $global = $user->roles()->get()->first(fn ($role) =>
            EmploymentRoles::isSuperAdministrator($role->nombre) || EmploymentRoles::matches($role->nombre, 'Administrador'));
        $selected = $roles->firstWhere('id_rol', (int) $request->session()->get('active_role_id'));
        if (! $selected && $global && (int) $global->getKey() === (int) $request->session()->get('active_role_id')) $selected = $global;
        $selected ??= $roles->first() ?? $global;
        if ($selected) {
            $user->activeRoleId = (int) $selected->getKey();
            $user->activeSiteId = (int) $selected->pivot->id_sede;
            $request->session()->put('active_role_id', $user->activeRoleId);
        } else {
            $request->session()->forget('active_role_id');
        }
    }
}
