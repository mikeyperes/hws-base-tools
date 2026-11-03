<?php
namespace HWS\BaseTools\Sitemaps;

use Hexa\PluginCore\Sitemaps\SitemapProviders;

defined( 'ABSPATH' ) || exit;

/**
 * The sitemaps HWS Base Tools owns: its own content types. Every other HWS
 * plugin declares its sitemaps in its own src/Sitemaps/SitemapTypes.php;
 * the Sitemaps tab shows those read-only with a link to that plugin.
 */
final class SitemapTypes {
    public const SLUG = 'hws-base-tools';

    public static function definitions(): array {
        return [
            [ 'id' => 'post_type_team-member', 'recommended' => static fn() => (bool) get_option( 'smp_enable_cpt_teammember', false ), 'why' => 'On when the Team Members content type is switched on.' ],
            [ 'id' => 'post_type_testimonial', 'recommended' => static fn() => (bool) get_option( 'enable_cpt_testimonial', false ), 'why' => 'On when the Testimonials content type is switched on.' ],
            [ 'id' => 'post_type_services', 'recommended' => static fn() => (bool) get_option( 'hws_enable_cpt_services', false ), 'why' => 'On when the Services content type is switched on.' ],
        ];
    }

    public static function register(): void {
        if ( ! class_exists( SitemapProviders::class ) ) {
            return;
        }
        SitemapProviders::register( self::SLUG, 'HWS Base Tools', admin_url( 'options-general.php?page=hws-core-tools&tab=sitemaps' ), self::definitions() );
    }
}
