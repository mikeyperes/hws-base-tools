<?php

declare( strict_types=1 );

namespace HWS\BaseTools\Breadcrumbs;

defined( 'ABSPATH' ) || exit;

/**
 * Breadcrumb settings, stored in one option. Off by default: a feature that
 * shows anything to visitors never switches itself on.
 *
 * Breadcrumbs used to live in SMP Publication Integration. The first read
 * carries over that plugin's saved settings, but only on a site where they
 * were deliberately saved as on; an unsaved (default) value stays off.
 */
final class BreadcrumbSettings {
    public const OPTION = 'hws_base_tools_breadcrumbs';
    private const LEGACY_OPTION = 'smpi_settings';
    public const STYLES = [ 'bc-b1', 'bc-b2', 'bc-b3', 'bc-b4', 'bc-b5', 'bc-b6' ];

    /** @return array<string,mixed> */
    public static function defaults(): array {
        return [
            'enabled'             => false,
            'style'               => 'bc-b2',
            'accent_color'        => '',
            'background_color'    => '',
            'text_color'          => '',
            'font_size'           => 13,
            'hide_home'           => true,
            'hide_term_archives'  => false,
            'disabled_post_types' => [],
            'disabled_ids'        => [],
            'css_override'        => '',
            'migrated_from_smp'   => false,
        ];
    }

    /** @return array<string,mixed> */
    public static function all(): array {
        $stored = get_option( self::OPTION, null );
        if ( ! is_array( $stored ) ) {
            $stored = self::from_legacy();
            update_option( self::OPTION, $stored, false );
        }

        return self::sanitize( $stored );
    }

    public static function enabled(): bool {
        return ! empty( self::all()['enabled'] );
    }

    /** @param array<string,mixed> $changes @return array<string,mixed> */
    public static function update( array $changes ): array {
        $settings = self::sanitize( array_merge( self::all(), $changes ) );
        update_option( self::OPTION, $settings, false );

        return $settings;
    }

    /** @param array<string,mixed> $values @return array<string,mixed> */
    public static function sanitize( array $values ): array {
        $defaults = self::defaults();
        $values   = array_merge( $defaults, $values );
        $style    = sanitize_key( (string) $values['style'] );
        $ids      = is_array( $values['disabled_ids'] ) ? $values['disabled_ids'] : preg_split( '/[\s,]+/', (string) $values['disabled_ids'] );

        return [
            'enabled'             => (bool) $values['enabled'],
            'style'               => in_array( $style, self::STYLES, true ) ? $style : $defaults['style'],
            'accent_color'        => (string) ( sanitize_hex_color( (string) $values['accent_color'] ) ?? '' ),
            'background_color'    => (string) ( sanitize_hex_color( (string) $values['background_color'] ) ?? '' ),
            'text_color'          => (string) ( sanitize_hex_color( (string) $values['text_color'] ) ?? '' ),
            'font_size'           => max( 9, min( 24, (int) $values['font_size'] ) ),
            'hide_home'           => (bool) $values['hide_home'],
            'hide_term_archives'  => (bool) $values['hide_term_archives'],
            'disabled_post_types' => array_values( array_filter( array_map( 'sanitize_key', (array) $values['disabled_post_types'] ) ) ),
            'disabled_ids'        => array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) ),
            'css_override'        => (string) $values['css_override'],
            'migrated_from_smp'   => (bool) $values['migrated_from_smp'],
        ];
    }

    /** @return array<string,mixed> */
    private static function from_legacy(): array {
        $defaults = self::defaults();
        $legacy   = get_option( self::LEGACY_OPTION, null );
        $saved_on = is_array( $legacy ) && in_array( $legacy['breadcrumbs_enabled'] ?? null, [ true, 1, '1' ], true );
        if ( ! $saved_on ) {
            return $defaults;
        }

        $disabled_ids = get_option( 'options_smpi_breadcrumb_disabled_objects', [] );

        return array_merge(
            $defaults,
            [
                'enabled'             => true,
                'style'               => (string) ( $legacy['breadcrumbs_style'] ?? $defaults['style'] ),
                'accent_color'        => (string) ( $legacy['breadcrumbs_accent_color'] ?? '' ),
                'background_color'    => (string) ( $legacy['breadcrumbs_background_color'] ?? '' ),
                'text_color'          => (string) ( $legacy['breadcrumbs_text_color'] ?? '' ),
                'font_size'           => (int) ( $legacy['breadcrumbs_font_size'] ?? $defaults['font_size'] ),
                'hide_home'           => (bool) ( $legacy['breadcrumbs_hide_home'] ?? true ),
                'hide_term_archives'  => (bool) ( $legacy['breadcrumbs_hide_term_archives'] ?? false ),
                'disabled_post_types' => (array) ( $legacy['breadcrumbs_disabled_post_types'] ?? [] ),
                'disabled_ids'        => is_array( $disabled_ids ) ? $disabled_ids : [],
                'css_override'        => (string) ( $legacy['breadcrumbs_css_override'] ?? '' ),
                'migrated_from_smp'   => true,
            ]
        );
    }
}
