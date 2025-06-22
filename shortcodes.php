<?php namespace hws_base_tools;




/**
 * Shortcode: [website_url social="facebook"]
 * Returns the requested social-URL from the “Website” user picked in Website Settings.
 */
function website_url_shortcode( $atts ) {

    $atts = shortcode_atts( [
        'social' => '',
    ], $atts, 'website_url' );

    $key = sanitize_key( $atts['social'] );
    if ( ! $key ) {
        return '';
    }

    // load Website Settings group (options page)
    $website = get_field( 'website', 'option' );
    if ( ! ( is_array( $website ) && ! empty( $website['user']['ID'] ) ) ) {
        return '';
    }

    // pull that user’s “urls” repeater/array
    $user_id   = $website['user']['ID'];

    $user_urls = get_field( 'urls', 'user_' . $user_id );

    if ( is_array( $user_urls ) && ! empty( $user_urls[ $key ] ) ) {
        $url = esc_url( $user_urls[ $key ] );
        return $url;
    }

     return '';
}
