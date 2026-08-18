<?php

namespace HWS\BaseTools\Testimonials;

defined( 'ABSPATH' ) || exit;

final class TestimonialQuoteShortcode {
    public const SHORTCODE = 'hws_testimonial_quote';
    public const POST_TYPE = 'testimonial';

    public static function register(): void {
        add_shortcode( self::SHORTCODE, [ self::class, 'render' ] );
    }

    /** @param array<string,mixed> $attributes */
    public static function render( array $attributes = [] ): string {
        $attributes = shortcode_atts(
            [
                'id'     => 0,
                'number' => 1,
            ],
            $attributes,
            self::SHORTCODE
        );

        $post_id = absint( $attributes['id'] );
        if ( 1 > $post_id ) {
            $post_id = absint( get_the_ID() );
        }

        if ( 1 > $post_id || self::POST_TYPE !== get_post_type( $post_id ) ) {
            return '';
        }

        $number = max( 1, absint( $attributes['number'] ) );
        $index = $number - 1;
        $quote = '';

        if ( function_exists( 'get_field' ) ) {
            foreach ( [ 'quotes', 'notable_quotes' ] as $field_name ) {
                $quotes = get_field( $field_name, $post_id );
                $row = is_array( $quotes ) ? ( $quotes[ $index ] ?? null ) : null;
                if ( is_array( $row ) && is_scalar( $row['quote'] ?? null ) ) {
                    $quote = (string) $row['quote'];
                }
                if ( '' !== trim( $quote ) ) {
                    break;
                }
            }
        }

        if ( '' === trim( $quote ) ) {
            foreach ( [ 'quotes', 'notable_quotes' ] as $field_name ) {
                $raw_quote = get_post_meta( $post_id, $field_name . '_' . $index . '_quote', true );
                if ( is_scalar( $raw_quote ) ) {
                    $quote = (string) $raw_quote;
                }
                if ( '' !== trim( $quote ) ) {
                    break;
                }
            }
        }

        return esc_html( trim( wp_strip_all_tags( $quote ) ) );
    }
}
