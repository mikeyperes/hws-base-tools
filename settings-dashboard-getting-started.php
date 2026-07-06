<?php

namespace hws_base_tools;

use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistAjaxController;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistConfig;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistRenderer;

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
                    'description' => 'Runs the existing HWS Quick Setup process: disables debug settings, sets WP_MEMORY_LIMIT, enables auto-updates, cleans logs and backups, disables comments and pingbacks, enables snippets, installs/activates essential plugins, and checks Redis, LiteSpeed, and Wordfence.',
                    'callback'    => __NAMESPACE__ . '\\hws_getting_started_run_quick_setup',
                ],
                [
                    'id'          => 'required_launch_settings',
                    'label'       => 'Verify Required Launch Settings',
                    'type'        => 'status_check',
                    'description' => 'Runs the existing Going Live Checklist status checks for WP memory, comments, pingbacks, SMTP authentication, debug constants, and Wordfence alert email configuration.',
                    'subtasks'    => hws_getting_started_required_launch_setting_subtasks(),
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
 * @param array<string,mixed> $payload
 * @return array<string,mixed>
 */
function hws_getting_started_run_quick_setup( array $payload ): array {
    if ( ! function_exists( __NAMESPACE__ . '\\hws_execute_quick_setup' ) ) {
        return [
            'success' => false,
            'message' => 'HWS Quick Setup function is not loaded.',
            'logs'    => [
                [
                    'level'   => 'error',
                    'message' => 'The checklist could not find hws_execute_quick_setup().',
                    'context' => [ 'function' => __NAMESPACE__ . '\\hws_execute_quick_setup' ],
                ],
            ],
        ];
    }

    $raw_log = hws_execute_quick_setup();
    $lines   = preg_split( '/\r\n|\r|\n/', trim( wp_strip_all_tags( (string) $raw_log ) ) ) ?: [];
    $logs    = [
        [
            'level'   => 'info',
            'message' => 'Existing HWS Quick Setup process started from the Hexa Core checklist.',
            'context' => [ 'source_function' => __NAMESPACE__ . '\\hws_execute_quick_setup' ],
        ],
    ];

    foreach ( $lines as $line ) {
        $line = trim( (string) $line );
        if ( '' === $line ) {
            continue;
        }

        $level = 'info';
        if ( str_contains( strtolower( $line ), 'error' ) || str_contains( $line, '❌' ) ) {
            $level = 'error';
        } elseif ( str_contains( strtolower( $line ), 'warning' ) || str_contains( $line, '⚠' ) ) {
            $level = 'warning';
        } elseif ( str_contains( $line, '✓' ) || str_contains( $line, '✅' ) || str_contains( strtolower( $line ), 'complete' ) ) {
            $level = 'success';
        }

        $logs[] = [
            'level'   => $level,
            'message' => $line,
            'context' => [],
        ];
    }

    return [
        'success' => true,
        'message' => 'HWS Quick Setup completed.',
        'logs'    => $logs,
    ];
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
