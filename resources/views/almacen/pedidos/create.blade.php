@extends('layouts.app')
@section('title', 'Nuevo pedido de insumos')

@section('content')
    @php
        // El controlador debe enviar $lowStockItems de la sede activa.
        // La vista nunca consulta directamente la base de datos.
        $lowStockItems = collect($lowStockItems ?? []);
        $formatNumber = static function ($value): string {
            $result = number_format((float) $value, 3, '.', ',');
            return rtrim(rtrim($result, '0'), '.') ?: '0';
        };
        $stockCategories = $lowStockItems->pluck('categoria')->filter(fn ($value) => trim((string) $value) !== '')->unique()->sort()->values();
        $urgentCount = $lowStockItems->filter(fn ($item) => (float) ($item->stock ?? 0) <= 0)->count();
        $lowCount = $lowStockItems->count() - $urgentCount;
    @endphp
    <div class="chef-page warehouse-orders-page">
        <section class="chef-hero">
            <div class="chef-hero-copy">
                <span class="chef-eyebrow">{{ $site->nombre }} · Abastecimiento</span>
                <h1>Nuevo pedido a proveedor</h1>
                <p>Consulta las existencias de tu almacén y solicita los alimentos que necesitas. El pedido se guardará como Programado; el inventario solamente cambiará cuando se registre una recepción confirmada.</p>
            </div>
        </section>

        @if($errors->any())
            <section class="chef-panel" role="alert">
                <strong>Revisa los datos del pedido:</strong>
                <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </section>
        @endif

        <section class="chef-panel warehouse-replenishment" aria-labelledby="stock-bajo-titulo">
            <div class="chef-panel-heading">
                <div>
                    <h2 id="stock-bajo-titulo">Prioridades de compra</h2>
                    <p>Alimentos agotados o por debajo de su mínimo. Filtra, ordena y añádelos a un pedido.</p>
                </div>
                <span class="chef-count">{{ $lowStockItems->count() }} por reponer</span>
            </div>
            <div class="warehouse-stats" aria-label="Resumen de existencias">
                <div><span>Productos por reponer</span><strong>{{ $lowStockItems->count() }}</strong></div>
                <div><span>Agotados</span><strong>{{ $urgentCount }}</strong></div>
                <div><span>Stock bajo</span><strong>{{ $lowCount }}</strong></div>
            </div>
            @if($lowStockItems->isEmpty())
                <p role="status" class="warehouse-empty">No se detectaron alimentos agotados o bajo el mínimo en esta sede. Si esperabas resultados, confirma que el controlador esté enviando <code>$lowStockItems</code> y que se hayan configurado los mínimos.</p>
            @else
                <div class="warehouse-filter-grid" role="group" aria-label="Buscar y filtrar alimentos">
                    <label>Buscar alimento
                        <input type="search" id="buscar-stock-bajo" placeholder="Manzana, arroz, leche…" autocomplete="off">
                    </label>
                    <label>Categoría
                        <select id="filtrar-categoria-stock">
                            <option value="">Todas las categorías</option>
                            @foreach($stockCategories as $category)
                                <option value="{{ $category }}">{{ $category }}</option>
                            @endforeach
                        </select>
                        @if($stockCategories->isEmpty())<small>Sin categorías registradas para estos productos</small>@endif
                    </label>
                    <label>Estado
                        <select id="filtrar-estado-stock">
                            <option value="">Todos los estados</option>
                            <option value="agotado">Agotado</option>
                            <option value="bajo">Stock bajo</option>
                        </select>
                    </label>
                    <label>Ordenar por
                        <select id="ordenar-stock">
                            <option value="urgencia">Menor porcentaje primero</option>
                            <option value="porcentaje-desc">Mayor porcentaje primero</option>
                            <option value="nombre">Nombre A–Z</option>
                            <option value="cantidad">Menor cantidad primero</option>
                        </select>
                    </label>
                    <button type="button" class="chef-button chef-button-secondary warehouse-clear" id="limpiar-filtros-stock">Limpiar filtros</button>
                </div>
                <p class="warehouse-metric-note">% del mínimo = existencias actuales ÷ mínimo de reorden × 100. <strong>0 %</strong> significa agotado; <strong>100 %</strong> significa que llegó al mínimo. Si el mínimo es cero o no está definido, se indica «Sin referencia».</p>
                <div class="chef-table-wrap">
                    <table class="chef-table warehouse-stock-table" id="tabla-stock-bajo">
                        <thead><tr><th>Alimento</th><th>Categoría</th><th>Disponible</th><th>Mínimo</th><th>% del mínimo</th><th>Estado</th><th>Acción</th></tr></thead>
                        <tbody id="filas-stock-bajo">
                        @foreach($lowStockItems as $item)
                            @php
                                $stock = max(0, (float) ($item->stock ?? 0));
                                $minimum = $item->punto_de_reorden;
                                $percentage = $minimum !== null && (float) $minimum > 0 ? ($stock / (float) $minimum) * 100 : null;
                                $unit = ($item->unidad_codigo ?? null) ?: (($item->unidad_nombre ?? null) ?: 'unidad');
                                $category = trim((string) ($item->categoria ?? ''));
                                $depleted = $stock <= 0;
                            @endphp
                            <tr data-stock-row
                                data-name="{{ mb_strtolower($item->nombre) }}"
                                data-category="{{ $category }}"
                                data-state="{{ $depleted ? 'agotado' : 'bajo' }}"
                                data-percent="{{ $percentage === null ? '' : round($percentage, 4) }}"
                                data-quantity="{{ $stock }}">
                                <td><strong>{{ $item->nombre }}</strong></td>
                                <td>{{ $category !== '' ? $category : 'Sin categoría' }}</td>
                                <td>{{ $formatNumber($stock) }} {{ $unit }}</td>
                                <td>{{ $minimum === null ? 'Sin definir' : $formatNumber($minimum).' '.$unit }}</td>
                                <td>
                                    @if($percentage === null)
                                        <span class="warehouse-no-percent">Sin referencia</span>
                                    @else
                                        <div class="warehouse-progress" role="progressbar" aria-valuenow="{{ min(100, round($percentage)) }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $item->nombre }}: {{ round($percentage) }} por ciento del mínimo">
                                            <span style="width:{{ min(100, max(0, $percentage)) }}%"></span>
                                        </div>
                                        <small>{{ $formatNumber($percentage) }} %</small>
                                    @endif
                                </td>
                                <td><span class="warehouse-status {{ $depleted ? 'warehouse-status-empty' : 'warehouse-status-low' }}">{{ $depleted ? 'Agotado' : 'Stock bajo' }}</span></td>
                                <td><button type="button" class="chef-button chef-button-secondary" data-add-ingredient="{{ $item->id_insumo }}" @disabled($suppliers->isEmpty() || $ingredients->isEmpty())>+ Al pedido</button></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <p id="stock-sin-resultados" role="status" hidden>No hay alimentos que coincidan con los filtros seleccionados.</p>
                <p class="warehouse-results"><span id="stock-count-visible">{{ $lowStockItems->count() }}</span> productos visibles</p>
            @endif
        </section>

        <section class="chef-panel" aria-labelledby="pedido-form-title">
            <div class="chef-panel-heading"><h2 id="pedido-form-title">Datos del nuevo pedido</h2></div>
            <p><a class="chef-button chef-button-secondary" href="{{ route('orders.suppliers.index') }}">Administrar proveedores</a></p>
            @if($suppliers->isEmpty() || $ingredients->isEmpty())
                <p role="status">Faltan {{ $suppliers->isEmpty() ? 'proveedores' : '' }} {{ $ingredients->isEmpty() ? 'insumos de esta sede con unidad válida' : '' }}. Regístralos antes de crear un pedido.</p>
            @else
                <form method="POST" action="{{ route('orders.store') }}" id="warehouse-order-form">
                    @csrf
                    <div class="chef-form">
                        <label>Proveedor
                            <select name="id_proveedor" required>
                                <option value="">Seleccionar proveedor</option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id_proveedor }}" @selected((string)old('id_proveedor') === (string)$supplier->id_proveedor)>{{ $supplier->nombre }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Fecha prevista de entrega
                            <input name="fecha_entrega" type="date" value="{{ old('fecha_entrega', now('America/Mexico_City')->toDateString()) }}" required>
                        </label>
                    </div>
                    <h3>Alimentos solicitados</h3>
                    <p>Selecciona productos en el formulario o utiliza “Añadir al pedido” en la lista de alimentos por reponer.</p>
                    <div id="partidas">
                        @foreach(old('detalles', [['id_insumo'=>'', 'cantidad'=>'', 'especificaciones'=>'']]) as $index => $oldDetail)
                            <fieldset class="chef-panel" data-partida>
                                <legend>Alimento solicitado</legend>
                                <div class="chef-form">
                                    <label>Producto
                                        <select data-name="id_insumo" name="detalles[{{ $index }}][id_insumo]" required>
                                            <option value="">Selecciona un alimento</option>
                                            @foreach($ingredients as $ingredient)
                                                <option value="{{ $ingredient->id_insumo }}" @selected((string)($oldDetail['id_insumo'] ?? '') === (string)$ingredient->id_insumo)>{{ $ingredient->nombre }} ({{ $ingredient->unidad_codigo ?: $ingredient->unidad_nombre }})</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label>Cantidad a solicitar
                                        <input data-name="cantidad" name="detalles[{{ $index }}][cantidad]" type="number" step="0.001" min="0.001" max="999999999.999" value="{{ $oldDetail['cantidad'] ?? '' }}" required>
                                    </label>
                                    <label>Especificaciones (opcional)
                                        <input data-name="especificaciones" name="detalles[{{ $index }}][especificaciones]" maxlength="1000" value="{{ $oldDetail['especificaciones'] ?? '' }}">
                                    </label>
                                    <button type="button" class="chef-button chef-button-secondary" data-remove>Quitar alimento</button>
                                </div>
                            </fieldset>
                        @endforeach
                    </div>
                    <p><button class="chef-button chef-button-secondary" type="button" id="agregar-partida">+ Agregar otro alimento</button></p>
                    <label>Observaciones generales
                        <textarea name="observaciones" rows="3" maxlength="3000">{{ old('observaciones') }}</textarea>
                    </label>
                    <div class="chef-form-actions">
                        <button class="chef-button chef-button-primary" type="submit">Guardar pedido</button>
                        <a class="chef-button chef-button-secondary" href="{{ route('orders.index') }}">Cancelar</a>
                    </div>
                </form>
            @endif
        </section>
    </div>
    <style>
        .warehouse-orders-page .warehouse-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:18px 0}
        .warehouse-orders-page .warehouse-stats>div{background:#f4f8fe;border:1px solid #e1eaf5;border-radius:13px;padding:14px;display:grid;gap:4px}
        .warehouse-orders-page .warehouse-stats span{color:#51627b;font-size:13px}
        .warehouse-orders-page .warehouse-stats strong{font-size:26px;color:#142d50}
        .warehouse-orders-page .warehouse-filter-grid{display:grid;grid-template-columns:repeat(4,minmax(145px,1fr));gap:12px;align-items:end;margin:16px 0}
        .warehouse-orders-page .warehouse-filter-grid label{display:flex;flex-direction:column;gap:7px;color:#203855;font-weight:600;font-size:13px}
        .warehouse-orders-page .warehouse-filter-grid input,.warehouse-orders-page .warehouse-filter-grid select{width:100%;min-width:0;border:1px solid #cdd8e7;background:#fff;padding:11px 12px;border-radius:9px;font:inherit;color:#1f344f}
        .warehouse-orders-page .warehouse-filter-grid small{font-weight:400;color:#66788d}
        .warehouse-orders-page .warehouse-filter-grid :is(input,select):focus-visible{outline:2px solid #1976e8;outline-offset:2px}
        .warehouse-orders-page .warehouse-clear{height:43px}
        .warehouse-orders-page .warehouse-metric-note{font-size:12px;line-height:1.5;color:#56657c;margin:8px 0 16px}
        .warehouse-orders-page .warehouse-progress{width:105px;max-width:100%;height:8px;overflow:hidden;background:#e3eaf3;border-radius:10px;margin-bottom:4px}
        .warehouse-orders-page .warehouse-progress span{display:block;height:100%;background:#2176de;border-radius:10px}
        .warehouse-orders-page .warehouse-no-percent{color:#738096;font-size:12px}
        .warehouse-orders-page .warehouse-status{display:inline-block;padding:5px 10px;border-radius:999px;font-size:12px;font-weight:700;white-space:nowrap}
        .warehouse-orders-page .warehouse-status-empty{background:#fee8e8;color:#a52727}
        .warehouse-orders-page .warehouse-status-low{background:#fff1d6;color:#855400}
        .warehouse-orders-page .warehouse-results{font-size:12px;color:#687a90;margin-top:12px}
        .warehouse-orders-page .warehouse-empty{padding:14px;background:#f7f9fd;border:1px solid #e0e8f3;border-radius:9px}
        .warehouse-orders-page [data-partida]{margin:12px 0}
        .warehouse-orders-page #stock-sin-resultados{padding:12px;color:#64748b}
        @media(max-width:1050px){.warehouse-orders-page .warehouse-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:700px){.warehouse-orders-page .warehouse-stats{grid-template-columns:1fr 1fr}.warehouse-orders-page .warehouse-filter-grid{grid-template-columns:1fr}.warehouse-orders-page .chef-table-wrap{overflow-x:auto}.warehouse-orders-page .warehouse-stock-table{min-width:850px}}
    </style>
    @if($suppliers->isNotEmpty() && $ingredients->isNotEmpty())
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const container = document.getElementById('partidas');
                const source = container?.querySelector('[data-partida]');
                if (!container || !source) return;
                const renumber = () => container.querySelectorAll('[data-partida]').forEach((row, index) => {
                    row.querySelectorAll('[data-name]').forEach(input => input.name = `detalles[${index}][${input.dataset.name}]`);
                });
                const newRow = () => {
                    if (container.querySelectorAll('[data-partida]').length >= 50) {
                        alert('El pedido admite un máximo de 50 alimentos.');
                        return null;
                    }
                    const clone = source.cloneNode(true);
                    clone.querySelectorAll('input, select').forEach(input => input.value = '');
                    container.appendChild(clone);
                    renumber();
                    return clone;
                };
                document.getElementById('agregar-partida')?.addEventListener('click', () => newRow()?.querySelector('select')?.focus());
                container.addEventListener('click', event => {
                    if (!event.target.closest('[data-remove]')) return;
                    if (container.querySelectorAll('[data-partida]').length <= 1) return;
                    event.target.closest('[data-partida]').remove();
                    renumber();
                });
                document.querySelectorAll('[data-add-ingredient]').forEach(button => {
                    button.addEventListener('click', () => {
                        const id = button.dataset.addIngredient;
                        const current = [...container.querySelectorAll('[data-partida]')].find(row => row.querySelector('[data-name="id_insumo"]')?.value === id);
                        if (current) {
                            current.querySelector('[data-name="cantidad"]')?.focus();
                            current.scrollIntoView({behavior:'smooth', block:'center'});
                            return;
                        }
                        const empty = [...container.querySelectorAll('[data-partida]')].find(row => !row.querySelector('[data-name="id_insumo"]')?.value);
                        const row = empty || newRow();
                        if (!row) return;
                        const select = row.querySelector('[data-name="id_insumo"]');
                        if (![...select.options].some(option => option.value === id)) {
                            alert('Ese alimento ya no está disponible para pedidos en esta sede. Actualiza la página.');
                            if (!empty) row.remove();
                            renumber();
                            return;
                        }
                        select.value = id;
                        row.scrollIntoView({behavior:'smooth', block:'center'});
                        row.querySelector('[data-name="cantidad"]')?.focus();
                    });
                });
            });
        </script>
    @endif
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const search = document.getElementById('buscar-stock-bajo');
            const category = document.getElementById('filtrar-categoria-stock');
            const state = document.getElementById('filtrar-estado-stock');
            const order = document.getElementById('ordenar-stock');
            const tbody = document.getElementById('filas-stock-bajo');
            const empty = document.getElementById('stock-sin-resultados');
            const count = document.getElementById('stock-count-visible');
            if (!tbody) return;

            const rows = Array.from(tbody.querySelectorAll('[data-stock-row]'));
            const apply = () => {
                const term = (search?.value || '').trim().toLocaleLowerCase('es');
                const selectedCategory = category?.value || '';
                const selectedState = state?.value || '';
                const mode = order?.value || 'urgencia';
                const sorted = rows.slice().sort((a, b) => {
                    if (mode === 'nombre') return a.dataset.name.localeCompare(b.dataset.name, 'es');
                    if (mode === 'cantidad') return Number(a.dataset.quantity) - Number(b.dataset.quantity);
                    const av = a.dataset.percent === '' ? Infinity : Number(a.dataset.percent);
                    const bv = b.dataset.percent === '' ? Infinity : Number(b.dataset.percent);
                    if (av === Infinity && bv === Infinity) return 0;
                    if (av === Infinity) return 1;
                    if (bv === Infinity) return -1;
                    return mode === 'porcentaje-desc' ? bv - av : av - bv;
                });
                sorted.forEach(row => tbody.appendChild(row));
                let shown = 0;
                sorted.forEach(row => {
                    const visible = (row.dataset.name || '').includes(term)
                        && (!selectedCategory || row.dataset.category === selectedCategory)
                        && (!selectedState || row.dataset.state === selectedState);
                    row.hidden = !visible;
                    if (visible) shown++;
                });
                if (empty) empty.hidden = shown !== 0;
                if (count) count.textContent = String(shown);
            };
            [search, category, state, order].forEach(control => control?.addEventListener(control === search ? 'input' : 'change', apply));
            document.getElementById('limpiar-filtros-stock')?.addEventListener('click', () => {
                if (search) search.value = '';
                if (category) category.value = '';
                if (state) state.value = '';
                if (order) order.value = 'urgencia';
                apply();
            });
            apply();
        });
    </script>
@endsection
