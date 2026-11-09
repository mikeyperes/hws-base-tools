# Reading progress bar

Features → Reading Progress Bar → Settings contains height and automatic contrast colors.

Height 0 preserves each design's original height. Heights 1–24 use whole pixels; 3 produces a 3 px bar. Automatic contrast is off by default. Existing fixed color, glow, scope and inline shortcode behavior remain unchanged. No migration writes new options on installation.

When enabled, the fixed top bar samples the element beneath its midpoint at the actual bar height on scroll, resize, load, page restore and content resize. Computed background colors are composited in hit-test paint order against white, including sections behind transparent fixed headers; relative luminance selects the configured light color on dark backgrounds or dark color on light backgrounds. The rail and glow follow the chosen color. Fixed headers count as the background when they cover the section beneath the bar.

Browsers cannot read image pixels from computed styles. For photographs, gradients, pseudo-element backgrounds or videos, optionally declare the background tone on an element or ancestor:

```html
<section data-hws-reading-progress-theme="dark">Dark image or video section</section>
<section data-hws-reading-progress-theme="light">Light image or gradient section</section>
```

No theme, template or site type is required for solid-background detection. The annotation describes the background, not the desired bar color. Without it, image and gradient sections use their computed background color and paint-layer fallback.

The fixed renderer remains `#hws-reading-progress` with `.hws-reading-progress__track` and `.hws-reading-progress__fill`. `--hws-reading-progress-height` overrides the track height. Existing color, soft and glow variables remain available. Adaptive output adds `data-hws-adaptive`, `data-hws-light-color`, `data-hws-dark-color` and the runtime `data-hws-background` state. Inline shortcode companions retain their existing themeable colors and 4 px default track.

Admin design samples use the runtime CSS and selected height. The two color controls preview their selected swatches. Saving settings uses the existing capability- and nonce-protected feature handler.
