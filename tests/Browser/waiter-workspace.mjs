// Runs exclusively against HTML exported by the SQLite :memory: feature fixture.
import fs from 'node:fs';
import path from 'node:path';
import {pathToFileURL} from 'node:url';
import {spawn} from 'node:child_process';
import assert from 'node:assert/strict';
const root=process.cwd(),preview=path.join(root,'storage/app/private/waiter-preview');
const manifest=JSON.parse(fs.readFileSync('public/build/manifest.json','utf8'));
const pages=['panel','servicios','mesas','atenciones','menu','historial','incidencias'];
for(const page of pages){let html=fs.readFileSync(path.join(preview,page+'.html'),'utf8');html=html.replace(/<script\b[^>]*>[\s\S]*?<\/script>/g,tag=>tag.includes('waiter-workspace.js')?tag:'');html=html.replace(/(href|src)="https?:\/\/localhost\/(css|js|img)\/([^"?]+)(?:\?[^" ]*)?"/g,(_,attr,folder,file)=>`${attr}="${pathToFileURL(path.join(root,'public',folder,file)).href}"`);html=html.replace('</head>',`<link rel="stylesheet" href="${pathToFileURL(path.join(root,'public/build',manifest['resources/css/app.css'].file)).href}"></head>`);fs.writeFileSync(path.join(preview,page+'.html'),html);}
const chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--allow-file-access-from-files','--remote-debugging-port=0','--user-data-dir='+path.join(preview,'chrome-profile'),'about:blank'],{windowsHide:true,stdio:['ignore','ignore','pipe']});
let socket;
try{
 const endpoint=await new Promise((resolve,reject)=>{let output='';const timer=setTimeout(()=>reject(Error('Chrome did not start')),15000);chrome.stderr.on('data',chunk=>{output+=chunk;const m=output.match(/DevTools listening on (ws:\/\/[^\s]+)/);if(m){clearTimeout(timer);resolve(m[1]);}});chrome.on('error',reject);});
 const targets=await(await fetch(`http://127.0.0.1:${new URL(endpoint).port}/json/list`)).json();socket=new WebSocket(targets.find(t=>t.type==='page').webSocketDebuggerUrl);await new Promise(resolve=>socket.addEventListener('open',resolve,{once:true}));
 let sequence=0;const tasks=new Map(),errors=[];socket.addEventListener('message',event=>{const m=JSON.parse(event.data);if(m.id){const task=tasks.get(m.id);tasks.delete(m.id);m.error?task.reject(Error(JSON.stringify(m.error))):task.resolve(m.result);}if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.exception?.description||m.params.exceptionDetails.text);});
 const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++sequence;tasks.set(id,{resolve,reject});socket.send(JSON.stringify({id,method,params}));});
 const evaluate=async expression=>{const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw Error(result.exceptionDetails.exception?.description||result.exceptionDetails.text);return result.result.value;};
 await send('Page.enable');await send('Runtime.enable');
 for(const page of pages){await send('Page.navigate',{url:pathToFileURL(path.join(preview,page+'.html')).href});for(let n=0;n<30;n++){await new Promise(r=>setTimeout(r,50));if(await evaluate('document.readyState==="complete"'))break;}
  for(const width of [1440,390]){await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});await new Promise(r=>setTimeout(r,50));const overflow=await evaluate(`(()=>{const r=document.querySelector('.ww-workspace').getBoundingClientRect();return {right:r.right,width:innerWidth,scroll:document.documentElement.scrollWidth}})()`);assert.ok(overflow.right<=width+1,`${page} overflows at ${width}: ${JSON.stringify(overflow)}`);
   if((page==='panel'&&width===1440)||(page==='menu'&&width===390)){const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});fs.writeFileSync(path.join(preview,page+'-'+width+'.png'),Buffer.from(shot.data,'base64'));}
  }
  assert.equal(await evaluate(`document.querySelectorAll('.sidebar-nav .nav-link').length`),7);
  if(page==='mesas'){
   await evaluate(`document.querySelector('[data-ww-open="attention-3"]').click()`);assert.equal(await evaluate(`document.getElementById('attention-3').open`),true);
   await evaluate(`document.querySelector('#attention-3 [data-ww-step="1"]').click()`);assert.equal(await evaluate(`document.querySelector('#attention-3 input[name=comensales]').value`),'2');
   // Simulate a backend validation response without submitting any application request.
   await evaluate(`window.fetch=async()=>({ok:false,status:422,json:async()=>({errors:{comensales:['Capacidad no válida']}})});document.querySelector('#attention-3 form').requestSubmit()`);await new Promise(r=>setTimeout(r,50));assert.equal(await evaluate(`document.querySelector('#attention-3 .ww-form-errors').textContent`),'Capacidad no válida');assert.equal(await evaluate(`document.querySelector('#attention-3 input[name=comensales]').value`),'2');
  }
  if(page==='incidencias'){
   await evaluate(`document.querySelector('[data-ww-open="incident"]').click();const zone=document.querySelector('[data-ww-incident-zone]'),table=document.querySelector('[data-ww-incident-table]');zone.value='1';zone.dispatchEvent(new Event('change'));table.value='3';zone.value='';zone.dispatchEvent(new Event('change'));`);assert.equal(await evaluate(`document.querySelector('[data-ww-incident-table]').value`),'');assert.equal(await evaluate(`document.querySelector('[data-ww-incident-table]').disabled`),true);
  }
 }
 assert.deepEqual(errors,[]);console.log('PASS: seven views at 1440/390 px; seven navigation links; attention drawer, guest stepper, inline validation retention and dependent incident tables.');
}finally{socket?.close();chrome.kill();}
