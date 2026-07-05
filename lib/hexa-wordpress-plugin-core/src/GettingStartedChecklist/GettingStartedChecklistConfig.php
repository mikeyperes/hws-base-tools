<?php

namespace Hexa\PluginCore\GettingStartedChecklist;

final class GettingStartedChecklistConfig {
    /**
     * @var array<string,mixed>
     */
    private array $values;

    /**
     * @var array<int,GettingStartedChecklistStep>
     */
    private array $steps;

    /**
     * @param array<string,mixed> $values
     */
    public function __construct( array $values = [] ) {
        $defaults = [
            'root_id'       => 'hpc-getting-started-checklist',
            'title'         => 'Getting Started Checklist',
            'description'   => 'Run plugin setup checks and startup actions one step at a time through guarded AJAX requests.',
            'capability'    => 'manage_options',
            'nonce_action'  => 'hpc_getting_started_checklist',
            'nonce_field'   => 'nonce',
            'run_action'    => 'hpc_getting_started_checklist_run_item',
            'empty_message' => 'No getting started steps have been registered.',
            'steps'         => [],
        ];

        $values                 = array_merge( $defaults, $values );
        $values['root_id']      = $this->clean_html_id( (string) $values['root_id'] );
        $values['nonce_field']  = $this->clean_key( (string) $values['nonce_field'] );
        $values['run_action']   = $this->clean_key( (string) $values['run_action'] );
        $values['capability']   = trim( (string) $values['capability'] );
        $values['nonce_action'] = trim( (string) $values['nonce_action'] );
        $this->steps            = $this->normalize_steps( (array) $values['steps'] );
        unset( $values['steps'] );

        $this->values = $values;
    }

    public function get( string $key, mixed $default = null ): mixed {
        return array_key_exists( $key, $this->values ) ? $this->values[ $key ] : $default;
    }

    public function root_id(): string {
        return (string) $this->get( 'root_id' );
    }

    public function title(): string {
        return (string) $this->get( 'title' );
    }

    public function description(): string {
        return (string) $this->get( 'description' );
    }

    public function capability(): string {
        return (string) $this->get( 'capability', 'manage_options' );
    }

    public function nonce_action(): string {
        return (string) $this->get( 'nonce_action' );
    }

    public function nonce_field(): string {
        return (string) $this->get( 'nonce_field', 'nonce' );
    }

    public function run_action(): string {
        return (string) $this->get( 'run_action' );
    }

    public function empty_message(): string {
        return (string) $this->get( 'empty_message' );
    }

    /**
     * @return array<int,GettingStartedChecklistStep>
     */
    public function steps(): array {
        return $this->steps;
    }

    public function find_step( string $step_id ): ?GettingStartedChecklistStep {
        $step_id = $this->clean_key( $step_id );

        foreach ( $this->steps as $step ) {
            if ( $step->id === $step_id ) {
                return $step;
            }
        }

        return null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function public_steps(): array {
        return array_map(
            static fn( GettingStartedChecklistStep $step ): array => $step->to_public_array(),
            $this->steps
        );
    }

    /**
     * @param array<int|string,mixed> $steps
     * @return array<int,GettingStartedChecklistStep>
     */
    private function normalize_steps( array $steps ): array {
        $normalized = [];
        $seen       = [];

        foreach ( $steps as $key => $definition ) {
            if ( ! is_array( $definition ) && ! $definition instanceof GettingStartedChecklistStep ) {
                continue;
            }

            if ( is_array( $definition ) && is_string( $key ) && ! isset( $definition['id'] ) ) {
                $definition['id'] = $key;
            }

            $step = GettingStartedChecklistStep::from( $definition );
            if ( isset( $seen[ $step->id ] ) ) {
                continue;
            }

            $normalized[]     = $step;
            $seen[ $step->id ] = true;
        }

        return $normalized;
    }

    private function clean_key( string $value ): string {
        return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : ( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?: '' );
    }

    private function clean_html_id( string $value ): string {
        $value = trim( $value );
        if ( '' === $value ) {
            return 'hpc-getting-started-checklist';
        }

        return function_exists( 'sanitize_html_class' ) ? sanitize_html_class( $value ) : ( preg_replace( '/[^a-zA-Z0-9_\-]/', '-', $value ) ?: 'hpc-getting-started-checklist' );
    }
}
