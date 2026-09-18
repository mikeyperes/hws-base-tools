<?php

declare( strict_types=1 );

namespace {
    define( 'ABSPATH', dirname( __DIR__ ) . '/' );

    $GLOBALS['hws_editorial_options'] = [
        'active_plugins'       => [ 'require-featured-image/require-featured-image.php' ],
        'rfi_post_types'       => [ 'post' ],
        'rfi_minimum_size'     => [ 'width' => 0, 'height' => 0 ],
        'rfi_enforcement_start'=> 1697435134,
    ];
    $GLOBALS['hws_editorial_actions'] = [];
    $GLOBALS['hws_editorial_filters'] = [];
    $GLOBALS['hws_editorial_thumbnails'] = [];

    function get_option( string $key, mixed $default = false ): mixed {
        return array_key_exists( $key, $GLOBALS['hws_editorial_options'] )
            ? $GLOBALS['hws_editorial_options'][ $key ]
            : $default;
    }

    function update_option( string $key, mixed $value, mixed $autoload = null ): bool {
        $GLOBALS['hws_editorial_options'][ $key ] = $value;
        return true;
    }

    function add_action( string $hook, mixed $callback, int $priority = 10, int $accepted_args = 1 ): bool {
        $GLOBALS['hws_editorial_actions'][ $hook ][] = compact( 'callback', 'priority', 'accepted_args' );
        return true;
    }

    function add_filter( string $hook, mixed $callback, int $priority = 10, int $accepted_args = 1 ): bool {
        $GLOBALS['hws_editorial_filters'][ $hook ][] = compact( 'callback', 'priority', 'accepted_args' );
        return true;
    }

    function sanitize_key( string $value ): string {
        return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) );
    }

    function sanitize_text_field( string $value ): string {
        return trim( strip_tags( $value ) );
    }

    function get_post_status( int $post_id ): string {
        return 'draft';
    }

    function get_post_thumbnail_id( int $post_id ): int {
        return (int) ( $GLOBALS['hws_editorial_thumbnails'][ $post_id ] ?? 0 );
    }

    function wp_get_attachment_image_src( int $attachment_id, string $size ): array|false {
        return $attachment_id > 0 ? [ 'image.jpg', 1200, 630 ] : false;
    }

    function __( string $text, string $domain = '' ): string {
        return $text;
    }

    function esc_html( string $text ): string {
        return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }

    function get_the_ID(): int {
        return 77;
    }

    function get_field( string $key, int $post_id, bool $format = true ): mixed {
        return 'one two three four five six seven eight nine ten eleven twelve thirteen fourteen fifteen sixteen seventeen eighteen nineteen twenty twenty-one';
    }

    function get_post_meta( int $post_id, string $key, bool $single = false ): mixed {
        return '';
    }

    function wp_trim_words( string $text, int $limit, string $suffix = '&hellip;' ): string {
        $words = preg_split( '/\s+/', trim( strip_tags( $text ) ) ) ?: [];
        return count( $words ) > $limit
            ? implode( ' ', array_slice( $words, 0, $limit ) ) . $suffix
            : implode( ' ', $words );
    }

    final class WP_Error {
        public function __construct( public string $code, public string $message, public array $data = [] ) {
        }
    }
}

namespace Elementor {
    final class Controls_Manager {
        public const TEXT = 'text';
        public const NUMBER = 'number';
    }
}

namespace Elementor\Core\DynamicTags {
    abstract class Tag {
        /** @var array<string,mixed> */
        private array $settings = [];

        public function set_test_settings( array $settings ): void {
            $this->settings = $settings;
        }

        public function get_settings( string $key = '' ): mixed {
            return '' === $key ? $this->settings : ( $this->settings[ $key ] ?? null );
        }

        protected function add_control( string $key, array $definition ): void {
        }
    }
}

namespace {
    require dirname( __DIR__ ) . '/lib/hexa-wordpress-plugin-core/src/CoreContracts/ModuleInterface.php';
    require dirname( __DIR__ ) . '/src/PluginRuntime/PluginMetadata.php';
    require dirname( __DIR__ ) . '/src/Editorial/FeaturedImageRequirement.php';
    require dirname( __DIR__ ) . '/src/Elementor/TrimmedAcfTextTag.php';
    require dirname( __DIR__ ) . '/src/FrontendContent/legacy-elementor-queries.php';

    use HWS\BaseTools\Editorial\FeaturedImageRequirement;
    use HWS\BaseTools\Elementor\TrimmedAcfTextTag;

    function editorial_expect( bool $condition, string $message ): void {
        if ( ! $condition ) {
            fwrite( STDERR, "FAIL: {$message}\n" );
            exit( 1 );
        }
    }

    FeaturedImageRequirement::migrate_legacy_settings();
    editorial_expect(
        true === get_option( FeaturedImageRequirement::FEATURE_OPTION )
        && [ 'post' ] === get_option( FeaturedImageRequirement::POST_TYPES_OPTION )
        && 0 === get_option( FeaturedImageRequirement::MIN_WIDTH_OPTION )
        && 0 === get_option( FeaturedImageRequirement::MIN_HEIGHT_OPTION )
        && 1697435134 === get_option( FeaturedImageRequirement::ENFORCEMENT_START_OPTION ),
        'legacy featured image settings migrate without changing their scope or dimensions'
    );

    $blocked = FeaturedImageRequirement::filter_insert_post_data(
        [ 'post_type' => 'post', 'post_status' => 'publish', 'post_date' => '2026-01-01 00:00:00' ],
        [ 'ID' => 44 ]
    );
    editorial_expect( 'draft' === $blocked['post_status'], 'a covered post without an image cannot publish' );

    $GLOBALS['hws_editorial_thumbnails'][44] = 9;
    $allowed = FeaturedImageRequirement::filter_insert_post_data(
        [ 'post_type' => 'post', 'post_status' => 'publish', 'post_date' => '2026-01-01 00:00:00' ],
        [ 'ID' => 44 ]
    );
    editorial_expect( 'publish' === $allowed['post_status'], 'a covered post with a valid image can publish' );

    $legacy = FeaturedImageRequirement::filter_insert_post_data(
        [ 'post_type' => 'post', 'post_status' => 'publish', 'post_date' => '2020-01-01 00:00:00' ],
        [ 'ID' => 45 ]
    );
    editorial_expect( 'publish' === $legacy['post_status'], 'the migrated enforcement start preserves older published content' );

    \hws_base_tools\enable_elementor_queries();
    foreach ( [
        'hpr_resources',
        'hpr_publications_featured_new',
        'hpr_publications_standard_featured',
        'hpr_updates_internal',
        'hpr_external_cision',
        'hpr_external_prcom',
    ] as $query_id ) {
        editorial_expect( isset( $GLOBALS['hws_editorial_actions'][ 'elementor/query/' . $query_id ] ), "{$query_id} is registered" );
    }
    editorial_expect(
        isset( $GLOBALS['hws_editorial_actions']['elementor/dynamic_tags/register'] ),
        'the HWS Elementor dynamic tag is registered with Elementor'
    );

    $query = new class {
        /** @var array<string,mixed> */
        public array $vars = [];
        public function set( string $key, mixed $value ): void {
            $this->vars[ $key ] = $value;
        }
    };
    \hws_base_tools\query_hpr_publications_standard_featured( $query );
    editorial_expect(
        'publication' === $query->vars['post_type']
        && 'ASC' === $query->vars['order']
        && 'AND' === $query->vars['meta_query']['relation']
        && [ 'product_tier', 'status', 'featured' ] === array_column( array_values( array_filter( $query->vars['meta_query'], 'is_array' ) ), 'key' ),
        'the standard publication query keeps its type, ascending order, and three ACF constraints'
    );

    editorial_expect(
        'link_output_html' === TrimmedAcfTextTag::normalize_field_key( 'field_652cb84e99150:link_output_html' ),
        'Elementor ACF key notation resolves to the stored field name'
    );
    $tag = new TrimmedAcfTextTag();
    $tag->set_test_settings(
        [
            'key'        => 'field_652cb84e99150:link_output_html',
            'word_limit' => 20,
            'suffix'     => '...',
        ]
    );
    ob_start();
    $tag->render();
    $rendered = (string) ob_get_clean();
    editorial_expect(
        str_ends_with( $rendered, 'twenty...' ) && ! str_contains( $rendered, 'twenty-one' ),
        'the HWS dynamic tag replaces JetEngine word trimming at the configured limit'
    );

    echo "PASS: featured image migration/enforcement and native Elementor query/tag replacements.\n";
}
