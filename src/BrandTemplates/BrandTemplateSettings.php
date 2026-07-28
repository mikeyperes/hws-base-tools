<?php

declare( strict_types=1 );

namespace HWS\BaseTools\BrandTemplates;

defined( 'ABSPATH' ) || exit;

final class BrandTemplateSettings {
    public const OPTION = 'hws_brand_template_settings';

    /** @return array<string,bool> */
    public static function defaults(): array {
        return [
            'author_enabled'               => false,
            'page_enabled'                 => false,
            'single_post_enabled'          => false,
            'category_enabled'             => false,
            'tag_enabled'                  => false,
            'page_content_styles_enabled'  => false,
            'single_content_styles_enabled'=> false,
        ];
    }

    /** @return array<string,bool> */
    public static function all(): array {
        $stored = get_option( self::OPTION, [] );
        return self::sanitize( is_array( $stored ) ? $stored : [] );
    }

    public static function enabled( string $key ): bool {
        $settings = self::all();
        return ! empty( $settings[ sanitize_key( $key ) ] );
    }

    public static function context_enabled( string $context ): bool {
        $definition = BrandTemplateRegistry::get( $context );
        return null !== $definition && self::enabled( (string) $definition['option'] );
    }

    /** @param array<string,mixed> $input
     *  @return array<string,bool>
     */
    public static function sanitize( array $input ): array {
        $settings = self::defaults();
        foreach ( array_keys( $settings ) as $key ) {
            $settings[ $key ] = self::truthy( $input[ $key ] ?? false );
        }
        return $settings;
    }

    /** @param array<string,mixed> $input
     *  @return array<string,bool>
     */
    public static function update( array $input ): array {
        $settings = self::sanitize( $input );
        update_option( self::OPTION, $settings, false );
        return $settings;
    }

    public static function enable_context( string $context ): bool {
        $definition = BrandTemplateRegistry::get( $context );
        if ( null === $definition ) {
            return false;
        }
        $settings = self::all();
        $settings[ (string) $definition['option'] ] = true;
        self::update( $settings );
        return true;
    }

    private static function truthy( mixed $value ): bool {
        if ( is_bool( $value ) ) {
            return $value;
        }
        return in_array( strtolower( trim( (string) $value ) ), [ '1', 'true', 'yes', 'on', 'enabled' ], true );
    }
}
