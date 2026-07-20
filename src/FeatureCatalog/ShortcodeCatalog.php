<?php

namespace HWS\BaseTools\FeatureCatalog;

use Hexa\PluginCore\ShortcodeRegistry\ShortcodeDisplayRenderer;
use HWS\BaseTools\FrontendContent\SearchDisplayFeature;
use HWS\BaseTools\TeamMembers\TeamMemberDirectory;

final class ShortcodeCatalog {
    public static function render(): void {
        echo ( new ShortcodeDisplayRenderer() )->render(
            self::definitions(),
            [
                'title'       => 'HWS Base Tools Shortcodes',
                'description' => 'HWS-owned shortcodes with descriptions, real output, parameters, examples, and a concrete test method.',
            ]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function definitions(): array {
        return [
            [
                'id' => TeamMemberDirectory::SHORTCODE,
                'label' => 'Team Member Directory',
                'shortcode' => '[hws_team_members]',
                'description' => 'Displays published HWS Team Member entries through the selected clean directory template.',
                'test_method' => 'Confirm the Team Member CPT and ACF fields are active, then render each style and verify names, positions, images, and responsive layout.',
                'source' => 'src/TeamMembers/TeamMemberDirectory.php',
                'provider' => 'HWS Base Tools',
                'parameters' => [
                    'style' => 'portrait_grid|editorial_list|compact_directory',
                    'featured_only' => 0,
                    'category' => '',
                    'limit' => -1,
                    'columns' => 3,
                    'show_excerpt' => 1,
                    'link_profiles' => 1,
                    'order' => 'ASC',
                    'orderby' => 'menu_order',
                ],
                'examples' => [
                    [ 'label' => 'Selected default', 'shortcode' => '[hws_team_members]', 'parameters' => [] ],
                    [ 'label' => 'Minimal portrait grid', 'shortcode' => '[hws_team_members style="portrait_grid" columns="3"]', 'parameters' => [ 'style' => 'portrait_grid', 'columns' => 3 ] ],
                    [ 'label' => 'Editorial list', 'shortcode' => '[hws_team_members style="editorial_list"]', 'parameters' => [ 'style' => 'editorial_list' ] ],
                    [ 'label' => 'Compact directory', 'shortcode' => '[hws_team_members style="compact_directory"]', 'parameters' => [ 'style' => 'compact_directory' ] ],
                    [ 'label' => 'Featured Team Members', 'shortcode' => '[hws_team_members featured_only="1" limit="6"]', 'parameters' => [ 'featured_only' => 1, 'limit' => 6 ] ],
                ],
            ],
            [
                'id' => SearchDisplayFeature::SHORTCODE,
                'label' => 'Site Search Display',
                'shortcode' => '[hexa_search]',
                'description' => 'Renders the selected Hexa WP Core site-search design and submits to the native WordPress search results URL.',
                'test_method' => 'Render all five styles, submit a query, and confirm WordPress receives it through the native s query parameter. For overlay, also verify click, Cmd/Ctrl+K, Escape, and backdrop close.',
                'source' => 'src/FrontendContent/SearchDisplayFeature.php',
                'provider' => 'HWS Base Tools + Hexa WP Core SearchDisplay',
                'parameters' => [
                    'style' => 'icon-reveal|overlay|pill|underline|command',
                    'accent' => '',
                    'placeholder' => 'Search...',
                    'label' => 'Search',
                    'radius' => '',
                ],
                'examples' => [
                    [ 'label' => 'Saved default', 'shortcode' => '[hexa_search]', 'parameters' => [] ],
                    [ 'label' => 'Icon reveal', 'shortcode' => '[hexa_search style="icon-reveal"]', 'parameters' => [ 'style' => 'icon-reveal' ] ],
                    [ 'label' => 'Overlay', 'shortcode' => '[hexa_search style="overlay" accent="#2f6df6"]', 'parameters' => [ 'style' => 'overlay', 'accent' => '#2f6df6' ] ],
                    [ 'label' => 'Pill', 'shortcode' => '[hexa_search style="pill" placeholder="Search stories..."]', 'parameters' => [ 'style' => 'pill', 'placeholder' => 'Search stories...' ] ],
                    [ 'label' => 'Underline', 'shortcode' => '[hexa_search style="underline"]', 'parameters' => [ 'style' => 'underline' ] ],
                    [ 'label' => 'Command bar', 'shortcode' => '[hexa_search style="command" radius="8"]', 'parameters' => [ 'style' => 'command', 'radius' => 8 ] ],
                ],
            ],
            [
                'id' => 'current_year',
                'label' => 'Current Year',
                'shortcode' => '[current_year]',
                'description' => 'Returns the current four-digit year. The display_year alias remains available for compatibility.',
                'test_method' => 'Render both aliases and confirm they match the current server year.',
                'source' => 'src/FrontendContent/legacy-base-features.php',
                'provider' => 'HWS Base Tools',
                'examples' => [
                    [ 'label' => 'Current', 'shortcode' => '[current_year]', 'parameters' => [] ],
                    [ 'label' => 'Legacy alias', 'shortcode' => '[display_year]', 'parameters' => [] ],
                ],
            ],
            [
                'id' => 'site_logo',
                'label' => 'Site Logo / Brand Asset',
                'shortcode' => '[site_logo key="logo" size="medium"]',
                'description' => 'Renders a configured Brand Assets image without skewing it. Width and height constrain the image while preserving its aspect ratio.',
                'test_method' => 'Render the selected asset, inspect its URL, and confirm rendered dimensions preserve the source aspect ratio.',
                'source' => 'src/BrandAssets/legacy-brand-functions.php',
                'provider' => 'HWS Base Tools',
                'parameters' => [ 'key' => 'logo', 'size' => 'medium', 'width' => '', 'height' => '', 'output' => 'img' ],
                'examples' => [
                    [ 'label' => 'Standard logo', 'shortcode' => '[site_logo key="logo" size="medium"]', 'parameters' => [ 'key' => 'logo', 'size' => 'medium' ] ],
                    [ 'label' => 'Logo with text', 'shortcode' => '[site_logo key="logo_text" size="medium" width="180"]', 'parameters' => [ 'key' => 'logo_text', 'width' => 180 ] ],
                    [ 'label' => 'URL only', 'shortcode' => '[site_logo key="logo" size="full" output="url"]', 'parameters' => [ 'output' => 'url' ] ],
                    [ 'label' => 'Constrained box', 'shortcode' => '[site_logo key="logo" width="180" height="180"]', 'parameters' => [ 'width' => 180, 'height' => 180 ] ],
                ],
            ],
            [
                'id' => 'brand_asset_gallery',
                'label' => 'Brand Asset Gallery',
                'shortcode' => '[brand_asset_gallery size="medium" columns="4"]',
                'description' => 'Renders the saved Brand Assets gallery as a responsive grid, URL list, or attachment-ID list.',
                'test_method' => 'Compare rendered items with the saved gallery and confirm all URLs resolve.',
                'source' => 'src/BrandAssets/legacy-brand-functions.php',
                'provider' => 'HWS Base Tools',
                'parameters' => [ 'size' => 'medium', 'columns' => 4, 'output' => 'grid' ],
                'examples' => [
                    [ 'label' => 'Grid', 'shortcode' => '[brand_asset_gallery size="medium" columns="4"]', 'parameters' => [ 'output' => 'grid' ] ],
                    [ 'label' => 'URLs', 'shortcode' => '[site_gallery output="urls" size="full"]', 'parameters' => [ 'output' => 'urls' ] ],
                    [ 'label' => 'IDs', 'shortcode' => '[brand_asset_gallery output="ids"]', 'parameters' => [ 'output' => 'ids' ] ],
                ],
            ],
            [
                'id' => 'hws_site_value',
                'label' => 'Site Value',
                'shortcode' => '[hws_site_value field="site_name"]',
                'description' => 'Returns a core site value or scalar Website Settings field in text, URL, email, or HTML format.',
                'test_method' => 'Compare output with the matching WordPress option or Website Settings value.',
                'source' => 'src/BrandAssets/legacy-brand-functions.php',
                'provider' => 'HWS Base Tools',
                'parameters' => [ 'field' => 'site_name', 'format' => 'text', 'fallback' => '' ],
                'examples' => [
                    [ 'label' => 'Site name', 'shortcode' => '[hws_site_value field="site_name"]', 'parameters' => [ 'field' => 'site_name' ] ],
                    [ 'label' => 'Home URL', 'shortcode' => '[hws_site_value field="home_url" format="url"]', 'parameters' => [ 'format' => 'url' ] ],
                    [ 'label' => 'Contact email', 'shortcode' => '[hws_site_value field="contact_email" format="email" fallback="admin_email"]', 'parameters' => [ 'fallback' => 'admin_email' ] ],
                ],
            ],
            [
                'id' => 'site_page_template',
                'label' => 'Site Page Template',
                'shortcode' => '[site_page_template type="privacy"]',
                'description' => 'Renders an HWS site-level starter template for a supported critical page type.',
                'test_method' => 'Render a supported type and confirm nested HWS site-value shortcodes resolve.',
                'source' => 'src/BrandAssets/legacy-brand-functions.php',
                'provider' => 'HWS Base Tools',
                'parameters' => [ 'type' => 'privacy' ],
                'examples' => [
                    [ 'label' => 'Privacy', 'shortcode' => '[site_page_template type="privacy"]', 'parameters' => [ 'type' => 'privacy' ] ],
                    [ 'label' => 'Terms', 'shortcode' => '[site_page_template type="terms"]', 'parameters' => [ 'type' => 'terms' ] ],
                    [ 'label' => 'Brand Assets', 'shortcode' => '[site_page_template type="brand_assets"]', 'parameters' => [ 'type' => 'brand_assets' ] ],
                ],
            ],
            [
                'id' => 'website_content',
                'label' => 'Website Settings Content',
                'shortcode' => '[website_content field="website_mission_statement"]',
                'description' => 'Returns one ACF Website Settings option field.',
                'test_method' => 'Compare output with the named field on the Website Settings options page.',
                'source' => 'src/BrandAssets/legacy-brand-functions.php',
                'provider' => 'HWS Base Tools',
                'parameters' => [ 'field' => 'website_mission_statement' ],
                'examples' => [
                    [ 'label' => 'Mission statement', 'shortcode' => '[website_content field="website_mission_statement"]', 'parameters' => [ 'field' => 'website_mission_statement' ] ],
                    [ 'label' => 'Footer text', 'shortcode' => '[website_content field="website_footer_text"]', 'parameters' => [ 'field' => 'website_footer_text' ] ],
                ],
            ],
        ];
    }
}
