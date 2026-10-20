<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class OperationalModuleController extends Controller
{
    public function show(Request $request): View
    {
        $modules = [
            'receptions.index' => ['Recepción de insumos', 'Consulta y registra la recepción de pedidos e insumos.'],
            'inventory.index' => ['Inventario de insumos', 'Controla existencias, lotes y movimientos de inventario.'],
            'services.index' => ['Gestión de servicios', 'Programa y administra los servicios de alimentación.'],
            'services.live' => ['Servicio en vivo', 'Supervisa el avance de los servicios activos.'],
            'tables.index' => ['Mesas y zonas', 'Organiza zonas, mesas y su capacidad operativa.'],
            'groups.index' => ['Grupos y entregas', 'Gestiona grupos de comensales y entregas realizadas.'],
            'menus.index' => ['Menú por servicio', 'Define el menú disponible para cada servicio.'],
            'dishes.index' => ['Platillos', 'Administra el catálogo general de platillos.'],
            'incidents.index' => ['Incidencias', 'Consulta y da seguimiento a incidencias operativas.'],
            'orders.index' => ['Pedidos', 'Consulta y administra pedidos de la sede.'],
            'requisitions.index' => ['Requisiciones', 'Registra y da seguimiento a requisiciones internas.'],
            'cash.index' => ['Caja', 'Gestiona cobros, aperturas y cierres de caja.'],
            'kitchen.index' => ['Operación de cocina', 'Coordina la preparación y entrega de alimentos.'],
            'reports.index' => ['Reportes', 'Analiza indicadores y resultados por sede.'],
        ];
        $routeName = (string) $request->route()->getName();
        abort_unless(isset($modules[$routeName]), 404);

        return view('modules.placeholder', [
            'title' => $modules[$routeName][0],
            'description' => $modules[$routeName][1],
        ]);
    }
}
