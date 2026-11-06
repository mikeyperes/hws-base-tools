(function () {
    'use strict';
    function init() {
        document.querySelectorAll('[data-hws-scroll-to-top]').forEach(function (button) {
            if (button.dataset.hwsBound === '1') return;
            button.dataset.hwsBound = '1';
            var pending = false;
            function update() {
                pending = false;
                button.hidden = (window.scrollY || document.documentElement.scrollTop || 0) <= Number(button.dataset.showAfter || 0);
            }
            window.addEventListener('scroll', function () {
                if (!pending) { pending = true; window.requestAnimationFrame(update); }
            }, {passive: true});
            window.addEventListener('pageshow', update);
            button.addEventListener('click', function () {
                var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                // Instant bypasses a theme's CSS scroll-behavior:smooth too.
                window.scrollTo({top: 0, left: 0, behavior: reduced ? 'instant' : 'smooth'});
                var target = document.querySelector('main, [role="main"]') || document.body;
                var previous = target.getAttribute('tabindex');
                target.setAttribute('tabindex', '-1');
                target.focus({preventScroll: true});
                if (previous === null) target.removeAttribute('tabindex');
                else target.setAttribute('tabindex', previous);
            });
            update();
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, {once: true});
    else window.setTimeout(init, 0);
})();
