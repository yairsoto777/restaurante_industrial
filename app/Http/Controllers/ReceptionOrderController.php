<?php

namespace App\Http\Controllers;

use App\Support\EmploymentRoles;
use App\Support\SiteContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ReceptionOrderController extends Controller
{
    /** Obtiene la sede activa y mantiene el control de acceso vigente. */
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

    private function isWarehouseManager(Request $request, int $siteId): bool
    {
        return $request->user()->roles()->get()->contains(
            fn ($role) => (int) $role->pivot->id_sede === $siteId
                && EmploymentRoles::matches($role->nombre, 'Jefe de almacen')
        );
    }

    private function orders(int $siteId)
    {
        return DB::table('pedidos as p')
            ->leftJoin('proveedores as proveedor', 'proveedor.id_proveedor', '=', 'p.id_proveedor')
            ->where('p.id_sede', $siteId)
            ->select('p.*', 'proveedor.nombre as proveedor_nombre');
    }

    private function receiptsAvailable(): bool
    {
        return Schema::hasTable('recepciones')
            && Schema::hasColumn('recepciones', 'id_pedido');
    }

    /** Vista existente: resources/views/receptions/orders.blade.php */
    public function index(Request $request, SiteContext $context): View
    {
        $site = $this->site($request, $context);
        $isWarehouseManager = $this->isWarehouseManager($request, (int) $site->id_sede);

        // Los filtros corresponden a la tabla de pedidos de orders.blade.php.
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'fecha_entrega' => ['nullable', 'date_format:Y-m-d'],
            'estado' => ['nullable', 'string', 'max:30'],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));

        $states = DB::table('pedidos')
            ->where('id_sede', $site->id_sede)
            ->whereNotNull('estado')
            ->distinct()
            ->orderBy('estado')
            ->pluck('estado');

        $orders = $this->orders((int) $site->id_sede)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('proveedor.nombre', 'like', '%'.$search.'%');
                    if (ctype_digit($search)) {
                        $query->orWhere('p.id_pedido', (int) $search);
                    }
                });
            })
            ->when($filters['fecha_entrega'] ?? null,
                fn ($query, $date) => $query->where('p.fecha_entrega', $date))
            ->when($filters['estado'] ?? null,
                fn ($query, $state) => $query->where('p.estado', $state))
            ->orderBy('p.fecha_entrega')
            ->orderByDesc('p.id_pedido')
            ->paginate(20)
            ->withQueryString();

        // Consultar historial NO depende de que la captura esté habilitada.
        $receiptsAvailable = $this->receiptsAvailable();
        $recentReceipts = collect();
        if ($isWarehouseManager && $receiptsAvailable) {
            $recentReceipts = DB::table('recepciones as r')
                ->join('pedidos as p', 'p.id_pedido', '=', 'r.id_pedido')
                ->where('p.id_sede', $site->id_sede)
                ->orderByDesc('r.id_recepcion')
                ->limit(20)
                ->get(['r.id_recepcion', 'r.id_pedido', 'r.fecha_recepcion', 'r.estado']);
        }

        return view('receptions.orders', compact(
            'site', 'orders', 'states', 'filters', 'search',
            'isWarehouseManager', 'receiptsAvailable', 'recentReceipts'
        ));
    }

    /** Vista existente: resources/views/receptions/order.blade.php */
    public function show(Request $request, int $order, SiteContext $context): View
    {
        $site = $this->site($request, $context);
        $isWarehouseManager = $this->isWarehouseManager($request, (int) $site->id_sede);

        $order = $this->orders((int) $site->id_sede)
            ->where('p.id_pedido', $order)
            ->first();
        abort_unless($order, 404);

        $details = DB::table('detalle_pedidos as d')
            ->leftJoin('insumos as i', 'i.id_insumo', '=', 'd.id_insumo')
            ->where('d.id_pedido', $order->id_pedido)
            ->orderBy('d.id_detalle_pedido')
            ->get(['d.cantidad', 'd.especificaciones', 'i.nombre as insumo_nombre']);

        $receiptsAvailable = $this->receiptsAvailable();
        $receipts = $receiptsAvailable
            ? DB::table('recepciones')
                ->where('id_pedido', $order->id_pedido)
                ->orderByDesc('id_recepcion')
                ->get()
            : collect();

        return view('receptions.order', compact(
            'site', 'order', 'details', 'receipts',
            'isWarehouseManager', 'receiptsAvailable'
        ));
    }

    /**
     * Supervisión de almacén, SOLO LECTURA.
     * Utiliza receipt.blade.php, no una vista nueva inexistente.
     * No registra movimientos ni confirma mercancía.
     */
    public function supervise(Request $request, int $receipt, SiteContext $context): View
    {
        $site = $this->site($request, $context);
        abort_unless($this->isWarehouseManager($request, (int) $site->id_sede), 403);
        abort_unless($this->receiptsAvailable(), 404);

        $receipt = DB::table('recepciones as r')
            ->join('pedidos as p', 'p.id_pedido', '=', 'r.id_pedido')
            ->leftJoin('proveedores as pr', 'pr.id_proveedor', '=', 'r.id_proveedor')
            ->leftJoin('usuarios as receptor', 'receptor.id_usuario', '=', 'r.id_usuario')
            ->where('p.id_sede', $site->id_sede)
            ->where('r.id_recepcion', $receipt)
            ->first([
                'r.*',
                'pr.nombre as proveedor_nombre',
                'receptor.nombre as receptor_nombre',
                'receptor.apellido_p as receptor_apellido',
            ]);
        abort_unless($receipt, 404);

        $details = DB::table('recepcion_detalles as rd')
            ->join('detalle_pedidos as d', 'd.id_detalle_pedido', '=', 'rd.id_detalle_pedido')
            ->leftJoin('insumos as i', 'i.id_insumo', '=', 'd.id_insumo')
            ->leftJoin('unidades as unidad', 'unidad.id_unidad', '=', 'i.id_unidad_base')
            ->leftJoin('lotes as lote', 'lote.id_lote', '=', 'rd.id_lote')
            ->where('rd.id_recepcion', $receipt->id_recepcion)
            ->where('d.id_pedido', $receipt->id_pedido)
            ->orderBy('rd.id_recepcion_detalle')
            ->get([
                'rd.id_recepcion_detalle',
                'rd.cantidad_entregada',
                'rd.cantidad_aceptada',
                'rd.caducidad',
                'rd.resultado_revision',
                'rd.observaciones',
                'd.cantidad as cantidad_solicitada',
                'i.nombre as insumo_nombre',
                'unidad.codigo as unidad',
                'lote.codigo_lote',
            ]);

        $incidents = Schema::hasTable('incidencias')
            ? DB::table('incidencias')
                ->where('id_sede', $site->id_sede)
                ->whereIn('id_recepcion_detalle', $details->pluck('id_recepcion_detalle'))
                ->orderByDesc('id_incidencia')
                ->get(['id_incidencia', 'descripcion', 'estado', 'respuesta'])
            : collect();

        $locations = DB::table('ubicacion_inventarios')->where('id_sede', $site->id_sede)
            ->orderBy('nombre')->get(['id_ubicacion_inv', 'nombre']);
        $isWarehouseManager = true;
        return view('receptions.receipt', compact('site', 'receipt', 'details', 'incidents', 'locations', 'isWarehouseManager'));
    }


    /**
     * Confirma una recepción pendiente y registra SOLO las cantidades aceptadas.
     * La fila de recepción bloqueada serializa los intentos de confirmación.
     */
    public function confirm(Request $request, int $receipt, SiteContext $context): RedirectResponse
    {
        $site = $this->site($request, $context);
        abort_unless($this->isWarehouseManager($request, (int) $site->id_sede), 403);
        $validated = $request->validate([
            'id_ubicacion' => ['required', 'integer'],
            'observacion_revision' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        // El nombre del estado confirmado debe coincidir con el contrato de captura.
        $confirmedState = 'Confirmada';
        abort_unless(in_array($confirmedState, (array) config('receptions.counted_states', []), true), 503,
            'Configura Confirmada en receptions.counted_states antes de habilitar la confirmación.');
        $pendingState = (string) config('receptions.pending', 'Pendiente de revisión');

        DB::transaction(function () use ($request, $site, $receipt, $validated, $confirmedState, $pendingState): void {
            // Control de concurrencia y aislamiento por sede.
            $row = DB::table('recepciones as r')
                ->join('pedidos as p', 'p.id_pedido', '=', 'r.id_pedido')
                ->where('p.id_sede', $site->id_sede)
                ->where('r.id_recepcion', $receipt)
                ->select('r.*')
                ->lockForUpdate()
                ->first();
            abort_unless($row, 404);
            if ($row->estado !== $pendingState) {
                throw ValidationException::withMessages(['recepcion' => 'Solo se puede confirmar una recepción pendiente. Actualiza la página.']);
            }

            $location = DB::table('ubicacion_inventarios')
                ->where('id_ubicacion_inv', $validated['id_ubicacion'])
                ->where('id_sede', $site->id_sede)
                ->lockForUpdate()
                ->first();
            if (! $location) {
                throw ValidationException::withMessages(['id_ubicacion' => 'La ubicación no pertenece a tu sede.']);
            }

            $lines = DB::table('recepcion_detalles as rd')
                ->join('detalle_pedidos as dp', 'dp.id_detalle_pedido', '=', 'rd.id_detalle_pedido')
                ->where('rd.id_recepcion', $row->id_recepcion)
                ->where('dp.id_pedido', $row->id_pedido)
                ->select('rd.*', 'dp.id_insumo')
                ->orderBy('rd.id_recepcion_detalle')
                ->lockForUpdate()
                ->get();
            if ($lines->isEmpty()) {
                throw ValidationException::withMessages(['recepcion' => 'La recepción no tiene partidas válidas.']);
            }
            $existingCount = DB::table('inventarios_finales')
                ->whereIn('id_recepcion_detalle', $lines->pluck('id_recepcion_detalle'))
                ->count();
            if ($existingCount > 0) {
                throw ValidationException::withMessages(['recepcion' => 'Esta recepción ya tiene movimientos relacionados. Requiere revisión antes de confirmar.']);
            }

            foreach ($lines as $line) {
                $accepted = (string) ($line->cantidad_aceptada ?? '0');
                $delivered = (string) ($line->cantidad_entregada ?? '0');
                $acceptedUnits = $this->thousandths($accepted);
                $deliveredUnits = $this->thousandths($delivered);
                if ($acceptedUnits < 0 || $acceptedUnits > $deliveredUnits) {
                    throw ValidationException::withMessages(['recepcion' => 'Hay cantidades aceptadas inválidas.']);
                }
                if ($acceptedUnits === 0) {
                    continue; // Nunca ingresar producto rechazado.
                }
                if (! $line->id_lote) {
                    throw ValidationException::withMessages(['recepcion' => 'Una partida aceptada no tiene lote.']);
                }
                $lot = DB::table('lotes')->where('id_lote', $line->id_lote)
                    ->where('id_insumo', $line->id_insumo)->first();
                if (! $lot) {
                    throw ValidationException::withMessages(['recepcion' => 'El lote no corresponde al insumo.']);
                }
                $stockRows = DB::table('insumo_sedes')
                    ->where('id_sede', $site->id_sede)
                    ->where('id_insumo', $line->id_insumo)
                    ->lockForUpdate()->get();
                if ($stockRows->count() !== 1 || $stockRows[0]->stock === null) {
                    throw ValidationException::withMessages(['recepcion' => 'El insumo carece de un saldo único y definido en esta sede. Revísalo en Inventario.']);
                }
                $stock = $stockRows[0];
                $newStock = $this->thousandths((string) $stock->stock) + $acceptedUnits;
                if ($newStock > 999999999999) {
                    throw ValidationException::withMessages(['recepcion' => 'El saldo supera el límite de inventario.']);
                }
                DB::table('insumo_sedes')->where('id_insumo_sede', $stock->id_insumo_sede)
                    ->update(['stock' => $this->decimalQuantity($newStock)]);
                DB::table('inventarios_finales')->insert([
                    'id_ubicacion' => $location->id_ubicacion_inv,
                    'id_lote' => $line->id_lote,
                    'id_usuario' => $request->user()->id_usuario,
                    'fecha' => now(),
                    'tipo' => 'entrada',
                    'cantidad_firmada' => $this->decimalQuantity($acceptedUnits),
                    'costo_unitario' => $line->precio_unitario,
                    'id_recepcion_detalle' => $line->id_recepcion_detalle,
                    'referencia' => 'Recepcion #'.$row->id_recepcion,
                    'observaciones' => 'Confirmada por jefe de almacen. '.$validated['observacion_revision'],
                ]);
            }
            DB::table('recepciones')->where('id_recepcion', $row->id_recepcion)->update([
                'estado' => $confirmedState,
                'fecha_confirmacion' => now(),
                'observaciones' => trim((string) $row->observaciones.' | Revision almacen: '.$validated['observacion_revision']),
            ]);
        }, 3);

        return redirect()->route('receptions.review.show', $receipt)
            ->with('status', 'Recepción confirmada. Solo las cantidades aceptadas se ingresaron al inventario.');
    }

    /**
     * Devuelve o cancela una captura pendiente sin borrarla ni generar movimientos.
     * El estado debe estar declarado en released_states para liberar el pendiente del pedido.
     */
    public function returnForCorrection(Request $request, int $receipt, SiteContext $context): RedirectResponse
    {
        $site = $this->site($request, $context);
        abort_unless($this->isWarehouseManager($request, (int) $site->id_sede), 403);
        $data = $request->validate(['motivo' => ['required', 'string', 'min:10', 'max:1000']]);
        $released = (array) config('receptions.released_states', []);
        // Exige un estado conocido que el flujo de captura ya libere del pendiente.
        $returnedState = collect($released)->first(fn ($s) => in_array($s, ['Devuelta para corrección', 'Cancelada', 'Rechazada'], true));
        abort_unless($returnedState, 503, 'Falta definir un estado de devolución/cancelación en receptions.released_states.');
        DB::transaction(function () use ($receipt, $site, $request, $data, $returnedState): void {
            $row = DB::table('recepciones as r')
                ->join('pedidos as p', 'p.id_pedido', '=', 'r.id_pedido')
                ->where('p.id_sede', $site->id_sede)->where('r.id_recepcion', $receipt)
                ->select('r.*')->lockForUpdate()->first();
            abort_unless($row, 404);
            abort_unless($row->estado === config('receptions.pending'), 409, 'Solo se pueden devolver recepciones pendientes.');
            $lineIds = DB::table('recepcion_detalles')->where('id_recepcion', $receipt)->pluck('id_recepcion_detalle');
            abort_if(DB::table('inventarios_finales')->whereIn('id_recepcion_detalle', $lineIds)->exists(),
                409, 'No se puede devolver una recepción con movimientos de inventario.');
            DB::table('recepciones')->where('id_recepcion', $receipt)->update([
                'estado' => $returnedState,
                'observaciones' => trim((string) $row->observaciones.' | Devuelta por usuario '.$request->user()->id_usuario.' el '.now().' : '.$data['motivo']),
            ]);
        }, 3);
        return redirect()->route('receptions.review.show', $receipt)
            ->with('status', 'Recepción devuelta/cancelada conservando su historial. Se liberó el pendiente según su configuración.');
    }

    private function thousandths(string $value): int
    {
        if (! preg_match('/^\d{1,9}(?:\.\d{1,3})?$/', $value)) {
            throw ValidationException::withMessages(['recepcion' => 'Cantidad almacenada fuera del formato admitido.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        return ((int) $whole * 1000) + (int) str_pad($fraction, 3, '0');
    }

    private function decimalQuantity(int $units): string
    {
        return intdiv($units, 1000).'.'.str_pad((string) ($units % 1000), 3, '0', STR_PAD_LEFT);
    }

}
