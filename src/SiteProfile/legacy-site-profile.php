<?php

namespace hws_base_tools;

defined( 'ABSPATH' ) || exit;

const HWS_SITE_TYPE_OPTION = 'hws_site_type';

function hws_site_type_options(): array {
    return [
        'news_outlet'       => 'News Outlet',
        'podcast_website'   => 'Podcast Website',
        'personal_website'  => 'Personal Website',
        'company_website'   => 'Company Website',
        'ecommerce_website' => 'e-Commerce Website',
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

function hws_site_type_is_news_outlet(): bool {
    return 'news_outlet' === hws_get_site_type();
}
