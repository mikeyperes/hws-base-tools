<?php

declare( strict_types=1 );

namespace HWS\BaseTools\BrandAssets;

defined( 'ABSPATH' ) || exit;

final class HighlightColorResolver {
    public const LEGACY_BACKGROUND = '#facc15';
    public const LEGACY_TEXT = '#111827';

    public static function uses_legacy_defaults( mixed $background, mixed $text ): bool {
        $background = self::hex( $background );
        $text = self::hex( $text );

        if ( '' === $background ) {
            return in_array( $text, [ '', self::LEGACY_BACKGROUND, self::LEGACY_TEXT ], true );
        }

        return self::LEGACY_BACKGROUND === $background
            && in_array( $text, [ '', self::LEGACY_BACKGROUND, self::LEGACY_TEXT ], true );
    }

    public static function contrast_text( string $background ): string {
        $hex = ltrim( self::hex( $background ), '#' );
        if ( 3 === strlen( $hex ) ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if ( 6 !== strlen( $hex ) ) {
            return '#000000';
        }

        $components = [];
        foreach ( [ 0, 2, 4 ] as $offset ) {
            $channel = hexdec( substr( $hex, $offset, 2 ) ) / 255;
            $components[] = $channel <= .04045
                ? $channel / 12.92
                : pow( ( $channel + .055 ) / 1.055, 2.4 );
        }
        $luminance = .2126 * $components[0] + .7152 * $components[1] + .0722 * $components[2];
        return $luminance > .179 ? '#000000' : '#ffffff';
    }

    public static function active_elementor_primary(): string {
        $kit_id = (int) get_option( 'elementor_active_kit', 0 );
        $settings = $kit_id > 0 ? get_post_meta( $kit_id, '_elementor_page_settings', true ) : [];
        if ( is_array( $settings ) ) {
            foreach ( (array) ( $settings['system_colors'] ?? [] ) as $color ) {
                if ( is_array( $color ) && 'primary' === ( $color['_id'] ?? '' ) ) {
                    $primary = sanitize_hex_color( (string) ( $color['color'] ?? '' ) );
                    if ( $primary ) {
                        return strtolower( $primary );
                    }
                }
            }
        }
        $brand = sanitize_hex_color( (string) get_option( 'hws_brand_primary_color', '' ) );
        return $brand ? strtolower( $brand ) : '#000000';
    }

    private static function hex( mixed $value ): string {
        $value = strtolower( trim( (string) $value ) );
        return preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/', $value ) ? $value : '';
    }
}
