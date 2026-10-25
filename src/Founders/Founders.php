<?php

declare( strict_types=1 );

namespace HWS\BaseTools\Founders;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * The website's founders: the one place they are picked (HWS Base Tools → Founders).
 * Separate from the Primary Entity and Website Identity, which read this list.
 *
 * User accounts in order, each with an optional title, under a section title
 * ("Founders" by default), on/off, and a style. Photo, name and bio come from
 * each user's profile. Shown by [hws_publication_founders] (alias [smpi_founders]
 * for pages built with the SMP shortcode); read by hws_publication_founders().
 * Markup and classes: docs/founders.md.
 */
final class Founders implements ModuleInterface {
    public const OPTION    = 'hws_publication_founders';
    /** Section titles offered on the Founders tab; any other text works too. */
    public const TITLE_SUGGESTIONS = [ 'Founders', 'Co-Founders', 'Executives', 'Leadership', 'Owners', 'Publishers', 'Our team' ];
    public const SHORTCODE = 'hws_publication_founders';
    public const ALIAS     = 'smpi_founders';
    public const BLOCK     = 'hws-founders';
    public const ITEM      = 'hws-founder';

    public function register(): void {
        require_once __DIR__ . '/founders-functions.php';
        add_action( 'init', [ self::class, 'register_shortcodes' ], 20 );
    }

    public static function register_shortcodes(): void {
        add_shortcode( self::SHORTCODE, [ self::class, 'shortcode' ] );
        // Older pages use the SMP tag; it keeps the SMP class names so their styling still applies.
        add_shortcode( self::ALIAS, [ self::class, 'alias_shortcode' ] );
    }

    /** @return array{enabled:bool,heading:string,ids:int[],titles:array<int,string>,style:string} */
    public static function settings(): array {
        $saved = get_option( self::OPTION, null );
        if ( ! is_array( $saved ) ) {
            $saved = self::seed();
            update_option( self::OPTION, $saved, true );
        }
        $style  = (string) ( $saved['style'] ?? 'cards' );
        $titles = [];
        foreach ( (array) ( $saved['titles'] ?? [] ) as $id => $title ) {
            $titles[ absint( $id ) ] = sanitize_text_field( (string) $title );
        }
        return [
            'enabled' => ! empty( $saved['enabled'] ),
            'heading' => isset( $saved['heading'] ) ? sanitize_text_field( (string) $saved['heading'] ) : 'Founders',
            'ids'     => array_values( array_unique( array_filter( array_map( 'absint', (array) ( $saved['ids'] ?? [] ) ) ) ) ),
            'titles'  => $titles,
            'style'   => isset( FounderCards::STYLES[ $style ] ) ? $style : 'cards',
            // The Primary Entity, when it is a person, is marked as a founder here.
            'entity_is_founder' => ! empty( $saved['entity_is_founder'] ),
        ];
    }

    /**
     * The Primary Entity's user: [ 'user_id' => int, 'type' => person|organization|publication ], or null.
     *
     * @return array{user_id:int,type:string}|null
     */
    public static function primary_entity(): ?array {
        if ( ! class_exists( \HWS\BaseTools\SiteProfile\PrimaryEntityIntegration::class ) ) {
            return null;
        }
        $entity = \HWS\BaseTools\SiteProfile\PrimaryEntityIntegration::manager()->resolve();
        $id     = is_array( $entity ) ? absint( $entity['object_id'] ?? 0 ) : 0;
        if ( ! $id || 'wordpress_user' !== ( $entity['source'] ?? 'wordpress_user' ) || ! get_userdata( $id ) ) {
            return null;
        }
        return [ 'user_id' => $id, 'type' => (string) ( $entity['entity_type'] ?? '' ) ];
    }

    /** The Primary Entity person's user ID when it is marked as a founder; 0 otherwise (a company never is). */
    public static function entity_founder_id(): int {
        $entity = self::primary_entity();
        return ( $entity && 'person' === $entity['type'] && self::settings()['entity_is_founder'] ) ? $entity['user_id'] : 0;
    }

    /**
     * First read: carry over the founders from where they were picked before,
     * SMP's Founders tab first, then Website Identity's founders field.
     *
     * @return array<string,mixed>
     */
    private static function seed(): array {
        $smp = get_option( 'smpi_founders', null );
        if ( is_array( $smp ) && ! empty( $smp['ids'] ) ) {
            return array_intersect_key( $smp, array_flip( [ 'enabled', 'heading', 'ids', 'titles', 'style' ] ) );
        }
        $identity = get_option( 'options_hws_company_founders', null );
        if ( null === $identity ) {
            $company  = get_option( 'options_hws_company', [] );
            $identity = is_array( $company ) ? ( $company['founders'] ?? [] ) : [];
        }
        $ids = array_values( array_filter( array_map( 'absint', (array) maybe_unserialize( $identity ) ) ) );
        return [ 'enabled' => (bool) $ids, 'heading' => 'Founders', 'ids' => $ids, 'titles' => [], 'style' => 'cards' ];
    }

    /** @param array<string,mixed> $changes */
    public static function update_settings( array $changes ): void {
        update_option( self::OPTION, array_merge( self::settings(), $changes ), true );
    }

    /** Founders added by hand on the Founders tab, in order. A company Primary Entity's user is never one. */
    public static function manual_ids(): array {
        $entity  = self::primary_entity();
        $company = ( $entity && 'person' !== $entity['type'] ) ? $entity['user_id'] : 0;
        return array_values( array_filter( self::settings()['ids'], static fn( int $id ): bool => $id !== $company && (bool) get_userdata( $id ) ) );
    }

    /**
     * Every founder, in order (whether or not the section is on): the Primary Entity
     * person first when marked as a founder, then the founders added by hand.
     */
    public static function user_ids(): array {
        $auto = self::entity_founder_id();
        return array_values( array_unique( array_filter( array_merge( [ $auto ], self::manual_ids() ) ) ) );
    }

    public static function title( int $id ): string {
        return (string) ( self::settings()['titles'][ $id ] ?? '' );
    }

    /**
     * The read API: one row per founder, in order.
     *
     * @return array<int,array{user_id:int,name:string,title:string,url:string,bio:string,photo:string}>
     */
    public static function all(): array {
        $rows = [];
        foreach ( self::user_ids() as $id ) {
            $user   = get_userdata( $id );
            $rows[] = [
                'user_id' => $id,
                'name'    => (string) $user->display_name,
                'title'   => self::title( $id ),
                'url'     => (string) get_author_posts_url( $id ),
                'bio'     => (string) get_the_author_meta( 'description', $id ),
                'photo'   => FounderCards::photo_url( $id, 'medium' ),
            ];
        }
        return $rows;
    }

    public static function edit_url(): string {
        return admin_url( 'options-general.php?page=hws-core-tools&tab=founders' );
    }

    /** @param array<string,mixed>|string $atts */
    public static function shortcode( $atts = [] ): string {
        return self::render( (array) $atts, self::SHORTCODE, self::BLOCK, self::ITEM );
    }

    /** @param array<string,mixed>|string $atts */
    public static function alias_shortcode( $atts = [] ): string {
        return self::render( (array) $atts, self::ALIAS, 'smpi-founders', 'smpi-founder' );
    }

    /** Off: prints nothing (people and titles are kept). */
    public static function render( array $atts = [], string $tag = self::SHORTCODE, string $block = self::BLOCK, string $item = self::ITEM ): string {
        $settings = self::settings();
        if ( ! $settings['enabled'] ) {
            return '';
        }
        // section_title, not heading: "heading" is the tag used for each person's name.
        $atts = shortcode_atts( FounderCards::ATTRIBUTES + [ 'section_title' => $settings['heading'], 'section_tag' => 'h2' ], $atts, $tag );
        $list = FounderCards::render( self::user_ids(), $atts, [ 'block' => $block, 'item' => $item, 'style' => $settings['style'], 'title' => [ self::class, 'title' ] ] );
        if ( '' === $list ) {
            return '';
        }
        $heading = trim( (string) $atts['section_title'] );
        $htag    = in_array( (string) $atts['section_tag'], [ 'h2', 'h3', 'h4', 'p' ], true ) ? (string) $atts['section_tag'] : 'h2';
        $head    = ( '' !== $heading && 'none' !== strtolower( $heading ) ) ? '<' . $htag . ' class="' . esc_attr( $block ) . '__heading">' . esc_html( $heading ) . '</' . $htag . '>' : '';
        return '<section class="' . esc_attr( $block ) . '-section">' . $head . $list . '</section>';
    }
}
