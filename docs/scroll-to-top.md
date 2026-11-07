# Scroll to top

- **Does:** a fixed round Back to top button appears after scrolling past 300px and smoothly returns to the top. Native button keyboard activation, visible focus, decorative SVG and reduced-motion support are included.
- **Switch:** WP Admin > Settings > HWS Base Tools > Features > Scroll to top. On by default (`hws_base_tools_scroll_to_top_enabled`); turning it off suppresses both automatic and shortcode markup and assets. Card settings are stored in `hws_base_tools_scroll_to_top_settings`.
- **Settings route:** `/wp-admin/options-general.php?page=hws-core-tools&tab=features`. Site-relative URL: `https://YOUR-DOMAIN/wp-admin/options-general.php?page=hws-core-tools&tab=features`. Canonical site's settings URL: https://wellnessvoice.com/wp-admin/options-general.php?page=hws-core-tools&tab=features (requires this release). No site settings are changed by publishing a release.
- **Use:** automatic on public frontend pages, or put `[hws_scroll_to_top]` in an Elementor footer Shortcode widget. The first rendered button claims the request; a shortcode rendered by the footer suppresses the late automatic fallback. Repeated shortcodes also print nothing. Requires the theme's normal `wp_footer` hook for automatic output.
- **Attributes:** each omitted value inherits the card setting. Factory defaults are `position="bottom_right"` (`bottom_right|bottom_left`), `offset="24"` (0-300px, both bottom and side), `size="48"` (44-120px), `background=""`, `icon_color=""`, `hover_background=""`, `hover_icon_color=""`, `ring_color=""` (blank or #RGB/#RRGGBB), `show_after="300"` (0-20000px), `hide_mobile="0"` (0|1; hides at 767px and below), `style="default"` (`default|depth|none`), `class=""` (space-separated CSS classes). Attributes cannot enable a disabled feature.
- **Example shortcode:** `[hws_scroll_to_top position="bottom_left" offset="32" size="56" background="#123456" icon_color="#ffffff" hover_background="#345678" hover_icon_color="#ffffff" show_after="500" hide_mobile="1" style="default" class="footer-top"]`.
- **Style:** blank backgrounds inherit Elementor `--e-global-color-primary` / hover `--e-global-color-secondary` with #2563eb / #1e40af fallbacks. Blank icons inherit `--e-global-color-background` with white fallback. Override `--hws-stt-bg`, `--hws-stt-icon`, `--hws-stt-hover-bg`, `--hws-stt-hover-icon`, `--hws-stt-ring`, `--hws-stt-offset`, `--hws-stt-size`. `style="depth"` (Layered depth) draws a shaded disc with an inner highlight and layered shadow, a decorative ring that fills with the reader's scroll progress (blank `ring_color` uses `--e-global-color-accent`), a lift on hover and a press-in on click; reduced motion removes the entrance, lift and ring animation. `style="none"` keeps the HTML and behavior while omitting the round fixed design rules.
- **Code:** `src/FrontendContent/ScrollToTop.php`, `assets/css/scroll-to-top.css`, `assets/js/scroll-to-top.js`.
- **Assets:** CSS and JavaScript print once with the first enabled button, never on an admin page or while off. No extra request or jQuery dependency. See [asset loading](asset-loading.md).
- **Design example:** the exact default runtime button and its CSS follow. For a static design draft, remove `hidden` to show it; the plugin script normally controls this attribute. Use `hws-scroll-to-top--bottom_left` instead of `--bottom_right` for left placement. Hover uses `:hover`, keyboard focus uses `:focus-visible`; the SVG follows the button color. Layered depth uses `hws-scroll-to-top--depth` and adds the ring SVG shown below as the button's first child; the script sets its `stroke-dashoffset` to the unread share of the page (100 at the top, 0 at the end). The card uses the same markup and stylesheet for its preview. [Standalone design contract](../README.md#scroll-to-top).

```html
<button type="button" class="hws-scroll-to-top hws-scroll-to-top--bottom_right hws-scroll-to-top--default" data-hws-scroll-to-top data-show-after="300" aria-label="Back to top" hidden style="--hws-stt-offset:24px;--hws-stt-size:48px;"><svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="m6 14 6-6 6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
```

```css
.hws-scroll-to-top[hidden]{display:none!important}
.hws-scroll-to-top--default,.hws-scroll-to-top--depth{position:fixed;bottom:calc(var(--hws-stt-offset,24px) + env(safe-area-inset-bottom,0px));right:calc(var(--hws-stt-offset,24px) + env(safe-area-inset-right,0px));z-index:9999;display:grid;place-items:center;width:var(--hws-stt-size,48px);height:var(--hws-stt-size,48px);min-width:44px;min-height:44px;padding:0;border:0;border-radius:50%;cursor:pointer}
.hws-scroll-to-top--default{background:var(--hws-stt-bg,var(--e-global-color-primary,#2563eb));color:var(--hws-stt-icon,var(--e-global-color-background,#fff));box-shadow:0 3px 12px #0003;transition:background-color .2s,color .2s}
.hws-scroll-to-top--default.hws-scroll-to-top--bottom_left,.hws-scroll-to-top--depth.hws-scroll-to-top--bottom_left{right:auto;left:calc(var(--hws-stt-offset,24px) + env(safe-area-inset-left,0px))}
.hws-scroll-to-top--default:hover{background:var(--hws-stt-hover-bg,var(--e-global-color-secondary,#1e40af));color:var(--hws-stt-hover-icon,var(--e-global-color-background,#fff))}
.hws-scroll-to-top--default:focus-visible{outline:3px solid var(--e-global-color-primary,#2563eb);outline-offset:4px}
.hws-scroll-to-top svg{display:block;pointer-events:none}
.hws-scroll-to-top--depth{--hws-stt-fill:var(--hws-stt-bg,var(--e-global-color-primary,#2563eb));--hws-stt-accent:var(--hws-stt-ring,var(--e-global-color-accent,#38bdf8));background:var(--hws-stt-fill);background:radial-gradient(120% 120% at 30% 18%,color-mix(in srgb,var(--hws-stt-fill) 76%,#fff) 0%,var(--hws-stt-fill) 52%,color-mix(in srgb,var(--hws-stt-fill) 78%,#000) 100%);color:var(--hws-stt-icon,var(--e-global-color-background,#fff));box-shadow:inset 0 1px 0 #ffffff38,inset 0 -3px 8px #00000059,0 0 0 1px #ffffff12,0 2px 4px #0000002e,0 12px 28px -8px #00000080,0 10px 30px -12px color-mix(in srgb,var(--hws-stt-accent) 75%,transparent);transition:transform .28s cubic-bezier(.2,.8,.2,1),box-shadow .28s,color .2s;animation:hws-stt-in .36s cubic-bezier(.2,.8,.2,1)}
.hws-scroll-to-top--depth .hws-stt-ring{position:absolute;inset:0;width:100%;height:100%;transform:rotate(-90deg);overflow:visible}
.hws-stt-ring circle{fill:none;stroke-width:2.4}
.hws-stt-ring-track{stroke:currentColor;stroke-opacity:.16}
.hws-stt-ring-progress{stroke:var(--hws-stt-accent);stroke-linecap:round;transition:stroke-dashoffset .12s linear,stroke .2s}
.hws-scroll-to-top--depth>svg:not(.hws-stt-ring){position:relative;transition:transform .28s cubic-bezier(.2,.8,.2,1)}
.hws-scroll-to-top--depth:hover{--hws-stt-fill:var(--hws-stt-hover-bg,var(--e-global-color-secondary,#1e40af));color:var(--hws-stt-hover-icon,var(--e-global-color-background,#fff));transform:translateY(-3px);box-shadow:inset 0 1px 0 #ffffff47,inset 0 -3px 8px #00000059,0 0 0 1px #ffffff1a,0 4px 8px #0000002e,0 18px 36px -10px #0000008c,0 14px 38px -12px color-mix(in srgb,var(--hws-stt-accent) 90%,transparent)}
.hws-scroll-to-top--depth:hover>svg:not(.hws-stt-ring){transform:translateY(-2px)}
.hws-scroll-to-top--depth:hover .hws-stt-ring-progress{stroke:currentColor}
.hws-scroll-to-top--depth:active{transform:translateY(0) scale(.94);transition-duration:.1s;box-shadow:inset 0 2px 6px #00000073,inset 0 -1px 0 #ffffff1f,0 0 0 1px #ffffff12,0 1px 2px #0000004d,0 4px 12px -6px #00000080}
.hws-scroll-to-top--depth:focus-visible{outline:3px solid var(--hws-stt-accent);outline-offset:4px}
@keyframes hws-stt-in{from{opacity:0;transform:translateY(10px) scale(.9)}}
@media(max-width:767px){.hws-scroll-to-top--hide-mobile{display:none!important}}
@media(prefers-reduced-motion:reduce){.hws-scroll-to-top,.hws-scroll-to-top svg,.hws-stt-ring-progress{transition:none;animation:none}.hws-scroll-to-top--depth:hover,.hws-scroll-to-top--depth:active,.hws-scroll-to-top--depth:hover>svg:not(.hws-stt-ring){transform:none}}
```

Layered depth ring (first child of the button when `style="depth"`):

```html
<svg class="hws-stt-ring" viewBox="0 0 48 48" aria-hidden="true" focusable="false"><circle class="hws-stt-ring-track" cx="24" cy="24" r="20.5" pathLength="100"/><circle class="hws-stt-ring-progress" cx="24" cy="24" r="20.5" pathLength="100" stroke-dasharray="100" stroke-dashoffset="100"/></svg>
```

The full stylesheet, both styles included, is [assets/css/scroll-to-top.css](../assets/css/scroll-to-top.css).

For an interactive standalone draft, copy the [runtime JavaScript](../assets/js/scroll-to-top.js) below this button. It controls `hidden` based on `data-show-after`; reduced motion uses instant scrolling even if the theme enables smooth scrolling in CSS.

