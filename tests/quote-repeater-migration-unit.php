<?php

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ );

$options = [];
$post_meta = [
    41 => [
        'quotes'                   => 1,
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
$update_calls = 0;
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
    global $post_meta;
    return $post_meta[ $post_id ][ $key ] ?? '';
}

function update_field( string $field_key, mixed $value, int $post_id ): bool {
    global $post_meta, $update_calls;
    ++$update_calls;

    $post_meta[ $post_id ]['quotes'] = count( $value );
    foreach ( $value as $index => $row ) {
        $post_meta[ $post_id ][ 'quotes_' . $index . '_quote' ] = $row['field_hws_testimonial_notable_quote_text'] ?? '';
        $post_meta[ $post_id ][ 'quotes_' . $index . '_url' ] = $row['field_hws_testimonial_quote_url'] ?? '';
        $post_meta[ $post_id ][ 'quotes_' . $index . '_tagline' ] = $row['field_hws_testimonial_quote_tagline'] ?? '';
    }
    return true;
}

require_once dirname( __DIR__ ) . '/src/AcfFields/QuoteRepeaterMigration.php';

use HWS\BaseTools\AcfFields\QuoteRepeaterMigration;

$report = QuoteRepeaterMigration::run();

quote_migration_expect( 1 === $report['posts_scanned'], 'migration scans the Testimonial record' );
quote_migration_expect( 1 === $report['posts_changed'], 'migration changes the Testimonial with legacy rows' );
quote_migration_expect( 2 === $report['legacy_rows'] && 2 === $report['rows_written'], 'both legacy rows are appended without replacing the canonical row' );
quote_migration_expect( 3 === $post_meta[41]['quotes'], 'canonical repeater retains the existing row and contains every migrated row' );
quote_migration_expect( '  First legacy quote.  ' === $post_meta[41]['quotes_1_quote'], 'legacy quote text and whitespace are preserved exactly' );
quote_migration_expect( 'https://example.com/legacy' === $post_meta[41]['quotes_2_url'], 'legacy source URL is preserved' );
quote_migration_expect( 'Legacy source' === $post_meta[41]['quotes_2_tagline'], 'legacy tagline is preserved' );
quote_migration_expect( 2 === $post_meta[41]['notable_quotes'], 'legacy repeater metadata remains intact as a rollback source' );
quote_migration_expect( [] === $report['errors'], 'migration verifies with no errors' );

$second_report = QuoteRepeaterMigration::run();
quote_migration_expect( 1 === $update_calls, 'completed migration is idempotent and does not rewrite data' );
quote_migration_expect( $second_report === $report, 'completed migration returns its stored verification report' );

echo "\n{$passes} checks passed; " . count( $failures ) . " failed.\n";
exit( $failures ? 1 : 0 );
