<?php

declare( strict_types=1 );

namespace HWS\BaseTools\NotFoundPage;

defined( 'ABSPATH' ) || exit;

/** 404 page settings, stored in one option. Off by default. */
final class NotFoundSettings {
    public const OPTION = 'hws_base_tools_404_page';
    public const DESIGNS = [ 'plinth', 'divider', 'headline' ];

    /** @return array<string,mixed> */
    public static function defaults(): array {
        return [
            'enabled'       => false,
            'design'        => 'plinth',
            'primary_color' => '',
            'text_color'    => '',
            'background'    => '',
        ];
    }

    /** @return array<string,mixed> */
    public static function all(): array {
        $stored = get_option( self::OPTION, [] );

        return self::sanitize( is_array( $stored ) ? $stored : [] );
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
        $values = array_merge( self::defaults(), $values );
        $design = sanitize_key( (string) $values['design'] );

        return [
            'enabled'       => (bool) $values['enabled'],
            'design'        => in_array( $design, self::DESIGNS, true ) ? $design : 'plinth',
            'primary_color' => (string) ( sanitize_hex_color( (string) $values['primary_color'] ) ?? '' ),
            'text_color'    => (string) ( sanitize_hex_color( (string) $values['text_color'] ) ?? '' ),
            'background'    => (string) ( sanitize_hex_color( (string) $values['background'] ) ?? '' ),
        ];
    }
}
