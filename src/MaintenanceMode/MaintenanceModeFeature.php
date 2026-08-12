<?php

declare( strict_types=1 );

namespace HWS\BaseTools\MaintenanceMode;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

defined( 'ABSPATH' ) || exit;

final class MaintenanceModeFeature implements ModuleInterface {
    public function register(): void {
        add_action( 'template_redirect', [ self::class, 'render_frontend' ], -PHP_INT_MAX );
        add_filter( 'rest_pre_dispatch', [ self::class, 'protect_rest_api' ], -PHP_INT_MAX, 3 );
        add_filter( 'rest_request_before_callbacks', [ self::class, 'protect_rest_callbacks' ], -PHP_INT_MAX, 3 );
        add_filter( 'xmlrpc_enabled', [ self::class, 'xmlrpc_enabled' ] );
        add_filter( 'robots_txt', [ self::class, 'robots_txt' ], 100, 2 );
        add_action( 'wp_ajax_hws_maintenance_process', [ MaintenanceModeAdmin::class, 'handle_process' ] );
        add_action( 'wp_ajax_hws_maintenance_select_template', [ MaintenanceModeAdmin::class, 'handle_template_selection' ] );
    }

    public static function render_frontend(): void {
        if ( ! self::should_intercept() ) {
            return;
        }

        if ( function_exists( 'nocache_headers' ) ) {
            nocache_headers();
        }
        if ( function_exists( 'status_header' ) ) {
            status_header( 503 );
        }
        if ( ! headers_sent() ) {
            header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset', 'UTF-8' ) );
            header( 'Retry-After: 3600' );
            header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
        }

        echo MaintenanceTemplateRenderer::document( MaintenanceSettings::selected_template() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    public static function should_intercept(): bool {
        if ( ! MaintenanceSettings::enabled() || self::can_bypass() ) {
            return false;
        }
        if ( function_exists( 'is_admin' ) && is_admin() ) {
            return false;
        }
        if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
            return false;
        }
        if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
            return false;
        }
        return ! ( defined( 'WP_CLI' ) && WP_CLI );
    }

    public static function can_bypass(): bool {
        return function_exists( 'is_user_logged_in' )
            && is_user_logged_in()
            && function_exists( 'current_user_can' )
            && current_user_can( 'manage_options' );
    }

    public static function protect_rest_api( mixed $result, mixed $server = null, mixed $request = null ): mixed {
        $error = self::rest_error();
        if ( null !== $error ) {
            return $error;
        }
        return $result;
    }

    public static function protect_rest_callbacks( mixed $response, mixed $handler = null, mixed $request = null ): mixed {
        $error = self::rest_error();
        return null !== $error ? $error : $response;
    }

    public static function xmlrpc_enabled( bool $enabled ): bool {
        return MaintenanceSettings::enabled() && ! self::can_bypass() ? false : $enabled;
    }

    public static function robots_txt( string $output, bool $public ): string {
        return MaintenanceSettings::enabled() ? "User-agent: *\nDisallow: /\n" : $output;
    }

    private static function rest_error(): mixed {
        if ( ! MaintenanceSettings::enabled() || self::can_bypass() || ! class_exists( '\WP_Error' ) ) {
            return null;
        }
        return new \WP_Error(
            'hws_maintenance_mode',
            'The site is temporarily unavailable while maintenance is in progress.',
            [ 'status' => 503 ]
        );
    }
}
