<?php

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

$root = dirname( __DIR__ );
$failures = [];
$passes = 0;
$options = [];

function brand_expect( bool $condition, string $message ): void {
    global $failures, $passes;
    if ( $condition ) {
        ++$passes;
        echo "PASS {$message}\n";
        return;
    }
    $failures[] = $message;
    echo "FAIL {$message}\n";
}

function sanitize_key( string $value ): string {
    return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) );
}

function wp_json_encode( mixed $value, int $flags = 0, int $depth = 512 ): string|false {
    return json_encode( $value, $flags, $depth );
}

function get_option( string $key, mixed $default = false ): mixed {
    global $options;
    return array_key_exists( $key, $options ) ? $options[ $key ] : $default;
}

function update_option( string $key, mixed $value, mixed $autoload = null ): bool {
    global $options;
    $options[ $key ] = $value;
    return true;
}

require_once $root . '/src/BrandTemplates/BrandTemplateRegistry.php';
require_once $root . '/src/BrandTemplates/BrandTemplateSettings.php';
require_once $root . '/src/BrandTemplates/ElementorStructureFactory.php';

use HWS\BaseTools\BrandTemplates\BrandTemplateRegistry;
use HWS\BaseTools\BrandTemplates\BrandTemplateSettings;
use HWS\BaseTools\BrandTemplates\ElementorStructureFactory;

$expected_contexts = [ 'author', 'page', 'single_post', 'category', 'tag' ];
brand_expect( BrandTemplateRegistry::contexts() === $expected_contexts, 'registry exposes author, page, single post, category, and tag contexts' );
brand_expect(
    BrandTemplateRegistry::get( 'category' )['condition'] === 'include/archive/category'
    && BrandTemplateRegistry::get( 'tag' )['condition'] === 'include/archive/post_tag',
    'category and tag imports use exact Elementor Theme Builder conditions'
);
brand_expect(
    BrandTemplateRegistry::get( 'page' )['conditions'] === [ 'include/singular/page', 'exclude/singular/front_page' ],
    'default Page import preserves the live front page'
);
brand_expect(
    BrandTemplateRegistry::get( 'category' )['template_file'] === 'category.php'
    && BrandTemplateRegistry::get( 'tag' )['template_file'] === 'tag.php'
    && file_exists( $root . '/templates/category.php' )
    && file_exists( $root . '/templates/tag.php' ),
    'plugin-owned category.php and tag.php fallbacks exist'
);

$defaults = BrandTemplateSettings::defaults();
brand_expect( ! in_array( true, $defaults, true ), 'every Brand Templates option is disabled by default' );
$sanitized = BrandTemplateSettings::sanitize( [ 'category_enabled' => 'yes', 'tag_enabled' => '0', 'page_content_styles_enabled' => 'on' ] );
brand_expect(
    $sanitized['category_enabled'] && ! $sanitized['tag_enabled'] && $sanitized['page_content_styles_enabled'],
    'Brand Templates settings normalize checkbox values predictably'
);

function brand_flatten( array $elements, array &$flat ): void {
    foreach ( $elements as $element ) {
        $flat[] = $element;
        brand_flatten( is_array( $element['elements'] ?? null ) ? $element['elements'] : [], $flat );
    }
}

foreach ( $expected_contexts as $context ) {
    $flat = [];
    brand_flatten( ElementorStructureFactory::elements( $context ), $flat );
    $ids = array_column( $flat, 'id' );
    $h1s = array_filter(
        $flat,
        static fn( array $element ): bool => 'heading' === ( $element['widgetType'] ?? '' ) && 'h1' === ( $element['settings']['header_size'] ?? '' )
    );
    brand_expect( $flat && count( $ids ) === count( array_unique( $ids ) ), "{$context} Elementor structure has stable unique element IDs" );
    brand_expect( 1 === count( $h1s ), "{$context} Elementor structure contains exactly one H1" );
}

foreach ( [ 'author', 'category', 'tag' ] as $context ) {
    $flat = [];
    brand_flatten( ElementorStructureFactory::elements( $context ), $flat );
    $breadcrumbs = array_values(
        array_filter(
            $flat,
            static fn( array $element ): bool => 'shortcode' === ( $element['widgetType'] ?? '' )
                && '[rank_math_breadcrumb]' === ( $element['settings']['shortcode'] ?? '' )
        )
    );
    $archives = array_values( array_filter( $flat, static fn( array $element ): bool => 'archive-posts' === ( $element['widgetType'] ?? '' ) ) );
    $archive_settings = $archives[0]['settings'] ?? [];
    brand_expect( 1 === count( $breadcrumbs ), "{$context} includes one native Elementor Rank Math shortcode widget" );
    brand_expect(
        1 === count( $archives )
        && '4' === ( $archive_settings['archive_classic_columns'] ?? '' )
        && '2' === ( $archive_settings['archive_classic_columns_tablet'] ?? '' )
        && '1' === ( $archive_settings['archive_classic_columns_mobile'] ?? '' ),
        "{$context} uses a native current-query archive at 4/2/1 columns"
    );
}

$category = ElementorStructureFactory::elements( 'category' );
brand_expect(
    'data-smpi-breadcrumbs-injected|1' === ( $category[0]['settings']['_attributes'] ?? '' ),
    'Elementor intros suppress duplicate SMP breadcrumb auto-injection without disabling Rank Math output'
);

$importer = (string) file_get_contents( $root . '/src/BrandTemplates/ElementorTemplateImporter.php' );
$backup_store = (string) file_get_contents( $root . '/src/BrandTemplates/BrandTemplateBackupStore.php' );
$loader = (string) file_get_contents( $root . '/src/BrandTemplates/TemplateLoader.php' );
$admin = (string) file_get_contents( $root . '/src/BrandTemplates/BrandTemplatesAdmin.php' );
$css = (string) file_get_contents( $root . '/assets/frontend/brand-templates.css' );
brand_expect(
    str_contains( $importer, 'get_conditions_conflicts_by_location' )
    && str_contains( $importer, 'BrandTemplateBackupStore::create' )
    && str_contains( $importer, 'save_conditions' )
    && str_contains( $importer, "shortcode_exists( 'rank_math_breadcrumb' )" ),
    'Elementor imports use official conflict detection, backups, and condition persistence'
);
brand_expect(
    str_contains( $backup_store, "'data_encoding' => 'base64'" )
    && str_contains( $backup_store, 'base64_decode' )
    && str_contains( $backup_store, 'JSON_ERROR_NONE' )
    && str_contains( $importer, "'invalid_backup'" ),
    'managed backups preserve escaped Elementor JSON and reject corrupt snapshots before restore'
);
brand_expect(
    str_contains( $loader, 'has_active_elementor_document' )
    && str_contains( $loader, 'is_individual_elementor_page' )
    && str_contains( $loader, '! is_front_page()' ),
    'fallback loading protects Theme Builder documents, Elementor pages, and the homepage'
);
brand_expect(
    str_contains( $admin, 'Import Elementor Template' )
    && str_contains( $admin, 'Replace Managed Design' )
    && str_contains( $admin, 'Restore Latest Backup' ),
    'admin UI exposes import, explicit replacement, and rollback workflows'
);
brand_expect(
    str_contains( $css, '.hws-default-template__grid' )
    && str_contains( $css, 'body.hws-page-content-styles' )
    && str_contains( $css, 'body.hws-single-content-styles' ),
    'frontend CSS is scoped to fallback templates and explicit content-style body classes'
);

if ( $failures ) {
    fwrite( STDERR, count( $failures ) . " Brand Templates assertion(s) failed.\n" );
    exit( 1 );
}

echo "PASS: {$passes} Brand Templates assertions passed.\n";
