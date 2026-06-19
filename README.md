# HWS Base Tools

**A comprehensive WordPress plugin for website management, optimization, security monitoring, and deployment readiness.**

---

## Overview

HWS Base Tools is an all-in-one WordPress administration plugin for developers and site administrators managing production websites. It provides a centralized dashboard for system monitoring, automated maintenance, deployment readiness checks, security oversight, and dynamic content shortcodes. ACF/ACF Pro is optional and only required for ACF field registration and ACF-powered content shortcodes.

Canonical plugin entry:

```text
/hws-base-tools
    hws-base-tools.php
    initialization.php
    /src
```

`hws-base-tools.php` is the WordPress plugin header file. `initialization.php` remains as a legacy bootstrap so older installs active as `hws-base-tools/initialization.php` can migrate safely to `hws-base-tools/hws-base-tools.php`.

---

## Dashboard Panels (Overview Tab)

### 🚀 Going Live Checklist
Three-column deployment readiness checker:
- **Snippets** — All recommended snippets enabled (ACF fields, auto-updates, admin logo, etc.)
- **Plugins** — Essential plugins installed & active (Elementor, Wordfence, WP Mail SMTP, Rank Math, LiteSpeed, etc.)
- **Settings & Server** — 25+ checks: WP_MEMORY_LIMIT, comments/pingbacks off, SMTP authenticated, WP_DEBUG off, display_errors off, Wordfence alerts, log file sizes (debug.log, error_log, wp-admin/error_log), WP_CRON disabled, Cloudflare active, PHP SAPI LiteSpeed, PHP ≥ 8.1, Imagick, no MyISAM tables, Redis active, post_max_size/upload_max ≥ 128MB, Brotli, max 2 themes, all updated, no Twenty* themes

### ⚡ Quick Setup
One-click production configuration: disable debug, set memory 4GB, enable auto-updates, delete logs/backups/comments, enable recommended snippets, install & activate essential free plugins, enable Redis/LiteSpeed/Wordfence.

### ⚡ LiteSpeed Cache Panel
Four-column status: Page Cache (on/off, private, browser, mobile, REST, TTL) · CSS/JS (minify, combine, async, defer) · Redis (connection, driver, version, memory, hit rate, uptime, keys) · Brotli & General (compression, PHP, SAPI, server)

### 📧 SMTP Status
Detects WP Mail SMTP mailer type and auth status for 15+ providers (SendGrid, Mailgun, Postmark, Brevo, SparkPost, SMTP, Gmail/Outlook/Zoho OAuth, etc.)

### 🛡️ Wordfence Security
Plugin/firewall status, alert email config, collapsible setup instructions.

### 🖥️ PHP & Server Extensions
22 PHP extensions with loaded/missing status (9 required, 13 recommended).

### 📄 Error Logs
Four-tab viewer: Fatal/Syntax Errors · debug.log · error_log · wp-admin/error_log. Real-time search, keyboard navigation, size display, delete buttons, display_errors indicator.

### ⚙️ WP-Config Settings
Toggle WP_DEBUG, WP_DEBUG_LOG, WP_DEBUG_DISPLAY, SCRIPT_DEBUG, DISABLE_WP_CRON, WP_MEMORY_LIMIT.

---

## Plugins Tab
Monitored plugins (11 total, 9 essential, 2 optional) with ESSENTIAL/OPTIONAL/PRO badges, batch install, auto-update controls, red flag plugin detection, and one-click HWS GitHub plugin installs with canonical slug normalization.

## Themes Tab
Active theme verification, auto-update status, batch delete, warning for >2 themes.

## Features Tab
Structured feature management with toggles, optional settings, use instructions, code examples, test reports, and activity logs.

## Brand Assets Tab
One place for favicon and logo assets:
- Site Icon PNG and physical `/favicon.ico` links with open-in-new-tab actions
- Letter-based favicon generator
- Brand Colors panel with Highlight Text Color picker saved as `hws_brand_highlight_text_color`
- Six logo slots: `logo`, `logo_1x1`, `logo_text`, `logo_dark`, `logo_dark_1x1`, `logo_text_dark`
- Shortcodes: `[site_logo key="logo" size="medium"]`, `[site_logo key="logo" size="full" output="url"]`, `[site_logo key="logo" size="300x120"]`

## UI Cleanup Tab
WordPress admin cleanup toggles: dashboard widgets, admin bar, menu items, footer text.

---

## Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[founder id="..."]` | Founder user data (name, title, bio, social URLs, education, etc.) |
| `[company id="..."]` | Company user data (same attributes as founder) |
| `[website_content id="..."]` | ACF website settings options |
| `[website_url]` | Site URL |
| `[display_year]` | Current year |
| `[current_year]` | Current year alias |
| `[site_logo key="logo" size="medium"]` | Brand/logo asset image |
| `[site_logo key="logo" size="full" output="url"]` | Brand/logo asset URL |

---

## Automated Maintenance

| Task | Default | Description |
|------|---------|-------------|
| Log Cleaner | 5 days, 10MB | Deletes debug.log, error_log, wp-admin/error_log |
| Backup Cleaner | Daily, 5 days | Removes backups from common directories |
| Elementor DB Updater | 3 days | Auto-runs Elementor DB migrations |

---

## Reusable Helper Functions

All in `generic-functions.php` for site-wide use:

| Function | Returns |
|----------|---------|
| `hws_check_redis_status()` | `{ active, extension, connected, litespeed_enabled, info{}, error }` |
| `hws_check_brotli_support()` | `{ enabled, details }` |
| `hws_get_litespeed_info()` | Full LiteSpeed config array (cache, CSS, JS, object cache) |
| `hws_get_glc_settings_checks()` | Array of `{ label, pass, value }` for 25+ checks |
| `check_cloudflare_active()` | CF-Ray/CF-Connecting-IP header detection + NS fallback |
| `check_smtp_auth_status_and_mailer()` | Auth status for 15+ mail providers |
| `check_myisam_tables()` | MyISAM detection scoped to current WP prefix |
| `hws_check_php_extensions()` | 22 extensions with loaded/required status |
| `hws_render_instructions()` | Reusable collapsible instruction box |

---

## Requirements

| Requirement | Minimum |
|-------------|---------|
| WordPress | 6.0+ |
| PHP | 8.1+ |
| LiteSpeed Cache | 6.0+ (for cache panel) |

**Essential Plugins:** Elementor + Pro, Classic Editor, Wordfence, WP Mail SMTP, Rank Math SEO, WP User Avatars, LiteSpeed Cache

**Optional Plugins:** ACF or ACF Pro for ACF field registration and ACF-powered shortcodes.

---

## Changelog

### v10.18.29 (Current)
- Added the Hexa WordPress Plugin Core package updater panel below the HWS Base Tools plugin updater.
- Updated the vendored Hexa Plugin Core package to v0.2.0 and points it at the public core GitHub repository.

### v10.18.28
- Replaced the HWS-specific GitHub updater and Plugin Info AJAX handlers with the vendored Hexa Plugin Core abstract updater.
- Added the Hexa Plugin Core library reference file to the HWS Base Tools package.

### v10.18.27
- Moved canonical `staff_writer`, `muckrack_verified`, and `muckrack_url` user fields into the User - Additional ACF group with toggle UI for boolean fields.
- Removed duplicate active Staff Writer and Settings-group MuckRack registrations from legacy HWS user ACF groups.

### v10.18.26
- Added UI Cleanup controls for hiding the WordPress core Application Passwords section, Classic Editor default editor selector, and Rank Math admin footer output.

### v10.18.24
- Restored the legacy Snippets tab as a separate deprecated dashboard tab instead of aliasing it to Features.

### v10.18.22
- Added a Plugins tab HWS Plugin Library for one-click GitHub installs with `repo-main` folder normalization.
- Changed dashboard tab navigation to load selected tabs with AJAX instead of full page refreshes.

### v10.18.21
- Changed Logo Assets previews to use the full uploaded asset URL instead of WordPress cropped thumbnails.

### v10.18.20
- Prevented Brand Gallery media saves from erasing existing selections by merging saved IDs with newly selected images.

### v10.18.19
- Changed Logo Assets to one full-width row per asset with larger uncropped contain previews.

### v10.18.18
- Forced the Brand Gallery media picker to open in Media Library browse mode with the saved selection visible.

### v10.18.17
- Seeded the Brand Gallery media frame with the saved attachment selection before opening it.

### v10.18.16
- Added a fallback copy path for individual Elementor color hex buttons when the Clipboard API rejects.

### v10.18.15
- Made the highlight override frontend-only, with wp-admin showing only the local preview.
- Made Elementor color assets collapsed and AJAX-loaded on demand, with individual copy buttons only.
- Preserved Brand Gallery ACF selections when reopening the media picker.

### v10.18.14
- Fixed Elementor color copy fallback so the copy buttons resolve consistently.

### v10.18.13
- Changed the Brand Colors highlight override control from a checkbox to a switch toggle.

### v10.18.12
- Added separate highlight enable, background color, and text color controls with cross-browser selection CSS output.
- Added an Elementor color assets viewer with copy buttons for individual and full color lists.

### v10.18.11
- Registered the Brand Gallery ACF field independently from the Website Settings preset and exposed the `[site_gallery]` alias in the Brand Assets UI.

### v10.18.10
- Added a Brand Gallery ACF field and a Brand Assets tab gallery manager with AJAX media selection.
- Added `[brand_asset_gallery]` and `[site_gallery]` shortcodes for rendering the managed brand gallery.

### v10.18.9
- Moved the Going Live Checklist and Quick Setup panels to the top of the Overview tab.
- Marked the WP_MEMORY_LIMIT config tile green when the configured value is greater than 511MB.

### v10.18.8
- Updated the Brand Assets Login Logo panel to use the active HWS masked-login URL when login masking is enabled.

### v10.18.7
- Enlarged generated letter favicons when server font fallback is used, and added more Linux font path fallbacks.
- Kept favicon PNG and ICO links together in the Site Icon/Favicon panel with safer wrapping for long URLs.
- Moved the login-logo controls out of the Features card layout into Brand Assets as a no-toggle Site Icon driven panel.
- Changed the Features tab to one feature per row and moved ACF/custom-field feature cards into Website Types.

### v10.18.6
- Added a Brand Colors panel to the Brand Assets tab with an AJAX Highlight Text Color picker.
- Outputs the saved highlight color as `--hws-highlight-text-color` for templates/CSS that need the shared brand accent.

### v10.18.5
- Separated favicon/site-icon management from logo asset management on the Brand Assets tab.
- Updated favicon actions and logo upload/clear actions to refresh the tab with AJAX instead of reloading the page.
- Renamed the six managed asset slots to logo-based keys and made the first slot sync to WordPress Custom Logo.

### v10.18.4
- Added a dedicated Brand Assets tab and removed duplicate favicon controls from Overview/System Basics.
- Consolidated favicon management into one panel that shows both the uploaded PNG source URL and physical `/favicon.ico` URL with new-tab links.
- Added six managed logo asset slots with upload/clear controls, URL links, WordPress Site Icon/Custom Logo sync, and `[site_logo]` / `[hws_brand_asset]` shortcodes with size parameters.

### v10.18.3
- Added canonical `hws-base-tools.php` plugin entry file to match the plugin folder slug for better toolkit/scanner compatibility.
- Kept `initialization.php` as a legacy bootstrap and added active plugin basename migration from `hws-base-tools/initialization.php` to `hws-base-tools/hws-base-tools.php`.
- Updated updater, activation hooks, and plugin-info version checks to use the canonical main file while falling back to legacy commits where needed.

### v10.18.2
- Render only the selected HWS dashboard tab instead of loading every tab on every request.
- Replaced full-file dashboard log reads with bounded tail reads so large `error_log` files do not slow the settings page.

### v10.18.1
- Fixed the Footer Text targeted selector picker so it does not save its temporary hover class and prefers stable Elementor selectors.
- Normalized saved targeted selectors server-side so old values containing `.hws-ft-picker-hover` are cleaned before frontend injection runs.

### v10.18.0
- Added a structured Features tab with toggle, settings, use instructions, code example, test report, and activity log sections.
- Added toggles for disabling the front-end admin bar for non-admins, limiting a tag RSS feed, enabling `[current_year]`, and lowercasing uploaded file names.
- Added Overview System Basics controls for indexability, website title/tagline, and favicon status/testing.
- Rebuilt favicon tools with SFPF-style one-letter icon generation and real `/favicon.ico` ICO output.
- Simplified Footer Text into one shared text editor with two clear methods: bottom section or targeted injection into an existing footer element.

### v10.17.0
- Added a separate Targeted Footer Injection tool that inserts inline HTML as a non-invasive span before, after, or within a selected footer element.
- Added a footer element picker for admins to inspect the live footer, click an element, and capture its ID/classes/suggested selector for targeted injection.
- Removed Hexa PR Wire force-sync admin code from HWS Base Tools; PR Wire functionality now belongs in the Hexa PR Wire plugin.

### v10.16.1
- Fixed the masked-login slug fallback so blank or malformed stored values cannot hit an undefined fallback constant.
- Made `/wp-admin/` return a real 404 for logged-out visitors when masked-login hiding is enabled, matching the existing dashboard status text.
- Updated plugin metadata to report WordPress 7.0 compatibility and corrected the internal plugin name label.

### v10.15.0
- Fixed server spec detection to prefer cgroup/container CPU and memory limits before host-level `/proc` values.
- Server RAM and processor checks now label host-visible fallback readings so shared-server specs are not presented as account-level allocations.

### v10.14.9
- Removed ACF Pro as a hard runtime prerequisite. Core tools now continue loading without ACF, while ACF-specific features stay gated until ACF or ACF Pro is active.
- Changed ACF Pro from an essential monitored plugin to optional.

### v10.14.8
- Fixed WP core plugin updates from GitHub archives by preserving the trailing slash on the normalized source directory, allowing core package validation to find `initialization.php`.

### v10.14.7
- Test bump to verify the new Update Now activity log + WP core update detection round-trip end-to-end on a live site.

### v10.14.6
- Rebuilt Plugin Info → Update Now: discrete logged install steps with a live activity log (download, extract, locate, backup, install, sweep duplicates, repoint active_plugins, clean up) — no more silent ''Downloading & Installing…'' hang.
- Atomic-style swap (rename) replaces the old delete-then-copy install path, so a failure mid-install rolls back cleanly. Backup is taken before any change.
- Updater now sweeps stray hws-base-tools-* duplicates (the old ''-main'' postfix bug) every run, and repoints active_plugins to the canonical hws-base-tools/initialization.php if the runtime folder was different.
- GitHub_Updater.check_for_update no longer early-returns when WordPress hasn'''t populated $transient->checked — it now adds to $transient->response (and to $transient->no_update when current) so the WP Plugins page reliably shows the update.
- Plugin Info UI redesigned: metadata card + side-by-side ''On this site'' vs ''In the Git repo'' Version Status card with a status badge (Up to date / Update available / Update available (WP sees it)).

### v10.14.5
- Tightened GitHub version checks to use a per-request cache-buster, so freshly pushed releases register immediately in WordPress.
- Fixed GitHub version checks to bypass stale cached raw responses when WordPress fetches the updater metadata.
- Fixed updater folder normalization so WordPress installs into `hws-base-tools` instead of `hws-base-tools-main`.
- Fixed footer text rendering to avoid Elementor replacing the saved footer text with full page content.

### v10.14.1
- Footer Text now supports per-band alignment: Left, Center, or Right. The choice applies to any of the 10 templates.
- Decorative accents shift to match the alignment, so when text aligns left the keyline accent, hairline rule, monolith bar, editorial rule, and colophon rules all anchor to the left edge (and right with right alignment).
- Admin picker shows a dedicated Alignment control above the Style picker. The mini previews and live preview update instantly as alignment changes.

### v10.14.0
- Footer Text templates fully rewritten and remounted as a full-width band at the very bottom of the theme footer, so they read as a natural extension of the footer instead of a panel jammed into an existing column
- Renamed and redesigned the entire 10-template set to a coherent intensity ladder: Whisper, Hairline, Colophon (minimal), Bookend, Keyline (light), Editorial, Broadsheet (medium), Marquee, Spotlight, Monolith (heavy)
- Heavy templates now render as opaque dark bands with white-on-dark typography that work consistently regardless of the host theme footer colour
- Link styling is enforced across templates with defensive overrides so theme-level a-tag rules can no longer break the band typography (font-size, weight, transform, letter-spacing now inherit from the template)
- Each template card in the picker now shows a tier badge (minimal / light / medium / heavy) so the visual progression is obvious at a glance
- Legacy template keys auto-migrate (boxed-card to keyline, pull-quote to spotlight, etc.) so existing sites keep working without admin intervention

### v10.13.3
- Footer Text injector now avoids hidden Elementor footer containers and prefers visible footer inner wrappers like `.e-con-inner`, which fixes cases where the content mounted into a hidden footer section and never appeared on the live site

### v10.13.2
- Footer Text injector script now opts out of LiteSpeed JS delay/optimization, so the saved footer text mounts into the real footer immediately instead of sitting below the footer until delayed JS fires

### v10.13.1
- Footer Text saves now purge frontend caches, so the live site updates immediately instead of waiting on a stale LiteSpeed snapshot
- Saving the underlying Website Settings options page also purges cache for footer text changes made outside the Footer Text tab

### v10.13.0
- Reworked the Footer Text design library so the templates no longer read like placeholders: kept a few minimalist options, but upgraded the rest into more deliberate panel, editorial, note, and card treatments
- Renamed the style set to clearer, more curated labels: Plain, Top Divider, Legal Small, Soft Panel, Signature Bar, Micro Stamp, Editorial Line, Journal Columns, Side Note, and Elevated Card
- Tightened the admin picker stage so the per-row previews and live preview better show the design differences instead of collapsing into the same generic white box

### v10.12.0
- Footer Text template picker is now a vertical 1-per-row list — each row shows your saved footer text rendered with that template's CSS, so you can see all 10 styles applied to your real content at the same time
- Renamed templates to action-oriented names: Plain, Top Divider, Smaller Centered, Boxed, Accent Bar, Uppercase Spaced, Italic Centered, Two Columns, Pull Quote, Shadow Card
- Punched up the visual contrast of every template so differences are obvious at a glance (e.g. Boxed now uses a real `#f3f4f6` panel, Shadow Card has a stronger drop shadow)
- Added an `admin-mini` CSS scope to the shared template helper so the per-row mini-previews share the same source of truth as the admin Live Preview and the live-site footer

### v10.11.0
- Expanded the Footer Text style library from 3 to 10 templates: Quiet Inline, Hairline Divider, Fine Print, Boxed Card, Accent Bar, Stamp, Italic Tagline, Two-Column, Pull Quote, and Soft Shadow — each with distinct CSS
- Added a single CSS helper (`hws_get_footer_text_template_css`) so the admin Live Preview and the live-site footer share one source of truth and cannot drift apart
- Made the admin preview JS class-strip data-driven from the template registry, so future template additions need no JS changes

### v10.10.0
- Redesigned the Footer Text settings page: visibility toggle moved into the page header, dropped the duplicate stat cards and the standalone visibility panel, replaced the bottom style picker with a compact pill row above the editor, and put the editor and live preview side-by-side at wider widths
- Removed the fake static template card previews (the live preview is now the only preview, so it cannot disagree with the user's actual content)
- Added a one-click Copy button for the footer text shortcode

### v10.9.9
- Moved the Footer Text preview into the footer content section
- Made the Footer Text preview update live while typing and when switching templates

### v10.9.8
- Removed the separate Footer Text shortcode panel and moved the shortcode into the footer editor instructions

### v10.9.7
- Added the actual footer text editor directly into the Footer Text tab
- Added shortcode information to the Footer Text tab
- Simplified the wording around live visibility so the tab reads plainly

### v10.9.6
- Changed the footer text feature into a two-step module: snippet toggle unlocks the module, while a dedicated Footer Text tab controls real frontend output
- Added a dedicated Footer Text dashboard tab with its own enable/disable switch
- Added minimalist template choices for footer text rendering

### v10.9.5
- Fixed the admin dashboard white page on `hws-core-tools` caused by a nonce constant namespace bug in the legacy event bridge

### v10.9.4
- Changed footer text auto-injection into an optional snippet toggle
- Default state is off until enabled in the Snippets screen

### v10.9.3
- Added automatic footer text injection from Website Settings
- Injects quietly into common footer containers with a safe body fallback
- Keeps footer text styling inherited and low-contrast for a more seamless look

### v10.9.2
- Fixed Redis redeclaration crash (function_exists guards)
- Fixed LiteSpeed panel undefined array key errors (null-coalescing)
- Fixed Cloudflare detection (HTTP headers first, nameservers fallback)
- Fixed Redis detection (reads LiteSpeed's stored host/port/auth config)
- Added display_errors, individual log checks, wp-admin/error_log to GLC
- Added wp-admin/error_log tab to Error Logs
- Removed SVG from recommended/GLC
- Comprehensive README rewrite

### v10.9.1
- Going Live Checklist 3-column layout with 25+ checks
- LiteSpeed Cache 4-column status panel
- Robust Redis/Brotli/LiteSpeed helper functions
- Removed memcached from PHP extensions

### v10.9.0
- SMTP auth fix for all 15+ mailers
- Plugin categorization (essential/optional/pro)
- Quick Setup: auto-enable snippets + install plugins
- Instruction system, Wordfence/Favicon setup guides
- PHP Extensions panel, Schema.org removal, ACF title WYSIWYG

---

**Author:** Michael Peres · [michaelperes.com](https://michaelperes.com)
