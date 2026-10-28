<?php

namespace HWS\BaseTools\PluginRuntime;

use Hexa\PluginCore\Calendar\CalendarModule;
use Hexa\PluginCore\CoreBootstrap\CoreBootstrap;
use Hexa\PluginCore\CoreRuntime\PluginContext;
use Hexa\PluginCore\CoreRuntime\CorePackageRuntime;
use Hexa\PluginCore\Search\SearchModule;
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
use HWS\BaseTools\PageLayoutStyling\PageLayoutFeature;
use HWS\BaseTools\MaintenanceMode\MaintenanceModeFeature;
use HWS\BaseTools\TeamMembers\TeamTab;
use HWS\BaseTools\FrontendContent\ReadingProgress;
use HWS\BaseTools\FrontendContent\FontSmoothing;
use HWS\BaseTools\FrontendContent\SkipLinkTarget;
use HWS\BaseTools\FrontendContent\SearchBoxFeature;
use HWS\BaseTools\FrontendContent\DiscussionPolicy;
use HWS\BaseTools\Editorial\FeaturedImageRequirement;
use HWS\BaseTools\QueryCompatibility\QueryHookCompatibility;
use HWS\BaseTools\QuickStart\QuickStartModule;
use HWS\BaseTools\ReviewCenter\ReviewCenterModule;
use HWS\BaseTools\LiteSpeed\LiteSpeedModule;
use HWS\BaseTools\DatabaseOptimization\DatabaseOptimizationModule;
use HWS\BaseTools\ExternalPublishing\ExternalPublishingModule;
use HWS\BaseTools\UserImpersonation\UserImpersonationFeature;
use HWS\BaseTools\Security\LoginLinkFallback;
use HWS\BaseTools\SeoSettings\RankMathSchemaSetting;
use HWS\BaseTools\SitemapTools\FeedListShortcode;
use HWS\BaseTools\SitemapTools\FeedSitemapSettings;
use HWS\BaseTools\SitemapTools\FeedSwitches;

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
            ->add_module( new DatabaseOptimizationModule() )
            ->add_module( new QueryHookCompatibility() )
            ->add_module( new BrandTemplateFeature() )
            ->add_module( new PageLayoutFeature() )
            ->add_module( new MaintenanceModeFeature() )
            ->add_module( new TeamTab() )
            ->add_module( new \HWS\BaseTools\Breadcrumbs\Breadcrumbs() )
            ->add_module( new \HWS\BaseTools\NotFoundPage\NotFoundPage() )
            // Founders: picked once here; Website Identity and SMP read hws_publication_founders().
            ->add_module( new \HWS\BaseTools\Founders\Founders() )
            ->add_module( new \HWS\BaseTools\Founders\FoundersTab() )
            ->add_module( new DiscussionPolicy() )
            ->add_module( new FeaturedImageRequirement() )
            ->add_module( new \HWS\BaseTools\BrandAssets\DefaultFeaturedImage() )
            ->add_module( new ReadingProgress() )
            ->add_module( new FontSmoothing() )
            ->add_module( new SkipLinkTarget() )
            ->add_module( new \HWS\BaseTools\FrontendContent\CardImageRenditions() )
            ->add_module( new FeedSwitches() )
            ->add_module( new FeedSitemapSettings() )
            ->add_module( new FeedListShortcode() )
            ->add_module( new ExternalPublishingModule() )
            ->add_module( new \HWS\BaseTools\ElementorFonts\CustomFontModule() )
            ->add_module( new RankMathSchemaSetting() )
            ->add_module( new UserImpersonationFeature() )
            ->add_module( new LoginLinkFallback() )
            ->add_module( new \HWS\BaseTools\ContentTypes\PageCollections() )
            ->add_module( new \HWS\BaseTools\FrontendContent\LoopLeadFirstPage() )
            ->add_module( new \HWS\BaseTools\FrontendContent\FirstScreenImages() )
            ->add_module( SharedContentTypes::registry() )
            ->add_module( SharedAcfStructures::registry() )
            ->add_module( UserProfileGalleryDetails::module() )
            ->add_module( PrimaryEntityIntegration::module() )
            ->add_module( new \HWS\BaseTools\SiteProfile\SiteIdentity() )
            // Contact, Headquarters and Logo page types (Pages tab) and the missing company facts notice.
            ->add_module( new \HWS\BaseTools\CompanyPages\CompanyPages() )
            ->add_module( \HWS\BaseTools\SiteProfile\SiteIdentity::panel() )
            ->add_module( PrimaryEntityIntegration::website_settings_panel() )
            // The one search (Hexa WP Core): [hws_search] above any list, [hws_search_box] in the header, [hexa_search id] for any registered profile.
            ->add_module( new SearchModule() )
            ->add_module( new SearchBoxFeature() )
            ->add_module( new CalendarModule() )
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
