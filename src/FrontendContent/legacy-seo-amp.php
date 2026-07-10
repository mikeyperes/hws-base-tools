<?php namespace hws_base_tools;
/**
 * Redirect any AMP URL back to its non-AMP equivalent,
 * but only on frontend, non-admin, non-AJAX, non-REST, non-cron, non-preview requests.
 */
function enable_seo_amp_no_index(){
add_action( 'template_redirect', function() {
    // Bail if in admin, doing AJAX, REST API, cron, or preview
    if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_preview() ) {
        return;
    }

    // Grab the raw request URI
    $request_uri = wp_unslash( $_SERVER['REQUEST_URI'] );

    // 1) Query-string AMP (?amp=1 or ?amp=1/)
    if ( isset( $_GET['amp'] ) ) {
        // Get just the path portion
        $path = parse_url( $request_uri, PHP_URL_PATH );
        // Ensure a single trailing slash
        $path = untrailingslashit( $path ) . '/';

        // Remove amp parameter but preserve any other query vars
        $args = $_GET;
        unset( $args['amp'] );
        $destination = home_url( $path );
        if ( ! empty( $args ) ) {
            $destination = add_query_arg( $args, $destination );
        }

        wp_redirect( $destination, 301 );
        exit;
    }

    // 2) Official AMP plugin endpoint
    if ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
        $path = preg_replace( '#/amp/?$#', '/', $request_uri );
    }
    // 3) Literal /amp/ suffix
    elseif ( preg_match( '#/amp/?$#', $request_uri ) ) {
        $path = preg_replace( '#/amp/?$#', '/', $request_uri );
    }
    else {
        // Not an AMP URL at all
        return;
    }

    // Build and send a 301 redirect to the clean URL
    $destination = home_url( $path );
    wp_redirect( $destination, 301 );
    exit;
}, 0 );
}
