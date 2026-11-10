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
            'cash.index' => ['Caja', 'Gestiona cobros, aperturas y cierres de caja.'],
            'kitchen.index' => ['Operación de cocina', 'Coordina la preparación y entrega de alimentos.'],
            'reports.index' => ['Reportes', 'Analiza indicadores y resultados por sede.'],
        ];
        $routeName = (string) $request->route()->getName();
        abort_unless(isset($modules[$routeName]), 404);

        $site = $siteContext->currentFor($request, $request->user());
        abort_unless($site && ($request->user()->canAdminister() || $request->user()->sites()->whereKey($site->id_sede)->exists()), 403);

        if ($routeName === 'tables.index') {
            $zones = DB::table('zonas')->where('id_sede', $site->id_sede)->orderBy('nombre')->get();
            $tables = DB::table('mesas as m')->join('zonas as z', 'z.id_zona', '=', 'm.id_zona')
                ->where('z.id_sede', $site->id_sede)->select('m.*', 'z.nombre as zona')
                ->orderBy('z.nombre')->orderBy('m.numero')->get();
            $assignments = DB::table('zona_meseros as zm')->join('zonas as z', 'z.id_zona', '=', 'zm.id_zona')
                ->join('usuarios as u', 'u.id_usuario', '=', 'zm.id_usuario_mesero')
                ->where('z.id_sede', $site->id_sede)->where('zm.activo', 1)
                ->select('zm.id_zona', 'u.id_usuario', 'u.nombre', 'u.apellido_p')->get();
            return view('mesero.tables', compact('site', 'zones', 'tables', 'assignments'));
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

        if ($routeName === 'reports.index') {
            $orders = Schema::hasTable('pedidos')
                ? DB::table('pedidos')->where('id_sede', $site->id_sede)->get(['id_pedido', 'estado', 'fecha_pedido'])
                : collect();
            $incidents = Schema::hasTable('incidencias')
                ? DB::table('incidencias')->where('id_sede', $site->id_sede)->get(['estado'])
                : collect();

            return view('modules.reports', [
                'site' => $site,
                'orders' => $orders,
                'incidents' => $incidents,
                'ordersByState' => $orders->groupBy(fn ($order) => $order->estado ?: 'Sin estado')->map->count(),
                'openIncidents' => $incidents->filter(fn ($incident) => ($incident->estado ?? '') !== 'Cerrada')->count(),
            ]);
        }

        return view('modules.placeholder', [
            'title' => $modules[$routeName][0],
            'description' => $modules[$routeName][1],
        ]);
    }
}
