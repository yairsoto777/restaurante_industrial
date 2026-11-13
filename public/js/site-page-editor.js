(() => {
    'use strict';
    const root = document.querySelector('#web-editor');
    if (!root) return;
    const $ = s => document.querySelector(s);
    const iframe = $('#site-preview');
    const raw = JSON.parse($('#page-data').textContent || '{}');
    const data = structuredClone(raw);
    delete data.saved_at;
    const fields = $('#property-panel');
    const status = $('#save-state');
    const mediaNames = JSON.parse($('#media-data')?.textContent || '{}');
    const mediaPainters = new Map();
    let saved = structuredClone(data);
    let revision = 0;
    let saving = false;
    const uploads = new Map(), localMedia = new Map(), mediaErrors = new Map();
    let initialPageState = $('#page-state').textContent;
    function releaseLocal(key) {
        const local = localMedia.get(key);
        if (local) URL.revokeObjectURL(local.url);
        localMedia.delete(key);
    }
    function refreshActions() {
        $('#save-page').disabled = saving || uploads.size > 0 || mediaErrors.size > 0;
        $('#discard-page').disabled = saving || (!changed && !uploads.size && !mediaErrors.size);
        $('#page-state').textContent = changed ? 'Cambios sin guardar' : initialPageState;
        $('#page-state').dataset.tone = changed ? 'pending' : '';
    }
    const sections = [
        {name:'Portada',id:'inicio',items:[
                ['hero_eyebrow','Texto superior'],['hero_title','Título'],['hero_title_emphasis','Título destacado'],['hero_description','Descripción','textarea'],['hero_button_primary','Botón principal'],['hero_button_secondary','Botón secundario'],['hero_points','Tres etiquetas','list',3],['hero_decoration','Texto decorativo'],['hero_scroll','Texto de desplazamiento'],['hero_image','Imagen/GIF/video de portada','media'],['hero_poster','Imagen de respaldo del video','media']
            ]},
        {name:'Visión',id:'vision',items:[
                ['vision_eyebrow','Texto superior'],['vision_title','Título'],['vision_title_emphasis','Título destacado'],['vision_paragraphs','Párrafos','list',2],['vision_list','Lista','list',3],['vision_image','Imagen/GIF','media'],['vision_image_alt','Descripción accesible'],['vision_caption','Pie de imagen']
            ]},
        {name:'Soluciones',id:'soluciones',items:[
                ['solutions_eyebrow','Texto superior'],['solutions_title','Título'],['solutions_title_emphasis','Título destacado'],['solutions_description','Descripción','textarea'],['solutions','Seis tarjetas','cards',6,'image']
            ]},
        {name:'Experiencia',id:'experiencia',items:[
                ['experience_eyebrow','Texto superior'],['experience_title','Título'],['experience_title_emphasis','Título destacado'],['experience_description','Descripción','textarea'],['experience_steps','Tres pasos','triples',3],['experience_image','Imagen/GIF','media'],['experience_image_alt','Descripción accesible'],['experience_caption','Pie de imagen']
            ]},
        {name:'Beneficios',id:'beneficios',items:[
                ['benefits_eyebrow','Texto superior'],['benefits_title','Título'],['benefits_title_emphasis','Título destacado'],['benefits_description','Descripción','textarea'],['benefit_badge_title','Insignia'],['benefit_badge_count','Cantidad'],['benefit_badge_description','Texto de insignia','textarea'],['benefits','Seis beneficios','triples',6],['benefit_stats','Tres indicadores','pairs',3]
            ]},
        {name:'Contacto',id:'contacto',items:[
                ['contact_eyebrow','Texto superior'],['contact_title','Título'],['contact_title_emphasis','Título destacado'],['contact_description','Descripción','textarea'],['contact_features','Tres características','list',3],['contact_form_eyebrow','Título pequeño del formulario'],['contact_form_title','Título del formulario'],['contact_submit','Texto del botón de envío'],['contact_note','Nota del formulario']
            ]},
        {name:'Colores',id:null,items:[
                ['theme.background','Fondo','color'],['theme.panel','Tarjetas','color'],['theme.primary','Color principal','color'],['theme.light','Acento','color'],['theme.text','Texto','color']
            ]}
    ];
    const get = k => k.split('.').reduce((v,p)=>v?.[p],data);
    const put = (k,v) => {const parts=k.split('.');let obj=data;for(let i=0;i<parts.length-1;i++){const part=parts[i];if(obj[part]==null)obj[part]=/^\d+$/.test(parts[i+1])?[]:{};obj=obj[part];}obj[parts.at(-1)]=v;dirty();updatePreview();};
    const mark = s => {status.textContent=s;status.dataset.tone=/Error|No se pudo/.test(s)?'error':/guardado/.test(s)?'success':/Cambios|Subiendo|Guardando/.test(s)?'pending':'';};
    let changed=false;
    function dirty(){changed=true;revision++;mark('Cambios sin guardar');refreshActions();}
    function elem(tag,attrs={},txt=''){const e=document.createElement(tag);Object.entries(attrs).forEach(([k,v])=>e.setAttribute(k,v));if(txt)e.textContent=txt;return e;}
    function addField(parent,label,key,type='text',fallback=''){
        const wrap=elem('label');wrap.append(document.createTextNode(label));
        const val=get(key)??fallback;
        const input=elem(type==='textarea'?'textarea':'input',type==='textarea'?{}:{type:type==='color'?'color':'text'});
        input.value=(type==='color' && !/^#[0-9a-f]{6}$/i.test(String(val))) ? ({'theme.background':'#0b0d10','theme.panel':'#12171d','theme.primary':'#2384ff','theme.light':'#8ac5ff','theme.text':'#f8fafc'}[key] || '#2384ff') : val;
        input.addEventListener('input',()=>put(key,input.value));wrap.append(input);parent.append(wrap);
    }
    function addMedia(parent,label,key){
        const wrap=elem('div',{class:'nu-media-field'});
        const caption=elem('label',{},label);
        const posterOnly=key==='hero_poster';
        const input=elem('input',{type:'file',accept:'image/jpeg,image/png,image/webp,image/gif'+(posterOnly?'':',video/mp4,video/webm')});
        caption.append(input);wrap.append(caption);
        const thumbnail=elem('div',{class:'nu-media-preview'});
        const filename=elem('p',{class:'nu-media-name'});
        const feedback=elem('small',{'aria-live':'polite'});
        const undo=elem('button',{type:'button'},'Restaurar archivo guardado');
        undo.addEventListener('click',()=>{
            uploads.delete(key);mediaErrors.delete(key);releaseLocal(key);
            const original=key.split('.').reduce((v,p)=>v?.[p],saved);
            put(key,structuredClone(original??null));paint();refreshActions();
            iframe.src=iframe.src;
        });
        function paint(){
            const value=get(key),local=localMedia.get(key),url=local?.url||mediaURL(value);
            thumbnail.replaceChildren();
            if(url){
                const video=(local?.type||value?.type)==='video'||String(value?.mime||'').startsWith('video/');
                const visual=elem(video?'video':'img',{src:url});
                if(video){visual.controls=true;visual.muted=true;visual.preload='metadata';}else visual.alt=label;
                visual.addEventListener('error',()=>{thumbnail.replaceChildren(elem('small',{},'Archivo no disponible. Selecciona otro archivo.'));});
                thumbnail.append(visual);
            }else thumbnail.append(elem('small',{},'Imagen predeterminada de la página'));
            filename.textContent=local?.name||mediaNames[value?.path]||value?.name||String(value?.path||'').split('/').pop()||'Sin archivo personalizado';
            const pending=uploads.has(key);
            const modified=JSON.stringify(value)!==JSON.stringify(key.split('.').reduce((v,p)=>v?.[p],saved));
            feedback.textContent=mediaErrors.get(key)||(pending?'Subiendo archivo…':modified?'Pendiente de guardar':'JPG, PNG, WebP, GIF'+(posterOnly?'':', MP4 o WebM')+' · Hasta 50 MB');
            feedback.className=mediaErrors.has(key)?'nu-media-error':pending||modified?'nu-media-pending':'';
            undo.hidden=!modified&&!pending&&!mediaErrors.has(key);
        }
        input.addEventListener('click',()=>{input.value='';});
        input.addEventListener('change',async()=>{
            const file=input.files?.[0];if(!file)return;
            if(!['image/jpeg','image/png','image/webp','image/gif',...(!posterOnly?['video/mp4','video/webm']:[])].includes(file.type)||file.size>51200*1024){
                feedback.textContent='Selecciona un formato permitido de hasta 50 MB.';feedback.className='nu-media-error';input.value='';return;
            }
            const token=Symbol(key);uploads.set(key,token);mediaErrors.delete(key);releaseLocal(key);
            localMedia.set(key,{url:URL.createObjectURL(file),name:file.name,type:file.type.startsWith('video/')?'video':'image'});
            dirty();paint();updatePreview();
            const body=new FormData();body.append('files[]',file);
            mark('Subiendo archivo…');
            try{
                const res=await fetch(root.dataset.uploadUrl,{method:'POST',credentials:'same-origin',headers:{'X-CSRF-TOKEN':root.dataset.csrf,'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},body});
                // Leer una sola vez la respuesta; los errores HTTP y JSON tienen el mismo diagnóstico.
                const responseText=await res.text();
                let out;
                try {
                    out=JSON.parse(responseText);
                } catch {
                    // No mostrar la respuesta completa: podría contener información sensible.
                    console.error('Respuesta no JSON de media.upload', {
                        status:res.status,
                        contentType:res.headers.get('content-type'),
                        responsePreview:responseText.slice(0,500)
                    });
                    const kind=responseText.trimStart().startsWith('<')?'HTML':'texto no JSON';
                    throw Error(`La ruta de subida devolvió ${kind} (HTTP ${res.status}). Abre F12 > Red > petición POST /operacion/mi-pagina-web/media > Respuesta y revisa storage/logs/laravel.log`);
                }
                if(!res.ok || out?.ok===false){
                    const messages=Object.values(out?.errors||{}).flat();
                    throw Error(messages.length?messages.join(' '):(out?.message||`Error HTTP ${res.status}`));
                }
                const media=Array.isArray(out?.media) ? out.media[0] : null;
                if(!media || typeof media!=='object')
                    throw Error('La respuesta JSON no contiene media[0]. Revisa el método uploadMedia.');
                const path=media.path??media.file_path;
                if(typeof path!=='string'||!path)
                    throw Error('La respuesta no contiene una ruta multimedia válida (path)');
                const mime=media.mime||file.type||'application/octet-stream';
                const type=media.type||(mime.startsWith('video/')?'video':mime==='image/gif'?'gif':'image');
                if(uploads.get(key)!==token)return;
                put(key,{path:String(path),type,mime,name:file.name});
                mark('Cambios sin guardar');
            }catch(err){if(uploads.get(key)===token){mediaErrors.set(key,`No se pudo cargar: ${err.message}`);mark('Error al subir archivo');}}
            finally{if(uploads.get(key)===token){uploads.delete(key);mediaPainters.get(key)?.();refreshActions();}input.value='';}
        });
        wrap.append(thumbnail,filename,feedback,undo);parent.append(wrap);mediaPainters.set(key,paint);paint();
    }
    function group(parent,title){parent.append(elem('div',{class:'group-label'},title));}
    function render(index){
        mediaPainters.clear();
        const s=sections[index];$('#properties-title').textContent=s.name;fields.replaceChildren();
        for(const item of s.items){const [key,label,type='text',n]=item;
            if(type==='media'){addMedia(fields,label,key);continue;}
            if(type==='list'){
                group(fields,label);for(let i=0;i<n;i++)addField(fields,`Elemento ${i+1}`,`${key}.${i}`);continue;
            }
            if(['cards','triples','pairs'].includes(type)){
                group(fields,label);
                for(let i=0;i<n;i++){
                    group(fields,`${label} ${i+1}`);
                    const labels=type==='pairs'?['Título','Descripción']:['Número','Título','Descripción'];
                    labels.forEach((name,j)=>addField(fields,name,`${key}.${i}.${j}`,j===2?'textarea':'text'));
                    if(type==='cards')addMedia(fields,'Imagen / GIF',`${key}.${i}.3`);
                }continue;
            }
            addField(fields,label,key,type,type==='color'?'#2384ff':'');
        }
    }
    const nav=$('#section-list');
    sections.forEach((s,i)=>{const b=elem('button',{type:'button'},s.name);b.addEventListener('click',()=>{nav.querySelectorAll('button').forEach(x=>x.classList.remove('active'));b.classList.add('active');render(i);if(s.id){try{iframe.contentDocument?.getElementById(s.id)?.scrollIntoView({block:'start'});}catch{}}});nav.append(b);});
    nav.firstElementChild.click();
    const map = [
        ['hero_eyebrow','#inicio .nu-eyebrow'],['hero_title','#inicio h1'],['hero_title_emphasis','#inicio h1 em'],['hero_description','#inicio .nu-hero-copy > p'],['hero_button_primary','#inicio .nu-button-blue'],['hero_button_secondary','#inicio .nu-button-ghost'],['hero_decoration','#inicio .nu-hero-decoration'],['hero_scroll','#inicio .nu-scroll-down'],
        ['vision_eyebrow','#vision .nu-eyebrow'],['vision_title','#vision h2'],['vision_title_emphasis','#vision h2 em'],['vision_caption','#vision figcaption'],
        ['solutions_eyebrow','#soluciones .nu-eyebrow'],['solutions_title','#soluciones h2'],['solutions_title_emphasis','#soluciones h2 em'],['solutions_description','#soluciones .nu-section-heading > p'],
        ['experience_eyebrow','#experiencia .nu-eyebrow'],['experience_title','#experiencia h2'],['experience_title_emphasis','#experiencia h2 em'],['experience_description','#experiencia .nu-experience-grid > div > p'],['experience_caption','#experiencia figcaption'],
        ['benefits_eyebrow','#beneficios .nu-eyebrow'],['benefits_title','#beneficios h2'],['benefits_title_emphasis','#beneficios h2 em'],['benefits_description','#beneficios .nu-benefits-copy > p'],['benefit_badge_title','#beneficios .nu-benefits-badge span'],['benefit_badge_count','#beneficios .nu-benefits-badge strong'],['benefit_badge_description','#beneficios .nu-benefits-badge small'],
        ['contact_eyebrow','#contacto .nu-contact-aside .nu-eyebrow'],['contact_title','#contacto h2'],['contact_title_emphasis','#contacto h2 em'],['contact_description','#contacto .nu-contact-aside > p'],['contact_form_eyebrow','#contacto .nu-contact-card .nu-eyebrow'],['contact_form_title','#contacto .nu-contact-card h3'],['contact_submit','#contacto [data-contact-submit]'],['contact_note','#contacto .nu-form-note']
    ];
    function setText(doc,selector,value){if(value===undefined||value===null)return;const e=doc.querySelector(selector);if(!e)return;
        // Preserve <em> and arrow spans for split titles and action buttons.
        if(e.querySelector('em')){const text=Array.from(e.childNodes).find(n=>n.nodeType===3);if(text)text.textContent=value+' ';return;}
        if(e.querySelector('span[aria-hidden]')){const text=Array.from(e.childNodes).find(n=>n.nodeType===3);if(text)text.textContent=value+' ';return;}
        e.textContent=String(value);
    }
    function mediaURL(m){
        if(!m)return null;
        const value=typeof m==='string'?{path:m}:m;
        const candidate=value.path||value.url;
        if(typeof candidate!=='string'||!candidate)return null;
        if(candidate.startsWith('photo-'))return `https://images.unsplash.com/${candidate}?auto=format&fit=crop&w=850&q=82`;
        if(/^https?:\/\//i.test(candidate))return candidate;
        // Evita duplicar /storage/ cuando Laravel ya entrega una URL pública.
        if(candidate.startsWith('/storage/'))return `${root.dataset.storageUrl}/${candidate.slice(9)}`;
        if(candidate.startsWith('/'))return candidate;
        const path=candidate.replace(/^public\//,'').replace(/^storage\//,'').replace(/^\/+/, '');
        return `${root.dataset.storageUrl}/${path}`;
    }
    const previewURL=key=>localMedia.get(key)?.url||mediaURL(get(key));
    function replaceMedia(doc,key,selector){
        const url=previewURL(key);let visual=doc.querySelector(selector);if(!url||!visual)return;
        const media=get(key),video=(localMedia.get(key)?.type||media?.type)==='video'||String(media?.mime||'').startsWith('video/');
        if(visual.tagName!==(video?'VIDEO':'IMG')){
            const replacement=doc.createElement(video?'video':'img');replacement.className=visual.className;
            replacement.style.cssText=visual.style.cssText;
            replacement.setAttribute('data-editor-media',key);visual.replaceWith(replacement);visual=replacement;
        }
        visual.dataset.editorMedia=key;
        if(video){visual.autoplay=true;visual.muted=true;visual.loop=true;visual.playsInline=true;visual.controls=true;}
        if(visual.getAttribute('src')!==url){visual.src=url;if(video)visual.play().catch(()=>{});}
    }
    function updatePreview(){let doc;try{doc=iframe.contentDocument;}catch{return;}if(!doc||!doc.body)return;
        map.forEach(([key,sel])=>setText(doc,sel,get(key)));
        const textLists=[['hero_points','#inicio .nu-hero-points span'],['vision_paragraphs','#vision .nu-vision-content > p'],['vision_list','#vision .nu-vision-list li'],['contact_features','#contacto .nu-contact-features span']];
        for(const [key,selector] of textLists){const vals=get(key);if(!Array.isArray(vals))continue;doc.querySelectorAll(selector).forEach((e,i)=>{if(vals[i]!==undefined)e.textContent=vals[i];});}
        const rows=[['solutions','#soluciones .nu-solution-card', ['.nu-solution-number','h3','p','img']],['benefits','#beneficios .nu-benefit-card',['.nu-benefit-number','h3','p']],['experience_steps','#experiencia .nu-timeline > div',['span','strong','p']],['benefit_stats','#beneficios .nu-benefits-stat',['strong','span']]];
        rows.forEach(([key,selector,children])=>{const vals=get(key);if(!Array.isArray(vals))return;doc.querySelectorAll(selector).forEach((row,i)=>{if(!Array.isArray(vals[i]))return;children.forEach((sel,j)=>{const item=row.querySelector(sel),v=vals[i][j];if(sel==='img'){replaceMedia(doc,`${key}.${i}.${j}`,`${selector}:nth-child(${i+1}) .nu-solution-photo :is(img,video)`);return;}if(!item||v==null)return;item.textContent=String(v);});});});
        const images=[['vision_image','#vision :is(img,video)'],['experience_image','#experiencia :is(img,video)']];images.forEach(([key,sel])=>replaceMedia(doc,key,sel));
        for(const [key,sel] of [['vision_image_alt','#vision img'],['experience_image_alt','#experiencia img']]){if(get(key)!=null){const im=doc.querySelector(sel);if(im)im.alt=get(key);}}
        const hero=get('hero_image'),heroUrl=previewURL('hero_image');
        const heroVisual=doc.querySelector('#inicio .nu-hero-visual');
        if(heroVisual&&heroUrl){
            const isVid=(localMedia.get('hero_image')?.type||hero?.type)==='video'||/\.(mp4|webm)(\?|$)/i.test(heroUrl);
            let visual=heroVisual.querySelector('.nu-hero-video');
            const expectedTag=isVid?'VIDEO':'IMG';
            if(!visual||visual.tagName!==expectedTag){
                const replacement=doc.createElement(isVid?'video':'img');
                replacement.className='nu-hero-video';
                if(isVid){
                    replacement.autoplay=true;replacement.muted=true;
                    replacement.loop=true;replacement.playsInline=true;
                    replacement.preload='metadata';
                }else{
                    replacement.alt='';
                    replacement.style.cssText='width:100%;height:100%;object-fit:cover;';
                }
                if(visual)visual.replaceWith(replacement);
                else heroVisual.prepend(replacement);
                visual=replacement;
            }
            if(isVid){
                const source=visual.querySelector('source')||doc.createElement('source');
                if(!source.parentNode)visual.append(source);
                if(source.getAttribute('src')!==heroUrl){
                    source.src=heroUrl;
                    source.type=localMedia.has('hero_image')?'':hero?.mime||'video/mp4';
                    visual.load();
                    visual.play().catch(()=>{});
                }
            }else if(visual.getAttribute('src')!==heroUrl)visual.src=heroUrl;
        }
        const poster=previewURL('hero_poster');const heroVideo=doc.querySelector('#inicio video');if(heroVideo&&poster)heroVideo.poster=poster;
        const theme=get('theme')||{};
        // El CSS es compartido por la vista previa y la página publicada.
        // No depende de ninguna clase especial del body.
        const defaults={background:'#0b0d10',panel:'#12171d',primary:'#2384ff',light:'#8ac5ff',text:'#f8fafc'};
        const names={background:'--site-bg',panel:'--site-panel',primary:'--site-primary',light:'--site-accent',text:'--site-text'};
        if(!doc.getElementById('site-theme-css')){
            const link=doc.createElement('link');link.id='site-theme-css';link.rel='stylesheet';
            link.href=new URL('/css/site-page-theme.css',location.origin).href;
            doc.head.append(link);
        }
        Object.entries(names).forEach(([key,variable])=>{
            const candidate=theme[key];
            const color=typeof candidate==='string'&&/^#[0-9a-f]{6}$/i.test(candidate)?candidate:defaults[key];
            doc.documentElement.style.setProperty(variable,color,'important');
            doc.body.style.setProperty(variable,color,'important');
        });
    }
    let initiallySeeded=false;
    function seedFromPage(){
        if(initiallySeeded)return;const doc=iframe.contentDocument;if(!doc?.body)return;
        for(const [key,sel] of map){if(get(key)!=null)continue;const e=doc.querySelector(sel);if(!e)continue;
            const value=e.querySelector('em') ? [...e.childNodes].filter(n=>n.nodeType===3).map(n=>n.textContent).join(' ').trim() : e.textContent.trim();
            if(value)putSilently(key,value.replace(/[↗↓]\s*$/,'').trim());
        }
        const sources=[['hero_points','#inicio .nu-hero-points span'],['vision_paragraphs','#vision .nu-vision-content > p'],['vision_list','#vision .nu-vision-list li'],['contact_features','#contacto .nu-contact-features span']];
        sources.forEach(([key,sel])=>{if(get(key)==null)putSilently(key,Array.from(doc.querySelectorAll(sel),e=>e.textContent.trim()));});
        const grids=[['solutions','#soluciones .nu-solution-card',['.nu-solution-number','h3','p','img']],['benefits','#beneficios .nu-benefit-card',['.nu-benefit-number','h3','p']],['experience_steps','#experiencia .nu-timeline > div',['span','strong','p']],['benefit_stats','#beneficios .nu-benefits-stat',['strong','span']]];
        grids.forEach(([key,selector,parts])=>{if(get(key)!=null)return;putSilently(key,Array.from(doc.querySelectorAll(selector),r=>parts.map(sel=>{const e=r.querySelector(sel);return sel==='img'?(e?.src||''):(e?.textContent.trim()||'');})));});
        initiallySeeded=true;const active=Array.from(nav.children).findIndex(e=>e.classList.contains('active'));if(active>=0)render(active);
    }
    function putSilently(k,v){const parts=k.split('.');let obj=data;for(let i=0;i<parts.length-1;i++)obj=obj[parts[i]]??={};obj[parts.at(-1)]=v;}
    iframe.addEventListener('load',()=>{seedFromPage();updatePreview();});
    $('#save-page').addEventListener('click',async()=>{
        if(saving||uploads.size||mediaErrors.size)return;
        const snapshot=structuredClone(data),version=revision;saving=true;refreshActions();
        mark('Guardando…');try{
            const res=await fetch(root.dataset.saveUrl,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':root.dataset.csrf},body:JSON.stringify({content:snapshot})});
            if(!res.ok){let detail=`Error HTTP ${res.status}`;try{const error=await res.json();const messages=Object.values(error.errors||{}).flat();detail=messages.length?messages.join(' '):(error.message||detail);}catch{}throw Error(detail);}
            saved=snapshot;initialPageState='Borrador';changed=revision!==version;
            mark(changed?'Cambios sin guardar':'Borrador guardado');
            if(!changed){for(const key of localMedia.keys())releaseLocal(key);updatePreview();const active=Array.from(nav.children).findIndex(e=>e.classList.contains('active'));render(active);}
        }catch(e){mark('Error al guardar');alert('Error: '+e.message);}finally{saving=false;refreshActions();}
    });
    $('#discard-page').addEventListener('click',()=>{
        uploads.clear();mediaErrors.clear();for(const key of localMedia.keys())releaseLocal(key);
        Object.keys(data).forEach(key=>delete data[key]);Object.assign(data,structuredClone(saved));
        revision++;changed=false;mark('Borrador restaurado');refreshActions();
        const active=Array.from(nav.children).findIndex(e=>e.classList.contains('active'));render(active);iframe.src=iframe.src;
    });
    $('#publish-form')?.addEventListener('submit',e=>{if(changed&&!confirm('Hay cambios sin guardar. ¿Publicar el último borrador guardado?'))e.preventDefault();});
    window.addEventListener('beforeunload',e=>{if(changed){e.preventDefault();e.returnValue='';}});
    window.addEventListener('pagehide',()=>{for(const key of localMedia.keys())releaseLocal(key);});
    // The iframe may have loaded before this deferred script attached its listener.
    if(iframe.contentDocument?.readyState==='complete'){seedFromPage();updatePreview();}
    let device='desktop';
    const frameOuter=iframe.parentElement;
    function fitPreview(){
        const padding=parseFloat(getComputedStyle(frameOuter).paddingLeft)||0;
        const available=frameOuter.clientWidth-padding*2;
        const viewport=({desktop:1280,tablet:768,mobile:390})[device];
        if(available<=0)return;
        const scale=Math.min(1,available/viewport);
        iframe.style.width=`${viewport}px`;
        iframe.style.height=`${(frameOuter.clientHeight-padding*2)/scale}px`;
        iframe.style.transform=`scale(${scale})`;
        iframe.style.left=`${padding+(available-viewport*scale)/2}px`;
        iframe.style.top=`${padding}px`;
    }
    document.querySelectorAll('[data-device]').forEach(btn=>btn.addEventListener('click',()=>{document.querySelectorAll('[data-device]').forEach(b=>b.classList.remove('active'));btn.classList.add('active');device=btn.dataset.device;fitPreview();}));
    new ResizeObserver(fitPreview).observe(frameOuter);
    fitPreview();
})();
