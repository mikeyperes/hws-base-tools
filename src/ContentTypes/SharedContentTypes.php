<?php

declare( strict_types=1 );

namespace HWS\BaseTools\ContentTypes;

use Hexa\PluginCore\ContentTypes\ContentTypeRegistrar;
use Hexa\PluginCore\ContentTypes\ContentTypeRegistry;

defined( 'ABSPATH' ) || exit;

final class SharedContentTypes {
    /**
     * @deprecated Organization is registered by SFPF Person Profile Integration.
     *             The constant remains for integrations that reference the key.
     */
    public const ORGANIZATION = 'organization';
    public const TESTIMONIAL = 'testimonial';
    public const TEAM_MEMBER = 'team-member';
    public const SERVICES = 'services';

    private const OPTIONS = [
        self::TESTIMONIAL  => 'enable_cpt_testimonial',
        self::TEAM_MEMBER  => 'smp_enable_cpt_teammember',
        self::SERVICES     => 'hws_enable_cpt_services',
    ];

    private static ?ContentTypeRegistry $registry = null;
    private static bool $booted = false;
    private static bool $migrating = false;

    public static function boot(): void {
        if ( self::$booted ) {
            return;
        }
        self::$booted = true;
        self::load_definition_files();
        self::migrate_legacy_settings();
        self::registry()->register();
    }

    public static function registry(): ContentTypeRegistry {
        if ( self::$registry instanceof ContentTypeRegistry ) {
            return self::$registry;
        }

        self::load_definition_files();
        self::$registry = new ContentTypeRegistry(
            [
                'option_name'   => 'hws_content_type_settings',
                'capability'    => 'manage_options',
                'ajax_action'   => 'hws_save_content_type',
                'nonce_action'  => 'hws_content_types',
                'nonce_field'   => 'nonce',
                'hook_priority' => 0,
            ]
        );
        self::$registry->add_many( self::core_definitions() );
        self::migrate_legacy_settings();
        return self::$registry;
    }

    public static function register_enabled(): void {
        ( new ContentTypeRegistrar( self::registry() ) )->register_post_types();
    }

    public static function register_type( string $post_type ): bool {
        if ( ! self::enable( $post_type ) ) {
            return false;
        }
        self::register_enabled();
        return post_type_exists( $post_type );
    }

    public static function is_enabled( string $post_type ): bool {
        $definition = self::registry()->definition( $post_type );
        return $definition ? ! empty( self::registry()->store()->resolve( $definition )['enabled'] ) : false;
    }

    public static function enable( string $post_type ): bool {
        $definition = self::registry()->definition( $post_type );
        if ( ! $definition ) {
            return false;
        }
        $resolved = self::registry()->store()->resolve( $definition );
        self::registry()->store()->save(
            $definition,
            [
                'enabled' => true, 'singular' => $resolved['post_type']['singular'], 'plural' => $resolved['post_type']['plural'],
                'rewrite_slug' => $resolved['post_type']['rewrite_slug'],
                'enabled_field_groups' => array_column( array_filter( $resolved['field_groups'], static fn( array $group ): bool => ! empty( $group['enabled'] ) ), 'id' ),
            ]
        );
        $legacy = self::option_for( $post_type );
        if ( $legacy ) {
            update_option( $legacy, 1, false );
        }
        return true;
    }

    public static function option_for( string $post_type ): ?string {
        return self::OPTIONS[ $post_type ] ?? null;
    }

    /** @return array<string,array<string,mixed>> */
    public static function definitions(): array {
        $definitions = [];
        foreach ( self::registry()->resolved_definitions() as $definition ) {
            $post = $definition['post_type'];
            $args = $post['args'];
            $args['rewrite'] = false === ( $args['rewrite'] ?? true ) ? false : array_merge( (array) ( $args['rewrite'] ?? [] ), [ 'slug' => $post['rewrite_slug'] ] );
            $args['labels'] = array_merge( (array) ( $args['labels'] ?? [] ), [ 'name' => $post['plural'], 'singular_name' => $post['singular'] ] );
            $definitions[ $post['key'] ] = $args;
        }
        return apply_filters( 'hws_base_tools_shared_content_type_definitions', $definitions );
    }

    /** @return array<int,array<string,mixed>> */
    private static function core_definitions(): array {
        return [
            self::definition( self::TEAM_MEMBER, 'Team Member', 'Team Members', 'team-member', 'Reusable team and leadership profiles.', [
                'menu_icon' => 'dashicons-groups', 'menu_position' => 23,
                'supports' => [ 'title', 'author', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes', 'custom-fields' ],
                'has_archive' => 'team', 'rewrite' => [ 'with_front' => false ],
            ], [
                [
                    'id' => 'team-member-fields', 'label' => 'Team Member Fields', 'group_key' => 'group_64b3a05760b1a',
                    'description' => 'Position, featured state, profile metadata, and team display controls.',
                    'definition' => static fn(): array => \hws_base_tools\enable_smp_acf_teammember(),
                    'fields' => [ 'Position', 'Featured', 'Profile and team display fields' ], 'dependencies' => [ 'Advanced Custom Fields Pro' ],
                    'legacy_option' => 'smp_enable_acf_teammember',
                    'enabled_default' => (bool) get_option( 'smp_enable_acf_teammember', false ),
                ],
            ] ),
            self::definition( self::TESTIMONIAL, 'Testimonial', 'Testimonials', 'testimonial', 'Reusable testimonial and endorsement records.', [
                'menu_icon' => 'dashicons-format-quote', 'menu_position' => 22,
                'supports' => [ 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields' ],
                'has_archive' => 'testimonials', 'rewrite' => [ 'with_front' => false ],
            ], [
                [
                    'id' => 'testimonial-fields', 'label' => 'Testimonial Fields', 'group_key' => 'group_64c2177b44137',
                    'description' => 'Testimonial attribution and reusable display metadata.',
                    'definition' => static fn(): array => \hws_base_tools\enable_acf_testimonial(),
                    'fields' => [ 'Testimonial attribution', 'Display metadata' ], 'dependencies' => [ 'Advanced Custom Fields Pro' ],
                    'legacy_option' => 'enable_acf_testimonial',
                    'enabled_default' => (bool) get_option( 'enable_acf_testimonial', false ),
                ],
            ] ),
            self::definition( self::SERVICES, 'Service', 'Services', 'services', 'Service records for company and professional websites.', [
                'menu_icon' => 'dashicons-admin-tools', 'menu_position' => 24,
                'supports' => [ 'title', 'author', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes', 'custom-fields' ],
                'has_archive' => false, 'rewrite' => [ 'with_front' => false ],
            ] ),
        ];
    }

    /** @param array<string,mixed> $args @param array<int,array<string,mixed>> $field_groups @return array<string,mixed> */
    private static function definition( string $key, string $singular, string $plural, string $slug, string $description, array $args, array $field_groups = [] ): array {
        return [
            'id' => $key, 'owner' => 'HWS Base Tools', 'description' => $description,
            'legacy_enabled_option' => self::OPTIONS[ $key ],
            'enabled_default' => (bool) get_option( self::OPTIONS[ $key ], false ),
            'post_type' => [
                'key' => $key, 'singular' => $singular, 'plural' => $plural, 'rewrite_slug' => $slug,
                'args' => array_replace( [
                    'public' => true, 'publicly_queryable' => true, 'show_ui' => true, 'show_in_menu' => true,
                    'show_in_nav_menus' => true, 'show_in_admin_bar' => true, 'show_in_rest' => true,
                    'capability_type' => 'post', 'hierarchical' => false, 'query_var' => true,
                    'taxonomies' => [ 'category' ], 'delete_with_user' => false,
                ], $args ),
            ],
            'field_groups' => $field_groups,
        ];
    }

    private static function load_definition_files(): void {
        static $loaded = false;
        if ( $loaded ) return;
        $loaded = true;
        foreach ( [ 'register-acf-team-member.php', 'register-acf-testimonial.php' ] as $file ) {
            require_once dirname( __DIR__ ) . '/AcfFields/LegacySmp/' . $file;
        }
    }

    private static function migrate_legacy_settings(): void {
        if ( self::$migrating || get_option( 'hws_content_type_settings_migrated_v1', false ) ) {
            return;
        }
        self::$migrating = true;
        try {
            $registry = self::registry();
            foreach ( $registry->definitions() as $definition ) {
                $resolved = $registry->store()->resolve( $definition );
                $enabled_groups = [];
                foreach ( $resolved['field_groups'] as $group ) {
                    if ( ! empty( $group['enabled_default'] ) ) $enabled_groups[] = $group['id'];
                }
                $registry->store()->save( $definition, [
                    'enabled' => (bool) get_option( self::OPTIONS[ $definition['id'] ], $definition['enabled_default'] ),
                    'singular' => $resolved['post_type']['singular'], 'plural' => $resolved['post_type']['plural'],
                    'rewrite_slug' => $resolved['post_type']['rewrite_slug'], 'enabled_field_groups' => $enabled_groups,
                ] );
            }
            update_option( 'hws_content_type_settings_migrated_v1', 1, false );
        } finally {
            self::$migrating = false;
        }
    }
}
