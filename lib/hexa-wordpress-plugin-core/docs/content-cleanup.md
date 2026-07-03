# Content Cleanup

Namespace:

```text
Hexa\PluginCore\ContentCleanup
```

Use this namespace when a host plugin needs to detect old WordPress content and expose guarded cleanup actions through wp-admin.

## Classes

- `ContentCleanupConfig`: owns host-specific labels, action names, nonce settings, allowed post types, statuses, default filters, fixed report mode, detection rules, limits, and protected post IDs.
- `ContentCleanupScanner`: normalizes criteria, queries WordPress content, applies detection rules, marks protected rows, and performs trash or permanent delete actions.
- `ContentCleanupAjaxController`: registers scan, trash, and delete AJAX actions through `WpAdminAjax\AjaxActionRegistry`.
- `ContentCleanupRenderer`: renders the filter UI, results table, edit links, destructive action buttons, and Hexa Core Log Type 1 activity log.

## Host Responsibilities

- Pass plugin-specific AJAX action names.
- Pass a plugin-specific nonce action and nonce field.
- Limit `post_types` to the content the tool is allowed to manage.
- Keep destructive actions behind a capability such as `manage_options`.
- Add plugin-specific protected page IDs when needed.
- Set `show_filters` to `false` when the host wants a fixed report instead of a visible filter form.
- Pass `detection_rules` when the report should show only matching content and display yellow/red row flags.

Core always protects the WordPress front page, posts page, and privacy policy page.

## Example

```php
use Hexa\PluginCore\ContentCleanup\ContentCleanupAjaxController;
use Hexa\PluginCore\ContentCleanup\ContentCleanupConfig;
use Hexa\PluginCore\ContentCleanup\ContentCleanupRenderer;

$config = new ContentCleanupConfig([
    'root_id'                => 'example-cleanup',
    'title'                  => 'Cleanup',
    'description'            => 'Detect old pages and clean them up through guarded AJAX actions.',
    'capability'             => 'manage_options',
    'nonce_action'           => 'example_cleanup',
    'nonce_field'            => 'nonce',
    'scan_action'            => 'example_cleanup_scan',
    'trash_action'           => 'example_cleanup_trash',
    'delete_action'          => 'example_cleanup_delete',
    'post_types'             => [ 'page' => 'Pages' ],
    'default_post_type'      => 'page',
    'default_status'         => 'publish',
    'default_published_days' => 365,
    'default_limit'          => 50,
    'show_filters'           => false,
    'count_label'            => 'Reported',
    'detection_rules'        => [
        [
            'id'                 => 'home_not_front',
            'label'              => 'Home',
            'tone'               => 'warning',
            'terms'              => [ 'home' ],
            'fields'             => [ 'title', 'slug' ],
            'exclude_option_ids' => [ 'page_on_front' ],
            'description'        => 'Contains home but is not the assigned WordPress front page.',
        ],
        [
            'id'          => 'old_delete',
            'label'       => 'Old/Delete',
            'tone'        => 'danger',
            'terms'       => [ 'old', 'delete' ],
            'fields'      => [ 'title', 'slug' ],
            'description' => 'Contains old or delete in the title or slug.',
        ],
    ],
]);

( new ContentCleanupAjaxController( $config ) )->register();
( new ContentCleanupRenderer( $config ) )->render();
```

## UI Contract

The renderer shows:

- filter criteria
- detected content rows
- row flags from detection rules
- title with slug below it
- status
- date published
- date modified
- edit link that opens in a new tab
- red move-to-trash action
- red permanent-delete action
- live dark activity log below the table

The UI updates through AJAX without page refreshes. Server responses include activity log entries, and the browser appends each step into the log.
