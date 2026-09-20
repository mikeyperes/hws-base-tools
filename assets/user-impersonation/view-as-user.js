(function () {
    'use strict';

    var banner = document.querySelector('[data-hws-view-as-banner]');
    if (!banner) {
        return;
    }

    var requestKey = banner.getAttribute('data-request-key') || '';
    var requestToken = banner.getAttribute('data-request-token') || '';
    if (!requestKey || !requestToken) {
        return;
    }

    var root = document.documentElement;
    root.classList.add('hws-view-as-root');
    if (document.body && document.body.classList.contains('admin-bar')) {
        root.classList.add('hws-view-as-with-admin-bar');
    }

    function tokenizedUrl(value) {
        if (!value || /^(?:#|javascript:|mailto:|tel:)/i.test(value)) {
            return value;
        }

        try {
            var url = new URL(value, window.location.href);
            if (url.origin !== window.location.origin || !/^https?:$/.test(url.protocol)) {
                return value;
            }
            url.searchParams.set(requestKey, requestToken);
            return url.toString();
        } catch (error) {
            return value;
        }
    }

    function prepareForm(form) {
        if (!form || !form.action) {
            return;
        }

        var action = tokenizedUrl(form.action);
        if (action === form.action && !action.includes(window.location.origin)) {
            return;
        }

        form.action = action;
        var input = form.querySelector('input[name="' + requestKey + '"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = requestKey;
            form.appendChild(input);
        }
        input.value = requestToken;
    }

    document.addEventListener('click', function (event) {
        var link = event.target && event.target.closest ? event.target.closest('a[href]') : null;
        if (link) {
            link.href = tokenizedUrl(link.href);
        }
    }, true);

    document.addEventListener('submit', function (event) {
        prepareForm(event.target);
    }, true);

    document.querySelectorAll('form').forEach(prepareForm);

    if (typeof window.ajaxurl === 'string') {
        window.ajaxurl = tokenizedUrl(window.ajaxurl);
    }
    if (window.wpApiSettings && typeof window.wpApiSettings.root === 'string') {
        window.wpApiSettings.root = tokenizedUrl(window.wpApiSettings.root);
    }

    if (window.jQuery && typeof window.jQuery.ajaxPrefilter === 'function') {
        window.jQuery.ajaxPrefilter(function (options) {
            if (options && typeof options.url === 'string') {
                options.url = tokenizedUrl(options.url);
            }
        });
    }

    if (window.fetch) {
        var originalFetch = window.fetch;
        window.fetch = function (input, init) {
            if (typeof input === 'string' || input instanceof URL) {
                input = tokenizedUrl(String(input));
            } else if (window.Request && input instanceof Request) {
                input = new Request(tokenizedUrl(input.url), input);
            }
            return originalFetch.call(this, input, init);
        };
    }

    if (window.XMLHttpRequest && window.XMLHttpRequest.prototype.open) {
        var originalOpen = window.XMLHttpRequest.prototype.open;
        window.XMLHttpRequest.prototype.open = function (method, url) {
            var args = Array.prototype.slice.call(arguments);
            args[1] = tokenizedUrl(String(url));
            return originalOpen.apply(this, args);
        };
    }
}());
