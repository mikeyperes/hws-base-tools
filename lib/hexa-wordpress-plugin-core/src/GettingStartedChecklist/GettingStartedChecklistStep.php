<?php

namespace Hexa\PluginCore\GettingStartedChecklist;

final class GettingStartedChecklistStep {
    public const TYPE_CALLBACK        = 'callback';
    public const TYPE_STATUS_CHECK    = 'status_check';
    public const TYPE_SETUP_ACTION    = 'setup_action';
    public const TYPE_FEATURE_TOGGLE  = 'feature_toggle';
    public const TYPE_CONFIG_MUTATION = 'config_mutation';
    public const TYPE_AJAX_REQUEST    = 'ajax_request';
    public const TYPE_CUSTOM          = 'custom';

    public string $id;

    public string $label;

    public string $description;

    public string $type;

    /**
     * @var callable|null
     */
    public mixed $callback;

    public string $action_label;

    /**
     * @var array<string,mixed>
     */
    public array $request;

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
        $this->type        = self::normalize_type( (string) ( $definition['type'] ?? $definition['kind'] ?? $definition['mode'] ?? self::TYPE_CALLBACK ) );
        $this->callback    = isset( $definition['callback'] ) && is_callable( $definition['callback'] ) ? $definition['callback'] : null;
        $this->action_label = trim( (string) ( $definition['action_label'] ?? self::default_action_label( $this->type ) ) );
        $this->request     = is_array( $definition['request'] ?? null ) ? $definition['request'] : [];
        $this->context     = is_array( $definition['context'] ?? null ) ? $definition['context'] : [];
        $this->subtasks    = $this->normalize_subtasks( (array) ( $definition['subtasks'] ?? [] ) );

        if ( '' === $this->id ) {
            $this->id = 'step';
        }

        if ( '' === $this->label ) {
            $this->label = $this->id;
        }

        if ( '' === $this->action_label ) {
            $this->action_label = self::default_action_label( $this->type );
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
            'type'         => $this->type,
            'action_label' => $this->action_label,
            'request'      => $this->public_request(),
            'has_callback' => $this->has_callback(),
            'subtasks'     => array_map(
                static fn( GettingStartedChecklistSubtask $subtask ): array => $subtask->to_public_array(),
                $this->subtasks
            ),
            'context'      => $this->context,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function to_callback_array(): array {
        $payload            = $this->to_public_array();
        $payload['request'] = $this->request;

        return $payload;
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

    public static function normalize_type( string $value ): string {
        $type = self::clean_key( $value );
        $type = str_replace( '-', '_', $type );

        $aliases = [
            'check'        => self::TYPE_STATUS_CHECK,
            'status'       => self::TYPE_STATUS_CHECK,
            'setup'        => self::TYPE_SETUP_ACTION,
            'action'       => self::TYPE_SETUP_ACTION,
            'toggle'       => self::TYPE_FEATURE_TOGGLE,
            'feature'      => self::TYPE_FEATURE_TOGGLE,
            'config'       => self::TYPE_CONFIG_MUTATION,
            'mutation'     => self::TYPE_CONFIG_MUTATION,
            'ajax'         => self::TYPE_AJAX_REQUEST,
            'request'      => self::TYPE_AJAX_REQUEST,
            'function'     => self::TYPE_CALLBACK,
            'php_callback' => self::TYPE_CALLBACK,
        ];

        if ( isset( $aliases[ $type ] ) ) {
            $type = $aliases[ $type ];
        }

        return in_array( $type, self::allowed_types(), true ) ? $type : self::TYPE_CUSTOM;
    }

    /**
     * @return array<int,string>
     */
    public static function allowed_types(): array {
        return [
            self::TYPE_CALLBACK,
            self::TYPE_STATUS_CHECK,
            self::TYPE_SETUP_ACTION,
            self::TYPE_FEATURE_TOGGLE,
            self::TYPE_CONFIG_MUTATION,
            self::TYPE_AJAX_REQUEST,
            self::TYPE_CUSTOM,
        ];
    }

    public static function default_action_label( string $type ): string {
        return match ( $type ) {
            self::TYPE_STATUS_CHECK => 'Check',
            self::TYPE_FEATURE_TOGGLE, self::TYPE_CONFIG_MUTATION => 'Apply',
            self::TYPE_AJAX_REQUEST => 'Run Request',
            default => 'Run',
        };
    }

    /**
     * @return array<string,mixed>
     */
    private function public_request(): array {
        if ( [] === $this->request ) {
            return [];
        }

        $public = [];
        foreach ( $this->request as $key => $value ) {
            $key_string = is_string( $key ) ? $key : (string) $key;
            if ( preg_match( '/(secret|password|token|key|nonce)/i', $key_string ) ) {
                $public[ $key_string ] = '[redacted]';
                continue;
            }

            $public[ $key_string ] = is_scalar( $value ) || null === $value ? $value : '[' . gettype( $value ) . ']';
        }

        return $public;
    }
}
