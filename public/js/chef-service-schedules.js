(()=>{'use strict';const form=document.querySelector('[data-cw-schedule]');if(!form)return;
const names=['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
const display=value=>{if(!value)return 'Sin horario';const[h,m]=value.split(':').map(Number);return `${String(h%12||12).padStart(2,'0')}:${String(m).padStart(2,'0')} ${h<12?'a. m.':'p. m.'}`;};
function update(){const mode=form.elements.mode.value,days=[...form.querySelectorAll('[name="days[]"]:checked')].map(i=>Number(i.value));
form.querySelector('[data-schedule-days]').hidden=['diario','evento'].includes(mode);
const general=form.querySelector('[data-schedule-general]');general.hidden=mode==='personalizado';general.querySelectorAll('input').forEach(i=>{i.disabled=mode==='personalizado';});
form.querySelectorAll('[data-schedule-hours]').forEach(row=>{const enabled=mode==='personalizado'&&days.includes(Number(row.dataset.scheduleHours));row.hidden=!enabled;row.querySelectorAll('input').forEach(i=>{i.disabled=!enabled;});});
const box=document.querySelector('[data-schedule-summary]');box.replaceChildren();const line=(text,heading=false)=>{const el=document.createElement(heading?'h3':'p');el.textContent=text;box.append(el);};
line(form.elements.nombre.value||'Tu próximo servicio',true);line(form.elements.mode.selectedOptions?.[0]?.textContent||form.querySelector('[name=mode]:checked').closest('label').textContent.trim());line(`${form.elements.capacidad.value||'—'} comensales`);
const chosen=mode==='diario'?[1,2,3,4,5,6,7]:mode==='evento'?[]:days;
if(mode==='personalizado')chosen.forEach(d=>line(`${names[d-1]}: ${display(form.elements[`hours[${d}][start]`].value)} – ${display(form.elements[`hours[${d}][end]`].value)}`));
else{line(mode==='evento'?'Fecha única':chosen.map(d=>names[d-1]).join(', '));line(`${display(form.elements.start.value)} – ${display(form.elements.end.value)}`);}
line(`${form.elements.desde.value||'Inicio pendiente'} · ${mode==='evento'?'Evento especial':form.elements.hasta.value||'Vigencia indefinida'}`);
const image=form.querySelector('[data-cw-preview]')||form.querySelector('img');if(image)document.querySelector('[data-schedule-photo]').src=image.src;
}
form.addEventListener('input',update);form.addEventListener('change',update);form.addEventListener('submit',event=>{const custom=form.elements.mode.value==='personalizado';const pairs=custom?[...form.querySelectorAll('[data-schedule-hours]:not([hidden])')].map(row=>[...row.querySelectorAll('input')]):[[form.elements.start,form.elements.end]];for(const[start,end]of pairs){if(!start.value||!end.value||end.value<=start.value){event.preventDefault();alert('La hora de finalización debe ser posterior al inicio en cada día de operación.');break;}}});update();})();
