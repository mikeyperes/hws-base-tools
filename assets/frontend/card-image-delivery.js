(() => {
  'use strict';
  const tracked = new WeakSet();
  const visible = new WeakSet();
  function updateHero(image, width) {
    const cap = Math.min(2560, Number(image.dataset.hwsHeroMax) || 2560);
    const quantize = value => Math.max(8, Math.floor(Math.min(cap, value) / 8) * 8);
    const widths = [...new Set([quantize(Math.ceil(width / 8) * 8), quantize(width * 2)])];
    const endpoint = pixels => {
      const url = new URL(image.dataset.hwsHeroEndpoint, document.baseURI);
      url.searchParams.set('w', String(pixels));
      return url.href;
    };
    // The measured CSS slot precedes the candidates, avoiding viewport guesses.
    image.sizes = width + 'px';
    const candidates = widths.map(pixels => endpoint(pixels) + ' ' + pixels + 'w').join(', ');
    if (image.getAttribute('srcset') !== candidates) image.srcset = candidates;
    const fallback = endpoint(widths[0]);
    if (image.getAttribute('src') !== fallback) image.src = fallback;
  }
  function update(image) {
    if (!visible.has(image)) return;
    const width = image.getBoundingClientRect().width;
    if (width < 1) return;
    if (image.dataset.hwsHeroEndpoint) { updateHero(image, width); return; }
    const pixels = Math.min(768, Math.max(8, Math.floor(width * Math.min(2, window.devicePixelRatio || 1) / 8) * 8));
    // WordPress/optimizers may enrich cached markup after the widget renders.
    image.removeAttribute('srcset');
    image.removeAttribute('sizes');
    if (image.dataset.hwsCardPixels === String(pixels)) return;
    const url = new URL(image.dataset.hwsCardEndpoint, document.baseURI);
    url.searchParams.set('w', String(pixels));
    image.dataset.hwsCardPixels = String(pixels);
    image.src = url.href;
  }
  const sizes = 'ResizeObserver' in window ? new ResizeObserver(entries => entries.forEach(entry => update(entry.target))) : null;
  const viewport = 'IntersectionObserver' in window ? new IntersectionObserver(entries => entries.forEach(entry => {
    if (entry.isIntersecting) { visible.add(entry.target); update(entry.target); }
  }), {rootMargin: '300px'}) : null;
  function discover(root = document) {
    root.querySelectorAll('img[data-hws-card-endpoint], img[data-hws-hero-endpoint]').forEach(image => {
      if (tracked.has(image)) return;
      tracked.add(image);
      if (sizes) sizes.observe(image);
      if (viewport) viewport.observe(image);
      else { visible.add(image); update(image); }
    });
  }
  discover();
  window.addEventListener('resize', () => document.querySelectorAll('img[data-hws-card-endpoint], img[data-hws-hero-endpoint]').forEach(update), {passive: true});
  new MutationObserver(records => {
    if (records.some(record => record.addedNodes.length)) discover();
  }).observe(document.documentElement, {childList: true, subtree: true});
})();
