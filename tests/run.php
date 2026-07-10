<?php

declare( strict_types=1 );

$root = dirname( __DIR__ );
$failures = [];
$passes = 0;

function expect_true( bool $condition, string $message ): void {
    global $failures, $passes;

    if ( $condition ) {
        ++$passes;
        echo "PASS {$message}\n";
        return;
    }

    $failures[] = $message;
    echo "FAIL {$message}\n";
}

function source( string $relative ): string {
    global $root;
    $contents = file_get_contents( $root . '/' . $relative );

    return false === $contents ? '' : $contents;
}

$options = [];

function get_option( string $key, mixed $default = false ): mixed {
    global $options;

    return array_key_exists( $key, $options ) ? $options[ $key ] : $default;
}

function update_option( string $key, mixed $value, mixed $autoload = null ): bool {
    global $options;
    $changed = ! array_key_exists( $key, $options ) || $options[ $key ] !== $value;
    $options[ $key ] = $value;

    return $changed;
}

function wp_generate_password(): string {
    return 'generated-test-secret-1234567890';
}

function wp_salt(): string {
    return 'hws-test-only-salt';
}

function sanitize_key( string $value ): string {
    return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) );
}

function apply_filters( string $hook, mixed $value ): mixed {
    return $value;
}

require_once $root . '/lib/hexa-wordpress-plugin-core/src/WpAdminTabs/TabDefinition.php';
require_once $root . '/lib/hexa-wordpress-plugin-core/src/WpAdminTabs/TabRegistry.php';
require_once $root . '/src/PluginRuntime/PluginMetadata.php';
require_once $root . '/src/AdminDashboard/DashboardModuleDefinition.php';
require_once $root . '/src/AdminDashboard/DashboardRegistry.php';
require_once $root . '/src/Security/SecretStore.php';
require_once $root . '/src/Security/RemoteActionPolicy.php';

$metadata = HWS\BaseTools\PluginRuntime\PluginMetadata::class;
$header = source( 'hws-base-tools.php' );
expect_true( str_contains( $header, 'Version: ' . $metadata::VERSION ), 'plugin header and metadata versions match' );
expect_true( str_contains( $header, 'Requires at least: ' . $metadata::REQUIRES_WORDPRESS ), 'WordPress requirement is declared' );
expect_true( str_contains( $header, 'Requires PHP: ' . $metadata::REQUIRES_PHP ), 'PHP requirement is declared' );
expect_true( substr_count( source( 'initialization.php' ), "\n" ) < 30, 'legacy initialization entry stays thin' );
expect_true( str_contains( $header, "require_once \$hexa_plugin_core_root . '/bootstrap.php'" ), 'canonical entry registers the shared Core package runtime' );
expect_true( ! str_contains( $header, 'Hexa\\PluginCore\\' ), 'canonical entry does not load a Core class before package selection' );

$registry = HWS\BaseTools\AdminDashboard\DashboardRegistry::instance();
$tabs = $registry->navigation_tabs();
$tab_ids = array_keys( $tabs );
expect_true( array_slice( $tab_ids, 0, 2 ) === [ 'overview', 'quick-start' ], 'Quick Start is the second dashboard tab' );
expect_true( $registry->normalize( 'getting-started-checklist' ) === 'quick-start', 'legacy Quick Start route remains compatible' );
expect_true( isset( $tabs['snippets'] ) && $tabs['snippets']->deprecated, 'Legacy Snippets remains visible and deprecated' );
expect_true( isset( $tabs['shortcodes'] ), 'HWS Shortcodes tab is registered through the dashboard registry' );

$secret_store = new HWS\BaseTools\Security\SecretStore( 'hws_master_secret_key' );
expect_true( $secret_store->set( 'a-long-test-secret-value' ), 'master secret can be stored' );
expect_true( get_option( 'hws_master_secret_key' ) !== 'a-long-test-secret-value', 'master secret is not stored as plaintext' );
expect_true( $secret_store->get() === 'a-long-test-secret-value', 'encrypted master secret round-trips' );
expect_true( ! HWS\BaseTools\Security\RemoteActionPolicy::legacy_get_routes_allowed(), 'legacy remote GET actions default to disabled' );

$runtime_options = source( 'src/PluginRuntime/RuntimeOptions.php' );
expect_true( str_contains( $runtime_options, "'hws_update_urls_enabled'       => 'no'" ), 'public update URLs seed disabled' );
expect_true( str_contains( $runtime_options, "'hws_login_urls_enabled'        => 'no'" ), 'public login-control URLs seed disabled' );

$force_handler = source( 'src/PluginPolicy/legacy-plugin-checks.php' );
$force_offset = strpos( $force_handler, 'function hws_ct_force_update_check' );
$force_source = false === $force_offset ? '' : substr( $force_handler, $force_offset, 700 );
expect_true( str_contains( $force_source, "current_user_can( 'update_plugins' )" ), 'force update AJAX checks capability' );
expect_true( str_contains( $force_source, 'hws_require_ajax_nonce_or_error()' ), 'force update AJAX verifies nonce' );

expect_true( ! str_contains( source( 'src/LegacyCompatibility/legacy-generic-functions.php' ), 'eval(' ), 'generic utilities contain no eval-based aliasing' );
expect_true( ! file_exists( $root . '/register-acf-functionality.php' ), 'unsafe dead short-tag ACF file is removed' );
expect_true( ! str_contains( source( 'src/AcfFields/LegacySmpUserFields.php' ), "0 => 'field_6482a0f010e43'" ), 'legacy ACF profile photo no longer clones itself' );
expect_true( ! str_contains( source( 'src/AcfFields/LegacySmpUserFields.php' ), 'Deprecated legacy social/profile fields' ), 'unreachable legacy ACF payload is removed' );

$root_implementation_files = [];
foreach ( glob( $root . '/*.php' ) ?: [] as $root_php_file ) {
    $filename = basename( $root_php_file );
    if ( in_array( $filename, [ 'hws-base-tools.php', 'initialization.php' ], true ) ) {
        continue;
    }

    $contents = (string) file_get_contents( $root_php_file );
    if ( substr_count( $contents, "\n" ) >= 8 || ! str_contains( $contents, '/src/' ) ) {
        $root_implementation_files[] = $filename;
    }
}
expect_true( [] === $root_implementation_files, 'root PHP compatibility files stay thin and delegate to src' );

$flat_smp_files = [];
foreach ( glob( $root . '/smp-core/*.php' ) ?: [] as $smp_php_file ) {
    $contents = (string) file_get_contents( $smp_php_file );
    $delegates_to_src = str_contains( $contents, '/src/AcfFields/LegacySmp/' )
        || str_contains( $contents, 'HWS\\BaseTools\\AcfFields\\LegacySmpUserFields' );
    if ( substr_count( $contents, "\n" ) >= 8 || ! $delegates_to_src ) {
        $flat_smp_files[] = basename( $smp_php_file );
    }
}
expect_true( [] === $flat_smp_files, 'legacy SMP file paths are thin ACF-domain compatibility shims' );

$unnamespaced_source_files = [];
$source_iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator( $root . '/src', FilesystemIterator::SKIP_DOTS )
);
foreach ( $source_iterator as $source_file ) {
    if ( ! $source_file->isFile() || 'php' !== strtolower( $source_file->getExtension() ) ) {
        continue;
    }

    $relative = substr( $source_file->getPathname(), strlen( $root ) + 1 );
    if ( 'src/LegacyCompatibility/legacy-helper.php' === $relative ) {
        continue;
    }

    $contents = (string) file_get_contents( $source_file->getPathname() );
    if ( ! preg_match( '/\bnamespace\s+[A-Za-z_\\\\][A-Za-z0-9_\\\\]*\s*;/', $contents ) ) {
        $unnamespaced_source_files[] = $relative;
    }
}
expect_true( [] === $unnamespaced_source_files, 'all src PHP files except the intentional global fallback declare a namespace' );
expect_true(
    source( 'HEXA_PLUGIN_CORE_LIBRARY.md' ) === source( 'lib/hexa-wordpress-plugin-core/HEXA_PLUGIN_CORE_LIBRARY.md' ),
    'host Core library guide matches the vendored Core guide'
);
expect_true( is_readable( $root . '/docs/architecture.md' ), 'architecture contract is present' );
expect_true(
    ! str_contains( source( 'src/Maintenance/legacy-log-cleaner.php' ), "dirname( __FILE__ ) . '/hws-base-tools.php'" ),
    'relocated log cleaner uses the canonical plugin root'
);
expect_true(
    str_contains( source( 'src/AdminDashboard/LegacyEventBridge.php' ), 'namespace HWS\\BaseTools\\AdminDashboard;' ),
    'dashboard bridge uses the canonical AdminDashboard namespace'
);

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
);

foreach ( $iterator as $file ) {
    if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) || str_contains( $file->getPathname(), '/.git/' ) ) {
        continue;
    }

    $command = escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $file->getPathname() ) . ' 2>&1';
    exec( $command, $output, $exit_code );
    expect_true( 0 === $exit_code, 'PHP lint: ' . substr( $file->getPathname(), strlen( $root ) + 1 ) );
    $output = [];
}

echo "\n{$passes} checks passed; " . count( $failures ) . " failed.\n";

if ( $failures ) {
    exit( 1 );
}
