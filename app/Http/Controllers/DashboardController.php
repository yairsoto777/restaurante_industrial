<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = request()->user();
        $stats = $user->isAdministrator()
            ? [
                'Usuarios' => User::count(),
                'Sedes' => Site::count(),
                'Gerentes' => User::whereHas('roles', fn ($query) => $query->where('roles.nombre', 'Gerente'))->count(),
            ]
            : ['Sedes asignadas' => $user->sites()->count(), 'Módulos disponibles' => 6, 'Incidencias abiertas' => 0];

        return view('dashboard', compact('stats'));
    }
}
