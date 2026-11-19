(() => {
 'use strict'; const root=document.querySelector('[data-ww-workspace]');if(!root)return;
 let opener;
 root.addEventListener('click',event=>{
  const trigger=event.target.closest('[data-ww-open]');if(trigger){const dialog=document.getElementById(trigger.dataset.wwOpen);if(!dialog)return;root.querySelectorAll('dialog[open]').forEach(d=>d.close());opener=trigger;dialog.showModal();return;}
  if(event.target.closest('[data-ww-close]')){event.target.closest('dialog')?.close();opener?.focus();}
  const step=event.target.closest('[data-ww-step]');if(step){const input=step.parentElement.querySelector('input');input.value=Math.min(Number(input.max),Math.max(Number(input.min),Number(input.value)+Number(step.dataset.wwStep)));input.dispatchEvent(new Event('input',{bubbles:true}));}
 });
 root.querySelectorAll('dialog').forEach(d=>d.addEventListener('click',e=>{if(e.target===d && e.offsetX<0)d.close();}));
 const service=root.querySelector('[data-ww-service-name]'),jornada=root.querySelector('[data-ww-jornada]');
 function filterJornadas(){if(!service||!jornada)return;[...jornada.options].forEach(o=>{o.hidden=o.dataset.service!==service.value;});}
 filterJornadas();service?.addEventListener('change',()=>{filterJornadas();const option=[...jornada.options].find(o=>!o.hidden);if(option){jornada.value=option.value;jornada.form.requestSubmit();}});
 const filters=[...root.querySelectorAll('[data-ww-filter]')];
 function filterTables(){const values=Object.fromEntries(filters.map(f=>[f.dataset.wwFilter,f.value.trim().toLowerCase()]));root.querySelectorAll('[data-ww-table]').forEach(t=>{t.hidden=Boolean((values.zone&&values.zone!==t.dataset.zone)||(values.state&&values.state!==t.dataset.state.toLowerCase())||(values.number&&!t.dataset.number.includes(values.number)));});root.querySelectorAll('[data-ww-zone]').forEach(z=>z.hidden=Boolean(z.querySelector('[data-ww-table]')&&![...z.querySelectorAll('[data-ww-table]')].some(t=>!t.hidden)));}
 filters.forEach(f=>f.addEventListener('input',filterTables));
 let category='';const menuSearch=root.querySelector('[data-ww-menu-search]');function filterDishes(){const q=(menuSearch?.value||'').toLocaleLowerCase('es');root.querySelectorAll('[data-ww-dish]').forEach(d=>d.hidden=Boolean((category&&d.dataset.category!==category)||!d.dataset.search.includes(q)));}
 menuSearch?.addEventListener('input',filterDishes);root.querySelectorAll('[data-ww-category]').forEach(b=>b.addEventListener('click',()=>{category=b.dataset.wwCategory;root.querySelectorAll('[data-ww-category]').forEach(c=>c.classList.toggle('is-active',c===b));filterDishes();}));
 root.querySelectorAll('[data-ww-command]').forEach(dialog => {
  const form = dialog.querySelector('form');
  const search = form.querySelector('[data-command-search]');
  const category = form.querySelector('[data-command-category]');
  const dishes = [...form.querySelectorAll('[data-command-dish]')];
  const catalog = new Map(dishes.map(dish => [Number(dish.dataset.commandDish), dish]));
  const lines = form.querySelector('[data-command-lines]');
  const error = form.querySelector('[data-command-error]');
  const status = form.querySelector('[data-command-status]');
  const send = form.querySelector('[value="send"]');
  const save = form.querySelector('[value="draft"]');
  const draft = JSON.parse(form.querySelector('[data-command-draft]').textContent);
  const quantities = new Map(draft.filter(item => catalog.has(Number(item.id)))
   .map(item => [Number(item.id), Number(item.quantity)]));
  const currency = new Intl.NumberFormat('es-MX', {style: 'currency', currency: 'MXN'});
  const money = cents => currency.format(cents / 100);
  const normalize = value => value.normalize('NFD').replace(/\p{M}/gu, '').trim().toLowerCase();
  let busy = false;

  function filter() {
   const query = normalize(search.value);
   dishes.forEach(dish => {
    const {name, description, category: dishCategory} = dish.dataset;
    dish.hidden = (category.value !== '' && category.value !== dishCategory) ||
     !normalize(name + ' ' + description).includes(query);
   });
   form.querySelector('[data-command-empty]').hidden = dishes.some(dish => !dish.hidden);
  }

  function quantityButton(label, text, id, action) {
   const button = document.createElement('button');
   button.type = 'button';
   button.textContent = text;
   button.setAttribute('aria-label', label);
   button.dataset.commandItem = id;
   button.dataset.commandChange = action;
   return button;
  }

  function render() {
   let total = 0;
   lines.replaceChildren();
   for (const [id, quantity] of quantities) {
    const dish = catalog.get(id);
    const {name, cents} = dish.dataset;
    const subtotal = Number(cents) * quantity;
    total += subtotal;
    const row = document.createElement('article');
    row.className = 'ww-command-line';
    const photo = dish.querySelector('.ww-command-photo').cloneNode(true);
    const details = document.createElement('div');
    const title = document.createElement('strong');
    title.textContent = name;
    const unit = document.createElement('small');
    unit.textContent = money(Number(cents)) + ' por unidad';
    const amount = document.createElement('strong');
    amount.textContent = money(subtotal);
    const controls = document.createElement('div');
    controls.className = 'ww-command-quantity';
    const count = document.createElement('span');
    count.textContent = quantity;
    const minus = quantityButton('Reducir ' + name, '−', id, '-1');
    minus.disabled = quantity === 1;
    const plus = quantityButton('Aumentar ' + name, '+', id, '1');
    plus.disabled = quantity === 999;
    const remove = quantityButton('Eliminar ' + name, '×', id, 'remove');
    controls.append(minus, count, plus, remove);
    details.append(title, unit, controls);
    row.append(photo, details, amount);
    lines.append(row);
   }
   form.querySelector('[data-command-no-lines]').hidden = quantities.size > 0;
   form.querySelector('[data-command-subtotal]').textContent = money(total);
   form.querySelector('[data-command-total]').textContent = money(total) + ' MXN';
   form.querySelector('[data-command-mobile-total]').textContent = 'Total ' + money(total) + ' MXN';
   send.disabled = busy || !quantities.size;
   save.disabled = busy;
  }

  search.addEventListener('input', filter);
  category.addEventListener('change', filter);
  form.addEventListener('click', event => {
   const add = event.target.closest('[data-command-add]');
   const change = event.target.closest('[data-command-change]');
   if (busy || (!add && !change)) return;
   const id = Number(add ? add.closest('[data-command-dish]').dataset.commandDish : change.dataset.commandItem);
   if (change?.dataset.commandChange === 'remove') {
    quantities.delete(id);
   } else {
    const quantity = (quantities.get(id) || 0) + Number(change?.dataset.commandChange ?? 1);
    if (quantity > 999 || (quantities.size === 200 && !quantities.has(id))) return;
    quantities.set(id, quantity);
   }
   status.textContent = '';
   error.hidden = true;
   render();
   if (change) {
    const replacement = [...lines.querySelectorAll('[data-command-change]')]
     .find(button => button.dataset.commandItem === change.dataset.commandItem &&
      button.dataset.commandChange === change.dataset.commandChange && !button.disabled);
    (replacement ?? catalog.get(id).querySelector('button')).focus();
   }
  });

  form.addEventListener('submit', async event => {
   event.preventDefault();
   const action = event.submitter?.value;
   if (busy || !action || (action === 'send' && !quantities.size)) return;
   busy = true;
   send.disabled = save.disabled = true;
   form.elements.notes.readOnly = true;
   status.textContent = '';
   error.hidden = true;
   const items = [...quantities].map(([id, quantity]) => ({id, quantity, cents: Number(catalog.get(id).dataset.cents)}));
   try {
    const response = await fetch(form.action, {
     method: 'POST',
     headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': form.elements._token.value},
     body: JSON.stringify({action, items, notes: form.elements.notes.value}),
    });
    const data = await response.json();
    if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message);
    if (action === 'send') {
     location.reload();
     return;
    }
    status.textContent = 'Borrador guardado';
   } catch (failure) {
    error.textContent = failure instanceof SyntaxError || failure instanceof TypeError
     ? 'No se pudo guardar. La selección sigue disponible en el panel.' : failure.message;
    error.hidden = false;
   }
   busy = false;
   form.elements.notes.readOnly = false;
   send.disabled = !quantities.size;
   save.disabled = false;
  });

  render();
  if (draft.some(item => Number(catalog.get(Number(item.id))?.dataset.cents) !== Number(item.cents))) {
   status.textContent = 'El menú cambió desde el borrador. Revisa los platillos y el total.';
  }
 });
 root.querySelectorAll('[data-ww-incident-zone]').forEach(zone=>{const table=zone.form.querySelector('[data-ww-incident-table]');function update(reset){if(reset)table.value='';[...table.options].forEach(o=>{o.hidden=Boolean(o.value&&o.dataset.zone!==zone.value);o.disabled=o.hidden;});table.disabled=!zone.value;if(table.selectedOptions[0]?.disabled)table.value='';}update(false);zone.addEventListener('change',()=>update(true));});
 root.querySelectorAll('[data-ww-image]').forEach(input=>{let url;input.addEventListener('change',()=>{const preview=input.form.querySelector('[data-ww-preview]'),file=input.files[0];if(url)URL.revokeObjectURL(url);preview.hidden=true;input.setCustomValidity('');if(!file)return;if(!['image/jpeg','image/png','image/webp'].includes(file.type)||file.size>4194304){input.setCustomValidity('Selecciona JPG, JPEG, PNG o WEBP de máximo 4 MB.');input.reportValidity();return;}url=URL.createObjectURL(file);preview.src=url;preview.hidden=false;});});
 root.querySelectorAll('[data-ww-form]').forEach(form=>form.addEventListener('submit',async event=>{
  event.preventDefault();if(form.dataset.busy||!form.reportValidity())return;const error=form.querySelector('.ww-form-errors'),submit=form.querySelector('[type=submit]');error.hidden=true;error.replaceChildren();form.dataset.busy='1';submit.disabled=true;
  try{const response=await fetch(form.action,{method:'POST',body:new FormData(form),credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});if(!response.ok){let data;try{data=await response.json();}catch{}const messages=data?.errors?Object.values(data.errors).flat():[data?.message||(response.status===419?'La sesión del formulario expiró. Actualiza la página para continuar.':'No se pudo guardar. Revisa los datos e inténtalo nuevamente.')];messages.forEach(m=>{const p=document.createElement('p');p.textContent=m;error.append(p);});error.hidden=false;error.scrollIntoView({block:'nearest'});return;}location.assign(response.redirected?response.url:location.href);
  }catch{error.textContent='No se pudo conectar. Tus datos se conservan; inténtalo nuevamente.';error.hidden=false;}finally{delete form.dataset.busy;submit.disabled=false;}
 }));
 function elapsed(){root.querySelectorAll('[data-ww-elapsed]').forEach(e=>{const start=new Date(e.dataset.wwElapsed),minutes=Math.floor((Date.now()-start.getTime())/60000);if(Number.isFinite(minutes)&&minutes>=0)e.textContent=minutes>=60?Math.floor(minutes/60)+' h '+minutes%60+' min':minutes+' min transcurridos';});}elapsed();setInterval(elapsed,30000);
})();
