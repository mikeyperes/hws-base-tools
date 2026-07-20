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
require_once $root . '/src/FeatureCatalog/FeatureValueResolver.php';
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
$groups = $registry->navigation_groups();
$grouped_tab_ids = [];
foreach ( $groups as $group ) {
    $grouped_tab_ids = array_merge( $grouped_tab_ids, $group['tabs'] ?? [] );
}
$sorted_grouped_tab_ids = $grouped_tab_ids;
$sorted_tab_ids = $tab_ids;
sort( $sorted_grouped_tab_ids );
sort( $sorted_tab_ids );
expect_true(
    count( $grouped_tab_ids ) === count( array_unique( $grouped_tab_ids ) )
    && $sorted_grouped_tab_ids === $sorted_tab_ids,
    'grouped sidebar assigns every HWS tab exactly once'
);
expect_true( count( $groups ) === 5 && ( $groups[0]['label'] ?? '' ) === 'Overview', 'HWS tabs use five clear sidebar groups' );
expect_true(
    $registry->implementation_files_for_tab( 'sitemaps' ) === [ 'settings-dashboard-site-profile.php', 'settings-dashboard-sitemaps.php' ],
    'Sitemaps tab loads its Site Profile dependency first'
);
expect_true(
    $registry->implementation_files_for_ajax_action( 'hws_sitemap_scan' ) === [ 'settings-dashboard-site-profile.php', 'settings-dashboard-sitemaps.php' ],
    'Sitemap AJAX actions load their Site Profile dependency first'
);
expect_true(
    $registry->implementation_files_for_tab( 'footer-text' ) === [ 'settings-dashboard-website-types.php', 'settings-dashboard-footer-text.php' ],
    'Footer Text loads its shared toggle dependency before rendering'
);

$dashboard_source = source( 'src/AdminDashboard/legacy-dashboard.php' );
expect_true(
    str_contains( $dashboard_source, "'layout'          => 'sidebar'" )
    && str_contains( $dashboard_source, "'sidebar_collapsible' => true" )
    && str_contains( $dashboard_source, "'sidebar_persist'     => true" ),
    'HWS dashboard uses the Hexa Core grouped, collapsible, persistent sidebar shell'
);
expect_true( str_contains( $dashboard_source, "'sidebar_identity'=> hws_dashboard_sidebar_identity()" ), 'HWS sidebar displays plugin and Core version identity' );

$team_directory_source = source( 'src/TeamMembers/TeamMemberDirectory.php' );
$team_feature_source = source( 'src/TeamMembers/TeamMemberFeature.php' );
expect_true(
    str_contains( $team_directory_source, "public const SHORTCODE = 'hws_team_members'" )
    && str_contains( $team_directory_source, "public const POST_TYPE = 'team-member'" ),
    'HWS owns the Team Member directory shortcode and canonical post type contract'
);
expect_true(
    str_contains( $team_directory_source, "'portrait_grid'" )
    && str_contains( $team_directory_source, "'editorial_list'" )
    && str_contains( $team_directory_source, "'compact_directory'" ),
    'HWS Team Member directory exposes exactly the three requested template identifiers'
);
expect_true(
    str_contains( $team_feature_source, 'Prerequisite check' )
    && str_contains( $team_feature_source, 'data-hws-team-template' )
    && str_contains( $team_feature_source, 'Copy-ready shortcodes' ),
    'HWS Features UI includes readiness, visual template selection, and shortcode documentation'
);
expect_true(
    str_contains( source( 'src/FeatureCatalog/ShortcodeCatalog.php' ), "'shortcode' => '[hws_team_members]'" ),
    'HWS Shortcodes catalog documents the owned Team Member shortcode'
);
expect_true( trim( source( 'lib/hexa-wordpress-plugin-core/VERSION' ) ) === '0.19.54', 'HWS bundles Hexa WordPress Plugin Core 0.19.54' );

$footer_editor_source = source( "src/FrontendContent/legacy-footer-text-settings.php" );
expect_true(
    ! str_contains( $footer_editor_source, "wp_editor(" )
    && str_contains( $footer_editor_source, "<textarea" )
    && str_contains( $footer_editor_source, "wp.editor.initialize(editorId" )
    && str_contains( $footer_editor_source, "hexa-core-host-tab-before-load" ),
    "Footer Text uses the supported dynamic editor lifecycle for AJAX tabs"
);

$secret_store = new HWS\BaseTools\Security\SecretStore( 'hws_master_secret_key' );
expect_true( $secret_store->set( 'a-long-test-secret-value' ), 'master secret can be stored' );
expect_true( get_option( 'hws_master_secret_key' ) !== 'a-long-test-secret-value', 'master secret is not stored as plaintext' );
expect_true( $secret_store->get() === 'a-long-test-secret-value', 'encrypted master secret round-trips' );
expect_true( ! HWS\BaseTools\Security\RemoteActionPolicy::legacy_get_routes_allowed(), 'legacy remote GET actions default to disabled' );
expect_true(
    HWS\BaseTools\FeatureCatalog\FeatureValueResolver::text( static fn() => static fn() => '<b>Lazy feature details</b>' ) === '<b>Lazy feature details</b>',
    'nested lazy feature metadata resolves to text without Closure conversion'
);
expect_true(
    '' === HWS\BaseTools\FeatureCatalog\FeatureValueResolver::text( static fn() => new stdClass() ),
    'non-scalar feature metadata resolves safely to an empty string'
);
$website_types_source = file_get_contents( $root . '/src/SiteProfile/legacy-website-types.php' );
$legacy_snippets_source = file_get_contents( $root . '/src/FeatureCatalog/legacy-snippets.php' );
expect_true(
    str_contains( $website_types_source, "FeatureValueResolver::text( \$snippet['info'] ?? '' )" ),
    'Website Types resolves nested lazy snippet metadata before escaping it'
);
expect_true(
    str_contains( $legacy_snippets_source, "FeatureValueResolver::text( \$snippet['info'] ?? '' )" ),
    'Legacy Snippets resolves nested lazy snippet metadata before escaping it'
);
expect_true(
    ! preg_match( "/'info'\s*=>\s*display_(?:acf|cpt)_structure\s*\(/", source( 'src/LegacyCompatibility/legacy-runtime.php' ) ),
    'ACF and CPT feature metadata stays deferred until dashboard helpers load'
);

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
