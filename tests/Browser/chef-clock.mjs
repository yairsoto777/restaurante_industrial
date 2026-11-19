// One interaction pass over fixture HTML exported from SQLite :memory:.
import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {pathToFileURL} from 'node:url';

const directory=path.resolve('storage/app/private/chef-clock-preview');
const chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',[
    '--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--allow-file-access-from-files',
    '--remote-debugging-port=0','--user-data-dir='+path.join(directory,'chrome-profile'),'about:blank',
],{windowsHide:true,stdio:['ignore','ignore','pipe']});
let socket;
try {
    const endpoint=await new Promise((resolve,reject)=>{
        let output='';const timeout=setTimeout(()=>reject(Error('Chrome startup timeout')),15000);
        chrome.stderr.on('data',data=>{output+=data;const match=output.match(/DevTools listening on (ws:\/\/[^\s]+)/);if(match){clearTimeout(timeout);resolve(match[1]);}});
        chrome.once('error',reject);
    });
    const targets=await(await fetch('http://127.0.0.1:'+new URL(endpoint).port+'/json/list')).json();
    socket=new WebSocket(targets.find(target=>target.type==='page').webSocketDebuggerUrl);
    await new Promise(resolve=>socket.addEventListener('open',resolve,{once:true}));
    let sequence=0;const pending=new Map(),errors=[];
    socket.addEventListener('message',event=>{
        const message=JSON.parse(event.data);
        if(message.id){const task=pending.get(message.id);pending.delete(message.id);message.error?task.reject(Error(JSON.stringify(message.error))):task.resolve(message.result);}
        if(message.method==='Runtime.exceptionThrown')errors.push(message.params.exceptionDetails.exception?.description || message.params.exceptionDetails.text);
    });
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++sequence;const timeout=setTimeout(()=>reject(Error(method+' timeout')),10000);pending.set(id,{resolve:value=>{clearTimeout(timeout);resolve(value)},reject});socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw Error(result.exceptionDetails.exception?.description||result.exceptionDetails.text);return result.result.value;};
    await send('Page.enable');await send('Runtime.enable');
    await send('Page.addScriptToEvaluateOnNewDocument',{source:"window.fetch=async()=>({ok:true,json:async()=>({items:[],unread:0})})"});
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:false});
    await send('Page.navigate',{url:pathToFileURL(path.join(directory,'clock.html')).href});
    for(let attempt=0;attempt<60;attempt++){if(await evaluate("document.readyState==='complete' && document.querySelector('[data-cw-time-open]')?.hidden===false"))break;await new Promise(resolve=>setTimeout(resolve,50));}
    assert.deepEqual(await evaluate("[...document.querySelectorAll('[data-cw-time-text]')].map(e=>e.textContent)"),['08:00 a. m.','11:00 a. m.']);
    assert.equal(await evaluate("document.querySelector('[name=inicio_hora]').type"),'hidden');
    await evaluate("document.querySelector('[data-cw-time-open]').click()");
    assert.equal(await evaluate("document.querySelector('dialog').open"),true);
    const bounds=await evaluate("(()=>{const r=document.querySelector('dialog').getBoundingClientRect();return {left:r.left,right:r.right,width:innerWidth};})()");
    assert.ok(bounds.left>=0&&bounds.right<=bounds.width);
    // Pointer choices move from hours to minutes, then to the meridiem step.
    await evaluate("document.querySelector('[data-cw-time-numbers] [data-value=\"1\"]').click()");
    assert.equal(await evaluate("document.querySelector('[data-cw-time-dial]').getAttribute('aria-label').startsWith('Minutos')"),true);
    await evaluate("document.querySelector('[data-cw-time-numbers] [data-value=\"45\"]').click();document.querySelector('[data-cw-time-meridiem=pm]').click();document.querySelector('[data-cw-time-accept]').click()");
    assert.equal(await evaluate("document.querySelector('[name=inicio_hora]').value"),'13:45');
    assert.equal(await evaluate("document.querySelector('[data-cw-time-text]').textContent"),'01:45 p. m.');
    assert.equal(await evaluate("document.querySelector('[name=fin_hora]').closest('[data-cw-time-field]').querySelector('[data-cw-time-error]').hidden"),false);
    // Cancellation must not commit a partially selected value.
    await evaluate("document.querySelector('[data-cw-time-open]').click();document.querySelector('[data-cw-time-numbers] [data-value=\"3\"]').click();document.querySelector('[data-cw-time-cancel]').click()");
    assert.equal(await evaluate("document.querySelector('[name=inicio_hora]').value"),'13:45');
    // Any minute is available from the keyboard, not just five-minute increments.
    await evaluate("document.querySelector('[name=fin_hora]').closest('[data-cw-time-field]').querySelector('[data-cw-time-open]').click();document.querySelector('[data-cw-time-numbers] [data-value=\"2\"]').click();document.querySelector('[data-cw-time-dial]').dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowRight',bubbles:true}));document.querySelector('[data-cw-time-dial]').dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}));document.querySelector('[data-cw-time-meridiem=pm]').click();document.querySelector('[data-cw-time-accept]').click()");
    assert.equal(await evaluate("document.querySelector('[name=fin_hora]').value"),'14:01');
    assert.equal(await evaluate("document.querySelector('[name=fin_hora]').closest('[data-cw-time-field]').querySelector('[data-cw-time-error]').hidden"),true);
    assert.equal(await evaluate("new FormData(document.querySelector('[data-cw-service-form]')).get('inicio_hora')"),'13:45');
    // Noon and midnight must retain their distinct 24-hour values.
    for(const [meridiem,expected] of [['am','00:00'],['pm','12:00']]) {
        await evaluate(`document.querySelector('[data-cw-time-open]').click();document.querySelector('[data-cw-time-numbers] [data-value="12"]').click();document.querySelector('[data-cw-time-numbers] [data-value="0"]').click();document.querySelector('[data-cw-time-meridiem=${meridiem}]').click();document.querySelector('[data-cw-time-accept]').click()`);
        assert.equal(await evaluate("document.querySelector('[name=inicio_hora]').value"),expected);
    }
    // Missing hours are blocked without a request or a real database write.
    assert.equal(await evaluate("(()=>{const f=document.querySelector('[data-cw-service-form]'),e=new Event('submit',{bubbles:true,cancelable:true});f.elements.inicio_hora.value='';f.dispatchEvent(e);return e.defaultPrevented;})()"),true);
    await evaluate("document.querySelector('[data-cw-time-open]').click()");
    await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    assert.equal(await evaluate("document.querySelector('dialog').open"),false);
    assert.deepEqual(errors,[]);
    console.log('OK: reloj móvil, hora/minutos/periodo, cancelación, teclado, validación, FormData, mediodía y medianoche.');
} finally {socket?.close();chrome.kill();}
