<?php namespace hws_base_tools; 
/*
Plugin Name: Hexa Web Systems - Website Base Tool
Description: Basic tools for optimization, performance, and debugging on Hexa-based web systems.
Author: Michael Peres
Plugin URI: https://github.com/mikeyperes/hws-base-tools
Version: 10.9.9
Text Domain: hws-base-tools
Domain Path: /languages
Author URI: https://michaelperes.com
GitHub Plugin URI: https://github.com/mikeyperes/hws-base-tools/
GitHub Branch: main 
*/   

// Ensure this file is being included by a parent file
defined('ABSPATH') or die('No script kiddies please!');

include_once("runtime-options.php");
require_once __DIR__ . '/src/Core/Autoloader.php';

\HWS\BaseTools\Core\Autoloader::register( __DIR__ . '/src' );

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

    public static $plugin_name = "Hexa PR Wire - Distributor";
    public static $plugin_starter_file = "initialization.php";
    public static $plugin_slug = "hws-core-tools";
    
    // Plugin identification - use these everywhere, never hardcode
    public static $plugin_folder_name = "hws-base-tools";
    public static $github_repo = "mikeyperes/hws-base-tools";
    public static $github_branch = "main";
    
    /**
     * Get the full plugin basename (folder/file.php)
     */
    public static function get_plugin_basename() {
        return self::$plugin_folder_name . '/' . self::$plugin_starter_file;
    }



public static function get_github_config() {
    // Ensure we can read plugin headers
    if ( ! function_exists( 'get_plugin_data' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    // Pull header info from this very file (it contains your Plugin Name, Version, etc.)
    $plugin_data = get_plugin_data( __FILE__ );

    // Build and return the updater config
    return [
        // 1) Plugin’s WP slug (folder/file path under wp‑content/plugins)
        'slug'               => plugin_basename( __FILE__ ),

        // 2) Folder name on disk
        'proper_folder_name' => dirname( plugin_basename( __FILE__ ) ),

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
        'tested'             => '6.0',
        'readme'             => 'README.md',

        // 6) Which file to read “Version:” from
        'plugin_starter_file'=> basename( __FILE__ ),

        // 7) Metadata pulled straight from the plugin header
        'plugin_name'        => $plugin_data['Name'],
        'version'            => $plugin_data['Version'],
        'author'             => $plugin_data['Author'],
        'homepage'           => $plugin_data['PluginURI'],
        'description'        => $plugin_data['Description'],
    ];
}
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

// Define global variables
//global $api_url, $plugin_github_url, $plugin_zip_url, $wordpress_version_tested, $plugin_name, $github_access_token, $author_name, $author_uri, $plugin_uri, $plugin_version;

$plugin_name = "Hexa Web Systems - Website Base Tool";
$plugin_description = "Basic tools for optimization, performance, and debugging on Hexa based web systems.";
$author_name = "Michael Peres";
$plugin_uri = "https://github.com/mikeyperes/hws-base-tools";
$plugin_version = "3.5";
$author_uri = "https://michaelperes.com";
$api_url = "https://api.github.com/repos/mikeyperes/hws-base-tools";
$plugin_github_url = "https://github.com/mikeyperes/hws-base-tools";
$plugin_zip_url = "https://github.com/mikeyperes/hws-base-tools/archive/main.zip";
$wordpress_version_tested = "6.0";
$github_access_token = ''; // Leave empty if not required for private repositories





/**
 * Initialize GitHub Updater only after plugins have loaded and i18n is ready.
 */
add_action( 'admin_init', function() {
    include_once("GitHub_Updater.php");
    // Initialize using the new abstract function - much cleaner!
    // @since 8.9.5.3 - Refactored to use abstract hws_init_github_updater()
    hws_init_github_updater([
        'plugin_file'   => __FILE__,
        'github_repo'   => 'mikeyperes/hws-base-tools',
        'github_branch' => 'main',
        'requires'      => '5.0',
        'tested'        => '6.4',
        // 'access_token' => '', // Uncomment for private repos
    ]);

    // if you still want your “force‐update‐check” debug hook:
    if ( isset( $_GET['force-update-check'] ) ) {
        wp_clean_update_cache();
        set_site_transient( 'update_plugins', null );
        wp_update_plugins();
        error_log( 'WP_GitHub_Updater: Forced plugin update check triggered.' );
    }
} );





// Array of plugins to check
$plugins_to_check = [
    'advanced-custom-fields-pro/acf.php',
    'advanced-custom-fields-pro-temp/acf.php'
];

// Initialize flags for active status
$acf_active = false;

// Check if any of the plugins is active
foreach ($plugins_to_check as $plugin) {
    list($installed, $active) = check_plugin_status($plugin);
    if ($active) {
        $acf_active = true;
        break; // Stop checking once we find an active one
    }
}

// If none of the ACF plugins are active, display a warning and prevent the plugin from running
if (!$acf_active && is_admin()) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>HWS - Base Tools:</strong> The Advanced Custom Fields (ACF) or Advanced Custom Fields Pro (ACF Pro) plugin is required and must be active to use this plugin. Please activate ACF or ACF Pro.</p></div>';
    });
    return; // Stop further execution of the plugin
}

// Hook to acf/init to ensure ACF is initialized before running any ACF-related code
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
hws_boot_structured_admin_modules();

// Build Dashboard - New modular structure
include_once("settings-dashboard.php");           // Main dashboard with tabs
include_once("settings-dashboard-system-checks.php");  // System checks (restored original)
include_once("settings-dashboard-check-plugins.php");  // Plugin status monitoring
include_once("settings-dashboard-config.php");    // Configuration & PHP info
include_once("settings-dashboard-backups.php");   // Backup detection & cleanup
include_once("settings-dashboard-log-delete-cron.php");  // Log file cleaner
include_once("settings-dashboard-elementor-db-cron.php"); // Elementor DB auto-updater
include_once("settings-dashboard-snippets.php");
include_once("settings-dashboard-website-types.php");  // Website type presets
include_once("settings-dashboard-footer-text.php");    // Footer text module settings
include_once("settings-dashboard-ui-cleanup.php");     // UI Cleanup (hide profile elements)
include_once("settings-dashboard-theme-checks.php");
include_once("settings-dashboard-plugin-info.php");
include_once("settings-dashboard-rank-math-settings.php");
include_once("settings-dashboard-shortcode-tests.php");

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
