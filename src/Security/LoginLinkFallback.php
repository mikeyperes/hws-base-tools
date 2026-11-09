<?php

namespace HWS\BaseTools\Security;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Answers a one-click login link (Code's WP Toolkit login) whose temporary
 * mu-plugin is gone because the link expired or was already used. A valid link
 * is handled by that mu-plugin on `init` and exits before admin-ajax dispatch,
 * so this only runs when nothing else recognized the token. Without it
 * WordPress prints a bare "0".
 */
final class LoginLinkFallback implements ModuleInterface {
    public const ACTION = 'hws_auto_login';

    public function register(): void {
        add_action( 'wp_ajax_nopriv_' . self::ACTION, [ self::class, 'respond' ] );
        add_action( 'wp_ajax_' . self::ACTION, [ self::class, 'respond' ] );
        add_filter( 'login_message', [ self::class, 'expiredNotice' ] );
    }

    public static function respond(): void {
        nocache_headers();

        $target = admin_url();
        $requested = isset( $_GET['redirect_to'] ) ? wp_unslash( $_GET['redirect_to'] ) : '';
        if ( is_string( $requested ) ) {
            $validated = wp_validate_redirect( $requested, '' );
            $host = wp_parse_url( $validated, PHP_URL_HOST );
            if ( $validated !== '' && ( ! $host || strcasecmp( $host, wp_parse_url( home_url(), PHP_URL_HOST ) ) === 0 ) ) {
                $target = $validated;
            }
        }

        if ( is_user_logged_in() && current_user_can( 'read' ) ) {
            wp_safe_redirect( $target );
            exit;
        }

        // wp_login_url applies the site's masked-login filter and preserves the target.
        wp_safe_redirect( add_query_arg( 'hws_login_expired', '1', wp_login_url( $target ) ) );
        exit;
    }

    public static function expiredNotice( string $message ): string {
        if ( isset( $_GET['hws_login_expired'] ) && $_GET['hws_login_expired'] === '1' ) {
            $message .= '<p class="message">'
                . esc_html__( 'This login link has expired or was already used. Sign in to continue.', 'hws-base-tools' )
                . '</p>';
        }

        return $message;
    }
}
