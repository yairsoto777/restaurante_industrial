@props(['title', 'message', 'icon' => 'activity'])
<div {{ $attributes->class(['ops-empty']) }} role="status"><x-icon :name="$icon" /><strong>{{ $title }}</strong><p>{{ $message }}</p></div>
