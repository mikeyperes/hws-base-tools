<?php

namespace hws_base_tools;


defined( 'ABSPATH' ) || exit;

const HWS_SITEMAP_NOCACHE_OPTION = 'hws_sitemaps_litespeed_nocache_enabled';

function hws_sitemaps_nocache_option_value(): string {
    $value = get_option( HWS_SITEMAP_NOCACHE_OPTION, null );

    if ( null === $value || false === $value ) {
        global $wpdb;

        $raw = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                HWS_SITEMAP_NOCACHE_OPTION
            )
        );

        if ( null !== $raw ) {
            return (string) $raw;
        }
    }

    return null === $value || false === $value ? 'no' : (string) $value;
}

function hws_sitemaps_nocache_enabled(): bool {
    return 'yes' === hws_sitemaps_nocache_option_value();
}

function hws_sitemaps_is_sitemap_uri( ?string $uri = null ): bool {
    $uri  = null === $uri ? (string) ( $_SERVER['REQUEST_URI'] ?? '' ) : $uri;
    $path = strtolower( (string) wp_parse_url( $uri, PHP_URL_PATH ) );

    if ( '' === $path || false === strpos( $path, 'sitemap' ) ) {
        return false;
    }

    return (bool) preg_match( '#/(sitemap(_index)?|wp-sitemap|[^/]+-sitemap)([^/]*\.xml)?$#', $path );
}

function hws_sitemaps_apply_litespeed_nocache(): void {
    if ( ! hws_sitemaps_nocache_enabled() ) {
        return;
    }

    add_filter( 'rank_math/sitemap/enable_caching', '__return_false' );

    if ( ! hws_sitemaps_is_sitemap_uri() ) {
        return;
    }

    if ( ! defined( 'DONOTCACHEPAGE' ) ) {
        define( 'DONOTCACHEPAGE', true );
    }

    do_action( 'litespeed_control_set_nocache', 'hws-sitemaps' );

    add_action(
        'send_headers',
        static function (): void {
            if ( headers_sent() ) {
                return;
            }

            header( 'X-LiteSpeed-Cache-Control: no-cache' );
            nocache_headers();
        },
        0
    );
}
add_action( 'init', __NAMESPACE__ . '\\hws_sitemaps_apply_litespeed_nocache', 0 );
