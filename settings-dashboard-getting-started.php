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
            'description' => 'Runs HWS Quick Setup as isolated Hexa Core tasks. Requirements are attached only to the task that consumes them.',
            'subtasks'    => hws_getting_started_quick_setup_subtasks(),
        ],
        [
            'id'          => 'required_launch_settings',
            'label'       => 'Verify Required Launch Settings',
            'type'        => 'status_check',
            'description' => 'Runs the existing Going Live Checklist status checks for WP memory, comments, pingbacks, SMTP authentication, debug constants, and Wordfence alert email configuration.',
            'subtasks'    => hws_getting_started_required_launch_setting_subtasks(),
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
        hws_getting_started_quick_setup_task_definition( 'close_comments', 'Close Comments', 'config_mutation', 'Closes future comments and existing open post comments.', $callback ),
        hws_getting_started_quick_setup_task_definition( 'delete_comments', 'Delete Comments', 'setup_action', 'Deletes existing comments and comment meta.', $callback ),
        hws_getting_started_quick_setup_task_definition( 'close_pingbacks', 'Close Pingbacks', 'config_mutation', 'Closes future pingbacks and existing open post pingbacks.', $callback ),
        hws_getting_started_quick_setup_task_definition( 'check_redis_object_cache', 'Check Redis Object Cache', 'status_check', 'Checks Redis availability and enables the existing LiteSpeed object cache constant when Redis connects.', $callback ),
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
        'result' => $result,
        'report' => ChecklistReportBuilder::wp_config_changes( array_values( $changes ) ),
    ];
}

function hws_getting_started_read_wp_config_constant( string $constant ): string {
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
            return hws_getting_started_quick_setup_result( (bool) ( $config_change['result']['status'] ?? false ), 'Debug settings disabled.', ! empty( $config_change['result']['status'] ) ? 'success' : 'error', [ 'constants' => [ 'WP_DEBUG', 'WP_DEBUG_DISPLAY', 'WP_DEBUG_LOG' ], 'wp_config_message' => (string) ( $config_change['result']['message'] ?? '' ) ], [ $config_change['report'] ] );

        case 'set_memory_limit':
            $config_change = hws_getting_started_apply_wp_config_constants( [ 'WP_MEMORY_LIMIT' => '4096M' ] );
            return hws_getting_started_quick_setup_result( (bool) ( $config_change['result']['status'] ?? false ), 'WP_MEMORY_LIMIT set to 4096M.', ! empty( $config_change['result']['status'] ) ? 'success' : 'error', [ 'constant' => 'WP_MEMORY_LIMIT', 'value' => '4096M', 'wp_config_message' => (string) ( $config_change['result']['message'] ?? '' ) ], [ $config_change['report'] ] );

        case 'enable_core_auto_updates':
            $config_change = hws_getting_started_apply_wp_config_constants( [ 'WP_AUTO_UPDATE_CORE' => 'true' ] );
            return hws_getting_started_quick_setup_result( (bool) ( $config_change['result']['status'] ?? false ), 'WordPress core auto-updates enabled.', ! empty( $config_change['result']['status'] ) ? 'success' : 'error', [ 'constant' => 'WP_AUTO_UPDATE_CORE', 'value' => 'true', 'wp_config_message' => (string) ( $config_change['result']['message'] ?? '' ) ], [ $config_change['report'] ] );

        case 'enable_plugin_auto_updates':
            if ( ! function_exists( 'get_plugins' ) && defined( 'ABSPATH' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $all_plugins = function_exists( 'get_plugins' ) ? array_keys( get_plugins() ) : [];
            update_option( 'auto_update_plugins', $all_plugins );
            update_option( 'enable_auto_update_plugins', true );
            return hws_getting_started_quick_setup_result( true, 'Plugin auto-updates enabled.', 'success', [ 'count' => count( $all_plugins ) ] );

        case 'enable_theme_auto_updates':
            $all_themes = array_keys( wp_get_themes() );
            update_option( 'auto_update_themes', $all_themes );
            update_option( 'enable_auto_update_themes', true );
            return hws_getting_started_quick_setup_result( true, 'Theme auto-updates enabled.', 'success', [ 'count' => count( $all_themes ) ] );

        case 'clean_log_files':
            $deleted = [];
            $skipped = [];
            foreach ( [ WP_CONTENT_DIR . '/debug.log', ABSPATH . 'error_log' ] as $log_file ) {
                if ( file_exists( $log_file ) && is_writable( $log_file ) ) {
                    $size_bytes = (int) filesize( $log_file );
                    $deleted[]  = [ 'path' => $log_file, 'size_bytes' => $size_bytes, 'size' => size_format( $size_bytes ) ];
                    @unlink( $log_file );
                } else {
                    $skipped[] = $log_file;
                }
            }
            return hws_getting_started_quick_setup_result( true, 'Log file cleanup completed.', 'success', [ 'deleted' => $deleted, 'skipped' => $skipped ], [ ChecklistReportBuilder::deleted_files( $deleted, [ 'title' => 'Log Files Deleted' ] ) ] );

        case 'clean_backup_files':
            if ( ! function_exists( __NAMESPACE__ . '\\hws_scan_backups' ) ) {
                return hws_getting_started_quick_setup_result( false, 'Backup scanner is not available.', 'error' );
            }
            $deleted = [];
            foreach ( hws_scan_backups() as $backup ) {
                $backup_path = (string) ( $backup['path'] ?? '' );
                if ( '' !== $backup_path && file_exists( $backup_path ) && is_writable( $backup_path ) ) {
                    $size_bytes = (int) filesize( $backup_path );
                    $deleted[]  = [ 'path' => $backup_path, 'size_bytes' => $size_bytes, 'size' => size_format( $size_bytes ) ];
                    @unlink( $backup_path );
                }
            }
            return hws_getting_started_quick_setup_result( true, 'Backup cleanup completed.', 'success', [ 'deleted_count' => count( $deleted ), 'deleted' => $deleted ], [ ChecklistReportBuilder::deleted_files( $deleted, [ 'title' => 'Backup Files Deleted' ] ) ] );

        case 'close_comments':
            global $wpdb;
            update_option( 'default_comment_status', 'closed' );
            update_option( 'default_ping_status', 'closed' );
            $updated_comments = $wpdb->query( "UPDATE {$wpdb->posts} SET comment_status = 'closed' WHERE comment_status = 'open'" );
            return hws_getting_started_quick_setup_result( true, 'Comments closed.', 'success', [ 'updated_posts' => (int) $updated_comments ] );

        case 'delete_comments':
            global $wpdb;
            $deleted_comments = $wpdb->query( "DELETE FROM {$wpdb->comments}" );
            $wpdb->query( "DELETE FROM {$wpdb->commentmeta}" );
            return hws_getting_started_quick_setup_result( true, 'Existing comments deleted.', 'success', [ 'deleted_comments' => (int) $deleted_comments ] );

        case 'delete_old_posts_keep_latest_10':
            return hws_getting_started_delete_old_posts_keep_latest_10_task( $payload );

        case 'ensure_smp_hexa_plugins':
            return hws_getting_started_ensure_smp_hexa_plugins_task();

        case 'close_pingbacks':
            global $wpdb;
            update_option( 'default_ping_status', 'closed' );
            $updated_pings = $wpdb->query( "UPDATE {$wpdb->posts} SET ping_status = 'closed' WHERE ping_status = 'open'" );
            return hws_getting_started_quick_setup_result( true, 'Pingbacks closed.', 'success', [ 'updated_posts' => (int) $updated_pings ] );

        case 'check_redis_object_cache':
            if ( ! class_exists( 'Redis' ) ) {
                return hws_getting_started_quick_setup_result( false, 'Redis PHP extension is not installed.', 'warning' );
            }
            try {
                $redis = new \Redis();
                if ( @$redis->connect( '127.0.0.1', 6379, 2 ) ) {
                    $config_change = hws_getting_started_apply_wp_config_constants( [ 'LSCWP_OBJECT_CACHE' => 'true' ] );
                    $redis->close();
                    return hws_getting_started_quick_setup_result( (bool) ( $config_change['result']['status'] ?? false ), 'Redis connected and LiteSpeed object cache constant enabled.', ! empty( $config_change['result']['status'] ) ? 'success' : 'error', [ 'wp_config_message' => (string) ( $config_change['result']['message'] ?? '' ) ], [ $config_change['report'] ] );
                }
            } catch ( \Exception $exception ) {
                return hws_getting_started_quick_setup_result( false, 'Redis check failed: ' . $exception->getMessage(), 'warning' );
            }
            return hws_getting_started_quick_setup_result( false, 'Redis service is not running.', 'warning' );

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
            foreach ( hws_get_going_live_snippets() as $snippet_id ) {
                if ( get_option( $snippet_id, false ) ) {
                    $already_count++;
                } else {
                    update_option( $snippet_id, true );
                    $enabled_count++;
                }
            }
            return hws_getting_started_quick_setup_result( true, 'Recommended snippets enabled.', 'success', [ 'enabled_count' => $enabled_count, 'already_count' => $already_count ] );

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
            $wordfence_result = hws_quick_setup_apply_wordfence_alert_email( $alert_email );
            return hws_getting_started_quick_setup_result( (bool) ( $wordfence_result['success'] ?? false ), wp_strip_all_tags( (string) ( $wordfence_result['message'] ?? '' ) ), ! empty( $wordfence_result['success'] ) ? 'success' : 'error', [ 'input' => 'wordfence_alert_email' ] );

        case 'apply_smtp_from_email':
            $from_email = isset( $inputs['smtp_from_email'] ) ? sanitize_email( (string) $inputs['smtp_from_email'] ) : '';
            if ( '' === $from_email || ! is_email( $from_email ) ) {
                return hws_getting_started_quick_setup_result( false, 'SMTP from email input is missing or invalid.', 'error' );
            }
            if ( ! function_exists( __NAMESPACE__ . '\\hws_quick_setup_apply_wp_mail_smtp_from_email' ) ) {
                return hws_getting_started_quick_setup_result( false, 'WP Mail SMTP from email updater is not available.', 'error' );
            }
            $smtp_result = hws_quick_setup_apply_wp_mail_smtp_from_email( $from_email );
            return hws_getting_started_quick_setup_result( (bool) ( $smtp_result['success'] ?? false ), wp_strip_all_tags( (string) ( $smtp_result['message'] ?? '' ) ), ! empty( $smtp_result['success'] ) ? 'success' : 'warning', [ 'input' => 'smtp_from_email' ] );
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
    $new_size      = file_exists( $favicon_path ) ? (int) filesize( $favicon_path ) : 0;
    $rows     = [
        [
            'asset'  => 'Generated PNG Site Icon',
            'status' => $icon_url ? 'Created and set as WordPress Site Icon' : 'Created without URL',
            'url'    => $icon_url,
            'meta'   => $attachment_id ? 'Attachment ID: ' . $attachment_id : '',
        ],
        [
            'asset'  => 'Generated ICO Favicon',
            'status' => $new_size > 0 ? 'Created at /favicon.ico' : 'Missing',
            'url'    => $favicon_url,
            'meta'   => $new_size > 0 ? size_format( $new_size ) : '',
        ],
        [
            'asset'  => 'Existing /favicon.ico',
            'status' => $purged_existing ? 'Purged before regeneration' : 'No existing file found',
            'url'    => '',
            'meta'   => $old_size > 0 ? size_format( $old_size ) : '',
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
                    'asset'  => 'Asset',
                    'status' => 'Status',
                    'url'    => 'URL',
                    'meta'   => 'Meta',
                ],
                [
                    'summary' => 'Quick Start called the same hws_create_letter_site_icon() path used by the Brand Assets Generate PNG + ICO button.',
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

    if ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin_path ) ) {
        return hws_getting_started_quick_setup_result( false, $label . ' is not installed.', 'warning', [ 'plugin' => $plugin_path ] );
    }

    if ( is_plugin_active( $plugin_path ) ) {
        return hws_getting_started_quick_setup_result( true, $label . ' is already active.', 'success', [ 'plugin' => $plugin_path ] );
    }

    $result = activate_plugin( $plugin_path );
    if ( is_wp_error( $result ) ) {
        return hws_getting_started_quick_setup_result( false, 'Could not activate ' . $label . ': ' . $result->get_error_message(), 'error', [ 'plugin' => $plugin_path ] );
    }

    return hws_getting_started_quick_setup_result( true, $label . ' activated.', 'success', [ 'plugin' => $plugin_path ] );
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

    foreach ( hws_get_monitored_plugins() as $plugin_path => $info ) {
        if ( ( $info['category'] ?? '' ) !== 'essential' ) {
            continue;
        }

        $name = (string) ( $info['name'] ?? $plugin_path );
        if ( ! empty( $info['pro'] ) ) {
            $skipped++;
            continue;
        }

        if ( is_plugin_active( $plugin_path ) ) {
            $skipped++;
            continue;
        }

        if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin_path ) ) {
            $result = activate_plugin( $plugin_path );
            if ( is_wp_error( $result ) ) {
                $failed[] = $name . ': ' . $result->get_error_message();
            } else {
                $activated++;
            }
            continue;
        }

        if ( ( $info['download'] ?? 'manual' ) === 'manual' ) {
            $skipped++;
            continue;
        }

        $slug = basename( dirname( $plugin_path ) );
        $api  = plugins_api( 'plugin_information', [ 'slug' => $slug, 'fields' => [ 'sections' => false ] ] );
        if ( is_wp_error( $api ) ) {
            $failed[] = $name . ': repository lookup failed';
            continue;
        }

        $upgrader = new \Plugin_Upgrader( new \WP_Ajax_Upgrader_Skin() );
        $result   = $upgrader->install( $api->download_link );
        if ( ! $result || is_wp_error( $result ) ) {
            $failed[] = $name . ': install failed';
            continue;
        }

        $activate_result = activate_plugin( $plugin_path );
        if ( is_wp_error( $activate_result ) ) {
            $failed[] = $name . ': activation failed after install';
        } else {
            $installed++;
        }
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
        ]
    );
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
