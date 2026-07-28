<?php

declare( strict_types=1 );

namespace HWS\BaseTools\BrandTemplates;

use HWS\BaseTools\PluginRuntime\PluginMetadata;

defined( 'ABSPATH' ) || exit;

final class ElementorTemplateImporter {
    public const META_CONTEXT = '_hws_brand_template_context';
    public const META_CONTENT_HASH = '_hws_brand_template_content_hash';
    public const META_VERSION = '_hws_brand_template_version';

    /** @return array{available:bool,errors:string[]} */
    public static function availability( string $context ): array {
        $errors = [];
        if ( ! class_exists( '\Elementor\Plugin' ) || ! post_type_exists( 'elementor_library' ) ) {
            $errors[] = 'Elementor is not active.';
        }
        if ( ! class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
            $errors[] = 'Elementor Pro Theme Builder is not active.';
        }

        if ( $errors ) {
            return [ 'available' => false, 'errors' => $errors ];
        }

        $widgets = array_keys( \Elementor\Plugin::instance()->widgets_manager->get_widget_types() );
        foreach ( self::required_widgets( $context ) as $widget ) {
            if ( ! in_array( $widget, $widgets, true ) ) {
                $errors[] = sprintf( 'Required Elementor widget "%s" is unavailable.', $widget );
            }
        }

        $dynamic_tags = array_keys( \Elementor\Plugin::instance()->dynamic_tags->get_tags() );
        foreach ( self::required_dynamic_tags( $context ) as $tag ) {
            if ( ! in_array( $tag, $dynamic_tags, true ) ) {
                $errors[] = sprintf( 'Required Elementor dynamic tag "%s" is unavailable.', $tag );
            }
        }
        if ( BrandTemplateRegistry::get( $context ) && ! defined( 'RANK_MATH_VERSION' ) && ! function_exists( 'rank_math' ) ) {
            $errors[] = 'Rank Math is unavailable, so its breadcrumb shortcode cannot be rendered.';
        }

        return [ 'available' => ! $errors, 'errors' => $errors ];
    }

    /** @return array<string,mixed> */
    public static function status( string $context ): array {
        $context = sanitize_key( $context );
        $definition = BrandTemplateRegistry::get( $context );
        $post_id = self::find_managed_template( $context );
        $availability = self::availability( $context );
        $post = $post_id > 0 ? get_post( $post_id ) : null;
        $conditions = $post_id > 0 ? get_post_meta( $post_id, '_elementor_conditions', true ) : [];
        $conditions = is_array( $conditions ) ? array_values( $conditions ) : [];
        $conflicts = $definition ? self::conflicts( $context, $post_id ) : [];

        return [
            'context'          => $context,
            'definition'       => $definition,
            'enabled'          => $definition ? BrandTemplateSettings::context_enabled( $context ) : false,
            'available'        => $availability['available'],
            'errors'           => $availability['errors'],
            'template_id'      => $post_id,
            'post_status'      => $post instanceof \WP_Post ? $post->post_status : '',
            'conditions'       => $conditions,
            'conflicts'        => $conflicts,
            'customized'       => $post_id > 0 && self::is_customized( $post_id ),
            'backup_count'     => $post_id > 0 ? BrandTemplateBackupStore::count( $post_id ) : 0,
            'editor_url'       => $post_id > 0 ? self::editor_url( $post_id ) : '',
            'example_url'      => self::example_url( $context ),
            'active'           => $post instanceof \WP_Post && 'publish' === $post->post_status && ! empty( $conditions ),
        ];
    }

    /** @return array{success:bool,code:string,message:string,template_id:int} */
    public static function import( string $context, bool $replace = false ): array {
        $context = sanitize_key( $context );
        $definition = BrandTemplateRegistry::get( $context );
        if ( null === $definition || empty( $definition['supports_import'] ) ) {
            return self::result( false, 'invalid_context', 'This template type cannot be imported.' );
        }

        $availability = self::availability( $context );
        if ( ! $availability['available'] ) {
            return self::result( false, 'missing_dependency', implode( ' ', $availability['errors'] ) );
        }

        $elements = ElementorStructureFactory::elements( $context );
        if ( ! $elements ) {
            return self::result( false, 'empty_structure', 'The Elementor structure is empty.' );
        }

        $json = wp_json_encode( $elements, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        if ( ! is_string( $json ) || '' === $json ) {
            return self::result( false, 'encode_failed', 'The Elementor structure could not be encoded.' );
        }

        $post_id = self::find_managed_template( $context );
        if ( $post_id > 0 && self::is_customized( $post_id ) && ! $replace ) {
            return self::result(
                false,
                'managed_template_edited',
                'This managed template was edited in Elementor. Use the explicit replace action to preserve conflict safety.',
                $post_id
            );
        }

        if ( $post_id > 0 ) {
            BrandTemplateBackupStore::create( $post_id, $replace ? 'Replace managed Elementor design' : 'Refresh managed Elementor design' );
        } else {
            $inserted = wp_insert_post(
                [
                    'post_type'   => 'elementor_library',
                    'post_status' => 'draft',
                    'post_title'  => (string) $definition['template_title'],
                ],
                true
            );
            if ( is_wp_error( $inserted ) ) {
                return self::result( false, 'insert_failed', $inserted->get_error_message() );
            }
            $post_id = (int) $inserted;
        }

        $preview_id = self::preview_id( $context );
        $settings = ElementorStructureFactory::document_settings( $context, $preview_id );
        $elementor_version = defined( 'ELEMENTOR_VERSION' ) ? (string) ELEMENTOR_VERSION : '';
        $elementor_pro_version = defined( 'ELEMENTOR_PRO_VERSION' ) ? (string) ELEMENTOR_PRO_VERSION : '';

        wp_set_object_terms( $post_id, (string) $definition['elementor_type'], 'elementor_library_type', false );
        update_post_meta( $post_id, self::META_CONTEXT, $context );
        update_post_meta( $post_id, '_elementor_template_type', (string) $definition['elementor_type'] );
        update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
        update_post_meta( $post_id, '_elementor_data', wp_slash( $json ) );
        update_post_meta( $post_id, '_elementor_page_settings', $settings );
        update_post_meta( $post_id, '_wp_page_template', (string) ( $settings['page_template'] ?? 'default' ) );
        update_post_meta( $post_id, '_elementor_version', $elementor_version );
        update_post_meta( $post_id, '_elementor_pro_version', $elementor_pro_version );
        update_post_meta( $post_id, self::META_CONTENT_HASH, self::content_hash( $json ) );
        update_post_meta( $post_id, self::META_VERSION, PluginMetadata::VERSION );

        $sync = self::synchronize_context( $context, false );
        self::refresh_elementor( $post_id );

        if ( ! $sync['success'] ) {
            return self::result( false, (string) $sync['code'], (string) $sync['message'], $post_id );
        }

        $conflicts = self::conflicts( $context, $post_id );
        if ( BrandTemplateSettings::context_enabled( $context ) && $conflicts ) {
            return self::result(
                true,
                'imported_with_conflict',
                'The Elementor template was imported as a draft because an existing Theme Builder condition already owns this context.',
                $post_id
            );
        }

        return self::result(
            true,
            BrandTemplateSettings::context_enabled( $context ) ? 'imported_active' : 'imported_draft',
            BrandTemplateSettings::context_enabled( $context )
                ? 'The Elementor template was imported and activated.'
                : 'The Elementor template was imported as a draft. Enable its runtime default to activate it.',
            $post_id
        );
    }

    /** @return array<string,array{success:bool,code:string,message:string,template_id:int}> */
    public static function synchronize_all(): array {
        $results = [];
        foreach ( BrandTemplateRegistry::contexts() as $context ) {
            $results[ $context ] = self::synchronize_context( $context );
        }
        return $results;
    }

    /** @return array{success:bool,code:string,message:string,template_id:int} */
    public static function synchronize_context( string $context, bool $backup = true ): array {
        $context = sanitize_key( $context );
        $definition = BrandTemplateRegistry::get( $context );
        $post_id = self::find_managed_template( $context );
        if ( null === $definition || $post_id < 1 ) {
            return self::result( true, 'fallback_only', 'No managed Elementor template requires synchronization.', $post_id );
        }

        $conflicts = self::conflicts( $context, $post_id );
        $enabled = BrandTemplateSettings::context_enabled( $context );
        $should_activate = $enabled && ! $conflicts;
        $desired_conditions = $should_activate ? self::conditions( $definition ) : [];
        $desired_status = $should_activate ? 'publish' : 'draft';
        $current_conditions = get_post_meta( $post_id, '_elementor_conditions', true );
        $current_conditions = is_array( $current_conditions ) ? array_values( $current_conditions ) : [];
        $current_status = (string) get_post_status( $post_id );

        if ( $current_conditions === $desired_conditions && $current_status === $desired_status ) {
            return self::result( true, 'unchanged', 'The managed Elementor template is already synchronized.', $post_id );
        }

        if ( $backup ) {
            BrandTemplateBackupStore::create( $post_id, 'Synchronize template activation' );
        }

        $updated = wp_update_post( [ 'ID' => $post_id, 'post_status' => $desired_status ], true );
        if ( is_wp_error( $updated ) ) {
            return self::result( false, 'status_update_failed', $updated->get_error_message(), $post_id );
        }
        if ( ! self::apply_conditions( $post_id, $desired_conditions ) ) {
            wp_update_post( [ 'ID' => $post_id, 'post_status' => 'draft' ] );
            return self::result( false, 'condition_update_failed', 'Elementor Theme Builder conditions could not be synchronized.', $post_id );
        }

        self::refresh_elementor( $post_id );
        if ( $enabled && $conflicts ) {
            return self::result( true, 'conflict', 'The managed template remains a draft because an existing template owns the same condition.', $post_id );
        }
        return self::result( true, $should_activate ? 'activated' : 'deactivated', $should_activate ? 'The managed template is active.' : 'The managed template is inactive.', $post_id );
    }

    /** @return array{success:bool,code:string,message:string,template_id:int} */
    public static function restore_latest( string $context ): array {
        $context = sanitize_key( $context );
        $post_id = self::find_managed_template( $context );
        if ( $post_id < 1 ) {
            return self::result( false, 'missing_template', 'No managed Elementor template exists for this context.' );
        }
        $snapshot = BrandTemplateBackupStore::latest( $post_id );
        if ( null === $snapshot ) {
            return self::result( false, 'missing_backup', 'No managed-template backup is available.', $post_id );
        }
        $elementor_data = BrandTemplateBackupStore::elementor_data( $snapshot );
        if ( null === $elementor_data ) {
            return self::result( false, 'invalid_backup', 'The latest backup contains invalid Elementor JSON and was not applied.', $post_id );
        }

        BrandTemplateBackupStore::create( $post_id, 'State before restoring backup' );
        wp_update_post(
            [
                'ID'          => $post_id,
                'post_status' => 'draft',
                'post_title'  => sanitize_text_field( (string) ( $snapshot['post']['post_title'] ?? get_the_title( $post_id ) ) ),
            ]
        );

        $terms = isset( $snapshot['terms'] ) && is_array( $snapshot['terms'] ) ? $snapshot['terms'] : [];
        wp_set_object_terms( $post_id, array_map( 'sanitize_key', $terms ), 'elementor_library_type', false );
        $meta = isset( $snapshot['meta'] ) && is_array( $snapshot['meta'] ) ? $snapshot['meta'] : [];
        $meta['_elementor_data'] = $elementor_data;
        foreach ( $meta as $key => $value ) {
            $key = sanitize_key( (string) $key );
            if ( '' === $key || BrandTemplateBackupStore::META_KEY === $key ) {
                continue;
            }
            if ( '' === $value || [] === $value ) {
                delete_post_meta( $post_id, $key );
                continue;
            }
            update_post_meta( $post_id, $key, '_elementor_data' === $key && is_string( $value ) ? wp_slash( $value ) : $value );
        }
        update_post_meta( $post_id, self::META_CONTEXT, $context );

        $sync = self::synchronize_context( $context, false );
        self::refresh_elementor( $post_id );
        if ( ! $sync['success'] ) {
            return self::result( false, (string) $sync['code'], (string) $sync['message'], $post_id );
        }
        return self::result( true, 'restored', 'The latest managed-template backup was restored.', $post_id );
    }

    public static function find_managed_template( string $context ): int {
        $ids = get_posts(
            [
                'post_type'        => 'elementor_library',
                'post_status'      => 'any',
                'posts_per_page'   => 1,
                'orderby'          => 'ID',
                'order'            => 'DESC',
                'fields'           => 'ids',
                'meta_key'         => self::META_CONTEXT,
                'meta_value'       => sanitize_key( $context ),
                'suppress_filters' => true,
            ]
        );
        return $ids ? (int) $ids[0] : 0;
    }

    /** @return array<int,array{template_id:int,template_title:string,edit_url:string}> */
    public static function conflicts( string $context, int $ignore_post_id = 0 ): array {
        $definition = BrandTemplateRegistry::get( $context );
        if ( null === $definition ) {
            return [];
        }

        try {
            if ( class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
                $manager = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager();
                $conflicts = $manager->get_conditions_conflicts_by_location(
                    (string) $definition['condition'],
                    (string) $definition['elementor_location'],
                    $ignore_post_id ?: null
                );
                if ( is_array( $conflicts ) ) {
                    return array_values( $conflicts );
                }
            }
        } catch ( \Throwable ) {
            // Fall through to the database-backed inspection below.
        }

        $conflicts = [];
        $ids = get_posts(
            [
                'post_type'        => 'elementor_library',
                'post_status'      => 'publish',
                'posts_per_page'   => -1,
                'fields'           => 'ids',
                'suppress_filters' => true,
            ]
        );
        foreach ( $ids as $id ) {
            $id = (int) $id;
            if ( $id === $ignore_post_id ) {
                continue;
            }
            $conditions = get_post_meta( $id, '_elementor_conditions', true );
            if ( ! is_array( $conditions ) || ! in_array( (string) $definition['condition'], $conditions, true ) ) {
                continue;
            }
            $conflicts[] = [
                'template_id'    => $id,
                'template_title' => get_the_title( $id ),
                'edit_url'       => self::editor_url( $id ),
            ];
        }
        return $conflicts;
    }

    public static function has_active_elementor_document( string $context ): bool {
        $definition = BrandTemplateRegistry::get( $context );
        if ( null === $definition || ! class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
            return false;
        }
        try {
            $documents = \ElementorPro\Modules\ThemeBuilder\Module::instance()
                ->get_conditions_manager()
                ->get_documents_for_location( (string) $definition['elementor_location'] );
            return ! empty( $documents );
        } catch ( \Throwable ) {
            return false;
        }
    }

    public static function editor_url( int $post_id ): string {
        return add_query_arg( [ 'post' => $post_id, 'action' => 'elementor' ], admin_url( 'post.php' ) );
    }

    private static function is_customized( int $post_id ): bool {
        $stored_hash = (string) get_post_meta( $post_id, self::META_CONTENT_HASH, true );
        $data = (string) get_post_meta( $post_id, '_elementor_data', true );
        if ( '' === $stored_hash ) {
            return '' !== trim( $data );
        }
        return ! hash_equals( $stored_hash, self::content_hash( $data ) );
    }

    private static function content_hash( string $data ): string {
        $decoded = json_decode( $data, true );
        $canonical = is_array( $decoded ) ? wp_json_encode( $decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : $data;
        return hash( 'sha256', (string) $canonical );
    }

    /** @param array<string,mixed> $definition
     *  @return string[]
     */
    private static function conditions( array $definition ): array {
        $conditions = $definition['conditions'] ?? [ $definition['condition'] ?? '' ];
        return array_values( array_filter( array_map( 'strval', is_array( $conditions ) ? $conditions : [] ) ) );
    }

    /** @param string[] $conditions */
    private static function apply_conditions( int $post_id, array $conditions ): bool {
        try {
            if ( class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
                $parsed = array_map( [ self::class, 'parse_condition' ], $conditions );
                \ElementorPro\Modules\ThemeBuilder\Module::instance()
                    ->get_conditions_manager()
                    ->save_conditions( $post_id, $parsed );
            } elseif ( $conditions ) {
                update_post_meta( $post_id, '_elementor_conditions', $conditions );
            } else {
                delete_post_meta( $post_id, '_elementor_conditions' );
            }
        } catch ( \Throwable ) {
            return false;
        }

        $saved = get_post_meta( $post_id, '_elementor_conditions', true );
        $saved = is_array( $saved ) ? array_values( $saved ) : [];
        return $saved === array_values( $conditions );
    }

    /** @return array{type:string,name:string,sub_name:string,sub_id:string} */
    private static function parse_condition( string $condition ): array {
        [ $type, $name, $sub_name, $sub_id ] = array_pad( explode( '/', $condition ), 4, '' );
        return compact( 'type', 'name', 'sub_name', 'sub_id' );
    }

    private static function refresh_elementor( int $post_id ): void {
        delete_post_meta( $post_id, '_elementor_css' );
        try {
            if ( class_exists( '\Elementor\Plugin' ) ) {
                \Elementor\Plugin::instance()->files_manager->clear_cache();
            }
            if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
                ( new \Elementor\Core\Files\CSS\Post( $post_id ) )->update();
            }
        } catch ( \Throwable ) {
            // Elementor will regenerate the deleted CSS cache on the next render.
        }
    }

    /** @return string[] */
    private static function required_widgets( string $context ): array {
        return match ( sanitize_key( $context ) ) {
            BrandTemplateRegistry::AUTHOR => [ 'shortcode', 'image', 'heading', 'text-editor', 'archive-posts' ],
            BrandTemplateRegistry::PAGE => [ 'shortcode', 'heading', 'theme-post-content' ],
            BrandTemplateRegistry::SINGLE_POST => [ 'shortcode', 'heading', 'post-info', 'theme-post-featured-image', 'theme-post-content' ],
            BrandTemplateRegistry::CATEGORY, BrandTemplateRegistry::TAG => [ 'shortcode', 'heading', 'text-editor', 'archive-posts' ],
            default => [],
        };
    }

    /** @return string[] */
    private static function required_dynamic_tags( string $context ): array {
        return match ( sanitize_key( $context ) ) {
            BrandTemplateRegistry::AUTHOR => [ 'author-profile-picture', 'author-name', 'author-info' ],
            BrandTemplateRegistry::PAGE, BrandTemplateRegistry::SINGLE_POST => [ 'post-title' ],
            BrandTemplateRegistry::CATEGORY, BrandTemplateRegistry::TAG => [ 'archive-title', 'archive-description' ],
            default => [],
        };
    }

    private static function preview_id( string $context ): int {
        if ( BrandTemplateRegistry::PAGE === $context ) {
            $front_page = (int) get_option( 'page_on_front', 0 );
            $ids = get_posts( [ 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'post__not_in' => $front_page ? [ $front_page ] : [] ] );
            return $ids ? (int) $ids[0] : 0;
        }
        if ( BrandTemplateRegistry::SINGLE_POST === $context ) {
            $ids = get_posts( [ 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids' ] );
            return $ids ? (int) $ids[0] : 0;
        }
        if ( BrandTemplateRegistry::AUTHOR === $context ) {
            $users = get_users( [ 'number' => 1, 'orderby' => 'post_count', 'order' => 'DESC', 'fields' => 'ID' ] );
            return $users ? (int) $users[0] : 0;
        }
        $taxonomy = BrandTemplateRegistry::CATEGORY === $context ? 'category' : 'post_tag';
        $terms = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => true, 'number' => 1, 'orderby' => 'count', 'order' => 'DESC', 'fields' => 'ids' ] );
        return ! is_wp_error( $terms ) && $terms ? (int) $terms[0] : 0;
    }

    private static function example_url( string $context ): string {
        $preview_id = self::preview_id( $context );
        if ( $preview_id < 1 ) {
            return '';
        }
        if ( BrandTemplateRegistry::AUTHOR === $context ) {
            return (string) get_author_posts_url( $preview_id );
        }
        if ( in_array( $context, [ BrandTemplateRegistry::CATEGORY, BrandTemplateRegistry::TAG ], true ) ) {
            $url = get_term_link( $preview_id, BrandTemplateRegistry::CATEGORY === $context ? 'category' : 'post_tag' );
            return is_wp_error( $url ) ? '' : (string) $url;
        }
        return (string) get_permalink( $preview_id );
    }

    /** @return array{success:bool,code:string,message:string,template_id:int} */
    private static function result( bool $success, string $code, string $message, int $template_id = 0 ): array {
        return compact( 'success', 'code', 'message', 'template_id' );
    }
}
