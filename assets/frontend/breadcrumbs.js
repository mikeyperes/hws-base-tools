/* HWS Base Tools breadcrumbs: places the band directly under the site header.
 * A page that already shows its own breadcrumb (a shortcode or theme
 * placement) keeps it, and no second band is added. */
(function () {
    'use strict';

    function visible(element) {
        if (!element || !element.getBoundingClientRect) return false;
        var rect = element.getBoundingClientRect();
        return rect.width > 1 && rect.height > 1 && window.getComputedStyle(element).display !== 'none';
    }

    function selectors(bar) {
        try {
            var list = JSON.parse(bar.getAttribute('data-hws-header-selectors') || '[]');
            return Array.isArray(list) ? list.filter(function (s) { return typeof s === 'string' && s.length > 0 && s.length <= 240; }) : [];
        } catch (error) {
            return [];
        }
    }

    function place() {
        var source = document.querySelector('template[data-hws-breadcrumbs-template]');
        if (!source || !source.content || !source.content.firstElementChild) return;
        if (document.querySelector('[data-hws-breadcrumbs-placed]')) return;

        var manual = Array.prototype.some.call(document.querySelectorAll('.smpi-breadcrumbs-band,.smpi-breadcrumbs'), function (element) {
            return !element.closest('template') && visible(element);
        });
        if (manual) return;

        var bar = source.content.firstElementChild.cloneNode(true);
        var target = null;
        selectors(bar).some(function (selector) {
            try {
                var candidate = document.querySelector(selector);
                if (candidate && visible(candidate)) { target = candidate; return true; }
            } catch (error) { return false; }
            return false;
        });

        bar.setAttribute('data-hws-breadcrumbs-placed', '1');
        if (target && target.parentNode) target.insertAdjacentElement('afterend', bar);
        else if (document.body.firstElementChild) document.body.insertBefore(bar, document.body.firstElementChild);
        else document.body.appendChild(bar);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', place, { once: true });
    else place();
})();
