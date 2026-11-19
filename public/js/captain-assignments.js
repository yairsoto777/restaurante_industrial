(() => {
    const root=document.querySelector('.av-workspace');
    if(!root)return;
    const assignForm=root.querySelector('[data-av-assign-form]');
    const retireForm=root.querySelector('[data-av-retire-form]');
    const zone=root.querySelector('[data-av-selected-zone]');
    const current=root.querySelector('[data-av-current]');
    const waiter=root.querySelector('[data-av-waiter]');
    const feedback=root.querySelector('[data-av-feedback]');
    const assign=root.querySelector('[data-av-assign]');
    const change=root.querySelector('[data-av-change]');
    const remove=root.querySelector('[data-av-remove]');
    const editable=root.dataset.avEditable==='1';
    let busy=false,selectedZone=null;
    const template=(attribute,id)=>root.querySelector(`template[${attribute}="${CSS.escape(String(id))}"]`);
    const message=(text,error=false)=>{feedback.textContent=text;feedback.hidden=false;feedback.classList.toggle('is-error',error);feedback.focus();};
    const blank=text=>{const p=document.createElement('p');p.textContent=text;return p;};
    const update=()=>{
        const old=current.selectedOptions[0];
        const oldProfile=template('data-av-zone-profiles',zone.value)?.content.querySelector(`[data-av-profile-id="${CSS.escape(current.value)}"]`);
        root.querySelector('[data-av-current-profile]').replaceChildren(oldProfile?oldProfile.cloneNode(true):blank('Esta zona no tiene meseros asignados.'));
        if(old?.dataset.retireUrl)retireForm.action=old.dataset.retireUrl;
        const newProfile=template('data-av-waiter-profile',waiter.value);
        root.querySelector('[data-av-new-profile]').replaceChildren(newProfile?newProfile.content.cloneNode(true):blank('Selecciona un mesero disponible para esta jornada.'));
        assign.disabled=busy || !editable || !zone.value || !waiter.value;
        change.disabled=assign.disabled || !current.value;
        remove.disabled=busy || !editable || !current.value || !old?.dataset.retireUrl;
    };
    const selectZone=(id,assignmentId=null)=>{
        if(busy)return;
        const previous=selectedZone;zone.value=id;selectedZone=id;
        for(const card of root.querySelectorAll('[data-av-zone-card]')){
            const active=card.dataset.avZoneCard===id;card.classList.toggle('is-selected',active);card.querySelector('[data-av-zone-choice]')?.setAttribute('aria-pressed',String(active));
        }
        const options=template('data-av-zone-options',id);
        current.replaceChildren();
        if(options)current.append(options.content.cloneNode(true));
        if(!current.options.length){const option=document.createElement('option');option.value='';option.textContent='Sin asignación';current.append(option);}
        if(assignmentId && [...current.options].some(option=>option.value===assignmentId))current.value=assignmentId;
        current.disabled=!editable || !current.value;
        if(previous!==id)waiter.value='';
        update();
    };
    for(const card of root.querySelectorAll('[data-av-zone-card]')){
        card.addEventListener('click',event=>{
            const profile=event.target.closest('[data-av-assignment]');
            selectZone(card.dataset.avZoneCard,profile?.dataset.avAssignment || null);
        });
        card.addEventListener('keydown',event=>{if(event.target===card && ['Enter',' '].includes(event.key)){event.preventDefault();selectZone(card.dataset.avZoneCard);}});
    }
    zone.addEventListener('change',()=>selectZone(zone.value));
    current.addEventListener('change',update);waiter.addEventListener('change',update);
    const filter=()=>{
        const selected=root.querySelector('[data-av-zone-filter]').value;
        const query=root.querySelector('[data-av-search]').value.trim().toLocaleLowerCase('es');
        let visible=0;
        for(const card of root.querySelectorAll('[data-av-zone-card]')){card.hidden=Boolean((selected && selected!==card.dataset.avZoneCard)||(query && !card.textContent.toLocaleLowerCase('es').includes(query)));if(!card.hidden)visible++;}
        root.querySelector('[data-av-empty-filter]').hidden=visible>0 || !root.querySelector('[data-av-zone-card]');
    };
    root.querySelector('[data-av-zone-filter]').addEventListener('change',filter);
    root.querySelector('[data-av-search]').addEventListener('input',filter);
    const send=async(form,body)=>{
        const response=await fetch(form.action,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},body});
        if(!response.ok){
            const data=await response.json().catch(()=>({}));
            const errors=Object.values(data.errors || {}).flat();
            throw Error(errors.join(' ') || data.message || 'No se pudo completar la operación. Comprueba la sesión y la asignación.');
        }
        const destination=new URL(response.url);
        const expected=new URL(assignForm.action);
        if(destination.origin!==expected.origin || destination.searchParams.get('modulo')!=='asignaciones')throw Error('No se pudo confirmar la operación. Actualiza la vista y comprueba tu sesión.');
        return destination;
    };
    const finish=(url,text)=>{
        message(text);
        try{sessionStorage.setItem(root.dataset.avFeedbackKey,text);}catch{}
        url.hash=`av-zone-${zone.value}`;window.location.assign(url.href);
    };
    const operate=async(mode)=>{
        if(busy || !editable || !zone.value)return;
        const old=current.selectedOptions[0];
        if(mode!=='assign' && (!current.value || !old?.dataset.retireUrl))return;
        if(mode!=='retire' && (!waiter.value || !assignForm.reportValidity()))return;
        const oldName=old?.dataset.personName || 'el mesero seleccionado';
        const newName=waiter.selectedOptions[0]?.textContent || '';
        const zoneName=zone.selectedOptions[0]?.textContent || '';
        if(mode==='retire' && !window.confirm(`¿Retirar a ${oldName} de ${zoneName} en esta jornada?`))return;
        if(mode==='change' && !window.confirm(`¿Cambiar la asignación de ${oldName} por ${newName} en ${zoneName}, solo para esta jornada?`))return;
        const assignmentBody=new FormData(assignForm),retirementBody=new FormData(retireForm);
        busy=true;zone.disabled=true;current.disabled=true;waiter.disabled=true;update();
        let added=false,completed=false;
        try{
            if(mode==='retire'){finish(await send(retireForm,retirementBody),'Mesero retirado correctamente de la jornada.');completed=true;}
            else{
                const assignedURL=await send(assignForm,assignmentBody);added=true;
                if(mode==='change'){
                    // Existing independent operations: validate/add the replacement first.
                    // A rejected or incompatible replacement never removes the original waiter.
                    finish(await send(retireForm,retirementBody),'Asignación cambiada correctamente para esta jornada.');
                }else finish(assignedURL,'Mesero asignado correctamente a la jornada.');
                completed=true;
            }
        }catch(error){
            if(added){
                message('El nuevo mesero quedó asignado, pero no se confirmó el retiro anterior. Actualiza la vista para revisar ambas asignaciones antes de continuar.',true);
                root.querySelector('[data-av-refresh]').hidden=false;
                // Block retries against stale assignment data after a partial operation.
                return;
            }
            message(error.message,true);
        }finally{
            if(!added && !completed){busy=false;zone.disabled=!zone.options.length;current.disabled=!editable || !current.value;waiter.disabled=!editable || waiter.options.length<2;update();}
        }
    };
    assignForm.addEventListener('submit',event=>{event.preventDefault();operate('assign');});
    change.addEventListener('click',()=>operate('change'));remove.addEventListener('click',()=>operate('retire'));
    root.querySelector('[data-av-refresh]').addEventListener('click',()=>window.location.reload());
    const requested=window.location.hash.match(/^#av-zone-(\d+)$/)?.[1];
    selectZone(requested && [...zone.options].some(option=>option.value===requested)?requested:zone.value);
    try{const prior=sessionStorage.getItem(root.dataset.avFeedbackKey);if(prior){sessionStorage.removeItem(root.dataset.avFeedbackKey);message(prior);}}catch{}
})();
