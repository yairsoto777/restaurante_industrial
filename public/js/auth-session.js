(() => {
    const form = document.querySelector('form[data-login-session]');
    if (!form) return;

    // HTTP cache headers alone do not cover every browser's back/forward cache.
    window.addEventListener('pageshow', event => {
        if (event.persisted) window.location.reload();
    });

    let refreshing = false;
    let ready = false;
    form.addEventListener('submit', async event => {
        if (ready) { ready = false; return; }
        event.preventDefault();
        if (refreshing) return;
        refreshing = true;
        const submitter = event.submitter;
        if (submitter) submitter.disabled = true;
        try {
            const response = await fetch(form.action, {
                method: 'GET', credentials: 'same-origin', cache: 'no-store', redirect: 'error',
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) throw new Error('Session refresh failed');
            const data = await response.json();
            if (typeof data.csrf_token !== 'string' || !data.csrf_token) throw new Error('Missing token');
            form.querySelector('input[name="_token"]').value = data.csrf_token;
            if (submitter) submitter.disabled = false;
            ready = true;
            form.requestSubmit(submitter || undefined);
        } catch {
            // Never retry credentials with a stale/missing token or send them by GET.
            window.location.replace(form.action);
        } finally {
            refreshing = false;
            if (submitter) submitter.disabled = false;
        }
    });
})();
