# Form protection

One per-site switch, off by default, protects Elementor Pro, Contact Form 7, Gravity Forms and WPForms. No other form engine is changed.

## Settings

HWS Base Tools, Security, Form protection:

- Enable protection for this site.
- Minimum fill time: 3 seconds by default, 1-30 allowed.
- Blocked retention: 14 days by default, 1-90 allowed; maximum 5,000 records.
- Optional Cloudflare Turnstile, with a public site key and a secret saved through Hexa WP Core CredentialStore. The secret is never returned by status, logs or the scanner.

One switch controls all four adapters. Turnstile is an optional extra. Settings saves use Core AJAX with nonce and administrator checks. Saving changes purges the page cache so old forms do not omit the new fields.

## Native integration

- Elementor: render_content for its Form widget only, then elementor_pro/forms/validation.
- Contact Form 7: wpcf7_form_elements and wpcf7_spam, preserving existing spam verdicts.
- Gravity Forms: gform_form_tag, gform_validation and its native validation message.
- WPForms: wpforms_display_submit_before and wpforms_process_initial_errors.

Each rendered form receives a hidden text honeypot and a signed, form-bound challenge input. A public read-only endpoint issues a fresh token. Tokens include a signed issue time and expire after two hours; changing form, timestamp or signature invalidates them. A token missing from a direct POST is blocked. This prevents stale cached page timestamps from bypassing minimum fill time. JavaScript is required while protection is on.

Turnstile server verification checks success, this site's exact hostname and the hws-form action. It fails closed on missing keys, invalid tokens or provider errors. No raw provider response or secret is logged.

The default IP is REMOTE_ADDR. Spoofable forwarding headers are not trusted. A hosting integration can supply a verified client IP through hws_base_tools_form_protection_client_ip.

## Read interface

- GET /wp-json/hws-base-tools/v1/form-protection/blocked?limit=100, administrator permission required.
- wp hws forms blocked --limit=100, JSON, read-only, hosting-owner CLI access.
- wp hws forms protection status, JSON, no secrets.
- wp hws forms protection set --enabled=1 --min_fill_seconds=3 --retention_days=14 --user=<administrator>. Setting changes require administrator identity. Secret keys are accepted only through protected settings.

The scanner can also SELECT the small prefix_hws_base_tools_form_blocks table. Fields: id, occurred_at (UTC), form_key, ip, reason. The table is created lazily on the first block. No submission body, email, token, key, cookie or user-agent is stored.

## Assets and markup

Only a rendered protected form loads assets/form-protection/protection.js. No CSS is added. Turnstile's external script loads only on a rendered protected form with Turnstile configured. Core admin UI assets load only when this tab opens. The script initializes dynamically inserted forms and refreshes challenges after reset.

Markup adds a data-hws-form-protection wrapper, a hidden hws_form_website text field and an hws_form_token hidden field. Turnstile adds its standard cf-turnstile wrapper. Nothing visible is added unless Turnstile is enabled.

See [asset loading](asset-loading.md). Native plugin validation, required fields and notifications stay owned by each form plugin.

## Tests

php tests/form-protection-unit.php covers default-off behavior, signed token scope/age/tampering, honeypot, minimum time, all four hook adapters, Turnstile and log privacy/retention. Browser acceptance uses one temporary form on an HWS-owned site. When email sends are excluded, it captures the normal plugin mail handoff and sends no external message; that proves the form reached its mail action, not SMTP delivery.
