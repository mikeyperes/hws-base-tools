<?php namespace hws_base_tools;
/*
Plugin Name: Hexa Web Systems - Website Base Tool
Description: Basic tools for optimization, performance, and debugging on Hexa-based web systems.
Author: Michael Peres
Plugin URI: https://github.com/mikeyperes/hws-base-tools
Version: 3.6.9.3
Author URI: https://michaelperes.com
GitHub Plugin URI: https://github.com/mikeyperes/hws-base-tools/
GitHub Branch: main 
*/          

// Ensure this file is being included by a parent file
defined('ABSPATH') or die('No script kiddies please!');



class Config {
    public static $settings_page_name = "HWS Base Tools";
    public static $settings_page_capability = "manage_options";
    public static $settings_page_slug = "hws-core-tools";
    public static $settings_page_display_title = "Hexa Core Tools - WP-Config Settings";
}


// Generic functions import 
include_once("generic-functions.php");

// Define global variables
global $api_url, $plugin_github_url, $plugin_zip_url, $wordpress_version_tested, $plugin_name, $github_access_token, $author_name, $author_uri, $plugin_uri, $plugin_version;

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

// Include the GitHub Updater class
include_once("GitHub_Updater.php");

// Use the WP_GitHub_Updater class
use hws_base_tools\WP_GitHub_Updater;

// Initialize the updater
if (is_admin()) { // Ensure this runs only in the admin area

    $config = array(
        'slug' => plugin_basename(__FILE__), // Plugin slug
        'proper_folder_name' => 'hws-base-tools', // Proper folder name
        'api_url' => 'https://api.github.com/repos/mikeyperes/hws-base-tools', // GitHub API URL
        'raw_url' => 'https://raw.github.com/mikeyperes/hws-base-tools/main', // Raw GitHub URL
        'github_url' => 'https://github.com/mikeyperes/hws-base-tools', // GitHub repository URL
        'zip_url' => 'https://github.com/mikeyperes/hws-base-tools/archive/main.zip', // Zip URL for the latest version
        'sslverify' => true, // SSL verification for the download
        'requires' => '5.0', // Minimum required WordPress version
        'tested' => $wordpress_version_tested, // Tested up to WordPress version
        'readme' => 'README.md', // Readme file for version checking
        'access_token' => '', // Access token if required
    );

   // $updater = new WP_GitHub_Updater($config);

    // Trigger an update check for debugging
   /* add_action('init', function() {

        if (is_admin() && isset($_GET['force-update-check'])) {
            // Force WordPress to check for plugin updates
            wp_clean_update_cache();
            set_site_transient('update_plugins', null);
            wp_update_plugins();

            // Log to confirm the check has been triggered
            error_log('WP_GitHub_Updater: Forced plugin update check triggered.');
        }
    });
    */
}

// Array of plugins to check
$plugins_to_check = [
    'advanced-custom-fields/acf.php',
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
if (!$acf_active) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>HWS - Base Tools:</strong> The Advanced Custom Fields (ACF) or Advanced Custom Fields Pro (ACF Pro) plugin is required and must be active to use this plugin. Please activate ACF or ACF Pro.</p></div>';
    });
    return; // Stop further execution of the plugin
}

function hws_ct_get_settings_snippets()
{
    $settings_snippets = [
        [
            'id' => 'enable_comments_management',
            'name' => 'Enable Comments Functionality',
            'description' => '',
            'info' => '',
            'function' => 'enable_comments_management'
        ],
        [
            'id' => 'enable_custom_rss_functionality',
            'name' => 'Enable Custom RSS Functionality',
            'description' => 'Enable the custom RSS feed functionality based on registered post types and categories.',
            'info' => 'Once this is selected, custom RSS feeds will be generated for the specified post types and categories defined in the ACF settings.',
            'function' => 'enable_custom_rss_functionality'
        ],
        /*
        [
            'id' => 'disable_wordpress_comments_forward',
            'name' => 'Disable WordPress Comments',
            'description' => 'Disable comments for all new posts and pages. This does not affect previously created content.',
            'info' => 'Once this is selected, all new posts and pages will have comments disabled by default. To disable comments on existing posts and pages, please use the appropriate setting in the options.',
            'function' => 'disable_wordpress_comments_forward'
        ],*/
        [
            'id' => 'disable_rankmath_sitemap_caching',
            'name' => 'Disable RankMath Sitemap Caching',
            'description' => 'Disables caching for RankMath sitemaps.',
            'info' => 'This prevents RankMath from caching sitemaps, which can be useful for development or debugging.',
            'function' => 'disable_rankmath_sitemap_caching'
        ],
        
        [
            'id' => 'activate_smp_pushads_functionality',
            'name' => 'Activate SMP PushAds Functionality',
            'description' => 'Activates the SMP PushAds functionality, including ad codes and shortcodes for ad display.',
            'info' => 'Shortcodes Example: [smp_display_ad ad_type="banner"], [smp_display_ad ad_type="sidebar"]. <a href="' . esc_url(admin_url('admin.php?page=display-ads-smp')) . '" target="_blank">Click here to configure ACF fields</a>',
            'function' => 'activate_snippet_smp_display_ads'
        ],
        [
            'id' => 'enable_auto_update_plugins',
            'name' => 'Enable Automatic Updates for Plugins',
            'description' => 'Enables automatic updates for all plugins.',
            'info' => 'Automatically keeps your plugins up to date.',
            'function' => 'enable_auto_update_plugins'
        ],
        [
            'id' => 'enable_auto_update_themes',
            'name' => 'Enable Automatic Updates for Themes',
            'description' => 'Enables automatic updates for all themes.',
            'info' => 'Automatically keeps your themes up to date.',
            'function' => 'enable_auto_update_themes'
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
            'function' => 'custom_wp_admin_logo'
        ],
        [
            'id' => 'disable_litespeed_js_combine',
            'name' => 'Disable JS Combine in LiteSpeed Cache',
            'description' => 'Disables JS combining in LiteSpeed Cache.',
            'info' => 'Prevents LiteSpeed from combining JavaScript files, which can be useful for resolving issues with script loading.',
            'function' => 'disable_litespeed_js_combine'
        ],
        [
            'name' => 'Enable Author Social ACFs',
            'id' => 'register_user_custom_fields',
            'function' => 'register_user_custom_fields',
            'description' => 'This will enable social media fields in author profiles.',
            'info' => implode('<br>', array_map(function($field) {
                if ($field['type'] === 'group') {
                    $sub_fields = implode(', ', array_map(function($sub_field) {
                        return "{$sub_field['name']}";
                    }, $field['sub_fields']));
                    return "{$field['name']}<br>&emsp;{$sub_fields}";
                } else {
                    return "{$field['name']}";
                }
            }, acf_get_fields('group_590d64c31db0a')))
        ],
    ];

    // Ensure closure results are handled
    foreach ($settings_snippets as &$snippet) {
        if (is_callable($snippet['info'])) {
            $snippet['info'] = $snippet['info'](); // Execute closure and replace it with the returned value
        }
    }

    return $settings_snippets;
}


// Hook to acf/init to ensure ACF is initialized before running any ACF-related code
add_action('acf/init', function() {


// Import ACF Fields
include_once("register-acf-fields-user.php");
include_once("register-acf-fields-rss.php");
//register_acf_rss();


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
// Set up event handling (click listeners and handlers)
include_once("settings-event-handling.php");


// add snippets
include_once("snippet-smp-display-ads.php");
include_once("snippet-rss.php");
include_once("snippet-comments.php");

// Build Dashboard
include_once("activate-snippets.php");
});