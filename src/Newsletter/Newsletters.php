<?php
namespace HWS\BaseTools\Newsletter;

use Hexa\PluginCore\DataNormalization\SlugConverter;
use Hexa\PluginCore\FrontendForms\SubmissionStore;
use HWS\BaseTools\PluginRuntime\PluginMetadata;

/**
 * The saved newsletters: create, read, update and remove them from PHP, a hook or REST.
 * Each newsletter is a signup list (its ID is the shortcode's list="...") with optional own
 * notice email, design and messages; blank fields fall back to the site defaults.
 * See docs/newsletter.md#creating-newsletters.
 */
final class Newsletters {
    public const OPTION = 'hws_base_tools_newsletters';
    public const FIELDS = [ 'name', 'notification_email', 'style', 'success', 'duplicate', 'error' ];

    public static function register(): void {
        // do_action( 'hws_base_tools_create_newsletter', [ 'name' => 'Weekly digest' ] ); creates it once, safe to call on every load.
        add_action( 'hws_base_tools_create_newsletter', static function ( $args ): void { self::ensure( (array) $args ); } );
        add_action( 'rest_api_init', [ self::class, 'routes' ] );
    }

    /** @return array<string,array<string,string>> every saved newsletter keyed by ID */
    public static function all(): array {
        $all = get_option( self::OPTION, [] );
        return is_array( $all ) ? $all : [];
    }

    public static function get( string $id ): ?array {
        return self::all()[ $id ] ?? null;
    }

    /** The ID a name turns into, using Hexa WP Core's slug rules. */
    public static function id_from( string $value ): string {
        return SlugConverter::from_name( $value, '-', 80 );
    }

    /**
     * Create a newsletter. Args: name (required), id (optional, made from the name), notification_email,
     * style (none|simple|rounded, blank = site default), success, duplicate, error.
     * @return array|\WP_Error the saved record with its id
     */
    public static function create( array $args ) {
        $name = sanitize_text_field( (string) ( $args['name'] ?? '' ) );
        if ( '' === $name ) {
            return new \WP_Error( 'name', 'Give the newsletter a name.', [ 'status' => 400 ] );
        }
        $id = self::id_from( (string) ( ( $args['id'] ?? '' ) !== '' ? $args['id'] : $name ) );
        try {
            $id = SubmissionStore::collection( $id );
        } catch ( \InvalidArgumentException $e ) {
            return new \WP_Error( 'id', 'The ID needs at least one letter or number.', [ 'status' => 400 ] );
        }
        $all = self::all();
        if ( isset( $all[ $id ] ) ) {
            return new \WP_Error( 'exists', 'A newsletter with the ID "' . $id . '" already exists.', [ 'status' => 409 ] );
        }
        $record = self::clean( array_merge( $args, [ 'name' => $name ] ) );
        if ( is_wp_error( $record ) ) {
            return $record;
        }
        $all[ $id ] = $record + [ 'created_at' => gmdate( 'Y-m-d H:i:s' ) ];
        update_option( self::OPTION, $all, false );
        do_action( 'hws_base_tools_newsletter_created', $id, $all[ $id ] );

        return [ 'id' => $id ] + $all[ $id ];
    }

    /** Create the newsletter unless one with that ID already exists; returns the existing or new record. */
    public static function ensure( array $args ) {
        $id = self::id_from( (string) ( ( $args['id'] ?? '' ) !== '' ? $args['id'] : ( $args['name'] ?? '' ) ) );
        $existing = '' !== $id ? self::get( $id ) : null;
        return null !== $existing ? [ 'id' => $id ] + $existing : self::create( $args );
    }

    /** Change some fields of a newsletter (the ID never changes). Also adopts a list that only exists in storage. */
    public static function update( string $id, array $args ) {
        try {
            $id = SubmissionStore::collection( $id );
        } catch ( \InvalidArgumentException $e ) {
            return new \WP_Error( 'id', 'Invalid newsletter ID.', [ 'status' => 400 ] );
        }
        $all    = self::all();
        $record = self::clean( array_merge( $all[ $id ] ?? [], array_intersect_key( $args, array_flip( self::FIELDS ) ) ) );
        if ( is_wp_error( $record ) ) {
            return $record;
        }
        $all[ $id ] = $record + [ 'created_at' => $all[ $id ]['created_at'] ?? gmdate( 'Y-m-d H:i:s' ) ];
        update_option( self::OPTION, $all, false );
        do_action( 'litespeed_purge_all' );
        do_action( 'hws_base_tools_newsletter_updated', $id, $all[ $id ] );

        return [ 'id' => $id ] + $all[ $id ];
    }

    /** Remove a newsletter's settings. Its subscribers stay stored and exportable. */
    public static function remove( string $id ): bool {
        $all = self::all();
        if ( ! isset( $all[ $id ] ) ) {
            return false;
        }
        unset( $all[ $id ] );
        update_option( self::OPTION, $all, false );
        do_action( 'hws_base_tools_newsletter_removed', $id );

        return true;
    }

    /** @return array|\WP_Error */
    private static function clean( array $args ) {
        $email = strtolower( trim( (string) ( $args['notification_email'] ?? '' ) ) );
        if ( '' !== $email && ! is_email( $email ) ) {
            return new \WP_Error( 'notification_email', 'Enter a valid notice email, or leave it blank to use the site default.', [ 'status' => 400 ] );
        }
        $style  = (string) ( $args['style'] ?? '' );
        $record = [
            'name'               => sanitize_text_field( (string) ( $args['name'] ?? '' ) ),
            'notification_email' => $email,
            'style'              => in_array( $style, [ 'none', 'simple', 'rounded' ], true ) ? $style : '',
        ];
        foreach ( [ 'success', 'duplicate', 'error' ] as $key ) {
            $value = $args[ $key ] ?? '';
            if ( ! is_string( $value ) || strlen( $value ) > NewsletterModule::MESSAGE_LIMIT ) {
                return new \WP_Error( $key, 'Messages must be text of at most 2000 characters.', [ 'status' => 400 ] );
            }
            $record[ $key ] = $value;
        }

        return $record;
    }

    public static function routes(): void {
        $admin = static function () { return current_user_can( PluginMetadata::ADMIN_CAPABILITY ); };
        $reply = static function ( $result, int $status = 200 ) {
            return is_wp_error( $result ) ? $result : new \WP_REST_Response( $result, $status );
        };
        register_rest_route( 'hws-base-tools/v1', '/newsletter/newsletters', [
            [ 'methods' => 'GET', 'permission_callback' => $admin, 'callback' => static function () {
                $out = [];
                foreach ( self::all() as $id => $record ) {
                    $out[] = [ 'id' => $id ] + $record;
                }
                return new \WP_REST_Response( [ 'items' => $out ] );
            } ],
            [ 'methods' => 'POST', 'permission_callback' => $admin, 'callback' => static function ( \WP_REST_Request $r ) use ( $reply ) {
                return $reply( self::create( (array) $r->get_params() ), 201 );
            } ],
        ] );
        register_rest_route( 'hws-base-tools/v1', '/newsletter/newsletters/(?P<id>[a-z0-9][a-z0-9_-]{0,79})', [
            [ 'methods' => 'GET', 'permission_callback' => $admin, 'callback' => static function ( \WP_REST_Request $r ) {
                $record = self::get( (string) $r['id'] );
                return null === $record ? new \WP_Error( 'not_found', 'No newsletter with that ID.', [ 'status' => 404 ] ) : new \WP_REST_Response( [ 'id' => (string) $r['id'] ] + $record );
            } ],
            [ 'methods' => 'POST, PUT, PATCH', 'permission_callback' => $admin, 'callback' => static function ( \WP_REST_Request $r ) use ( $reply ) {
                return $reply( self::update( (string) $r['id'], (array) $r->get_params() ) );
            } ],
            [ 'methods' => 'DELETE', 'permission_callback' => $admin, 'callback' => static function ( \WP_REST_Request $r ) {
                return self::remove( (string) $r['id'] ) ? new \WP_REST_Response( [ 'removed' => true ] ) : new \WP_Error( 'not_found', 'No newsletter with that ID.', [ 'status' => 404 ] );
            } ],
        ] );
    }
}
