<?php

namespace HWS\BaseTools\ArticleImageIndexing;

defined( 'ABSPATH' ) || exit;

/**
 * Completes Rank Math's targeted invalidation when transients live in Redis.
 *
 * Rank Math removes transient rows with a direct options-table query. That
 * query cannot evict the matching persistent object-cache entries, so this
 * adapter deletes the same deterministic keys through WordPress' cache API.
 */
final class RankMathSitemapCache {
    public const REFRESH_EVENT = 'hws_base_tools_refresh_article_sitemaps';

    public static function register(): void {
        add_filter( 'rank_math/sitemap/invalidate_storage', [ self::class, 'before_invalidation' ], PHP_INT_MAX, 2 );
        add_action( 'rank_math/sitemap/invalidated_storage', [ self::class, 'after_invalidation' ], PHP_INT_MAX, 1 );
        add_action( self::REFRESH_EVENT, [ self::class, 'refresh_and_verify' ], 10, 2 );
    }

    /**
     * @param mixed $allowed
     * @param mixed $type
     * @return mixed
     */
    public static function before_invalidation( $allowed, $type ) {
        if ( $allowed && null === $type ) {
            self::evict_all_types();
        }

        return $allowed;
    }

    /**
     * @param mixed $type
     */
    public static function after_invalidation( $type ): void {
        $type = sanitize_key( (string) $type );
        if ( '' !== $type ) {
            self::evict_type( $type );
        }
    }

    /**
     * @return array<int,string> Evicted transient keys.
     */
    public static function evict_for_post( int $post_id ): array {
        $post = get_post( $post_id );
        if ( ! is_object( $post ) || empty( $post->post_type ) ) {
            return [];
        }

        $types = [ '1', sanitize_key( (string) $post->post_type ) ];
        if ( self::news_sitemap_includes( (string) $post->post_type ) ) {
            $types[] = 'news';
        }

        $evicted = [];
        foreach ( array_unique( $types ) as $type ) {
            $evicted = array_merge( $evicted, self::evict_type( $type ) );
        }

        foreach ( self::sitemap_urls( $post_id ) as $url ) {
            do_action( 'litespeed_purge_url', $url );
        }

        return array_values( array_unique( $evicted ) );
    }

    /**
     * @return array<int,string>
     */
    public static function evict_type( string $type ): array {
        $keys = self::transient_keys( $type );
        if ( empty( $keys ) ) {
            return [];
        }

        foreach ( array_chunk( $keys, 100 ) as $chunk ) {
            if ( function_exists( 'wp_cache_delete_multiple' ) ) {
                wp_cache_delete_multiple( $chunk, 'transient' );
                continue;
            }

            foreach ( $chunk as $key ) {
                wp_cache_delete( $key, 'transient' );
            }
        }

        return $keys;
    }

    /**
     * @return array<int,string>
     */
    public static function transient_keys( string $type ): array {
        $type  = sanitize_key( $type );
        $pages = self::page_count( $type );
        $keys  = [];

        for ( $page = 1; $page <= $pages; ++$page ) {
            $keys[] = self::transient_key( $type, $page );
        }

        return $keys;
    }

    public static function transient_key( string $type, int $page = 1 ): string {
        $type     = sanitize_key( $type );
        $page     = max( 1, $page );
        $filename = 'rank_math_' . md5( "{$type}_{$page}_" . home_url() ) . '.xml';

        return "sitemap_{$type}_{$filename}";
    }

    public static function schedule_refresh( int $post_id, int $attempt = 1, int $delay = 20 ): void {
        $args = [ $post_id, max( 1, $attempt ) ];
        if ( ! wp_next_scheduled( self::REFRESH_EVENT, $args ) ) {
            wp_schedule_single_event( time() + max( 1, $delay ), self::REFRESH_EVENT, $args );
        }
    }

    public static function refresh_and_verify( int $post_id, int $attempt = 1 ): void {
        $post = get_post( $post_id );
        if ( ! is_object( $post ) || 'publish' !== (string) $post->post_status ) {
            return;
        }

        self::evict_for_post( $post_id );

        $permalink    = (string) get_permalink( $post_id );
        $featured_id  = (int) get_post_thumbnail_id( $post_id );
        $landscape    = $featured_id > 0 ? ImageFamily::landscape( $featured_id ) : null;
        $sitemap_urls = self::sitemap_urls( $post_id );
        $checks       = [];

        foreach ( $sitemap_urls as $kind => $url ) {
            $response = wp_remote_get(
                $url,
                [
                    'timeout'     => 15,
                    'redirection' => 3,
                    'headers'     => [ 'Cache-Control' => 'no-cache' ],
                ]
            );

            $code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
            $body = is_wp_error( $response ) ? '' : html_entity_decode( (string) wp_remote_retrieve_body( $response ), ENT_QUOTES | ENT_XML1 );
            $ok   = $code >= 200 && $code < 300;

            if ( 'post_type' === $kind ) {
                $ok = $ok && '' !== $permalink && false !== strpos( $body, $permalink );
                if ( null !== $landscape ) {
                    $ok = $ok
                        && false !== strpos( $body, '<image:loc>' )
                        && false !== strpos( $body, (string) $landscape['url'] );
                }
            }

            if ( 'news' === $kind ) {
                $ok = $ok && '' !== $permalink && false !== strpos( $body, $permalink );
            }

            $checks[ $kind ] = [ 'url' => $url, 'code' => $code, 'ok' => $ok ];
        }

        $failed = array_filter( $checks, static fn( array $check ): bool => empty( $check['ok'] ) );
        if ( empty( $failed ) ) {
            do_action( 'hws_base_tools_article_sitemaps_verified', $post_id, $checks );
            return;
        }

        if ( $attempt < 3 ) {
            self::schedule_refresh( $post_id, $attempt + 1, 60 * $attempt );
            return;
        }

        do_action( 'hws_base_tools_article_sitemaps_failed', $post_id, $checks );
        error_log( sprintf( 'HWS article sitemap verification failed for post %d after %d attempts.', $post_id, $attempt ) );
    }

    /**
     * @return array<string,string>
     */
    public static function sitemap_urls( int $post_id ): array {
        $post = get_post( $post_id );
        if ( ! is_object( $post ) || empty( $post->post_type ) ) {
            return [];
        }

        $post_type = sanitize_key( (string) $post->post_type );
        $urls      = [
            'index'     => home_url( '/sitemap_index.xml' ),
            'post_type' => home_url( '/' . $post_type . '-sitemap.xml' ),
        ];

        if ( self::news_sitemap_includes( $post_type ) ) {
            $urls['news'] = home_url( '/news-sitemap.xml' );
        }

        return $urls;
    }

    private static function evict_all_types(): void {
        $types = [ '1' ];
        $types = array_merge( $types, array_values( get_post_types( [ 'public' => true ], 'names' ) ) );
        $types = array_merge( $types, array_values( get_taxonomies( [ 'public' => true ], 'names' ) ) );
        $types[] = 'author';

        if ( self::news_sitemap_enabled() ) {
            $types[] = 'news';
        }

        foreach ( array_unique( array_map( 'sanitize_key', $types ) ) as $type ) {
            self::evict_type( $type );
        }
    }

    private static function page_count( string $type ): int {
        if ( in_array( $type, [ '1', 'news' ], true ) ) {
            return 1;
        }

        $count = 1;
        if ( function_exists( 'post_type_exists' ) && post_type_exists( $type ) ) {
            $statuses = wp_count_posts( $type );
            $count    = (int) ( $statuses->publish ?? 0 ) + (int) ( $statuses->inherit ?? 0 );
        } elseif ( 'author' === $type ) {
            $users = count_users();
            $count = (int) ( $users['total_users'] ?? 1 );
        } elseif ( function_exists( 'taxonomy_exists' ) && taxonomy_exists( $type ) ) {
            $term_count = wp_count_terms( [ 'taxonomy' => $type, 'hide_empty' => true ] );
            $count      = is_wp_error( $term_count ) ? 1 : (int) $term_count;
        }

        $settings = get_option( 'rank-math-options-sitemap', [] );
        $per_page = max( 1, (int) ( is_array( $settings ) ? ( $settings['items_per_page'] ?? 100 ) : 100 ) );
        $pages    = (int) ceil( max( 1, $count ) / $per_page ) + 1;

        return min( 5000, max( 1, $pages ) );
    }

    private static function news_sitemap_enabled(): bool {
        $modules = get_option( 'rank_math_modules', [] );
        if ( is_string( $modules ) ) {
            $modules = maybe_unserialize( $modules );
        }

        return defined( 'RANK_MATH_PRO_VERSION' )
            && in_array( 'news-sitemap', (array) $modules, true );
    }

    private static function news_sitemap_includes( string $post_type ): bool {
        if ( ! self::news_sitemap_enabled() ) {
            return false;
        }

        $settings   = get_option( 'rank-math-options-sitemap', [] );
        $post_types = is_array( $settings ) ? (array) ( $settings['news_sitemap_post_type'] ?? [] ) : [];

        return in_array( $post_type, $post_types, true );
    }
}
