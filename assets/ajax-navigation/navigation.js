/* HWS AJAX Navigation. Turbo owns fetching, rendering, history, scroll and permanent nodes. */
(() => {
    'use strict';
    if (window.HwsAjaxNavigation) return;
    const config = window.hwsAjaxNavigationConfig || {};
    const optOut = '[data-turbo="false"],[data-hws-ajax="false"],.no-ajax,#wpadminbar';
    const scripts = new Set();
    const inline = new Set();
    const instances = new Set();
    const initialized = new WeakSet();
    const handlersByNode = new WeakMap();
    let incoming = null, target = location.href, timer, visiting = false, initial = true, previous = location.href, failed = false, visitSequence = 0;
    const api = window.HwsAjaxNavigation = { version: '1', adapters: new Map(), lastVisit: null };
    api.register = (name, adapter) => api.adapters.set(name, adapter);
    const emit = (name, detail) => document.dispatchEvent(new CustomEvent(`hws:page-${name}`, { detail }));
    const fallback = (url = target, reason = 'navigation-error') => {
        if (failed) return;
        failed = true; visiting = false;
        clearTimeout(timer);
        api.lastVisit = { url, fallback: reason };
        emit('fallback', { url, reason });
        location.assign(url);
    };
    function excluded(value) {
        const url = new URL(value, location.href), path = decodeURIComponent(url.pathname).toLowerCase();
        if (url.origin !== location.origin || !/^https?:$/.test(url.protocol)) return true;
        if (/(?:^|\/)(?:wp-admin|feed)(?:\/|$)|\.[a-z0-9]{1,8}$/i.test(path)) return true;
        if (['elementor-preview','preview','preview_id','feed','rest_route','add-to-cart','wc-ajax','customize_changeset_uuid'].some(k => url.searchParams.has(k))) return true;
        if (['elementor','edit'].includes(url.searchParams.get('action'))) return true;
        return (config.excludedPaths || []).some(p => { p = p.toLowerCase().replace(/\/$/, ''); return !p || path === p || path.startsWith(p + '/'); });
    }
    function remember(root = document) {
        root.querySelectorAll('script').forEach(s => {
            if (s.src) scripts.add(new URL(s.src, location.href).href);
            else inline.add(s.textContent);
        });
    }
    // Instrument before Elementor's first widget is created, including lazy Pro handlers.
    function trackElementor() {
        const manager = window.elementorFrontend?.elementsHandler;
        if (!manager || manager.hwsTracked) return;
        manager.hwsTracked = true;
        const ready = manager.runReadyTrigger;
        // Pro loop requests can finish after the originating document has been removed.
        manager.runReadyTrigger = function (scope, ...args) {
            if (!scope?.isConnected) return;
            if (scope.closest('[data-turbo-permanent][id]') && handlersByNode.has(scope)) return;
            return ready.call(this, scope, ...args);
        };
        const add = manager.addHandler;
        manager.addHandler = function (Handler, options, ...rest) {
            const node = options.$element?.[0];
            if (!node?.isConnected) return;
            const bound = handlersByNode.get(node) || new Map();
            handlersByNode.set(node, bound);
            bound.get(Handler)?.onDestroy?.();
            const Tracked = new Proxy(Handler, { construct(Type, args) {
                const instance = Reflect.construct(Type, args);
                instances.add(instance);
                bound.set(Handler, instance);
                const destroy = instance.onDestroy;
                let destroyed = false;
                instance.onDestroy = function (...args) {
                    if (destroyed) return;
                    destroyed = true;
                    instances.delete(instance);
                    return destroy?.apply(this, args);
                };
                return instance;
            } });
            return add.call(this, Tracked, options, ...rest);
        };
    }
    if (window.jQuery) window.jQuery(window).on('elementor/frontend/init.hwsAjax', trackElementor);
    // jQuery may load later in the head. Elementor emits a native CustomEvent too.
    window.addEventListener('elementor/frontend/init', trackElementor);
    function teardown() {
        emit('will-change', { from: previous, to: target });
        api.adapters.forEach(adapter => adapter.before?.());
        for (const instance of instances) {
            const node = instance.$element?.[0];
            if (node?.closest('[data-turbo-permanent][id]')) continue;
            instance.onDestroy?.();
            instances.delete(instance);
        }
        const manager = window.elementorFrontend?.documentsManager;
        if (manager) for (const [id, doc] of Object.entries(manager.documents)) {
            if (doc.$element?.[0]?.closest('[data-turbo-permanent][id]')) continue;
            doc.onDestroy?.();
            delete manager.documents[id];
        }
    }
    function restartElementor() {
        const frontend = window.elementorFrontend;
        if (!frontend || !document.querySelector('.elementor')) return;
        if (!frontend.documentsManager || !frontend.elementsHandler) throw new Error('Elementor frontend not ready');
        trackElementor();
        if (window.elementorFrontendConfig) frontend.config = window.elementorFrontendConfig;
        if (window.elementorProFrontend && window.ElementorProFrontendConfig) window.elementorProFrontend.config = window.ElementorProFrontendConfig;
        frontend.elements.$body = window.jQuery(document.body);
        frontend.elements.$wpAdminBar = window.jQuery('#wpadminbar');
        const device = document.getElementById('elementor-device-mode');
        if (device) frontend.elements.$deviceMode = window.jQuery(device);
        else frontend.elements.$body.append(frontend.elements.$deviceMode);
        frontend.addUserAgentClasses();
        frontend.setDeviceModeData();
        document.querySelectorAll('.elementor[data-elementor-id]').forEach(node => {
            if (initialized.has(node)) return;
            initialized.add(node);
            frontend.documentsManager.attachDocumentClass(window.jQuery(node));
        });
        // Elementor's background observer is otherwise attached only to DOMContentLoaded.
        const observer = new IntersectionObserver(entries => entries.forEach(entry => {
            if (entry.isIntersecting) { entry.target.classList.add('e-lazyloaded'); observer.unobserve(entry.target); }
        }), { rootMargin: '200px' });
        document.querySelectorAll('.e-con.e-parent:not(.e-lazyloaded)').forEach(node => observer.observe(node));
        api.backgroundObserver?.disconnect();
        api.backgroundObserver = observer;
    }
    new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node => {
        if (node.nodeName === 'SCRIPT' && node.src && node.getAttribute('data-turbo-eval') !== 'false') scripts.add(node.src);
    }))).observe(document.documentElement, { childList: true, subtree: true });
    function pageHead(doc) {
        const assets = new Set([...doc.head.querySelectorAll('[data-hws-ajax-style]')].map(n => n.outerHTML));
        document.head.querySelectorAll('[data-hws-ajax-style]').forEach(n => {
            if (!assets.has(n.outerHTML) && !n.hasAttribute('data-turbo-permanent')) n.remove();
        });
        // Turbo retains scripts/styles. Page metadata must instead match the destination exactly.
        const selector = 'title,meta:not([name="csrf-token"]),link[rel="canonical"],link[rel="alternate"],script[type="application/ld+json"]';
        document.head.querySelectorAll(selector).forEach(node => node.remove());
        doc.head.querySelectorAll(selector).forEach(node => document.head.append(node.cloneNode(true)));
        for (const attr of [...document.documentElement.attributes]) {
            if (!doc.documentElement.hasAttribute(attr.name) && !['aria-busy','data-turbo-preview'].includes(attr.name)) document.documentElement.removeAttribute(attr.name);
        }
        for (const attr of doc.documentElement.attributes) document.documentElement.setAttribute(attr.name, attr.value);
    }
    async function executeAssets(doc) {
        for (const source of doc.scripts) {
            // Localized page data is refreshed without replaying its script or global listeners.
            const data = source.textContent.match(/(?:var\s+)?(elementorFrontendConfig|ElementorProFrontendConfig)\s*=\s*(\{[\s\S]*\});/);
            if (data) { window[data[1]] = JSON.parse(data[2]); continue; }
            if (source.closest('[data-turbo-permanent][id]')) continue;
            if (source.type && !['text/javascript','application/javascript','module'].includes(source.type)) continue;
            const src = source.getAttribute('src');
            if (src && scripts.has(new URL(src, target).href)) continue;
            if (!src && inline.has(source.textContent)) continue;
            const script = document.createElement('script');
            for (const attr of source.attributes) if (!['id','async','defer','data-turbo-eval'].includes(attr.name)) script.setAttribute(attr.name, attr.value);
            script.setAttribute('data-turbo-eval', 'false');
            script.async = false;
            if (src) {
                const url = new URL(src, target).href;
                script.src = url;
                await new Promise((resolve, reject) => {
                    script.onload = resolve;
                    script.onerror = () => reject(new Error(`Asset failed: ${url}`));
                    document.head.append(script);
                });
                scripts.add(url);
            } else {
                script.textContent = source.textContent;
                document.head.append(script);
                inline.add(source.textContent);
            }
        }
    }
    function analytics() {
        // Choose one owner: manual calls here, or GA4's own history measurement.
        if (config.analytics !== 'history' && typeof window.gtag === 'function') {
            window.gtag('event', 'page_view', { page_location: location.href, page_title: document.title, page_referrer: previous });
        }
    }
    document.addEventListener('turbo:click', event => {
        const link = event.target.closest('a');
        try {
            if (!link || link.closest(optOut) || link.hasAttribute('download') || (link.target && link.target !== '_self') || link.hasAttribute('data-method') || link.hasAttribute('data-turbo-method') || excluded(event.detail.url)) event.preventDefault();
        } catch { event.preventDefault(); }
    });
    document.addEventListener('turbo:before-visit', event => {
        try { if (excluded(event.detail.url)) { event.preventDefault(); fallback(event.detail.url, 'excluded'); } }
        catch { event.preventDefault(); fallback(event.detail.url, 'invalid-url'); }
    });
    document.addEventListener('turbo:visit', event => {
        target = event.detail.url; incoming = null; visiting = true; failed = false; visitSequence++;
        clearTimeout(timer); timer = setTimeout(() => fallback(target, 'timeout'), config.timeout || 12000);
    });
    document.addEventListener('turbo:before-fetch-response', event => {
        if (!visiting) return;
        const response = event.detail.fetchResponse.response, sequence = visitSequence;
        incoming = (async () => {
            if (!response.ok || new URL(response.url).origin !== location.origin) throw new Error('HTTP response rejected');
            const doc = new DOMParser().parseFromString(await response.clone().text(), 'text/html');
            if (!doc.querySelector('meta[name="hws-ajax-navigation"][content="1"]') || doc.querySelector('#wpadminbar') || doc.querySelector('script[type="text/litespeed"]')) throw new Error('Destination requires a normal load');
            return doc;
        })();
        incoming.catch(error => { if (sequence === visitSequence) fallback(response.url || target, error.message); });
    });
    document.addEventListener('turbo:fetch-request-error', event => { if (event.detail.error?.name !== 'AbortError') fallback(target, 'network-error'); });
    document.addEventListener('turbo:before-render', event => {
        event.preventDefault();
        const render = event.detail.render, snapshot = incoming;
        if (!snapshot) { fallback(target, 'missing-snapshot'); return; }
        event.detail.render = async (...args) => {
            try {
                const doc = await snapshot;
                teardown();
                await render(...args);
                pageHead(doc);
                await executeAssets(doc);
                await new Promise(resolve => setTimeout(resolve, 50));
                restartElementor();
                api.adapters.forEach(adapter => adapter.after?.(document));
                // Existing SMP frontends already expose this scoped, idempotent re-mount event.
                document.dispatchEvent(new CustomEvent('smp:content-ready', { detail: { root: document, source: 'hws-ajax-navigation' } }));
            } catch (error) { fallback(target, error.message); }
        };
        snapshot.then(() => event.detail.resume()).catch(error => fallback(target, error.message));
    });
    document.addEventListener('turbo:load', () => {
        if (initial) {
            initial = false; remember();
            document.querySelectorAll('.elementor[data-elementor-id]').forEach(node => initialized.add(node));
            return;
        }
        if (failed) return;
        clearTimeout(timer); visiting = false;
        analytics();
        const detail = { from: previous, url: location.href, title: document.title };
        api.lastVisit = detail;
        emit('changed', detail);
        previous = location.href;
        if (!location.hash) {
            const focus = [...document.querySelectorAll('main,h1,[role="main"]')].find(node => node.getClientRects().length && getComputedStyle(node).visibility !== 'hidden');
            if (focus) { if (!focus.hasAttribute('tabindex')) focus.setAttribute('tabindex', '-1'); focus.focus({ preventScroll: true }); }
        }
    });
    document.addEventListener('DOMContentLoaded', () => {
        remember(); trackElementor();
        if (!window.Turbo) return;
        window.Turbo.config.forms.mode = 'off';
        window.Turbo.config.drive.prefetch = false;
        window.Turbo.cache.exemptPageFromCache();
        document.querySelectorAll('.elementor[data-elementor-id]').forEach(node => initialized.add(node));
    }, { once: true });
    // Disable form interception immediately, even if a form is submitted before DOM ready.
    document.addEventListener('submit', event => { event.target.setAttribute('data-turbo', 'false'); }, true);
    window.addEventListener('error', () => { if (visiting) fallback(target, 'script-error'); });
    window.addEventListener('unhandledrejection', () => { if (visiting) fallback(target, 'script-rejection'); });
})();
