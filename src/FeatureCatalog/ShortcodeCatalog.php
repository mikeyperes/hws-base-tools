<?php

namespace HWS\BaseTools\FeatureCatalog;

use Hexa\PluginCore\ShortcodeRegistry\ShortcodeDisplayRenderer;

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
