# Hexa WordPress Plugin Core Agent Instructions

These instructions are for Codex, Claude, and any other implementation agent modifying or consuming `hexa-wordpress-plugin-core`.

## Non-Negotiable Names

Use these exact names:

- Repository folder: `hexa-wordpress-plugin-core`
- Composer package: `hexa/plugin-core`
- Root PHP namespace: `Hexa\PluginCore\`
- Source folder: `src/`
- Version file: `VERSION`

Do not invent alternatives. Do not use host plugin names inside this package.

Forbidden shared-core namespaces:

- `HWS\BaseTools\PluginCore`
- `hws_base_tools`
- `HexaWordPressPluginCore`
- `Hexa\Core`
- `HexaWP`
- `HexaTiger`

Host plugin namespaces may exist only inside the host plugin repository. Shared code in this package must remain `Hexa\PluginCore`.

## Folder Contract

Each sub-namespace must have a matching folder:

```text
src/Activity/    Hexa\PluginCore\Activity
src/Bootstrap/   Hexa\PluginCore\Bootstrap
src/Contracts/   Hexa\PluginCore\Contracts
src/Shortcodes/  Hexa\PluginCore\Shortcodes
src/Support/     Hexa\PluginCore\Support
src/Tabs/        Hexa\PluginCore\Tabs
src/Updater/     Hexa\PluginCore\Updater
```

If you add a namespace, add it to `README.md`, `docs/folder-map.md`, and this file in the same change.

## Setup Protocol

Every host plugin must initialize the core in the same order:

1. Load Composer or vendored autoload.
2. Create `Hexa\PluginCore\Support\PluginContext`.
3. Create `Hexa\PluginCore\Bootstrap\CoreBootstrap`.
4. Add modules.
5. Call `boot()` once.

Never make a module boot itself at file include time. Modules register hooks from their `register()` method only.

## Implementation Rules

- Put interfaces in `src/Contracts`.
- Put small generic helpers and value objects in `src/Support`.
- Put admin tab abstractions in `src/Tabs`.
- Put activity log abstractions, storage modes, and the shared dark renderer in `src/Activity`.
- Put shortcode registries, definitions, and testing tools in `src/Shortcodes`.
- Put update/GitHub configuration and updater abstractions in `src/Updater`.
- Put vendored core package version checks and core package update UI in `src/Updater`; do not treat the shared core as a WordPress plugin.
- Put bootstrap/lifecycle orchestration in `src/Bootstrap`.

## WordPress Rules

- Core code may call WordPress functions only where the class is explicitly WordPress-facing.
- Generic value objects should not call WordPress functions.
- Do not hard-code `manage_options`; read capability from the context unless a host explicitly overrides it.
- Do not hard-code plugin slugs, GitHub repos, admin page slugs, paths, URLs, or versions.

## Documentation Rule

Any new public class or protocol must be documented in `docs/`.

Minimum documentation for a new implementation:

- namespace
- folder
- purpose
- host plugin responsibilities
- example usage
- testing method

Also update `HEXA_PLUGIN_CORE_LIBRARY.md` whenever a public namespace, class, setup protocol, or host integration pattern changes. That file is intended to be copied into every plugin that consumes the core.

The automatic core tab must remain host-neutral. Host plugins provide hook names; the implementation stays in `Hexa\PluginCore\Tabs`.

## Commit Hygiene

Do not create backup files. Do not commit generated vendor files unless explicitly requested. Keep implementation and docs in the same commit when they define a new public pattern.
