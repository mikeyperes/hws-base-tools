<?php

namespace hws_base_tools;

use HWS\BaseTools\AdminDashboard\DashboardRegistry;
use HWS\BaseTools\AcfFields\AcfModule;
use HWS\BaseTools\PluginRuntime\PluginMetadata;
use HWS\BaseTools\PluginRuntime\RequestContext;
use HWS\BaseTools\PluginRuntime\CoreIntegration;
use HWS\BaseTools\Security\RemoteActionPolicy;
use HWS\BaseTools\FrontendContent\FeatureLoader;
use HWS\BaseTools\Maintenance\ScheduledTaskLoader;
use HWS\BaseTools\TeamMembers\TeamMemberFeature;

/**
 * Legacy bootstrap.
 *
 * The canonical WordPress plugin entry is hws-base-tools.php. This file stays
 * loadable so existing installs active as hws-base-tools/initialization.php can
 * migrate their stored plugin basename without a deactivate/reactivate cycle.
 */

// Ensure this file is being included by a parent file
defined('ABSPATH') or die('No script kiddies please!');

if ( ! defined( 'HWS_BASE_TOOLS_CANONICAL_PLUGIN_FILE' ) ) {
    define( 'HWS_BASE_TOOLS_CANONICAL_PLUGIN_FILE', HWS_BASE_TOOLS_DIR . '/hws-base-tools.php' );
}

if ( ! defined( 'HWS_BASE_TOOLS_LEGACY_PLUGIN_FILE' ) ) {
    define( 'HWS_BASE_TOOLS_LEGACY_PLUGIN_FILE', HWS_BASE_TOOLS_DIR . '/initialization.php' );
}

function hws_migrate_active_plugin_basename_to_canonical(): void {
    if ( ! function_exists( 'plugin_basename' ) ) {
        return;
    }

    $legacy_basename    = plugin_basename( HWS_BASE_TOOLS_LEGACY_PLUGIN_FILE );
    $canonical_basename = plugin_basename( HWS_BASE_TOOLS_CANONICAL_PLUGIN_FILE );

    if ( $legacy_basename === $canonical_basename ) {
        return;
    }

    $active_plugins = (array) get_option( 'active_plugins', [] );
    $updated        = [];
    $changed        = false;

    foreach ( $active_plugins as $plugin ) {
        if ( $plugin === $legacy_basename ) {
            if ( ! in_array( $canonical_basename, $updated, true ) ) {
                $updated[] = $canonical_basename;
            }
            $changed = true;
            continue;
        }

        if ( $plugin === $canonical_basename && in_array( $canonical_basename, $updated, true ) ) {
            $changed = true;
            continue;
        }

        $updated[] = $plugin;
    }

    if ( $changed ) {
        update_option( 'active_plugins', array_values( $updated ) );
    }

    if ( is_multisite() ) {
        $network_active = (array) get_site_option( 'active_sitewide_plugins', [] );
        if ( isset( $network_active[ $legacy_basename ] ) ) {
            $activated_at = $network_active[ $legacy_basename ];
            unset( $network_active[ $legacy_basename ] );
            $network_active[ $canonical_basename ] = $network_active[ $canonical_basename ] ?? $activated_at;
            update_site_option( 'active_sitewide_plugins', $network_active );
        }
    }
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\hws_migrate_active_plugin_basename_to_canonical', 1 );

require_once HWS_BASE_TOOLS_DIR . '/runtime-options.php';

require_once HWS_BASE_TOOLS_DIR . '/safe-wrappers.php';
require_once HWS_BASE_TOOLS_DIR . '/src/LegacyCompatibility/runtime-functions.php';
ScheduledTaskLoader::load_for_request();

add_action( 'plugins_loaded', [ CoreIntegration::class, 'boot' ], 20 );

if ( 'yes' === (string) get_option( 'hws_sitemaps_litespeed_nocache_enabled', 'no' ) ) {
    require_once HWS_BASE_TOOLS_DIR . '/settings-dashboard-sitemaps.php';
}

function hws_get_structured_plugin() {
    return CoreIntegration::bootstrap();
}

function hws_boot_structured_admin_modules() {
    static $booted = false;

    if ( $booted ) {
        return;
    }

    CoreIntegration::boot();

    $booted = true;
}

// CRITICAL: Load shortcodes FIRST at plugin load time (before any guards)
// This ensures shortcodes work with Elementor, Gutenberg, and all page builders
require_once HWS_BASE_TOOLS_DIR . '/snippet-website-settings-functionality.php';
require_once HWS_BASE_TOOLS_DIR . '/src/FrontendContent/search-display.php';

// === Guard: don't bootstrap ADMIN features during Elementor's internal AJAX ===
if ( defined('DOING_AJAX') && DOING_AJAX ) {
    $ajax_action = isset($_REQUEST['action']) ? sanitize_text_field($_REQUEST['action']) : '';
    if ( $ajax_action === 'elementor_ajax' ) {
        // Elementor sends a JSON 'actions' payload (often includes get_widgets_config)
        // Shortcodes already registered above - just skip admin features
        return;
    }
}



require_once HWS_BASE_TOOLS_DIR . '/snippet-login-mask.php';
require_once HWS_BASE_TOOLS_DIR . '/snippet-base-features.php';

// — Update Center: loaded early so secret URL handlers register on init:1 for frontend access
// — Dashboard UI rendering is gated inside display_settings_update_center() called only in admin
if ( RequestContext::is_dashboard_page() || RequestContext::is_dashboard_ajax() || RemoteActionPolicy::option_enabled( 'hws_update_urls_enabled' ) ) {
    require_once HWS_BASE_TOOLS_DIR . '/settings-dashboard-update-center.php';
}

//if (!is_admin()) return;

class Config {
    public static $settings_page_name = "HWS Base Tools";
    public static $settings_page_capability = "manage_options";
    public static $settings_page_slug = PluginMetadata::ADMIN_PAGE_SLUG;
    public static $settings_page_display_title = "HWS Base Tools";

    public static $plugin_name = PluginMetadata::NAME;
    public static $plugin_starter_file = PluginMetadata::MAIN_FILE;
    public static $plugin_slug = PluginMetadata::ADMIN_PAGE_SLUG;

    // Plugin identification - use these everywhere, never hardcode
    public static $plugin_folder_name = PluginMetadata::SLUG;
    public static $github_repo = PluginMetadata::GITHUB_REPOSITORY;
    public static $github_branch = PluginMetadata::GITHUB_BRANCH;

    /**
     * Get the currently-running plugin basename (folder/file.php).
     * This may differ from the canonical folder when a site has been updated
     * from a raw GitHub package such as hws-base-tools-main.
     */
    public static function get_plugin_basename() {
        return plugin_basename( HWS_BASE_TOOLS_CANONICAL_PLUGIN_FILE );
    }

    /**
     * Get the currently-running plugin folder name.
     */
    public static function get_runtime_plugin_folder_name() {
        return dirname( self::get_plugin_basename() );
    }

    /**
     * Get the canonical plugin basename (folder/file.php).
     */
    public static function get_canonical_plugin_basename() {
        return self::$plugin_folder_name . '/' . self::$plugin_starter_file;
    }



public static function get_github_config() {
    // Ensure we can read plugin headers
    if ( ! function_exists( 'get_plugin_data' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    // Pull header info from the canonical plugin file.
    $plugin_data = get_plugin_data( HWS_BASE_TOOLS_CANONICAL_PLUGIN_FILE );

    // Build and return the updater config
    return [
        // 1) Plugin’s WP slug (folder/file path under wp‑content/plugins)
        'slug'               => self::get_plugin_basename(),

        // 2) Folder name on disk
        'proper_folder_name' => self::$plugin_folder_name,

        // 3) GitHub endpoints & download URL
        'api_url'            => 'https://api.github.com/repos/' . PluginMetadata::GITHUB_REPOSITORY,
        'raw_url'            => 'https://raw.githubusercontent.com/' . PluginMetadata::GITHUB_REPOSITORY . '/' . PluginMetadata::GITHUB_BRANCH,
        'github_url'         => 'https://github.com/' . PluginMetadata::GITHUB_REPOSITORY,
        'zip_url'            => 'https://github.com/' . PluginMetadata::GITHUB_REPOSITORY . '/archive/refs/heads/' . PluginMetadata::GITHUB_BRANCH . '.zip',

        // 4) HTTP settings
        'sslverify'          => true,
        'access_token'       => '',

        // 5) WP compatibility
        'requires'           => PluginMetadata::REQUIRES_WORDPRESS,
        'requires_php'       => PluginMetadata::REQUIRES_PHP,
        'tested'             => PluginMetadata::TESTED_WORDPRESS,
        'readme'             => 'README.md',

        // 6) Which file to read “Version:” from
        'plugin_starter_file'=> basename( HWS_BASE_TOOLS_CANONICAL_PLUGIN_FILE ),

        // 7) Metadata pulled straight from the plugin header
        'plugin_name'        => $plugin_data['Name'],
        'version'            => $plugin_data['Version'],
        'author'             => $plugin_data['Author'],
        'homepage'           => $plugin_data['PluginURI'],
        'description'        => $plugin_data['Description'],
    ];
}
}

function hws_is_current_plugin_upgrader_target( array $hook_extra ): bool {
    $targets = array_unique(
        array_filter(
            [
                Config::get_plugin_basename(),
                Config::get_canonical_plugin_basename(),
                function_exists( 'plugin_basename' ) ? plugin_basename( HWS_BASE_TOOLS_LEGACY_PLUGIN_FILE ) : '',
            ]
        )
    );

    $requested = [];

    if ( ! empty( $hook_extra['plugin'] ) && is_string( $hook_extra['plugin'] ) ) {
        $requested[] = $hook_extra['plugin'];
    }

    if ( ! empty( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) ) {
        foreach ( $hook_extra['plugins'] as $plugin ) {
            if ( is_string( $plugin ) ) {
                $requested[] = $plugin;
            }
        }
    }

    foreach ( $requested as $plugin ) {
        if ( in_array( $plugin, $targets, true ) ) {
            return true;
        }
    }

    return false;
}

function hws_delete_path_recursive( string $path ): bool {
    if ( ! file_exists( $path ) && ! is_link( $path ) ) {
        return true;
    }

    if ( is_link( $path ) || is_file( $path ) ) {
        return @unlink( $path );
    }

    if ( ! is_dir( $path ) ) {
        return false;
    }

    $items = array_diff( (array) scandir( $path ), [ '.', '..' ] );
    foreach ( $items as $item ) {
        if ( ! hws_delete_path_recursive( $path . '/' . $item ) ) {
            return false;
        }
    }

    return @rmdir( $path );
}

function hws_purge_vendored_core_vcs_metadata(): true|\WP_Error {
    $core_root = HWS_BASE_TOOLS_DIR . '/lib/hexa-wordpress-plugin-core';

    if ( ! is_dir( $core_root ) ) {
        return true;
    }

    if (
        class_exists( '\\Hexa\\PluginCore\\PluginUpdates\\UpdaterFilesystem' )
        && method_exists( '\\Hexa\\PluginCore\\PluginUpdates\\UpdaterFilesystem', 'purge_ignored_package_paths' )
    ) {
        $removed = \Hexa\PluginCore\PluginUpdates\UpdaterFilesystem::purge_ignored_package_paths( $core_root, true );

        return is_wp_error( $removed ) ? $removed : true;
    }

    $failed = [];
    foreach ( [ '.git', '.svn', '.hg', '.bzr' ] as $directory ) {
        $path = $core_root . '/' . $directory;
        if ( file_exists( $path ) && ! hws_delete_path_recursive( $path ) ) {
            $failed[] = $path;
        }
    }

    foreach ( [ '.DS_Store', 'Thumbs.db' ] as $file ) {
        $path = $core_root . '/' . $file;
        if ( file_exists( $path ) && ! @unlink( $path ) ) {
            $failed[] = $path;
        }
    }

    if ( $failed ) {
        return new \WP_Error(
            'hws_base_tools_vendored_core_metadata_locked',
            'HWS Base Tools cannot update cleanly because the vendored Hexa WordPress Plugin Core contains locked VCS metadata: ' . implode( ', ', $failed ) . '. Fix ownership/permissions, then run the WordPress update again.'
        );
    }

    return true;
}

function hws_preflight_native_plugin_update( $response, array $hook_extra ) {
    if ( ! hws_is_current_plugin_upgrader_target( $hook_extra ) ) {
        return $response;
    }

    $purged = hws_purge_vendored_core_vcs_metadata();

    return is_wp_error( $purged ) ? $purged : $response;
}
add_filter( 'upgrader_pre_install', __NAMESPACE__ . '\\hws_preflight_native_plugin_update', 9, 2 );

function hws_request_value( string $key ): string {
    return RequestContext::request_value( $key );
}

function hws_is_dashboard_ajax_request(): bool {
    return RequestContext::is_dashboard_ajax();
}

function hws_is_dashboard_request(): bool {
    return RequestContext::is_dashboard_page() || RequestContext::is_dashboard_ajax();
}

function hws_load_dashboard_files(): void {
    static $loaded = false;

    if ( $loaded ) {
        return;
    }

    require_once HWS_BASE_TOOLS_DIR . '/helper.php';
    require_once HWS_BASE_TOOLS_DIR . '/generic-functions.php';
    require_once HWS_BASE_TOOLS_DIR . '/safe-wrappers.php';
    hws_boot_structured_admin_modules();

    require_once HWS_BASE_TOOLS_DIR . '/settings-dashboard.php';

    $registry = DashboardRegistry::instance();
    $action   = RequestContext::ajax_action();
    $tab      = RequestContext::dashboard_tab();

    $registry->load_ajax_action( $action );

    if ( 'hws_load_dashboard_tab' === $action || RequestContext::is_dashboard_page() ) {
        $registry->load_tab( $registry->normalize( $tab ) );
    }

    $loaded = true;
}

function hws_render_wp_admin_settings_page(): void {
    hws_load_dashboard_files();

    if ( function_exists( __NAMESPACE__ . '\\display_wp_admin_settings_page' ) ) {
        display_wp_admin_settings_page();
    }
}

function hws_add_wp_admin_settings_page(): void {
    add_options_page(
        Config::$settings_page_name,
        Config::$settings_page_name,
        Config::$settings_page_capability,
        Config::$settings_page_slug,
        __NAMESPACE__ . '\\hws_render_wp_admin_settings_page'
    );
}
add_action( 'admin_menu', __NAMESPACE__ . '\\hws_add_wp_admin_settings_page' );

function hws_get_hexa_plugin_core_updater_config(): \Hexa\PluginCore\PluginUpdates\UpdaterConfig {
    static $config = null;

    if ( $config instanceof \Hexa\PluginCore\PluginUpdates\UpdaterConfig ) {
        return $config;
    }

    $config = \Hexa\PluginCore\PluginUpdates\UpdaterConfig::from_plugin_file(
        HWS_BASE_TOOLS_CANONICAL_PLUGIN_FILE,
        'https://github.com/' . Config::$github_repo,
        [
            'plugin_slug'               => Config::$plugin_folder_name,
            'proper_folder_name'        => Config::$plugin_folder_name,
            'runtime_folder_name'       => Config::get_runtime_plugin_folder_name(),
            'plugin_basename'           => Config::get_plugin_basename(),
            'canonical_plugin_basename' => Config::get_canonical_plugin_basename(),
            'plugin_starter_file'       => Config::$plugin_starter_file,
            'github_branch'             => Config::$github_branch,
            'requires'                  => PluginMetadata::REQUIRES_WORDPRESS,
            'requires_php'              => PluginMetadata::REQUIRES_PHP,
            'tested'                    => PluginMetadata::TESTED_WORDPRESS,
            'nonce_action'              => 'hws_base_tools_ajax_nonce',
            'nonce_param'               => 'nonce',
            'ajax_action_prefix'        => 'hws_base_tools_core_updater',
            'progress_key'              => 'hws_base_tools_core_update_progress',
        ]
    );

    return $config;
}

function hws_get_hexa_plugin_core_package_config(): \Hexa\PluginCore\CorePackageUpdates\CorePackageConfig {
    static $config = null;

    if ( $config instanceof \Hexa\PluginCore\CorePackageUpdates\CorePackageConfig ) {
        return $config;
    }

    $config = \Hexa\PluginCore\CorePackageUpdates\CorePackageConfig::from_core_root(
        HWS_BASE_TOOLS_DIR . '/lib/hexa-wordpress-plugin-core',
        [
            'github_repo'        => 'mikeyperes/hexa-wordpress-plugin-core',
            'github_branch'      => 'main',
            'nonce_action'       => 'hws_base_tools_ajax_nonce',
            'nonce_param'        => 'nonce',
            'ajax_action_prefix' => 'hws_base_tools_core_package',
            'cache_key'          => 'hws_base_tools_hexa_plugin_core_package',
        ]
    );

    return $config;
}





// Always loaded on every admin page:
add_action( 'admin_init', function() {
    // Only remove the shutdown hook on our settings page:
    if ( isset( $_GET['page'] ) && $_GET['page'] === Config::$settings_page_slug ) {
        remove_action( 'shutdown', 'wp_ob_end_flush_all', 1 );
    }
});



// Generic functions import
if ( ! function_exists( __NAMESPACE__ . '\\hws_is_acf_available' ) ) {
    function hws_is_acf_available() {
        return function_exists( 'acf' )
            || function_exists( 'acf_add_local_field_group' )
            || class_exists( 'ACF' );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\get_field' ) ) {
    function get_field( ...$args ) {
        return function_exists( 'get_field' )
            ? \get_field( ...$args )
            : null;
    }
}

// Define global variables
//global $api_url, $plugin_github_url, $plugin_zip_url, $wordpress_version_tested, $plugin_name, $github_access_token, $author_name, $author_uri, $plugin_uri, $plugin_version;

$plugin_name = PluginMetadata::NAME;
$plugin_description = "Basic tools for optimization, performance, and debugging on Hexa based web systems.";
$author_name = "Michael Peres";
$plugin_uri = "https://github.com/mikeyperes/hws-base-tools";
$plugin_version = PluginMetadata::VERSION;
$author_uri = "https://michaelperes.com";
$api_url = 'https://api.github.com/repos/' . PluginMetadata::GITHUB_REPOSITORY;
$plugin_github_url = 'https://github.com/' . PluginMetadata::GITHUB_REPOSITORY;
$plugin_zip_url = $plugin_github_url . '/archive/refs/heads/' . PluginMetadata::GITHUB_BRANCH . '.zip';
$wordpress_version_tested = PluginMetadata::TESTED_WORDPRESS;
$github_access_token = ''; // Leave empty if not required for private repositories





/**
 * Boot the abstract Hexa Plugin Core updater early enough for wp-admin, AJAX, cron, and WP-CLI.
 * WordPress core update checks can run outside admin_init, so loading here
 * keeps the plugin visible to the native update system.
 */
function hws_should_boot_hexa_plugin_core_updater(): bool {
    if ( is_admin() ) {
        return true;
    }

    if ( wp_doing_ajax() || wp_doing_cron() ) {
        return true;
    }

    return defined( 'WP_CLI' ) && WP_CLI;
}

add_action( 'plugins_loaded', function() {
    if ( ! hws_should_boot_hexa_plugin_core_updater() ) {
        return;
    }

    $updater_config = hws_get_hexa_plugin_core_updater_config();

    ( new \Hexa\PluginCore\PluginUpdates\GitHubPluginUpdater( $updater_config ) )->register();
    ( new \Hexa\PluginCore\PluginUpdates\UpdaterAjaxController( $updater_config ) )->register();
    ( new \Hexa\PluginCore\CorePackageUpdates\CorePackageAjaxController( hws_get_hexa_plugin_core_package_config() ) )->register();
}, 20 );





if ( is_admin() ) {
    add_action( 'admin_notices', function() {
        if ( hws_is_acf_available() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        echo '<div class="notice notice-warning"><p><strong>HWS Base Tools:</strong> ACF is not active. Core tools remain available; ACF field registration and ACF-powered shortcodes will stay inactive until ACF or ACF Pro is enabled.</p></div>';
    } );
}

// ACF-specific registration stays behind ACF's lifecycle hook.
add_action( 'acf/init', [ AcfModule::class, 'register' ], 5 );


//register_acf_rss();




add_action('init', function() {


if (is_admin()){


require_once HWS_BASE_TOOLS_DIR . '/helper.php';
require_once HWS_BASE_TOOLS_DIR . '/safe-wrappers.php';
require_once HWS_BASE_TOOLS_DIR . '/generic-functions.php';
// UI cleanup must load across wp-admin so editor/profile screens receive filters and CSS.
require_once HWS_BASE_TOOLS_DIR . '/settings-dashboard-ui-cleanup.php';
if ( hws_is_dashboard_request() ) {
    hws_load_dashboard_files();
}

require_once HWS_BASE_TOOLS_DIR . '/snippet-allow-svg-upload.php';
require_once HWS_BASE_TOOLS_DIR . '/snippet-rss.php';
require_once HWS_BASE_TOOLS_DIR . '/snippet-comments.php';
// Always enable comments management (not a snippet - core feature)
if ( function_exists( __NAMESPACE__ . '\\enable_comments_management' ) ) {
    enable_comments_management();
}
// — Legacy ACF migration UI (pending delete — moved to delete-prefixed file)
require_once HWS_BASE_TOOLS_DIR . '/delete-snippet-acf-migration-structures.php';


require_once HWS_BASE_TOOLS_DIR . '/snippet-clean-user.php';
// NOTE: snippet-website-settings-functionality.php moved outside is_admin() block
// so shortcodes work on frontend

require_once HWS_BASE_TOOLS_DIR . '/snippet-acf-source-tracker.php';
activate_snippets("admin");
}

// Load only enabled frontend feature implementations before activation.
FeatureLoader::load_enabled();

require_once HWS_BASE_TOOLS_DIR . '/shortcodes.php';
//include_once("shortcodes.php");

// NOTE: snippet-website-settings-functionality.php is now loaded at plugin init (top of file)
// to ensure shortcodes work with all page builders

add_shortcode('display_year', __NAMESPACE__ . '\\display_year_shortcode');
if ( get_option( 'enable_current_year_shortcode', false ) ) {
    add_shortcode('current_year', __NAMESPACE__ . '\\display_year_shortcode');
}



activate_snippets("non_admin");



},5);













function get_snippets($type = "")
{

    // ─── ACF FIELD SNIPPETS ────────────────────────────────────────────
    $snippets_acf = [
        // ★ RECOMMENDED — shown first in the list
        [
            'id' => 'register_acf_website_settings',
            'name' => 'Website Settings Page',
            'description' => 'Registers a Theme Options page with ACF fields for global site settings like logos, colors, and contact info.',
            'info' => static fn() => display_acf_structure( 'group_6842076add7ad' ),
            'function' => 'register_acf_website_settings',
            'scope_admin_only' => false,
            'recommended' => true
        ],
        [
            'id' => 'register_user_custom_fields_2025',
            'name' => 'User Profile Fields (2025)',
            'description' => 'Extends WordPress user profiles with additional fields like bio, avatar, and preferences.',
            'info'        => static fn() => display_acf_structure( 'group_684252fd99081' ),
            'function' => 'register_user_custom_fields_2025',
            'scope_admin_only' => false,
            'recommended' => true
        ],
        [
            'id' => 'register_user_custom_fields_additional_2025',
            'name' => 'Additional User Profile Fields',
            'description' => 'Extra user profile fields for extended functionality and metadata.',
            'info'        => static fn() => display_acf_structure( 'group_6842_additional_user_fields_2025' ),
            'function' => 'register_user_custom_fields_additional_2025',
            'scope_admin_only' => false,
            'recommended' => true
        ],

        // ─── Other ACF snippets ───────────────────────────────────────
        [
            'id' => 'smp_enable_cpt_teammember',
            'name' => 'Team Member Custom Post Type',
            'description' => 'Creates a "Team Member" post type for displaying staff/team profiles on your site.',
            'info' => static fn() => display_cpt_structure( 'team-member' ),
            'function' => 'enable_smp_cpt_teammember',
            'scope_admin_only' => false
        ],
        [
            'id' => 'smp_enable_acf_teammember',
            'name' => 'Team Member ACF Fields',
            'description' => 'Adds custom fields to team members: job title, bio, photo, social links.',
            'info'        => static fn() => display_acf_structure( 'group_64b3a05760b1a' ),
            'function' => 'enable_smp_acf_teammember',
            'scope_admin_only' => false
        ],

        // ─── Deprecated ───────────────────────────────────────────────
        [
            'id' => 'smp_enable_cpt_organization',
            'name' => 'Organizations Custom Post Type',
            'description' => 'Creates an "Organization" post type for displaying company/partner profiles.',
            'info' => static fn() => display_cpt_structure( 'organization' ),
            'function' => 'enable_smp_cpt_organization',
            'scope_admin_only' => false,
            'deprecated' => true
        ],
        [
            'id' => 'enable_cpt_testimonial',
            'name' => 'Testimonials Custom Post Type',
            'description' => 'Creates a "Testimonial" post type for displaying customer reviews and quotes.',
            'info' => static fn() => display_cpt_structure( 'testimonial' ),
            'function' => 'enable_cpt_testimonial',
            'scope_admin_only' => false,
            'deprecated' => true
        ],
        [
            'id' => 'enable_acf_testimonial',
            'name' => 'Testimonial ACF Fields',
            'description' => 'Adds custom fields to testimonials: author name, company, rating, photo, etc.',
            'info'        => static fn() => display_acf_structure( 'group_64c2177b44137' ),
            'function' => 'enable_acf_testimonial',
            'scope_admin_only' => false,
            'deprecated' => true
        ],
        [
            'id' => 'smp_enable_acf_organization',
            'name' => 'Organization ACF Fields',
            'description' => 'Adds custom fields to organizations: logo, website, description, contact info.',
            'info'        => static fn() => display_acf_structure( 'group_64bc3b458d863' ),
            'function' => 'enable_smp_acf_organization',
            'scope_admin_only' => false,
            'deprecated' => true
        ],
        [
            'name'        => 'Author Social Media Links',
            'id'          => 'register_user_custom_fields',
            'function'    => 'register_user_custom_fields',
            'description' => 'Adds social media profile links (Twitter, Facebook, LinkedIn, etc.) to author profiles.',
            'info'        => static fn() => display_acf_structure( 'group_590d64c31db0a', true ),
            'scope_admin_only' => false,
            'deprecated'  => true
        ]
    ];


    // ─── ADMIN / SETTINGS SNIPPETS ─────────────────────────────────────
    $snippets_admin = [
    [
        'id'               => 'enable_acf_source_tracker',
        'name'             => 'ACF Source Tracker',
        'description'      => 'Shows a subtle source header on each ACF field group (registering plugin plus the unique group key) with a faint per-source colour accent, so field origins are trackable. Generic engine; currently active on the user edit/profile screen.',
        'info'             => 'Default off. Generic: auto-detects which plugin or theme registered each group by its unique key. Active screens are filterable via the hws_acf_source_tracker_screens filter.',
        'function'         => 'enable_acf_source_tracker',
        'scope_admin_only' => true,
    ],
    // ★ RECOMMENDED
    [
        'id' => 'enable_website_settings_functionality',
        'name' => 'Theme Settings Functions',
        'description' => 'Enables helper functions to retrieve theme settings values throughout your site templates.',
        'info' => 'Provides functions like get_theme_option() for easy access to ACF theme settings.',
        'function' => 'enable_website_settings_functionality',
        'scope_admin_only' => false,
        'recommended' => true
    ],
    [
        'id' => 'enable_auto_update_plugins',
        'name' => 'Auto-Update All Plugins',
        'description' => 'Automatically updates all plugins when new versions are released.',
        'info' => '⚠️ Recommended for most sites. Keeps plugins secure and up-to-date without manual intervention.',
        'function' => 'enable_auto_update_plugins',
        'scope_admin_only' => true,
        'recommended' => true
    ],
    [
        'id' => 'enable_auto_update_themes',
        'name' => 'Auto-Update All Themes',
        'description' => 'Automatically updates all themes when new versions are released.',
        'info' => '⚠️ Recommended for most sites. Keeps themes secure and up-to-date.',
        'function' => 'enable_auto_update_themes',
        'scope_admin_only' => true,
        'recommended' => true
    ],
    [
        'id' => 'snippet_enable_svg_uploads',
        'name' => 'Allow SVG Uploads',
        'description' => 'Enables SVG file uploads in the WordPress media library.',
        'info' => '⚠️ Security note: Only enable if you trust all users who can upload files.',
        'function' => 'snippet_enable_svg_uploads',
        'scope_admin_only' => true
    ],

    // ─── Other admin snippets ─────────────────────────────────────────
    [
        'id' => 'register_sponsored_functionality',
        'name' => 'Sponsored Content Fields',
        'description' => 'Adds "Sponsored" checkbox and sponsor details fields to posts for affiliate/sponsored content disclosure.',
        'info' => static fn() => display_acf_structure( 'group_sponsored_field' ),
        'function' => 'register_acf_sponsored_functionality',
        'scope_admin_only' => true
    ],
    [
        'id' => 'enable_custom_rss_functionality',
        'name' => 'Custom RSS Feeds',
        'description' => 'Creates custom RSS feeds for specific post types and categories.',
        'info' => static fn() => display_acf_structure( 'group_66e9ebd79f8e0' ),
        'function' => 'enable_custom_rss_functionality',
        'scope_admin_only' => true
    ],
    [
        'id' => 'disable_litespeed_js_combine',
        'name' => 'Disable LiteSpeed JS Combine',
        'description' => 'Prevents LiteSpeed Cache from combining JavaScript files.',
        'info' => 'Useful for debugging JS issues or when combined scripts cause conflicts.',
        'function' => 'disable_litespeed_js_combine',
        'scope_admin_only' => true
    ],
];


    // ─── NON-ADMIN / FRONTEND SNIPPETS ─────────────────────────────────
    $snippet_non_admin = [
    // ★ RECOMMENDED
    TeamMemberFeature::definition(),
    [
        'id' => 'enable_elementor_social_icon_cleanup',
        'name' => 'Elementor Social Icons Cleanup',
        'description' => 'Hides empty social icons and adds target="_blank" to external links in all Elementor Social Icons widgets.',
        'info' => '<strong>What it does:</strong><br>
            <code>1.</code> Hides any social icon with no URL (no <code>href</code>, empty, or <code>#</code>)<br>
            <code>2.</code> Adds <code>target="_blank"</code> + <code>rel="noopener"</code> to external links<br><br>
            <strong>Exclude Parameters</strong> (add as CSS class on the widget in Elementor → Advanced → CSS Classes):<br>
            <code>hws-no-hide-empty</code> — Skip hiding empty icons for that widget<br>
            <code>hws-no-external-blank</code> — Skip adding target="_blank" for that widget<br><br>
            <strong>Technical:</strong> Vanilla JS in <code>wp_footer</code> @ priority 99. No jQuery dependency. Runs on <code>DOMContentLoaded</code>. ~1KB inline.',
        'function' => 'enable_elementor_social_icon_cleanup',
        'scope_admin_only' => false,
        'recommended' => true
    ],
    [
        'id' => 'enable_footer_text_auto_injection',
        'name' => 'Footer Text Module',
        'description' => 'Unlocks the Footer Text tab and loads the footer text feature.',
        'info' => 'This does not show anything on the website by itself.<br>
            Use the <code>Footer Text</code> tab to write the content, turn live display on or off, and choose a style.',
        'function' => 'enable_footer_text_auto_injection',
        'scope_admin_only' => false
    ],
    [
        'id' => 'disable_non_admin_admin_bar',
        'name' => 'Disable Admin Bar for Non-Admins',
        'description' => 'Hides the front-end WordPress admin bar for users who cannot manage site options.',
        'info' => 'Admins keep the toolbar. Editors, authors, subscribers, and logged-out visitors do not see it on the front end.',
        'function' => 'disable_non_admin_admin_bar',
        'scope_admin_only' => false,
        'recommended' => true,
        'code_example' => "add_action( 'wp', function () {\n\tif ( ! current_user_can( 'manage_options' ) ) {\n\t\tshow_admin_bar( false );\n\t}\n} );",
    ],
    [
        'id' => 'enable_syndtd_feed_limit',
        'name' => 'RSS Feed Limit for Tag',
        'description' => 'Overrides the item count for a specific tag feed, defaulting to the syndtd tag.',
        'info' => 'Set the tag slug and item limit in the Features tab. The default is tag <code>syndtd</code> with a limit of <code>100</code> items.',
        'function' => 'enable_syndtd_feed_limit',
        'scope_admin_only' => false,
        'code_example' => "add_action( 'pre_get_posts', function( WP_Query \$query ) {\n\tif ( ! is_admin() && \$query->is_main_query() && \$query->is_feed() && \$query->is_tag( 'syndtd' ) ) {\n\t\t\$query->set( 'posts_per_rss', 100 );\n\t}\n} );",
    ],
    [
        'id' => 'enable_current_year_shortcode',
        'name' => 'Current Year Shortcode',
        'description' => 'Adds the intuitive [current_year] shortcode. The legacy [display_year] shortcode remains available.',
        'info' => 'Use <code>[current_year]</code> in footer text, Elementor, Gutenberg, or post content to output the current four-digit year.',
        'function' => 'enable_current_year_shortcode',
        'scope_admin_only' => false,
        'recommended' => true,
        'code_example' => '[current_year]',
    ],
    [
        'id' => 'enable_lowercase_upload_filenames',
        'name' => 'Lowercase Upload File Names',
        'description' => 'Forces uploaded media file names to lowercase during WordPress sanitization.',
        'info' => 'Example: <code>My File.PNG</code> becomes <code>my-file.png</code>. This reduces case-sensitive URL and CDN cache issues.',
        'function' => 'enable_lowercase_upload_filenames',
        'scope_admin_only' => false,
        'code_example' => "add_filter( 'sanitize_file_name', 'mb_strtolower' );",
    ],
    [
        'id' => 'enable_wp_admin_logo',
        'name' => 'Custom Login Logo',
        'description' => 'Replaces the WordPress logo on the login screen with your site icon.',
        'info' => function() {
            $logo_url = get_site_icon_url();
            $thumbnail = $logo_url ? '<img src="' . esc_url($logo_url) . '" style="max-width:100px; display:block; margin-top:10px;" alt="Custom Logo Thumbnail" onclick="event.stopPropagation();">' : '';

            if ($logo_url) {
                return 'Uses your Site Icon as the login logo.<br>' .
                       '<span onclick="event.stopPropagation();">' . $thumbnail . '</span><br>' .
                       '<a href="' . esc_url($logo_url) . '" target="_blank">View Image</a> | ' .
                       '<a href="' . esc_url(admin_url('options-general.php')) . '" target="_blank">Change Icon</a>';
            } else {
                return '⚠️ No site icon set. <a href="' . esc_url(admin_url('options-general.php')) . '" target="_blank">Set one in Settings → General</a>';
            }
        },
        'function' => 'custom_wp_admin_logo',
        'scope_admin_only' => false,
        'recommended' => true
    ],

    // ─── Other frontend snippets ──────────────────────────────────────
    [
        'id' => 'enable_elementor_queries',
        'name' => 'Elementor Custom Queries',
        'description' => 'Adds custom query filters for Elementor Pro Posts/Archive widgets.',
        'info' => '<strong>Available Query IDs:</strong><br>
            <code>featured_team_members</code> – Filters Team Members CPT where <code>featured = 1</code><br>
            <code>featured_testimonials</code> – Filters Testimonials CPT where <code>featured = 1</code><br>
            <code>query_featured_posts</code> – Filters Posts where <code>featured = 1</code><br>
            <em>Usage: Loop Widget → Query → Advanced → Query ID</em>',
        'function' => 'enable_elementor_queries',
        'scope_admin_only' => false
    ],
    [
        'id' => 'enable_seo_amp_no_index',
        'name' => 'NoIndex AMP Pages',
        'description' => 'Adds noindex meta tag to AMP pages to prevent duplicate content in search results.',
        'info' => 'Recommended if you have AMP pages that mirror your main content.',
        'function' => 'enable_seo_amp_no_index',
        'scope_admin_only' => false
    ],
    [
        'id' => 'enable_seo_feeds_no_index',
        'name' => 'NoIndex RSS Feeds',
        'description' => 'Adds noindex to RSS/Atom feeds to prevent feed URLs from appearing in search results.',
        'info' => 'Helps focus SEO on your actual content pages.',
        'function' => 'enable_seo_feeds_no_index',
        'scope_admin_only' => false
    ],
    [
        'id' => 'disable_rankmath_sitemap_caching',
        'name' => 'Disable Rank Math Sitemap Cache',
        'description' => 'Prevents Rank Math from caching sitemaps, forcing fresh generation on each request.',
        'info' => 'Useful during development or when sitemap changes are not reflecting.',
        'function' => 'disable_rankmath_sitemap_caching',
        'scope_admin_only' => false
    ],
];

if ($type === 'non_admin')
    return $snippet_non_admin;
if ($type === 'admin')
    return $snippets_admin;

return $snippets_acf;

}




?>
