(() => {
    let turnstileLoading;
    function loadTurnstile() {
        if (window.turnstile) return Promise.resolve();
        if (!turnstileLoading) turnstileLoading = new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
            s.onload = resolve; s.onerror = reject; document.head.append(s);
        });
        return turnstileLoading;
    }
    document.querySelectorAll('form.hws-newsletter').forEach(form => {
        const status = form.querySelector('.hws-newsletter__status');
        const button = form.querySelector('button[type=submit]');
        const widget = form.querySelector('.hws-newsletter__turnstile');
        const failure = form.dataset.error;
        const show = (message, state) => {
            status.textContent = message;
            ['success', 'duplicate', 'error'].forEach(value => status.classList.remove('hws-newsletter__status--' + value));
            if (state) status.classList.add('hws-newsletter__status--' + (state === 'created' ? 'success' : state));
            if (state) form.dataset.state = state; else delete form.dataset.state;
        };
        let widgetId;
        if (widget) loadTurnstile().then(() => { widgetId = turnstile.render(widget, {sitekey: widget.dataset.sitekey, action: 'hws-form'}); }).catch(() => { show(failure, 'error'); });
        form.addEventListener('submit', async event => {
            event.preventDefault(); if (button.disabled) return;
            button.disabled = true; form.setAttribute('aria-busy', 'true');
            show('Submitting…');
            try {
                const endpoint = form.dataset.endpoint;
                const fresh = await fetch(endpoint + '/nonce', {cache: 'no-store', credentials: 'same-origin'});
                const token = await fresh.json(); if (!fresh.ok) throw new Error(failure);
                const data = new FormData(form); data.set('nonce', token.nonce);
                data.set('page_url', location.href);
                const response = await fetch(endpoint, {method: 'POST', body: data, credentials: 'same-origin'});
                const result = await response.json();
                if (!response.ok) { show(result.message || failure, 'error'); return; }
                show(result.message, result.status);
                if (result.status === 'created') form.reset();
            } catch (e) { show(failure, 'error'); }
            finally { button.disabled = false; form.removeAttribute('aria-busy'); if (widgetId !== undefined) turnstile.reset(widgetId); }
        });
    });
})();
