<header class="gm-modern-masthead">@include('chef.generator.brand')<div class="gm-modern-heading"><span class="gm-kicker">COCINA / SABOR / ENCUENTRO</span>@include('chef.generator.title')</div></header>
@if($document['hero'])<figure class="gm-modern-hero gm-hero gm-food-photo"><img src="{{ $document['hero'] }}" alt="Platillo registrado en este menú"><figcaption><span>HOY SE DISFRUTA.</span><small>{{ $document['siteName'] }}</small></figcaption></figure>@endif
@include('chef.generator.body',['layout'=>'modern'])
