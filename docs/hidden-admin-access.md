# Hidden admin access message

When login masking and guest admin hiding are enabled, logged-out visits to `/wp-admin/` show this message through WordPress’s standard error-page renderer. The response remains HTTP 404 with no-cache headers; it does not expose the masked login address.

Owner: `src/Security/legacy-login-mask.php`, `Login_Masking::render_admin_access_notice()`.

The email subject uses the canonical hostname from `home_url()`. The WhatsApp support destination is +1 415-212-9449. The links do not send a message automatically.

## Message HTML

Example for a site whose canonical hostname is `example.org`:

```html
<h1>Login URL changed</h1>
<p>The WordPress login URL has been changed by <a href="https://hexacloudservices.com/">Hexa Cloud Services</a> for security and performance.</p>
<p>Please email <a href="mailto:support@hexacloudservices.com?subject=example.org%20-%20Login%20URL%20Request">support@hexacloudservices.com</a> to request the correct login URL or help with emergency access.</p>
<p><a href="https://wa.me/14152129449">Message 24/7 support. Click here.</a></p>
<p>Visit <a href="https://hexatiger.com/">HexaTiger</a>.</p>
```

WordPress provides the surrounding HTML and default error-page styling; the plugin enqueues no extra assets for this message.
