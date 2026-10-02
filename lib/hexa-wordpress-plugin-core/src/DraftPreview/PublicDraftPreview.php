<?php

namespace Hexa\PluginCore\DraftPreview;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Lets anyone open a recent draft at its own URL (`?p=ID`, `?page_id=ID`,
 * `?post_type=x&p=ID`, the link WordPress reports for a draft) without logging in.
 *
 * Only the main singular front-end query is affected. The visitor receives a
 * request-local copy marked `publish`, so WordPress's own status check passes
 * while the cached post keeps its real status. The view is never cached and
 * asks search engines not to index it. Hosts decide when to register it.
 */
final class PublicDraftPreview implements ModuleInterface {
    public const DEFAULT_WINDOW = 86400;
    public const DEFAULT_STATUSES = [ 'draft' ];

    private int $window;
    /** @var list<string> */
    private array $statuses;

    /** @param list<string> $statuses */
    public function __construct( int $window = self::DEFAULT_WINDOW, array $statuses = self::DEFAULT_STATUSES ) {
        $this->window   = max( 1, $window );
        $this->statuses = array_values( array_filter( array_map( 'strval', $statuses ) ) );
    }

    public function register(): void {
        add_filter( 'posts_results', [ $this, 'filter_posts_results' ], 10, 2 );
    }

    /**
     * @param array<int,mixed> $posts
     * @return array<int,mixed>
     */
    public function filter_posts_results( array $posts, $query ): array {
        if ( 1 !== count( $posts ) || ! $this->is_target_query( $query ) ) {
            return $posts;
        }

        $post = $posts[0];
        if ( ! $post instanceof \WP_Post || ! $this->is_viewable_type( $post->post_type ) ) {
            return $posts;
        }

        if ( ! self::is_eligible( $post->post_status, self::draft_timestamp( $post ), time(), $this->window, $this->statuses ) ) {
            return $posts;
        }

        // Editors already see drafts through WordPress's own preview.
        if ( current_user_can( 'edit_post', $post->ID ) ) {
            return $posts;
        }

        // A copy, so update_post_caches() never stores a draft as published.
        $visible              = clone $post;
        $visible->post_status = 'publish';
        $posts[0]             = $visible;

        $this->protect_response();

        return $posts;
    }

    /** @param list<string> $statuses */
    public static function is_eligible( string $status, ?int $drafted_at, int $now, int $window = self::DEFAULT_WINDOW, array $statuses = self::DEFAULT_STATUSES ): bool {
        if ( ! in_array( $status, $statuses, true ) || null === $drafted_at ) {
            return false;
        }

        $age = $now - $drafted_at;

        return $age >= 0 && $age < $window;
    }

    /**
     * The draft's date as a Unix timestamp. Drafts have no GMT date until they
     * publish, so this reads the local date in the site timezone.
     */
    public static function draft_timestamp( \WP_Post $post ): ?int {
        $date = get_post_datetime( $post, 'date', 'local' );

        return $date ? $date->getTimestamp() : null;
    }

    private function is_target_query( $query ): bool {
        if ( ! $query instanceof \WP_Query || ! $query->is_main_query() ) {
            return false;
        }

        if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return false;
        }

        return $query->is_singular() && ! $query->is_preview() && ! $query->is_feed() && ! $query->is_embed();
    }

    private function is_viewable_type( string $post_type ): bool {
        return function_exists( 'is_post_type_viewable' ) ? is_post_type_viewable( $post_type ) : true;
    }

    private function protect_response(): void {
        if ( ! headers_sent() ) {
            nocache_headers();
            header( 'X-Robots-Tag: noindex, nofollow', true );
        }

        do_action( 'litespeed_control_set_nocache', 'hexa public draft preview' );

        if ( function_exists( 'wp_robots_no_robots' ) ) {
            add_filter( 'wp_robots', 'wp_robots_no_robots' );
        }

        add_filter( 'comments_open', '__return_false' );
        add_filter( 'pings_open', '__return_false' );
    }
}
