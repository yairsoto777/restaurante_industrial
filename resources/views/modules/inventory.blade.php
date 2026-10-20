@extends('layouts.app')
@section('title', 'Inventario de insumos')
@section('content')
<div class="page-heading"><div><span class="section-kicker">Almacén · {{ $site->nombre }}</span><h1>Inventario de insumos</h1><p>Consulta existencias de la sede. Esta pantalla no modifica movimientos.</p></div></div>
<section class="panel"><div class="panel-head"><h2>Existencias</h2><span class="catalog-count">{{ $rows->count() }} registro(s){{ $table ? ' · '.$table : '' }}</span></div><div class="table-wrap"><table class="catalog-table"><thead><tr><th>Insumo</th><th>Existencia</th><th>Unidad</th><th>Ubicación</th></tr></thead><tbody>@forelse($rows as $row)<tr><td>{{ $row->id_insumo ?? '—' }}</td><td>{{ $row->existencia ?? $row->cantidad ?? $row->stock ?? '—' }}</td><td>{{ $row->id_unidad ?? '—' }}</td><td>{{ $row->ubicacion ?? '—' }}</td></tr>@empty<tr><td colspan="4"><div class="empty-state">No hay existencias registradas para esta sede.</div></td></tr>@endforelse</tbody></table></div></section>
@endsection
