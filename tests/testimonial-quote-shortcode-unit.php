<?php

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ );

$registered_shortcodes = [];
$current_post_id = 41;
$post_types = [ 41 => 'testimonial', 73 => 'testimonial', 74 => 'testimonial', 99 => 'post' ];
$acf_quotes = [
    41 => [
        [ 'quote' => 'The first notable quote.' ],
        [ 'quote' => 'The <strong>second</strong> quote.' ],
    ],
    73 => false,
];
$post_meta = [
    73 => [ 'quotes_0_quote' => 'A stored canonical quote.' ],
    74 => [ 'notable_quotes_0_quote' => 'A retained legacy quote.' ],
];
$failures = [];
$passes = 0;

function testimonial_quote_expect( bool $condition, string $message ): void {
    global $failures, $passes;
    if ( $condition ) {
        ++$passes;
        echo "PASS {$message}\n";
        return;
    }

    $failures[] = $message;
    echo "FAIL {$message}\n";
}

function add_shortcode( string $name, callable $callback ): void {
    global $registered_shortcodes;
    $registered_shortcodes[ $name ] = $callback;
}

function shortcode_atts( array $defaults, array $attributes, string $shortcode = '' ): array {
    return array_merge( $defaults, array_intersect_key( $attributes, $defaults ) );
}

function absint( mixed $value ): int {
    return abs( (int) $value );
}

function get_the_ID(): int {
    global $current_post_id;
    return $current_post_id;
}

function get_post_type( int $post_id ): string|false {
    global $post_types;
    return $post_types[ $post_id ] ?? false;
}

function get_field( string $field, int $post_id ): mixed {
    global $acf_quotes;
    return 'quotes' === $field ? ( $acf_quotes[ $post_id ] ?? false ) : false;
}

function get_post_meta( int $post_id, string $key, bool $single = false ): mixed {
    global $post_meta;
    return $post_meta[ $post_id ][ $key ] ?? '';
}

function wp_strip_all_tags( string $value ): string {
    return strip_tags( $value );
}

function esc_html( string $value ): string {
    return htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

require_once dirname( __DIR__ ) . '/src/Testimonials/TestimonialQuoteShortcode.php';

use HWS\BaseTools\Testimonials\TestimonialQuoteShortcode;

TestimonialQuoteShortcode::register();

testimonial_quote_expect(
    isset( $registered_shortcodes[ TestimonialQuoteShortcode::SHORTCODE ] ),
    'Testimonial quote shortcode registers under the HWS-prefixed name'
);
testimonial_quote_expect(
    'The first notable quote.' === TestimonialQuoteShortcode::render(),
    'shortcode returns the first quote from the current Testimonial by default'
);
testimonial_quote_expect(
    'The second quote.' === TestimonialQuoteShortcode::render( [ 'number' => '2' ] ),
    'number selects a repeater row and output is plain text'
);
testimonial_quote_expect(
    'A stored canonical quote.' === TestimonialQuoteShortcode::render( [ 'id' => '73' ] ),
    'specific Testimonial IDs work with canonical raw ACF repeater metadata'
);
testimonial_quote_expect(
    'A retained legacy quote.' === TestimonialQuoteShortcode::render( [ 'id' => '74' ] ),
    'legacy notable_quotes metadata remains readable during migration compatibility'
);
testimonial_quote_expect(
    '' === TestimonialQuoteShortcode::render( [ 'id' => '99' ] ),
    'shortcode ignores non-Testimonial posts'
);
testimonial_quote_expect(
    '' === TestimonialQuoteShortcode::render( [ 'number' => '9' ] ),
    'missing quote rows return an empty string'
);

echo "\n{$passes} checks passed; " . count( $failures ) . " failed.\n";
exit( $failures ? 1 : 0 );
