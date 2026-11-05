@extends('layouts.app')
@section('title', 'Nuevo pedido de insumos')
@section('content')
    <div class="chef-page">
        <section class="chef-hero"><div class="chef-hero-copy"><span class="chef-eyebrow">{{ $site->nombre }} · Abastecimiento</span><h1>Nuevo pedido a proveedor</h1><p>El pedido se guardará como Programado. Las existencias NO cambian hasta una recepción confirmada.</p></div></section>
        <section class="chef-panel">
            <p><a class="chef-button chef-button-secondary" href="{{ route('orders.suppliers.index') }}">+ Registrar o administrar proveedores</a></p>
            @if($errors->any())<div role="alert"><strong>Corrige los siguientes campos:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @if($suppliers->isEmpty() || $ingredients->isEmpty())
                <p role="status">Faltan {{ $suppliers->isEmpty() ? 'proveedores' : '' }} {{ $ingredients->isEmpty() ? 'insumos asignados a esta sede con unidad válida' : '' }}. Regístralos antes de crear pedidos. Usa el enlace superior para dar de alta proveedores.</p>
            @else
                <form method="POST" action="{{ route('orders.store') }}">@csrf
                    <div class="chef-form">
                        <label>Proveedor <select name="id_proveedor" required><option value="">Seleccionar proveedor</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id_proveedor }}" @selected((string)old('id_proveedor') === (string)$supplier->id_proveedor)>{{ $supplier->nombre }}</option>@endforeach</select></label>
                        <label>Fecha prevista de entrega<input name="fecha_entrega" type="date" value="{{ old('fecha_entrega', now('America/Mexico_City')->toDateString()) }}" required></label>
                    </div>
                    <h2>Partidas del pedido</h2>
                    <div id="partidas">
                        @foreach(old('detalles', [['id_insumo'=>'', 'cantidad'=>'', 'especificaciones'=>'']]) as $index => $oldDetail)
                            <fieldset class="chef-panel" data-partida><legend>Insumo</legend><div class="chef-form">
                                    <label>Producto<select data-name="id_insumo" name="detalles[{{ $index }}][id_insumo]" required><option value="">Selecciona un insumo</option>@foreach($ingredients as $ingredient)<option value="{{ $ingredient->id_insumo }}" @selected((string)($oldDetail['id_insumo'] ?? '') === (string)$ingredient->id_insumo)>{{ $ingredient->nombre }} ({{ $ingredient->unidad_codigo ?: $ingredient->unidad_nombre }})</option>@endforeach</select></label>
                                    <label>Cantidad<input data-name="cantidad" name="detalles[{{ $index }}][cantidad]" type="number" step="0.001" min="0.001" max="999999999.999" value="{{ $oldDetail['cantidad'] ?? '' }}" required></label>
                                    <label>Especificaciones<input data-name="especificaciones" name="detalles[{{ $index }}][especificaciones]" maxlength="1000" value="{{ $oldDetail['especificaciones'] ?? '' }}"></label>
                                    <button type="button" class="chef-button chef-button-secondary" data-remove>Quitar partida</button>
                                </div></fieldset>
                        @endforeach
                    </div>
                    <p><button class="chef-button chef-button-secondary" type="button" id="agregar-partida">+ Agregar otra partida</button></p>
                    <label>Observaciones generales<textarea name="observaciones" rows="3" maxlength="3000">{{ old('observaciones') }}</textarea></label>
                    <div class="chef-form-actions"><button class="chef-button chef-button-primary" type="submit">Guardar pedido</button><a class="chef-button chef-button-secondary" href="{{ route('orders.index') }}">Cancelar</a></div>
                </form>
            @endif
        </section>
    </div>
    @if($suppliers->isNotEmpty() && $ingredients->isNotEmpty())
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const container = document.getElementById('partidas');
                const renumber = () => container.querySelectorAll('[data-partida]').forEach((row, i) => {
                    row.querySelectorAll('[data-name]').forEach(input => input.name = `detalles[${i}][${input.dataset.name}]`);
                });
                document.getElementById('agregar-partida').addEventListener('click', () => {
                    if (container.querySelectorAll('[data-partida]').length >= 50) return;
                    const source = container.querySelector('[data-partida]');
                    const clone = source.cloneNode(true);
                    clone.querySelectorAll('input, select').forEach(input => input.value = '');
                    container.appendChild(clone); renumber();
                });
                container.addEventListener('click', e => {
                    if (!e.target.closest('[data-remove]')) return;
                    if (container.querySelectorAll('[data-partida]').length <= 1) return;
                    e.target.closest('[data-partida]').remove(); renumber();
                });
            });
        </script>
    @endif
@endsection
