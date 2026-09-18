<?php

declare( strict_types=1 );

namespace {
    define( 'ABSPATH', dirname( __DIR__ ) . '/' );

    function esc_html__( string $text, string $domain = '' ): string {
        return $text;
    }

    function sanitize_key( string $value ): string {
        return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) );
    }

    function sanitize_text_field( string $value ): string {
        return trim( strip_tags( $value ) );
    }

    function wp_unslash( string $value ): string {
        return stripslashes( $value );
    }

    function request_condition_expect( bool $condition, string $message ): void {
        if ( ! $condition ) {
            fwrite( STDERR, "FAIL: {$message}\n" );
            exit( 1 );
        }
    }
}

namespace Elementor {
    final class Controls_Manager {
        public const TEXT = 'text';
        public const SELECT = 'select';
    }
}

namespace ElementorPro\Modules\DisplayConditions\Classes {
    final class Comparator_Provider {
        public const COMPARATOR_IS = 'is';
        public const COMPARATOR_IS_NOT = 'is_not';
        public const COMPARATOR_CONTAINS = 'contains';
        public const COMPARATOR_NOT_CONTAIN = 'not_contain';

        public static function get_comparators( array $comparators ): array {
            return array_combine( $comparators, $comparators );
        }
    }

    final class Comparators_Checker {
        public static function check_string_contains( string $comparator, string $expected, string $actual ): bool {
            $expected = strtolower( $expected );
            $actual   = strtolower( $actual );

            return match ( $comparator ) {
                'is'          => $expected === $actual,
                'is_not'      => $expected !== $actual,
                'contains'    => str_contains( $actual, $expected ),
                'not_contain' => ! str_contains( $actual, $expected ),
                default       => false,
            };
        }
    }
}

namespace ElementorPro\Modules\DisplayConditions\Conditions\Base {
    abstract class Condition_Base {
        /** @var array<string,array<string,mixed>> */
        protected array $controls = [];

        protected function add_control( string $id, array $definition ): void {
            $this->controls[ $id ] = $definition;
        }

        /** @return array<string,array<string,mixed>> */
        public function test_controls(): array {
            return $this->controls;
        }
    }
}

namespace {
    require dirname( __DIR__ ) . '/src/Elementor/RequestParameterCondition.php';

    use HWS\BaseTools\Elementor\RequestParameterCondition;

    $manager = new class {
        /** @var list<object> */
        public array $conditions = [];

        public function register_condition_instance( object $condition ): void {
            $this->conditions[] = $condition;
        }
    };

    RequestParameterCondition::register( $manager );
    request_condition_expect( 1 === count( $manager->conditions ), 'the condition registers with Elementor Pro' );

    $condition = $manager->conditions[0];
    request_condition_expect( RequestParameterCondition::NAME === $condition->get_name(), 'the condition identifier is stable' );
    request_condition_expect( 'other' === $condition->get_group(), 'the condition is in the Other group' );
    $condition->get_options();
    $controls = $condition->test_controls();
    request_condition_expect( [ 'parameter', 'comparator', 'value' ] === array_keys( $controls ), 'the condition exposes the parameter, comparator, and value controls' );
    request_condition_expect( 'select' === $controls['comparator']['type'] && 'is' === $controls['comparator']['default'], 'the comparator control defaults to exact equality' );
    request_condition_expect( true === $controls['parameter']['required'] && true === $controls['value']['required'], 'parameter and value are required controls' );

    $_GET = [ 'purchase' => 'true' ];
    request_condition_expect( true === $condition->check( [ 'parameter' => 'purchase', 'comparator' => 'is', 'value' => 'true' ] ), 'purchase=true passes exact equality' );
    request_condition_expect( false === $condition->check( [ 'parameter' => 'purchase', 'comparator' => 'is', 'value' => 'false' ] ), 'a different value fails exact equality' );
    request_condition_expect( true === $condition->check( [ 'parameter' => 'purchase', 'comparator' => 'contains', 'value' => 'ru' ] ), 'contains uses the current request value' );

    $_GET = [];
    request_condition_expect( false === $condition->check( [ 'parameter' => 'purchase', 'comparator' => 'is', 'value' => 'true' ] ), 'a missing parameter fails closed' );

    $_GET = [ 'purchase' => '<strong>true</strong>' ];
    request_condition_expect( 'true' === RequestParameterCondition::get_request_value( '  PURCHASE  ' ), 'parameter names and values are sanitized before comparison' );
    $_GET = [ 'purchase' => [ 'true' ] ];
    request_condition_expect( null === RequestParameterCondition::get_request_value( 'purchase' ), 'array request values are rejected' );

    echo "PASS: HWS request parameter display condition registration, controls, comparison, missing values, and sanitization.\n";
}
