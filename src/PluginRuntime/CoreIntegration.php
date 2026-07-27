<?php

namespace HWS\BaseTools\PluginRuntime;

use Hexa\PluginCore\CoreBootstrap\CoreBootstrap;
use Hexa\PluginCore\CoreRuntime\PluginContext;
use Hexa\PluginCore\CoreRuntime\CorePackageRuntime;
use Hexa\PluginCore\WpAdminTabs\CoreTabConfig;
use Hexa\PluginCore\WpAdminTabs\CoreTabModule;
use HWS\BaseTools\AdminDashboard\DashboardAssets;
use HWS\BaseTools\AdminDashboard\LegacyEventBridge;
use HWS\BaseTools\AcfFields\SharedAcfStructures;
use HWS\BaseTools\ContentTypes\SharedContentTypes;
use HWS\BaseTools\SiteProfile\PrimaryEntityIntegration;

final class CoreIntegration {
    private static ?CoreBootstrap $bootstrap = null;

    public static function bootstrap(): CoreBootstrap {
        if ( self::$bootstrap instanceof CoreBootstrap ) {
            return self::$bootstrap;
        }

        $plugin_file = PluginMetadata::plugin_file();
        $basename = function_exists( 'plugin_basename' )
            ? plugin_basename( $plugin_file )
            : PluginMetadata::SLUG . '/' . PluginMetadata::MAIN_FILE;
        $plugin_url = function_exists( 'plugin_dir_url' )
            ? plugin_dir_url( $plugin_file )
            : '';
        $selected_core_root = CorePackageRuntime::selected_root();
        $core_root = '' !== $selected_core_root
            ? $selected_core_root
            : PluginMetadata::root_path() . '/lib/hexa-wordpress-plugin-core';

        $context = new PluginContext(
            [
                'slug'        => PluginMetadata::SLUG,
                'basename'    => $basename,
                'version'     => PluginMetadata::VERSION,
                'path'        => PluginMetadata::root_path(),
                'url'         => $plugin_url ?: '/',
                'github_repo' => PluginMetadata::GITHUB_REPOSITORY,
                'admin_page'  => PluginMetadata::ADMIN_PAGE_SLUG,
                'capability'  => PluginMetadata::ADMIN_CAPABILITY,
            ]
        );

        self::$bootstrap = new CoreBootstrap( $context );
        self::$bootstrap
            ->add_module( new LegacyEventBridge() )
            ->add_module( new DashboardAssets() )
            ->add_module( SharedContentTypes::registry() )
            ->add_module( SharedAcfStructures::registry() )
            ->add_module( PrimaryEntityIntegration::module() )
            ->add_module( PrimaryEntityIntegration::website_settings_panel() )
            ->add_module(
                new CoreTabModule(
                    new CoreTabConfig(
                        [
                            'tabs_filter'   => 'hws_base_tools_dashboard_tabs',
                            'render_filter' => 'hws_base_tools_render_dashboard_tab',
                            'capability'    => PluginMetadata::ADMIN_CAPABILITY,
                            'core_root'     => $core_root,
                            'readme_path'   => $core_root . '/README.md',
                            'library_path'  => PluginMetadata::root_path() . '/HEXA_PLUGIN_CORE_LIBRARY.md',
                        ]
                    )
                )
            );

        return self::$bootstrap;
    }

    public static function boot(): void {
        self::bootstrap()->boot();
    }
}
