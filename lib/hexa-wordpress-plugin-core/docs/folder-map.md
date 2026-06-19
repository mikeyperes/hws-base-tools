# Folder Map

This package uses one root namespace and explicit folders for every sub-namespace.

## Fixed Root

```text
Hexa\PluginCore\
```

The Composer package name is:

```text
hexa/plugin-core
```

The repository folder is:

```text
hexa-wordpress-plugin-core
```

## Sub-Namespace Folders

| Folder | Namespace | Purpose |
| --- | --- | --- |
| `src/Activity/` | `Hexa\PluginCore\Activity` | Activity logs and activity storage adapters. |
| `src/Bootstrap/` | `Hexa\PluginCore\Bootstrap` | Core setup, module registration, and lifecycle. |
| `src/Contracts/` | `Hexa\PluginCore\Contracts` | Interfaces shared across modules and host plugins. |
| `src/Shortcodes/` | `Hexa\PluginCore\Shortcodes` | Shortcode definitions, registries, dashboard lists, and testing. |
| `src/Support/` | `Hexa\PluginCore\Support` | Shared value objects and small helpers. |
| `src/Tabs/` | `Hexa\PluginCore\Tabs` | Admin tab definitions, registries, and rendering contracts. |
| `src/Updater/` | `Hexa\PluginCore\Updater` | GitHub/update configuration and updater contracts. |

## Naming Rules

Class names are singular unless they are collection registries.

Good:

```text
ActivityLogEntry
ActivityLogger
CoreBootstrap
PluginContext
ShortcodeDefinition
ShortcodeRegistry
TabDefinition
TabRegistry
UpdaterConfig
```

Bad:

```text
HwsActivityLogger
HexaBaseToolsTabs
PluginCoreShortcodesManagerThing
UpdaterStuff
```

## Adding A New Namespace

Do not add a new sub-namespace casually.

Before adding one, document:

1. Why none of the existing folders fit.
2. The exact folder name.
3. The exact namespace.
4. The public classes that will live there.
5. Which host plugin needs it first.

Then update:

- `README.md`
- `AGENTS.md`
- `docs/folder-map.md`

