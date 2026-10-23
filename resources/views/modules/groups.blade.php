@extends('layouts.app')
@section('title', 'Grupos y entregas')
@section('content')
@php($canManage = auth()->user()->hasRole('Jefe de cocina'))
<div class="chef-page">
    <section class="chef-hero" aria-labelledby="groups-title">
        <div class="chef-hero-icon"><x-icon name="users" /></div>
        <div class="chef-hero-copy"><span class="chef-eyebrow">{{ $site->nombre }} · Cocina</span><h1 id="groups-title">Grupos y entregas</h1><p>Organiza la atención de los grupos y consulta las entregas registradas para los servicios de la sede.</p></div>
    </section>

    <section class="chef-stat-grid" aria-label="Resumen de grupos y entregas">
        <article class="chef-stat"><span class="chef-stat-icon chef-stat-blue"><x-icon name="users" /></span><div><strong>{{ $groupCount }}</strong><span>Grupos registrados</span></div></article>
        <article class="chef-stat"><span class="chef-stat-icon chef-stat-indigo"><x-icon name="group" /></span><div><strong>{{ $plannedGuests }}</strong><span>Comensales previstos</span></div></article>
        <article class="chef-stat"><span class="chef-stat-icon chef-stat-amber"><x-icon name="map" /></span><div><strong>{{ $attentionCount }}</strong><span>Atenciones asignadas</span></div></article>
        <article class="chef-stat"><span class="chef-stat-icon chef-stat-green"><x-icon name="plates" /></span><div><strong>{{ $deliveryCount }}</strong><span>Entregas registradas</span></div></article>
    </section>

    @if($canManage)
        <section class="chef-panel">
            <div class="chef-panel-heading"><div><span class="chef-kicker">Acciones de cocina</span><h2>Registrar una operación</h2></div></div>
            <div class="chef-workflow-grid">
                <form class="chef-form-card" method="POST" action="{{ route('groups.store') }}">
                    @csrf
                    <div class="chef-form-card-heading"><span class="chef-stat-icon chef-stat-blue"><x-icon name="users" /></span><div><h3>Agregar grupo</h3><p>Vincúlalo a un servicio aprobado.</p></div></div>
                    @if(! $groupsAvailable)<div class="chef-notice chef-notice-warning">Las tablas necesarias para grupos y servicios no están disponibles.</div>
                    @elseif($services->isEmpty())<div class="chef-notice chef-notice-warning">No hay servicios aprobados y operativos para asignar un grupo.</div>@endif
                    <label class="chef-field"><span>Servicio</span><select name="id_servicio" required @disabled(! $groupsAvailable || $services->isEmpty())><option value="">Selecciona un servicio</option>@foreach($services as $service)<option value="{{ $service->id_servicio }}" @selected((string) old('id_servicio') === (string) $service->id_servicio)>{{ $service->nombre }} · {{ $service->fecha?->format('d/m/Y') }}</option>@endforeach</select></label>
                    <label class="chef-field"><span>Nombre del grupo</span><input name="nombre" value="{{ old('nombre') }}" maxlength="100" required placeholder="Nombre identificador"></label>
                    <div class="chef-form-pair"><label class="chef-field"><span>Inicio</span><input type="time" name="inicio_hora" value="{{ old('inicio_hora') }}" required></label><label class="chef-field"><span>Fin</span><input type="time" name="fin_hora" value="{{ old('fin_hora') }}" required></label></div>
                    <label class="chef-field"><span>Comensales previstos</span><input type="number" name="comensales_pre" value="{{ old('comensales_pre') }}" min="1" max="100000" step="1" required></label>
                    <button class="chef-button chef-button-primary" type="submit" @disabled(! $groupsAvailable || $services->isEmpty())>Guardar grupo</button>
                </form>

                <form class="chef-form-card" method="POST" action="{{ route('groups.attentions.store') }}">
                    @csrf
                    <div class="chef-form-card-heading"><span class="chef-stat-icon chef-stat-indigo"><x-icon name="map" /></span><div><h3>Asignar grupo a mesa</h3><p>Registra la atención y su capacidad.</p></div></div>
                    @if(! $attentionAvailable)<div class="chef-notice chef-notice-warning">Faltan tablas para asignar grupos a mesas.</div>
                    @elseif($services->isEmpty() || $groups->isEmpty() || $tables->isEmpty())<div class="chef-notice chef-notice-warning">Se requiere un servicio aprobado, un grupo y una mesa activa de la sede.</div>@endif
                    <label class="chef-field"><span>Servicio aprobado</span><select name="id_servicio" id="attention-service-select" required @disabled(! $attentionAvailable || $services->isEmpty())><option value="">Selecciona un servicio</option>@foreach($services as $service)<option value="{{ $service->id_servicio }}" @selected((string) old('id_servicio') === (string) $service->id_servicio)>{{ $service->nombre }} · {{ $service->fecha?->format('d/m/Y') }}</option>@endforeach</select></label>
                    <label class="chef-field"><span>Grupo</span><select name="id_grupo" id="attention-group-select" required @disabled(! $attentionAvailable || $groups->isEmpty())><option value="">Selecciona un grupo</option>@foreach($groups as $group)<option value="{{ $group->id_grupo }}" data-service="{{ $group->id_servicio }}" @selected((string) old('id_grupo') === (string) $group->id_grupo)>{{ $group->nombre }} · {{ $group->servicio_nombre }}</option>@endforeach</select></label>
                    <label class="chef-field"><span>Mesa</span><select name="id_mesa" required @disabled(! $attentionAvailable || $tables->isEmpty())><option value="">Selecciona una mesa</option>@foreach($tables as $table)<option value="{{ $table->id_mesa }}" @selected((string) old('id_mesa') === (string) $table->id_mesa)>{{ $table->zona }} · Mesa {{ $table->numero }} · {{ $table->capacidad ?? 'Capacidad sin definir' }}</option>@endforeach</select></label>
                    <label class="chef-field"><span>Mesero responsable <small>(opcional)</small></span><select name="id_usuario_mesero"><option value="">Sin asignar</option>@foreach($waiters as $waiter)<option value="{{ $waiter->id_usuario }}" @selected((string) old('id_usuario_mesero') === (string) $waiter->id_usuario)>{{ $waiter->nombre }} {{ $waiter->apellido_p }}</option>@endforeach</select></label>
                    <div class="chef-form-pair"><label class="chef-field"><span>Comensales</span><input type="number" name="comensales" value="{{ old('comensales') }}" min="1" max="100000" required></label><label class="chef-field"><span>Inicio <small>(opcional)</small></span><input type="time" name="inicio_hora" value="{{ old('inicio_hora') }}"></label></div>
                    <button class="chef-button chef-button-primary" type="submit" @disabled(! $attentionAvailable || $services->isEmpty() || $groups->isEmpty() || $tables->isEmpty())>Asignar mesa</button>
                </form>

                <form class="chef-form-card" method="POST" action="{{ route('groups.deliveries.store') }}">
                    @csrf
                    <div class="chef-form-card-heading"><span class="chef-stat-icon chef-stat-green"><x-icon name="plates" /></span><div><h3>Registrar entrega</h3><p>Guarda platillo, cantidad y responsable.</p></div></div>
                    @if(! $deliveryAvailable)<div class="chef-notice chef-notice-warning">El esquema para registrar entregas no está disponible.</div>
                    @elseif($attentions->isEmpty() || $menuItems->isEmpty())<div class="chef-notice chef-notice-warning">Se necesita una atención y un platillo publicado y disponible.</div>@endif
                    <label class="chef-field"><span>Atención</span><select name="id_atencion" required @disabled(! $deliveryAvailable || $attentions->isEmpty())><option value="">Selecciona atención</option>@foreach($attentions as $attention)<option value="{{ $attention->id_atencion }}" @selected((string) old('id_atencion') === (string) $attention->id_atencion)>Mesa {{ $attention->mesa_numero }} · {{ $attention->servicio_nombre }} · {{ $attention->grupo_nombre ?: 'Sin grupo' }}</option>@endforeach</select></label>
                    <label class="chef-field"><span>Platillo publicado</span><select name="id_menu" required @disabled(! $deliveryAvailable || $menuItems->isEmpty())><option value="">Selecciona un platillo</option>@foreach($menuItems as $item)<option value="{{ $item->id_menu }}" @selected((string) old('id_menu') === (string) $item->id_menu)>{{ $item->platillo_nombre }} · {{ $item->servicio_nombre }}</option>@endforeach</select></label>
                    <label class="chef-field"><span>Cantidad</span><input type="number" name="cantidad" value="{{ old('cantidad') }}" min="1" max="100000" step="1" required></label>
                    <button class="chef-button chef-button-primary" type="submit" @disabled(! $deliveryAvailable || $attentions->isEmpty() || $menuItems->isEmpty())>Guardar entrega</button>
                </form>
            </div>
        </section>
    @endif

    <section class="chef-panel">
        <div class="chef-panel-heading"><div><span class="chef-kicker">Planificación</span><h2>Grupos por servicio</h2></div><span class="chef-count">{{ $groups->count() }} registros</span></div>
        @if(! $groupsAvailable)<div class="chef-empty"><strong>El registro de grupos no está disponible</strong><p>El esquema conectado no contiene todas las relaciones requeridas.</p></div>
        @else<div class="chef-table-wrap"><table class="chef-table"><thead><tr><th>Grupo</th><th>Servicio</th><th>Fecha y horario</th><th>Comensales</th><th>Atenciones</th><th>Entregas</th></tr></thead><tbody>
            @forelse($groups as $group)<tr><td><strong>{{ $group->nombre }}</strong></td><td>{{ $group->servicio_nombre }}</td><td>{{ $group->servicio_fecha ? \Carbon\Carbon::parse($group->servicio_fecha)->format('d/m/Y') : 'Sin fecha' }}<small class="chef-cell-note">{{ $group->inicio_hora ? substr($group->inicio_hora, 0, 5) : '--:--' }} – {{ $group->fin_hora ? substr($group->fin_hora, 0, 5) : '--:--' }}</small></td><td>{{ number_format((int) $group->comensales_pre) }}</td><td>{{ $group->total_atenciones }}</td><td>{{ $group->total_entregas }}</td></tr>
            @empty<tr><td colspan="6"><div class="chef-empty"><span class="chef-empty-icon"><x-icon name="users" /></span><strong>Aún no hay grupos registrados</strong><p>Los grupos aparecerán aquí al asociarlos con un servicio aprobado.</p></div></td></tr>@endforelse
        </tbody></table></div>@endif
    </section>

    <section class="chef-panel">
        <div class="chef-panel-heading"><div><span class="chef-kicker">Servicio en sede</span><h2>Atenciones y entregas recientes</h2></div><span class="chef-count">{{ $deliveryCount }} entregas</span></div>
        @if(! $attentionAvailable || ! $deliveryAvailable)<div class="chef-empty"><strong>La consulta operativa está incompleta</strong><p>Una o más relaciones de servicio, atención o entrega no están disponibles en la base conectada.</p></div>
        @elseif($deliveries->isEmpty())<div class="chef-empty"><span class="chef-empty-icon"><x-icon name="plates" /></span><strong>No hay entregas registradas</strong><p>Cuando se registren entregas, se mostrarán aquí con su servicio, grupo, platillo, cantidad y usuario.</p></div>
        @else<div class="chef-table-wrap"><table class="chef-table"><thead><tr><th>Fecha</th><th>Servicio</th><th>Grupo</th><th>Platillo</th><th>Cantidad</th><th>Registró</th></tr></thead><tbody>@foreach($deliveries as $delivery)<tr><td>{{ $delivery->fecha_entrega ? \Carbon\Carbon::parse($delivery->fecha_entrega)->format('d/m/Y H:i') : '—' }}</td><td>{{ $delivery->servicio_nombre }}</td><td>{{ $delivery->grupo_nombre ?: 'Sin grupo' }}</td><td><strong>{{ $delivery->platillo_nombre }}</strong></td><td>{{ number_format((int) $delivery->cantidad) }}</td><td>{{ trim(($delivery->usuario_nombre ?? '').' '.($delivery->usuario_apellido ?? '')) ?: 'Sin nombre' }}</td></tr>@endforeach</tbody></table></div>@endif
    </section>
</div>
@if($canManage)<script>
document.querySelectorAll('form[data-confirm]').forEach((form)=>form.addEventListener('submit',(event)=>{if(!window.confirm(form.dataset.confirm))event.preventDefault()}));
const serviceSelect=document.getElementById('attention-service-select');
const groupSelect=document.getElementById('attention-group-select');
const filterGroups=()=>{if(!serviceSelect||!groupSelect)return;const serviceId=serviceSelect.value;for(const option of groupSelect.options){if(!option.value)continue;option.hidden=!!serviceId&&option.dataset.service!==serviceId;if(option.selected&&option.hidden)groupSelect.value='';}};
serviceSelect?.addEventListener('change',filterGroups);filterGroups();
</script>@endif
@endsection
