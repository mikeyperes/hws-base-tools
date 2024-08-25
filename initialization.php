<?php
/*
Plugin Name: Hexa Web Systems - Website Base Tool
Description: Basic tools for optimization, performance, and debugging on Hexa-based web systems.
Author: Michael Peres
Plugin URI: https://github.com/mikeyperes/hws-base-tools
Version: 1.0.1
Author URI: https://michaelperes.com
GitHub Plugin URI: https://github.com/mikeyperes/hws-base-tools/
GitHub Branch: main
*/ 

// Ensure this file is being included by a parent file
defined('ABSPATH') or die('No script kiddies please!');

// Define global variables
global $api_url, $plugin_github_url, $plugin_zip_url, $wordpress_version_tested, $plugin_name, $github_access_token, $author_name, $author_uri, $plugin_uri, $plugin_version;

$plugin_name = "Hexa Web Systems - Website Base Tool";
$plugin_description = "Basic tools for optimization, performance, and debugging on Hexa based web systems.";
$author_name = "Michael Peres";
$plugin_uri = "https://github.com/mikeyperes/hws-base-tools";
$plugin_version = "1.0.1";
$author_uri = "https://michaelperes.com";
$api_url = "https://api.github.com/repos/mikeyperes/hws-base-tools";
$plugin_github_url = "https://github.com/mikeyperes/hws-base-tools/";
$plugin_zip_url = "https://github.com/mikeyperes/hws-base-tools/archive/main.zip";
$wordpress_version_tested = "6.0";
$github_access_token = ''; // Leave empty if not required for private repositories


// Generic functions import
include_once("generic-functions.php");

// Check if ACF or ACF Pro is installed and active using the generic function
list($acf_installed, $acf_active) = check_plugin_status('advanced-custom-fields/acf.php');
list($acf_pro_installed, $acf_pro_active) = check_plugin_status('advanced-custom-fields-pro/acf.php');

// If neither ACF nor ACF Pro is active, display a warning and prevent the plugin from running
if (!$acf_active && !$acf_pro_active) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>HWS - Base Tools:</strong> The Advanced Custom Fields (ACF) or Advanced Custom Fields Pro (ACF Pro) plugin is required and must be active to use this plugin. Please activate ACF or ACF Pro.</p></div>';
    });

    // Stop further execution of the plugin
    return;
}



function hws_ct_get_settings_snippets()
{
    $settings_snippets = [
        [
            'id' => 'disable_rankmath_sitemap_caching',
            'name' => 'Disable RankMath Sitemap Caching',
            'description' => 'Disables caching for RankMath sitemaps.',
            'info' => 'This prevents RankMath from caching sitemaps, which can be useful for development or debugging.',
            'function' => 'disable_rankmath_sitemap_caching'
        ],
        [
            'id' => 'enable_auto_update_plugins',
            'name' => 'Enable Automatic Updates for Plugins',
            'description' => 'Enables automatic updates for all plugins.',
            'info' => 'Automatically keeps your plugins up to date.',
            'function' => 'enable_auto_update_plugins'
        ],
        [
            'id' => 'enable_wp_admin_logo',
            'name' => 'Enable WP Admin Logo',
            'description' => 'Enable a custom logo on the WP admin login screen using ACF.',
            'info' => 'This will use the logo from the ACF field "login_logo".',
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
            'id' => 'custom_wp_admin_logo',
            'name' => 'Custom WP Admin Logo',
            'description' => 'Adds a custom logo to the WP admin login screen.',
            'info' => 'Allows you to upload a custom logo via ACF and display it on the login page.',
            'function' => 'custom_wp_admin_logo'
        ],
        
    [
        'name' => 'Enable Author Social ACFs',
        'id' => 'hws_ct_snippets_author_social_acfs',
         'function' => 'hws_ct_snippets_activate_author_social_acfs',
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
return $settings_snippets; 
}



// Precheck WordPress is set up correctly
include_once("wordpress-pre-check.php");

// Import ACF Fields for wp-admin settings page
include_once("register-acf-fields-settings-page.php");

// Import ACF Fields
include_once("register-acf-fields.php");
// Import ACF Fields
include_once("register-acf-fields-user.php");

// Build Dashboard
include_once("activate-snippets.php");

// Precheck WordPress is set up correctly
include_once("initiate-user-roles.php");

// Build Dashboard
include_once("settings-dashboard.php");
// Settings sub-pages
include_once("settings-dashboard-wp-config.php");
include_once("settings-dashboard-log-delete-cron.php");
include_once("settings-dashboard-snippets.php");

// Functionality to process empty Pages and Jet Engine Listing Grids
include_once("create-pages-and-listing-grids.php");

// Run updater check
// include_once("plugin-updater.php");

// Include the WP_GitHub_Updater class file
if (file_exists(plugin_dir_path(__FILE__) . 'GitHub_Updater.php')) {
    require_once(plugin_dir_path(__FILE__) . 'GitHub_Updater.php');
} else {
    write_log('WP_GitHub_Updater.php file is missing.');
}

// Initialize the updater
if (is_admin()) { // Ensure this runs only in the admin area

    $config = array(
        'slug' => plugin_basename(__FILE__), // Plugin slug
        'proper_folder_name' => dirname(plugin_basename(__FILE__)), // Proper folder name
        'sslverify' => true, // SSL verification for the download
        'api_url' => $api_url, // GitHub API URL
        'raw_url' => $plugin_github_url . 'main', // Raw GitHub URL
        'github_url' => $plugin_github_url, // GitHub repository URL
        'zip_url' => $plugin_zip_url, // Zip URL for the latest version
        'requires' => '5.0', // Minimum required WordPress version
        'tested' => $wordpress_version_tested, // Tested up to WordPress version
        'readme' => 'README.md', // Readme file for version checking
    );

    $updater = new WP_GitHub_Updater($config);
}