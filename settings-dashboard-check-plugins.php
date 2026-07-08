<?php namespace hws_base_tools;

use Hexa\PluginCore\PluginChecks\PluginInventoryAjaxController;
use Hexa\PluginCore\PluginChecks\PluginInventoryRenderer;
use Hexa\PluginCore\PluginChecks\PluginRecommendationRegistry;
use Hexa\PluginCore\PluginProvisioning\PluginProvisioner;

/**
 * Plugin Status Monitoring System
 * 
 * Smart, abstract plugin monitoring with easy extensibility.
 * Simply add plugins to the $monitored_plugins array.
 * 
 * @since 8.9.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function hws_require_plugin_inventory_core(): void {
    $files = [
        'Hexa\\PluginCore\\PluginChecks\\PluginCheckDefinition'        => __DIR__ . '/lib/hexa-wordpress-plugin-core/src/PluginChecks/PluginCheckDefinition.php',
        'Hexa\\PluginCore\\PluginChecks\\PluginCheckService'           => __DIR__ . '/lib/hexa-wordpress-plugin-core/src/PluginChecks/PluginCheckService.php',
        'Hexa\\PluginCore\\PluginChecks\\PluginInventoryRenderer'      => __DIR__ . '/lib/hexa-wordpress-plugin-core/src/PluginChecks/PluginInventoryRenderer.php',
        'Hexa\\PluginCore\\PluginChecks\\PluginInventoryAjaxController'=> __DIR__ . '/lib/hexa-wordpress-plugin-core/src/PluginChecks/PluginInventoryAjaxController.php',
        'Hexa\\PluginCore\\PluginChecks\\PluginRecommendationRegistry' => __DIR__ . '/lib/hexa-wordpress-plugin-core/src/PluginChecks/PluginRecommendationRegistry.php',
        'Hexa\\PluginCore\\PluginProvisioning\\PluginProvisioner'      => __DIR__ . '/lib/hexa-wordpress-plugin-core/src/PluginProvisioning/PluginProvisioner.php',
    ];

    foreach ( $files as $class_name => $file ) {
        if ( ! class_exists( $class_name, false ) && is_readable( $file ) ) {
            require_once $file;
        }
    }
}

hws_require_plugin_inventory_core();

// Register AJAX handler for plugin installation (from WordPress.org → install + activate)
add_action( 'wp_ajax_hws_install_plugin', __NAMESPACE__ . '\\ajax_install_plugin' );

// Register AJAX handler for activating an already-installed plugin
add_action( 'wp_ajax_hws_activate_plugin', __NAMESPACE__ . '\\ajax_activate_plugin' );

// Register AJAX handler for installing HWS-owned plugins from GitHub ZIPs.
add_action( 'wp_ajax_hws_install_hws_github_plugin', __NAMESPACE__ . '\\ajax_install_hws_github_plugin' );

function hws_get_hexa_plugin_catalog(): array {
    return [
        'hws-base-tools' => [
            'name'        => 'Hexa Web Systems - Website Base Tool',
            'plugin_file' => 'hws-base-tools/hws-base-tools.php',
            'repo'        => 'mikeyperes/hws-base-tools',
            'description' => 'Base Hexa WordPress tools and shared Core admin structures.',
        ],
        'hexa-pr-wire-distributor' => [
            'name'        => 'Hexa PR Wire Distributor',
            'plugin_file' => 'hexa-pr-wire-distributor/hexa-pr-wire-distributor.php',
            'repo'        => 'mikeyperes/hexa-pr-wire-distributor',
            'description' => 'PR wire distribution workflow plugin.',
        ],
        'smp-publication-integration' => [
            'name'        => 'SMP Publication Integration',
            'plugin_file' => 'smp-publication-integration/smp-publication-integration.php',
            'repo'        => 'mikeyperes/smp-publication-integration',
            'description' => 'Publication integration tools for SMP sites.',
        ],
        'smp-wp-text-to-speech' => [
            'name'        => 'SMP WP Text To Speech',
            'plugin_file' => 'smp-wp-text-to-speech/smp-wp-text-to-speech.php',
            'repo'        => 'mikeyperes/smp-wp-text-to-speech',
            'description' => 'Text-to-speech tooling for SMP publications.',
        ],
        'smp-core-podcast-integration' => [
            'name'        => 'SMP Core Podcast Integration',
            'plugin_file' => 'smp-core-podcast-integration/smp-core-podcast-integration.php',
            'repo'        => 'mikeyperes/smp-core-podcast-integration',
            'description' => 'Podcast integration tools for SMP core workflows.',
        ],
        'smp-verified-profiles' => [
            'name'        => 'SMP Verified Profiles',
            'plugin_file' => 'smp-verified-profiles/smp-verified-profiles.php',
            'repo'        => 'mikeyperes/smp-verified-profiles',
            'description' => 'Verified profile management for SMP sites.',
        ],
        'smp-contributor-network' => [
            'name'        => 'SMP Contributor Network',
            'plugin_file' => 'smp-contributor-network/smp-contributor-network.php',
            'repo'        => 'mikeyperes/smp-contributor-network',
            'description' => 'Contributor network tooling for SMP publications.',
        ],
        'sfpf-person-profile-integration' => [
            'name'        => 'SFPF Person Profile Integration',
            'plugin_file' => 'sfpf-person-profile-integration/sfpf-person-profile-integration.php',
            'repo'        => 'mikeyperes/sfpf-person-profile-integration',
            'description' => 'Person profile integration for SFPF sites.',
        ],
    ];
}

function hws_get_additional_hws_plugins(): array {
    return hws_get_hexa_plugin_catalog();
}

function hws_get_additional_hws_plugin( string $slug ): ?array {
    $plugins = hws_get_additional_hws_plugins();

    return $plugins[ sanitize_key( $slug ) ] ?? null;
}

function hws_find_plugin_file_by_folder( string $slug ): string {
    return PluginProvisioner::find_plugin_file_by_folder( $slug );
}

function hws_check_additional_hws_plugin_status( string $slug ): array {
    return PluginProvisioner::plugin_status_by_folder( $slug );
}

function hws_render_additional_hws_plugin_status( array $status ): string {
    if ( ! $status['installed'] ) {
        return '<span class="status-bad">Not installed</span>';
    }

    $html = $status['active']
        ? '<span class="status-ok">Active</span>'
        : '<span class="status-warn">Installed, inactive</span>';

    if ( ! empty( $status['plugin_file'] ) ) {
        $html .= '<br><code style="font-size:11px;">' . esc_html( $status['plugin_file'] ) . '</code>';
    }

    return $html;
}

function hws_prepare_wp_filesystem() {
    return PluginProvisioner::prepare_filesystem();
}

function hws_cleanup_install_work_dir( string $path ): void {
    PluginProvisioner::cleanup_work_dir( $path, 'hws-github-plugin-' );
}

function hws_normalize_hws_github_plugin_folder( string $slug ) {
    return PluginProvisioner::normalize_github_folder( $slug );
}

function hws_install_hws_github_plugin_package( string $slug, array $plugin ) {
    return PluginProvisioner::install_github_plugin(
        $slug,
        sanitize_text_field( $plugin['repo'] ?? '' ),
        [
            'branch'      => 'main',
            'work_prefix' => 'hws-github-plugin',
            'timeout'     => 60,
        ]
    );
}

function ajax_install_hws_github_plugin() {
    if ( ! current_user_can( 'install_plugins' ) ) {
        wp_send_json_error( 'Unauthorized - you do not have permission to install plugins.' );
    }

    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], HWS_AJAX_NONCE ) ) {
        wp_send_json_error( 'Security check failed.' );
    }

    $slug   = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '';
    $plugin = hws_get_additional_hws_plugin( $slug );

    if ( ! $plugin ) {
        wp_send_json_error( 'Unknown HWS plugin slug.' );
    }

    $normalized = hws_normalize_hws_github_plugin_folder( $slug );
    if ( is_wp_error( $normalized ) ) {
        wp_send_json_error( $normalized->get_error_message() );
    }

    $status      = hws_check_additional_hws_plugin_status( $slug );
    $plugin_file = $status['plugin_file'];
    $message     = '';

    if ( ! $status['installed'] ) {
        $installed = hws_install_hws_github_plugin_package( $slug, $plugin );
        if ( is_wp_error( $installed ) ) {
            wp_send_json_error( $installed->get_error_message() );
        }

        $plugin_file = $installed;
        $message     = 'Installed from GitHub and normalized to folder ' . $slug . '.';
    }

    if ( $plugin_file === '' ) {
        wp_send_json_error( 'Plugin file could not be found after install.' );
    }

    if ( ! is_plugin_active( $plugin_file ) ) {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            wp_send_json_error( 'Installed, but you do not have permission to activate plugins.' );
        }

        $activate_result = activate_plugin( $plugin_file );
        if ( is_wp_error( $activate_result ) ) {
            wp_send_json_error( 'Activation failed: ' . $activate_result->get_error_message() );
        }

        $message = $message ? $message . ' Activated.' : 'Activated.';
    } else {
        $message = $message ?: 'Plugin is already active.';
    }

    $status = hws_check_additional_hws_plugin_status( $slug );

    wp_send_json_success( [
        'message'     => $message,
        'slug'        => $slug,
        'status_html' => hws_render_additional_hws_plugin_status( $status ),
        'button_text' => $status['active'] ? 'Active' : 'Activate',
        'installed'   => $status['installed'],
        'active'      => $status['active'],
        'plugin_file' => $status['plugin_file'],
        'folder'      => $status['folder'],
    ] );
}

/**
 * AJAX handler to install a plugin from WordPress.org
 */
function ajax_install_plugin() {
    // Check permissions
    if ( ! current_user_can( 'install_plugins' ) ) {
        wp_send_json_error( 'Unauthorized - you do not have permission to install plugins.' );
        return;
    }
    
    // Verify nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], HWS_AJAX_NONCE ) ) {
        wp_send_json_error( 'Security check failed.' );
        return;
    }
    
    // Get plugin slug
    $slug = isset( $_POST['slug'] ) ? sanitize_text_field( $_POST['slug'] ) : '';
    
    if ( empty( $slug ) ) {
        wp_send_json_error( 'No plugin slug provided.' );
        return;
    }
    
    $result = PluginProvisioner::install_wordpress_org_plugin( $slug, true );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( 'Installation failed: ' . $result->get_error_message() );
        return;
    }

    wp_send_json_success( [
        'message'   => $result['message'] ?? 'Plugin installed and activated successfully.',
        'activated' => ! empty( $result['activated'] ),
    ]);
}


/**
 * AJAX handler to activate an already-installed plugin.
 *
 * Abstract/reusable: any panel can call this with a plugin_file parameter.
 * Uses the same nonce (HWS_AJAX_NONCE) as install handler for consistency.
 *
 * Expected POST params:
 *   - plugin_file  (string) e.g. 'wordfence/wordfence.php'
 *   - nonce        (string) wp_create_nonce( HWS_AJAX_NONCE )
 *
 * @since 10.8.4
 */
function ajax_activate_plugin() {
    // — Check permissions
    if ( ! current_user_can( 'activate_plugins' ) ) {
        wp_send_json_error( 'Unauthorized — you do not have permission to activate plugins.' );
        return;
    }

    // — Verify nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], HWS_AJAX_NONCE ) ) {
        wp_send_json_error( 'Security check failed.' );
        return;
    }

    // — Get plugin file path (e.g. 'wordfence/wordfence.php')
    $plugin_file = isset( $_POST['plugin_file'] ) ? sanitize_text_field( $_POST['plugin_file'] ) : '';

    if ( empty( $plugin_file ) ) {
        wp_send_json_error( 'No plugin file provided.' );
        return;
    }

    $result = PluginProvisioner::activate_plugin_file( $plugin_file );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( 'Activation failed: ' . $result->get_error_message() );
        return;
    }

    wp_send_json_success( [
        'message'   => $result['message'] ?? 'Plugin activated successfully.',
        'activated' => ! empty( $result['activated'] ),
    ]);
}


/**
 * Get list of monitored plugins
 * 
 * Easy to extend - just add to this array!
 * 
 * Format:
 * 'plugin-folder/plugin-file.php' => [
 *     'name'        => 'Display Name',
 *     'should_be'   => 'active' | 'inactive',  // Expected state
 *     'auto_update' => true | false,           // Should auto-update be enabled?
 *     'download'    => 'url' | 'manual',       // How to get it
 *     'category'    => 'essential' | 'optional', // Importance level
 *     'pro'         => true | false,            // Pro/paid plugin? (won't auto-install)
 * ]
 */
function hws_get_monitored_plugins() {
    return [
        // === OPTIONAL PRO PLUGINS ===
        'advanced-custom-fields-pro/acf.php' => [
            'name'        => 'Advanced Custom Fields Pro',
            'should_be'   => 'active',
            'auto_update' => true,
            'download'    => 'manual',
            'category'    => 'optional',
            'pro'         => true,
        ],
        // === ESSENTIAL ACTIVE (auto-installed by Quick Setup where possible) ===
        'elementor/elementor.php' => [
            'name'        => 'Elementor',
            'should_be'   => 'active',
            'auto_update' => true,
            'download'    => 'https://wordpress.org/plugins/elementor/',
            'category'    => 'essential',
            'pro'         => false,
        ],
        'elementor-pro/elementor-pro.php' => [
            'name'        => 'Elementor Pro',
            'should_be'   => 'active',
            'auto_update' => true,
            'download'    => 'manual',
            'category'    => 'essential',
            'pro'         => true,
        ],
        'classic-editor/classic-editor.php' => [
            'name'        => 'Classic Editor',
            'should_be'   => 'active',
            'auto_update' => true,
            'download'    => 'https://wordpress.org/plugins/classic-editor/',
            'category'    => 'essential',
            'pro'         => false,
        ],
        'wordfence/wordfence.php' => [
            'name'        => 'Wordfence',
            'should_be'   => 'active',
            'auto_update' => false,  // — Security plugin: review updates manually
            'download'    => 'https://wordpress.org/plugins/wordfence/',
            'category'    => 'essential',
            'pro'         => false,
        ],
        'wp-mail-smtp/wp_mail_smtp.php' => [
            'name'        => 'WP Mail SMTP',
            'should_be'   => 'active',
            'auto_update' => true,
            'download'    => 'https://wordpress.org/plugins/wp-mail-smtp/',
            'category'    => 'essential',
            'pro'         => false,
        ],
        'seo-by-rank-math/rank-math.php' => [
            'name'        => 'Rank Math SEO',
            'should_be'   => 'active',
            'auto_update' => true,
            'download'    => 'https://wordpress.org/plugins/seo-by-rank-math/',
            'category'    => 'essential',
            'pro'         => false,
        ],
        'wp-user-avatars/wp-user-avatars.php' => [
            'name'        => 'WP User Avatars',
            'should_be'   => 'active',
            'auto_update' => true,
            'download'    => 'https://wordpress.org/plugins/wp-user-avatars/',
            'category'    => 'essential',
            'pro'         => false,
        ],
        'litespeed-cache/litespeed-cache.php' => [
            'name'        => 'LiteSpeed Cache',
            'should_be'   => 'active',
            'auto_update' => true,
            'download'    => 'https://wordpress.org/plugins/litespeed-cache/',
            'category'    => 'essential',
            'pro'         => false,
        ],

        // === OPTIONAL ===
        'wp-optimize/wp-optimize.php' => [
            'name'        => 'WP Optimize',
            'should_be'   => 'inactive',
            'auto_update' => true,
            'download'    => 'https://wordpress.org/plugins/wp-optimize/',
            'category'    => 'optional',
            'pro'         => false,
        ],
        'regenerate-thumbnails/regenerate-thumbnails.php' => [
            'name'        => 'Regenerate Thumbnails',
            'should_be'   => 'inactive',
            'auto_update' => true,
            'download'    => 'https://wordpress.org/plugins/regenerate-thumbnails/',
            'category'    => 'optional',
            'pro'         => false,
        ],
    ];
}


/**
 * Get red flag plugins that should NOT be installed
 */
function hws_get_red_flag_plugins() {
    return [
        'wp-file-manager/file-manager.php' => [
            'name'   => 'WP File Manager',
            'reason' => 'Critical security vulnerability - allows remote file access',
        ],
        'duplicator/duplicator.php' => [
            'name'   => 'Duplicator',
            'reason' => 'Often left installed after migration - remove when done',
        ],
    ];
}


/**
 * Check plugin status
 * 
 * @param string $plugin_path Plugin path (folder/file.php)
 * @return array Status array with installed, active, auto_update keys
 */
function hws_check_plugin_status( $plugin_path ) {
    return PluginProvisioner::plugin_status_by_file( (string) $plugin_path );
}

function hws_plugin_inventory_slug_from_download( string $download ): string {
    if ( preg_match( '#wordpress\.org/plugins/([^/]+)/?#', $download, $matches ) ) {
        return sanitize_key( $matches[1] );
    }

    return '';
}

function hws_get_monitored_plugin_definitions(): array {
    $definitions = [];

    foreach ( hws_get_monitored_plugins() as $plugin_file => $config ) {
        $download    = (string) ( $config['download'] ?? '' );
        $wp_org_slug = hws_plugin_inventory_slug_from_download( $download );
        $is_pro      = ! empty( $config['pro'] );
        $is_manual   = 'manual' === $download || '' === $download;
        $recommended = 'essential' === (string) ( $config['category'] ?? 'essential' );
        $source      = $is_pro ? 'pro' : ( $wp_org_slug ? 'wordpress_org' : 'manual' );
        $active_expected = 'active' === (string) ( $config['should_be'] ?? 'active' );

        $definitions[] = [
            'id'                   => str_replace( '/', '-', (string) $plugin_file ),
            'name'                 => (string) ( $config['name'] ?? $plugin_file ),
            'plugin_file'          => (string) $plugin_file,
            'slug'                 => dirname( (string) $plugin_file ),
            'source'               => $source,
            'wp_org_slug'          => $wp_org_slug,
            'download_url'         => $is_manual ? admin_url( 'plugin-install.php?tab=upload' ) : $download,
            'download_label'       => $is_manual ? 'Upload plugin' : 'Download plugin',
            'required'             => $recommended,
            'recommended'          => $recommended,
            'auto_update_expected' => ! empty( $config['auto_update'] ),
            'checks'               => [
                'installed'   => true,
                'active'      => $active_expected,
                'up_to_date'  => false,
                'auto_update' => true,
            ],
            'notes'                => sprintf(
                '%s%s. Expected state: %s.',
                ucfirst( (string) ( $config['category'] ?? 'essential' ) ),
                $is_pro ? ' pro plugin' : ' plugin',
                $active_expected ? 'active' : 'inactive'
            ),
        ];
    }

    return $definitions;
}

function hws_get_hws_plugin_library_definitions(): array {
    $definitions = [];

    foreach ( hws_get_additional_hws_plugins() as $slug => $plugin ) {
        $slug          = sanitize_key( (string) $slug );
        $definitions[] = [
            'id'            => $slug,
            'name'          => (string) ( $plugin['name'] ?? $slug ),
            'plugin_file'   => (string) ( $plugin['plugin_file'] ?? '' ),
            'slug'          => $slug,
            'source'        => 'github',
            'github_repo'   => (string) ( $plugin['repo'] ?? '' ),
            'github_branch' => 'main',
            'download_url'  => 'https://github.com/' . (string) ( $plugin['repo'] ?? '' ),
            'download_label'=> 'Open GitHub',
            'required'      => false,
            'recommended'   => true,
            'checks'        => [
                'installed'   => true,
                'active'      => true,
                'up_to_date'  => false,
                'auto_update' => false,
            ],
            'notes'         => (string) ( $plugin['description'] ?? '' ),
        ];
    }

    return $definitions;
}

function hws_get_all_recommended_plugin_definitions(): array {
    return array_values(
        array_merge(
            hws_get_hws_plugin_library_definitions(),
            hws_get_monitored_plugin_definitions()
        )
    );
}

function hws_register_hexa_plugin_recommendation_providers(): void {
    static $registered = false;

    if ( $registered || ! class_exists( PluginRecommendationRegistry::class ) ) {
        return;
    }

    $registered = true;

    foreach ( hws_get_hexa_plugin_catalog() as $slug => $plugin ) {
        PluginRecommendationRegistry::register_hexa_plugin(
            [
                'id'          => (string) $slug,
                'name'        => (string) ( $plugin['name'] ?? $slug ),
                'plugin_file' => (string) ( $plugin['plugin_file'] ?? '' ),
                'repo'        => (string) ( $plugin['repo'] ?? '' ),
                'notes'       => (string) ( $plugin['description'] ?? '' ),
            ]
        );
    }

    PluginRecommendationRegistry::register_hexa_plugin(
        [
            'id'          => 'hws-base-tools',
            'name'        => 'Hexa Web Systems - Website Base Tool',
            'plugin_file' => 'hws-base-tools/hws-base-tools.php',
            'repo'        => 'mikeyperes/hws-base-tools',
            'callback'    => __NAMESPACE__ . '\\hws_get_all_recommended_plugin_definitions',
        ]
    );

    if ( class_exists( 'smp_publication_integration\\Support\\PluginInventory' ) ) {
        PluginRecommendationRegistry::register_hexa_plugin(
            [
                'id'          => 'smp-publication-integration',
                'name'        => 'SMP Publication Integration',
                'plugin_file' => 'smp-publication-integration/smp-publication-integration.php',
                'repo'        => 'mikeyperes/smp-publication-integration',
                'callback'    => [ 'smp_publication_integration\\Support\\PluginInventory', 'recommended_definitions' ],
            ]
        );
    }
}

add_action( 'admin_init', __NAMESPACE__ . '\\hws_register_hexa_plugin_recommendation_providers', 1 );

function hws_get_unrecommended_plugin_definitions(): array {
    hws_register_hexa_plugin_recommendation_providers();

    return class_exists( PluginRecommendationRegistry::class )
        ? PluginRecommendationRegistry::get_installed_not_recommended_definitions( false )
        : [];
}

function hws_register_plugin_inventory_ajax(): void {
    static $registered = false;

    if ( $registered ) {
        return;
    }

    $registered = true;
    hws_register_hexa_plugin_recommendation_providers();

    ( new PluginInventoryAjaxController(
        hws_get_hws_plugin_library_definitions(),
        [
            'nonce_action'  => HWS_AJAX_NONCE,
            'nonce_field'   => 'nonce',
            'action_prefix' => 'hws_plugin_library',
            'renderer_args' => hws_get_hws_plugin_library_renderer_args(),
        ]
    ) )->register();

    ( new PluginInventoryAjaxController(
        hws_get_monitored_plugin_definitions(),
        [
            'nonce_action'  => HWS_AJAX_NONCE,
            'nonce_field'   => 'nonce',
            'action_prefix' => 'hws_plugin_status',
            'renderer_args' => hws_get_monitored_plugin_renderer_args(),
        ]
    ) )->register();

    ( new PluginInventoryAjaxController(
        hws_get_unrecommended_plugin_definitions(),
        [
            'nonce_action'  => HWS_AJAX_NONCE,
            'nonce_field'   => 'nonce',
            'action_prefix' => 'hws_unrecommended_plugins',
            'renderer_args' => hws_get_unrecommended_plugin_renderer_args(),
        ]
    ) )->register();

}

add_action( 'admin_init', __NAMESPACE__ . '\\hws_register_plugin_inventory_ajax' );

function hws_get_hws_plugin_library_renderer_args(): array {
    return [
        'title'            => 'HWS Plugin Library',
        'description'      => 'Install additional HWS plugins directly from GitHub. ZIP folders are normalized from repo-main to the correct WordPress plugin slug before activation.',
        'action_prefix'    => 'hws_plugin_library',
        'nonce'            => wp_create_nonce( HWS_AJAX_NONCE ),
        'nonce_field'      => 'nonce',
        'persist_key'      => 'hws-plugin-library',
        'open'             => true,
        'show_install_all' => true,
        'columns'          => [
            'auto_update' => false,
            'version'     => true,
            'source'      => true,
        ],
    ];
}

function hws_get_monitored_plugin_renderer_args(): array {
    return [
        'title'            => 'Plugin Status',
        'description'      => 'Recommended and optional plugin health for this site. Missing WordPress.org and GitHub plugins can be installed from this table without a page refresh.',
        'action_prefix'    => 'hws_plugin_status',
        'nonce'            => wp_create_nonce( HWS_AJAX_NONCE ),
        'nonce_field'      => 'nonce',
        'persist_key'      => 'hws-plugin-status',
        'open'             => true,
        'show_install_all' => true,
        'columns'          => [
            'auto_update' => true,
            'version'     => true,
            'source'      => true,
        ],
    ];
}

function hws_get_unrecommended_plugin_renderer_args(): array {
    return [
        'title'            => 'Installed Plugins Not Recommended by Hexa',
        'description'      => 'Installed plugins that are not recommended by any registered Hexa plugin. These rows are Core-generated and expose AJAX Deactivate and Delete actions.',
        'action_prefix'    => 'hws_unrecommended_plugins',
        'nonce'            => wp_create_nonce( HWS_AJAX_NONCE ),
        'nonce_field'      => 'nonce',
        'persist_key'      => 'hws-unrecommended-plugins',
        'open'             => true,
        'empty_text'       => 'Every installed plugin is recommended by at least one registered Hexa plugin.',
        'show_install_all' => false,
        'hide_compliant_forbidden' => true,
        'show_unwanted'    => true,
        'columns'          => [
            'auto_update' => true,
            'version'     => true,
            'source'      => true,
        ],
    ];
}

function hws_render_monitored_plugins_panel(): void {
    echo ( new PluginInventoryRenderer() )->render(
        hws_get_monitored_plugin_definitions(),
        hws_get_monitored_plugin_renderer_args()
    ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function hws_render_unrecommended_plugins_panel(): void {
    echo ( new PluginInventoryRenderer() )->render(
        hws_get_unrecommended_plugin_definitions(),
        hws_get_unrecommended_plugin_renderer_args()
    ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function hws_render_additional_hws_plugins_panel(): void {
    echo ( new PluginInventoryRenderer() )->render(
        hws_get_hws_plugin_library_definitions(),
        hws_get_hws_plugin_library_renderer_args()
    ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}


/**
 * Render Plugins Tab
 */
function render_tab_plugins() {
    $monitored = hws_get_monitored_plugins();
    $red_flags = hws_get_red_flag_plugins();
    
    // Get update info
    $plugin_updates = get_plugin_updates();
    $theme_updates = get_theme_updates();
    $core_updates = get_core_updates();
    $core_update_available = ! empty( $core_updates ) && isset( $core_updates[0]->response ) && $core_updates[0]->response === 'upgrade';
    ?>
    
    <!-- Red Flag Alerts -->
    <?php
    $found_red_flags = [];
    foreach ( $red_flags as $plugin_path => $info ) {
        $status = hws_check_plugin_status( $plugin_path );
        if ( $status['installed'] ) {
            $found_red_flags[ $plugin_path ] = $info;
        }
    }
    
    if ( ! empty( $found_red_flags ) ) :
    ?>
    <div class="hws-red-flag">
        <h4>Red Flag Plugins - Remove These Plugins</h4>
        <?php foreach ( $found_red_flags as $path => $info ) : ?>
            <p>
                <strong><?php echo esc_html( $info['name'] ); ?></strong><br>
                <span style="color: #666;"><?php echo esc_html( $info['reason'] ); ?></span><br>
                <a href="<?php echo wp_nonce_url( admin_url( 'plugins.php?action=delete-selected&checked[]=' . $path ), 'bulk-plugins' ); ?>" class="hws-btn hws-btn-danger" style="margin-top: 5px;">Delete Plugin</a>
            </p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- Updates Center -->
    <div class="hws-panel">
        <div class="hws-panel-header">Updates Center</div>
        <div class="hws-panel-body">
            <div class="hws-status-grid">
                <div class="hws-status-card <?php echo $core_update_available ? 'bad' : 'good'; ?>">
                    <div class="value"><?php global $wp_version; echo $wp_version; ?></div>
                    <div class="label">WordPress <?php echo $core_update_available ? '(Update!)' : ''; ?></div>
                </div>
                <div class="hws-status-card <?php echo count( $plugin_updates ) > 0 ? 'warn' : 'good'; ?>">
                    <div class="value"><?php echo count( $plugin_updates ); ?></div>
                    <div class="label">Plugin Updates</div>
                </div>
                <div class="hws-status-card <?php echo count( $theme_updates ) > 0 ? 'warn' : 'good'; ?>">
                    <div class="value"><?php echo count( $theme_updates ); ?></div>
                    <div class="label">Theme Updates</div>
                </div>
            </div>
            
            <?php if ( count( $plugin_updates ) > 0 || count( $theme_updates ) > 0 || $core_update_available ) : ?>
            <a href="<?php echo admin_url( 'update-core.php' ); ?>" class="hws-btn" target="_blank">Go to Updates Page</a>
            <a href="<?php echo admin_url( 'update-core.php?force-check=1' ); ?>" class="hws-btn hws-btn-secondary" target="_blank">Force Update Check</a>
            <?php else : ?>
            <p class="status-ok">Everything is up to date.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php
    hws_render_additional_hws_plugins_panel();
    hws_render_monitored_plugins_panel();
    hws_render_unrecommended_plugins_panel();

    if ( function_exists( __NAMESPACE__ . '\\display_settings_theme_checks' ) ) {
        display_settings_theme_checks();
    }
    ?>

    <script>
    jQuery(document).ready(function($) {
        $('#enable-plugin-auto-updates').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text('Enabling...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hws_enable_all_auto_updates',
                    nonce: '<?php echo wp_create_nonce( HWS_AJAX_NONCE ); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $btn.text(response.data.message || 'Auto-updates enabled');
                        return;
                    }

                    $btn.prop('disabled', false).text('Enable Auto-Updates for All');
                    alert('Error: ' + (response.data || 'Unknown error'));
                },
                error: function(xhr, status, error) {
                    $btn.prop('disabled', false).text('Enable Auto-Updates for All');
                    alert('AJAX request failed: ' + status + ' - ' + error + '\n' + (xhr.responseText || '').substring(0, 200));
                }
            });
        });
    });
    </script>
    <?php
    return;
}


/**
 * Force update check AJAX handler
 */
function hws_ct_force_update_check() {
    wp_clean_update_cache();
    wp_update_plugins();
    wp_update_themes();

    $plugin_updates = get_plugin_updates();
    $last_checked = date( 'Y-m-d H:i:s' );
    $plugins_list = [];

    foreach ( $plugin_updates as $plugin_file => $plugin_data ) {
        $plugins_list[] = $plugin_data->Name;
    }

    wp_send_json( [
        'last_checked'         => $last_checked,
        'plugins_with_updates' => count( $plugin_updates ),
        'plugins_list'         => $plugins_list,
    ] );
}
add_action( 'wp_ajax_hws_base_tools_force_update_check', __NAMESPACE__ . '\\hws_ct_force_update_check' );
