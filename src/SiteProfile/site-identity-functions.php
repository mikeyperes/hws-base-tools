<?php

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'hws_site_identity' ) ) {
    /**
     * The website's basic identity from HWS Base Tools (Website & Primary Entity tab).
     *
     *     hws_site_identity()                 full array: type, company, person
     *     hws_site_identity( 'type' )         'company', 'person', 'none' or ''
     *     hws_site_identity( 'company.name' ) one value
     *     hws_site_identity( 'name' )         the value for the type the site represents
     */
    function hws_site_identity( string $field = '' ): mixed {
        return '' === $field ? \HWS\BaseTools\SiteProfile\SiteIdentity::get() : \HWS\BaseTools\SiteProfile\SiteIdentity::value( $field );
    }
}

if ( ! function_exists( 'hws_site_identity_edit_url' ) ) {
    /** Where the identity is edited; pair with Hexa WP Core's SourceBanner when showing it elsewhere. */
    function hws_site_identity_edit_url(): string {
        return \HWS\BaseTools\SiteProfile\SiteIdentity::edit_url();
    }
}
