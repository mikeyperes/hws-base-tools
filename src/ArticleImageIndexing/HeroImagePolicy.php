<?php

namespace HWS\BaseTools\ArticleImageIndexing;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the singular featured image crawlable in the initial HTML response.
 */
final class HeroImagePolicy {
    /**
     * @param array<string,string> $attributes
     * @param mixed                $attachment
     * @param mixed                $size
     * @return array<string,string>
     */
    public static function attributes( array $attributes, $attachment, $size ): array {
        unset( $size );

        $attachment_id = is_object( $attachment ) && isset( $attachment->ID ) ? (int) $attachment->ID : 0;
        if ( ! self::is_current_featured_image( $attachment_id ) ) {
            return $attributes;
        }

        $attributes['fetchpriority'] = 'high';
        $attributes['loading']       = 'eager';
        $attributes['decoding']      = 'async';
        $attributes['data-no-lazy']  = '1';
        $attributes['class']         = self::add_class( (string) ( $attributes['class'] ?? '' ), 'hws-article-hero-image skip-lazy' );

        return $attributes;
    }

    /**
     * @param array<int,string>|string $exclusions
     * @return array<int,string>
     */
    public static function litespeed_url_exclusions( $exclusions ): array {
        if ( is_string( $exclusions ) ) {
            $exclusions = preg_split( '/\R+/', $exclusions ) ?: [];
        }

        $attachment_id = self::current_featured_image_id();
        if ( $attachment_id > 0 ) {
            $exclusions = array_merge( (array) $exclusions, ImageFamily::filename_fragments( $attachment_id ) );
        }

        return array_values( array_unique( array_filter( array_map( 'strval', (array) $exclusions ) ) ) );
    }

    /**
     * @param array<int,string>|string $exclusions
     * @return array<int,string>
     */
    public static function litespeed_class_exclusions( $exclusions ): array {
        if ( is_string( $exclusions ) ) {
            $exclusions = preg_split( '/\R+/', $exclusions ) ?: [];
        }

        $exclusions[] = 'hws-article-hero-image';

        return array_values( array_unique( array_filter( array_map( 'strval', (array) $exclusions ) ) ) );
    }

    public static function current_featured_image_id(): int {
        if ( ! is_singular() ) {
            return 0;
        }

        $post_id = (int) get_queried_object_id();

        return $post_id > 0 ? (int) get_post_thumbnail_id( $post_id ) : 0;
    }

    private static function is_current_featured_image( int $attachment_id ): bool {
        return $attachment_id > 0 && $attachment_id === self::current_featured_image_id();
    }

    private static function add_class( string $current, string $additional ): string {
        $classes = preg_split( '/\s+/', trim( $current . ' ' . $additional ) ) ?: [];

        return implode( ' ', array_values( array_unique( array_filter( $classes ) ) ) );
    }
}
