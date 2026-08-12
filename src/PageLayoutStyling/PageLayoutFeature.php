<?php

declare( strict_types=1 );

namespace HWS\BaseTools\PageLayoutStyling;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use HWS\BaseTools\BrandAssets\HighlightColorResolver;
use HWS\BaseTools\PluginRuntime\PluginMetadata;

defined( 'ABSPATH' ) || exit;

final class PageLayoutFeature implements ModuleInterface {
    public function register(): void {
        add_filter( 'body_class', [ self::class, 'body_classes' ] );
        add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue' ], 100 );
        add_action( 'admin_post_hws_page_layout_style_save', [ PageLayoutAdmin::class, 'handle_save' ] );
    }

    /** @param string[] $classes
     *  @return string[]
     */
    public static function body_classes( array $classes ): array {
        $style = PageLayoutSettings::selected();
        if ( self::supports_current_page() && PageLayoutSettings::NO_STYLE !== $style ) {
            $classes[] = 'hws-page-layout-styled';
            $classes[] = 'hws-page-layout-style-' . sanitize_html_class( $style );
        }
        return array_values( array_unique( $classes ) );
    }

    public static function enqueue(): void {
        $style = PageLayoutSettings::selected();
        if ( ! self::supports_current_page() || PageLayoutSettings::NO_STYLE === $style ) {
            return;
        }
        $dependencies = wp_style_is( 'elementor-frontend', 'registered' ) ? [ 'elementor-frontend' ] : [];
        wp_enqueue_style(
            'hws-page-layout-styling',
            plugin_dir_url( PluginMetadata::plugin_file() ) . 'assets/frontend/page-layout-styling.css',
            $dependencies,
            PluginMetadata::VERSION
        );
        wp_add_inline_style(
            'hws-page-layout-styling',
            ':root{--hws-page-layout-accent:' . self::elementor_primary_color() . ';}'
        );
    }

    public static function supports_current_page(): bool {
        if ( ! is_page() || is_front_page() ) {
            return false;
        }
        $page_id = (int) get_queried_object_id();
        if ( $page_id <= 0 ) {
            return false;
        }
        $template = (string) get_page_template_slug( $page_id );
        if ( '' !== $template && 'default' !== $template ) {
            return false;
        }
        return 'builder' !== (string) get_post_meta( $page_id, '_elementor_edit_mode', true );
    }

    public static function elementor_primary_color(): string {
        $primary = HighlightColorResolver::active_elementor_primary();
        return '#000000' === $primary ? '#3157d5' : $primary;
    }
}
