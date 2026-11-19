<div class="gm-menu-body gm-layout-{{ $layout }}">
@foreach($document['weeks'] as $week=>$services)
    <section class="gm-week"><h2 class="gm-week-heading">Semana del {{ \Illuminate\Support\Carbon::parse($week)->locale('es')->translatedFormat('j \d\e F') }}</h2>
    @foreach($services as $service)<section class="gm-service" data-service-id="{{ $service['id'] }}">
        <header class="gm-service-heading"><div class="gm-service-date"><span>{{ $service['day'] }}</span><strong>{{ $service['date'] }}</strong></div><div><h3>{{ $service['name'] }}</h3><p class="gm-hours">{{ $service['hours'] }}</p></div>@if($service['draft'] && !$document['settings']['publico'])<small class="gm-draft-tag">Borrador interno · servicio sin autorización operativa</small>@endif</header>
        <div class="gm-categories">@foreach($service['groups'] as $category=>$dishes)<section class="gm-category"><div class="gm-category-lead"><h4>{{ $category }}</h4>@include('chef.generator.dish',['dish'=>$dishes[0],'number'=>1])</div>@foreach(array_slice($dishes,1) as $index=>$dish)@include('chef.generator.dish',['number'=>$index+2])@endforeach</section>@endforeach</div>
    </section>@endforeach</section>
@endforeach
@if(!$document['dishCount'])<p class="gm-empty">No hay platillos configurados para esta selección.</p>@endif
</div>
