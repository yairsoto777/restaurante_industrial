// Local fixture previews exported by OperationsPresentationTest; no production requests.
import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import { spawn } from 'node:child_process';
import assert from 'node:assert/strict';

const root = process.cwd();
const preview = path.join(root,'storage/app/private/ui-preview');
const manifest = JSON.parse(fs.readFileSync(path.join(root,'public/build/manifest.json'),'utf8'));
const extractCaptainAssets=process.argv.includes('--extract-captain-assets');
const pages = extractCaptainAssets ? [] : fs.readdirSync(preview).filter(name=>name.endsWith('.html') && (!process.argv.includes('--captain-only') || name.startsWith('captain-')) && (!process.argv.includes('--waiter-only') || name.startsWith('waiter-')) && (!process.argv.includes('--assigned-tables-only') || name==='waiter-waiter-index.html'));
for (const name of pages) {
    const file = path.join(preview,name);
    let html = fs.readFileSync(file,'utf8');
    html = html.replace(/public\/(?:build\/)?assets\/app-[^"<>]+\.css/g, 'public/build/'+manifest['resources/css/app.css'].file);
    html = html.replace(/https?:\/\/localhost\/img\/ui\/((?:captain-(?:dining|kitchen)\.webp|service-(?:breakfast|lunch|dinner)\.jpg))/g,(_,image)=>pathToFileURL(path.join(root,'public/img/ui',image)).href);
    // Prevent print UI in the standalone test preview, preserving source ticket behavior.
    if (name==='cashier-ticket.html') html=html.replace("window.addEventListener('load',()=>window.print())",'');
    fs.writeFileSync(file,html);
}
const chrome = spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',[
    '--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check',
    '--allow-file-access-from-files','--remote-debugging-port=0',
    '--user-data-dir='+path.join(preview,'chrome-profile'),'about:blank',
],{windowsHide:true,stdio:['ignore','ignore','pipe']});
chrome.stderr.on('data',chunk=>fs.appendFileSync(path.join(preview,'chrome-stderr.log'),chunk));
let socket;
try {
    const endpoint = await new Promise((resolve,reject)=>{
        let output='';
        const timeout=setTimeout(()=>reject(new Error('Chrome did not expose DevTools: '+output)),15000);
        chrome.stderr.on('data',chunk=>{
            output+=chunk;
            const match=output.match(/DevTools listening on (ws:\/\/[^\s]+)/);
            if(match){clearTimeout(timeout);resolve(match[1]);}
        });
        chrome.on('error',reject);
    });
    const port = new URL(endpoint).port;
    const targets=await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket=new WebSocket(targets.find(target=>target.type==='page').webSocketDebuggerUrl);
    await new Promise((resolve,reject)=>{socket.addEventListener('open',resolve,{once:true});socket.addEventListener('error',reject,{once:true});});
    let sequence=0;
    const pending=new Map(),errors=[];
    socket.addEventListener('close',event=>{
        for(const task of pending.values())task.reject(new Error('DevTools closed: '+event.code+' '+event.reason));
        pending.clear();
    });
    socket.addEventListener('message',event=>{
        const message=JSON.parse(event.data);
        if(message.id){const task=pending.get(message.id);pending.delete(message.id);message.error?task.reject(new Error(JSON.stringify(message.error))):task.resolve(message.result);}
        if(message.method==='Runtime.exceptionThrown')errors.push(message.params.exceptionDetails.exception?.description || message.params.exceptionDetails.text);
    });
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++sequence;const timeout=setTimeout(()=>reject(new Error('DevTools timeout: '+method)),10000);pending.set(id,{resolve:value=>{clearTimeout(timeout);resolve(value);},reject:error=>{clearTimeout(timeout);reject(error);}});socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{
        const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});
        if(result.exceptionDetails)throw new Error(result.exceptionDetails.text);
        return result.result.value;
    };
    await send('Page.enable');await send('Runtime.enable');
    if(extractCaptainAssets){
        const capture=path.join(root,'storage/app/private/captain-asset-capture.html');
        fs.writeFileSync(capture,'<!doctype html><html><head><meta charset="utf-8"></head><body></body></html>');
        await send('Page.navigate',{url:pathToFileURL(capture).href});
        await new Promise(resolve=>setTimeout(resolve,250));
        fs.mkdirSync(path.join(root,'public/img/ui'),{recursive:true});
        for(const [source,destination] of [['comedor-industrial.mp4','captain-dining.webp'],['login-cocina.mp4','captain-kitchen.webp']]){
            const url=pathToFileURL(path.join(root,'public/videos',source)).href;
            const data=await evaluate(`new Promise((resolve,reject)=>{const video=document.createElement('video');video.muted=true;video.preload='auto';video.onloadedmetadata=()=>{video.currentTime=Math.min(2,video.duration/3)};video.onseeked=()=>{const canvas=document.createElement('canvas');canvas.width=Math.min(1600,video.videoWidth);canvas.height=Math.round(canvas.width*video.videoHeight/video.videoWidth);canvas.getContext('2d').drawImage(video,0,0,canvas.width,canvas.height);resolve(canvas.toDataURL('image/webp',0.86));video.remove()};video.onerror=()=>reject(new Error('Cannot load local video'));video.src=${JSON.stringify(url)};document.body.append(video)})`);
            fs.writeFileSync(path.join(root,'public/img/ui',destination),Buffer.from(data.split(',')[1],'base64'));
            console.log('Extracted '+destination+' from '+source);
        }
    }
    const findings=[];
    for(const name of pages){
        await send('Page.navigate',{url:pathToFileURL(path.join(preview,name)).href});
        for(let tries=0;tries<40;tries++){
            if(await evaluate('document.readyState === "complete"'))break;
            await new Promise(resolve=>setTimeout(resolve,100));
        }
        await evaluate(`Promise.all([...document.images].filter(image=>image.src.includes('/img/ui/')).map(image=>{image.loading='eager';return image.decode().catch(()=>null)}))`);
        for(const width of [1440,768,390]){
            await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
            await new Promise(resolve=>setTimeout(resolve,60));
            const layout=await evaluate(`({width:innerWidth,scroll:document.documentElement.scrollWidth,heading:document.querySelector('h1,h2')?.textContent.trim(),styles:!!document.querySelector('link[href*="operations.css"]')})`);
            findings.push({page:name,width,...layout});
            if(layout.scroll>width+1){
                console.log('OVERFLOW '+name+' '+width+' -> '+layout.scroll);
                console.log(await evaluate(`JSON.stringify([...document.querySelectorAll('main *')].filter(el=>el.getBoundingClientRect().right>innerWidth+1).slice(0,8).map(el=>({tag:el.tagName,cls:el.className,right:el.getBoundingClientRect().right,width:getComputedStyle(el).minWidth})))`));
            }
            if(['captain-panel.html','captain-programming.html','captain-sites-select.html','captain-tables.html','waiter-dashboard.html','waiter-service-programming.html','waiter-sites-select.html','waiter-waiter-index.html','cashier-cash-index.html'].includes(name) && [1440,390].includes(width)){
                const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
                fs.writeFileSync(path.join(preview,name.replace('.html',`-${width}.png`)),Buffer.from(shot.data,'base64'));
            }
        }
        if(name.startsWith('captain-')){
            assert.equal(await evaluate(`document.body.classList.contains('ops-captain') && !!document.querySelector('link[href*="captain-visual.css"]')`),true);
            const broken=await evaluate(`([...document.images].filter(image=>image.src.includes('/img/ui/') && (!image.complete || !image.naturalWidth))).map(image=>image.src)`);
            assert.deepEqual(broken,[],'Local captain photographs must load');
            assert.equal(await evaluate(`([...document.querySelectorAll('.captain-photo-metrics .ops-metric,.kpi-grid .kpi-card')]).every(card=>!getComputedStyle(card).backgroundImage.includes('url('))`),true,'Statistics must not repeat photographs');
        }else if(name!=='cashier-ticket.html'){
            assert.equal(await evaluate(`!document.body.classList.contains('ops-captain') && !document.querySelector('link[href*="captain-visual.css"]')`),true);
        }
        if(name.startsWith('waiter-')){
            assert.equal(await evaluate(`document.body.classList.contains('ops-waiter') && !!document.querySelector('link[href*="waiter-visual.css"]')`),true);
            assert.deepEqual(await evaluate(`([...document.images].filter(image=>image.src.includes('/img/ui/') && (!image.complete || !image.naturalWidth))).map(image=>image.src)`),[],'Local waiter images must load');
            assert.equal(await evaluate(`([...document.querySelectorAll('.chef-stat,.kpi-card')]).every(card=>!getComputedStyle(card).backgroundImage.includes('url('))`),true,'Waiter statistics must not contain photographs');
        }
        if(name==='waiter-service-programming.html')assert.equal(await evaluate(`new Set([...document.querySelectorAll('.captain-card-photo')].map(image=>image.src)).size`),3);
        if(name==='waiter-sites-select.html')assert.equal(await evaluate(`([...document.querySelectorAll('.site-choice')]).every(card=>card.getBoundingClientRect().height<380 && !getComputedStyle(card).backgroundImage.includes('url('))`),true);
        if(name==='captain-programming.html'){
            assert.equal(await evaluate(`new Set([...document.querySelectorAll('.captain-card-photo')].map(image=>image.src)).size`),3);
        }
        if(name==='captain-sites-select.html'){
            assert.equal(await evaluate(`([...document.querySelectorAll('.site-choice')]).every(card=>card.getBoundingClientRect().height<380 && !getComputedStyle(card).backgroundImage.includes('url('))`),true,'Compact site body without background photograph');
        }
        if(name==='captain-servicios.html'){
            await evaluate(`(()=>{const search=document.querySelector('[data-captain-live-search]');search.value='nonexistent';search.dispatchEvent(new Event('input'))})()`);
            assert.equal(await evaluate(`([...document.querySelectorAll('[data-captain-live-card]')]).every(card=>card.hidden)`),true);
            await evaluate(`(()=>{const search=document.querySelector('[data-captain-live-search]');search.value='';search.dispatchEvent(new Event('input'))})()`);
            assert.equal(await evaluate(`([...document.querySelectorAll('[data-captain-live-card]')]).some(card=>!card.hidden)`),true);
        }
        if(name==='cashier-cash-index.html'){
            const result=await evaluate(`(()=>{const open=document.querySelector('[data-open-payment]');open.click();const modal=document.querySelector('[data-payment-modal]');const received=modal.querySelector('[data-payment-received]');received.value='200';received.dispatchEvent(new Event('input'));return {open:!modal.hidden,total:modal.querySelector('[data-payment-total]').textContent,change:modal.querySelector('[data-payment-change]').textContent,role:modal.querySelector('.payment-dialog').getAttribute('role'),action:modal.querySelector('form').action};})()`);
            assert.equal(result.open,true);assert.equal(result.total,'171.00');assert.equal(result.change,'29.00');assert.equal(result.role,'dialog');assert.ok(result.action.endsWith('/caja/atenciones/1/cobrar'));
            assert.equal(await evaluate(`(()=>{const modal=document.querySelector('[data-payment-modal]');modal.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));return modal.hidden;})()`),true);
        }
        if(name==='captain-tables.html'){
            assert.equal(await evaluate(`(()=>{document.querySelector('[data-edit-table]').click();return !document.getElementById('cz-mesa-modal').hidden && document.getElementById('cz-table-notes').value === 'Observación conservada';})()`),true);
            await evaluate(`document.querySelector('#cz-mesa-modal [data-close]').click()`);
            assert.equal(await evaluate(`(()=>{const input=document.getElementById('cz-search');input.value='nonexistent';input.dispatchEvent(new Event('input'));return [...document.querySelectorAll('[data-ops-floor-card]')].every(card=>card.hidden);})()`),true);
        }
        if(name==='captain-atenciones.html'){
            assert.equal(await evaluate(`(()=>{const filter=document.querySelector('[data-ops-filter]');const input=filter.querySelector('input');input.value='nonexistent';input.dispatchEvent(new Event('input'));return document.querySelector('#ops-atenciones-table tbody tr').hidden;})()`),true);
        }
        if(name==='waiter-waiter-index.html'){
            const cards=await evaluate(`(()=>{const section=document.getElementById('mesas-asignadas');return {table:!!section.querySelector('table'),occupiedHasForm:!!section.querySelector('[data-waiter-occupied="true"] form'),forms:[...section.querySelectorAll('form')].map(form=>({action:form.action,method:form.method,csrf:!!form.querySelector('input[name=_token]'),service:!!form.querySelector('select[name=id_servicio][required]'),mesa:form.querySelector('input[name=id_mesa]').value,min:form.querySelector('input[name=comensales]').min,max:form.querySelector('input[name=comensales]').max,button:form.querySelector('button').textContent.trim()}))};})()`);
            assert.equal(cards.table,false);assert.equal(cards.occupiedHasForm,false);assert.ok(cards.forms.length>0);
            for(const form of cards.forms){assert.ok(form.action.endsWith('/mesero/atenciones'));assert.equal(form.method,'post');assert.equal(form.csrf,true);assert.equal(form.service,true);assert.equal(form.min,'1');assert.ok(Number(form.max)>0);assert.equal(form.button,'Iniciar atención');}
            await evaluate(`document.querySelector('[data-sidebar-toggle]').click()`);
            assert.equal(await evaluate(`document.querySelector('[data-app-shell]').classList.contains('sidebar-open')`),true);
            assert.equal(await evaluate(`document.querySelector('[data-sidebar-toggle]').getAttribute('aria-expanded')`),'true');
            await evaluate(`document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}))`);
            assert.equal(await evaluate(`document.querySelector('[data-app-shell]').classList.contains('sidebar-open')`),false);
        }
    }
    fs.writeFileSync(path.join(preview,'browser-results.json'),JSON.stringify({findings,errors},null,2));
    assert.deepEqual(errors,[],'Browser JavaScript exceptions');
    const overflows=findings.filter(item=>item.scroll>item.width+1);
    assert.deepEqual(overflows,[],'Unexpected page overflow');
    if(!extractCaptainAssets)console.log(`PASS: ${pages.length} pages at 3 widths; payment dialog/change/Escape, table modal, table selection and filters. No JavaScript exceptions.`);
} finally {
    socket?.close();chrome.kill();
}
