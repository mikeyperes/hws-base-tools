# Hexa Plugin Core Library

Copy this file into every plugin that consumes `hexa/plugin-core`. Keep it updated when the core API changes.

This is the quick reference for developers and agents working in separate Codex or Claude chats.

## Fixed Identity

```text
Repository: hexa-wordpress-plugin-core
Composer package: hexa/plugin-core
Root namespace: Hexa\PluginCore\
Source root: src/
Version source: VERSION
```

Do not rename these.

## Folder And Namespace Map

```text
src/Activity/    Hexa\PluginCore\Activity
src/Bootstrap/   Hexa\PluginCore\Bootstrap
src/Contracts/   Hexa\PluginCore\Contracts
src/Shortcodes/  Hexa\PluginCore\Shortcodes
src/Support/     Hexa\PluginCore\Support
src/Tabs/        Hexa\PluginCore\Tabs
src/UI/          Hexa\PluginCore\UI
src/Logs/        Hexa\PluginCore\Logs
src/Updater/     Hexa\PluginCore\Updater
```

## UI Components

Namespace:

```text
Hexa\PluginCore\UI
```

Class:

```text
CoreUi
```

Use this for:

```text
cards
subcards
buttons
pills
tooltips
collapsible sections
copy buttons
shared admin styles
```

Never rebuild those patterns directly in host plugins when `CoreUi` can render them.

## Error Logs

Namespace:

```text
Hexa\PluginCore\Logs
```

Classes:

```text
ErrorLogSource
ErrorLogClassifier
ErrorLogReader
ErrorLogPanelRenderer
```

Use this for reusable log viewing:

```php
( new ErrorLogPanelRenderer() )->render(
    [
        new ErrorLogSource( 'debug', 'debug.log', WP_CONTENT_DIR . '/debug.log', true, 'delete-debug-log' ),
        new ErrorLogSource( 'error', 'error_log', ABSPATH . 'error_log', true, 'delete-error-log' ),
        new ErrorLogSource( 'admin-error', 'wp-admin/error_log', ABSPATH . 'wp-admin/error_log' ),
    ]
);
```

## README Locations

When this core is vendored into a host plugin, the important files are:

```text
lib/hexa-wordpress-plugin-core/README.md
lib/hexa-wordpress-plugin-core/HEXA_PLUGIN_CORE_LIBRARY.md
HEXA_PLUGIN_CORE_LIBRARY.md
```

The host root copy of `HEXA_PLUGIN_CORE_LIBRARY.md` exists so agents can read the rules without opening the vendored package first.

## Activity Log Component

Namespace:

```text
Hexa\PluginCore\Activity
```

Classes:

```text
ActivityLogConfig
ActivityLogEntry
ActivityLogger
ActivityLogRenderer
```

Storage modes:

```text
page       render-only, removed on refresh
transient  stored with set_transient
permanent  stored with update_option
```

Example:

```php
$config = new ActivityLogConfig(
    [
        'id'          => 'example-activity-log',
        'title'       => 'Example Activity Log',
        'storage'     => ActivityLogConfig::STORAGE_TRANSIENT,
        'storage_key' => 'example_activity_log',
    ]
);

$logger = new ActivityLogger( $config );
$logger->add( new ActivityLogEntry( 'Update started.', [], 'admin', 'updater', null, 'info' ) );

( new ActivityLogRenderer( $config ) )->render( $logger->all() );
```

## Required Host Plugin Boot

Every consuming plugin should:

1. Load Composer or the vendored core autoloader.
2. Create `Hexa\PluginCore\Support\PluginContext`.
3. Create `Hexa\PluginCore\Bootstrap\CoreBootstrap`.
4. Add core modules and host adapter modules.
5. Call `boot()` once.

```php
use Hexa\PluginCore\Bootstrap\CoreBootstrap;
use Hexa\PluginCore\Support\PluginContext;

$context = new PluginContext(
    [
        'slug'        => 'example-plugin',
        'basename'    => plugin_basename( __FILE__ ),
        'version'     => '1.0.0',
        'path'        => plugin_dir_path( __FILE__ ),
        'url'         => plugin_dir_url( __FILE__ ),
        'github_repo' => 'owner/example-plugin',
        'admin_page'  => 'example-plugin',
        'capability'  => 'manage_options',
    ]
);

( new CoreBootstrap( $context ) )
    ->add_module( $module )
    ->boot();
```

## Updater

Namespace:

```text
Hexa\PluginCore\Updater
```

Purpose:

- GitHub version checks
- WordPress plugin update transient injection
- GitHub archive folder normalization
- HWS-style plugin update status panel
- Force update check
- Direct update from GitHub
- Normalized plugin ZIP downloads
- Version history ZIP downloads
- Transient-backed update activity log
- Vendored Hexa WordPress Plugin Core status/update panel

### Required Updater Config

The updater can be initialized from a plugin file and a GitHub URL/repo:

```php
use Hexa\PluginCore\Updater\UpdaterConfig;
use Hexa\PluginCore\Updater\GitHubPluginUpdater;
use Hexa\PluginCore\Updater\UpdaterAjaxController;
use Hexa\PluginCore\Updater\UpdaterPanelRenderer;

$updater_config = UpdaterConfig::from_plugin_file(
    __FILE__,
    'https://github.com/owner/example-plugin',
    [
        'plugin_slug'          => 'example-plugin',
        'plugin_starter_file'  => 'example-plugin.php',
        'github_branch'        => 'main',
        'nonce_action'         => 'example_plugin_nonce',
        'ajax_action_prefix'   => 'example_plugin_updater',
        'capability'           => 'update_plugins',
        'download_capability'  => 'manage_options',
    ]
);

( new GitHubPluginUpdater( $updater_config ) )->register();
( new UpdaterAjaxController( $updater_config ) )->register();
```

Render the panel in an admin page:

```php
( new UpdaterPanelRenderer( $updater_config ) )->render();
```

## Automatic Core Tab

Namespace:

```text
Hexa\PluginCore\Tabs
```

Classes:

```text
CoreTabConfig
CoreTabModule
CoreTabRenderer
```

The host dashboard must expose:

```text
one filter that returns the tab list
one filter that renders a selected tab and returns true when rendered
```

Then register:

```php
( new CoreTabModule(
    new CoreTabConfig(
        [
            'tabs_filter'   => 'example_dashboard_tabs',
            'render_filter' => 'example_dashboard_render_tab',
            'core_root'     => __DIR__ . '/lib/hexa-wordpress-plugin-core',
            'readme_path'   => __DIR__ . '/lib/hexa-wordpress-plugin-core/README.md',
            'library_path'  => __DIR__ . '/HEXA_PLUGIN_CORE_LIBRARY.md',
        ]
    )
) )->register();
```

The default tab is:

```text
ID: hexa-core
Label: Hexa WordPress Plugin Core
```

If the caller only has a plugin folder slug and a GitHub URL, use:

```php
$updater_config = UpdaterConfig::from_slug_and_github_url(
    'example-plugin',
    'https://github.com/owner/example-plugin',
    [
        'plugin_starter_file' => 'example-plugin.php',
        'plugin_name'         => 'Example Plugin',
        'version'             => '1.0.0',
    ]
);
```

### Updater Classes

```text
UpdaterConfig
GitHubVersionClient
GitHubPluginUpdater
PluginUpdateStatus
UpdateProgressStore
DirectPluginInstaller
PluginZipBuilder
UpdaterAjaxController
UpdaterPanelRenderer
UpdaterFilesystem
CorePackageConfig
CorePackageVersionClient
CorePackageStatus
CorePackageInstaller
CorePackageAjaxController
CorePackagePanelRenderer
```

### Vendored Core Package Updater

The Hexa WordPress Plugin Core is a library, not a WordPress plugin. Its version is stored in `VERSION`.

Host plugins that vendor the core should place a core status panel directly under their plugin updater panel:

```php
use Hexa\PluginCore\Updater\CorePackageAjaxController;
use Hexa\PluginCore\Updater\CorePackageConfig;
use Hexa\PluginCore\Updater\CorePackagePanelRenderer;

$core_config = CorePackageConfig::from_core_root(
    __DIR__ . '/lib/hexa-wordpress-plugin-core',
    [
        'github_repo'        => 'mikeyperes/hexa-wordpress-plugin-core',
        'github_branch'      => 'main',
        'nonce_action'       => 'example_plugin_nonce',
        'ajax_action_prefix' => 'example_plugin_core_package',
    ]
);

( new CorePackageAjaxController( $core_config ) )->register();
( new CorePackagePanelRenderer( $core_config ) )->render();
```

The panel compares:

```text
vendored VERSION in the host plugin
public GitHub VERSION from mikeyperes/hexa-wordpress-plugin-core
```

Do not use the WordPress plugin header updater for the core library.

### Updater Input Terms

Use these terms consistently:

```text
plugin_slug: folder slug, e.g. hws-base-tools
plugin_basename: folder/main-file.php, e.g. hws-base-tools/hws-base-tools.php
plugin_starter_file: main plugin file, e.g. hws-base-tools.php
github_repo: owner/repo, e.g. mikeyperes/hws-base-tools
github_url: full GitHub URL, normalized internally to owner/repo
github_branch: branch to check and download, usually main
```

### Updater Behavior

`GitHubPluginUpdater` registers:

```text
pre_set_site_transient_update_plugins
plugins_api
upgrader_source_selection
upgrader_post_install
http_request_timeout
http_request_args
```

`DirectPluginInstaller` performs:

```text
download GitHub ZIP
extract into wp-content/upgrade
find plugin folder
rename repo-main to the canonical plugin slug
verify the starter file exists
back up the current canonical folder
install the new folder
remove duplicate slug-* folders
repoint active_plugins to canonical basename
clear update caches
write progress steps to a transient
```

## Shortcodes

Namespace:

```text
Hexa\PluginCore\Shortcodes
```

Purpose:

- define shortcode metadata
- collect shortcodes in a registry
- prepare one shortcode test at a time
- support admin UIs that show shortcode, description, test method, test input, and output

Core classes:

```text
ShortcodeDefinition
ShortcodeRegistry
ShortcodeTestResult
ShortcodeTester
```

Example:

```php
use Hexa\PluginCore\Shortcodes\ShortcodeDefinition;
use Hexa\PluginCore\Shortcodes\ShortcodeRegistry;

$registry = ( new ShortcodeRegistry() )
    ->add(
        new ShortcodeDefinition(
            'display_year',
            'Current Year',
            '[display_year]',
            'Outputs the current four-digit year.',
            'Runs without input and checks for non-empty output.'
        )
    );
```

## Tabs

Namespace:

```text
Hexa\PluginCore\Tabs
```

Purpose:

- define admin tab IDs and labels
- keep tab registration consistent
- avoid scattered tab arrays across plugins

Core classes:

```text
TabDefinition
TabRegistry
```

Tab IDs must be lowercase slugs, not labels:

```text
overview
shortcodes
update-center
brand-assets
```

## Activity

Namespace:

```text
Hexa\PluginCore\Activity
```

Purpose:

- standardize activity log entries
- record admin actions, updater actions, and tests

Core classes:

```text
ActivityLogEntry
ActivityLogger
```

Activity logs must not include secrets, tokens, private keys, or raw request payloads.

## Contracts

Namespace:

```text
Hexa\PluginCore\Contracts
```

Core interfaces:

```text
ModuleInterface
PluginContextInterface
```

Modules must register hooks only from:

```php
public function register(): void;
```

Do not execute feature behavior at include time.

## Support

Namespace:

```text
Hexa\PluginCore\Support
```

Core classes:

```text
PluginContext
```

Use `PluginContext` for host plugin identity. Do not hard-code host plugin names inside shared core classes.

## Agent Checklist

Before changing a plugin that consumes this core:

1. Identify whether the change belongs in the shared core or the host plugin.
2. If it is reusable across plugins, put it in `Hexa\PluginCore`.
3. If it is plugin-specific, keep it in that plugin's namespace.
4. Use the exact folder/namespace map above.
5. Do not invent new names for existing concepts.
6. Update this file when adding public core behavior.
