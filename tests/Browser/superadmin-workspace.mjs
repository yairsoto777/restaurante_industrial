// Offline UI smoke: generated read-only previews; never submits commercial forms.
import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import {pathToFileURL} from 'node:url';
import {spawn} from 'node:child_process';

const preview = path.join(process.cwd(), 'storage/app/private/superadmin-preview');
const chrome = spawn('C:/Program Files/Google/Chrome/Application/chrome.exe', [
    '--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check',
    '--allow-file-access-from-files','--remote-debugging-port=0',
    '--user-data-dir='+path.join(preview,'chrome-profile'),'about:blank',
], {windowsHide:true,stdio:['ignore','ignore','pipe']});
let socket;
try {
    const endpoint = await new Promise((resolve,reject) => {
        let output='';
        const timer=setTimeout(()=>reject(new Error('Chrome startup timed out: '+output)),15000);
        chrome.stderr.on('data',chunk=>{output+=chunk;const match=output.match(/DevTools listening on (ws:\/\/[^\s]+)/);if(match){clearTimeout(timer);resolve(match[1]);}});
        chrome.on('error',reject);
    });
    const targets=await (await fetch(`http://127.0.0.1:${new URL(endpoint).port}/json/list`)).json();
    socket=new WebSocket(targets.find(item=>item.type==='page').webSocketDebuggerUrl);
    await new Promise((resolve,reject)=>{socket.addEventListener('open',resolve,{once:true});socket.addEventListener('error',reject,{once:true});});
    let sequence=0;
    const pending=new Map(),errors=[];
    socket.addEventListener('message',event=>{
        const message=JSON.parse(event.data);
        if(message.id){const item=pending.get(message.id);pending.delete(message.id);message.error?item.reject(new Error(JSON.stringify(message.error))):item.resolve(message.result);}
        if(message.method==='Runtime.exceptionThrown') errors.push(message.params.exceptionDetails.exception?.description || message.params.exceptionDetails.text);
    });
    const send=(method,params={})=>new Promise((resolve,reject)=>{
        const id=++sequence;const timer=setTimeout(()=>reject(new Error('DevTools timeout: '+method)),12000);
        pending.set(id,{resolve:value=>{clearTimeout(timer);resolve(value);},reject:error=>{clearTimeout(timer);reject(error);}});
        socket.send(JSON.stringify({id,method,params}));
    });
    const evaluate=async expression=>{
        const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});
        if(result.exceptionDetails)throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
        return result.result.value;
    };
    await send('Page.enable');await send('Runtime.enable');
    const report=[];
    for(const page of ['panel','solicitudes','clientes','sedes','usuarios','configuracion','usuarios-general']) {
        const module=page==='usuarios-general' ? 'usuarios' : page;
        await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
        await send('Page.navigate',{url:pathToFileURL(path.join(preview,page+'.html')).href});
        for(let n=0;n<40;n++){
            if(await evaluate(`document.readyState==='complete' && !!document.querySelector('[data-module="${module}"]')`))break;
            await new Promise(resolve=>setTimeout(resolve,100));
        }
        await evaluate('Promise.all([...document.images].map(image=>image.decode().catch(()=>null)))');
        assert.equal(await evaluate(`getComputedStyle(document.querySelector('.app-sidebar')).backgroundColor`),'rgb(23, 55, 94)');
        assert.equal(await evaluate('document.querySelector(".sa-brand-logo").naturalWidth>0'),true,'Original logo must load');
        for(const width of [1440,390]) {
            await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
            const layout=await evaluate(`({width:innerWidth,scroll:document.documentElement.scrollWidth,kpis:document.querySelectorAll('.sa-kpi').length})`);
            assert.ok(layout.scroll<=width+1,`${module}: page overflow at ${width}: ${layout.scroll}`);
            if(module!=='configuracion')assert.equal(layout.kpis,4);
            assert.equal(await evaluate(`getComputedStyle(document.querySelector('.notification-button')).display!=='none'`),true,'Notification bell must remain visible');
            report.push({module,...layout});
            if(width===390 && page==='panel') {
                const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
                fs.writeFileSync(path.join(preview,'panel-390.png'),Buffer.from(shot.data,'base64'));
            }
        }
        await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
        const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
        fs.writeFileSync(path.join(preview,page+'-1440.png'),Buffer.from(shot.data,'base64'));
        const triggers=await evaluate(`[...document.querySelectorAll('[data-sa-open]')].filter(el=>!el.closest('dialog')).map(el=>el.dataset.saOpen)`);
        for(const id of [...new Set(triggers)]) {
            await evaluate(`document.querySelector('[data-sa-open="${id}"]').click()`);
            assert.equal(await evaluate(`document.getElementById('${id}').open`),true,'Drawer failed: '+id);
            assert.equal(await evaluate(`document.getElementById('${id}').querySelector('[data-sa-close]')!==null`),true);
            await evaluate(`document.getElementById('${id}').querySelector('[data-sa-close]').click()`);
            assert.equal(await evaluate(`document.getElementById('${id}').open`),false);
        }
        if(module==='configuracion') {
            await evaluate(`document.querySelector('[data-sa-notifications]').click()`);
            await new Promise(resolve=>setTimeout(resolve,30));
            assert.equal(await evaluate(`document.querySelector('.manager-notifications').open`),true);
        }
        if(triggers.length) {
            await send('Emulation.setDeviceMetricsOverride',{width:390,height:850,deviceScaleFactor:1,mobile:false});
            const id=triggers[0];
            await evaluate(`document.querySelector('[data-sa-open="${id}"]').click()`);
            const box=await evaluate(`(()=>{const el=document.getElementById('${id}');return {open:el.open,width:el.getBoundingClientRect().width,scroll:el.scrollWidth};})()`);
            assert.equal(box.open,true);assert.ok(box.width<=390 && box.scroll<=391,'Drawer must fit mobile viewport');
            await evaluate(`document.getElementById('${id}').close()`);
        }
        console.log('OK '+page+': desktop/mobile, logo, '+triggers.length+' drawer triggers');
    }
    assert.deepEqual(errors,[],'Browser runtime errors');
    fs.writeFileSync(path.join(preview,'browser-report.json'),JSON.stringify(report,null,2));
    console.log('OK six views; no runtime errors; no forms submitted or database writes');
} finally {
    socket?.close();
    chrome.kill();
}
