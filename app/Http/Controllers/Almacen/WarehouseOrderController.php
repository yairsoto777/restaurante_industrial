<?php

namespace App\Http\Controllers\Almacen;

use App\Http\Controllers\Controller;
use App\Support\EmploymentRoles;
use App\Support\SiteContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WarehouseOrderController extends Controller
{
    private function site(Request $request, SiteContext $context)
    {
        $user = $request->user();
        $site = $context->currentFor($request, $user);
        abort_unless($site, 403);

        $role = $user->roles()->get()->contains(fn ($role) =>
            (int) $role->pivot->id_sede === (int) $site->id_sede &&
            EmploymentRoles::matches($role->nombre, 'Jefe de almacen')
        );

        abort_unless(
            $role || $user->isAdministrator() ||
            ($user->isManagerAtSite((int) $site->id_sede)) ||
            ($user->canAdminister() && $user->sites()->whereKey($site->id_sede)->exists()),
            403
        );

        return $site;
    }

    private function assertWarehouse(Request $request, int $siteId): void
    {
        abort_unless($request->user()->roles()->get()->contains(fn ($role) =>
            (int) $role->pivot->id_sede === $siteId &&
            EmploymentRoles::matches($role->nombre, 'Jefe de almacen')
        ), 403);
    }

    private function query(int $siteId)
    {
        return DB::table('pedidos as p')
            ->leftJoin('proveedores as pr', 'pr.id_proveedor', '=', 'p.id_proveedor')
            ->where('p.id_sede', $siteId)
            ->select('p.*', 'pr.nombre as proveedor_nombre');
    }

    public function index(Request $request, SiteContext $context): View
    {
        $site = $this->site($request, $context);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'estado' => ['nullable', 'string', 'max:30'],
        ]);
        $q = trim($filters['q'] ?? '');
        $states = DB::table('pedidos')->where('id_sede', $site->id_sede)
            ->whereNotNull('estado')->distinct()->orderBy('estado')->pluck('estado');
        $orders = $this->query((int) $site->id_sede)
            ->when($q !== '', fn ($builder) => $builder->where(function ($builder) use ($q) {
                $builder->where('pr.nombre', 'like', '%'.$q.'%');
                if (ctype_digit($q)) {
                    $builder->orWhere('p.id_pedido', (int) $q);
                }
            }))
            ->when($filters['estado'] ?? null, fn ($builder, $status) => $builder->where('p.estado', $status))
            ->orderByDesc('p.id_pedido')->paginate(20)->withQueryString();
        $stats = [
            'total' => DB::table('pedidos')->where('id_sede', $site->id_sede)->count(),
            'programados' => DB::table('pedidos')->where('id_sede', $site->id_sede)->where('estado', 'Programado')->count(),
        ];
        $canCreate = $request->user()->roles()->get()->contains(fn ($role) =>
            (int) $role->pivot->id_sede === (int) $site->id_sede &&
            EmploymentRoles::matches($role->nombre, 'Jefe de almacen')
        );

        return view('almacen.pedidos.index', compact('site', 'orders', 'states', 'filters', 'q', 'stats', 'canCreate'));
    }

    public function create(Request $request, SiteContext $context): View
    {
        $site = $this->site($request, $context);
        $this->assertWarehouse($request, (int) $site->id_sede);

        $suppliers = DB::table('proveedores')->orderBy('nombre')->get(['id_proveedor', 'nombre']);
        $ingredients = DB::table('insumo_sedes as s')
            ->join('insumos as i', 'i.id_insumo', '=', 's.id_insumo')
            ->leftJoin('unidades as u', 'u.id_unidad', '=', 'i.id_unidad_base')
            ->where('s.id_sede', $site->id_sede)
            ->select('i.id_insumo', 'i.nombre', 'u.codigo as unidad_codigo', 'u.nombre as unidad_nombre', 'u.permite_fracciones')
            ->orderBy('i.nombre')->distinct()->get();

        // Solo se incluyen productos asignados a la sede con stock definido.
        // Se considera agotado si stock <= 0 y bajo si stock <= mínimo configurado.
        // No se inventan categorías: si la columna no existe, se muestran sin categoría.
        $hasCategory = Schema::hasColumn('insumos', 'categoria');
        $lowStockQuery = DB::table('insumo_sedes as s')
            ->join('insumos as i', 'i.id_insumo', '=', 's.id_insumo')
            ->leftJoin('unidades as u', 'u.id_unidad', '=', 'i.id_unidad_base')
            ->where('s.id_sede', $site->id_sede)
            ->whereNotNull('s.stock')
            ->where(function ($query): void {
                $query->where('s.stock', '<=', 0)
                    ->orWhere(function ($query): void {
                        $query->whereNotNull('s.punto_de_reorden')
                            ->whereColumn('s.stock', '<=', 's.punto_de_reorden');
                    });
            })
            ->orderBy('i.nombre');

        $fields = [
            'i.id_insumo', 'i.nombre', 's.stock', 's.punto_de_reorden',
            'u.codigo as unidad_codigo', 'u.nombre as unidad_nombre',
        ];
        $fields[] = $hasCategory ? 'i.categoria as categoria' : DB::raw('NULL as categoria');
        $lowStockItems = $lowStockQuery->get($fields);

        return view('almacen.pedidos.create', compact('site', 'suppliers', 'ingredients', 'lowStockItems'));
    }

    public function store(Request $request, SiteContext $context): RedirectResponse
    {
        $site = $this->site($request, $context);
        $this->assertWarehouse($request, (int) $site->id_sede);
        $data = $request->validate([
            'id_proveedor' => ['required', 'integer', 'exists:proveedores,id_proveedor'],
            'fecha_entrega' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'observaciones' => ['nullable', 'string', 'max:3000'],
            'detalles' => ['required', 'array', 'min:1', 'max:50'],
            'detalles.*.id_insumo' => ['required', 'integer', 'distinct', 'exists:insumos,id_insumo'],
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:999999999.999', 'decimal:0,3'],
            'detalles.*.especificaciones' => ['nullable', 'string', 'max:1000'],
        ]);

        $ids = array_map(fn ($row) => (int) $row['id_insumo'], $data['detalles']);
        $allowed = DB::table('insumo_sedes as s')
            ->join('insumos as i', 'i.id_insumo', '=', 's.id_insumo')
            ->join('unidades as u', 'u.id_unidad', '=', 'i.id_unidad_base')
            ->where('s.id_sede', $site->id_sede)->whereIn('s.id_insumo', $ids)
            ->get(['s.id_insumo', 'u.permite_fracciones'])->keyBy('id_insumo');

        foreach ($data['detalles'] as $i => $row) {
            $id = (int) $row['id_insumo'];
            if (! $allowed->has($id)) {
                throw ValidationException::withMessages([
                    "detalles.$i.id_insumo" => 'El insumo no está asignado a esta sede o carece de unidad válida.',
                ]);
            }
            if (! (bool) $allowed[$id]->permite_fracciones && floor((float) $row['cantidad']) != (float) $row['cantidad']) {
                throw ValidationException::withMessages([
                    "detalles.$i.cantidad" => 'Esta unidad requiere una cantidad entera.',
                ]);
            }
        }

        $orderId = DB::transaction(function () use ($data, $site, $request): int {
            $orderId = (int) DB::table('pedidos')->insertGetId([
                'id_sede' => $site->id_sede,
                'id_proveedor' => (int) $data['id_proveedor'],
                'id_usuario' => $request->user()->id_usuario,
                'fecha_pedido' => now('America/Mexico_City')->toDateString(),
                'fecha_entrega' => $data['fecha_entrega'],
                'estado' => 'Programado',
                'observaciones' => $data['observaciones'] ?? null,
            ], 'id_pedido');
            foreach ($data['detalles'] as $detail) {
                DB::table('detalle_pedidos')->insert([
                    'id_pedido' => $orderId,
                    'id_insumo' => (int) $detail['id_insumo'],
                    'cantidad' => number_format((float) $detail['cantidad'], 3, '.', ''),
                    'especificaciones' => $detail['especificaciones'] ?? null,
                ]);
            }
            return $orderId;
        }, 3);

        return redirect()->route('orders.show', $orderId)
            ->with('status', 'Pedido #'.$orderId.' registrado correctamente. No se han aumentado existencias.');
    }

    public function show(Request $request, int $order, SiteContext $context): View
    {
        $site = $this->site($request, $context);
        $order = $this->query((int) $site->id_sede)->where('p.id_pedido', $order)->first();
        abort_unless($order, 404);
        $details = DB::table('detalle_pedidos as d')
            ->leftJoin('insumos as i', 'i.id_insumo', '=', 'd.id_insumo')
            ->leftJoin('unidades as u', 'u.id_unidad', '=', 'i.id_unidad_base')
            ->where('d.id_pedido', $order->id_pedido)->orderBy('d.id_detalle_pedido')
            ->get(['d.id_detalle_pedido', 'i.nombre as insumo', 'd.cantidad', 'd.especificaciones', 'u.codigo as unidad']);
        $receipts = DB::table('recepciones')->where('id_pedido', $order->id_pedido)
            ->orderByDesc('id_recepcion')->get(['id_recepcion', 'fecha_recepcion', 'estado']);

        return view('almacen.pedidos.show', compact('site', 'order', 'details', 'receipts'));
    }
}
