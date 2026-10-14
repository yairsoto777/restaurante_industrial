<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ModuleController extends Controller
{
    public function sites(): View
    {
        return view('manager.sites', ['sites' => request()->user()->sites()->orderBy('sedes.nombre')->get()]);
    }

    public function show(string $module): View
    {
        $modules = [
            'recepcion' => ['Recepción', 'Registro y seguimiento de entradas de insumos.'],
            'inventario' => ['Inventario', 'Control de existencias por sede.'],
            'servicios' => ['Servicios', 'Administración de servicios de alimentación.'],
            'menu' => ['Menú', 'Planeación de menús por sede.'],
            'incidencias' => ['Incidencias', 'Registro y atención de incidencias operativas.'],
            'reportes' => ['Reportes', 'Indicadores y resultados de las sedes asignadas.'],
        ];
        abort_unless(isset($modules[$module]), 404);

        return view('manager.module', ['module' => $module, 'title' => $modules[$module][0], 'description' => $modules[$module][1]]);
    }
}
