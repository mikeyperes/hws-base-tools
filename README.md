# HWS Base Tools

Shared WordPress site configuration, administration, and operational tooling for Hexa-managed websites.

## Ownership

HWS Base Tools is the canonical owner of:

- Website classification: News Outlet, Personal Website, Company Website, e-Commerce Website, or Other.
- The optional primary entity selection consumed by profile and publication plugins.
- Shared custom post types: `organization`, `team-member`, `testimonial`, and `services`.
- Brand assets, common shortcodes, site checks, maintenance tools, and WordPress admin cleanup.

The primary entity is optional. Sites that do not need a canonical person, organization, publication, or verified profile continue to work without one.

HWS does not own publication-specific Knowledge Base or Resources post types. SMP Publication Integration owns those structures.

## Architecture

The canonical entry point is `hws-base-tools.php`. `initialization.php` remains a compatibility loader for older active-plugin records.

Namespaced implementation code lives under `src/`. Historical root files are compatibility adapters and are not destinations for new behavior.

Reusable cross-plugin behavior comes from the bundled Hexa WordPress Plugin Core:

- Dashboard tabs, collapsible cards, buttons, activity logs, and guarded AJAX.
- Custom post type, taxonomy, and ACF structure registration and settings UI.
- Canonical entity resolution and attached-user field inspection.
- FAQ normalization/rendering and schema document utilities.
- Plugin/Core update reporting, provisioning, cleanup, search, and system utilities.

See [docs/architecture.md](docs/architecture.md) and [HEXA_PLUGIN_CORE_LIBRARY.md](HEXA_PLUGIN_CORE_LIBRARY.md).

## Dashboard

The HWS dashboard includes:

- Overview and Quick Start readiness checks.
- Website and Primary Entity settings.
- Custom Post Types and ACF structure controls.
- Plugins, themes, updates, backups, cleanup, and system checks.
- Brand Assets, Footer Text, UI Cleanup, and Site Structure tools.
- Sitemaps, Search Display, Search Behavior, Features, and Shortcodes.
- Brand Templates for safe WordPress fallbacks and native Elementor Theme Builder imports.

Dashboard navigation and asynchronous tab loading use Hexa WP Core. Operational actions report progress without requiring full page refreshes.

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
| `[website_content id="..."]` | Read a configured Website Settings value. |
| `[website_url]` | Return the site URL. |
| `[current_year]` | Return the current year. |

Legacy `[founder]` and `[company]` compatibility remains available, but profile-specific behavior belongs to the corresponding profile plugin.

## Requirements

| Requirement | Minimum |
| --- | --- |
| WordPress | 6.0 |
| PHP | 8.1 |
| Hexa WP Core bundle | 1.1.4 |

ACF or ACF Pro is optional and is required only for ACF-backed structures and values. Individual operational panels may require the plugin they inspect, such as LiteSpeed Cache or Rank Math.

## Installation

Install the repository as `wp-content/plugins/hws-base-tools`, activate `hws-base-tools.php`, and open **Settings > HWS Core Tools**. Existing installs activated through `initialization.php` are migrated by the compatibility bootstrap.

## Development

Run the complete static, unit, architecture, Core-integrity, and PHP-lint suite with:

```bash
php tests/run.php
```

The bundled Core `VERSION`, `PACKAGE_HASH`, executable source, and root `HEXA_PLUGIN_CORE_LIBRARY.md` must match the canonical Core repository exactly.

## Changelog

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
