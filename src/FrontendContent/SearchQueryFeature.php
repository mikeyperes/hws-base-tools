<?php

namespace HWS\BaseTools\FrontendContent;

use Hexa\PluginCore\SearchQuery\SearchQueryConfiguration;
use Hexa\PluginCore\SearchQuery\SearchQueryEngine;
use Hexa\PluginCore\SearchQuery\JetEngineSearchAdapter;

final class SearchQueryFeature {
    public const OPTION_KEY = 'hws_search_behavior';
    public const QUERY_VAR = 'hexa_search';

    private static ?SearchQueryEngine $engine = null;
    private static ?JetEngineSearchAdapter $jet_engine_adapter = null;

    /** @return array<string,mixed> */
    public static function settings(): array {
        $stored = get_option( self::OPTION_KEY, [] );

        return self::sanitize_settings( is_array( $stored ) ? $stored : [] );
    }

    /**
     * @param array<string,mixed> $settings
     * @param array<string,string>|null $post_types
     * @param array<string,string>|null $taxonomies
     * @return array<string,mixed>
     */
    public static function sanitize_settings( array $settings, ?array $post_types = null, ?array $taxonomies = null ): array {
        $post_types = $post_types ?? self::post_type_choices();
        $taxonomies = $taxonomies ?? self::taxonomy_choices();

        if ( isset( $settings['custom_fields'] ) && is_string( $settings['custom_fields'] ) ) {
            $settings['custom_fields'] = preg_split( '/[\s,]+/', $settings['custom_fields'] ) ?: [];
        }

        return SearchQueryConfiguration::normalize( $settings, $post_types, $taxonomies );
    }

    /** @return array<string,string> */
    public static function post_type_choices(): array {
        $choices = [ 'post' => 'Posts', 'page' => 'Pages' ];
        if ( ! function_exists( 'get_post_types' ) ) {
            return $choices;
        }

        $objects = get_post_types( [ 'public' => true ], 'objects' );
        if ( ! is_array( $objects ) ) {
            return $choices;
        }

        foreach ( $objects as $name => $object ) {
            $name = sanitize_key( (string) $name );
            if ( '' === $name || 'attachment' === $name || ! is_object( $object ) || ! empty( $object->exclude_from_search ) ) {
                continue;
            }
            $label = isset( $object->labels->name ) ? (string) $object->labels->name : (string) ( $object->label ?? $name );
            $choices[ $name ] = '' !== trim( $label ) ? $label : $name;
        }

        $fixed = array_intersect_key( $choices, [ 'post' => true, 'page' => true ] );
        $custom = array_diff_key( $choices, $fixed );
        natcasesort( $custom );

        return $fixed + $custom;
    }

    /** @return array<string,string> */
    public static function taxonomy_choices(): array {
        if ( ! function_exists( 'get_taxonomies' ) ) {
            return [ 'category' => 'Categories', 'post_tag' => 'Tags' ];
        }

        $choices = [];
        $objects = get_taxonomies( [ 'public' => true ], 'objects' );
        if ( is_array( $objects ) ) {
            foreach ( $objects as $name => $object ) {
                $name = sanitize_key( (string) $name );
                if ( '' === $name || 'post_format' === $name || ! is_object( $object ) ) {
                    continue;
                }
                $label = isset( $object->labels->name ) ? (string) $object->labels->name : (string) ( $object->label ?? $name );
                $choices[ $name ] = '' !== trim( $label ) ? $label : $name;
            }
        }
        natcasesort( $choices );

        return $choices;
    }

    /** @return array<string,string> */
    public static function marker_fields(): array {
        return [ self::QUERY_VAR => '1' ];
    }

    public static function register_query_engine(): void {
        if ( self::$engine || ! class_exists( SearchQueryEngine::class ) ) {
            return;
        }

        self::$engine = new SearchQueryEngine( [ self::class, 'settings' ], self::QUERY_VAR );
        self::$engine->register();

        if ( class_exists( JetEngineSearchAdapter::class ) ) {
            self::$jet_engine_adapter = new JetEngineSearchAdapter( [ self::class, 'settings' ], self::QUERY_VAR );
            self::$jet_engine_adapter->register();
        }
    }

    /** @return array<int,array<string,string>> */
    public static function research_audit(): array {
        return [
            [
                'plugin'   => 'AJAX Search Lite',
                'version'  => '4.14.4',
                'matching' => 'AND/OR, exact-word variants, full phrase, start/end/full-field locations',
                'sources'  => 'CPTs, title, content, excerpt, permalink, ID, terms, selected or all custom fields',
                'lesson'   => 'Separate term logic from match location and keep expensive sources opt-in.',
            ],
            [
                'plugin'   => 'Ivory Search / Add Search to Menu',
                'version'  => '5.5.16',
                'matching' => 'AND/OR, whole, begins, ends, partial, fuzzy, stop words, synonyms',
                'sources'  => 'Post fields, CPTs, terms, authors, comments, custom fields, media, WooCommerce',
                'lesson'   => 'Per-form scope and field selection are useful; unrestricted joins are not a safe default.',
            ],
            [
                'plugin'   => 'Relevanssi',
                'version'  => '4.27.2',
                'matching' => 'AND/OR, quoted phrases, fuzzy partial matching, TF-IDF relevance',
                'sources'  => 'Indexed posts/CPTs, comments, taxonomies, custom fields, shortcode content',
                'lesson'   => 'An index enables advanced relevance but can require roughly three times the wp_posts table size.',
            ],
            [
                'plugin'   => 'Advanced Woo Search',
                'version'  => '3.67',
                'matching' => 'Partial/exact, stemming, fuzzy correction, synonyms, stop words, weighted relevance',
                'sources'  => 'Products, title, content, excerpt, SKU, ID, categories, tags, variations',
                'lesson'   => 'Commerce-specific indexing is powerful but should remain outside a general site-search engine.',
            ],
            [
                'plugin'   => 'WP Extended Search',
                'version'  => '2.2.1',
                'matching' => 'AND/OR terms and exact/partial matching',
                'sources'  => 'Title, content, excerpt, public post types, meta keys, taxonomies, authors',
                'lesson'   => 'This is the closest lightweight model; query hooks still need stronger request and object scoping.',
            ],
        ];
    }
}
