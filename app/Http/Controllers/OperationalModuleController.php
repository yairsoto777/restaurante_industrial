<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Support\SiteContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class OperationalModuleController extends Controller
{
    public function show(Request $request, SiteContext $siteContext): View
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

        $site = $siteContext->currentFor($request, $request->user());
        abort_unless($site && ($request->user()->canAdminister() || $request->user()->sites()->whereKey($site->id_sede)->exists()), 403);

        if ($routeName === 'inventory.index') {
            $table = collect(['insumo_sedes', 'inventarios_finales', 'inventarios', 'existencias'])->first(fn ($name) => Schema::hasTable($name));
            $rows = $table ? DB::table($table)->where('id_sede', $site->id_sede)->limit(200)->get() : collect();
            return view('modules.inventory', compact('site', 'rows', 'table'));
        }
        if ($routeName === 'incidents.index') {
            $incidents = Schema::hasTable('incidencias') ? DB::table('incidencias')->where('id_sede', $site->id_sede)->orderByDesc('fecha')->limit(100)->get() : collect();
            return view('modules.incidents', compact('site', 'incidents'));
        }

        if ($routeName === 'groups.index') {
            $groups = collect();
            if (Schema::hasTable('grupos') && Schema::hasTable('servicios')) {
                $groups = DB::table('grupos as g')->join('servicios as s', 's.id_servicio', '=', 'g.id_servicio')
                    ->where('s.id_sede', $site->id_sede)->where('s.estado_aprobacion', 'Aprobado')
                    ->select('g.*', 's.nombre as servicio_nombre', 's.fecha as servicio_fecha')->orderBy('s.fecha')->orderBy('g.inicio_hora')->get();
            }
            return view('modules.groups', compact('site', 'groups'));
        }

        return view('modules.placeholder', [
            'title' => $modules[$routeName][0],
            'description' => $modules[$routeName][1],
        ]);
    }
}
