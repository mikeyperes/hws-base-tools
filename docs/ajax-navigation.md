# AJAX Navigation (acceptance candidate)

This feature is off by default. Enable it in Base Tools > Operations > AJAX Navigation only after testing the site. The tab uses the existing Core tab registry, cards, toggle and protected AJAX controller. Settings live in `hws_base_tools_ajax_navigation`; only administrators can save them.

## How it works

Turbo Drive 8.0.23 is bundled locally in `assets/ajax-navigation/vendor/`, with its MIT license, exact npm source and integrity digest. No CDN or hand-written page swapper is used. Turbo owns requests, body replacement, history, scroll restoration and permanent elements. Snapshot caching and hover prefetch are disabled so WordPress nonces, forms, personalization and widget state are not restored from stale HTML.

On eligible public GET requests, WordPress HTML Tag Processor adds `data-turbo-eval="false"` to scripts. Browsers ignore this attribute on normal loads. During a swap the adapter loads new external assets in document order, never reloads a known URL, and executes each distinct inline script once. WordPress localized configuration can change with the page because its content changes. Turbo merges styles, and the adapter removes obsolete head styles and synchronizes page metadata, JSON-LD and document attributes. Native View Source is a separate request, not the in-memory DOM; compare both against a fresh direct visit.

Base Tools refuses to start when SMP Podcast's player is enabled with its own AJAX navigation switch on. The AJAX Navigation tab explains the conflict, and saving the Base Tools switch reports the blocked state. Changing the player enable/navigation setting invalidates LiteSpeed pages while Base Tools is enabled, so stale cached startup state is discarded. No player setting is changed automatically. Disable the player's AJAX navigation setting before testing or enabling Base Tools.

All logged-in visitors use normal navigation, including administrators, editors, Elementor editor/preview and the admin bar. Non-GET requests, feeds, downloads, WordPress control endpoints, previews, WooCommerce cart/checkout/account, errors, other origins and explicit opt-outs use normal loads. Custom commerce/login paths must be added to exclusions. Network errors, missing opt-in metadata, script errors and the 12-second watchdog fall back to the destination URL. A destination without the feature enabled cannot be swapped.

## Opt-out markup

```html
<a href="/special/" data-turbo="false">Normal navigation</a>
<section data-turbo="false"><a href="/special/">Normal navigation</a></section>
<a href="/special/" data-hws-ajax="false">Normal navigation</a>
<div class="no-ajax"><a href="/special/">Normal navigation</a></div>
```

All forms retain native/plugin-owned submission. Turbo form interception is disabled, including GET search forms. Ordinary links to search results remain eligible. A PHP integration can return false from `hws_base_tools_ajax_navigation_allowed` to require a full load.

## Permanent elements

```html
<div id="my-persistent-player" data-turbo-permanent>Player content</div>
```

The exact stable, unique ID and permanent attribute must appear on both pages. Turbo preserves the actual DOM node, event handlers and state. A permanent element's scripts should initialize on its initial page only. Elementor handlers beneath permanent nodes are not destroyed.

## JavaScript event API

```js
document.addEventListener('hws:page-will-change', event => {
  // event.detail.from and event.detail.to
});
document.addEventListener('hws:page-changed', event => {
  // event.detail.from, event.detail.url, event.detail.title
});
window.HwsAjaxNavigation.register('my-plugin', {
  before() { /* release page-owned handlers */ },
  after(document) { /* initialize new content */ }
});
```

`will-change` runs immediately before Turbo replaces the body. `changed` runs after assets and Elementor restart, once per completed swap, including Back and Forward. Neither fires as an extra page view on the initial document load. Adapter exceptions trigger a normal load. `hws:page-fallback` carries `{url, reason}` for diagnostics. Existing SMP frontends receive their established `smp:content-ready` re-mount event as a compatibility bridge. `HwsAjaxNavigation.lastVisit` reports the latest completed navigation or fallback reason.

## Elementor lifecycle

WordPress installations without the native HTML Tag Processor use normal navigation. The adapter observes Elementor's initial frontend event, wraps handler construction to retain instances, and destroys page-owned handlers before removal. It retains the original frontend and Pro registries, refreshes per-page configuration and cached body/device nodes, then attaches document classes once per new document root. Callbacks for detached widgets are ignored when old asynchronous loop requests finish, and handler destruction is idempotent. This runs Core and Pro hooks for widgets, loop grids, motion effects, sticky, menus, forms and popup documents without repeatedly booting Elementor's global listeners. The background lazy-loading observer is recreated per body. This integration depends on Elementor internals and requires regression tests after updates.

## Analytics ownership

Select one analytics owner in the tab. Manual (the default) calls `gtag('event', 'page_view', ...)` once per completed swap when gtag exists. If gtag is absent but an existing dataLayer array is present, it pushes one `{event: 'page_view', page_location, page_title, page_referrer}` event for GTM. If both are present, gtag owns its queue; no second direct dataLayer event is added. With neither present, it creates neither and does nothing. Disable the browser-history page-view option in GA4 Enhanced Measurement for manual mode. Alternatively choose History, which sends no manual event and lets GA4 Enhanced Measurement own history views. Never enable both owners. The requested acceptance uses a stub gtag and proves exactly one call; actual GA4 delivery is outside that stub test and still needs a connected property and consent.

## LiteSpeed Cache

The module automatically excludes `assets/ajax-navigation/navigation.js` and `assets/ajax-navigation/vendor/turbo.js` from JavaScript optimization, defer and delay through LiteSpeed filters while enabled. Keep Elementor's per-page configuration and widget scripts in document order; disable combining of page-dependent scripts if necessary. CSS aggregation must not combine different pages into a permanently retained stylesheet. Minification is acceptable when it preserves script attributes and execution order. Purge caches when toggling the feature or changing exclusions. A response containing deferred `text/litespeed` script blocks falls back to a full load rather than pretending those scripts ran. Record the exact tested settings in the acceptance report.

## Known limits and release gate

Plugins that initialize only on DOMContentLoaded must register lifecycle adapters. Replaying all DOMContentLoaded or window load handlers would create double bindings, so these events are deliberately not replayed. Unknown widgets, third-party scripts, consent systems, CSP nonces, automatic popups and code snippets need site-specific verification. AJAX navigation owned by another plugin must be disabled before enabling this one. This feature does not make every arbitrary plugin compatible automatically.

The podcast site's initial baseline has another player-owned AJAX navigation setting enabled and Site Kit Analytics unconnected. Analytics acceptance uses the expressly authorized stub gtag; it does not claim real GA4 delivery. The startup guard enforces one navigation owner. Browser acceptance temporarily disables player navigation and restores its original value when Base Tools is turned off. Do not publish 13.47.0 until every requested check passes. See the [acceptance report](ajax-navigation-acceptance.md) and [machine-readable results](ajax-navigation-results.json) for actual outcomes and release blockers.

## Automated tests

`php tests/ajax-navigation-unit.php` checks defaults, path boundaries, malformed exclusions, encoded control paths, request guards, commerce endpoints, extension opt-out and inert-script output. Browser testing must independently compare direct and swapped pages and exercise interactive widgets, failures, permanent nodes, scroll, focus and analytics.

The [analytics and navigation ownership follow-up](ajax-navigation-followup.md) records the later isolated browser tests and conflict notice proof.
