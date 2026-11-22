<?php

declare( strict_types=1 );

namespace HWS\BaseTools\ContentTypes\Organization;

use Hexa\PluginCore\ContentTypes\ContentTypeAjaxController;
use Hexa\PluginCore\ContentTypes\ContentTypeRegistrar;
use Hexa\PluginCore\ContentTypes\ContentTypeRegistry;
use Hexa\PluginCore\ContentTypes\ContentTypeSettingsStore;

defined( 'ABSPATH' ) || exit;

/** Owns Organization post-type settings and registration. Feature plugins supply their fields and schema. */
final class OrganizationContentType {
    public const POST_TYPE = 'organization';
    public const SETTINGS_OPTION = 'hws_organization_content_type_settings';
    private const CUSTOM_ARGS_OPTION = 'hws_organization_registration_args';
    private const CONFIG = [
        'option_name' => self::SETTINGS_OPTION,
        'capability' => 'manage_options',
        'ajax_action' => 'hws_save_organization_content_type',
        'nonce_action' => 'hws_organization_content_type',
        'nonce_field' => 'nonce',
    ];
    private static ?int $custom_owner_blog = null;

    /** Bind migration, registration and the existing Core settings UI. */
    public static function register(): void {
        add_action( 'init', [ self::class, 'migrate_person_profile_settings' ], 3 );
        add_action( 'init', [ self::class, 'register_type' ], 4 );
        add_action( 'wp_loaded', [ self::class, 'sync_rewrite_rules' ] );
        add_filter( 'cptui_disable_organization_cpt', [ self::class, 'skip_migrated_cptui_type' ] );
        ( new ContentTypeAjaxController( self::registry(), self::CONFIG ) )->register();
    }

    /** Preserve the currently active legacy settings once in Base Tools. */
    public static function migrate_person_profile_settings(): void {
        $registry = self::registry();
        $owner = $registry->store();
        if ( array_key_exists( self::POST_TYPE, $owner->all() ) ) {
            return;
        }

        $active = (array) get_option( 'active_plugins', [] );
        if ( is_multisite() ) {
            $active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) ) );
        }
        if ( ! in_array( 'sfpf-person-profile-integration/initialization.php', $active, true ) ) {
            return;
        }

        $legacy = new ContentTypeSettingsStore( 'sfpf_content_type_settings' );
        $definition = $registry->definition( self::POST_TYPE );
        $legacy_definition = $definition;
        $legacy_definition['legacy_enabled_option'] = 'smp_enable_cpt_organization';
        if ( ! array_key_exists( self::POST_TYPE, $legacy->all() ) && null === get_option( $legacy_definition['legacy_enabled_option'], null ) ) {
            return;
        }
        $current = $legacy->resolve( $legacy_definition );
        $owner->save( $definition, [
            'enabled' => $current['enabled'],
            'singular' => $current['post_type']['singular'],
            'plural' => $current['post_type']['plural'],
            'rewrite_slug' => $current['post_type']['rewrite_slug'],
        ] );
    }

    /** Register from Base Tools settings independently of any feature plugin. */
    public static function register_type(): void {
        if ( post_type_exists( self::POST_TYPE ) ) {
            return;
        }

        $registry = self::registry();
        $config = $registry->resolved_definitions()[0];
        if ( empty( $config['enabled'] ) ) {
            return;
        }

        $custom = self::custom_args();
        if ( [] === $custom ) {
            ( new ContentTypeRegistrar( $registry ) )->register_post_types();
            return;
        }

        $settings = $config['post_type'];
        $custom['labels']['name'] = $settings['plural'];
        $custom['labels']['singular_name'] = $settings['singular'];
        if ( is_array( $custom['rewrite'] ?? null ) ) {
            $custom['rewrite']['slug'] = $settings['rewrite_slug'];
        }
        if ( register_post_type( self::POST_TYPE, $custom ) instanceof \WP_Post_Type ) {
            self::$custom_owner_blog = get_current_blog_id();
        }
    }

    /** Update the URL cache only on sites with an Organization configuration. */
    public static function sync_rewrite_rules(): void {
        $registry = self::registry();
        if ( array_key_exists( self::POST_TYPE, $registry->store()->all() ) ) {
            $registry->sync_rewrite_rules();
        }
    }

    /** Disable only CPT UI's Organization hook after Base Tools has registered it. */
    public static function skip_migrated_cptui_type( bool $disabled ): bool {
        return $disabled || self::$custom_owner_blog === get_current_blog_id();
    }

    /** Save the exact WordPress arguments captured before removing a CPT UI definition. */
    public static function adopt_cptui_args( array $args ): bool {
        $cptui = get_option( 'cptui_post_types', [] );
        if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! is_array( $cptui ) || ! isset( $cptui[ self::POST_TYPE ] )
            || ! is_array( $args['labels'] ?? null ) || ! isset( $args['labels']['name'], $args['labels']['singular_name'] )
        ) {
            return false;
        }

        $saved = get_option( self::CUSTOM_ARGS_OPTION, null );
        $registry = self::registry();
        if ( null !== $saved ) {
            return $saved === $args && ! empty( $registry->resolved_definitions()[0]['enabled'] );
        }
        if ( array_key_exists( self::POST_TYPE, $registry->store()->all() ) || ! add_option( self::CUSTOM_ARGS_OPTION, $args, '', false ) ) {
            return false;
        }
        $registry = self::registry();
        $state = $registry->store()->save( $registry->definition( self::POST_TYPE ), [
            'enabled' => true,
            'singular' => $args['labels']['singular_name'],
            'plural' => $args['labels']['name'],
            'rewrite_slug' => is_array( $args['rewrite'] ?? null ) ? ( $args['rewrite']['slug'] ?? self::POST_TYPE ) : self::POST_TYPE,
        ] );
        if ( empty( $state['enabled'] ) ) {
            delete_option( self::CUSTOM_ARGS_OPTION );
            return false;
        }
        return true;
    }

    /** Create the single-type registry used by the Base Tools settings tab. */
    public static function registry(): ContentTypeRegistry {
        $custom = self::custom_args();
        $standard = [
        'public' => true, 'publicly_queryable' => true, 'show_ui' => true,
        'show_in_menu' => true, 'show_in_nav_menus' => true, 'show_in_admin_bar' => true,
        'show_in_rest' => true, 'menu_position' => 21, 'menu_icon' => 'dashicons-building',
        'capability_type' => 'post', 'hierarchical' => false,
        'supports' => [ 'title', 'author', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes', 'custom-fields' ],
        'has_archive' => 'organizations', 'rewrite' => [ 'with_front' => false ],
        'query_var' => true, 'taxonomies' => [ 'category' ], 'delete_with_user' => false,
        ];
        return ( new ContentTypeRegistry( self::CONFIG ) )->add( [
            'id' => self::POST_TYPE,
            'owner' => 'HWS Base Tools',
            'description' => 'Configure the shared Organization post type. Its fields and schema are supplied by the organization and person profile integrations.',
            'enabled_default' => false,
            'registration_mode' => [] === $custom ? 'owned' : 'external',
            'retain_post_type_settings' => [] !== $custom,
            'post_type' => [
                'key' => self::POST_TYPE,
                'singular' => (string) ( $custom['labels']['singular_name'] ?? 'Organization' ),
                'plural' => (string) ( $custom['labels']['name'] ?? 'Organizations' ),
                'rewrite_slug' => is_array( $custom['rewrite'] ?? null ) ? ( $custom['rewrite']['slug'] ?? self::POST_TYPE ) : self::POST_TYPE,
                'args' => [] === $custom ? $standard : $custom,
            ],
        ] );
    }

    /** Read adopted arguments without inventing configuration for other sites. */
    private static function custom_args(): array {
        $args = get_option( self::CUSTOM_ARGS_OPTION, [] );
        return is_array( $args ) ? $args : [];
    }
}
