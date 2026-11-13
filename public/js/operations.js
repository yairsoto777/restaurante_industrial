/* Presentation helpers: no requests, financial calculations or permission changes. */
document.addEventListener('DOMContentLoaded', () => {
    if (!document.body.classList.contains('ops-ui')) return;
    const paymentDialog = document.querySelector('.payment-dialog');
    if (paymentDialog) {
        paymentDialog.setAttribute('role', 'dialog');
        paymentDialog.setAttribute('aria-modal', 'true');
        paymentDialog.querySelector('h2').id = 'ops-payment-title';
        paymentDialog.setAttribute('aria-labelledby', 'ops-payment-title');
        paymentDialog.querySelector('[data-close-payment]').setAttribute('aria-label', 'Cerrar formulario de cobro');
    }
    const fieldLabels = { id_zona:'Zona asignada', numero:'Número de mesa', capacidad:'Capacidad', estado_fisico:'Estado físico', observaciones:'Observaciones (opcional)', descripcion:'Descripción del servicio', comensales:'Comensales', precio_unitario:'Precio por persona', id_servicio:'Servicio programado' };
    document.querySelectorAll('.chef-filters input[name]:not([type=hidden]),.chef-filters select[name]').forEach((field,index) => {
        if (field.labels?.length || !fieldLabels[field.name]) return;
        field.id ||= `ops-labeled-field-${index}`;
        const label = document.createElement('label');
        label.htmlFor = field.id;
        label.textContent = fieldLabels[field.name];
        field.before(label);
        label.append(field);
    });
    document.querySelectorAll('[data-ops-select-table]').forEach(button => button.addEventListener('click', event => {
        event.preventDefault();
        const field = [...document.querySelectorAll('#directorio-atencion input[name=id_mesa]')].find(input => input.value === button.dataset.opsSelectTable);
        const row = field?.closest('tr');
        document.querySelectorAll('.ops-selected-row').forEach(item => item.classList.remove('ops-selected-row'));
        if (row) {
            row.classList.add('ops-selected-row');
            row.scrollIntoView({ block:'center', behavior:'auto' });
            field.closest('form').querySelector('select')?.focus({ preventScroll:true });
        }
    }));
    const floorSearch = document.getElementById('cz-search');
    const floorZone = document.getElementById('cz-filter');
    const filterFloor = () => {
        document.querySelectorAll('[data-ops-floor-card]').forEach(card => {
            card.hidden = !card.dataset.search.includes(floorSearch.value.trim().toLocaleLowerCase()) || (!!floorZone.value && card.dataset.zone !== floorZone.value);
        });
        document.querySelectorAll('[data-ops-zone-group]').forEach(group => group.hidden = ![...group.querySelectorAll('[data-ops-floor-card]')].some(card => !card.hidden));
    };
    if (floorSearch && floorZone) { floorSearch.addEventListener('input',filterFloor); floorZone.addEventListener('change',filterFloor); }
    document.querySelectorAll('[data-ops-filter]').forEach(filter => {
        const target = document.getElementById(filter.dataset.opsFilter);
        if (!target) return;
        const input = filter.querySelector('input');
        const rows = [...target.querySelectorAll('tbody tr')].filter(row => row.querySelectorAll('td').length > 1);
        const apply = () => {
            const query = input.value.trim().toLocaleLowerCase('es');
            let visible = 0;
            rows.forEach(row => {
                row.hidden = !row.textContent.toLocaleLowerCase('es').includes(query);
                if (!row.hidden) visible++;
            });
            filter.querySelector('[data-ops-filter-count]').textContent = `${visible} de ${rows.length} registros cargados`;
        };
        input.addEventListener('input', apply);
        filter.querySelector('[data-ops-clear]').addEventListener('click', () => { input.value = ''; apply(); input.focus(); });
        apply();
    });
    document.querySelectorAll('.table-wrap,.chef-table-wrap,.cp-table-container,.cz-tablewrap').forEach(wrap => {
        wrap.tabIndex = 0;
        wrap.setAttribute('role', 'region');
        wrap.setAttribute('aria-label', 'Listado con desplazamiento horizontal');
    });
    document.querySelectorAll('input:not([type=hidden]),select,textarea').forEach(field => {
        if (!field.labels?.length && !field.hasAttribute('aria-label')) field.setAttribute('aria-label', field.placeholder || field.name.replaceAll('_', ' ') || 'Filtro');
    });
    const errors = document.getElementById('ops-validation-errors');
    if (errors) {
        const messages = JSON.parse(errors.textContent);
        document.querySelectorAll('input[name],select[name],textarea[name]').forEach((field, index) => {
            const message = messages[field.name]?.[0];
            if (!message || field.type === 'hidden') return;
            const note = document.createElement('small');
            note.className = 'ops-field-error'; note.id = `ops-field-error-${index}`; note.textContent = message;
            field.classList.add('ops-field-invalid'); field.setAttribute('aria-invalid', 'true');
            field.setAttribute('aria-describedby', `${field.getAttribute('aria-describedby') || ''} ${note.id}`.trim());
            field.insertAdjacentElement('afterend', note);
            const disclosure = field.closest('details'); if (disclosure) disclosure.open = true;
        });
    }
    let returnFocus = null;
    document.addEventListener('click', event => {
        const opener = event.target.closest('[data-open-payment],[data-open],[data-edit-table],[data-edit-zone]');
        if (opener) returnFocus = opener;
    }, true);
    document.querySelectorAll('.cz-modal,.payment-modal').forEach(modal => {
        const dialog = modal.querySelector('[role=dialog],.payment-dialog'); if (!dialog) return;
        new MutationObserver(() => {
            if (modal.hidden) returnFocus?.focus(); else document.body.style.overflow = 'hidden';
            if (![...document.querySelectorAll('.cz-modal,.payment-modal')].some(item => !item.hidden)) document.body.style.overflow = '';
        }).observe(modal, { attributes:true, attributeFilter:['hidden'] });
        modal.addEventListener('keydown', event => {
            if (event.key === 'Escape') { event.preventDefault(); modal.querySelector('[data-close-payment],[data-close]')?.click(); }
            if (event.key !== 'Tab') return;
            const focusable = [...dialog.querySelectorAll('button,input:not([type=hidden]),select,textarea,a[href]')].filter(el => !el.disabled && el.getClientRects().length);
            const first = focusable[0], last = focusable.at(-1);
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
            if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
        });
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') { document.querySelector('[data-app-shell]')?.classList.remove('sidebar-open'); document.querySelector('[data-sidebar-toggle]')?.setAttribute('aria-expanded', 'false'); }
    });
    document.querySelector('[data-sidebar-toggle]')?.addEventListener('click', event => {
        const button=event.currentTarget;
        queueMicrotask(() => button.setAttribute('aria-expanded', String(document.querySelector('[data-app-shell]').classList.contains('sidebar-open'))));
    });
});
