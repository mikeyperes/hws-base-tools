<?php

declare( strict_types=1 );

namespace HWS\BaseTools\ExternalPublishing;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use HWS\BaseTools\PluginRuntime\PluginMetadata;
use HWS\BaseTools\Security\SecretStore;

defined( 'ABSPATH' ) || exit;

final class ExternalPublishingModule implements ModuleInterface {
    public const OPTION = 'hws_base_tools_external_publishing_enabled';
    public const KEY_ID_OPTION = 'hws_base_tools_external_publishing_key_id';
    public const SECRET_OPTION = 'hws_base_tools_external_publishing_secret';
    public const ACTOR_OPTION = 'hws_base_tools_external_publishing_actor_id';

    private const NAMESPACE = 'hws-base-tools/v1';
    private const OPERATION_PREFIX = 'hws_ep_';
    private const REPLAY_PREFIX = 'hws_ep_nonce_';
    private const REVEAL_PREFIX = 'hws_ep_reveal_';
    private const MAX_CLOCK_SKEW = 300;
    private const MAX_UPLOAD_BYTES = 16777216;
    private const IMAGE_MIME_TYPES = [ 'image/avif', 'image/gif', 'image/jpeg', 'image/png', 'image/webp' ];

    public function register(): void {
        add_action( 'init', [ $this, 'register_external_meta' ], 30 );
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
        add_action( 'rest_after_insert_post', [ $this, 'cleanup_rest_faq_rows' ], 20, 3 );
        add_action( 'admin_post_hws_external_publishing_settings', [ self::class, 'save_settings' ] );
    }

    public function register_routes(): void {
        register_rest_route( self::NAMESPACE, '/external-publishing', [
            'methods' => 'GET', 'callback' => [ $this, 'status' ], 'permission_callback' => [ $this, 'can_use_collection' ],
        ] );
        register_rest_route( self::NAMESPACE, '/external-publishing/posts', [
            'methods' => 'POST', 'callback' => [ $this, 'create_post' ], 'permission_callback' => [ $this, 'can_use_collection' ],
        ] );
        register_rest_route( self::NAMESPACE, '/external-publishing/posts/(?P<id>\d+)', [
            [ 'methods' => 'GET', 'callback' => [ $this, 'read_post' ], 'permission_callback' => [ $this, 'can_use_post' ] ],
            [ 'methods' => 'POST', 'callback' => [ $this, 'update_post' ], 'permission_callback' => [ $this, 'can_use_post' ] ],
            [ 'methods' => 'DELETE', 'callback' => [ $this, 'delete_post' ], 'permission_callback' => [ $this, 'can_use_post' ] ],
        ] );
        register_rest_route( self::NAMESPACE, '/external-publishing/authors', [
            'methods' => 'GET', 'callback' => [ $this, 'list_authors' ], 'permission_callback' => [ $this, 'can_list_authors' ],
        ] );
        register_rest_route( self::NAMESPACE, '/external-publishing/wp/v2/(?P<resource>[A-Za-z0-9_-]+)(?:/(?P<id>\d+))?', [
            'methods' => [ 'GET', 'POST', 'DELETE' ], 'callback' => [ $this, 'proxy_wp_v2' ], 'permission_callback' => [ $this, 'can_use_proxy' ],
        ] );
        register_rest_route( self::NAMESPACE, '/external-publishing/media/upload', [
            'methods' => 'POST', 'callback' => [ $this, 'upload_media' ], 'permission_callback' => [ $this, 'can_upload_media' ],
        ] );
        register_rest_route( self::NAMESPACE, '/external-publishing/cache/purge', [
            'methods' => 'POST', 'callback' => [ $this, 'purge_cache' ], 'permission_callback' => [ $this, 'can_use_collection' ],
        ] );
        register_rest_route( self::NAMESPACE, '/external-publishing/article-audio/(?P<id>\d+)', [
            'methods' => 'POST', 'callback' => [ $this, 'generate_article_audio' ], 'permission_callback' => [ $this, 'can_use_post' ],
        ] );
    }

    /**
     * Expose only the article-delivery metadata that Publish owns. Registering
     * these exact keys lets both Application Password requests and the HMAC
     * bridge use the same WordPress REST persistence and readback behavior.
     */
    public function register_external_meta(): void {
        if ( ! self::enabled() ) {
            return;
        }

        foreach ( self::post_meta_definitions() as $key => $type ) {
            $this->register_meta_key( 'post', $key, $type );
        }
        foreach ( self::attachment_meta_definitions() as $key => $type ) {
            $this->register_meta_key( 'attachment', $key, $type );
        }
    }

    private function register_meta_key( string $post_type, string $key, string $type ): void {
        $registered = get_registered_meta_keys( 'post', $post_type );
        if ( isset( $registered[ $key ] ) ) {
            return;
        }

        register_post_meta( $post_type, $key, [
            'single' => true,
            'type' => $type,
            'show_in_rest' => true,
            'auth_callback' => static function ( bool $allowed, string $meta_key, int $object_id ): bool {
                return $object_id > 0 && current_user_can( 'edit_post', $object_id );
            },
        ] );
    }

    public function cleanup_rest_faq_rows( \WP_Post $post, \WP_REST_Request $request, bool $creating ): void {
        if ( ! self::enabled() || ! current_user_can( 'edit_post', $post->ID ) ) {
            return;
        }
        $params = $this->request_params( $request );
        $meta = is_array( $params['meta'] ?? null ) ? $params['meta'] : [];
        if ( ! array_key_exists( 'post_faq_items', $meta ) ) {
            return;
        }

        $keep = max( 0, min( 5, (int) $meta['post_faq_items'] ) );
        for ( $index = $keep; $index < 5; $index++ ) {
            foreach ( [ 'question', 'answer', 'enabled_for_schema' ] as $field ) {
                $key = 'post_faq_items_' . $index . '_' . $field;
                delete_post_meta( $post->ID, $key );
                delete_post_meta( $post->ID, '_' . $key );
            }
        }
    }

    private static function post_meta_definitions(): array {
        $definitions = [
            'post_summary' => 'string',
            '_post_summary' => 'string',
            'post_faqs' => 'string',
            '_post_faqs' => 'string',
            'post_faq_items' => 'string',
            '_post_faq_items' => 'string',
            'rank_math_title' => 'string',
            'rank_math_description' => 'string',
            'rank_math_focus_keyword' => 'string',
            'rank_math_seo_score' => 'string',
        ];
        foreach ( range( 0, 4 ) as $index ) {
            foreach ( [ 'question', 'answer', 'enabled_for_schema' ] as $field ) {
                $key = 'post_faq_items_' . $index . '_' . $field;
                $definitions[ $key ] = 'string';
                $definitions[ '_' . $key ] = 'string';
            }
        }

        return $definitions;
    }

    private static function attachment_meta_definitions(): array {
        return [
            '_hexa_media_sha256' => 'string',
            '_hexa_media_source_url' => 'string',
            '_hexa_media_original_filename' => 'string',
            '_hexa_media_pipeline' => 'string',
            '_hexa_upload' => 'boolean',
            '_hexa_draft_id' => 'integer',
        ];
    }

    public static function enabled(): bool {
        return (bool) get_option( self::OPTION, false );
    }

    public static function provision_credentials( int $actor_id ): array|\WP_Error {
        $actor = get_user_by( 'id', $actor_id );
        $required = [ 'edit_posts', 'publish_posts', 'upload_files', 'list_users', 'manage_categories' ];
        if ( ! $actor || array_filter( $required, static fn ( string $capability ): bool => ! user_can( $actor, $capability ) ) ) {
            return new \WP_Error( 'hws_external_publishing_actor_invalid', 'Select an administrator who can publish posts, upload media, resolve authors, and manage taxonomies.', [ 'status' => 400 ] );
        }

        try {
            $key_id = 'hws_' . bin2hex( random_bytes( 12 ) );
            $secret = rtrim( strtr( base64_encode( random_bytes( 48 ) ), '+/', '-_' ), '=' );
        } catch ( \Throwable $exception ) {
            return new \WP_Error( 'hws_external_publishing_random_failed', 'Secure credentials could not be generated.', [ 'status' => 500 ] );
        }

        if ( ! ( new SecretStore( self::SECRET_OPTION ) )->set( $secret ) ) {
            return new \WP_Error( 'hws_external_publishing_secret_failed', 'The publishing secret could not be stored securely.', [ 'status' => 500 ] );
        }

        update_option( self::KEY_ID_OPTION, $key_id, false );
        update_option( self::ACTOR_OPTION, $actor_id, false );

        return [ 'key_id' => $key_id, 'secret' => $secret, 'actor_id' => $actor_id ];
    }

    public function can_use_collection( \WP_REST_Request $request ): bool|\WP_Error {
        $authenticated = $this->authenticate_or_current_user( $request );
        if ( is_wp_error( $authenticated ) ) {
            return $authenticated;
        }

        return current_user_can( 'edit_posts' )
            ? true
            : new \WP_Error( 'hws_external_publishing_forbidden', 'The configured publishing user cannot create posts.', [ 'status' => 403 ] );
    }

    public function can_use_post( \WP_REST_Request $request ): bool|\WP_Error {
        $authenticated = $this->authenticate_or_current_user( $request );
        if ( is_wp_error( $authenticated ) ) {
            return $authenticated;
        }

        $post_id = absint( $request['id'] );
        $capability = 'DELETE' === strtoupper( $request->get_method() ) ? 'delete_post' : 'edit_post';

        return $post_id > 0 && current_user_can( $capability, $post_id )
            ? true
            : new \WP_Error( 'hws_external_publishing_forbidden', 'The configured publishing user cannot access the requested post.', [ 'status' => 403 ] );
    }

    public function can_list_authors( \WP_REST_Request $request ): bool|\WP_Error {
        $authenticated = $this->authenticate_or_current_user( $request );
        if ( is_wp_error( $authenticated ) ) {
            return $authenticated;
        }

        return current_user_can( 'list_users' )
            ? true
            : new \WP_Error( 'hws_external_publishing_forbidden', 'The configured publishing user cannot resolve authors.', [ 'status' => 403 ] );
    }

    public function can_use_proxy( \WP_REST_Request $request ): bool|\WP_Error {
        $authenticated = $this->authenticate_or_current_user( $request );
        if ( is_wp_error( $authenticated ) ) {
            return $authenticated;
        }

        $resource = sanitize_key( (string) $request['resource'] );
        $kind = $this->proxy_resource_kind( $resource );
        $method = strtoupper( $request->get_method() );
        $capability = match ( $kind ) {
            'users' => 'list_users',
            'media' => 'upload_files',
            'taxonomy' => 'POST' === $method ? 'manage_categories' : 'edit_posts',
            default => 'edit_posts',
        };

        return null !== $kind && current_user_can( $capability )
            ? true
            : new \WP_Error( 'hws_external_publishing_forbidden', 'The configured publishing user cannot access publishing resources.', [ 'status' => 403 ] );
    }

    public function can_upload_media( \WP_REST_Request $request ): bool|\WP_Error {
        $authenticated = $this->authenticate_or_current_user( $request );
        if ( is_wp_error( $authenticated ) ) {
            return $authenticated;
        }

        return current_user_can( 'upload_files' )
            ? true
            : new \WP_Error( 'hws_external_publishing_forbidden', 'The configured publishing user cannot upload files.', [ 'status' => 403 ] );
    }

    public function status(): \WP_REST_Response {
        $user = wp_get_current_user();

        return new \WP_REST_Response( [
            'enabled' => true,
            'connector' => 'hws_base_tools',
            'authentication' => 'hmac_sha256',
            'version' => PluginMetadata::VERSION,
            'user' => [ 'id' => (int) $user->ID, 'name' => (string) $user->display_name, 'roles' => array_values( (array) $user->roles ) ],
            'capabilities' => [
                'create_posts' => current_user_can( 'edit_posts' ),
                'publish_posts' => current_user_can( 'publish_posts' ),
                'upload_media' => current_user_can( 'upload_files' ),
                'authors' => current_user_can( 'list_users' ),
                'taxonomies' => current_user_can( 'manage_categories' ),
                'post_meta' => current_user_can( 'edit_posts' ),
                'cache_purge' => current_user_can( 'edit_posts' ),
                'article_audio' => current_user_can( 'edit_posts' ),
            ],
        ], 200 );
    }

    public function create_post( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        return $this->mutate( $request, 'POST', '/wp/v2/posts', $this->post_payload( $request ) );
    }

    public function read_post( \WP_REST_Request $request ): \WP_REST_Response {
        return $this->proxy( 'GET', '/wp/v2/posts/' . absint( $request['id'] ), [], [ 'context' => 'edit' ] );
    }

    public function update_post( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        return $this->mutate( $request, 'POST', '/wp/v2/posts/' . absint( $request['id'] ), $this->post_payload( $request ) );
    }

    public function delete_post( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        return $this->mutate( $request, 'DELETE', '/wp/v2/posts/' . absint( $request['id'] ), [ 'force' => rest_sanitize_boolean( $request->get_param( 'force' ) ) ] );
    }

    public function list_authors( \WP_REST_Request $request ): \WP_REST_Response {
        return $this->proxy_users( $request );
    }

    public function proxy_wp_v2( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $resource = sanitize_key( (string) $request['resource'] );
        $id = absint( $request['id'] );
        $method = strtoupper( $request->get_method() );
        $kind = $this->proxy_resource_kind( $resource );
        if ( null === $kind || ! $this->proxy_method_allowed( $kind, $method, $id ) ) {
            return new \WP_Error( 'hws_external_publishing_route_forbidden', 'This WordPress REST operation is not available through the publishing bridge.', [ 'status' => 404 ] );
        }

        $route = '/wp/v2/' . $resource . ( $id > 0 ? '/' . $id : '' );
        $payload = $this->proxy_payload( $kind, $request );
        $query = $this->proxy_query( $request );
        if ( 'GET' === $method ) {
            if ( 'users' === $kind ) {
                return $this->proxy_users( $request );
            }

            return $this->proxy( $method, $route, [], $query );
        }

        return $this->mutate( $request, $method, $route, $payload, $query );
    }

    public function upload_media( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $body = (string) $request->get_body();
        $bytes = strlen( $body );
        $mime = strtolower( trim( (string) $request->get_header( 'content-type' ) ) );
        $disposition = trim( (string) $request->get_header( 'content-disposition' ) );
        if ( $bytes <= 0 || $bytes > self::MAX_UPLOAD_BYTES || ! in_array( $mime, self::IMAGE_MIME_TYPES, true ) ) {
            return new \WP_Error( 'hws_external_publishing_media_invalid', 'The media upload is empty, too large, or has an unsupported image type.', [ 'status' => 400 ] );
        }
        if ( 1 !== preg_match( '/^attachment;\s*filename="([A-Za-z0-9._-]{1,190})"$/D', $disposition ) ) {
            return new \WP_Error( 'hws_external_publishing_media_invalid', 'A safe media filename is required.', [ 'status' => 400 ] );
        }

        $fingerprint_payload = [
            'sha256' => hash( 'sha256', $body ),
            'bytes' => $bytes,
            'mime' => $mime,
            'disposition' => $disposition,
        ];

        return $this->mutate_callback(
            $request,
            'POST',
            '/wp/v2/media',
            $fingerprint_payload,
            function () use ( $body, $mime, $disposition ): \WP_REST_Response {
                $subrequest = new \WP_REST_Request( 'POST', '/wp/v2/media' );
                $subrequest->set_header( 'Content-Type', $mime );
                $subrequest->set_header( 'Content-Disposition', $disposition );
                $subrequest->set_body( $body );

                return $this->decorate_response( rest_do_request( $subrequest ) );
            }
        );
    }

    public function purge_cache( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        return $this->mutate_callback(
            $request,
            'POST',
            '/hws-base-tools/cache/purge',
            [],
            function (): \WP_REST_Response {
                $purged = [];
                if ( defined( 'LSCWP_V' ) || class_exists( '\\LiteSpeed\\Purge' ) ) {
                    do_action( 'litespeed_purge_all' );
                    $purged[] = 'litespeed';
                }
                if ( function_exists( 'wp_cache_clear_cache' ) ) {
                    wp_cache_clear_cache();
                    $purged[] = 'wp_cache';
                }
                if ( function_exists( 'w3tc_flush_all' ) ) {
                    w3tc_flush_all();
                    $purged[] = 'w3tc';
                }
                if ( function_exists( 'rocket_clean_domain' ) ) {
                    rocket_clean_domain();
                    $purged[] = 'wp_rocket';
                }
                wp_cache_flush();
                $purged[] = 'object_cache';

                return new \WP_REST_Response( [ 'success' => true, 'purged' => array_values( array_unique( $purged ) ), 'hexa_connector' => 'hws_base_tools' ], 200 );
            }
        );
    }

    public function generate_article_audio( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $post_id = absint( $request['id'] );
        $payload = $this->scalar_payload( $request, [ 'force', 'shorten', 'provider', 'profile', 'voice', 'speed' ] );

        return $this->mutate( $request, 'POST', '/smp-tts/v1/posts/' . $post_id . '/generate', $payload );
    }

    private function authenticate_or_current_user( \WP_REST_Request $request ): bool|\WP_Error {
        if ( ! self::enabled() ) {
            return new \WP_Error( 'hws_external_publishing_disabled', 'External publishing is disabled in HWS Base Tools.', [ 'status' => 403 ] );
        }

        $has_hmac = '' !== trim( (string) $request->get_header( 'x-hexa-key-id' ) )
            || '' !== trim( (string) $request->get_header( 'x-hexa-signature' ) );
        if ( ! $has_hmac && get_current_user_id() > 0 ) {
            return true;
        }

        return $this->authenticate( $request );
    }

    private function authenticate( \WP_REST_Request $request ): bool|\WP_Error {
        if ( ! self::enabled() ) {
            return new \WP_Error( 'hws_external_publishing_disabled', 'External publishing is disabled in HWS Base Tools.', [ 'status' => 403 ] );
        }

        $key_id = trim( (string) $request->get_header( 'x-hexa-key-id' ) );
        $timestamp = trim( (string) $request->get_header( 'x-hexa-timestamp' ) );
        $nonce = trim( (string) $request->get_header( 'x-hexa-nonce' ) );
        $content_hash = strtolower( trim( (string) $request->get_header( 'x-hexa-content-sha256' ) ) );
        $signature = strtolower( trim( (string) $request->get_header( 'x-hexa-signature' ) ) );
        $stored_key_id = trim( (string) get_option( self::KEY_ID_OPTION, '' ) );

        if (
            1 !== preg_match( '/^hws_[a-f0-9]{24}$/', $key_id )
            || '' === $stored_key_id
            || ! hash_equals( $stored_key_id, $key_id )
            || 1 !== preg_match( '/^[0-9]{10}$/', $timestamp )
            || abs( time() - (int) $timestamp ) > self::MAX_CLOCK_SKEW
            || 1 !== preg_match( '/^[A-Za-z0-9_-]{24,128}$/', $nonce )
            || 1 !== preg_match( '/^[a-f0-9]{64}$/', $content_hash )
            || 1 !== preg_match( '/^[a-f0-9]{64}$/', $signature )
        ) {
            return new \WP_Error( 'hws_external_publishing_unauthorized', 'External publishing authentication failed.', [ 'status' => 401 ] );
        }

        $body_hash = hash( 'sha256', (string) $request->get_body() );
        if ( ! hash_equals( $body_hash, $content_hash ) ) {
            return new \WP_Error( 'hws_external_publishing_unauthorized', 'External publishing authentication failed.', [ 'status' => 401 ] );
        }

        $route = '/' . ltrim( (string) $request->get_route(), '/' );
        $canonical = strtoupper( $request->get_method() ) . "\n" . $route . "\n" . $timestamp . "\n" . $nonce . "\n" . $body_hash;
        $secret = ( new SecretStore( self::SECRET_OPTION ) )->get();
        if ( strlen( $secret ) < 43 || strlen( $secret ) > 256 ) {
            return new \WP_Error( 'hws_external_publishing_unauthorized', 'External publishing authentication failed.', [ 'status' => 401 ] );
        }
        $expected = hash_hmac( 'sha256', $canonical, $secret );
        if ( ! hash_equals( $expected, $signature ) ) {
            return new \WP_Error( 'hws_external_publishing_unauthorized', 'External publishing authentication failed.', [ 'status' => 401 ] );
        }

        $replay_key = self::REPLAY_PREFIX . hash( 'sha256', $key_id . '|' . $nonce );
        if ( false !== get_transient( $replay_key ) ) {
            return new \WP_Error( 'hws_external_publishing_replay', 'This signed request was already received.', [ 'status' => 409 ] );
        }
        set_transient( $replay_key, 1, self::MAX_CLOCK_SKEW * 2 );

        $actor = get_user_by( 'id', absint( get_option( self::ACTOR_OPTION, 0 ) ) );
        if ( ! $actor ) {
            return new \WP_Error( 'hws_external_publishing_actor_missing', 'The configured publishing user is unavailable.', [ 'status' => 403 ] );
        }
        wp_set_current_user( (int) $actor->ID );

        return true;
    }

    private function mutate( \WP_REST_Request $request, string $method, string $route, array $payload, array $query = [] ): \WP_REST_Response|\WP_Error {
        return $this->mutate_callback(
            $request,
            $method,
            $route,
            [ 'payload' => $payload, 'query' => $query ],
            fn (): \WP_REST_Response => $this->proxy( $method, $route, $payload, $query )
        );
    }

    private function mutate_callback( \WP_REST_Request $request, string $method, string $route, array $fingerprint_payload, callable $callback ): \WP_REST_Response|\WP_Error {
        $operation_id = trim( (string) ( $request->get_header( 'x-hexa-operation-id' ) ?: $request->get_param( 'operation_id' ) ) );
        if ( 1 !== preg_match( '/^[A-Za-z0-9._:-]{16,128}$/', $operation_id ) ) {
            return new \WP_Error( 'hws_operation_id_required', 'A valid X-Hexa-Operation-ID is required for every mutation.', [ 'status' => 400 ] );
        }
        $key = self::OPERATION_PREFIX . hash( 'sha256', $operation_id );
        $fingerprint = hash( 'sha256', wp_json_encode( [ $method, $route, $fingerprint_payload ] ) );
        $existing = get_transient( $key );
        if ( is_array( $existing ) ) {
            return $this->replay( $existing, $fingerprint );
        }
        $lock_key = $key . '_lock';
        $old_lock = get_option( $lock_key, null );
        if ( is_array( $old_lock ) && (int) ( $old_lock['created_at'] ?? 0 ) < time() - 300 ) {
            delete_option( $lock_key );
        }
        if ( ! add_option( $lock_key, [ 'fingerprint' => $fingerprint, 'created_at' => time() ], '', false ) ) {
            $raced = get_transient( $key );

            return is_array( $raced ) ? $this->replay( $raced, $fingerprint ) : new \WP_Error( 'hws_operation_in_progress', 'The matching operation is still in progress.', [ 'status' => 409 ] );
        }
        try {
            $response = $callback();
            set_transient( $key, [
                'state' => 'complete', 'fingerprint' => $fingerprint,
                'status' => $response->get_status(), 'data' => $response->get_data(),
            ], DAY_IN_SECONDS );

            return $response;
        } finally {
            delete_option( $lock_key );
        }
    }

    private function replay( array $record, string $fingerprint ): \WP_REST_Response|\WP_Error {
        if ( ! hash_equals( (string) ( $record['fingerprint'] ?? '' ), $fingerprint ) ) {
            return new \WP_Error( 'hws_operation_id_reused', 'The operation ID was already used for a different mutation.', [ 'status' => 409 ] );
        }
        if ( 'complete' !== (string) ( $record['state'] ?? '' ) ) {
            return new \WP_Error( 'hws_operation_in_progress', 'The matching operation is still in progress.', [ 'status' => 409 ] );
        }
        $data = is_array( $record['data'] ?? null ) ? $record['data'] : [];
        $data['hexa_idempotent_replay'] = true;

        return new \WP_REST_Response( $data, (int) ( $record['status'] ?? 200 ) );
    }

    private function proxy( string $method, string $route, array $body = [], array $query = [] ): \WP_REST_Response {
        $subrequest = new \WP_REST_Request( $method, $route );
        $subrequest->set_body_params( $body );
        $subrequest->set_query_params( $query );
        return $this->decorate_response( rest_do_request( $subrequest ) );
    }

    /**
     * Return the bounded author directory directly after the bridge's signed
     * request has authenticated an actor with list_users. Some WordPress
     * security layers reject a nested /wp/v2/users dispatch even though that
     * actor remains authorized, so the bridge owns this exact read contract.
     */
    private function proxy_users( \WP_REST_Request $request ): \WP_REST_Response {
        $per_page = max( 1, min( 100, absint( $request->get_param( 'per_page' ) ?: 100 ) ) );
        $page = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
        $query = [
            'number' => $per_page,
            'offset' => ( $page - 1 ) * $per_page,
            'orderby' => 'display_name',
            'order' => 'ASC',
        ];

        $include = wp_parse_id_list( $request->get_param( 'include' ) );
        if ( [] !== $include ) {
            $query['include'] = $include;
        }

        $search = sanitize_text_field( (string) $request->get_param( 'search' ) );
        if ( '' !== $search ) {
            $query['search'] = '*' . $search . '*';
            $query['search_columns'] = [ 'user_login', 'user_nicename', 'user_email', 'display_name' ];
        }

        $authors = array_map(
            static fn ( \WP_User $user ): array => [
                'id' => (int) $user->ID,
                'login' => (string) $user->user_login,
                'name' => (string) $user->display_name,
                'slug' => (string) $user->user_nicename,
                'email' => (string) $user->user_email,
                'roles' => array_values( array_map( 'strval', (array) $user->roles ) ),
            ],
            array_values( array_filter( get_users( $query ), static fn ( mixed $user ): bool => $user instanceof \WP_User ) )
        );

        $user_counts = count_users();
        $total_users = (int) ( $user_counts['total_users'] ?? count( $authors ) );
        $response = new \WP_REST_Response( $authors, 200 );
        $response->header( 'X-WP-Total', (string) $total_users );
        $response->header( 'X-WP-TotalPages', (string) max( 1, (int) ceil( $total_users / $per_page ) ) );

        return $this->decorate_response( $response );
    }

    private function decorate_response( \WP_REST_Response $response ): \WP_REST_Response {
        $data = $response->get_data();
        if ( is_array( $data ) ) {
            $data['hexa_connector'] = 'hws_base_tools';
            $response->set_data( $data );
        }

        return $response;
    }

    private function proxy_resource_kind( string $resource ): ?string {
        if ( in_array( $resource, [ 'posts', 'media', 'users' ], true ) ) {
            return $resource;
        }
        if ( in_array( $resource, [ 'categories', 'tags' ], true ) ) {
            return 'taxonomy';
        }
        foreach ( get_taxonomies( [ 'show_in_rest' => true ], 'objects' ) as $taxonomy ) {
            if ( ! is_object( $taxonomy ) || ! in_array( 'post', (array) ( $taxonomy->object_type ?? [] ), true ) ) {
                continue;
            }
            $rest_base = sanitize_key( (string) ( $taxonomy->rest_base ?: $taxonomy->name ) );
            if ( $resource === $rest_base ) {
                return 'taxonomy';
            }
        }

        return null;
    }

    private function proxy_method_allowed( string $kind, string $method, int $id ): bool {
        if ( 'users' === $kind ) {
            return 'GET' === $method;
        }
        if ( 'taxonomy' === $kind ) {
            return in_array( $method, [ 'GET', 'POST' ], true ) && ( 'POST' !== $method || 0 === $id );
        }
        if ( 'posts' === $kind || 'media' === $kind ) {
            return in_array( $method, [ 'GET', 'POST', 'DELETE' ], true )
                && ( 'DELETE' !== $method || $id > 0 );
        }

        return false;
    }

    private function proxy_payload( string $kind, \WP_REST_Request $request ): array {
        if ( 'posts' === $kind ) {
            if ( 'DELETE' === strtoupper( $request->get_method() ) ) {
                return [ 'force' => rest_sanitize_boolean( $request->get_param( 'force' ) ) ];
            }

            return $this->post_payload( $request );
        }
        if ( 'media' === $kind ) {
            if ( 'DELETE' === strtoupper( $request->get_method() ) ) {
                return [ 'force' => rest_sanitize_boolean( $request->get_param( 'force' ) ) ];
            }
            $payload = $this->scalar_payload( $request, [ 'title', 'caption', 'description', 'alt_text' ] );
            $params = $this->request_params( $request );
            $payload['meta'] = $this->allowed_meta_payload( (array) ( $params['meta'] ?? [] ), self::attachment_meta_definitions() );
            if ( [] === $payload['meta'] ) {
                unset( $payload['meta'] );
            }

            return $payload;
        }
        if ( 'taxonomy' === $kind ) {
            return $this->scalar_payload( $request, [ 'name', 'slug', 'description', 'parent' ] );
        }

        return [];
    }

    private function proxy_query( \WP_REST_Request $request ): array {
        $query = $request->get_query_params();
        unset( $query['resource'], $query['id'], $query['rest_route'] );

        return $query;
    }

    private function scalar_payload( \WP_REST_Request $request, array $fields ): array {
        $params = $this->request_params( $request );
        $payload = [];
        foreach ( $fields as $field ) {
            if ( array_key_exists( $field, $params ) && is_scalar( $params[ $field ] ) ) {
                $payload[ $field ] = $params[ $field ];
            }
        }

        return $payload;
    }

    private function request_params( \WP_REST_Request $request ): array {
        $params = $request->get_json_params();

        return is_array( $params ) && [] !== $params ? $params : (array) $request->get_body_params();
    }

    private function allowed_meta_payload( array $meta, array $definitions ): array {
        $allowed = [];
        foreach ( $meta as $key => $value ) {
            $key = (string) $key;
            if ( ! isset( $definitions[ $key ] ) || ( ! is_scalar( $value ) && null !== $value ) ) {
                continue;
            }
            $allowed[ $key ] = $value;
        }

        return $allowed;
    }

    private function post_payload( \WP_REST_Request $request ): array {
        $params = $request->get_json_params();
        if ( ! is_array( $params ) || [] === $params ) {
            $params = $request->get_body_params();
        }
        $payload = [];
        foreach ( [ 'title', 'content', 'excerpt', 'status', 'slug', 'date' ] as $field ) {
            if ( array_key_exists( $field, $params ) && is_scalar( $params[ $field ] ) ) {
                $payload[ $field ] = (string) $params[ $field ];
            }
        }
        foreach ( [ 'author', 'featured_media' ] as $field ) {
            if ( array_key_exists( $field, $params ) ) {
                $payload[ $field ] = absint( $params[ $field ] );
            }
        }
        $array_fields = [ 'categories', 'tags' ];
        foreach ( get_object_taxonomies( 'post', 'objects' ) as $taxonomy ) {
            if ( ! empty( $taxonomy->show_in_rest ) ) {
                $array_fields[] = (string) ( $taxonomy->rest_base ?: $taxonomy->name );
            }
        }
        foreach ( array_unique( $array_fields ) as $field ) {
            if ( array_key_exists( $field, $params ) && is_array( $params[ $field ] ) ) {
                $payload[ $field ] = array_values( array_unique( array_filter( array_map( 'absint', $params[ $field ] ) ) ) );
            }
        }
        $meta = $this->allowed_meta_payload( (array) ( $params['meta'] ?? [] ), self::post_meta_definitions() );
        if ( [] !== $meta ) {
            $payload['meta'] = $meta;
        }

        return $payload;
    }

    public static function render(): void {
        $enabled = self::enabled();
        $key_id = trim( (string) get_option( self::KEY_ID_OPTION, '' ) );
        $actor = get_user_by( 'id', absint( get_option( self::ACTOR_OPTION, 0 ) ) );
        $revealed = self::consume_revealed_credentials( get_current_user_id() );
        if ( isset( $_GET['hws_external_publishing_saved'] ) ) {
            echo '<div class="notice notice-success inline"><p>External publishing settings saved.</p></div>';
        }
        echo '<div class="hws-section"><h2>External Publishing API</h2>';
        echo '<p>Provides a dedicated HMAC-signed publishing bridge for Scale / Publish. It is disabled by default and does not use WordPress passwords.</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'hws_external_publishing_settings' );
        echo '<input type="hidden" name="action" value="hws_external_publishing_settings">';
        echo '<label><input type="checkbox" name="enabled" value="1" ' . checked( $enabled, true, false ) . '> Enable external post submissions</label> ';
        submit_button( 'Save', 'primary', 'submit', false );
        echo '</form>';
        echo '<p><strong>Key ID:</strong> <code>' . esc_html( '' !== $key_id ? $key_id : 'Not generated' ) . '</code></p>';
        echo '<p><strong>Publishing user:</strong> ' . esc_html( $actor ? $actor->user_login : 'Not configured' ) . '</p>';
        if ( is_array( $revealed ) ) {
            echo '<div class="notice notice-warning inline"><p><strong>Copy this secret now. It will not be shown again.</strong></p>';
            echo '<p>Key ID: <code>' . esc_html( (string) $revealed['key_id'] ) . '</code></p>';
            echo '<p>Secret: <code>' . esc_html( (string) $revealed['secret'] ) . '</code></p></div>';
        }
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'hws_external_publishing_settings' );
        echo '<input type="hidden" name="action" value="hws_external_publishing_settings">';
        echo '<input type="hidden" name="rotate_credentials" value="1">';
        echo '<input type="hidden" name="enabled" value="' . ( $enabled ? '1' : '0' ) . '">';
        submit_button( '' === $key_id ? 'Generate Credentials' : 'Rotate Credentials', 'secondary', 'submit', false );
        echo '</form><p><code>' . esc_html( rest_url( 'hws-base-tools/v1/external-publishing' ) ) . '</code></p></div>';
    }

    public static function save_settings(): void {
        if ( ! current_user_can( PluginMetadata::ADMIN_CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to change this setting.', 'hws-base-tools' ) );
        }
        check_admin_referer( 'hws_external_publishing_settings' );

        $enabled = isset( $_POST['enabled'] ) && '1' === (string) wp_unslash( $_POST['enabled'] );
        $rotate = isset( $_POST['rotate_credentials'] );
        $credentials_configured = '' !== trim( (string) get_option( self::KEY_ID_OPTION, '' ) )
            && '' !== trim( (string) get_option( self::SECRET_OPTION, '' ) );

        if ( $rotate || ( $enabled && ! $credentials_configured ) ) {
            $credentials = self::provision_credentials( get_current_user_id() );
            if ( is_wp_error( $credentials ) ) {
                wp_die( esc_html( $credentials->get_error_message() ) );
            }
            self::store_revealed_credentials( get_current_user_id(), $credentials );
        } elseif ( $enabled && 0 === absint( get_option( self::ACTOR_OPTION, 0 ) ) ) {
            update_option( self::ACTOR_OPTION, get_current_user_id(), false );
        }

        update_option( self::OPTION, $enabled ? 1 : 0, false );
        wp_safe_redirect( add_query_arg( [ 'page' => PluginMetadata::ADMIN_PAGE_SLUG, 'tab' => 'external-publishing', 'hws_external_publishing_saved' => '1' ], admin_url( 'options-general.php' ) ) );
        exit;
    }

    private static function store_revealed_credentials( int $user_id, array $credentials ): void {
        $option = self::REVEAL_PREFIX . $user_id;
        ( new SecretStore( $option ) )->set( wp_json_encode( $credentials ) );
        update_option( $option . '_expires', time() + self::MAX_CLOCK_SKEW, false );
    }

    private static function consume_revealed_credentials( int $user_id ): ?array {
        $option = self::REVEAL_PREFIX . $user_id;
        $expires = (int) get_option( $option . '_expires', 0 );
        if ( $expires <= 0 ) {
            return null;
        }

        $credentials = null;
        if ( $expires >= time() ) {
            $decoded = json_decode( ( new SecretStore( $option ) )->get(), true );
            $credentials = is_array( $decoded ) ? $decoded : null;
        }
        delete_option( $option );
        delete_option( $option . '_expires' );

        return $credentials;
    }
}
