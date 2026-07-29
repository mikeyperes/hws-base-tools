<?php

declare( strict_types=1 );

namespace HWS\BaseTools\SiteProfile;

use Hexa\PluginCore\EntitySources\PrimaryEntityManager;
use Hexa\PluginCore\EntitySources\PrimaryEntityModule;
use Hexa\PluginCore\EntitySources\PrimaryEntityRenderer;
use Hexa\PluginCore\FieldStructures\AcfSettingsPanel;

defined( 'ABSPATH' ) || exit;

final class PrimaryEntityIntegration {
    private static ?PrimaryEntityManager $manager = null;

    public static function manager(): PrimaryEntityManager {
        if ( self::$manager instanceof PrimaryEntityManager ) {
            return self::$manager;
        }
        self::$manager = new PrimaryEntityManager(
            [
                'entity_option' => 'hws_primary_entity', 'site_type_option' => 'hws_site_type',
                'site_types' => [
                    'news_outlet' => 'News Outlet', 'podcast_website' => 'Podcast Website', 'personal_website' => 'Personal Website',
                    'company_website' => 'Company Website', 'ecommerce_website' => 'e-Commerce Website', 'other' => 'Other',
                ],
                'site_entity_types' => [
                    'news_outlet' => 'publication', 'podcast_website' => 'publication', 'personal_website' => 'person',
                    'company_website' => 'organization', 'ecommerce_website' => 'organization', 'other' => 'person',
                ],
                'allow_entity_type_selection' => false, 'allow_empty_site_type' => true,
                'site_type_placeholder' => 'Select website type',
                'sources' => [
                    'wordpress_user' => [ 'label' => 'WordPress Author', 'kind' => 'user', 'description' => 'HWS binds only a WordPress user. Verified Profile and Organization relationships remain owned by their respective plugins.' ],
                ],
                'capability' => 'manage_options', 'ajax_action' => 'hws_save_primary_entity',
                'nonce_action' => 'hws_primary_entity', 'nonce_field' => 'nonce',
                'migration_flag' => 'hws_primary_entity_migrated_v1',
                'legacy_resolvers' => [ [ self::class, 'resolve_legacy_entity' ] ],
                'render_args' => [ 'title' => 'Website & Primary Entity', 'consumers' => self::consumers(), 'show_field_inventory' => false ],
            ]
        );
        return self::$manager;
    }

    public static function module(): PrimaryEntityModule {
        return new PrimaryEntityModule( self::manager() );
    }

    public static function website_settings_panel(): AcfSettingsPanel {
        return new AcfSettingsPanel(
            [
                'page_slug' => 'hws-core-tools', 'tab' => 'website-types', 'post_id' => 'option',
                'field_groups' => [ 'group_hws_brand_assets_gallery', 'group_6842076add7ad' ],
                'title' => 'Website Settings Fields',
                'description' => 'Existing Website Settings values remain stored in the same ACF option records. This panel replaces the standalone Website Settings admin page.',
                'submit_value' => 'Save Website Settings', 'updated_message' => 'Website settings saved.',
                'persist_key' => 'hws-website-settings-fields', 'open' => false,
            ]
        );
    }

    public static function render(): void {
        echo ( new PrimaryEntityRenderer() )->render( self::manager() );
        echo self::website_settings_panel()->render();
    }

    /** @return array<int,array<string,mixed>> */
    public static function consumers(): array {
        return [
            [ 'label' => 'SFPF Person Profile', 'description' => 'Consumes a Person website author and keeps its founder-to-Organization relationship inside SFPF.', 'active' => static fn( array $entity ): bool => 'person' === $entity['entity_type'] && self::plugin_active( 'sfpf-person-profile-integration' ) ],
            [ 'label' => 'SMC Organization Profile', 'description' => 'Consumes an Organization website author while Organization records remain owned by SMC.', 'active' => static fn( array $entity ): bool => 'organization' === $entity['entity_type'] && self::plugin_active( 'smc-organization-profile-integration' ) ],
            [ 'label' => 'SMP Publication', 'description' => 'Consumes a News Outlet or Podcast Website author as the publication identity.', 'active' => static fn( array $entity ): bool => 'publication' === $entity['entity_type'] && self::plugin_active( 'smp-publication-integration' ) ],
        ];
    }

    /** @return array<string,mixed> */
    public static function resolve_legacy_entity(): array {
        $site_type = (string) get_option( 'hws_site_type', '' );
        if ( 'news_outlet' === $site_type ) {
            $publication_user = self::acf_option( [ 'smpi_publication_user', 'publication_user' ] );
            $id = self::object_id( $publication_user );
            if ( $id ) return [ 'source' => 'wordpress_user', 'object_id' => $id, 'entity_type' => 'publication', 'migrated_from' => 'SMP publication user' ];
        }

        $founder = self::acf_option( [ 'founder' ] );
        if ( is_array( $founder ) ) {
            $id = self::object_id( $founder['founder_user'] ?? $founder['user'] ?? 0 );
            if ( $id ) return [ 'source' => 'wordpress_user', 'object_id' => $id, 'entity_type' => 'person', 'migrated_from' => 'SFPF founder user' ];
        }

        $website = self::acf_option( [ 'website' ] );
        if ( is_array( $website ) ) {
            $id = self::object_id( $website['company'] ?? 0 );
            if ( $id ) return [ 'source' => 'wordpress_user', 'object_id' => $id, 'entity_type' => 'organization', 'migrated_from' => 'Website company user' ];
        }

        $publication_user = self::acf_option( [ 'smpi_publication_user', 'publication_user' ] );
        $id = self::object_id( $publication_user );
        return $id ? [ 'source' => 'wordpress_user', 'object_id' => $id, 'entity_type' => 'publication', 'migrated_from' => 'SMP publication user' ] : [];
    }

    private static function acf_option( array $names ): mixed {
        if ( ! function_exists( 'get_field' ) ) return null;
        foreach ( $names as $name ) {
            $value = get_field( $name, 'option' );
            if ( null !== $value && false !== $value && '' !== $value && [] !== $value ) return $value;
        }
        return null;
    }

    private static function object_id( mixed $value ): int {
        if ( is_object( $value ) && isset( $value->ID ) ) return (int) $value->ID;
        if ( is_array( $value ) && isset( $value['ID'] ) ) return (int) $value['ID'];
        return is_numeric( $value ) ? (int) $value : 0;
    }

    private static function plugin_active( string $directory ): bool {
        foreach ( (array) get_option( 'active_plugins', [] ) as $plugin ) {
            if ( str_starts_with( (string) $plugin, $directory . '/' ) ) return true;
        }
        return false;
    }
}
