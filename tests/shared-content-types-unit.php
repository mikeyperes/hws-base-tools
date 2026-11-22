<?php

declare( strict_types=1 );

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$options = [];
$hooks = [];
$registered = [];

function add_action( string $hook, callable $callback, int $priority = 10 ): void {
    $GLOBALS['hooks'][] = [ $hook, $callback, $priority ];
}

function add_filter( string $hook, callable $callback ): void {
    $GLOBALS['hooks'][] = [ $hook, $callback, 10 ];
}

final class WP_Post_Type {
    public function __construct( public string $name ) {}
}

function get_current_blog_id(): int { return $GLOBALS['blog_id'] ?? 1; }

function get_option( string $key, mixed $default = false ): mixed {
    return $GLOBALS['options'][ $key ] ?? $default;
}

function update_option( string $key, mixed $value, bool $autoload = true ): bool {
    $GLOBALS['options'][ $key ] = $value;
    return true;
}

function post_type_exists( string $post_type ): bool {
    return isset( $GLOBALS['registered'][ $post_type ] );
}

function register_post_type( string $post_type, array $args ): object {
    $GLOBALS['registered'][ $post_type ] = $args;
    return new WP_Post_Type( $post_type );
}

function is_wp_error( mixed $value ): bool {
    return false;
}

function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
    return $value;
}

$core_root = dirname( __DIR__ ) . '/lib/hexa-wordpress-plugin-core';
require_once $core_root . '/bootstrap.php';
hexa_plugin_core_register_package( 'hws-shared-content-types-test', $core_root );
HexaPluginCorePackageRegistry::resolve();
$hooks = [];

require_once __DIR__ . '/bootstrap-fields.php';
require_once dirname( __DIR__ ) . '/src/ContentTypes/Organization/OrganizationContentType.php';
require_once dirname( __DIR__ ) . '/src/ContentTypes/SharedContentTypes.php';
require_once dirname( __DIR__ ) . '/src/AcfFields/QuoteRepeaterDefinition.php';
require_once dirname( __DIR__ ) . '/src/AcfFields/LegacySmp/register-acf-testimonial.php';
require_once dirname( __DIR__ ) . '/src/AcfFields/user-profile-2025.php';

use HWS\BaseTools\ContentTypes\SharedContentTypes;
use HWS\BaseTools\ContentTypes\Organization\OrganizationContentType;

$failures = [];
$expect = static function ( bool $condition, string $message ) use ( &$failures ): void {
    if ( ! $condition ) {
        $failures[] = $message;
        echo 'FAIL ' . $message . PHP_EOL;
        return;
    }
    echo 'PASS ' . $message . PHP_EOL;
};

$definitions = SharedContentTypes::definitions();
$expect(
    array_keys( $definitions ) === [ 'team-member', 'testimonial', 'services' ],
    'the existing Base Tools settings list is unchanged by the separate Organization provider'
);

OrganizationContentType::register_type();
$expect( ! post_type_exists( 'organization' ), 'standalone sites without an Organization setting preserve the disabled state' );

eval( 'namespace SFPF\PersonProfile\ContentTypes\Organization; final class OrganizationContentType {}' );
$options['sfpf_content_type_settings'] = [ 'organization' => [
    'enabled' => 1, 'singular' => 'Company', 'plural' => 'Companies', 'rewrite_slug' => 'company',
] ];
$expect( OrganizationContentType::provides_registration(), 'SFPF can use the active provider for the existing enabled type' );
OrganizationContentType::register_type();
$expect( ( $registered['organization']['labels']['name'] ?? '' ) === 'Companies'
    && ( $registered['organization']['rewrite']['slug'] ?? '' ) === 'company'
    && ( $registered['organization']['has_archive'] ?? '' ) === 'organizations',
    'SFPF labels, URL and archive settings stay in use' );
unset( $registered['organization'], $options['sfpf_content_type_settings'] );
$custom = [ 'labels' => [ 'name' => 'Affiliate Organizations', 'singular_name' => 'Affiliate Organization' ], 'has_archive' => false, 'rewrite' => [ 'slug' => 'organization', 'with_front' => true ] ];
$options['hws_organization_registration_args'] = $custom;
OrganizationContentType::register_type();
$expect( $registered['organization'] === $custom, 'the adopted CPT UI registration keeps every supplied argument' );
$expect( OrganizationContentType::skip_migrated_cptui_type( false ), 'CPT UI skips Organization only after Base Tools registers it' );
$blog_id=2;
$expect( ! OrganizationContentType::skip_migrated_cptui_type( false ), 'the CPT UI skip does not leak to another multisite blog' );
unset( $blog_id, $registered['organization'], $options['hws_organization_registration_args'] );

$testimonial_group = \hws_base_tools\enable_acf_testimonial();
$testimonial_fields = array_column( $testimonial_group['fields'] ?? [], null, 'name' );
$quotes = $testimonial_fields['quotes'] ?? [];
$quote_fields = array_column( $quotes['sub_fields'] ?? [], null, 'name' );
$expect(
    'repeater' === ( $quotes['type'] ?? '' )
        && 'Add Quote' === ( $quotes['button_label'] ?? '' )
        && 'textarea' === ( $quote_fields['quote']['type'] ?? '' )
        && 'url' === ( $quote_fields['url']['type'] ?? '' )
        && 'text' === ( $quote_fields['tagline']['type'] ?? '' )
        && 'Attribution / Tagline' === ( $quote_fields['tagline']['label'] ?? '' ),
    'Testimonial fields include the canonical Quotes repeater with quote, URL, and attribution/tagline fields'
);

$user_group = \hws_base_tools\hws_user_additional_fields_group();
$user_fields = array_column( $user_group['fields'] ?? [], null, 'name' );
$user_quotes = $user_fields['quotes'] ?? [];
$user_quote_fields = array_column( $user_quotes['sub_fields'] ?? [], null, 'name' );
$expect(
    'repeater' === ( $user_quotes['type'] ?? '' )
        && [ 'quote', 'url', 'tagline' ] === array_keys( $user_quote_fields )
        && 'textarea' === ( $user_quote_fields['quote']['type'] ?? '' )
        && 'url' === ( $user_quote_fields['url']['type'] ?? '' )
        && 'text' === ( $user_quote_fields['tagline']['type'] ?? '' ),
    'User - Additional uses the same canonical Quotes repeater structure as Testimonials'
);

foreach ( $definitions as $post_type => $definition ) {
    $expect( true === $definition['public'], $post_type . ' is public' );
    $expect( true === $definition['show_in_rest'], $post_type . ' is REST-enabled' );
    $expect( in_array( 'custom-fields', $definition['supports'], true ), $post_type . ' preserves custom fields' );
}

SharedContentTypes::boot();
$init_hooks = array_values( array_filter( $hooks, static fn( array $hook ): bool => 'init' === $hook[0] ) );
$expect(
    2 === count( $init_hooks ) && [ 4, 0 ] === array_column( $init_hooks, 2 ),
    'existing shared types stay at priority zero and Organization stays at priority four'
);
$expect( str_contains( (string) file_get_contents( dirname( __DIR__ ) . '/lib/hexa-wordpress-plugin-core/src/ContentTypes/ContentTypeRegistry.php' ), 'FieldGroups::ready' ), 'registry registers its field groups through Hexa WP Core Fields (ACF or native)' );
$expect( in_array( 'wp_ajax_hws_save_content_type', array_column( $hooks, 0 ), true ), 'registry owns the guarded AJAX save hook' );

SharedContentTypes::register_enabled();
$expect( [] === $registered, 'disabled types do not register' );

$expect( ! SharedContentTypes::enable( SharedContentTypes::ORGANIZATION ), 'Organization is not added to the existing Shared Content Types controls' );
$expect( null === SharedContentTypes::option_for( SharedContentTypes::ORGANIZATION ), 'the original Shared Content Types option mapping stays unchanged' );
SharedContentTypes::register_enabled();
$expect( ! isset( $registered['organization'] ), 'the other shared types do not register Organization' );
$expect( ! isset( $registered['testimonial'], $registered['team-member'], $registered['services'] ), 'unselected HWS types remain disabled' );

$expect( SharedContentTypes::enable( SharedContentTypes::SERVICES ), 'services option can be enabled' );
SharedContentTypes::register_enabled();
$expect( isset( $registered['services'] ), 'enabled services registers' );
$expect( false === $definitions['services']['has_archive'], 'services preserves the static landing page' );
$expect( false === $definitions['team-member']['has_archive'], 'team members preserve the static Team landing page' );
$expect( SharedContentTypes::enable( SharedContentTypes::TEAM_MEMBER ), 'team member option can be enabled' );
SharedContentTypes::register_enabled();
$expect( isset( $registered['team-member'] ) && false === $registered['team-member']['has_archive'], 'registered team members cannot take over the Team page' );

$count = count( $registered );
$expect( ! SharedContentTypes::register_type( SharedContentTypes::ORGANIZATION ), 'the legacy control does not enable Organization on an unconfigured site' );
$expect( $count === count( $registered ), 'the legacy control does not mutate existing registrations' );
$expect( ! SharedContentTypes::register_type( 'unknown' ), 'unknown content types are rejected' );

if ( $failures ) {
    exit( 1 );
}

echo count( $definitions ) . ' shared content types verified.' . PHP_EOL;
