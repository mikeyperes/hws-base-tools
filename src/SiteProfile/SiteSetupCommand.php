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
 *   wp hws brand get
 *   wp hws brand favicon <attachment-id|file>
 *   wp hws brand favicon --from=logo_1x1
 */
final class SiteSetupCommand {
    public static function register(): void {
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            \WP_CLI::add_command( 'hws identity', [ self::class, 'identity' ] );
            \WP_CLI::add_command( 'hws brand', [ self::class, 'brand' ] );
            \WP_CLI::add_command( 'hws founders', [ self::class, 'founders' ] );
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
     * : Any company key (name, legal_name, description, website (the url key), founding_date, founding_location,
     * headquarters_city, headquarters_address, parent_name, parent_url, email, phone, mission,
     * profiles, logo). Lists (alternate_names, profiles, parent_name, parent_url) take one item per
     * line or items separated by " | ". An empty value clears the key.
     *
     * @param array<int,string>    $args
     * @param array<string,string> $assoc
     */
    public static function identity( array $args, array $assoc ): void {
        if ( ! function_exists( 'hws_base_tools\\hws_site_type_options' ) ) {
            require_once __DIR__ . '/legacy-site-profile.php';
        }
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
        if ( isset( $assoc['website'] ) ) {
            $assoc['url'] = $assoc['website'];
        }
        $saved = [];
        foreach ( SiteIdentity::COMPANY_KEYS as $key ) {
            if ( ! array_key_exists( $key, $assoc ) || 'founders' === $key ) {
                continue;
            }
            $value = (string) $assoc[ $key ];
            if ( in_array( $key, SiteIdentity::LIST_KEYS, true ) ) {
                $value = implode( "\n", SiteIdentity::list_values( $value ) );
            }
            update_option( 'options_' . SiteIdentity::COMPANY . '_' . $key, 'logo' === $key ? (int) $value : $value );
            update_option( '_options_' . SiteIdentity::COMPANY . '_' . $key, 'field_hws_identity_' . $key );
            $saved[] = $key;
        }
        if ( $saved ) {
            // The group row ties the saved keys to the Website Identity form.
            update_option( 'options_' . SiteIdentity::COMPANY, '' );
            update_option( '_options_' . SiteIdentity::COMPANY, 'field_' . SiteIdentity::COMPANY );
        }
        if ( isset( $assoc['type'] ) ) {
            update_option( '_options_' . SiteIdentity::CHOICE, 'field_' . SiteIdentity::CHOICE );
        }
        \WP_CLI::success( 'Saved: ' . ( $saved ? implode( ', ', $saved ) : 'type only' ) . '.' );
    }

    /**
     * Read the Brand Assets slots, save one, or make the favicon from one.
     *
     * ## OPTIONS
     *
     * <action>
     * : get, set or favicon
     *
     * [<slot>]
     * : logo, logo_1x1, logo_text, logo_dark, logo_dark_1x1 or logo_text_dark (for set).
     *
     * [<source>]
     * : An attachment ID or an image file path (imported into the media library). 1:1 slots and the favicon need a square image.
     *
     * [--from=<slot>]
     * : For favicon without its own image: the slot to make it from (default logo_1x1).
     *
     * @param array<int,string>    $args
     * @param array<string,string> $assoc
     */
    public static function brand( array $args, array $assoc ): void {
        if ( ! function_exists( 'hws_base_tools\\hws_get_brand_asset_definition' ) ) {
            \WP_CLI::error( 'Brand Assets are not loaded.' );
        }
        $action = $args[0] ?? '';
        if ( 'get' === $action ) {
            $out = [];
            foreach ( array_keys( \hws_base_tools\hws_get_brand_asset_definitions() ) as $slot ) {
                $out[ $slot ] = self::asset( \hws_base_tools\hws_get_brand_asset_attachment_id( $slot ) );
            }
            // The raw group value is keyed by field keys, so the favicon is read as its own sub field by name.
            $out['favicon'] = self::asset( (int) Field::get( 'branding_favicon', 'option', false ) );
            $out['site_icon'] = self::asset( (int) get_option( 'site_icon', 0 ) );
            \WP_CLI::line( (string) wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
            return;
        }
        if ( 'favicon' === $action ) {
            if ( '' !== (string) ( $args[1] ?? '' ) ) {
                // The favicon is its own design (the brand's initial), so it can come from its own image.
                $id   = self::attachment( (string) $args[1] );
                $meta = wp_get_attachment_metadata( $id );
                if ( empty( $meta['width'] ) || (int) $meta['width'] !== (int) ( $meta['height'] ?? 0 ) ) {
                    \WP_CLI::error( 'The favicon needs a square image.' );
                }
                $slot = 'its own image';
            } else {
                $slot = \hws_base_tools\hws_normalize_brand_asset_key( (string) ( $assoc['from'] ?? 'logo_1x1' ) );
                $id   = \hws_base_tools\hws_get_brand_asset_attachment_id( $slot );
                if ( $id <= 0 ) {
                    \WP_CLI::error( 'Save ' . $slot . ' first.' );
                }
            }
            // Only the favicon sub field is written; the group's other settings stay as they are.
            Field::update( 'branding_favicon', $id, 'option' );
            update_option( 'site_icon', $id );
            if ( (int) Field::get( 'branding_favicon', 'option', false ) !== $id ) {
                \WP_CLI::error( 'The favicon did not save to Brand Assets (attachment ' . $id . ').' );
            }
            \WP_CLI::success( 'Favicon and site icon set from ' . $slot . ' (attachment ' . $id . ').' );
            return;
        }
        if ( 'set' !== $action ) {
            \WP_CLI::error( 'Use get, set or favicon.' );
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

    /**
     * Read or set the founders section title.
     *
     * ## OPTIONS
     *
     * <action>
     * : get or set
     *
     * [--heading=<heading>]
     * : The section title over the founders list ("Founders" by default; for example Executives). Empty for none.
     *
     * [--users=<ids>]
     * : The founders in order, as user IDs separated by commas (replaces the list).
     *
     * [--titles=<titles>]
     * : Each founder's title, in the same order, separated by " | " (for example "Executive | Executive").
     *
     * [--enabled=<value>]
     * : on, yes, true or 1 to show the founders list; off, no, false or 0 to hide it.
     *
     * [--profile=<user-page>]
     * : A founder's profile page, as user ID and page ID (12:129446). Their card links there instead of their author archive. Page 0 clears it.
     *
     * [--photo=<user-image>]
     * : A founder's portrait, as user ID and an attachment ID or image file (12:129450). Their card shows it instead of their avatar. 0 clears it.
     *
     * @param array<int,string>    $args
     * @param array<string,string> $assoc
     */
    public static function founders( array $args, array $assoc ): void {
        $action = $args[0] ?? 'get';
        if ( 'set' === $action ) {
            $changes = [];
            if ( array_key_exists( 'heading', $assoc ) ) {
                $changes['heading'] = sanitize_text_field( (string) $assoc['heading'] );
            }
            if ( array_key_exists( 'users', $assoc ) ) {
                $ids = array_values( array_unique( array_filter( array_map( 'absint', explode( ',', (string) $assoc['users'] ) ) ) ) );
                foreach ( $ids as $id ) {
                    if ( ! get_userdata( $id ) ) {
                        \WP_CLI::error( "No user with ID {$id}." );
                    }
                }
                $changes['ids'] = $ids;
                if ( array_key_exists( 'titles', $assoc ) ) {
                    $titles = array_map( 'trim', explode( '|', (string) $assoc['titles'] ) );
                    $changes['titles'] = [];
                    foreach ( $ids as $i => $id ) {
                        $changes['titles'][ $id ] = sanitize_text_field( (string) ( $titles[ $i ] ?? '' ) );
                    }
                }
            } elseif ( array_key_exists( 'titles', $assoc ) ) {
                \WP_CLI::error( '--titles needs --users, so each title has its founder.' );
            }
            if ( array_key_exists( 'enabled', $assoc ) ) {
                $enabled = filter_var( trim( (string) $assoc['enabled'] ), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
                if ( null === $enabled || '' === trim( (string) $assoc['enabled'] ) ) {
                    \WP_CLI::error( '--enabled must be on, yes, true, 1, off, no, false or 0.' );
                }
                $changes['enabled'] = $enabled;
            }
            $founder_ids = $changes['ids'] ?? \HWS\BaseTools\Founders\Founders::user_ids();
            $settings    = \HWS\BaseTools\Founders\Founders::settings();
            foreach ( [ 'profile' => 'pages', 'photo' => 'photos' ] as $flag => $key ) {
                if ( ! array_key_exists( $flag, $assoc ) ) {
                    continue;
                }
                [ $user, $value ] = array_pad( explode( ':', (string) $assoc[ $flag ], 2 ), 2, '' );
                $user = absint( $user );
                if ( ! in_array( $user, $founder_ids, true ) ) {
                    \WP_CLI::error( "User {$user} is not a founder." );
                }
                if ( 'profile' === $flag && '0' !== $value && 'page' !== get_post_type( (int) $value ) ) {
                    \WP_CLI::error( 'Give a page ID for --profile.' );
                }
                $post = '0' === $value ? 0 : ( 'photo' === $flag ? self::attachment( $value ) : (int) $value );
                $map  = $settings[ $key ];
                if ( $post > 0 ) {
                    $map[ $user ] = $post;
                } else {
                    unset( $map[ $user ] );
                }
                $changes[ $key ] = $map;
            }
            if ( ! $changes ) {
                \WP_CLI::error( 'Use set with --heading, --users, --titles, --enabled, --profile or --photo.' );
            }
            \HWS\BaseTools\Founders\Founders::update_settings( $changes );
        } elseif ( 'get' !== $action ) {
            \WP_CLI::error( 'Use get or set.' );
        }
        $settings = \HWS\BaseTools\Founders\Founders::settings();
        \WP_CLI::line( (string) wp_json_encode( [ 'enabled' => $settings['enabled'], 'heading' => $settings['heading'], 'ids' => $settings['ids'], 'titles' => $settings['titles'], 'pages' => $settings['pages'], 'photos' => $settings['photos'] ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
    }

    /** One saved image as id, url and size; empty when the slot is unset or the file is gone. */
    private static function asset( int $id ): array {
        $url = $id > 0 ? wp_get_attachment_url( $id ) : false;
        if ( ! $url ) {
            return [ 'id' => 0, 'url' => '', 'width' => 0, 'height' => 0 ];
        }
        $meta = (array) wp_get_attachment_metadata( $id );
        return [ 'id' => $id, 'url' => $url, 'width' => (int) ( $meta['width'] ?? 0 ), 'height' => (int) ( $meta['height'] ?? 0 ) ];
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
