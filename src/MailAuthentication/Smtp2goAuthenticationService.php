<?php

declare( strict_types=1 );

namespace HWS\BaseTools\MailAuthentication;

defined( 'ABSPATH' ) || exit;

final class Smtp2goAuthenticationService {
    public const LAST_RESULT_OPTION = 'hws_smtp2go_last_authentication_test';

    private const PLUGIN_FILE = 'wp-mail-smtp/wp_mail_smtp.php';
    private const VALIDATION_URL = 'https://api.smtp2go.com/v3/api_keys/view';
    private const RESULT_MAX_AGE = 604800;

    /** @var array<string,callable> */
    private array $dependencies;

    /**
     * Optional dependencies keep the three-stage workflow independently testable.
     *
     * @param array<string,callable> $dependencies
     */
    public function __construct( array $dependencies = [] ) {
        $this->dependencies = $dependencies;
    }

    /**
     * Run settings, API-key, and delivery tests in order, stopping on failure.
     *
     * @return array<string,mixed>
     */
    public function run( string $recipient ): array {
        $recipient = $this->sanitize_email( $recipient );
        $started   = $this->timestamp();
        $config    = $this->configuration();
        $steps     = [];

        if ( ! $this->is_email( $recipient ) ) {
            $steps[] = $this->step( 'settings', 'Settings', false, 'Enter a valid test recipient before running authentication.', [] );
            $steps[] = $this->skipped_step( 'api_key', 'API Key', 'Skipped because the test recipient is invalid.' );
            $steps[] = $this->skipped_step( 'test_email', 'Test Email', 'Skipped because the test recipient is invalid.' );

            return $this->store_result( false, $recipient, $started, $steps, $config );
        }

        $settings = $this->settings_step( $config );
        $steps[]  = $settings;

        if ( ! $settings['success'] ) {
            $steps[] = $this->skipped_step( 'api_key', 'API Key', 'Skipped until the WP Mail SMTP settings pass.' );
            $steps[] = $this->skipped_step( 'test_email', 'Test Email', 'Skipped until the WP Mail SMTP settings pass.' );

            return $this->store_result( false, $recipient, $started, $steps, $config );
        }

        $api_key = $this->api_key_step( $config );
        $steps[] = $api_key;

        if ( ! $api_key['success'] ) {
            $steps[] = $this->skipped_step( 'test_email', 'Test Email', 'Skipped because SMTP2GO did not validate the API key.' );

            return $this->store_result( false, $recipient, $started, $steps, $config );
        }

        $email   = $this->test_email_step( $recipient, $config );
        $steps[] = $email;

        return $this->store_result( (bool) $email['success'], $recipient, $started, $steps, $config );
    }

    /**
     * Return the current configuration plus the last authoritative full test.
     *
     * @return array<string,mixed>
     */
    public function current_status(): array {
        $config        = $this->configuration();
        $settings      = $this->settings_step( $config );
        $last          = $this->get_option( self::LAST_RESULT_OPTION, [] );
        $last          = is_array( $last ) ? $last : [];
        $same_settings = ! empty( $last['configuration_fingerprint'] )
            && hash_equals( (string) $last['configuration_fingerprint'], $this->configuration_fingerprint( $config ) );
        $age           = isset( $last['timestamp'] ) ? max( 0, $this->unix_time() - (int) $last['timestamp'] ) : PHP_INT_MAX;
        $fresh         = $age <= self::RESULT_MAX_AGE;
        $healthy       = (bool) $settings['success'] && ! empty( $last['success'] ) && $same_settings && $fresh;

        if ( $healthy ) {
            $message = 'SMTP2GO authentication and test delivery passed ' . $this->relative_age( $age ) . '.';
        } elseif ( ! $settings['success'] ) {
            $message = (string) $settings['message'];
        } elseif ( empty( $last ) ) {
            $message = 'Settings are present, but a full SMTP2GO authentication test has not run yet.';
        } elseif ( ! $same_settings ) {
            $message = 'SMTP2GO settings changed after the last test. Run authentication again.';
        } elseif ( ! $fresh ) {
            $message = 'The last SMTP2GO authentication test is older than seven days. Run it again.';
        } else {
            $message = (string) ( $last['message'] ?? 'The last SMTP2GO authentication test failed.' );
        }

        return [
            'healthy'       => $healthy,
            'message'       => $message,
            'settings'      => $settings,
            'last_result'   => $last,
            'last_result_current' => $same_settings && $fresh,
            'mailer'        => (string) $config['mailer'],
            'from_email'    => (string) $config['from_email'],
            'key_configured'=> '' !== (string) $config['api_key'],
        ];
    }

    /**
     * Compatibility shape consumed by existing HWS health checks.
     *
     * @return array{status:bool,mailer:string,raw_value:string,details:string}
     */
    public function legacy_status(): array {
        $status = $this->current_status();

        return [
            'status'    => (bool) $status['healthy'],
            'mailer'    => (string) $status['mailer'],
            'raw_value' => (string) $status['message'],
            'details'   => (string) $status['from_email'],
        ];
    }

    /** @return array<string,mixed> */
    private function configuration(): array {
        $options = $this->get_option( 'wp_mail_smtp', [] );
        $options = is_array( $options ) ? $options : [];
        $mail    = is_array( $options['mail'] ?? null ) ? $options['mail'] : [];
        $api_key = $this->read_api_key( $options );

        return [
            'plugin_active' => $this->plugin_active(),
            'mailer'        => sanitize_key( (string) ( $mail['mailer'] ?? 'none' ) ),
            'from_email'    => $this->sanitize_email( (string) ( $mail['from_email'] ?? '' ) ),
            'from_name'     => sanitize_text_field( (string) ( $mail['from_name'] ?? '' ) ),
            'api_key'       => trim( $api_key ),
        ];
    }

    /** @param array<string,mixed> $config */
    private function settings_step( array $config ): array {
        $errors = [];

        if ( ! $config['plugin_active'] ) {
            $errors[] = 'WP Mail SMTP is not active.';
        }
        if ( 'smtp2go' !== $config['mailer'] ) {
            $errors[] = 'WP Mail SMTP is not using the SMTP2GO mailer.';
        }
        if ( ! $this->is_email( (string) $config['from_email'] ) ) {
            $errors[] = 'A valid From Email is not configured.';
        }
        if ( '' === (string) $config['api_key'] ) {
            $errors[] = 'The SMTP2GO API key is missing.';
        }

        $success = [] === $errors;
        $message = $success
            ? 'WP Mail SMTP is active and has complete SMTP2GO settings.'
            : implode( ' ', $errors );

        return $this->step(
            'settings',
            'Settings',
            $success,
            $message,
            [
                'WP Mail SMTP' => $config['plugin_active'] ? 'Active' : 'Inactive',
                'Mailer'       => 'smtp2go' === $config['mailer'] ? 'SMTP2GO' : ( '' !== $config['mailer'] ? (string) $config['mailer'] : 'Not configured' ),
                'From Email'   => '' !== $config['from_email'] ? (string) $config['from_email'] : 'Not configured',
                'API Key'      => '' !== $config['api_key'] ? 'Configured' : 'Missing',
            ]
        );
    }

    /** @param array<string,mixed> $config */
    private function api_key_step( array $config ): array {
        $response = $this->remote_post(
            self::VALIDATION_URL,
            [
                'headers' => [
                    'Accept'              => 'application/json',
                    'Content-Type'        => 'application/json',
                    'X-Smtp2go-Api-Key'   => (string) $config['api_key'],
                ],
                'body'        => '{}',
                'data_format' => 'body',
                'timeout'     => 20,
            ]
        );

        if ( $this->is_remote_error( $response ) ) {
            return $this->step(
                'api_key',
                'API Key',
                false,
                'SMTP2GO could not be reached: ' . $this->remote_error_message( $response ),
                [ 'Endpoint' => self::VALIDATION_URL, 'HTTP Status' => 'Network error' ]
            );
        }

        $http       = $this->response_code( $response );
        $payload    = $this->response_json( $response );
        $data       = is_array( $payload['data'] ?? null ) ? $payload['data'] : [];
        $error      = sanitize_text_field( (string) ( $data['error'] ?? $payload['message'] ?? '' ) );
        $error_code = sanitize_text_field( (string) ( $data['error_code'] ?? '' ) );
        $request_id = sanitize_text_field( (string) ( $payload['request_id'] ?? $data['request_id'] ?? '' ) );
        $limited    = $http >= 400 && $http < 500
            && str_contains( strtoupper( $error_code ), 'ENDPOINT_PERMISSION_DENIED' );
        $success    = ( $http >= 200 && $http < 300 && '' === $error ) || $limited;

        if ( $success ) {
            $message = $limited
                ? 'SMTP2GO accepted the API key. The key is active and correctly restricted to its permitted endpoints.'
                : 'SMTP2GO accepted the API key and confirmed it is active.';
        } else {
            $message = 'SMTP2GO rejected the API key.' . ( '' !== $error ? ' ' . $error : '' );
        }

        return $this->step(
            'api_key',
            'API Key',
            $success,
            $message,
            array_filter(
                [
                    'Endpoint'     => self::VALIDATION_URL,
                    'HTTP Status'  => (string) $http,
                    'Access'       => $limited ? 'Active, restricted key' : ( $success ? 'Active' : 'Rejected' ),
                    'Request ID'   => $request_id,
                    'Error Code'   => $success ? '' : $error_code,
                ],
                static fn( string $value ): bool => '' !== $value
            )
        );
    }

    /** @param array<string,mixed> $config */
    private function test_email_step( string $recipient, array $config ): array {
        $test_id = 'hws-' . gmdate( 'Ymd-His', $this->unix_time() ) . '-' . substr( hash( 'sha256', $recipient . '|' . $this->microtime() ), 0, 10 );
        $subject = '[HWS SMTP2GO Test] ' . $this->site_name();
        $body    = "HWS Base Tools completed an SMTP2GO authentication test.\n\n"
            . 'Site: ' . $this->site_url() . "\n"
            . 'From: ' . (string) $config['from_email'] . "\n"
            . 'Test ID: ' . $test_id . "\n"
            . 'Time: ' . $this->timestamp() . "\n";
        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'X-HWS-SMTP-Test-ID: ' . $test_id,
        ];
        $mail_result = $this->send_mail( $recipient, $subject, $body, $headers );
        $success     = ! empty( $mail_result['success'] );
        $error       = sanitize_text_field( (string) ( $mail_result['error'] ?? '' ) );
        $message     = $success
            ? 'WP Mail SMTP sent the test through SMTP2GO and confirmed the provider accepted it.'
            : 'The SMTP2GO test email failed.' . ( '' !== $error ? ' ' . $error : '' );

        return $this->step(
            'test_email',
            'Test Email',
            $success,
            $message,
            array_filter(
                [
                    'Recipient' => $recipient,
                    'From Email'=> (string) $config['from_email'],
                    'Test ID'   => $test_id,
                    'Result'    => $success ? 'Accepted by SMTP2GO' : 'Failed',
                    'Error'     => $success ? '' : $error,
                ],
                static fn( string $value ): bool => '' !== $value
            )
        );
    }

    /**
     * @param array<int,array<string,mixed>> $steps
     * @param array<string,mixed>            $config
     * @return array<string,mixed>
     */
    private function store_result( bool $success, string $recipient, string $started, array $steps, array $config ): array {
        $result = [
            'success'                   => $success,
            'message'                   => $success ? 'SMTP2GO authentication and test delivery passed.' : $this->failure_message( $steps ),
            'recipient'                 => $recipient,
            'started_at'                => $started,
            'completed_at'              => $this->timestamp(),
            'timestamp'                 => $this->unix_time(),
            'configuration_fingerprint' => $this->configuration_fingerprint( $config ),
            'steps'                     => $steps,
        ];

        $this->update_option( self::LAST_RESULT_OPTION, $result );

        return $result;
    }

    /** @param array<int,array<string,mixed>> $steps */
    private function failure_message( array $steps ): string {
        foreach ( $steps as $step ) {
            if ( 'failed' === ( $step['status'] ?? '' ) ) {
                return (string) ( $step['message'] ?? 'SMTP2GO authentication failed.' );
            }
        }

        return 'SMTP2GO authentication failed.';
    }

    /** @return array<string,mixed> */
    private function step( string $id, string $label, bool $success, string $message, array $details ): array {
        return [
            'id'        => $id,
            'label'     => $label,
            'status'    => $success ? 'passed' : 'failed',
            'success'   => $success,
            'message'   => $message,
            'details'   => $details,
            'timestamp' => $this->timestamp(),
        ];
    }

    /** @return array<string,mixed> */
    private function skipped_step( string $id, string $label, string $message ): array {
        return [
            'id'        => $id,
            'label'     => $label,
            'status'    => 'skipped',
            'success'   => false,
            'message'   => $message,
            'details'   => [],
            'timestamp' => $this->timestamp(),
        ];
    }

    /** @param array<string,mixed> $config */
    private function configuration_fingerprint( array $config ): string {
        return hash( 'sha256', implode( '|', [
            $config['plugin_active'] ? '1' : '0',
            (string) $config['mailer'],
            strtolower( (string) $config['from_email'] ),
            (string) $config['api_key'],
        ] ) );
    }

    /** @param array<string,mixed> $options */
    private function read_api_key( array $options ): string {
        if ( isset( $this->dependencies['api_key_reader'] ) ) {
            return (string) ( $this->dependencies['api_key_reader'] )( $options );
        }

        if ( function_exists( 'wp_mail_smtp' ) ) {
            try {
                $connection = wp_mail_smtp()->get_connections_manager()->get_primary_connection();
                $value      = $connection->get_options()->get( 'smtp2go', 'api_key' );
                if ( is_string( $value ) && '' !== trim( $value ) ) {
                    return $value;
                }
            } catch ( \Throwable ) {
                // Fall back to the stable wp_mail_smtp option shape below.
            }
        }

        return (string) ( $options['smtp2go']['api_key'] ?? '' );
    }

    private function plugin_active(): bool {
        if ( isset( $this->dependencies['plugin_active'] ) ) {
            return (bool) ( $this->dependencies['plugin_active'] )( self::PLUGIN_FILE );
        }

        if ( ! function_exists( 'is_plugin_active' ) && defined( 'ABSPATH' ) ) {
            $plugin_api = ABSPATH . 'wp-admin/includes/plugin.php';
            if ( is_readable( $plugin_api ) ) {
                require_once $plugin_api;
            }
        }

        return function_exists( 'is_plugin_active' ) && is_plugin_active( self::PLUGIN_FILE );
    }

    private function get_option( string $key, mixed $default ): mixed {
        if ( isset( $this->dependencies['get_option'] ) ) {
            return ( $this->dependencies['get_option'] )( $key, $default );
        }

        return get_option( $key, $default );
    }

    private function update_option( string $key, mixed $value ): void {
        if ( isset( $this->dependencies['update_option'] ) ) {
            ( $this->dependencies['update_option'] )( $key, $value );
            return;
        }

        update_option( $key, $value, false );
    }

    private function remote_post( string $url, array $args ): mixed {
        if ( isset( $this->dependencies['remote_post'] ) ) {
            return ( $this->dependencies['remote_post'] )( $url, $args );
        }

        return wp_remote_post( $url, $args );
    }

    /** @return array{success:bool,error:string} */
    private function send_mail( string $recipient, string $subject, string $body, array $headers ): array {
        if ( isset( $this->dependencies['send_mail'] ) ) {
            $result = ( $this->dependencies['send_mail'] )( $recipient, $subject, $body, $headers );
            if ( is_array( $result ) ) {
                return [ 'success' => ! empty( $result['success'] ), 'error' => (string) ( $result['error'] ?? '' ) ];
            }
            return [ 'success' => (bool) $result, 'error' => '' ];
        }

        $failure = '';
        $handler = static function( mixed $error ) use ( &$failure ): void {
            if ( is_object( $error ) && method_exists( $error, 'get_error_message' ) ) {
                $failure = (string) $error->get_error_message();
            }
        };

        add_action( 'wp_mail_failed', $handler );
        $sent = wp_mail( $recipient, $subject, $body, $headers );
        remove_action( 'wp_mail_failed', $handler );

        return [ 'success' => (bool) $sent, 'error' => $failure ];
    }

    private function is_remote_error( mixed $response ): bool {
        if ( isset( $this->dependencies['is_remote_error'] ) ) {
            return (bool) ( $this->dependencies['is_remote_error'] )( $response );
        }

        return function_exists( 'is_wp_error' ) && is_wp_error( $response );
    }

    private function remote_error_message( mixed $response ): string {
        if ( is_object( $response ) && method_exists( $response, 'get_error_message' ) ) {
            return sanitize_text_field( (string) $response->get_error_message() );
        }

        return 'Unknown network error.';
    }

    private function response_code( mixed $response ): int {
        if ( isset( $this->dependencies['response_code'] ) ) {
            return (int) ( $this->dependencies['response_code'] )( $response );
        }

        return (int) wp_remote_retrieve_response_code( $response );
    }

    /** @return array<string,mixed> */
    private function response_json( mixed $response ): array {
        if ( isset( $this->dependencies['response_body'] ) ) {
            $body = (string) ( $this->dependencies['response_body'] )( $response );
        } else {
            $body = (string) wp_remote_retrieve_body( $response );
        }

        $decoded = json_decode( $body, true );
        return is_array( $decoded ) ? $decoded : [];
    }

    private function sanitize_email( string $email ): string {
        if ( function_exists( 'sanitize_email' ) ) {
            return sanitize_email( $email );
        }

        $filtered = filter_var( $email, FILTER_SANITIZE_EMAIL );
        return is_string( $filtered ) ? $filtered : '';
    }

    private function is_email( string $email ): bool {
        return function_exists( 'is_email' ) ? (bool) is_email( $email ) : false !== filter_var( $email, FILTER_VALIDATE_EMAIL );
    }

    private function timestamp(): string {
        if ( isset( $this->dependencies['timestamp'] ) ) {
            return (string) ( $this->dependencies['timestamp'] )();
        }

        return function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' );
    }

    private function unix_time(): int {
        return isset( $this->dependencies['unix_time'] ) ? (int) ( $this->dependencies['unix_time'] )() : time();
    }

    private function microtime(): string {
        return isset( $this->dependencies['microtime'] ) ? (string) ( $this->dependencies['microtime'] )() : (string) microtime( true );
    }

    private function site_name(): string {
        if ( isset( $this->dependencies['site_name'] ) ) {
            return (string) ( $this->dependencies['site_name'] )();
        }

        return function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : 'WordPress Site';
    }

    private function site_url(): string {
        if ( isset( $this->dependencies['site_url'] ) ) {
            return (string) ( $this->dependencies['site_url'] )();
        }

        return function_exists( 'home_url' ) ? (string) home_url( '/' ) : '';
    }

    private function relative_age( int $age ): string {
        if ( $age < 60 ) {
            return 'less than a minute ago';
        }
        if ( $age < 3600 ) {
            $minutes = max( 1, (int) floor( $age / 60 ) );
            return $minutes . ' minute' . ( 1 === $minutes ? '' : 's' ) . ' ago';
        }
        if ( $age < 86400 ) {
            $hours = max( 1, (int) floor( $age / 3600 ) );
            return $hours . ' hour' . ( 1 === $hours ? '' : 's' ) . ' ago';
        }

        $days = max( 1, (int) floor( $age / 86400 ) );
        return $days . ' day' . ( 1 === $days ? '' : 's' ) . ' ago';
    }
}
