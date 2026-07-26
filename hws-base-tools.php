<?php
/*
Plugin Name: Hexa Web Systems - Website Base Tool
Description: Basic tools for optimization, performance, and debugging on Hexa-based web systems.
Author: Michael Peres
Plugin URI: https://github.com/mikeyperes/hws-base-tools
Version: 10.18.147
Requires at least: 6.0
Requires PHP: 8.1
Text Domain: hws-base-tools
Domain Path: /languages
Author URI: https://michaelperes.com
GitHub Plugin URI: https://github.com/mikeyperes/hws-base-tools/
GitHub Branch: main
*/

defined( 'ABSPATH' ) || exit;

if ( defined( 'HWS_BASE_TOOLS_BOOTSTRAPPED' ) ) {
    return;
}

define( 'HWS_BASE_TOOLS_BOOTSTRAPPED', true );

if ( ! defined( 'HWS_BASE_TOOLS_DIR' ) ) {
    define( 'HWS_BASE_TOOLS_DIR', __DIR__ );
}

if ( ! defined( 'HWS_BASE_TOOLS_CANONICAL_PLUGIN_FILE' ) ) {
    define( 'HWS_BASE_TOOLS_CANONICAL_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'HWS_BASE_TOOLS_LEGACY_PLUGIN_FILE' ) ) {
    define( 'HWS_BASE_TOOLS_LEGACY_PLUGIN_FILE', __DIR__ . '/initialization.php' );
}

require_once __DIR__ . '/src/PluginRuntime/Autoloader.php';

\HWS\BaseTools\PluginRuntime\Autoloader::register( __DIR__ . '/src' );

$hexa_plugin_core_root = __DIR__ . '/lib/hexa-wordpress-plugin-core';
require_once $hexa_plugin_core_root . '/bootstrap.php';
\hexa_plugin_core_register_package( 'hws-base-tools', $hexa_plugin_core_root );

if ( function_exists( 'did_action' ) && did_action( 'plugins_loaded' ) ) {
    \HWS\BaseTools\PluginRuntime\Bootstrap::boot();
} else {
    add_action( 'plugins_loaded', [ \HWS\BaseTools\PluginRuntime\Bootstrap::class, 'boot' ], -PHP_INT_MAX + 100 );
}
