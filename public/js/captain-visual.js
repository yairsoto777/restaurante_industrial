/* Captain presentation only. Mirrors existing filters; never submits requests. */
document.addEventListener('DOMContentLoaded', () => {
    if (!document.body.classList.contains('ops-captain')) return;
    const mapSearch=document.querySelector('[data-captain-map-search]');
    const mapZone=document.querySelector('[data-captain-map-zone]');
    const directorySearch=document.getElementById('cz-search');
    const directoryZone=document.getElementById('cz-filter');
    const updateMapCount=()=>{
        const counter=document.querySelector('[data-captain-map-count]');
        if(counter){const cards=[...document.querySelectorAll('[data-ops-floor-card]')];counter.textContent=`${cards.filter(card=>!card.hidden).length} de ${cards.length} mesas`}
    };
    if(mapSearch && mapZone && directorySearch && directoryZone){
        mapSearch.addEventListener('input',()=>{directorySearch.value=mapSearch.value;directorySearch.dispatchEvent(new Event('input'))});
        mapZone.addEventListener('change',()=>{directoryZone.value=mapZone.value;directoryZone.dispatchEvent(new Event('change'))});
        const sync=()=>queueMicrotask(()=>{mapSearch.value=directorySearch.value;mapZone.value=directoryZone.value;updateMapCount()});
        directorySearch.addEventListener('input',sync);directoryZone.addEventListener('change',sync);updateMapCount();
    }
    const liveSearch=document.querySelector('[data-captain-live-search]');
    const liveState=document.querySelector('[data-captain-live-status]');
    const originalSearch=document.getElementById('cp-live-search');
    const originalState=document.getElementById('cp-live-status-filter');
    const mirrorLive=()=>queueMicrotask(()=>{
        const rows=[...document.querySelectorAll('[data-captain-live-id]')];
        const cards=[...document.querySelectorAll('[data-captain-live-card]')];
        cards.forEach(card=>{const row=rows.find(item=>item.dataset.captainLiveId===card.dataset.captainLiveCard);if(row)card.hidden=row.hidden});
        const empty=document.querySelector('[data-captain-live-empty]');if(empty)empty.hidden=cards.length===0 || cards.some(card=>!card.hidden);
        if(liveSearch)liveSearch.value=originalSearch.value;if(liveState)liveState.value=originalState.value;
    });
    if(liveSearch && liveState && originalSearch && originalState){
        liveSearch.addEventListener('input',()=>{originalSearch.value=liveSearch.value;originalSearch.dispatchEvent(new Event('input'))});
        liveState.addEventListener('change',()=>{originalState.value=liveState.value;originalState.dispatchEvent(new Event('change'))});
        [originalSearch,originalState,document.getElementById('cp-live-zone-filter'),document.getElementById('cp-live-waiter-filter')].filter(Boolean).forEach(field=>{field.addEventListener('input',mirrorLive);field.addEventListener('change',mirrorLive)});
    }
});
