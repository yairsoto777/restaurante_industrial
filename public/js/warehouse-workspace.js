document.addEventListener('DOMContentLoaded', () => {
    if (!document.documentElement.classList.contains('warehouse-workspace')) return;
    let opener;
    const open = dialog => { if (!dialog || dialog.open) return; opener=document.activeElement; dialog.showModal(); };
    document.querySelectorAll('[data-open-dialog]').forEach(button => button.addEventListener('click', () => open(document.getElementById(button.dataset.openDialog))));
    document.querySelectorAll('.wh-dialog').forEach(dialog => {
        dialog.querySelectorAll('[data-close-dialog]').forEach(button => button.addEventListener('click', () => dialog.close()));
        dialog.addEventListener('click', event => { const rect=dialog.getBoundingClientRect(); if(event.target===dialog && (event.clientX<rect.left || event.clientX>rect.right || event.clientY<rect.top || event.clientY>rect.bottom)) dialog.close(); });
        dialog.addEventListener('close', () => opener?.focus());
    });
    const automatic=document.querySelector('[data-auto-open]'); if(automatic) open(automatic);
    document.querySelectorAll('[data-order-form]').forEach(form => {
        const container=form.querySelector('[data-order-lines]');
        const source=container.querySelector('[data-order-line]').cloneNode(true);
        const update=row => {
            const select=row.querySelector('[data-field="id_insumo"]');
            const option=select.selectedOptions[0];
            const quantity=row.querySelector('[data-field="cantidad"]');
            const fraction=option?.dataset.fraction==='1';
            quantity.step=fraction ? '0.001' : '1'; quantity.min=fraction ? '0.001' : '1';
            row.querySelector('[data-order-unit]').textContent=option?.dataset.unit || 'Selecciona un insumo';
        };
        const renumber=() => {
            const rows=[...container.querySelectorAll('[data-order-line]')];
            rows.forEach((row,index) => {
                row.querySelector('[data-line-number]').textContent=index+1;
                row.querySelectorAll('[data-field]').forEach(field => field.name=`detalles[${index}][${field.dataset.field}]`);
                row.querySelector('[data-remove-line]').disabled=rows.length===1;
                update(row);
            });
            form.querySelector('[data-add-line]').disabled=rows.length>=50;
        };
        form.querySelector('[data-add-line]').addEventListener('click', () => {
            if(container.children.length>=50) return;
            const row=source.cloneNode(true); row.querySelectorAll('input,textarea,select').forEach(input => input.value='');
            container.append(row); renumber(); row.querySelector('select').focus();
        });
        container.addEventListener('click', event => { const button=event.target.closest('[data-remove-line]'); if(button && container.children.length>1) { button.closest('[data-order-line]').remove(); renumber(); } });
        container.addEventListener('change', event => { if(event.target.matches('select')) update(event.target.closest('[data-order-line]')); });
        form.addEventListener('submit', event => {
            const selected=[...form.querySelectorAll('[data-field="id_insumo"]')].map(input=>input.value);
            form.querySelectorAll('[data-field="id_insumo"]').forEach(input=>input.setCustomValidity(selected.filter(id=>id===input.value).length>1 ? 'El insumo ya está incluido. Modifica la cantidad en su partida.' : ''));
            if(!form.checkValidity()) { event.preventDefault();form.reportValidity(); }
        });
        container.addEventListener('change', () => form.querySelectorAll('[data-field="id_insumo"]').forEach(input=>input.setCustomValidity('')));
        renumber();
    });
    document.querySelectorAll('[data-review-form]').forEach(form=>form.addEventListener('submit',event=>{
        if(!form.checkValidity()) return;
        if(!window.confirm(form.dataset.confirmMessage)) { event.preventDefault();return; }
        const button=event.submitter;if(button){button.disabled=true;button.textContent='Procesando…';}
    }));
});
