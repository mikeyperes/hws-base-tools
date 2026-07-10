# HWS Core Migration Scan

This is the current HWS-to-Core ownership audit. The implementation contract is
in `docs/architecture.md`; the complete reusable Core API is in
`HEXA_PLUGIN_CORE_LIBRARY.md`.

## Completed Core Integrations

- Package runtime: every vendored candidate registers through Core
  `bootstrap.php`; one compatible package root is selected before Core classes
  load, and collisions/source mismatches are reportable.
- Admin tabs: HWS tab definitions use `WpAdminTabs\TabRegistry` and
  `HostTabsRenderer`; tab bodies and mapped AJAX handlers load on demand.
- Admin components: collapsible cards, detail cards, toggles, dynamic buttons,
  color controls, and shared visual patterns come from `WpAdminComponents`.
- AJAX: HWS nonce/capability wrappers delegate to `WpAdminAjax\AjaxGuard`.
- Activity logs: reusable log configuration, storage modes, entries, and dark
  expandable displays come from `ActivityLog`.
- Plugin inventory: reusable status, required/optional presentation,
  install/activate/deactivate/delete actions, and GitHub slug normalization come
  from `PluginChecks` and `PluginProvisioning`; HWS owns only its catalog.
- Updates: native GitHub updates, direct installs, ZIP normalization, progress,
  update panels, and vendored Core updates come from `PluginUpdates` and
  `CorePackageUpdates`.
- Shortcodes: HWS owns shortcode definitions and callbacks; catalog display,
  parameters, examples, test methods, and real output come from
  `ShortcodeRegistry`.
- Cleanup: stale content, backups, article/media deletion, protected pages,
  batch progress, and live reports come from `ContentCleanup`.
- Database cleanup: provider-backed cleanup sessions and table iteration come
  from `DatabaseCleanup`.
- Object cache: LiteSpeed/Redis configuration and real cache round-trip checks
  come from `ObjectCache`.
- System environment: constants, ini values, memory, CPU, cgroup, shell safety,
  and byte formatting come from `SystemEnvironment`.
- wp-config: safe constant and ini-style mutation delegates to `WpConfigFile`.
- Cron: schedule registration, status, and unscheduling delegate to
  `WpCronTasks`.
- UI cleanup: definitions, AJAX saves, selector hiding, postbox collapse, and
  footer filtering use `WpAdminUiCleanup`; HWS owns its selector policy.
- Site structure: critical-page and navigation assignment behavior uses
  `SiteStructure`.
- Fields/schema/FAQ: reusable ACF/CPT/taxonomy displays and profile/schema/FAQ
  structures use `FieldStructures`, `AcfFieldFactory`, `SchemaDetection`,
  `SchemaTools`, and `FaqSets` where applicable.
- Smart search and credentials: reusable content lookup and protected key fields
  come from `SmartSearch` and `CredentialVault`.

## HWS Domain Boundaries

- `PluginRuntime`: canonical boot, metadata, request classification, Core setup.
- `AdminDashboard`: HWS tab registry, dashboard bridge, and dashboard assets.
- `FeatureCatalog`: HWS feature/snippet/shortcode definitions and adapters.
- `FrontendContent`: option-gated frontend hooks and content transforms.
- `AcfFields`: HWS-owned field groups and opt-in legacy SMP compatibility.
- `BrandAssets`: site logo, favicon, palette, and historical shortcode adapters.
- `PluginPolicy`: HWS required, optional, forbidden, and library plugin lists.
- `SystemHealth`: HWS-specific health composition and status presentation.
- `Maintenance`: HWS cleanup schedules and Core service configuration.
- `SitemapTools`: sitemap policy, discovery, and cache-control adapters.
- `SiteProfile`: website type and site identity policy.
- `SiteStructure`: HWS page/menu configuration.
- `UiCleanup`: HWS wp-admin cleanup definitions.
- `Security`: encrypted secret storage and disabled-by-default remote actions.
- `LegacyCompatibility`: procedural callback adapters that cannot yet be renamed.

## Remaining Extraction Candidates

These are boundaries for future Core releases, not permission to duplicate code
inside HWS:

- Secret actions: Update Center and Masked Login still share route-state,
  master-secret, audit-output, and enable/disable concepts. A future Core
  `SecretActions` module should own that mechanism while HWS owns route policy.
- Media assets: ICO generation, image resizing, square derivatives, attachment
  persistence, and logo constraints can become a Core `MediaAssets` service.
- Feature catalog: definitions, toggle state, test callbacks, code examples, and
  logs can become a Core `FeatureRegistry`; HWS retains feature data.
- Scheduled-task UI: log cleaner, backup cleaner, and Elementor DB jobs use Core
  cron mechanics but still have HWS-specific repeated settings panels.
- Historical SMP compatibility: founder/company shortcodes and dormant SMP ACF
  groups must move to their owning SMP plugins after deployed references are
  inventoried.

## Flat-Code Prevention

- `hws-base-tools.php` is the canonical entry and contains no feature behavior.
- `initialization.php` is a thin legacy entry.
- Root implementation filenames are compatibility shims only.
- New implementations go in a named `src/` domain.
- New cross-plugin mechanisms go to the standalone Core repository first, then
  the released Core package is vendored into HWS.
- Large `legacy-*` files are migration surfaces, not extension points. New logic
  must be extracted into a class and called through a thin adapter.

## Verification Baseline

- `composer test` lints every PHP file and verifies architecture/security rules.
- Core package tests verify package integrity, single-root selection, collision
  reporting, and database cleanup state restoration.
- Release verification must also cover frontend, wp-admin, HWS AJAX tabs, cron,
  updater, media/editor tabs, and browser console/page errors.
