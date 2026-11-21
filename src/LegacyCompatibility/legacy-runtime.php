<?php

namespace hws_base_tools;

use HWS\BaseTools\AdminDashboard\DashboardRegistry;
use HWS\BaseTools\AcfFields\AcfModule;
use HWS\BaseTools\PluginRuntime\PluginMetadata;
use HWS\BaseTools\PluginRuntime\RequestContext;
use HWS\BaseTools\PluginRuntime\CoreIntegration;
use HWS\BaseTools\Security\RemoteActionPolicy;
use HWS\BaseTools\FrontendContent\FeatureLoader;
use HWS\BaseTools\FrontendContent\ReadingProgress;
use HWS\BaseTools\Editorial\FeaturedImageRequirement;
use HWS\BaseTools\Editorial\PublicDraftPreviewFeature;
use HWS\BaseTools\Maintenance\BackupPluginBlocker;
use HWS\BaseTools\Maintenance\ScheduledTaskLoader;
use HWS\BaseTools\UserImpersonation\UserImpersonationFeature;

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
    if ( function_exists( 'plugin_basename' ) ) {
        \Hexa\PluginCore\PluginUpdates\LegacyPluginBasenameMigration::migrate(
            plugin_basename( HWS_BASE_TOOLS_LEGACY_PLUGIN_FILE ),
            plugin_basename( HWS_BASE_TOOLS_CANONICAL_PLUGIN_FILE )
        );
    }
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\hws_migrate_active_plugin_basename_to_canonical', 1 );

require_once HWS_BASE_TOOLS_DIR . '/runtime-options.php';

require_once HWS_BASE_TOOLS_DIR . '/safe-wrappers.php';
require_once HWS_BASE_TOOLS_DIR . '/src/LegacyCompatibility/runtime-functions.php';
ScheduledTaskLoader::load_for_request();

// Register request identity switching before another plugin can resolve the
// current user during plugins_loaded. The structured Core module is idempotent.
if ( UserImpersonationFeature::enabled() ) {
    UserImpersonationFeature::activate();
}

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

    $config = \Hexa\PluginCore\PluginUpdates\UpdaterConfig::from_managed_plugin_file(
        HWS_BASE_TOOLS_CANONICAL_PLUGIN_FILE,
        'https://github.com/' . Config::$github_repo,
        Config::$plugin_folder_name,
        'hws_base_tools_core_updater',
        [
            'plugin_starter_file' => Config::$plugin_starter_file,
            'github_branch'       => Config::$github_branch,
            'requires'            => PluginMetadata::REQUIRES_WORDPRESS,
            'requires_php'        => PluginMetadata::REQUIRES_PHP,
            'tested'              => PluginMetadata::TESTED_WORDPRESS,
            'nonce_action'        => 'hws_base_tools_ajax_nonce',
            'plugin_basename'     => Config::get_plugin_basename(),
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





// Register the site's field definitions during Core initialization.
\Hexa\PluginCore\Fields\Hooks::on( 'init', [ AcfModule::class, 'register' ], 5 );


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



require_once HWS_BASE_TOOLS_DIR . '/src/LegacyCompatibility/GenericLibrary/generic-auto-updates.php';
activate_snippets("non_admin");



},5);













function get_snippets($type = "")
{

    // ─── FIELD SNIPPETS ────────────────────────────────────────────
    // Field structures are managed exclusively by the Core-rendered Custom Post
    // Types tab. Their established option names remain the compatibility layer.
    $snippets_acf = [];


    // ─── ADMIN / SETTINGS SNIPPETS ─────────────────────────────────────
    $snippets_admin = [
    [
        'id'               => UserImpersonationFeature::FEATURE_OPTION,
        'name'             => 'View As User',
        'description'      => 'Lets an administrator open an isolated virtual session as any WordPress user without replacing the administrator login in other tabs.',
        'info'             => 'Default off. Enable it under HWS Core Tools → Features → Admin Features. Only administrators can start or end sessions. A fixed red banner identifies the virtual user and provides an End View As action.',
        'function'         => 'enable_hws_user_impersonation',
        'scope_admin_only' => true,
        'code_example'     => 'Choose user administration, content editors or single content pages in the feature settings. Configure optional owner user fields; use the top-bar picker to open a separate tab.',
    ],
    [
        'id'               => FeaturedImageRequirement::FEATURE_OPTION,
        'name'             => 'Require Featured Image',
        'description'      => 'Prevents selected content types from publishing until a featured image meeting the configured minimum dimensions is present.',
        'info'             => 'Choose the covered content types and optional minimum dimensions in HWS Base Tools → Features. Existing settings from the Require Featured Image plugin migrate automatically once.',
        'function'         => 'enable_required_featured_image',
        'scope_admin_only' => true,
        'code_example'     => 'No shortcode needed. Configure it in HWS Base Tools → Features.',
    ],
    [
        'id'               => 'enable_acf_source_tracker',
        'name'             => 'Field Source Tracker',
        'description'      => 'Shows a subtle source header on each field group (registering plugin plus the unique group key) with a faint per-source colour accent, so field origins are trackable. Generic engine; currently active on the user edit/profile screen.',
        'info'             => 'Default off. Generic: auto-detects which plugin or theme registered each group by its unique key. Active screens are filterable via the hws_acf_source_tracker_screens filter.',
        'function'         => 'enable_acf_source_tracker',
        'scope_admin_only' => true,
    ],
    // ★ RECOMMENDED
    [
        'id' => 'enable_website_settings_functionality',
        'name' => 'Theme Settings Functions',
        'description' => 'Enables helper functions to retrieve theme settings values throughout your site templates.',
        'info' => 'Provides functions like get_theme_option() for easy access to Core theme settings.',
        'function' => 'enable_website_settings_functionality',
        'scope_admin_only' => false,
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
    // Auto-updates run in WordPress background (cron/CLI) runs, never in wp-admin, so these load in every context.
    [
        'id' => 'enable_auto_update_plugins',
        'name' => 'Auto-Update All Plugins',
        'description' => 'Automatically updates all plugins, including ones installed later, whenever WordPress runs its background updates. Plugins on GPL Vault\'s do-not-update list are skipped.',
        'info' => '⚠️ Recommended for most sites. Keeps plugins secure and up-to-date without manual intervention.',
        'function' => 'enable_auto_update_plugins',
        'scope_admin_only' => false,
        'recommended' => true
    ],
    [
        'id' => 'enable_auto_update_themes',
        'name' => 'Auto-Update All Themes',
        'description' => 'Automatically updates all themes whenever WordPress runs its background updates.',
        'info' => '⚠️ Recommended for most sites. Keeps themes secure and up-to-date.',
        'function' => 'enable_auto_update_themes',
        'scope_admin_only' => false,
        'recommended' => true
    ],
    // ★ RECOMMENDED
    \HWS\BaseTools\FrontendContent\ScrollToTop::definition(),
    PublicDraftPreviewFeature::definition(),
    BackupPluginBlocker::definition(),
    [
        'id'               => ReadingProgress::FEATURE_OPTION,
        'name'             => 'Reading Progress Bar',
        'description'      => 'Shows a configurable fixed reading-progress indicator at the top of selected public pages.',
        'info'             => 'Choose the display scope, one of five visual designs, and a color through the shared Hexa WP Core picker. Existing SMP Publication Integration settings migrate automatically once.',
        'function'         => 'enable_reading_progress_bar',
        'scope_admin_only' => false,
        'code_example'     => 'No shortcode needed. Configure it in HWS Base Tools → Features.',
    ],
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
        'description' => 'Replaces the WordPress logo on the login screen with the brand logo (Logo with text, then Logo, then the Site Icon).',
        'info' => function() {
            $logo_url = function_exists( 'hws_base_tools\\hws_get_login_logo_source' ) ? \hws_base_tools\hws_get_login_logo_source()['url'] : get_site_icon_url();
            $thumbnail = $logo_url ? '<img src="' . esc_url($logo_url) . '" style="max-width:100px; display:block; margin-top:10px;" alt="Custom Logo Thumbnail" onclick="event.stopPropagation();">' : '';

            if ($logo_url) {
                return 'Uses the Brand Assets logo shown below.<br>' .
                       '<span onclick="event.stopPropagation();">' . $thumbnail . '</span><br>' .
                       '<a href="' . esc_url($logo_url) . '" target="_blank">View Image</a> | ' .
                       '<a href="' . esc_url(admin_url('options-general.php')) . '" target="_blank">Change Icon</a>';
            } else {
                return '⚠️ No logo set. Add one in Brand Assets.';
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
            <code>hpr_resources</code> – HexaPR Wire resources<br>
            <code>hpr_publications_featured_new</code> – New featured publications<br>
            <code>hpr_publications_standard_featured</code> – Active standard featured publications<br>
            <code>hpr_updates_internal</code> – Internal announcements<br>
            <code>hpr_external_cision</code> – Cision press releases<br>
            <code>hpr_external_prcom</code> – PR.com press releases<br>
            <strong>Dynamic tag:</strong> <code>HWS Trimmed Field Text</code><br>
            <strong>Display condition:</strong> <code>hws_request_parameter</code> – matches a scalar GET parameter by comparator and value<br>
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
