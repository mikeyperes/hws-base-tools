<?php
/*
Plugin Name: Hexa Web Systems - Website Base Tool
Description: Basic tools for optimization, performance, and debugging on Hexa-based web systems.
Author: Michael Peres
Plugin URI: https://github.com/mikeyperes/hws-base-tools
Version: 10.18.119
Requires at least: 6.0
Requires PHP: 8.1
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

$hexa_plugin_core_root = __DIR__ . '/lib/hexa-wordpress-plugin-core';
require_once $hexa_plugin_core_root . '/bootstrap.php';
\hexa_plugin_core_register_package( 'hws-base-tools', $hexa_plugin_core_root );

require_once __DIR__ . '/initialization.php';
