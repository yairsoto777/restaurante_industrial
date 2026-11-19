document.addEventListener('DOMContentLoaded',()=>{
    const root=document.querySelector('[data-gm-generator]');if(!root)return;
    const form=root.querySelector('[data-gm-form]'),preview=root.querySelector('[data-gm-preview]'),final=root.querySelector('[data-gm-final]'),stage=root.querySelector('[data-gm-stage]'),feedback=root.querySelector('[data-gm-feedback]');
    const cache=new Map(),media=new Map();let timer,requestId=0,controller;
    root.querySelectorAll('[data-gm-cache] template').forEach(node=>cache.set(node.dataset.template+':'+node.dataset.kind,node.innerHTML));
    const value=name=>new FormData(form).getAll(name).at(-1) ?? '';
    const message=(text,error=false)=>{feedback.textContent=text;feedback.dataset.error=String(error);feedback.hidden=!text;};
    const pageStyle=document.createElement('style');pageStyle.dataset.gmPageSize='';document.head.append(pageStyle);
    function fit(){const doc=preview.querySelector('[data-gm-document]');if(!doc)return;const padding=getComputedStyle(stage);preview.style.zoom=Math.min(1,Math.max(.2,(stage.clientWidth-parseFloat(padding.paddingLeft)-parseFloat(padding.paddingRight))/doc.offsetWidth));}
    function apply(){
        const template=value('template');
        [preview,final].forEach(container=>{
            const doc=container.querySelector('[data-gm-document]');if(!doc)return;
            doc.style.setProperty('--gm-primary',value('color'));doc.style.setProperty('--gm-secondary',value('color_secundario'));doc.style.setProperty('--gm-title-size',value('tamano_titulo')+'px');
            ['diario','semanal','quincenal','mensual','personalizado'].forEach(period=>doc.classList.toggle('gm-period-'+period,value('periodo')===period));
            ['vertical','horizontal'].forEach(orientation=>doc.classList.toggle('gm-orientation-'+orientation,value('orientacion')===orientation));
            ['Carta','A4'].forEach(paper=>doc.classList.toggle('gm-paper-'+paper,value('papel')===paper));
            for(const [key,cls] of Object.entries({fotos:'photos',precios:'prices',descripciones:'descriptions',horarios:'hours',destacar_especial:'special'}))doc.classList.toggle('gm-no-'+cls,value(key)!=='1');
            doc.querySelectorAll('[data-gm-title]').forEach(n=>n.textContent=value('titulo'));
            doc.querySelectorAll('[data-gm-comedor]').forEach(n=>n.textContent=value('comedor'));
            doc.querySelectorAll('[data-gm-subtitle]').forEach(n=>{n.textContent=value('subtitulo');n.hidden=!value('subtitulo').trim();});
            for(const [field,selector] of [['design_logo','[data-gm-logo]'],['design_background','[data-gm-background]']]){
                const src=media.get(field)?.src;
                if(src!==undefined)doc.querySelectorAll(selector).forEach(n=>{if(src)n.src=src;else n.removeAttribute('src');n.hidden=!src;});
            }
        });
        root.querySelector('[data-gm-title-size]').textContent=value('tamano_titulo')+' px';
        root.querySelector('[data-gm-paper-label]').textContent=value('papel')+' · '+value('orientacion');
        pageStyle.textContent='@page{size:'+(value('papel')==='Carta'?'letter':'A4')+' '+(value('orientacion')==='horizontal'?'landscape':'portrait')+';margin:12mm}';
        root.dataset.template=template;requestAnimationFrame(fit);
    }
    function render(){const template=value('template');for(const [target,kind] of [[preview,'preview'],[final,'final']]){const html=cache.get(template+':'+kind);if(html)target.innerHTML=html;}apply();}
    const palettes={elegante:['#171813','#c3a36a'],corporativa:['#17375e','#b49a70'],gastronomica:['#465338','#69734b'],contemporanea:['#222224','#ed4b45'],minimalista:['#303d34','#8b978b'],revista:['#342d27','#b45c36']};
    function range(){const period=value('periodo');if(period==='personalizado'||!value('desde'))return;const date=new Date(value('desde')+'T12:00:00');if(period==='mensual'){const day=date.getDate();date.setDate(1);date.setMonth(date.getMonth()+1);date.setDate(Math.min(day,new Date(date.getFullYear(),date.getMonth()+1,0).getDate()));date.setDate(date.getDate()-1);}else date.setDate(date.getDate()+({diario:0,semanal:6,quincenal:13}[period]??6));form.elements.hasta.value=[date.getFullYear(),String(date.getMonth()+1).padStart(2,'0'),String(date.getDate()).padStart(2,'0')].join('-');}
    async function responseError(response){let body;try{body=await response.json();}catch{throw Error('No se pudo completar la operación. Inténtalo nuevamente.');}throw Error(body.errors?Object.values(body.errors).flat().join(' '):body.message || 'No se pudo completar la operación.');}
    async function refresh(){
        if(!form.checkValidity())return;
        const id=++requestId;controller?.abort();controller=new AbortController();root.setAttribute('aria-busy','true');
        try{
            const response=await fetch(form.dataset.preview,{method:'POST',body:new FormData(form),credentials:'same-origin',headers:{Accept:'application/json'},signal:controller.signal});
            if(!response.ok)await responseError(response);const data=await response.json();if(id!==requestId)return;
            for(const [template,items] of Object.entries(data.templates)){cache.set(template+':preview',items.preview);cache.set(template+':final',items.final);}
            root.querySelector('[data-gm-count]').textContent=data.count+' platillos';root.querySelector('[data-gm-final-count]').textContent=data.finalCount+' platillos en la selección final';
            root.querySelector('[data-gm-pending-count]').textContent=data.pending.length;root.querySelector('[data-gm-pending]').textContent=data.pending.length ? data.pending.join(' · ') : 'Todas las fechas incluidas tienen un menú configurado.';
            render();message('');
        }catch(error){if(error.name!=='AbortError')message(error.message,true);}finally{if(id===requestId)root.removeAttribute('aria-busy');}
    }
    form.addEventListener('input',event=>{if(event.target.type==='file'||event.target.matches('[data-gm-content]'))return;apply();});
    form.addEventListener('change',event=>{
        if(event.target.type==='file')return;
        if(event.target.name==='template'){
            const [primary,secondary]=palettes[value('template')];form.elements.color.value=primary;form.elements.color_secundario.value=secondary;render();return;
        }
        if(event.target.matches('[data-gm-content]')){if(['periodo','desde'].includes(event.target.name))range();clearTimeout(timer);timer=setTimeout(refresh,180);}else apply();
    });
    root.querySelectorAll('[data-gm-upload]').forEach(block=>{
        const field=block.dataset.gmUpload,input=block.querySelector('input[type=file]'),image=block.querySelector('img'),empty=block.querySelector('.gm-upload-preview>span'),status=block.querySelector('p');
        media.set(field,{src:block.dataset.original || '',objectUrl:null});let selection=0;
        const updateImage=src=>{if(src)image.src=src;else image.removeAttribute('src');image.hidden=!src;empty.hidden=!!src;apply();};
        const restore=()=>{const old=media.get(field);if(old.objectUrl)URL.revokeObjectURL(old.objectUrl);media.set(field,{src:block.dataset.original || '',objectUrl:null});input.value='';status.textContent='';status.dataset.error='false';updateImage(media.get(field).src);};
        input.addEventListener('change',async()=>{
            const id=++selection,file=input.files[0];if(!file){restore();return;}
            if(!['image/jpeg','image/png','image/webp'].includes(file.type)||!/\.(jpe?g|png|webp)$/i.test(file.name)||file.size>4*1024*1024){restore();status.textContent='Selecciona JPG, JPEG, PNG o WEBP de hasta 4 MB.';status.dataset.error='true';return;}
            const src=URL.createObjectURL(file);const probe=new Image();probe.src=src;
            try{await probe.decode();}catch{URL.revokeObjectURL(src);if(id!==selection)return;restore();status.textContent='No se pudo abrir la imagen. Selecciona otro archivo.';status.dataset.error='true';return;}
            if(id!==selection){URL.revokeObjectURL(src);return;}const old=media.get(field);if(old.objectUrl)URL.revokeObjectURL(old.objectUrl);
            media.set(field,{src,objectUrl:src});updateImage(src);status.dataset.error='false';status.textContent=input.name ? 'Imagen seleccionada correctamente. Guarda para conservarla.' : 'Vista previa local. El guardado requiere habilitar el esquema.';
        });
        block.querySelector('[data-gm-restore]').addEventListener('click',()=>{selection++;restore();});
    });
    const waitImages=async doc=>{await doc.fonts?.ready;await Promise.all([...doc.images].filter(image=>image.getAttribute('src')).map(async image=>{if(image.complete&&image.naturalWidth)return;await image.decode();if(!image.naturalWidth)throw Error('No se pudo cargar una imagen del documento.');}));};
    async function exportDocument(kind){
        if(!form.reportValidity())return;
        const buttons=[...root.querySelectorAll('[data-gm-print],[data-gm-pdf]')],states=buttons.map(b=>b.disabled);buttons.forEach(b=>b.disabled=true);
        message(kind==='pdf'?'Preparando tu PDF con las imágenes y la selección autorizada…':'Preparando el documento final para imprimir…');
        try{
            const response=await fetch(kind==='pdf'?form.dataset.pdf:form.dataset.print,{method:'POST',body:new FormData(form),credentials:'same-origin',headers:{Accept:kind==='pdf'?'application/pdf':'text/html'}});
            if(!response.ok)await responseError(response);
            if(kind==='pdf'){
                const blob=await response.blob();if(!blob.type.includes('application/pdf'))throw Error('El servidor no devolvió un PDF válido.');
                const url=URL.createObjectURL(blob),link=document.createElement('a');link.href=url;link.download='menu-nutriliado.pdf';document.body.append(link);link.click();link.remove();setTimeout(()=>URL.revokeObjectURL(url),30000);message('PDF generado correctamente.');
            }else{
                const html=await response.text(),frame=document.createElement('iframe');frame.title='Menú final para imprimir';frame.setAttribute('aria-hidden','true');frame.style.cssText='position:fixed;left:-12000px;top:0;width:1100px;height:800px;border:0';
                const loaded=new Promise((resolve,reject)=>{const timeout=setTimeout(()=>reject(Error('El documento tardó demasiado en cargar.')),20000);frame.onload=()=>{clearTimeout(timeout);resolve();};});
                frame.srcdoc=html;document.body.append(frame);
                try{await loaded;await waitImages(frame.contentDocument);frame.contentWindow.addEventListener('afterprint',()=>frame.remove(),{once:true});frame.contentWindow.focus();frame.contentWindow.print();setTimeout(()=>frame.remove(),90000);}catch(error){frame.remove();throw error;}
                message('Documento final listo para imprimir.');
            }
        }catch(error){message(error.message,true);}finally{buttons.forEach((button,index)=>button.disabled=states[index]);}
    }
    root.querySelector('[data-gm-print]').addEventListener('click',()=>exportDocument('print'));
    root.querySelector('[data-gm-pdf]').addEventListener('click',()=>exportDocument('pdf'));
    root.querySelector('[data-gm-fit]').addEventListener('click',fit);
    document.addEventListener('keydown',event=>{if((event.ctrlKey||event.metaKey)&&event.key.toLowerCase()==='p'){event.preventDefault();exportDocument('print');}});
    window.addEventListener('beforeprint',apply);new ResizeObserver(fit).observe(stage);
    window.addEventListener('pagehide',()=>media.forEach(value=>{if(value.objectUrl)URL.revokeObjectURL(value.objectUrl);}));
    apply();
});
