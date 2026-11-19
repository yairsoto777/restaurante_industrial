(() => {
    const form=document.querySelector('[data-cw-incident-form]');
    if(!form) return;
    const zone=form.querySelector('[data-cw-incident-zone]');
    const table=form.querySelector('[data-cw-incident-table]');
    const options=form.querySelector('[data-cw-incident-table-options]');
    const errors=form.querySelector('[data-cw-incident-errors]');
    const dialog=form.closest('dialog');
    const filterTables=(preserve=false)=>{
        if(!zone || !table || !options) return;
        const previous=preserve?table.value:'';
        const empty=document.createElement('option');empty.value='';empty.textContent='Sin mesa específica';
        table.replaceChildren(empty);
        for(const option of options.content.querySelectorAll('option')) if(option.dataset.zoneId===zone.value)table.append(option.cloneNode(true));
        table.disabled=!zone.value;
        table.value=[...table.options].some(option=>option.value===previous)?previous:'';
    };
    zone?.addEventListener('change',()=>filterTables());
    filterTables(true);
    if(dialog.hasAttribute('data-cw-reopen') && !dialog.open)dialog.showModal();
    const showErrors=messages=>{
        errors.replaceChildren();
        for(const message of messages){const p=document.createElement('p');p.textContent=message;errors.append(p);}
        errors.hidden=false;errors.focus();
    };
    let saving=false;
    form.addEventListener('submit',async event=>{
        event.preventDefault();if(saving)return;
        saving=true;
        const button=event.submitter || form.querySelector('button[type="submit"],button.cw-btn');
        const body=new FormData(form);
        if(button)button.disabled=true;
        errors.hidden=true;
        try{
            const response=await fetch(form.action,{method:'POST',credentials:'same-origin',redirect:'error',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},body});
            if(response.status===419){showErrors(['La sesión del formulario expiró. Conserva tus datos y actualiza la sesión antes de volver a intentar.']);return;}
            const data=await response.json();
            if(response.status===422){showErrors(Object.values(data.errors || {}).flat().length?Object.values(data.errors).flat():['Revisa los datos de la incidencia.']);return;}
            if(!response.ok || !data.redirect)throw Error('No se pudo guardar');
            window.location.assign(data.redirect);
        }catch{
            showErrors(['No se pudo confirmar el registro. Tus datos y la evidencia siguen en el formulario; revisa el historial antes de volver a enviarlo.']);
        }finally{saving=false;if(button)button.disabled=false;}
    });
})();
