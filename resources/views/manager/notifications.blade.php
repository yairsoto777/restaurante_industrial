<details class="manager-notifications" data-feed="{{ route('manager.notifications.index') }}" data-read="{{ route('manager.notifications.read') }}">
    <summary class="notification-button" aria-label="Notificaciones"><x-icon name="bell"/><span class="mn-count" hidden></span></summary>
    <section class="mn-panel" aria-label="Notificaciones de tu sede">
        <header><strong>Notificaciones</strong><button type="button" class="mn-all">Marcar todas como leídas</button></header>
        <p class="mn-feedback" role="status">Cargando notificaciones…</p>
        <div class="mn-items"></div>
    </section>
</details>
<link rel="stylesheet" href="{{ asset('css/manager-notifications.css') }}">
<script src="{{ asset('js/manager-notifications.js') }}" defer></script>
