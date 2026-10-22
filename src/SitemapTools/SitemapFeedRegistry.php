<?php

namespace HWS\BaseTools\SitemapTools;

defined( 'ABSPATH' ) || exit;

/**
 * Every RSS feed and XML sitemap the site serves, listed on Settings, Sitemaps & Feeds.
 *
 * Other plugins add their own through two filters; each entry is an array:
 *
 *     add_filter( 'hws_base_tools_feeds', function ( array $feeds ): array {
 *         $feeds['my_feed'] = [
 *             'label'      => 'My feed',
 *             'group'      => 'My Plugin',
 *             'url'        => home_url( '/feed/my-feed/' ),
 *             'toggle'     => true,          // Base Tools may turn it off (404).
 *             'match'      => static fn(): bool => is_feed( 'my-feed' ), // Identifies a request for it.
 *             'total'      => static fn(): int => 12,      // Published items behind the feed (optional).
 *             'latest'     => static fn(): int => time(),  // Newest publish timestamp (optional).
 *             'available'  => true,          // False shows the reason instead of checking it.
 *             'note'       => '',            // Why it is unavailable, or a short description.
 *             'settings'   => admin_url( '...' ), // Where it is configured (optional).
 *         ];
 *         return $feeds;
 *     } );
 *
 *     add_filter( 'hws_base_tools_sitemaps', function ( array $sitemaps ): array {
 *         $sitemaps['my_sitemap'] = [ 'label' => 'My sitemap', 'group' => 'My Plugin', 'url' => home_url( '/my-sitemap.xml' ) ];
 *         return $sitemaps;
 *     } );
 */
final class SitemapFeedRegistry {
    public const FEEDS_FILTER    = 'hws_base_tools_feeds';
    public const SITEMAPS_FILTER = 'hws_base_tools_sitemaps';
    public const DISABLED_OPTION = 'hws_feeds_disabled';
    public const RESULTS_OPTION  = 'hws_sitemap_feed_checks';

    /** @var array<string,array>|null */
    private static ?array $feeds = null;

    /** @return array<string,array> */
    public static function feeds(): array {
        if ( null !== self::$feeds ) {
            return self::$feeds;
        }

        $feeds = [
            'main'     => [
                'label'  => 'All posts',
                'group'  => 'WordPress',
                'url'    => get_feed_link(),
                'match'  => static fn(): bool => self::is_main_feed(),
                'total'  => static fn(): int => self::published( 'post' ),
                'latest' => static fn(): int => self::latest( 'post' ),
            ],
            'comments' => [
                'label'  => 'Comments',
                'group'  => 'WordPress',
                'url'    => get_feed_link( 'comments_rss2' ),
                'match'  => static fn(): bool => is_comment_feed() && ! is_singular(),
                'total'  => static fn(): int => (int) wp_count_comments()->approved,
                'latest' => static function (): int {
                    $comments = get_comments( [ 'number' => 1, 'status' => 'approve', 'orderby' => 'comment_date_gmt' ] );
                    return $comments ? (int) strtotime( $comments[0]->comment_date_gmt . ' UTC' ) : 0;
                },
            ],
        ];

        foreach ( self::feed_post_types() as $post_type => $object ) {
            $archive = get_post_type_archive_feed_link( $post_type );
            $feeds[ 'post_type_' . $post_type ] = [
                'label'  => (string) $object->labels->name,
                'group'  => 'Content types',
                'url'    => $archive ? $archive : add_query_arg( [ 'post_type' => $post_type, 'feed' => get_default_feed() ], home_url( '/' ) ),
                'match'  => static fn(): bool => self::is_post_type_feed( $post_type ),
                'total'  => static fn(): int => self::published( $post_type ),
                'latest' => static fn(): int => self::latest( $post_type ),
            ];
        }

        foreach ( [ 'category' => 'Each category', 'post_tag' => 'Each tag' ] as $taxonomy => $label ) {
            $terms = get_terms( [ 'taxonomy' => $taxonomy, 'orderby' => 'count', 'order' => 'DESC', 'number' => 1, 'hide_empty' => true ] );
            $term  = is_array( $terms ) && $terms ? $terms[0] : null;
            $feeds[ 'taxonomy_' . $taxonomy ] = [
                'label'     => $label,
                'group'     => 'Archives',
                'url'       => $term ? get_term_feed_link( $term->term_id, $taxonomy ) : '',
                'note'      => $term ? 'Checked with the largest one, ' . $term->name . '.' : 'None have posts yet.',
                'available' => (bool) $term,
                'match'     => static fn(): bool => is_feed() && ( 'category' === $taxonomy ? is_category() : is_tag() ),
                'total'     => $term ? static fn(): int => (int) $term->count : null,
            ];
        }

        $author = get_users( [ 'has_published_posts' => [ 'post' ], 'number' => 1, 'orderby' => 'post_count', 'order' => 'DESC', 'fields' => [ 'ID', 'display_name' ] ] );
        $feeds['authors'] = [
            'label'     => 'Each author',
            'group'     => 'Archives',
            'url'       => $author ? get_author_feed_link( (int) $author[0]->ID ) : '',
            'note'      => $author ? 'Checked with the author with the most posts, ' . $author[0]->display_name . '.' : 'No author has published yet.',
            'available' => (bool) $author,
            'match'     => static fn(): bool => is_feed() && is_author(),
        ];

        $feeds['rank_math_podcast'] = [
            'label'     => 'Podcast',
            'group'     => 'Rank Math Pro',
            'url'       => home_url( '/feed/podcast/' ),
            'available' => self::rank_math_module( 'podcast' ),
            'note'      => self::rank_math_module( 'podcast' ) ? 'Rank Math Pro podcast feed.' : 'Rank Math Pro\'s Podcast module is off, so there is no podcast feed.',
            'settings'  => admin_url( 'admin.php?page=rank-math' ),
            'match'     => static fn(): bool => is_feed( 'podcast' ),
        ];

        $feeds = (array) apply_filters( self::FEEDS_FILTER, $feeds );

        return self::$feeds = self::normalise( $feeds );
    }

    /** @return array<string,array> */
    public static function sitemaps(): array {
        $rank_math = self::rank_math_module( 'sitemap' );
        $sitemaps  = [
            'sitemap_index' => [
                'label' => 'Sitemap index',
                'group' => $rank_math ? 'Rank Math' : 'WordPress',
                'url'   => $rank_math ? home_url( '/sitemap_index.xml' ) : home_url( '/wp-sitemap.xml' ),
            ],
        ];

        if ( $rank_math || self::rank_math_module( 'news-sitemap' ) ) {
            $sitemaps['rank_math_news'] = [
                'label'     => 'News sitemap',
                'group'     => 'Rank Math Pro',
                'url'       => home_url( '/news-sitemap.xml' ),
                'kind'      => 'news',
                'available' => self::rank_math_module( 'news-sitemap' ),
                'note'      => self::rank_math_module( 'news-sitemap' ) ? 'Google News sitemap: articles from the last two days.' : 'Rank Math Pro\'s News Sitemap module is off.',
                'settings'  => admin_url( 'admin.php?page=rank-math-options-sitemap#setting-panel-news-sitemap' ),
            ];
            $sitemaps['rank_math_video'] = [
                'label'     => 'Video sitemap',
                'group'     => 'Rank Math Pro',
                'url'       => home_url( '/video-sitemap.xml' ),
                'available' => self::rank_math_module( 'video-sitemap' ),
                'note'      => self::rank_math_module( 'video-sitemap' ) ? 'Posts and pages with embedded video.' : 'Rank Math Pro\'s Video Sitemap module is off.',
                'settings'  => admin_url( 'admin.php?page=rank-math-options-sitemap#setting-panel-video-sitemap' ),
            ];
        }

        $settings = $rank_math ? (array) get_option( 'rank-math-options-sitemap', [] ) : [];
        foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $post_type => $object ) {
            if ( 'attachment' === $post_type ) {
                continue;
            }
            $included = ! $rank_math || 'on' === ( $settings[ 'pt_' . $post_type . '_sitemap' ] ?? 'off' );
            $sitemaps[ 'post_type_' . $post_type ] = [
                'label'     => (string) $object->labels->name,
                'group'     => 'Content types',
                'url'       => $rank_math ? home_url( '/' . $post_type . '-sitemap.xml' ) : home_url( '/wp-sitemap-posts-' . $post_type . '-1.xml' ),
                'available' => $included && self::published( $post_type ) > 0,
                'note'      => ! $included ? 'Left out of the sitemap in Rank Math.' : ( self::published( $post_type ) > 0 ? '' : 'Nothing published yet.' ),
                'settings'  => $rank_math ? admin_url( 'admin.php?page=rank-math-options-sitemap#setting-panel-post-type-' . $post_type ) : '',
            ];
        }

        $sitemaps = (array) apply_filters( self::SITEMAPS_FILTER, $sitemaps );

        return self::normalise( $sitemaps );
    }

    public static function is_disabled( string $key ): bool {
        return in_array( $key, self::disabled(), true );
    }

    /** @return string[] */
    public static function disabled(): array {
        $value = get_option( self::DISABLED_OPTION, [] );
        return is_array( $value ) ? array_values( array_map( 'strval', $value ) ) : [];
    }

    public static function set_disabled( string $key, bool $disabled ): void {
        $keys = array_diff( self::disabled(), [ $key ] );
        if ( $disabled ) {
            $keys[] = $key;
        }
        update_option( self::DISABLED_OPTION, array_values( $keys ), true );
    }

    /** @return array<string,array> */
    public static function results(): array {
        $value = get_option( self::RESULTS_OPTION, [] );
        return is_array( $value ) ? $value : [];
    }

    public static function save_result( string $key, array $result ): void {
        $results         = self::results();
        $results[ $key ] = $result;
        update_option( self::RESULTS_OPTION, $results, false );
    }

    /** The feed entry the current request is for, or ''. */
    public static function current_feed_key(): string {
        if ( ! is_feed() ) {
            return '';
        }
        foreach ( self::feeds() as $key => $feed ) {
            if ( is_callable( $feed['match'] ) && call_user_func( $feed['match'] ) ) {
                return $key;
            }
        }
        return '';
    }

    public static function rank_math_module( string $module ): bool {
        if ( ! defined( 'RANK_MATH_VERSION' ) && ! class_exists( '\RankMath\Plugin' ) ) {
            return false;
        }
        $modules = maybe_unserialize( get_option( 'rank_math_modules', [] ) );
        return in_array( $module, (array) $modules, true );
    }

    /** Public content types visitors browse; builder parts (Elementor templates, floating buttons) and attachments are left out. */
    private static function feed_post_types(): array {
        $types = [];
        foreach ( get_post_types( [ 'public' => true, 'publicly_queryable' => true ], 'objects' ) as $name => $object ) {
            if ( 'post' === $name || 'attachment' === $name || ! empty( $object->exclude_from_search ) || empty( $object->show_in_nav_menus ) ) {
                continue;
            }
            $types[ $name ] = $object;
        }
        return $types;
    }

    private static function is_main_feed(): bool {
        if ( is_comment_feed() || is_archive() || is_search() || is_singular() || '' !== (string) get_query_var( 'post_type' ) ) {
            return false;
        }
        $feed = (string) get_query_var( 'feed' );
        return in_array( $feed, [ '', 'feed', 'rss2', 'rss', 'atom', 'rdf' ], true );
    }

    private static function is_post_type_feed( string $post_type ): bool {
        $queried = (array) get_query_var( 'post_type' );
        return is_post_type_archive( $post_type ) || ( ! is_singular() && [ $post_type ] === array_values( array_filter( $queried ) ) );
    }

    private static function published( string $post_type ): int {
        $counts = wp_count_posts( $post_type );
        return isset( $counts->publish ) ? (int) $counts->publish : 0;
    }

    private static function latest( string $post_type ): int {
        $ids = get_posts( [ 'post_type' => $post_type, 'post_status' => 'publish', 'numberposts' => 1, 'orderby' => 'date', 'order' => 'DESC', 'fields' => 'ids', 'suppress_filters' => true ] );
        return $ids ? (int) get_post_time( 'U', true, (int) $ids[0] ) : 0;
    }

    private static function normalise( array $entries ): array {
        $clean = [];
        foreach ( $entries as $key => $entry ) {
            if ( ! is_array( $entry ) || empty( $entry['label'] ) ) {
                continue;
            }
            $clean[ sanitize_key( (string) $key ) ] = array_merge(
                [
                    'label'     => '',
                    'group'     => 'Other',
                    'url'       => '',
                    'toggle'    => true,
                    'available' => true,
                    'note'      => '',
                    'settings'  => '',
                    'kind'      => '',
                    'match'     => null,
                    'total'     => null,
                    'latest'    => null,
                ],
                $entry
            );
        }
        return $clean;
    }
}
