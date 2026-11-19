<details class="manager-notifications" data-feed="{{ route($notificationFeed ?? 'manager.notifications.index') }}"
    @if($explicitReview ?? false) data-explicit-review @else data-read="{{ route($notificationRead ?? 'manager.notifications.read') }}" @endif>
    <summary class="notification-button" aria-label="Notificaciones"><x-icon name="bell"/><span class="mn-count" hidden></span></summary>
    <section class="mn-panel" aria-label="Notificaciones de tu sede">
        <header><strong>Notificaciones</strong>@unless($explicitReview ?? false)<button type="button" class="mn-all">Marcar todas como leídas</button>@endunless</header>
        <p class="mn-feedback" role="status">Cargando notificaciones…</p>
        <div class="mn-items"></div>
    </section>
</details>
<link rel="stylesheet" href="{{ asset('css/manager-notifications.css') }}">
<script src="{{ asset('js/manager-notifications.js') }}" defer></script>
