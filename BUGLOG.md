# HWS Base Tools Bug Log

## HWS-BASE-BUG-002 — Protected users path intercepted author discovery before the bridge

- Severity: High
- Symptom: HWS Base Tools 13.2.18 was active and advertised author support, but the signed author call still returned `Sorry, you are not allowed to list users.`
- Impact: HWS Base Tools campaign operations could not pass author resolution on WordPress sites whose security layer protects route paths containing the core users resource.
- Root cause: The bridge-owned author callback remained behind `/external-publishing/wp/v2/users`. Her Forward rejected that outer route before the registered bridge callback could execute, so direct author enumeration inside the callback was unreachable.
- Patch: External Publishing now exposes `/external-publishing/authors` with its own HMAC authentication and `list_users` permission callback, returning the same bounded author directory without a protected core users path.
- Guard: The focused suite requires the dedicated authors route, callback, and capability check. Publish must route HWS author discovery to this endpoint rather than the generic WordPress proxy.

## HWS-BASE-BUG-001 — Signed publishing bridge could not resolve authors

- Severity: High
- Symptom: A valid HMAC-authenticated External Publishing request advertised author support, but `GET /external-publishing/wp/v2/users` returned `Sorry, you are not allowed to list users.`
- Impact: Every HWS Base Tools campaign delivery stopped at author resolution before article generation.
- Root cause: The bridge re-dispatched author discovery through the nested WordPress `/wp/v2/users` controller. Security layers can reject that nested request even after the outer bridge has authenticated an administrator with `list_users`.
- Patch: The allowlisted users resource now returns a bounded, paginated author directory directly after the existing HMAC and `list_users` checks.
- Guard: The focused suite asserts that user discovery uses the bridge-owned author directory and does not introduce a direct nested users proxy.
