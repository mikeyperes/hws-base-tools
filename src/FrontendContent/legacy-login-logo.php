<?php

namespace hws_base_tools;

function custom_wp_admin_logo(): void {
    $render_callback = __NAMESPACE__ . '\\hws_render_custom_login_logo';
    $link_callback   = __NAMESPACE__ . '\\custom_wp_admin_logo_link';

    if ( ! has_action( 'login_enqueue_scripts', $render_callback ) ) {
        add_action( 'login_enqueue_scripts', $render_callback, 20 );
    }

    if ( ! has_filter( 'login_headerurl', $link_callback ) ) {
        add_filter( 'login_headerurl', $link_callback );
    }
}

function hws_render_custom_login_logo(): void {
    $logo_url = function_exists( __NAMESPACE__ . '\\hws_get_brand_asset_url' )
        ? hws_get_brand_asset_url( 'logo_text' )
        : '';

    if ( '' === $logo_url ) {
        $logo_url = (string) get_site_icon_url();
    }

    if ( '' === $logo_url ) {
        return;
    }

    echo '<style id="hws-custom-login-logo">#login h1 a,.login h1 a{background-image:url("'
        . esc_url( $logo_url )
        . '");width:min(250px,100%);height:80px;background-size:contain;background-position:center;background-repeat:no-repeat}</style>';
}

function custom_wp_admin_logo_link(): string {
    return home_url( '/' );
}
