# HWS Base Tools

WordPress site policy and operations tooling for Hexa-managed websites.

Version 10.18.152 restores WP Toolkit one-time login compatibility while masked
login protection is fully active.

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

## Architecture

The canonical plugin entry is `hws-base-tools.php`. It selects one shared Hexa
WordPress Plugin Core package, registers the `HWS\BaseTools\` autoloader, and
boots request-specific modules. `initialization.php` is only a compatibility
entry for older active-plugin records.

Implementation code is organized by ownership under `src/`. Historical root
files remain thin include shims so deployed integrations do not break. New code
must be added to a domain class or adapter, never to a root shim or a generic
function dump.

See [docs/architecture.md](docs/architecture.md) for the namespace map, runtime
sequence, Core ownership rules, compatibility policy, and release test matrix.

## Dashboard Tabs

The dashboard shell and AJAX tab navigation come from
`Hexa\PluginCore\WpAdminTabs`. HWS owns the tab definitions and site-specific
callbacks. Quick Start is the second tab, Shortcodes is a first-class tab, and
Legacy Snippets remains visible and marked deprecated.

The Search tab provides five live front-end search previews from the shared
`Hexa\PluginCore\SearchDisplay` renderer. Saving a design changes the default
output of `[hexa_search]` without changing the shortcode wherever it is placed.
Its separate Search Behavior panel uses `Hexa\PluginCore\SearchQuery` for
strictly scoped native result matching.

## Overview

### Going Live Checklist
Three-column deployment readiness checker:
- **Snippets** — All recommended snippets enabled (ACF fields, auto-updates, admin logo, etc.)
- **Plugins** — Essential plugins installed & active (Elementor, Wordfence, WP Mail SMTP, Rank Math, LiteSpeed, etc.)
- **Settings & Server** — 25+ checks: WP_MEMORY_LIMIT, comments/pingbacks off, SMTP authenticated, WP_DEBUG off, display_errors off, Wordfence alerts, log file sizes (debug.log, error_log, wp-admin/error_log), WP_CRON disabled, Cloudflare active, PHP SAPI LiteSpeed, PHP ≥ 8.1, Imagick, no MyISAM tables, Redis active, post_max_size/upload_max ≥ 128MB, Brotli, max 2 themes, all updated, no Twenty* themes

### LiteSpeed Cache Panel
Four-column status: Page Cache (on/off, private, browser, mobile, REST, TTL) · CSS/JS (minify, combine, async, defer) · Redis (connection, driver, version, memory, hit rate, uptime, keys) · Brotli & General (compression, PHP, SAPI, server)

### SMTP Status
Detects WP Mail SMTP mailer type and auth status for 15+ providers (SendGrid, Mailgun, Postmark, Brevo, SparkPost, SMTP, Gmail/Outlook/Zoho OAuth, etc.)

### Wordfence Security
Plugin/firewall status, alert email config, collapsible setup instructions.

### PHP And Server Extensions
22 PHP extensions with loaded/missing status (9 required, 13 recommended).

### Error Logs
Four-tab viewer: Fatal/Syntax Errors · debug.log · error_log · wp-admin/error_log. Real-time search, keyboard navigation, size display, delete buttons, display_errors indicator.

### WP-Config Settings
Toggle WP_DEBUG, WP_DEBUG_LOG, WP_DEBUG_DISPLAY, SCRIPT_DEBUG, DISABLE_WP_CRON, WP_MEMORY_LIMIT.

---

## Plugins Tab
Monitored plugins (11 total, 9 essential, 2 optional) with ESSENTIAL/OPTIONAL/PRO badges, batch install, auto-update controls, red flag plugin detection, and one-click HWS GitHub plugin installs with canonical slug normalization.

## Themes Tab
Active theme verification, auto-update status, batch delete, warning for >2 themes.

## Features Tab
Structured feature management with toggles, optional settings, use instructions, code examples, test reports, and activity logs.

## Quick Start Tab
Reusable Hexa WP Core startup process runner. HWS registers the former Quick Setup process, environment checks, plugin version checks, core version checks, site identity checks, and permalink checks; Hexa WP Core provides the checklist UI, guarded AJAX execution, sequential subtasks, spinner/check/X states, and technical activity log.

## Shortcodes Tab
HWS supplies its shortcode definitions, descriptions, parameters, examples, and
test methods. Hexa WP Core supplies the reusable catalog, real-output display,
and isolated test structure.

## Search Tab
Five selectable public site-search templates: Icon Reveal, Overlay, Pill,
Underline, and Command Bar. Admin previews and `[hexa_search]` both call the
same Hexa WP Core renderer. Searches submit through WordPress's native
`/?s=query` flow; this feature does not load AJAX search results. A separate,
default-disabled behavior panel controls all/any/exact terms,
whole/prefix/contains matching, dynamic public post types, title/content/excerpt/slug,
opt-in taxonomy/author/custom-field sources, result count, ordering, and
shortcode-only versus all-public-search scope. See
[docs/search-query-audit.md](docs/search-query-audit.md) for the five-plugin
source audit and the decisions carried into HWS.

## Brand Assets Tab
One place for favicon and logo assets:
- Site Icon PNG and physical `/favicon.ico` links with open-in-new-tab actions
- Letter-based favicon generator
- Brand Colors panel with Hexa WP Core color controls for primary/secondary/highlight colors plus the generic Elementor palette detector
- Six logo slots: `logo`, `logo_1x1`, `logo_text`, `logo_dark`, `logo_dark_1x1`, `logo_text_dark`
- Shortcodes: `[site_logo key="logo" size="medium"]`, `[site_logo key="logo" size="full" output="url"]`, `[site_logo key="logo" size="300x120"]`, `[site_logo key="logo_text" size="medium" width="180"]`

## UI Cleanup Tab
WordPress admin cleanup toggles: dashboard widgets, admin bar, menu items, footer text.

## Cleanup Tab
Hexa WP Core cleanup tools for stale page reports, backup file deletion, and article/media cleanup. Article cleanup supports preview scanning, selected-row deletion, "delete all matching posts", and "delete matching posts except the latest X"; destructive batch actions run through repeated AJAX requests and can delete associated featured/inline/gallery media when Media Cleanup is enabled.

---

## Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[founder id="..."]` | Legacy SMP compatibility shortcode; new person/publication behavior belongs in the SMP plugins |
| `[company id="..."]` | Legacy SMP compatibility shortcode; new organization behavior belongs in the SMP plugins |
| `[website_content id="..."]` | ACF website settings options |
| `[website_url]` | Site URL |
| `[display_year]` | Current year |
| `[current_year]` | Current year alias |
| `[hexa_search]` | Saved Hexa WP Core public search design |
| `[hexa_search style="overlay" accent="#2f6df6"]` | One-placement search design override |
| `[site_logo key="logo" size="medium"]` | Brand/logo asset image |
| `[site_logo key="logo" size="full" output="url"]` | Brand/logo asset URL |
| `[site_logo key="logo_text" size="medium" width="180"]` | Brand/logo image constrained inside the requested box without skewing |

---

## Automated Maintenance

| Task | Default | Description |
|------|---------|-------------|
| Log Cleaner | 5 days, 10MB | Deletes debug.log, error_log, wp-admin/error_log |
| Backup Cleaner | Daily, 5 days | Removes backups from common directories |
| Elementor DB Updater | 3 days | Auto-runs Elementor DB migrations |

---

## Reusable Services

Reusable cross-plugin behavior lives in the vendored Hexa WordPress Plugin
Core under `lib/hexa-wordpress-plugin-core/src/`. HWS adapters use focused Core
namespaces for tabs, admin components, AJAX guards, activity logs, plugin
inventory, updates, cleanup, system environment, wp-config, cron tasks, and
shortcode display.

HWS-only reusable behavior lives in focused classes under `src/`. Historical
functions in `generic-functions.php` are compatibility adapters and must not be
used as the destination for new functionality.

---

## Implementation Queue

- No active README-tracked implementation queue remains.

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

### v10.18.152 (Current)

- Allowed only the exact short-lived 64-character WP Toolkit token request to
  reach the toolkit validator before masked-login endpoint blocking runs.
- Kept ordinary and malformed `/wp-login.php` requests hidden.

### v10.18.151

- Fixed masked-login branding so the enabled Site Icon logo hook is registered
  before the priority-1 `/hexa-admin/` fallback includes WordPress login.
- Made custom login-logo hook registration idempotent.

### v10.18.150

- Added optional HWS-owned Services (`services`) and Knowledge Base
  (`knowledge-base`) custom post types with dedicated snippet toggles.
- Kept both landing paths available to static pages while entries use nested
  single URLs, and loaded thin legacy callbacks for snippet compatibility.

### v10.18.149

- Added the former `User - Admin` key to the canonical migration suppression
  list so database-saved copies cannot reappear on other installations.

### v10.18.148

- Removed the dormant compatibility path that could recreate the superseded
  `User - Admin` ACF group. Its profile/team fields now live only in the
  canonical 2025 user groups, and the former SMP hook delegates to those groups.
- Renamed the canonical user-field source module to `user-profile-2025.php` so
  its ownership is explicit.

### v10.18.137

- Added a comprehensive Search Behavior panel with AJAX persistence, dynamic
  public post-type/taxonomy controls, matching modes, sources, limits, ordering,
  and a visible five-plugin criteria audit.
- Added the reusable Hexa WordPress Plugin Core `SearchQuery` engine with strict
  request guards and one-query-only SQL filtering.
- Updated the bundled Hexa WordPress Plugin Core to `0.19.59`.

### v10.18.136

- Fixed Search template settings so the AJAX save runs without nested forms.

### v10.18.135

- Added five reusable site-search display templates and the `[hexa_search]`
  shortcode through Hexa WordPress Plugin Core `SearchDisplay`.

### v10.18.134

- Updated Hexa WordPress Plugin Core to `0.19.57` so Quick Start search visibly
  removes nonmatching grid rows instead of only marking them hidden in the DOM.
- Allowed Core collapsible titles to wrap on narrow screens instead of truncating.

### v10.18.133

- Updated the bundled Hexa WordPress Plugin Core to `0.19.56` and enabled its
  reusable nested search on Quick Start.
- Rebuilt every Features entry with the shared Hexa Core collapsible component;
  all feature panels now load collapsed while retaining their complete controls.

### v10.18.132

- Updated the bundled Hexa WordPress Plugin Core to `0.19.55`.
- Removed sticky positioning from the shared Core sidebar so the complete HWS
  navigation moves normally with the page and remains reachable while scrolling.

### v10.18.131

- Ensured the flat sidebar releases its desktop width when collapsed instead
  of leaving an empty 220-pixel navigation track.

### v10.18.130

- Restored the Hexa Core sidebar as a flat, titled, one-column navigation list
  instead of bordered wrapping tab cards.
- Prevented the overview checklist grid from forcing horizontal page overflow.

### v10.18.129

- Ensured the Features tab loads the shared HWS toggle component before feature
  cards render, preventing plain-checkbox fallbacks for Team Member templates
  and the rest of the feature catalog.

### v10.18.128

- Added the HWS-owned `[hws_team_members]` directory with minimal portrait grid,
  editorial list, and compact directory templates.
- Added Team Member CPT/ACF readiness checks, AJAX template selection, visual
  previews, shortcode examples, test reporting, and feature activity entries.
- Updated the bundled Hexa WordPress Plugin Core to `0.19.54` and adopted its
  grouped, collapsible, persistent AJAX sidebar navigation for all HWS tabs.

### v10.18.127

- Replaced AJAX-injected server editor markup in Footer Text with the supported
  WordPress dynamic editor lifecycle and Core tab cleanup, restoring reliable
  Visual and Text mode switching.

### v10.18.126

- Declared the shared toggle renderer as a lazy Footer Text tab dependency so
  the AJAX-rendered footer controls load without an undefined-function fatal.

### v10.18.125

- Routed Website Types and Legacy Snippets metadata through the shared lazy
  value resolver so nested ACF/CPT detail callbacks cannot reach WordPress
  escaping functions as Closure objects.

### v10.18.124

- Added the Site Profile dependency to lazy Sitemaps tab and sitemap AJAX loads,
  preventing the news-outlet sitemap check from calling an undefined helper.

### v10.18.123

- Kept ACF/CPT feature metadata deferred during early ACF initialization while
  retaining nested callable resolution in the Features tab.

### v10.18.122

- Fixed the Features AJAX tab fatal by resolving nested lazy ACF/CPT metadata
  callbacks before rendering descriptions and code examples.

### v10.18.121

- Updated the vendored Hexa WordPress Plugin Core package to `0.19.39`, including
  metadata-only snippet catalog rendering.

### v10.18.120

- Replaced the historical `initialization.php` bootstrap with the canonical
  `hws-base-tools.php` entry and a thin legacy compatibility shim.
- Organized HWS runtime code into explicit domains under `src/`, added
  request-specific dashboard, AJAX, frontend, ACF, cron, and CLI loading, and
  retained root filenames only as compatibility shims.
- Integrated Hexa WordPress Plugin Core through `PluginContext`,
  `CoreBootstrap`, the shared package resolver, Core tabs, and the Core
  Shortcode Registry display.
- Updated the vendored Hexa WordPress Plugin Core package to `0.19.38`, including
  the reusable lazy page workspace release.
- Added security hardening for disabled-by-default remote actions, encrypted
  master-secret storage, constant-time secret checks, and guarded updater AJAX.
- Added architecture and migration documentation plus automated PHP, package
  integrity, collision, and database-cleanup tests.

### v10.18.117

- Fixed the HWS Cleanup task table layout so long WP-Optimize result messages wrap cleanly instead of clipping or stretching the section.
- Moved active database cleanup run state out of transients so WP-Optimize transient cleanup cannot delete the running session before table optimization finishes.

### v10.18.114

- Added a HWS Cleanup tab database cleanup section backed by reusable Hexa WP Core service/controller/renderer code. It runs WP-Optimize cleanup tasks, optimizes tables one by one over AJAX, and disables WP-Optimize after the run.
- Added a separate Overview Redis Object Cache panel that reports both LiteSpeed Redis enabled state and actively running WordPress object-cache verification, with refresh and enable actions.
- Added HWS Quick Start items for the shared database cleanup service and the rewritten LiteSpeed Redis verifier.

### v10.18.112

- Quick Start reports now include clearer before/action/verified-after/what-changed proof for favicon generation, plugin activation, recommended snippets, essential plugin setup, news outlet cleanup, and email configuration tasks.
- Favicon Quick Start output now shows the generated PNG and ICO URLs in the same checklist report with explicit verification details.

### v10.18.111

- Updated vendored Hexa WP Core to 0.19.33.
- Removed the redundant Quick Start "Verify Required Launch Settings" pass; setup actions now include their own before/action/verified-after proof reports.
- Added plain-English before/action/verified-after reports for plugin/theme auto-updates, log cleanup, backup cleanup, comments, pingbacks, and Redis object-cache checks.

### v10.18.110

- Quick Start favicon generation now shows visible PNG and ICO preview cards above the URL report, with each preview opening the generated asset in a new tab.
- Renamed wp-config report columns to `Target Value` and `Verified Value`, and changed the memory-limit task to report the verified value honestly. Values above 511M are treated as acceptable even when the exact requested value is overridden by the live site.
- Updated vendored Hexa WP Core to 0.19.32.

### v10.18.109
- Hardened the HWS native updater preflight for sites where another plugin loaded an older Hexa Core class first; HWS now falls back to its local remover unless the shared Core purge method exists.

### v10.18.108
- Updated vendored Hexa WP Core to 0.19.31 and added a native WordPress updater preflight that purges vendored Core VCS metadata before HWS Base Tools updates. If locked metadata remains, the updater now returns a clear ownership/permissions error instead of a long file-copy failure list.

### v10.18.107
- Changed the Quick Start favicon task to use the same letter-based Generate PNG + ICO function as Brand Assets and report both the generated PNG Site Icon URL and physical `/favicon.ico` URL.

### v10.18.106
- Added Generate ICO as the first Quick Start task. It purges the physical `/favicon.ico` file and regenerates it from the current WordPress Site Icon PNG with a Hexa WP Core checklist report.

### v10.18.105
- Updated vendored Hexa WP Core to 0.19.30 so forbidden/unwanted plugin rows show Activate when an installed plugin is inactive, alongside Delete.

### v10.18.104
- Added WP-Sweep to the HWS Base Tools Plugins tab monitored/recommended plugin list as an installable WordPress.org plugin expected to be active.

### v10.18.101
- Updated the News Outlets Initial Setup Quick Start plugin task to exclude Pro/manual plugins and automatically install/activate the public WordPress.org stack requested for MashViral: Classic Editor, Elementor, LiteSpeed Cache, Rank Math SEO, Site Kit by Google, Wordfence Security, WP-Optimize, WP-Sweep, and WP Mail SMTP.

### v10.18.100
- Expanded the News Outlets Initial Setup plugin task to enforce the Mash Viral news outlet plugin stack, including Elementor, Rank Math, Site Kit, SMP TTS, and installed-inactive Visibility Logic.

### v10.18.99
- Added the News Outlets Initial Setup Quick Start profile with guarded post-only cleanup that keeps the newest 10 posts and a Core plugin-check task for HWS Base Tools, SMP Publication Integration, and Verified Profiles.
- Removed the temporary sample delete task from the active Quick Setup list.

### v10.18.98
- Updated vendored Hexa WP Core to 0.19.28 so plugin inventory rows include subtle secondary Deactivate and Delete controls for installed plugins.

### v10.18.97
- Updated vendored Hexa WP Core to 0.19.27 and added a Quick Start destructive-delete sample that requires typed confirmation, creates/deletes temporary sample posts with media, and renders reusable Hexa WP Core deleted-post/deleted-file reports.

### v10.18.96
- Updated vendored Hexa WP Core to 0.19.26 and registered Quick Start templates: Default and Diamond Website.

### v10.18.95
- Updated vendored Hexa WP Core to 0.19.25 and added the UI Cleanup option registry as a generated Quick Start parent checklist section with reusable Core reports for file deletion and wp-config mutations.

### v10.18.94
- Updated vendored Hexa WP Core to 0.19.24 so blocked Quick Start action buttons show the exact unmet required-input reason on hover.

### v10.18.93
- Moved Quick Start required inputs onto isolated task-level subtasks so SMTP and Wordfence values are rendered and processed only by the tasks that consume them.

### v10.18.92
- Added Hexa Core required-input fields to Quick Setup for Wordfence alert email and WP Mail SMTP from email.

### v10.18.91
- Removed non-requested Quick Start status sections so the checklist focuses on Quick Setup and required launch settings.

### v10.18.90
- Added required launch setting checks to Quick Start using the existing Going Live Checklist status source.

### v10.18.89
- Changed the Quick Start navigation slug to `quick-start` and kept `getting-started-checklist` as a compatibility alias.

### v10.18.88
- Updated vendored Hexa WP Core to 0.19.22 so Cleanup remains compatible when another plugin has already loaded an older Hexa Core `CoreUi` class.

### v10.18.87
- Restored the visible startup tab label to Quick Start while keeping the existing tab slug for compatibility.
- Updated vendored Hexa WP Core to 0.19.21 so Cleanup hides backend detection criteria from the operator view and keeps wide reports contained inside Core collapsible sections.

### v10.18.86
- Updated vendored Hexa WP Core to 0.19.20 so the Getting Started Checklist renders top-level steps as collapsible Core sections and keeps the technical activity log collapsed by default.

### v10.18.82
- Updated vendored Hexa WP Core to 0.19.17 so Article & Media Cleanup shows the two primary batch deletion actions first, each with its own associated-media toggle, while advanced filters and preview rows are collapsed by default.

### v10.18.81
- Updated vendored Hexa WP Core to 0.19.16 so the Cleanup tab opens without launching page, backup, and article scans automatically. Each cleanup section now shows a clear manual scan empty state and only starts AJAX work when its scan button is clicked.

### v10.18.80
- Updated vendored Hexa WP Core to 0.19.15 and added true article/media batch deletion in Cleanup. The UI now has explicit actions for deleting all matching posts or deleting all matching except the latest X posts; batch deletion ignores the preview limit, runs through AJAX batches, logs each batch, and supports associated media deletion when the toggle is enabled.

### v10.18.79
- Updated vendored Hexa WP Core to 0.19.14 so Cleanup tab descriptions, detection rules, and backup scan locations render as subtle collapsed secondary details instead of large focus cards.
- Backup scans now show a loading row while scanning and log file patterns searched, folders inspected, directory entries looked at, matched files, and no-result state.

### v10.18.78
- Replaced the HWS dashboard legacy tab shell with the Hexa WP Core HostTabsRenderer, removed old tab button/panel CSS and JavaScript, and normalized tab labels to plain Core-style names.

### v10.18.77
- Updated vendored Hexa WP Core to 0.19.13 so Cleanup shows collapsed description subcards, visible detection rules, and a detailed Backup Files scan-location list with resolved directory status.

### v10.18.76
- Updated vendored Hexa WP Core to 0.19.12 so Core toggle inputs cannot create horizontal page overflow on the Cleanup tab.

### v10.18.75
- Updated vendored Hexa WP Core to 0.19.11 so Cleanup tab services render as separate collapsible Core cards with closed-by-default activity logs and contained table/log overflow.

### v10.18.74
- Removed the temporary plugin inventory scenario examples from the live Plugins tab. The tab now shows only real plugin library/status sections.
- Updated vendored Hexa WP Core to 0.19.10 so Core collapsible cards show a visible chevron toggle indicator.
- Preloaded the HWS vendored Hexa Core admin UI on wp-admin requests so older active plugin core copies cannot render stale collapsible markup first.

### v10.18.73
- Updated vendored Hexa WP Core to 0.19.9 and switched plugin inventory installed/missing indicators to inline Font Awesome SVGs.

### v10.18.72
- Updated vendored Hexa WP Core to 0.19.8 and removed the separate Installed column from plugin inventory tables. Installed/missing state now appears as a hoverable green check or red X beside each plugin title.

### v10.18.71
- Hardened the Plugins tab so HWS loads its own vendored Hexa WP Core PluginChecks classes before rendering plugin inventory sections, preventing another plugin's autoloader from serving an older renderer.

### v10.18.70
- Updated vendored Hexa WP Core to 0.19.7, changed plugin inventory title icons to reflect actual plugin presence, added Required/Optional badges and missing-required row styling, and validated required/optional present/missing states.

### v10.18.69
- Updated vendored Hexa WP Core to 0.19.6 and rebuilt the Plugins tab HWS Plugin Library and Plugin Status sections with reusable Core plugin inventory cards, AJAX refresh/install/activate actions, and green/red Font Awesome-style indicators.

### v10.18.68
- Updated vendored Hexa WP Core to 0.19.5 so backup cleanup correctly requires both file and parent directory writability before enabling delete actions.

### v10.18.67
- Updated vendored Hexa WP Core to 0.19.4 and expanded the Cleanup tab with Core-powered backup file cleanup plus article/media cleanup. Backup rows delete in real time with loaders/logs. Article cleanup supports filters, keep-most-recent, select-all, post-only deletion by default, and explicit associated featured/inline media deletion.

### v10.18.66
- Updated vendored Hexa WP Core to 0.19.3 and changed the Cleanup tab to a report-only Core rules view. It now flags non-front-page Home pages in yellow and Old/Delete pages in red without showing manual detection filters.

### v10.18.65
- Updated vendored Hexa WP Core to 0.19.2 and added a Cleanup tab using the reusable ContentCleanup module for old page detection, edit links, AJAX trash/delete actions, and live activity logging.

### v10.18.64
- Reworked the Sitemaps tab layout so settings links are full-width, sitemap URLs appear before actions, and the no-cache action displays its current enabled state.

### v10.18.63
- Added an Overview Website Profile selector for site type classification and made the Sitemaps tab include Rank Math News Sitemap checks when the site type is News Outlet.

### v10.18.62
- Fixed sitemap no-cache option reads on persistent object-cache installs by falling back to the concrete options table and clearing stale option/notoptions cache entries after the AJAX save.

### v10.18.61
- Updated the vendored Hexa WP Core package to 0.19.1 and added a Sitemaps tab with AJAX sitemap scanning, LiteSpeed cache header checks, sitemap no-cache controls, LiteSpeed sitemap purge, Rank Math settings links, and AJAX permalink refresh.

### v10.18.60
- Moved the Brand Colors panel to Hexa WP Core color controls and the generic Elementor palette detector while keeping the existing AJAX save endpoint.

### v10.18.59
- Added a UI Cleanup toggle to hide the Post Attributes metabox and hardened the collapsed-by-default behavior so WordPress postbox state restoration cannot reopen it on editor load.
- Preserved the live ACF Source Tracker feature in the Git source and corrected the runtime plugin version metadata.

### v10.18.58
- Added the ACF Source Tracker feature toggle for showing source/context cards on ACF field groups.

### v10.18.57
- Fixed the Footer Text admin tab editor by explicitly loading WordPress editor assets, restoring readable Code-mode textarea colors, and hardening TinyMCE/Code toggle sync for preview and AJAX saves.

### v10.18.56
- Added a UI Cleanup option to hide the Simple Local Avatar rating controls on profile and user-edit screens.
- Removed the older duplicate local ACF Team Member and Team Member Title fields from the HWS User - Admin field group.

### v10.18.55
- Process HWS brand/logo shortcodes inside Elementor widget output, including Heading widgets, so header logo shortcodes render instead of displaying raw text.
- Enforce shortcode `width` / `height` as inline ratio-safe image styles, so `width="180"` and `width="180px"` both render at the requested width without skewing.

### v10.18.54
- Added `width` and `height` support to `[site_logo]` / `[hws_brand_asset]`; requested dimensions are treated as a bounding box and recalculated from the real image ratio so logos never skew.

### v10.18.52
- Site Pages now creates required shared pages as published pages by default instead of drafts.

### v10.18.51
- Removed the transient UI Cleanup handoff file from the plugin package. The handoff was provided in chat and should not ship inside the plugin.

### v10.18.49
- Fixed UI Cleanup so Rank Math Content AI, admin footer cleanup, and editor/profile controls load on the screens they target instead of only inside the HWS dashboard.
- Added UI Cleanup options for hiding the classic Comments metabox, hiding the LiteSpeed metabox, forcing LiteSpeed collapsed, and forcing Post Attributes collapsed on editor screens.
- Renamed the WordPress cleanup group to WordPress User & Editor Screens.

### v10.18.42
- Added a Pages tab for shared site-level page structures: Terms of Use, Privacy Policy, Brand Assets, Headquarters, Contact, and FAQs.
- Pages tab supports create/select/reuse flow, template editing, page detail display, and menu attachment through Hexa Plugin Core SiteStructure.

### v10.18.38
- Updated vendored Hexa WordPress Plugin Core to v0.10.0.
- Added `Hexa\PluginCore\WpCronTasks\WpCronTask` for reusable WP-Cron interval registration, scheduling, unscheduling, event inspection, and health status payloads.
- Refactored log cleaner, backup cleaner, and Elementor DB updater cron helpers to delegate shared scheduling/status mechanics to Hexa Plugin Core.

### v10.18.37
- Updated vendored Hexa WordPress Plugin Core to v0.9.0.
- Added `Hexa\PluginCore\WpConfigFile\WpConfigFile` for safe `wp-config.php` constant and `ini_set()` reads/writes.
- Refactored HWS wp-config helpers in `generic-functions.php` into compatibility shims that delegate to Hexa Plugin Core.

### v10.18.36
- Updated vendored Hexa WordPress Plugin Core to v0.8.0.
- Added `Hexa\PluginCore\PluginProvisioning\PluginProvisioner` for reusable plugin status checks, WordPress.org installs, GitHub ZIP installs, folder normalization, and activation.
- Refactored HWS plugin library/install handlers to delegate reusable provisioning mechanics to Hexa Plugin Core while keeping HWS plugin catalog data in HWS.
- Corrected the Overview error-log renderer reference to the flat `Hexa\PluginCore\LogFiles` namespace.

### v10.18.35
- Updated vendored Hexa WordPress Plugin Core to v0.7.0.
- Moved reusable HWS safe wrappers into core namespaces: `WpAdminAjax` for nonce/capability/AJAX guards and `SystemEnvironment` for safe shell, INI/constant reads, CPU/memory detection, size parsing, and byte formatting.
- Converted HWS `safe-wrappers.php` into compatibility shims so existing dashboard and AJAX handlers keep the same function names while using the shared core implementation.

### v10.18.34
- Updated vendored Hexa WordPress Plugin Core to v0.6.0 and switched HWS to the flat core namespaces: PluginUpdates, CorePackageUpdates, WpAdminTabs, WpAdminComponents, ActivityLog, SmartSearch, CredentialVault, LogFiles, ShortcodeRegistry, CoreRuntime, CoreContracts, and CoreBootstrap.

### v10.18.33
- Refreshed the vendored Hexa WordPress Plugin Core package after the upstream composer cleanup so HWS ships the canonical v0.5.0 package.

### v10.18.32
- Updated vendored Hexa WordPress Plugin Core to v0.5.0.
- Reworked the Hexa Core dashboard into core-owned internal tabs: README, UI Elements, Activity Log, Smart Search / X-Search, API Keys, and Error Logs.
- Added WordPress core equivalents for Laravel CredentialService and x-hexa-smart-search, including live visual examples.

### v10.18.31
- Updated vendored Hexa WordPress Plugin Core to v0.4.0.
- Redesigned the Hexa Core tab around shared core UI primitives.
- Started the first HWS swap by rendering the Overview error-log viewer through Hexa Plugin Core Logs.
- Added an HWS core migration scan that inventories current tabs, repeated UI patterns, and the next extraction order.

### v10.18.30
- Updated the vendored Hexa WordPress Plugin Core package to v0.3.0.
- Added the automatically registered Hexa WordPress Plugin Core dashboard tab through core tab hooks.
- Added the core dark expandable activity log component and README-style core documentation tab.

### v10.18.29
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
