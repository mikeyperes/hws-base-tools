# Hexa WordPress Plugin Core

Shared WordPress plugin core for Hexa plugins.

This package exists to stop each plugin from re-implementing the same admin tabs, activity logs, updater wiring, shortcode lists, and setup patterns differently.

## Package Identity

These names are fixed. Do not rename them in plugin implementations.

| Item | Value |
| --- | --- |
| Repository folder | `hexa-wordpress-plugin-core` |
| Composer package | `hexa/plugin-core` |
| Root namespace | `Hexa\PluginCore\` |
| Autoload path | `src/` |
| Version source | `VERSION` |

## Required Folder Map

Every sub-namespace has its own folder under `src/`.

```text
hexa-wordpress-plugin-core/
  VERSION
  src/
    Activity/    -> Hexa\PluginCore\Activity
    Bootstrap/   -> Hexa\PluginCore\Bootstrap
    Contracts/   -> Hexa\PluginCore\Contracts
    Credentials/ -> Hexa\PluginCore\Credentials
    Search/      -> Hexa\PluginCore\Search
    Shortcodes/  -> Hexa\PluginCore\Shortcodes
    Support/     -> Hexa\PluginCore\Support
    Tabs/        -> Hexa\PluginCore\Tabs
    UI/          -> Hexa\PluginCore\UI
    Logs/        -> Hexa\PluginCore\Logs
    Updater/     -> Hexa\PluginCore\Updater
```

Do not create `HWS\BaseTools\PluginCore`, `HexaWordPressPluginCore`, `Hexa\Core`, or plugin-specific namespaces inside this package. Consuming plugins may have their own namespaces, but this shared package always stays under `Hexa\PluginCore`.

## First Core Areas

- `Activity`: shared activity log records, storage modes, and expandable dark log renderer.
- `Bootstrap`: consistent setup/init protocol for loading this core in a host plugin.
- `Contracts`: interfaces that host plugins and core modules must follow.
- `Credentials`: encrypted API-key/secret storage, masking, and credential field examples.
- `Search`: smart search/X-Search AJAX endpoint and reusable typeahead renderer.
- `Shortcodes`: shortcode definition registry, dashboard metadata, and test runner contracts.
- `Support`: small shared value objects and helpers that are not specific to one module.
- `Tabs`: admin tab definitions, registry, host hook integration, and the automatic Hexa core documentation tab.
- `UI`: shared visual primitives such as cards, subcards, buttons, pills, tooltips, and collapsible sections.
- `Logs`: shared error-log source definitions, tail readers, classifiers, search/highlight UI, and renderers.
- `Updater`: shared GitHub/update configuration objects, host plugin updater, and vendored core package updater.

## Host Plugin Integration Rule

A plugin using this package must provide a host context. The host context is the only place plugin-specific values belong.

Examples of host-specific values:

- plugin slug
- plugin basename
- plugin version
- plugin root path
- plugin root URL
- GitHub repository
- admin page slug
- WordPress capability

Core classes must read those values from `PluginContextInterface`. They must not hard-code a host plugin name.

## Required Setup Protocol

Every plugin that uses this core follows the same sequence:

1. Load Composer autoload or the vendored core autoloader.
2. Build a `PluginContext`.
3. Build a `CoreBootstrap` with that context.
4. Register modules with the bootstrap.
5. Call `boot()` once.

Example:

```php
use Hexa\PluginCore\Bootstrap\CoreBootstrap;
use Hexa\PluginCore\Support\PluginContext;

$context = new PluginContext(
    [
        'slug'        => 'hws-base-tools',
        'basename'    => plugin_basename( __FILE__ ),
        'version'     => '10.18.27',
        'path'        => plugin_dir_path( __FILE__ ),
        'url'         => plugin_dir_url( __FILE__ ),
        'github_repo' => 'mikeyperes/hws-base-tools',
        'admin_page'  => 'hws-core-tools',
        'capability'  => 'manage_options',
    ]
);

( new CoreBootstrap( $context ) )
    ->add_module( $shortcodes_module )
    ->add_module( $tabs_module )
    ->add_module( $updater_module )
    ->boot();
```

## Agent Rule

Before adding implementations in another Codex or Claude chat, read:

- `AGENTS.md`
- `HEXA_PLUGIN_CORE_LIBRARY.md`
- `docs/folder-map.md`
- `docs/setup-protocol.md`
- `docs/implementation-checklist.md`
- the namespace-specific doc for the folder being changed

If a new feature does not fit an existing namespace, document the proposed namespace first before adding code.

## Core Package Versioning

The shared core is a library, not a WordPress plugin. Its current version is stored in the repository root `VERSION` file.

Host plugins that vendor this package should render a separate core-package status panel under the host plugin updater:

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

This panel compares the vendored `VERSION` in the host plugin with the public GitHub repository `VERSION`.

## Activity Log Component

Use the activity component for updater progress, imports, tests, maintenance tasks, and any admin workflow that benefits from a collapsible dark monitor.

Storage modes:

| Mode | Storage | Lifetime |
| --- | --- | --- |
| `page` | Rendered only | Removed on page refresh |
| `transient` | WordPress transient | Removed after TTL or clear |
| `permanent` | WordPress option | Kept until clear |

```php
use Hexa\PluginCore\Activity\ActivityLogConfig;
use Hexa\PluginCore\Activity\ActivityLogEntry;
use Hexa\PluginCore\Activity\ActivityLogger;
use Hexa\PluginCore\Activity\ActivityLogRenderer;

$config = new ActivityLogConfig(
    [
        'id'          => 'example-activity-log',
        'title'       => 'Example Activity Log',
        'storage'     => ActivityLogConfig::STORAGE_TRANSIENT,
        'storage_key' => 'example_activity_log',
        'collapsed'   => false,
    ]
);

$logger = new ActivityLogger( $config );
$logger->add( new ActivityLogEntry( 'Started import.', [ 'batch' => 12 ], 'admin', 'importer', null, 'info' ) );

( new ActivityLogRenderer( $config ) )->render( $logger->all() );
```

## Automatic Core Tab

Host dashboards expose a tab-list filter and tab-render filter. The core registers itself through those hooks:

```php
use Hexa\PluginCore\Tabs\CoreTabConfig;
use Hexa\PluginCore\Tabs\CoreTabModule;

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

## UI Primitives

Use `Hexa\PluginCore\UI\CoreUi` for reusable admin UI pieces.

```php
use Hexa\PluginCore\UI\CoreUi;

CoreUi::render_assets();

echo CoreUi::card(
    [
        'title'     => 'System Status',
        'body_html' => '<p>Everything is healthy.</p>',
        'meta_html' => CoreUi::pill( 'Healthy', 'success' ),
    ]
);

echo CoreUi::collapsible(
    [
        'title'     => 'Advanced details',
        'body_html' => '<p>Hidden until expanded.</p>',
    ]
);
```

## Credentials / API Keys

Use `Hexa\PluginCore\Credentials` for API-key and secret storage.

```php
$store = new \Hexa\PluginCore\Credentials\CredentialStore();
$store->store( 'openai', 'api_key', $raw_key );
$key = $store->get( 'openai', 'api_key' );
$masked = $store->get_masked( 'openai', 'api_key' );
$exists = $store->exists( 'openai', 'api_key' );
```

The storage key pattern is:

```text
hpc_cred_{slug}_{keyName}
```

## Smart Search / X-Search

Use `Hexa\PluginCore\Search` for reusable typeahead search. This is the WordPress equivalent of Laravel `<x-hexa-smart-search>`.

```php
( new \Hexa\PluginCore\Search\SmartSearchRenderer() )->render(
    [
        'id'        => 'plugin-content-search',
        'label'     => 'Find content',
        'source'    => 'posts',
        'post_type' => 'any',
    ]
);
```

The core module registers:

```text
wp_ajax_hexa_plugin_core_smart_search
```

## Error Log Viewer

Use `Hexa\PluginCore\Logs` for reusable error-log monitoring.

```php
use Hexa\PluginCore\Logs\ErrorLogPanelRenderer;
use Hexa\PluginCore\Logs\ErrorLogSource;

( new ErrorLogPanelRenderer() )->render(
    [
        new ErrorLogSource( 'debug', 'debug.log', WP_CONTENT_DIR . '/debug.log', true, 'delete-debug-log' ),
        new ErrorLogSource( 'error', 'error_log', ABSPATH . 'error_log', true, 'delete-error-log' ),
    ]
);
```
