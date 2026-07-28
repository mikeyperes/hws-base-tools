<?php

declare( strict_types=1 );

namespace HWS\BaseTools\BrandTemplates;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

defined( 'ABSPATH' ) || exit;

final class BrandTemplateFeature implements ModuleInterface {
    public function register(): void {
        add_filter( 'template_include', [ TemplateLoader::class, 'filter' ], 99 );
        add_filter( 'body_class', [ FrontendStyles::class, 'body_classes' ] );
        add_action( 'wp_enqueue_scripts', [ FrontendStyles::class, 'enqueue' ] );
        add_action( 'admin_post_hws_brand_templates_save', [ BrandTemplatesAdmin::class, 'handle_save' ] );
        add_action( 'admin_post_hws_brand_templates_import', [ BrandTemplatesAdmin::class, 'handle_import' ] );
        add_action( 'admin_post_hws_brand_templates_restore', [ BrandTemplatesAdmin::class, 'handle_restore' ] );
    }
}
