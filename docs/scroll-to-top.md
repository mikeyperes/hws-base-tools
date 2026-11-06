# Scroll to top

- **Does:** a fixed round Back to top button appears after scrolling past 300px and smoothly returns to the top. Native button keyboard activation, visible focus, decorative SVG and reduced-motion support are included.
- **Switch:** WP Admin > Settings > HWS Base Tools > Features > Scroll to top. On by default (`hws_base_tools_scroll_to_top_enabled`); turning it off suppresses both automatic and shortcode markup and assets. Card settings are stored in `hws_base_tools_scroll_to_top_settings`.
- **Settings route:** `/wp-admin/options-general.php?page=hws-core-tools&tab=features`. Site-relative URL: `https://YOUR-DOMAIN/wp-admin/options-general.php?page=hws-core-tools&tab=features`. Canonical site's settings URL: https://wellnessvoice.com/wp-admin/options-general.php?page=hws-core-tools&tab=features (requires this release). No site settings are changed by publishing a release.
- **Use:** automatic on public frontend pages, or put `[hws_scroll_to_top]` in an Elementor footer Shortcode widget. The first rendered button claims the request; a shortcode rendered by the footer suppresses the late automatic fallback. Repeated shortcodes also print nothing. Requires the theme's normal `wp_footer` hook for automatic output.
- **Attributes:** each omitted value inherits the card setting. Factory defaults are `position="bottom_right"` (`bottom_right|bottom_left`), `offset="24"` (0-300px, both bottom and side), `size="48"` (44-120px), `background=""`, `icon_color=""`, `hover_background=""`, `hover_icon_color=""` (blank or #RGB/#RRGGBB), `show_after="300"` (0-20000px), `hide_mobile="0"` (0|1; hides at 767px and below), `style="default"` (`default|none`), `class=""` (space-separated CSS classes). Attributes cannot enable a disabled feature.
- **Example shortcode:** `[hws_scroll_to_top position="bottom_left" offset="32" size="56" background="#123456" icon_color="#ffffff" hover_background="#345678" hover_icon_color="#ffffff" show_after="500" hide_mobile="1" style="default" class="footer-top"]`.
- **Style:** blank backgrounds inherit Elementor `--e-global-color-primary` / hover `--e-global-color-secondary` with #2563eb / #1e40af fallbacks. Blank icons inherit `--e-global-color-background` with white fallback. Override `--hws-stt-bg`, `--hws-stt-icon`, `--hws-stt-hover-bg`, `--hws-stt-hover-icon`, `--hws-stt-offset`, `--hws-stt-size`. `style="none"` keeps the HTML and behavior while omitting the round fixed design rules.
- **Code:** `src/FrontendContent/ScrollToTop.php`, `assets/css/scroll-to-top.css`, `assets/js/scroll-to-top.js`.
- **Assets:** CSS and JavaScript print once with the first enabled button, never on an admin page or while off. No extra request or jQuery dependency. See [asset loading](asset-loading.md).
- **Design example:** the exact default runtime button and its CSS follow. For a static design draft, remove `hidden` to show it; the plugin script normally controls this attribute. Use `hws-scroll-to-top--bottom_left` instead of `--bottom_right` for left placement. Hover uses `:hover`, keyboard focus uses `:focus-visible`; the SVG follows the button color. The card uses the same markup and stylesheet for its preview. [Standalone design contract](../README.md#scroll-to-top).

```html
<button type="button" class="hws-scroll-to-top hws-scroll-to-top--bottom_right hws-scroll-to-top--default" data-hws-scroll-to-top data-show-after="300" aria-label="Back to top" hidden style="--hws-stt-offset:24px;--hws-stt-size:48px;"><svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="m6 14 6-6 6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
```

```css
.hws-scroll-to-top[hidden]{display:none!important}
.hws-scroll-to-top--default{position:fixed;bottom:calc(var(--hws-stt-offset,24px) + env(safe-area-inset-bottom,0px));right:calc(var(--hws-stt-offset,24px) + env(safe-area-inset-right,0px));z-index:9999;display:grid;place-items:center;width:var(--hws-stt-size,48px);height:var(--hws-stt-size,48px);min-width:44px;min-height:44px;padding:0;border:0;border-radius:50%;background:var(--hws-stt-bg,var(--e-global-color-primary,#2563eb));color:var(--hws-stt-icon,var(--e-global-color-background,#fff));box-shadow:0 3px 12px #0003;cursor:pointer;transition:background-color .2s,color .2s}
.hws-scroll-to-top--default.hws-scroll-to-top--bottom_left{right:auto;left:calc(var(--hws-stt-offset,24px) + env(safe-area-inset-left,0px))}
.hws-scroll-to-top--default:hover{background:var(--hws-stt-hover-bg,var(--e-global-color-secondary,#1e40af));color:var(--hws-stt-hover-icon,var(--e-global-color-background,#fff))}
.hws-scroll-to-top--default:focus-visible{outline:3px solid var(--e-global-color-primary,#2563eb);outline-offset:4px}
.hws-scroll-to-top svg{display:block;pointer-events:none}
@media(max-width:767px){.hws-scroll-to-top--hide-mobile{display:none!important}}
@media(prefers-reduced-motion:reduce){.hws-scroll-to-top{transition:none}}
```

For an interactive standalone draft, copy the [runtime JavaScript](../assets/js/scroll-to-top.js) below this button. It controls `hidden` based on `data-show-after`; reduced motion uses instant scrolling even if the theme enables smooth scrolling in CSS.

