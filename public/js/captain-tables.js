(() => {
    const workspace=document.querySelector('.ctv-workspace');
    if(!workspace)return;
    const filter=()=>{
        const zone=workspace.querySelector('[data-ctv-zone]').value;
        const state=workspace.querySelector('[data-ctv-state]').value;
        const number=workspace.querySelector('[data-ctv-search]').value.trim().toLocaleLowerCase('es').replace(/^mesa\s*/,'');
        for(const record of workspace.querySelectorAll('[data-ctv-record]'))record.hidden=Boolean((zone && record.dataset.zoneId!==zone)||(state && record.dataset.tableState!==state)||(number && !record.dataset.tableNumber.toLocaleLowerCase('es').includes(number)));
        for(const group of workspace.querySelectorAll('[data-ctv-group]'))group.hidden=Boolean(zone && group.dataset.zoneId!==zone) || (Boolean(state || number) && !group.querySelector('[data-ctv-record]:not([hidden])'));
        const count=workspace.querySelectorAll('.ctv-table-card:not([hidden])').length;
        workspace.querySelector('[data-ctv-visible]').textContent=count;
        workspace.querySelector('[data-ctv-no-results]').hidden=count>0 || !workspace.querySelector('[data-ctv-record]');
    };
    workspace.querySelector('[data-ctv-zone]').addEventListener('change',filter);
    workspace.querySelector('[data-ctv-state]').addEventListener('change',filter);
    workspace.querySelector('[data-ctv-search]').addEventListener('input',filter);
    workspace.addEventListener('click',event=>{
        const toggle=event.target.closest('[data-cw-view]');
        if(toggle)for(const button of workspace.querySelectorAll('[data-cw-view]'))button.setAttribute('aria-pressed',String(button===toggle));
    });
    const updatePreview=form=>{
        const number=form.elements.numero.value;
        const capacity=form.elements.capacidad.value;
        const state=form.elements.estado_fisico.value;
        const preview=form.querySelector('[data-ctv-preview]');
        const tone=state==='Disponible'?'green':state==='Fuera de servicio'?'red':'orange';
        preview.classList.remove('ctv-green','ctv-red','ctv-orange');preview.classList.add(`ctv-${tone}`);
        form.querySelector('[data-ctv-preview-name]').textContent=number?`Mesa ${number}`:'Tu nueva mesa';
        form.querySelector('[data-ctv-preview-capacity]').textContent=capacity || '—';
        form.querySelector('[data-ctv-preview-state]').textContent=state;
        form.querySelector('.cw-table-symbol text').textContent=number || '＋';
        const summary=form.querySelector('[data-ctv-consecutive]');
        if(summary){
            const first=Number(number),quantity=Number(form.elements.cantidad.value),last=first+quantity-1;
            summary.textContent=first>=1 && first<=9999 && quantity>=1 && quantity<=200 && last<=9999?`${quantity===1?'Se registrará 1 mesa':'Se registrarán '+quantity+' mesas consecutivas'}: ${first===last?'Mesa '+first:'mesas '+first+' a '+last}.`:'Indica un número inicial y una cantidad válidos.';
        }
    };
    for(const panel of workspace.querySelectorAll('.ctv-drawer')){
        const form=panel.querySelector('form');updatePreview(form);
        form.addEventListener('input',()=>updatePreview(form));
        form.addEventListener('change',()=>updatePreview(form));
        if(panel.hasAttribute('data-ctv-reopen') && !panel.open)panel.showModal();
        panel.addEventListener('click',event=>{
            if(event.target!==panel)return;
            const rect=panel.getBoundingClientRect();
            if(event.clientX<rect.left || event.clientX>rect.right || event.clientY<rect.top || event.clientY>rect.bottom)panel.close();
        });
    }
})();
