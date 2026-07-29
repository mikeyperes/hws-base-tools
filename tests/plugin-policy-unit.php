<?php

declare( strict_types=1 );

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$options = [ 'hws_site_type' => '' ];
$failures = [];
$passes = 0;

function add_action( string $hook, mixed $callback, int $priority = 10, int $accepted_args = 1 ): void {}

function sanitize_key( mixed $value ): string {
    return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function get_option( string $key, mixed $default = false ): mixed {
    global $options;

    return array_key_exists( $key, $options ) ? $options[ $key ] : $default;
}

function admin_url( string $path = '' ): string {
    return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
}

function expect_policy( bool $condition, string $message ): void {
    global $failures, $passes;

    if ( $condition ) {
        ++$passes;
        echo "PASS {$message}\n";
        return;
    }

    $failures[] = $message;
    echo "FAIL {$message}\n";
}

function policy_definition( array $definitions, string $id ): array {
    foreach ( $definitions as $definition ) {
        if ( $id === ( $definition['id'] ?? '' ) ) {
            return $definition;
        }
    }

    return [];
}

require_once dirname( __DIR__ ) . '/src/SiteProfile/legacy-site-profile.php';
require_once dirname( __DIR__ ) . '/src/PluginPolicy/legacy-plugin-checks.php';

$podcast_slug = 'smp-core-podcast-integration';
$catalog = hws_base_tools\hws_get_hexa_plugin_catalog();
$site_kit_definition = policy_definition(
    hws_base_tools\hws_get_monitored_plugin_definitions(),
    'google-site-kit-google-site-kit.php'
);

expect_policy(
    ( $site_kit_definition['plugin_file'] ?? '' ) === 'google-site-kit/google-site-kit.php'
    && ( $site_kit_definition['wp_org_slug'] ?? '' ) === 'google-site-kit'
    && ( $site_kit_definition['download_url'] ?? '' ) === 'https://wordpress.org/plugins/google-site-kit/',
    'Google Site Kit uses the exact WordPress.org package and plugin entry file'
);
expect_policy(
    true === ( $site_kit_definition['required'] ?? false )
    && true === ( $site_kit_definition['recommended'] ?? false )
    && true === ( $site_kit_definition['checks']['installed'] ?? false )
    && true === ( $site_kit_definition['checks']['active'] ?? false ),
    'Google Site Kit is required, recommended, installed, and active policy'
);

expect_policy(
    ( $catalog[ $podcast_slug ]['plugin_file'] ?? '' ) === 'smp-core-podcast-integration/initialization.php',
    'podcast policy points to the actual plugin entry file'
);
expect_policy(
    ( $catalog[ $podcast_slug ]['site_types'] ?? [] ) === [ 'podcast_website' ],
    'podcast integration is restricted to Podcast Website'
);

$options['hws_site_type'] = 'news_outlet';
$news_catalog = hws_base_tools\hws_get_additional_hws_plugins();
$news_definition = policy_definition( hws_base_tools\hws_get_hws_plugin_library_definitions(), $podcast_slug );

expect_policy( ! isset( $news_catalog[ $podcast_slug ] ), 'news outlets cannot install the podcast integration' );
expect_policy( null === hws_base_tools\hws_get_additional_hws_plugin( $podcast_slug ), 'direct install lookup rejects the podcast integration on news outlets' );
expect_policy(
    true === ( $news_definition['should_not_contain'] ?? false )
    && false === ( $news_definition['recommended'] ?? true )
    && true === ( $news_definition['checks']['not_installed'] ?? false ),
    'news outlets mark an installed podcast integration as incompatible and removable'
);

$options['hws_site_type'] = 'podcast_website';
$podcast_catalog = hws_base_tools\hws_get_additional_hws_plugins();
$podcast_definition = policy_definition( hws_base_tools\hws_get_hws_plugin_library_definitions(), $podcast_slug );

expect_policy( isset( $podcast_catalog[ $podcast_slug ] ), 'podcast websites can install the podcast integration' );
expect_policy(
    false === ( $podcast_definition['should_not_contain'] ?? true )
    && true === ( $podcast_definition['recommended'] ?? false )
    && true === ( $podcast_definition['checks']['installed'] ?? false )
    && true === ( $podcast_definition['checks']['active'] ?? false ),
    'podcast websites recommend and monitor the podcast integration'
);

$options['hws_site_type'] = '';
expect_policy(
    ! isset( hws_base_tools\hws_get_additional_hws_plugins()[ $podcast_slug ] ),
    'an unconfigured website cannot install a site-specific podcast integration'
);

echo "\n{$passes} checks passed; " . count( $failures ) . " failed.\n";
exit( $failures ? 1 : 0 );
