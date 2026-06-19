# Tabs Namespace

Namespace:

```text
Hexa\PluginCore\Tabs
```

Folder:

```text
src/Tabs/
```

## Purpose

The tabs namespace standardizes admin tab definitions and rendering.

Host plugins decide which tabs exist. The core provides a consistent registry and definition format.

## Required Pattern

Each tab has:

- ID
- label
- renderer
- optional capability
- optional status metadata, such as deprecated

Tab IDs are lowercase slugs:

```text
overview
shortcodes
settings
update-center
```

Do not use labels as IDs. Do not include emojis in IDs.

