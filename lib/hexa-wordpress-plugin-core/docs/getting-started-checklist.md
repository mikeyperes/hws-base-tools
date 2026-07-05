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

The host plugin owns only the process definition and callback functions. The UI, AJAX contract, sequential execution, status rendering, request type display, and log rendering belong in Hexa WP Core.

## Step Types

Every step and subtask may declare a `type`. The type is shown in the UI and is passed to callbacks as `request_type`.

Allowed types:

- `callback`: generic PHP callback.
- `status_check`: reads/report status without changing configuration.
- `setup_action`: runs a setup or repair command.
- `feature_toggle`: enables or disables a plugin feature.
- `config_mutation`: writes configuration such as options, constants, or settings.
- `ajax_request`: represents a host-owned AJAX request step. Register the actual callback in the host plugin.
- `custom`: anything plugin-specific that does not fit the other types.

Each step may also define:

- `action_label`: button label for that item. If omitted, Core chooses one from the type.
- `request`: structured request metadata. Core passes the raw request array to the callback and redacts secret/token/password/nonce/key values in public output.

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
            'type'        => 'status_check',
            'description' => 'Checks WordPress and PHP values.',
            'subtasks'    => [
                [
                    'id'       => 'wordpress',
                    'label'    => 'WordPress Runtime',
                    'type'     => 'status_check',
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
    'request'    => [...],
    'request_type' => 'status_check',
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
