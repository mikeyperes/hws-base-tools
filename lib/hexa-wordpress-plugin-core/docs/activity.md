# Activity Namespace

Namespace:

```text
Hexa\PluginCore\Activity
```

Folder:

```text
src/Activity/
```

## Purpose

The activity namespace stores normalized activity log records for admin actions, updater actions, tests, and maintenance tasks.

## Required Record Shape

Activity records should include:

- message
- context
- timestamp
- actor, when available
- source module

Activity logs must avoid secrets, tokens, passwords, private keys, and raw request payloads.

