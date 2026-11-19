<span class="kp-dish-photo">
    @if(filled($image))
        <img src="{{ \App\Support\ChefWorkspace::image($image) }}" alt="" loading="lazy">
    @else
        <x-icon name="dish"/>
    @endif
</span>
