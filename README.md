# HWS Base Tools

**A comprehensive WordPress plugin for website management, optimization, security monitoring, and deployment readiness.**

---

## Overview

HWS Base Tools is an all-in-one WordPress administration plugin for developers and site administrators managing production websites. It provides a centralized dashboard for system monitoring, automated maintenance, deployment readiness checks, security oversight, and dynamic content shortcodes — all powered by ACF.

---

## Dashboard Panels (Overview Tab)

### 🚀 Going Live Checklist
Three-column deployment readiness checker:
- **Snippets** — All recommended snippets enabled (ACF fields, auto-updates, admin logo, etc.)
- **Plugins** — Essential plugins installed & active (ACF Pro, Elementor, Wordfence, WP Mail SMTP, Rank Math, LiteSpeed, etc.)
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
Monitored plugins (11 total, 9 essential, 2 optional) with ESSENTIAL/OPTIONAL/PRO badges, batch install, auto-update controls, red flag plugin detection.

## Themes Tab
Active theme verification, auto-update status, batch delete, warning for >2 themes.

## Snippets Tab
Toggle-based feature management with recommended badges: ACF Field Registration, Admin Features, Frontend Features.

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
| ACF Pro | 6.0+ |
| LiteSpeed Cache | 6.0+ (for cache panel) |

**Essential Plugins:** ACF Pro, Elementor + Pro, Classic Editor, Wordfence, WP Mail SMTP, Rank Math SEO, WP User Avatars, LiteSpeed Cache

---

## Changelog

### v10.14.4 (Current)
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
