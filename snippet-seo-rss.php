<?php namespace hws_base_tools;
/**
 * Prevent Google (and other bots) from indexing any feed, while still serving it.
 */
function enable_seo_feeds_no_index()
{

    add_action( 'send_headers', 'seo_feeds_no_index' );
}

function seo_feeds_no_index() {
    if ( is_feed() ) {
        // send an HTTP header that tells crawlers "noindex, follow"
        header( 'X-Robots-Tag: noindex, follow', true );
    }
}

