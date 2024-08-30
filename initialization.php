<?php
/*
Plugin Name: Hexa Web Systems - Website Base Tool
Description: Basic tools for optimization, performance, and debugging on Hexa-based web systems.
Author: Michael Peres
Plugin URI: https://github.com/mikeyperes/hws-base-tools
Version: 2.5.13
Author URI: https://michaelperes.com
GitHub Plugin URI: https://github.com/mikeyperes/hws-base-tools/
GitHub Branch: main
*/

namespace hws_base_tools;

// Generic functions import
include_once("generic-functions.php");

// Use function for easy access without namespace prefix
use function hws_base_tools\check_plugin_status;

// Ensure this file is being included by a parent file
defined('ABSPATH') or die('No script kiddies please!');

// Define global variables with hardcoded values
global $api_url, $plugin_github_url, $plugin_zip_url, $wordpress_version_tested, $plugin_name, $github_access_token, $author_name, $author_uri, $plugin_uri, $plugin_version;

$plugin_name = "Hexa Web Systems - Website Base Tool";
$plugin_description = "Basic tools for optimization, performance, and debugging on Hexa based web systems.";
$author_name = "Michael Peres";
$plugin_uri = "https://github.com/mikeyperes/hws-base-tools";
$plugin_version = "2.5.12";  // Make sure this is the correct version
$author_uri = "https://michaelperes.com";
$api_url = "https://api.github.com/repos/mikeyperes/hws-base-tools";
$plugin_github_url = "https://github.com/mikeyperes/hws-base-tools/";
$plugin_zip_url = "https://github.com/mikeyperes/hws-base-tools/archive/main.zip";
$wordpress_version_tested = "6.0";
$github_access_token = ''; // Leave empty if not required for private repositories

// Initialize the updater with hardcoded values
if (is_admin()) { // Ensure this runs only in the admin area
    $config = array(
        'slug' => 'hws-base-tools/hws-base-tools.php', // Plugin slug should match your directory and main file name
        'proper_folder_name' => 'hws-base-tools', // Proper folder name
        'sslverify' => true, // SSL verification for the download
        'api_url' => 'https://api.github.com/repos/mikeyperes/hws-base-tools', // GitHub API URL
        'raw_url' => 'https://raw.githubusercontent.com/mikeyperes/hws-base-tools/main', // Raw GitHub URL
        'github_url' => 'https://github.com/mikeyperes/hws-base-tools', // GitHub repository URL
        'zip_url' => 'https://github.com/mikeyperes/hws-base-tools/archive/main.zip', // Zip URL for the latest version
        'requires' => '5.0', // Minimum required WordPress version
        'tested' => '6.0', // Tested up to WordPress version
        'readme' => 'README.md', // Readme file for version checking
    );

    $updater = new \hws_base_tools\WP_GitHub_Updater($config);

    // Force update check for debugging purposes
    add_action('init', function() {
        if (is_admin() && isset($_GET['force-update-check'])) {
            wp_clean_update_cache();
            set_site_transient('update_plugins', null);
            wp_update_plugins();

            // Log to confirm the check has been triggered
            write_log('WP_GitHub_Updater: Forced plugin update check triggered.', "true");
        }
    });
}

// Ensure ACF or ACF Pro is installed and active
list($acf_installed, $acf_active) = check_plugin_status('advanced-custom-fields/acf.php');
list($acf_pro_installed, $acf_pro_active) = check_plugin_status('advanced-custom-fields-pro/acf.php');

if (!$acf_active && !$acf_pro_active) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>HWS - Base Tools:</strong> The Advanced Custom Fields (ACF) or Advanced Custom Fields Pro (ACF Pro) plugin is required and must be active to use this plugin. Please activate ACF or ACF Pro.</p></div>';
    });
    return;
}
?>