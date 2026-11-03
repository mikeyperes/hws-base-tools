<?php

declare( strict_types=1 );
namespace HWS\BaseTools\SeoSettings;
use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\WpAdminComponents\CoreUi;
use HWS\BaseTools\PluginRuntime\PluginMetadata;
defined( 'ABSPATH' ) || exit;

/** Opt-in per-post-type editorial fallbacks. Existing SEO descriptions win. */
final class DescriptionFallback implements ModuleInterface {
    public const OPTION = 'hws_base_tools_description_fallbacks';
    public const ACTION = 'hws_base_tools_description_fallbacks_save';
    public function register(): void {
        add_filter( 'rank_math/frontend/description', [ self::class, 'filter' ], 20 );
        add_action( 'admin_post_' . self::ACTION, [ self::class, 'save' ] );
    }
    public static function sanitize( array $rules ): array {
        $clean = [];
        foreach ( $rules as $type => $rule ) {
            if ( ! is_array( $rule ) || ! sanitize_key( (string) $type ) ) { continue; }
            $sources = [];
            foreach ( (array) ( $rule['sources'] ?? [] ) as $source ) {
                if ( is_string( $source ) && ( 'excerpt' === $source || preg_match( '/^meta:[a-zA-Z0-9_-]+$/D', $source ) ) ) { $sources[] = $source; }
            }
            $clean[ sanitize_key( (string) $type ) ] = [
                'enabled' => ! empty( $rule['enabled'] ), 'sources' => $sources,
                'template' => sanitize_text_field( (string) ( $rule['template'] ?? '' ) ),
                'limit' => max( 80, min( 220, (int) ( $rule['limit'] ?? 160 ) ) ),
            ];
        }
        return $clean;
    }
    public static function rules(): array { return self::sanitize( (array) get_option( self::OPTION, [] ) ); }
    public static function filter( $description ): string {
        if ( ! is_singular() || '' !== trim( wp_strip_all_tags( (string) $description ) ) ) { return (string) $description; }
        $generated = self::generate( (int) get_queried_object_id() );
        return '' !== $generated ? $generated : (string) $description;
    }
    /** Also available to schema producers so the editorial text has one owner. */
    public static function generate( int $id, int $limit = 0 ): string {
        $rule = self::rules()[ get_post_type( $id ) ] ?? null;
        if ( ! $id || ! is_array( $rule ) || empty( $rule['enabled'] ) ) { return ''; }
        $text = '';
        foreach ( $rule['sources'] as $source ) {
            $value = 'excerpt' === $source ? get_post_field( 'post_excerpt', $id ) : get_post_meta( $id, substr( $source, 5 ), true );
            if ( ! is_scalar( $value ) ) { continue; }
            $value = html_entity_decode( wp_strip_all_tags( strip_shortcodes( (string) $value ), true ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
            $value = trim( (string) preg_replace( '/\s+/u', ' ', $value ) );
            if ( '' !== $value ) { $text = $value; break; }
        }
        if ( '' === $text ) {
            $title = trim( wp_strip_all_tags( (string) get_the_title( $id ) ) );
            if ( '' === $title ) { return ''; }
            $text = strtr( $rule['template'], [ '{title}' => $title, '{site_name}' => get_bloginfo( 'name' ) ] );
        }
        $limit = $limit ? max( 80, min( 220, $limit ) ) : $rule['limit'];
        $length = function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
        if ( $length <= $limit ) { return $text; }
        $trimmed = wp_html_excerpt( $text, $limit - 1, '' );
        $word_safe = preg_replace( '/\s+\S*$/u', '', $trimmed );
        if ( is_string( $word_safe ) && '' !== trim( $word_safe ) ) { $trimmed = trim( $word_safe ); }
        return rtrim( $trimmed, " \t\n\r\0\x0B,;:-" ) . '…';
    }
    public static function save(): void {
        if ( ! current_user_can( PluginMetadata::ADMIN_CAPABILITY ) ) { wp_die( 'Permission denied.', '', [ 'response' => 403 ] ); }
        check_admin_referer( self::ACTION );
        $rules = json_decode( wp_unslash( (string) ( $_POST['rules'] ?? '' ) ), true );
        if ( ! is_array( $rules ) ) { wp_die( 'Description rules must be a JSON object.' ); }
        update_option( self::OPTION, self::sanitize( $rules ), false );
        do_action( 'litespeed_purge_all' );
        wp_safe_redirect( admin_url( 'options-general.php?page=' . PluginMetadata::ADMIN_PAGE_SLUG . '&tab=seo' ) ); exit;
    }
    public static function render(): void {
        $body = '<p>Optional JSON object keyed by post type. Each rule accepts enabled, ordered sources (meta:field_name or excerpt), template ({title}, {site_name}), and limit (80 to 220). Only fills an empty Rank Math description. Disabled until configured.</p>';
        $body .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
        $body .= wp_nonce_field( self::ACTION, '_wpnonce', true, false );
        $body .= '<label for="hws-description-rules">Description rules</label><textarea id="hws-description-rules" name="rules" rows="12" class="large-text code">' . esc_textarea( (string) wp_json_encode( (object) self::rules(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) . '</textarea><p><button class="hpc-button" type="submit">Save description fallbacks</button></p></form>';
        echo CoreUi::card( [ 'title' => 'Search description fallbacks', 'body_html' => $body ] );
    }
}
