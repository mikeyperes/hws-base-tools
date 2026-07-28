<?php

declare( strict_types=1 );

namespace HWS\BaseTools\BrandTemplates;

defined( 'ABSPATH' ) || exit;

final class BrandTemplateRegistry {
    public const AUTHOR = 'author';
    public const PAGE = 'page';
    public const SINGLE_POST = 'single_post';
    public const CATEGORY = 'category';
    public const TAG = 'tag';

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function definitions(): array {
        return [
            self::AUTHOR => [
                'label'             => 'Default Author Archive',
                'description'       => 'Author identity, biography, and a current-query article grid.',
                'option'            => 'author_enabled',
                'template_file'     => 'author.php',
                'elementor_type'    => 'archive',
                'elementor_location'=> 'archive',
                'condition'         => 'include/archive/author',
                'conditions'        => [ 'include/archive/author' ],
                'preview_type'      => 'archive/author',
                'template_title'    => 'HWS Default Author Archive',
                'supports_import'   => true,
                'supports_fallback' => true,
                'frontend_context'  => 'author',
            ],
            self::PAGE => [
                'label'             => 'Default Page Template',
                'description'       => 'Rank Math breadcrumbs, one dynamic page title, and native page content.',
                'option'            => 'page_enabled',
                'template_file'     => 'page.php',
                'elementor_type'    => 'single-page',
                'elementor_location'=> 'single',
                'condition'         => 'include/singular/page',
                'conditions'        => [ 'include/singular/page', 'exclude/singular/front_page' ],
                'preview_type'      => 'singular/page',
                'template_title'    => 'HWS Default Page',
                'supports_import'   => true,
                'supports_fallback' => true,
                'frontend_context'  => 'page',
            ],
            self::SINGLE_POST => [
                'label'             => 'Default Single Post',
                'description'       => 'A restrained article shell that preserves dynamic post content and media.',
                'option'            => 'single_post_enabled',
                'template_file'     => '',
                'elementor_type'    => 'single-post',
                'elementor_location'=> 'single',
                'condition'         => 'include/singular/post',
                'conditions'        => [ 'include/singular/post' ],
                'preview_type'      => 'single/post',
                'template_title'    => 'HWS Default Single Post',
                'supports_import'   => true,
                'supports_fallback' => false,
                'frontend_context'  => 'single_post',
            ],
            self::CATEGORY => [
                'label'             => 'Default Category Archive',
                'description'       => 'Rank Math breadcrumbs, the dynamic category title, description, and archive posts.',
                'option'            => 'category_enabled',
                'template_file'     => 'category.php',
                'elementor_type'    => 'archive',
                'elementor_location'=> 'archive',
                'condition'         => 'include/archive/category',
                'conditions'        => [ 'include/archive/category' ],
                'preview_type'      => 'archive/category',
                'template_title'    => 'HWS Default Category Archive',
                'supports_import'   => true,
                'supports_fallback' => true,
                'frontend_context'  => 'category',
            ],
            self::TAG => [
                'label'             => 'Default Tag Archive',
                'description'       => 'Rank Math breadcrumbs, the dynamic tag title, description, and archive posts.',
                'option'            => 'tag_enabled',
                'template_file'     => 'tag.php',
                'elementor_type'    => 'archive',
                'elementor_location'=> 'archive',
                'condition'         => 'include/archive/post_tag',
                'conditions'        => [ 'include/archive/post_tag' ],
                'preview_type'      => 'archive/post_tag',
                'template_title'    => 'HWS Default Tag Archive',
                'supports_import'   => true,
                'supports_fallback' => true,
                'frontend_context'  => 'tag',
            ],
        ];
    }

    /** @return array<string,array<string,mixed>> */
    public static function all(): array {
        return self::definitions();
    }

    /** @return array<string,mixed>|null */
    public static function get( string $context ): ?array {
        $definitions = self::definitions();
        return $definitions[ sanitize_key( $context ) ] ?? null;
    }

    /** @return string[] */
    public static function contexts(): array {
        return array_keys( self::definitions() );
    }

    /** @return string[] */
    public static function option_keys(): array {
        $keys = [ 'page_content_styles_enabled', 'single_content_styles_enabled' ];
        foreach ( self::definitions() as $definition ) {
            $keys[] = (string) $definition['option'];
        }
        return array_values( array_unique( $keys ) );
    }
}
