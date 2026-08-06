<?php

namespace hws_base_tools;

use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistAjaxController;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistConfig;
use HWS\BaseTools\QuickStart\QuickStartModule;
use HWS\BaseTools\QuickStart\QuickStartProfileRegistry;
use HWS\BaseTools\QuickStart\QuickStartTaskRunner;

defined( 'ABSPATH' ) || exit;

/**
 * Compatibility facade for the pre-v13 procedural Quick Start API.
 *
 * New code uses HWS\BaseTools\QuickStart. These small adapters intentionally
 * contain no setup policy, renderer, updater, cleanup, or AJAX implementation.
 */

function hws_getting_started_checklist_config(): GettingStartedChecklistConfig {
    return QuickStartModule::config();
}

function hws_getting_started_checklist_templates(): array {
    return QuickStartProfileRegistry::profiles();
}

function hws_register_getting_started_checklist_ajax(): void {
    static $registered = false;
    if ( $registered ) {
        return;
    }

    $compatibility = new GettingStartedChecklistConfig( [
        'root_id'              => 'hws-getting-started-checklist-legacy',
        'title'                => 'Quick Start',
        'capability'           => 'manage_options',
        'nonce_action'         => 'hws_base_tools_getting_started_checklist',
        'nonce_field'          => 'nonce',
        'run_action'           => 'hws_getting_started_checklist_run_item',
        'status_action'        => 'hws_getting_started_checklist_status',
        'reset_action'         => 'hws_getting_started_checklist_reset',
        'state_option'         => 'hws_quick_start_state',
        'persistence_enabled'  => true,
        'template_id'          => QuickStartProfileRegistry::DEFAULT_PROFILE,
        'show_template_picker' => true,
        'templates'            => QuickStartProfileRegistry::profiles(),
    ] );
    ( new GettingStartedChecklistAjaxController( $compatibility ) )->register();
    $registered = true;
}

function display_settings_getting_started_checklist(): void {
    QuickStartModule::render();
}

/** @param array<string,mixed> $payload
 *  @return array<string,mixed>
 */
function hws_getting_started_run_quick_setup_task( array $payload ): array {
    $context = is_array( $payload['context'] ?? null ) ? $payload['context'] : [];
    if ( isset( $context['quick_setup_task'] ) && ! isset( $context['quick_start_task'] ) ) {
        $context['quick_start_task'] = $context['quick_setup_task'];
    }
    $payload['context'] = $context;
    return QuickStartTaskRunner::run( $payload );
}

hws_register_getting_started_checklist_ajax();
