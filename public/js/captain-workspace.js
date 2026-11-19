(() => {
    let selected = null, refreshing = false;
    const page = () => document.querySelector('.cw-page');
    const filter = () => {
        const query = (document.querySelector('[data-cw-search]')?.value || '').toLocaleLowerCase('es');
        const state = document.querySelector('[data-cw-state-filter]')?.value || '';
        const zone = document.querySelector('[data-cw-zone-filter]')?.value || '';
        document.querySelectorAll('[data-cw-record]').forEach(el => {
            el.hidden = !el.textContent.toLocaleLowerCase('es').includes(query) || (state && el.dataset.state !== state) || (zone && el.dataset.zoneId && el.dataset.zoneId !== zone);
        });
        document.querySelectorAll('.cw-floor-zone').forEach(el=>{el.hidden=Boolean(zone && el.dataset.zoneId!==zone);});
    };
    const select = (id, open = true) => {
        selected = id;
        const table = document.querySelector(`[data-cw-table-id="${CSS.escape(id)}"]`);
        document.querySelectorAll('.cw-map-table').forEach(el => el.classList.toggle('is-selected',el === table));
        if(document.querySelector('.lv-workspace')) document.querySelectorAll('.lv-table-pick').forEach(button=>button.setAttribute('aria-pressed',String(button.dataset.cwPick===id)));
        const detail = table?.querySelector('template[data-cw-detail]');
        const panel = document.querySelector('[data-cw-selected]');
        if (panel) panel.replaceChildren(detail ? detail.content.cloneNode(true) : document.createTextNode('La mesa ya no está en esta consulta.'));
        const dialog = document.getElementById('cw-table-details');
        if (open && dialog && !dialog.open) dialog.showModal();
    };
    document.addEventListener('click', e => {
        const button = e.target.closest('[data-cw-open],[data-cw-close],[data-cw-pick],[data-cw-view]');
        if (!button) return;
        if (button.hasAttribute('data-cw-open')) document.getElementById(button.dataset.cwOpen)?.showModal();
        if (button.hasAttribute('data-cw-close')) button.closest('dialog')?.close();
        if (button.hasAttribute('data-cw-pick')) select(button.dataset.cwPick);
        if (button.hasAttribute('data-cw-view')) {
            document.querySelector('[data-cw-cards]').hidden = button.dataset.cwView !== 'cards';
            document.querySelector('[data-cw-table]').hidden = button.dataset.cwView !== 'table';
        }
    });
    document.addEventListener('input', e => {
        if (e.target.matches('[data-cw-search]')) filter();
        if (e.target.matches('[data-cw-zone-name]')) e.target.form.querySelector('[data-cw-zone-title]').textContent = e.target.value || 'Nombre de la zona';
        if (e.target.matches('[data-cw-zone-color]')) e.target.form.querySelector('.cw-zone-preview').style.setProperty('--zone',e.target.value);
        if (e.target.matches('[data-cw-capacity]')) {
            const svg=e.target.form.querySelector('.cw-table-symbol');
            const count=Math.min(100,Math.max(1,Number(e.target.value)||1));
            svg.querySelectorAll('g').forEach(g=>g.remove());
            for(let i=0;i<count;i++) {
                const group=document.createElementNS('http://www.w3.org/2000/svg','g');
                group.setAttribute('transform',`rotate(${i*360/count} 70 70)`);
                const rect=document.createElementNS('http://www.w3.org/2000/svg','rect');
                for(const [k,v] of Object.entries({x:60,y:9,width:20,height:20,rx:6,fill:'currentColor',opacity:'.65'})) rect.setAttribute(k,v);
                group.append(rect);svg.prepend(group);
            }
            svg.setAttribute('aria-label',`Mesa, ${count} lugares`);
        }
    });
    document.addEventListener('change', e => {
        if(e.target.matches('.cw-filters input[name="fecha"]')) e.target.form.querySelector('[name="id_servicio"]').value='';
        if (e.target.matches('[data-cw-state-filter],[data-cw-zone-filter]')) filter();
        if (!e.target.matches('[data-cw-preview]')) return;
        const file=e.target.files?.[0], preview=e.target.form.querySelector('.cw-preview');
        if(!file) return;
        if(!['image/jpeg','image/png','image/webp'].includes(file.type) || file.size>4*1024*1024) {
            e.target.value='';e.target.setCustomValidity('Selecciona JPG, PNG o WEBP de máximo 4 MB.');e.target.reportValidity();return;
        }
        e.target.setCustomValidity('');
        if(preview){const url=URL.createObjectURL(file);preview.onload=()=>URL.revokeObjectURL(url);preview.src=url;preview.hidden=false;}
    });
    setInterval(async () => {
        if(page()?.dataset.cwModule!=='comedor' || document.hidden || refreshing) return;
        refreshing=true;
        try {
            const response=await fetch(location.href,{headers:{'X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});
            if(!response.ok || response.redirected) throw Error('Actualización no disponible');
            const doc=new DOMParser().parseFromString(await response.text(),'text/html');
            const sections=doc.querySelector('.lv-workspace')?['.lv-service-facts','.cw-live-banner','.cw-live-layout']:['.cw-live-banner','.cw-live-layout'];
            for(const selector of sections) {
                const next=doc.querySelector(selector),current=document.querySelector(selector);
                if(!next || !current) throw Error('Respuesta inesperada');
                current.replaceWith(next);
            }
            if(selected) select(selected,false);
            filter();
            document.querySelector('[data-cw-updated]').textContent=`Actualizado ${new Date().toLocaleTimeString('es-MX')} · cada 30 s`;
        } catch { const status=document.querySelector('[data-cw-updated]');if(status) status.textContent='Sin actualizar · reintentando en 30 s'; }
        finally {refreshing=false;}
    },30000);
})();
