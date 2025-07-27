<?php namespace hws_base_tools;
/*
Plugin Name: Hexa Web Systems - Website Base Tool
Description: Basic tools for optimization, performance, and debugging on Hexa-based web systems.
Author: Michael Peres
Plugin URI: https://github.com/mikeyperes/hws-base-tools
Version: 8.3
Text Domain: hws-base-tools
Domain Path: /languages
Author URI: https://michaelperes.com
GitHub Plugin URI: https://github.com/mikeyperes/hws-base-tools/
GitHub Branch: main 
*/  




// Ensure this file is being included by a parent file
defined('ABSPATH') or die('No script kiddies please!');

//if (!is_admin()) return;

class Config {
    public static $settings_page_name = "HWS Base Tools";
    public static $settings_page_capability = "manage_options";
    public static $settings_page_slug = "hws-core-tools";
    public static $settings_page_display_title = "Hexa Core Tools - WP-Config Settings";

    public static $plugin_name = "Hexa PR Wire - Distributor";
    public static $plugin_starter_file = "initialization.php";
public static $plugin_slug =  "hws-core-tools";



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
    $updater = new WP_GitHub_Updater( Config::get_github_config() );

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

include_once("smp-core/register-acf-user.php");
include_once("smp-core/register-acf-organization.php");
include_once("smp-core/register-acf-team-member.php");

//include_once("scale-my-podcast/register-acf-post-podcast.php");



activate_snippets("acf");

}, 5 );


//register_acf_rss();




add_action('init', function() { 

   
if (is_admin()){


include_once("helper.php");

// Build Dashboard
include_once("settings-dashboard.php");
// Settings sub-pages
include_once("settings-dashboard-wp-config.php");
include_once("settings-dashboard-log-delete-cron.php");
include_once("settings-dashboard-snippets.php");
include_once("settings-dashboard-plugin-checks.php");
include_once("settings-dashboard-system-checks.php");
include_once("settings-dashboard-theme-checks.php");
include_once("settings-dashboard-php-ini.php");
include_once("settings-dashboard-plugin-info.php");
include_once("settings-dashboard-php-libraries.php");
include_once("settings-dashboard-rank-math-settings.php");

// Set up event handling (click listeners and handlers)
include_once("settings-event-handling.php");

include_once("snippet-allow-svg-upload.php");
include_once("snippet-smp-display-ads.php");
include_once("snippet-rss.php");
include_once("snippet-comments.php");
include_once("snippet-acf-migration-structures.php");
include_once("snippet-website-settings-functionality.php");



activate_snippets("admin");
}


include_once("snippet-seo-rss.php");
include_once("snippet-seo-amp.php");

//include_once("shortcodes.php");
add_shortcode( 'website_url', __NAMESPACE__ . '\\website_url_shortcode' );

activate_snippets("non_admin");



},5);















function get_snippets($type = "")
{

    $snippets_acf = [
        [
            'id' => 'register_acf_website_settings',
            'name' => 'Register Website Settings Page (theme options and acf structures)',
            'description' => '',
            'info' => display_acf_structure('group_6842076add7ad'),
            'function' => 'register_acf_website_settings',
            'scope_admin_only' => false
            
        ],
        [
            'id' => 'smp_enable_cpt_teammember',
            'name' => 'SMP: Enable Team Member CPT',
            'description' => '',
            'info' => display_cpt_structure('team-member'),
            'function' => 'enable_smp_cpt_teammember',
            'scope_admin_only' => false
        ],

        [
            'id' => 'smp_enable_cpt_organization',
            'name' => 'SMP: Enable Organizations CPT',
            'description' => '',
            'info' => display_cpt_structure('organization'),
            'function' => 'enable_smp_cpt_organization',
            'scope_admin_only' => false
        ],
        [
            'id' => 'smp_enable_acf_organization',
            'name' => 'SMP: Enable Organizations ACFs',
            'description' => '',
  
            'info'        =>  display_acf_structure('group_64bc3b458d863'),
            'function' => 'enable_smp_acf_organization',
            'scope_admin_only' => false
        ],
        
 

        [
            'id' => 'smp_enable_acf_teammember',
            'name' => 'SMP: Enable Team Member ACFs',
            'description' => '',
            'info' => '',
            'info'        =>  display_acf_structure('group_64b3a05760b1a'),
            'function' => 'enable_smp_acf_teammember',
            'scope_admin_only' => false
        ],

        [
            'id' => 'register_user_custom_fields_2025',
            'name' => 'Enable user.php acf fields - 2025',
            'description' => '',
            'info' => '',
            'info'        =>  display_acf_structure('group_684252fd99081'),
            'function' => 'register_user_custom_fields_2025',
            'scope_admin_only' => false
        ],
        [
            'name'        => 'Enable Author Social ACFs',
            'id'          => 'register_user_custom_fields',
            'function'    => 'register_user_custom_fields',
            'description' => 'This will enable social media fields in author profiles.',
            'info'        =>  display_acf_structure('group_590d64c31db0a',true),
            'scope_admin_only' => false
        ]
    ];


    $snippets_admin = [
    
    [  
        'id' => 'enable_website_settings_functionality',
        'name' => 'Website Theme Settings Functionality',
        'description' => '',
        'info' => '',
        'function' => 'enable_website_settings_functionality',
        'scope_admin_only' => false
    ],
    [
        'id' => 'register_sponsored_functionality',
        'name' => 'register_sponsored_functionality',
        'description' => 'Add sponsored ACF to posts',
        'info' => '',
        'function' => 'register_acf_sponsored_functionality',
        'scope_admin_only' => true
    ],
    [
        'id' => 'enable_comments_management',
        'name' => 'Enable Comments Functionality',
        'description' => '',
        'info' => '',
        'function' => 'enable_comments_management',
        'scope_admin_only' => true
    ],
    [
        'id' => 'enable_custom_rss_functionality',
        'name' => 'Enable Custom RSS Functionality',
        'description' => 'Enable the custom RSS feed functionality based on registered post types and categories.',
        'info' => 'Once this is selected, custom RSS feeds will be generated for the specified post types and categories defined in the ACF settings.',
        'function' => 'enable_custom_rss_functionality',
        'scope_admin_only' => true
    ],
    [
        'id' => 'activate_smp_pushads_functionality',
        'name' => 'Activate SMP PushAds Functionality',
        'description' => 'Activates the SMP PushAds functionality, including ad codes and shortcodes for ad display.',
        'info' => 'Shortcodes Example: [smp_display_ad ad_type="banner"], [smp_display_ad ad_type="sidebar"]. <a href="' . esc_url(admin_url('admin.php?page=display-ads-smp')) . '" target="_blank">Click here to configure ACF fields</a>',
        'function' => 'activate_snippet_smp_display_ads',
        'scope_admin_only' => true
    ],
    [
        'id' => 'enable_auto_update_plugins',
        'name' => 'Enable Automatic Updates for Plugins',
        'description' => 'Enables automatic updates for all plugins.',
        'info' => 'Automatically keeps your plugins up to date.',
        'function' => 'enable_auto_update_plugins',
        'scope_admin_only' => true
    ],
    [
        'id' => 'enable_auto_update_themes',
        'name' => 'Enable Automatic Updates for Themes',
        'description' => 'Enables automatic updates for all themes.',
        'info' => 'Automatically keeps your themes up to date.',
        'function' => 'enable_auto_update_themes',
        'scope_admin_only' => true
    ],
    [
        'id' => 'disable_litespeed_js_combine',
        'name' => 'Disable JS Combine in LiteSpeed Cache',
        'description' => 'Disables JS combining in LiteSpeed Cache.',
        'info' => 'Prevents LiteSpeed from combining JavaScript files, which can be useful for resolving issues with script loading.',
        'function' => 'disable_litespeed_js_combine',
        'scope_admin_only' => true
    ],

    [
        'id' => 'snippet_enable_svg_uploads',
        'name' => 'Allow SVG uploads',
        'description' => '',
        'info' => '',
        'function' => 'snippet_enable_svg_uploads',
        'scope_admin_only' => true
    ]

];


    $snippet_non_admin = [

        

[
    'id' => 'enable_seo_amp_no_index',
    'name' => 'enable_seo_amp_no_index',
    'description' => 'enable_seo_amp_no_index',
    'info' => 'enable_seo_amp_no_index',
    'function' => 'enable_seo_amp_no_index',
    'scope_admin_only' => false
],    

[
    'id' => 'enable_seo_feeds_no_index',
    'name' => 'enable_seo_feeds_no_index',
    'description' => 'enable_seo_feeds_no_index',
    'info' => 'enable_seo_feeds_no_index',
    'function' => 'enable_seo_feeds_no_index',
    'scope_admin_only' => false
],


    [
        'id' => 'disable_rankmath_sitemap_caching',
        'name' => 'Disable RankMath Sitemap Caching',
        'description' => 'Disables caching for RankMath sitemaps.',
        'info' => 'This prevents RankMath from caching sitemaps, which can be useful for development or debugging.',
        'function' => 'disable_rankmath_sitemap_caching',
        'scope_admin_only' => false
    ],
    [
        'id' => 'enable_wp_admin_logo',
        'name' => 'Enable WP Admin Logo',
        'description' => 'Enable a custom logo on the WP admin login screen using ACF.',
        'info' => function() {
            $logo_url = get_site_icon_url(); // Ensure the logo URL is retrieved
            $thumbnail = $logo_url ? '<img src="' . esc_url($logo_url) . '" style="max-width:100px; display:block; margin-top:10px;" alt="Custom Logo Thumbnail" onclick="event.stopPropagation();">' : '';
    
            if ($logo_url) {
                return 'This will use the logo from the Site Icon.<br>' . 
                       '<span onclick="event.stopPropagation();">' . $thumbnail . '</span><br>' .
                       '<a href="' . esc_url($logo_url) . '" target="_blank">View Image</a><br>' . 
                       '<a href="' . esc_url(admin_url('options-general.php')) . '" target="_blank">View in Site Identity Settings</a>';
            } else {
                return 'No site icon is set. Please set a site icon in the Site Identity settings.';
            }
        },
        'function' => 'custom_wp_admin_logo',
        'scope_admin_only' => false,
    ]
];

if ($type === 'non_admin') 
    return $snippet_non_admin;
if ($type === 'admin') 
    return $snippets_admin;

return $snippets_acf;
/* [
            'id' => 'smp_enable_acf_user',
            'name' => 'OLD DELETE - SMP: Enable User ACFs',
            'description' => '',
            'function' => 'enable_smp_acf_user',
            'info'        =>  display_acf_structure(['group_65a8b18d98147','group_6419bc02b6e93'],true),


        ],*/
        /*
        [
            'id' => 'disable_wordpress_comments_forward',
            'name' => 'Disable WordPress Comments',
            'description' => 'Disable comments for all new posts and pages. This does not affect previously created content.',
            'info' => 'Once this is selected, all new posts and pages will have comments disabled by default. To disable comments on existing posts and pages, please use the appropriate setting in the options.',
            'function' => 'disable_wordpress_comments_forward'
        ],*/
    
}



?>