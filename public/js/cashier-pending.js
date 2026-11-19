(() => {
 'use strict';const root=document.querySelector('[data-cp-workspace]');if(!root)return;
 const active=root.querySelector('[data-cp-active]'),confirm=root.querySelector('[data-cp-confirm]'),cancel=root.querySelector('[data-cp-cancel]'),paidTicket=root.querySelector('[data-cp-paid-ticket]'),disabledTicket=root.querySelector('[data-cp-ticket-disabled]');
 let selected=null,busy=false;
 const cents=value=>{const match=String(value).trim().match(/^(\d+)(?:\.(\d{0,2}))?$/);return match?BigInt(match[1])*100n+BigInt((match[2]||'').padEnd(2,'0')):null;};
 const money=value=>new Intl.NumberFormat('es-MX',{style:'currency',currency:'MXN'}).format(Number(value)/100);
 function ticketState(){if(paidTicket)paidTicket.hidden=selected!==null;disabledTicket.hidden=Boolean(paidTicket)&&selected===null;}
 function update(){const form=active.querySelector('[data-cp-form]');if(!form)return;const eligible=form.dataset.eligible==='1',method=form.querySelector('input[name=metodo]:checked')?.value||'efectivo',cash=method==='efectivo',received=form.querySelector('[data-cp-received]'),total=cents(form.dataset.total)||0n,paid=cents(received.value),short=cash&&paid!==null&&paid<total;
  form.querySelector('[data-cp-cash]').hidden=!cash;form.querySelector('[data-cp-bank]').hidden=cash;received.disabled=!eligible||!cash;received.required=eligible&&cash;received.setCustomValidity(short?'El importe recibido no cubre el total.':'');
  const change=paid!==null&&paid>=total?paid-total:0n;form.querySelector('[data-cp-change]').textContent=money(change)+' MXN';const warning=form.querySelector('[data-cp-warning]');warning.hidden=!short;warning.textContent=short?'Faltan '+money(total-paid)+' MXN para cubrir la cuenta.':'';
  const box=form.querySelector('[data-cp-change-box]');box.classList.toggle('cr-red',short);box.classList.toggle('cr-green',!short);
  active.querySelector('[data-cp-preview-method]').textContent={efectivo:'Efectivo',tarjeta:'Tarjeta',transferencia:'Transferencia'}[method];active.querySelector('[data-cp-preview-cash]').hidden=!cash;active.querySelector('[data-cp-preview-received]').textContent=paid===null?'—':money(paid);active.querySelector('[data-cp-preview-change]').textContent=paid===null?'—':money(change);
  confirm.disabled=busy||selected===null||!eligible||(cash&&(paid===null||short));cancel.disabled=busy||selected===null;
 }
 function select(id){if(busy)return;const template=id===null?root.querySelector('[data-cp-empty]'):[...root.querySelectorAll('[data-cp-template]')].find(t=>t.dataset.cpTemplate===id);if(!template)return;selected=id;active.replaceChildren(template.content.cloneNode(true));root.querySelectorAll('[data-cp-row]').forEach(row=>{const picked=row.dataset.cpRow===id;row.classList.toggle('is-selected',picked);row.querySelector('[data-cp-pick]').setAttribute('aria-pressed',String(picked));});ticketState();update();}
 root.addEventListener('click',event=>{const pick=event.target.closest('[data-cp-pick]');if(pick){select(pick.dataset.cpPick);return;}if(event.target.closest('[data-cp-cancel]')){select(null);return;}const amount=event.target.closest('[data-cp-amount]');if(amount&&!busy){const form=active.querySelector('[data-cp-form]'),received=form.querySelector('[data-cp-received]');received.value=amount.dataset.cpAmount==='exacto'?form.dataset.total:amount.dataset.cpAmount;update();}});
 root.addEventListener('input',event=>{if(event.target.closest('[data-cp-form]'))update();});root.addEventListener('change',event=>{if(event.target.closest('[data-cp-form]'))update();});
 root.querySelector('[data-cp-order]')?.addEventListener('change',event=>{const field=root.querySelector('[data-cp-order-field]');field.value=event.target.value;field.form.requestSubmit();});
 root.addEventListener('submit',async event=>{const form=event.target.closest('[data-cp-form]');if(!form)return;event.preventDefault();if(busy||selected===null||form.dataset.eligible!=='1'||confirm.disabled||!form.reportValidity())return;
  const payload=new FormData(form),feedback=form.querySelector('.cr-form-errors');feedback.replaceChildren();feedback.hidden=true;busy=true;update();root.querySelectorAll('[data-cp-pick]').forEach(b=>b.disabled=true);
  try{const response=await fetch(form.action,{method:'POST',credentials:'same-origin',body:payload,headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});let result;try{result=await response.json();}catch{}if(!response.ok){const messages=result?.errors?Object.values(result.errors).flat():[result?.message||(response.status===419?'La sesión expiró. Actualiza la página para continuar.':'No se pudo registrar el pago. Revisa los datos.')];for(const message of messages){const p=document.createElement('p');p.textContent=message;feedback.append(p);}feedback.hidden=false;feedback.scrollIntoView({block:'nearest'});return;}
   if(!result?.redirect)throw Error('Respuesta de cobro no confirmada');
   location.assign(result.redirect); // Actual server state refreshes KPIs, pending rows, success and the persisted ticket link.
  }catch{feedback.textContent='No se pudo confirmar el resultado. Consulta el historial antes de volver a cobrar esta cuenta.';feedback.hidden=false;}finally{busy=false;root.querySelectorAll('[data-cp-pick]').forEach(b=>b.disabled=false);update();}
 });
 ticketState();update();
})();
