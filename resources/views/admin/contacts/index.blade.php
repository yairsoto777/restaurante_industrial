@extends('layouts.app')

@section('title', 'Solicitudes de servicio')

@section('content')
<div class="page-heading">
    <div>
        <span class="section-kicker">Atención comercial</span>
        <h1>Solicitudes de servicio</h1>
        <p>Revisa los contactos recibidos desde la landing pública.</p>
    </div>
</div>

<section class="panel">
    <form class="form-grid" method="GET" action="{{ route('admin.contacts.index') }}">
        <label>
            Buscar
            <input name="buscar" value="{{ request('buscar') }}" maxlength="100" placeholder="Nombre, correo o mensaje">
        </label>
        <label>
            Estado
            <select name="estado">
                <option value="">Todos los estados</option>
                @foreach($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(request('estado') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <div class="actions full">
            <a class="button" href="{{ route('admin.contacts.index') }}">Limpiar</a>
            <button class="button primary" type="submit">Filtrar</button>
        </div>
    </form>
</section>

<section class="panel">
    <div class="table-wrap">
        <table class="contacts-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Mensaje</th>
                    <th>Fecha</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse($contacts as $contact)
                @php($statusClass = \Illuminate\Support\Str::slug($contact->estado ?: 'nuevo'))
                <tr>
                    <td><strong>{{ $contact->nombre }}</strong></td>
                    <td>{{ $contact->correo }}</td>
                    <td class="message-cell">{{ \Illuminate\Support\Str::limit($contact->mensaje, 100) }}</td>
                    <td>{{ $contact->fecha_contacto?->format('d/m/Y H:i') ?? 'Sin fecha' }}</td>
                    <td><span class="status {{ $statusClass }}">{{ $contact->statusLabel() }}</span></td>
                    <td>
                        <div class="table-actions">
                            <a class="button compact" href="{{ route('admin.contacts.show', $contact) }}">Ver detalle</a>
                            @if($contact->estado === \App\Models\CommercialContact::STATUS_NEW)
                                <form method="POST" action="{{ route('admin.contacts.update', $contact) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="estado" value="contactado">
                                    <button class="button compact" type="submit">Marcar contactado</button>
                                </form>
                            @endif
                            @if(in_array($contact->estado, [\App\Models\CommercialContact::STATUS_NEW, \App\Models\CommercialContact::STATUS_CONTACTED], true))
                                <a class="button compact primary" href="{{ route('admin.contacts.convert.create', $contact) }}">Aprobar y convertir</a>
                            @endif
                            @if($contact->estado !== \App\Models\CommercialContact::STATUS_APPROVED && $contact->estado !== \App\Models\CommercialContact::STATUS_REJECTED)
                                <form method="POST" action="{{ route('admin.contacts.update', $contact) }}" onsubmit="return confirm('¿Rechazar esta solicitud?')">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="estado" value="rechazado">
                                    <button class="button compact danger" type="submit">Rechazar</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="table-empty">No hay contactos que coincidan con los filtros.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-wrap">{{ $contacts->withQueryString()->links() }}</div>
</section>
@endsection
