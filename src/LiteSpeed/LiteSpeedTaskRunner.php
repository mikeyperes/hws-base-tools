<?php

namespace HWS\BaseTools\LiteSpeed;

use Hexa\PluginCore\LiteSpeedCache\LiteSpeedCacheService;
use Hexa\PluginCore\PluginProvisioning\PluginProvisioner;
use HWS\BaseTools\PluginRuntime\PluginMetadata;

final class LiteSpeedTaskRunner {
    public const PLUGIN_FILE = 'litespeed-cache/litespeed-cache.php';

    /** @param array<string,mixed> $payload
     *  @return array<string,mixed>
     */
    public static function run( array $payload ): array {
        $context = is_array( $payload['context'] ?? null ) ? $payload['context'] : [];
        $task    = self::clean_key( (string) ( $context['litespeed_task'] ?? '' ) );
        $profile = self::clean_key( (string) ( $context['litespeed_profile'] ?? LiteSpeedProfileRegistry::DEFAULT_PROFILE ) );
        $group   = self::clean_key( (string) ( $context['settings_group'] ?? '' ) );

        return match ( $task ) {
            'provision'          => self::provision(),
            'environment'        => self::environment(),
            'apply_cache',
            'apply_purge',
            'apply_browser',
            'apply_optimization',
            'apply_media',
            'apply_crawler'      => self::apply_profile( $profile, $group ),
            'configure_redis'    => self::configure_redis(),
            'verify_redis'       => self::verify_redis(),
            'audit'              => self::audit_profile( $profile ),
            'purge'              => self::purge(),
            'verify'             => self::verify_profile( $profile, true ),
            default              => self::result( false, 'Unknown LiteSpeed checklist task: ' . $task ),
        };
    }

    /** @return array<string,mixed> */
    public static function provision(): array {
        $before = PluginProvisioner::plugin_status_by_file( self::PLUGIN_FILE );
        if ( empty( $before['installed'] ) ) {
            $result = PluginProvisioner::install_wordpress_org_plugin( 'litespeed-cache', true );
            if ( self::is_error( $result ) ) {
                return self::result( false, $result->get_error_message(), [ 'before' => $before ] );
            }
        } elseif ( empty( $before['active'] ) ) {
            $result = PluginProvisioner::activate_plugin_file( self::PLUGIN_FILE );
            if ( self::is_error( $result ) ) {
                return self::result( false, $result->get_error_message(), [ 'before' => $before ] );
            }
        }

        $after = PluginProvisioner::plugin_status_by_file( self::PLUGIN_FILE );
        return self::result(
            ! empty( $after['active'] ),
            ! empty( $after['active'] ) ? 'LiteSpeed Cache is installed and active.' : 'LiteSpeed Cache did not activate.',
            [ 'before' => $before, 'after' => $after ]
        );
    }

    /** @return array<string,mixed> */
    public static function environment(): array {
        $plugin   = PluginProvisioner::plugin_status_by_file( self::PLUGIN_FILE );
        $software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( (string) $_SERVER['SERVER_SOFTWARE'] ) : '';
        $sapi     = PHP_SAPI;
        $server_litespeed = false !== stripos( $software, 'litespeed' ) || false !== stripos( $sapi, 'litespeed' );
        $redis_extension  = extension_loaded( 'redis' );
        $memcached_extension = extension_loaded( 'memcached' );
        $success = ! empty( $plugin['active'] ) && $server_litespeed;

        return self::result(
            $success,
            $success ? 'LiteSpeed plugin and server environment are ready.' : 'LiteSpeed is not fully active at both the plugin and server layers.',
            [
                'plugin'              => $plugin,
                'server_software'     => $software,
                'php_sapi'            => $sapi,
                'litespeed_server'    => $server_litespeed,
                'lscache_constant'    => defined( 'LSCWP_V' ),
                'redis_extension'     => $redis_extension,
                'memcached_extension' => $memcached_extension,
            ]
        );
    }

    /** @return array<string,mixed> */
    public static function audit_profile( string $profile_id ): array {
        $profile = LiteSpeedProfileRegistry::profile( $profile_id );
        $audit   = ( new LiteSpeedCacheService( $profile ) )->audit();
        return self::service_result( $audit, $audit['success'] ? $profile->label() . ' matches.' : $profile->label() . ' has ' . (int) $audit['mismatched'] . ' difference(s).' );
    }

    /** @return array<string,mixed> */
    public static function apply_profile( string $profile_id, string $group = '' ): array {
        $plugin = PluginProvisioner::plugin_status_by_file( self::PLUGIN_FILE );
        if ( empty( $plugin['active'] ) ) {
            return self::result( false, 'Install and activate LiteSpeed Cache before applying a profile.', [ 'plugin' => $plugin ] );
        }

        $profile = LiteSpeedProfileRegistry::profile( $profile_id, $group );
        if ( [] === $profile->settings() ) {
            return self::result( false, 'No LiteSpeed settings are registered for this profile group.', [ 'profile' => $profile_id, 'group' => $group ] );
        }

        $result = ( new LiteSpeedCacheService( $profile ) )->apply();
        if ( ! empty( $result['success'] ) ) {
            update_option( 'hws_litespeed_active_profile', $profile_id, false );
            do_action( 'litespeed_purge_all' );
        }

        $label = '' !== $group ? ucwords( str_replace( '_', ' ', $group ) ) : $profile->label();
        return self::service_result(
            $result,
            ! empty( $result['success'] )
                ? $label . ' settings applied and verified.'
                : $label . ' settings did not fully verify.'
        );
    }

    /** @return array<string,mixed> */
    public static function verify_profile( string $profile_id, bool $check_headers = false ): array {
        $profile = LiteSpeedProfileRegistry::profile( $profile_id );
        $result  = ( new LiteSpeedCacheService( $profile ) )->verify();
        $plugin  = PluginProvisioner::plugin_status_by_file( self::PLUGIN_FILE );
        $headers = $check_headers ? self::verify_public_cache() : [];
        $success = ! empty( $result['success'] )
            && ! empty( $plugin['active'] )
            && ( ! $check_headers || ! empty( $headers['success'] ) );

        $result['success'] = $success;
        $result['plugin']  = $plugin;
        $result['public_cache'] = $headers;
        $message = $success
            ? $profile->label() . ' settings and LiteSpeed activation verified.'
            : $profile->label() . ' verification found differences or an inactive plugin.';
        if ( $check_headers && empty( $headers['success'] ) ) {
            if ( empty( $headers['acceptable_http_status'] ) ) {
                $message .= ' The public cache probe did not return an acceptable HTTP status.';
            } elseif ( ! empty( $headers['proxy_ambiguity'] ) ) {
                $message .= ' The public response was healthy, but a proxy may have stripped LiteSpeed cache headers; cache verification remains incomplete.';
            } else {
                $message .= ' The public response did not contain a meaningful LiteSpeed cache signal.';
            }
        }
        return self::service_result( $result, $message );
    }

    /** @return array<string,mixed> */
    public static function configure_redis(): array {
        if ( ! extension_loaded( 'redis' ) ) {
            return self::result(
                true,
                'Redis PHP support is unavailable, so LiteSpeed object cache was left unchanged for safety.',
                [ 'configured' => false, 'reason' => 'redis_extension_missing', 'review_required' => true ]
            );
        }

        self::load_cleanup_service();
        if ( ! function_exists( '\hws_base_tools\hws_litespeed_redis_service' ) ) {
            return self::result( false, 'The HWS LiteSpeed Redis adapter is unavailable.' );
        }

        $service = \hws_base_tools\hws_litespeed_redis_service();
        $before  = $service->status();
        $applied = $service->enable();
        $after   = is_array( $applied['after'] ?? null ) ? $applied['after'] : $service->status();

        return self::result(
            ! empty( $applied['success'] ),
            (string) ( $applied['message'] ?? ( ! empty( $applied['success'] ) ? 'Redis object cache was configured.' : 'Redis object cache did not configure.' ) ),
            [
                'before'               => $before,
                'after'                => $after,
                'configured'           => ! empty( $applied['configured'] ),
                'active'               => ! empty( $applied['active'] ),
                'requires_new_request' => ! empty( $applied['requires_new_request'] ),
                'log'                  => (array) ( $applied['log'] ?? [] ),
            ]
        );
    }

    /** @return array<string,mixed> */
    public static function verify_redis(): array {
        self::load_cleanup_service();
        if ( ! function_exists( '\hws_base_tools\hws_litespeed_redis_service' ) ) {
            return self::result( false, 'The HWS LiteSpeed Redis adapter is unavailable.' );
        }

        $status = \hws_base_tools\hws_litespeed_redis_service()->status();
        return self::result(
            ! empty( $status['active'] ),
            (string) ( $status['message'] ?? 'LiteSpeed Redis verification did not return a status.' ),
            $status
        );
    }

    /** @return array<string,mixed> */
    public static function purge(): array {
        do_action( 'litespeed_purge_all' );
        return self::result( true, 'LiteSpeed full-purge request sent.' );
    }

    /** @return array<string,mixed> */
    public static function verify_public_cache(): array {
        $url      = add_query_arg( 'hws_cache_verify', '1', home_url( '/' ) );
        $attempts = [];
        foreach ( [ 1, 2 ] as $attempt ) {
            $response = wp_remote_get( $url, [ 'timeout' => 15, 'redirection' => 5, 'user-agent' => 'HWS-LiteSpeed-Check/' . PluginMetadata::VERSION ] );
            if ( is_wp_error( $response ) ) {
                $attempts[] = [ 'attempt' => $attempt, 'success' => false, 'message' => $response->get_error_message() ];
                continue;
            }
            $headers = wp_remote_retrieve_headers( $response );
            $status  = (int) wp_remote_retrieve_response_code( $response );
            $cache   = self::header_value( $headers, 'x-litespeed-cache' );
            $control = self::header_value( $headers, 'x-litespeed-cache-control' );
            $tag     = self::header_value( $headers, 'x-litespeed-tag' );
            $cache_signal   = self::cache_status_signal( $cache );
            $control_signal = self::public_control_signal( $control );
            $meaningful     = $cache_signal || ( $control_signal && '' !== $tag );
            $proxy_headers  = self::present_headers( $headers, [ 'age', 'cf-cache-status', 'via', 'x-cache', 'x-proxy-cache', 'x-varnish' ] );
            $attempts[] = [
                'attempt'                  => $attempt,
                'success'                  => true,
                'status_code'              => $status,
                'acceptable_status'        => $status >= 200 && $status < 400,
                'x_litespeed_cache'        => $cache,
                'x_litespeed_cache_control'=> $control,
                'x_litespeed_tag_present'  => '' !== $tag,
                'meaningful_cache_signal'  => $meaningful,
                'proxy_headers_present'    => $proxy_headers,
            ];
        }

        $detected   = false;
        $meaningful = false;
        $acceptable = false;
        $proxy      = [];
        foreach ( $attempts as $attempt ) {
            if ( '' !== (string) ( $attempt['x_litespeed_cache'] ?? '' ) || '' !== (string) ( $attempt['x_litespeed_cache_control'] ?? '' ) || ! empty( $attempt['x_litespeed_tag_present'] ) ) {
                $detected = true;
            }
            $acceptable = $acceptable || ! empty( $attempt['acceptable_status'] );
            $meaningful = $meaningful || ( ! empty( $attempt['acceptable_status'] ) && ! empty( $attempt['meaningful_cache_signal'] ) );
            $proxy      = array_merge( $proxy, (array) ( $attempt['proxy_headers_present'] ?? [] ) );
        }
        $proxy = array_values( array_unique( array_map( 'strval', $proxy ) ) );
        return [
            'success'                     => $acceptable && $meaningful,
            'url'                         => $url,
            'acceptable_http_status'      => $acceptable,
            'litespeed_header_detected'   => $detected,
            'meaningful_litespeed_header' => $meaningful,
            'proxy_ambiguity'             => $acceptable && ! $meaningful && ! $detected,
            'proxy_headers_present'       => $proxy,
            'attempts'                    => $attempts,
        ];
    }

    private static function cache_status_signal( string $value ): bool {
        $tokens = preg_split( '/[\s,;]+/', strtolower( trim( $value ) ) ) ?: [];
        return in_array( 'hit', $tokens, true ) || in_array( 'miss', $tokens, true );
    }

    private static function public_control_signal( string $value ): bool {
        $tokens = preg_split( '/[\s,;=]+/', strtolower( trim( $value ) ) ) ?: [];
        return in_array( 'public', $tokens, true )
            && ! in_array( 'no-cache', $tokens, true )
            && ! in_array( 'no-store', $tokens, true );
    }

    /** @param list<string> $names
     *  @return list<string>
     */
    private static function present_headers( mixed $headers, array $names ): array {
        return array_values(
            array_filter(
                $names,
                static fn( string $name ): bool => '' !== self::header_value( $headers, $name )
            )
        );
    }

    private static function header_value( mixed $headers, string $name ): string {
        if ( is_object( $headers ) && method_exists( $headers, 'offsetGet' ) ) {
            return trim( (string) $headers->offsetGet( $name ) );
        }
        if ( is_array( $headers ) ) {
            return trim( (string) ( $headers[ $name ] ?? $headers[ strtolower( $name ) ] ?? '' ) );
        }
        return '';
    }

    private static function load_cleanup_service(): void {
        if ( defined( 'HWS_BASE_TOOLS_DIR' ) ) {
            $file = HWS_BASE_TOOLS_DIR . '/settings-dashboard-cleanup.php';
            if ( is_readable( $file ) ) {
                require_once $file;
            }
        }
    }

    /** @return array<string,mixed> */
    private static function service_result( array $result, string $message ): array {
        return [
            'success' => ! empty( $result['success'] ),
            'message' => $message,
            'data'    => $result,
        ];
    }

    /** @return array<string,mixed> */
    private static function result( bool $success, string $message, array $data = [] ): array {
        return [ 'success' => $success, 'message' => $message, 'data' => $data ];
    }

    private static function is_error( mixed $value ): bool {
        return function_exists( 'is_wp_error' ) && is_wp_error( $value );
    }

    private static function clean_key( string $value ): string {
        return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) );
    }
}
