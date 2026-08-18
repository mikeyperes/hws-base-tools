<?php

declare( strict_types=1 );

namespace HWS\BaseTools\AcfFields;

use Hexa\PluginCore\FieldStructures\AcfFieldGroupRegistry;
use HWS\BaseTools\PluginRuntime\PluginMetadata;

defined( 'ABSPATH' ) || exit;

final class SharedAcfStructures {
    private static ?AcfFieldGroupRegistry $registry = null;

    public static function registry(): AcfFieldGroupRegistry {
        if ( self::$registry instanceof AcfFieldGroupRegistry ) {
            return self::$registry;
        }

        self::load_definitions();
        self::$registry = new AcfFieldGroupRegistry(
            [
                'option_name'   => 'hws_acf_structure_settings',
                'capability'    => 'manage_options',
                'ajax_action'   => 'hws_save_acf_structure',
                'nonce_action'  => 'hws_acf_structures',
                'nonce_field'   => 'nonce',
                'hook_priority' => 4,
            ]
        );

        foreach ( self::definitions() as $definition ) {
            self::$registry->add( $definition );
        }

        return self::$registry;
    }

    /** @return array<int,array<string,mixed>> */
    private static function definitions(): array {
        return [
            self::definition(
                'brand-assets',
                'Assets',
                'group_hws_brand_assets_gallery',
                '',
                true,
                'hws_base_tools\\hws_brand_assets_gallery_acf_group',
                'HWS Website & Primary Entity settings',
                [ 'Brand — Gallery', 'Banners — Gallery' ]
            ),
            self::definition(
                'website-settings',
                'Website Settings Fields',
                'group_6842076add7ad',
                'register_acf_website_settings',
                false,
                'hws_base_tools\\hws_website_settings_acf_group',
                'HWS Website & Primary Entity settings',
                [ 'Founder', 'Branding', 'Website identity', 'Contact information', 'Content and policy text' ]
            ),
            self::definition(
                'user-profile-2025',
                'User Profile Fields (2025)',
                'group_684252fd99081',
                UserProfile2025Migration::PROFILE_OPTION,
                false,
                'hws_base_tools\\hws_user_profile_2025_group',
                'All WordPress user profiles',
                [ 'Social and profile URLs', 'Subtitle', 'Location', 'Schema markup', 'Photos' ]
            ),
            self::definition(
                'user-additional',
                'Additional User Profile Fields',
                'group_6842_additional_user_fields_2025',
                UserProfile2025Migration::ADDITIONAL_OPTION,
                false,
                'hws_base_tools\\hws_user_additional_fields_group',
                'All WordPress user profiles',
                [ 'Public email', 'Public phone', 'Title', 'Team member', 'Profile type', 'Profile photo', 'Quotes repeater', 'Staff writer', 'Muck Rack fields' ]
            ),
            self::definition(
                'sponsored-posts',
                'Sponsored Content Fields',
                'group_sponsored_field',
                'register_sponsored_functionality',
                false,
                'hws_base_tools\\hws_sponsored_acf_group',
                'Post editors',
                [ 'Sponsored' ]
            ),
            self::definition(
                'rss-structures',
                'RSS Structures',
                'group_66e9ebd79f8e0',
                'enable_custom_rss_functionality',
                false,
                'hws_base_tools\\hws_rss_acf_group',
                'Custom RSS settings',
                [ 'Post Type RSS feeds', 'Category RSS feeds' ]
            ),
        ];
    }

    /** @param array<int,string> $fields @return array<string,mixed> */
    private static function definition(
        string $id,
        string $label,
        string $group_key,
        string $legacy_option,
        bool $default,
        callable|string $provider,
        string $location,
        array $fields
    ): array {
        return [
            'id'              => $id,
            'label'           => $label,
            'description'     => 'Registered through the shared Hexa WP Core while preserving existing field keys and stored values.',
            'group_key'       => $group_key,
            'legacy_option'   => $legacy_option,
            'enabled_default' => $default,
            'definition'      => $provider,
            'location'        => $location,
            'fields'          => $fields,
            'dependencies'    => [ 'Advanced Custom Fields Pro' ],
        ];
    }

    private static function load_definitions(): void {
        static $loaded = false;
        if ( $loaded ) {
            return;
        }
        $loaded = true;
        $root = PluginMetadata::root_path() . '/src/AcfFields';
        foreach ( [ 'user-profile-2025.php', 'legacy-website-settings.php', 'legacy-sponsored-fields.php', 'legacy-rss-fields.php' ] as $file ) {
            require_once $root . '/' . $file;
        }
    }
}
