<?php

declare( strict_types=1 );

namespace HWS\BaseTools\BrandTemplates;

use HWS\BaseTools\PluginRuntime\PluginMetadata;

defined( 'ABSPATH' ) || exit;

final class FrontendStyles {
    public static function enqueue(): void {
        if ( ! self::should_enqueue() ) {
            return;
        }
        wp_enqueue_style(
            'hws-brand-templates',
            plugin_dir_url( PluginMetadata::plugin_file() ) . 'assets/frontend/brand-templates.css',
            [],
            PluginMetadata::VERSION
        );
        wp_add_inline_style(
            'hws-brand-templates',
            ':root{--hws-brand-template-accent:' . self::brand_accent() . ';}'
        );
    }

    /** @param string[] $classes
     *  @return string[]
     */
    public static function body_classes( array $classes ): array {
        if ( is_singular( 'post' ) && BrandTemplateSettings::enabled( 'single_content_styles_enabled' ) ) {
            $classes[] = 'hws-single-content-styles';
        }
        $selected = TemplateLoader::selected_context();
        if ( '' !== $selected ) {
            $classes[] = 'hws-brand-template-fallback';
            $classes[] = 'hws-brand-template-' . sanitize_html_class( str_replace( '_', '-', $selected ) );
        }
        return array_values( array_unique( $classes ) );
    }

    private static function should_enqueue(): bool {
        return '' !== TemplateLoader::selected_context()
            || ( is_singular( 'post' ) && BrandTemplateSettings::enabled( 'single_content_styles_enabled' ) );
    }

    private static function brand_accent(): string {
        $accent = sanitize_hex_color( (string) get_option( 'hws_brand_primary_color', '' ) );
        if ( $accent ) {
            return $accent;
        }

        $kit_id = (int) get_option( 'elementor_active_kit', 0 );
        $settings = $kit_id > 0 ? get_post_meta( $kit_id, '_elementor_page_settings', true ) : [];
        if ( is_array( $settings ) && isset( $settings['system_colors'] ) && is_array( $settings['system_colors'] ) ) {
            foreach ( $settings['system_colors'] as $color ) {
                if ( is_array( $color ) && 'primary' === ( $color['_id'] ?? '' ) ) {
                    $accent = sanitize_hex_color( (string) ( $color['color'] ?? '' ) );
                    if ( $accent ) {
                        return $accent;
                    }
                }
            }
        }
        return '#3157d5';
    }
}
