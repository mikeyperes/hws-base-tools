<?php

declare( strict_types=1 );

namespace HWS\BaseTools\BrandTemplates;

use HWS\BaseTools\PluginRuntime\PluginMetadata;

defined( 'ABSPATH' ) || exit;

final class TemplateLoader {
    private static string $selected_context = '';

    public static function filter( string $template ): string {
        $context = self::current_context();
        if ( '' === $context || ! BrandTemplateSettings::context_enabled( $context ) ) {
            return $template;
        }

        $definition = BrandTemplateRegistry::get( $context );
        if ( null === $definition || empty( $definition['supports_fallback'] ) || '' === (string) $definition['template_file'] ) {
            return $template;
        }
        if ( ElementorTemplateImporter::has_active_elementor_document( $context ) ) {
            return $template;
        }
        if ( BrandTemplateRegistry::PAGE === $context && self::is_individual_elementor_page() ) {
            return $template;
        }

        $fallback = PluginMetadata::root_path() . '/templates/' . basename( (string) $definition['template_file'] );
        if ( ! is_readable( $fallback ) ) {
            return $template;
        }

        self::$selected_context = $context;
        return $fallback;
    }

    public static function selected_context(): string {
        return self::$selected_context;
    }

    public static function current_context(): string {
        if ( is_author() ) {
            return BrandTemplateRegistry::AUTHOR;
        }
        if ( is_category() ) {
            return BrandTemplateRegistry::CATEGORY;
        }
        if ( is_tag() ) {
            return BrandTemplateRegistry::TAG;
        }
        if ( is_page() && ! is_front_page() ) {
            return BrandTemplateRegistry::PAGE;
        }
        if ( is_singular( 'post' ) ) {
            return BrandTemplateRegistry::SINGLE_POST;
        }
        return '';
    }

    private static function is_individual_elementor_page(): bool {
        $post_id = (int) get_queried_object_id();
        if ( $post_id < 1 || 'builder' !== (string) get_post_meta( $post_id, '_elementor_edit_mode', true ) ) {
            return false;
        }
        $data = get_post_meta( $post_id, '_elementor_data', true );
        return is_string( $data ) ? '' !== trim( $data ) && '[]' !== trim( $data ) : ! empty( $data );
    }
}
