# Updater Namespace

Namespace:

```text
Hexa\PluginCore\Updater
```

Folder:

```text
src/Updater/
```

## Purpose

The updater namespace standardizes GitHub/update configuration.

The updater core must not hard-code one plugin's repository. It reads repository and plugin identity from the host `PluginContext` or an explicit `UpdaterConfig`.

The updater namespace starts with the HWS Base Tools updater behavior and makes it abstract:

- a plugin passes its slug/basename and GitHub repo URL
- GitHub URLs are normalized to `owner/repo`
- the updater checks the remote plugin header version
- WordPress core update transients receive the GitHub version
- GitHub `repo-main` folders are normalized to the canonical plugin folder
- the direct updater downloads, extracts, backs up, installs, cleans duplicate folders, and clears caches
- the admin panel can render the same update-status flow for any host plugin

## GitHub Slug Rule

GitHub repositories use:

```text
owner/repository
```

Do not include a protocol, branch, or `.git` suffix in the repository value.

Good:

```text
mikeyperes/hws-base-tools
```

Bad:

```text
https://github.com/mikeyperes/hws-base-tools
hws-base-tools-main
mikeyperes/hws-base-tools.git
```

## Classes

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
```

## Minimal Host Integration

```php
use Hexa\PluginCore\Updater\GitHubPluginUpdater;
use Hexa\PluginCore\Updater\UpdaterAjaxController;
use Hexa\PluginCore\Updater\UpdaterConfig;
use Hexa\PluginCore\Updater\UpdaterPanelRenderer;

$config = UpdaterConfig::from_plugin_file(
    __FILE__,
    'https://github.com/owner/example-plugin',
    [
        'plugin_slug'         => 'example-plugin',
        'plugin_starter_file' => 'example-plugin.php',
        'nonce_action'        => 'example_plugin_nonce',
        'ajax_action_prefix'  => 'example_plugin_updater',
    ]
);

( new GitHubPluginUpdater( $config ) )->register();
( new UpdaterAjaxController( $config ) )->register();

// Inside an admin page:
( new UpdaterPanelRenderer( $config ) )->render();
```

Slug plus GitHub URL setup:

```php
$config = UpdaterConfig::from_slug_and_github_url(
    'example-plugin',
    'https://github.com/owner/example-plugin',
    [
        'plugin_starter_file' => 'example-plugin.php',
        'plugin_name'         => 'Example Plugin',
        'version'             => '1.0.0',
    ]
);
```

## Required Terms

Use these names consistently:

| Term | Meaning |
| --- | --- |
| `plugin_slug` | Plugin folder slug. |
| `plugin_basename` | WordPress plugin basename, `folder/main-file.php`. |
| `plugin_starter_file` | Main plugin file with the WordPress plugin header. |
| `canonical_plugin_basename` | Expected canonical basename after normalization. |
| `github_repo` | GitHub `owner/repo`. |
| `github_branch` | Branch to check/download. |
