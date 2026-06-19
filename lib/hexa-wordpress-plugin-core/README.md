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

## Required Folder Map

Every sub-namespace has its own folder under `src/`.

```text
hexa-wordpress-plugin-core/
  src/
    Activity/    -> Hexa\PluginCore\Activity
    Bootstrap/   -> Hexa\PluginCore\Bootstrap
    Contracts/   -> Hexa\PluginCore\Contracts
    Shortcodes/  -> Hexa\PluginCore\Shortcodes
    Support/     -> Hexa\PluginCore\Support
    Tabs/        -> Hexa\PluginCore\Tabs
    Updater/     -> Hexa\PluginCore\Updater
```

Do not create `HWS\BaseTools\PluginCore`, `HexaWordPressPluginCore`, `Hexa\Core`, or plugin-specific namespaces inside this package. Consuming plugins may have their own namespaces, but this shared package always stays under `Hexa\PluginCore`.

## First Core Areas

- `Activity`: shared activity log records and storage adapters.
- `Bootstrap`: consistent setup/init protocol for loading this core in a host plugin.
- `Contracts`: interfaces that host plugins and core modules must follow.
- `Shortcodes`: shortcode definition registry, dashboard metadata, and test runner contracts.
- `Support`: small shared value objects and helpers that are not specific to one module.
- `Tabs`: admin tab definitions, registry, and rendering contracts.
- `Updater`: shared GitHub/update configuration objects and updater contracts.

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
