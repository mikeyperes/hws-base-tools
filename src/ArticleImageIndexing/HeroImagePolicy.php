<?php

namespace HWS\BaseTools\ArticleImageIndexing;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the singular featured image crawlable in the initial HTML response.
 */
final class HeroImagePolicy {
    private static bool $buffer_started = false;

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

        $attributes = self::preferred_attributes( $attributes, $attachment_id );
        $attributes['fetchpriority'] = 'high';
        $attributes['loading']       = 'eager';
        $attributes['decoding']      = 'async';
        $attributes['data-no-lazy']  = '1';
        $attributes['class']         = self::add_class( (string) ( $attributes['class'] ?? '' ), 'hws-article-hero-image skip-lazy' );

        return $attributes;
    }

    /**
     * Capture a singular article response so a theme-authored raw hero <img>
     * receives the same crawlable attributes as a native WordPress image.
     */
    public static function start_output_buffer(): void {
        if ( self::$buffer_started || self::current_featured_image_id() < 1 ) {
            return;
        }

        if (
            ( function_exists( 'is_admin' ) && is_admin() )
            || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() )
            || ( defined( 'REST_REQUEST' ) && REST_REQUEST )
            || ( function_exists( 'is_feed' ) && is_feed() )
            || ( function_exists( 'is_embed' ) && is_embed() )
            || ( function_exists( 'is_trackback' ) && is_trackback() )
        ) {
            return;
        }

        $method = strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) );
        if ( 'GET' !== $method || headers_sent() ) {
            return;
        }

        self::$buffer_started = true;
        ob_start( [ self::class, 'filter_html' ] );
    }

    public static function filter_html( string $html ): string {
        if ( '' === $html || false === stripos( $html, '<img' ) ) {
            return $html;
        }

        $attachment_id = self::current_featured_image_id();
        $landscape     = $attachment_id > 0 ? ImageFamily::landscape( $attachment_id ) : null;
        if ( null === $landscape || ! ImageFamily::complete( $attachment_id ) ) {
            return $html;
        }

        $filenames = self::featured_filenames( $attachment_id );
        if ( empty( $filenames ) ) {
            return $html;
        }

        if ( class_exists( '\\WP_HTML_Tag_Processor' ) ) {
            $processor = new \WP_HTML_Tag_Processor( $html );
            while ( $processor->next_tag( 'IMG' ) ) {
                $source = (string) (
                    $processor->get_attribute( 'src' )
                    ?: $processor->get_attribute( 'data-lazy-src' )
                    ?: $processor->get_attribute( 'data-src' )
                );
                if ( ! self::matches_featured_source( $source, $filenames ) ) {
                    continue;
                }

                $attributes = self::preferred_attributes(
                    [
                        'class' => (string) $processor->get_attribute( 'class' ),
                        'alt'   => (string) $processor->get_attribute( 'alt' ),
                    ],
                    $attachment_id
                );
                foreach ( $attributes as $name => $value ) {
                    $processor->set_attribute( $name, $value );
                }

                foreach ( [ 'data-src', 'data-srcset', 'data-lazy-src', 'data-lazy-srcset' ] as $attribute ) {
                    $processor->remove_attribute( $attribute );
                }

                return $processor->get_updated_html();
            }

            return $html;
        }

        return self::filter_html_fallback( $html, $attachment_id, $filenames );
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

    /**
     * @param array<string,string> $attributes
     * @return array<string,string>
     */
    private static function preferred_attributes( array $attributes, int $attachment_id ): array {
        $landscape = ImageFamily::landscape( $attachment_id );
        if ( null !== $landscape && ImageFamily::complete( $attachment_id ) ) {
            $srcset = function_exists( 'wp_get_attachment_image_srcset' )
                ? wp_get_attachment_image_srcset( $attachment_id, ImageFamily::LANDSCAPE )
                : false;

            $attributes['src']    = $landscape['url'];
            $attributes['srcset'] = is_string( $srcset ) && '' !== trim( $srcset )
                ? $srcset
                : $landscape['url'] . ' ' . $landscape['width'] . 'w';
            $attributes['sizes']  = '(max-width: ' . $landscape['width'] . 'px) 100vw, ' . $landscape['width'] . 'px';
            $attributes['width']  = (string) $landscape['width'];
            $attributes['height'] = (string) $landscape['height'];

            if ( '' !== $landscape['alt'] ) {
                $attributes['alt'] = $landscape['alt'];
            }
        }

        $attributes['fetchpriority'] = 'high';
        $attributes['loading']       = 'eager';
        $attributes['decoding']      = 'async';
        $attributes['data-no-lazy']  = '1';
        $attributes['class']         = self::add_class( (string) ( $attributes['class'] ?? '' ), 'hws-article-hero-image skip-lazy' );

        return $attributes;
    }

    /**
     * @return array<int,string>
     */
    private static function featured_filenames( int $attachment_id ): array {
        $filenames = [];
        $file      = (string) get_attached_file( $attachment_id );
        if ( '' !== $file ) {
            $filenames[] = strtolower( basename( $file ) );
        }

        $metadata = wp_get_attachment_metadata( $attachment_id );
        foreach ( (array) ( is_array( $metadata ) ? ( $metadata['sizes'] ?? [] ) : [] ) as $size ) {
            if ( is_array( $size ) && ! empty( $size['file'] ) ) {
                $filenames[] = strtolower( basename( (string) $size['file'] ) );
            }
        }

        return array_values( array_unique( array_filter( $filenames ) ) );
    }

    /**
     * @param array<int,string> $filenames
     */
    private static function matches_featured_source( string $source, array $filenames ): bool {
        if ( '' === trim( $source ) ) {
            return false;
        }

        $source = html_entity_decode( $source, ENT_QUOTES | ENT_HTML5 );
        $path   = function_exists( 'wp_parse_url' ) ? wp_parse_url( $source, PHP_URL_PATH ) : parse_url( $source, PHP_URL_PATH );
        $file   = strtolower( rawurldecode( basename( (string) $path ) ) );

        return '' !== $file && in_array( $file, $filenames, true );
    }

    /**
     * WordPress 6.0/6.1 fallback for installations without the HTML API.
     *
     * @param array<int,string> $filenames
     */
    private static function filter_html_fallback( string $html, int $attachment_id, array $filenames ): string {
        $replaced = false;

        return (string) preg_replace_callback(
            '/<img\\b[^>]*>/i',
            static function ( array $match ) use ( $attachment_id, $filenames, &$replaced ): string {
                if ( $replaced ) {
                    return $match[0];
                }

                $tag    = $match[0];
                $source = self::tag_attribute( $tag, 'src' )
                    ?: self::tag_attribute( $tag, 'data-lazy-src' )
                    ?: self::tag_attribute( $tag, 'data-src' );
                if ( ! self::matches_featured_source( $source, $filenames ) ) {
                    return $tag;
                }

                $attributes = self::preferred_attributes(
                    [
                        'class' => self::tag_attribute( $tag, 'class' ),
                        'alt'   => self::tag_attribute( $tag, 'alt' ),
                    ],
                    $attachment_id
                );
                foreach ( [ 'data-src', 'data-srcset', 'data-lazy-src', 'data-lazy-srcset' ] as $attribute ) {
                    $tag = self::remove_tag_attribute( $tag, $attribute );
                }
                foreach ( $attributes as $name => $value ) {
                    $tag = self::set_tag_attribute( $tag, $name, $value );
                }

                $replaced = true;
                return $tag;
            },
            $html
        );
    }

    private static function tag_attribute( string $tag, string $name ): string {
        $pattern = '/\\s' . preg_quote( $name, '/' ) . '\\s*=\\s*(["\'])(.*?)\\1/is';
        return preg_match( $pattern, $tag, $match ) ? html_entity_decode( (string) $match[2], ENT_QUOTES | ENT_HTML5 ) : '';
    }

    private static function remove_tag_attribute( string $tag, string $name ): string {
        $pattern = '/\\s+' . preg_quote( $name, '/' ) . '\\s*=\\s*(?:"[^"]*"|\'[^\']*\'|[^\\s>]+)/i';
        return (string) preg_replace( $pattern, '', $tag, 1 );
    }

    private static function set_tag_attribute( string $tag, string $name, string $value ): string {
        $tag     = self::remove_tag_attribute( $tag, $name );
        $escaped = htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
        $closing = str_ends_with( rtrim( $tag ), '/>' ) ? ' />' : '>';

        return (string) preg_replace(
            '/\\s*\\/?>(?![\\s\\S]*>)/',
            ' ' . $name . '="' . $escaped . '"' . $closing,
            $tag,
            1
        );
    }

    private static function add_class( string $current, string $additional ): string {
        $classes = preg_split( '/\s+/', trim( $current . ' ' . $additional ) ) ?: [];

        return implode( ' ', array_values( array_unique( array_filter( $classes ) ) ) );
    }
}
