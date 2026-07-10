<?php

namespace HWS\BaseTools\FrontendContent;

use HWS\BaseTools\PluginRuntime\PluginMetadata;

final class FeatureLoader {
    /** @var array<string,string> */
    private const FILES_BY_OPTION = [
        'enable_elementor_social_icon_cleanup' => 'snippet-elementor-social-icons.php',
        'enable_footer_text_auto_injection'    => 'snippet-footer-text.php',
        'enable_elementor_queries'             => 'register-elementor-queries.php',
        'enable_seo_amp_no_index'              => 'snippet-seo-amp.php',
        'enable_seo_feeds_no_index'            => 'snippet-seo-rss.php',
        'enable_wp_admin_logo'                 => 'snippet-login-logo.php',
        'disable_rankmath_sitemap_caching'     => 'snippet-rank-math-sitemap.php',
    ];

    public static function load_enabled(): void {
        foreach ( self::FILES_BY_OPTION as $option => $file ) {
            if ( ! get_option( $option, false ) ) {
                continue;
            }

            $path = PluginMetadata::root_path() . '/' . $file;
            if ( is_readable( $path ) ) {
                require_once $path;
            }
        }
    }
}
