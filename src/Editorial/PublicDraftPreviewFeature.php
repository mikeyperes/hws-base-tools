<?php

namespace HWS\BaseTools\Editorial;

defined( 'ABSPATH' ) || exit;

/**
 * Public draft links: a draft or pending post opens for anyone, without logging
 * in, at its own URL plus the site's secret key (`?p=123&draft_key=amber-falcon-river-42`),
 * without a time limit. Default off.
 *
 * The key is one human-friendly secret per site, created the first time the
 * feature runs and kept in an option; deleting the option rotates it. A link
 * without the right key behaves exactly as before
 * (404 for visitors). Only the main singular front-end query is touched; the
 * visitor gets a request-local copy marked `publish`, so the stored and cached
 * post keeps its real status. The view is never cached and is marked noindex.
 */
final class PublicDraftPreviewFeature {
    public const FEATURE_OPTION = 'enable_public_draft_preview';
    public const KEY_OPTION     = 'hws_public_draft_key';
    public const PARAM          = 'draft_key';
    public const CREATED_META   = '_hws_created_at';
    public const STATUSES       = [ 'draft', 'pending' ];

    private const ADJECTIVES = [ 'amber', 'bold', 'brisk', 'calm', 'clever', 'coral', 'crisp', 'dusty', 'eager', 'fancy', 'gentle', 'golden', 'hazel', 'humble', 'ivory', 'jolly', 'keen', 'lively', 'lucky', 'mellow', 'misty', 'noble', 'olive', 'plucky', 'proud', 'quiet', 'rapid', 'rosy', 'rustic', 'sandy', 'silver', 'sunny', 'swift', 'tidy', 'velvet', 'vivid', 'warm', 'wise', 'witty', 'zesty' ];
    private const NOUNS      = [ 'anchor', 'badger', 'beacon', 'birch', 'canyon', 'cedar', 'comet', 'falcon', 'fern', 'harbor', 'heron', 'island', 'jasper', 'lantern', 'maple', 'meadow', 'orchid', 'otter', 'pebble', 'pine', 'quartz', 'raven', 'river', 'robin', 'saddle', 'spruce', 'summit', 'thistle', 'tiger', 'valley', 'willow', 'wren' ];

    private static bool $active = false;

    public static function activate(): void {
        if ( self::$active ) {
            return;
        }

        self::$active = true;
        self::key();
        add_action( 'wp_insert_post', [ self::class, 'record_created' ], 10, 3 );
        add_filter( 'posts_results', [ self::class, 'filter_posts_results' ], 10, 2 );
        add_filter( 'post_row_actions', [ self::class, 'row_actions' ], 10, 2 );
        add_filter( 'page_row_actions', [ self::class, 'row_actions' ], 10, 2 );
        add_action( 'rest_api_init', [ self::class, 'register_rest_field' ] );
    }

    /** The site's secret key, created on first use. */
    public static function key(): string {
        $key = (string) get_option( self::KEY_OPTION, '' );
        if ( '' === $key ) {
            $key = self::generate_key();
            update_option( self::KEY_OPTION, $key, false );
        }

        return $key;
    }

    public static function generate_key(): string {
        $word = static fn ( array $list ): string => $list[ random_int( 0, count( $list ) - 1 ) ];

        return $word( self::ADJECTIVES ) . '-' . $word( self::NOUNS ) . '-' . $word( self::NOUNS ) . '-' . random_int( 10, 99 );
    }

    /** Remember when a post was first created; drafts move their own date on every save. */
    public static function record_created( $post_id, $post, $update ): void {
        if ( $update || ! $post instanceof \WP_Post || 'revision' === $post->post_type ) {
            return;
        }
        if ( '' === (string) get_post_meta( (int) $post_id, self::CREATED_META, true ) ) {
            update_post_meta( (int) $post_id, self::CREATED_META, (string) time() );
        }
    }

    /** When the post was created: the recorded time, else the post's own date. */
    public static function created_timestamp( \WP_Post $post ): ?int {
        $recorded = get_post_meta( $post->ID, self::CREATED_META, true );
        if ( is_numeric( $recorded ) && (int) $recorded > 0 ) {
            return (int) $recorded;
        }
        $date = get_post_datetime( $post, 'date', 'local' );

        return $date ? $date->getTimestamp() : null;
    }

    public static function is_eligible( string $status, ?int $created_at, int $now ): bool {
        // Keep the existing signature for callers; draft access does not expire.
        return in_array( $status, self::STATUSES, true );
    }

    public static function key_matches( $given ): bool {
        $stored = (string) get_option( self::KEY_OPTION, '' );

        return '' !== $stored && is_string( $given ) && hash_equals( $stored, trim( $given ) );
    }

    /** The non-expiring public link, available only when this site enables the feature. */
    public static function link( int $post_id ): ?string {
        $post = get_post( $post_id );
        if ( ! get_option( self::FEATURE_OPTION, false ) || ! $post instanceof \WP_Post
            || ! empty( $post->post_password ) || ! is_post_type_viewable( $post->post_type )
            || ! self::is_eligible( $post->post_status, self::created_timestamp( $post ), time() ) ) {
            return null;
        }

        return add_query_arg( self::PARAM, rawurlencode( self::key() ), get_permalink( $post ) );
    }

    /**
     * @param array<int,mixed> $posts
     * @return array<int,mixed>
     */
    public static function filter_posts_results( array $posts, $query ): array {
        if ( 1 !== count( $posts ) || ! self::is_target_query( $query ) || ! self::key_matches( $_GET[ self::PARAM ] ?? null ) ) {
            return $posts;
        }

        $post = $posts[0];
        if ( ! get_option( self::FEATURE_OPTION, false ) || ! $post instanceof \WP_Post
            || ! empty( $post->post_password ) || ! is_post_type_viewable( $post->post_type )
            || ! self::is_eligible( $post->post_status, self::created_timestamp( $post ), time() ) ) {
            return $posts;
        }

        // A copy, so the object cache never stores a draft as published.
        $visible              = clone $post;
        $visible->post_status = 'publish';
        $posts[0]             = $visible;
        self::protect_response();

        return $posts;
    }

    /**
     * @param array<string,string> $actions
     * @return array<string,string>
     */
    public static function row_actions( array $actions, $post ): array {
        if ( $post instanceof \WP_Post && current_user_can( 'edit_post', $post->ID ) ) {
            $link = self::link( $post->ID );
            if ( null !== $link ) {
                $actions['hws_public_draft_link'] = '<a href="' . esc_url( $link ) . '" target="_blank" rel="noopener">Public draft link</a>';
            }
        }

        return $actions;
    }

    private static function is_target_query( $query ): bool {
        if ( ! $query instanceof \WP_Query || ! $query->is_main_query() || is_admin() || wp_doing_ajax()
            || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return false;
        }

        return $query->is_singular() && ! $query->is_preview() && ! $query->is_feed() && ! $query->is_embed();
    }

    private static function protect_response(): void {
        if ( ! headers_sent() ) {
            nocache_headers();
            header( 'X-Robots-Tag: noindex, nofollow', true );
        }
        do_action( 'litespeed_control_set_nocache', 'hws public draft link' );
        if ( function_exists( 'wp_robots_no_robots' ) ) {
            add_filter( 'wp_robots', 'wp_robots_no_robots' );
        }
        add_filter( 'comments_open', '__return_false' );
        add_filter( 'pings_open', '__return_false' );
    }

    /** Read-only REST field; the site code is returned only to an editor of this post. */
    public static function register_rest_field(): void {
        foreach ( get_post_types( [ 'show_in_rest' => true ], 'names' ) as $type ) {
            register_rest_field( $type, 'hws_public_draft_url', [
                'get_callback' => static function ( array $object ): ?string {
                    $id = (int) ( $object['id'] ?? 0 );
                    return current_user_can( 'edit_post', $id ) ? self::link( $id ) : null;
                },
                'schema' => [ 'type' => [ 'string', 'null' ], 'readonly' => true, 'context' => [ 'edit' ] ],
            ] );
        }
    }

    /** @return array<string,mixed> */
    public static function definition(): array {
        return [
            'id'               => self::FEATURE_OPTION,
            'name'             => 'Public Draft Links',
            'description'      => 'Lets anyone with the link open a draft or pending post without logging in. Links do not expire.',
            'info'             => 'Default off. Uses the post URL plus the existing site code in draft_key. Private and password-protected posts remain private. Disabling this feature or rotating the site code revokes draft access. Views are not cached, are marked noindex, and have comments closed.',
            'function'         => 'enable_public_draft_preview',
            'scope_admin_only' => false,
            'code_example'     => 'https://example.com/?p=123&draft_key=<site-code>',
        ];
    }
}
