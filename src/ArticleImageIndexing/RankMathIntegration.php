<?php

namespace HWS\BaseTools\ArticleImageIndexing;

defined( 'ABSPATH' ) || exit;

/**
 * Extends Rank Math's existing output without creating another schema provider.
 */
final class RankMathIntegration {
    public static function register(): void {
        add_filter( 'rank_math/json_ld', [ self::class, 'schema_graph' ], 120, 2 );
        add_filter( 'rank_math/opengraph/facebook/image_array', [ self::class, 'social_image' ], 120 );
        add_filter( 'rank_math/opengraph/twitter/image_array', [ self::class, 'social_image' ], 120 );
        add_filter( 'rank_math/opengraph/twitter/card_type', [ self::class, 'twitter_card_type' ], 120 );
        add_filter( 'rank_math/frontend/robots', [ self::class, 'rank_math_robots' ], 120 );
        add_filter( 'wp_robots', [ self::class, 'wordpress_robots' ], 120 );
        add_filter( 'rank_math/sitemap/urlimages', [ self::class, 'sitemap_images' ], 120, 2 );
    }

    /**
     * @param mixed $data
     * @param mixed $json_ld
     * @return mixed
     */
    public static function schema_graph( $data, $json_ld ) {
        if ( ! is_array( $data ) || empty( $data ) ) {
            return $data;
        }

        $post_id = is_object( $json_ld ) && isset( $json_ld->post_id )
            ? (int) $json_ld->post_id
            : self::current_post_id();
        $attachment_id = $post_id > 0 ? (int) get_post_thumbnail_id( $post_id ) : 0;
        $objects       = $attachment_id > 0 ? ImageFamily::schema_objects( $attachment_id ) : [];

        if ( empty( $objects ) ) {
            return $data;
        }

        foreach ( $data as $key => $entity ) {
            if ( ! is_array( $entity ) || ! self::is_article_entity( $entity ) ) {
                continue;
            }

            $entity['image'] = $objects;
            $data[ $key ]    = $entity;
        }

        return $data;
    }

    /**
     * @param mixed $attachment
     * @return mixed
     */
    public static function social_image( $attachment ) {
        $post_id       = self::current_post_id();
        $featured_id   = $post_id > 0 ? (int) get_post_thumbnail_id( $post_id ) : 0;
        $landscape     = $featured_id > 0 ? ImageFamily::landscape( $featured_id ) : null;
        $attachment_id = is_array( $attachment ) ? (int) ( $attachment['id'] ?? 0 ) : 0;

        if ( null === $landscape || ! ImageFamily::complete( $featured_id ) ) {
            return $attachment;
        }

        // Respect a deliberately selected, different social attachment.
        if ( $attachment_id > 0 && $attachment_id !== $featured_id ) {
            return $attachment;
        }

        return [
            'id'     => $featured_id,
            'url'    => $landscape['url'],
            'width'  => $landscape['width'],
            'height' => $landscape['height'],
            'alt'    => $landscape['alt'],
            'type'   => $landscape['mime_type'],
        ];
    }

    public static function twitter_card_type( string $card_type ): string {
        $post_id       = self::current_post_id();
        $attachment_id = $post_id > 0 ? (int) get_post_thumbnail_id( $post_id ) : 0;

        return $attachment_id > 0 && ImageFamily::complete( $attachment_id )
            ? 'summary_large_image'
            : $card_type;
    }

    /**
     * @param array<string,string> $robots
     * @return array<string,string>
     */
    public static function rank_math_robots( array $robots ): array {
        if ( ! self::current_article_has_featured_image() || self::is_noindex( $robots ) ) {
            return $robots;
        }

        $robots['max-image-preview'] = 'max-image-preview:large';

        return $robots;
    }

    /**
     * @param array<string,string|bool> $robots
     * @return array<string,string|bool>
     */
    public static function wordpress_robots( array $robots ): array {
        if ( ! self::current_article_has_featured_image() || self::is_noindex( $robots ) ) {
            return $robots;
        }

        $robots['max-image-preview'] = 'large';

        return $robots;
    }

    /**
     * @param mixed $images
     * @return mixed
     */
    public static function sitemap_images( $images, int $post_id ) {
        if ( ! is_array( $images ) ) {
            $images = [];
        }

        $attachment_id = (int) get_post_thumbnail_id( $post_id );
        $landscape     = $attachment_id > 0 ? ImageFamily::landscape( $attachment_id ) : null;
        if ( null === $landscape || ! ImageFamily::complete( $attachment_id ) ) {
            return $images;
        }

        $preferred = [ 'src' => $landscape['url'] ];
        $unique    = [ self::normalize_url( $landscape['url'] ) => true ];
        $result    = [ $preferred ];

        foreach ( $images as $image ) {
            if ( ! is_array( $image ) || empty( $image['src'] ) ) {
                continue;
            }

            $normalized = self::normalize_url( (string) $image['src'] );
            if ( isset( $unique[ $normalized ] ) ) {
                continue;
            }

            $unique[ $normalized ] = true;
            $result[]              = $image;
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $entity
     */
    private static function is_article_entity( array $entity ): bool {
        foreach ( (array) ( $entity['@type'] ?? [] ) as $type ) {
            if ( in_array( strtolower( (string) $type ), [ 'article', 'newsarticle', 'blogposting' ], true ) ) {
                return true;
            }
        }

        return false;
    }

    private static function current_article_has_featured_image(): bool {
        $post_id = self::current_post_id();

        return $post_id > 0 && (int) get_post_thumbnail_id( $post_id ) > 0;
    }

    private static function current_post_id(): int {
        return is_singular() ? (int) get_queried_object_id() : 0;
    }

    /**
     * @param array<string,mixed> $robots
     */
    private static function is_noindex( array $robots ): bool {
        foreach ( $robots as $key => $value ) {
            if ( 'noindex' === strtolower( (string) $key ) || 'noindex' === strtolower( (string) $value ) ) {
                return true;
            }
        }

        return false;
    }

    private static function normalize_url( string $url ): string {
        return strtolower( (string) preg_replace( '/[?#].*$/', '', trim( $url ) ) );
    }
}
