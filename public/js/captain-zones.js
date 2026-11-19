(() => {
    const workspace=document.querySelector('.czv-workspace');
    if(!workspace)return;
    for(const panel of workspace.querySelectorAll('[data-czv-reopen]'))if(!panel.open)panel.showModal();
    for(const panel of workspace.querySelectorAll('.czv-drawer')){
        panel.addEventListener('click',event=>{
            if(event.target!==panel)return;
            const rect=panel.getBoundingClientRect();
            if(event.clientX<rect.left || event.clientX>rect.right || event.clientY<rect.top || event.clientY>rect.bottom)panel.close();
        });
    }
})();
