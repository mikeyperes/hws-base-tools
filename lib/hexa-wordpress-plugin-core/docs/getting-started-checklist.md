# Getting Started Checklist

Namespace:

```text
Hexa\PluginCore\GettingStartedChecklist
```

Use this module when a plugin needs a reusable setup or onboarding process that runs plugin-owned callbacks in a predictable sequence.

## Core Classes

- `GettingStartedChecklistConfig`: host-specific IDs, labels, action names, nonce settings, capability, and registered steps.
- `GettingStartedChecklistStep`: one top-level checklist item. A step may have a callback, subtasks, or both.
- `GettingStartedChecklistSubtask`: one nested checklist item under a parent step.
- `GettingStartedChecklistRunner`: executes one step or subtask and normalizes callback results.
- `GettingStartedChecklistAjaxController`: registers the guarded AJAX endpoint through `WpAdminAjax\AjaxActionRegistry`.
- `GettingStartedChecklistRenderer`: renders the checklist UI, sequential AJAX runner, spinner/check/X states, nested subtasks, and technical activity log.

## Host Plugin Rule

The host plugin owns only the process definition and callback functions. The UI, AJAX contract, sequential execution, status rendering, and log rendering belong in Hexa WP Core.

## Basic Setup

```php
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistAjaxController;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistConfig;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistRenderer;

$config = new GettingStartedChecklistConfig([
    'root_id'      => 'my-plugin-getting-started',
    'title'        => 'Getting Started Checklist',
    'description'  => 'Runs setup checks for this plugin.',
    'capability'   => 'manage_options',
    'nonce_action' => 'my_plugin_getting_started',
    'nonce_field'  => 'nonce',
    'run_action'   => 'my_plugin_getting_started_run_item',
    'steps'        => [
        [
            'id'          => 'environment',
            'label'       => 'Verify Environment',
            'description' => 'Checks WordPress and PHP values.',
            'subtasks'    => [
                [
                    'id'       => 'wordpress',
                    'label'    => 'WordPress Runtime',
                    'callback' => 'my_plugin_check_wordpress_runtime',
                ],
            ],
        ],
    ],
]);

add_action('init', function() use ($config) {
    ( new GettingStartedChecklistAjaxController($config) )->register();
});

function my_plugin_render_getting_started_tab(): void {
    ( new GettingStartedChecklistRenderer(my_plugin_getting_started_config()) )->render();
}
```

## Callback Contract

Callbacks receive one array:

```php
[
    'step'       => [...],
    'subtask'    => [...]|null,
    'context'    => [...],
    'is_subtask' => true|false,
    'item_id'    => 'step-id' or 'step-id:subtask-id',
]
```

Callbacks may return:

- `true` or `false`
- a success message string
- `WP_Error`
- an array:

```php
[
    'success' => true,
    'message' => 'Finished.',
    'logs'    => [
        ['level' => 'info', 'message' => 'Detailed log line.', 'context' => ['key' => 'value']],
    ],
    'data'    => ['optional' => 'payload'],
]
```

## UI Behavior

- `Run Checklist` executes top-level steps in order.
- A step with subtasks shows the parent row as running while each subtask runs one after another.
- A completed item shows a green check SVG.
- A failed item shows a red X SVG.
- The currently running item shows a spinner.
- The technical activity log below the checklist receives callback logs and core transition logs in real time.
