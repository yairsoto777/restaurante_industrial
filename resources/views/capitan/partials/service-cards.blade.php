@push('styles')
<link rel="stylesheet" href="{{ asset('css/captain-service-cards.css') }}?v={{ filemtime(public_path('css/captain-service-cards.css')) }}">
@endpush
@php
    // Description belongs to the approved revision of this dated service, not a later proposal.
    $revisionDescriptions=collect();
    $revisionIds=$servicios->pluck('schedule_revision_id')->filter()->unique();
    if($revisionIds->isNotEmpty() && \Illuminate\Support\Facades\Schema::hasTable('service_schedule_revisions')) {
        $revisionDescriptions=\Illuminate\Support\Facades\DB::table('service_schedule_revisions as revision')
            ->join('service_schedules as schedule','schedule.id','=','revision.schedule_id')
            ->where('schedule.id_sede',$sede->id_sede)->whereIn('revision.id',$revisionIds)
            ->get(['revision.id','revision.settings'])->mapWithKeys(fn($revision)=>[$revision->id=>\App\Support\ServiceRecurrence::settings($revision)['descripcion'] ?? '']);
    }
@endphp
<div class="cw-service-grid captain-services-grid">
@forelse($servicios as $service)
    @php
        $sc=$serviceCoverage[$service->id_servicio];
        $staff=$sc['staff']->unique('id_usuario_mesero')->values();
        $name=mb_strtolower($service->nombre);
        $meal=str_contains($name,'desayun')?'breakfast':(str_contains($name,'comida')||str_contains($name,'almuerzo')?'lunch':(str_contains($name,'cena')?'dinner':'neutral'));
        $description=trim((string)($service->descripcion ?? '')) ?: trim((string)$revisionDescriptions->get($service->schedule_revision_id ?? null,''));
        $tableCount=$mesasServicio->count();
        $coverageTone=$tableCount===0?'neutral':($sc['covered']===0?'empty':($sc['covered']>=$tableCount?'complete':'partial'));
        $fullName=fn($person)=>trim(implode(' ',array_filter([$person->nombre,$person->apellido_p ?? '',$person->apellido_m ?? ''])));
    @endphp
    <article class="cw-card cw-service captain-service-card captain-meal-{{ $meal }}" data-cw-record data-state="{{ $service->estado }}">
        <div class="cw-service-cover captain-service-cover">
            <img src="{{ \App\Support\ChefWorkspace::serviceImage($service) }}" alt="Portada de {{ $service->nombre }}" loading="lazy">
            <span class="captain-service-folio">Servicio #{{ $service->id_servicio }}</span>
            <span class="cw-badge {{ $service->estado==='Finalizado'?'green':$CW::tone($service->estado) }}">{{ $service->estado }}</span>
        </div>
        <div class="cw-service-body captain-service-body">
            <div class="captain-service-intro">
                <span class="captain-service-icon" aria-hidden="true">
                    @if($meal==='breakfast')<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5"/></svg>
                    @elseif($meal==='dinner')<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"><path d="M20.5 14A8.7 8.7 0 0 1 10 3.5 8.8 8.8 0 1 0 20.5 14Z"/></svg>
                    @else<x-icon name="utensils"/>@endif
                </span>
                <div><h2>{{ $service->nombre }}</h2><p class="captain-service-description" title="{{ $description }}">{{ $description ?: 'Sin descripción registrada.' }}</p></div>
            </div>
            <dl class="captain-service-facts">
                <div><dt><x-icon name="clock"/>Horario</dt><dd>{{ substr($service->inicio_hora,0,5) }} – {{ substr($service->fin_hora,0,5) }}</dd></div>
                <div><dt><x-icon name="users"/>Capacidad</dt><dd>{{ $service->capacidad ?? '—' }} comensales</dd></div>
                <div><dt><x-icon name="calendar"/>Jornada</dt><dd>{{ \Carbon\Carbon::parse($service->fecha)->locale('es')->translatedFormat('d M Y') }}</dd></div>
            </dl>
            <div class="captain-service-team">
                <section><h3>Zonas configuradas</h3><div class="captain-service-zones">
                    @forelse($zonas as $zone)<span class="captain-service-zone" style="--service-zone:{{ $zone->color ?? '#17375E' }}">{{ $zone->nombre }}</span>@empty<span class="captain-service-empty">Sin zonas configuradas</span>@endforelse
                </div></section>
                <section><h3>Meseros asignados</h3><div class="captain-service-avatars">
                    @foreach($staff->take(4) as $person)
                        @php($person->photo=$CW::photo((int)$sede->id_sede,(int)$person->id_usuario_mesero))
                        <span class="captain-service-avatar" title="{{ $fullName($person) }}" aria-label="{{ $fullName($person) }}" tabindex="0"><x-capitan.avatar :person="$person"/></span>
                    @endforeach
                    @if($staff->count()>4)<span class="captain-service-more" title="{{ $staff->skip(4)->map($fullName)->implode(', ') }}" aria-label="{{ $staff->count()-4 }} meseros adicionales" tabindex="0">+{{ $staff->count()-4 }}</span>@endif
                    @if($staff->isEmpty())<span class="captain-service-empty">Sin meseros asignados</span>@endif
                </div></section>
            </div>
            <div class="captain-service-coverage captain-coverage-{{ $coverageTone }}">
                <div><span>Cobertura de atención</span><strong>{{ $tableCount ? $sc['percent'].'%' : 'Sin mesas' }}</strong></div>
                <div class="captain-service-progress" role="progressbar" aria-label="Cobertura de atención de {{ $service->nombre }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $sc['percent'] }}"><i style="width:{{ $sc['percent'] }}%"></i></div>
                <small>{{ $sc['covered'] }} de {{ $tableCount }} mesas cubiertas{{ !$jornadaSupported?' · asignación general heredada':'' }}</small>
            </div>
            <footer class="captain-service-actions">
                <a class="cw-btn" href="{{ $link('comedor',$service->id_servicio) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg><span>Ver comedor</span></a>
                <a class="cw-btn cw-outline" href="{{ $link('zonas',$service->id_servicio) }}"><x-icon name="map"/><span>Organizar zonas</span></a>
                <a class="cw-btn cw-outline" href="{{ $link('asignaciones',$service->id_servicio) }}"><x-icon name="users"/><span>Asignar meseros</span></a>
            </footer>
        </div>
    </article>
@empty<p class="cw-empty">No hay servicios aprobados en esta fecha.</p>@endforelse
</div>
