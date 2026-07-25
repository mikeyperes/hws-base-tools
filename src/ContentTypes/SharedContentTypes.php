<?php

declare( strict_types=1 );

namespace HWS\BaseTools\ContentTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Canonical HWS ownership for content types shared by profile plugins.
 */
final class SharedContentTypes {
    public const ORGANIZATION = 'organization';
    public const TESTIMONIAL = 'testimonial';
    public const TEAM_MEMBER = 'team-member';

    private const OPTIONS = [
        self::ORGANIZATION => 'smp_enable_cpt_organization',
        self::TESTIMONIAL  => 'enable_cpt_testimonial',
        self::TEAM_MEMBER  => 'smp_enable_cpt_teammember',
    ];

    private static bool $booted = false;

    public static function boot(): void {
        if ( self::$booted ) {
            return;
        }

        self::$booted = true;
        add_action( 'init', [ self::class, 'register_enabled' ], 0 );
    }

    public static function register_enabled(): void {
        foreach ( array_keys( self::definitions() ) as $post_type ) {
            if ( self::is_enabled( $post_type ) ) {
                self::register_type( $post_type );
            }
        }
    }

    public static function register_type( string $post_type ): bool {
        $definitions = self::definitions();

        if ( ! isset( $definitions[ $post_type ] ) || post_type_exists( $post_type ) ) {
            return false;
        }

        $result = register_post_type( $post_type, $definitions[ $post_type ] );

        return ! is_wp_error( $result );
    }

    public static function is_enabled( string $post_type ): bool {
        $option = self::option_for( $post_type );

        return null !== $option && (bool) get_option( $option, false );
    }

    public static function enable( string $post_type ): bool {
        $option = self::option_for( $post_type );

        if ( null === $option ) {
            return false;
        }

        if ( (bool) get_option( $option, false ) ) {
            return true;
        }

        return update_option( $option, 1, false );
    }

    public static function option_for( string $post_type ): ?string {
        return self::OPTIONS[ $post_type ] ?? null;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function definitions(): array {
        $definitions = [
            self::ORGANIZATION => self::definition(
                'Organization',
                'Organizations',
                [
                    'menu_icon'     => 'dashicons-building',
                    'menu_position' => 21,
                    'supports'      => [ 'title', 'author', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes', 'custom-fields' ],
                    'has_archive'   => 'organizations',
                    'rewrite'       => [ 'slug' => 'organization', 'with_front' => false ],
                ]
            ),
            self::TESTIMONIAL => self::definition(
                'Testimonial',
                'Testimonials',
                [
                    'menu_icon'     => 'dashicons-format-quote',
                    'menu_position' => 22,
                    'supports'      => [ 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields' ],
                    'has_archive'   => 'testimonials',
                    'rewrite'       => [ 'slug' => 'testimonial', 'with_front' => false ],
                ]
            ),
            self::TEAM_MEMBER => self::definition(
                'Team Member',
                'Team Members',
                [
                    'menu_icon'     => 'dashicons-groups',
                    'menu_position' => 23,
                    'supports'      => [ 'title', 'author', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes', 'custom-fields' ],
                    'has_archive'   => 'team',
                    'rewrite'       => [ 'slug' => 'team-member', 'with_front' => false ],
                ]
            ),
        ];

        $filtered = apply_filters( 'hws_base_tools_shared_content_type_definitions', $definitions );

        return is_array( $filtered ) ? $filtered : $definitions;
    }

    /**
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    private static function definition( string $singular, string $plural, array $overrides ): array {
        return array_replace(
            [
                'labels'             => self::labels( $singular, $plural ),
                'public'             => true,
                'publicly_queryable' => true,
                'show_ui'            => true,
                'show_in_menu'       => true,
                'show_in_nav_menus'  => true,
                'show_in_admin_bar'  => true,
                'show_in_rest'       => true,
                'capability_type'    => 'post',
                'hierarchical'       => false,
                'query_var'          => true,
                'taxonomies'         => [ 'category' ],
                'delete_with_user'   => false,
            ],
            $overrides
        );
    }

    /**
     * @return array<string,string>
     */
    private static function labels( string $singular, string $plural ): array {
        $singular_lower = strtolower( $singular );
        $plural_lower = strtolower( $plural );

        return [
            'name'                     => $plural,
            'singular_name'            => $singular,
            'menu_name'                => $plural,
            'all_items'                => 'All ' . $plural,
            'edit_item'                => 'Edit ' . $singular,
            'view_item'                => 'View ' . $singular,
            'view_items'               => 'View ' . $plural,
            'add_new_item'             => 'Add New ' . $singular,
            'add_new'                  => 'Add New',
            'new_item'                 => 'New ' . $singular,
            'parent_item_colon'        => 'Parent ' . $singular . ':',
            'search_items'             => 'Search ' . $plural,
            'not_found'                => 'No ' . $plural_lower . ' found',
            'not_found_in_trash'       => 'No ' . $plural_lower . ' found in Trash',
            'archives'                 => $singular . ' Archives',
            'attributes'               => $singular . ' Attributes',
            'insert_into_item'         => 'Insert into ' . $singular_lower,
            'uploaded_to_this_item'    => 'Uploaded to this ' . $singular_lower,
            'filter_items_list'        => 'Filter ' . $plural_lower . ' list',
            'filter_by_date'           => 'Filter ' . $plural_lower . ' by date',
            'items_list_navigation'    => $plural . ' list navigation',
            'items_list'               => $plural . ' list',
            'item_published'           => $singular . ' published.',
            'item_published_privately' => $singular . ' published privately.',
            'item_reverted_to_draft'   => $singular . ' reverted to draft.',
            'item_scheduled'           => $singular . ' scheduled.',
            'item_updated'             => $singular . ' updated.',
            'item_link'                => $singular . ' Link',
            'item_link_description'    => 'A link to a ' . $singular_lower . '.',
        ];
    }
}
