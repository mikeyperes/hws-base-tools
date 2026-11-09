# Changelog

## 13.73.0 - 2026-11-08

- Add optional 1–24 px reading-bar height and automatic light/dark background contrast colors on all site types. Existing design heights, fixed color, targeting and defaults remain unchanged. Bundles Hexa WP Core 3.59.1.

## 13.72.4 - 2026-11-08

- Encode masked-login redirect_to destinations so targets with multiple query parameters survive expired-link recovery and sign-in.


## 13.72.3 - 2026-11-08

- Redirect signed-out used or expired links to the masked login form with the destination preserved and a short expiration notice, avoiding the admin-ajax error handler.


## 13.72.2 - 2026-11-08

- Preserve validated same-site redirect_to destinations for logged-in, used, and expired one-click login links.


## 13.69.0
Scroll to top: new "Layered depth" style chosen in the feature settings. A shaded disc with an inner highlight and layered shadow, a ring that fills with the reader's scroll progress, a lift on hover and a press-in on click, plus a progress ring color setting (blank uses the Elementor accent global). The round button stays the default; reduced motion turns the motion off. The settings card shows a real sample of each style. Bundles Core 3.56.1.

## 13.68.0
Reading progress bar: the top bar now uses the highest possible stacking layer (z-index 2147483647, important) so sticky headers, Elementor sections, cookie banners and popups never cover it. Bundles Core 3.56.1.

## 13.67.0
Scroll to top: enabled-by-default accessible round button, Features settings, Elementor footer shortcode with duplicate suppression, inherited global colors, reduced motion and mobile visibility. Includes markup/CSS documentation and runtime unit tests. Bundles Core 3.56.1.

## 13.57.1
Fix stylesheet deduplication outside the_content and cached no-JavaScript forms. Validate signed config, add subscriber deletion/paging and disable CLI command, enrich notification metadata, and cover REST/admin-post/concurrent entry points. Bundles Core 3.50.1.

## 13.57.0
Newsletter signup collector, disabled by default, with Core 3.50.0 collection storage, admin counts/recent rows, safe CSV, notifications, protection and privacy hooks.

## 13.72.1 - 2026-11-08

- Bundle Hexa WP Core 3.59.1 with explicit avatar provider ownership.
