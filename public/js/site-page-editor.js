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
    const mark = s => {status.textContent=s;};
    let changed=false;
    function dirty(){changed=true;mark('Cambios sin guardar');}
    function elem(tag,attrs={},txt=''){const e=document.createElement(tag);Object.entries(attrs).forEach(([k,v])=>e.setAttribute(k,v));if(txt)e.textContent=txt;return e;}
    function addField(parent,label,key,type='text',fallback=''){
        const wrap=elem('label');wrap.append(document.createTextNode(label));
        const val=get(key)??fallback;
        const input=elem(type==='textarea'?'textarea':'input',type==='textarea'?{}:{type:type==='color'?'color':'text'});
        input.value=(type==='color' && !/^#[0-9a-f]{6}$/i.test(String(val))) ? ({'theme.background':'#0b0d10','theme.panel':'#12171d','theme.primary':'#2384ff','theme.light':'#8ac5ff','theme.text':'#f8fafc'}[key] || '#2384ff') : val;
        input.addEventListener('input',()=>put(key,input.value));wrap.append(input);parent.append(wrap);
    }
    function addMedia(parent,label,key){
        const wrap=elem('label');wrap.append(document.createTextNode(label));
        const input=elem('input',{type:'file',accept:'image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm'});
        const current=get(key);
        const message=elem('small',{},current?.path
            ? `Archivo actual: ${String(current.path).split('/').pop()} (guardado en el borrador si ya presionaste Guardar)`
            : 'JPG, PNG, WebP, GIF, MP4 o WebM. El servidor también debe validar los archivos.');
        input.addEventListener('click',()=>{input.value='';});
        input.addEventListener('change',async()=>{
            const file=input.files?.[0];if(!file)return;
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
                put(key,{path:String(path),url:media.url||null,type,mime});
                message.textContent=`Archivo cargado: ${file.name}`;
            }catch(err){mark('Error al subir archivo');message.textContent=`No se pudo cargar: ${err.message}`;}
            finally{input.value='';}
        });
        wrap.append(input,message);parent.append(wrap);
    }
    function group(parent,title){parent.append(elem('div',{class:'group-label'},title));}
    function render(index){
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
        const candidate=value.url||value.path;
        if(typeof candidate!=='string'||!candidate)return null;
        if(/^https?:\/\//i.test(candidate))return candidate;
        // Evita duplicar /storage/ cuando Laravel ya entrega una URL pública.
        if(candidate.startsWith('/storage/'))return candidate;
        if(candidate.startsWith('/'))return candidate;
        const path=candidate.replace(/^public\//,'').replace(/^storage\//,'').replace(/^\/+/, '');
        const appBase=window.location.pathname.split('/operacion/')[0].replace(/\/$/,'');
        return `${appBase}/storage/${path}`;
    }
    function updatePreview(){let doc;try{doc=iframe.contentDocument;}catch{return;}if(!doc||!doc.body)return;
        map.forEach(([key,sel])=>setText(doc,sel,get(key)));
        const textLists=[['hero_points','#inicio .nu-hero-points span'],['vision_paragraphs','#vision .nu-vision-content > p'],['vision_list','#vision .nu-vision-list li'],['contact_features','#contacto .nu-contact-features span']];
        for(const [key,selector] of textLists){const vals=get(key);if(!Array.isArray(vals))continue;doc.querySelectorAll(selector).forEach((e,i)=>{if(vals[i]!==undefined)e.textContent=vals[i];});}
        const rows=[['solutions','#soluciones .nu-solution-card', ['.nu-solution-number','h3','p','img']],['benefits','#beneficios .nu-benefit-card',['.nu-benefit-number','h3','p']],['experience_steps','#experiencia .nu-timeline > div',['span','strong','p']],['benefit_stats','#beneficios .nu-benefits-stat',['strong','span']]];
        rows.forEach(([key,selector,children])=>{const vals=get(key);if(!Array.isArray(vals))return;doc.querySelectorAll(selector).forEach((row,i)=>{if(!Array.isArray(vals[i]))return;children.forEach((sel,j)=>{const item=row.querySelector(sel),v=vals[i][j];if(!item||v==null)return;if(sel==='img'){const url=mediaURL(v);if(url)item.src=url;}else item.textContent=String(v);});});});
        const images=[['vision_image','#vision img'],['experience_image','#experiencia img']];images.forEach(([key,sel])=>{const url=mediaURL(get(key)),im=doc.querySelector(sel);if(url&&im)im.src=url;});
        for(const [key,sel] of [['vision_image_alt','#vision img'],['experience_image_alt','#experiencia img']]){if(get(key)!=null){const im=doc.querySelector(sel);if(im)im.alt=get(key);}}
        const hero=get('hero_image'),heroUrl=mediaURL(hero);
        const heroVisual=doc.querySelector('#inicio .nu-hero-visual');
        if(heroVisual&&heroUrl){
            const isVid=(typeof hero==='object' && hero?.type==='video')||/\.(mp4|webm)(\?|$)/i.test(heroUrl);
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
                    source.type=hero?.mime||'video/mp4';
                    visual.load();
                    visual.play().catch(()=>{});
                }
            }else if(visual.getAttribute('src')!==heroUrl)visual.src=heroUrl;
        }
        const poster=mediaURL(get('hero_poster'));const heroVideo=doc.querySelector('#inicio video');if(heroVideo&&poster)heroVideo.poster=poster;
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
        mark('Guardando…');try{const res=await fetch(root.dataset.saveUrl,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':root.dataset.csrf},body:JSON.stringify({content:data})});if(!res.ok){let detail=`Error HTTP ${res.status}`;try{const error=await res.json();const messages=Object.values(error.errors||{}).flat();detail=messages.length?messages.join(' '):(error.message||detail);}catch{}throw Error(detail);}changed=false;mark('Borrador guardado');}catch(e){mark('Error al guardar');alert('Error: '+e.message);}
    });
    $('#publish-form')?.addEventListener('submit',e=>{if(changed&&!confirm('Hay cambios sin guardar. ¿Publicar el último borrador guardado?'))e.preventDefault();});
    window.addEventListener('beforeunload',e=>{if(changed){e.preventDefault();e.returnValue='';}});
    document.querySelectorAll('[data-device]').forEach(btn=>btn.addEventListener('click',()=>{document.querySelectorAll('[data-device]').forEach(b=>b.classList.remove('active'));btn.classList.add('active');iframe.style.width=({desktop:'100%',tablet:'768px',mobile:'390px'})[btn.dataset.device];}));
})();
