@extends('layouts.app')

@push('styles')
    @vite('resources/css/capitan-mesas.css')
@endpush

@section('content')
<div class="cz-page">
    <div class="cz-topline"><span>CAPITÁN DE MESEROS</span><span class="cz-dot"></span><span>{{ $sede->nombre }}</span></div>

    <header class="cz-hero">
        <div>
            <div class="cz-eyebrow">OPERACIÓN / ESPACIOS DEL COMEDOR</div>
            <h1>Mesas <em>&</em> zonas</h1>
            <p>Organiza el salón, registra mesas y mantén actualizado el estado físico de cada espacio.</p>
        </div>
        <div class="cz-hero-actions">
            <button type="button" class="cz-btn cz-btn-light" data-open="zona">+ Nueva zona</button>
            <button type="button" class="cz-btn cz-btn-primary" data-open="mesa" @disabled($zonas->isEmpty())>+ Nueva mesa</button>
        </div>
    </header>

    @if(session('success'))
        <div class="cz-alert cz-ok" role="status">✓ {{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="cz-alert cz-error" role="alert">
            <strong>Revisa los datos del formulario:</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="cz-panel" aria-labelledby="cz-distribution-title" style="margin-top:19px">
        <div class="cz-panelhead"><div><span>CONTROL CENTRALIZADO</span><h2 id="cz-distribution-title">DISTRIBUCIÓN GENERAL DEL COMEDOR</h2><small>Total programado: {{ $mesasProgramadas ?? 'Sin definir' }} · Asignado: {{ $totalMesas }}</small></div><div style="display:flex;align-items:center;gap:12px"><span class="cz-count">{{ $mesasProgramadas ? max(0, $mesasProgramadas - $totalMesas) : '—' }} disponibles para repartir</span><button type="button" class="cz-btn cz-btn-light" data-open="total">Programar total</button></div></div>
        <div class="cz-distribution-grid">
            @forelse($distribucion as $zona)
                @php $porcentaje = $totalMesas > 0 ? round(($zona->mesas_count / $totalMesas) * 100) : 0; @endphp
                <div class="cz-zone"><div class="cz-zone-icon">{{ sprintf('%02d', $loop->iteration) }}</div><div class="cz-zone-copy"><strong>{{ $zona->nombre }}</strong><small>{{ $zona->mesas_count }} {{ $zona->mesas_count === 1 ? 'mesa' : 'mesas' }} · {{ $zona->capacidad_total }} personas · {{ $porcentaje }}% del total</small></div><button type="button" class="cz-btn cz-btn-light" data-open="mesa" data-zone="{{ $zona->id_zona }}">Asignar mesas a la zona</button></div>
            @empty<div class="cz-empty">Crea una zona para comenzar la distribución del comedor.</div>@endforelse
        </div>
    </section>

    @php
        $numDisponibles = $mesasDisponibles;
        $numMantenimiento = $mesasNoDisponibles;
    @endphp
    <section class="cz-stats" aria-label="Indicadores de mesas y zonas">
        <div class="cz-stat"><span>Zonas registradas</span><strong>{{ $zonas->count() }}</strong><small>Áreas de la sede</small></div>
        <div class="cz-stat"><span>Total de mesas</span><strong>{{ $mesas->count() }}</strong><small>Inventario de espacios</small></div>
        <div class="cz-stat"><span>Mesas disponibles</span><strong>{{ $numDisponibles }}</strong><small>Estado físico disponible</small></div>
        <div class="cz-stat"><span>Con mantenimiento / fuera</span><strong>{{ $numMantenimiento }}</strong><small>Requieren atención</small></div>
    </section>

    <div class="cz-layout">
        <section class="cz-panel">
            <div class="cz-panelhead"><div><span>ORGANIZACIÓN</span><h2>Zonas del comedor</h2></div><span class="cz-count">{{ $zonas->count() }} zonas</span></div>
            @forelse($zonas as $zona)
                @php $cantidad = $mesas->where('id_zona', $zona->id_zona)->count(); @endphp
                <div class="cz-zone">
                    <div class="cz-zone-icon">{{ sprintf('%02d', $loop->iteration) }}</div>
                    <div class="cz-zone-copy"><strong>{{ $zona->nombre }}</strong><small>{{ $cantidad }} {{ $cantidad === 1 ? 'mesa' : 'mesas' }}</small></div>
                    <button type="button" class="cz-iconbtn" aria-label="Editar zona {{ $zona->nombre }}" data-edit-zone="{{ $zona->id_zona }}" data-name="{{ $zona->nombre }}">Editar</button>
                </div>
            @empty
                <div class="cz-empty">Aún no hay zonas. Crea la primera para comenzar.</div>
            @endforelse
            <button type="button" class="cz-addzone" data-open="zona">+ Agregar otra zona</button>
        </section>

        <section class="cz-panel cz-tablepanel">
            <div class="cz-panelhead"><div><span>DISTRIBUCIÓN</span><h2>Directorio de mesas</h2></div><span class="cz-count" id="cz-visible">{{ $mesas->count() }} mesas</span></div>
            <div class="cz-filters">
                <input id="cz-search" type="search" placeholder="Buscar mesa o zona..." aria-label="Buscar mesa o zona">
                <select id="cz-filter" aria-label="Filtrar por zona">
                    <option value="">Todas las zonas</option>
                    @foreach($zonas as $zona)<option value="{{ $zona->id_zona }}">{{ $zona->nombre }}</option>@endforeach
                </select>
            </div>
            <div class="cz-tablewrap"><table>
                <thead><tr><th>MESA</th><th>ZONA</th><th>CAPACIDAD</th><th>ESTADO FÍSICO</th><th>ACCIÓN</th></tr></thead>
                <tbody id="cz-tablebody">
                    @forelse($mesas as $mesa)
                        <tr data-search="{{ mb_strtolower('mesa '.$mesa->numero.' '.$mesa->zona_nombre) }}" data-zone="{{ $mesa->id_zona }}">
                            <td><span class="cz-mesatag">{{ str_pad((string)$mesa->numero, 2, '0', STR_PAD_LEFT) }}</span><strong>Mesa {{ $mesa->numero }}</strong>@if($mesa->observaciones)<small>{{ $mesa->observaciones }}</small>@endif</td>
                            <td>{{ $mesa->zona_nombre }}</td>
                            <td>{{ $mesa->capacidad }} personas</td>
                            <td><span class="cz-status {{ mb_strtolower((string) $mesa->estado_fisico) === 'disponible' ? 'good' : 'warn' }}">{{ $mesa->estado_fisico ?: 'Sin estado' }}</span></td>
                            <td><button type="button" class="cz-link" data-edit-table="{{ $mesa->id_mesa }}" data-zone="{{ $mesa->id_zona }}" data-number="{{ $mesa->numero }}" data-capacity="{{ $mesa->capacidad }}" data-status="{{ $mesa->estado_fisico }}" data-observations="{{ $mesa->observaciones }}">Editar</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="cz-empty">Aún no hay mesas registradas.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            <p class="cz-footnote">El estado físico no representa la ocupación actual: esta se determina mediante atenciones y servicios.</p>
        </section>
    </div>
</div>

<div class="cz-modal" id="cz-zona-modal" hidden>
    <div class="cz-shade" data-close></div>
    <div class="cz-dialog" role="dialog" aria-modal="true" aria-labelledby="cz-zone-title">
        <form id="cz-zone-form" method="POST" action="{{ route('capitan.mesas.zonas.store') }}">
            @csrf
            <input type="hidden" name="_method" id="cz-zone-method" value="POST" disabled>
            <div class="cz-dialog-head"><div><span>NUTRILIADO / ESPACIOS</span><h2 id="cz-zone-title">Nueva zona</h2></div><button type="button" data-close aria-label="Cerrar">×</button></div>
            <label>Nombre de zona <input name="nombre" id="cz-zone-name" maxlength="100" minlength="3" placeholder="Ej. Comedor principal" required></label>
            <div class="cz-dialog-actions"><button type="button" class="cz-btn cz-btn-light" data-close>Cancelar</button><button type="submit" class="cz-btn cz-btn-primary">Guardar zona</button></div>
        </form>
    </div>
</div>

<div class="cz-modal" id="cz-mesa-modal" hidden>
    <div class="cz-shade" data-close></div>
    <div class="cz-dialog" role="dialog" aria-modal="true" aria-labelledby="cz-table-title">
        <form id="cz-table-form" method="POST" action="{{ route('capitan.mesas.mesas.store') }}">
            @csrf
            <input type="hidden" name="_method" id="cz-table-method" value="PUT" disabled>
            <div class="cz-dialog-head"><div><span>NUTRILIADO / ESPACIOS</span><h2 id="cz-table-title">Nueva mesa</h2></div><button type="button" data-close aria-label="Cerrar">×</button></div>
            <label>Zona <select name="id_zona" id="cz-table-zone" required>
                <option value="">Selecciona una zona</option>
                @foreach($zonas as $zona)<option value="{{ $zona->id_zona }}">{{ $zona->nombre }}</option>@endforeach
            </select></label>
        <label>Observaciones opcionales <textarea name="observaciones" id="cz-table-notes" maxlength="500" rows="3"></textarea></label>
            <div class="cz-form-grid"><label>Mesas en esta zona<input type="number" name="cantidad" id="cz-table-quantity" min="1" max="200" value="1" required></label>
                <label>Sillas por mesa<input type="number" name="capacidad" id="cz-table-capacity" min="1" max="100" required></label></div>
            <input type="hidden" name="estado_fisico" id="cz-table-status" value="Disponible">
            <input type="hidden" name="numero" id="cz-table-number" value="">
            <div class="cz-dialog-actions"><button type="button" class="cz-btn cz-btn-light" data-close>Cancelar</button><button type="submit" class="cz-btn cz-btn-primary">Guardar mesa</button></div>
        </form>
    </div>
</div>

<div class="cz-modal" id="cz-total-modal" hidden>
    <div class="cz-shade" data-close></div><div class="cz-dialog" role="dialog" aria-modal="true">
        <form method="POST" action="{{ route('capitan.mesas.programar-total') }}">@csrf
            <div class="cz-dialog-head"><div><span>CONTROL DEL COMEDOR</span><h2>Programar mesas totales</h2></div><button type="button" data-close aria-label="Cerrar">×</button></div>
            <label>Total de mesas de la sede<input type="number" name="mesas_programadas" min="1" max="100000" value="{{ $mesasProgramadas }}" required><small>Este total limita la suma de mesas asignadas entre todas las zonas.</small></label>
            <div class="cz-dialog-actions"><button type="button" class="cz-btn cz-btn-light" data-close>Cancelar</button><button type="submit" class="cz-btn cz-btn-primary">Guardar total</button></div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const zoneModal = document.getElementById('cz-zona-modal');
    const tableModal = document.getElementById('cz-mesa-modal');
    const totalModal = document.getElementById('cz-total-modal');
    const zoneForm = document.getElementById('cz-zone-form');
    const tableForm = document.getElementById('cz-table-form');
    const zoneBase = @json(route('capitan.mesas.zonas.store'));
    const tableBase = @json(route('capitan.mesas.mesas.store'));
    const open = modal => { modal.hidden = false; document.body.style.overflow = 'hidden'; modal.querySelector('input:not([type=hidden]), select')?.focus(); };
    const close = () => { zoneModal.hidden = true; tableModal.hidden = true; totalModal.hidden = true; document.body.style.overflow = ''; };
    document.querySelectorAll('[data-close]').forEach(el => el.addEventListener('click', close));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
    document.querySelectorAll('[data-open]').forEach(button => button.addEventListener('click', () => {
        if (button.dataset.open === 'total') {
            open(totalModal);
        } else if (button.dataset.open === 'zona') {
            zoneForm.reset(); zoneForm.action = zoneBase;
            document.getElementById('cz-zone-method').disabled = true;
            document.getElementById('cz-zone-title').textContent = 'Nueva zona';
            open(zoneModal);
        } else {
            tableForm.reset(); tableForm.action = tableBase;
            if (button.dataset.zone) document.getElementById('cz-table-zone').value = button.dataset.zone;
            document.getElementById('cz-table-method').disabled = true;
            document.getElementById('cz-table-title').textContent = 'Nueva mesa';
            open(tableModal);
        }
    }));
    document.querySelectorAll('[data-edit-zone]').forEach(button => button.addEventListener('click', () => {
        zoneForm.action = zoneBase + '/' + encodeURIComponent(button.dataset.editZone);
        document.getElementById('cz-zone-method').disabled = false;
        document.getElementById('cz-zone-method').value = 'PUT';
        document.getElementById('cz-zone-name').value = button.dataset.name;
        document.getElementById('cz-zone-title').textContent = 'Editar zona';
        open(zoneModal);
    }));
    document.querySelectorAll('[data-edit-table]').forEach(button => button.addEventListener('click', () => {
        tableForm.action = tableBase + '/' + encodeURIComponent(button.dataset.editTable);
        document.getElementById('cz-table-method').disabled = false;
        document.getElementById('cz-table-method').value = 'PUT';
        document.getElementById('cz-table-zone').value = button.dataset.zone;
        document.getElementById('cz-table-number').value = button.dataset.number;
        document.getElementById('cz-table-capacity').value = button.dataset.capacity;
        document.getElementById('cz-table-status').value = button.dataset.status;
        document.getElementById('cz-table-notes').value = button.dataset.observations || '';
        document.getElementById('cz-table-title').textContent = 'Editar mesa';
        open(tableModal);
    }));
    const search = document.getElementById('cz-search');
    const filter = document.getElementById('cz-filter');
    function applyFilters() {
        let visible = 0;
        document.querySelectorAll('#cz-tablebody tr[data-search]').forEach(tr => {
            const match = tr.dataset.search.includes(search.value.trim().toLocaleLowerCase()) && (!filter.value || tr.dataset.zone === filter.value);
            tr.hidden = !match; if (match) visible++;
        });
        document.getElementById('cz-visible').textContent = visible + ' mesas';
    }
    search.addEventListener('input', applyFilters);
    filter.addEventListener('change', applyFilters);
})();
</script>
@endpush
