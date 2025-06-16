<?php namespace hws_base_tools;


add_shortcode( 'website_url', __NAMESPACE__ . '\\website_url_shortcode' );


/**
 * Shortcode: [website_url social="facebook"]
 * Returns the requested social-URL from the “Website” user picked in Website Settings.
 */
function website_url_shortcode( $atts ) {
    write_log( 'website_url_shortcode called with $atts: ' . var_export( $atts, true ), true );

    $atts = shortcode_atts( [
        'social' => '',
    ], $atts, 'website_url' );
    write_log( 'Parsed shortcode atts: ' . var_export( $atts, true ), true );

    $key = sanitize_key( $atts['social'] );
    write_log( 'Sanitized key: ' . $key, true );
    if ( ! $key ) {
        write_log( 'No key provided, returning empty.', true );
        return '';
    }

    // load Website Settings group (options page)
    $website = get_field( 'website', 'option' );
    write_log( 'Website settings loaded: ' . var_export( $website, true ), true );
    if ( ! ( is_array( $website ) && ! empty( $website['user']['ID'] ) ) ) {
        write_log( 'Website settings missing user ID, returning empty.', true );
        return '';
    }

    // pull that user’s “urls” repeater/array
    $user_id   = $website['user']['ID'];
    write_log( 'Found user ID: ' . $user_id, true );

    $user_urls = get_field( 'urls', 'user_' . $user_id );
    write_log( "User URLs for user_{$user_id}: " . var_export( $user_urls, true ), true );

    if ( is_array( $user_urls ) && ! empty( $user_urls[ $key ] ) ) {
        $url = esc_url( $user_urls[ $key ] );
        write_log( 'Returning URL: ' . $url, true );
        return $url;
    }

    write_log( "No URL found for key '{$key}', returning empty.", true );
    return '';
}
