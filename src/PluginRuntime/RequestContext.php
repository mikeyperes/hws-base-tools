<?php

namespace HWS\BaseTools\PluginRuntime;

final class RequestContext {
    public static function request_value( string $key ): string {
        if ( ! isset( $_REQUEST[ $key ] ) || is_array( $_REQUEST[ $key ] ) ) {
            return '';
        }

        $value = (string) $_REQUEST[ $key ];
        $value = function_exists( 'wp_unslash' ) ? wp_unslash( $value ) : stripslashes( $value );

        if ( function_exists( 'sanitize_key' ) ) {
            return sanitize_key( $value );
        }

        return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) );
    }

    public static function ajax_action(): string {
        $doing_ajax = function_exists( 'wp_doing_ajax' )
            ? wp_doing_ajax()
            : defined( 'DOING_AJAX' ) && DOING_AJAX;

        return $doing_ajax ? self::request_value( 'action' ) : '';
    }

    public static function dashboard_tab(): string {
        return self::request_value( 'tab' );
    }

    public static function is_dashboard_page(): bool {
        return function_exists( 'is_admin' )
            && is_admin()
            && PluginMetadata::ADMIN_PAGE_SLUG === self::request_value( 'page' );
    }

    public static function is_dashboard_ajax(): bool {
        $action = self::ajax_action();

        if ( '' === $action ) {
            return false;
        }

        return str_starts_with( $action, 'hws_' )
            || str_starts_with( $action, 'hws_base_tools_' )
            || in_array( $action, [ 'modify_wp_config_constants', 'delete_debug_log', 'delete_error_log' ], true );
    }
}
