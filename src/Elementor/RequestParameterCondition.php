<?php

namespace HWS\BaseTools\Elementor;

use Elementor\Controls_Manager;
use ElementorPro\Modules\DisplayConditions\Classes\Comparator_Provider;
use ElementorPro\Modules\DisplayConditions\Classes\Comparators_Checker;
use ElementorPro\Modules\DisplayConditions\Conditions\Base\Condition_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Display an Elementor element when a scalar GET parameter matches a value.
 */
final class RequestParameterCondition extends Condition_Base {
    public const NAME = 'hws_request_parameter';

    public static function register( mixed $conditions_manager ): void {
        if ( ! is_object( $conditions_manager ) || ! method_exists( $conditions_manager, 'register_condition_instance' ) ) {
            return;
        }

        $conditions_manager->register_condition_instance( new self() );
    }

    public function get_name(): string {
        return self::NAME;
    }

    public function get_label(): string {
        return esc_html__( 'HWS Request Parameter', 'hws-base-tools' );
    }

    public function get_group(): string {
        return 'other';
    }

    public function get_options(): void {
        $this->add_control(
            'parameter',
            [
                'label'       => esc_html__( 'Parameter', 'hws-base-tools' ),
                'type'        => Controls_Manager::TEXT,
                'placeholder' => 'purchase',
                'required'    => true,
            ]
        );

        $this->add_control(
            'comparator',
            [
                'label'   => esc_html__( 'Comparator', 'hws-base-tools' ),
                'type'    => Controls_Manager::SELECT,
                'options' => Comparator_Provider::get_comparators(
                    [
                        Comparator_Provider::COMPARATOR_IS,
                        Comparator_Provider::COMPARATOR_IS_NOT,
                        Comparator_Provider::COMPARATOR_CONTAINS,
                        Comparator_Provider::COMPARATOR_NOT_CONTAIN,
                    ]
                ),
                'default' => Comparator_Provider::COMPARATOR_IS,
            ]
        );

        $this->add_control(
            'value',
            [
                'label'       => esc_html__( 'Value', 'hws-base-tools' ),
                'type'        => Controls_Manager::TEXT,
                'placeholder' => 'true',
                'required'    => true,
            ]
        );
    }

    public function check( $args ): bool {
        $parameter = self::normalize_parameter( (string) ( $args['parameter'] ?? '' ) );
        $actual    = self::get_request_value( $parameter );

        if ( null === $actual ) {
            return false;
        }

        return Comparators_Checker::check_string_contains(
            (string) ( $args['comparator'] ?? Comparator_Provider::COMPARATOR_IS ),
            self::sanitize_value( (string) ( $args['value'] ?? '' ) ),
            $actual
        );
    }

    public static function normalize_parameter( string $parameter ): string {
        $parameter = trim( $parameter );

        return function_exists( 'sanitize_key' )
            ? sanitize_key( $parameter )
            : (string) preg_replace( '/[^a-z0-9_\-]/i', '', strtolower( $parameter ) );
    }

    public static function get_request_value( string $parameter ): ?string {
        $parameter = self::normalize_parameter( $parameter );

        if ( '' === $parameter || ! array_key_exists( $parameter, $_GET ) || is_array( $_GET[ $parameter ] ) || is_object( $_GET[ $parameter ] ) ) {
            return null;
        }

        $value = (string) $_GET[ $parameter ];
        $value = function_exists( 'wp_unslash' ) ? wp_unslash( $value ) : stripslashes( $value );

        return self::sanitize_value( $value );
    }

    public static function sanitize_value( string $value ): string {
        return function_exists( 'sanitize_text_field' )
            ? sanitize_text_field( $value )
            : trim( strip_tags( $value ) );
    }
}
