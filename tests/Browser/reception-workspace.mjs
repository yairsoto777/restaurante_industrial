// Uses SQLite fixture HTML exported by ReceptionWorkspaceTest, never the real application.
import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {pathToFileURL} from 'node:url';

const directory=path.resolve('storage/app/private/reception-preview');
const chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',[
    '--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check',
    '--allow-file-access-from-files','--remote-debugging-port=0',
    '--user-data-dir='+path.join(directory,'chrome-profile'),'about:blank',
],{windowsHide:true,stdio:['ignore','ignore','pipe']});
let socket;
try {
    const endpoint=await new Promise((resolve,reject)=>{
        let output='';const timeout=setTimeout(()=>reject(Error('Chrome startup timeout')),15000);
        chrome.stderr.on('data',data=>{output+=data;const match=output.match(/DevTools listening on (ws:\/\/[^\s]+)/);if(match){clearTimeout(timeout);resolve(match[1]);}});
        chrome.once('error',reject);
    });
    const targets=await(await fetch('http://127.0.0.1:'+new URL(endpoint).port+'/json/list')).json();
    socket=new WebSocket(targets.find(t=>t.type==='page').webSocketDebuggerUrl);
    await new Promise(resolve=>socket.addEventListener('open',resolve,{once:true}));
    let sequence=0;const pending=new Map(),errors=[];
    socket.addEventListener('message',event=>{
        const message=JSON.parse(event.data);
        if(message.id){const task=pending.get(message.id);pending.delete(message.id);message.error?task.reject(Error(JSON.stringify(message.error))):task.resolve(message.result);}
        if(message.method==='Runtime.exceptionThrown')errors.push(message.params.exceptionDetails.exception?.description || message.params.exceptionDetails.text);
    });
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++sequence;const timeout=setTimeout(()=>reject(Error(method+' timeout')),10000);pending.set(id,{resolve:value=>{clearTimeout(timeout);resolve(value)},reject});socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw Error(result.exceptionDetails.text);return result.result.value;};
    await send('Page.enable');await send('Runtime.enable');
    await send('Page.addScriptToEvaluateOnNewDocument',{source:"window.fetch=async()=>({ok:true,json:async()=>({items:[],unread:0})})"});
    const findings=[];
    for(const name of ['dashboard','deliveries','register','capture','history','incidents','receipt']) {
        await send('Page.navigate',{url:pathToFileURL(path.join(directory,name+'.html')).href});
        for(let attempt=0;attempt<40;attempt++) {if(await evaluate('document.readyState===\'complete\''))break;await new Promise(resolve=>setTimeout(resolve,75));}
        for(const width of [1440,768,390]) {
            await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
            await new Promise(resolve=>setTimeout(resolve,80));
            const result=await evaluate(`({page:${JSON.stringify(name)},width:innerWidth,scroll:document.documentElement.scrollWidth,sidebar:getComputedStyle(document.querySelector('.app-sidebar')).backgroundColor,links:[...document.querySelectorAll('.sidebar-nav .nav-link')].map(e=>e.textContent.trim())})`);
            assert.ok(result.scroll<=width+1,JSON.stringify(result));
            assert.equal(result.sidebar,'rgb(23, 55, 94)');
            assert.deepEqual(result.links,['Panel principal','Entregas programadas','Registrar recepción','Historial de recepciones','Incidencias']);
            findings.push(result);
            if(width===1440&&name==='dashboard'||width===390&&name==='capture') {
                const screenshot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
                fs.writeFileSync(path.join(directory,name+'-'+width+'.png'),Buffer.from(screenshot.data,'base64'));
            }
        }
        if(name==='capture') {
            const result=await evaluate(`(()=>{const f=document.querySelector('[data-capture-form]');for(const [selector,value] of [['[data-delivered]','3'],['[data-accepted]','2'],['[data-lot]','LOTE-1'],['[data-expiry]','2027-01-01'],['[data-incident]','Saco dañado y faltante']]){const e=f.querySelector(selector);e.value=value;e.dispatchEvent(new Event('input',{bubbles:true}));}f.querySelector('select').value='Aceptación parcial';f.querySelector('[data-submit-reception]').click();return {open:document.querySelector('[data-capture-dialog]').open,rejected:f.querySelector('[data-rejected]').textContent,missing:f.querySelector('[data-missing]').textContent,summary:document.querySelector('[data-dialog-summary]').textContent,disabled:f.querySelector('[data-submit-reception]').disabled}})()`);
            assert.equal(result.open,true);assert.equal(result.rejected,'1');assert.equal(result.missing,'1.5');assert.equal(result.disabled,false);assert.ok(result.summary.includes('Saco dañado y faltante'));
            await evaluate("document.querySelector('[data-capture-back]').click()");
            assert.equal(await evaluate("document.querySelector('[data-capture-dialog]').open"),false);
        }
    }
    assert.deepEqual(errors,[]);
    fs.writeFileSync(path.join(directory,'results.json'),JSON.stringify(findings,null,2));
    console.log('OK: 7 vistas, 3 tamaños, navegación aislada y resumen de captura sin envío.');
} finally {socket?.close();chrome.kill();}
