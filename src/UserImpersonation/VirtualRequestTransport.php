<?php

declare( strict_types=1 );

namespace HWS\BaseTools\UserImpersonation;

defined( 'ABSPATH' ) || exit;

final class VirtualRequestTransport {
    public function __construct( private readonly VirtualRequestContext $context ) {
    }

    public function register(): void {
        add_filter( 'wp_redirect', [ $this, 'filter_redirect' ], PHP_INT_MAX, 2 );
        add_filter( 'rest_url', [ $this, 'filter_rest_url' ], PHP_INT_MAX );
        add_action( 'init', [ $this, 'mark_uncacheable' ], -PHP_INT_MAX );
        add_action( 'send_headers', [ $this, 'send_privacy_headers' ], PHP_INT_MAX );
    }

    public function filter_redirect( string $location, int $status = 302 ): string {
        return $this->context->is_active()
            ? self::append_token( $location, $this->context->request_token() )
            : $location;
    }

    public function filter_rest_url( string $url ): string {
        return $this->context->is_active()
            ? self::append_token( $url, $this->context->request_token() )
            : $url;
    }

    public function mark_uncacheable(): void {
        if ( ! $this->context->is_active() ) {
            return;
        }

        if ( ! defined( 'DONOTCACHEPAGE' ) ) {
            define( 'DONOTCACHEPAGE', true );
        }
        if ( function_exists( 'nocache_headers' ) ) {
            nocache_headers();
        }
    }

    public function send_privacy_headers(): void {
        if ( ! $this->context->is_active() || headers_sent() ) {
            return;
        }

        header( 'Cache-Control: no-store, private, max-age=0', true );
        header( 'Referrer-Policy: same-origin', true );
        header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
    }

    public static function append_token( string $url, string $request_token ): string {
        $request_token = WordPressVirtualSessionStore::normalize_token( $request_token );
        if ( '' === $url || '' === $request_token ) {
            return $url;
        }

        $parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url );
        $home_parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( home_url( '/' ) ) : parse_url( home_url( '/' ) );
        if ( false === $parts || false === $home_parts ) {
            return $url;
        }

        $scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
        if ( '' !== $scheme && ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
            return $url;
        }

        $host = strtolower( (string) ( $parts['host'] ?? '' ) );
        $home_host = strtolower( (string) ( $home_parts['host'] ?? '' ) );
        if ( '' !== $host && '' !== $home_host && $host !== $home_host ) {
            return $url;
        }

        return add_query_arg( VirtualRequestContext::REQUEST_KEY, $request_token, $url );
    }
}
