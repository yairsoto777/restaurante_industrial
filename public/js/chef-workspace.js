document.addEventListener('DOMContentLoaded', () => {
    if (!document.documentElement.classList.contains('chef-workspace')) return;
    initializeTimePicker();
    document.querySelectorAll('[data-cw-upload]').forEach(block => {
        const input = block.querySelector('[data-cw-file]');
        const image = block.querySelector('[data-cw-preview]');
        const status = block.querySelector('.cw-media-status');
        let objectUrl;
        const revoke = () => { if (objectUrl) URL.revokeObjectURL(objectUrl); objectUrl = null; };
        const restore = () => { revoke(); input.value=''; image.src=image.dataset.original; status.textContent=''; status.dataset.tone=''; updateService(); };
        input.addEventListener('change', () => {
            const file=input.files[0];
            if (!file) { restore(); return; }
            if (!['image/jpeg','image/png','image/webp'].includes(file.type) || !/\.(jpe?g|png|webp)$/i.test(file.name) || file.size>4*1024*1024) {
                restore(); status.textContent='Selecciona JPG, JPEG, PNG o WEBP de hasta 4 MB.'; status.dataset.tone='error'; return;
            }
            revoke(); objectUrl=URL.createObjectURL(file); image.src=objectUrl;
            status.textContent=input.name ? 'Imagen seleccionada correctamente. Se guardará con el formulario.' : 'Imagen seleccionada. Vista previa local; el guardado requiere habilitación.';
            status.dataset.tone=input.name ? 'success' : 'waiting'; updateService();
            if (input.name==='design_background') { const target=document.querySelector('.cw-generated-background'); if(target)target.src=objectUrl; }
            if (input.name==='design_logo') { const target=document.querySelector('.cw-generated-logo'); if(target)target.src=objectUrl; }
        });
        image.addEventListener('error',()=>{if(objectUrl){restore();status.dataset.tone='error';status.textContent='No se pudo abrir la imagen. Selecciona otro archivo.';}});
        block.querySelector('[data-cw-restore]').addEventListener('click',restore);
        window.addEventListener('pagehide',revoke);
    });
    function updateService() {
        const form=document.querySelector('[data-cw-service-form]'); if(!form)return;
        const title=document.querySelector('[data-cw-service-title]'); const photo=document.querySelector('[data-cw-service-photo]');
        title.textContent=form.elements.nombre.value || 'Tu próximo servicio';
        photo.src=form.querySelector('[data-cw-preview]').src;
        document.querySelector('[data-cw-service-meta]').textContent=[form.elements.fecha.value,[form.elements.inicio_hora.value,form.elements.fin_hora.value].filter(Boolean).map(friendlyTime).join('–'),form.elements.capacidad.value ? form.elements.capacidad.value+' comensales' : ''].filter(Boolean).join(' · ');
    }
    document.querySelector('[data-cw-service-form]')?.addEventListener('input',updateService);
    const builder=document.querySelector('[data-cw-menu-form]');
    if(builder) {
        const update = () => {
            const selected=new Set([...builder.querySelectorAll('[data-cw-menu-select]:checked')].map(input=>input.value));
            builder.querySelectorAll('[data-cw-selected-dish]').forEach(row=>{row.hidden=!selected.has(row.dataset.cwSelectedDish);row.querySelectorAll('input,select').forEach(input=>{input.disabled=row.hidden || builder.querySelector('[data-cw-menu-select]')?.disabled || (input.name.startsWith('publicado') && input.dataset.approvalLocked==='1');});});
            builder.querySelectorAll('[data-cw-selected-category]').forEach(group=>group.hidden=![...group.querySelectorAll('[data-cw-selected-dish]')].some(row=>!row.hidden));
            builder.querySelector('[data-cw-selected-count]').textContent=selected.size+' platillos'; builder.querySelector('[data-cw-menu-empty]').hidden=selected.size>0;
        };
        builder.querySelectorAll('select[name^="publicado"]:disabled').forEach(input=>input.dataset.approvalLocked='1');
        builder.addEventListener('change',update);update();
        const search=builder.querySelector('[data-cw-dish-search]'); const category=builder.querySelector('[data-cw-category-filter]');
        const filter=()=>builder.querySelectorAll('[data-cw-dish]').forEach(row=>row.hidden=!row.dataset.name.toLocaleLowerCase('es').includes(search.value.toLocaleLowerCase('es')) || (!!category.value && row.dataset.category!==category.value));
        search.addEventListener('input',filter);category.addEventListener('change',filter);
    }
    document.querySelectorAll('[data-cw-print]').forEach(button=>button.addEventListener('click',()=>window.print()));
    const generator=document.querySelector('[data-cw-generator]');
    if(generator) {
        const period=generator.querySelector('[data-cw-period]'),start=generator.querySelector('[data-cw-start]'),end=generator.querySelector('[data-cw-end]');
        const range=()=>{if(!start.value||period.value==='personalizado')return;const date=new Date(start.value+'T12:00:00');if(period.value==='mensual'){const day=date.getDate();date.setDate(1);date.setMonth(date.getMonth()+1);const last=new Date(date.getFullYear(),date.getMonth()+1,0).getDate();date.setDate(Math.min(day,last));date.setDate(date.getDate()-1);}else date.setDate(date.getDate()+({diario:0,semanal:6,quincenal:13}[period.value]??6));end.value=[date.getFullYear(),String(date.getMonth()+1).padStart(2,'0'),String(date.getDate()).padStart(2,'0')].join('-');};
        period.addEventListener('change',range);start.addEventListener('change',range);
        generator.querySelector('[data-cw-template]').addEventListener('change',()=>generator.requestSubmit());
    }

    function friendlyTime(value) {
        if (!/^([01]\d|2[0-3]):[0-5]\d$/.test(value)) return '';
        const [hour,minute]=value.split(':').map(Number);
        return String(hour%12 || 12).padStart(2,'0')+':'+String(minute).padStart(2,'0')+(hour<12 ? ' a. m.' : ' p. m.');
    }

    function initializeTimePicker() {
        const dialog=document.getElementById('cw-time-dialog');
        if (!dialog || typeof dialog.showModal!=='function') return;
        const fields=[...document.querySelectorAll('[data-cw-time-field]')].map(block=>({
            block,input:block.querySelector('[data-cw-time-value]'),trigger:block.querySelector('[data-cw-time-open]'),
            text:block.querySelector('[data-cw-time-text]'),error:block.querySelector('[data-cw-time-error]'),label:block.querySelector('label').textContent.trim(),
        }));
        const dial=dialog.querySelector('[data-cw-time-dial]');
        const numbers=dialog.querySelector('[data-cw-time-numbers]');
        const periodChoice=dialog.querySelector('[data-cw-time-period-choice]');
        let activeField=null, hour=8, minute=0, period='am', step='hour', dragging=false;
        const syncField=field=>field.text.textContent=friendlyTime(field.input.value) || 'Seleccionar hora';
        const error=(field,message)=>{
            field.error.textContent=message;field.error.hidden=!message;
            field.trigger.setAttribute('aria-invalid',message ? 'true' : 'false');
        };
        const validate=(form,reportMissing=false)=>{
            const ownFields=fields.filter(field=>field.input.form===form && !field.input.disabled);
            ownFields.forEach(field=>error(field,reportMissing && !field.input.value ? 'Selecciona '+field.label.toLocaleLowerCase('es')+'.' : ''));
            const start=ownFields.find(field=>field.input.name==='inicio_hora');
            const end=ownFields.find(field=>field.input.name==='fin_hora');
            // Preserve the server's existing strict "after:inicio_hora" rule.
            if (start?.input.value && end?.input.value && end.input.value<=start.input.value) {
                error(end,'La hora de finalización debe ser posterior a la hora de inicio ('+friendlyTime(start.input.value)+').');
            }
            ownFields.filter(field=>field.input.name==='start'||field.input.name.endsWith('[start]')).forEach(start=>{
                const end=ownFields.find(field=>field.input.name===(start.input.name==='start'?'end':start.input.name.replace(/\[start\]$/,'[end]')));
                if(start.input.value && end?.input.value && end.input.value<=start.input.value)error(end,'La hora de finalización debe ser posterior a la hora de inicio.');
            });
            return ownFields.find(field=>!field.error.hidden);
        };
        const render=(moveFocus=false)=>{
            dialog.querySelector('[data-cw-time-hour]').textContent=String(hour).padStart(2,'0');
            dialog.querySelector('[data-cw-time-minute]').textContent=String(minute).padStart(2,'0');
            dialog.querySelector('[data-cw-time-period]').textContent=period==='am' ? 'a. m.' : 'p. m.';
            dialog.querySelectorAll('[data-cw-time-step]').forEach(button=>button.setAttribute('aria-pressed',String(button.dataset.cwTimeStep===step)));
            dialog.querySelectorAll('[data-cw-time-meridiem]').forEach(button=>button.setAttribute('aria-pressed',String(button.dataset.cwTimeMeridiem===period)));
            dial.hidden=step==='period';periodChoice.hidden=step!=='period';
            dialog.querySelector('#cw-time-instruction').textContent=step==='hour' ? 'Selecciona la hora. Después elegirás los minutos.' : step==='minute' ? 'Selecciona los minutos. Arrastra la aguja para elegir cualquier minuto; también puedes usar las flechas del teclado.' : 'Elige a. m. o p. m. y confirma con Aceptar.';
            if (step!=='period') {
                const isHour=step==='hour', selected=isHour ? hour : minute;
                dial.setAttribute('aria-label',isHour ? 'Hora. Usa las flechas y pulsa Enter para elegir minutos.' : 'Minutos. Usa las flechas y pulsa Enter para elegir a. m. o p. m.');
                dial.setAttribute('aria-valuemin',isHour ? '1' : '0');dial.setAttribute('aria-valuemax',isHour ? '12' : '59');
                dial.setAttribute('aria-valuenow',String(selected));dial.setAttribute('aria-valuetext',selected+(isHour ? ' horas' : ' minutos'));
                const angle=isHour ? hour%12*30 : minute*6;
                dialog.querySelector('[data-cw-time-hand]').style.setProperty('--clock-angle',angle+'deg');
                dialog.querySelector('[data-cw-time-tip]').textContent=!isHour && minute%5!==0 ? String(minute).padStart(2,'0') : '';
                numbers.replaceChildren();
                for(let i=0;i<12;i++) {
                    const value=isHour ? (i || 12) : i*5;
                    const button=document.createElement('button');button.type='button';button.tabIndex=-1;
                    button.className='cw-time-number';button.dataset.value=String(value);
                    button.textContent=isHour ? String(value) : String(value).padStart(2,'0');
                    button.setAttribute('aria-label',value+(isHour ? ' horas' : ' minutos'));
                    button.setAttribute('aria-pressed',String(value===selected));
                    button.style.left=(50+Math.sin(i*Math.PI/6)*42)+'%';button.style.top=(50-Math.cos(i*Math.PI/6)*42)+'%';
                    numbers.append(button);
                }
            }
            if(moveFocus) (step==='period' ? periodChoice.querySelector('[data-cw-time-meridiem="'+period+'"]') : dial).focus();
        };
        const advance=()=>{step=step==='hour' ? 'minute' : 'period';render(true);};
        fields.forEach(field=>{
            field.original=field.input.value;
            field.input.type='hidden';field.input.required=false;field.trigger.hidden=false;
            syncField(field);
            field.trigger.addEventListener('click',()=>{
                activeField=field;
                const [storedHour,storedMinute]=(field.input.value || '08:00').split(':').map(Number);
                hour=storedHour%12 || 12;minute=storedMinute;period=storedHour<12 ? 'am' : 'pm';step='hour';
                dialog.querySelector('#cw-time-title').textContent=field.label;
                render();dialog.showModal();dial.focus();
            });
            field.input.addEventListener('change',()=>{syncField(field);validate(field.input.form);});
        });
        new Set(fields.map(field=>field.input.form)).forEach(form=>{
            form.addEventListener('submit',event=>{const invalid=validate(form,true);if(invalid){event.preventDefault();invalid.trigger.focus();}});
            form.addEventListener('reset',()=>setTimeout(()=>{fields.filter(field=>field.input.form===form).forEach(field=>{field.input.value=field.original;syncField(field);error(field,'');});updateService();},0));
            validate(form);
        });
        numbers.addEventListener('click',event=>{
            const button=event.target.closest('[data-value]');if(!button)return;
            if(step==='hour')hour=Number(button.dataset.value);else minute=Number(button.dataset.value);
            advance();
        });
        const pointToValue=event=>{
            const rect=dial.getBoundingClientRect();
            const x=event.clientX-(rect.left+rect.width/2), y=event.clientY-(rect.top+rect.height/2);
            if(Math.hypot(x,y)<rect.width*.13)return;
            const angle=(Math.atan2(x,-y)*180/Math.PI+360)%360;
            if(step==='hour')hour=Math.round(angle/30)%12 || 12;else minute=Math.round(angle/6)%60;
            render();
        };
        dial.addEventListener('pointerdown',event=>{
            if(event.target.closest('[data-value]') || event.button>0)return;
            dragging=true;dial.setPointerCapture(event.pointerId);pointToValue(event);event.preventDefault();
        });
        dial.addEventListener('pointermove',event=>{if(dragging)pointToValue(event);});
        dial.addEventListener('pointerup',event=>{if(!dragging)return;pointToValue(event);dragging=false;dial.releasePointerCapture(event.pointerId);advance();});
        dial.addEventListener('pointercancel',()=>{dragging=false;});
        dial.addEventListener('keydown',event=>{
            if(event.key==='Enter' || event.key===' '){event.preventDefault();advance();return;}
            if(!['ArrowUp','ArrowRight','ArrowDown','ArrowLeft','Home','End'].includes(event.key))return;
            event.preventDefault();
            const increment=['ArrowUp','ArrowRight'].includes(event.key) ? 1 : -1;
            if(step==='hour')hour=event.key==='Home' ? 1 : event.key==='End' ? 12 : (hour-1+increment+12)%12+1;
            else minute=event.key==='Home' ? 0 : event.key==='End' ? 59 : (minute+increment+60)%60;
            render();
        });
        dialog.querySelectorAll('[data-cw-time-step]').forEach(button=>button.addEventListener('click',()=>{step=button.dataset.cwTimeStep;render(true);}));
        dialog.querySelectorAll('[data-cw-time-meridiem]').forEach(button=>button.addEventListener('click',()=>{period=button.dataset.cwTimeMeridiem;render();dialog.querySelector('[data-cw-time-accept]').focus();}));
        dialog.querySelector('[data-cw-time-cancel]').addEventListener('click',()=>dialog.close());
        dialog.querySelector('[data-cw-time-accept]').addEventListener('click',()=>{
            if(!activeField)return;
            activeField.input.value=String(hour%12+(period==='pm' ? 12 : 0)).padStart(2,'0')+':'+String(minute).padStart(2,'0');
            syncField(activeField);validate(activeField.input.form);
            activeField.input.dispatchEvent(new Event('input',{bubbles:true}));activeField.input.dispatchEvent(new Event('change',{bubbles:true}));
            dialog.close();
        });
        dialog.addEventListener('click',event=>{if(event.target!==dialog)return;const rect=dialog.getBoundingClientRect();if(event.clientX<rect.left||event.clientX>rect.right||event.clientY<rect.top||event.clientY>rect.bottom)dialog.close();});
        dialog.addEventListener('close',()=>{dragging=false;activeField?.trigger.focus();});
    }
});
