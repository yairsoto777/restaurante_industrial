@extends('layouts.app')

@section('title', 'Cuentas por cobrar')

@push('styles')
    @vite('resources/css/cash.css')
@endpush

@section('content')
<div class="cash-page">
    <div class="cash-page-heading"><div><span class="section-kicker">Caja · {{ $site->nombre }}</span><h1>Cuentas por cobrar</h1><p>Consulta las cuentas generadas desde las comandas de la sede activa.</p></div><a class="cash-button secondary" href="{{ route('cash.index') }}">Volver a caja</a></div>
    <section class="cash-panel">
        <form class="cash-filters" method="GET" action="{{ route('cash.accounts.index') }}">
            <label><span>Buscar</span><input type="search" name="q" value="{{ $search }}" placeholder="Folio, mesa, servicio o grupo"></label>
            <label><span>Estado</span><select name="estado"><option value="">Todos</option><option value="abierta" @selected($state === 'abierta')>Pendientes</option><option value="pagada" @selected(in_array($state, ['pagada', 'cobrada'], true))>Cobradas</option><option value="cancelada" @selected($state === 'cancelada')>Canceladas</option></select></label>
            <button class="cash-button primary" type="submit">Buscar</button><a class="cash-button secondary" href="{{ route('cash.accounts.index') }}">Limpiar</a>
        </form>
    </section>
    <section class="cash-panel"><div class="cash-panel-head"><div><span class="section-kicker">Resultados</span><h2>Listado de cuentas</h2></div><span class="cash-count">{{ $query->total() }} registros</span></div><div class="cash-table-wrap"><table class="cash-table"><thead><tr><th>Cuenta</th><th>Atención</th><th>Servicio</th><th>Grupo</th><th>Total</th><th>Estado</th><th>Apertura</th><th></th></tr></thead><tbody>
    @forelse($query as $account)
        <tr><td><strong>#{{ $account->id_cuenta }}</strong></td><td>Mesa {{ $account->mesa_numero ?: 'sin definir' }}<small class="cash-cell-note">Atención #{{ $account->id_atencion }}</small></td><td>{{ $account->servicio_nombre }}</td><td>{{ $account->grupo_nombre ?: 'Sin grupo' }}</td><td><strong>${{ number_format((float) $account->total, 2) }}</strong></td><td><span class="cash-status-pill {{ $account->estado }}">{{ $account->estado === 'abierta' ? 'Pendiente' : ucfirst($account->estado) }}</span></td><td>{{ \Carbon\Carbon::parse($account->fecha_apertura)->format('d/m/Y H:i') }}</td><td><a class="cash-button small {{ $account->estado === 'abierta' ? 'primary' : 'secondary' }}" href="{{ route('cash.accounts.show', $account->id_cuenta) }}">Consultar</a></td></tr>
    @empty
        <tr><td colspan="8"><div class="cash-empty"><x-icon name="layers" /><strong>No hay cuentas para mostrar</strong><span>Las cuentas aparecerán cuando exista una comanda entregada con importes confiables.</span></div></td></tr>
    @endforelse
    </tbody></table></div><div class="cash-pagination">{{ $query->links() }}</div></section>
</div>
@endsection
