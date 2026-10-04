# HWS Base Tools Bug Log

## HWS-BASE-BUG-006 — Client draft links expired during article review

- Severity: High
- Symptom: Article review links stopped opening after 48 hours, and delivery could not retrieve a supported anonymous publication URL.
- Cause: Draft visibility and link generation used a creation-time window; no protected REST read field exposed the link.
- Patch: The existing opt-in site-code feature now returns stable publication-domain draft links without an age limit. Editors can read `hws_public_draft_url` over REST; disabling the feature or rotating its code revokes access. Private and password-protected posts are excluded. Stored status, cache isolation and noindex behavior remain intact.
- Guard: The focused public-draft fixture covers links older than a year, wrong codes, disabled features, private/password-protected posts and stored-status preservation.

## HWS-BASE-BUG-005 — Anyone could trigger the login-mask emergency actions

- Severity: High
- Symptom: `/?hws=repair` flushed rewrite rules and purged LiteSpeed, object and page caches for any visitor, and `/?hws=bypass` served the native login page past the mask.
- Impact: Repeated anonymous requests could keep every cache empty and force uncached page generation.
- Root cause: `Login_Masking::maybe_emergency()` ran on `init` for every request and checked only the `hws` action name.
- Patch: 13.3.6 requires the hardcoded `hws_key` emergency key. The key lives in source rather than settings, so recovery still works when options are unreadable; the Masked Login tab shows the keyed URLs.
- Guard: An isolated fixture confirms requests without the key, with a wrong or non-string key, or with an unknown action do nothing, and that the correct key returns the requested action.

## HWS-BASE-BUG-004 — wp-config writer could emit quote-bearing input as executable PHP

- Severity: Critical
- Symptom: The shared `WpConfigFile` writer escaped single quotes inside a generated PHP string but did not serialize the value as a PHP literal, so a quote-bearing value could terminate the generated assignment.
- Impact: Any caller that exposed the writer could turn a configuration update into a persistent PHP-code injection path.
- Root cause: The writer assembled `ini_set()` and `define()` statements by string interpolation.
- Patch: Hexa WP Core 3.7.3 uses `var_export()` for names and string values while preserving existing numeric and boolean scalar behavior; the bundle includes an inert syntax and execution regression fixture.
- Guard: The source-level security proof passes the exact quote-bearing payload through an isolated fixture and confirms the generated file remains valid PHP without executing the marker.

## HWS-BASE-BUG-003 — Author directory replaced the WordPress login with its nicename

- Severity: High
- Symptom: The signed author directory returned all 136 Her Forward users, but campaign integrity could not match the configured author even though it was the bridge actor (user ID 9).
- Impact: HWS Base Tools campaign operation 6871 stopped before generation after authentication and pagination had succeeded.
- Root cause: The custom route exposed `slug` from `user_nicename` but omitted `user_login`. Her Forward's configured author is the real WordPress login, whose nicename differs.
- Patch: The already HMAC-authenticated, `list_users`-gated author response now includes `login` from `user_login`; existing ID, display name, nicename, email and role fields remain unchanged.
- Guard: The focused suite requires the bridge-owned author directory to expose the real login identity. Publish must prefer it while retaining nicename compatibility.

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
