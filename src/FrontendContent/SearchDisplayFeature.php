<?php

namespace HWS\BaseTools\FrontendContent;

use Hexa\PluginCore\SearchDisplay\SearchDisplayRenderer;

final class SearchDisplayFeature {
    public const OPTION_KEY = 'hws_search_display';
    public const SHORTCODE = 'hexa_search';

    /** @return array{style:string,accent:string,placeholder:string} */
    public static function defaults(): array {
        return [
            'style'       => 'pill',
            'accent'      => '',
            'placeholder' => 'Search...',
        ];
    }

    /** @return array{style:string,accent:string,placeholder:string} */
    public static function settings(): array {
        $stored = get_option( self::OPTION_KEY, [] );

        return self::sanitize_settings( is_array( $stored ) ? $stored : [] );
    }

    /**
     * @param array<string,mixed> $settings
     * @return array{style:string,accent:string,placeholder:string}
     */
    public static function sanitize_settings( array $settings ): array {
        $defaults = self::defaults();
        $style = sanitize_key( (string) ( $settings['style'] ?? $defaults['style'] ) );
        if ( ! in_array( $style, SearchDisplayRenderer::STYLES, true ) ) {
            $style = $defaults['style'];
        }

        $accent = trim( (string) ( $settings['accent'] ?? $defaults['accent'] ) );
        if ( '' !== $accent ) {
            $accent = function_exists( 'sanitize_hex_color' )
                ? (string) sanitize_hex_color( $accent )
                : ( preg_match( '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $accent ) ? $accent : '' );
        }

        $placeholder = sanitize_text_field( (string) ( $settings['placeholder'] ?? $defaults['placeholder'] ) );
        if ( '' === trim( $placeholder ) ) {
            $placeholder = $defaults['placeholder'];
        }

        return [
            'style'       => $style,
            'accent'      => $accent,
            'placeholder' => $placeholder,
        ];
    }

    public static function register_shortcode(): void {
        add_shortcode( self::SHORTCODE, [ self::class, 'render_shortcode' ] );
    }

    /** @param array<string,mixed>|string $attributes */
    public static function render_shortcode( $attributes = [] ): string {
        if ( ! class_exists( SearchDisplayRenderer::class ) ) {
            return '';
        }

        $settings = self::settings();
        $attributes = shortcode_atts(
            [
                'style'       => $settings['style'],
                'accent'      => $settings['accent'],
                'placeholder' => $settings['placeholder'],
                'label'       => 'Search',
                'radius'      => '',
            ],
            is_array( $attributes ) ? $attributes : [],
            self::SHORTCODE
        );

        return SearchDisplayRenderer::render(
            [
                'style'       => (string) $attributes['style'],
                'accent'      => (string) $attributes['accent'],
                'placeholder' => (string) $attributes['placeholder'],
                'label'       => (string) $attributes['label'],
                'radius'      => (string) $attributes['radius'],
                'hidden_fields' => SearchQueryFeature::marker_fields(),
            ]
        );
    }
}
