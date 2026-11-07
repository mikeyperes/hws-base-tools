(function () {
    'use strict';
    function init() {
        document.querySelectorAll('[data-hws-scroll-to-top]').forEach(function (button) {
            if (button.dataset.hwsBound === '1') return;
            button.dataset.hwsBound = '1';
            var pending = false;
            var ring = button.querySelector('.hws-stt-ring-progress');
            function update() {
                pending = false;
                var top = window.scrollY || document.documentElement.scrollTop || 0;
                button.hidden = top <= Number(button.dataset.showAfter || 0);
                if (ring) {
                    // The ring path is 100 units long, so the offset is the unread share.
                    var room = document.documentElement.scrollHeight - window.innerHeight;
                    var progress = room > 0 ? Math.min(1, top / room) : 0;
                    ring.style.strokeDashoffset = String(Math.round((1 - progress) * 1000) / 10);
                }
            }
            function schedule() {
                if (!pending) { pending = true; window.requestAnimationFrame(update); }
            }
            window.addEventListener('scroll', schedule, {passive: true});
            window.addEventListener('resize', schedule, {passive: true});
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
