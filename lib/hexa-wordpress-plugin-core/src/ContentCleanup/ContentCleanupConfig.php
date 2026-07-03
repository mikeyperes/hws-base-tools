<?php

namespace Hexa\PluginCore\ContentCleanup;

final class ContentCleanupConfig {
    private array $values;

    public function __construct( array $values = [] ) {
        $defaults = [
            'root_id'                => 'hpc-content-cleanup',
            'title'                  => 'Content Cleanup',
            'description'            => 'Detect stale content, review the page list, and clean up old WordPress records through guarded AJAX actions.',
            'capability'             => 'manage_options',
            'nonce_action'           => 'hpc_content_cleanup',
            'nonce_field'            => 'nonce',
            'scan_action'            => 'hpc_content_cleanup_scan',
            'trash_action'           => 'hpc_content_cleanup_trash',
            'delete_action'          => 'hpc_content_cleanup_delete',
            'post_types'             => [ 'page' => 'Pages' ],
            'statuses'               => [
                'publish' => 'Published',
                'draft'   => 'Draft',
                'private' => 'Private',
                'pending' => 'Pending',
                'trash'   => 'Trash',
                'any'     => 'Any visible status',
            ],
            'default_post_type'      => 'page',
            'default_status'         => 'publish',
            'default_published_days' => 365,
            'default_modified_days'  => 0,
            'default_limit'          => 50,
            'max_limit'              => 250,
            'exclude_protected'      => false,
            'protected_post_ids'     => [],
            'empty_message'          => 'No matching old pages were detected for the selected filters.',
        ];

        $values = array_merge( $defaults, $values );
        $values['root_id']                = $this->clean_html_id( (string) $values['root_id'] );
        $values['nonce_field']            = $this->clean_key( (string) $values['nonce_field'] );
        $values['scan_action']            = $this->clean_key( (string) $values['scan_action'] );
        $values['trash_action']           = $this->clean_key( (string) $values['trash_action'] );
        $values['delete_action']          = $this->clean_key( (string) $values['delete_action'] );
        $values['default_published_days'] = max( 0, (int) $values['default_published_days'] );
        $values['default_modified_days']  = max( 0, (int) $values['default_modified_days'] );
        $values['default_limit']          = max( 1, (int) $values['default_limit'] );
        $values['max_limit']              = max( 1, (int) $values['max_limit'] );
        $values['post_types']             = $this->normalize_options( (array) $values['post_types'], [ 'page' => 'Pages' ] );
        $values['statuses']               = $this->normalize_options( (array) $values['statuses'], [ 'publish' => 'Published' ] );
        $values['default_post_type']      = $this->clean_key( (string) $values['default_post_type'] );
        $values['default_status']         = $this->clean_key( (string) $values['default_status'] );

        if ( ! isset( $values['post_types'][ $values['default_post_type'] ] ) ) {
            $values['default_post_type'] = array_key_first( $values['post_types'] );
        }

        if ( ! isset( $values['statuses'][ $values['default_status'] ] ) ) {
            $values['default_status'] = array_key_first( $values['statuses'] );
        }

        $this->values = $values;
    }

    public function get( string $key, mixed $default = null ): mixed {
        return array_key_exists( $key, $this->values ) ? $this->values[ $key ] : $default;
    }

    public function root_id(): string {
        return (string) $this->get( 'root_id' );
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

    public function scan_action(): string {
        return (string) $this->get( 'scan_action' );
    }

    public function trash_action(): string {
        return (string) $this->get( 'trash_action' );
    }

    public function delete_action(): string {
        return (string) $this->get( 'delete_action' );
    }

    public function post_types(): array {
        return (array) $this->get( 'post_types', [] );
    }

    public function statuses(): array {
        return (array) $this->get( 'statuses', [] );
    }

    public function default_criteria(): array {
        return [
            'post_type'             => (string) $this->get( 'default_post_type', 'page' ),
            'status'                => (string) $this->get( 'default_status', 'publish' ),
            'published_before_days' => (int) $this->get( 'default_published_days', 365 ),
            'modified_before_days'  => (int) $this->get( 'default_modified_days', 0 ),
            'search'                => '',
            'limit'                 => (int) $this->get( 'default_limit', 50 ),
        ];
    }

    public function max_limit(): int {
        return (int) $this->get( 'max_limit', 250 );
    }

    public function exclude_protected(): bool {
        return (bool) $this->get( 'exclude_protected', false );
    }

    public function protected_post_ids(): array {
        $ids = $this->get( 'protected_post_ids', [] );
        if ( is_callable( $ids ) ) {
            $ids = call_user_func( $ids );
        }

        return array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
    }

    private function normalize_options( array $options, array $fallback ): array {
        $normalized = [];
        foreach ( $options as $key => $label ) {
            $key = $this->clean_key( is_string( $key ) ? $key : (string) $label );
            if ( '' === $key ) {
                continue;
            }
            $normalized[ $key ] = is_scalar( $label ) ? (string) $label : $key;
        }

        return [] !== $normalized ? $normalized : $fallback;
    }

    private function clean_key( string $value ): string {
        return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : ( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?: '' );
    }

    private function clean_html_id( string $value ): string {
        return function_exists( 'sanitize_html_class' ) ? sanitize_html_class( $value ) : ( preg_replace( '/[^a-zA-Z0-9_\-]/', '-', $value ) ?: 'hpc-content-cleanup' );
    }
}
