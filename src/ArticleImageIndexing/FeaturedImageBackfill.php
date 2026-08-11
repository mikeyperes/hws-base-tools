<?php

namespace HWS\BaseTools\ArticleImageIndexing;

defined( 'ABSPATH' ) || exit;

/**
 * Gradually adds the HWS crops to existing published featured images.
 */
final class FeaturedImageBackfill {
    public const EVENT          = 'hws_base_tools_article_image_backfill_batch';
    public const VERSION_OPTION = 'hws_article_image_backfill_version';
    public const CURSOR_OPTION  = 'hws_article_image_backfill_page';
    public const VERSION        = '2';
    private const BATCH_SIZE    = 10;

    public static function register(): void {
        add_action( 'init', [ self::class, 'maybe_schedule' ], 30 );
        add_action( self::EVENT, [ self::class, 'run_batch' ] );
    }

    public static function maybe_schedule(): void {
        if ( self::VERSION === (string) get_option( self::VERSION_OPTION, '' ) ) {
            return;
        }

        if ( ! wp_next_scheduled( self::EVENT ) ) {
            wp_schedule_single_event( time() + 30, self::EVENT );
        }
    }

    public static function run_batch(): void {
        $post_types = ArticleImageModule::eligible_post_types();
        if ( empty( $post_types ) ) {
            self::finish();
            return;
        }

        $page  = max( 1, (int) get_option( self::CURSOR_OPTION, 1 ) );
        $query = new \WP_Query(
            [
                'post_type'              => $post_types,
                'post_status'            => 'publish',
                'fields'                 => 'ids',
                'posts_per_page'         => self::BATCH_SIZE,
                'paged'                  => $page,
                'orderby'                => 'ID',
                'order'                  => 'ASC',
                'ignore_sticky_posts'    => true,
                'no_found_rows'          => false,
                'update_post_meta_cache' => true,
                'update_post_term_cache' => false,
                'meta_query'             => [
                    [
                        'key'     => '_thumbnail_id',
                        'compare' => 'EXISTS',
                    ],
                ],
            ]
        );

        $posts_by_attachment = [];
        foreach ( (array) $query->posts as $post_id ) {
            $attachment_id = (int) get_post_thumbnail_id( (int) $post_id );
            if ( $attachment_id > 0 ) {
                $posts_by_attachment[ $attachment_id ][] = (int) $post_id;
            }
        }

        foreach ( $posts_by_attachment as $attachment_id => $post_ids ) {
            $result = ImageFamily::ensure( (int) $attachment_id );
            if ( empty( $result['generated'] ) ) {
                continue;
            }

            foreach ( array_unique( $post_ids ) as $post_id ) {
                do_action( 'litespeed_purge_post', $post_id );
                do_action( 'litespeed_purge_url', get_permalink( $post_id ) );

                if ( defined( 'RANK_MATH_VERSION' ) || class_exists( '\\RankMath\\Plugin' ) ) {
                    RankMathSitemapCache::evict_for_post( $post_id );
                    RankMathSitemapCache::schedule_refresh( $post_id );
                }
            }
        }

        if ( $page < (int) $query->max_num_pages ) {
            update_option( self::CURSOR_OPTION, $page + 1, false );
            wp_schedule_single_event( time() + 20, self::EVENT );
            return;
        }

        self::finish();
    }

    private static function finish(): void {
        update_option( self::VERSION_OPTION, self::VERSION, false );
        delete_option( self::CURSOR_OPTION );
        do_action( 'hws_base_tools_article_image_backfill_completed', self::VERSION );
    }
}
