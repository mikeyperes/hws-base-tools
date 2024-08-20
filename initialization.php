<?php

// Define global variables
global $api_url, $plugin_github_url, $plugin_zip_url, $wordpress_version_tested, $plugin_name, $github_access_token, $author_name, $author_uri, $plugin_uri, $plugin_version;

$plugin_name = "Hexa Web Systems - Website Base Tool";
$plugin_description = "Basic tools for optimziation, performance, and debugging on Hexa based web systems.";
$author_name = "Michael Peres";
$plugin_uri = "https://github.com/mikeyperes/hws-base-tools";
$plugin_version = "1.0.0";
$author_uri = "https://michaelperes.com";
$api_url = "https://api.github.com/repos/mikeyperes/hws-base-tools";
$plugin_github_url = "https://github.com/mikeyperes/hws-base-tools/";
$plugin_zip_url = "https://github.com/mikeyperes/hws-base-tools/archive/main.zip";
$wordpress_version_tested = "6.0";
$github_access_token = ''; // Leave empty if not required for private repositories

/*
Plugin Name: <?php echo $plugin_name; ?>
Description: <?php echo $plugin_description; ?>
Author: <?php echo $author_name; ?>
Plugin URI: <?php echo $plugin_uri; ?>
Version: <?php echo $plugin_version; ?>
Author URI: <?php echo $author_uri; ?>
GitHub Plugin URI: <?php echo $plugin_github_url; ?>
GitHub Branch: main
*/

// Ensure this file is being included by a parent file
defined('ABSPATH') or die('No script kiddies please!');

// Generic functions import
include_once("generic-functions.php");

// Check if ACF is installed and active using the generic function
list($acf_installed, $acf_active, $acf_auto_update) = check_plugin_status('advanced-custom-fields/acf.php');

// If ACF is not active, display a warning and prevent the plugin from running
if (!$acf_active) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>Verified Profiles - Scale My Publication:</strong> The Advanced Custom Fields (ACF) plugin is required and must be active to use this plugin. Please activate ACF.</p></div>';
    });

    // Stop further execution of the plugin
    return;
}

// Precheck WordPress is set up correctly
include_once("wordpress-pre-check.php");

// Import ACF Fields for wp-admin settings page
include_once("register-acf-fields-settings-page.php");

// Import ACF Fields
include_once("register-acf-fields.php");

// Precheck WordPress is set up correctly
include_once("initiate-user-roles.php");

// Build Dashboard
include_once("settings-dashboard.php");
// Settings sub-pages
include_once("settings-dashboard-wp-config.php");
include_once("settings-dashboard-log-delete-cron.php");

// Functionality to process empty Pages and Jet Engine Listing Grids
include_once("create-pages-and-listing-grids.php");

// Run updater check
// include_once("plugin-updater.php");

// Include the WP_GitHub_Updater class file
if (file_exists(plugin_dir_path(__FILE__) . 'GitHub_Updater.php')) {
    require_once(plugin_dir_path(__FILE__) . 'GitHub_Updater.php');
} else {
    error_log('WP_GitHub_Updater.php file is missing.');
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