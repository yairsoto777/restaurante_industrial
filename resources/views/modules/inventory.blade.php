@extends('layouts.app')
@section('title', 'Inventario de cocina')
@section('content')
@php
    $inventoryUnitAbbreviation = static function ($code, $name): string {
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
    };
    $formatInventoryNumber = static function ($value): string {
        $formatted = number_format((float) $value, 3, '.', ',');

        return rtrim(rtrim($formatted, '0'), '.');
    };
    $formatInventoryQuantity = static function ($value, $code, $name) use ($inventoryUnitAbbreviation, $formatInventoryNumber): string {
        if ($value === null || $value === '') {
            return 'Sin dato';
        }

        return $formatInventoryNumber($value).' '.$inventoryUnitAbbreviation($code, $name);
    };
@endphp
<div class="inventory-page">
    <section class="inventory-hero inventory-sheet-heading" aria-labelledby="inventory-title">
        <div class="inventory-hero-copy">
            <span class="inventory-eyebrow">{{ $site->nombre }}</span>
            <h1 id="inventory-title">Inventario de cocina</h1>
            <p class="inventory-hero-description">{{ $canManageInventory ? 'Consulta y control de existencias de la sede activa.' : 'Consulta las existencias reales de la sede. Reporta un faltante para que el equipo responsable pueda atenderlo.' }}</p>
        </div>
        @if(auth()->user()->hasRole('Personal de cocina'))
            <a class="inventory-button inventory-button-primary inventory-shortage-action" href="{{ route('incidents.index') }}#registrar-incidencia"><x-icon name="alert" />Reportar faltante o problema</a>
        @endif
    </section>

    <section class="inventory-count-panel" aria-labelledby="inventory-count-title">
        <div class="inventory-count-heading">
            <span class="inventory-panel-kicker">Registro de consulta</span>
            <h2 id="inventory-count-title">Datos del conteo</h2>
        </div>
        <div class="inventory-sheet-meta">
            <div><small>Establecimiento</small><strong>{{ $site->nombre }}</strong></div>
            <div><small>Departamento</small><strong>Cocina</strong></div>
            <div><small>Fecha</small><strong>{{ $inventoryDate->format('d/m/Y') }}</strong></div>
            <div><small>Empleado</small><strong>{{ $employeeName ?: 'Usuario autenticado' }}</strong></div>
        </div>
    </section>

    <section class="inventory-summary-grid" aria-label="Indicadores de inventario en {{ $site->nombre }}">
        <article class="inventory-summary-card inventory-summary-card-blue">
            <span class="inventory-summary-icon inventory-summary-icon-blue" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5v-9Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="m4.4 7.7 7.6 4.4 7.6-4.4M12 12.2V21" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
            </span>
            <div class="inventory-summary-copy">
                <span>Insumos registrados</span>
                <strong>{{ $registeredCount }}</strong>
                <small>{{ $registeredCount > 0 ? 'Asignados a la sede activa.' : 'El Jefe de cocina debe asignar insumos a esta sede.' }}</small>
            </div>
        </article>
        <article class="inventory-summary-card inventory-summary-card-amber">
            <span class="inventory-summary-icon inventory-summary-icon-orange" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><path d="M12 3.5 21 20H3l9-16.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M12 9v4.5M12 17h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </span>
            <div class="inventory-summary-copy">
                <span>Existencias bajas</span>
                <strong>{{ $lowCount }}</strong>
                <small>{{ $registeredCount === 0 ? 'El conteo inicia al asignar insumos.' : ($lowCount > 0 ? 'En o bajo el mínimo de reorden.' : 'Ninguna existencia está bajo su mínimo.') }}</small>
            </div>
        </article>
        <article class="inventory-summary-card inventory-summary-card-red">
            <span class="inventory-summary-icon inventory-summary-icon-red" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.8"/><path d="M12 7.5v5M12 16.5h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </span>
            <div class="inventory-summary-copy">
                <span>Agotados</span>
                <strong>{{ $depletedCount }}</strong>
                <small>{{ $registeredCount === 0 ? 'El Jefe de cocina registra el saldo inicial.' : ($depletedCount > 0 ? 'Con existencia igual o menor que cero.' : 'No hay existencias en cero.') }}</small>
            </div>
        </article>
        <article class="inventory-summary-card inventory-summary-card-purple">
            <span class="inventory-summary-icon inventory-summary-icon-purple" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.8"/><path d="M12 7v5l3.2 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <div class="inventory-summary-copy">
                <span>Próximos a caducar</span>
                <strong>{{ $expiryAvailable ? $expiringCount : '—' }}</strong>
                <small>{{ ! $expiryAvailable ? 'La base no permite consultar fechas de caducidad.' : ($registeredCount === 0 ? 'El Jefe de cocina debe registrar lotes con fecha.' : 'Insumos con lotes que vencen en '.$soonDays.' días.') }}</small>
            </div>
        </article>
    </section>

    <section class="inventory-panel inventory-sheet-panel" aria-labelledby="inventory-list-title">
        <div class="inventory-panel-heading inventory-sheet-toolbar">
            <div>
                <span class="inventory-panel-kicker">{{ $site->nombre }}</span>
                <h2 id="inventory-list-title">Existencias por insumo</h2>
            </div>
            <span class="inventory-count">{{ $registeredCount }} {{ $registeredCount === 1 ? 'insumo' : 'insumos' }}</span>
        </div>

        @if($canManageInventory && $inventoryAvailable)
            <div class="inventory-header-actions" aria-label="Operaciones de inventario">
                <button class="inventory-button inventory-button-primary" type="button" data-inventory-open="inventory-catalog-dialog">Agregar insumo</button>
                <button class="inventory-button inventory-button-light" type="button" data-inventory-open="inventory-assignment-dialog" @disabled($unassignedIngredients->isEmpty())>Asignar del catálogo</button>
                @if($operationsAvailable)
                    <button class="inventory-button" type="button" data-inventory-open="inventory-movement-dialog" data-inventory-type="entrada" @disabled($ingredients->isEmpty())>Registrar entrada</button>
                    <button class="inventory-button" type="button" data-inventory-open="inventory-movement-dialog" data-inventory-type="salida" @disabled($ingredients->isEmpty())>Salida / consumo</button>
                    <button class="inventory-button" type="button" data-inventory-open="inventory-movement-dialog" data-inventory-type="merma" @disabled($ingredients->isEmpty())>Registrar merma</button>
                    <button class="inventory-button inventory-button-light" type="button" data-inventory-open="inventory-movement-dialog" data-inventory-type="ajuste" @disabled($ingredients->isEmpty())>Ajustar cantidad</button>
                @endif
            </div>
            @if($catalogUnits->isEmpty())
                <p class="inventory-unavailable-note">No hay unidades en el catálogo; hace falta una unidad existente para crear insumos.</p>
            @elseif($registeredCount > 0 && $ingredients->isEmpty())
                <p class="inventory-unavailable-note">Los insumos asignados no tienen una unidad base válida. Se ocultan las operaciones hasta corregir la relación del catálogo.</p>
            @elseif($ingredients->isEmpty() && $unassignedIngredients->isNotEmpty())
                <p class="inventory-unavailable-note">Asigna primero un insumo del catálogo a esta sede para habilitar movimientos.</p>
            @elseif(! $operationsAvailable)
                <p class="inventory-unavailable-note">Las tablas de lotes, ubicaciones y movimientos no están disponibles.</p>
            @endif
        @elseif($canRegisterIngredients && $inventoryAvailable)
            <div class="inventory-header-actions" aria-label="Registro de insumos">
                <button class="inventory-button inventory-button-primary" type="button" data-inventory-open="inventory-catalog-dialog">Registrar insumo y entrada</button>
            </div>
            @if($catalogUnits->isEmpty())
                <p class="inventory-unavailable-note">No hay unidades configuradas. El Jefe de cocina debe agregarlas en Inventario → Administrar unidades antes de registrar un insumo.</p>
            @elseif(! $operationsAvailable)
                <p class="inventory-unavailable-note">Las tablas de lotes, ubicaciones y movimientos no están disponibles para guardar la entrada inicial.</p>
            @endif
        @endif

        @if($inventoryAvailable)
            <form class="inventory-filters" method="GET" action="{{ route('inventory.index') }}">
                <label class="inventory-search">
                    <span class="inventory-sr-only">Buscar insumo, unidad o ubicación registrada en movimientos</span>
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8" stroke="currentColor" stroke-width="1.8"/><path d="m16 16 4.2 4.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    <input type="search" name="q" value="{{ $search }}" placeholder="Buscar producto, unidad o ubicación…">
                </label>
                <label class="inventory-filter-field"><span>Estado</span>
                    <select name="estado">
                        <option value="">Todos</option>
                        <option value="suficiente" @selected($filterState === 'suficiente')>Suficiente</option>
                        <option value="bajo" @selected($filterState === 'bajo')>Bajo / reponer</option>
                        <option value="agotado" @selected($filterState === 'agotado')>Agotado</option>
                        <option value="sin-minimo" @selected($filterState === 'sin-minimo')>Sin mínimo</option>
                        <option value="sin-dato" @selected($filterState === 'sin-dato')>Sin existencia definida</option>
                        @if($expiryAvailable)<option value="por-caducar" @selected($filterState === 'por-caducar')>Por caducar</option>@endif
                    </select>
                </label>
                @if($units->isNotEmpty())
                    <label class="inventory-filter-field"><span>Unidad</span>
                        <select name="unidad">
                            <option value="">Todas</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id_unidad }}" @selected((string) $unit->id_unidad === $filterUnit)>{{ $unit->nombre }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                @if($historyAvailable && $locations->isNotEmpty())
                    <label class="inventory-filter-field"><span>Ubicación de movimiento</span>
                        <select name="ubicacion_filtro">
                            <option value="">Todas</option>
                            @foreach($locations as $location)
                                <option value="{{ $location->id_ubicacion_inv }}" @selected((string) $location->id_ubicacion_inv === $filterLocation)>{{ $location->nombre }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <button class="inventory-button inventory-filter-submit" type="submit">Filtrar</button>
                @if($search !== '' || $filterState !== '' || $filterUnit !== '' || $filterLocation !== '')
                    <a class="inventory-filter-clear" href="{{ route('inventory.index') }}">Limpiar</a>
                @endif
            </form>
        @endif

        <p class="inventory-data-note">La existencia corresponde al saldo asignado a esta sede. @if($historyAvailable)La ubicación, cuando aparece, proviene de movimientos registrados.@endif</p>

        @php($inventoryColumnCount = 5 + ($historyAvailable ? 1 : 0) + ($expiryAvailable ? 1 : 0) + ($canManageInventory ? 1 : 0))
        <p class="inventory-scroll-hint">En pantallas pequeñas, desliza la tabla horizontalmente para ver todas las columnas.</p>
        <div class="inventory-table-wrap">
            <table class="inventory-table inventory-sheet-table">
                <caption class="inventory-sr-only">Insumos asignados a {{ $site->nombre }} con sus cantidades y estados.</caption>
                <thead><tr>
                    <th scope="col">Producto</th>
                    <th scope="col">Unidad</th>
                    <th scope="col">Cantidad</th>
                    @if($historyAvailable)<th scope="col">Ubicación de movimiento</th>@endif
                    <th scope="col">Mínimo reorden</th>
                    @if($expiryAvailable)<th scope="col">Caducidad</th>@endif
                    <th scope="col">Estado</th>
                    @if($canManageInventory)<th scope="col">Acciones</th>@endif
                </tr></thead>
                <tbody>
                    @if(! $inventoryAvailable)
                        <tr><td colspan="{{ $inventoryColumnCount }}"><div class="inventory-table-empty"><strong>Inventario no disponible</strong><span>Faltan tablas o columnas requeridas en la base conectada.</span></div></td></tr>
                    @elseif($rows->isEmpty())
                        <tr>
                            <td colspan="{{ $inventoryColumnCount }}">
                                <div class="inventory-table-empty">
                                    @if($search !== '' || $filterState !== '' || $filterUnit !== '' || $filterLocation !== '')
                                        <strong>No hay resultados para estos filtros</strong>
                                        <span>Prueba otro término o limpia los filtros para volver a ver la hoja.</span>
                                        <a class="inventory-filter-clear" href="{{ route('inventory.index') }}">Limpiar filtros</a>
                                    @elseif($registeredCount === 0 && $canManageInventory && $unassignedIngredients->isNotEmpty())
                                        <strong>No hay insumos asignados a esta sede</strong>
                                        <span>Asigna un producto del catálogo e indica su existencia inicial para registrar la entrada en esta sede.</span>
                                        <button class="inventory-button inventory-button-primary inventory-empty-action" type="button" data-inventory-open="inventory-assignment-dialog">Asignar primer insumo</button>
                                    @elseif($registeredCount === 0 && $catalogUnits->isEmpty())
                                        <strong>No hay unidades de medida configuradas</strong>
                                        <span>Hace falta una unidad real del catálogo compartido. Solicita a un Jefe de cocina, Administrador o Superadministrador que la registre en Inventario → Administrar unidades; después actualiza esta pantalla.</span>
                                        @if(auth()->user()->hasRole('Jefe de cocina'))<a class="inventory-button inventory-button-primary inventory-empty-action" href="{{ route('admin.units.index') }}">Administrar unidades</a>@endif
                                        @if(auth()->user()->hasRole('Personal de cocina'))<a class="inventory-button inventory-button-primary inventory-empty-action" href="{{ route('incidents.index') }}#registrar-incidencia">Reportar faltante o problema</a>@endif
                                    @elseif($registeredCount === 0 && $canRegisterIngredients && $catalogCount === 0 && $catalogUnits->isNotEmpty())
                                        <strong>El catálogo está vacío</strong>
                                        <span>Registra un insumo con una unidad existente y captura su entrada inicial para esta sede.</span>
                                        <button class="inventory-button inventory-button-primary inventory-empty-action" type="button" data-inventory-open="inventory-catalog-dialog">Registrar primer insumo</button>
                                    @elseif($registeredCount === 0 && $canManageInventory && $ingredients->isEmpty() && $catalogUnits->isNotEmpty())
                                        <strong>No hay insumos con unidad base válida</strong>
                                        <span>El catálogo contiene {{ $catalogCount }} {{ $catalogCount === 1 ? 'insumo' : 'insumos' }}, pero hace falta una unidad válida para asignarlos.</span>
                                        <button class="inventory-button inventory-button-primary inventory-empty-action" type="button" data-inventory-open="inventory-catalog-dialog">Revisar alta de insumo</button>
                                    @elseif($registeredCount === 0)
                                        <strong>No hay insumos registrados para esta sede</strong>
                                        <span>Solicita al Jefe de cocina que agregue un insumo o asigne uno existente a esta sede.</span>
                                    @else
                                        <strong>No hay resultados para estos filtros</strong>
                                        <span>Prueba otro término o limpia los filtros para volver a ver la hoja.</span>
                                        <a class="inventory-filter-clear" href="{{ route('inventory.index') }}">Limpiar filtros</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @else
                        @foreach($rows as $row)
                            <tr>
                                <td data-label="Producto"><strong class="inventory-item-name">{{ $row->insumo }}</strong></td>
                                <td data-label="Unidad">{{ $row->unidad ?? 'Sin unidad' }}</td>
                                <td data-label="Cantidad"><span class="inventory-stock-value">{{ $formatInventoryQuantity($row->stock, $row->unidad_codigo, $row->unidad) }}</span></td>
                                @if($historyAvailable)
                                    <td data-label="Ubicación de movimiento"><span class="inventory-location-value">{{ $row->ubicaciones_movimiento ?: 'Sin ubicación registrada' }}</span></td>
                                @endif
                                <td data-label="Mínimo reorden">{{ $row->punto_de_reorden === null ? 'Sin definir' : $formatInventoryQuantity($row->punto_de_reorden, $row->unidad_codigo, $row->unidad) }}</td>
                                @if($expiryAvailable)
                                    <td data-label="Caducidad">
                                        @if($row->stock !== null && (float) $row->stock > 0 && $row->fecha_caducidad)
                                            @php($expiryDate = \Illuminate\Support\Carbon::parse($row->fecha_caducidad))
                                            @if($expiryDate->isBefore($inventoryToday))
                                                <span class="inventory-expiry inventory-expiry-past">Vencido · {{ $expiryDate->format('d/m/Y') }}</span>
                                            @elseif($expiryDate->lte($inventoryToday->copy()->addDays($soonDays)))
                                                <span class="inventory-expiry inventory-expiry-soon">Próximo a caducar · {{ $expiryDate->format('d/m/Y') }}</span>
                                            @else
                                                <span class="inventory-expiry">{{ $expiryDate->format('d/m/Y') }}</span>
                                            @endif
                                        @else
                                            <span class="inventory-muted">Sin caducidad registrada</span>
                                        @endif
                                    </td>
                                @endif
                                <td data-label="Estado">
                                    <div class="inventory-status-stack">
                                        @if($row->stock === null)
                                            <span class="inventory-status inventory-status-undefined">Sin conteo</span>
                                        @elseif((float) $row->stock <= 0)
                                            <span class="inventory-status inventory-status-empty"><span aria-hidden="true"></span>Agotado</span>
                                            @if($row->punto_de_reorden !== null && (float) $row->stock <= (float) $row->punto_de_reorden)
                                                <span class="inventory-status inventory-status-reorder"><span aria-hidden="true"></span>Reponer</span>
                                            @endif
                                        @elseif($row->punto_de_reorden !== null && (float) $row->stock <= (float) $row->punto_de_reorden)
                                            <span class="inventory-status inventory-status-reorder"><span aria-hidden="true"></span>Bajo · reponer</span>
                                        @elseif($row->punto_de_reorden === null)
                                            <span class="inventory-status inventory-status-undefined">Sin mínimo</span>
                                        @else
                                            <span class="inventory-status inventory-status-ok"><span aria-hidden="true"></span>Suficiente</span>
                                        @endif
                                    </div>
                                </td>
                                @if($canManageInventory)
                                    <td data-label="Acciones">
                                        <div class="inventory-row-actions">
                                            <button class="inventory-row-action" type="button"
                                                data-inventory-reorder-open
                                                data-id="{{ $row->id_insumo }}"
                                                data-name="{{ $row->insumo }}"
                                                data-minimum="{{ $row->punto_de_reorden === null ? '' : $formatInventoryNumber($row->punto_de_reorden) }}"
                                                data-unit-abbreviation="{{ $inventoryUnitAbbreviation($row->unidad_codigo, $row->unidad) }}"
                                                data-unit-name="{{ $row->unidad }}"
                                                data-allows-fractions="{{ $row->permite_fracciones ? '1' : '0' }}"
                                                data-action="{{ route('inventory.reorder.update', ['ingredient' => $row->id_insumo]) }}">Editar mínimo</button>
                                            <a class="inventory-row-action" href="{{ route('inventory.index', ['historial' => $row->id_insumo]) }}">Historial</a>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>
        @if(method_exists($rows, 'links') && $rows->hasPages())
            <div class="inventory-pagination">{{ $rows->links() }}</div>
        @endif
    </section>
</div>
@if($canRegisterIngredients && $inventoryAvailable)
    <dialog class="inventory-dialog inventory-dialog-compact" id="inventory-catalog-dialog" aria-labelledby="inventory-catalog-title"
        @if(old('_inventory_form') === 'catalog' || request()->boolean('abrir_catalogo')) data-inventory-autoshow @endif>
        <div class="inventory-dialog-heading">
            <div><span class="inventory-panel-kicker">Catálogo compartido</span><h2 id="inventory-catalog-title">Agregar insumo</h2></div>
            <button class="inventory-dialog-x" type="button" data-inventory-close aria-label="Cerrar">×</button>
        </div>
        @if($catalogUnits->isEmpty())
            <p class="inventory-dialog-notice" role="status">El catálogo de unidades está vacío. Solicita a un Jefe de cocina, Administrador o Superadministrador que registre una unidad desde Administración → Catálogo de unidades o Inventario → Administrar unidades; después actualiza este formulario.</p>
        @elseif(! $operationsAvailable)
            <p class="inventory-dialog-notice" role="status">No se puede guardar hasta que estén disponibles las tablas de lotes, ubicaciones y movimientos de inventario.</p>
        @else
            <p class="inventory-dialog-notice">Se creará el insumo del catálogo, se asignará a {{ $site->nombre }} y se registrará la existencia inicial como entrada en una sola transacción.</p>
        @endif
        <form method="POST" action="{{ route('inventory.ingredients.store') }}" class="inventory-dialog-form" data-inventory-catalog-form data-inventory-initial-form data-inventory-unit-form>
            @csrf
            <input type="hidden" name="_inventory_form" value="catalog">
            <label class="inventory-form-field inventory-form-field-full"><span>Nombre del insumo</span>
                <input type="text" name="nombre" maxlength="150" value="{{ old('_inventory_form') === 'catalog' ? old('nombre') : '' }}" required @disabled($catalogUnits->isEmpty() || ! $operationsAvailable)>
            </label>
            <label class="inventory-form-field inventory-form-field-full"><span>Unidad base</span>
                <select name="id_unidad_base" required data-inventory-unit-select @disabled($catalogUnits->isEmpty() || ! $operationsAvailable)>
                    <option value="">Selecciona una unidad existente</option>
                    @foreach($catalogUnits as $unit)
                        <option value="{{ $unit->id_unidad }}"
                            data-unit-abbreviation="{{ $inventoryUnitAbbreviation($unit->codigo, $unit->nombre) }}"
                            data-unit-name="{{ $unit->nombre }}"
                            data-allows-fractions="{{ $unit->permite_fracciones ? '1' : '0' }}"
                            @selected(old('_inventory_form') === 'catalog' && (string) old('id_unidad_base') === (string) $unit->id_unidad)>{{ $unit->nombre }}{{ $unit->codigo ? ' · '.$unit->codigo : '' }}</option>
                    @endforeach
                </select>
            </label>
            <label class="inventory-form-field"><span>Existencia inicial <small>(entrada en <span data-inventory-unit-name>la unidad seleccionada</span>)</small></span>
                <div class="inventory-quantity-input">
                    <input type="number" name="cantidad_inicial" min="0.001" max="999999999.999" step="0.001" value="{{ old('_inventory_form') === 'catalog' ? old('cantidad_inicial') : '' }}" inputmode="decimal" required data-inventory-unit-quantity data-positive-quantity @disabled($catalogUnits->isEmpty() || ! $operationsAvailable)>
                    <span class="inventory-quantity-suffix" data-inventory-unit-suffix aria-live="polite">—</span>
                </div>
            </label>
            <label class="inventory-form-field"><span>Fecha de entrada</span>
                <input type="date" name="fecha_entrada" value="{{ old('_inventory_form') === 'catalog' ? old('fecha_entrada', $inventoryDate->toDateString()) : $inventoryDate->toDateString() }}" required @disabled($catalogUnits->isEmpty() || ! $operationsAvailable)>
            </label>
            <label class="inventory-form-field"><span>Mínimo de reorden <small>(opcional · <span data-inventory-unit-name>la unidad seleccionada</span>)</small></span>
                <div class="inventory-quantity-input">
                    <input type="number" name="punto_de_reorden" min="0" max="999999999.999" step="0.001" value="{{ old('_inventory_form') === 'catalog' ? old('punto_de_reorden') : '' }}" inputmode="decimal" data-inventory-unit-quantity @disabled($catalogUnits->isEmpty() || ! $operationsAvailable)>
                    <span class="inventory-quantity-suffix" data-inventory-unit-suffix aria-live="polite">—</span>
                </div>
            </label>
            <label class="inventory-form-field inventory-form-field-full"><span>Ubicación del inventario</span>
                <select name="ubicacion" required data-inventory-initial-location @disabled($catalogUnits->isEmpty() || ! $operationsAvailable)>
                    @foreach($locations as $location)
                        <option value="{{ $location->id_ubicacion_inv }}" @selected(old('_inventory_form') === 'catalog' && (string) old('ubicacion') === (string) $location->id_ubicacion_inv)>{{ $location->nombre }}</option>
                    @endforeach
                    <option value="nueva" @selected(old('_inventory_form') === 'catalog' ? old('ubicacion', $locations->isEmpty() ? 'nueva' : '') === 'nueva' : $locations->isEmpty())>Crear ubicación para esta sede…</option>
                </select>
            </label>
            <label class="inventory-form-field inventory-form-field-full" data-inventory-initial-new-location><span>Nombre de ubicación</span>
                <input type="text" name="nueva_ubicacion" maxlength="100" value="{{ old('_inventory_form') === 'catalog' ? old('nueva_ubicacion') : '' }}" placeholder="Ej. Almacén de cocina" @required($locations->isEmpty() || (old('_inventory_form') === 'catalog' && old('ubicacion') === 'nueva')) @disabled($catalogUnits->isEmpty() || ! $operationsAvailable)>
            </label>
            <label class="inventory-form-field inventory-form-field-full"><span>Motivo de la entrada inicial</span>
                <textarea name="motivo_inicial" rows="3" maxlength="1000" required placeholder="Ej. Conteo inicial de apertura" @disabled($catalogUnits->isEmpty() || ! $operationsAvailable)>{{ old('_inventory_form') === 'catalog' ? old('motivo_inicial') : '' }}</textarea>
            </label>
            <div class="inventory-entry-lot inventory-form-field-full">
                <p class="inventory-entry-lot-title">Datos del lote <small>opcionales si están disponibles</small></p>
                <label class="inventory-form-field"><span>Código de lote</span><input type="text" name="codigo_lote" maxlength="100" value="{{ old('_inventory_form') === 'catalog' ? old('codigo_lote') : '' }}" @disabled($catalogUnits->isEmpty() || ! $operationsAvailable)></label>
                <label class="inventory-form-field"><span>Fecha de caducidad</span><input type="date" name="fecha_caducidad" value="{{ old('_inventory_form') === 'catalog' ? old('fecha_caducidad') : '' }}" @disabled($catalogUnits->isEmpty() || ! $operationsAvailable)></label>
            </div>
            <div class="inventory-dialog-actions inventory-form-field-full">
                <button class="inventory-button" type="button" data-inventory-close>Cancelar</button>
                <button class="inventory-button inventory-button-primary" type="submit" @disabled($catalogUnits->isEmpty() || ! $operationsAvailable)>Crear, asignar y registrar entrada</button>
            </div>
        </form>
    </dialog>

    <dialog class="inventory-dialog inventory-dialog-compact" id="inventory-assignment-dialog" aria-labelledby="inventory-assignment-title"
        @if(old('_inventory_form') === 'assignment') data-inventory-autoshow @endif>
        <div class="inventory-dialog-heading">
            <div><span class="inventory-panel-kicker">{{ $site->nombre }}</span><h2 id="inventory-assignment-title">Asignar del catálogo</h2></div>
            <button class="inventory-dialog-x" type="button" data-inventory-close aria-label="Cerrar">×</button>
        </div>
        @if($unassignedIngredients->isEmpty())
            <p class="inventory-dialog-notice" role="status">No hay insumos del catálogo pendientes de asignar a esta sede.</p>
        @elseif(! $operationsAvailable)
            <p class="inventory-dialog-notice" role="status">No se puede guardar hasta que estén disponibles las tablas de lotes, ubicaciones y movimientos de inventario.</p>
        @else
            <p class="inventory-dialog-notice">Elige un insumo existente; se asignará a {{ $site->nombre }} y su saldo inicial quedará respaldado por una entrada.</p>
        @endif
        <form method="POST" action="{{ route('inventory.assignments.store') }}" class="inventory-dialog-form" data-inventory-initial-form data-inventory-unit-form>
            @csrf
            <input type="hidden" name="_inventory_form" value="assignment">
            <label class="inventory-form-field inventory-form-field-full"><span>Insumo del catálogo</span>
                <select name="id_insumo" required data-inventory-unit-select @disabled($unassignedIngredients->isEmpty() || ! $operationsAvailable)>
                    <option value="">Selecciona un insumo</option>
                    @foreach($unassignedIngredients as $ingredient)
                        <option value="{{ $ingredient->id_insumo }}"
                            data-unit-abbreviation="{{ $inventoryUnitAbbreviation($ingredient->unidad_codigo, $ingredient->unidad) }}"
                            data-unit-name="{{ $ingredient->unidad }}"
                            data-allows-fractions="{{ $ingredient->permite_fracciones ? '1' : '0' }}"
                            @selected(old('_inventory_form') === 'assignment' && (string) old('id_insumo') === (string) $ingredient->id_insumo)>{{ $ingredient->nombre }}{{ $ingredient->unidad ? ' · '.$ingredient->unidad : '' }}{{ $ingredient->unidad_codigo ? ' ('.$ingredient->unidad_codigo.')' : '' }}</option>
                    @endforeach
                </select>
            </label>
            <label class="inventory-form-field"><span>Existencia inicial <small>(entrada en <span data-inventory-unit-name>la unidad del insumo</span>)</small></span>
                <div class="inventory-quantity-input">
                    <input type="number" name="cantidad_inicial" min="0.001" max="999999999.999" step="0.001" value="{{ old('_inventory_form') === 'assignment' ? old('cantidad_inicial') : '' }}" inputmode="decimal" required data-inventory-unit-quantity data-positive-quantity @disabled($unassignedIngredients->isEmpty() || ! $operationsAvailable)>
                    <span class="inventory-quantity-suffix" data-inventory-unit-suffix aria-live="polite">—</span>
                </div>
            </label>
            <label class="inventory-form-field"><span>Fecha de entrada</span>
                <input type="date" name="fecha_entrada" value="{{ old('_inventory_form') === 'assignment' ? old('fecha_entrada', $inventoryDate->toDateString()) : $inventoryDate->toDateString() }}" required @disabled($unassignedIngredients->isEmpty() || ! $operationsAvailable)>
            </label>
            <label class="inventory-form-field"><span>Mínimo de reorden <small>(opcional · <span data-inventory-unit-name>la unidad del insumo</span>)</small></span>
                <div class="inventory-quantity-input">
                    <input type="number" name="punto_de_reorden" min="0" max="999999999.999" step="0.001" value="{{ old('_inventory_form') === 'assignment' ? old('punto_de_reorden') : '' }}" inputmode="decimal" data-inventory-unit-quantity @disabled($unassignedIngredients->isEmpty() || ! $operationsAvailable)>
                    <span class="inventory-quantity-suffix" data-inventory-unit-suffix aria-live="polite">—</span>
                </div>
            </label>
            <label class="inventory-form-field inventory-form-field-full"><span>Ubicación del inventario</span>
                <select name="ubicacion" required data-inventory-initial-location @disabled($unassignedIngredients->isEmpty() || ! $operationsAvailable)>
                    @foreach($locations as $location)
                        <option value="{{ $location->id_ubicacion_inv }}" @selected(old('_inventory_form') === 'assignment' && (string) old('ubicacion') === (string) $location->id_ubicacion_inv)>{{ $location->nombre }}</option>
                    @endforeach
                    <option value="nueva" @selected(old('_inventory_form') === 'assignment' ? old('ubicacion', $locations->isEmpty() ? 'nueva' : '') === 'nueva' : $locations->isEmpty())>Crear ubicación para esta sede…</option>
                </select>
            </label>
            <label class="inventory-form-field inventory-form-field-full" data-inventory-initial-new-location><span>Nombre de ubicación</span>
                <input type="text" name="nueva_ubicacion" maxlength="100" value="{{ old('_inventory_form') === 'assignment' ? old('nueva_ubicacion') : '' }}" placeholder="Ej. Almacén de cocina" @required($locations->isEmpty() || (old('_inventory_form') === 'assignment' && old('ubicacion') === 'nueva')) @disabled($unassignedIngredients->isEmpty() || ! $operationsAvailable)>
            </label>
            <label class="inventory-form-field inventory-form-field-full"><span>Motivo de la entrada inicial</span>
                <textarea name="motivo_inicial" rows="3" maxlength="1000" required placeholder="Ej. Conteo inicial de apertura" @disabled($unassignedIngredients->isEmpty() || ! $operationsAvailable)>{{ old('_inventory_form') === 'assignment' ? old('motivo_inicial') : '' }}</textarea>
            </label>
            <div class="inventory-entry-lot inventory-form-field-full">
                <p class="inventory-entry-lot-title">Datos del lote <small>opcionales si están disponibles</small></p>
                <label class="inventory-form-field"><span>Código de lote</span><input type="text" name="codigo_lote" maxlength="100" value="{{ old('_inventory_form') === 'assignment' ? old('codigo_lote') : '' }}" @disabled($unassignedIngredients->isEmpty() || ! $operationsAvailable)></label>
                <label class="inventory-form-field"><span>Fecha de caducidad</span><input type="date" name="fecha_caducidad" value="{{ old('_inventory_form') === 'assignment' ? old('fecha_caducidad') : '' }}" @disabled($unassignedIngredients->isEmpty() || ! $operationsAvailable)></label>
            </div>
            <div class="inventory-dialog-actions inventory-form-field-full">
                <button class="inventory-button" type="button" data-inventory-close>Cancelar</button>
                <button class="inventory-button inventory-button-primary" type="submit" @disabled($unassignedIngredients->isEmpty() || ! $operationsAvailable)>Asignar y registrar entrada</button>
            </div>
        </form>
    </dialog>
@endif

@if($canManageInventory && $operationsAvailable && $ingredients->isNotEmpty())
    <dialog class="inventory-dialog" id="inventory-movement-dialog" aria-labelledby="inventory-movement-title"
        @if(old('_inventory_form') === 'movement') data-inventory-autoshow @endif>
        <div class="inventory-dialog-heading">
            <div><span class="inventory-panel-kicker">{{ $site->nombre }}</span><h2 id="inventory-movement-title">Registrar movimiento</h2></div>
            <button class="inventory-dialog-x" type="button" data-inventory-close aria-label="Cerrar">×</button>
        </div>
        <form method="POST" action="{{ route('inventory.movements.store') }}" class="inventory-dialog-form" data-inventory-movement-form>
            @csrf
            <input type="hidden" name="_inventory_form" value="movement">
            <label class="inventory-form-field"><span>Tipo de movimiento</span>
                <select name="tipo" required data-inventory-movement-type>
                    <option value="entrada" @selected(old('_inventory_form') === 'movement' && old('tipo') === 'entrada')>Entrada</option>
                    <option value="salida" @selected(old('_inventory_form') === 'movement' && old('tipo') === 'salida')>Salida</option>
                    <option value="consumo" @selected(old('_inventory_form') === 'movement' && old('tipo') === 'consumo')>Consumo</option>
                    <option value="merma" @selected(old('_inventory_form') === 'movement' && old('tipo') === 'merma')>Merma</option>
                    <option value="ajuste" @selected(old('_inventory_form') === 'movement' && old('tipo') === 'ajuste')>Ajuste físico</option>
                </select>
            </label>
            <label class="inventory-form-field"><span>Insumo</span>
                <select name="id_insumo" required>
                    <option value="">Selecciona un insumo</option>
                    @foreach($ingredients as $ingredient)
                        <option value="{{ $ingredient->id_insumo }}" data-stock-defined="{{ $ingredient->stock === null ? '0' : '1' }}" data-unit-abbreviation="{{ $inventoryUnitAbbreviation($ingredient->unidad_codigo, $ingredient->unidad) }}" data-unit-name="{{ $ingredient->unidad }}" data-allows-fractions="{{ $ingredient->permite_fracciones ? '1' : '0' }}" @selected(old('_inventory_form') === 'movement' && (string) old('id_insumo') === (string) $ingredient->id_insumo)>
                            {{ $ingredient->nombre }}{{ $ingredient->unidad ? ' · '.$ingredient->unidad : ' · Unidad no asignada' }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label class="inventory-form-field"><span data-inventory-quantity-label>Cantidad</span>
                <div class="inventory-quantity-input">
                    <input type="number" name="cantidad" min="0.001" max="999999999.999" step="0.001" value="{{ old('cantidad') }}" required inputmode="decimal" data-inventory-quantity data-inventory-unit-quantity data-positive-quantity>
                    <span class="inventory-quantity-suffix" data-inventory-unit-suffix aria-live="polite">—</span>
                </div>
                <small data-inventory-quantity-hint>La entrada suma existencias. Las salidas, consumos y mermas las descuentan.</small>
                <small data-inventory-unit-hint>Selecciona un insumo para ver su unidad base.</small>
            </label>
            <label class="inventory-form-field inventory-form-field-full"><span>Motivo / observación <small>(el motivo es obligatorio para ajustes)</small></span>
                <textarea name="motivo" rows="3" maxlength="1000" placeholder="Describe el motivo o deja una nota para el historial">{{ old('motivo') }}</textarea>
            </label>
            <label class="inventory-form-field"><span>Ubicación del movimiento</span>
                <select name="ubicacion" required data-inventory-location>
                    @foreach($locations as $location)
                        <option value="{{ $location->id_ubicacion_inv }}" @selected((string) old('ubicacion') === (string) $location->id_ubicacion_inv)>{{ $location->nombre }}</option>
                    @endforeach
                    <option value="nueva" @selected(old('ubicacion', $locations->isEmpty() ? 'nueva' : '') === 'nueva')>Crear ubicación para esta sede…</option>
                </select>
            </label>
            <label class="inventory-form-field" data-inventory-new-location>
                <span>Nombre de ubicación</span>
                <input type="text" name="nueva_ubicacion" maxlength="100" value="{{ old('nueva_ubicacion') }}" placeholder="Ej. Almacén de cocina">
            </label>
            <div class="inventory-form-field inventory-form-field-full" data-inventory-lot-choice>
                <label class="inventory-form-field"><span>Lote para el historial</span>
                    <select name="id_lote" data-inventory-lot>
                        <option value="">Sin lote identificado</option>
                        @foreach($batchOptions as $batch)
                            <option value="{{ $batch->id_lote }}" data-ingredient="{{ $batch->id_insumo }}" @selected((string) old('id_lote') === (string) $batch->id_lote)>
                                {{ $batch->codigo_lote ?: 'Lote sin código' }} · saldo {{ $formatInventoryQuantity($batch->stock_lote, $batch->unidad_codigo, $batch->unidad) }}
                            </option>
                        @endforeach
                    </select>
                    <small>La salida no supera el saldo del lote seleccionado. Si se desconoce el lote, usa “Sin lote identificado”.</small>
                </label>
            </div>
            <div class="inventory-entry-lot inventory-form-field-full" data-inventory-entry-lot>
                <p class="inventory-entry-lot-title">Datos del lote recibido <small>Opcionales si no están disponibles</small></p>
                @if($suppliers->isNotEmpty())
                    <label class="inventory-form-field"><span>Proveedor</span>
                        <select name="id_proveedor">
                            <option value="">Sin proveedor registrado</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id_proveedor }}" @selected((string) old('id_proveedor') === (string) $supplier->id_proveedor)>{{ $supplier->nombre }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <label class="inventory-form-field"><span>Código de lote</span><input type="text" name="codigo_lote" maxlength="100" value="{{ old('codigo_lote') }}" placeholder="Si viene en el empaque"></label>
                <label class="inventory-form-field"><span>Fecha de caducidad</span><input type="date" name="fecha_caducidad" value="{{ old('fecha_caducidad') }}"></label>
            </div>
            <label class="inventory-form-field inventory-form-field-full"><span>Referencia <small>(opcional)</small></span>
                <input type="text" name="referencia" maxlength="100" value="{{ old('referencia') }}" placeholder="Folio, nota o referencia interna">
            </label>
            <div class="inventory-dialog-actions inventory-form-field-full">
                <button class="inventory-button" type="button" data-inventory-close>Cancelar</button>
                <button class="inventory-button inventory-button-primary" type="submit">Guardar movimiento</button>
            </div>
        </form>
    </dialog>

    <dialog class="inventory-dialog inventory-dialog-compact" id="inventory-reorder-dialog" aria-labelledby="inventory-reorder-title"
        @if(old('_inventory_form') === 'reorder') data-inventory-autoshow @endif>
        <div class="inventory-dialog-heading">
            <div><span class="inventory-panel-kicker">Configuración de mínimo</span><h2 id="inventory-reorder-title">Editar punto de reorden</h2></div>
            <button class="inventory-dialog-x" type="button" data-inventory-close aria-label="Cerrar">×</button>
        </div>
        <form method="POST" action="{{ route('inventory.reorder.update', ['ingredient' => 0]) }}" class="inventory-dialog-form" data-inventory-reorder-form>
            @csrf
            @method('PUT')
            <input type="hidden" name="_inventory_form" value="reorder">
            <input type="hidden" name="id_insumo" value="{{ old('id_insumo') }}" data-inventory-reorder-id>
            <p class="inventory-reorder-item" data-inventory-reorder-name></p>
            <label class="inventory-form-field"><span>Punto de reorden <small>(<span data-inventory-unit-name>unidad base</span>)</small></span>
                <div class="inventory-quantity-input">
                    <input type="number" name="punto_de_reorden" min="0" max="999999999.999" step="0.001" value="{{ old('punto_de_reorden') }}" inputmode="decimal" data-inventory-unit-quantity>
                    <span class="inventory-quantity-suffix" data-inventory-unit-suffix aria-live="polite">—</span>
                </div>
                <small>Deja el campo vacío para quitar el mínimo y mostrar “Sin mínimo”.</small>
            </label>
            <div class="inventory-dialog-actions">
                <button class="inventory-button" type="button" data-inventory-close>Cancelar</button>
                <button class="inventory-button inventory-button-primary" type="submit">Guardar mínimo</button>
            </div>
        </form>
    </dialog>
@endif

@if($historyItem)
    <dialog class="inventory-dialog inventory-dialog-history" id="inventory-history-dialog" aria-labelledby="inventory-history-title" data-inventory-autoshow>
        <div class="inventory-dialog-heading">
            <div><span class="inventory-panel-kicker">{{ $site->nombre }}</span><h2 id="inventory-history-title">Historial · {{ $historyItem->insumo }}</h2></div>
            <a class="inventory-dialog-x" href="{{ route('inventory.index') }}" aria-label="Cerrar historial">×</a>
        </div>
        @if($historyAvailable && $historyRows->isNotEmpty())
            <div class="inventory-history-list">
                @foreach($historyRows as $movement)
                    <article class="inventory-history-row">
                        <div class="inventory-history-top">
                            <strong>{{ ucfirst($movement->tipo ?: 'Movimiento') }}</strong>
                            <span class="inventory-history-quantity {{ (float) $movement->cantidad_firmada < 0 ? 'inventory-history-negative' : 'inventory-history-positive' }}">{{ $formatInventoryQuantity($movement->cantidad_firmada, $historyItem->unidad_codigo, $historyItem->unidad) }}</span>
                        </div>
                        <div class="inventory-history-meta">
                            <span>{{ $movement->fecha ? \Illuminate\Support\Carbon::parse($movement->fecha)->timezone('America/Mexico_City')->format('d/m/Y H:i') : 'Fecha no registrada' }}</span>
                            <span>{{ $movement->ubicacion }}</span>
                            <span>{{ trim(($movement->usuario_nombre ?? '').' '.($movement->usuario_apellido ?? '')) ?: 'Usuario no disponible' }}</span>
                        </div>
                        <p>{{ $movement->observaciones ?: 'Sin observaciones' }}</p>
                        <small>Lote: {{ $movement->codigo_lote ?: 'Sin referencia de lote' }}{{ $movement->referencia ? ' · '.$movement->referencia : '' }}</small>
                    </article>
                @endforeach
            </div>
            @if($historyRows->hasPages())<div class="inventory-pagination">{{ $historyRows->links() }}</div>@endif
        @elseif($historyAvailable)
            <div class="inventory-empty-state inventory-history-empty"><strong>Sin movimientos registrados</strong><p>Las entradas, salidas, consumos, mermas y ajustes aparecerán aquí.</p></div>
        @else
            <div class="inventory-empty-state inventory-history-empty"><strong>Historial no disponible</strong><p>La base conectada no tiene todas las columnas necesarias para consultar movimientos.</p></div>
        @endif
        <div class="inventory-dialog-actions"><a class="inventory-button" href="{{ route('inventory.index') }}">Cerrar</a></div>
    </dialog>
@endif

<script>
document.addEventListener('DOMContentLoaded', () => {
    const dialogs = document.querySelectorAll('.inventory-dialog');

    document.querySelectorAll('[data-inventory-open]').forEach((button) => {
        button.addEventListener('click', () => {
            const dialog = document.getElementById(button.dataset.inventoryOpen);
            if (!dialog) return;
            if (button.dataset.inventoryType) {
                const typeSelect = dialog.querySelector('[data-inventory-movement-type]');
                if (typeSelect) {
                    typeSelect.value = button.dataset.inventoryType;
                    typeSelect.dispatchEvent(new Event('change'));
                }
            }
            if (button.dataset.inventoryIngredient) {
                const ingredientSelect = dialog.querySelector('[name="id_insumo"]');
                if (ingredientSelect) {
                    ingredientSelect.value = button.dataset.inventoryIngredient;
                    ingredientSelect.dispatchEvent(new Event('change'));
                }
            }
            dialog.showModal();
        });
    });

    document.querySelectorAll('[data-inventory-close]').forEach((button) => {
        button.addEventListener('click', () => button.closest('dialog')?.close());
    });

    const syncUnitFields = (form, option) => {
        const data = option?.dataset;
        const abbreviation = data?.unitAbbreviation || '—';
        const unitName = data?.unitName || 'unidad no seleccionada';
        const allowsFractions = data ? data.allowsFractions === '1' : true;

        form?.querySelectorAll('[data-inventory-unit-suffix]').forEach((suffix) => {
            suffix.textContent = abbreviation;
        });
        form?.querySelectorAll('[data-inventory-unit-name]').forEach((name) => {
            name.textContent = unitName;
        });
        form?.querySelectorAll('[data-inventory-unit-quantity]').forEach((input) => {
            input.step = allowsFractions ? '0.001' : '1';
            input.inputMode = allowsFractions ? 'decimal' : 'numeric';
            if (input.hasAttribute('data-positive-quantity')) {
                input.min = allowsFractions ? '0.001' : '1';
            }
        });

        return { abbreviation, unitName, allowsFractions };
    };

    document.querySelectorAll('[data-inventory-unit-form]').forEach((form) => {
        const unitSelect = form.querySelector('[data-inventory-unit-select]');
        const updateUnitFields = () => syncUnitFields(form, unitSelect?.selectedOptions[0]);
        unitSelect?.addEventListener('change', updateUnitFields);
        updateUnitFields();
    });

    const movementDialog = document.getElementById('inventory-movement-dialog');
    const movementType = movementDialog?.querySelector('[data-inventory-movement-type]');
    const quantityInput = movementDialog?.querySelector('[data-inventory-quantity]');
    const quantityLabel = movementDialog?.querySelector('[data-inventory-quantity-label]');
    const quantityHint = movementDialog?.querySelector('[data-inventory-quantity-hint]');
    const unitHint = movementDialog?.querySelector('[data-inventory-unit-hint]');
    const locationSelect = movementDialog?.querySelector('[data-inventory-location]');
    const newLocation = movementDialog?.querySelector('[data-inventory-new-location]');
    const existingLot = movementDialog?.querySelector('[data-inventory-lot-choice]');
    const entryLot = movementDialog?.querySelector('[data-inventory-entry-lot]');
    const lotSelect = movementDialog?.querySelector('[data-inventory-lot]');
    const ingredientSelect = movementDialog?.querySelector('[name="id_insumo"]');

    const updateMovementFields = () => {
        if (!movementType) return;
        const isEntry = movementType.value === 'entrada';
        const isAdjustment = movementType.value === 'ajuste';
        if (quantityLabel) quantityLabel.textContent = isAdjustment ? 'Nueva existencia física' : 'Cantidad';
        const selectedIngredient = ingredientSelect?.selectedOptions[0];
        const unit = syncUnitFields(movementDialog, selectedIngredient);
        const stockIsUndefined = selectedIngredient?.dataset.stockDefined === '0';
        if (quantityHint) quantityHint.textContent = isAdjustment
            ? 'Captura el conteo físico final. Puede ser cero; indica el motivo del ajuste.'
            : stockIsUndefined
                ? 'La existencia actual no está definida. Registra primero un ajuste físico con motivo; después podrás sumar o descontar existencias.'
                : 'La entrada suma existencias. Las salidas, consumos y mermas las descuentan.';
        if (quantityInput) quantityInput.min = isAdjustment ? '0' : unit.allowsFractions ? '0.001' : '1';
        if (unitHint) unitHint.textContent = selectedIngredient?.value
            ? `Unidad base: ${unit.unitName} (${unit.abbreviation}). Se guarda en esta unidad, sin conversión automática.`
            : 'Selecciona un insumo para ver su unidad base.';
        if (existingLot) existingLot.hidden = isEntry;
        if (entryLot) entryLot.hidden = !isEntry;
        if (lotSelect) lotSelect.disabled = isEntry;
        if (locationSelect && newLocation) {
            const isNewLocation = locationSelect.value === 'nueva';
            newLocation.hidden = !isNewLocation;
            const locationName = newLocation.querySelector('input');
            if (locationName) locationName.required = isNewLocation;
        }
        if (ingredientSelect && lotSelect) {
            Array.from(lotSelect.options).forEach((option) => {
                if (!option.dataset.ingredient) return;
                option.hidden = option.dataset.ingredient !== ingredientSelect.value;
                if (option.hidden && option.selected) lotSelect.value = '';
            });
        }
    };

    movementType?.addEventListener('change', updateMovementFields);
    ingredientSelect?.addEventListener('change', updateMovementFields);
    locationSelect?.addEventListener('change', updateMovementFields);
    updateMovementFields();

    document.querySelectorAll('[data-inventory-initial-form]').forEach((form) => {
        const initialLocation = form.querySelector('[data-inventory-initial-location]');
        const newLocationField = form.querySelector('[data-inventory-initial-new-location]');
        const newLocationInput = newLocationField?.querySelector('input');
        const updateInitialLocation = () => {
            const isNewLocation = initialLocation?.value === 'nueva';
            if (newLocationField) newLocationField.hidden = !isNewLocation;
            if (newLocationInput) newLocationInput.required = Boolean(isNewLocation);
        };

        initialLocation?.addEventListener('change', updateInitialLocation);
        updateInitialLocation();
    });

    const reorderDialog = document.getElementById('inventory-reorder-dialog');
    const reorderForm = reorderDialog?.querySelector('[data-inventory-reorder-form]');
    const reorderInput = reorderForm?.querySelector('[name="punto_de_reorden"]');
    const reorderId = reorderForm?.querySelector('[data-inventory-reorder-id]');
    const reorderName = reorderDialog?.querySelector('[data-inventory-reorder-name]');

    document.querySelectorAll('[data-inventory-reorder-open]').forEach((button) => {
        button.addEventListener('click', () => {
            if (reorderForm) reorderForm.action = button.dataset.action;
            if (reorderInput) reorderInput.value = button.dataset.minimum ?? '';
            if (reorderId) reorderId.value = button.dataset.id;
            if (reorderName) reorderName.textContent = button.dataset.name;
            syncUnitFields(reorderForm, { dataset: button.dataset });
            reorderDialog?.showModal();
        });
    });

    dialogs.forEach((dialog) => {
        if (!dialog.hasAttribute('data-inventory-autoshow')) return;
        if (dialog.id === 'inventory-reorder-dialog') {
            const targetId = reorderId?.value;
            const trigger = Array.from(document.querySelectorAll('[data-inventory-reorder-open]'))
                .find((button) => button.dataset.id === targetId);
            if (trigger) {
                if (reorderForm) reorderForm.action = trigger.dataset.action;
                if (reorderName) reorderName.textContent = trigger.dataset.name;
                syncUnitFields(reorderForm, { dataset: trigger.dataset });
            }
        }
        dialog.showModal();
    });
});
</script>
@endsection
