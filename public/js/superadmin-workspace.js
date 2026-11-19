(() => {
    'use strict';
    if (!document.body.classList.contains('superadmin-workspace')) return;
    let returnFocus = null;
    const open = id => {
        const dialog = document.getElementById(id);
        if (!(dialog instanceof HTMLDialogElement)) return;
        returnFocus = document.activeElement;
        document.querySelectorAll('.sa-drawer[open]').forEach(item => item.close());
        dialog.showModal();
        document.documentElement.style.overflow = 'hidden';
    };
    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-sa-open]');
        if (trigger) open(trigger.dataset.saOpen);
        const close = event.target.closest('[data-sa-close]');
        if (close) close.closest('dialog')?.close();
        if (event.target.matches('.sa-drawer')) {
            const box = event.target.getBoundingClientRect();
            if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) event.target.close();
        }
        if (event.target.closest('[data-sa-notifications]')) {
            const notifications = document.querySelector('.manager-notifications');
            if (notifications) setTimeout(() => { notifications.open = true; notifications.querySelector('summary')?.focus(); }, 0);
        }
    });
    document.querySelectorAll('.sa-drawer').forEach(dialog => {
        dialog.addEventListener('close', () => {
            if (!document.querySelector('.sa-drawer[open]')) {
                document.documentElement.style.overflow = '';
                if (returnFocus?.isConnected) returnFocus.focus();
            }
        });
    });
    document.querySelectorAll('[data-sa-image]').forEach(input => {
        let previousUrl;
        input.addEventListener('change', () => {
            if (previousUrl) URL.revokeObjectURL(previousUrl);
            const preview = input.closest('.sa-upload').querySelector('.sa-image-preview');
            const file = input.files?.[0];
            if (!file) { input.setCustomValidity(''); preview.hidden = true; return; }
            const valid = ['image/jpeg', 'image/png', 'image/webp'].includes(file.type) && file.size <= 4 * 1024 * 1024;
            input.setCustomValidity(valid ? '' : 'Selecciona una imagen JPG, PNG o WEBP de hasta 4 MB.');
            if (!valid) { input.reportValidity(); preview.hidden = true; return; }
            preview.src = previousUrl = URL.createObjectURL(file);
            preview.hidden = false;
        });
    });
    document.querySelectorAll('.sa-form').forEach(form => form.addEventListener('submit', () => {
        const submit = form.querySelector('[type="submit"]');
        if (submit) { submit.disabled = true; submit.textContent = 'Procesando…'; }
    }));
    document.querySelectorAll('[data-sa-activation]').forEach(form => {
        const method = form.querySelector('[data-sa-activation-method]');
        const input = form.querySelector('[data-sa-temporary-password]');
        const generated = form.querySelector('[data-sa-generate-value]');
        const feedback = form.querySelector('[data-sa-generation-feedback]');
        const update = () => {
            const temporary = method.value === 'temporal';
            form.querySelector('[data-sa-link-method]').hidden = temporary;
            form.querySelector('[data-sa-temporary-method]').hidden = !temporary;
            input.disabled = !temporary || generated.value === '1';
            input.required = temporary && generated.value !== '1';
            generated.disabled = !temporary;
        };
        method.addEventListener('change', () => {
            input.value = ''; generated.value = '0'; feedback.textContent = ''; update();
        });
        form.querySelector('[data-sa-generate-password]').addEventListener('click', () => {
            if (generated.value === '1') {
                generated.value = '0'; feedback.textContent = 'Ingresa la contraseña temporal de forma privada.';
            } else {
                input.value = ''; generated.value = '1';
                feedback.textContent = 'Se generará una contraseña aleatoria de 20 caracteres al confirmar. Se mostrará una sola vez.';
            }
            update();
        });
        update();
    });
    window.addEventListener('pagehide', () => {
        document.querySelectorAll('[data-sa-once-secret],[data-sa-temporary-password]').forEach(input => { input.value = ''; input.defaultValue = ''; });
    });
    const workspace = document.querySelector('.sa-workspace');
    const recovery = workspace?.dataset.reopen;
    if (recovery) open(recovery);
    else if (new URLSearchParams(location.search).get('abrir') === 'convertir') open('contact-convert');
})();
