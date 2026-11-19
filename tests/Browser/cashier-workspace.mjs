// Only consumes the HTML exported by CashierWorkspaceTest (SQLite :memory:).
import fs from 'node:fs';
import path from 'node:path';
import {pathToFileURL} from 'node:url';
import {spawn} from 'node:child_process';
import assert from 'node:assert/strict';
const root=process.cwd(),preview=path.join(root,'storage/app/private/cashier-preview');
const manifest=JSON.parse(fs.readFileSync('public/build/manifest.json','utf8'));
const turnoOnly=process.argv.includes('--turno-only');
const pendingOnly=process.argv.includes('--pending-only');
const ticketsOnly=process.argv.includes('--tickets-only');
const pages=turnoOnly?['turno']:pendingOnly?['pendientes']:ticketsOnly?['tickets','tickets-empty']:['panel','turno','pendientes','historial','tickets','cierre','ticket-preview'];
for(const page of pages){let html=fs.readFileSync(path.join(preview,page+'.html'),'utf8');html=html.replace(/<script\b[^>]*>[\s\S]*?<\/script>/g,tag=>(tag.includes('cashier-workspace.js')||tag.includes('cashier-pending.js')||tag.includes('cashier-tickets.js'))?tag:'');html=html.replace(/(href|src)="https?:\/\/localhost\/(css|js|img)\/([^"?]+)(?:\?[^" ]*)?"/g,(_,attr,folder,file)=>`${attr}="${pathToFileURL(path.join(root,'public',folder,file)).href}"`);html=html.replace('</head>',`<link rel="stylesheet" href="${pathToFileURL(path.join(root,'public/build',manifest['resources/css/app.css'].file)).href}"></head>`);fs.writeFileSync(path.join(preview,page+'.html'),html);}
const chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--allow-file-access-from-files','--remote-debugging-port=0','--user-data-dir='+path.join(preview,'chrome-profile'),'about:blank'],{windowsHide:true,stdio:['ignore','ignore','pipe']});
let socket;
try{
 const endpoint=await new Promise((resolve,reject)=>{let output='';const timer=setTimeout(()=>reject(Error('Chrome did not start')),15000);chrome.stderr.on('data',chunk=>{output+=chunk;const m=output.match(/DevTools listening on (ws:\/\/[^\s]+)/);if(m){clearTimeout(timer);resolve(m[1]);}});chrome.on('error',reject);});
 const targets=await(await fetch(`http://127.0.0.1:${new URL(endpoint).port}/json/list`)).json();socket=new WebSocket(targets.find(t=>t.type==='page').webSocketDebuggerUrl);await new Promise((resolve,reject)=>{socket.addEventListener('open',resolve,{once:true});socket.addEventListener('error',reject,{once:true});});
 let sequence=0;const tasks=new Map(),errors=[];socket.addEventListener('message',event=>{const m=JSON.parse(event.data);if(m.id){const task=tasks.get(m.id);tasks.delete(m.id);m.error?task.reject(Error(JSON.stringify(m.error))):task.resolve(m.result);}if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.exception?.description||m.params.exceptionDetails.text);});
 const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++sequence;tasks.set(id,{resolve,reject});socket.send(JSON.stringify({id,method,params}));});
 const evaluate=async expression=>{const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw Error(result.exceptionDetails.exception?.description||result.exceptionDetails.text);return result.result.value;};
 await send('Page.enable');await send('Runtime.enable');
 for(const page of pages){await send('Page.navigate',{url:pathToFileURL(path.join(preview,page+'.html')).href});for(let n=0;n<30;n++){await new Promise(r=>setTimeout(r,50));if(await evaluate('document.readyState==="complete"'))break;}
  for(const width of [1440,390]){await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});await new Promise(r=>setTimeout(r,50));const right=await evaluate(`document.querySelector('.cr-workspace').getBoundingClientRect().right`);assert.ok(right<=width+1,`${page} overflows at ${width}: ${right}`);
   if((page==='panel'||turnoOnly)&&width===1440){const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});fs.writeFileSync(path.join(preview,page+'-desktop.png'),Buffer.from(shot.data,'base64'));}
  }
  assert.equal(await evaluate(`document.querySelectorAll('.sidebar-nav .nav-link').length`),6);
  if(ticketsOnly){
   if(page==='tickets-empty'){
    assert.equal(await evaluate(`document.querySelector('[data-tk-preview]').textContent.includes('Selecciona un ticket')`),true);
    assert.equal(await evaluate(`[...document.querySelectorAll('[data-tk-action]')].every(b=>b.disabled)`),true);
   }else{
    const first=await evaluate(`document.querySelector('[data-tk-preview] .tk-receipt-paper').getAttribute('aria-label')`);
    await evaluate(`const ticketRows=[...document.querySelectorAll('tr[data-tk-pick]')];ticketRows[1].click()`);
    assert.notEqual(await evaluate(`document.querySelector('[data-tk-preview] .tk-receipt-paper').getAttribute('aria-label')`),first);
    await evaluate(`document.querySelector('[data-tk-pick="3"]').click()`);
    assert.equal(await evaluate(`document.querySelector('[data-tk-preview]').textContent.includes('Concepto histórico original')`),true);
    assert.equal(await evaluate(`document.querySelector('[data-tk-preview]').textContent.includes('$42.00')`),true);
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});fs.writeFileSync(path.join(preview,'tickets-desktop.png'),Buffer.from(shot.data,'base64'));
    const folio=await evaluate(`document.querySelector('[data-tk-preview] .tk-receipt-paper').getAttribute('aria-label')`);
    await evaluate(`window.tkPrintCount=0;window.print=()=>{window.tkPrintCount++};document.querySelector('[data-tk-paper]').value='58';document.querySelector('[data-tk-action="print"]').click()`);
    assert.equal(await evaluate(`document.querySelector('.tk-print-host').dataset.paper`),'58');
    assert.equal(await evaluate(`document.querySelector('.tk-print-host .tk-receipt-paper').getAttribute('aria-label')`),folio);
    await send('Emulation.setEmulatedMedia',{media:'print'});
    const printWidth=await evaluate(`document.querySelector('.tk-print-host').getBoundingClientRect().width`);assert.ok(printWidth>215&&printWidth<224);
    await send('Emulation.setEmulatedMedia',{media:''});await evaluate(`window.dispatchEvent(new Event('afterprint'));document.querySelector('[data-tk-paper]').value='80';document.querySelector('[data-tk-action="reprint"]').click()`);
    assert.equal(await evaluate(`document.querySelector('.tk-print-host').dataset.paper`),'80');
    assert.equal(await evaluate(`document.querySelector('.tk-print-host .tk-receipt-paper').getAttribute('aria-label')`),folio);
    await evaluate(`window.dispatchEvent(new Event('afterprint'));document.querySelector('[data-tk-action="pdf"]').click()`);
    assert.equal(await evaluate(`window.tkPrintCount`),3);
    assert.equal(await evaluate(`document.querySelector('[data-tk-feedback]').textContent.includes('Guardar como PDF')`),true);
    await evaluate(`window.dispatchEvent(new Event('afterprint'))`);
    assert.equal(await evaluate(`document.querySelector('.tk-print-host')===null`),true);
    assert.equal(await evaluate(`document.querySelector('.tk-send').disabled`),true);
   }
  }
  if(turnoOnly){
   assert.ok(await evaluate(`document.querySelectorAll('.ct-flow-bar').length>0`));
   assert.equal(await evaluate(`document.querySelector('.ct-flow-line').getAttribute('points').length>0`),true);
   await evaluate(`window.print=()=>{window.ctPrintCalled=true};document.querySelector('[data-ct-print]').click()`);
   assert.equal(await evaluate(`window.ctPrintCalled&&document.body.classList.contains('cr-print-shift')`),true);
   await evaluate(`window.dispatchEvent(new Event('afterprint'))`);
   assert.equal(await evaluate(`document.body.classList.contains('cr-print-shift')`),false);
  }
  if(page==='pendientes'){
   assert.equal(await evaluate(`document.querySelector('[data-cp-confirm]').disabled`),true);
   await evaluate(`document.querySelector('[data-cp-pick]').click();const firstForm=document.querySelector('[data-cp-active] form');window.cpTotal=Number(firstForm.dataset.total);const firstReceived=firstForm.querySelector('[data-cp-received]');firstReceived.value=(window.cpTotal-1).toFixed(2);firstReceived.dispatchEvent(new Event('input',{bubbles:true}));`);
   assert.equal(await evaluate(`document.querySelector('[data-cp-confirm]').disabled`),true);
   assert.equal(await evaluate(`document.querySelector('[data-cp-active] [data-cp-warning]').hidden`),false);
   await evaluate(`const received=document.querySelector('[data-cp-active] [data-cp-received]');received.value=(window.cpTotal+15).toFixed(2);received.dispatchEvent(new Event('input',{bubbles:true}));`);
   assert.equal(await evaluate(`document.querySelector('[data-cp-active] [data-cp-change]').textContent`),'$15.00 MXN');
   assert.equal(await evaluate(`document.querySelector('[data-cp-active] [data-cp-preview-change]').textContent`),'$15.00');
   await evaluate(`const cardRadio=document.querySelector('[data-cp-active] input[value=tarjeta]');cardRadio.checked=true;cardRadio.dispatchEvent(new Event('change',{bubbles:true}));`);
   assert.equal(await evaluate(`document.querySelector('[data-cp-active] [data-cp-received]').disabled`),true);
   assert.equal(await evaluate(`document.querySelector('[data-cp-active] [data-cp-preview-cash]').hidden`),true);
   assert.equal(await evaluate(`document.querySelector('[data-cp-active] [data-cp-preview-method]').textContent`),'Tarjeta');
   await evaluate(`const cashRadio=document.querySelector('[data-cp-active] input[value=efectivo]');cashRadio.checked=true;cashRadio.dispatchEvent(new Event('change',{bubbles:true}));`);
   await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
   const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});fs.writeFileSync(path.join(preview,'pendientes-desktop.png'),Buffer.from(shot.data,'base64'));
   await evaluate(`window.cpRequestCount=0;window.fetch=()=>{window.cpRequestCount++;return new Promise(resolve=>window.cpResolve=resolve)};const form=document.querySelector('[data-cp-active] form');form.requestSubmit();form.requestSubmit();`);
   assert.equal(await evaluate(`window.cpRequestCount`),1);
   await evaluate(`window.cpResolve({ok:false,status:422,json:async()=>({errors:{recibido:['Validación de prueba aislada']}})})`);await new Promise(r=>setTimeout(r,80));
   assert.equal(await evaluate(`document.querySelector('[data-cp-active] .cr-form-errors').textContent`),'Validación de prueba aislada');
   assert.equal(await evaluate(`Number(document.querySelector('[data-cp-active] [data-cp-received]').value)===window.cpTotal+15`),true);
   assert.equal(await evaluate(`document.querySelector('[data-cp-ticket-disabled]').disabled`),true);
   await evaluate(`document.querySelector('[data-cp-cancel]').click()`);
   assert.equal(await evaluate(`document.querySelector('[data-cp-confirm]').disabled`),true);
   assert.equal(await evaluate(`document.querySelectorAll('[data-cp-row].is-selected').length`),0);
  }
  if(page==='cierre'){
   await evaluate(`const counted=document.querySelector('[data-cr-counted]');counted.value='490';counted.dispatchEvent(new Event('input',{bubbles:true}));document.querySelector('[data-cr-closing]').requestSubmit();`);assert.equal(await evaluate(`document.querySelector('[data-cr-difference-total]').textContent`),'-$10.00');assert.equal(await evaluate(`document.querySelector('#cr-close-confirm').open`),true);assert.equal(await evaluate(`document.querySelector('[data-cr-difference]').classList.contains('cr-red')`),true);
  }
 }
 assert.deepEqual(errors,[]);console.log(ticketsOnly?'PASS: Tickets and empty state at 1440/390 px; inline selection, historical prices, unchanged folio for printing/reprinting/PDF, thermal 58/80 mm and disabled sending. No application requests.':pendingOnly?'PASS: pending desk at 1440/390 px; inline selection, cash change, method-dependent receipt, insufficient-payment block, duplicate-submit block, validation retention and cancel. No application requests.':turnoOnly?'PASS: Turno de caja at 1440/390 px, mixed SVG chart and read-only partial printing. No application requests.':'PASS: six views + ticket at 1440/390 px; six navigation links; real change calculation, payment-method fields, inline validation and cash-closing confirmation. No application requests.');
}finally{socket?.close();chrome.kill();}
