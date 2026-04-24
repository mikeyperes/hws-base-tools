<?php

namespace hws_base_tools;

use HWS\BaseTools\Admin\Dashboard\LegacyEventBridge;
use HWS\BaseTools\Core\Autoloader;

if ( ! class_exists( LegacyEventBridge::class ) ) {
    $autoloader = __DIR__ . '/src/Core/Autoloader.php';

    if ( is_readable( $autoloader ) ) {
        require_once $autoloader;
        Autoloader::register( __DIR__ . '/src' );
    }
}

if ( class_exists( LegacyEventBridge::class ) ) {
    LegacyEventBridge::register_hooks();
}

function activate_listeners() {
    if ( class_exists( LegacyEventBridge::class ) ) {
        LegacyEventBridge::render_admin_head_script();
    }
}

function modify_wp_config_constants_handler() {
    if ( class_exists( LegacyEventBridge::class ) ) {
        LegacyEventBridge::handle_modify_wp_config_constants();
        return;
    }

    wp_send_json_error( [ 'message' => 'Legacy event bridge is unavailable.' ] );
}

if ( ! function_exists( __NAMESPACE__ . '\\toggle_snippet' ) ) {
    function toggle_snippet() {
        if ( class_exists( LegacyEventBridge::class ) ) {
            LegacyEventBridge::handle_toggle_snippet();
            return;
        }

        wp_send_json_error( 'Legacy event bridge is unavailable.' );
    }
}
