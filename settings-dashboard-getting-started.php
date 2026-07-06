<?php

namespace hws_base_tools;

use Hexa\PluginCore\GettingStartedChecklist\ChecklistReportBuilder;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistAjaxController;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistConfig;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistRenderer;
use Hexa\PluginCore\WpAdminUiCleanup\CleanupChecklistAdapter;

defined( 'ABSPATH' ) || exit;

const HWS_GETTING_STARTED_CHECKLIST_NONCE_ACTION = 'hws_base_tools_getting_started_checklist';

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
            'empty_message' => 'No HWS getting started checks are registered.',
            'steps'         => [
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
                    'id'          => 'ui_cleanup',
                    'label'       => 'UI',
                    'type'        => 'status_check',
                    'action_label'=> 'Check UI',
                    'description' => 'Lists every registered UI Cleanup option from the UI Cleanup tab as a checklist subtask. The attributes are generated from the source option definitions, not hand-coded into the checklist.',
                    'subtasks'    => hws_getting_started_ui_cleanup_subtasks(),
                ],
            ],
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
