# Newsletter signup collector

Enable: Settings > HWS Base Tools > Content > Newsletter, check Enabled and Save.
Default is disabled, with no plugin styling. Existing data remains when disabled.
Admin: /wp-admin/admin.php?page=hws-core-tools&tab=newsletter
No mailing service is subscribed and no marketing campaign is sent. This is a collector, not double opt-in.
Connect downstream sending separately and honor unsubscribe requests there. IP/header data is personal data:
publish a privacy notice and set your organization's retention policy. WordPress Tools > Export/Erase Personal Data includes all newsletter lists.
Storage remains on uninstall to avoid accidental subscriber loss.

## Shortcode
    [hws_newsletter]
    [hws_newsletter list="weekly" placeholder="Email address" button="Join" success="You're on the list!" style="rounded" id="weekly-signup" class="my-signup" name="on" consent="on" consent_text="I agree to receive the weekly newsletter." source="footer"]

Attributes and defaults:
- list: default (lowercase letters, digits, underscore and hyphen; max 80; starts with letter/digit)
- placeholder: Your email address
- button: Subscribe
- success: saved site default, initially Thanks for signing up!
- duplicate: saved site default, initially This email is already subscribed to this list.
- error: saved site override, initially blank. Blank uses Signup could not be saved. Try again later. for server/rate/security failures, and preserves specific invalid-email and missing-consent instructions. A nonempty override replaces all failure messages, including field instructions.
- All three message parameters are plain text, maximum 2000 UTF-8 bytes each (not characters). Overlong/non-string shortcode messages reject the form; invalid admin saves preserve all prior settings. HTML is displayed as text, never executed. New forms sign these messages; unsigned request fields cannot override them. Older signed forms retain their success and inherit current duplicate/error settings.
- style: saved admin default, initially none; none, simple, rounded
- id: generated unique ID; class: empty (space separated additional classes)
- name: off; on adds optional name
- consent: off; on requires consent both in HTML and on server
- consent_text: I agree to receive newsletter emails.
- source: newsletter (string, maximum 120 bytes; stored form/source ID; use a unique value per form)

## Stable HTML structure
Generated configuration and nonce values use placeholders below; production URLs are absolute. This shows one form with optional fields enabled.
The same classes are present with every style. Each label wraps its control.
Name, consent and Turnstile containers appear only when enabled.

    <form id="weekly-signup" class="hws-newsletter hws-newsletter--none my-signup" method="post" action="/wp-admin/admin-post.php" data-endpoint="/wp-json/hws-base-tools/v1/newsletter" data-error="Signup could not be saved. Try again later.">
      <input type="hidden" name="action" value="hws_base_tools_newsletter">
      <input type="hidden" name="config" value="SIGNED_CONFIGURATION">
      <input type="hidden" name="nonce" value="NONCE">
      <input type="hidden" name="page_url" value="">
      <div class="hws-newsletter__fields">
        <label class="hws-newsletter__field">
          <span class="hws-newsletter__label">Name</span>
          <input class="hws-newsletter__input" name="name" autocomplete="name" maxlength="200">
        </label>
        <label class="hws-newsletter__field">
          <span class="hws-newsletter__label">Email</span>
          <input class="hws-newsletter__input" type="email" name="email" autocomplete="email" required maxlength="254" placeholder="Your email address">
        </label>
        <button class="hws-newsletter__button" type="submit">Subscribe</button>
      </div>
      <div class="hws-newsletter__honeypot" hidden aria-hidden="true"><label>Leave empty<input name="company_website" tabindex="-1" autocomplete="off"></label></div>
      <label class="hws-newsletter__consent"><input type="checkbox" name="consent" value="1" required> I agree to receive newsletter emails.</label>
      <div class="hws-newsletter__turnstile" data-sitekey="PUBLIC_SITE_KEY"></div>
      <div class="hws-newsletter__status" role="status" aria-live="polite"></div>
    </form>

The status element gains exactly one result class: `hws-newsletter__status--success` (created), `hws-newsletter__status--duplicate`, or `hws-newsletter__status--error`. Pending submissions clear the prior result class.

JS sets data-state="created", "duplicate" or "error" and aria-busy during requests.
REST uses freshly fetched nonces. The no-JavaScript admin-post fallback deliberately ignores the page-baked public signup nonce, which may be expired in a cached page. Signed configuration, honeypot, IP rate limits, validation, enabled state and inherited Turnstile still apply. Without JS the POST fallback displays a result with a back link.
Turnstile requires JS and inherits the enabled Base Tools Form Protection provider/keys. reCAPTCHA is not inherited.
A failed security check never saves a row.

Additional examples:

    [hws_newsletter list="weekly" style="simple" success="Welcome aboard!" duplicate="You are already on our list." error="Please try again later."]
    [hws_newsletter list="events" style="rounded" name="on"]
    [hws_newsletter list="weekly" style="none" consent="on"]

## Built-in CSS
No CSS for none. Exact generated simple and rounded CSS is reproduced below and in the selected admin preview.

### simple

```css
.hws-newsletter--simple .hws-newsletter__fields{display:flex;flex-wrap:wrap;gap:12px;align-items:end}.hws-newsletter--simple .hws-newsletter__field{display:flex;flex:1 1 180px;flex-direction:column;gap:6px}.hws-newsletter--simple .hws-newsletter__input{box-sizing:border-box;width:100%;padding:12px;border:1px solid #767676;background:#fff;color:#161616}.hws-newsletter--simple .hws-newsletter__button{padding:12px 20px;border:1px solid #162638;background:#162638;color:#fff;cursor:pointer}.hws-newsletter--simple .hws-newsletter__status{margin-top:12px}.hws-newsletter--simple :focus-visible{outline:2px solid #2673b8;outline-offset:3px}.hws-newsletter--simple .hws-newsletter__status--success{color:#166534;background:#fff}.hws-newsletter--simple .hws-newsletter__status--error{color:#b91c1c;background:#fff}
```

### rounded

```css
.hws-newsletter--rounded .hws-newsletter__fields{display:flex;flex-wrap:wrap;gap:12px;align-items:end}.hws-newsletter--rounded .hws-newsletter__field{display:flex;flex:1 1 180px;flex-direction:column;gap:6px}.hws-newsletter--rounded .hws-newsletter__input{box-sizing:border-box;width:100%;padding:12px;border:1px solid #767676;background:#fff;color:#161616}.hws-newsletter--rounded .hws-newsletter__button{padding:12px 20px;border:1px solid #162638;background:#162638;color:#fff;cursor:pointer}.hws-newsletter--rounded .hws-newsletter__status{margin-top:12px}.hws-newsletter--rounded :focus-visible{outline:2px solid #2673b8;outline-offset:3px}.hws-newsletter--rounded .hws-newsletter__status--success{color:#166534;background:#fff}.hws-newsletter--rounded .hws-newsletter__status--error{color:#b91c1c;background:#fff}.hws-newsletter--rounded .hws-newsletter__input,.hws-newsletter--rounded .hws-newsletter__button{border-radius:24px}
```

## Elementor Custom CSS
Use a Shortcode widget with [hws_newsletter style="none"]. In Advanced > Custom CSS:

    selector .hws-newsletter__fields { display: flex; flex-wrap: wrap; gap: 12px; }
    selector .hws-newsletter__field { flex: 1 1 180px; }
    selector .hws-newsletter__input { width: 100%; padding: 12px; border: 1px solid #777; }
    selector .hws-newsletter__button { padding: 12px 20px; background: #162638; color: #fff; }
    selector .hws-newsletter__status--success { color: #166534; background: #fff; }
    selector .hws-newsletter__status--duplicate { color: #374151; background: #fff; }
    selector .hws-newsletter__status--error { color: #b91c1c; background: #fff; }

Simple and rounded use green #166534 and red #b91c1c on white (contrast ratios about 7.1:1 and 6.5:1). Style none adds no CSS. The no-JavaScript result page uses the same signed messages and HTML escaping. Configure site defaults in the Newsletter tab; shortcode values override them per form.

## Export and notifications
Select the list ID in the Newsletter tab. Total and recent rows include EST and IP. Older submissions pages through 20 rows at a time; Newest submissions returns to the start. Each row has a Delete subscriber button protected by administrator capability and a nonce bound to that list and subscriber ID. Deletion permanently removes that row.
Export CSV requires manage_options plus a valid export nonce; UTF-8 CSV includes UTC/EST and safe request metadata.
Formula-like cells receive a leading apostrophe. Notifications default to the site admin email; blank disables them.
Notices include email, list, EST time, IP, page URL, source, and a link to the list in the Newsletter admin tab. Only created rows send notices. A failed mail handoff never discards a saved subscriber; the notification hook exposes its result.
Mail acceptance by WordPress does not prove delivery to a recipient.

## Follow-up automation
Run via hexa-run <domain> -- wp ...; replace 1 with a site's administrator user ID.
Install the published canonical-folder release ZIP with WP Toolkit following the repair contract, activate hws-base-tools,
then explicitly enable the feature:

    wp hws newsletter enable --user=1
    wp hws newsletter disable --user=1
    wp hws newsletter recent --list=weekly --limit=20 --user=1
    wp hws newsletter export --list=weekly --user=1 > newsletter.csv

Every command above requires --user naming a WordPress administrator; disable retains subscriber rows.

Set notification and default styling in the admin tab. Do not pass secrets in command arguments.
PHP helpers:

    $store = HWS\BaseTools\Newsletter\NewsletterModule::store();
    $store->recent('weekly', 20);
    $store->total('weekly');
    $store->csv($authorized_stream, 'weekly');

HTTP: POST /wp-json/hws-base-tools/v1/newsletter with signed config from shortcode, nonce, email, optional name/consent,
page_url, company_website (empty), and inherited cf-turnstile-response.
GET /wp-json/hws-base-tools/v1/newsletter/nonce supplies a non-cached nonce.
POST results: 201 created, 200 duplicate; errors: 400 validation, 403 disabled/nonce, 429 rate, 503 storage.
The signed config binds list, consent, source and messages; clients cannot weaken those fields.
SubmissionStore is an internal PHP API; callers must apply their own authorization before reading personal data.

## Style loading
Styles use one WordPress handle per built-in style, not per-render flags. An indexer can discard a shortcode expansion without losing its queued stylesheet. WordPress prints each handle once, in the head if queued early or via the footer for late-rendered Elementor widgets. No style is queued for none.

## Message-change asset inventory

Before and after this change: the home page and an article without the shortcode load no newsletter assets. A page using the enabled shortcode loads newsletter.js, plus only the chosen simple/rounded inline CSS handle (none loads no CSS). The Newsletter admin tab prints its selected style preview and CSS without loading the visitor script. Message colors extend the existing inline stylesheet; no new asset, request, or global loading hook was added.

Tests: `wp eval-file wp-content/plugins/hws-base-tools/tests/newsletter-messages-integration.php --user=<admin>` (54 assertions, restores settings and deletes its fixtures), `node tests/newsletter-messages-js.cjs` (21 assertions, Node 22), and the existing newsletter integration and web-context tests.
