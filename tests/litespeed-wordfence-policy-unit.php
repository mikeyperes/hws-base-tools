<?php

declare(strict_types=1);

const ABSPATH = '/tmp/hws-policy-test-wordpress/';
const WP_PLUGIN_DIR = '/tmp/hws-policy-test-wordpress/wp-content/plugins';
const DAY_IN_SECONDS = 86400;

$root = dirname( __DIR__ );
$passed = 0;
$failed = 0;

function policy_expect( bool $condition, string $message ): void {
    global $passed, $failed;
    if ( $condition ) {
        ++$passed;
        echo "PASS {$message}\n";
        return;
    }
    ++$failed;
    echo "FAIL {$message}\n";
}

$GLOBALS['policy_options'] = [
    'active_plugins' => [ 'wordfence/wordfence.php', 'litespeed-cache/litespeed-cache.php' ],
    'admin_email'    => 'security@example.test',
];
$GLOBALS['policy_http_responses'] = [];
$GLOBALS['policy_injected_wordfence_key'] = '';

function get_plugins(): array {
    return [
        'wordfence/wordfence.php'                 => [ 'Name' => 'Wordfence', 'Version' => 'test' ],
        'litespeed-cache/litespeed-cache.php'     => [ 'Name' => 'LiteSpeed Cache', 'Version' => '7.9' ],
    ];
}

function is_plugin_active( string $plugin ): bool {
    return in_array( $plugin, (array) get_option( 'active_plugins', [] ), true );
}

function wp_normalize_path( string $path ): string {
    return str_replace( '\\', '/', $path );
}

function get_option( string $key, mixed $default = false ): mixed {
    return array_key_exists( $key, $GLOBALS['policy_options'] ) ? $GLOBALS['policy_options'][ $key ] : $default;
}

function get_site_option( string $key, mixed $default = false ): mixed {
    return get_option( $key, $default );
}

function update_option( string $key, mixed $value, mixed $autoload = null ): bool {
    $GLOBALS['policy_options'][ $key ] = $value;
    return true;
}

function sanitize_email( string $email ): string {
    return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? strtolower( $email ) : '';
}

function sanitize_key( string $value ): string {
    return (string) preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) );
}

function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
    if ( 'hws_base_tools_wordfence_license_key' === $hook ) {
        return $GLOBALS['policy_injected_wordfence_key'];
    }
    return $value;
}

function home_url( string $path = '/' ): string {
    return 'https://policy.example.test' . $path;
}

function add_query_arg( string $key, string $value, string $url ): string {
    return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . rawurlencode( $key ) . '=' . rawurlencode( $value );
}

function wp_remote_get( string $url, array $args = [] ): mixed {
    return array_shift( $GLOBALS['policy_http_responses'] );
}

function wp_remote_retrieve_headers( mixed $response ): mixed {
    return is_array( $response ) ? ( $response['headers'] ?? [] ) : [];
}

function wp_remote_retrieve_response_code( mixed $response ): int {
    return is_array( $response ) ? (int) ( $response['status'] ?? 0 ) : 0;
}

function is_wp_error( mixed $value ): bool {
    return false;
}

function do_action( string $hook, mixed ...$args ): void {}

final class wfConfig {
    public static array $values = [];
    public static array $validated = [];
    public static array $saved = [];

    public static function reset(): void {
        self::$values = [
            'apiKey'            => '',
            'keyType'           => 'free',
            'licenseType'       => 'free',
            'hasKeyConflict'    => false,
            'alertEmails'       => '',
            'touppPromptNeeded' => false,
        ];
        foreach ( [
            'alertOn_scanIssues',
            'alertOn_loginLockout',
            'alertOn_wordfenceDeactivated',
            'alertOn_wafDeactivated',
            'scheduledScansEnabled',
            'firewallEnabled',
            'loginSecurityEnabled',
            'loginSec_breachPasswds_enabled',
            'loginSec_maskLoginErrors',
            'loginSec_blockAdminReg',
            'loginSec_disableAuthorScan',
            'ssl_verify',
        ] as $key ) {
            self::$values[ $key ] = false;
        }
        self::$validated = [];
        self::$saved     = [];
    }

    public static function get( string $key, mixed $default = false ): mixed {
        return array_key_exists( $key, self::$values ) ? self::$values[ $key ] : $default;
    }

    public static function getAlertEmails(): array {
        return array_values( array_filter( preg_split( '/[\s,;]+/', (string) self::get( 'alertEmails', '' ) ) ?: [] ) );
    }

    public static function validate( array $changes ): true|array {
        self::$validated[] = $changes;
        if ( isset( $changes['apiKey'] ) && ! preg_match( '/^[a-f0-9]+$/i', (string) $changes['apiKey'] ) ) {
            return [ [ 'option' => 'apiKey', 'error' => 'invalid' ] ];
        }
        return true;
    }

    public static function clean( array $changes ): array {
        return $changes;
    }

    public static function save( array $changes ): void {
        self::$saved[] = $changes;
        foreach ( $changes as $key => $value ) {
            if ( 'wafStatus' === $key ) {
                wfFirewall::$mode = (string) $value;
                continue;
            }
            self::$values[ $key ] = $value;
        }
        if ( isset( $changes['apiKey'] ) ) {
            self::$values['keyType']       = 'free';
            self::$values['licenseType']   = 'free';
            self::$values['hasKeyConflict']= false;
        }
    }
}

final class wfFirewall {
    public static string $mode = 'disabled';
    public static string $protection = 'basic';
    public static bool $configValid = true;
    public static bool $subdirectory = false;

    public function firewallMode(): string { return self::$mode; }
    public function protectionMode(): string { return self::$protection; }
    public function testConfig(): bool { return self::$configValid; }
    public function isSubDirectoryInstallation(): bool { return self::$subdirectory; }
}

final class wfOnboardingController {
    public static int $migrations = 0;

    public static function shouldShowAttempt3(): bool {
        return '' === trim( (string) wfConfig::get( 'apiKey', '' ) );
    }

    public static function shouldShowAnyAttempt(): bool {
        return self::shouldShowAttempt3();
    }

    public static function migrateOnboarding(): void {
        ++self::$migrations;
    }
}

require_once $root . '/lib/hexa-wordpress-plugin-core/src/LiteSpeedCache/SettingDefinition.php';
require_once $root . '/lib/hexa-wordpress-plugin-core/src/LiteSpeedCache/Profile.php';
require_once $root . '/lib/hexa-wordpress-plugin-core/src/PluginProvisioning/PluginProvisioner.php';
require_once $root . '/src/PluginRuntime/PluginMetadata.php';
require_once $root . '/src/LiteSpeed/LiteSpeedProfileRegistry.php';
require_once $root . '/src/LiteSpeed/LiteSpeedTaskRunner.php';
require_once $root . '/src/Security/WordfencePolicyService.php';

use HWS\BaseTools\LiteSpeed\LiteSpeedProfileRegistry;
use HWS\BaseTools\LiteSpeed\LiteSpeedTaskRunner;
use HWS\BaseTools\Security\WordfencePolicyService;

$profiles = LiteSpeedProfileRegistry::definitions();
$safe_settings = array_column( $profiles['safe_baseline']['settings'], null, 'id' );
policy_expect( 'cache-ttl_pub' === $safe_settings['public_ttl']['option_name'], 'LiteSpeed profiles declare official option IDs instead of raw database row names' );
policy_expect( [ '404 3600', '500 0' ] === $safe_settings['status_ttl']['expected'] && 'array' === $safe_settings['status_ttl']['cast'], 'LiteSpeed line-list settings use the effective API array type' );
$task_source = (string) file_get_contents( $root . '/src/LiteSpeed/LiteSpeedTaskRunner.php' );
policy_expect(
    str_contains( $task_source, 'Hexa\\PluginCore\\LiteSpeedCache\\LiteSpeedCacheService' )
    && ! is_file( $root . '/src/LiteSpeed/LiteSpeedConfigurationService.php' ),
    'HWS keeps profiles and orchestration while Core owns the generic LiteSpeed engine and official Conf adapter'
);

$GLOBALS['policy_http_responses'] = [
    [ 'status' => 200, 'headers' => [ 'x-litespeed-cache' => 'miss' ] ],
    [ 'status' => 200, 'headers' => [ 'x-litespeed-cache' => 'hit' ] ],
];
$cache_verified = LiteSpeedTaskRunner::verify_public_cache();
policy_expect( $cache_verified['success'] && $cache_verified['acceptable_http_status'] && $cache_verified['meaningful_litespeed_header'], 'public cache verification requires a healthy response and recognized LiteSpeed hit/miss signal' );

$GLOBALS['policy_http_responses'] = [
    [ 'status' => 200, 'headers' => [ 'cf-cache-status' => 'DYNAMIC' ] ],
    [ 'status' => 200, 'headers' => [ 'via' => 'test-proxy' ] ],
];
$proxy_ambiguous = LiteSpeedTaskRunner::verify_public_cache();
policy_expect( ! $proxy_ambiguous['success'] && $proxy_ambiguous['proxy_ambiguity'], 'healthy proxy responses without LiteSpeed headers remain explicitly ambiguous and do not pass' );

$GLOBALS['policy_http_responses'] = [
    [ 'status' => 503, 'headers' => [ 'x-litespeed-cache' => 'hit' ] ],
    [ 'status' => 503, 'headers' => [ 'x-litespeed-cache' => 'hit' ] ],
];
$bad_http = LiteSpeedTaskRunner::verify_public_cache();
policy_expect( ! $bad_http['success'] && ! $bad_http['acceptable_http_status'], 'LiteSpeed headers cannot mask an unacceptable public HTTP status' );

wfConfig::reset();
wfFirewall::$mode = 'disabled';
wfFirewall::$protection = 'basic';
wfOnboardingController::$migrations = 0;
$GLOBALS['policy_injected_wordfence_key'] = 'abc123'; // Deliberately short synthetic value; the stub treats it as an accepted per-site key.
$wordfence = new WordfencePolicyService();
$wordfence_apply = $wordfence->apply();
policy_expect( $wordfence_apply['success'], 'Wordfence applies and verifies the explicit WordPress-level baseline with a valid site license' );
policy_expect( ! empty( wfConfig::$validated ) && ! empty( wfConfig::$saved ), 'Wordfence routes baseline and license changes through official validation and save methods' );
policy_expect( 1 === wfOnboardingController::$migrations, 'Wordfence uses its onboarding migration only after configuration, license, and TOS state verify' );
policy_expect( ! str_contains( json_encode( $wordfence_apply ), 'abc123' ), 'Wordfence results never expose the injected site license' );
policy_expect( 'basic' === $wordfence_apply['after']['waf']['optimization'] && $wordfence_apply['after']['waf']['optimization_review_required'], 'Wordfence reports basic WAF mode as a separate manual optimization review' );

wfConfig::$values['keyType'] = 'paid-expired';
$expired = $wordfence->status();
policy_expect( ! $expired['success'] && ! $expired['license_valid'], 'an expired Wordfence key cannot satisfy the baseline' );

wfConfig::$values['keyType'] = 'free';
wfConfig::$values['touppPromptNeeded'] = true;
$tos_pending = $wordfence->status();
policy_expect( ! $tos_pending['success'] && $tos_pending['tos_review_required'], 'pending Wordfence terms require human review and prevent a success claim' );
$migrations_before = wfOnboardingController::$migrations;
$tos_apply = $wordfence->apply();
policy_expect( ! $tos_apply['success'] && true === wfConfig::$values['touppPromptNeeded'] && $migrations_before === wfOnboardingController::$migrations, 'Wordfence never auto-accepts terms or completes onboarding while TOS review is pending' );

wfConfig::$values['touppPromptNeeded'] = false;
wfConfig::$values['loginSecurityEnabled'] = false;
$bad_configuration = $wordfence->status();
policy_expect( ! $bad_configuration['success'] && in_array( 'loginSecurityEnabled', $bad_configuration['configuration_mismatches'], true ), 'a present license cannot hide a Wordfence baseline configuration mismatch' );

$source = (string) file_get_contents( $root . '/src/Security/WordfencePolicyService.php' );
policy_expect( str_contains( $source, 'wfLicense::current()' ) && ! str_contains( $source, "wfConfig::set( 'apiKey'" ) && ! str_contains( $source, 'ajax_recordTOUPP_callback' ), 'Wordfence uses its license model without a direct key write or automated legal acceptance path' );

echo "\n{$passed} passed, {$failed} failed.\n";
exit( 0 === $failed ? 0 : 1 );
