<?php

namespace HWS\BaseTools\AdminDashboard;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use HWS\BaseTools\PluginRuntime\PluginMetadata;

final class DashboardAssets implements ModuleInterface {
    public function register(): void {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
    }

    public function enqueue(): void {
        $page = isset( $_GET['page'] ) && ! is_array( $_GET['page'] )
            ? sanitize_key( wp_unslash( $_GET['page'] ) )
            : '';

        if ( PluginMetadata::ADMIN_PAGE_SLUG !== $page ) {
            return;
        }

        wp_enqueue_style(
            'hws-base-tools-dashboard',
            plugin_dir_url( PluginMetadata::plugin_file() ) . 'assets/admin/dashboard.css',
            [],
            PluginMetadata::VERSION
        );

        // AJAX tabs can open media/editor-based sections after enqueue time.
        // Load these WordPress-owned dependencies once for the HWS workspace.
        wp_enqueue_media();
        if ( function_exists( 'wp_enqueue_editor' ) ) {
            wp_enqueue_editor();
        }
        wp_enqueue_script( 'editor' );
        wp_enqueue_script( 'quicktags' );
    }
}
