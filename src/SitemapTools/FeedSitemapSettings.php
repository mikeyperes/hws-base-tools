<?php

namespace HWS\BaseTools\SitemapTools;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * The Settings and Caching sections of the RSS Feeds and Sitemaps tabs:
 * how many items each feed holds, how many addresses each sitemap page holds,
 * how long LiteSpeed keeps each one, and clearing them when a post is published or updated.
 */
final class FeedSitemapSettings implements ModuleInterface {
    public const OPTION = 'hws_feed_sitemap_settings';
    public const UNLIMITED = 1000000;
    /** Lets a plugin that controls feed caching itself say so: return [ 'label' => ..., 'url' => ... ]. */
    public const FEED_CACHE_OWNER_FILTER = 'hws_base_tools_feed_cache_owner';

    /** Items per feed; -1 is unlimited, 0 the site default (Settings, Reading). */
    public const FEED_LIMITS = [ 0 => 'Site default', 5 => '5', 10 => '10', 20 => '20', 50 => '50', 100 => '100', -1 => 'Unlimited' ];
    /** Addresses per sitemap page; search engines read at most 50,000. */
    public const SITEMAP_SIZES = [ 100 => '100', 500 => '500', 1000 => '1,000', 2000 => '2,000', 5000 => '5,000', 10000 => '10,000', 50000 => '50,000 (the most search engines read)' ];
    /** Cache length in seconds; 'default' keeps LiteSpeed's own setting, 0 never caches. */
    public const CACHE_TIMES = [ 'default' => 'LiteSpeed\'s own setting', '0' => 'Don\'t cache', '900' => '15 minutes', '3600' => '1 hour', '21600' => '6 hours', '86400' => '1 day', '604800' => '1 week' ];

    public function register(): void {
        add_action( 'pre_get_posts', [ self::class, 'apply_feed_limit' ] );
        add_filter( 'comment_feed_limits', [ self::class, 'apply_comment_feed_limit' ], 10, 2 );
        add_action( 'template_redirect', [ self::class, 'apply_feed_cache' ], 1 );
        add_action( 'init', [ self::class, 'apply_sitemap_cache' ], 1 );
        add_filter( 'wp_sitemaps_max_urls', [ self::class, 'core_sitemap_size' ] );
        add_action( 'transition_post_status', [ self::class, 'clear_on_publish' ], 10, 3 );
    }

    /** @return array{feed_limits:array<string,int>,feed_cache:string,feed_clear:bool,sitemap_cache:string,sitemap_clear:bool,sitemap_size:int} */
    public static function get(): array {
        $saved = get_option( self::OPTION, [] );
        $saved = is_array( $saved ) ? $saved : [];

        return [
            'feed_limits'   => array_map( 'intval', (array) ( $saved['feed_limits'] ?? [] ) ),
            'feed_cache'    => isset( self::CACHE_TIMES[ (string) ( $saved['feed_cache'] ?? '' ) ] ) ? (string) $saved['feed_cache'] : 'default',
            'feed_clear'    => ! isset( $saved['feed_clear'] ) || ! empty( $saved['feed_clear'] ),
            'sitemap_cache' => self::sitemap_cache(),
            'sitemap_clear' => ! isset( $saved['sitemap_clear'] ) || ! empty( $saved['sitemap_clear'] ),
            'sitemap_size'  => self::sitemap_size(),
        ];
    }

    /** Saves the posted settings for one tab ('feed' or 'sitemap'). */
    public static function save( string $kind, array $input ): void {
        $saved = get_option( self::OPTION, [] );
        $saved = is_array( $saved ) ? $saved : [];

        if ( 'feed' === $kind ) {
            $limits = [];
            foreach ( (array) ( $input['limits'] ?? [] ) as $key => $limit ) {
                $limit = (int) $limit;
                if ( isset( SitemapFeedRegistry::feeds()[ sanitize_key( (string) $key ) ] ) && isset( self::FEED_LIMITS[ $limit ] ) && 0 !== $limit ) {
                    $limits[ sanitize_key( (string) $key ) ] = $limit;
                }
            }
            $saved['feed_limits'] = $limits;
            $saved['feed_cache']  = isset( self::CACHE_TIMES[ (string) ( $input['cache'] ?? '' ) ] ) ? (string) $input['cache'] : 'default';
            $saved['feed_clear']  = ! empty( $input['clear'] );
        } else {
            $cache                 = isset( self::CACHE_TIMES[ (string) ( $input['cache'] ?? '' ) ] ) ? (string) $input['cache'] : 'default';
            $saved['sitemap_cache'] = $cache;
            $saved['sitemap_clear'] = ! empty( $input['clear'] );
            // "Don't cache" is the existing sitemap no-cache switch, which also stops Rank Math's own sitemap cache.
            update_option( 'hws_sitemaps_litespeed_nocache_enabled', '0' === $cache ? 'yes' : 'no', false );
            $size = (int) ( $input['size'] ?? 0 );
            if ( isset( self::SITEMAP_SIZES[ $size ] ) && $size !== self::sitemap_size() ) {
                if ( SitemapFeedRegistry::rank_math_module( 'sitemap' ) ) {
                    $options                   = (array) get_option( 'rank-math-options-sitemap', [] );
                    $options['items_per_page'] = $size;
                    update_option( 'rank-math-options-sitemap', $options );
                    if ( class_exists( '\RankMath\Sitemap\Cache' ) && method_exists( '\RankMath\Sitemap\Cache', 'invalidate_storage' ) ) {
                        \RankMath\Sitemap\Cache::invalidate_storage();
                    }
                } else {
                    $saved['sitemap_size'] = $size;
                }
                foreach ( SitemapFeedRegistry::sitemaps() as $sitemap ) {
                    do_action( 'litespeed_purge_url', $sitemap['url'] );
                }
            }
        }

        update_option( self::OPTION, $saved, true );
    }

    public static function feed_limit( string $key ): int {
        return (int) ( self::get()['feed_limits'][ $key ] ?? 0 );
    }

    public static function apply_feed_limit( $query ): void {
        if ( ! $query instanceof \WP_Query || ! $query->is_main_query() || ! $query->is_feed() || $query->is_comment_feed() ) {
            return;
        }
        $limit = self::feed_limit( SitemapFeedRegistry::current_feed_key() );
        if ( 0 !== $limit ) {
            // WordPress pages feeds even with -1, which breaks the query, so unlimited is a limit no site reaches.
            $query->set( 'posts_per_rss', -1 === $limit ? self::UNLIMITED : $limit );
        }
    }

    public static function apply_comment_feed_limit( $limits, $query = null ) {
        $limit = self::feed_limit( 'comments' );
        if ( 0 === $limit ) {
            return $limits;
        }
        return -1 === $limit ? '' : 'LIMIT ' . $limit;
    }

    /**
     * The plugin that already decides feed caching, if any; Base Tools' cache length then does not apply.
     *
     * @return array{label:string,url:string}|null
     */
    public static function feed_cache_owner(): ?array {
        $owner = null;
        // Hexa PR Wire Distributor's "Disable local RSS caching" keeps press release feeds current.
        if ( function_exists( 'hpr_distributor\\disable_rss_caching' ) && get_option( 'disable_rss_caching', false ) ) {
            $owner = [ 'label' => 'Hexa PR Wire Distributor\'s "Disable local RSS caching" setting', 'url' => admin_url( 'options-general.php?page=hpr-distributor' ) ];
        }
        $owner = apply_filters( self::FEED_CACHE_OWNER_FILTER, $owner );
        return is_array( $owner ) && ! empty( $owner['label'] ) ? [ 'label' => (string) $owner['label'], 'url' => (string) ( $owner['url'] ?? '' ) ] : null;
    }

    public static function apply_feed_cache(): void {
        if ( is_feed() && null === self::feed_cache_owner() ) {
            self::apply_cache( self::get()['feed_cache'], 'hws-feed-cache' );
        }
    }

    public static function apply_sitemap_cache(): void {
        $path = strtolower( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ) );
        if ( '' !== $path && preg_match( '#/(sitemap(_index)?|wp-sitemap|[^/]+-sitemap)([^/]*\.xml)?$#', $path ) ) {
            // "Don't cache" is handled by the sitemap no-cache switch.
            $cache = self::sitemap_cache();
            if ( '0' !== $cache ) {
                self::apply_cache( $cache, 'hws-sitemap-cache' );
            }
        }
    }

    private static function apply_cache( string $cache, string $reason ): void {
        if ( 'default' === $cache ) {
            return;
        }
        if ( '0' === $cache ) {
            do_action( 'litespeed_control_set_nocache', $reason );
            return;
        }
        // LiteSpeed leaves feeds uncached unless told otherwise; a chosen length caches them for that long.
        do_action( 'litespeed_control_force_cacheable', $reason );
        do_action( 'litespeed_control_set_ttl', (int) $cache, $reason );
    }

    public static function core_sitemap_size( $size ): int {
        $saved = get_option( self::OPTION, [] );
        return is_array( $saved ) && ! empty( $saved['sitemap_size'] ) ? (int) $saved['sitemap_size'] : (int) $size;
    }

    /** Clears the cached copy of the feeds and sitemaps a post appears in when it is published, updated or unpublished. */
    public static function clear_on_publish( $new_status, $old_status, $post ): void {
        if ( ! $post instanceof \WP_Post || ( 'publish' !== $new_status && 'publish' !== $old_status ) || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
            return;
        }
        $settings = self::get();
        $urls     = [];
        if ( $settings['feed_clear'] ) {
            foreach ( SitemapFeedRegistry::feeds() as $feed ) {
                $urls[] = $feed['url'];
            }
            foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
                foreach ( (array) wp_get_post_terms( $post->ID, $taxonomy, [ 'fields' => 'ids' ] ) as $term_id ) {
                    $link = get_term_feed_link( (int) $term_id, $taxonomy );
                    if ( $link ) {
                        $urls[] = $link;
                    }
                }
            }
            $urls[] = get_author_feed_link( (int) $post->post_author );
        }
        if ( $settings['sitemap_clear'] ) {
            foreach ( SitemapFeedRegistry::sitemaps() as $sitemap ) {
                $urls[] = $sitemap['url'];
            }
        }
        foreach ( array_unique( array_filter( $urls ) ) as $url ) {
            \HWS\BaseTools\LiteSpeed\LiteSpeedPurge::url_quietly( $url );
        }
    }

    private static function sitemap_cache(): string {
        if ( 'yes' === (string) get_option( 'hws_sitemaps_litespeed_nocache_enabled', 'no' ) ) {
            return '0';
        }
        $saved = get_option( self::OPTION, [] );
        $cache = is_array( $saved ) ? (string) ( $saved['sitemap_cache'] ?? 'default' ) : 'default';
        return isset( self::CACHE_TIMES[ $cache ] ) && '0' !== $cache ? $cache : 'default';
    }

    /** Rank Math's own "Links per sitemap" setting when it builds the sitemaps, otherwise WordPress's. */
    private static function sitemap_size(): int {
        if ( SitemapFeedRegistry::rank_math_module( 'sitemap' ) ) {
            $options = (array) get_option( 'rank-math-options-sitemap', [] );
            return (int) ( $options['items_per_page'] ?? 100 );
        }
        $saved = get_option( self::OPTION, [] );
        return is_array( $saved ) && ! empty( $saved['sitemap_size'] ) ? (int) $saved['sitemap_size'] : 2000;
    }
}
