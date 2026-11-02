(() => {
    const sdk = new Map();
    const load = (name, url, ready) => {
        if (ready()) return Promise.resolve();
        if (sdk.has(name)) return sdk.get(name);
        const promise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = url; script.async = true;
            script.onload = () => ready() ? resolve() : reject(new Error('Provider unavailable'));
            script.onerror = () => reject(new Error('Provider unavailable'));
            document.head.append(script);
        });
        sdk.set(name, promise);
        promise.catch(() => sdk.delete(name));
        return promise;
    };
    const initialize = root => {
        const wrappers = root.matches?.('[data-hws-form-protection]') ? [root] : root.querySelectorAll?.('[data-hws-form-protection]') || [];
        for (const wrapper of wrappers) {
            if (wrapper.dataset.hwsReady) continue;
            wrapper.dataset.hwsReady = '1';
            const form = wrapper.closest('form'), input = wrapper.querySelector('[name="hws_form_token"]');
            const refresh = async () => {
                if (!input) return;
                input.value = '';
                try {
                    const url = new URL(hwsFormProtection.challenge_url, location.origin);
                    url.searchParams.set('form_key', wrapper.dataset.hwsFormProtection);
                    url.searchParams.set('_', String(Date.now()));
                    const response = await fetch(url, {credentials:'same-origin', cache:'no-store'});
                    const json = await response.json();
                    if (response.ok && typeof json.token === 'string' && wrapper.isConnected) input.value = json.token;
                } catch { input.value = ''; }
            };
            refresh();
            let widget;
            const renderTurnstile = async () => {
                if (!wrapper.dataset.sitekey) return;
                try {
                    await load('turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit', () => !!window.turnstile?.render);
                    if (!wrapper.isConnected) return;
                    widget = window.turnstile.render(wrapper.querySelector('[data-hws-turnstile]'), {sitekey:wrapper.dataset.sitekey, action:'hws-form'});
                } catch { /* Server validation fails closed; the form remains retryable. */ }
            };
            if (wrapper.dataset.botCheck === 'turnstile') renderTurnstile();
            if (wrapper.dataset.botCheck === 'recaptcha' && form) {
                let busy = false, resumed = false;
                form.addEventListener('submit', async event => {
                    if (resumed) { resumed = false; return; }
                    event.preventDefault(); event.stopImmediatePropagation();
                    if (busy) return;
                    busy = true;
                    const response = wrapper.querySelector('[name="g-recaptcha-response"]');
                    response.value = '';
                    try {
                        await load('recaptcha', 'https://www.google.com/recaptcha/api.js?render='+encodeURIComponent(wrapper.dataset.sitekey), () => !!window.grecaptcha?.ready);
                        await new Promise(resolve => window.grecaptcha.ready(resolve));
                        response.value = await Promise.race([window.grecaptcha.execute(wrapper.dataset.sitekey, {action:'hws_form'}), new Promise((_, reject) => setTimeout(() => reject(new Error('Provider timeout')), 10000))]);
                        if (!response.value || !form.isConnected) return;
                        resumed = true;
                        form.requestSubmit(event.submitter || undefined);
                        resumed = false;
                    } catch {
                        let message = wrapper.querySelector('[data-hws-bot-error]');
                        if (!message) { message = document.createElement('p'); message.dataset.hwsBotError = '1'; message.setAttribute('role','alert'); wrapper.append(message); }
                        message.textContent = 'Spam protection could not load. Please try submitting again.';
                    } finally { busy = false; }
                }, true);
            }
            form?.addEventListener('reset', () => setTimeout(() => {
                refresh();
                if (widget !== undefined && wrapper.isConnected) window.turnstile?.reset(widget);
                const response = wrapper.querySelector('[name="g-recaptcha-response"]');
                if (response) response.value = '';
            }, 0));
        }
    };
    const start = () => {
        initialize(document);
        new MutationObserver(records => {
            for (const record of records) for (const node of record.addedNodes) if (node.nodeType === 1) initialize(node);
        }).observe(document.body, {childList:true, subtree:true});
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once:true}); else start();
})();
