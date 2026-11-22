<?php

declare( strict_types=1 );

namespace HWS\BaseTools\ContentTypes\Organization;

use Hexa\PluginCore\ContentTypes\ContentTypeRegistrar;
use Hexa\PluginCore\ContentTypes\ContentTypeRegistry;

defined( 'ABSPATH' ) || exit;

/** Registers Organization from the site's existing settings without changing its fields. */
final class OrganizationContentType {
    public const POST_TYPE = 'organization';
    private const CUSTOM_ARGS_OPTION = 'hws_organization_registration_args';
    private static ?int $custom_owner_blog = null;

    /** Register at the existing Organization init priority, before CPT UI. */
    public static function register(): void {
        add_action( 'init', [ self::class, 'register_type' ], 4 );
        add_filter( 'cptui_disable_organization_cpt', [ self::class, 'skip_migrated_cptui_type' ] );
    }

    /** Tell SFPF whether the active Base Tools provider can serve its current settings. */
    public static function provides_registration(): bool {
        if ( [] !== self::custom_args() ) {
            return true;
        }
        return class_exists( \SFPF\PersonProfile\ContentTypes\Organization\OrganizationContentType::class )
            && ! empty( self::person_registry()->resolved_definitions()[0]['enabled'] );
    }

    /** Use the preserved CPT UI arguments or the existing SFPF settings. */
    public static function register_type(): void {
        if ( post_type_exists( self::POST_TYPE ) ) {
            return;
        }

        $custom = self::custom_args();
        if ( [] !== $custom ) {
            $type = register_post_type( self::POST_TYPE, $custom );
            if ( $type instanceof \WP_Post_Type ) {
                self::$custom_owner_blog = get_current_blog_id();
            }
            return;
        }

        if ( class_exists( \SFPF\PersonProfile\ContentTypes\Organization\OrganizationContentType::class ) ) {
            ( new ContentTypeRegistrar( self::person_registry() ) )->register_post_types();
        }
    }

    /** Disable only CPT UI's Organization hook after Base Tools has registered it. */
    public static function skip_migrated_cptui_type( bool $disabled ): bool {
        return $disabled || self::$custom_owner_blog === get_current_blog_id();
    }

    /** Save the exact WordPress arguments captured before removing a CPT UI definition. */
    public static function adopt_cptui_args( array $args ): bool {
        $cptui = get_option( 'cptui_post_types', [] );
        if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! post_type_exists( self::POST_TYPE )
            || ! is_array( $cptui ) || ! isset( $cptui[ self::POST_TYPE ] )
            || ! is_array( $args['labels'] ?? null ) || ! isset( $args['labels']['name'], $args['labels']['singular_name'] )
        ) {
            return false;
        }

        $saved = get_option( self::CUSTOM_ARGS_OPTION, null );
        if ( null !== $saved ) {
            return $saved === $args;
        }
        return add_option( self::CUSTOM_ARGS_OPTION, $args, '', false );
    }

    /** Read an adopted registration without inventing defaults for other sites. */
    private static function custom_args(): array {
        $args = get_option( self::CUSTOM_ARGS_OPTION, [] );
        return is_array( $args ) ? $args : [];
    }

    /** Read the existing SFPF settings so its current editor controls keep working. */
    private static function person_registry(): ContentTypeRegistry {
        return ( new ContentTypeRegistry( [ 'option_name' => 'sfpf_content_type_settings' ] ) )->add(
            [
                'id' => self::POST_TYPE,
                'owner' => 'HWS Base Tools',
                'enabled_default' => false,
                'legacy_enabled_option' => 'smp_enable_cpt_organization',
                'post_type' => [
                    'key' => self::POST_TYPE,
                    'singular' => 'Organization',
                    'plural' => 'Organizations',
                    'rewrite_slug' => 'organization',
                    'args' => [
                        'public' => true, 'publicly_queryable' => true, 'show_ui' => true,
                        'show_in_menu' => true, 'show_in_nav_menus' => true, 'show_in_admin_bar' => true,
                        'show_in_rest' => true, 'menu_position' => 21, 'menu_icon' => 'dashicons-building',
                        'capability_type' => 'post', 'hierarchical' => false,
                        'supports' => [ 'title', 'author', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes', 'custom-fields' ],
                        'has_archive' => 'organizations', 'rewrite' => [ 'with_front' => false ],
                        'query_var' => true, 'taxonomies' => [ 'category' ], 'delete_with_user' => false,
                    ],
                ],
            ]
        );
    }
}
