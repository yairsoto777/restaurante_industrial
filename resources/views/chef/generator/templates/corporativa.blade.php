<header class="gm-oxford-masthead"><div class="gm-oxford-brand">@include('chef.generator.brand')<span class="gm-kicker">NUTRILIADO / ALIMENTACIÓN</span></div><div class="gm-oxford-title">@include('chef.generator.title')</div></header>
@if($document['hero'])<figure class="gm-oxford-hero gm-hero gm-food-photo"><img src="{{ $document['hero'] }}" alt="Platillo registrado en este menú"><figcaption>Preparado para disfrutar cada día</figcaption></figure>@endif
@include('chef.generator.body',['layout'=>'oxford'])
