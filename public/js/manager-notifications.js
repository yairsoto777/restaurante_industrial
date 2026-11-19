(() => {
    const root = document.querySelector('.manager-notifications');
    if (!root) return;
    const feedback = root.querySelector('.mn-feedback');
    const explicitReview = root.hasAttribute('data-explicit-review');
    let busy = false;
    async function refresh(id) {
        if (busy) return;
        busy = true;
        try {
            const response = await fetch(id ? root.dataset.read : root.dataset.feed, {
                method: id ? 'POST' : 'GET', credentials: 'same-origin',
                headers: {Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
                ...(id ? {body: JSON.stringify({id})} : {})
            });
            if (!response.ok) throw new Error();
            const data = await response.json();
            const count = root.querySelector('.mn-count');
            count.textContent = data.unread; count.hidden = !data.unread;
            root.querySelector('summary').setAttribute('aria-label', `Notificaciones: ${data.unread} sin leer`);
            feedback.textContent = data.items.length ? '' : '✓ Todo al día. Aquí aparecerán los avisos de tu sede.';
            const list = root.querySelector('.mn-items'); list.replaceChildren();
            data.items.forEach(item => {
                const article = document.createElement('article'); article.className = `mn-item ${item.tone} ${item.read ? 'is-read' : ''}`;
                const icon = document.createElement('span'); icon.className = 'mn-icon'; icon.textContent = {waiting:'◷',ok:'✓',danger:'!'}[item.tone] || 'i'; icon.setAttribute('aria-hidden','true');
                const link = document.createElement('a'); link.href = item.url;
                const title = document.createElement('strong'); title.textContent = item.title;
                const description = document.createElement('p'); description.textContent = item.description;
                const date = document.createElement('small'); date.textContent = `${item.date} · ${item.read ? 'Leída' : 'Sin leer'}`;
                link.append(title, description, date);
                if (!explicitReview) link.addEventListener('click', async event => { if (!item.read) { event.preventDefault(); await refresh(item.id); location.assign(item.url); } });
                article.append(icon, link);
                if (!explicitReview && !item.read) { const button = document.createElement('button'); button.type = 'button'; button.textContent = 'Marcar leída'; button.onclick = () => refresh(item.id); article.append(button); }
                list.append(article);
            });
        } catch { feedback.textContent = 'No se pudieron actualizar los avisos. Se reintentará automáticamente.'; }
        finally { busy = false; }
    }
    root.querySelector('.mn-all')?.addEventListener('click', () => refresh('all'));
    document.addEventListener('click', event => { if (!root.contains(event.target)) root.open = false; });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') root.open = false; });
    refresh(); setInterval(() => refresh(), 30000);
})();
