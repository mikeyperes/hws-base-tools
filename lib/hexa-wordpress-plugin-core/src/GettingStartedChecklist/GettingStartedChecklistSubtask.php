<?php

namespace Hexa\PluginCore\GettingStartedChecklist;

final class GettingStartedChecklistSubtask {
    public string $id;

    public string $label;

    public string $description;

    /**
     * @var callable|null
     */
    public mixed $callback;

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

        if ( '' === $this->id ) {
            $this->id = 'subtask';
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

    /**
     * @return array<string,mixed>
     */
    public function to_public_array(): array {
        return [
            'id'           => $this->id,
            'label'        => $this->label,
            'description'  => $this->description,
            'has_callback' => $this->has_callback(),
            'context'      => $this->context,
        ];
    }

    private static function clean_key( string $value ): string {
        return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : ( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?: '' );
    }
}
