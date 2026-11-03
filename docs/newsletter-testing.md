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
