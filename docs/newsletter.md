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
- success: Thanks for signing up!
- style: saved admin default, initially none; none, simple, rounded
- id: generated unique ID; class: empty (space separated additional classes)
- name: off; on adds optional name
- consent: off; on requires consent both in HTML and on server
- consent_text: I agree to receive newsletter emails.
- source: newsletter (stored form/source ID; use a unique value per form)

## Stable HTML structure
Signed configuration, nonce, page URL and action fields are omitted here for clarity.
The same classes are present with every style. Each label wraps its control.
Name, consent and Turnstile containers appear only when enabled.

    <form id="weekly-signup" class="hws-newsletter hws-newsletter--none my-signup" method="post" action="/wp-admin/admin-post.php" data-endpoint="/wp-json/hws-base-tools/v1/newsletter">
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

JS sets data-state="created", "duplicate" or "error" and aria-busy during requests.
Fresh nonces avoid full-page-cache expiry. Without JS the POST fallback displays a result with a back link.
Turnstile requires JS and inherits the enabled Base Tools Form Protection provider/keys. reCAPTCHA is not inherited.
A failed security check never saves a row.

## Built-in CSS
No CSS for none. Exact generated simple and rounded CSS is reproduced below and in the selected admin preview.

### simple

```css
.hws-newsletter--simple .hws-newsletter__fields{display:flex;flex-wrap:wrap;gap:12px;align-items:end}.hws-newsletter--simple .hws-newsletter__field{display:flex;flex:1 1 180px;flex-direction:column;gap:6px}.hws-newsletter--simple .hws-newsletter__input{box-sizing:border-box;width:100%;padding:12px;border:1px solid #767676;background:#fff;color:#161616}.hws-newsletter--simple .hws-newsletter__button{padding:12px 20px;border:1px solid #162638;background:#162638;color:#fff;cursor:pointer}.hws-newsletter--simple .hws-newsletter__status{margin-top:12px}.hws-newsletter--simple :focus-visible{outline:2px solid #2673b8;outline-offset:3px}
```

### rounded

```css
.hws-newsletter--rounded .hws-newsletter__fields{display:flex;flex-wrap:wrap;gap:12px;align-items:end}.hws-newsletter--rounded .hws-newsletter__field{display:flex;flex:1 1 180px;flex-direction:column;gap:6px}.hws-newsletter--rounded .hws-newsletter__input{box-sizing:border-box;width:100%;padding:12px;border:1px solid #767676;background:#fff;color:#161616}.hws-newsletter--rounded .hws-newsletter__button{padding:12px 20px;border:1px solid #162638;background:#162638;color:#fff;cursor:pointer}.hws-newsletter--rounded .hws-newsletter__status{margin-top:12px}.hws-newsletter--rounded :focus-visible{outline:2px solid #2673b8;outline-offset:3px}.hws-newsletter--rounded .hws-newsletter__input,.hws-newsletter--rounded .hws-newsletter__button{border-radius:24px}
```

## Elementor Custom CSS
Use a Shortcode widget with [hws_newsletter style="none"]. In Advanced > Custom CSS:

    selector .hws-newsletter__fields { display: flex; flex-wrap: wrap; gap: 12px; }
    selector .hws-newsletter__field { flex: 1 1 180px; }
    selector .hws-newsletter__input { width: 100%; padding: 12px; border: 1px solid #777; }
    selector .hws-newsletter__button { padding: 12px 20px; background: #162638; color: #fff; }

## Export and notifications
Select the list ID in the Newsletter tab. Total and the latest 20 rows include EST and IP.
Export CSV requires manage_options plus a valid export nonce; UTF-8 CSV includes UTC/EST and safe request metadata.
Formula-like cells receive a leading apostrophe. Notifications default to the site admin email; blank disables them.
Only created rows send notices. A failed mail handoff never discards a saved subscriber; the notification hook exposes its result.
Mail acceptance by WordPress does not prove delivery to a recipient.

## Follow-up automation
Run via hexa-run <domain> -- wp ...; replace 1 with a site's administrator user ID.
Install the published canonical-folder release ZIP with WP Toolkit following the repair contract, activate hws-base-tools,
then explicitly enable the feature:

    wp hws newsletter enable --user=1
    wp hws newsletter recent --list=weekly --limit=20 --user=1
    wp hws newsletter export --list=weekly --user=1 > newsletter.csv

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
