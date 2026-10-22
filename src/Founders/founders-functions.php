<?php

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'hws_publication_founders' ) ) {
    /**
     * The website's founders from HWS Base Tools (Founders tab), in order:
     * [ [ 'user_id', 'name', 'title', 'url', 'bio', 'photo' ], ... ].
     * Returned whether or not the on-page section is switched on.
     */
    function hws_publication_founders(): array {
        return \HWS\BaseTools\Founders\Founders::all();
    }
}

if ( ! function_exists( 'hws_publication_founders_enabled' ) ) {
    /** Whether the founders section is switched on (the shortcode prints nothing while off). */
    function hws_publication_founders_enabled(): bool {
        return \HWS\BaseTools\Founders\Founders::settings()['enabled'];
    }
}

if ( ! function_exists( 'hws_publication_founders_edit_url' ) ) {
    /** Where founders are edited; pair with Hexa WP Core's SourceBanner when showing them elsewhere. */
    function hws_publication_founders_edit_url(): string {
        return \HWS\BaseTools\Founders\Founders::edit_url();
    }
}
