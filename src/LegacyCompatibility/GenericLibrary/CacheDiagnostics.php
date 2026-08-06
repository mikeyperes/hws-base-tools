<?php

namespace HWS\BaseTools\LegacyCompatibility\GenericLibrary;

/** Legacy cache diagnostics backed by LiteSpeed's effective configuration API. */
final class CacheDiagnostics {
    private LiteSpeedConfigurationReader $litespeed;

    /** @var callable|null */
    private mixed $redis_factory;

    /** @var callable|null */
    private mixed $extension_checker;

    /** @var callable|null */
    private mixed $plugin_checker;

    public function __construct(
        ?LiteSpeedConfigurationReader $litespeed = null,
        ?callable $redis_factory = null,
        ?callable $extension_checker = null,
        ?callable $plugin_checker = null
    ) {
        $this->litespeed         = $litespeed ?? new LiteSpeedConfigurationReader();
        $this->redis_factory     = $redis_factory;
        $this->extension_checker = $extension_checker;
        $this->plugin_checker    = $plugin_checker;
    }

    /**
     * @return array{active:bool,extension:bool,connected:bool,litespeed_enabled:bool,info:array<string,mixed>,error:string}
     */
    public function redis_status(): array {
        $result = [
            'active'            => false,
            'extension'         => false,
            'connected'         => false,
            'litespeed_enabled' => false,
            'info'              => [],
            'error'             => '',
        ];

        $result['extension'] = $this->extension_loaded( 'redis' );
        if ( ! $result['extension'] ) {
            $result['error'] = 'Redis PHP extension not installed';

            return $result;
        }

        $settings = $this->litespeed->read_many(
            [
                'object'         => false,
                'object-kind'    => false,
                'object-host'    => '',
                'object-port'    => 6379,
                'object-db_id'   => 0,
                'object-user'    => '',
                'object-pswd'    => '',
            ]
        );

        $host     = trim( (string) $settings['object-host'] );
        $password = (string) $settings['object-pswd'];
        $username = (string) $settings['object-user'];
        $database = (int) $settings['object-db_id'];

        $result['litespeed_enabled'] = (bool) $settings['object']
            && (bool) $settings['object-kind']
            && '' !== $host;

        $host           = '' !== $host ? $host : '127.0.0.1';
        $is_unix_socket = str_starts_with( $host, '/' ) || str_starts_with( $host, 'unix://' );
        $configured_port = (int) $settings['object-port'];
        $port           = $is_unix_socket ? 0 : ( $configured_port > 0 ? $configured_port : 6379 );
        $target         = $is_unix_socket ? $host : "{$host}:{$port}";

        try {
            $redis = $this->redis();
            if ( ! is_object( $redis ) || ! @$redis->connect( $host, $port, 2.0 ) ) {
                $result['error'] = "Connection refused ({$target})";

                return $result;
            }

            if ( '' !== $password ) {
                $credentials = '' !== $username ? [ $username, $password ] : $password;
                if ( false === $redis->auth( $credentials ) ) {
                    $result['error'] = 'Redis authentication failed';

                    return $result;
                }
            }

            if ( $database > 0 && false === $redis->select( $database ) ) {
                $result['error'] = 'Redis database selection failed';

                return $result;
            }

            if ( method_exists( $redis, 'setOption' ) && defined( '\\Redis::OPT_READ_TIMEOUT' ) ) {
                $redis->setOption( \Redis::OPT_READ_TIMEOUT, 2 );
            }

            $pong = $redis->rawCommand( 'PING' );
            if ( ! in_array( $pong, [ 'PONG', '+PONG', true ], true ) ) {
                $result['error'] = 'Redis PING failed';

                return $result;
            }

            $result['connected'] = true;
            $info                = @$redis->info();

            if ( is_array( $info ) ) {
                $hits   = (int) ( $info['keyspace_hits'] ?? 0 );
                $misses = (int) ( $info['keyspace_misses'] ?? 0 );
                $total  = $hits + $misses;

                $result['info'] = [
                    'version'     => $info['redis_version'] ?? 'unknown',
                    'port'        => $info['tcp_port'] ?? $port,
                    'host'        => $host,
                    'db_index'    => $database,
                    'used_memory' => $info['used_memory_human'] ?? '0B',
                    'peak_memory' => $info['used_memory_peak_human'] ?? '0B',
                    'uptime_days' => isset( $info['uptime_in_seconds'] ) ? round( (int) $info['uptime_in_seconds'] / 86400, 1 ) : 0,
                    'total_keys'  => method_exists( $redis, 'dbSize' ) ? (int) ( @$redis->dbSize() ?: 0 ) : 0,
                    'hit_rate'    => $total > 0 ? round( ( $hits / $total ) * 100, 1 ) . '%' : 'N/A',
                ];
            }

            if ( method_exists( $redis, 'close' ) ) {
                @$redis->close();
            }
        } catch ( \Throwable ) {
            $result['error'] = 'Redis connection or command failed.';

            return $result;
        }

        $result['active'] = $result['connected'] && $result['litespeed_enabled'];

        return $result;
    }

    /** @return array<string,mixed>|false */
    public function litespeed_info(): array|false {
        if ( ! $this->plugin_active( 'litespeed-cache/litespeed-cache.php' ) ) {
            return false;
        }

        $option = fn( string $id, mixed $default = false ): mixed => $this->litespeed->read( $id, $default );

        return [
            'cache_enabled'     => (bool) $option( 'cache' ),
            'cache_private'     => (bool) $option( 'cache-priv' ),
            'cache_browser'     => (bool) $option( 'cache-browser' ),
            'cache_mobile'      => (bool) $option( 'cache-mobile' ),
            'cache_rest'        => (bool) $option( 'cache-rest' ),
            'cache_ttl_public'  => (int) $option( 'cache-ttl_pub', 0 ),
            'cache_ttl_browser' => (int) $option( 'cache-ttl_browser', 0 ),
            'css_minify'        => (bool) $option( 'optm-css_min' ),
            'css_combine'       => (bool) $option( 'optm-css_comb' ),
            'css_async'         => (bool) $option( 'optm-css_async' ),
            'css_font_display'  => $option( 'optm-css_font_display' ),
            'js_minify'         => (bool) $option( 'optm-js_min' ),
            'js_combine'        => (bool) $option( 'optm-js_comb' ),
            'js_defer'          => $option( 'optm-js_defer' ),
            'object_enabled'    => (bool) $option( 'object' ),
            'object_kind'       => $option( 'object-kind' ) ? 'Redis' : 'Memcached',
            'object_host'       => $option( 'object-host', '' ),
            'object_port'       => (int) $option( 'object-port', 0 ),
            'object_db_id'      => (int) $option( 'object-db_id', 0 ),
            'object_persistent' => (bool) $option( 'object-persistent' ),
            'object_admin'      => (bool) $option( 'object-admin' ),
            'object_transients' => (bool) $option( 'object-transients' ),
        ];
    }

    private function extension_loaded( string $extension ): bool {
        return is_callable( $this->extension_checker )
            ? (bool) call_user_func( $this->extension_checker, $extension )
            : extension_loaded( $extension );
    }

    private function plugin_active( string $plugin ): bool {
        if ( is_callable( $this->plugin_checker ) ) {
            return (bool) call_user_func( $this->plugin_checker, $plugin );
        }

        return function_exists( 'is_plugin_active' ) && is_plugin_active( $plugin );
    }

    private function redis(): object {
        if ( is_callable( $this->redis_factory ) ) {
            $redis = call_user_func( $this->redis_factory );

            if ( is_object( $redis ) ) {
                return $redis;
            }

            throw new \RuntimeException( 'Redis client factory did not return an object.' );
        }

        return new \Redis();
    }
}
