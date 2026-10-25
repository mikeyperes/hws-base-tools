<?php

declare(strict_types=1);

namespace HWS\BaseTools\CompanyPages;

use HWS\BaseTools\Founders\Founders;
use HWS\BaseTools\SiteProfile\SiteIdentity;

defined( 'ABSPATH' ) || exit;

/**
 * The company-page building blocks, for Base Tools' page templates and any page:
 *
 *     [hws_company field="name"]                 one company fact from Website Identity
 *     [hws_if field="mission"]...[/hws_if]       text only when the fact, page or shortcode exists
 *     [hws_page_link key="contact"]              link to a key page from any plugin
 *
 * [hws_company] attributes: format (text, link, raw, year), sep, size, fallback,
 * before and after (printed only with a value; after="." is skipped when the
 * value already ends in a period). Facts: every Website Identity company field,
 * plus founders (from Base Tools' Founders) and url.
 */
final class CompanyFacts {
    public const SHORTCODE      = 'hws_company';
    public const IF_SHORTCODE   = 'hws_if';
    public const LINK_SHORTCODE = 'hws_page_link';

    public static function register(): void {
        add_shortcode( self::SHORTCODE, [ self::class, 'shortcode' ] );
        add_shortcode( self::IF_SHORTCODE, [ self::class, 'if_shortcode' ] );
        add_shortcode( self::LINK_SHORTCODE, [ self::class, 'link_shortcode' ] );
    }

    /** Raw value: the Website Identity company fact, then a site default. */
    public static function value( string $field ) {
        $field = sanitize_key( $field );
        if ( 'founders' === $field ) {
            return Founders::user_ids();
        }
        $value = SiteIdentity::value( 'company.' . $field );
        if ( ! self::empty_value( $value ) ) {
            return $value;
        }
        switch ( $field ) {
            case 'name':
                return get_bloginfo( 'name' );
            case 'url':
                return home_url( '/' );
            case 'email':
                return get_option( 'admin_email' );
            case 'description':
                return get_bloginfo( 'description' );
        }
        return '';
    }

    public static function shortcode( $atts ): string {
        $atts  = shortcode_atts( [ 'field' => 'name', 'format' => '', 'sep' => ', ', 'size' => 'medium', 'fallback' => '', 'before' => '', 'after' => '' ], (array) $atts, self::SHORTCODE );
        $field = sanitize_key( (string) $atts['field'] );
        $value = self::value( $field );
        if ( self::empty_value( $value ) ) {
            return esc_html( (string) $atts['fallback'] );
        }
        $html = self::format_value( $field, $value, $atts );
        if ( '' === trim( wp_strip_all_tags( $html ) ) && 'logo' !== $field ) {
            return esc_html( (string) $atts['fallback'] );
        }
        $after = (string) $atts['after'];
        if ( '' !== $after && '.' === $after[0] && '.' === substr( rtrim( wp_strip_all_tags( $html ) ), -1 ) ) {
            $after = substr( $after, 1 );
        }
        return esc_html( (string) $atts['before'] ) . $html . esc_html( $after );
    }

    /** Whether a condition holds: a company fact has a value, a key page is linked (any plugin), a shortcode is registered. */
    public static function condition( array $atts ): bool {
        $atts = shortcode_atts( [ 'field' => '', 'page' => '', 'shortcode' => '', 'not' => '' ], $atts, self::IF_SHORTCODE );
        $ok   = true;
        if ( '' !== $atts['field'] ) {
            $ok = $ok && ! self::empty_value( self::value( (string) $atts['field'] ) );
        }
        if ( '' !== $atts['page'] ) {
            $ok = $ok && self::published_page( sanitize_key( (string) $atts['page'] ) ) > 0;
        }
        if ( '' !== $atts['shortcode'] ) {
            $ok = $ok && shortcode_exists( sanitize_key( (string) $atts['shortcode'] ) );
        }
        return '' !== $atts['not'] ? ! $ok : $ok;
    }

    public static function if_shortcode( $atts, $content = '' ): string {
        return self::condition( (array) $atts ) ? do_shortcode( (string) $content ) : '';
    }

    public static function link_shortcode( $atts ): string {
        $atts    = shortcode_atts( [ 'key' => '', 'text' => '' ], (array) $atts, self::LINK_SHORTCODE );
        $page_id = self::published_page( sanitize_key( (string) $atts['key'] ) );
        if ( ! $page_id ) {
            return '';
        }
        $text = '' !== (string) $atts['text'] ? (string) $atts['text'] : get_the_title( $page_id );
        return '<a href="' . esc_url( (string) get_permalink( $page_id ) ) . '">' . esc_html( $text ) . '</a>';
    }

    /** A published page linked to a key page from Base Tools or any other plugin (0 when none). */
    public static function published_page( string $key ): int {
        $id = CompanyPages::page_id( $key );
        return $id && 'publish' === get_post_status( $id ) ? $id : 0;
    }

    private static function format_value( string $field, $value, array $atts ): string {
        $format = sanitize_key( (string) $atts['format'] );
        $sep    = (string) $atts['sep'];

        if ( 'founding_date' === $field && 'raw' !== $format ) {
            $time = strtotime( (string) $value );
            return $time ? esc_html( date_i18n( 'year' === $format ? 'Y' : (string) get_option( 'date_format', 'F j, Y' ), $time ) ) : esc_html( (string) $value );
        }
        if ( 'logo' === $field ) {
            return 'url' === $format ? esc_url( (string) wp_get_attachment_image_url( absint( $value ), 'full' ) ) : (string) wp_get_attachment_image( absint( $value ), sanitize_key( (string) $atts['size'] ), false, [ 'class' => 'hws-company-logo', 'loading' => 'lazy' ] );
        }
        if ( 'founders' === $field ) {
            $names = [];
            foreach ( (array) $value as $user_id ) {
                $user = get_user_by( 'id', absint( $user_id ) );
                if ( ! $user ) {
                    continue;
                }
                $profile = CompanyPages::founder_page_id( (int) $user->ID );
                $url     = $profile ? (string) get_permalink( $profile ) : (string) get_author_posts_url( $user->ID );
                $names[] = 'text' === $format ? esc_html( $user->display_name ) : '<a href="' . esc_url( $url ) . '">' . esc_html( $user->display_name ) . '</a>';
            }
            return implode( esc_html( $sep ), $names );
        }
        if ( 'email' === $field ) {
            $email = sanitize_email( (string) $value );
            return 'text' === $format ? esc_html( $email ) : '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
        }
        if ( in_array( $field, [ 'url', 'parent_url', 'headquarters_url', 'founding_location_url' ], true ) ) {
            return 'link' === $format ? '<a href="' . esc_url( (string) $value ) . '">' . esc_html( (string) $value ) . '</a>' : esc_url( (string) $value );
        }
        if ( is_array( $value ) ) {
            return esc_html( implode( $sep, array_filter( array_map( 'strval', $value ) ) ) );
        }
        return in_array( $field, [ 'mission', 'description', 'headquarters_address' ], true ) ? wp_kses_post( nl2br( (string) $value ) ) : esc_html( wp_strip_all_tags( (string) $value ) );
    }

    private static function empty_value( $value ): bool {
        return null === $value || false === $value || '' === $value || [] === $value || ( is_array( $value ) && [] === array_filter( $value ) );
    }
}
