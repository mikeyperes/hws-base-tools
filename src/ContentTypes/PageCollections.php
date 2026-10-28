<?php

namespace HWS\BaseTools\ContentTypes;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/** Editorial collections classify existing pages without changing their content or URLs. */
final class PageCollections implements ModuleInterface {
    public const TAXONOMY = 'hws_page_collection';
    public const MAGAZINE_SLUG = 'magazine-issues';
    public const QUERY_ID = 'hws_magazine_issues';

    public function register(): void {
        add_action( 'init', [ self::class, 'register_taxonomy' ] );
        add_action( 'elementor/query/' . self::QUERY_ID, [ self::class, 'magazine_query' ] );
    }

    public static function register_taxonomy(): void {
        register_taxonomy( self::TAXONOMY, [ 'page' ], [
            'labels' => [
                'name'          => __( 'Page Collections', 'hws-base-tools' ),
                'singular_name' => __( 'Page Collection', 'hws-base-tools' ),
                'menu_name'     => __( 'Page Collections', 'hws-base-tools' ),
                'add_new_item'  => __( 'Add New Page Collection', 'hws-base-tools' ),
                'edit_item'     => __( 'Edit Page Collection', 'hws-base-tools' ),
            ],
            'description'       => __( 'Group existing pages for reusable queries. Use the magazine-issues slug for magazine issue pages.', 'hws-base-tools' ),
            'hierarchical'      => true,
            'public'            => false,
            'publicly_queryable'=> false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_quick_edit'=> true,
            'show_in_rest'      => true,
            'rewrite'           => false,
            'query_var'         => false,
            'capabilities'      => [
                'manage_terms' => 'edit_pages',
                'edit_terms'   => 'edit_pages',
                'delete_terms' => 'edit_pages',
                'assign_terms' => 'edit_pages',
            ],
        ] );
    }

    /** Keep the widget's page size, offset and pagination; always select published issues. */
    public static function magazine_query( \WP_Query $query ): void {
        $query->set( 'post_type', 'page' );
        $query->set( 'post_status', 'publish' );
        $query->set( 'ignore_sticky_posts', true );
        $query->set( 'orderby', [ 'date' => 'DESC', 'ID' => 'DESC' ] );
        $query->set( 'order', 'DESC' );
        // Elementor's manual selection must not turn this into a fixed list.
        $query->set( 'post__in', [] );
        $existing = $query->get( 'tax_query' );
        $marker = [
            'taxonomy'         => self::TAXONOMY,
            'field'            => 'slug',
            'terms'            => [ self::MAGAZINE_SLUG ],
            'include_children' => false,
        ];
        $query->set( 'tax_query', empty( $existing )
            ? [ $marker ]
            : [ 'relation' => 'AND', $existing, $marker ] );
    }
}
