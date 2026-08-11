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
use HWS\BaseTools\AcfFields\UserProfileGalleryDetails;
use HWS\BaseTools\ContentTypes\SharedContentTypes;
use HWS\BaseTools\SiteProfile\PrimaryEntityIntegration;
use HWS\BaseTools\Diagnostics\IntegrationTests;
use HWS\BaseTools\BrandTemplates\BrandTemplateFeature;
use HWS\BaseTools\FrontendContent\ReadingProgress;
use HWS\BaseTools\FrontendContent\DiscussionPolicy;
use HWS\BaseTools\QueryCompatibility\QueryHookCompatibility;
use HWS\BaseTools\QuickStart\QuickStartModule;
use HWS\BaseTools\ReviewCenter\ReviewCenterModule;
use HWS\BaseTools\LiteSpeed\LiteSpeedModule;
use HWS\BaseTools\ArticleImageIndexing\ArticleImageModule;

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
            ->add_module( new QuickStartModule() )
            ->add_module( new ReviewCenterModule() )
            ->add_module( new LiteSpeedModule() )
            ->add_module( new QueryHookCompatibility() )
            ->add_module( new BrandTemplateFeature() )
            ->add_module( new DiscussionPolicy() )
            ->add_module( new ReadingProgress() )
            ->add_module( new ArticleImageModule() )
            ->add_module( SharedContentTypes::registry() )
            ->add_module( SharedAcfStructures::registry() )
            ->add_module( UserProfileGalleryDetails::module() )
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
        add_action( 'hexa_plugin_core_register_integration_tests', [ IntegrationTests::class, 'register' ] );
        self::bootstrap()->boot();
    }
}
