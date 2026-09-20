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

    public function register(): void {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
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
    }

    public static function enabled(): bool {
        return (bool) get_option( self::OPTION, false );
    }

    public static function provision_credentials( int $actor_id ): array|\WP_Error {
        $actor = get_user_by( 'id', $actor_id );
        if ( ! $actor || ! user_can( $actor, 'edit_posts' ) ) {
            return new \WP_Error( 'hws_external_publishing_actor_invalid', 'Select a WordPress user who can edit posts.', [ 'status' => 400 ] );
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
        $authenticated = $this->authenticate( $request );
        if ( is_wp_error( $authenticated ) ) {
            return $authenticated;
        }

        return current_user_can( 'edit_posts' )
            ? true
            : new \WP_Error( 'hws_external_publishing_forbidden', 'The configured publishing user cannot create posts.', [ 'status' => 403 ] );
    }

    public function can_use_post( \WP_REST_Request $request ): bool|\WP_Error {
        $authenticated = $this->authenticate( $request );
        if ( is_wp_error( $authenticated ) ) {
            return $authenticated;
        }

        $post_id = absint( $request['id'] );
        $capability = 'DELETE' === strtoupper( $request->get_method() ) ? 'delete_post' : 'edit_post';

        return $post_id > 0 && current_user_can( $capability, $post_id )
            ? true
            : new \WP_Error( 'hws_external_publishing_forbidden', 'The configured publishing user cannot access the requested post.', [ 'status' => 403 ] );
    }

    public function status(): \WP_REST_Response {
        $user = wp_get_current_user();

        return new \WP_REST_Response( [
            'enabled' => true,
            'connector' => 'hws_base_tools',
            'authentication' => 'hmac_sha256',
            'version' => PluginMetadata::VERSION,
            'user' => [ 'id' => (int) $user->ID, 'name' => (string) $user->display_name, 'roles' => array_values( (array) $user->roles ) ],
            'capabilities' => [ 'create_posts' => current_user_can( 'edit_posts' ), 'publish_posts' => current_user_can( 'publish_posts' ) ],
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

    private function mutate( \WP_REST_Request $request, string $method, string $route, array $payload ): \WP_REST_Response|\WP_Error {
        $operation_id = trim( (string) ( $request->get_header( 'x-hexa-operation-id' ) ?: $request->get_param( 'operation_id' ) ) );
        if ( 1 !== preg_match( '/^[A-Za-z0-9._:-]{16,128}$/', $operation_id ) ) {
            return new \WP_Error( 'hws_operation_id_required', 'A valid X-Hexa-Operation-ID is required for every mutation.', [ 'status' => 400 ] );
        }
        $key = self::OPERATION_PREFIX . hash( 'sha256', $operation_id );
        $fingerprint = hash( 'sha256', wp_json_encode( [ $method, $route, $payload ] ) );
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
            $response = $this->proxy( $method, $route, $payload );
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
        $response = rest_do_request( $subrequest );
        $data = $response->get_data();
        if ( is_array( $data ) ) {
            $data['hexa_connector'] = 'hws_base_tools';
            $response->set_data( $data );
        }

        return $response;
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
