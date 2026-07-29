<?php

namespace HWS\BaseTools\AdminDashboard;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use HWS\BaseTools\PluginRuntime\PluginMetadata;

final class DashboardAssets implements ModuleInterface {
    private const MEDIA_TABS = [ 'brand-assets', 'footer-text' ];

    private const EDITOR_TABS = [ 'footer-text' ];

    public function register(): void {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
        add_action( 'admin_footer', [ $this, 'render_navigation_guard' ], 1 );
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

        $tab = $this->current_tab();

        if ( in_array( $tab, self::MEDIA_TABS, true ) ) {
            wp_enqueue_media();
        }

        if ( in_array( $tab, self::EDITOR_TABS, true ) ) {
            if ( function_exists( 'wp_enqueue_editor' ) ) {
                wp_enqueue_editor();
            }

            wp_enqueue_script( 'editor' );
            wp_enqueue_script( 'quicktags' );
        }
    }

    public function render_navigation_guard(): void {
        if ( ! $this->is_dashboard_page() ) {
            return;
        }
        ?>
        <script id="hws-dashboard-asset-navigation">
        (function(){
            var fullLoadTabs = <?php echo wp_json_encode( array_values( array_unique( array_merge( self::MEDIA_TABS, self::EDITOR_TABS ) ) ) ); ?>;
            document.addEventListener('click', function(event){
                var link = event.target.closest('[data-hpc-host-tab]');
                if (!link) return;
                var tab = link.getAttribute('data-hpc-host-tab') || '';
                var root = link.closest('[data-hpc-tab-root]');
                if (fullLoadTabs.indexOf(tab) === -1 || (root && root.dataset.activeTab === tab)) return;
                event.preventDefault();
                event.stopImmediatePropagation();
                window.location.assign(link.href);
            }, true);
        })();
        </script>
        <?php
    }

    private function is_dashboard_page(): bool {
        $page = isset( $_GET['page'] ) && ! is_array( $_GET['page'] )
            ? sanitize_key( wp_unslash( $_GET['page'] ) )
            : '';

        return PluginMetadata::ADMIN_PAGE_SLUG === $page;
    }

    private function current_tab(): string {
        $tab = isset( $_GET['tab'] ) && ! is_array( $_GET['tab'] )
            ? sanitize_key( wp_unslash( $_GET['tab'] ) )
            : '';

        return '' !== $tab ? $tab : 'overview';
    }
}
