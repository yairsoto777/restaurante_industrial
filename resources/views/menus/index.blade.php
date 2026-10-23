@extends('layouts.app')
@section('title', 'Menú por servicio')
@section('content')
@php($canManage = auth()->user()->hasRole('Jefe de cocina'))
<div class="chef-page">
    <section class="chef-hero" aria-labelledby="menu-title">
        <div class="chef-hero-icon"><x-icon name="utensils" /></div>
        <div class="chef-hero-copy"><span class="chef-eyebrow">{{ $site->nombre }} · Menú</span><h1 id="menu-title">Menú por servicio</h1><p>{{ $canManage ? 'Asigna platillos, precios y disponibilidad a cada servicio desde el catálogo de tu sede.' : 'Consulta los platillos publicados y disponibles en servicios aprobados de tu sede.' }}</p></div>
        <a class="chef-button chef-button-secondary" href="{{ route('dishes.index') }}"><x-icon name="dish" />Ver platillos</a>
    </section>

    <section class="chef-stat-grid {{ $canManage ? 'chef-stat-grid-three' : 'chef-stat-grid-two' }}" aria-label="Resumen del menú">
        <article class="chef-stat"><span class="chef-stat-icon chef-stat-blue"><x-icon name="utensils" /></span><div><strong>{{ $selected->count() }}</strong><span>{{ $canManage ? 'Platillos vinculados' : 'Platillos disponibles' }}</span></div></article>
        <article class="chef-stat"><span class="chef-stat-icon chef-stat-green"><x-icon name="check" /></span><div><strong>{{ $publishedCount }}</strong><span>Publicados y disponibles</span></div></article>
        @if($canManage)<article class="chef-stat"><span class="chef-stat-icon chef-stat-amber"><x-icon name="clock" /></span><div><strong>{{ $draftCount }}</strong><span>En borrador</span></div></article>@endif
    </section>

    <section class="chef-panel">
        <div class="chef-panel-heading"><div><span class="chef-kicker">Programación de cocina</span><h2>Selecciona un servicio</h2></div><span class="chef-count">{{ $services->count() }} disponibles</span></div>
        <form class="chef-filters" method="GET" action="{{ route('menus.index') }}">
            <label class="chef-field"><span>Servicio</span><select name="service" required><option value="">Selecciona un servicio</option>@foreach($services as $item)<option value="{{ $item->id_servicio }}" @selected($service?->id_servicio === $item->id_servicio)>{{ $item->nombre }} · {{ $item->fecha?->format('d/m/Y') ?: 'Sin fecha' }} · {{ $item->estado_aprobacion ?: 'Pendiente' }}</option>@endforeach</select></label>
            <button class="chef-button chef-button-secondary" type="submit">Consultar</button>
        </form>
    </section>

    @if($service)
        <section class="chef-panel">
            <div class="chef-panel-heading"><div><span class="chef-kicker">{{ $service->fecha?->format('d/m/Y') ?: 'Fecha por definir' }} · {{ $service->inicio_hora ? substr($service->inicio_hora, 0, 5) : '--:--' }}–{{ $service->fin_hora ? substr($service->fin_hora, 0, 5) : '--:--' }}</span><h2>{{ $service->nombre }}</h2></div>@if($service->estado_aprobacion === 'Aprobado')<span class="chef-pill chef-pill-green">Servicio aprobado</span>@else<span class="chef-pill chef-pill-amber">{{ $service->estado_aprobacion ?: 'Pendiente de aprobación' }}</span>@endif</div>

            @if(! $menuAvailable)
                <div class="chef-empty"><strong>El menú no está disponible</strong><p>La tabla <code>menus</code> debe contener las columnas reales de platillo, servicio, precio, publicación y disponibilidad.</p></div>
            @elseif($dishes->isEmpty())
                <div class="chef-empty"><span class="chef-empty-icon"><x-icon name="dish" /></span><strong>{{ $canManage ? 'No hay platillos para asignar' : 'No hay platillos publicados para este servicio' }}</strong><p>{{ $canManage ? 'Primero debe existir un platillo de esta sede. El catálogo actual no tiene platillos disponibles.' : 'El menú mostrará aquí los platillos que el Jefe de cocina publique y marque como disponibles.' }}</p>@if($canManage)<a class="chef-button chef-button-primary" href="{{ route('dishes.create') }}">Registrar platillo</a>@endif</div>
            @elseif($canManage && ! in_array($service->estado, ['Finalizado', 'Cancelado'], true))
                @php($selectedByDish = $selected->keyBy('id_platillo'))
                @if($service->estado_aprobacion !== 'Aprobado')<div class="chef-notice"><x-icon name="clock" /><span>El servicio sigue pendiente de revisión. Puedes guardar el menú como borrador; publicación quedará desactivada hasta su aprobación.</span></div>@endif
                <form method="POST" action="{{ route('menus.store', $service) }}" class="chef-menu-form" data-confirm="Al guardar, los platillos que quites se conservarán en el historial, pero quedarán no publicados y no disponibles. ¿Continuar?">
                    @csrf
                    <div class="chef-table-wrap">
                        <table class="chef-table">
                            <thead><tr><th>Incluir</th><th>Platillo</th><th>Categoría</th><th>Precio de venta</th><th>Publicado</th><th>Disponible</th></tr></thead>
                            <tbody>
                            @foreach($dishes as $dish)
                                @php($entry = $selectedByDish->get($dish->id_platillo))
                                <tr>
                                    <td><input class="chef-check" type="checkbox" name="platillos[]" value="{{ $dish->id_platillo }}" @checked($entry) aria-label="Incluir {{ $dish->nombre }}"></td>
                                    <td><strong>{{ $dish->nombre }}</strong>@if($dish->descripcion)<small class="chef-cell-note">{{ $dish->descripcion }}</small>@endif</td>
                                    <td>{{ $dish->category?->nombre ?: 'Sin categoría' }}</td>
                                    <td><label class="chef-sr-only" for="price-{{ $dish->id_platillo }}">Precio de {{ $dish->nombre }}</label><input id="price-{{ $dish->id_platillo }}" class="chef-table-input" type="number" name="precio_venta[{{ $dish->id_platillo }}]" min="0" max="99999999.99" step="0.01" value="{{ old('precio_venta.'.$dish->id_platillo, $entry?->precio_venta) }}" placeholder="Sin definir"></td>
                                    <td><label class="chef-switch"><input type="checkbox" name="publicado[{{ $dish->id_platillo }}]" value="1" @checked((bool) old('publicado.'.$dish->id_platillo, $entry?->publicado ?? false)) @disabled($service->estado_aprobacion !== 'Aprobado')><span>Publicar</span></label></td>
                                    <td><label class="chef-switch"><input type="checkbox" name="disponible[{{ $dish->id_platillo }}]" value="1" @checked((bool) old('disponible.'.$dish->id_platillo, $entry?->disponible ?? true))><span>Disponible</span></label></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="chef-form-actions"><span class="chef-muted">Los platillos que quites se archivarán como no publicados y no disponibles para conservar el historial.</span><button class="chef-button chef-button-primary" type="submit">Guardar menú</button></div>
                </form>
            @else
                @if($canManage && in_array($service->estado, ['Finalizado', 'Cancelado'], true))<div class="chef-notice chef-notice-warning"><x-icon name="alert" /><span>El servicio está finalizado o cancelado. El menú se conserva para consulta y no admite cambios.</span></div>@endif<div class="chef-table-wrap"><table class="chef-table"><thead><tr><th>Platillo</th><th>Categoría</th><th>Precio</th><th>Estado</th></tr></thead><tbody>@forelse($selected as $item)@php($dish = $dishes->firstWhere('id_platillo', $item->id_platillo))<tr><td>{{ $dish?->nombre ?: 'Platillo no disponible' }}</td><td>{{ $dish?->category?->nombre ?: 'Sin categoría' }}</td><td>{{ $item->precio_venta !== null ? '$'.number_format((float) $item->precio_venta, 2) : 'Sin precio' }}</td><td>@if($item->publicado && $item->disponible)<span class="chef-pill chef-pill-green">Disponible</span>@else<span class="chef-pill chef-pill-slate">No disponible</span>@endif</td></tr>@empty<tr><td colspan="4"><div class="chef-empty"><strong>Este servicio aún no tiene platillos asignados.</strong></div></td></tr>@endforelse</tbody></table></div>
            @endif
        </section>
    @else
        <section class="chef-panel"><div class="chef-empty"><span class="chef-empty-icon"><x-icon name="calendar" /></span><strong>{{ $canManage ? 'No hay servicios en esta sede' : 'No hay servicios aprobados vigentes' }}</strong><p>{{ $canManage ? 'Registra una propuesta de servicio para preparar el menú.' : 'El Jefe de cocina prepara el menú cuando exista un servicio aprobado y vigente.' }}</p>@if($canManage)<a class="chef-button chef-button-primary" href="{{ route('kitchen.services.create') }}">Registrar servicio</a>@endif</div></section>
    @endif
</div>
<script>document.querySelectorAll('form[data-confirm]').forEach((form)=>form.addEventListener('submit',(event)=>{if(!window.confirm(form.dataset.confirm))event.preventDefault()}));</script>
@endsection
