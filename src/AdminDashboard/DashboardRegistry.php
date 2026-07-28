<?php

namespace HWS\BaseTools\AdminDashboard;

use Hexa\PluginCore\WpAdminTabs\TabDefinition;
use Hexa\PluginCore\WpAdminTabs\TabRegistry;
use HWS\BaseTools\PluginRuntime\PluginMetadata;

final class DashboardRegistry {
    private static ?self $instance = null;

    private TabRegistry $core_registry;

    /** @var array<string,DashboardModuleDefinition> */
    private array $modules = [];

    /** @var array<string,string> */
    private array $aliases = [
        'getting-started-checklist' => 'quick-start',
    ];

    private function __construct() {
        $this->core_registry = new TabRegistry();
        $this->register_defaults();
    }

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @return array<string,string|TabDefinition>
     */
    public function navigation_tabs(): array {
        $tabs = [];

        foreach ( $this->core_registry->all() as $id => $tab ) {
            $module = $this->modules[ $id ] ?? null;
            if ( $module && ! $module->is_visible() ) {
                continue;
            }

            $tabs[ $id ] = $tab;
        }

        return function_exists( 'apply_filters' )
            ? (array) apply_filters( 'hws_base_tools_dashboard_tabs', $tabs )
            : $tabs;
    }

    public function normalize( string $id ): string {
        $id = $this->sanitize_id( $id );
        $id = $this->aliases[ $id ] ?? $id;
        $tabs = $this->navigation_tabs();

        if ( ! array_key_exists( $id, $tabs ) ) {
            $id = (string) array_key_first( $tabs );
        }

        return $id;
    }

    /**
     * @return array<int,array{label:string,tabs:array<int,string>}>
     */
    public function navigation_groups(): array {
        $available = array_fill_keys( array_keys( $this->navigation_tabs() ), true );
        $assigned = [];
        $groups = [];
        $definitions = [
            'Overview' => [ 'overview', 'quick-start' ],
            'Site & Brand' => [ 'brand-assets', 'brand-templates', 'website-types', 'pages', 'menu-tools', 'footer-text' ],
            'Content' => [ 'custom-post-types', 'features', 'search', 'shortcodes', 'comments' ],
            'Operations' => [ 'plugins', 'system-checks', 'sitemaps', 'cleanup', 'backups', 'update-center' ],
            'Security' => [ 'masked-login' ],
            'WordPress Admin' => [ 'ui-cleanup', 'config', 'advanced', 'snippets', 'hexa-core' ],
        ];

        foreach ( $definitions as $label => $ids ) {
            $group_tabs = [];
            foreach ( $ids as $id ) {
                if ( isset( $available[ $id ] ) && ! isset( $assigned[ $id ] ) ) {
                    $group_tabs[] = $id;
                    $assigned[ $id ] = true;
                }
            }

            if ( $group_tabs ) {
                $groups[] = [ 'label' => $label, 'tabs' => $group_tabs ];
            }
        }

        $leftover = [];
        foreach ( array_keys( $available ) as $id ) {
            if ( ! isset( $assigned[ $id ] ) ) {
                $leftover[] = $id;
            }
        }

        if ( $leftover ) {
            $groups[] = [ 'label' => 'More', 'tabs' => $leftover ];
        }

        return function_exists( 'apply_filters' )
            ? (array) apply_filters( 'hws_base_tools_dashboard_tab_groups', $groups )
            : $groups;
    }

    public function load_tab( string $id ): void {
        $id = $this->aliases[ $id ] ?? $id;

        foreach ( $this->implementation_files_for_tab( $id ) as $file ) {
            $this->load_file( $file );
        }
    }

    public function load_ajax_action( string $action ): void {
        foreach ( $this->implementation_files_for_ajax_action( $action ) as $file ) {
            $this->load_file( $file );
        }
    }

    /** @return string[] */
    public function implementation_files_for_tab( string $id ): array {
        $id = $this->aliases[ $id ] ?? $id;
        $module = $this->modules[ $id ] ?? null;

        return $module ? $module->files : [];
    }

    /** @return string[] */
    public function implementation_files_for_ajax_action( string $action ): array {
        foreach ( $this->ajax_file_map() as $prefix => $files ) {
            if ( $action === $prefix || str_starts_with( $action, $prefix ) ) {
                return $files;
            }
        }

        return [];
    }

    public function render( string $id ): bool {
        $id = $this->normalize( $id );
        $this->load_tab( $id );

        if ( function_exists( 'apply_filters' ) && apply_filters( 'hws_base_tools_render_dashboard_tab', false, $id ) ) {
            return true;
        }

        $module = $this->modules[ $id ] ?? null;
        if ( ! $module || ! is_callable( $module->renderer ) ) {
            return false;
        }

        call_user_func( $module->renderer );

        return true;
    }

    public function label( string $id ): string {
        $tabs = $this->navigation_tabs();
        $tab  = $tabs[ $id ] ?? $id;

        if ( $tab instanceof TabDefinition ) {
            return $tab->label . ( $tab->deprecated ? ' (Deprecated)' : '' );
        }

        if ( is_array( $tab ) && isset( $tab['label'] ) ) {
            return (string) $tab['label'];
        }

        return (string) $tab;
    }

    private function register_defaults(): void {
        $this->add( new DashboardModuleDefinition( 'overview', 'Overview', 'hws_base_tools\\render_tab_overview', [
            'settings-dashboard-site-profile.php',
            'settings-dashboard-check-plugins.php',
            'settings-dashboard-backups.php',
            'settings-dashboard-log-delete-cron.php',
            'settings-dashboard-cleanup.php',
            'settings-dashboard-plugin-info.php',
        ] ) );
        $this->add( new DashboardModuleDefinition( 'quick-start', 'Quick Start', 'hws_base_tools\\display_settings_getting_started_checklist', [ 'settings-dashboard-getting-started.php' ] ) );
        $this->add( new DashboardModuleDefinition( 'brand-assets', 'Brand Assets', 'hws_base_tools\\render_tab_brand_assets' ) );
        $this->add( new DashboardModuleDefinition( 'brand-templates', 'Brand Templates', [ \HWS\BaseTools\BrandTemplates\BrandTemplatesAdmin::class, 'render' ] ) );
        $this->add( new DashboardModuleDefinition( 'pages', 'Pages', 'hws_base_tools\\display_settings_pages', [ 'settings-dashboard-pages.php' ] ) );
        $this->add( new DashboardModuleDefinition( 'features', 'Features', 'hws_base_tools\\display_settings_features', [
            'settings-dashboard-website-types.php',
            'settings-dashboard-features.php',
        ] ) );
        $this->add( new DashboardModuleDefinition( 'search', 'Search', 'hws_base_tools\\render_tab_search', [ 'settings-dashboard-search.php' ] ) );
        $this->add( new DashboardModuleDefinition( 'shortcodes', 'Shortcodes', [ \HWS\BaseTools\FeatureCatalog\ShortcodeCatalog::class, 'render' ] ) );
        $this->add( new DashboardModuleDefinition( 'plugins', 'Plugins', 'hws_base_tools\\render_tab_plugins', [ 'settings-dashboard-check-plugins.php', 'settings-dashboard-theme-checks.php' ] ) );
        $this->add( new DashboardModuleDefinition( 'system-checks', 'System Checks', 'hws_base_tools\\display_settings_system_checks', [ 'settings-dashboard-system-checks.php' ] ) );
        $this->add( new DashboardModuleDefinition( 'sitemaps', 'Sitemaps', 'hws_base_tools\\render_tab_sitemaps', [
            'settings-dashboard-site-profile.php',
            'settings-dashboard-sitemaps.php',
        ] ) );
        $this->add( new DashboardModuleDefinition( 'cleanup', 'Cleanup', 'hws_base_tools\\display_settings_cleanup', [ 'settings-dashboard-cleanup.php' ] ) );
        $this->add( new DashboardModuleDefinition( 'backups', 'Backups', 'hws_base_tools\\render_tab_backups', [ 'settings-dashboard-backups.php' ] ) );
        $this->add( new DashboardModuleDefinition( 'update-center', 'Update Center', 'hws_base_tools\\display_settings_update_center', [ 'settings-dashboard-update-center.php' ] ) );
        $this->add( new DashboardModuleDefinition( 'ui-cleanup', 'UI Cleanup', 'hws_base_tools\\display_settings_ui_cleanup', [ 'settings-dashboard-ui-cleanup.php' ] ) );
        $this->add( new DashboardModuleDefinition( 'website-types', 'Website & Primary Entity', [ \HWS\BaseTools\SiteProfile\PrimaryEntityIntegration::class, 'render' ] ) );
        $this->add( new DashboardModuleDefinition( 'custom-post-types', 'Custom Post Types', 'hws_base_tools\\render_tab_content_types', [ 'settings-dashboard-content-types.php' ] ) );
        $this->add( new DashboardModuleDefinition( 'footer-text', 'Footer Text', 'hws_base_tools\\display_settings_footer_text', [
            'settings-dashboard-website-types.php',
            'settings-dashboard-footer-text.php',
        ], false, static function(): bool {
            return (bool) get_option( 'enable_footer_text_auto_injection', false );
        } ) );
        $this->add( new DashboardModuleDefinition( 'menu-tools', 'Menu Tools', 'hws_base_tools\\display_settings_menu_tools', [ 'settings-dashboard-menu-tools.php' ] ) );
        $this->add( new DashboardModuleDefinition( 'comments', 'Comments', 'hws_base_tools\\display_settings_comments_dashboard', [ 'snippet-comments.php' ] ) );
        $this->add( new DashboardModuleDefinition( 'masked-login', 'Masked Login', 'hws_base_tools\\display_settings_masked_login', [ 'settings-dashboard-masked-login.php' ] ) );
        $this->add( new DashboardModuleDefinition( 'config', 'Configuration', 'hws_base_tools\\render_tab_config', [ 'settings-dashboard-config.php' ] ) );
        $this->add( new DashboardModuleDefinition( 'advanced', 'Advanced', 'hws_base_tools\\render_tab_advanced', [
            'settings-dashboard-config.php',
            'settings-dashboard-log-delete-cron.php',
            'settings-dashboard-elementor-db-cron.php',
            'settings-dashboard-rank-math-settings.php',
        ] ) );
        $this->add( new DashboardModuleDefinition( 'snippets', 'Legacy Snippets', 'hws_base_tools\\display_settings_snippets', [
            'settings-dashboard-website-types.php',
            'settings-dashboard-snippets.php',
        ], true ) );
    }

    private function add( DashboardModuleDefinition $module ): void {
        $this->modules[ $module->id ] = $module;
        $this->core_registry->add(
            new TabDefinition(
                $module->id,
                $module->label,
                $module->renderer,
                PluginMetadata::ADMIN_CAPABILITY,
                $module->deprecated
            )
        );
    }

    /**
     * @return array<string,string[]>
     */
    private function ajax_file_map(): array {
        return [
            'hws_load_dashboard_tab'             => [],
            'hws_install_'                       => [ 'settings-dashboard-check-plugins.php' ],
            'hws_activate_plugin'                => [ 'settings-dashboard-check-plugins.php' ],
            'hws_base_tools_force_update_check' => [ 'settings-dashboard-check-plugins.php' ],
            'hws_delete_themes'                  => [ 'settings-dashboard-theme-checks.php' ],
            'hws_backup_'                        => [ 'settings-dashboard-backups.php' ],
            'hws_delete_backups'                 => [ 'settings-dashboard-backups.php' ],
            'hws_base_tools_run_log_cleaner'     => [ 'settings-dashboard-log-delete-cron.php' ],
            'hws_base_tools_toggle_auto_delete'  => [ 'settings-dashboard-log-delete-cron.php' ],
            'hws_base_tools_update_log_settings' => [ 'settings-dashboard-log-delete-cron.php' ],
            'hws_get_log_cleaner_state'          => [ 'settings-dashboard-log-delete-cron.php' ],
            'delete_debug_log'                   => [ 'settings-dashboard-log-delete-cron.php' ],
            'delete_error_log'                   => [ 'settings-dashboard-log-delete-cron.php' ],
            'hws_elementor_db_'                  => [ 'settings-dashboard-elementor-db-cron.php' ],
            'hws_get_elementor_db_state'         => [ 'settings-dashboard-elementor-db-cron.php' ],
            'hws_feature_'                       => [ 'settings-dashboard-features.php' ],
            'hws_search_display_'                => [ 'settings-dashboard-search.php' ],
            'hws_search_behavior_'               => [ 'settings-dashboard-search.php' ],
            'hws_footer_text_'                   => [ 'settings-dashboard-footer-text.php' ],
            'hws_toggle_ui_cleanup'              => [ 'settings-dashboard-ui-cleanup.php' ],
            'hws_ui_cleanup_bulk'                => [ 'settings-dashboard-ui-cleanup.php' ],
            'hws_sitemap_'                       => [ 'settings-dashboard-site-profile.php', 'settings-dashboard-sitemaps.php' ],
            'hws_content_cleanup_'               => [ 'settings-dashboard-cleanup.php' ],
            'hws_backup_file_cleanup_'           => [ 'settings-dashboard-cleanup.php' ],
            'hws_article_media_cleanup_'         => [ 'settings-dashboard-cleanup.php' ],
            'hws_database_cleanup_'              => [ 'settings-dashboard-cleanup.php' ],
            'hws_litespeed_redis_'               => [ 'settings-dashboard-cleanup.php' ],
            'hws_menu_tools_'                    => [ 'settings-dashboard-menu-tools.php' ],
            'hws_pages_'                         => [ 'settings-dashboard-pages.php' ],
            'hws_getting_started_'               => [ 'settings-dashboard-getting-started.php' ],
            'hws_update_center'                  => [ 'settings-dashboard-update-center.php' ],
            'hws_toggle_update_urls'             => [ 'settings-dashboard-update-center.php' ],
            'hws_login_mask_'                    => [ 'settings-dashboard-masked-login.php' ],
            'hws_toggle_login_urls'              => [ 'settings-dashboard-masked-login.php' ],
            'hws_clear_login_log'                => [ 'settings-dashboard-masked-login.php' ],
            'hws_get_login_mask_state'           => [ 'settings-dashboard-masked-login.php' ],
            'hws_base_tools_toggle_wordpress_'   => [ 'snippet-comments.php' ],
            'hws_base_tools_get_comments_stats' => [ 'snippet-comments.php' ],
            'hws_base_tools_delete_comments_'    => [ 'snippet-comments.php' ],
        ];
    }

    private function load_file( string $file ): void {
        $path = PluginMetadata::root_path() . '/' . ltrim( $file, '/\\' );
        if ( is_readable( $path ) ) {
            require_once $path;
        }
    }

    private function sanitize_id( string $id ): string {
        return function_exists( 'sanitize_key' )
            ? sanitize_key( $id )
            : (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $id ) );
    }
}
