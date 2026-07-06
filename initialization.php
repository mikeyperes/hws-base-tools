<?php
namespace hws_base_tools;

/**
 * Legacy bootstrap.
 *
 * The canonical WordPress plugin entry is hws-base-tools.php. This file stays
 * loadable so existing installs active as hws-base-tools/initialization.php can
 * migrate their stored plugin basename without a deactivate/reactivate cycle.
 */

// Ensure this file is being included by a parent file
defined('ABSPATH') or die('No script kiddies please!');

if ( defined( 'HWS_BASE_TOOLS_BOOTSTRAPPED' ) ) {
    return;
}

define( 'HWS_BASE_TOOLS_BOOTSTRAPPED', true );

if ( ! defined( 'HWS_BASE_TOOLS_CANONICAL_PLUGIN_FILE' ) ) {
    define( 'HWS_BASE_TOOLS_CANONICAL_PLUGIN_FILE', __DIR__ . '/hws-base-tools.php' );
}

if ( ! defined( 'HWS_BASE_TOOLS_LEGACY_PLUGIN_FILE' ) ) {
    define( 'HWS_BASE_TOOLS_LEGACY_PLUGIN_FILE', __FILE__ );
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

include_once("runtime-options.php");
require_once __DIR__ . '/src/Core/Autoloader.php';

\HWS\BaseTools\Core\Autoloader::register( __DIR__ . '/src' );

function hws_register_hexa_plugin_core_autoloader(): void {
    static $registered = false;

    if ( $registered ) {
        return;
    }

    $base_dir = __DIR__ . '/lib/hexa-wordpress-plugin-core/src/';
    $prefix   = 'Hexa\\PluginCore\\';

    spl_autoload_register( static function( $class_name ) use ( $base_dir, $prefix ) {
        if ( strpos( $class_name, $prefix ) !== 0 ) {
            return;
        }

        $relative_class = substr( $class_name, strlen( $prefix ) );
        $file           = $base_dir . str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';

        if ( is_readable( $file ) ) {
            require_once $file;
        }
    } );

    $registered = true;
}

hws_register_hexa_plugin_core_autoloader();

if ( is_admin() && ! class_exists( '\\Hexa\\PluginCore\\WpAdminComponents\\CoreUi', false ) ) {
    class_exists( '\\Hexa\\PluginCore\\WpAdminComponents\\CoreUi' );
}

include_once __DIR__ . '/safe-wrappers.php';
include_once __DIR__ . '/settings-dashboard-site-profile.php';
include_once __DIR__ . '/settings-dashboard-sitemaps.php';
include_once __DIR__ . '/settings-dashboard-cleanup.php';
include_once __DIR__ . '/settings-dashboard-getting-started.php';

function hws_get_structured_plugin() {
    static $plugin = null;

    if ( null === $plugin ) {
        $plugin = new \HWS\BaseTools\Core\Plugin();
    }

    return $plugin;
}

function hws_boot_structured_admin_modules() {
    static $booted = false;

    if ( $booted ) {
        return;
    }

    $plugin = hws_get_structured_plugin();
    $plugin->add_module( new \HWS\BaseTools\Admin\Dashboard\LegacyEventBridge() );
    $plugin->boot();

    $booted = true;
}

// CRITICAL: Load shortcodes FIRST at plugin load time (before any guards)
// This ensures shortcodes work with Elementor, Gutenberg, and all page builders
include_once("snippet-website-settings-functionality.php");

// === Guard: don't bootstrap ADMIN features during Elementor's internal AJAX ===
if ( defined('DOING_AJAX') && DOING_AJAX ) {
    $ajax_action = isset($_REQUEST['action']) ? sanitize_text_field($_REQUEST['action']) : '';
    if ( $ajax_action === 'elementor_ajax' ) {
        // Elementor sends a JSON 'actions' payload (often includes get_widgets_config)
        // Shortcodes already registered above - just skip admin features
        return;
    } 
}  
 


include_once("snippet-login-mask.php");
include_once("snippet-base-features.php");

// — Update Center: loaded early so secret URL handlers register on init:1 for frontend access
// — Dashboard UI rendering is gated inside display_settings_update_center() called only in admin
include_once("settings-dashboard-update-center.php");

// — Masked Login Dashboard: loaded early so public URL handlers register on init:1
// — Dashboard UI rendering is gated inside display_settings_masked_login() called only in admin
include_once("settings-dashboard-masked-login.php");




//if (!is_admin()) return;

class Config {
    public static $settings_page_name = "HWS Base Tools";
    public static $settings_page_capability = "manage_options";
    public static $settings_page_slug = "hws-core-tools";
    public static $settings_page_display_title = "Hexa Core Tools - WP-Config Settings";

    public static $plugin_name = "Hexa Web Systems - Website Base Tool";
    public static $plugin_starter_file = "hws-base-tools.php";
    public static $plugin_slug = "hws-core-tools";
    
    // Plugin identification - use these everywhere, never hardcode
    public static $plugin_folder_name = "hws-base-tools";
    public static $github_repo = "mikeyperes/hws-base-tools";
    public static $github_branch = "main";
    
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
        'api_url'            => 'https://api.github.com/repos/mikeyperes/hws-base-tools',
        'raw_url'            => 'https://raw.githubusercontent.com/mikeyperes/hws-base-tools/main',
        'github_url'         => 'https://github.com/mikeyperes/hws-base-tools',
        'zip_url'            => 'https://github.com/mikeyperes/hws-base-tools/archive/main.zip',

        // 4) HTTP settings
        'sslverify'          => true,
        'access_token'       => '',

        // 5) WP compatibility
        'requires'           => '5.0',
        'tested'             => '7.0',
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

function hws_request_value( string $key ): string {
    if ( ! isset( $_REQUEST[ $key ] ) || is_array( $_REQUEST[ $key ] ) ) {
        return '';
    }

    return sanitize_key( wp_unslash( $_REQUEST[ $key ] ) );
}

function hws_is_dashboard_ajax_request(): bool {
    if ( ! function_exists( 'wp_doing_ajax' ) || ! wp_doing_ajax() ) {
        return false;
    }

    $action = hws_request_value( 'action' );
    if ( '' === $action ) {
        return false;
    }

    if ( 0 === strpos( $action, 'hws_' ) || 0 === strpos( $action, 'hws_base_tools_' ) ) {
        return true;
    }

    return in_array( $action, [ 'modify_wp_config_constants', 'delete_debug_log', 'delete_error_log' ], true );
}

function hws_is_dashboard_request(): bool {
    if ( ! is_admin() ) {
        return false;
    }

    if ( Config::$settings_page_slug === hws_request_value( 'page' ) ) {
        return true;
    }

    return hws_is_dashboard_ajax_request();
}

function hws_load_dashboard_files(): void {
    static $loaded = false;

    if ( $loaded ) {
        return;
    }

    include_once __DIR__ . "/helper.php";
    include_once __DIR__ . "/safe-wrappers.php";
    include_once __DIR__ . "/settings-dashboard-site-profile.php";
    hws_boot_structured_admin_modules();

    // Build Dashboard - New modular structure
    include_once __DIR__ . "/settings-dashboard.php";           // Main dashboard with tabs
    include_once __DIR__ . "/settings-dashboard-system-checks.php";  // System checks (restored original)
    include_once __DIR__ . "/settings-dashboard-check-plugins.php";  // Plugin status monitoring
    include_once __DIR__ . "/settings-dashboard-config.php";    // Configuration & PHP info
    include_once __DIR__ . "/settings-dashboard-backups.php";   // Backup detection & cleanup
    include_once __DIR__ . "/settings-dashboard-log-delete-cron.php";  // Log file cleaner
    include_once __DIR__ . "/settings-dashboard-elementor-db-cron.php"; // Elementor DB auto-updater
    include_once __DIR__ . "/settings-dashboard-snippets.php";
    include_once __DIR__ . "/settings-dashboard-features.php";
    include_once __DIR__ . "/settings-dashboard-website-types.php";  // Website type presets
    include_once __DIR__ . "/settings-dashboard-footer-text.php";    // Footer text module settings
    include_once __DIR__ . "/settings-dashboard-ui-cleanup.php";     // UI Cleanup (hide profile elements)
    include_once __DIR__ . "/settings-dashboard-theme-checks.php";
    include_once __DIR__ . "/settings-dashboard-plugin-info.php";
    include_once __DIR__ . "/settings-dashboard-rank-math-settings.php";
    include_once __DIR__ . "/settings-dashboard-shortcode-tests.php";
    include_once __DIR__ . "/settings-dashboard-menu-tools.php";
    include_once __DIR__ . "/settings-dashboard-pages.php";
    include_once __DIR__ . "/settings-dashboard-sitemaps.php";
    include_once __DIR__ . "/settings-dashboard-cleanup.php";
    include_once __DIR__ . "/settings-dashboard-getting-started.php";

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
            'requires'                  => '5.0',
            'tested'                    => '7.0',
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
        __DIR__ . '/lib/hexa-wordpress-plugin-core',
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
include_once("generic-functions.php");

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

$plugin_name = "Hexa Web Systems - Website Base Tool";
$plugin_description = "Basic tools for optimization, performance, and debugging on Hexa based web systems.";
$author_name = "Michael Peres";
$plugin_uri = "https://github.com/mikeyperes/hws-base-tools";
$plugin_version = "10.18.100";
$author_uri = "https://michaelperes.com";
$api_url = "https://api.github.com/repos/mikeyperes/hws-base-tools";
$plugin_github_url = "https://github.com/mikeyperes/hws-base-tools";
$plugin_zip_url = "https://github.com/mikeyperes/hws-base-tools/archive/main.zip";
$wordpress_version_tested = "7.0";
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
    ( new \Hexa\PluginCore\WpAdminTabs\CoreTabModule(
        new \Hexa\PluginCore\WpAdminTabs\CoreTabConfig(
            [
                'tabs_filter'   => 'hws_base_tools_dashboard_tabs',
                'render_filter' => 'hws_base_tools_render_dashboard_tab',
                'capability'    => Config::$settings_page_capability,
                'core_root'     => __DIR__ . '/lib/hexa-wordpress-plugin-core',
                'readme_path'   => __DIR__ . '/lib/hexa-wordpress-plugin-core/README.md',
                'library_path'  => __DIR__ . '/HEXA_PLUGIN_CORE_LIBRARY.md',
            ]
        )
    ) )->register();

    if ( is_admin() && isset( $_GET['force-update-check'] ) ) {
        wp_clean_update_cache();
        set_site_transient( 'update_plugins', null );
        wp_update_plugins();
        error_log( 'Hexa Plugin Core Updater: Forced plugin update check triggered for HWS Base Tools.' );
    }
}, 20 );





if ( is_admin() ) {
    add_action( 'admin_notices', function() {
        if ( hws_is_acf_available() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        echo '<div class="notice notice-warning"><p><strong>HWS Base Tools:</strong> ACF is not active. Core tools remain available; ACF field registration and ACF-powered shortcodes will stay inactive until ACF or ACF Pro is enabled.</p></div>';
    } );
}

// Hook to acf/init so ACF-related modules load only when ACF is available.
add_action('acf/init', function() {


// Import ACF Fields
include_once("register-acf-fields-user.php");
include_once("register-acf-fields-rss.php");
include_once("register-acf-sponsored-functionality.php");
include_once("register-acf-website-settings.php");


// Import ACF Fields
include_once("smp-core/register-post-type-organization.php");
include_once("smp-core/register-post-type-team-member.php");
include_once("smp-core/register-post-type-testimonial.php");

include_once("smp-core/register-acf-user.php");
if ( function_exists( '\\enable_smp_acf_user' ) ) {
    \enable_smp_acf_user();
}
include_once("smp-core/register-acf-organization.php");
include_once("smp-core/register-acf-team-member.php");
include_once("smp-core/register-acf-testimonial.php");

//include_once("scale-my-podcast/register-acf-post-podcast.php");



activate_snippets("acf");

}, 5 );


//register_acf_rss();




add_action('init', function() { 

   
if (is_admin()){


include_once("helper.php");
include_once("safe-wrappers.php");  // Safe AJAX, shell_exec, and error handling utilities
// UI cleanup must load across wp-admin so editor/profile screens receive filters and CSS.
include_once("settings-dashboard-ui-cleanup.php");
if ( hws_is_dashboard_request() ) {
    hws_load_dashboard_files();
}

include_once("snippet-allow-svg-upload.php");
include_once("snippet-rss.php");
include_once("snippet-comments.php");
// Always enable comments management (not a snippet - core feature)
if ( function_exists( __NAMESPACE__ . '\\enable_comments_management' ) ) {
    enable_comments_management();
}
// — Legacy ACF migration UI (pending delete — moved to delete-prefixed file)
include_once("delete-snippet-acf-migration-structures.php");


include_once("snippet-clean-user.php");
include_once("snippet-seo-rss.php");
include_once("snippet-seo-amp.php");
// NOTE: snippet-website-settings-functionality.php moved outside is_admin() block
// so shortcodes work on frontend

include_once("snippet-acf-source-tracker.php");
activate_snippets("admin");
}

// — Frontend snippets: included outside is_admin() so they load on the frontend
include_once("snippet-elementor-social-icons.php");



include_once("snippet-footer-text.php");
include_once("shortcodes.php");
include_once("register-elementor-queries.php");
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
            'info' => display_acf_structure('group_6842076add7ad'),
            'function' => 'register_acf_website_settings',
            'scope_admin_only' => false,
            'recommended' => true
        ],
        [
            'id' => 'register_user_custom_fields_2025',
            'name' => 'User Profile Fields (2025)',
            'description' => 'Extends WordPress user profiles with additional fields like bio, avatar, and preferences.',
            'info'        =>  display_acf_structure('group_684252fd99081'),
            'function' => 'register_user_custom_fields_2025',
            'scope_admin_only' => false,
            'recommended' => true
        ],
        [
            'id' => 'register_user_custom_fields_additional_2025',
            'name' => 'Additional User Profile Fields',
            'description' => 'Extra user profile fields for extended functionality and metadata.',
            'info'        =>  display_acf_structure('group_6842_additional_user_fields_2025'),
            'function' => 'register_user_custom_fields_additional_2025',
            'scope_admin_only' => false,
            'recommended' => true
        ],

        // ─── Other ACF snippets ───────────────────────────────────────
        [
            'id' => 'smp_enable_cpt_teammember',
            'name' => 'Team Member Custom Post Type',
            'description' => 'Creates a "Team Member" post type for displaying staff/team profiles on your site.',
            'info' => display_cpt_structure('team-member'),
            'function' => 'enable_smp_cpt_teammember',
            'scope_admin_only' => false
        ],
        [
            'id' => 'smp_enable_acf_teammember',
            'name' => 'Team Member ACF Fields',
            'description' => 'Adds custom fields to team members: job title, bio, photo, social links.',
            'info'        =>  display_acf_structure('group_64b3a05760b1a'),
            'function' => 'enable_smp_acf_teammember',
            'scope_admin_only' => false
        ],

        // ─── Deprecated ───────────────────────────────────────────────
        [
            'id' => 'smp_enable_cpt_organization',
            'name' => 'Organizations Custom Post Type',
            'description' => 'Creates an "Organization" post type for displaying company/partner profiles.',
            'info' => display_cpt_structure('organization'),
            'function' => 'enable_smp_cpt_organization',
            'scope_admin_only' => false,
            'deprecated' => true
        ],
        [
            'id' => 'enable_cpt_testimonial',
            'name' => 'Testimonials Custom Post Type',
            'description' => 'Creates a "Testimonial" post type for displaying customer reviews and quotes.',
            'info' => display_cpt_structure('testimonial'),
            'function' => 'enable_cpt_testimonial',
            'scope_admin_only' => false,
            'deprecated' => true
        ],
        [
            'id' => 'enable_acf_testimonial',
            'name' => 'Testimonial ACF Fields',
            'description' => 'Adds custom fields to testimonials: author name, company, rating, photo, etc.',
            'info'        =>  display_acf_structure('group_64c2177b44137'),
            'function' => 'enable_acf_testimonial',
            'scope_admin_only' => false,
            'deprecated' => true
        ],
        [
            'id' => 'smp_enable_acf_organization',
            'name' => 'Organization ACF Fields',
            'description' => 'Adds custom fields to organizations: logo, website, description, contact info.',
            'info'        =>  display_acf_structure('group_64bc3b458d863'),
            'function' => 'enable_smp_acf_organization',
            'scope_admin_only' => false,
            'deprecated' => true
        ],
        [
            'name'        => 'Author Social Media Links',
            'id'          => 'register_user_custom_fields',
            'function'    => 'register_user_custom_fields',
            'description' => 'Adds social media profile links (Twitter, Facebook, LinkedIn, etc.) to author profiles.',
            'info'        =>  display_acf_structure('group_590d64c31db0a',true),
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
        'info' => display_acf_structure('group_sponsored_field'),
        'function' => 'register_acf_sponsored_functionality',
        'scope_admin_only' => true
    ],
    [
        'id' => 'enable_custom_rss_functionality',
        'name' => 'Custom RSS Feeds',
        'description' => 'Creates custom RSS feeds for specific post types and categories.',
        'info' => display_acf_structure('group_66e9ebd79f8e0'),
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
