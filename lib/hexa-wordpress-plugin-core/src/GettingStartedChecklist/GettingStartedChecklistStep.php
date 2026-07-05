<?php

namespace Hexa\PluginCore\GettingStartedChecklist;

final class GettingStartedChecklistStep {
    public string $id;

    public string $label;

    public string $description;

    /**
     * @var callable|null
     */
    public mixed $callback;

    /**
     * @var array<int,GettingStartedChecklistSubtask>
     */
    public array $subtasks;

    /**
     * @var array<string,mixed>
     */
    public array $context;

    /**
     * @param array<string,mixed> $definition
     */
    public function __construct( array $definition = [] ) {
        $this->id          = self::clean_key( (string) ( $definition['id'] ?? $definition['key'] ?? $definition['label'] ?? '' ) );
        $this->label       = trim( (string) ( $definition['label'] ?? $this->id ) );
        $this->description = trim( (string) ( $definition['description'] ?? '' ) );
        $this->callback    = isset( $definition['callback'] ) && is_callable( $definition['callback'] ) ? $definition['callback'] : null;
        $this->context     = is_array( $definition['context'] ?? null ) ? $definition['context'] : [];
        $this->subtasks    = $this->normalize_subtasks( (array) ( $definition['subtasks'] ?? [] ) );

        if ( '' === $this->id ) {
            $this->id = 'step';
        }

        if ( '' === $this->label ) {
            $this->label = $this->id;
        }
    }

    /**
     * @param array<string,mixed>|self $definition
     */
    public static function from( array|self $definition ): self {
        return $definition instanceof self ? $definition : new self( $definition );
    }

    public function has_callback(): bool {
        return is_callable( $this->callback );
    }

    public function has_subtasks(): bool {
        return [] !== $this->subtasks;
    }

    public function find_subtask( string $subtask_id ): ?GettingStartedChecklistSubtask {
        foreach ( $this->subtasks as $subtask ) {
            if ( $subtask->id === $subtask_id ) {
                return $subtask;
            }
        }

        return null;
    }

    /**
     * @return array<string,mixed>
     */
    public function to_public_array(): array {
        return [
            'id'           => $this->id,
            'label'        => $this->label,
            'description'  => $this->description,
            'has_callback' => $this->has_callback(),
            'subtasks'     => array_map(
                static fn( GettingStartedChecklistSubtask $subtask ): array => $subtask->to_public_array(),
                $this->subtasks
            ),
            'context'      => $this->context,
        ];
    }

    /**
     * @param array<int|string,mixed> $subtasks
     * @return array<int,GettingStartedChecklistSubtask>
     */
    private function normalize_subtasks( array $subtasks ): array {
        $normalized = [];
        $seen       = [];

        foreach ( $subtasks as $key => $definition ) {
            if ( ! is_array( $definition ) && ! $definition instanceof GettingStartedChecklistSubtask ) {
                continue;
            }

            if ( is_array( $definition ) && is_string( $key ) && ! isset( $definition['id'] ) ) {
                $definition['id'] = $key;
            }

            $subtask = GettingStartedChecklistSubtask::from( $definition );
            if ( isset( $seen[ $subtask->id ] ) ) {
                continue;
            }

            $normalized[]        = $subtask;
            $seen[ $subtask->id ] = true;
        }

        return $normalized;
    }

    private static function clean_key( string $value ): string {
        return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : ( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?: '' );
    }
}
