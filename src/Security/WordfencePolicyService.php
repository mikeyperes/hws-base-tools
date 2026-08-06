<?php

namespace HWS\BaseTools\Security;

use Hexa\PluginCore\PluginProvisioning\PluginProvisioner;

/**
 * A conservative, WordPress-level Wordfence baseline.
 *
 * License material may be injected by the server for this site only. It is
 * never returned, logged, copied from another site, or written directly with
 * wfConfig::set(). Wordfence's validated save path remains responsible for
 * checking and installing the license with its API.
 */
final class WordfencePolicyService {
    public const PLUGIN_FILE = 'wordfence/wordfence.php';

    /** @var array<string,bool> */
    private const BOOLEAN_BASELINE = [
        'alertOn_scanIssues'           => true,
        'alertOn_loginLockout'         => true,
        'alertOn_wordfenceDeactivated' => true,
        'alertOn_wafDeactivated'       => true,
        'scheduledScansEnabled'        => true,
        'firewallEnabled'              => true,
        'loginSecurityEnabled'         => true,
        'loginSec_breachPasswds_enabled' => true,
        'loginSec_maskLoginErrors'     => true,
        'loginSec_blockAdminReg'       => true,
        'loginSec_disableAuthorScan'   => true,
        'ssl_verify'                    => true,
    ];

    /** @return array<string,mixed> */
    public function status(): array {
        $plugin       = PluginProvisioner::plugin_status_by_file( self::PLUGIN_FILE );
        $api_available = ! empty( $plugin['active'] )
            && class_exists( '\\wfConfig' )
            && method_exists( '\\wfConfig', 'get' )
            && method_exists( '\\wfConfig', 'validate' )
            && method_exists( '\\wfConfig', 'save' );

        if ( ! $api_available ) {
            return [
                'success'                  => false,
                'installed'                => ! empty( $plugin['installed'] ),
                'active'                   => ! empty( $plugin['active'] ),
                'configuration_api'        => false,
                'configuration_valid'      => false,
                'configuration_mismatches' => array_keys( self::BOOLEAN_BASELINE ),
                'alert_email_set'          => false,
                'license_present'          => false,
                'license_valid'            => false,
                'license_type'             => 'unknown',
                'license_key_type'         => 'unknown',
                'license_source'           => '' !== $this->site_license_key() ? 'server_site' : 'none',
                'tos_review_required'      => false,
                'onboarding_review_required' => true,
                'waf'                      => $this->unavailable_waf_status(),
                'review_required'          => true,
            ];
        }

        $email       = $this->alert_email_status();
        $license     = $this->license_status();
        $configuration = $this->configuration_status();
        $waf         = $this->waf_status();
        $tos_review  = (bool) \wfConfig::get( 'touppPromptNeeded', false );
        $onboarding  = $this->onboarding_status();
        $success     = ! empty( $plugin['active'] )
            && ! empty( $email['valid'] )
            && ! empty( $license['valid'] )
            && ! empty( $configuration['valid'] )
            && ! empty( $waf['enabled'] )
            && ! empty( $waf['configuration_valid'] )
            && ! $tos_review;

        return [
            'success'                    => $success,
            'installed'                  => ! empty( $plugin['installed'] ),
            'active'                     => ! empty( $plugin['active'] ),
            'configuration_api'          => true,
            'configuration_valid'        => $configuration['valid'],
            'configuration_mismatches'   => $configuration['mismatches'],
            'alert_email_set'            => $email['valid'],
            'alert_email_count'          => $email['count'],
            'license_present'            => $license['present'],
            'license_valid'              => $license['valid'],
            'license_type'               => $license['type'],
            'license_key_type'           => $license['key_type'],
            'license_conflict'           => $license['conflict'],
            'license_source'             => '' !== $this->site_license_key() ? 'server_site' : ( $license['present'] ? 'existing_site' : 'none' ),
            'tos_review_required'        => $tos_review,
            'onboarding_review_required' => $onboarding['review_required'],
            'license_onboarding_required'=> $onboarding['license_required'],
            'waf'                        => $waf,
            'review_required'            => ! $success || ! empty( $waf['optimization_review_required'] ) || ! empty( $onboarding['review_required'] ),
        ];
    }

    /** @return array<string,mixed> */
    public function apply(): array {
        $before = $this->status();
        $plugin = $before;

        if ( empty( $plugin['installed'] ) ) {
            $installed = PluginProvisioner::install_wordpress_org_plugin( 'wordfence', true );
            if ( $this->is_error( $installed ) ) {
                return $this->failure( $installed, $before );
            }
        } elseif ( empty( $plugin['active'] ) ) {
            $activated = PluginProvisioner::activate_plugin_file( self::PLUGIN_FILE );
            if ( $this->is_error( $activated ) ) {
                return $this->failure( $activated, $before );
            }
        }

        if ( ! $this->configuration_api_available() ) {
            return [
                'success' => false,
                'message' => 'Wordfence is active, but its configuration API is unavailable in this request.',
                'before'  => $before,
                'after'   => $this->status(),
            ];
        }

        $configuration = $this->save_baseline();
        $license       = $this->validate_site_license();
        $interim       = $this->status();

        if ( ! empty( $configuration['success'] )
            && ! empty( $license['success'] )
            && empty( $interim['tos_review_required'] )
            && class_exists( '\\wfOnboardingController' )
            && method_exists( '\\wfOnboardingController', 'migrateOnboarding' ) ) {
            // This is Wordfence's own migration/completion path. It does not
            // record acceptance of terms or privacy policy on the user's behalf.
            \wfOnboardingController::migrateOnboarding();
        }

        $after   = $this->status();
        $success = ! empty( $configuration['success'] )
            && ! empty( $license['success'] )
            && ! empty( $after['success'] );

        if ( $success ) {
            $message = 'Wordfence WordPress-level configuration and the site license were validated.';
            if ( ! empty( $after['waf']['optimization_review_required'] ) ) {
                $message .= ' Extended WAF optimization remains a manual server review.';
            }
        } elseif ( ! empty( $after['tos_review_required'] ) ) {
            $message = 'Wordfence configuration was saved, but its current terms or privacy policy require an authorized user review.';
        } elseif ( empty( $license['success'] ) ) {
            $message = 'Wordfence configuration was saved, but a valid site license could not be verified.';
        } else {
            $message = 'Wordfence did not fully verify the required WordPress-level security baseline.';
        }

        return [
            'success'            => $success,
            'message'            => $message,
            'before'             => $before,
            'after'              => $after,
            'configuration_save' => $configuration,
            'license_validation' => $license,
        ];
    }

    /** @return array<string,mixed> */
    private function save_baseline(): array {
        $changes = self::BOOLEAN_BASELINE;
        $emails  = $this->target_alert_emails();
        if ( [] !== $emails ) {
            $changes['alertEmails'] = implode( ',', $emails );
        }

        $waf = $this->waf_status();
        if ( ! in_array( $waf['mode'], [ 'enabled', 'learning-mode' ], true ) ) {
            $changes['wafStatus']                     = 'learning-mode';
            $changes['learningModeGracePeriodEnabled'] = true;
            $changes['learningModeGracePeriod']       = gmdate( 'c', time() + ( 7 * DAY_IN_SECONDS ) );
        }

        try {
            $validation = \wfConfig::validate( $changes );
            if ( true !== $validation ) {
                return [
                    'success'         => false,
                    'validated'       => false,
                    'saved'           => false,
                    'invalid_options' => $this->validation_option_names( $validation ),
                ];
            }

            $clean = method_exists( '\\wfConfig', 'clean' ) ? \wfConfig::clean( $changes ) : $changes;
            \wfConfig::save( $clean );
            $status = $this->configuration_status();
            $email  = $this->alert_email_status();
            $waf    = $this->waf_status();
            $valid  = ! empty( $status['valid'] )
                && ! empty( $email['valid'] )
                && ! empty( $waf['enabled'] )
                && ! empty( $waf['configuration_valid'] );

            return [
                'success'         => $valid,
                'validated'       => true,
                'saved'           => true,
                'invalid_options' => [],
                'mismatches'      => $status['mismatches'],
            ];
        } catch ( \Throwable $error ) {
            return [
                'success'         => false,
                'validated'       => false,
                'saved'           => false,
                'invalid_options' => [],
                'reason'          => 'wordfence_configuration_save_failed',
            ];
        }
    }

    /** @return array<string,mixed> */
    private function validate_site_license(): array {
        $injected = $this->site_license_key();
        $stored   = trim( (string) \wfConfig::get( 'apiKey', '' ) );
        $key      = '' !== $injected ? $injected : $stored;
        $source   = '' !== $injected ? 'server_site' : ( '' !== $stored ? 'existing_site' : 'none' );

        if ( '' === $key ) {
            return [ 'success' => false, 'attempted' => false, 'source' => $source, 'reason' => 'site_license_missing' ];
        }

        try {
            $changes    = [ 'apiKey' => $key ];
            $validation = \wfConfig::validate( $changes );
            if ( true !== $validation ) {
                return [ 'success' => false, 'attempted' => true, 'source' => $source, 'reason' => 'site_license_format_invalid' ];
            }

            // wfConfig::save validates changed keys with check_api_key and pings
            // unchanged keys. It only persists keys accepted by Wordfence.
            $clean = method_exists( '\\wfConfig', 'clean' ) ? \wfConfig::clean( $changes ) : $changes;
            \wfConfig::save( $clean );

            $after   = trim( (string) \wfConfig::get( 'apiKey', '' ) );
            $license = $this->license_status();
            $same    = strlen( $after ) === strlen( $key ) && hash_equals( $after, $key );
            return [
                'success'   => $same && ! empty( $license['valid'] ),
                'attempted' => true,
                'source'    => $source,
                'reason'    => $same && ! empty( $license['valid'] ) ? 'validated' : 'license_state_invalid',
            ];
        } catch ( \Throwable $error ) {
            return [ 'success' => false, 'attempted' => true, 'source' => $source, 'reason' => 'license_validation_failed' ];
        }
    }

    /** @return array{valid:bool,mismatches:list<string>} */
    private function configuration_status(): array {
        $mismatches = [];
        foreach ( self::BOOLEAN_BASELINE as $key => $expected ) {
            if ( (bool) \wfConfig::get( $key, false ) !== $expected ) {
                $mismatches[] = $key;
            }
        }
        return [ 'valid' => [] === $mismatches, 'mismatches' => $mismatches ];
    }

    /** @return array{present:bool,valid:bool,type:string,key_type:string,conflict:bool} */
    private function license_status(): array {
        $stored   = trim( (string) \wfConfig::get( 'apiKey', '' ) );
        $present  = '' !== $stored;
        $type     = $this->safe_identifier( (string) \wfConfig::get( 'licenseType', 'free' ), [ 'free', 'premium', 'care', 'response' ], 'unknown' );
        $key_type = $this->safe_identifier( (string) \wfConfig::get( 'keyType', '' ), [ 'free', 'paid-current', 'paid-expired', 'paid-deleted' ], 'unknown' );
        $conflict = (bool) \wfConfig::get( 'hasKeyConflict', false );
        $valid    = $present && ! $conflict && in_array( $key_type, [ 'free', 'paid-current' ], true );

        // Prefer Wordfence's license model when it represents the same key.
        // During a same-request key change wfLicense may still hold its prior
        // cached object, so a non-matching model is deliberately ignored.
        if ( $present && class_exists( '\\wfLicense' ) && method_exists( '\\wfLicense', 'current' ) ) {
            try {
                $license = \wfLicense::current();
                if ( is_object( $license ) && method_exists( $license, 'getApiKey' ) ) {
                    $model_key = trim( (string) $license->getApiKey() );
                    $same_key  = strlen( $model_key ) === strlen( $stored ) && hash_equals( $model_key, $stored );
                    if ( $same_key ) {
                        if ( method_exists( $license, 'getType' ) ) {
                            $type = $this->safe_identifier( (string) $license->getType(), [ 'free', 'premium', 'care', 'response' ], $type );
                        }
                        if ( method_exists( $license, 'getKeyType' ) ) {
                            $key_type = $this->safe_identifier( (string) $license->getKeyType(), [ 'free', 'paid-current', 'paid-expired', 'paid-deleted' ], $key_type );
                        }
                        if ( method_exists( $license, 'hasConflict' ) ) {
                            $conflict = (bool) $license->hasConflict();
                        }
                        $model_valid = ! method_exists( $license, 'isValid' ) || (bool) $license->isValid();
                        $valid       = $model_valid && ! $conflict && in_array( $key_type, [ 'free', 'paid-current' ], true );
                    }
                }
            } catch ( \Throwable $error ) {
                $valid = false;
            }
        }

        return [ 'present' => $present, 'valid' => $valid, 'type' => $type, 'key_type' => $key_type, 'conflict' => $conflict ];
    }

    /** @return array{valid:bool,count:int} */
    private function alert_email_status(): array {
        $emails = $this->configured_alert_emails();
        return [ 'valid' => [] !== $emails, 'count' => count( $emails ) ];
    }

    /** @return list<string> */
    private function target_alert_emails(): array {
        $emails = $this->configured_alert_emails();
        $admin  = function_exists( 'sanitize_email' ) ? sanitize_email( (string) get_option( 'admin_email', '' ) ) : '';
        if ( '' !== $admin ) {
            $emails[] = strtolower( $admin );
        }
        return array_values( array_unique( $emails ) );
    }

    /** @return list<string> */
    private function configured_alert_emails(): array {
        $values = [];
        if ( method_exists( '\\wfConfig', 'getAlertEmails' ) ) {
            $values = (array) \wfConfig::getAlertEmails();
        } else {
            $values = preg_split( '/[\s,;]+/', (string) \wfConfig::get( 'alertEmails', '' ) ) ?: [];
        }

        $emails = [];
        foreach ( $values as $value ) {
            $email = function_exists( 'sanitize_email' ) ? sanitize_email( (string) $value ) : trim( (string) $value );
            if ( '' !== $email ) {
                $emails[] = strtolower( $email );
            }
        }
        return array_values( array_unique( $emails ) );
    }

    /** @return array<string,mixed> */
    private function waf_status(): array {
        if ( ! class_exists( '\\wfFirewall' ) ) {
            return $this->unavailable_waf_status();
        }

        try {
            $firewall      = new \wfFirewall();
            $mode          = method_exists( $firewall, 'firewallMode' ) ? (string) $firewall->firewallMode() : 'unknown';
            $protection    = method_exists( $firewall, 'protectionMode' ) ? (string) $firewall->protectionMode() : 'unknown';
            $subdirectory  = method_exists( $firewall, 'isSubDirectoryInstallation' ) && (bool) $firewall->isSubDirectoryInstallation();
            $config_valid  = ! method_exists( $firewall, 'testConfig' ) || (bool) $firewall->testConfig();
            $enabled       = in_array( $mode, [ 'enabled', 'learning-mode' ], true );
            $optimized     = 'extended' === $protection && ! $subdirectory;

            return [
                'mode'                         => $this->safe_identifier( $mode, [ 'enabled', 'learning-mode', 'disabled' ], 'unknown' ),
                'enabled'                      => $enabled,
                'configuration_valid'          => $config_valid,
                'optimization'                 => $optimized ? 'extended' : ( $subdirectory ? 'subdirectory_inherited' : 'basic' ),
                'optimization_review_required' => ! $optimized,
                'subdirectory_installation'    => $subdirectory,
            ];
        } catch ( \Throwable $error ) {
            return $this->unavailable_waf_status();
        }
    }

    /** @return array<string,mixed> */
    private function unavailable_waf_status(): array {
        return [
            'mode'                         => 'unknown',
            'enabled'                      => false,
            'configuration_valid'          => false,
            'optimization'                 => 'unknown',
            'optimization_review_required' => true,
            'subdirectory_installation'    => false,
        ];
    }

    /** @return array{review_required:bool,license_required:bool} */
    private function onboarding_status(): array {
        if ( ! class_exists( '\\wfOnboardingController' ) ) {
            return [ 'review_required' => true, 'license_required' => true ];
        }

        try {
            $license_required = method_exists( '\\wfOnboardingController', 'shouldShowAttempt3' )
                ? (bool) \wfOnboardingController::shouldShowAttempt3()
                : false;
            $review_required = method_exists( '\\wfOnboardingController', 'shouldShowAnyAttempt' )
                ? (bool) \wfOnboardingController::shouldShowAnyAttempt()
                : $license_required;
            return [ 'review_required' => $review_required, 'license_required' => $license_required ];
        } catch ( \Throwable $error ) {
            return [ 'review_required' => true, 'license_required' => true ];
        }
    }

    private function configuration_api_available(): bool {
        return class_exists( '\\wfConfig' )
            && method_exists( '\\wfConfig', 'get' )
            && method_exists( '\\wfConfig', 'validate' )
            && method_exists( '\\wfConfig', 'save' );
    }

    private function site_license_key(): string {
        $key = defined( 'HWS_WORDFENCE_LICENSE_KEY' ) ? (string) HWS_WORDFENCE_LICENSE_KEY : '';
        if ( function_exists( 'apply_filters' ) ) {
            $site = function_exists( 'home_url' ) ? (string) home_url( '/' ) : '';
            $key  = (string) apply_filters( 'hws_base_tools_wordfence_license_key', $key, $site );
        }
        return trim( $key );
    }

    /** @return list<string> */
    private function validation_option_names( mixed $validation ): array {
        $names = [];
        foreach ( is_array( $validation ) ? $validation : [] as $error ) {
            if ( is_array( $error ) && isset( $error['option'] ) ) {
                $name = preg_replace( '/[^A-Za-z0-9_.-]/', '', (string) $error['option'] );
                if ( '' !== $name ) {
                    $names[] = $name;
                }
            }
        }
        return array_values( array_unique( $names ) );
    }

    /** @param list<string> $allowed */
    private function safe_identifier( string $value, array $allowed, string $fallback ): string {
        return in_array( $value, $allowed, true ) ? $value : $fallback;
    }

    private function is_error( mixed $value ): bool {
        return function_exists( 'is_wp_error' ) && is_wp_error( $value );
    }

    /** @return array<string,mixed> */
    private function failure( mixed $error, array $before ): array {
        return [
            'success' => false,
            'message' => is_object( $error ) && method_exists( $error, 'get_error_message' ) ? $error->get_error_message() : 'Wordfence provisioning failed.',
            'before'  => $before,
            'after'   => $this->status(),
        ];
    }
}
