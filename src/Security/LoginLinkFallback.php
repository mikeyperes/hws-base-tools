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
    }

    public static function respond(): void {
        nocache_headers();

        if ( is_user_logged_in() && current_user_can( 'read' ) ) {
            wp_safe_redirect( admin_url() );
            exit;
        }

        wp_die(
            esc_html__( 'This login link has expired or was already used. Ask for a new one.', 'hws-base-tools' ),
            esc_html__( 'Login link expired', 'hws-base-tools' ),
            [
                'response'  => 410,
                'link_url'  => wp_login_url(),
                'link_text' => esc_html__( 'Go to the login page', 'hws-base-tools' ),
            ]
        );
    }
}
