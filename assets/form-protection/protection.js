(() => {
    const initialize = root => {
        const wrappers = root.matches?.('[data-hws-form-protection]') ? [root] : root.querySelectorAll?.('[data-hws-form-protection]') || [];
        for (const wrapper of wrappers) {
            if (wrapper.dataset.hwsReady) continue;
            wrapper.dataset.hwsReady = '1';
            const input = wrapper.querySelector('input[name="hws_form_token"]');
            const refresh = async () => {
                try {
                    const url = new URL(hwsFormProtection.challenge_url, location.origin);
                    url.searchParams.set('form_key', wrapper.dataset.hwsFormProtection);
                    url.searchParams.set('_', String(Date.now()));
                    const response = await fetch(url, {credentials:'same-origin', cache:'no-store'});
                    const json = await response.json();
                    if (response.ok && typeof json.token === 'string') input.value = json.token;
                } catch { input.value = ''; }
            };
            refresh();
            wrapper.closest('form')?.addEventListener('reset', () => setTimeout(refresh, 0));
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
