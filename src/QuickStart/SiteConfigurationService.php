<?php

namespace HWS\BaseTools\QuickStart;

use Hexa\PluginCore\WpConfigFile\WpConfigFile;
use HWS\BaseTools\MailAuthentication\Smtp2goAuthenticationService;

final class SiteConfigurationService {
    /** @return array<string,mixed> */
    public function set_memory_limit(): array {
        $expected = [ 'WP_MEMORY_LIMIT' => '4096M', 'WP_MAX_MEMORY_LIMIT' => '4096M' ];
        $result   = WpConfigFile::modify_constants( $expected );
        $actual   = [];
        foreach ( array_keys( $expected ) as $constant ) {
            $actual[ $constant ] = $this->file_constant( $constant );
        }
        $verified = ! empty( $result['status'] ) && $expected === $actual;
        return [
            'success' => $verified,
            'message' => $verified ? 'WordPress front-end and admin memory are set to exactly 4096M.' : (string) ( $result['message'] ?? 'WordPress memory constants did not verify.' ),
            'data'    => [ 'expected' => $expected, 'actual' => $actual, 'writer' => $result ],
        ];
    }

    /** @return array<string,mixed> */
    public function disable_debug(): array {
        $result = WpConfigFile::modify_constants( [ 'WP_DEBUG' => false, 'WP_DEBUG_DISPLAY' => false, 'WP_DEBUG_LOG' => false ] );
        $values = [
            'WP_DEBUG'         => $this->file_constant( 'WP_DEBUG' ),
            'WP_DEBUG_DISPLAY' => $this->file_constant( 'WP_DEBUG_DISPLAY' ),
            'WP_DEBUG_LOG'     => $this->file_constant( 'WP_DEBUG_LOG' ),
        ];
        $verified = ! empty( $result['status'] ) && [] === array_filter( $values, static fn( mixed $value ): bool => false !== $value );
        @ini_set( 'display_errors', '0' );
        return [
            'success' => $verified,
            'message' => $verified ? 'Production debug output is disabled and verified in wp-config.php.' : (string) ( $result['message'] ?? 'Debug settings did not verify.' ),
            'data'    => [ 'values' => $values, 'writer' => $result ],
        ];
    }

    /** @return array<string,mixed> */
    public function generate_favicon(): array {
        $path          = ABSPATH . 'favicon.ico';
        $site_icon_url = function_exists( 'get_site_icon_url' ) ? trim( (string) get_site_icon_url( 512 ) ) : '';
        $root_exists   = is_file( $path ) && is_readable( $path ) && (int) filesize( $path ) > 0;

        if ( '' !== $site_icon_url || $root_exists ) {
            return [
                'success' => true,
                'message' => 'An existing favicon was found and left unchanged.',
                'data'    => [
                    'action'             => 'preserved',
                    'site_icon_present'  => '' !== $site_icon_url,
                    'site_icon_url'      => $site_icon_url,
                    'root_icon_present'  => $root_exists,
                    'favicon_url'        => $root_exists ? home_url( '/favicon.ico' ) : '',
                    'favicon_size'       => $root_exists ? (int) filesize( $path ) : 0,
                ],
            ];
        }

        $this->load_brand_dependencies();
        if ( ! function_exists( '\hws_base_tools\hws_create_letter_site_icon' ) ) {
            return [ 'success' => false, 'message' => 'The HWS Site Icon generator is unavailable.', 'data' => [] ];
        }

        $title  = sanitize_title( (string) get_bloginfo( 'name' ) );
        $letter = strtoupper( substr( $title, 0, 1 ) ?: 'H' );
        $result = \hws_base_tools\hws_create_letter_site_icon( $letter, '#111827', '#ffffff' );
        if ( is_wp_error( $result ) ) {
            return [ 'success' => false, 'message' => $result->get_error_message(), 'data' => [] ];
        }

        $attachment = (int) ( $result['attachment_id'] ?? 0 );
        $success    = $attachment > 0 && is_file( $path ) && (int) filesize( $path ) > 0;
        return [
            'success' => $success,
            'message' => $success ? 'WordPress Site Icon and root favicon.ico generated and verified.' : 'Favicon generation did not produce both required assets.',
            'data'    => [
                'action'        => 'generated',
                'letter'        => $letter,
                'attachment_id' => $attachment,
                'icon_url'      => (string) ( $result['icon_url'] ?? '' ),
                'favicon_url'   => home_url( '/favicon.ico' ),
                'favicon_size'  => is_file( $path ) ? (int) filesize( $path ) : 0,
            ],
        ];
    }

    /** @return array<string,mixed> */
    public function enable_recommended_features(): array {
        $this->load_feature_dependencies();
        if ( ! function_exists( '\hws_base_tools\hws_get_going_live_snippets' ) ) {
            return [ 'success' => false, 'message' => 'The HWS recommended feature policy is unavailable.', 'data' => [] ];
        }

        $enabled = [];
        foreach ( (array) \hws_base_tools\hws_get_going_live_snippets() as $option ) {
            $option = sanitize_key( (string) $option );
            if ( '' === $option ) {
                continue;
            }
            update_option( $option, true );
            if ( (bool) get_option( $option, false ) ) {
                $enabled[] = $option;
            }
        }
        return [ 'success' => true, 'message' => count( $enabled ) . ' recommended HWS feature(s) enabled.', 'data' => [ 'enabled' => $enabled ] ];
    }

    /** Save through WP Mail SMTP's pipeline without sending mail. */
    public function configure_smtp( array $inputs = [] ): array {
        $key = defined( 'HWS_SMTP2GO_API_KEY' ) ? trim( (string) HWS_SMTP2GO_API_KEY ) : '';
        $key = trim( (string) apply_filters( 'hws_base_tools_smtp2go_api_key', $key, home_url( '/' ) ) );
        $email = strtolower( sanitize_email( (string) ( $inputs['smtp_from_email'] ?? get_option( 'admin_email', '' ) ) ) );
        $name = sanitize_text_field( (string) ( $inputs['smtp_from_name'] ?? get_bloginfo( 'name' ) ) );
        if ( '' === $key || ! is_email( $email ) ) return [ 'success' => false, 'message' => 'A protected site-specific SMTP2GO API key and valid sender email are required.', 'data' => [ 'key_present' => '' !== $key, 'sender_valid' => (bool) is_email( $email ) ] ];
        $file = 'wp-mail-smtp/wp_mail_smtp.php';
        $status = \Hexa\PluginCore\PluginProvisioning\PluginProvisioner::plugin_status_by_file( $file );
        if ( empty( $status['installed'] ) ) {
            $installed = \Hexa\PluginCore\PluginProvisioning\PluginProvisioner::install_wordpress_org_plugin( 'wp-mail-smtp', true );
            if ( is_wp_error( $installed ) ) return [ 'success' => false, 'message' => 'WP Mail SMTP installation failed.' ];
        } elseif ( empty( $status['active'] ) ) {
            $activated = \Hexa\PluginCore\PluginProvisioning\PluginProvisioner::activate_plugin_file( $file );
            if ( is_wp_error( $activated ) ) return [ 'success' => false, 'message' => 'WP Mail SMTP activation failed.' ];
        }
        if ( ! function_exists( 'wp_mail_smtp' ) ) return [ 'success' => false, 'message' => 'WP Mail SMTP is active. Run setup again in a fresh request to load its API.' ];
        try {
            $options = wp_mail_smtp()->get_connections_manager()->get_primary_connection()->get_options();
            $options->set( [ 'mail' => [ 'mailer' => 'smtp2go', 'from_email' => $email, 'from_name' => $name, 'from_email_force' => true ], 'smtp2go' => [ 'api_key' => $key ] ], false, false );
            $saved = (string) $options->get( 'smtp2go', 'api_key' );
            $verified = 'smtp2go' === $options->get( 'mail', 'mailer' ) && $email === $options->get( 'mail', 'from_email' ) && hash_equals( $key, $saved );
            return [ 'success' => $verified, 'message' => $verified ? 'SMTP2GO settings saved and verified. Use the separate verification email to test delivery.' : 'SMTP2GO saved settings did not match.', 'data' => [ 'mailer' => 'smtp2go', 'sender' => $email, 'key_present' => '' !== $saved, 'email_sent' => false ] ];
        } catch ( \Throwable $error ) {
            return [ 'success' => false, 'message' => 'WP Mail SMTP could not save SMTP2GO configuration.', 'data' => [ 'email_sent' => false ] ];
        }
    }

    /** @return array<string,mixed> */
    public function test_smtp( string $recipient ): array {
        $recipient = sanitize_email( $recipient );
        if ( '' === $recipient || ! is_email( $recipient ) ) {
            return [ 'success' => false, 'message' => 'A valid SMTP test recipient is required.', 'data' => [] ];
        }
        $result = ( new Smtp2goAuthenticationService() )->run( $recipient );
        return [
            'success' => ! empty( $result['success'] ),
            'message' => (string) ( $result['message'] ?? 'SMTP2GO verification failed.' ),
            'data'    => $result,
        ];
    }

    /** @return array<string,mixed> */
    private function write_constants( array $constants, string $verify_constant, mixed $expected, string $success_message ): array {
        $result   = WpConfigFile::modify_constants( $constants );
        $actual   = $this->file_constant( $verify_constant );
        $verified = ! empty( $result['status'] ) && $actual === $expected;
        return [
            'success' => $verified,
            'message' => $verified ? $success_message : (string) ( $result['message'] ?? $verify_constant . ' did not verify.' ),
            'data'    => [ 'constant' => $verify_constant, 'expected' => $expected, 'actual' => $actual, 'writer' => $result ],
        ];
    }

    private function file_constant( string $constant ): mixed {
        $path = WpConfigFile::default_path();
        if ( '' === $path || ! is_readable( $path ) ) {
            return null;
        }
        $content = file_get_contents( $path );
        if ( ! is_string( $content ) ) {
            return null;
        }
        $pattern = '/define\s*\(\s*[\'\"]' . preg_quote( $constant, '/' ) . '[\'\"]\s*,\s*(true|false|[\'\"](?:\\\\.|[^\'\"])*[\'\"]|[-+]?\d+(?:\.\d+)?)\s*\)\s*;/i';
        if ( ! preg_match( $pattern, $content, $matches ) ) {
            return null;
        }
        $raw = trim( (string) $matches[1] );
        if ( 0 === strcasecmp( $raw, 'true' ) ) {
            return true;
        }
        if ( 0 === strcasecmp( $raw, 'false' ) ) {
            return false;
        }
        if ( ( str_starts_with( $raw, "'" ) && str_ends_with( $raw, "'" ) ) || ( str_starts_with( $raw, '"' ) && str_ends_with( $raw, '"' ) ) ) {
            return stripcslashes( substr( $raw, 1, -1 ) );
        }
        return is_numeric( $raw ) ? $raw + 0 : $raw;
    }

    private function load_brand_dependencies(): void {
        if ( defined( 'HWS_BASE_TOOLS_DIR' ) ) {
            $file = HWS_BASE_TOOLS_DIR . '/settings-dashboard.php';
            if ( is_readable( $file ) ) {
                require_once $file;
            }
        }
    }

    private function load_feature_dependencies(): void {
        if ( ! defined( 'HWS_BASE_TOOLS_DIR' ) ) {
            return;
        }
        foreach ( [ 'generic-functions.php', 'settings-dashboard.php' ] as $relative ) {
            $file = HWS_BASE_TOOLS_DIR . '/' . $relative;
            if ( is_readable( $file ) ) {
                require_once $file;
            }
        }
    }
}
