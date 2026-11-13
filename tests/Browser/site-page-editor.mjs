// Real Blade exports + production JavaScript; simulated uploads/save, no application data.
import fs from 'node:fs';
import path from 'node:path';
import http from 'node:http';
import { spawn } from 'node:child_process';
import assert from 'node:assert/strict';

const root=process.cwd(), fixtures=path.join(root,'storage/app/private/site-editor-preview');
const server=http.createServer((req,res)=>{
    const pathname=new URL(req.url,'http://localhost').pathname;
    let file=pathname==='/operacion/mi-pagina-web/editor'?path.join(fixtures,'editor.html'):
        pathname.includes('/preview/')?path.join(fixtures,'preview.html'):
        pathname.startsWith('/storage/')?path.join(root,'public/img/site-media-placeholder.svg'):
        path.join(root,'public',pathname);
    if(!file.startsWith(root)||!fs.existsSync(file)||fs.statSync(file).isDirectory()){res.writeHead(404);res.end();return;}
    const types={'.html':'text/html','.js':'text/javascript','.css':'text/css','.svg':'image/svg+xml','.png':'image/png'};
    res.setHeader('Content-Type',types[path.extname(file)]||'application/octet-stream');
    let content=fs.readFileSync(file);
    if(file.endsWith('.html')) content=content.toString().replaceAll('http://localhost','').replace(/https:\/\/images\.unsplash\.com\/[^"\s]+/g,'/img/site-media-placeholder.svg');
    res.end(content);
});
await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
const address=`http://127.0.0.1:${server.address().port}`;
const chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',[
    '--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--remote-debugging-port=0',
    '--user-data-dir='+path.join(fixtures,'chrome-profile'),'about:blank'
],{windowsHide:true,stdio:['ignore','ignore','pipe']});
let socket;
try{
    const endpoint=await new Promise((resolve,reject)=>{
        let output='';const timer=setTimeout(()=>reject(Error('Chrome startup timed out')),15000);
        chrome.stderr.on('data',chunk=>{output+=chunk;const match=output.match(/DevTools listening on (ws:\/\/[^\s]+)/);if(match){clearTimeout(timer);resolve(match[1]);}});
        chrome.on('error',reject);
    });
    const targets=await(await fetch(`http://127.0.0.1:${new URL(endpoint).port}/json/list`)).json();
    socket=new WebSocket(targets.find(t=>t.type==='page').webSocketDebuggerUrl);
    await new Promise(resolve=>socket.addEventListener('open',resolve,{once:true}));
    let sequence=0;const pending=new Map(),errors=[];
    socket.addEventListener('message',event=>{const m=JSON.parse(event.data);if(m.id){const task=pending.get(m.id);pending.delete(m.id);m.error?task.reject(Error(JSON.stringify(m.error))):task.resolve(m.result);}if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.exception?.description||m.params.exceptionDetails.text);});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++sequence;const timer=setTimeout(()=>{pending.delete(id);reject(Error('DevTools timed out: '+method));},15000);pending.set(id,{resolve:value=>{clearTimeout(timer);resolve(value);},reject:error=>{clearTimeout(timer);reject(error);}});socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const out=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(out.exceptionDetails)throw Error(out.exceptionDetails.exception?.description||out.exceptionDetails.text);return out.result.value;};
    const waitFor=async expression=>{for(let i=0;i<100;i++){if(await evaluate(expression))return;await new Promise(r=>setTimeout(r,50));}throw Error('Timed out: '+expression);};
    await send('Runtime.enable');await send('Page.enable');
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await send('Page.navigate',{url:address+'/operacion/mi-pagina-web/editor'});
    await waitFor(`document.querySelectorAll('#section-list button').length===7 && document.querySelector('#site-preview').contentDocument?.querySelector('#inicio')`);
    await evaluate(`window.queue=[];window.savedPayload=null;window.confirm=()=>true;window.alert=message=>{throw Error(message)};
        window.fetch=(url,options)=>new Promise(resolve=>{
            if(String(url).includes('/media'))queue.push(resolve);
            else{savedPayload=JSON.parse(options.body);resolve(new Response(JSON.stringify({ok:true}),{status:200}));}
        });
        window.choose=(index,name,type='image/png')=>{const bytes=Uint8Array.from(atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1kAAAAASUVORK5CYII='),c=>c.charCodeAt(0));const dt=new DataTransfer();dt.items.add(new File([bytes],name,{type}));const input=document.querySelectorAll('input[type=file]')[index];input.files=dt.files;input.dispatchEvent(new Event('change'));};
        window.finish=(index,path)=>queue[index](new Response(JSON.stringify({media:[{path,type:'image',mime:'image/png'}]}),{status:201}));`);
    await evaluate(`choose(0,'primera.png')`);
    const first=await evaluate(`document.querySelector('#site-preview').contentDocument.querySelector('.nu-hero-video').getAttribute('src')`);
    assert.ok(first.startsWith('blob:'),'Immediate local preview before upload');
    assert.equal(await evaluate(`document.querySelector('#save-page').disabled`),true);
    await evaluate(`choose(0,'segunda.png')`);
    const second=await evaluate(`document.querySelector('#site-preview').contentDocument.querySelector('.nu-hero-video').getAttribute('src')`);
    assert.notEqual(first,second,'Replacement changes preview');
    await evaluate(`finish(1,'site-pages/1/media/second.png');finish(0,'site-pages/1/media/stale.png')`);
    await waitFor(`!document.querySelector('#save-page').disabled`);
    await evaluate(`document.querySelector('#save-page').click()`);
    await waitFor(`document.querySelector('#save-state').textContent==='Borrador guardado'`);
    assert.equal(await evaluate(`savedPayload.content.hero_image.path`),'site-pages/1/media/second.png');
    assert.ok(!JSON.stringify(await evaluate('savedPayload')).includes('blob:'),'No temporary URLs saved');
    await evaluate(`choose(0,'cancelar.png');document.querySelector('#discard-page').click();finish(2,'site-pages/1/media/cancelled.png')`);
    await waitFor(`document.querySelector('#site-preview').contentDocument?.querySelector('.nu-hero-video')?.getAttribute('src')?.includes('second.png')`);
    // Every configurable media slot: cover, poster, vision, six cards, experience.
    for(const [section,count,selector] of [[0,2,'.nu-hero-video'],[1,1,'#vision img'],[2,6,'.nu-solution-photo img'],[3,1,'#experiencia img']]){
        await evaluate(`document.querySelectorAll('#section-list button')[${section}].click()`);
        assert.equal(await evaluate(`document.querySelectorAll('input[type=file]').length`),count);
        for(let i=0;i<count;i++){
            await evaluate(`choose(${i},'campo-${section}-${i}.gif','image/gif')`);
            if(!(section===0&&i===1)){
                const found=await evaluate(`Array.from(document.querySelector('#site-preview').contentDocument.querySelectorAll(${JSON.stringify(selector)})).some(e=>e.getAttribute('src')?.startsWith('blob:'))`);
                assert.ok(found,`Preview slot ${section}:${i}`);
            }
            await evaluate(`finish(queue.length-1,'site-pages/1/media/field-${section}-${i}.gif')`);
        }
    }
    await waitFor(`!document.querySelector('#save-page').disabled`);
    await evaluate(`document.querySelector('#save-page').click()`);
    await waitFor(`document.querySelector('#save-state').textContent==='Borrador guardado'`);
    assert.equal(await evaluate(`savedPayload.content.solutions.every(row=>row[3].path.endsWith('.gif'))`),true);
    // Failed upload stays local, reports error and cannot be saved accidentally.
    await evaluate(`choose(0,'fallo.png');queue[queue.length-1](new Response(JSON.stringify({message:'Fallo de prueba'}),{status:422}))`);
    await waitFor(`document.querySelector('.nu-media-error')?.textContent.includes('Fallo de prueba')`);
    assert.equal(await evaluate(`document.querySelector('#save-page').disabled`),true);
    await evaluate(`document.querySelector('#discard-page').click()`);
    // Upload completion must refresh a newly mounted section panel as well.
    await evaluate(`choose(0,'cambiar-seccion.png');document.querySelectorAll('#section-list button')[0].click();document.querySelectorAll('#section-list button')[3].click();finish(queue.length-1,'site-pages/1/media/section.png')`);
    await waitFor(`document.querySelector('.nu-media-pending')?.textContent==='Pendiente de guardar'`);
    await evaluate(`document.querySelector('#discard-page').click();window.scrollTo(0,0)`);
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
    await evaluate(`document.querySelector('[data-device=mobile]').click()`);
    assert.equal(await evaluate(`document.querySelector('#site-preview').getBoundingClientRect().width<=390`),true);
    assert.equal(await evaluate(`document.querySelector('#site-preview').contentWindow.innerWidth`),390);
    assert.equal(await evaluate(`document.documentElement.scrollWidth<=window.innerWidth`),true,'No mobile horizontal overflow');
    assert.equal(await evaluate(`getComputedStyle(document.querySelector('#web-editor')).gridTemplateColumns.split(' ').length`),1);
    fs.writeFileSync(path.join(fixtures,'mobile.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await evaluate(`document.querySelector('[data-device=desktop]').click();document.querySelectorAll('#section-list button')[0].click();window.scrollTo(0,0)`);
    assert.equal(await evaluate(`document.querySelector('#site-preview').contentWindow.innerWidth`),1280);
    const ratio=await evaluate(`document.querySelector('.nu-editor-center').getBoundingClientRect().width/document.querySelector('#web-editor').getBoundingClientRect().width`);
    assert.ok(ratio>.6&&ratio<.71,'Preview uses approximately two thirds of workspace');
    fs.writeFileSync(path.join(fixtures,'desktop.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
    assert.deepEqual(errors,[]);
    console.log('PASS: immediate preview, replacement, upload races, cancel, ten media fields, save without blob URLs, failed upload, mobile layout; real Blade + JS.');
}finally{socket?.close();chrome.kill();server.closeAllConnections();await new Promise(resolve=>server.close(resolve));}
