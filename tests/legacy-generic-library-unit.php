<?php

declare(strict_types=1);

$root     = dirname( __DIR__ );
$failures = [];
$passes   = 0;

function legacy_generic_expect( bool $condition, string $message ): void {
    global $failures, $passes;

    if ( $condition ) {
        ++$passes;
        echo "PASS: {$message}\n";
        return;
    }

    $failures[] = $message;
    echo "FAIL: {$message}\n";
}

final class FakeLiteSpeedConfiguration {
    /** @var array<string,mixed> */
    public array $values;

    /** @var list<string> */
    public array $reads = [];

    /** @param array<string,mixed> $values */
    public function __construct( array $values ) {
        $this->values = $values;
    }

    public function has_conf( string $id ): bool {
        return array_key_exists( $id, $this->values );
    }

    public function has_network_conf( string $id ): bool {
        return false;
    }

    public function conf( string $id ): mixed {
        $this->reads[] = $id;

        return $this->values[ $id ] ?? null;
    }
}

final class FakeRedisClient {
    /** @var array{host:string,port:int,timeout:float}|null */
    public ?array $connection = null;

    public mixed $authentication = null;
    public int $database = 0;
    public bool $closed = false;

    public function connect( string $host, int $port, float $timeout ): bool {
        $this->connection = compact( 'host', 'port', 'timeout' );
        return true;
    }

    public function auth( mixed $credentials ): bool {
        $this->authentication = $credentials;
        return true;
    }

    public function select( int $database ): bool {
        $this->database = $database;
        return true;
    }

    public function rawCommand( string $command ): string {
        return 'PING' === $command ? 'PONG' : '';
    }

    /** @return array<string,mixed> */
    public function info(): array {
        return [
            'redis_version'          => '7.4.0',
            'tcp_port'               => 0,
            'used_memory_human'      => '2M',
            'used_memory_peak_human' => '3M',
            'uptime_in_seconds'      => 172800,
            'keyspace_hits'          => 9,
            'keyspace_misses'        => 1,
        ];
    }

    public function dbSize(): int {
        return 12;
    }

    public function close(): bool {
        $this->closed = true;
        return true;
    }
}

require_once $root . '/src/LegacyCompatibility/GenericLibrary/LiteSpeedConfigurationReader.php';
require_once $root . '/src/LegacyCompatibility/GenericLibrary/CacheDiagnostics.php';

use HWS\BaseTools\LegacyCompatibility\GenericLibrary\CacheDiagnostics;
use HWS\BaseTools\LegacyCompatibility\GenericLibrary\LiteSpeedConfigurationReader;

$configuration = new FakeLiteSpeedConfiguration(
    [
        'object'                 => true,
        'object-kind'            => true,
        'object-host'            => 'unix:///run/redis/redis.sock',
        'object-port'            => 6380,
        'object-db_id'           => 2,
        'object-user'            => 'cache-user',
        'object-pswd'            => 'test-secret-not-for-output',
        'object-persistent'      => true,
        'object-admin'           => false,
        'object-transients'      => true,
        'cache'                  => true,
        'cache-priv'             => false,
        'cache-browser'          => true,
        'cache-mobile'           => false,
        'cache-rest'             => true,
        'cache-ttl_pub'          => 3600,
        'cache-ttl_browser'      => 86400,
        'optm-css_min'           => true,
        'optm-css_comb'          => false,
        'optm-css_async'         => false,
        'optm-css_font_display'  => 'swap',
        'optm-js_min'            => true,
        'optm-js_comb'           => false,
        'optm-js_defer'          => 1,
    ]
);
$reader = new LiteSpeedConfigurationReader( $configuration );
$redis  = new FakeRedisClient();
$cache  = new CacheDiagnostics(
    $reader,
    static fn(): FakeRedisClient => $redis,
    static fn( string $extension ): bool => 'redis' === $extension,
    static fn( string $plugin ): bool => 'litespeed-cache/litespeed-cache.php' === $plugin
);

$redis_status = $cache->redis_status();
legacy_generic_expect( $redis_status['active'] && $redis_status['connected'] && $redis_status['litespeed_enabled'], 'Redis status combines connection health with effective LiteSpeed enablement' );
legacy_generic_expect(
    'unix:///run/redis/redis.sock' === ( $redis->connection['host'] ?? '' )
    && 0 === ( $redis->connection['port'] ?? -1 ),
    'Redis status preserves port zero for LiteSpeed Unix socket connections'
);
legacy_generic_expect( [ 'cache-user', 'test-secret-not-for-output' ] === $redis->authentication && 2 === $redis->database, 'Redis status uses the effective LiteSpeed credentials and database' );
legacy_generic_expect( ! str_contains( json_encode( $redis_status ) ?: '', 'test-secret-not-for-output' ), 'Redis status never returns the configured password' );

$litespeed_info = $cache->litespeed_info();
legacy_generic_expect(
    is_array( $litespeed_info )
    && true === $litespeed_info['cache_enabled']
    && 3600 === $litespeed_info['cache_ttl_public']
    && 'Redis' === $litespeed_info['object_kind']
    && 'swap' === $litespeed_info['css_font_display'],
    'LiteSpeed compatibility info preserves its historical result shape'
);
legacy_generic_expect( false === $reader->read( 'missing-setting', false ), 'LiteSpeed reader returns the caller fallback for an unavailable option ID' );
legacy_generic_expect(
    in_array( 'object-host', $configuration->reads, true )
    && in_array( 'cache-ttl_pub', $configuration->reads, true ),
    'LiteSpeed diagnostics read official option IDs through the configuration object'
);

$loader = (string) file_get_contents( $root . '/src/LegacyCompatibility/legacy-generic-functions.php' );
legacy_generic_expect( substr_count( $loader, "require_once __DIR__ . '/GenericLibrary/" ) === 6 && ! str_contains( $loader, 'function ' ), 'legacy generic facade is a thin six-section loader' );

$expected_functions = [
    'add_settings_menu', 'check_caching_source', 'check_cloudflare_active', 'check_imagick_available',
    'check_log_file_sizes', 'check_myisam_tables', 'check_php_handler', 'check_php_ini_status',
    'check_php_sapi_is_litespeed', 'check_php_type', 'check_php_version', 'check_plugin_auto_update_status',
    'check_plugin_status', 'check_query_monitor_status', 'check_redis_active', 'check_server_is_litespeed',
    'check_server_memory_limit', 'check_server_ram', 'check_server_specs', 'check_smtp_auth_status_and_mailer',
    'check_wordfence_notification_email', 'check_wordpress_main_email', 'check_wordpress_memory_limit',
    'check_wp_backup_status', 'check_wp_config_constant_status', 'check_wp_core_auto_update_status',
    'check_wp_debug_disabled', 'convert_to_bytes', 'convert_to_kb', 'detect_additional_wp_installs',
    'disable_litespeed_js_combine', 'display_acf_structure', 'display_check_status', 'display_cpt_structure',
    'display_precheck_result', 'does_post_type_exist', 'does_taxonomy_exist', 'does_term_exist',
    'does_user_exist', 'enable_auto_update_plugins', 'enable_auto_update_themes', 'get_database_table_prefix',
    'get_php_ini_value', 'get_smtp_sending_domain', 'get_wp_config_defined_constants', 'get_wp_version_from_file',
    'hws_check_brotli_support', 'hws_check_php_extensions', 'hws_check_redis_status',
    'hws_ct_highlight_based_on_criteria', 'hws_ct_highlight_if_essential_setting_failed',
    'hws_ct_package_constant_value_for_checks', 'hws_format_cpu_value', 'hws_format_resource_source_note',
    'hws_get_glc_settings_checks', 'hws_get_going_live_snippets', 'hws_get_litespeed_info',
    'hws_render_acf_fields_recursive', 'hws_render_instructions', 'is_acf_field_group_imported',
    'is_plugin_auto_update_enabled', 'is_theme_active', 'is_theme_auto_update_enabled',
    'modify_wp_config_constants', 'perform_php_ini_check', 'render_enable_plugin_auto_updates_button',
    'toggle_php_ini_value',
];
sort( $expected_functions );

$declared_functions = [];
$compatibility_source = '';
foreach ( glob( $root . '/src/LegacyCompatibility/GenericLibrary/generic-*.php' ) ?: [] as $section ) {
    $source                = (string) file_get_contents( $section );
    $compatibility_source .= $source;
    $tokens                = token_get_all( $source );
    $token_count           = count( $tokens );

    for ( $index = 0; $index < $token_count; ++$index ) {
        if ( ! is_array( $tokens[ $index ] ) || T_FUNCTION !== $tokens[ $index ][0] ) {
            continue;
        }
        for ( $cursor = $index + 1; $cursor < $token_count; ++$cursor ) {
            if ( '(' === $tokens[ $cursor ] ) {
                break;
            }
            if ( is_array( $tokens[ $cursor ] ) && T_STRING === $tokens[ $cursor ][0] ) {
                $declared_functions[] = $tokens[ $cursor ][1];
                break;
            }
        }
    }
}
sort( $declared_functions );
legacy_generic_expect( $expected_functions === $declared_functions, 'all 67 historical hws_base_tools functions remain declared exactly once' );
legacy_generic_expect( 1 === substr_count( $compatibility_source, 'function check_caching_source(' ), 'duplicate check_caching_source implementation is removed' );
legacy_generic_expect( ! str_contains( $compatibility_source, 'litespeed.conf.' ), 'compatibility sections contain no direct LiteSpeed option-row reads' );
legacy_generic_expect(
    str_contains( $compatibility_source, '\\Hexa\\PluginCore\\WpConfigFile\\WpConfigFile::' )
    && str_contains( $compatibility_source, '\\Hexa\\PluginCore\\WordPressOperations\\AutoUpdatePolicy' ),
    'legacy adapters reuse Core wp-config and WordPress operations services'
);

require_once $root . '/src/LegacyCompatibility/legacy-generic-functions.php';
$missing_runtime_functions = array_values(
    array_filter(
        $expected_functions,
        static fn( string $function ): bool => ! function_exists( 'hws_base_tools\\' . $function )
    )
);
legacy_generic_expect( [] === $missing_runtime_functions, 'thin facade loads the complete historical runtime function surface' );

echo "\n{$passes} checks passed; " . count( $failures ) . " failed.\n";
if ( [] !== $failures ) {
    exit( 1 );
}
