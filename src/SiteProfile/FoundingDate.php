<?php

declare( strict_types=1 );

namespace HWS\BaseTools\SiteProfile;

defined( 'ABSPATH' ) || exit;

/** A company date with exactly the precision known, never an invented month or day. */
final class FoundingDate {
    public static function normalize( mixed $value ): string {
        $value = is_scalar( $value ) ? trim( (string) $value ) : '';
        // ACF's previous date picker stored complete dates as YYYYMMDD.
        if ( preg_match( '/^([0-9]{4})([0-9]{2})([0-9]{2})$/D', $value, $m ) ) {
            $value = $m[1] . '-' . $m[2] . '-' . $m[3];
        }
        if ( ! preg_match( '/^([0-9]{4})(?:-([0-9]{2})(?:-([0-9]{2}))?)?$/D', $value, $m ) ) {
            return '';
        }
        return checkdate( (int) ( $m[2] ?? 1 ), (int) ( $m[3] ?? 1 ), (int) $m[1] ) ? $value : '';
    }

    /** Display only known parts. Sentence form supplies the appropriate preposition. */
    public static function format( mixed $value, string $format = '' ): string {
        $date = self::normalize( $value );
        if ( '' === $date || 'raw' === $format ) {
            return $date;
        }
        if ( 'year' === $format || 4 === strlen( $date ) ) {
            $text = substr( $date, 0, 4 );
        } else {
            $text = wp_date( 7 === strlen( $date ) ? 'F Y' : (string) get_option( 'date_format', 'F j, Y' ),
                (int) strtotime( $date . ( 7 === strlen( $date ) ? '-01' : '' ) . ' 12:00:00 UTC' ), new \DateTimeZone( 'UTC' ) );
        }
        return 'sentence' === $format ? ( 10 === strlen( $date ) ? 'on ' : 'in ' ) . $text : $text;
    }

    public static function validate( mixed $valid, mixed $value ): mixed {
        if ( true !== $valid || '' === trim( (string) $value ) ) {
            return $valid;
        }
        return '' !== self::normalize( $value ) ? true : 'Use YYYY, YYYY-MM, or YYYY-MM-DD with a valid calendar date.';
    }
}
