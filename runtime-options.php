<?php

namespace hws_base_tools;

use HWS\BaseTools\Core\Autoloader;
use HWS\BaseTools\Core\RuntimeOptions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( RuntimeOptions::class ) ) {
    $autoloader = __DIR__ . '/src/Core/Autoloader.php';

    if ( is_readable( $autoloader ) ) {
        require_once $autoloader;
        Autoloader::register( __DIR__ . '/src' );
    }
}

/**
 * Seed runtime options so secret routes always depend on explicit DB state.
 */
function hws_seed_runtime_options(): void {
    RuntimeOptions::seed_defaults();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\hws_seed_runtime_options', 1 );

/**
 * Normalize yes/no style options to booleans.
 */
function hws_option_is_enabled( string $option_name, bool $default = false ): bool {
    return RuntimeOptions::option_is_enabled( $option_name, $default );
}

/**
 * Get the master secret, generating and storing one when missing.
 */
function hws_get_master_secret(): string {
    return RuntimeOptions::get_master_secret();
}
