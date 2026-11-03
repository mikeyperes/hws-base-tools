# Newsletter 13.57.1 patch acceptance

Core 3.50.1. Executed 2026-11-03 00:32:10 EST.
- Core submission-store.php: 21 unit assertions passed.
- newsletter-integration.php: 84 assertions passed. Uses rest_do_request for POST/GET status codes 201/200/400/403/429; admin-post action hooks with captured wp_die; expired nonce fallback, malformed signed config, notification body, paging and protected deletion.
- newsletter-web-context.php: four assertions passed with WP_CLI undefined and outside the_content. Loads real WordPress APIs in installation bootstrap mode to avoid unrelated active-plugin redirects, then registers only the tested class autoloaders. Discards an indexer render, renders two forms, prints pending styles twice and checks exactly one complete stylesheet.
- newsletter-concurrency.php: committed socket-barrier test forks two independent database connections and dispatches simultaneous REST requests. Asserts 201/200 and one row.
- Live wellnessvoice.com HTTP probe: nonce GET 200; new POST 201; duplicate POST 200; invalid email 400; invalid nonce 403. Afterwards collector disabled, zero nl1 fixture rows, disabled POST 403.
- Notification delivery remains mocked; actual recipient inbox delivery is not claimed.
- PHP syntax checks passed for NewsletterModule and InlineStyles.

## Reproduce
Use a WordPress installation with the released plugin active. The integration suite temporarily enables the collector, creates unique fixture lists, then removes its rows and restores settings. Run in a quiet authorized acceptance window.
Replace administrator ID 1 with the site's administrator ID. PHP needs pcntl for the two-process test.

    php wp-content/plugins/hws-base-tools/lib/hexa-wordpress-plugin-core/tests/submission-store.php
    php wp-content/plugins/hws-base-tools/tests/newsletter-web-context.php
    wp eval-file wp-content/plugins/hws-base-tools/tests/newsletter-integration.php --user=1

The concurrency helper is invoked by the integration suite, not run by itself.

## Previous release evidence (13.57.0)
# Newsletter 13.57.0 acceptance evidence

Executed 2026-11-03, fixed EST. Core 3.50.0.
- Core tests/submission-store.php: 21 assertions passed.
- Base Tools tests/newsletter-integration.php: 48 assertions passed.
- PHP syntax: SubmissionStore, NewsletterModule and ShortcodeCatalog passed.
- Core package-integrity.php: hash matches executable source.
- Live browser form: created, then normalized duplicate. First observed input-to-result 1310 ms.
- Simultaneous live POSTs: 201 created and 200 duplicate; 633 ms combined endpoint completion.
- Admin list: total 2, IP and fixed EST timestamps visible.
- Admin CSV: HTTP 200 text/csv; name =NL1 browser exported with leading apostrophe.
- Invalid export nonce: HTTP 403. Unauthenticated export: no CSV (site responded 404).
- Rounded CSS: exactly one style block in visible content; computed 24px radius.
- Mobile 390px viewport: form 358px, within viewport.
- Notifications: WordPress mail handoff mocked; new only, no duplicates, blank disables, failed handoff preserves row.
- Turnstile: inherited widget and missing-token rejection covered; real provider success not exercised because site provider is none.
- No claim of recipient inbox delivery or production Turnstile-provider validation.
- No-JavaScript POST fallback: HTTP 200, success message and back link.
- Client error recovery: visible error, button enabled for retry.
- Fixture cleanup removes temporary page, nl1-live rows and integration test list rows; collector restored disabled.

## Newsletter asset inventory
Before this feature, newsletter.js and newsletter style blocks did not exist.
After: home and ordinary article without shortcode have no newsletter asset.
Feature page: newsletter.js only; selected rounded inline CSS once; Turnstile absent with site's provider none.
Newsletter admin tab: selected-style preview only, Core code-display assets for CSS; no newsletter.js.
Other existing Base Tools assets are unaffected.
