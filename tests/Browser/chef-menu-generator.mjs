// One browser pass: six compositions, live editor, image independence and print flow.
import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {pathToFileURL} from 'node:url';
const directory=path.resolve('storage/app/private/chef-generator-preview');
const finalHtml=fs.readFileSync(path.join(directory,'final.html'),'utf8');
const pdf=fs.readFileSync(path.join(directory,'menu.pdf')).toString('base64');
const chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--allow-file-access-from-files','--remote-debugging-port=0','--user-data-dir='+path.join(directory,'browser-profile'),'about:blank'],{windowsHide:true,stdio:['ignore','ignore','pipe']});
let socket;
try {
    const endpoint=await new Promise((resolve,reject)=>{let output='';const timeout=setTimeout(()=>reject(Error('Chrome startup timeout')),15000);chrome.stderr.on('data',data=>{output+=data;const match=output.match(/DevTools listening on (ws:\/\/[^\s]+)/);if(match){clearTimeout(timeout);resolve(match[1]);}});chrome.once('error',reject);});
    const targets=await(await fetch('http://127.0.0.1:'+new URL(endpoint).port+'/json/list')).json();socket=new WebSocket(targets.find(target=>target.type==='page').webSocketDebuggerUrl);await new Promise(resolve=>socket.addEventListener('open',resolve,{once:true}));
    let sequence=0;const pending=new Map(),errors=[];
    socket.addEventListener('message',event=>{const message=JSON.parse(event.data);if(message.id){const task=pending.get(message.id);pending.delete(message.id);message.error?task.reject(Error(JSON.stringify(message.error))):task.resolve(message.result);}if(message.method==='Runtime.exceptionThrown')errors.push(message.params.exceptionDetails.exception?.description||message.params.exceptionDetails.text);});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++sequence,timeout=setTimeout(()=>reject(Error(method+' timeout')),15000);pending.set(id,{resolve:value=>{clearTimeout(timeout);resolve(value)},reject});socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const response=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(response.exceptionDetails)throw Error(response.exceptionDetails.exception?.description||response.exceptionDetails.text);return response.result.value;};
    await send('Page.enable');await send('Runtime.enable');
    await send('Page.addScriptToEvaluateOnNewDocument',{source:`window.fetch=async(url,options)=>{
        if(String(url).includes('/pdf'))return new Response(Uint8Array.from(atob(${JSON.stringify(pdf)}),c=>c.charCodeAt(0)),{headers:{'Content-Type':'application/pdf'}});
        if(String(url).includes('/imprimir')){const doc=new DOMParser().parseFromString(${JSON.stringify(finalHtml)},'text/html');doc.querySelectorAll('.gm-logo,.gm-background').forEach(i=>{i.removeAttribute('src');i.hidden=true;});return new Response(doc.documentElement.outerHTML,{headers:{'Content-Type':'text/html'}});}
        if(String(url).includes('/vista-previa')){window.__sentContent=true;const templates={};document.querySelectorAll('[data-gm-cache] template').forEach(node=>{(templates[node.dataset.template]??={})[node.dataset.kind]=node.innerHTML;});return new Response(JSON.stringify({templates,count:2,finalCount:1,pending:[]}),{headers:{'Content-Type':'application/json'}});}
        return new Response(JSON.stringify({items:[],unread:0}),{headers:{'Content-Type':'application/json'}});
    };`});
    await send('Emulation.setDeviceMetricsOverride',{width:1500,height:1000,deviceScaleFactor:1,mobile:false});
    await send('Page.navigate',{url:pathToFileURL(path.join(directory,'editor.html')).href});
    for(let n=0;n<100;n++){if(await evaluate("document.readyState==='complete' && document.querySelector('[data-gm-preview]')?.style.zoom!==''"))break;await new Promise(resolve=>setTimeout(resolve,60));}
    assert.equal(await evaluate("document.querySelectorAll('.gm-template-option').length"),6);
    if(process.argv.includes('--print-empty-images')) {
        await evaluate("document.querySelector('[data-gm-print]').click()");
        for(let n=0;n<100;n++){if(await evaluate("document.querySelector('[data-gm-feedback]').textContent.includes('Documento final listo')"))break;await new Promise(resolve=>setTimeout(resolve,50));}
        assert.ok(await evaluate("document.querySelector('[data-gm-feedback]').textContent.includes('Documento final listo')"),await evaluate("document.querySelector('[data-gm-feedback]').textContent"));
        assert.deepEqual(errors,[]);console.log('OK: impresión lista después de cargar las fotografías, incluso sin logotipo ni fondo guardados.');
    } else {
    const findings=[];
    for(const template of ['elegante','corporativa','gastronomica','contemporanea','minimalista','revista']) {
        await evaluate(`(()=>{const input=document.querySelector('[name=template][value=${template}]');input.checked=true;input.dispatchEvent(new Event('change',{bubbles:true}));})()`);
        const result=await evaluate(`(()=>{const d=document.querySelector('[data-gm-preview] [data-gm-document]');return {template:d.dataset.template,header:d.querySelector('header').className,background:getComputedStyle(d).backgroundColor,overflow:d.scrollWidth>d.offsetWidth+1,dishes:d.querySelectorAll('.gm-dish').length,logo:d.querySelector('[data-gm-logo]').src};})()`);
        assert.equal(result.template,template);assert.equal(result.overflow,false,JSON.stringify(result));assert.ok(result.dishes>0);findings.push(result);
    }
    assert.equal(new Set(findings.map(f=>f.header)).size,6);
    await evaluate("(()=>{const f=document.querySelector('[data-gm-form]');f.elements.titulo.value='Nuestra selección gastronómica';f.elements.titulo.dispatchEvent(new Event('input',{bubbles:true}));})()");
    assert.equal(await evaluate("document.querySelector('[data-gm-preview] [data-gm-title]').textContent"),'Nuestra selección gastronómica');
    await send('DOM.enable');const dom=await send('DOM.getDocument');const input=await send('DOM.querySelector',{nodeId:dom.root.nodeId,selector:'input[name=design_background]'});
    await send('DOM.setFileInputFiles',{nodeId:input.nodeId,files:[path.resolve('public/img/ui/service-dinner.jpg')]});
    for(let n=0;n<60;n++){if(await evaluate("document.querySelector('[data-gm-preview] [data-gm-background]').src.startsWith('blob:')"))break;await new Promise(resolve=>setTimeout(resolve,50));}
    const blob=await evaluate("document.querySelector('[data-gm-preview] [data-gm-background]').src");assert.ok(blob.startsWith('blob:'));
    await evaluate("document.querySelector('[name=template][value=elegante]').click()");
    assert.equal(await evaluate("document.querySelector('[data-gm-preview] [data-gm-background]').src"),blob);
    assert.equal(await evaluate("document.querySelector('[data-gm-preview] [data-gm-title]').textContent"),'Nuestra selección gastronómica');
    await evaluate("document.querySelector('input[type=checkbox][name=fotos]').click()");
    assert.equal(await evaluate("getComputedStyle(document.querySelector('[data-gm-preview] .gm-dish-photo')).display"),'none');
    assert.notEqual(await evaluate("getComputedStyle(document.querySelector('[data-gm-preview] [data-gm-background]')).display"),'none');
    assert.notEqual(await evaluate("getComputedStyle(document.querySelector('[data-gm-preview] [data-gm-logo]')).display"),'none');
    await evaluate("document.querySelector('input[type=checkbox][name=fotos]').click()");
    await send('Page.setDownloadBehavior',{behavior:'allow',downloadPath:directory});
    await evaluate("document.querySelector('[data-gm-pdf]').click()");
    const download=path.join(directory,'menu-nutriliado.pdf');
    for(let n=0;n<80;n++){if(fs.existsSync(download))break;await new Promise(resolve=>setTimeout(resolve,50));}
    assert.ok(fs.existsSync(download));assert.equal(fs.readFileSync(download).subarray(0,5).toString(),'%PDF-');
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:false});
    await new Promise(resolve=>setTimeout(resolve,120));
    const mobile=await evaluate('({width:innerWidth,scroll:document.documentElement.scrollWidth})');assert.ok(mobile.scroll<=mobile.width+1,JSON.stringify(mobile));
    // One gallery artifact for visual review of all six real template structures.
    const gallery=await evaluate(`(()=>{const colors={elegante:['#171813','#c3a36a'],corporativa:['#17375e','#b49a70'],gastronomica:['#465338','#69734b'],contemporanea:['#222224','#ed4b45'],minimalista:['#303d34','#8b978b'],revista:['#342d27','#b45c36']};return [...document.querySelectorAll('[data-gm-cache] template[data-kind=final]')].map(t=>{const d=t.content.firstElementChild.cloneNode(true);d.classList.remove('gm-orientation-horizontal');d.classList.add('gm-orientation-vertical');d.style.setProperty('--gm-primary',colors[t.dataset.template][0]);d.style.setProperty('--gm-secondary',colors[t.dataset.template][1]);return '<section class="sample"><h2>'+t.dataset.template+'</h2><div class="scaled">'+d.outerHTML+'</div></section>';}).join('');})()`);
    const cssUrl=pathToFileURL(path.resolve('public/css/chef-menu-document.css')).href;
    const galleryHtml=`<!doctype html><meta charset="utf-8"><link rel="stylesheet" href="${cssUrl}"><style>body{margin:0;padding:22px;background:#e8ecef;font-family:Arial}main{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;align-items:start}.sample{padding:12px;border-radius:9px;background:#fff;overflow:hidden}.sample>h2{margin:0 0 10px;font-size:13px;text-transform:uppercase;letter-spacing:2px;color:#44596e}.scaled{zoom:.65;width:816px;margin:auto}.scaled .gm-document{max-width:none}</style><main>${gallery}</main>`;
    fs.writeFileSync(path.join(directory,'gallery.html'),galleryHtml);
    await send('Emulation.setDeviceMetricsOverride',{width:1800,height:1750,deviceScaleFactor:1,mobile:false});
    await send('Page.navigate',{url:pathToFileURL(path.join(directory,'gallery.html')).href});
    for(let n=0;n<100;n++){if(await evaluate("document.readyState==='complete' && [...document.images].every(i=>i.complete)"))break;await new Promise(resolve=>setTimeout(resolve,50));}
    const capture=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync(path.join(directory,'gallery.png'),Buffer.from(capture.data,'base64'));
    // Stress only the print flow with an isolated long-document fixture.
    await send('Page.navigate',{url:pathToFileURL(path.join(directory,'final.html')).href});
    for(let n=0;n<80;n++){if(await evaluate("document.readyState==='complete' && [...document.images].every(i=>i.complete)"))break;await new Promise(resolve=>setTimeout(resolve,50));}
    await evaluate(`(()=>{const doc=document.querySelector('.gm-document');doc.classList.remove('gm-period-quincenal');doc.classList.add('gm-period-mensual');const week=doc.querySelector('.gm-week');const dish=week.querySelector('.gm-dish');for(let n=0;n<15;n++){const row=dish.cloneNode(true);row.querySelector('h5').textContent='Platillo registrado de prueba '+(n+1);week.querySelector('.gm-category').append(row);}for(let n=1;n<4;n++){const next=week.cloneNode(true);next.querySelector('.gm-week-heading').textContent='Semana de prueba '+(n+1);week.parentElement.append(next);}})()`);
    await send('Emulation.setEmulatedMedia',{media:'print'});
    const printed=await send('Page.printToPDF',{printBackground:true,preferCSSPageSize:true});
    const longPdf=Buffer.from(printed.data,'base64');fs.writeFileSync(path.join(directory,'monthly-print.pdf'),longPdf);
    const pages=(longPdf.toString('latin1').match(/\/Type\s*\/Page\b/g)||[]).length;assert.ok(pages>=4,'Long monthly document must paginate, got '+pages);
    assert.deepEqual(errors,[]);
    fs.writeFileSync(path.join(directory,'review.json'),JSON.stringify({templates:findings,mobile,monthlyPages:pages},null,2));
    console.log('OK: seis composiciones, editor inmediato, imágenes independientes, PDF descargable, móvil y documento mensual de '+pages+' páginas.');
    }
} finally{socket?.close();chrome.kill();}
