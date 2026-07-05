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
            'title'         => 'Getting Started Checklist',
            'description'   => 'Runs HWS Base Tools startup checks through the reusable Hexa WP Core checklist structure. HWS registers the steps; Hexa WP Core owns the UI, AJAX runner, status states, and activity log.',
            'capability'    => 'manage_options',
            'nonce_action'  => HWS_GETTING_STARTED_CHECKLIST_NONCE_ACTION,
            'nonce_field'   => 'nonce',
            'run_action'    => 'hws_getting_started_checklist_run_item',
            'empty_message' => 'No HWS getting started checks are registered.',
            'steps'         => [
                [
                    'id'          => 'system_environment',
                    'label'       => 'Verify System Environment',
                    'description' => 'Checks the WordPress and PHP runtime values needed before plugin setup work starts.',
                    'subtasks'    => [
                        [
                            'id'          => 'wordpress_runtime',
                            'label'       => 'WordPress Runtime',
                            'description' => 'Reports WordPress version, home URL, site URL, and admin AJAX availability.',
                            'callback'    => __NAMESPACE__ . '\\hws_getting_started_check_wordpress_runtime',
                        ],
                        [
                            'id'          => 'php_runtime',
                            'label'       => 'PHP Runtime',
                            'description' => 'Reports PHP version and memory limit.',
                            'callback'    => __NAMESPACE__ . '\\hws_getting_started_check_php_runtime',
                        ],
                    ],
                ],
                [
                    'id'          => 'plugin_versions',
                    'label'       => 'Verify Plugin Versions',
                    'description' => 'Checks the active HWS Base Tools version and the vendored Hexa WP Core version.',
                    'subtasks'    => [
                        [
                            'id'          => 'hws_base_tools_version',
                            'label'       => 'HWS Base Tools Version',
                            'description' => 'Reads the active plugin header/runtime version.',
                            'callback'    => __NAMESPACE__ . '\\hws_getting_started_check_hws_version',
                        ],
                        [
                            'id'          => 'hexa_wp_core_version',
                            'label'       => 'Hexa WP Core Version',
                            'description' => 'Reads the vendored Hexa WP Core VERSION file.',
                            'callback'    => __NAMESPACE__ . '\\hws_getting_started_check_core_version',
                        ],
                    ],
                ],
                [
                    'id'          => 'site_basics',
                    'label'       => 'Verify Site Basics',
                    'description' => 'Checks site identity and permalink readiness without changing site content.',
                    'subtasks'    => [
                        [
                            'id'          => 'site_identity',
                            'label'       => 'Site Identity',
                            'description' => 'Reports the website title and front-end URL values.',
                            'callback'    => __NAMESPACE__ . '\\hws_getting_started_check_site_identity',
                        ],
                        [
                            'id'          => 'permalink_structure',
                            'label'       => 'Permalink Structure',
                            'description' => 'Confirms WordPress permalink settings are readable.',
                            'callback'    => __NAMESPACE__ . '\\hws_getting_started_check_permalink_structure',
                        ],
                    ],
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
        if ( isset( $tabs['getting-started-checklist'] ) ) {
            return $tabs;
        }

        $updated  = [];
        $inserted = false;

        foreach ( $tabs as $key => $label ) {
            $updated[ $key ] = $label;
            if ( 'overview' === $key ) {
                $updated['getting-started-checklist'] = 'Getting Started Checklist';
                $inserted = true;
            }
        }

        if ( ! $inserted ) {
            $updated['getting-started-checklist'] = 'Getting Started Checklist';
        }

        return $updated;
    }
);

add_filter(
    'hws_base_tools_render_dashboard_tab',
    function( bool $handled, string $tab_id ): bool {
        if ( $handled || 'getting-started-checklist' !== $tab_id ) {
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
