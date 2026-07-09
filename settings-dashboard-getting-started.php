<?php

namespace hws_base_tools;

use Hexa\PluginCore\ContentCleanup\ArticleMediaCleanupScanner;
use Hexa\PluginCore\GettingStartedChecklist\ChecklistReportBuilder;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistAjaxController;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistConfig;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistRenderer;
use Hexa\PluginCore\PluginChecks\PluginCheckDefinition;
use Hexa\PluginCore\PluginChecks\PluginCheckService;
use Hexa\PluginCore\PluginProvisioning\PluginProvisioner;
use Hexa\PluginCore\WpAdminUiCleanup\CleanupChecklistAdapter;

defined( 'ABSPATH' ) || exit;

const HWS_GETTING_STARTED_CHECKLIST_NONCE_ACTION = 'hws_base_tools_getting_started_checklist';
const HWS_GETTING_STARTED_NEWS_OUTLET_DELETE_CONFIRMATION = 'DELETE OLD POSTS';

function hws_getting_started_checklist_config(): GettingStartedChecklistConfig {
    return new GettingStartedChecklistConfig(
        [
            'root_id'       => 'hws-getting-started-checklist',
            'title'         => 'Quick Start',
            'description'   => 'Runs HWS Base Tools startup checks through the reusable Hexa WP Core checklist structure. HWS registers the steps; Hexa WP Core owns the UI, AJAX runner, status states, and activity log.',
            'capability'    => 'manage_options',
            'nonce_action'  => HWS_GETTING_STARTED_CHECKLIST_NONCE_ACTION,
            'nonce_field'   => 'nonce',
            'run_action'    => 'hws_getting_started_checklist_run_item',
            'empty_message'        => 'No HWS getting started checks are registered.',
            'template_id'          => 'default',
            'template_label'       => 'Quick Start Template',
            'template_load_label'  => 'Load Template',
            'show_template_picker' => true,
            'templates'            => hws_getting_started_checklist_templates(),
        ]
    );
}

function hws_register_getting_started_checklist_ajax(): void {
    static $registered = false;

    if ( $registered ) {
        return;
    }

    ( new GettingStartedChecklistAjaxController( hws_getting_started_checklist_config() ) )->register();

    $registered = true;
}
add_action( 'init', __NAMESPACE__ . '\\hws_register_getting_started_checklist_ajax', 20 );

add_filter(
    'hws_base_tools_dashboard_tabs',
    function( array $tabs ): array {
        if ( isset( $tabs['quick-start'] ) ) {
            return $tabs;
        }

        $updated  = [];
        $inserted = false;

        foreach ( $tabs as $key => $label ) {
            $updated[ $key ] = $label;
            if ( 'overview' === $key ) {
                $updated['quick-start'] = 'Quick Start';
                $inserted = true;
            }
        }

        if ( ! $inserted ) {
            $updated['quick-start'] = 'Quick Start';
        }

        return $updated;
    }
);

add_filter(
    'hws_base_tools_render_dashboard_tab',
    function( bool $handled, string $tab_id ): bool {
        if ( $handled || 'quick-start' !== $tab_id ) {
            return $handled;
        }

        display_settings_getting_started_checklist();

        return true;
    },
    10,
    2
);

function display_settings_getting_started_checklist(): void {
    hws_register_getting_started_checklist_ajax();

    ( new GettingStartedChecklistRenderer( hws_getting_started_checklist_config() ) )->render();
}

/**
 * @return array<string,array<string,mixed>>
 */
function hws_getting_started_checklist_templates(): array {
    $default_steps = hws_getting_started_default_template_steps();

    return [
        'default'         => [
            'label'       => 'Default',
            'description' => 'The standard HWS Base Tools launch checklist for normal site setup.',
            'steps'       => $default_steps,
        ],
        'diamond_website' => [
            'label'       => 'Diamond Website',
            'description' => 'A named preset template that currently starts from the standard HWS launch checklist and can be expanded with Diamond-specific steps.',
            'steps'       => $default_steps,
        ],
        'news_outlets_initial_setup' => [
            'label'       => 'News Outlets Initial Setup',
            'description' => 'A news publication startup profile with SMP plugin readiness and guarded article cleanup actions.',
            'steps'       => hws_getting_started_news_outlet_template_steps( $default_steps ),
        ],
    ];
}

/**
 * @return array<int,array<string,mixed>>
 */
function hws_getting_started_default_template_steps(): array {
    return [
        [
            'id'          => 'quick_setup',
            'label'       => 'Run Quick Setup',
            'type'        => 'setup_action',
            'description' => 'Runs HWS Quick Setup as isolated Hexa Core tasks. Each action reports what existed before, what it changed, and what was verified afterward.',
            'subtasks'    => hws_getting_started_quick_setup_subtasks(),
        ],
        [
            'id'           => 'ui_cleanup',
            'label'        => 'UI',
            'type'         => 'status_check',
            'action_label' => 'Check UI',
            'description'  => 'Lists every registered UI Cleanup option from the UI Cleanup tab as a checklist subtask. The attributes are generated from the source option definitions, not hand-coded into the checklist.',
            'subtasks'     => hws_getting_started_ui_cleanup_subtasks(),
        ],
    ];
}

/**
 * @param array<int,array<string,mixed>> $default_steps
 * @return array<int,array<string,mixed>>
 */
function hws_getting_started_news_outlet_template_steps( array $default_steps ): array {
    $steps   = $default_steps;
    $steps[] = [
        'id'          => 'news_outlet_initial_setup',
        'label'       => 'Getting Started for News Outlets',
        'type'        => 'setup_action',
        'description' => 'Runs SMP-specific startup actions for news publications. The destructive cleanup action is restricted to WordPress posts and preserves the newest 10.',
        'subtasks'    => hws_getting_started_news_outlet_setup_subtasks(),
    ];

    return $steps;
}

/**
 * @return array<int,array<string,mixed>>
 */
function hws_getting_started_ui_cleanup_subtasks(): array {
    if ( ! function_exists( __NAMESPACE__ . '\\get_ui_cleanup_options' ) || ! class_exists( CleanupChecklistAdapter::class ) ) {
        return [];
    }

    return CleanupChecklistAdapter::subtasks_from_options(
        get_ui_cleanup_options(),
        __NAMESPACE__ . '\\hws_getting_started_check_ui_cleanup_attribute',
        [
            'type'                => 'status_check',
            'action_label'        => 'Check',
            'include_attributes'  => true,
            'default_admin_pages' => [ 'profile.php', 'user-edit.php', 'post.php', 'post-new.php' ],
        ]
    );
}

/**
 * @param array<string,mixed> $payload
 * @return array<string,mixed>
 */
function hws_getting_started_check_ui_cleanup_attribute( array $payload ): array {
    $context = is_array( $payload['context'] ?? null ) ? $payload['context'] : [];
    $key     = sanitize_key( (string) ( $context['ui_cleanup_key'] ?? '' ) );

    if ( '' === $key || ! function_exists( __NAMESPACE__ . '\\get_ui_cleanup_options' ) || ! function_exists( __NAMESPACE__ . '\\get_ui_cleanup_option' ) ) {
        return hws_getting_started_quick_setup_result( false, 'UI Cleanup option source is not available.', 'error' );
    }

    $options = get_ui_cleanup_options();
    if ( ! isset( $options[ $key ] ) ) {
        return hws_getting_started_quick_setup_result( false, 'UI Cleanup option is not registered: ' . $key, 'error', [ 'ui_cleanup_key' => $key ] );
    }

    $enabled    = get_ui_cleanup_option( $key );
    $attributes = is_array( $context['ui_cleanup_attribute'] ?? null ) ? $context['ui_cleanup_attribute'] : CleanupChecklistAdapter::attributes( $options[ $key ], $key );
    $mode       = (string) ( $attributes['mode'] ?? '' );
    $state      = $enabled ? (string) ( $attributes['on_label'] ?? ( 'postbox_collapse' === $mode ? 'Collapsed' : 'Hidden' ) ) : (string) ( $attributes['off_label'] ?? ( 'postbox_collapse' === $mode ? 'Expanded' : 'Visible' ) );
    $label      = (string) ( $attributes['label'] ?? $key );

    return hws_getting_started_quick_setup_result(
        true,
        $label . ' is currently ' . $state . '.',
        'success',
        [
            'ui_cleanup_key'   => $key,
            'current_state'    => $state,
            'source_attribute' => $attributes,
        ],
        [ CleanupChecklistAdapter::status_report_from_payload( [ 'context' => [ 'ui_cleanup_attribute' => $attributes ] ], $enabled ) ]
    );
}

/**
 * @return array<int,array<string,mixed>>
 */
function hws_getting_started_quick_setup_subtasks(): array {
    $callback = __NAMESPACE__ . '\\hws_getting_started_run_quick_setup_task';

    return [
        hws_getting_started_quick_setup_task_definition( 'regenerate_favicon_ico', 'Generate PNG + ICO', 'setup_action', 'Uses the same letter favicon generator as Brand Assets, purges the existing physical /favicon.ico file, creates a fresh PNG Site Icon, and regenerates the ICO.', $callback ),
        hws_getting_started_quick_setup_task_definition( 'disable_debug_settings', 'Disable Debug Settings', 'config_mutation', 'Sets WP_DEBUG, WP_DEBUG_DISPLAY, and WP_DEBUG_LOG to false through the existing wp-config writer.', $callback ),
        hws_getting_started_quick_setup_task_definition( 'set_memory_limit', 'Set WP Memory Limit', 'config_mutation', 'Sets WP_MEMORY_LIMIT to 4096M through the existing wp-config writer.', $callback ),
        hws_getting_started_quick_setup_task_definition( 'enable_core_auto_updates', 'Enable WordPress Core Auto Updates', 'config_mutation', 'Enables WP_AUTO_UPDATE_CORE through the existing wp-config writer.', $callback ),
        hws_getting_started_quick_setup_task_definition( 'enable_plugin_auto_updates', 'Enable Plugin Auto Updates', 'config_mutation', 'Enables auto-updates for installed plugins through WordPress options.', $callback ),
        hws_getting_started_quick_setup_task_definition( 'enable_theme_auto_updates', 'Enable Theme Auto Updates', 'config_mutation', 'Enables auto-updates for installed themes through WordPress options.', $callback ),
        hws_getting_started_quick_setup_task_definition( 'clean_log_files', 'Clean Log Files', 'setup_action', 'Deletes writable debug.log and root error_log files.', $callback ),
        hws_getting_started_quick_setup_task_definition( 'clean_backup_files', 'Clean Backup Files', 'setup_action', 'Uses the existing HWS backup scanner and removes writable backup files it reports.', $callback ),
        hws_getting_started_quick_setup_task_definition( 'run_database_cleanup', 'Run Database Cleanup', 'setup_action', 'Runs WP-Optimize cleanup tasks and table optimization, then disables WP-Optimize when finished.', $callback ),
        hws_getting_started_quick_setup_task_definition( 'close_comments', 'Close Comments', 'config_mutation', 'Closes future comments and existing open post comments.', $callback ),
        hws_getting_started_quick_setup_task_definition( 'delete_comments', 'Delete Comments', 'setup_action', 'Deletes existing comments and comment meta.', $callback ),
        hws_getting_started_quick_setup_task_definition( 'close_pingbacks', 'Close Pingbacks', 'config_mutation', 'Closes future pingbacks and existing open post pingbacks.', $callback ),
        hws_getting_started_quick_setup_task_definition( 'check_redis_object_cache', 'Enable Redis Object Cache', 'setup_action', 'Uses LiteSpeed settings and object-cache checks to enable Redis and verify both enabled and actively running states.', $callback ),
        hws_getting_started_quick_setup_task_definition( 'activate_litespeed_cache', 'Activate LiteSpeed Cache', 'setup_action', 'Activates LiteSpeed Cache when installed.', $callback ),
        hws_getting_started_quick_setup_task_definition( 'activate_wordfence', 'Activate Wordfence', 'setup_action', 'Activates Wordfence when installed.', $callback ),
        hws_getting_started_quick_setup_task_definition( 'enable_recommended_snippets', 'Enable Recommended Snippets', 'feature_toggle', 'Uses the existing Going Live Checklist snippet list and enables each recommended snippet option.', $callback ),
        hws_getting_started_quick_setup_task_definition( 'install_essential_plugins', 'Install Essential Plugins', 'setup_action', 'Uses the existing monitored plugin list and installs or activates essential plugins when possible.', $callback ),
        hws_getting_started_quick_setup_task_definition(
            'apply_wordfence_alert_email',
            'Set Wordfence Alert Email',
            'config_mutation',
            'Feeds this task-level typed email into the existing Wordfence alertEmails updater.',
            $callback,
            [
                [
                    'id'          => 'wordfence_alert_email',
                    'label'       => 'Wordfence alert email',
                    'type'        => 'email',
                    'required'    => true,
                    'placeholder' => '',
                    'description' => 'This value is sent only to the Wordfence alert email task.',
                ],
            ]
        ),
        hws_getting_started_quick_setup_task_definition(
            'apply_smtp_from_email',
            'Set WP Mail SMTP From Email',
            'config_mutation',
            'Feeds this task-level typed email into the existing WP Mail SMTP option structure.',
            $callback,
            [
                [
                    'id'          => 'smtp_from_email',
                    'label'       => 'SMTP from email',
                    'type'        => 'email',
                    'required'    => true,
                    'placeholder' => '',
                    'description' => 'This value is sent only to the WP Mail SMTP from email task. Authentication still requires the selected mailer credentials.',
                ],
            ]
        ),
    ];
}

/**
 * @return array<int,array<string,mixed>>
 */
function hws_getting_started_news_outlet_setup_subtasks(): array {
    $callback = __NAMESPACE__ . '\\hws_getting_started_run_quick_setup_task';

    return [
        hws_getting_started_quick_setup_task_definition(
            'delete_old_posts_keep_latest_10',
            'Delete Old Posts, Keep Latest 10',
            'setup_action',
            'Uses the existing Article & Media Cleanup scanner to permanently delete only WordPress posts older than the most recent 10 matching posts, including associated featured, inline, and gallery media.',
            $callback,
            [
                ChecklistReportBuilder::confirmation_input(
                    'delete_old_posts_confirmation',
                    HWS_GETTING_STARTED_NEWS_OUTLET_DELETE_CONFIRMATION,
                    'Delete old posts confirmation',
                    [
                        'description' => 'Type exactly: ' . HWS_GETTING_STARTED_NEWS_OUTLET_DELETE_CONFIRMATION . '. This deletes only post type post, preserves the newest 10 posts, and deletes associated media detected by the existing Article & Media Cleanup scanner.',
                    ]
                ),
            ]
        ),
        hws_getting_started_quick_setup_task_definition(
            'ensure_smp_hexa_plugins',
            'Install and Activate News Outlet Plugins',
            'setup_action',
            'Installs and activates: Classic Editor, Elementor, LiteSpeed Cache, Rank Math SEO, Site Kit by Google, Wordfence Security, WP-Optimize, WP-Sweep, WP Mail SMTP, HWS Base Tools, SMP Publication Integration, and Verified Profiles. Pro/manual plugins are intentionally excluded.',
            $callback
        ),
    ];
}

/**
 * @param array<int,array<string,mixed>> $required_inputs
 * @return array<string,mixed>
 */
function hws_getting_started_quick_setup_task_definition( string $id, string $label, string $type, string $description, string $callback, array $required_inputs = [] ): array {
    $definition = [
        'id'          => $id,
        'label'       => $label,
        'type'        => $type,
        'description' => $description,
        'callback'    => $callback,
        'context'     => [
            'quick_setup_task' => $id,
        ],
    ];

    if ( [] !== $required_inputs ) {
        $definition['required_inputs'] = $required_inputs;
    }

    return $definition;
}

/**
 * @return array<int,string>
 */
function hws_getting_started_required_launch_setting_labels(): array {
    return [
        'WP Memory Limit > 512MB',
        'Comments Disabled',
        'Pingbacks Disabled',
        'Email / SMTP Authenticated',
        'WP_DEBUG Off',
        'WP_DEBUG_DISPLAY Off',
        'WP_DEBUG_LOG Off',
        'Wordfence Alert Email Set',
    ];
}

/**
 * @return array<int,array<string,mixed>>
 */
function hws_getting_started_required_launch_setting_subtasks(): array {
    $subtasks = [];

    foreach ( hws_getting_started_required_launch_setting_labels() as $label ) {
        $subtasks[] = [
            'id'          => sanitize_key( str_replace( [ ' / ', ' > ', ' ', '/' ], '_', strtolower( $label ) ) ),
            'label'       => $label,
            'type'        => 'status_check',
            'description' => 'Uses the existing HWS Going Live Checklist setting check source.',
            'callback'    => __NAMESPACE__ . '\\hws_getting_started_check_required_launch_setting',
            'context'     => [
                'glc_label'       => $label,
                'source_function' => __NAMESPACE__ . '\\hws_get_glc_settings_checks',
            ],
        ];
    }

    return $subtasks;
}

/**
 * @param array<string,mixed> $payload
 * @return array<string,mixed>
 */
function hws_getting_started_check_required_launch_setting( array $payload ): array {
    $context = is_array( $payload['context'] ?? null ) ? $payload['context'] : [];
    $label   = (string) ( $context['glc_label'] ?? '' );

    if ( '' === $label || ! function_exists( __NAMESPACE__ . '\\hws_get_glc_settings_checks' ) ) {
        return [
            'success' => false,
            'message' => 'Required launch setting check is not available.',
            'logs'    => [
                [
                    'level'   => 'error',
                    'message' => 'The checklist could not find the required Going Live Checklist source.',
                    'context' => [
                        'label'           => $label,
                        'source_function' => __NAMESPACE__ . '\\hws_get_glc_settings_checks',
                    ],
                ],
            ],
        ];
    }

    foreach ( hws_get_glc_settings_checks() as $check ) {
        if ( (string) ( $check['label'] ?? '' ) !== $label ) {
            continue;
        }

        $passed = (bool) ( $check['pass'] ?? false );
        $value  = (string) ( $check['value'] ?? '' );

        return [
            'success' => $passed,
            'message' => $passed ? $label . ' passed.' : $label . ' needs attention.',
            'logs'    => [
                [
                    'level'   => $passed ? 'success' : 'error',
                    'message' => $label . ': ' . ( $passed ? 'PASS' : 'FAIL' ),
                    'context' => [
                        'value'           => $value,
                        'source_function' => __NAMESPACE__ . '\\hws_get_glc_settings_checks',
                    ],
                ],
            ],
            'data'    => [
                'label' => $label,
                'pass'  => $passed,
                'value' => $value,
            ],
        ];
    }

    return [
        'success' => false,
        'message' => $label . ' is missing from the Going Live Checklist source.',
        'logs'    => [
            [
                'level'   => 'error',
                'message' => 'Required launch setting label was not found in hws_get_glc_settings_checks().',
                'context' => [
                    'label' => $label,
                ],
            ],
        ],
    ];
}

/**
 * @param array<string,mixed> $constants
 * @return array{result:array<string,mixed>,report:array<string,mixed>}
 */
function hws_getting_started_apply_wp_config_constants( array $constants ): array {
    $changes = [];
    $file    = defined( 'ABSPATH' ) ? ABSPATH . 'wp-config.php' : 'wp-config.php';

    foreach ( $constants as $constant => $value ) {
        $constant = (string) $constant;
        $changes[ $constant ] = [
            'setting'   => $constant,
            'old_value' => hws_getting_started_read_wp_config_constant( $constant ),
            'new_value' => hws_getting_started_format_wp_config_value( $value ),
            'file'      => $file,
        ];
    }

    $result = function_exists( __NAMESPACE__ . '\\modify_wp_config_constants' )
        ? modify_wp_config_constants( $constants )
        : [ 'status' => false, 'message' => 'wp-config writer is not available.' ];

    foreach ( array_keys( $changes ) as $constant ) {
        $changes[ $constant ]['actual_value'] = hws_getting_started_read_wp_config_constant( $constant );
    }

    return [
        'result'  => $result,
        'changes' => array_values( $changes ),
        'report'  => ChecklistReportBuilder::wp_config_changes( array_values( $changes ) ),
    ];
}

/**
 * @param array{changes?:array<int,array<string,mixed>>} $config_change
 */
function hws_getting_started_wp_config_changes_verified( array $config_change ): bool {
    foreach ( (array) ( $config_change['changes'] ?? [] ) as $change ) {
        if ( ! is_array( $change ) ) {
            continue;
        }

        $target = strtolower( trim( (string) ( $change['new_value'] ?? '' ) ) );
        $actual = strtolower( trim( (string) ( $change['actual_value'] ?? '' ) ) );

        if ( $target !== $actual ) {
            return false;
        }
    }

    return true;
}

function hws_getting_started_wp_config_actual_value( array $config_change, string $setting ): string {
    foreach ( (array) ( $config_change['changes'] ?? [] ) as $change ) {
        if ( is_array( $change ) && $setting === (string) ( $change['setting'] ?? '' ) ) {
            return (string) ( $change['actual_value'] ?? '' );
        }
    }

    return '';
}

function hws_getting_started_memory_limit_to_mb( string $value ): int {
    $value = trim( $value );
    if ( '' === $value || 'undefined' === strtolower( $value ) ) {
        return 0;
    }

    if ( function_exists( 'wp_convert_hr_to_bytes' ) ) {
        $bytes = wp_convert_hr_to_bytes( $value );
        return $bytes > 0 ? (int) floor( $bytes / 1048576 ) : 0;
    }

    if ( ! preg_match( '/^([0-9]+)\s*([kmgt]?)b?$/i', $value, $matches ) ) {
        return 0;
    }

    $number = (int) $matches[1];
    $unit   = strtolower( $matches[2] ?? 'm' );

    if ( 't' === $unit ) {
        return $number * 1048576;
    }
    if ( 'g' === $unit ) {
        return $number * 1024;
    }
    if ( 'k' === $unit ) {
        return (int) floor( $number / 1024 );
    }

    return $number;
}

function hws_getting_started_read_wp_config_constant( string $constant ): string {
    $file_value = hws_getting_started_read_wp_config_constant_from_file( $constant );
    if ( '' !== $file_value ) {
        return $file_value;
    }

    if ( function_exists( __NAMESPACE__ . '\\check_wp_config_constant_status' ) ) {
        $value = check_wp_config_constant_status( $constant );
        return is_scalar( $value ) || null === $value ? (string) $value : wp_json_encode( $value );
    }

    if ( defined( $constant ) ) {
        $value = constant( $constant );
        if ( is_bool( $value ) ) {
            return $value ? 'true' : 'false';
        }
        return is_scalar( $value ) || null === $value ? (string) $value : wp_json_encode( $value );
    }

    return 'undefined';
}

function hws_getting_started_read_wp_config_constant_from_file( string $constant ): string {
    $path = defined( 'ABSPATH' ) ? ABSPATH . 'wp-config.php' : '';
    if ( '' === $path || ! file_exists( $path ) || ! is_readable( $path ) ) {
        return '';
    }

    $content = file_get_contents( $path );
    if ( ! is_string( $content ) || '' === $content ) {
        return '';
    }

    $pattern = "/define\s*\(\s*['\"]" . preg_quote( strtoupper( $constant ), '/' ) . "['\"]\s*,\s*(.*?)\s*\)\s*;/is";
    if ( ! preg_match( $pattern, $content, $matches ) ) {
        return '';
    }

    $raw = trim( (string) ( $matches[1] ?? '' ) );
    if ( '' === $raw ) {
        return '';
    }

    if ( preg_match( "/^(['\"])(.*)\\1$/s", $raw, $value_match ) ) {
        return stripcslashes( (string) $value_match[2] );
    }

    $lower = strtolower( $raw );
    if ( in_array( $lower, [ 'true', 'false', 'null' ], true ) ) {
        return $lower;
    }

    return trim( $raw, " \t\n\r\0\x0B," );
}

function hws_getting_started_format_wp_config_value( mixed $value ): string {
    if ( is_array( $value ) ) {
        return wp_json_encode( $value );
    }
    if ( is_bool( $value ) ) {
        return $value ? 'true' : 'false';
    }
    return is_scalar( $value ) || null === $value ? (string) $value : wp_json_encode( $value );
}

/**
 * @param array<int,array{label:string,value:string}> $summary_items
 * @return array<string,mixed>
 */
function hws_getting_started_report_meta( string $documentation, array $summary_items = [] ): array {
    return [
        'documentation' => $documentation,
        'summary_items' => $summary_items,
    ];
}

function hws_getting_started_bool_label( mixed $value ): string {
    if ( is_bool( $value ) ) {
        return $value ? 'Enabled' : 'Disabled';
    }

    $normalized = strtolower( trim( (string) $value ) );
    if ( in_array( $normalized, [ '1', 'true', 'yes', 'on', 'enabled' ], true ) ) {
        return 'Enabled';
    }
    if ( in_array( $normalized, [ '0', 'false', 'no', 'off', 'disabled', '' ], true ) ) {
        return 'Disabled';
    }

    return (string) $value;
}

/**
 * @return array{exists:bool,writable:bool,size_bytes:int,size:string,before:string}
 */
function hws_getting_started_file_state( string $path ): array {
    $exists     = file_exists( $path );
    $writable   = $exists && is_writable( $path );
    $size_bytes = $exists ? (int) filesize( $path ) : 0;
    $size       = $size_bytes > 0 ? size_format( $size_bytes ) : '0 B';

    if ( ! $exists ) {
        $before = 'Not found before cleanup.';
    } else {
        $before = 'Found before cleanup; size was ' . $size . '; ' . ( $writable ? 'writable.' : 'not writable.' );
    }

    return [
        'exists'     => $exists,
        'writable'   => $writable,
        'size_bytes' => $size_bytes,
        'size'       => $size,
        'before'     => $before,
	    ];
}

function hws_getting_started_read_wp_mail_smtp_from_email(): string {
    $smtp_options = get_option( 'wp_mail_smtp', [] );
    if ( ! is_array( $smtp_options ) ) {
        return '';
    }

    $mail = is_array( $smtp_options['mail'] ?? null ) ? $smtp_options['mail'] : [];
    return sanitize_email( (string) ( $mail['from_email'] ?? '' ) );
}

function hws_getting_started_read_wordfence_alert_email(): string {
    global $wpdb;

    if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
        return '';
    }

    $wf_table = $wpdb->prefix . 'wfconfig';
    $exists   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wf_table ) );
    if ( $exists !== $wf_table ) {
        return '';
    }

    $raw = $wpdb->get_var( $wpdb->prepare( "SELECT `val` FROM `{$wf_table}` WHERE `name` = %s", 'alertEmails' ) );
    if ( null === $raw ) {
        return '';
    }

    $value = maybe_unserialize( $raw );
    if ( is_array( $value ) ) {
        return implode( ', ', array_filter( array_map( 'sanitize_email', array_map( 'strval', $value ) ) ) );
    }

    return sanitize_text_field( (string) $value );
}

/**
 * @return array<int,string>
 */
function hws_getting_started_email_list_from_value( string $value ): array {
    $emails = preg_split( '/[\s,;]+/', $value );
    if ( ! is_array( $emails ) ) {
        return [];
    }

    return array_values( array_filter( array_map( 'sanitize_email', $emails ) ) );
}

/**
 * @param array<int,array<string,mixed>> $rows
 * @param array<int,array{label:string,value:string}> $summary_items
 * @return array<string,mixed>
 */
function hws_getting_started_before_after_report( string $type, string $title, array $rows, string $summary, string $documentation, array $summary_items = [] ): array {
    return ChecklistReportBuilder::before_after(
        $title,
        $rows,
        [
            'type'    => $type,
            'summary' => $summary,
            'meta'    => hws_getting_started_report_meta( $documentation, $summary_items ),
        ]
    );
}

/**
 * @param array<string,mixed> $payload
 * @return array<string,mixed>
 */
function hws_getting_started_run_quick_setup_task( array $payload ): array {
    $context = is_array( $payload['context'] ?? null ) ? $payload['context'] : [];
    $task    = sanitize_key( (string) ( $context['quick_setup_task'] ?? '' ) );
    $inputs  = is_array( $payload['inputs'] ?? null ) ? $payload['inputs'] : [];

    switch ( $task ) {
        case 'regenerate_favicon_ico':
            return hws_getting_started_regenerate_favicon_ico_task();

        case 'disable_debug_settings':
            $config_change = hws_getting_started_apply_wp_config_constants(
                [
                    'WP_DEBUG'         => 'false',
                    'WP_DEBUG_DISPLAY' => 'false',
                    'WP_DEBUG_LOG'     => 'false',
                ]
            );
            @ini_set( 'display_errors', '0' );
            $verified = ! empty( $config_change['result']['status'] ) && hws_getting_started_wp_config_changes_verified( $config_change );
            return hws_getting_started_quick_setup_result( $verified, $verified ? 'Debug settings verified disabled.' : 'Debug setting write did not verify. Check the Verified Value column.', $verified ? 'success' : 'error', [ 'constants' => [ 'WP_DEBUG', 'WP_DEBUG_DISPLAY', 'WP_DEBUG_LOG' ], 'wp_config_message' => (string) ( $config_change['result']['message'] ?? '' ) ], [ $config_change['report'] ] );

        case 'set_memory_limit':
            $config_change = hws_getting_started_apply_wp_config_constants( [ 'WP_MEMORY_LIMIT' => '4096M' ] );
            $actual_value  = hws_getting_started_wp_config_actual_value( $config_change, 'WP_MEMORY_LIMIT' );
            $actual_mb     = hws_getting_started_memory_limit_to_mb( $actual_value );
            $exact_match   = '4096m' === strtolower( trim( $actual_value ) );
            $acceptable    = $actual_mb >= 512;
            $memory_status = $exact_match || $acceptable;
            $message       = $exact_match
                ? 'WP_MEMORY_LIMIT verified at 4096M.'
                : ( $acceptable ? 'WP_MEMORY_LIMIT verified acceptable at ' . $actual_value . '. Target was 4096M.' : 'WP_MEMORY_LIMIT did not verify above 511M. Verified value: ' . ( '' !== $actual_value ? $actual_value : 'unknown' ) . '.' );

            return hws_getting_started_quick_setup_result( $memory_status, $message, $memory_status ? 'success' : 'error', [ 'constant' => 'WP_MEMORY_LIMIT', 'target_value' => '4096M', 'verified_value' => $actual_value, 'verified_mb' => $actual_mb, 'wp_config_message' => (string) ( $config_change['result']['message'] ?? '' ) ], [ $config_change['report'] ] );

        case 'enable_core_auto_updates':
            $config_change = hws_getting_started_apply_wp_config_constants( [ 'WP_AUTO_UPDATE_CORE' => 'true' ] );
            $verified = ! empty( $config_change['result']['status'] ) && hws_getting_started_wp_config_changes_verified( $config_change );
            return hws_getting_started_quick_setup_result( $verified, $verified ? 'WordPress core auto-updates verified enabled.' : 'WordPress core auto-update write did not verify. Check the Verified Value column.', $verified ? 'success' : 'error', [ 'constant' => 'WP_AUTO_UPDATE_CORE', 'value' => 'true', 'wp_config_message' => (string) ( $config_change['result']['message'] ?? '' ) ], [ $config_change['report'] ] );

        case 'enable_plugin_auto_updates':
            if ( ! function_exists( 'get_plugins' ) && defined( 'ABSPATH' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $all_plugins = function_exists( 'get_plugins' ) ? array_keys( get_plugins() ) : [];
            $before_plugins = (array) get_option( 'auto_update_plugins', [] );
            $before_enabled = (bool) get_option( 'enable_auto_update_plugins', false );
            update_option( 'auto_update_plugins', $all_plugins );
            update_option( 'enable_auto_update_plugins', true );
            $after_plugins = (array) get_option( 'auto_update_plugins', [] );
            $after_enabled = (bool) get_option( 'enable_auto_update_plugins', false );
            $plugin_count  = count( $all_plugins );
            return hws_getting_started_quick_setup_result(
                true,
                'Plugin auto-updates enabled.',
                'success',
                [
                    'installed_plugins' => $plugin_count,
                    'before_count'      => count( $before_plugins ),
                    'after_count'       => count( $after_plugins ),
                ],
                [
                    hws_getting_started_before_after_report(
                        'plugin_auto_update_options',
                        'Plugin Auto-Update Changes',
                        [
                            [
                                'item'    => 'Plugin auto-update list',
                                'before'  => count( $before_plugins ) . ' plugin' . ( 1 === count( $before_plugins ) ? '' : 's' ) . ' selected before action.',
                                'action'  => 'Saved all installed plugin files into the WordPress auto-update option.',
                                'after'   => count( $after_plugins ) . ' of ' . $plugin_count . ' installed plugin' . ( 1 === $plugin_count ? '' : 's' ) . ' selected after action.',
                                'meaning' => 'Installed plugins are now included in WordPress plugin auto-updates.',
                            ],
                            [
                                'item'    => 'Auto-update setting flag',
                                'before'  => hws_getting_started_bool_label( $before_enabled ),
                                'action'  => 'Set enable_auto_update_plugins to true.',
                                'after'   => hws_getting_started_bool_label( $after_enabled ),
                                'meaning' => 'The HWS option flag now records plugin auto-updates as enabled.',
                            ],
                        ],
                        'Plugin auto-update settings were read, updated, and read again after saving.',
                        'This report uses WordPress options. It shows how many plugins were selected for auto-updates before the action, what was saved, and the verified option state afterward.',
                        [
                            [ 'label' => 'Before', 'value' => count( $before_plugins ) . ' plugin auto-update entries existed.' ],
                            [ 'label' => 'After', 'value' => count( $after_plugins ) . ' plugin auto-update entries are saved.' ],
                        ]
                    ),
                ]
            );

        case 'enable_theme_auto_updates':
            $all_themes = array_keys( wp_get_themes() );
            $before_themes = (array) get_option( 'auto_update_themes', [] );
            $before_enabled = (bool) get_option( 'enable_auto_update_themes', false );
            update_option( 'auto_update_themes', $all_themes );
            update_option( 'enable_auto_update_themes', true );
            $after_themes = (array) get_option( 'auto_update_themes', [] );
            $after_enabled = (bool) get_option( 'enable_auto_update_themes', false );
            $theme_count = count( $all_themes );
            return hws_getting_started_quick_setup_result(
                true,
                'Theme auto-updates enabled.',
                'success',
                [
                    'installed_themes' => $theme_count,
                    'before_count'     => count( $before_themes ),
                    'after_count'      => count( $after_themes ),
                ],
                [
                    hws_getting_started_before_after_report(
                        'theme_auto_update_options',
                        'Theme Auto-Update Changes',
                        [
                            [
                                'item'    => 'Theme auto-update list',
                                'before'  => count( $before_themes ) . ' theme' . ( 1 === count( $before_themes ) ? '' : 's' ) . ' selected before action.',
                                'action'  => 'Saved all installed themes into the WordPress auto-update option.',
                                'after'   => count( $after_themes ) . ' of ' . $theme_count . ' installed theme' . ( 1 === $theme_count ? '' : 's' ) . ' selected after action.',
                                'meaning' => 'Installed themes are now included in WordPress theme auto-updates.',
                            ],
                            [
                                'item'    => 'Auto-update setting flag',
                                'before'  => hws_getting_started_bool_label( $before_enabled ),
                                'action'  => 'Set enable_auto_update_themes to true.',
                                'after'   => hws_getting_started_bool_label( $after_enabled ),
                                'meaning' => 'The HWS option flag now records theme auto-updates as enabled.',
                            ],
                        ],
                        'Theme auto-update settings were read, updated, and read again after saving.',
                        'This report uses WordPress options. It shows how many themes were selected for auto-updates before the action, what was saved, and the verified option state afterward.',
                        [
                            [ 'label' => 'Before', 'value' => count( $before_themes ) . ' theme auto-update entries existed.' ],
                            [ 'label' => 'After', 'value' => count( $after_themes ) . ' theme auto-update entries are saved.' ],
                        ]
                    ),
                ]
            );

        case 'clean_log_files':
            $deleted = [];
            $skipped = [];
            $rows    = [];
            foreach ( [ 'debug.log' => WP_CONTENT_DIR . '/debug.log', 'root error_log' => ABSPATH . 'error_log' ] as $label => $log_file ) {
                $before = hws_getting_started_file_state( $log_file );
                $action = 'No deletion needed.';
                if ( $before['exists'] && $before['writable'] ) {
                    $deleted[] = [ 'path' => $log_file, 'size_bytes' => $before['size_bytes'], 'size' => $before['size'] ];
                    $removed   = @unlink( $log_file );
                    $action    = $removed ? 'Deleted the writable log file.' : 'Tried to delete the writable log file, but unlink failed.';
                } else {
                    $skipped[] = $log_file;
                    $action    = $before['exists'] ? 'Skipped because the file was not writable.' : 'Skipped because the file did not exist.';
                }

                $after_exists = file_exists( $log_file );
                $rows[] = [
                    'item'    => $label,
                    'before'  => $before['before'],
                    'action'  => $action,
                    'after'   => $after_exists ? 'Still present after cleanup.' : 'Not present after cleanup.',
                    'meaning' => $after_exists ? 'Review file permissions or the log writer if this file should have been removed.' : 'The log file is no longer taking disk space at that path.',
                ];
            }
            return hws_getting_started_quick_setup_result(
                true,
                'Log file cleanup completed.',
                'success',
                [ 'deleted' => $deleted, 'skipped' => $skipped ],
                [
                    hws_getting_started_before_after_report(
                        'log_file_cleanup',
                        'Log File Cleanup',
                        $rows,
                        count( $deleted ) . ' log file' . ( 1 === count( $deleted ) ? '' : 's' ) . ' deleted.',
                        'This scans only the HWS Quick Start log targets: wp-content/debug.log and the site-root error_log. Each row shows whether the file existed before, the cleanup action, and the verified state afterward.',
                        [
                            [ 'label' => 'Before', 'value' => count( $deleted ) . ' writable log file' . ( 1 === count( $deleted ) ? '' : 's' ) . ' matched cleanup rules.' ],
                            [ 'label' => 'After', 'value' => count( $deleted ) . ' log file' . ( 1 === count( $deleted ) ? '' : 's' ) . ' deleted; ' . count( $skipped ) . ' skipped.' ],
                        ]
                    ),
                ]
            );

        case 'clean_backup_files':
            if ( ! function_exists( __NAMESPACE__ . '\\hws_scan_backups' ) ) {
                return hws_getting_started_quick_setup_result( false, 'Backup scanner is not available.', 'error' );
            }
            $deleted = [];
            $rows    = [];
            $backups = hws_scan_backups();
            foreach ( $backups as $backup ) {
                $backup_path = (string) ( $backup['path'] ?? '' );
                if ( '' !== $backup_path && file_exists( $backup_path ) && is_writable( $backup_path ) ) {
                    $before = hws_getting_started_file_state( $backup_path );
                    $deleted[] = [ 'path' => $backup_path, 'size_bytes' => $before['size_bytes'], 'size' => $before['size'] ];
                    $removed = @unlink( $backup_path );
                    $after_exists = file_exists( $backup_path );
                    $rows[] = [
                        'item'    => basename( $backup_path ),
                        'before'  => $before['before'],
                        'action'  => $removed ? 'Deleted the writable backup file.' : 'Tried to delete the writable backup file, but unlink failed.',
                        'after'   => $after_exists ? 'Still present after cleanup.' : 'Not present after cleanup.',
                        'meaning' => $after_exists ? 'Backup was detected but not fully removed.' : 'The backup file is no longer present at that path.',
                    ];
                }
            }
            if ( [] === $rows ) {
                $rows[] = [
                    'item'    => 'Backup scanner result',
                    'before'  => count( $backups ) . ' backup candidate' . ( 1 === count( $backups ) ? '' : 's' ) . ' returned by hws_scan_backups().',
                    'action'  => 'No writable backup files were deleted.',
                    'after'   => 'No deleted backup files to verify.',
                    'meaning' => 'The scanner ran, but there were no writable backup files matching the cleanup action.',
                ];
            }
            return hws_getting_started_quick_setup_result(
                true,
                'Backup cleanup completed.',
                'success',
                [ 'deleted_count' => count( $deleted ), 'deleted' => $deleted ],
                [
                    hws_getting_started_before_after_report(
                        'backup_file_cleanup',
                        'Backup File Cleanup',
                        $rows,
                        count( $deleted ) . ' backup file' . ( 1 === count( $deleted ) ? '' : 's' ) . ' deleted.',
                        'This uses the existing HWS backup scanner, then deletes only backup files that still exist and are writable. The report shows scanner output, action taken, and verified after state.',
                        [
                            [ 'label' => 'Before', 'value' => count( $backups ) . ' backup candidate' . ( 1 === count( $backups ) ? '' : 's' ) . ' returned by the scanner.' ],
                            [ 'label' => 'After', 'value' => count( $deleted ) . ' writable backup file' . ( 1 === count( $deleted ) ? '' : 's' ) . ' deleted.' ],
                        ]
                    ),
                ]
            );

        case 'run_database_cleanup':
            if ( ! function_exists( __NAMESPACE__ . '\\hws_database_cleanup_service' ) ) {
                return hws_getting_started_quick_setup_result( false, 'HWS Database Cleanup service is not available.', 'error' );
            }

            $database_cleanup = hws_database_cleanup_service()->run_full_summary();
            $task_rows        = is_array( $database_cleanup['tasks'] ?? null ) ? $database_cleanup['tasks'] : [];
            $table_rows       = is_array( $database_cleanup['tables'] ?? null ) ? $database_cleanup['tables'] : [];
            $success          = ! empty( $database_cleanup['success'] );

            return hws_getting_started_quick_setup_result(
                $success,
                $success ? 'Database cleanup and table optimization completed.' : (string) ( $database_cleanup['message'] ?? 'Database cleanup failed.' ),
                $success ? 'success' : 'error',
                [
                    'task_count'  => count( $task_rows ),
                    'table_count' => count( $table_rows ),
                ],
                [
                    hws_getting_started_before_after_report(
                        'database_cleanup_tasks',
                        'Database Cleanup Tasks',
                        [] !== $task_rows ? $task_rows : [
                            [
                                'item'    => 'Database cleanup',
                                'before'  => 'Queued',
                                'action'  => 'Tried to run the HWS Database Cleanup service.',
                                'after'   => (string) ( $database_cleanup['message'] ?? 'No task details returned.' ),
                                'meaning' => $success ? 'Cleanup completed.' : 'Cleanup did not complete.',
                            ],
                        ],
                        count( $task_rows ) . ' WP-Optimize cleanup task' . ( 1 === count( $task_rows ) ? '' : 's' ) . ' reported.',
                        'This Quick Start item calls the same HWS Database Cleanup service used by the Cleanup tab. The full live table-by-table UI is available under HWS Base Tools > Cleanup.',
                        [
                            [ 'label' => 'Cleanup Tasks', 'value' => count( $task_rows ) . ' task' . ( 1 === count( $task_rows ) ? '' : 's' ) . ' reported.' ],
                            [ 'label' => 'Tables', 'value' => count( $table_rows ) . ' table' . ( 1 === count( $table_rows ) ? '' : 's' ) . ' optimized or attempted.' ],
                        ]
                    ),
                    ChecklistReportBuilder::table(
                        'database_table_optimization',
                        'Database Table Optimization',
                        $table_rows,
                        [
                            'table'  => 'Table',
                            'engine' => 'Engine',
                            'before' => 'Overhead Before',
                            'after'  => 'Overhead After',
                            'status' => 'Status',
                        ],
                        [
                            'summary' => count( $table_rows ) . ' database table' . ( 1 === count( $table_rows ) ? '' : 's' ) . ' reported.',
                            'meta'    => [
                                'documentation' => 'Each row was sent through the HWS Database Cleanup service table optimization path.',
                            ],
                        ]
                    ),
                ]
            );

        case 'close_comments':
            global $wpdb;
            $before_default_comments = (string) get_option( 'default_comment_status', '' );
            $before_default_pings    = (string) get_option( 'default_ping_status', '' );
            $before_open_comments    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE comment_status = 'open'" );
            update_option( 'default_comment_status', 'closed' );
            update_option( 'default_ping_status', 'closed' );
            $updated_comments = $wpdb->query( "UPDATE {$wpdb->posts} SET comment_status = 'closed' WHERE comment_status = 'open'" );
            $after_open_comments = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE comment_status = 'open'" );
            return hws_getting_started_quick_setup_result(
                true,
                'Comments closed.',
                'success',
                [ 'updated_posts' => (int) $updated_comments ],
                [
                    hws_getting_started_before_after_report(
                        'comment_status_changes',
                        'Comment Status Changes',
                        [
                            [
                                'item'    => 'Future comments default',
                                'before'  => '' !== $before_default_comments ? $before_default_comments : 'not set',
                                'action'  => 'Set default_comment_status to closed.',
                                'after'   => (string) get_option( 'default_comment_status', '' ),
                                'meaning' => 'New posts default to closed comments.',
                            ],
                            [
                                'item'    => 'Future ping default touched by comments task',
                                'before'  => '' !== $before_default_pings ? $before_default_pings : 'not set',
                                'action'  => 'Set default_ping_status to closed.',
                                'after'   => (string) get_option( 'default_ping_status', '' ),
                                'meaning' => 'New posts default to closed pingbacks.',
                            ],
                            [
                                'item'    => 'Existing posts with open comments',
                                'before'  => (string) $before_open_comments,
                                'action'  => 'Updated open comment_status rows to closed.',
                                'after'   => (string) $after_open_comments,
                                'meaning' => (int) $updated_comments . ' post row' . ( 1 === (int) $updated_comments ? '' : 's' ) . ' updated.',
                            ],
                        ],
                        'Comment settings and existing post comment states were updated and verified.',
                        'This report separates future defaults from existing post rows so it is clear what was changed before and after the action.',
                        [
                            [ 'label' => 'Before', 'value' => $before_open_comments . ' post' . ( 1 === $before_open_comments ? '' : 's' ) . ' had open comments.' ],
                            [ 'label' => 'After', 'value' => $after_open_comments . ' post' . ( 1 === $after_open_comments ? '' : 's' ) . ' still have open comments.' ],
                        ]
                    ),
                ]
            );

        case 'delete_comments':
            global $wpdb;
            $before_comments = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments}" );
            $before_meta     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->commentmeta}" );
            $deleted_comments = $wpdb->query( "DELETE FROM {$wpdb->comments}" );
            $wpdb->query( "DELETE FROM {$wpdb->commentmeta}" );
            $after_comments = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments}" );
            $after_meta     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->commentmeta}" );
            return hws_getting_started_quick_setup_result(
                true,
                'Existing comments deleted.',
                'success',
                [ 'deleted_comments' => (int) $deleted_comments ],
                [
                    hws_getting_started_before_after_report(
                        'comment_deletion',
                        'Comment Deletion',
                        [
                            [
                                'item'    => 'Comments table',
                                'before'  => $before_comments . ' comment row' . ( 1 === $before_comments ? '' : 's' ) . '.',
                                'action'  => 'Deleted all rows from wp_comments.',
                                'after'   => $after_comments . ' comment row' . ( 1 === $after_comments ? '' : 's' ) . '.',
                                'meaning' => (int) $deleted_comments . ' comment row' . ( 1 === (int) $deleted_comments ? '' : 's' ) . ' deleted.',
                            ],
                            [
                                'item'    => 'Comment meta table',
                                'before'  => $before_meta . ' comment meta row' . ( 1 === $before_meta ? '' : 's' ) . '.',
                                'action'  => 'Deleted all rows from wp_commentmeta.',
                                'after'   => $after_meta . ' comment meta row' . ( 1 === $after_meta ? '' : 's' ) . '.',
                                'meaning' => 'Comment metadata was cleared after comments were removed.',
                            ],
                        ],
                        'Existing comments and comment metadata were counted, deleted, and counted again.',
                        'This destructive action clears WordPress comments and comment metadata. The report shows row counts before deletion and verified counts after deletion.',
                        [
                            [ 'label' => 'Before', 'value' => $before_comments . ' comments and ' . $before_meta . ' comment meta rows existed.' ],
                            [ 'label' => 'After', 'value' => $after_comments . ' comments and ' . $after_meta . ' comment meta rows remain.' ],
                        ]
                    ),
                ]
            );

        case 'delete_old_posts_keep_latest_10':
            return hws_getting_started_delete_old_posts_keep_latest_10_task( $payload );

        case 'ensure_smp_hexa_plugins':
            return hws_getting_started_ensure_smp_hexa_plugins_task();

        case 'close_pingbacks':
            global $wpdb;
            $before_default_ping = (string) get_option( 'default_ping_status', '' );
            $before_open_pings   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE ping_status = 'open'" );
            update_option( 'default_ping_status', 'closed' );
            $updated_pings = $wpdb->query( "UPDATE {$wpdb->posts} SET ping_status = 'closed' WHERE ping_status = 'open'" );
            $after_open_pings = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE ping_status = 'open'" );
            return hws_getting_started_quick_setup_result(
                true,
                'Pingbacks closed.',
                'success',
                [ 'updated_posts' => (int) $updated_pings ],
                [
                    hws_getting_started_before_after_report(
                        'pingback_status_changes',
                        'Pingback Status Changes',
                        [
                            [
                                'item'    => 'Future pingback default',
                                'before'  => '' !== $before_default_ping ? $before_default_ping : 'not set',
                                'action'  => 'Set default_ping_status to closed.',
                                'after'   => (string) get_option( 'default_ping_status', '' ),
                                'meaning' => 'New posts default to closed pingbacks.',
                            ],
                            [
                                'item'    => 'Existing posts with open pingbacks',
                                'before'  => (string) $before_open_pings,
                                'action'  => 'Updated open ping_status rows to closed.',
                                'after'   => (string) $after_open_pings,
                                'meaning' => (int) $updated_pings . ' post row' . ( 1 === (int) $updated_pings ? '' : 's' ) . ' updated.',
                            ],
                        ],
                        'Pingback defaults and existing post pingback states were updated and verified.',
                        'This report separates the future default from existing post rows so it is clear what changed before and after the action.',
                        [
                            [ 'label' => 'Before', 'value' => $before_open_pings . ' post' . ( 1 === $before_open_pings ? '' : 's' ) . ' had open pingbacks.' ],
                            [ 'label' => 'After', 'value' => $after_open_pings . ' post' . ( 1 === $after_open_pings ? '' : 's' ) . ' still have open pingbacks.' ],
                        ]
                    ),
                ]
            );

        case 'check_redis_object_cache':
            if ( ! function_exists( __NAMESPACE__ . '\\hws_litespeed_redis_service' ) ) {
                return hws_getting_started_quick_setup_result( false, 'LiteSpeed Redis service is not available.', 'error' );
            }

            $service = hws_litespeed_redis_service();
            $before  = $service->status();
            $result  = $service->enable();
            $after   = is_array( $result['after'] ?? null ) ? $result['after'] : $service->status();
            $success = ! empty( $result['success'] );

            return hws_getting_started_quick_setup_result(
                $success,
                $success ? 'LiteSpeed Redis object cache is enabled and active.' : (string) ( $result['message'] ?? 'LiteSpeed Redis object cache did not fully verify.' ),
                $success ? 'success' : 'warning',
                [
                    'enabled_before' => ! empty( $before['enabled'] ),
                    'active_before'  => ! empty( $before['active'] ),
                    'enabled_after'  => ! empty( $after['enabled'] ),
                    'active_after'   => ! empty( $after['active'] ),
                    'host'           => (string) ( $after['host'] ?? '' ),
                    'port'           => (int) ( $after['port'] ?? 0 ),
                ],
                [
                    hws_getting_started_before_after_report(
                        'litespeed_redis_object_cache',
                        'LiteSpeed Redis Object Cache',
                        [
                            [
                                'item'    => 'Redis enabled in LiteSpeed',
                                'before'  => hws_getting_started_bool_label( ! empty( $before['enabled'] ) ),
                                'action'  => 'Saved LiteSpeed object-cache settings for Redis and asked LiteSpeed to refresh managed cache files.',
                                'after'   => hws_getting_started_bool_label( ! empty( $after['enabled'] ) ),
                                'meaning' => ! empty( $after['enabled'] ) ? 'LiteSpeed object cache is configured for Redis and the drop-in exists.' : 'LiteSpeed Redis configuration still needs attention.',
                            ],
                            [
                                'item'    => 'Redis actively running',
                                'before'  => hws_getting_started_bool_label( ! empty( $before['active'] ) ),
                                'action'  => 'Ran Redis connection and WordPress object-cache set/get/delete tests.',
                                'after'   => hws_getting_started_bool_label( ! empty( $after['active'] ) ),
                                'meaning' => ! empty( $after['active'] ) ? 'Redis is reachable and WordPress object-cache calls verified.' : (string) ( $after['message'] ?? 'Object-cache verification failed.' ),
                            ],
                            [
                                'item'    => 'LiteSpeed Redis target',
                                'before'  => (string) ( $before['host'] ?? '' ) . ':' . (string) ( $before['port'] ?? '' ),
                                'action'  => 'Used LiteSpeed object-cache settings as the source of truth.',
                                'after'   => (string) ( $after['host'] ?? '' ) . ':' . (string) ( $after['port'] ?? '' ),
                                'meaning' => 'This avoids the old hardcoded Redis localhost-only check.',
                            ],
                        ],
                        $success ? 'LiteSpeed Redis object cache verified enabled and active.' : (string) ( $result['message'] ?? 'LiteSpeed Redis object cache needs attention.' ),
                        'This report uses the shared LiteSpeed Redis service used by the HWS Overview Redis panel.',
                        [
                            [ 'label' => 'Enabled', 'value' => hws_getting_started_bool_label( ! empty( $after['enabled'] ) ) ],
                            [ 'label' => 'Actively Running', 'value' => hws_getting_started_bool_label( ! empty( $after['active'] ) ) ],
                        ]
                    ),
                ]
            );

        case 'activate_litespeed_cache':
            return hws_getting_started_activate_plugin_task( 'litespeed-cache/litespeed-cache.php', 'LiteSpeed Cache' );

        case 'activate_wordfence':
            return hws_getting_started_activate_plugin_task( 'wordfence/wordfence.php', 'Wordfence' );

	        case 'enable_recommended_snippets':
	            if ( ! function_exists( __NAMESPACE__ . '\\hws_get_going_live_snippets' ) ) {
	                return hws_getting_started_quick_setup_result( false, 'Going Live snippet list is not available.', 'error' );
	            }
	            $enabled_count = 0;
	            $already_count = 0;
	            $rows          = [];
	            foreach ( hws_get_going_live_snippets() as $snippet_id ) {
	                $before_enabled = (bool) get_option( $snippet_id, false );
	                if ( $before_enabled ) {
	                    $already_count++;
	                    $action = 'No change needed; snippet was already enabled.';
	                } else {
	                    update_option( $snippet_id, true );
	                    $enabled_count++;
	                    $action = 'Set the snippet option to enabled.';
	                }
	                $after_enabled = (bool) get_option( $snippet_id, false );
	                $rows[] = [
	                    'item'    => (string) $snippet_id,
	                    'before'  => hws_getting_started_bool_label( $before_enabled ),
	                    'action'  => $action,
	                    'after'   => hws_getting_started_bool_label( $after_enabled ),
	                    'meaning' => $after_enabled ? 'Recommended snippet is active for Going Live checks.' : 'Snippet did not verify enabled.',
	                ];
	            }
	            return hws_getting_started_quick_setup_result(
	                true,
	                'Recommended snippets enabled.',
	                'success',
	                [ 'enabled_count' => $enabled_count, 'already_count' => $already_count ],
	                [
	                    hws_getting_started_before_after_report(
	                        'recommended_snippet_enablement',
	                        'Recommended Snippet Enablement',
	                        $rows,
	                        count( $rows ) . ' recommended snippet option' . ( 1 === count( $rows ) ? '' : 's' ) . ' checked and enabled where needed.',
	                        'This report reads each Going Live recommended snippet option before the action, enables missing ones, then reads the option again to verify the final state.',
	                        [
	                            [ 'label' => 'Before', 'value' => $already_count . ' snippet option' . ( 1 === $already_count ? '' : 's' ) . ' already enabled.' ],
	                            [ 'label' => 'Action Taken', 'value' => $enabled_count . ' snippet option' . ( 1 === $enabled_count ? '' : 's' ) . ' changed to enabled.' ],
	                            [ 'label' => 'Verified After', 'value' => count( array_filter( $rows, static fn( array $row ): bool => 'Enabled' === ( $row['after'] ?? '' ) ) ) . ' snippet option' . ( 1 === count( $rows ) ? '' : 's' ) . ' verified enabled.' ],
	                        ]
	                    ),
	                ]
	            );

        case 'install_essential_plugins':
            return hws_getting_started_install_essential_plugins_task();

	        case 'apply_wordfence_alert_email':
	            $alert_email = isset( $inputs['wordfence_alert_email'] ) ? sanitize_email( (string) $inputs['wordfence_alert_email'] ) : '';
	            if ( '' === $alert_email || ! is_email( $alert_email ) ) {
	                return hws_getting_started_quick_setup_result( false, 'Wordfence alert email input is missing or invalid.', 'error' );
	            }
	            if ( ! function_exists( __NAMESPACE__ . '\\hws_quick_setup_apply_wordfence_alert_email' ) ) {
	                return hws_getting_started_quick_setup_result( false, 'Wordfence alert email updater is not available.', 'error' );
	            }
	            $before_wordfence_email = hws_getting_started_read_wordfence_alert_email();
	            $wordfence_result = hws_quick_setup_apply_wordfence_alert_email( $alert_email );
	            $after_wordfence_email  = hws_getting_started_read_wordfence_alert_email();
	            $wordfence_verified     = in_array( $alert_email, hws_getting_started_email_list_from_value( $after_wordfence_email ), true );
	            $wordfence_success      = ! empty( $wordfence_result['success'] ) && $wordfence_verified;
	            return hws_getting_started_quick_setup_result(
	                $wordfence_success,
	                $wordfence_success ? 'Wordfence alert email saved and verified.' : wp_strip_all_tags( (string) ( $wordfence_result['message'] ?? 'Wordfence alert email did not verify.' ) ),
	                $wordfence_success ? 'success' : 'error',
	                [ 'input' => 'wordfence_alert_email', 'requested_email' => $alert_email, 'verified_value' => $after_wordfence_email ],
	                [
	                    hws_getting_started_before_after_report(
	                        'wordfence_alert_email_update',
	                        'Wordfence Alert Email Update',
	                        [
	                            [
	                                'item'    => 'Wordfence alertEmails',
	                                'before'  => '' !== $before_wordfence_email ? $before_wordfence_email : 'No value found before action.',
	                                'action'  => 'Saved typed email ' . $alert_email . ' into the Wordfence alertEmails config value.',
	                                'after'   => '' !== $after_wordfence_email ? $after_wordfence_email : 'No value found after action.',
	                                'meaning' => $wordfence_verified ? 'The requested alert email is now present in Wordfence config.' : 'The requested alert email was not verified in Wordfence config.',
	                            ],
	                        ],
	                        'Wordfence alert email was read, updated, and read again.',
	                        'This report reads the Wordfence wfconfig alertEmails value before the action, writes the typed email through the existing updater, then verifies the requested email afterward.',
	                        [
	                            [ 'label' => 'Before', 'value' => '' !== $before_wordfence_email ? $before_wordfence_email : 'No alert email value was readable.' ],
	                            [ 'label' => 'Action Taken', 'value' => 'Requested alert email: ' . $alert_email . '.' ],
	                            [ 'label' => 'Verified After', 'value' => $wordfence_verified ? 'Requested email found in Wordfence config.' : 'Requested email not found in Wordfence config.' ],
	                        ]
	                    ),
	                ]
	            );

	        case 'apply_smtp_from_email':
	            $from_email = isset( $inputs['smtp_from_email'] ) ? sanitize_email( (string) $inputs['smtp_from_email'] ) : '';
	            if ( '' === $from_email || ! is_email( $from_email ) ) {
	                return hws_getting_started_quick_setup_result( false, 'SMTP from email input is missing or invalid.', 'error' );
	            }
	            if ( ! function_exists( __NAMESPACE__ . '\\hws_quick_setup_apply_wp_mail_smtp_from_email' ) ) {
	                return hws_getting_started_quick_setup_result( false, 'WP Mail SMTP from email updater is not available.', 'error' );
	            }
	            $before_smtp_email = hws_getting_started_read_wp_mail_smtp_from_email();
	            $smtp_result = hws_quick_setup_apply_wp_mail_smtp_from_email( $from_email );
	            $after_smtp_email  = hws_getting_started_read_wp_mail_smtp_from_email();
	            $smtp_verified     = strtolower( $after_smtp_email ) === strtolower( $from_email );
	            return hws_getting_started_quick_setup_result(
	                ! empty( $smtp_result['success'] ) && $smtp_verified,
	                ( ! empty( $smtp_result['success'] ) && $smtp_verified ) ? 'WP Mail SMTP from email saved and verified.' : wp_strip_all_tags( (string) ( $smtp_result['message'] ?? 'WP Mail SMTP from email did not fully verify.' ) ),
	                ( ! empty( $smtp_result['success'] ) && $smtp_verified ) ? 'success' : 'warning',
	                [ 'input' => 'smtp_from_email', 'requested_email' => $from_email, 'verified_value' => $after_smtp_email ],
	                [
	                    hws_getting_started_before_after_report(
	                        'wp_mail_smtp_from_email_update',
	                        'WP Mail SMTP From Email Update',
	                        [
	                            [
	                                'item'    => 'WP Mail SMTP from_email',
	                                'before'  => '' !== $before_smtp_email ? $before_smtp_email : 'No from email found before action.',
	                                'action'  => 'Saved typed email ' . $from_email . ' into wp_mail_smtp[mail][from_email].',
	                                'after'   => '' !== $after_smtp_email ? $after_smtp_email : 'No from email found after action.',
	                                'meaning' => $smtp_verified ? 'The requested sender email is now saved in WP Mail SMTP options.' : 'The requested sender email was not verified in WP Mail SMTP options.',
	                            ],
	                        ],
	                        'WP Mail SMTP from email was read, updated, and read again.',
	                        'This report reads the WP Mail SMTP from_email option before the action, writes the typed email through the existing updater, then verifies the requested email afterward.',
	                        [
	                            [ 'label' => 'Before', 'value' => '' !== $before_smtp_email ? $before_smtp_email : 'No sender email was readable.' ],
	                            [ 'label' => 'Action Taken', 'value' => 'Requested sender email: ' . $from_email . '.' ],
	                            [ 'label' => 'Verified After', 'value' => $smtp_verified ? 'Requested email found in WP Mail SMTP options.' : 'Requested email not found in WP Mail SMTP options.' ],
	                        ]
	                    ),
	                ]
	            );
    }

    return hws_getting_started_quick_setup_result( false, 'Unknown Quick Setup task.', 'error', [ 'task' => $task ] );
}

/**
 * @return array<string,mixed>
 */
function hws_getting_started_regenerate_favicon_ico_task(): array {
    if ( ! function_exists( __NAMESPACE__ . '\\hws_create_letter_site_icon' ) ) {
        require_once __DIR__ . '/settings-dashboard.php';
    }

    if ( ! function_exists( __NAMESPACE__ . '\\hws_create_letter_site_icon' ) ) {
        return hws_getting_started_quick_setup_result( false, 'Letter favicon PNG + ICO generator is not available.', 'error' );
    }

    $favicon_path    = ABSPATH . 'favicon.ico';
    $purged_existing = false;
    $old_size        = 0;

    if ( file_exists( $favicon_path ) ) {
        $old_size        = (int) filesize( $favicon_path );
        $purged_existing = @unlink( $favicon_path );

        if ( ! $purged_existing && file_exists( $favicon_path ) ) {
            return hws_getting_started_quick_setup_result( false, 'Could not purge the existing /favicon.ico file before regeneration.', 'error', [ 'favicon_path' => $favicon_path, 'old_size_bytes' => $old_size ] );
        }
    }

    $letter     = strtoupper( substr( sanitize_title( get_bloginfo( 'name' ) ), 0, 1 ) ?: 'H' );
    $background = '#111827';
    $foreground = '#ffffff';
    $result     = hws_create_letter_site_icon( $letter, $background, $foreground );

    if ( is_wp_error( $result ) ) {
        return hws_getting_started_quick_setup_result(
            false,
            $result->get_error_message(),
            'error',
            [
                'letter'           => $letter,
                'background'       => $background,
                'foreground'       => $foreground,
                'favicon_path'     => $favicon_path,
                'purged_existing'  => $purged_existing,
                'old_size_bytes'   => $old_size,
                'old_size'         => $old_size > 0 ? size_format( $old_size ) : '',
            ]
        );
    }

    $attachment_id = (int) ( $result['attachment_id'] ?? 0 );
    $icon_url      = (string) ( $result['icon_url'] ?? ( $attachment_id ? wp_get_attachment_url( $attachment_id ) : '' ) );
    $favicon_url   = home_url( '/favicon.ico' );
    $cache_bust    = (string) time();
    $icon_preview_url    = $icon_url ? add_query_arg( 'hws_preview', $cache_bust, $icon_url ) : '';
    $favicon_preview_url = add_query_arg( 'hws_preview', $cache_bust, $favicon_url );
    $new_size      = file_exists( $favicon_path ) ? (int) filesize( $favicon_path ) : 0;
	    $rows     = [
	        [
	            'item'    => 'Generated PNG Site Icon',
	            'before'  => 'Generated during this action from the site title letter; no prior attachment was reused.',
	            'action'  => 'Called hws_create_letter_site_icon() and set the result as the WordPress Site Icon.',
	            'after'   => $icon_url ? 'PNG URL verified: ' . $icon_url : 'Created without a readable URL.',
	            'meaning' => $attachment_id ? 'WordPress Site Icon attachment ID ' . $attachment_id . ' is available.' : 'The PNG attachment could not be verified.',
	            'url'     => $icon_url,
	        ],
	        [
	            'item'    => 'Generated ICO Favicon',
	            'before'  => $old_size > 0 ? 'Existing /favicon.ico was present at ' . size_format( $old_size ) . '.' : 'No existing /favicon.ico file was present.',
	            'action'  => $purged_existing ? 'Purged the old /favicon.ico and regenerated a fresh ICO file.' : 'Generated a fresh ICO file at /favicon.ico.',
	            'after'   => $new_size > 0 ? 'ICO file verified at ' . size_format( $new_size ) . '.' : 'ICO file was not found after generation.',
	            'meaning' => $new_size > 0 ? 'Browsers can request the root favicon.ico URL.' : 'The favicon ICO still needs investigation.',
	            'url'     => $favicon_url,
	        ],
	        [
	            'item'    => 'Existing /favicon.ico cleanup',
	            'before'  => $old_size > 0 ? 'Existing file size was ' . size_format( $old_size ) . '.' : 'No old favicon file existed before this run.',
	            'action'  => $old_size > 0 ? 'Removed the old root favicon before writing the new one.' : 'No old file needed removal.',
	            'after'   => file_exists( $favicon_path ) ? 'Root favicon path now exists.' : 'Root favicon path is still missing.',
	            'meaning' => 'The old favicon state was handled before verifying the newly generated ICO.',
	            'url'     => '',
	        ],
	    ];

    return hws_getting_started_quick_setup_result(
        $attachment_id > 0 && $new_size > 0,
        $attachment_id > 0 && $new_size > 0 ? 'Generated a fresh PNG Site Icon and /favicon.ico through the Brand Assets letter generator.' : 'Favicon generation completed but one generated asset was not found.',
        $attachment_id > 0 && $new_size > 0 ? 'success' : 'error',
        [
            'letter'           => $letter,
            'background'       => $background,
            'foreground'       => $foreground,
            'attachment_id'    => $attachment_id,
            'icon_url'         => $icon_url,
            'png_url'          => $icon_url,
            'favicon_url'      => $favicon_url,
            'ico_url'          => $favicon_url,
            'favicon_path'     => $favicon_path,
            'favicon_size'     => $new_size > 0 ? size_format( $new_size ) : '',
            'favicon_size_bytes' => $new_size,
            'purged_existing'  => $purged_existing,
            'old_size_bytes'   => $old_size,
            'old_size'         => $old_size > 0 ? size_format( $old_size ) : '',
        ],
        [
            ChecklistReportBuilder::table(
                'favicon_ico_regeneration',
                'Favicon ICO Regeneration',
                $rows,
	                [
	                    'item'    => 'Item',
	                    'before'  => 'Before Action',
	                    'action'  => 'Action Taken',
	                    'after'   => 'Verified After',
	                    'meaning' => 'What Changed',
	                    'url'     => 'URL',
	                ],
	                [
	                    'summary' => 'Quick Start called the same hws_create_letter_site_icon() path used by the Brand Assets Generate PNG + ICO button.',
	                    'meta'    => [
	                        'documentation' => 'This report shows the favicon state before generation, the exact generator action, and the verified PNG plus ICO links afterward.',
	                        'summary_items' => [
	                            [ 'label' => 'Before', 'value' => $old_size > 0 ? 'An existing /favicon.ico file was present at ' . size_format( $old_size ) . '.' : 'No existing root ICO file was found.' ],
	                            [ 'label' => 'Action Taken', 'value' => 'Generated a PNG WordPress Site Icon and a root /favicon.ico with the Brand Assets letter generator.' ],
	                            [ 'label' => 'Verified After', 'value' => ( $attachment_id > 0 ? 'PNG attachment verified. ' : 'PNG attachment missing. ' ) . ( $new_size > 0 ? 'ICO file verified at ' . size_format( $new_size ) . '.' : 'ICO file missing.' ) ],
	                        ],
	                        'preview_assets' => array_values(
                            array_filter(
                                [
                                    $icon_url ? [
                                        'label'       => 'Generated PNG Site Icon',
                                        'format'      => 'PNG',
                                        'url'         => $icon_url,
                                        'preview_url' => $icon_preview_url,
                                        'meta'        => $attachment_id ? 'Attachment ID: ' . $attachment_id : '',
                                    ] : [],
                                    $new_size > 0 ? [
                                        'label'       => 'Generated ICO Favicon',
                                        'format'      => 'ICO',
                                        'url'         => $favicon_url,
                                        'preview_url' => $favicon_preview_url,
                                        'meta'        => size_format( $new_size ),
                                    ] : [],
                                ]
                            )
                        ),
                    ],
                ]
            ),
        ]
    );
}

/**
 * @param array<string,mixed> $context
 * @return array<string,mixed>
 */
function hws_getting_started_quick_setup_result( bool $success, string $message, string $level = 'success', array $context = [], array $reports = [] ): array {
    $data = $context;
    if ( [] !== $reports ) {
        $data['reports'] = array_values( array_filter( $reports ) );
    }

    return [
        'success' => $success,
        'message' => '' !== $message ? $message : ( $success ? 'Task completed.' : 'Task failed.' ),
        'logs'    => [
            [
                'level'   => $level,
                'message' => '' !== $message ? $message : ( $success ? 'Task completed.' : 'Task failed.' ),
                'context' => $context,
            ],
        ],
        'data'    => $data,
    ];
}

/**
 * @return array<string,mixed>
 */
function hws_getting_started_activate_plugin_task( string $plugin_path, string $label ): array {
    if ( ! function_exists( 'is_plugin_active' ) && defined( 'ABSPATH' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $before_installed = file_exists( WP_PLUGIN_DIR . '/' . $plugin_path );
    $before_active    = $before_installed && is_plugin_active( $plugin_path );

    if ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin_path ) ) {
        return hws_getting_started_quick_setup_result(
            false,
            $label . ' is not installed.',
            'warning',
            [ 'plugin' => $plugin_path ],
            [
                hws_getting_started_before_after_report(
                    'plugin_activation',
                    $label . ' Activation',
                    [
                        [
                            'item'    => $label,
                            'before'  => 'Plugin file missing: ' . $plugin_path,
                            'action'  => 'Activation skipped because WordPress cannot activate a missing plugin file.',
                            'after'   => 'Plugin still missing.',
                            'meaning' => 'Install the plugin before this checklist item can activate it.',
                        ],
                    ],
                    $label . ' was not activated because it is missing.',
                    'This report reads the plugin file and active state before activation, runs activation only when the file exists, then verifies the active state afterward.',
                    [
                        [ 'label' => 'Before', 'value' => 'Plugin file was missing.' ],
                        [ 'label' => 'Verified After', 'value' => 'Plugin remains unavailable for activation.' ],
                    ]
                ),
            ]
        );
    }

    if ( is_plugin_active( $plugin_path ) ) {
        return hws_getting_started_quick_setup_result(
            true,
            $label . ' is already active.',
            'success',
            [ 'plugin' => $plugin_path ],
            [
                hws_getting_started_before_after_report(
                    'plugin_activation',
                    $label . ' Activation',
                    [
                        [
                            'item'    => $label,
                            'before'  => 'Installed and active.',
                            'action'  => 'No activation needed.',
                            'after'   => 'Installed and active.',
                            'meaning' => 'The required plugin state was already satisfied.',
                        ],
                    ],
                    $label . ' active state was verified without changes.',
                    'This report reads the plugin file and active state before activation, runs activation only when needed, then verifies the active state afterward.',
                    [
                        [ 'label' => 'Before', 'value' => 'Plugin was already active.' ],
                        [ 'label' => 'Verified After', 'value' => 'Plugin is still active.' ],
                    ]
                ),
            ]
        );
    }

    $result = activate_plugin( $plugin_path );
    $after_installed = file_exists( WP_PLUGIN_DIR . '/' . $plugin_path );
    $after_active    = $after_installed && is_plugin_active( $plugin_path );
    if ( is_wp_error( $result ) ) {
        return hws_getting_started_quick_setup_result(
            false,
            'Could not activate ' . $label . ': ' . $result->get_error_message(),
            'error',
            [ 'plugin' => $plugin_path ],
            [
                hws_getting_started_before_after_report(
                    'plugin_activation',
                    $label . ' Activation',
                    [
                        [
                            'item'    => $label,
                            'before'  => ( $before_installed ? 'Installed' : 'Missing' ) . ', ' . ( $before_active ? 'active' : 'inactive' ) . '.',
                            'action'  => 'Attempted activate_plugin(' . $plugin_path . ').',
                            'after'   => ( $after_installed ? 'Installed' : 'Missing' ) . ', ' . ( $after_active ? 'active' : 'inactive' ) . '.',
                            'meaning' => 'Activation failed: ' . $result->get_error_message(),
                        ],
                    ],
                    $label . ' activation failed and the verified state is shown below.',
                    'This report reads the plugin file and active state before activation, attempts activation, then verifies the active state afterward.',
                    [
                        [ 'label' => 'Before', 'value' => $before_active ? 'Plugin was active.' : 'Plugin was installed but inactive.' ],
                        [ 'label' => 'Verified After', 'value' => $after_active ? 'Plugin is active.' : 'Plugin is not active.' ],
                    ]
                ),
            ]
        );
    }

    return hws_getting_started_quick_setup_result(
        $after_active,
        $after_active ? $label . ' activated and verified.' : $label . ' activation did not verify.',
        $after_active ? 'success' : 'error',
        [ 'plugin' => $plugin_path ],
        [
            hws_getting_started_before_after_report(
                'plugin_activation',
                $label . ' Activation',
                [
                    [
                        'item'    => $label,
                        'before'  => ( $before_installed ? 'Installed' : 'Missing' ) . ', ' . ( $before_active ? 'active' : 'inactive' ) . '.',
                        'action'  => 'Ran activate_plugin(' . $plugin_path . ').',
                        'after'   => ( $after_installed ? 'Installed' : 'Missing' ) . ', ' . ( $after_active ? 'active' : 'inactive' ) . '.',
                        'meaning' => $after_active ? 'The plugin can now run on the site.' : 'The plugin did not reach the required active state.',
                    ],
                ],
                $label . ' activation was run and verified.',
                'This report reads the plugin file and active state before activation, runs activation, then verifies the active state afterward.',
                [
                    [ 'label' => 'Before', 'value' => $before_active ? 'Plugin was active.' : 'Plugin was installed but inactive.' ],
                    [ 'label' => 'Verified After', 'value' => $after_active ? 'Plugin is active.' : 'Plugin is not active.' ],
                ]
            ),
        ]
    );
}

/**
 * @return array<string,mixed>
 */
function hws_getting_started_install_essential_plugins_task(): array {
    if ( ! function_exists( __NAMESPACE__ . '\\hws_get_monitored_plugins' ) ) {
        return hws_getting_started_quick_setup_result( false, 'Monitored plugin list is not available.', 'error' );
    }

    if ( ! function_exists( 'is_plugin_active' ) && defined( 'ABSPATH' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';

	    $activated = 0;
	    $installed = 0;
	    $skipped   = 0;
	    $failed    = [];
	    $rows      = [];

	    foreach ( hws_get_monitored_plugins() as $plugin_path => $info ) {
	        if ( ( $info['category'] ?? '' ) !== 'essential' ) {
	            continue;
	        }

	        $name = (string) ( $info['name'] ?? $plugin_path );
	        $before_installed = file_exists( WP_PLUGIN_DIR . '/' . $plugin_path );
	        $before_active    = $before_installed && is_plugin_active( $plugin_path );
	        $action_result    = '';
	        if ( ! empty( $info['pro'] ) ) {
	            $skipped++;
	            $rows[] = hws_getting_started_essential_plugin_report_row( $name, $plugin_path, $before_installed, $before_active, $before_installed, $before_active, 'Skipped because this is marked as a pro/manual plugin.' );
	            continue;
	        }

	        if ( is_plugin_active( $plugin_path ) ) {
	            $skipped++;
	            $rows[] = hws_getting_started_essential_plugin_report_row( $name, $plugin_path, $before_installed, $before_active, $before_installed, true, 'No change needed; plugin was already active.' );
	            continue;
	        }

	        if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin_path ) ) {
	            $result = activate_plugin( $plugin_path );
	            if ( is_wp_error( $result ) ) {
	                $failed[] = $name . ': ' . $result->get_error_message();
	                $action_result = 'Activation failed: ' . $result->get_error_message();
	            } else {
	                $activated++;
	                $action_result = 'Activated installed plugin.';
	            }
	            $rows[] = hws_getting_started_essential_plugin_report_row( $name, $plugin_path, $before_installed, $before_active, file_exists( WP_PLUGIN_DIR . '/' . $plugin_path ), is_plugin_active( $plugin_path ), $action_result );
	            continue;
	        }

	        if ( ( $info['download'] ?? 'manual' ) === 'manual' ) {
	            $skipped++;
	            $rows[] = hws_getting_started_essential_plugin_report_row( $name, $plugin_path, $before_installed, $before_active, false, false, 'Skipped because this plugin requires manual installation.' );
	            continue;
	        }

	        $slug = basename( dirname( $plugin_path ) );
	        $api  = plugins_api( 'plugin_information', [ 'slug' => $slug, 'fields' => [ 'sections' => false ] ] );
	        if ( is_wp_error( $api ) ) {
	            $failed[] = $name . ': repository lookup failed';
	            $rows[] = hws_getting_started_essential_plugin_report_row( $name, $plugin_path, $before_installed, $before_active, file_exists( WP_PLUGIN_DIR . '/' . $plugin_path ), is_plugin_active( $plugin_path ), 'WordPress.org lookup failed.' );
	            continue;
	        }

	        $upgrader = new \Plugin_Upgrader( new \WP_Ajax_Upgrader_Skin() );
	        $result   = $upgrader->install( $api->download_link );
	        if ( ! $result || is_wp_error( $result ) ) {
	            $failed[] = $name . ': install failed';
	            $rows[] = hws_getting_started_essential_plugin_report_row( $name, $plugin_path, $before_installed, $before_active, file_exists( WP_PLUGIN_DIR . '/' . $plugin_path ), is_plugin_active( $plugin_path ), 'Install failed from WordPress.org.' );
	            continue;
	        }

	        $activate_result = activate_plugin( $plugin_path );
	        if ( is_wp_error( $activate_result ) ) {
	            $failed[] = $name . ': activation failed after install';
	            $action_result = 'Installed from WordPress.org, but activation failed.';
	        } else {
	            $installed++;
	            $action_result = 'Installed from WordPress.org and activated.';
	        }
	        $rows[] = hws_getting_started_essential_plugin_report_row( $name, $plugin_path, $before_installed, $before_active, file_exists( WP_PLUGIN_DIR . '/' . $plugin_path ), is_plugin_active( $plugin_path ), $action_result );
	    }

	    $success = [] === $failed;
	    return hws_getting_started_quick_setup_result(
	        $success,
        $success ? 'Essential plugin setup completed.' : 'Essential plugin setup completed with failures.',
        $success ? 'success' : 'warning',
        [
	            'installed' => $installed,
	            'activated' => $activated,
	            'skipped'   => $skipped,
	            'failed'    => $failed,
	        ],
	        [
	            hws_getting_started_before_after_report(
	                'essential_plugin_setup',
	                'Essential Plugin Setup',
	                $rows,
	                count( $rows ) . ' essential plugin' . ( 1 === count( $rows ) ? '' : 's' ) . ' checked.',
	                'This report reads each essential plugin file and active state before the action, installs or activates where allowed, then reads the plugin state again afterward.',
	                [
	                    [ 'label' => 'Before', 'value' => count( array_filter( $rows, static fn( array $row ): bool => str_contains( (string) ( $row['before'] ?? '' ), 'Active' ) ) ) . ' plugin' . ( 1 === count( $rows ) ? '' : 's' ) . ' active before action.' ],
	                    [ 'label' => 'Action Taken', 'value' => $installed . ' installed, ' . $activated . ' activated, ' . $skipped . ' skipped.' ],
	                    [ 'label' => 'Verified After', 'value' => count( array_filter( $rows, static fn( array $row ): bool => str_contains( (string) ( $row['after'] ?? '' ), 'Active' ) ) ) . ' plugin' . ( 1 === count( $rows ) ? '' : 's' ) . ' active after action.' ],
	                ]
	            ),
	        ]
	    );
}

function hws_getting_started_essential_plugin_report_row( string $name, string $plugin_path, bool $before_installed, bool $before_active, bool $after_installed, bool $after_active, string $action_result ): array {
    $before = $before_installed ? ( $before_active ? 'Installed, Active' : 'Installed, Inactive' ) : 'Missing';
    $after  = $after_installed ? ( $after_active ? 'Installed, Active' : 'Installed, Inactive' ) : 'Missing';

    return [
        'item'    => $name,
        'before'  => $before,
        'action'  => $action_result,
        'after'   => $after,
        'meaning' => $after_active ? 'The plugin is available and active.' : ( $after_installed ? 'The plugin exists but is not active.' : 'The plugin is still missing.' ),
    ];
}

/**
 * @param array<string,mixed> $payload
 * @return array<string,mixed>
 */
function hws_getting_started_delete_old_posts_keep_latest_10_task( array $payload ): array {
    $inputs       = is_array( $payload['inputs'] ?? null ) ? $payload['inputs'] : [];
    $confirmation = isset( $inputs['delete_old_posts_confirmation'] ) ? trim( (string) $inputs['delete_old_posts_confirmation'] ) : '';

    if ( ! hash_equals( HWS_GETTING_STARTED_NEWS_OUTLET_DELETE_CONFIRMATION, $confirmation ) ) {
        return hws_getting_started_quick_setup_result( false, 'Delete old posts confirmation is invalid.', 'error' );
    }

    if ( function_exists( 'current_user_can' ) && ! current_user_can( 'delete_posts' ) ) {
        return hws_getting_started_quick_setup_result( false, 'Current user cannot delete posts.', 'error' );
    }

    if ( ! function_exists( __NAMESPACE__ . '\\hws_article_media_cleanup_config' ) ) {
        return hws_getting_started_quick_setup_result( false, 'Article & Media Cleanup configuration is not available.', 'error' );
    }

    $config   = hws_article_media_cleanup_config();
    $scanner  = new ArticleMediaCleanupScanner( $config );
    $criteria = [
        'post_type'   => 'post',
        'status'      => 'any',
        'keep_recent' => 10,
        'search'      => '',
        'limit'       => $config->max_limit(),
    ];

    $batch_size          = $config->max_batch_size();
    $exclude_ids         = [];
    $batches             = [];
    $deleted_total       = 0;
    $failed_total        = 0;
    $deleted_media_total = 0;
    $preserved_ids       = [];
    $last_has_more       = false;

    for ( $batch = 1; $batch <= 200; $batch++ ) {
        $result = $scanner->delete_batch( $criteria, true, 'all_except_keep_recent', $batch_size, $exclude_ids );
        if ( is_wp_error( $result ) ) {
            return hws_getting_started_quick_setup_result(
                false,
                'Article cleanup failed: ' . $result->get_error_message(),
                'error',
                [
                    'post_type'   => 'post',
                    'keep_recent' => 10,
                    'batch'       => $batch,
                ]
            );
        }

        $deleted_count       = (int) ( $result['deleted_count'] ?? 0 );
        $failed_count        = (int) ( $result['failed_count'] ?? 0 );
        $deleted_media_count = (int) ( $result['deleted_media_count'] ?? 0 );
        $preserved_ids       = array_values( array_unique( array_merge( $preserved_ids, (array) ( $result['preserved_ids'] ?? [] ) ) ) );
        $exclude_ids         = array_values( array_unique( (array) ( $result['exclude_ids'] ?? $exclude_ids ) ) );
        $last_has_more       = ! empty( $result['has_more'] );

        $deleted_total       += $deleted_count;
        $failed_total        += $failed_count;
        $deleted_media_total += $deleted_media_count;

        $batches[] = [
            'batch'         => $batch,
            'post_type'     => 'post',
            'deleted_posts' => $deleted_count,
            'failed_posts'  => $failed_count,
            'deleted_media' => $deleted_media_count,
            'preserved_ids' => implode( ', ', array_map( 'strval', (array) ( $result['preserved_ids'] ?? [] ) ) ),
            'failed_ids'    => implode( ', ', array_map( 'strval', (array) ( $result['failed_ids'] ?? [] ) ) ),
        ];

        if ( ! $last_has_more ) {
            break;
        }
    }

    $success = ! $last_has_more && 0 === $failed_total;
    $message = $deleted_total > 0
        ? sprintf( 'Deleted %d old posts and %d associated media item(s); preserved the newest 10 posts.', $deleted_total, $deleted_media_total )
        : 'No old posts needed deletion; the newest 10 posts remain protected.';

    if ( $last_has_more ) {
        $message = 'Article cleanup stopped before all batches completed. Re-run the task to continue.';
    } elseif ( $failed_total > 0 ) {
        $message = sprintf( 'Article cleanup completed with %d failed post deletion(s).', $failed_total );
    }

    return hws_getting_started_quick_setup_result(
        $success,
        $message,
        $success ? 'success' : 'warning',
        [
            'post_type'           => 'post',
            'status'              => 'any',
            'keep_recent'         => 10,
            'deleted_posts'       => $deleted_total,
            'failed_posts'        => $failed_total,
            'deleted_media'       => $deleted_media_total,
            'preserved_ids'       => $preserved_ids,
            'batch_limit_reached' => $last_has_more,
        ],
        [
            ChecklistReportBuilder::table(
                'news_outlet_article_cleanup',
                'News Outlet Article Cleanup',
                $batches,
                [
                    'batch'         => 'Batch',
                    'post_type'     => 'Post Type',
                    'deleted_posts' => 'Deleted Posts',
                    'failed_posts'  => 'Failed Posts',
                    'deleted_media' => 'Deleted Media',
                    'preserved_ids' => 'Preserved IDs',
                    'failed_ids'    => 'Failed IDs',
                ],
	                [
	                    'summary' => 'Post type post only. The newest 10 matching posts were preserved; older matching posts were deleted with associated media through the existing cleanup scanner.',
	                    'meta'    => [
	                        'documentation' => 'This destructive report scans only WordPress posts, preserves the newest 10, deletes older matching posts in batches through the existing Article & Media Cleanup scanner, and reports deleted posts, failed posts, deleted media, and preserved IDs.',
	                        'summary_items' => [
	                            [ 'label' => 'Before', 'value' => 'The scanner selected post type post with any status and protected the newest 10 posts.' ],
	                            [ 'label' => 'Action Taken', 'value' => $deleted_total . ' old post' . ( 1 === $deleted_total ? '' : 's' ) . ' and ' . $deleted_media_total . ' associated media item' . ( 1 === $deleted_media_total ? '' : 's' ) . ' were deleted across ' . count( $batches ) . ' batch' . ( 1 === count( $batches ) ? '' : 'es' ) . '.' ],
	                            [ 'label' => 'Verified After', 'value' => $failed_total . ' failed post deletion' . ( 1 === $failed_total ? '' : 's' ) . '; batch limit reached: ' . ( $last_has_more ? 'yes' : 'no' ) . '.' ],
	                        ],
	                    ],
	                ]
	            ),
	        ]
    );
}

/**
 * @return array<string,mixed>
 */
function hws_getting_started_ensure_smp_hexa_plugins_task(): array {
    if ( function_exists( 'current_user_can' ) && ( ! current_user_can( 'install_plugins' ) || ! current_user_can( 'activate_plugins' ) ) ) {
        return hws_getting_started_quick_setup_result( false, 'Current user cannot install and activate plugins.', 'error' );
    }

    $definitions = hws_getting_started_smp_hexa_plugin_definitions();
    $rows        = [];
    $failed      = [];
    $installed   = 0;
    $activated   = 0;
    $already     = 0;

    foreach ( $definitions as $definition_config ) {
        $definition = PluginCheckDefinition::from_array( $definition_config );
        $before     = PluginCheckService::status( $definition );

        $result = hws_getting_started_ensure_plugin_state( $definition );
        if ( is_wp_error( $result ) ) {
            $after     = PluginCheckService::status( $definition );
            $failed[]  = $definition->name . ': ' . $result->get_error_message();
            $rows[]    = hws_getting_started_plugin_setup_report_row( $definition->name, $before, $after, 'Failed: ' . $result->get_error_message() );
            continue;
        }

        $after = is_array( $result['status'] ?? null ) ? $result['status'] : PluginCheckService::status( $definition );

        if ( empty( $before['installed'] ) && ! empty( $after['installed'] ) ) {
            $installed++;
        } elseif ( empty( $before['active'] ) && ! empty( $after['active'] ) ) {
            $activated++;
        } else {
            $already++;
        }

        if ( empty( $after['ok'] ) ) {
            $failed[] = $definition->name . ': required plugin state was not reached.';
        }

        $rows[] = hws_getting_started_plugin_setup_report_row( $definition->name, $before, $after, (string) ( $result['message'] ?? 'Processed.' ) );
    }

    $success = [] === $failed;

    return hws_getting_started_quick_setup_result(
        $success,
        $success ? 'News outlet plugin setup completed.' : 'News outlet plugin setup completed with failures.',
        $success ? 'success' : 'warning',
        [
            'installed' => $installed,
            'activated' => $activated,
            'already'   => $already,
            'failed'    => $failed,
        ],
        [
            ChecklistReportBuilder::table(
                'news_outlet_plugin_setup',
                'News Outlet Plugin Setup',
                $rows,
                [
                    'plugin'        => 'Plugin',
                    'before'        => 'Before',
                    'after'         => 'After',
                    'version'       => 'Version',
                    'plugin_file'   => 'Plugin File',
                    'action_result' => 'Action Result',
                ],
	                [
	                    'summary' => 'Required public WordPress.org plugins and HWS/SMP GitHub plugins are installed and activated through Hexa WP Core plugin checks. Pro/manual plugins are excluded from this automatic task.',
	                    'meta'    => [
	                        'documentation' => 'This report checks the required news outlet plugin stack before each install or activation action, runs the Hexa WP Core plugin installer/status path where possible, and verifies installed/active state afterward.',
	                        'summary_items' => [
	                            [ 'label' => 'Before', 'value' => count( $definitions ) . ' required plugin definition' . ( 1 === count( $definitions ) ? '' : 's' ) . ' were checked.' ],
	                            [ 'label' => 'Action Taken', 'value' => $installed . ' installed, ' . $activated . ' activated, ' . $already . ' already satisfied.' ],
	                            [ 'label' => 'Verified After', 'value' => [] === $failed ? 'All required plugin states verified.' : count( $failed ) . ' plugin issue' . ( 1 === count( $failed ) ? '' : 's' ) . ' reported.' ],
	                        ],
	                    ],
	                ]
	            ),
	        ]
    );
}

/**
 * @return array<string,mixed>|\WP_Error
 */
function hws_getting_started_ensure_plugin_state( PluginCheckDefinition $definition ): array|\WP_Error {
    $status          = PluginCheckService::status( $definition );
    $requires_active = ! empty( $definition->checks['active'] );

    if ( ! empty( $status['installed'] ) ) {
        if ( $requires_active ) {
            return PluginCheckService::activate( $definition );
        }

        if ( ! empty( $status['active'] ) ) {
            return PluginCheckService::deactivate( $definition );
        }

        return [
            'message' => 'Plugin is installed and intentionally inactive.',
            'status'  => $status,
        ];
    }

    if ( 'wordpress_org' === $definition->source ) {
        $installed = PluginProvisioner::install_wordpress_org_plugin( $definition->wp_org_slug, $requires_active );
        if ( is_wp_error( $installed ) ) {
            return $installed;
        }

        return [
            'message' => (string) ( $installed['message'] ?? 'Plugin installed.' ),
            'status'  => PluginCheckService::status( $definition ),
        ];
    }

    if ( 'github' === $definition->source ) {
        if ( $requires_active ) {
            return PluginCheckService::install_and_activate( $definition );
        }

        $installed = PluginProvisioner::install_github_plugin(
            $definition->slug,
            $definition->github_repo,
            [ 'branch' => $definition->github_branch ]
        );
        if ( is_wp_error( $installed ) ) {
            return $installed;
        }

        return [
            'message' => 'Plugin installed and intentionally left inactive.',
            'status'  => PluginCheckService::status( $definition ),
        ];
    }

    return new \WP_Error( 'hws_getting_started_plugin_manual_install_required', $definition->name . ' requires a manual or pro plugin install.' );
}

/**
 * @return array<int,array<string,mixed>>
 */
function hws_getting_started_smp_hexa_plugin_definitions(): array {
    return [
        hws_getting_started_news_outlet_plugin_definition( 'hws-base-tools/hws-base-tools.php', 'HWS Base Tools', 'github', [ 'github_repo' => 'mikeyperes/hws-base-tools', 'notes' => 'Hexa admin foundation plugin.' ] ),
        hws_getting_started_news_outlet_plugin_definition( 'classic-editor/classic-editor.php', 'Classic Editor', 'wordpress_org', [ 'wp_org_slug' => 'classic-editor', 'auto_update' => true ] ),
        hws_getting_started_news_outlet_plugin_definition( 'elementor/elementor.php', 'Elementor', 'wordpress_org', [ 'wp_org_slug' => 'elementor', 'auto_update' => true ] ),
        hws_getting_started_news_outlet_plugin_definition( 'litespeed-cache/litespeed-cache.php', 'LiteSpeed Cache', 'wordpress_org', [ 'wp_org_slug' => 'litespeed-cache', 'auto_update' => true ] ),
        hws_getting_started_news_outlet_plugin_definition( 'seo-by-rank-math/rank-math.php', 'Rank Math SEO', 'wordpress_org', [ 'wp_org_slug' => 'seo-by-rank-math', 'auto_update' => true ] ),
        hws_getting_started_news_outlet_plugin_definition( 'google-site-kit/google-site-kit.php', 'Site Kit by Google', 'wordpress_org', [ 'wp_org_slug' => 'google-site-kit', 'auto_update' => true ] ),
        hws_getting_started_news_outlet_plugin_definition( 'wordfence/wordfence.php', 'Wordfence Security', 'wordpress_org', [ 'wp_org_slug' => 'wordfence', 'auto_update' => false ] ),
        hws_getting_started_news_outlet_plugin_definition( 'wp-optimize/wp-optimize.php', 'WP-Optimize', 'wordpress_org', [ 'wp_org_slug' => 'wp-optimize', 'auto_update' => true ] ),
        hws_getting_started_news_outlet_plugin_definition( 'wp-sweep/wp-sweep.php', 'WP-Sweep', 'wordpress_org', [ 'wp_org_slug' => 'wp-sweep', 'auto_update' => true ] ),
        hws_getting_started_news_outlet_plugin_definition( 'wp-mail-smtp/wp_mail_smtp.php', 'WP Mail SMTP', 'wordpress_org', [ 'wp_org_slug' => 'wp-mail-smtp', 'auto_update' => true ] ),
        hws_getting_started_news_outlet_plugin_definition( 'smp-publication-integration/smp-publication-integration.php', 'SMP Publication Integration', 'github', [ 'github_repo' => 'mikeyperes/smp-publication-integration', 'notes' => 'SMP publication workflow plugin.' ] ),
        hws_getting_started_news_outlet_plugin_definition( 'smp-verified-profiles/smp-verified-profiles.php', 'Verified Profiles', 'github', [ 'github_repo' => 'mikeyperes/smp-verified-profiles', 'notes' => 'Verified profile management for SMP publications.' ] ),
    ];
}

/**
 * @param array<string,mixed> $args
 * @return array<string,mixed>
 */
function hws_getting_started_news_outlet_plugin_definition( string $plugin_file, string $name, string $source, array $args = [] ): array {
    $active_check = array_key_exists( 'active', $args ) ? (bool) $args['active'] : true;

    return [
        'id'                   => str_replace( [ '/', '.' ], '-', $plugin_file ),
        'name'                 => $name,
        'plugin_file'          => $plugin_file,
        'slug'                 => (string) ( $args['slug'] ?? dirname( $plugin_file ) ),
        'source'               => $source,
        'wp_org_slug'          => (string) ( $args['wp_org_slug'] ?? '' ),
        'github_repo'          => (string) ( $args['github_repo'] ?? '' ),
        'github_branch'        => (string) ( $args['github_branch'] ?? 'main' ),
        'required'             => true,
        'recommended'          => true,
        'auto_update_expected' => (bool) ( $args['auto_update'] ?? false ),
        'checks'               => [
            'installed'  => true,
            'active'     => $active_check,
            'up_to_date' => false,
        ],
        'notes'                => (string) ( $args['notes'] ?? 'Required news outlet plugin stack.' ),
    ];
}

/**
 * @param array<string,mixed> $before
 * @param array<string,mixed> $after
 * @return array<string,string>
 */
function hws_getting_started_plugin_setup_report_row( string $name, array $before, array $after, string $action_result ): array {
    return [
        'plugin'        => $name,
        'before'        => hws_getting_started_plugin_status_label( $before ),
        'after'         => hws_getting_started_plugin_status_label( $after ),
        'version'       => (string) ( $after['version'] ?? '' ),
        'plugin_file'   => (string) ( $after['plugin_file'] ?? $after['configured_file'] ?? '' ),
        'action_result' => $action_result,
    ];
}

/**
 * @param array<string,mixed> $status
 */
function hws_getting_started_plugin_status_label( array $status ): string {
    if ( empty( $status['installed'] ) ) {
        return 'Missing';
    }

    if ( empty( $status['active'] ) ) {
        return 'Installed, inactive';
    }

    return 'Active';
}


/**
 * @param array<string,mixed> $payload
 * @return array<string,mixed>
 */
function hws_getting_started_check_wordpress_runtime( array $payload ): array {
    global $wp_version;

    return [
        'success' => true,
        'message' => 'WordPress runtime is readable.',
        'logs'    => [
            [
                'level'   => 'success',
                'message' => 'WordPress runtime values were collected.',
                'context' => [
                    'wordpress_version' => (string) $wp_version,
                    'home_url'          => function_exists( 'home_url' ) ? home_url( '/' ) : '',
                    'site_url'          => function_exists( 'site_url' ) ? site_url( '/' ) : '',
                    'ajax_url'          => function_exists( 'admin_url' ) ? admin_url( 'admin-ajax.php' ) : '',
                ],
            ],
        ],
    ];
}

/**
 * @param array<string,mixed> $payload
 * @return array<string,mixed>
 */
function hws_getting_started_check_php_runtime( array $payload ): array {
    $memory_limit = ini_get( 'memory_limit' );

    return [
        'success' => true,
        'message' => 'PHP runtime is readable.',
        'logs'    => [
            [
                'level'   => version_compare( PHP_VERSION, '8.0', '>=' ) ? 'success' : 'warning',
                'message' => 'PHP runtime values were collected.',
                'context' => [
                    'php_version'  => PHP_VERSION,
                    'memory_limit' => false === $memory_limit ? '' : (string) $memory_limit,
                ],
            ],
        ],
    ];
}

/**
 * @param array<string,mixed> $payload
 * @return array<string,mixed>
 */
function hws_getting_started_check_hws_version( array $payload ): array {
    $version = hws_getting_started_plugin_version();

    return [
        'success' => '' !== $version,
        'message' => '' !== $version ? 'HWS Base Tools version detected: ' . $version : 'HWS Base Tools version could not be detected.',
        'logs'    => [
            [
                'level'   => '' !== $version ? 'success' : 'error',
                'message' => 'HWS Base Tools plugin version check finished.',
                'context' => [
                    'version'     => $version,
                    'plugin_file' => defined( 'HWS_BASE_TOOLS_CANONICAL_PLUGIN_FILE' ) ? HWS_BASE_TOOLS_CANONICAL_PLUGIN_FILE : '',
                ],
            ],
        ],
    ];
}

/**
 * @param array<string,mixed> $payload
 * @return array<string,mixed>
 */
function hws_getting_started_check_core_version( array $payload ): array {
    $version_file = __DIR__ . '/lib/hexa-wordpress-plugin-core/VERSION';
    $version      = is_readable( $version_file ) ? trim( (string) file_get_contents( $version_file ) ) : '';

    return [
        'success' => '' !== $version,
        'message' => '' !== $version ? 'Hexa WP Core version detected: ' . $version : 'Hexa WP Core version could not be detected.',
        'logs'    => [
            [
                'level'   => '' !== $version ? 'success' : 'error',
                'message' => 'Vendored Hexa WP Core version check finished.',
                'context' => [
                    'version'      => $version,
                    'version_file' => $version_file,
                ],
            ],
        ],
    ];
}

/**
 * @param array<string,mixed> $payload
 * @return array<string,mixed>
 */
function hws_getting_started_check_site_identity( array $payload ): array {
    $title = function_exists( 'get_bloginfo' ) ? trim( (string) get_bloginfo( 'name' ) ) : '';

    return [
        'success' => '' !== $title,
        'message' => '' !== $title ? 'Site identity is readable.' : 'Site title is empty.',
        'logs'    => [
            [
                'level'   => '' !== $title ? 'success' : 'error',
                'message' => 'Site identity check finished.',
                'context' => [
                    'title'    => $title,
                    'home_url' => function_exists( 'home_url' ) ? home_url( '/' ) : '',
                    'site_url' => function_exists( 'site_url' ) ? site_url( '/' ) : '',
                ],
            ],
        ],
    ];
}

/**
 * @param array<string,mixed> $payload
 * @return array<string,mixed>
 */
function hws_getting_started_check_permalink_structure( array $payload ): array {
    $structure = function_exists( 'get_option' ) ? (string) get_option( 'permalink_structure', '' ) : '';

    return [
        'success' => true,
        'message' => '' !== $structure ? 'Permalink structure is configured.' : 'Permalink structure is using the default format.',
        'logs'    => [
            [
                'level'   => '' !== $structure ? 'success' : 'warning',
                'message' => 'Permalink structure check finished.',
                'context' => [
                    'permalink_structure' => $structure,
                ],
            ],
        ],
    ];
}

function hws_getting_started_plugin_version(): string {
    if ( defined( 'HWS_BASE_TOOLS_CANONICAL_PLUGIN_FILE' ) && is_readable( HWS_BASE_TOOLS_CANONICAL_PLUGIN_FILE ) ) {
        if ( ! function_exists( 'get_plugin_data' ) && defined( 'ABSPATH' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if ( function_exists( 'get_plugin_data' ) ) {
            $plugin_data = get_plugin_data( HWS_BASE_TOOLS_CANONICAL_PLUGIN_FILE, false, false );
            $version     = trim( (string) ( $plugin_data['Version'] ?? '' ) );
            if ( '' !== $version ) {
                return $version;
            }
        }
    }

    global $plugin_version;

    return isset( $plugin_version ) ? trim( (string) $plugin_version ) : '';
}
