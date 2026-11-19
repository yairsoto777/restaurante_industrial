document.addEventListener('DOMContentLoaded', () => {
    if (!document.documentElement.classList.contains('warehouse-workspace')) return;
    document.querySelectorAll('[data-supplier-form]').forEach(form => {
      form.querySelectorAll('[data-supplier-upload]').forEach(upload => {
        const input = upload.querySelector('[data-supplier-file]');
        const image = upload.querySelector('[data-supplier-image]');
        const initials = upload.querySelector('[data-supplier-initials]');
        const name = form.querySelector('[name="nombre"]');
        const status = upload.querySelector('[data-supplier-image-status]');
        let objectUrl;
        const release = () => { if (objectUrl) URL.revokeObjectURL(objectUrl); objectUrl = null; };
        const restore = () => {
            release();
            input.value = '';
            input.setCustomValidity('');
            status.textContent = '';
            delete status.dataset.tone;
            if (image.dataset.currentSrc) { image.src = image.dataset.currentSrc; image.hidden = false; }
            else { image.removeAttribute('src'); image.hidden = true; }
        };
        const updateName = () => {
            if (!initials) return;
            const words = name.value.trim().split(/\s+/).filter(Boolean);
            initials.textContent = words.length ? words.slice(0, 2).map(word => Array.from(word)[0]).join('').toLocaleUpperCase('es') : 'NP';
            initials.setAttribute('aria-label', name.value.trim() || 'Nuevo proveedor');
            image.alt = `Logo o imagen de ${name.value.trim() || 'nuevo proveedor'}`;
        };
        name.addEventListener('input', updateName);
        updateName();
        input.addEventListener('change', () => {
            const file = input.files[0];
            if (!file) { restore(); return; }
            const valid = ['image/jpeg', 'image/png', 'image/webp'].includes(file.type) && /\.(jpe?g|png|webp)$/i.test(file.name) && file.size <= 4 * 1024 * 1024;
            if (!valid) {
                restore();
                status.textContent = 'Selecciona una imagen JPG, JPEG, PNG o WEBP de hasta 4 MB.';
                return;
            }
            release();
            input.setCustomValidity('');
            status.textContent = input.name ? 'Imagen seleccionada. Se guardará al enviar el formulario.' : 'Vista previa local. Esta imagen no se guardará todavía.';
            status.dataset.tone = input.name ? 'success' : 'notice';
            objectUrl = URL.createObjectURL(file);
            image.src = objectUrl;
            image.hidden = false;
        });
        image.addEventListener('error', () => {
            if (!objectUrl) return;
            restore();
            status.textContent = 'No se pudo abrir la imagen. Selecciona otro archivo.';
        });
        upload.querySelector('[data-supplier-restore]').addEventListener('click', restore);
        form.addEventListener('reset', () => { restore(); setTimeout(updateName, 0); });
        window.addEventListener('pagehide', release);
      });
    });
});
