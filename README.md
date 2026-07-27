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

Dashboard navigation and asynchronous tab loading use Hexa WP Core. Operational actions report progress without requiring full page refreshes.

## Primary Entity

HWS stores `hws_site_type` and the optional `hws_primary_entity` record. A selected source may be:

- A WordPress user.
- A Verified Profile post.
- An Organization post.

The entity panel shows the selected record, semantic type, public/edit links, bound WordPress author when applicable, WordPress identity values, and every available ACF field grouped by source. SFPF, SMC, SMP Publication, and Verified Profiles consume this canonical selection while retaining read-only legacy fallbacks for migration.

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
| Hexa WP Core bundle | 1.0.0 |

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
