<?php

namespace HWS\BaseTools\LiteSpeed;

/**
 * Clears one address from LiteSpeed's page cache without an admin notice.
 *
 * LiteSpeed's `litespeed_purge_url` action shows a "Purge url …" notice for every address, stored
 * site-wide until the next admin page load, so automatic clears on publish flooded every admin's
 * dashboard. Its own method takes a "quiet" flag; the action is only the fallback.
 */
final class LiteSpeedPurge {
    public static function url_quietly( $url ): void {
        $url = is_string( $url ) ? trim( $url ) : '';
        if ( '' === $url ) {
            return;
        }
        if ( class_exists( '\\LiteSpeed\\Purge' ) && method_exists( '\\LiteSpeed\\Purge', 'cls' ) ) {
            $purge = \LiteSpeed\Purge::cls();
            if ( method_exists( $purge, 'purge_url' ) ) {
                $purge->purge_url( $url, false, true );
                return;
            }
        }
        do_action( 'litespeed_purge_url', $url );
    }
}
