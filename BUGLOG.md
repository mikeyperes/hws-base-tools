# HWS Base Tools Bug Log

## HWS-BASE-BUG-001 — Signed publishing bridge could not resolve authors

- Severity: High
- Symptom: A valid HMAC-authenticated External Publishing request advertised author support, but `GET /external-publishing/wp/v2/users` returned `Sorry, you are not allowed to list users.`
- Impact: Every HWS Base Tools campaign delivery stopped at author resolution before article generation.
- Root cause: The bridge re-dispatched author discovery through the nested WordPress `/wp/v2/users` controller. Security layers can reject that nested request even after the outer bridge has authenticated an administrator with `list_users`.
- Patch: The allowlisted users resource now returns a bounded, paginated author directory directly after the existing HMAC and `list_users` checks.
- Guard: The focused suite asserts that user discovery uses the bridge-owned author directory and does not introduce a direct nested users proxy.
