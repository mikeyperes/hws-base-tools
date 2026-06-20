<?php
/*
Plugin Name: Hexa Web Systems - Website Base Tool
Description: Basic tools for optimization, performance, and debugging on Hexa-based web systems.
Author: Michael Peres
Plugin URI: https://github.com/mikeyperes/hws-base-tools
Version: 10.18.39
Text Domain: hws-base-tools
Domain Path: /languages
Author URI: https://michaelperes.com
GitHub Plugin URI: https://github.com/mikeyperes/hws-base-tools/
GitHub Branch: main
*/

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'HWS_BASE_TOOLS_CANONICAL_PLUGIN_FILE' ) ) {
    define( 'HWS_BASE_TOOLS_CANONICAL_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'HWS_BASE_TOOLS_LEGACY_PLUGIN_FILE' ) ) {
    define( 'HWS_BASE_TOOLS_LEGACY_PLUGIN_FILE', __DIR__ . '/initialization.php' );
}

require_once __DIR__ . '/initialization.php';
