<?php

namespace hws_base_tools;

defined( 'ABSPATH' ) || exit;

const HWS_SITE_TYPE_OPTION = 'hws_site_type';
const HWS_SECONDARY_SITE_TYPES_OPTION = 'hws_site_types_secondary';

function hws_site_type_options(): array {
    return [
        'news_outlet'       => 'News Outlet',
        'podcast_website'   => 'Podcast Website',
        'personal_website'  => 'Personal Website',
        'company_website'   => 'Company Website',
        'ecommerce_website' => 'e-Commerce Website',
        'hexa_pr_wire'      => 'Hexa PR Wire Distributor',
        'verified_profiles' => 'Verified Profiles',
        'other'             => 'Other',
    ];
}

function hws_sanitize_site_type( string $site_type ): string {
    $site_type = sanitize_key( $site_type );

    return array_key_exists( $site_type, hws_site_type_options() ) ? $site_type : '';
}

function hws_get_site_type(): string {
    return hws_sanitize_site_type( (string) get_option( HWS_SITE_TYPE_OPTION, '' ) );
}

function hws_get_site_type_label( ?string $site_type = null ): string {
    $site_type = hws_sanitize_site_type( null === $site_type ? hws_get_site_type() : $site_type );
    $options   = hws_site_type_options();

    return $options[ $site_type ] ?? 'Not selected';
}

/** Valid, unique site types from a list, keeping their order. */
function hws_sanitize_site_types( array $site_types ): array {
    return array_values( array_unique( array_filter( array_map( static fn( $type ): string => hws_sanitize_site_type( (string) $type ), $site_types ) ) ) );
}

/** The secondary site types: any number besides the primary one. */
function hws_get_secondary_site_types(): array {
    $primary = hws_get_site_type();

    return array_values( array_diff( hws_sanitize_site_types( (array) get_option( HWS_SECONDARY_SITE_TYPES_OPTION, [] ) ), [ $primary ] ) );
}

/** Save the secondary site types; the primary site type is never stored twice. */
function hws_set_secondary_site_types( array $site_types ): array {
    $secondary = array_values( array_diff( hws_sanitize_site_types( $site_types ), [ hws_get_site_type() ] ) );
    update_option( HWS_SECONDARY_SITE_TYPES_OPTION, $secondary, false );

    return $secondary;
}

/** Every site type of the site: the primary first, then the secondary ones. */
function hws_get_site_types(): array {
    return array_values( array_filter( array_merge( [ hws_get_site_type() ], hws_get_secondary_site_types() ) ) );
}

function hws_site_has_type( string $site_type ): bool {
    return in_array( sanitize_key( $site_type ), hws_get_site_types(), true );
}

function hws_site_type_is_news_outlet(): bool {
    return 'news_outlet' === hws_get_site_type();
}
