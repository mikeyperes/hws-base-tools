# HWS Core Migration Scan

This file records the current HWS Base Tools dashboard feature inventory and the order for moving repeated UI and behavior into Hexa WordPress Plugin Core.

## Current Dashboard Tabs

- Overview: going-live checklist, quick setup, debug settings, wp-config controls, SMTP status, Wordfence status, error-log viewer, log cleaner, LiteSpeed, PHP/server checks, Elementor DB updater, plugin info.
- Plugins: monitored plugin status, install/update controls, essential plugin checks, HWS GitHub plugin installs.
- Themes: active theme and cleanup checks.
- Features: feature cards with toggles, descriptions, settings, code examples, tests, activity logs.
- Snippets: deprecated legacy snippet controls retained for compatibility.
- Brand Assets: favicon/site icon, brand colors, logo asset slots, gallery ACF.
- Website Types: ACF and website-type integrations.
- UI Cleanup: admin bar, user profile, editor, footer, and admin UI hiding controls.
- Advanced/Config: debug constants, wp-config editing, public setup URLs.
- Comments: comment and pingback controls.
- Update Center: GitHub updater, core package updater, version history, download ZIP.
- Masked Login: login URL controls.
- Footer Text: shared footer text and injection methods.
- System Checks: health and deployment tests.
- Backups/Log Cleaner: backups, generated files, and log deletion helpers.

## Repeated UI Patterns To Move Into Core

- Panel shells: title, body, footer actions.
- Subcards: compact repeated cards with status, preview, URL rows, and actions.
- Toggle controls: enabled/disabled state, description, proof/test output.
- Tooltips and helper text.
- Collapsible sections and expandable detail rows.
- Activity logs: dark, page-only/transient/permanent modes.
- Code/shortcode rows with copy buttons.
- AJAX action feedback and inline status messages.
- Log viewers: source summaries, tabs, search, highlighting, delete buttons.

## First Core Swaps

1. Hexa Core tab design: rendered from `Hexa\PluginCore\WpAdminComponents\CoreUi`.
2. Overview error-log viewer: rendered from `Hexa\PluginCore\LogFiles\ErrorLogPanelRenderer`.
3. Feature rows: replace hard-coded feature cards with core card/toggle primitives.
4. Brand asset rows: replace logo/favicons cards with core subcards and copy rows.
5. Update Center panels: replace local updater panels with core updater UI primitives.
6. Tab framework: replace HWS tab output with the core tab registry while preserving existing tab IDs.

## Deep Refactor Scan - 2026-06-19

### Extracted To Hexa WordPress Plugin Core

- Safe admin-AJAX guards: `safe-wrappers.php` functions `hws_create_nonce()`, `hws_verify_nonce( $nonce = null )`, `hws_require_ajax_nonce_or_error( $field = 'nonce' )`, and `hws_safe_ajax_handler( $callback, $capability = 'manage_options', $verify_nonce = true )` now delegate to `Hexa\PluginCore\WpAdminAjax\AjaxGuard`.
- System environment helpers: `safe-wrappers.php` functions `hws_is_function_disabled( $function_name )`, `hws_safe_shell_exec( $command )`, `hws_safe_exec( $command, $timeout = 5 )`, `hws_get_constant( $name, $default = null )`, `hws_get_ini( $name, $default = null )`, `hws_parse_size( $size )`, `hws_read_system_file( $path )`, `hws_parse_cgroup_memory_limit( $value )`, `hws_get_cgroup_memory_limit()`, `hws_count_cpuset_cpus( $cpuset )`, `hws_get_cpu_info()`, `hws_get_memory_info()`, `hws_get_cpu_count()`, and `hws_format_bytes( $bytes, $precision = 2 )` now delegate to `Hexa\PluginCore\SystemEnvironment\SystemEnvironment`.
- Plugin provisioning mechanics: `settings-dashboard-check-plugins.php` functions `hws_find_plugin_file_by_folder( string $slug ): string`, `hws_check_additional_hws_plugin_status( string $slug ): array`, `hws_prepare_wp_filesystem()`, `hws_cleanup_install_work_dir( string $path ): void`, `hws_normalize_hws_github_plugin_folder( string $slug )`, `hws_install_hws_github_plugin_package( string $slug, array $plugin )`, `hws_check_plugin_status( $plugin_path )`, `ajax_install_plugin()`, and `ajax_activate_plugin()` now delegate reusable discovery/install/activation logic to `Hexa\PluginCore\PluginProvisioning\PluginProvisioner`.

### Next Generic Extractions

- Plugin library UI: `settings-dashboard-check-plugins.php` still owns HWS-specific plugin catalog data (`hws_get_additional_hws_plugins(): array`, `hws_get_additional_hws_plugin( string $slug ): ?array`) and HTML rendering. Keep catalog data host-owned, but move reusable table/action UI into `WpAdminComponents` once another plugin needs the same panel.
- Repeated scheduler panels: `settings-dashboard-log-delete-cron.php`, `settings-dashboard-backups.php`, and `settings-dashboard-elementor-db-cron.php` repeat enable/disable/update/run-now/state patterns. Extract to a core `ScheduledAdminTask` namespace before touching the individual UIs.
- Secret/public action URLs: `settings-dashboard-update-center.php` and `settings-dashboard-masked-login.php` both define URL keys, shared master secret usage, public output rendering, logs, AJAX toggles, and status payloads. Extract route/key/output behavior to a core `SecretActions` namespace.
- Media and brand assets: favicon generation, ICO writing, image cropping, attachment persistence, logo slots, gallery IDs, and shortcode output are spread across `settings-dashboard.php`, `register-acf-website-settings.php`, and `snippet-website-settings-functionality.php`. Extract generic image processing to `MediaAssets`, then brand-specific registry/output to `BrandAssets`.
- WP config mutation: existing structured bridge `src/Admin/Dashboard/LegacyEventBridge.php` still calls HWS procedural config handlers. The wp-config read/write logic should move into a core `WpConfigFile` namespace before expanding memory/debug controls.
- Feature registry: `initialization.php`, `settings-dashboard-features.php`, and snippet files duplicate feature metadata, toggles, tests, code examples, and logs. Extract definition/render/test contracts to a core `FeatureRegistry` namespace.

## Error Log Findings

HWS currently has two log systems:

- Overview viewer in `settings-dashboard.php`: displays `debug.log`, root `error_log`, and `wp-admin/error_log`; extracts fatal/syntax lines; provides search and delete buttons.
- Cleaner in `settings-dashboard-log-delete-cron.php`: owns scheduled deletion, manual cleanup, options, and cleaner activity output.

The first migration moves the Overview display into core. The cleaner remains in HWS until core has a scheduler/options abstraction.
