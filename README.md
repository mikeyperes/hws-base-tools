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

### v10.9.2 (Current)
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
