<?php

declare( strict_types=1 );

namespace HWS\BaseTools\FrontendContent;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\WpAdminComponents\CoreUi;
use HWS\BaseTools\PluginRuntime\PluginMetadata;

defined( 'ABSPATH' ) || exit;

/**
 * Heroes are designed for a landscape featured photo. When a story's photo is portrait or square, this feature marks
 * it with its shape so the hero shows it whole on a soft backdrop instead of zooming in and cutting it.
 *
 * It acts only on an image inside an element carrying the CSS class hws-fi-hero, on the front page and on the single
 * pages of the content types switched on here. The landscape design is untouched; portrait and square are the fallback.
 */
final class FeaturedImageOrientation implements ModuleInterface {
    public const OPTION    = 'hws_featured_image_orientation';
    public const HERO      = 'hws-fi-hero';
    public const TAB       = 'featured-image-orientation';
    private const SAVE     = 'hws_featured_image_orientation_save';

    public function register(): void {
        add_filter( 'wp_get_attachment_image_attributes', [ self::class, 'mark' ], 20, 2 );
        add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue' ], 100 );
        add_action( 'admin_post_' . self::SAVE, [ self::class, 'handle_save' ] );
    }

    /** landscape, portrait or square from a width and height; landscape when either is unknown. */
    public static function shape( int $width, int $height ): string {
        if ( $width <= 0 || $height <= 0 ) {
            return 'landscape';
        }
        $ratio = $width / $height;
        return $ratio > 1.05 ? 'landscape' : ( $ratio < 0.95 ? 'portrait' : 'square' );
    }

    /** Content types whose single page can show a featured image: public, viewable and with thumbnail support. */
    public static function post_types(): array {
        $types = [];
        foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $name => $type ) {
            if ( in_array( $name, [ 'attachment', 'elementor_library', 'e-landing-page', 'e-floating-buttons' ], true ) ) {
                continue;
            }
            if ( ! is_post_type_viewable( $type ) || ! post_type_supports( $name, 'thumbnail' ) ) {
                continue;
            }
            $types[ $name ] = (string) $type->labels->singular_name;
        }
        return $types;
    }

    /**
     * Every switch is on until a site turns it off. Nothing changes on a page until its hero carries hws-fi-hero,
     * so an update never alters a live design.
     *
     * @return array{front_page:bool,post_types:array<string,bool>}
     */
    public static function settings(): array {
        $saved = get_option( self::OPTION, [] );
        $saved = is_array( $saved ) ? $saved : [];
        $types = [];
        foreach ( array_keys( self::post_types() ) as $name ) {
            $types[ $name ] = ! isset( $saved['post_types'][ $name ] ) || (bool) $saved['post_types'][ $name ];
        }
        return [ 'front_page' => ! isset( $saved['front_page'] ) || (bool) $saved['front_page'], 'post_types' => $types ];
    }

    public static function active(): bool {
        if ( is_admin() ) {
            return false;
        }
        $settings = self::settings();
        if ( is_front_page() ) {
            return $settings['front_page'];
        }
        if ( is_singular() ) {
            return ! empty( $settings['post_types'][ (string) get_post_type( get_queried_object_id() ) ] );
        }
        return false;
    }

    /**
     * Mark the featured image of the post being drawn (the page's own, or each story in a front-page list).
     *
     * @param array<string,string> $attr
     * @param \WP_Post             $attachment
     */
    public static function mark( array $attr, $attachment ): array {
        if ( ! is_object( $attachment ) || ! self::active() ) {
            return $attr;
        }
        $post_id = (int) get_the_ID();
        if ( $post_id <= 0 || (int) get_post_thumbnail_id( $post_id ) !== (int) $attachment->ID ) {
            return $attr;
        }
        $meta  = wp_get_attachment_metadata( (int) $attachment->ID );
        $shape = self::shape( (int) ( $meta['width'] ?? 0 ), (int) ( $meta['height'] ?? 0 ) );
        $attr['class'] = trim( ( $attr['class'] ?? '' ) . ' hws-fi-' . $shape );
        return $attr;
    }

    public static function enqueue(): void {
        if ( ! self::active() ) {
            return;
        }
        wp_enqueue_style(
            'hws-featured-image-orientation',
            plugin_dir_url( PluginMetadata::plugin_file() ) . 'assets/frontend/featured-image-orientation.css',
            wp_style_is( 'elementor-frontend', 'registered' ) ? [ 'elementor-frontend' ] : [],
            PluginMetadata::VERSION
        );
    }

    public static function handle_save(): void {
        if ( ! current_user_can( PluginMetadata::ADMIN_CAPABILITY ) ) {
            wp_die( esc_html__( 'You are not allowed to change this setting.', 'hws-base-tools' ), '', [ 'response' => 403 ] );
        }
        check_admin_referer( self::SAVE );
        $posted = isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] ) ? array_map( 'sanitize_key', array_keys( wp_unslash( $_POST['post_types'] ) ) ) : [];
        $types  = [];
        foreach ( array_keys( self::post_types() ) as $name ) {
            $types[ $name ] = in_array( $name, $posted, true );
        }
        update_option( self::OPTION, [ 'front_page' => ! empty( $_POST['front_page'] ), 'post_types' => $types ], false );
        wp_safe_redirect( add_query_arg( [ 'page' => PluginMetadata::ADMIN_PAGE_SLUG, 'tab' => self::TAB, 'saved' => '1' ], admin_url( 'options-general.php' ) ) );
        exit;
    }

    public static function render(): void {
        CoreUi::render_assets();
        $settings = self::settings();
        $css      = (string) file_get_contents( PluginMetadata::root_path() . '/assets/frontend/featured-image-orientation.css' );
        ?>
        <div class="hpc-ui hws-fio-admin">
            <style>
                .hws-fio-admin .hws-fio-samples{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));margin:12px 0 4px;max-width:760px}
                .hws-fio-admin .hws-fio-samples figure{margin:0}.hws-fio-admin figcaption{color:#64748b;font-size:12px;margin-top:6px}
                .hws-fio-admin .hws-fio-photo{display:block}
                <?php echo $css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </style>
            <?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <div class="notice notice-success inline"><p>Featured image orientation saved.</p></div>
            <?php endif; ?>
            <h2>Featured image orientation</h2>
            <p>Heroes are designed for a landscape featured photo. When a story's photo is portrait or square, the hero shows it whole on a soft backdrop instead of zooming in and cutting it. It applies only to a hero whose image widget carries the CSS class <code><?php echo esc_html( self::HERO ); ?></code> (add <code>hws-fi-hero--3x2</code> for a 3:2 box; the default box is 16:9).</p>
            <div class="hws-fio-samples">
                <?php foreach ( [ 'landscape' => [ 1600, 900, 'Landscape (the design)' ], 'portrait' => [ 768, 1024, 'Portrait (fallback)' ], 'square' => [ 900, 900, 'Square (fallback)' ] ] as $shape => [ $w, $h, $label ] ) : ?>
                    <figure>
                        <div class="<?php echo esc_attr( self::HERO ); ?>"><div class="elementor-widget-container"><img class="hws-fio-photo hws-fi-<?php echo esc_attr( $shape ); ?>" width="<?php echo (int) $w; ?>" height="<?php echo (int) $h; ?>" alt="" src="<?php echo esc_attr( self::sample_svg( $w, $h ) ); ?>"></div></div>
                        <figcaption><?php echo esc_html( $label ); ?></figcaption>
                    </figure>
                <?php endforeach; ?>
            </div>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE ); ?>">
                <?php wp_nonce_field( self::SAVE ); ?>
                <h3>Front page</h3>
                <p><?php echo CoreUi::toggle( 'front_page', $settings['front_page'], 'Lead story and other heroes on the front page' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
                <h3>Single pages</h3>
                <?php foreach ( self::post_types() as $name => $label ) : ?>
                    <p><?php echo CoreUi::toggle( 'post_types[' . $name . ']', ! empty( $settings['post_types'][ $name ] ), $label . ' (' . $name . ')' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
                <?php endforeach; ?>
                <p><button type="submit" class="button button-primary">Save</button></p>
            </form>
        </div>
        <?php
    }

    private static function sample_svg( int $w, int $h ): string {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' ' . $h . '"><defs><linearGradient id="g" x2="1" y2="1"><stop offset="0" stop-color="#9fb8ad"/><stop offset="1" stop-color="#2f5d50"/></linearGradient></defs><rect width="100%" height="100%" fill="url(#g)"/><circle cx="50%" cy="42%" r="' . (int) ( min( $w, $h ) * 0.18 ) . '" fill="#f3efe6"/></svg>';
        return 'data:image/svg+xml;charset=utf-8,' . rawurlencode( $svg );
    }
}
