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

1. Hexa Core tab design: rendered from `Hexa\PluginCore\UI\CoreUi`.
2. Overview error-log viewer: rendered from `Hexa\PluginCore\Logs\ErrorLogPanelRenderer`.
3. Feature rows: replace hard-coded feature cards with core card/toggle primitives.
4. Brand asset rows: replace logo/favicons cards with core subcards and copy rows.
5. Update Center panels: replace local updater panels with core updater UI primitives.
6. Tab framework: replace HWS tab output with the core tab registry while preserving existing tab IDs.

## Error Log Findings

HWS currently has two log systems:

- Overview viewer in `settings-dashboard.php`: displays `debug.log`, root `error_log`, and `wp-admin/error_log`; extracts fatal/syntax lines; provides search and delete buttons.
- Cleaner in `settings-dashboard-log-delete-cron.php`: owns scheduled deletion, manual cleanup, options, and cleaner activity output.

The first migration moves the Overview display into core. The cleaner remains in HWS until core has a scheduler/options abstraction.
