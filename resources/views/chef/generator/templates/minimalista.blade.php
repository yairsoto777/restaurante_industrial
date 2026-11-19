<header class="gm-minimal-masthead">@include('chef.generator.brand')<div><span class="gm-kicker">NUESTRA CARTA</span>@include('chef.generator.title')</div></header>
@if($document['hero'])<figure class="gm-minimal-hero gm-hero gm-food-photo"><img src="{{ $document['hero'] }}" alt="Platillo registrado en este menú"></figure>@endif
@include('chef.generator.body',['layout'=>'minimal'])
