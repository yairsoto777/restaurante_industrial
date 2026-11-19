<header class="gm-premium-masthead">@include('chef.generator.brand')<span class="gm-kicker">LA EXPERIENCIA DE NUESTRA COCINA</span>@include('chef.generator.title')<span class="gm-premium-rule" aria-hidden="true">◆</span></header>
@if($document['hero'])<figure class="gm-premium-hero gm-hero gm-food-photo"><img src="{{ $document['hero'] }}" alt="Platillo registrado en este menú"><figcaption>Selección de cocina <span>El gusto está en los detalles</span></figcaption></figure>@endif
@include('chef.generator.body',['layout'=>'premium'])
