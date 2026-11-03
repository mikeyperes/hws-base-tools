(() => {
  'use strict';
  const tracked = new WeakSet();
  const visible = new WeakSet();
  function update(image) {
    if (!visible.has(image)) return;
    const width = image.getBoundingClientRect().width;
    if (width < 1) return;
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
    root.querySelectorAll('img[data-hws-card-endpoint]').forEach(image => {
      if (tracked.has(image)) return;
      tracked.add(image);
      if (sizes) sizes.observe(image);
      if (viewport) viewport.observe(image);
      else { visible.add(image); update(image); }
    });
  }
  discover();
  window.addEventListener('resize', () => document.querySelectorAll('img[data-hws-card-endpoint]').forEach(update), {passive: true});
  new MutationObserver(records => {
    if (records.some(record => record.addedNodes.length)) discover();
  }).observe(document.documentElement, {childList: true, subtree: true});
})();
