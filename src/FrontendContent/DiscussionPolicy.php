<?php

namespace HWS\BaseTools\FrontendContent;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the site-wide HWS discussion policy authoritative over content writers.
 */
final class DiscussionPolicy implements ModuleInterface {
    public function register(): void {
        add_filter( 'wp_insert_post_data', [ self::class, 'enforce_post_data' ], PHP_INT_MAX, 4 );
        add_filter( 'comments_open', [ self::class, 'enforce_comments_open' ], PHP_INT_MAX, 2 );
        add_filter( 'pings_open', [ self::class, 'enforce_pings_open' ], PHP_INT_MAX, 2 );
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $postarr
     * @param array<string,mixed> $unsanitized_postarr
     * @return array<string,mixed>
     */
    public static function enforce_post_data( array $data, array $postarr = [], array $unsanitized_postarr = [], bool $update = false ): array {
        unset( $postarr, $unsanitized_postarr, $update );

        if ( self::comments_disabled() ) {
            $data['comment_status'] = 'closed';
        }

        if ( self::pings_disabled() ) {
            $data['ping_status'] = 'closed';
        }

        return $data;
    }

    public static function enforce_comments_open( bool $open, int $post_id = 0 ): bool {
        unset( $post_id );

        return self::comments_disabled() ? false : $open;
    }

    public static function enforce_pings_open( bool $open, int $post_id = 0 ): bool {
        unset( $post_id );

        return self::pings_disabled() ? false : $open;
    }

    public static function comments_disabled(): bool {
        return 'closed' === (string) get_option( 'default_comment_status', 'open' );
    }

    public static function pings_disabled(): bool {
        return 'closed' === (string) get_option( 'default_ping_status', 'open' );
    }
}
