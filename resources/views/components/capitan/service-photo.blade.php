@props(['name', 'index' => 0])
@php
    // Decorative image selection only: no service state or operational data changes.
    $photoName = mb_strtolower((string) $name);
    $photoIndex = match (true) {
        str_contains($photoName, 'desayuno'), str_contains($photoName, 'breakfast') => 0,
        str_contains($photoName, 'cena'), str_contains($photoName, 'dinner') => 2,
        str_contains($photoName, 'comida'), str_contains($photoName, 'almuerzo'), str_contains($photoName, 'lunch') => 1,
        default => (int) $index % 3,
    };
    $photoFiles = ['service-breakfast.jpg', 'service-lunch.jpg', 'service-dinner.jpg'];
    $photoKinds = ['breakfast', 'lunch', 'dinner'];
@endphp
<img class="captain-card-photo" data-meal="{{ $photoKinds[$photoIndex] }}" src="{{ asset('img/ui/'.$photoFiles[$photoIndex]) }}" alt="" loading="lazy" decoding="async">
