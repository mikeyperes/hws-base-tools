<?php

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ );

/** Verifies legacy testimonial quote migration through native Core Fields. */

$options = [];
$post_meta = [
    41 => [
        'quotes'                   => 1,
        '_quotes'                  => 'field_hws_testimonial_notable_quotes',
        'quotes_0_quote'           => 'An existing canonical quote.',
        'quotes_0_url'             => 'https://example.com/existing',
        'quotes_0_tagline'         => 'Existing source',
        'notable_quotes'           => 2,
        'notable_quotes_0_quote'   => '  First legacy quote.  ',
        'notable_quotes_0_url'     => '',
        'notable_quotes_0_tagline' => '',
        'notable_quotes_1_quote'   => 'Second legacy quote.',
        'notable_quotes_1_url'     => 'https://example.com/legacy',
        'notable_quotes_1_tagline' => 'Legacy source',
    ],
];
$write_log = [];
$failures = [];
$passes = 0;

function quote_migration_expect( bool $condition, string $message ): void {
    global $failures, $passes;
    if ( $condition ) {
        ++$passes;
        echo "PASS {$message}\n";
        return;
    }
    $failures[] = $message;
    echo "FAIL {$message}\n";
}

function add_action( string $hook, callable $callback, int $priority = 10 ): void {}

function get_option( string $key, mixed $default = false ): mixed {
    global $options;
    return $options[ $key ] ?? $default;
}

function update_option( string $key, mixed $value, bool $autoload = true ): bool {
    global $options;
    $options[ $key ] = $value;
    return true;
}

function get_post_stati(): array {
    return [ 'publish' => 'publish' ];
}

function get_posts( array $args ): array {
    return 1 === (int) ( $args['paged'] ?? 1 ) ? [ 41 ] : [];
}

function get_post_meta( int $post_id, string $key, bool $single = false ): mixed {
    return get_metadata( 'post', $post_id, $key, $single );
}

require_once __DIR__ . '/fixtures/native-migration-storage.php';
require_once dirname( __DIR__ ) . '/src/AcfFields/QuoteRepeaterDefinition.php';
require_once dirname( __DIR__ ) . '/src/AcfFields/LegacySmp/register-acf-testimonial.php';
require_once dirname( __DIR__ ) . '/src/AcfFields/QuoteRepeaterMigration.php';

use Hexa\PluginCore\Fields\Field;
use Hexa\PluginCore\Fields\FieldGroups;
use HWS\BaseTools\AcfFields\QuoteRepeaterMigration;

FieldGroups::add( \hws_base_tools\enable_acf_testimonial() );

$report = QuoteRepeaterMigration::run();
$quotes = Field::get( 'quotes', 41 );

quote_migration_expect( 1 === $report['posts_scanned'], 'migration scans the Testimonial record' );
quote_migration_expect( 1 === $report['posts_changed'], 'migration changes the Testimonial with legacy rows' );
quote_migration_expect( 2 === $report['legacy_rows'] && 2 === $report['rows_written'], 'both legacy rows are appended without replacing the canonical row' );
quote_migration_expect( is_array( $quotes ) && 3 === count( $quotes ) && 'An existing canonical quote.' === $quotes[0]['quote'], 'canonical repeater retains the existing row and contains every migrated row' );
quote_migration_expect( '  First legacy quote.  ' === ( $quotes[1]['quote'] ?? null ), 'legacy quote text and whitespace are preserved exactly' );
quote_migration_expect( 'https://example.com/legacy' === ( $quotes[2]['url'] ?? null ), 'legacy source URL is preserved' );
quote_migration_expect( 'Legacy source' === ( $quotes[2]['tagline'] ?? null ), 'legacy tagline is preserved' );
quote_migration_expect( 2 === $post_meta[41]['notable_quotes'], 'legacy repeater metadata remains intact as a rollback source' );
quote_migration_expect( [] === $report['errors'], 'migration verifies with no errors' );

$writes_after_migration = count( $write_log );
$second_report = QuoteRepeaterMigration::run();
quote_migration_expect( $writes_after_migration === count( $write_log ), 'completed migration is idempotent and does not rewrite data' );
quote_migration_expect( $second_report === $report, 'completed migration returns its stored verification report' );

echo "\n{$passes} checks passed; " . count( $failures ) . " failed.\n";
exit( $failures ? 1 : 0 );
