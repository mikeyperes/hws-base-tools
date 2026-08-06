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
        $this->load_brand_dependencies();
        if ( ! function_exists( '\hws_base_tools\hws_create_letter_site_icon' ) ) {
            return [ 'success' => false, 'message' => 'The HWS Site Icon generator is unavailable.', 'data' => [] ];
        }

        $path = ABSPATH . 'favicon.ico';
        if ( is_file( $path ) ) {
            wp_delete_file( $path );
            if ( is_file( $path ) ) {
                return [ 'success' => false, 'message' => 'The existing root favicon could not be removed.', 'data' => [ 'path' => $path ] ];
            }
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
