<?php

namespace hws_base_tools;

use HWS\BaseTools\AdminDashboard\LegacyEventBridge;
use HWS\BaseTools\PluginRuntime\Autoloader;

if ( ! class_exists( LegacyEventBridge::class ) ) {
    $autoloader = HWS_BASE_TOOLS_DIR . '/src/PluginRuntime/Autoloader.php';

    if ( is_readable( $autoloader ) ) {
        require_once $autoloader;
        Autoloader::register( HWS_BASE_TOOLS_DIR . '/src' );
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
