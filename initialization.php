<?php

/**
 * Legacy plugin entry retained for installs still activated as
 * hws-base-tools/initialization.php.
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'HWS_BASE_TOOLS_BOOTSTRAPPED' ) ) {
    return;
}

if ( ! defined( 'HWS_BASE_TOOLS_LEGACY_PLUGIN_FILE' ) ) {
    define( 'HWS_BASE_TOOLS_LEGACY_PLUGIN_FILE', __FILE__ );
}

require_once __DIR__ . '/hws-base-tools.php';
