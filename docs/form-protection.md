# Form protection

This engine validates only individual submissions inside Elementor Pro, Contact Form 7, Gravity Forms and WPForms. It never installs a firewall, blocks a visitor, changes a page response or replaces the form plugin's own validation.

## Settings and CLI

The existing `hws_base_tools_form_protection` option stores public settings only. New sites keep `enabled=false`. Honeypots and too-fast default on inside that master switch; `header_check_enabled` defaults off to preserve upgraded sites. `bot_check` is exactly `none`, `turnstile` or `recaptcha`. At first Turnstile key setup it defaults to Turnstile; an explicitly saved `none` remains off. Legacy `turnstile_enabled` writes still work and the returned provider flags are mutually exclusive.

```
wp hws forms protection status
wp hws forms protection set --user=<administrator> --enabled=1 --honeypots_enabled=1 --too_fast_enabled=1 --header_check_enabled=1 --min_fill_seconds=3
wp hws forms protection set --user=<administrator> --bot_check=turnstile --site_key=<public-site-key>
wp hws forms protection set --user=<administrator> --bot_check=recaptcha --recaptcha_site_key=<public-site-key> --recaptcha_score_threshold=0.5
wp hws forms protection set --user=<administrator> --bot_check=none
wp hws forms blocked --limit=100
wp hws forms signals --limit=100
```

Secrets are saved only through the administrator settings handler into Core CredentialStore, using `hws-base-tools/form_turnstile_secret` and `hws-base-tools/form_recaptcha_secret`. Blank password controls preserve the existing secret. Status and activity logs return presence flags only; secret arguments are rejected by the CLI. The settings AJAX endpoint uses Core's nonce and capability enforcement. The existing Form protection tab uses plain single-column controls.

## Checks and reasons

- Honeypots: `Honeypots::names(form_key)` selects two distinct names by a stable SHA-256 ordering of `website`, `company_url`, `fax`, `address_2`, `phone_alt`, `middle_name`. The names are nested under `hws_form_honeypots[name]` to avoid collisions with legitimate form fields. Hidden, empty, nonfocusable fields are injected per form. Either filled field, an array value or malformed group rejects as `honeypot`.
- Too-fast: the existing form-bound HMAC challenge enforces `min_fill_seconds` (1 to 30, default 3), expires after two hours and rejects tampering/cross-form/future tokens. Reasons: `missing_token`, `invalid_token`, `too_fast`. Disabling only this check skips tokens entirely, including on the challenge endpoint.
- Headers: at least one of Origin / Referer must exist, and every supplied value must be an HTTP(S) URL on the site's exact host. Reasons: `origin_referer_missing`, `origin_invalid`, `referer_invalid`. Empty user agents reject as `user_agent_missing`; case-insensitive curl, python, wget, go-http, java, libwww or bot reject as `user_agent_script`. Supplied Sec-Fetch-Site must equal same-origin, otherwise `sec_fetch_site_invalid`. Missing Accept-Language records `accept_language_missing` as a signal without rejecting, and is excluded from blocked attempts.
- Bot check: missing/array/empty responses reject as `turnstile_missing` or `recaptcha_missing`. Verification requires success, exact hostname and action `hws-form` for Turnstile or `hws_form` for reCAPTCHA; transport errors, absent keys, invalid response/host/action reject as `turnstile` or `recaptcha_invalid`. reCAPTCHA requires a numeric score from 0 to 1; scores below `recaptcha_score_threshold` (0 to 1, default 0.5) reject as `recaptcha_score`. The unselected provider is never loaded or verified.

Order: honeypots, token/time, headers, selected provider. Only the first rejection reason is recorded. Missing-language signals can accompany an accepted or rejected submission. Disabling a check bypasses it independently; disabling the master switch bypasses all checks and renders no protection fields or assets. Switching off all individual checks also passes.

## Native bindings and markup

- Elementor: `elementor/widget/render_content`, bound to native hidden post_id and widget ID; `elementor_pro/forms/validation` adds only a form error.
- Contact Form 7: `wpcf7_form_elements` and `wpcf7_spam`; an existing spam result stays unchanged.
- Gravity Forms: `gform_form_tag`, `gform_validation`, `gform_validation_message`.
- WPForms: `wpforms_display_submit_before`, `wpforms_process_initial_errors`.

```html
<div data-hws-form-protection="elementor:123:abc1234" data-bot-check="none" data-sitekey="">
  <div hidden aria-hidden="true">
    <label>Leave this field empty<input type="text" name="hws_form_honeypots[website]" value="" autocomplete="off" tabindex="-1"></label>
    <label>Leave this field empty<input type="text" name="hws_form_honeypots[fax]" value="" autocomplete="off" tabindex="-1"></label>
  </div>
  <input type="hidden" name="hws_form_token" value="">
</div>
```

The example names are illustrative; actual names are deterministic per form. Markup has no visual styling to override. Provider widgets render only inside the selected form. Protection assets are registered early without being enqueued globally, so Elementor can replay cached asset handles. Protected form widgets render dynamically; settings saves invalidate Elementor markup through its public cache API. Signed challenges are fetched uncached, including for dynamically inserted forms, and renewed on reset. reCAPTCHA executes immediately before submission, then resumes the form plugin's original submit path; provider-load failure displays a retryable error. Turnstile uses explicit per-form rendering and resets on form reset.

## Logs and privacy

The existing capped attempt table stores only UTC time, form key, IP and reason. No submitted field values or provider tokens are logged. Retention defaults to 14 days, is configurable from 1 to 90 days, and is capped at 5,000 combined records. The blocked REST reader requires an administrator and is read-only. Non-blocking language signals are available separately through `wp hws forms signals`.

## Tests

`php tests/form-protection-unit.php` covers every check and reason, stable field selection, independent switches, provider host/action/score/transport failures, protected credential persistence, no-secret status, defaults, native hooks and metadata-only logging. `tests/fixtures/form-protection-elementor.php` creates a private native Elementor document with no post-submit actions on the expressly authorized Hexa-owned site. Its matching cleanup deletes the document and restores the exact prior option. Never run a live fixture on a client site. External emails are not an acceptance mechanism.


Cloudflare's exact official always-pass development site key and matching public development secret are supported for controlled acceptance tests. Only that exact pair may accept Cloudflare's fixed `example.com` hostname and absent action, after a real successful siteverify response. Other keys always require the site's actual hostname and action. Failed Turnstile server verification retains the existing `turnstile` reason; missing responses use `turnstile_missing`. Development keys are removed after acceptance.
