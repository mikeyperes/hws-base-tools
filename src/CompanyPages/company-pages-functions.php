<?php

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'hws_base_tools_linked_page' ) ) {
    /**
     * The page linked on HWS Base Tools' Pages tab for a page type ('contact', 'headquarters', 'logo'): page ID, or 0.
     * Other plugins show these pages read-only; edit at hws_base_tools_pages_url().
     */
    function hws_base_tools_linked_page( string $type ): int {
        return max( 0, (int) get_option( 'hws_site_page_assignment_' . sanitize_key( $type ), 0 ) );
    }
}

if ( ! function_exists( 'hws_base_tools_pages_url' ) ) {
    /** Where Base Tools' page types are linked and their templates edited. */
    function hws_base_tools_pages_url(): string {
        return admin_url( 'options-general.php?page=hws-core-tools&tab=pages' );
    }
}
