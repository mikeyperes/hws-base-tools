# Task Runner

Namespace: `Hexa\PluginCore\WpAdminComponents\TaskRunner`

Shared pieces for an admin screen that runs a long job as a series of AJAX
steps, so the page reports every step live and never reloads.

## PHP

- `TaskRunner::stats( $cards )`: stat cards. Each card has `key`, `label`,
  optional `value` and `hint`; the client updates `[data-hpc-stat="key"]`.
- `TaskRunner::progress()`: progress meter, hidden until the first step.
- `TaskRunner::log( [ 'title' => 'Live Log', 'open' => true ] )`: live log
  console with a Clear button.
- `TaskRunner::state( $status, $label )`: status badge (`pending`, `running`,
  `done`, `error`, `skipped`).
- Tables use `.hpc-runner-table-wrap` and `.hpc-runner-table` (`td.num` for
  numbers, `tr.hpc-runner-group` for group rows).

## JavaScript

`window.HexaWpCoreTaskRunner.create( root )` reads `data-ajax-url`,
`data-nonce` and `data-nonce-field` from the root and returns a runner:

- `post( action, payload )`: guarded admin-ajax POST; resolves `data`, rejects
  with the server message.
- `queue( items, worker, { onProgress, onError } )`: runs `worker( item )` one
  item at a time; a failed item is logged and the queue continues.
- `loop( action, payload, { onStep, maxSteps } )`: repeats a batched action
  until the server returns `done: true`.
- `begin()`, `cancel()`, `cancelled()`, `end()`, `busy`: stop between steps.
- `progress( done, total, title, text )`, `log( level, message )`,
  `setRow( tr, status, label )`, plus `esc`, `bytes`, `number`, `state`.

Use it with `DynamicButton` for button states and `DynamicNotice` for the
final message. Host plugins keep their own AJAX actions and business rules.
