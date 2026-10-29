<?php

namespace App\Http\Controllers;

use App\Support\EmploymentRoles;
use App\Support\SiteContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReceptionOrderController extends Controller
{
    private function site(Request $request, SiteContext $context)
    {
        $user = $request->user();
        $site = $context->currentFor($request, $user);
        abort_unless($site, 403);
        $allowed = $user->isAdministrator() || $user->roles()->get()->contains(
            fn ($role) => (int) $role->pivot->id_sede === (int) $site->id_sede
                && collect(['Recepcion de insumos', 'Jefe de almacen', 'Gerente de sede'])->contains(
                    fn ($name) => EmploymentRoles::matches($role->nombre, $name)
                )
        );
        abort_unless($allowed, 403);

        return $site;
    }

    private function orders(int $siteId)
    {
        return DB::table('pedidos as p')->leftJoin('proveedores as proveedor', 'proveedor.id_proveedor', '=', 'p.id_proveedor')
            ->where('p.id_sede', $siteId)->select('p.*', 'proveedor.nombre as proveedor_nombre');
    }

    public function index(Request $request, SiteContext $context): View
    {
        $site = $this->site($request, $context);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'fecha_entrega' => ['nullable', 'date_format:Y-m-d'],
            'estado' => ['nullable', 'string', 'max:30'],
        ]);
        $search = trim($filters['q'] ?? '');
        $states = DB::table('pedidos')->where('id_sede', $site->id_sede)->whereNotNull('estado')->distinct()->orderBy('estado')->pluck('estado');
        // Sin catálogo de estados confirmado: mostrar todos, sin ocultar pedidos por supuestos.
        $orders = $this->orders($site->id_sede)
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('proveedor.nombre', 'like', '%'.$search.'%');
                if (ctype_digit($search)) {
                    $query->orWhere('p.id_pedido', $search);
                }
            }))
            ->when($filters['fecha_entrega'] ?? null, fn ($query, $date) => $query->where('p.fecha_entrega', $date))
            ->when($filters['estado'] ?? null, fn ($query, $state) => $query->where('p.estado', $state))
            ->orderBy('p.fecha_entrega')->orderByDesc('p.id_pedido')->paginate(20)->withQueryString();

        return view('receptions.orders', compact('site', 'orders', 'states', 'filters', 'search'));
    }

    public function show(Request $request, int $order, SiteContext $context): View
    {
        $site = $this->site($request, $context);
        $order = $this->orders($site->id_sede)->where('p.id_pedido', $order)->first();
        abort_unless($order, 404);
        $details = DB::table('detalle_pedidos as d')->leftJoin('insumos as i', 'i.id_insumo', '=', 'd.id_insumo')
            ->where('d.id_pedido', $order->id_pedido)->orderBy('d.id_detalle_pedido')
            ->get(['d.cantidad', 'd.especificaciones', 'i.nombre as insumo_nombre']);

        $receipts = config('receptions.capture_enabled') && \Illuminate\Support\Facades\Schema::hasTable('recepciones')
            ? DB::table('recepciones')->where('id_pedido', $order->id_pedido)->orderByDesc('id_recepcion')->get()
            : collect();

        return view('receptions.order', compact('site', 'order', 'details', 'receipts'));
    }
}
