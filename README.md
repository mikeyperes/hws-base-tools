# HWS Base Tools

Shared WordPress site configuration, administration, and operational tooling for Hexa-managed websites.

## Ownership

HWS Base Tools is the canonical owner of:

- Website classification: News Outlet, Podcast Website, Personal Website, Company Website, e-Commerce Website, or Other.
- The optional primary entity selection consumed by profile and publication plugins.
- Shared custom post types: `team-member`, `testimonial`, and `services`.
- Site-wide article image crops, crawlable featured-image policy, SEO-provider adapters, and targeted sitemap invalidation.
- Brand assets, common shortcodes, site checks, maintenance tools, and WordPress admin cleanup.

The primary entity is optional. Sites that do not need a canonical person, organization, publication, or verified profile continue to work without one.

HWS does not own the `organization` post type or its fields; SFPF Person Profile Integration owns those structures. HWS also leaves publication-specific Knowledge Base and Resources post types to SMP Publication Integration.

## Architecture

The canonical entry point is `hws-base-tools.php`. `initialization.php` remains a compatibility loader for older active-plugin records.

Namespaced implementation code lives under `src/`. Historical root files are compatibility adapters and are not destinations for new behavior.

Reusable cross-plugin behavior comes from the bundled Hexa WordPress Plugin Core:

- Dashboard tabs, collapsible cards, buttons, activity logs, and guarded AJAX.
- Custom post type, taxonomy, and ACF structure registration and settings UI.
- Canonical entity resolution and attached-user field inspection.
- FAQ normalization/rendering and schema document utilities.
- Plugin/Core update reporting, provisioning, cleanup, search, and system utilities.
- Persistent real-time checklists, WordPress operations, LiteSpeed setting
  profiles, and reusable field-value normalization.

See [docs/architecture.md](docs/architecture.md) and [HEXA_PLUGIN_CORE_LIBRARY.md](HEXA_PLUGIN_CORE_LIBRARY.md).

## Dashboard

The HWS dashboard includes:

- A profile-driven Quick Start master checklist with individual and safe batch
  execution, resume/retry state, rollback snapshots, and before/after reporting.
- A separate Review Center for deletion candidates that must be approved one at
  a time, including complete comment removal.
- A dedicated LiteSpeed checklist with Compatibility, Safe Baseline, Editorial,
  and Aggressive Test-First profiles.
- Website and Primary Entity settings.
- Custom Post Types and ACF structure controls.
- Plugins, themes, updates, backups, cleanup, and system checks.
- Brand Assets, Footer Text, UI Cleanup, and Site Structure tools.
- Sitemaps, Search Display, Search Behavior, Features, and Shortcodes.
- A generic reading-progress feature with an entire-site override, front-page and public post-type targeting, five visual designs, live previews, and the shared Hexa WP Core color picker.
- Brand Templates for safe WordPress fallbacks and native Elementor Theme Builder imports.
- Default Page Content styling with six exact visual designs, an explicit No Style option, and an inspectable template/CSS view.
- Full Site Maintenance Mode with five exact response templates, administrator bypass, proper public 503/Retry-After/noindex handling, REST/XML-RPC protection, and a live enable/disable checklist.

Dashboard navigation and asynchronous tab loading use Hexa WP Core. Operational actions report progress without requiring full page refreshes.

See [Quick Start and Review Center](docs/quick-start.md), [LiteSpeed Profiles](docs/litespeed.md), and the [HWS Bootstrap URL](docs/bootstrap-installer.md).

## Brand Templates

Open **Settings > HWS Core Tools > Brand Templates** to opt into default Author, Page, Single Post, Category, or Tag behavior. All switches are disabled by default.

- `author.php`, `page.php`, `category.php`, and `tag.php` are plugin-owned fallbacks.
- Page and Single Post content-style switches are separately scoped and optional.
- Elementor imports use native containers, dynamic tags, current-query archive widgets, global Kit tokens, responsive controls, and Rank Math breadcrumbs.
- The default Page import excludes the front page.
- Matching Theme Builder conditions are treated as conflicts. HWS never overwrites or deactivates another template automatically.
- Managed imports are idempotent, backed up before replacement or activation changes, and expose a restore action.
- If a matching Elementor document is active, the PHP fallback yields to Elementor.

## Primary Entity

HWS stores `hws_site_type` and the optional `hws_primary_entity` record. New sites remain unclassified until a website type is deliberately selected. The optional primary entity source is:

- A WordPress user.

The entity panel shows the selected author, semantic type, public/edit links, WordPress identity values, and available profile data. SFPF, SMC, SMP Publication, and Verified Profiles consume this canonical selection while retaining plugin-owned entity relationships and read-only legacy fallbacks for migration.

## Custom Post Types

Every HWS-owned type is registered through `Hexa\PluginCore\ContentTypes`. The WordPress post-type key is immutable to protect existing content. Administrators may edit:

- Public rewrite slug.
- Singular label.
- Plural label.
- Related ACF structure toggles.

Each type and field structure is closed by default in the dashboard and includes an exact ownership and field breakdown.

## Key Shortcodes

| Shortcode | Purpose |
| --- | --- |
| `[hexa_search]` | Render the saved public search design. |
| `[site_logo key="logo" size="medium"]` | Render a configured brand image. |
| `[site_logo key="logo_text" width="180"]` | Constrain a logo without changing its aspect ratio. |
| `[team_members]` | Render the HWS Team Member directory. |
| `[hws_testimonial_quote]` | Return a notable quote from the current or specified Testimonial. |
| `[website_content id="..."]` | Read a configured Website Settings value. |
| `[website_url]` | Return the site URL. |
| `[current_year]` | Return the current year. |

Legacy `[founder]` and `[company]` compatibility remains available, but profile-specific behavior belongs to the corresponding profile plugin.

## Requirements

| Requirement | Minimum |
| --- | --- |
| WordPress | 6.0 |
| PHP | 8.1 |
| Hexa WP Core bundle | 3.0.1 |

ACF or ACF Pro is optional and is required only for ACF-backed structures and values. Individual operational panels may require the plugin they inspect, such as LiteSpeed Cache or Rank Math.

## Installation

Install the repository as `wp-content/plugins/hws-base-tools`, activate `hws-base-tools.php`, and open **Settings > HWS Core Tools**. Existing installs activated through `initialization.php` are migrated by the compatibility bootstrap. Prepared sites can also use the secure administrator bootstrap URL documented above to download the canonical GitHub package without a manual ZIP workflow.

## Development

Run the complete static, unit, architecture, Core-integrity, and PHP-lint suite with:

```bash
php tests/run.php
```

The bundled Core `VERSION`, `PACKAGE_HASH`, executable source, and root `HEXA_PLUGIN_CORE_LIBRARY.md` must match the canonical Core repository exactly.

## Changelog

### 13.2.4

- Uses the attachment's descriptive alt text on the crawlable hero and verifies Google News sitemaps only when the saved module and active Rank Math Pro runtime both make that endpoint applicable.
- Adds Default Page Content styling with six exact visual designs, No Style, and code views for the active default renderer.
- Adds Full Site Maintenance Mode with five exact HTML/CSS templates, an administrator bypass, 503/Retry-After/noindex responses, REST/XML-RPC protection, and persisted permalink, cache, and verification steps.

### 13.2.3

- Rewrites a theme-authored raw featured image in the initial singular article HTML so it uses the same 1200px landscape family as metadata and carries crawlable `src`, `srcset`, `sizes`, dimensions, descriptive alt text, eager loading, and high fetch priority.

### 13.2.2

- Generates exact 1200px article crops from landscape sources whose shorter edge needs controlled upscaling, rejects undersized derivative metadata instead of marking an incomplete image family as complete, and reruns backfill with targeted article and sitemap cache refreshes.

### 13.2.1

- Adds the `[hws_testimonial_quote]` shortcode for rendering numbered notable quotes from the current or specified Testimonial, with ACF and raw-meta fallback support.

### 13.2.0

- Adds automatic 1200px 16:9, 4:3, and 1:1 crops for new and existing published article featured images across every installation.
- Extends Rank Math's existing Article graph, Open Graph, Twitter, robots, and image-sitemap output without creating a competing schema provider or overriding a deliberately selected social image.
- Keeps native featured images out of LiteSpeed lazy replacement and adds high fetch priority while preserving responsive WordPress markup.
- Completes targeted Rank Math sitemap invalidation in persistent Redis/object cache, then primes and verifies the affected sitemap endpoints with bounded retries.

### 13.1.1

- Adds reusable notable quote entries to the Testimonial ACF fields as a textarea repeater.

### 13.1.0

- Transfers Organization CPT and field ownership to SFPF Person Profile Integration while retaining the immutable post-type key for compatibility and preventing duplicate registration.

### 13.0.4

- Completes required-plugin provisioning before enabling automatic updates, verifies both WordPress memory constants at `4096M`, and captures `WP_MAX_MEMORY_LIMIT` in rollback snapshots.

### 13.0.3

- Separates LiteSpeed Redis configuration from next-request verification, fails closed for foreign object-cache drop-ins, and accepts an explicit UTC offset of zero during Quick Start identity checks. Synchronized the bundled Hexa WP Core to 3.0.1.

### 13.0.2

- Makes the HWS discussion policy authoritative over importers and other content writers: closed comment or ping defaults are enforced on every post insert/update at final priority and on frontend availability checks, while explicitly enabled discussion remains untouched.

### 13.0.1

- Invalidates stale WordPress plugin discovery before activating a fresh bootstrap install and after rollback, preventing `no_plugin_header` errors when `get_plugins()` was populated earlier in the request. The MU bootstrap loader is now version 2.0.1.

### 13.0.0

- Coordinated major release for the expanded HWS operations, provisioning, review, security, and Quick Start infrastructure, synchronized with Hexa WordPress Plugin Core 3.0.0.

### 12.1.8

- Added an entire-site reading-progress toggle that disables narrower targeting while active, plus independent front-page and dynamically discovered public post-type choices for every single item in selected types.

### 12.1.7

- Kept full Quick Start runs available when later child tasks need input, ensuring the first task can automatically install and activate missing required plugins before dependent actions continue.
- Removed the launch-email prefill workaround; email-dependent tasks validate only themselves and no longer block plugin provisioning or other runnable setup tasks.

### 12.1.6

- Prefilled Quick Start's required Wordfence and SMTP email fields from their configured values or the current administrator so Quick Run is not disabled by blank launch-contact inputs.

### 12.1.5

- Updated the bundled Hexa WP Core package to 2.1.3 so Quick Start distinguishes a selected template from a completed load and gives the Load Template button visible loading, success, and failure feedback.

### 12.1.4

- Moved the generic reading-progress bar from SMP Publication Integration into the HWS Features architecture, including enablement, scope, five shared frontend/admin designs, and the Hexa WP Core color picker.
- Added a one-time, non-destructive migration of existing SMP enablement, scope, Thin style, and color settings while preventing duplicate renderers during staggered plugin updates.

### 12.1.3

- Made Quick Start load the plugin policy it consumes and install or activate missing required plugins through the shared Hexa WP Core provisioner before plugin-dependent setup actions run.

### 12.1.2

- Preserved the canonical 2025 Threads field key while making it an explicit `Threads URL` field with URL validation, legacy-value migration coverage, and shortcode compatibility.
- Updated the bundled Hexa WP Core package to 2.1.2.

### 12.1.1

- Treat duplicate Post Type Transfer callbacks at unexpected hook priorities as critical drift and quarantine every broad query callback.

### 12.1.0

- Added fail-closed, source-validated query-hook adapters for Post Type Transfer 1.6 and Echo RSS Feed Post Generator 5.5.1.2 without editing vendor plugins.
- Capped validated Elementor Pro enhanced-search main queries at a filterable default of 100 while preserving ordinary, smaller, secondary, suppressed, and background queries.
- Skipped Post Type Transfer meta joins when no visibility rules exist, with a bounded cached presence check and complete metadata invalidation.
- Removed HWS-owned unlimited query defaults and changed complete administrative scans to fixed-size batches.
- Updated the bundled Hexa WP Core package to 1.2.0 and its shared frontend query eligibility contract.

### 12.0.0

- Scope WordPress media and editor dependencies to the HWS tabs that use them.
- Preserve asset-heavy tab behavior with explicit full-page navigation.
- Stop loading Cleanup and log-maintenance controllers on Overview requests.

### 11.2.13

- Added Site Kit by Google to the required and recommended WordPress.org plugin policy with direct AJAX install-and-activate support.

### 11.2.12

- Rebuilt the Photos gallery Details panel through the generic Hexa WP Core ACF gallery module.
- Added immediate Details refresh after native ACF add, remove, and reorder changes, including unsaved selections.
- Added larger previews, separate image-data and URL clipboard actions, and gallery-only deletion that preserves Media Library attachments.
- Updated the bundled Hexa WP Core package to 1.1.9.

### 11.2.11

- Scoped podcast plugin recommendations to Podcast Website installs.

### 11.2.10

- Updated the Core gallery clipboard action to fall back cleanly when a browser exposes but rejects the modern Clipboard API.

### 11.2.9

- Added Podcast Website to the canonical website types and mapped it to the publication entity contract.
- Added JetEngine to the explicit red-flag plugin policy.
- Added a selectable Core-powered Details panel below the HWS Photos gallery with full and generated-size URLs, new-tab links, and dynamic clipboard buttons.
- Updated the bundled Hexa WP Core package to 1.1.8.

### 11.2.8

- Added a dedicated Wikidata URL to the canonical HWS user-profile URL group, including legacy metadata migration and shortcode support.

### 11.2.7

- Removed synchronous GitHub version requests from the dashboard sidebar and kept it on local/cached version data.
- Moved the Git updater panels from the default Overview into Update Center so the initial HWS dashboard request stays local.

### 11.2.6

- Added direct new-tab links from the Brand Assets primary-author image card to the WordPress profile editor and public author archive.
- Restored all lazy-loaded plugin inventory AJAX actions so install, activate, refresh, deactivate, and delete controls reach their registered controllers.

### 11.2.5

- Added a dedicated Mail Authentication tab with an ordered SMTP2GO settings, API-key, and test-delivery workflow.
- Added the same full SMTP2GO authentication test to Quick Start and made legacy SMTP health reporting use its authoritative result.

### 11.2.4

- Suppressed the local Brand Template breadcrumb when the active Elementor header already renders a Rank Math breadcrumb.

### 11.2.3

- Made the Rank Math dependency check admin-safe because Rank Math registers its breadcrumb shortcode only for frontend rendering requests.

### 11.2.2

- Replaced Elementor Pro's Yoast-only breadcrumb widget with Elementor's native Shortcode widget and the Rank Math `[rank_math_breadcrumb]` shortcode.
- Added an import dependency check so Brand Templates cannot claim Rank Math breadcrumb support when the shortcode is unavailable.

### 11.2.1

- Preserved escaped Elementor dynamic-tag JSON in versioned managed-template backups.
- Added strict backup decoding and JSON validation so a corrupt snapshot cannot be applied.

### 11.2.0

- Added the Brand Templates dashboard for Author, Page, Single Post, Category, and Tag defaults.
- Added conflict-aware, reversible native Elementor Theme Builder imports with exact preview contexts and conditions.
- Added plugin-owned Author, Page, Category, and Tag fallbacks plus optional scoped Page and Single Post content styles.
- Added Rank Math breadcrumbs, dynamic archive data, responsive current-query grids, duplicate-breadcrumb protection, and focused regression coverage.

### 11.1.7

- Left new sites unclassified until an administrator deliberately selects a website type, without changing existing saved selections.
- Cleaned up the optional primary-author empty state and removed the blank Smart Search selection strip.
- Updated the bundled Hexa WP Core to 1.1.4.

### 11.1.6

- Added a UI Cleanup option that completely removes WooCommerce customer billing and shipping sections from profile and user-edit screens.
- Added the primary WordPress author profile image as a visible Site Icon and favicon source, using the existing PNG and ICO workflow.

### 11.1.5

- Moved the selected primary author’s complete WordPress/ACF field inventory from Website & Primary Entity to the Custom Post Types tab after ACF Structures.
- Updated the bundled Hexa WP Core to 1.1.3.

### 11.1.4

- Displayed primary-author social links as labeled rows with their complete clickable URLs.
- Updated the bundled Hexa WP Core to 1.1.2.

### 11.0.0

- Established HWS as the stable source of truth for website classification and optional primary entities.
- Standardized shared CPT, ACF, entity, schema, AJAX, updater, and admin UI integrations on Hexa WP Core 1.0.0.
- Preserved optional entity configuration and existing content, field, slug, and label settings across the major upgrade.

### 10.18.154

- Made HWS the canonical source for website classification and optional primary entities.
- Consolidated shared custom post types and ACF structures behind Hexa WP Core with editable labels and rewrite slugs.
- Removed duplicate legacy CPT/ACF registration paths while preserving existing option state.
- Updated the bundled Hexa WordPress Plugin Core to 0.19.78.
- Consolidated this README; full historical release details remain in Git history.

## Support

Report issues at <https://github.com/mikeyperes/hws-base-tools/issues>.

## License

Proprietary Hexa Web Systems software unless a source file states otherwise.
