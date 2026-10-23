<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Support\SiteContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InventoryController extends Controller
{
    private const PAGE_SIZE = 20;

    private const MAX_THOUSANDTHS = 999999999999;

    private const LOCAL_TIMEZONE = 'America/Mexico_City';

    public function index(Request $request, SiteContext $siteContext): View
    {
        $site = $this->activeSite($request, $siteContext);
        $employeeName = $request->user()->name;
        $inventoryDate = now(self::LOCAL_TIMEZONE);
        $inventoryToday = $inventoryDate->copy()->startOfDay();
        $inventoryAvailable = $this->hasCoreInventorySchema();
        $historyAvailable = $this->hasMovementSchema();
        $expiryAvailable = $historyAvailable && Schema::hasColumn('lotes', 'fecha_caducidad');
        $operationsAvailable = $inventoryAvailable && $historyAvailable;
        $canManageInventory = $request->user()->hasRole('Jefe de cocina');
        $canRegisterIngredients = $canManageInventory || $request->user()->hasRole('Recepcion de insumos');

        $rows = collect();
        $ingredients = collect();
        $unassignedIngredients = collect();
        $catalogUnits = collect();
        $units = collect();
        $locations = collect();
        $suppliers = collect();
        $batchOptions = collect();
        $registeredCount = 0;
        $catalogCount = 0;
        $lowCount = 0;
        $depletedCount = 0;
        $expiringCount = 0;
        $soonDays = 30;

        $search = trim((string) $request->query('q', ''));
        $filterState = (string) $request->query('estado', '');
        $filterUnit = (string) $request->query('unidad', '');
        $requestedLocation = (string) $request->query('ubicacion_filtro', '');
        $filterLocation = '';
        $validStates = ['suficiente', 'bajo', 'agotado', 'sin-minimo', 'sin-dato', 'por-caducar'];
        if (! in_array($filterState, $validStates, true)) {
            $filterState = '';
        }

        if ($inventoryAvailable) {
            $catalogCount = DB::table('insumos')->count();
            if ($historyAvailable) {
                $locations = DB::table('ubicacion_inventarios')
                    ->where('id_sede', $site->id_sede)
                    ->orderBy('nombre')
                    ->get(['id_ubicacion_inv', 'nombre']);

                if (ctype_digit($requestedLocation) && $locations->contains(
                    fn ($location): bool => (int) $location->id_ubicacion_inv === (int) $requestedLocation,
                )) {
                    $filterLocation = $requestedLocation;
                }
            }

            $siteStock = DB::table('insumo_sedes')->where('id_sede', $site->id_sede);
            $registeredCount = (clone $siteStock)->count();
            $lowCount = (clone $siteStock)
                ->whereNotNull('punto_de_reorden')
                ->whereColumn('stock', '<=', 'punto_de_reorden')
                ->count();
            $depletedCount = (clone $siteStock)
                ->whereNotNull('stock')
                ->where('stock', '<=', 0)
                ->count();

            $expiringQuery = $expiryAvailable ? $this->expiringLotsForSite($site->id_sede) : null;
            if ($expiringQuery) {
                $upcomingLots = $this->upcomingLotsForSite($site->id_sede, $soonDays);
                $expiringCount = DB::table('insumo_sedes as existencia')
                    ->joinSub($upcomingLots, 'proxima_caducidad', fn ($join) => $join->on('proxima_caducidad.id_insumo', '=', 'existencia.id_insumo'))
                    ->where('existencia.id_sede', $site->id_sede)
                    ->where('existencia.stock', '>', 0)
                    ->distinct()
                    ->count('existencia.id_insumo');
            }

            $inventoryQuery = DB::table('insumo_sedes as existencia')
                ->join('insumos as insumo', 'insumo.id_insumo', '=', 'existencia.id_insumo')
                ->leftJoin('unidades as unidad', 'unidad.id_unidad', '=', 'insumo.id_unidad_base')
                ->where('existencia.id_sede', $site->id_sede);

            if ($historyAvailable) {
                $inventoryQuery->leftJoinSub(
                    $this->movementLocationsForSite($site->id_sede),
                    'ubicacion_movimiento',
                    fn ($join) => $join->on('ubicacion_movimiento.id_insumo', '=', 'existencia.id_insumo'),
                );
            }

            $inventoryQuery->select([
                'existencia.id_insumo_sede',
                'existencia.id_insumo',
                'insumo.nombre as insumo',
                'existencia.stock',
                'existencia.punto_de_reorden',
                'insumo.id_unidad_base as id_unidad',
                'unidad.nombre as unidad',
                'unidad.codigo as unidad_codigo',
                'unidad.permite_fracciones',
            ]);

            if ($expiringQuery) {
                $inventoryQuery->leftJoinSub($expiringQuery, 'caducidad', fn ($join) => $join->on('caducidad.id_insumo', '=', 'existencia.id_insumo'))
                    ->addSelect('caducidad.fecha_caducidad');
            } else {
                $inventoryQuery->addSelect(DB::raw('NULL as fecha_caducidad'));
            }

            if ($historyAvailable) {
                $inventoryQuery->addSelect('ubicacion_movimiento.lista as ubicaciones_movimiento');
            } else {
                $inventoryQuery->addSelect(DB::raw('NULL as ubicaciones_movimiento'));
            }

            if ($search !== '') {
                $inventoryQuery->where(function ($query) use ($search, $historyAvailable): void {
                    $query->where('insumo.nombre', 'like', '%'.$search.'%')
                        ->orWhere('unidad.nombre', 'like', '%'.$search.'%');

                    if ($historyAvailable) {
                        $query->orWhere('ubicacion_movimiento.lista', 'like', '%'.$search.'%');
                    }
                });
            }

            if ($filterUnit !== '' && ctype_digit($filterUnit)) {
                $inventoryQuery->where('insumo.id_unidad_base', (int) $filterUnit);
            }

            if ($filterLocation !== '' && $historyAvailable) {
                $inventoryQuery->whereExists(function ($query) use ($site, $filterLocation): void {
                    $query->selectRaw('1')
                        ->from('inventarios_finales as movimiento_filtro')
                        ->join('lotes as lote_filtro', 'lote_filtro.id_lote', '=', 'movimiento_filtro.id_lote')
                        ->join('ubicacion_inventarios as ubicacion_filtro', 'ubicacion_filtro.id_ubicacion_inv', '=', 'movimiento_filtro.id_ubicacion')
                        ->whereColumn('lote_filtro.id_insumo', 'existencia.id_insumo')
                        ->where('ubicacion_filtro.id_sede', $site->id_sede)
                        ->where('ubicacion_filtro.id_ubicacion_inv', (int) $filterLocation);
                });
            }

            if ($filterState === 'por-caducar' && $expiryAvailable) {
                $inventoryQuery->joinSub($this->upcomingLotsForSite($site->id_sede, $soonDays), 'proxima_caducidad', fn ($join) => $join->on('proxima_caducidad.id_insumo', '=', 'existencia.id_insumo'));
            }

            match ($filterState) {
                'agotado' => $inventoryQuery->whereNotNull('existencia.stock')->where('existencia.stock', '<=', 0),
                'bajo' => $inventoryQuery->whereNotNull('existencia.punto_de_reorden')->whereColumn('existencia.stock', '<=', 'existencia.punto_de_reorden'),
                'suficiente' => $inventoryQuery->where('existencia.stock', '>', 0)->whereNotNull('existencia.punto_de_reorden')->whereColumn('existencia.stock', '>', 'existencia.punto_de_reorden'),
                'sin-minimo' => $inventoryQuery->where('existencia.stock', '>', 0)->whereNull('existencia.punto_de_reorden'),
                'sin-dato' => $inventoryQuery->whereNull('existencia.stock'),
                'por-caducar' => $expiringQuery
                    ? $inventoryQuery->where('existencia.stock', '>', 0)->whereNotNull('proxima_caducidad.fecha_caducidad')
                    : $inventoryQuery,
                default => $inventoryQuery,
            };

            $rows = $inventoryQuery
                ->orderBy('insumo.nombre')
                ->paginate(self::PAGE_SIZE)
                ->withQueryString();

            $ingredients = DB::table('insumos as insumo')
                ->join('insumo_sedes as existencia', function ($join) use ($site): void {
                    $join->on('existencia.id_insumo', '=', 'insumo.id_insumo')
                        ->where('existencia.id_sede', '=', $site->id_sede);
                })
                ->join('unidades as unidad', 'unidad.id_unidad', '=', 'insumo.id_unidad_base')
                ->select([
                    'insumo.id_insumo',
                    'insumo.nombre',
                    'existencia.stock',
                    'unidad.nombre as unidad',
                    'unidad.codigo as unidad_codigo',
                    'unidad.permite_fracciones',
                ])
                ->orderBy('insumo.nombre')
                ->get();

            $unassignedIngredients = DB::table('insumos as insumo')
                ->join('unidades as unidad', 'unidad.id_unidad', '=', 'insumo.id_unidad_base')
                ->whereNotExists(function ($query) use ($site): void {
                    $query->selectRaw('1')
                        ->from('insumo_sedes as existencia')
                        ->whereColumn('existencia.id_insumo', 'insumo.id_insumo')
                        ->where('existencia.id_sede', $site->id_sede);
                })
                ->select([
                    'insumo.id_insumo',
                    'insumo.nombre',
                    'unidad.nombre as unidad',
                    'unidad.codigo as unidad_codigo',
                    'unidad.permite_fracciones',
                ])
                ->orderBy('insumo.nombre')
                ->get();

            $catalogUnits = DB::table('unidades')
                ->orderBy('nombre')
                ->get(['id_unidad', 'nombre', 'codigo', 'permite_fracciones']);

            $units = DB::table('insumo_sedes as existencia')
                ->join('insumos as insumo', 'insumo.id_insumo', '=', 'existencia.id_insumo')
                ->join('unidades as unidad', 'unidad.id_unidad', '=', 'insumo.id_unidad_base')
                ->where('existencia.id_sede', $site->id_sede)
                ->select(['unidad.id_unidad', 'unidad.nombre'])
                ->distinct()
                ->orderBy('unidad.nombre')
                ->get();

            if ($operationsAvailable) {
                $batchOptions = DB::table('lotes as lote')
                    ->joinSub($this->positiveLotsForSite($site->id_sede), 'saldo_lote', fn ($join) => $join->on('saldo_lote.id_lote', '=', 'lote.id_lote'))
                    ->join('insumos as insumo', 'insumo.id_insumo', '=', 'lote.id_insumo')
                    ->join('unidades as unidad', 'unidad.id_unidad', '=', 'insumo.id_unidad_base')
                    ->orderBy('lote.id_insumo')
                    ->orderBy('lote.fecha_caducidad')
                    ->get([
                        'lote.id_lote',
                        'lote.id_insumo',
                        'lote.codigo_lote',
                        'lote.fecha_caducidad',
                        'saldo_lote.stock_lote',
                        'unidad.nombre as unidad',
                        'unidad.codigo as unidad_codigo',
                    ]);

                if (Schema::hasTable('proveedores')) {
                    $suppliers = DB::table('proveedores')->orderBy('nombre')->get(['id_proveedor', 'nombre']);
                }
            }
        }

        $historyItem = null;
        $historyRows = collect();
        if ($request->filled('historial') && $inventoryAvailable) {
            $historyId = filter_var($request->query('historial'), FILTER_VALIDATE_INT);
            abort_unless($historyId && $historyId > 0, 404);

            $historyItem = DB::table('insumo_sedes as existencia')
                ->join('insumos as insumo', 'insumo.id_insumo', '=', 'existencia.id_insumo')
                ->leftJoin('unidades as unidad', 'unidad.id_unidad', '=', 'insumo.id_unidad_base')
                ->where('existencia.id_sede', $site->id_sede)
                ->where('existencia.id_insumo', $historyId)
                ->first([
                    'insumo.id_insumo',
                    'insumo.nombre as insumo',
                    'unidad.nombre as unidad',
                    'unidad.codigo as unidad_codigo',
                ]);
            abort_unless($historyItem, 404);

            if ($historyAvailable) {
                $historyRows = DB::table('inventarios_finales as movimiento')
                    ->join('lotes as lote', 'lote.id_lote', '=', 'movimiento.id_lote')
                    ->join('ubicacion_inventarios as ubicacion', 'ubicacion.id_ubicacion_inv', '=', 'movimiento.id_ubicacion')
                    ->leftJoin('usuarios as usuario', 'usuario.id_usuario', '=', 'movimiento.id_usuario')
                    ->where('ubicacion.id_sede', $site->id_sede)
                    ->where('lote.id_insumo', $historyId)
                    ->orderByDesc('movimiento.fecha')
                    ->orderByDesc('movimiento.id_inventario_final')
                    ->paginate(12, [
                        'movimiento.fecha',
                        'movimiento.tipo',
                        'movimiento.cantidad_firmada',
                        'movimiento.observaciones',
                        'movimiento.referencia',
                        'lote.codigo_lote',
                        'lote.fecha_caducidad',
                        'ubicacion.nombre as ubicacion',
                        'usuario.nombre as usuario_nombre',
                        'usuario.apellido_p as usuario_apellido',
                    ], 'historial_pagina')
                    ->withQueryString();
            }
        }

        return view('modules.inventory', compact(
            'site',
            'rows',
            'ingredients',
            'unassignedIngredients',
            'catalogUnits',
            'units',
            'locations',
            'suppliers',
            'batchOptions',
            'registeredCount',
            'catalogCount',
            'lowCount',
            'depletedCount',
            'expiringCount',
            'soonDays',
            'search',
            'filterState',
            'filterUnit',
            'filterLocation',
            'employeeName',
            'inventoryDate',
            'inventoryToday',
            'inventoryAvailable',
            'historyAvailable',
            'expiryAvailable',
            'operationsAvailable',
            'canManageInventory',
            'canRegisterIngredients',
            'historyItem',
            'historyRows',
        ));
    }

    public function storeIngredient(Request $request, SiteContext $siteContext): RedirectResponse
    {
        abort_unless(
            $request->user()->hasRole('Jefe de cocina') || $request->user()->hasRole('Recepcion de insumos'),
            403,
        );
        $site = $this->activeSite($request, $siteContext);
        abort_unless($this->hasCoreInventorySchema() && $this->hasMovementSchema(), 503, 'El catálogo, las ubicaciones o el historial de inventario no están disponibles.');

        $allowsFractions = $this->unitAllowsFractionsForUnit($request->input('id_unidad_base'));
        $validated = $request->validate(array_merge([
            'nombre' => ['required', 'string', 'max:150'],
            'id_unidad_base' => ['required', 'integer', 'exists:unidades,id_unidad'],
            'punto_de_reorden' => array_merge(
                ['nullable'],
                $this->quantityPrecisionRules($allowsFractions),
                ['min:0', 'max:999999999.999'],
            ),
        ], $this->initialEntryRules($allowsFractions)), [
            'id_unidad_base.exists' => 'Selecciona una unidad existente del catálogo.',
            'punto_de_reorden.min' => 'El mínimo de reorden no puede ser menor que cero.',
            'cantidad_inicial.gt' => 'La existencia inicial debe ser mayor que cero.',
            'cantidad_inicial.integer' => 'La unidad base no permite fracciones; captura una existencia entera.',
            'punto_de_reorden.integer' => 'La unidad base no permite fracciones; captura un mínimo entero.',
            'fecha_entrada.date_format' => 'Indica una fecha válida para la entrada inicial.',
            'motivo_inicial.required' => 'Indica el motivo de la entrada inicial.',
            'nueva_ubicacion.required_if' => 'Escribe el nombre de la ubicación de esta sede.',
        ]);

        $ingredientId = DB::transaction(function () use ($validated, $site, $request): int {
            $unit = DB::table('unidades')
                ->where('id_unidad', (int) $validated['id_unidad_base'])
                ->lockForUpdate()
                ->first(['id_unidad', 'permite_fracciones']);

            if (! $unit) {
                throw ValidationException::withMessages([
                    'id_unidad_base' => 'La unidad seleccionada ya no está disponible. Actualiza la página e inténtalo de nuevo.',
                ]);
            }

            if (! $unit->permite_fracciones) {
                $this->assertIntegerQuantity($validated['cantidad_inicial'], 'cantidad_inicial');
                $this->assertIntegerQuantity($validated['punto_de_reorden'] ?? null, 'punto_de_reorden');
            }

            $name = trim($validated['nombre']);
            $existingIngredient = DB::table('insumos')
                ->whereRaw('LOWER(nombre) = LOWER(?)', [$name])
                ->lockForUpdate()
                ->first(['id_insumo']);

            if ($existingIngredient) {
                throw ValidationException::withMessages([
                    'nombre' => 'Ya existe un insumo con ese nombre. Selecciónalo del catálogo para asignarlo a esta sede.',
                ]);
            }

            $ingredientId = (int) DB::table('insumos')->insertGetId([
                'nombre' => $name,
                'id_unidad_base' => (int) $unit->id_unidad,
            ]);

            DB::table('insumo_sedes')->insert([
                'id_sede' => $site->id_sede,
                'id_insumo' => $ingredientId,
                'stock' => $this->persistInitialEntry($validated, $ingredientId, $site->id_sede, (int) $request->user()->id_usuario),
                'punto_de_reorden' => isset($validated['punto_de_reorden']) && $validated['punto_de_reorden'] !== ''
                    ? $this->fromThousandths($this->toThousandths($validated['punto_de_reorden']))
                    : null,
            ]);

            return $ingredientId;
        }, 3);

        return redirect()->route('inventory.index')->with(
            'status',
            'Insumo creado, asignado a '.$site->nombre.' y registrado con su entrada inicial.',
        );
    }

    public function assignIngredient(Request $request, SiteContext $siteContext): RedirectResponse
    {
        abort_unless($request->user()->hasRole('Jefe de cocina'), 403);
        $site = $this->activeSite($request, $siteContext);
        abort_unless($this->hasCoreInventorySchema() && $this->hasMovementSchema(), 503, 'El catálogo, las ubicaciones o el historial de inventario no están disponibles.');

        $allowsFractions = $this->unitAllowsFractionsForIngredient($request->input('id_insumo'));
        $validated = $request->validate(array_merge([
            'id_insumo' => ['required', 'integer', 'exists:insumos,id_insumo'],
            'punto_de_reorden' => array_merge(
                ['nullable'],
                $this->quantityPrecisionRules($allowsFractions),
                ['min:0', 'max:999999999.999'],
            ),
        ], $this->initialEntryRules($allowsFractions)), [
            'punto_de_reorden.min' => 'El mínimo de reorden no puede ser menor que cero.',
            'cantidad_inicial.gt' => 'La existencia inicial debe ser mayor que cero.',
            'cantidad_inicial.integer' => 'La unidad base del insumo no permite fracciones; captura una existencia entera.',
            'punto_de_reorden.integer' => 'La unidad base del insumo no permite fracciones; captura un mínimo entero.',
            'fecha_entrada.date_format' => 'Indica una fecha válida para la entrada inicial.',
            'motivo_inicial.required' => 'Indica el motivo de la entrada inicial.',
            'nueva_ubicacion.required_if' => 'Escribe el nombre de la ubicación de esta sede.',
        ]);

        DB::transaction(function () use ($validated, $site, $request): void {
            $ingredient = DB::table('insumos as insumo')
                ->join('unidades as unidad', 'unidad.id_unidad', '=', 'insumo.id_unidad_base')
                ->where('insumo.id_insumo', (int) $validated['id_insumo'])
                ->lockForUpdate()
                ->first(['insumo.id_insumo', 'unidad.permite_fracciones']);

            if (! $ingredient) {
                throw ValidationException::withMessages([
                    'id_insumo' => 'Selecciona un insumo existente del catálogo.',
                ]);
            }

            if (! $ingredient->permite_fracciones) {
                $this->assertIntegerQuantity($validated['cantidad_inicial'], 'cantidad_inicial');
                $this->assertIntegerQuantity($validated['punto_de_reorden'] ?? null, 'punto_de_reorden');
            }

            $existingAssignments = DB::table('insumo_sedes')
                ->where('id_sede', $site->id_sede)
                ->where('id_insumo', $ingredient->id_insumo)
                ->lockForUpdate()
                ->count();

            if ($existingAssignments > 0) {
                throw ValidationException::withMessages([
                    'id_insumo' => 'Este insumo ya está asignado a la sede activa. Actualiza la página.',
                ]);
            }

            DB::table('insumo_sedes')->insert([
                'id_sede' => $site->id_sede,
                'id_insumo' => $ingredient->id_insumo,
                'stock' => $this->persistInitialEntry($validated, (int) $ingredient->id_insumo, $site->id_sede, (int) $request->user()->id_usuario),
                'punto_de_reorden' => isset($validated['punto_de_reorden']) && $validated['punto_de_reorden'] !== ''
                    ? $this->fromThousandths($this->toThousandths($validated['punto_de_reorden']))
                    : null,
            ]);
        }, 3);

        return redirect()->route('inventory.index')->with(
            'status',
            'Insumo asignado a '.$site->nombre.' y registrado con su entrada inicial.',
        );
    }

    public function storeMovement(Request $request, SiteContext $siteContext): RedirectResponse
    {
        abort_unless($request->user()->hasRole('Jefe de cocina'), 403);
        $site = $this->activeSite($request, $siteContext);
        abort_unless($this->hasCoreInventorySchema() && $this->hasMovementSchema(), 503, 'Las tablas necesarias para registrar movimientos no están disponibles.');

        $request->validate([
            'tipo' => ['required', Rule::in(['entrada', 'salida', 'consumo', 'merma', 'ajuste'])],
        ]);
        $type = (string) $request->input('tipo');
        $allowsFractions = $this->unitAllowsFractionsForIngredient($request->input('id_insumo'));
        $quantityRules = array_merge(
            ['required'],
            $this->quantityPrecisionRules($allowsFractions),
            ['max:999999999.999'],
        );
        $quantityRules[] = $type === 'ajuste' ? 'min:0' : 'gt:0';

        $validated = $request->validate([
            'tipo' => ['required', Rule::in(['entrada', 'salida', 'consumo', 'merma', 'ajuste'])],
            'id_insumo' => ['required', 'integer', 'exists:insumos,id_insumo'],
            'cantidad' => $quantityRules,
            'motivo' => ['nullable', 'string', 'max:1000', 'required_if:tipo,ajuste'],
            'ubicacion' => ['required', 'string', 'max:20'],
            'nueva_ubicacion' => ['nullable', 'string', 'max:100', 'required_if:ubicacion,nueva'],
            'id_lote' => ['nullable', 'integer'],
            'id_proveedor' => ['nullable', 'integer'],
            'codigo_lote' => ['nullable', 'string', 'max:100'],
            'fecha_caducidad' => ['nullable', 'date'],
            'referencia' => ['nullable', 'string', 'max:100'],
        ], [
            'cantidad.gt' => 'La cantidad del movimiento debe ser mayor que cero.',
            'cantidad.min' => 'La existencia final del ajuste no puede ser menor que cero.',
            'cantidad.integer' => 'La unidad base del insumo no permite fracciones; captura una cantidad entera.',
            'motivo.required_if' => 'Indica el motivo del ajuste.',
            'nueva_ubicacion.required_if' => 'Escribe el nombre de la nueva ubicación.',
        ]);

        $movement = DB::transaction(function () use ($validated, $type, $site, $request): array {
            $ingredientId = (int) $validated['id_insumo'];
            $ingredientUnit = DB::table('insumos as insumo')
                ->join('unidades as unidad', 'unidad.id_unidad', '=', 'insumo.id_unidad_base')
                ->where('insumo.id_insumo', $ingredientId)
                ->lockForUpdate()
                ->first(['unidad.codigo', 'unidad.nombre', 'unidad.permite_fracciones']);

            if (! $ingredientUnit) {
                throw ValidationException::withMessages([
                    'id_insumo' => 'Este insumo no tiene una unidad base válida; corrige la relación del catálogo antes de operar.',
                ]);
            }

            if (! $ingredientUnit->permite_fracciones) {
                $this->assertIntegerQuantity($validated['cantidad'], 'cantidad');
            }

            $locationId = $this->resolveLocation($validated, $site->id_sede);
            $stockRows = DB::table('insumo_sedes')
                ->where('id_sede', $site->id_sede)
                ->where('id_insumo', $ingredientId)
                ->lockForUpdate()
                ->get();

            if ($stockRows->count() > 1) {
                throw ValidationException::withMessages([
                    'id_insumo' => 'Hay más de un saldo para este insumo en la sede. Corrige ese registro antes de operar.',
                ]);
            }

            if ($stockRows->count() !== 1) {
                throw ValidationException::withMessages([
                    'id_insumo' => $stockRows->isEmpty()
                        ? 'Asigna primero el insumo a la sede activa desde el inventario.'
                        : 'Hay más de un saldo para este insumo en la sede. Corrige ese registro antes de operar.',
                ]);
            }

            $stockRow = $stockRows->first();
            $currentStock = $stockRow->stock === null ? null : $this->toThousandths($stockRow->stock);
            $quantity = $this->toThousandths($validated['cantidad']);

            if ($type !== 'ajuste' && $currentStock === null) {
                throw ValidationException::withMessages([
                    'cantidad' => 'La existencia actual no está definida. Registra un ajuste físico con motivo para establecerla.',
                ]);
            }

            if ($type === 'ajuste') {
                $newStock = $quantity;
                $signedQuantity = $currentStock === null ? $quantity : $newStock - $currentStock;
            } else {
                $signedQuantity = in_array($type, ['salida', 'consumo', 'merma'], true) ? -$quantity : $quantity;
                $newStock = $currentStock + $signedQuantity;
            }

            if ($newStock < 0) {
                throw ValidationException::withMessages([
                    'cantidad' => 'El movimiento dejaría una existencia negativa. Revisa el saldo o reduce la cantidad.',
                ]);
            }

            if ($newStock > self::MAX_THOUSANDTHS) {
                throw ValidationException::withMessages([
                    'cantidad' => 'La existencia supera la capacidad admitida por el campo de stock.',
                ]);
            }

            $lotId = $this->resolveMovementLot($validated, $type, $ingredientId, $site->id_sede, $signedQuantity);
            $observations = trim((string) ($validated['motivo'] ?? ''));
            if ($type === 'ajuste' && $currentStock === null) {
                $observations = 'Saldo físico establecido desde un valor sin definir. '.$observations;
            }

            DB::table('insumo_sedes')
                ->where('id_insumo_sede', $stockRow->id_insumo_sede)
                ->update(['stock' => $this->fromThousandths($newStock)]);

            DB::table('inventarios_finales')->insert([
                'id_ubicacion' => $locationId,
                'id_lote' => $lotId,
                'id_usuario' => $request->user()->id_usuario,
                'fecha' => now(),
                'tipo' => $type,
                'cantidad_firmada' => $this->fromThousandths($signedQuantity),
                'referencia' => $validated['referencia'] ?? null,
                'observaciones' => $observations !== '' ? $observations : null,
            ]);

            return [
                'stock' => $this->fromThousandths($newStock),
                'unit_abbreviation' => $this->unitAbbreviation($ingredientUnit->codigo, $ingredientUnit->nombre),
            ];
        }, 3);

        $label = match ($type) {
            'entrada' => 'Entrada registrada',
            'salida' => 'Salida registrada',
            'consumo' => 'Consumo registrado',
            'merma' => 'Merma registrada',
            'ajuste' => 'Existencia ajustada',
        };

        return redirect()->route('inventory.index')->with(
            'status',
            $label.'. Existencia actual: '.$this->formatQuantity($movement['stock']).' '.$movement['unit_abbreviation'].'.',
        );
    }

    public function updateReorderPoint(Request $request, int $ingredient, SiteContext $siteContext): RedirectResponse
    {
        abort_unless($request->user()->hasRole('Jefe de cocina'), 403);
        $site = $this->activeSite($request, $siteContext);
        abort_unless($this->hasCoreInventorySchema(), 503, 'Las tablas de inventario no están disponibles.');

        $allowsFractions = $this->unitAllowsFractionsForIngredient($ingredient);
        $validated = $request->validate([
            'punto_de_reorden' => array_merge(
                ['nullable'],
                $this->quantityPrecisionRules($allowsFractions),
                ['min:0', 'max:999999999.999'],
            ),
        ], [
            'punto_de_reorden.min' => 'El mínimo de reorden no puede ser menor que cero.',
            'punto_de_reorden.integer' => 'La unidad base del insumo no permite fracciones; captura un mínimo entero.',
        ]);

        DB::transaction(function () use ($site, $ingredient, $validated): void {
            $rows = DB::table('insumo_sedes')
                ->where('id_sede', $site->id_sede)
                ->where('id_insumo', $ingredient)
                ->lockForUpdate()
                ->get();

            if ($rows->count() !== 1) {
                throw ValidationException::withMessages([
                    'punto_de_reorden' => 'El insumo no tiene un único saldo asignado a esta sede.',
                ]);
            }

            DB::table('insumo_sedes')
                ->where('id_insumo_sede', $rows->first()->id_insumo_sede)
                ->update([
                    'punto_de_reorden' => $validated['punto_de_reorden'] === null
                        ? null
                        : $this->fromThousandths($this->toThousandths($validated['punto_de_reorden'])),
                ]);
        });

        $message = $validated['punto_de_reorden'] === null
            ? 'El punto de reorden quedó sin definir.'
            : 'Punto de reorden actualizado.';

        return redirect()->route('inventory.index')->with('status', $message);
    }

    private function activeSite(Request $request, SiteContext $siteContext): Site
    {
        $site = $siteContext->currentFor($request, $request->user());
        abort_unless($site && ($request->user()->canAdminister() || $request->user()->sites()->whereKey($site->id_sede)->exists()), 403);

        return $site;
    }

    private function hasCoreInventorySchema(): bool
    {
        return Schema::hasTable('insumo_sedes')
            && Schema::hasTable('insumos')
            && Schema::hasTable('unidades')
            && Schema::hasColumn('insumo_sedes', 'id_insumo_sede')
            && Schema::hasColumn('insumo_sedes', 'id_sede')
            && Schema::hasColumn('insumo_sedes', 'id_insumo')
            && Schema::hasColumn('insumo_sedes', 'stock')
            && Schema::hasColumn('insumo_sedes', 'punto_de_reorden')
            && Schema::hasColumn('insumos', 'id_insumo')
            && Schema::hasColumn('insumos', 'nombre')
            && Schema::hasColumn('insumos', 'id_unidad_base')
            && Schema::hasColumn('unidades', 'id_unidad')
            && Schema::hasColumn('unidades', 'nombre')
            && Schema::hasColumn('unidades', 'codigo')
            && Schema::hasColumn('unidades', 'permite_fracciones');
    }

    private function hasMovementSchema(): bool
    {
        $columns = [
            'lotes' => ['id_lote', 'id_insumo', 'id_proveedor', 'codigo_lote', 'fecha_caducidad'],
            'inventarios_finales' => ['id_inventario_final', 'id_ubicacion', 'id_lote', 'id_usuario', 'fecha', 'tipo', 'cantidad_firmada', 'referencia', 'observaciones'],
            'ubicacion_inventarios' => ['id_ubicacion_inv', 'id_sede', 'nombre'],
            'usuarios' => ['id_usuario', 'nombre', 'apellido_p'],
        ];

        foreach ($columns as $table => $tableColumns) {
            if (! Schema::hasTable($table)) {
                return false;
            }

            foreach ($tableColumns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function positiveLotsForSite(int $siteId)
    {
        return DB::table('inventarios_finales as movimiento')
            ->join('ubicacion_inventarios as ubicacion', 'ubicacion.id_ubicacion_inv', '=', 'movimiento.id_ubicacion')
            ->where('ubicacion.id_sede', $siteId)
            ->groupBy('movimiento.id_lote')
            ->select('movimiento.id_lote')
            ->selectRaw('SUM(COALESCE(movimiento.cantidad_firmada, 0)) as stock_lote')
            ->havingRaw('SUM(COALESCE(movimiento.cantidad_firmada, 0)) > 0');
    }

    private function movementLocationsForSite(int $siteId)
    {
        return DB::table('inventarios_finales as movimiento')
            ->join('lotes as lote', 'lote.id_lote', '=', 'movimiento.id_lote')
            ->join('ubicacion_inventarios as ubicacion', 'ubicacion.id_ubicacion_inv', '=', 'movimiento.id_ubicacion')
            ->where('ubicacion.id_sede', $siteId)
            ->groupBy('lote.id_insumo')
            ->select('lote.id_insumo')
            ->selectRaw("GROUP_CONCAT(DISTINCT ubicacion.nombre ORDER BY ubicacion.nombre SEPARATOR ', ') as lista");
    }

    private function expiringLotsForSite(int $siteId)
    {
        return DB::table('lotes as lote')
            ->joinSub($this->positiveLotsForSite($siteId), 'saldo_lote', fn ($join) => $join->on('saldo_lote.id_lote', '=', 'lote.id_lote'))
            ->whereNotNull('lote.fecha_caducidad')
            ->groupBy('lote.id_insumo')
            ->select('lote.id_insumo')
            ->selectRaw(
                'COALESCE(MIN(CASE WHEN lote.fecha_caducidad >= ? THEN lote.fecha_caducidad END), MAX(lote.fecha_caducidad)) as fecha_caducidad',
                [now(self::LOCAL_TIMEZONE)->toDateString()],
            );
    }

    private function upcomingLotsForSite(int $siteId, int $days)
    {
        return DB::table('lotes as lote')
            ->joinSub($this->positiveLotsForSite($siteId), 'saldo_lote', fn ($join) => $join->on('saldo_lote.id_lote', '=', 'lote.id_lote'))
            ->whereBetween('lote.fecha_caducidad', [
                now(self::LOCAL_TIMEZONE)->toDateString(),
                now(self::LOCAL_TIMEZONE)->addDays($days)->toDateString(),
            ])
            ->groupBy('lote.id_insumo')
            ->select('lote.id_insumo')
            ->selectRaw('MIN(lote.fecha_caducidad) as fecha_caducidad');
    }

    private function quantityPrecisionRules(bool $allowsFractions): array
    {
        return $allowsFractions
            ? ['numeric', 'decimal:0,3']
            : ['numeric', 'integer'];
    }

    private function unitAllowsFractionsForUnit(mixed $unitId): bool
    {
        if (! is_scalar($unitId) || ! preg_match('/^\d+$/', (string) $unitId)) {
            return true;
        }

        $unit = DB::table('unidades')
            ->where('id_unidad', (int) $unitId)
            ->first(['permite_fracciones']);

        return $unit ? (bool) $unit->permite_fracciones : true;
    }

    private function unitAllowsFractionsForIngredient(mixed $ingredientId): bool
    {
        if (! is_scalar($ingredientId) || ! preg_match('/^\d+$/', (string) $ingredientId)) {
            return true;
        }

        $unit = DB::table('insumos as insumo')
            ->join('unidades as unidad', 'unidad.id_unidad', '=', 'insumo.id_unidad_base')
            ->where('insumo.id_insumo', (int) $ingredientId)
            ->first(['unidad.permite_fracciones']);

        return $unit ? (bool) $unit->permite_fracciones : true;
    }

    private function assertIntegerQuantity(mixed $value, string $field): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! preg_match('/^\d+$/', trim((string) $value))) {
            throw ValidationException::withMessages([
                $field => 'La unidad base no permite fracciones; captura una cantidad entera.',
            ]);
        }
    }

    private function unitAbbreviation(?string $code, ?string $name): string
    {
        $code = trim((string) $code);
        if ($code !== '') {
            return $code;
        }

        $normalizedName = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii(trim((string) $name)));
        $knownAbbreviations = [
            'kilogramo' => 'kg', 'kilogramos' => 'kg',
            'gramo' => 'g', 'gramos' => 'g',
            'litro' => 'L', 'litros' => 'L',
            'mililitro' => 'ml', 'mililitros' => 'ml',
            'pieza' => 'pza', 'piezas' => 'pza',
            'unidad' => 'ud', 'unidades' => 'ud',
        ];

        if (isset($knownAbbreviations[$normalizedName])) {
            return $knownAbbreviations[$normalizedName];
        }

        $words = preg_split('/[^a-z0-9]+/', $normalizedName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) > 1) {
            return implode('', array_map(static fn (string $word): string => substr($word, 0, 1), $words));
        }

        return substr($normalizedName, 0, 3) ?: 'unid.';
    }

    private function formatQuantity(mixed $value): string
    {
        $formatted = number_format((float) $value, 3, '.', ',');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    private function initialEntryRules(bool $allowsFractions): array
    {
        return [
            'cantidad_inicial' => array_merge(
                ['required'],
                $this->quantityPrecisionRules($allowsFractions),
                ['gt:0', 'max:999999999.999'],
            ),
            'fecha_entrada' => ['required', 'date_format:Y-m-d'],
            'motivo_inicial' => ['required', 'string', 'max:1000'],
            'ubicacion' => ['required', 'string', 'max:20'],
            'nueva_ubicacion' => ['nullable', 'string', 'max:100', 'required_if:ubicacion,nueva'],
            'codigo_lote' => ['nullable', 'string', 'max:100'],
            'fecha_caducidad' => ['nullable', 'date'],
            'referencia' => ['nullable', 'string', 'max:100'],
        ];
    }

    private function persistInitialEntry(array $validated, int $ingredientId, int $siteId, int $userId): string
    {
        $quantity = $this->toThousandths($validated['cantidad_inicial']);
        if ($quantity <= 0 || $quantity > self::MAX_THOUSANDTHS) {
            throw ValidationException::withMessages([
                'cantidad_inicial' => 'La existencia inicial debe ser positiva y estar dentro del límite permitido.',
            ]);
        }

        $locationId = $this->resolveLocation($validated, $siteId);
        $lotId = $this->resolveMovementLot($validated, 'entrada', $ingredientId, $siteId, $quantity);
        $entryDate = \Illuminate\Support\Carbon::parse($validated['fecha_entrada'], self::LOCAL_TIMEZONE)
            ->startOfDay()
            ->utc()
            ->format('Y-m-d H:i:s');

        DB::table('inventarios_finales')->insert([
            'id_ubicacion' => $locationId,
            'id_lote' => $lotId,
            'id_usuario' => $userId,
            'fecha' => $entryDate,
            'tipo' => 'entrada',
            'cantidad_firmada' => $this->fromThousandths($quantity),
            'referencia' => $validated['referencia'] ?? null,
            'observaciones' => trim($validated['motivo_inicial']),
        ]);

        return $this->fromThousandths($quantity);
    }

    private function resolveLocation(array $validated, int $siteId): int
    {
        if ($validated['ubicacion'] !== 'nueva') {
            $location = DB::table('ubicacion_inventarios')
                ->where('id_ubicacion_inv', (int) $validated['ubicacion'])
                ->where('id_sede', $siteId)
                ->lockForUpdate()
                ->first();

            if (! $location) {
                throw ValidationException::withMessages([
                    'ubicacion' => 'Selecciona una ubicación perteneciente a la sede activa.',
                ]);
            }

            return (int) $location->id_ubicacion_inv;
        }

        $name = trim((string) ($validated['nueva_ubicacion'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['nueva_ubicacion' => 'Escribe el nombre de la ubicación.']);
        }

        $location = DB::table('ubicacion_inventarios')
            ->where('id_sede', $siteId)
            ->where('nombre', $name)
            ->lockForUpdate()
            ->first();

        if ($location) {
            return (int) $location->id_ubicacion_inv;
        }

        return (int) DB::table('ubicacion_inventarios')->insertGetId([
            'id_sede' => $siteId,
            'nombre' => $name,
        ]);
    }

    private function resolveMovementLot(array $validated, string $type, int $ingredientId, int $siteId, int $signedQuantity): int
    {
        if ($type !== 'entrada' && ! empty($validated['id_lote'])) {
            $lot = DB::table('lotes')
                ->where('id_lote', (int) $validated['id_lote'])
                ->where('id_insumo', $ingredientId)
                ->first();

            $belongsToSite = $lot && DB::table('inventarios_finales as movimiento')
                ->join('ubicacion_inventarios as ubicacion', 'ubicacion.id_ubicacion_inv', '=', 'movimiento.id_ubicacion')
                ->where('movimiento.id_lote', (int) $validated['id_lote'])
                ->where('ubicacion.id_sede', $siteId)
                ->exists();

            if (! $belongsToSite) {
                throw ValidationException::withMessages([
                    'id_lote' => 'Selecciona un lote del insumo que tenga movimientos en la sede activa.',
                ]);
            }

            if ($signedQuantity < 0) {
                $lotStock = DB::table('inventarios_finales as movimiento')
                    ->join('ubicacion_inventarios as ubicacion', 'ubicacion.id_ubicacion_inv', '=', 'movimiento.id_ubicacion')
                    ->where('movimiento.id_lote', (int) $validated['id_lote'])
                    ->where('ubicacion.id_sede', $siteId)
                    ->sum('movimiento.cantidad_firmada');

                if ($this->toThousandths($lotStock) < abs($signedQuantity)) {
                    throw ValidationException::withMessages([
                        'cantidad' => 'La cantidad supera el saldo del lote seleccionado. Usa otro lote o registra el movimiento sin lote identificado.',
                    ]);
                }
            }

            return (int) $lot->id_lote;
        }

        $supplierId = null;
        if ($type === 'entrada' && ! empty($validated['id_proveedor'])) {
            $supplierId = (int) $validated['id_proveedor'];
            if (! Schema::hasTable('proveedores') || ! DB::table('proveedores')->where('id_proveedor', $supplierId)->exists()) {
                throw ValidationException::withMessages(['id_proveedor' => 'Selecciona un proveedor válido.']);
            }
        }

        $lotCode = $type === 'entrada' ? trim((string) ($validated['codigo_lote'] ?? '')) : '';

        return (int) DB::table('lotes')->insertGetId([
            'id_insumo' => $ingredientId,
            'id_proveedor' => $supplierId,
            'codigo_lote' => $lotCode !== '' ? $lotCode : null,
            'fecha_caducidad' => $type === 'entrada' ? ($validated['fecha_caducidad'] ?? null) : null,
        ]);
    }

    private function toThousandths(mixed $value): int
    {
        $value = trim((string) $value);
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $milli = ((int) $whole * 1000) + (int) str_pad(substr($fraction, 0, 3), 3, '0');

        return $negative ? -$milli : $milli;
    }

    private function fromThousandths(int $value): string
    {
        $absolute = abs($value);
        $formatted = intdiv($absolute, 1000).'.'.str_pad((string) ($absolute % 1000), 3, '0', STR_PAD_LEFT);

        return $value < 0 ? '-'.$formatted : $formatted;
    }
}
