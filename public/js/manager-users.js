document.querySelectorAll('form:has([data-primary-role])').forEach((form) => {
    const primary = form.querySelector('[data-primary-role]');
    const checkboxes = [...form.querySelectorAll('input[name="role_ids[]"]')];
    const summary = form.querySelector('[data-role-summary]');
    let previousPrimary = primary.value;
    const refreshRoles = () => {
        checkboxes.forEach((checkbox) => {
            const main = checkbox.value === primary.value;
            checkbox.disabled = main;
            if (main) checkbox.checked = false;
            const row = checkbox.closest('[data-role-option]');
            row.classList.toggle('is-main', main);
            row.querySelector('[data-role-kind]').textContent = main ? 'Principal' : 'Adicional';
        });
        const count = checkboxes.filter((checkbox) => checkbox.checked && !checkbox.disabled).length;
        summary.textContent = `${primary.value ? '1 rol principal' : 'Selecciona un rol principal'} · ${count} ${count === 1 ? 'rol adicional' : 'roles adicionales'}`;
    };
    primary.addEventListener('change', () => {
        const previous = checkboxes.find((checkbox) => checkbox.value === previousPrimary);
        if (previous && primary.value !== previousPrimary) previous.checked = true;
        previousPrimary = primary.value;
        refreshRoles();
    });
    checkboxes.forEach((checkbox) => checkbox.addEventListener('change', refreshRoles));
    refreshRoles();

    const fileInput = form.querySelector('[data-employee-photo]');
    const feedback = form.querySelector('[data-photo-feedback]');
    const preview = form.querySelector('[data-avatar-photo]');
    const originalSource = preview.getAttribute('src');
    let previewUrl;
    const resetPreview = () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = undefined;
        if (originalSource) { preview.src = originalSource; preview.hidden = false; }
        else { preview.removeAttribute('src'); preview.hidden = true; }
    };
    fileInput.addEventListener('change', () => {
        resetPreview();
        const file = fileInput.files[0];
        feedback.hidden = !file;
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
            feedback.className = 'mgr-photo-feedback alert';
            feedback.textContent = 'Selecciona una imagen JPG, PNG o WebP de hasta 2 MB.';
            fileInput.value = '';
            return;
        }
        previewUrl = URL.createObjectURL(file);
        preview.src = previewUrl;
        preview.hidden = false;
        feedback.className = 'mgr-photo-feedback approved';
        feedback.textContent = 'Foto lista. Se guardará junto con los cambios del empleado.';
    });
    form.querySelectorAll('input[name="nombre"], input[name="apellido_p"]').forEach((input) => input.addEventListener('input', () => {
        const first = form.elements.nombre.value.trim();
        const last = form.elements.apellido_p.value.trim();
        form.querySelector('[data-avatar-initials]').textContent = ((first[0] || 'N') + (last[0] || '')).toLocaleUpperCase('es');
    }));
    window.addEventListener('pagehide', () => { if (previewUrl) URL.revokeObjectURL(previewUrl); });
});
