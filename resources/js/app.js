import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    const shell = document.querySelector('[data-app-shell]');
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const close = document.querySelector('[data-sidebar-close]');

    toggle?.addEventListener('click', () => shell?.classList.toggle('sidebar-open'));
    close?.addEventListener('click', () => shell?.classList.remove('sidebar-open'));
});
