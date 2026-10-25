<?php

declare( strict_types=1 );

namespace HWS\BaseTools\SiteProfile;

use Hexa\PluginCore\Fields\Field;

defined( 'ABSPATH' ) || exit;

/**
 * WP-CLI: save a site's identity and brand assets without the admin screens, so setup
 * workflows (and skills) fill them through the plugin. See README "Site setup commands".
 *
 *   wp hws identity get
 *   wp hws identity set --type=company --site-type=news_outlet --name="Strive Mag" ...
 *   wp hws brand set logo_1x1 /path/to/square.png
 *   wp hws brand favicon --from=logo_1x1
 */
final class SiteSetupCommand {
    public static function register(): void {
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            \WP_CLI::add_command( 'hws identity', [ self::class, 'identity' ] );
            \WP_CLI::add_command( 'hws brand', [ self::class, 'brand' ] );
        }
    }

    /**
     * Read or save the Website Identity.
     *
     * ## OPTIONS
     *
     * <action>
     * : get or set
     *
     * [--type=<type>]
     * : company, person or none.
     *
     * [--site-type=<site-type>]
     * : news_outlet, podcast_website, personal_website, company_website, ecommerce_website or other.
     *
     * [--<field>=<value>]
     * : Any company key (name, legal_name, description, url, founding_date, founding_location,
     * headquarters_city, headquarters_address, parent_name, parent_url, email, phone, mission,
     * profiles, logo). Lists (alternate_names, profiles, parent_name, parent_url) take one item per
     * line or items separated by " | ". An empty value clears the key.
     *
     * @param array<int,string>    $args
     * @param array<string,string> $assoc
     */
    public static function identity( array $args, array $assoc ): void {
        $action = $args[0] ?? 'get';
        if ( 'get' === $action ) {
            $out = SiteIdentity::get();
            $out['site_type'] = function_exists( 'hws_base_tools\\hws_get_site_type' ) ? \hws_base_tools\hws_get_site_type() : (string) get_option( 'hws_site_type', '' );
            \WP_CLI::line( (string) wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
            return;
        }
        if ( 'set' !== $action ) {
            \WP_CLI::error( 'Use get or set.' );
        }
        if ( isset( $assoc['type'] ) ) {
            if ( ! isset( SiteIdentity::TYPES[ $assoc['type'] ] ) ) {
                \WP_CLI::error( 'Unknown type: ' . $assoc['type'] );
            }
            Field::update( SiteIdentity::CHOICE, $assoc['type'], 'option' );
            update_option( 'options_' . SiteIdentity::CHOICE, $assoc['type'] );
        }
        if ( isset( $assoc['site-type'] ) ) {
            $options = function_exists( 'hws_base_tools\\hws_site_type_options' ) ? \hws_base_tools\hws_site_type_options() : [];
            if ( ! isset( $options[ $assoc['site-type'] ] ) ) {
                \WP_CLI::error( 'Unknown site type: ' . $assoc['site-type'] );
            }
            update_option( 'hws_site_type', $assoc['site-type'] );
        }
        $saved = [];
        foreach ( SiteIdentity::COMPANY_KEYS as $key ) {
            if ( ! array_key_exists( $key, $assoc ) || 'founders' === $key ) {
                continue;
            }
            $value = (string) $assoc[ $key ];
            if ( in_array( $key, SiteIdentity::LIST_KEYS, true ) ) {
                $value = implode( "\n", array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n|\s\|\s/', $value ) ?: [] ) ) );
            }
            update_option( 'options_' . SiteIdentity::COMPANY . '_' . $key, 'logo' === $key ? (int) $value : $value );
            update_option( '_options_' . SiteIdentity::COMPANY . '_' . $key, 'field_hws_identity_' . $key );
            $saved[] = $key;
        }
        \WP_CLI::success( 'Saved: ' . ( $saved ? implode( ', ', $saved ) : 'type only' ) . '.' );
    }

    /**
     * Save a Brand Assets slot, or make the favicon from one.
     *
     * ## OPTIONS
     *
     * <action>
     * : set or favicon
     *
     * [<slot>]
     * : logo, logo_1x1, logo_text, logo_dark, logo_dark_1x1 or logo_text_dark (for set).
     *
     * [<source>]
     * : An attachment ID or an image file path (imported into the media library). 1:1 slots need a square image.
     *
     * [--from=<slot>]
     * : For favicon: the slot to make it from (default logo_1x1).
     *
     * @param array<int,string>    $args
     * @param array<string,string> $assoc
     */
    public static function brand( array $args, array $assoc ): void {
        if ( ! function_exists( 'hws_base_tools\\hws_get_brand_asset_definition' ) ) {
            \WP_CLI::error( 'Brand Assets are not loaded.' );
        }
        $action = $args[0] ?? '';
        if ( 'favicon' === $action ) {
            $slot = \hws_base_tools\hws_normalize_brand_asset_key( (string) ( $assoc['from'] ?? 'logo_1x1' ) );
            $id   = \hws_base_tools\hws_get_brand_asset_attachment_id( $slot );
            if ( $id <= 0 ) {
                \WP_CLI::error( 'Save ' . $slot . ' first.' );
            }
            $branding = (array) ( Field::get( 'branding', 'option', false ) ?: [] );
            $branding['favicon'] = $id;
            Field::update( 'branding', $branding, 'option' );
            update_option( 'site_icon', $id );
            \WP_CLI::success( 'Favicon and site icon set from ' . $slot . ' (attachment ' . $id . ').' );
            return;
        }
        if ( 'set' !== $action ) {
            \WP_CLI::error( 'Use set or favicon.' );
        }
        $slot       = \hws_base_tools\hws_normalize_brand_asset_key( (string) ( $args[1] ?? '' ) );
        $definition = \hws_base_tools\hws_get_brand_asset_definition( $slot );
        if ( ! $definition ) {
            \WP_CLI::error( 'Unknown slot: ' . ( $args[1] ?? '' ) );
        }
        $id = self::attachment( (string) ( $args[2] ?? '' ) );
        if ( str_ends_with( $slot, '_1x1' ) ) {
            $meta = wp_get_attachment_metadata( $id );
            if ( empty( $meta['width'] ) || (int) $meta['width'] !== (int) ( $meta['height'] ?? 0 ) ) {
                \WP_CLI::error( 'A 1:1 slot needs a square image.' );
            }
        }
        update_option( $definition['option'], $id );
        if ( 'custom_logo' === ( $definition['core'] ?? '' ) ) {
            set_theme_mod( 'custom_logo', $id );
        }
        \WP_CLI::success( 'Saved ' . $slot . ' (attachment ' . $id . '): ' . wp_get_attachment_url( $id ) );
    }

    private static function attachment( string $source ): int {
        if ( ctype_digit( $source ) && 'attachment' === get_post_type( (int) $source ) ) {
            return (int) $source;
        }
        if ( '' === $source || ! is_readable( $source ) ) {
            \WP_CLI::error( 'Give an attachment ID or a readable image file.' );
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $tmp = wp_tempnam( basename( $source ) );
        copy( $source, $tmp );
        $id = media_handle_sideload( [ 'name' => basename( $source ), 'tmp_name' => $tmp ], 0 );
        if ( is_wp_error( $id ) ) {
            \WP_CLI::error( $id->get_error_message() );
        }
        return (int) $id;
    }
}
