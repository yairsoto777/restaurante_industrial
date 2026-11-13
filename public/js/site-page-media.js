(() => {
    'use strict';
    const script = document.currentScript;
    const placeholder = script.dataset.placeholder;
    document.addEventListener('error', event => {
        let media = event.target;
        if (media.tagName === 'SOURCE') media = media.parentElement;
        if (!media?.matches?.('.nu-hero-visual img,.nu-hero-visual video,.nu-vision-image img,.nu-vision-image video,.nu-experience-image img,.nu-experience-image video,.nu-solution-photo img,.nu-solution-photo video')) return;
        if (media.dataset.fallback === 'true') return;
        const image = document.createElement('img');
        image.className = media.className;
        image.style.cssText = media.style.cssText;
        image.dataset.fallback = 'true';
        image.alt = 'No se pudo cargar el archivo multimedia. Imagen de respaldo.';
        image.src = media.tagName === 'VIDEO' && media.poster ? media.poster : placeholder;
        image.addEventListener('error', () => {
            if (image.getAttribute('src') !== placeholder) image.src = placeholder;
        });
        media.replaceWith(image);
        const container = image.parentElement;
        if (!container.querySelector('.nu-media-unavailable')) {
            const notice = document.createElement('span');
            notice.className = 'nu-media-unavailable';
            notice.textContent = 'Archivo no disponible · Imagen de respaldo';
            notice.setAttribute('role', 'status');
            container.append(notice);
        }
    }, true);
})();
