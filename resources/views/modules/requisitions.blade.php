@extends('layouts.app')
@section('title', 'Requisiciones')
@section('content')
<div class="chef-page">
    <section class="chef-hero" aria-labelledby="requisitions-title">
        <div class="chef-hero-copy"><span class="chef-eyebrow">{{ $site->nombre }} · Almacén</span><h1 id="requisitions-title">Requisiciones</h1><p>Consulta las solicitudes persistidas por cocina y su estado operativo.</p></div>
    </section>
    <section class="chef-panel">
        @if(! $available)
            <div class="chef-empty"><strong>Requisiciones no disponibles</strong><p>Cocina todavía no genera la tabla de requisiciones. No se muestran datos ficticios ni se crean solicitudes automáticamente.</p></div>
        @elseif($requisitions->isEmpty())
            <div class="chef-empty"><strong>No hay requisiciones para esta sede</strong><p>Las solicitudes autorizables aparecerán aquí cuando cocina registre una requisición real.</p></div>
        @else
            <div class="chef-table-wrap"><table class="chef-table"><thead><tr><th>Identificador</th><th>Sede</th><th>Estado</th></tr></thead><tbody>@foreach($requisitions as $requisition)<tr><td>{{ $requisition->id_requisicion }}</td><td>{{ $site->nombre }}</td><td>{{ $requisition->estado ?? 'Sin estado definido' }}</td></tr>@endforeach</tbody></table></div>
            @if($requisitions->hasPages())<div class="chef-pagination">{{ $requisitions->links() }}</div>@endif
        @endif
    </section>
</div>
@endsection
