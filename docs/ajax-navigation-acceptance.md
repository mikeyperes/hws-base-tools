# AJAX Navigation acceptance report

2026-11-01 00:45:28 EST

## Outcome

Built and tested, but 13.47.0 is **not released**. The feature is OFF on podcast.michaelperes.com. The existing version header remains 13.46.1 until the acceptance gate passes; this is an unreleased source candidate, not a 13.46.1 distribution release. No tag or GitHub Release was created.

## Browser and scope

Server 236 system Google Chrome 156.0.8078.79 through the shared Playwright package, in disposable browser contexts. The feature was enabled only during each run and disabled by cleanup. No saved browser profile was used. Analytics used the explicitly authorized stub gtag; no real GA4 delivery is claimed.

## Page matrix

- https://podcast.michaelperes.com/: HTTP 200 direct load; title, description and canonical match response source; swapped title/description/canonical/Open Graph/JSON-LD/body classes/widget inventory match the direct document; all 192 widget ready hooks and mobile menu open/close passed; one document, one changed event and one stub page view.
- https://podcast.michaelperes.com/a-conversation-with-craig-guarraci/: HTTP 200 direct load; title, description and canonical match response source; swapped title/description/canonical/Open Graph/JSON-LD/body classes/widget inventory match the direct document; all 101 widget ready hooks and mobile menu open/close passed; one document, one changed event and one stub page view.
- https://podcast.michaelperes.com/profile/craig-guarraci/: HTTP 200 direct load; title, description and canonical match response source; swapped title/description/canonical/Open Graph/JSON-LD/body classes/widget inventory match the direct document; all 49 widget ready hooks and mobile menu open/close passed; one document, one changed event and one stub page view.
- https://podcast.michaelperes.com/voices/: HTTP 200 direct load; title, description and canonical match response source; swapped title/description/canonical/Open Graph/JSON-LD/body classes/widget inventory match the direct document; all 103 widget ready hooks and mobile menu open/close passed; one document, one changed event and one stub page view.
- https://podcast.michaelperes.com/episodes/: HTTP 200 direct load; title, description and canonical match response source; swapped title/description/canonical/Open Graph/JSON-LD/body classes/widget inventory match the direct document; all 204 widget ready hooks and mobile menu open/close passed; one document, one changed event and one stub page view.
- https://podcast.michaelperes.com/about/: HTTP 200 direct load; title, description and canonical match response source; swapped title/description/canonical/Open Graph/JSON-LD/body classes/widget inventory match the direct document; all 37 widget ready hooks and mobile menu open/close passed; one document, one changed event and one stub page view.
- https://podcast.michaelperes.com/category/technology/: HTTP 200 direct load; title, description and canonical match response source; swapped title/description/canonical/Open Graph/JSON-LD/body classes/widget inventory match the direct document; all 116 widget ready hooks and mobile menu open/close passed; one document, one changed event and one stub page view.
- https://podcast.michaelperes.com/?s=technology: HTTP 200 direct load; title, description and canonical match response source; swapped title/description/canonical/Open Graph/JSON-LD/body classes/widget inventory match the direct document; all 66 widget ready hooks and mobile menu open/close passed; one document, one changed event and one stub page view.
- https://podcast.michaelperes.com/contact/: HTTP 200 direct load; title, description and canonical match response source; swapped title/description/canonical/Open Graph/JSON-LD/body classes/widget inventory match the direct document; all 32 widget ready hooks and mobile menu open/close passed; one document, one changed event and one stub page view.

Ten swaps plus Back and Forward completed without JavaScript exceptions or fallback. All 937 tested widget instances hit the ready hook. Back restored the tested 650-pixel scroll position and Forward restored the destination at the top. The window token survived throughout.

## Interaction and safety checks

- Elementor category taxonomy filter: one refresh-loop request, HTTP 200, active state updated, results changed from nine to two.
- Existing published mobile-menu popup: not emitted on normal tested pages; its rendered template was injected into an incoming response fixture, then opened and closed after a swap. No display conditions or CMS content were edited.
- Sticky and scrolling motion effects: native Elementor markup/dependency response fixtures, two round trips. Sticky had one active instance, motion transform changed with scroll, and both cleaned up after leaving.
- Permanent DOM element: exact object identity and custom state survived; will-change then changed fired once each.
- Permanent Elementor menu: exact node and open state survived; zero repeated ready hooks; existing toggle still closed it afterward.
- New head/body JavaScript: executed in dependency order, once across repeat visits. Page CSS loaded and was removed when leaving.
- Homepage filtering/search worked after returning by swap; an impossible search produced the empty state.
- GET search form and POST fixture used native navigation. Cart paths, file links, opt-out areas/links and admin-bar areas used normal navigation. External target=_blank opened separately.
- HTTP 500 and timeout fixtures fell back to a normal document load. The deliberate HTTP 500 produces an expected browser resource-error log only in that negative test.
- Rapid overlapping visits finished at the latest requested URL while preserving the document.
- Logged-in/editor/preview/feed/control endpoints and commerce request guards have PHP tests. No real administrator session was used to navigate the frontend.

## LiteSpeed repeat

Original settings: JS minify OFF, CSS minify OFF, combine OFF, defer/delay OFF. Enabled only JS and CSS minification for the repeat, then restored the exact original values. Runtime scripts are excluded by feature-owned LiteSpeed filters. Combining/defer/delay remain disabled and are not claimed tested.

- Homepage: 15 minified JS URLs and 21 minified CSS URLs.
- About: 16 minified JS URLs and 16 minified CSS URLs.
- Episodes: 17 minified JS URLs and 20 minified CSS URLs.

Direct and swapped metadata, body classes, widgets and sampled typography matched; one document and one stub page view per swap. Zero JavaScript exceptions, console errors or repeated script requests after the initial document.

## PHP and admin

- Final focused unit check: 59 passed.
- Final real WordPress HTML parser check: 6 passed, including script-like text inside a script and page stylesheet ownership.
- Existing suite earlier in the task: 817 passed, zero failed. It was not rerun after the later focused changes.
- New tab registration, Operations group, Core toggle/cards, exclusions, analytics owner selection and test checklist rendered successfully.
- The settings controller uses Core nonce and manage_options protection.
- The bundled Core remains 3.47.0, matching upstream main; Core was not changed.

## Asset accounting

Before: no navigation assets existed in the plugin. After, while OFF: zero on homepage, article, feature page and admin tab. While ON: eligible public homepage, episode/article and feature pages receive the local adapter plus local Turbo and one settings object; the admin tab receives no Turbo or navigation runtime. No new frontend CSS file is added. Assets have content-based cache versions.

## Checks that prevent release

1. A real contact-form submission was attempted both after a swap and on a direct load. Both reached Elementor and returned the same existing error: `reCAPTCHA V3 validation failed, suspected as abusive usage`. Required-field validation and normal Elementor POST ownership worked, but successful delivery is unverified. CAPTCHA was not bypassed or reconfigured.
2. The full nine-page matrix logged one external Google message: `Framing https://www.google.com/ violates the following report-only Content Security Policy directive: frame-ancestors self`. It states no action was taken, but the requested strict zero-console-error condition was not met. The optimized repeat had zero console errors.

The initial implementation response-resume defect and later detached Elementor loop callback defect were fixed in this task and retested. They are not open blockers. A global CAPTCHA/security change is outside the feature scope and was not made.

## Evidence

The machine-readable [results](ajax-navigation-results.json) record per-URL outcomes and fixture results. Raw browser scripts, console traces, JSON results and request-scoped receipts remain in `/tmp/ajax-1-evidence/` on server 236. This folder is disposable evidence, not plugin source.
