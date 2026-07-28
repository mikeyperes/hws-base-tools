<?php

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

$failures = [];
$passes   = 0;

function mail_auth_expect( bool $condition, string $message ): void {
    global $failures, $passes;

    if ( $condition ) {
        ++$passes;
        echo "PASS {$message}\n";
        return;
    }

    $failures[] = $message;
    echo "FAIL {$message}\n";
}

function sanitize_key( string $value ): string {
    return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) );
}

function sanitize_text_field( mixed $value ): string {
    return trim( strip_tags( (string) $value ) );
}

function sanitize_email( string $value ): string {
    $filtered = filter_var( $value, FILTER_SANITIZE_EMAIL );
    return is_string( $filtered ) ? $filtered : '';
}

function is_email( string $value ): bool {
    return false !== filter_var( $value, FILTER_VALIDATE_EMAIL );
}

require_once dirname( __DIR__ ) . '/src/MailAuthentication/Smtp2goAuthenticationService.php';

use HWS\BaseTools\MailAuthentication\Smtp2goAuthenticationService;

/**
 * @param array<string,mixed> $wp_mail_smtp
 * @param callable            $remote_post
 * @param callable            $send_mail
 * @return array{0:Smtp2goAuthenticationService,1:object{values:array<string,mixed>}}
 */
function mail_auth_service( array $wp_mail_smtp, callable $remote_post, callable $send_mail ): array {
    $stored = (object) [ 'values' => [ 'wp_mail_smtp' => $wp_mail_smtp ] ];
    $now    = 1785232800;

    $service = new Smtp2goAuthenticationService(
        [
            'plugin_active' => static fn(): bool => true,
            'get_option'    => static function( string $key, mixed $default ) use ( $stored ): mixed {
                return $stored->values[ $key ] ?? $default;
            },
            'update_option' => static function( string $key, mixed $value ) use ( $stored ): void {
                $stored->values[ $key ] = $value;
            },
            'api_key_reader' => static fn( array $options ): string => (string) ( $options['smtp2go']['api_key'] ?? '' ),
            'remote_post'    => $remote_post,
            'response_code'  => static fn( array $response ): int => (int) ( $response['response']['code'] ?? 0 ),
            'response_body'  => static fn( array $response ): string => (string) ( $response['body'] ?? '' ),
            'send_mail'      => $send_mail,
            'timestamp'      => static fn(): string => '2026-07-28 10:00:00',
            'unix_time'      => static fn(): int => $now,
            'microtime'      => static fn(): string => '1785232800.1234',
            'site_name'      => static fn(): string => 'Example Site',
            'site_url'       => static fn(): string => 'https://example.com/',
        ]
    );

    return [ $service, $stored ];
}

$remote_calls = 0;
$mail_calls   = 0;
[ $missing_service ] = mail_auth_service(
    [ 'mail' => [ 'mailer' => 'mail', 'from_email' => '' ] ],
    static function() use ( &$remote_calls ): array {
        ++$remote_calls;
        return [];
    },
    static function() use ( &$mail_calls ): bool {
        ++$mail_calls;
        return true;
    }
);
$missing = $missing_service->run( 'recipient@example.com' );
mail_auth_expect( ! $missing['success'], 'missing settings fail the authentication workflow' );
mail_auth_expect( [ 'failed', 'skipped', 'skipped' ] === array_column( $missing['steps'], 'status' ), 'missing settings stop API and email stages' );
mail_auth_expect( 0 === $remote_calls && 0 === $mail_calls, 'missing settings make no API or mail call' );

$invalid_remote_calls = 0;
$invalid_mail_calls   = 0;
[ $invalid_service ] = mail_auth_service(
    [
        'mail'    => [ 'mailer' => 'smtp2go', 'from_email' => 'admin@example.com' ],
        'smtp2go' => [ 'api_key' => 'unit-test-invalid-key' ],
    ],
    static function() use ( &$invalid_remote_calls ): array {
        ++$invalid_remote_calls;
        return [
            'response' => [ 'code' => 400 ],
            'body'     => json_encode( [ 'data' => [ 'error' => 'Invalid API key.', 'error_code' => 'E_API_KEY_INVALID' ] ] ),
        ];
    },
    static function() use ( &$invalid_mail_calls ): bool {
        ++$invalid_mail_calls;
        return true;
    }
);
$invalid = $invalid_service->run( 'recipient@example.com' );
mail_auth_expect( ! $invalid['success'], 'rejected API key fails the authentication workflow' );
mail_auth_expect( [ 'passed', 'failed', 'skipped' ] === array_column( $invalid['steps'], 'status' ), 'rejected API key stops before test delivery' );
mail_auth_expect( 1 === $invalid_remote_calls && 0 === $invalid_mail_calls, 'rejected API key never sends a test email' );

$active_key   = 'unit-test-active-key';
$success_mail = [];
[ $success_service, $success_store ] = mail_auth_service(
    [
        'mail'    => [ 'mailer' => 'smtp2go', 'from_email' => 'admin@example.com', 'from_name' => 'Example Site' ],
        'smtp2go' => [ 'api_key' => $active_key ],
    ],
    static function( string $url, array $args ) use ( $active_key ): array {
        mail_auth_expect( str_ends_with( $url, '/api_keys/view' ), 'API validation uses the SMTP2GO key endpoint' );
        mail_auth_expect( $active_key === ( $args['headers']['X-Smtp2go-Api-Key'] ?? '' ), 'API validation uses the configured key internally' );
        return [
            'response' => [ 'code' => 403 ],
            'body'     => json_encode(
                [
                    'data' => [
                        'error'      => 'You do not have permission to access this API endpoint',
                        'error_code' => 'E_ApiResponseCodes.ENDPOINT_PERMISSION_DENIED',
                    ],
                ]
            ),
        ];
    },
    static function( string $recipient, string $subject, string $body, array $headers ) use ( &$success_mail ): array {
        $success_mail = compact( 'recipient', 'subject', 'body', 'headers' );
        return [ 'success' => true, 'error' => '' ];
    }
);
$success = $success_service->run( 'recipient@example.com' );
mail_auth_expect( $success['success'], 'restricted send-only API key passes as active' );
mail_auth_expect( [ 'passed', 'passed', 'passed' ] === array_column( $success['steps'], 'status' ), 'successful workflow passes all three stages in order' );
mail_auth_expect( 'recipient@example.com' === ( $success_mail['recipient'] ?? '' ), 'successful workflow sends to the requested recipient' );
mail_auth_expect( isset( $success_store->values[ Smtp2goAuthenticationService::LAST_RESULT_OPTION ] ), 'successful workflow stores its authoritative result' );
mail_auth_expect( ! str_contains( json_encode( $success ), $active_key ), 'result payload never exposes the SMTP2GO API key' );
mail_auth_expect( ! str_contains( json_encode( $success_store->values[ Smtp2goAuthenticationService::LAST_RESULT_OPTION ] ), $active_key ), 'stored test result never exposes the SMTP2GO API key' );
mail_auth_expect( $success_service->current_status()['healthy'], 'fresh successful test marks current SMTP2GO status healthy' );
$success_store->values['wp_mail_smtp']['mail']['from_email'] = 'changed@example.com';
$changed_status = $success_service->current_status();
mail_auth_expect( ! $changed_status['healthy'] && ! $changed_status['last_result_current'], 'settings changes invalidate the displayed authentication result' );

[ $mail_failure_service ] = mail_auth_service(
    [
        'mail'    => [ 'mailer' => 'smtp2go', 'from_email' => 'admin@example.com' ],
        'smtp2go' => [ 'api_key' => $active_key ],
    ],
    static fn(): array => [
        'response' => [ 'code' => 400 ],
        'body'     => json_encode( [ 'data' => [ 'error_code' => 'E_ApiResponseCodes.ENDPOINT_PERMISSION_DENIED' ] ] ),
    ],
    static fn(): array => [ 'success' => false, 'error' => 'Provider rejected the message.' ]
);
$mail_failure = $mail_failure_service->run( 'recipient@example.com' );
mail_auth_expect( ! $mail_failure['success'] && 'failed' === $mail_failure['steps'][2]['status'], 'delivery failure fails the final stage after valid settings and key' );

echo "\n{$passes} passed, " . count( $failures ) . " failed\n";
exit( [] === $failures ? 0 : 1 );
