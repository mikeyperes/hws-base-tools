<?php

declare( strict_types=1 );

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$failures = [];
$passes   = 0;

function article_image_expect( bool $condition, string $message ): void {
    global $failures, $passes;

    if ( $condition ) {
        ++$passes;
        echo "PASS {$message}\n";
        return;
    }

    $failures[] = $message;
    echo "FAIL {$message}\n";
}

$article_image_sizes       = [];
$article_image_metadata    = [];
$article_image_src         = [];
$article_image_alt         = [];
$article_image_files       = [];
$article_image_thumbnails  = [];
$article_image_cache_deletes = [];
$article_image_options     = [];
$article_image_singular    = true;
$article_image_queried_id  = 91;
$article_image_post_types  = [ 'post', 'news', 'product' ];

function add_image_size( string $name, int $width, int $height, bool $crop ): void {
    global $article_image_sizes;
    $article_image_sizes[ $name ] = compact( 'width', 'height', 'crop' );
}

function wp_get_attachment_metadata( int $attachment_id ): array|false {
    global $article_image_metadata;
    return $article_image_metadata[ $attachment_id ] ?? false;
}

function wp_get_attachment_image_src( int $attachment_id, string $size ): array|false {
    global $article_image_src;
    return $article_image_src[ $attachment_id ][ $size ] ?? false;
}

function wp_get_attachment_image_srcset( int $attachment_id, string $size ): string|false {
    unset( $attachment_id, $size );
    return 'https://example.com/uploads/story-1200x675.webp 1200w';
}

function wp_parse_url( string $url, int $component = -1 ): mixed {
    return parse_url( $url, $component );
}

function get_post_meta( int $post_id, string $key, bool $single = false ): mixed {
    global $article_image_alt;
    unset( $single );
    return '_wp_attachment_image_alt' === $key ? ( $article_image_alt[ $post_id ] ?? '' ) : '';
}

function get_post_mime_type( int $attachment_id ): string {
    unset( $attachment_id );
    return 'image/webp';
}

function wp_get_attachment_caption( int $attachment_id ): string {
    unset( $attachment_id );
    return 'Descriptive article image';
}

function get_attached_file( int $attachment_id ): string {
    global $article_image_files;
    return $article_image_files[ $attachment_id ] ?? '';
}

function get_post_thumbnail_id( int $post_id ): int {
    global $article_image_thumbnails;
    return (int) ( $article_image_thumbnails[ $post_id ] ?? 0 );
}

function is_singular(): bool {
    global $article_image_singular;
    return $article_image_singular;
}

function get_queried_object_id(): int {
    global $article_image_queried_id;
    return $article_image_queried_id;
}

function sanitize_key( string $key ): string {
    return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
}

function home_url( string $path = '' ): string {
    return 'https://example.com' . $path;
}

function wp_cache_delete_multiple( array $keys, string $group ): void {
    global $article_image_cache_deletes;
    $article_image_cache_deletes[] = [ $keys, $group ];
}

function post_type_exists( string $type ): bool {
    return 'post' === $type;
}

function wp_count_posts( string $type ): object {
    unset( $type );
    return (object) [ 'publish' => 150, 'inherit' => 0 ];
}

function taxonomy_exists( string $type ): bool {
    unset( $type );
    return false;
}

function get_option( string $key, mixed $default = false ): mixed {
    global $article_image_options;
    return $article_image_options[ $key ] ?? $default;
}

function is_wp_error( mixed $value ): bool {
    return $value instanceof WP_Error;
}

function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
    unset( $hook, $args );
    return $value;
}

function maybe_unserialize( mixed $value ): mixed {
    return $value;
}

function get_post_types( array $args = [], string $output = 'names' ): array {
    global $article_image_post_types;
    unset( $args, $output );
    return array_combine( $article_image_post_types, $article_image_post_types ) ?: [];
}

function post_type_supports( string $post_type, string $feature ): bool {
    unset( $post_type );
    return 'thumbnail' === $feature;
}

function get_taxonomies( array $args = [], string $output = 'names' ): array {
    unset( $args, $output );
    return [];
}

function count_users(): array {
    return [ 'total_users' => 1 ];
}

function wp_count_terms( array $args ): int {
    unset( $args );
    return 1;
}

function wp_attachment_is_image( int $attachment_id ): bool {
    return $attachment_id > 0;
}

class WP_Error {
    public function get_error_message(): string {
        return 'test error';
    }
}

require_once dirname( __DIR__ ) . '/lib/hexa-wordpress-plugin-core/src/CoreContracts/ModuleInterface.php';
require_once dirname( __DIR__ ) . '/src/ArticleImageIndexing/ImageFamily.php';
require_once dirname( __DIR__ ) . '/src/ArticleImageIndexing/HeroImagePolicy.php';
require_once dirname( __DIR__ ) . '/src/ArticleImageIndexing/RankMathIntegration.php';
require_once dirname( __DIR__ ) . '/src/ArticleImageIndexing/RankMathSitemapCache.php';
require_once dirname( __DIR__ ) . '/src/ArticleImageIndexing/ArticleImageModule.php';

use HWS\BaseTools\ArticleImageIndexing\ArticleImageModule;
use HWS\BaseTools\ArticleImageIndexing\HeroImagePolicy;
use HWS\BaseTools\ArticleImageIndexing\ImageFamily;
use HWS\BaseTools\ArticleImageIndexing\RankMathIntegration;
use HWS\BaseTools\ArticleImageIndexing\RankMathSitemapCache;

ImageFamily::register_sizes();
article_image_expect(
    $article_image_sizes === [
        'hws-article-16x9' => [ 'width' => 1200, 'height' => 675, 'crop' => true ],
        'hws-article-4x3'  => [ 'width' => 1200, 'height' => 900, 'crop' => true ],
        'hws-article-1x1'  => [ 'width' => 1200, 'height' => 1200, 'crop' => true ],
    ],
    'the plugin registers one fixed 16:9, 4:3, and 1:1 article image family'
);

$square_dimensions = ImageFamily::exact_crop_dimensions( null, 1880, 1058, 1200, 1200, true );
article_image_expect(
    [ 0, 0, 411, 0, 1200, 1200, 1058, 1058 ] === $square_dimensions,
    'a landscape source receives an exact centered 1200px square crop even when its shorter edge needs modest upscaling'
);

$attachment_id = 501;
$article_image_metadata[ $attachment_id ] = [
    'width'  => 1880,
    'height' => 1253,
    'sizes'  => [
        'hws-article-16x9' => [ 'file' => 'story-1200x675.webp', 'width' => 1200, 'height' => 675, 'mime-type' => 'image/webp' ],
        'hws-article-4x3'  => [ 'file' => 'story-1200x900.webp', 'width' => 1200, 'height' => 900, 'mime-type' => 'image/webp' ],
        'hws-article-1x1'  => [ 'file' => 'story-1200x1200.webp', 'width' => 1200, 'height' => 1200, 'mime-type' => 'image/webp' ],
        'medium_large'     => [ 'file' => 'story-768x512.webp', 'width' => 768, 'height' => 512, 'mime-type' => 'image/webp' ],
    ],
];
$article_image_src[ $attachment_id ] = [
    'hws-article-16x9' => [ 'https://example.com/uploads/story-1200x675.webp', 1200, 675, true ],
    'hws-article-4x3'  => [ 'https://example.com/uploads/story-1200x900.webp', 1200, 900, true ],
    'hws-article-1x1'  => [ 'https://example.com/uploads/story-1200x1200.webp', 1200, 1200, true ],
];
$article_image_alt[ $attachment_id ]   = 'Accurate image description';
$article_image_files[ $attachment_id ] = '/uploads/story.webp';
$article_image_thumbnails[91]          = $attachment_id;

$undersized_attachment_id = 502;
$article_image_files[ $undersized_attachment_id ] = __FILE__;
$undersized_metadata = [
    'width'  => 1880,
    'height' => 1058,
    'sizes'  => [
        'hws-article-16x9' => [ 'file' => basename( __FILE__ ), 'width' => 1200, 'height' => 675 ],
        'hws-article-4x3'  => [ 'file' => basename( __FILE__ ), 'width' => 1200, 'height' => 900 ],
        'hws-article-1x1'  => [ 'file' => basename( __FILE__ ), 'width' => 1058, 'height' => 1058 ],
    ],
];
$missing_method = ( new ReflectionClass( ImageFamily::class ) )->getMethod( 'missing_definitions' );
$missing_sizes  = $missing_method->invoke( null, $undersized_attachment_id, $undersized_metadata );
article_image_expect(
    [ 'hws-article-1x1' ] === array_keys( $missing_sizes ),
    'an undersized stored crop remains missing instead of being reported as complete'
);

$objects = ImageFamily::schema_objects( $attachment_id );
article_image_expect(
    3 === count( $objects )
    && [ 675, 900, 1200 ] === array_column( $objects, 'height' )
    && 'https://example.com/uploads/story-1200x675.webp' === $objects[0]['contentUrl'],
    'the resolver returns three complete permanent ImageObjects in recommended order'
);

$graph = [
    'Organization' => [ '@type' => 'Organization', 'image' => 'unchanged' ],
    'richSnippet'  => [ '@type' => 'NewsArticle', 'image' => [ '@id' => 'old-image' ] ],
];
$json_ld          = (object) [ 'post_id' => 91 ];
$filtered_graph   = RankMathIntegration::schema_graph( $graph, $json_ld );
article_image_expect(
    'unchanged' === $filtered_graph['Organization']['image']
    && 3 === count( $filtered_graph['richSnippet']['image'] ),
    'Rank Math integration changes only an Article entity already owned by Rank Math'
);

$provider_free_graph = RankMathIntegration::schema_graph(
    [ 'Organization' => [ '@type' => 'Organization' ] ],
    $json_ld
);
article_image_expect(
    ! isset( $provider_free_graph['Organization']['image'] ),
    'the plugin does not create a competing Article schema entity'
);

$social = RankMathIntegration::social_image( [ 'id' => $attachment_id, 'url' => 'https://example.com/uploads/story.webp' ] );
article_image_expect(
    'https://example.com/uploads/story-1200x675.webp' === $social['url']
    && 1200 === $social['width']
    && 'summary_large_image' === RankMathIntegration::twitter_card_type( 'summary' ),
    'Open Graph and Twitter select the generated 16:9 featured variant'
);
article_image_expect(
    999 === RankMathIntegration::social_image( [ 'id' => 999, 'url' => 'https://example.com/manual.webp' ] )['id'],
    'an explicitly selected different social attachment remains untouched'
);

$hero = HeroImagePolicy::attributes( [ 'class' => 'attachment-large', 'alt' => 'Generic article title' ], (object) [ 'ID' => $attachment_id ], 'large' );
article_image_expect(
    'high' === $hero['fetchpriority']
    && 'eager' === $hero['loading']
    && '1' === $hero['data-no-lazy']
    && 'https://example.com/uploads/story-1200x675.webp' === $hero['src']
    && '1200' === $hero['width']
    && '675' === $hero['height']
    && 'Accurate image description' === $hero['alt']
    && str_contains( $hero['srcset'], '1200w' )
    && str_contains( $hero['class'], 'skip-lazy' ),
    'the native featured image uses the preferred family plus responsive, dimensional, priority, and lazy-load exclusion attributes'
);

$raw_hero_html = '<figure><img class="theme-hero" src="https://example.com/uploads/story-768x512.webp" alt="Accurate image description"></figure>';
$filtered_hero_html = HeroImagePolicy::filter_html( $raw_hero_html );
article_image_expect(
    str_contains( $filtered_hero_html, 'src="https://example.com/uploads/story-1200x675.webp"' )
    && str_contains( $filtered_hero_html, 'srcset="https://example.com/uploads/story-1200x675.webp 1200w"' )
    && str_contains( $filtered_hero_html, 'width="1200"' )
    && str_contains( $filtered_hero_html, 'height="675"' )
    && str_contains( $filtered_hero_html, 'fetchpriority="high"' )
    && str_contains( $filtered_hero_html, 'loading="eager"' ),
    'a theme-authored raw hero image is corrected in the initial HTML response'
);

$url_exclusions = HeroImagePolicy::litespeed_url_exclusions( [] );
article_image_expect(
    in_array( 'story', $url_exclusions, true )
    && in_array( 'story-1200x675', $url_exclusions, true ),
    'LiteSpeed exclusion fragments cover raw hero markup and generated derivatives'
);

$article_image_options['rank-math-options-sitemap'] = [ 'items_per_page' => 100 ];
$expected_key = 'sitemap_post_rank_math_' . md5( 'post_1_https://example.com' ) . '.xml';
article_image_expect(
    $expected_key === RankMathSitemapCache::transient_key( 'post', 1 )
    && 3 === count( RankMathSitemapCache::transient_keys( 'post' ) ),
    'Rank Math Redis keys match its deployed deterministic transient format across every sitemap page'
);

RankMathSitemapCache::evict_type( 'post' );
article_image_expect(
    'transient' === $article_image_cache_deletes[0][1]
    && 3 === count( $article_image_cache_deletes[0][0] ),
    'targeted invalidation deletes only affected Rank Math transient keys through the WordPress object-cache API'
);

$article_image_options['rank-math-options-titles'] = [
    'pt_news_default_rich_snippet'    => 'article',
    'pt_product_default_rich_snippet' => 'product',
];
article_image_expect(
    [ 'post', 'news' ] === ArticleImageModule::eligible_post_types(),
    'coverage is plugin-wide but remains limited to standard posts and dynamically declared Article post types'
);

$article_image_options['rank_math_modules'] = [ 'sitemap', 'news-sitemap' ];
$news_enabled_method = ( new ReflectionClass( RankMathSitemapCache::class ) )->getMethod( 'news_sitemap_enabled' );
article_image_expect(
    false === $news_enabled_method->invoke( null ),
    'a stale News Sitemap option is not treated as active without the running Rank Math Pro provider'
);
define( 'RANK_MATH_PRO_VERSION', '3.0.118' );
article_image_expect(
    true === $news_enabled_method->invoke( null ),
    'News Sitemap verification activates when both its saved module and Rank Math Pro runtime are present'
);

$source = implode(
    "\n",
    array_map(
        static fn( string $file ): string => (string) file_get_contents( dirname( __DIR__ ) . '/src/ArticleImageIndexing/' . $file ),
        [ 'ArticleImageModule.php', 'ImageFamily.php', 'HeroImagePolicy.php', 'RankMathIntegration.php', 'RankMathSitemapCache.php', 'FeaturedImageBackfill.php' ]
    )
);
article_image_expect(
    ! preg_match( '/gritdaily|blocktelegraph|hexaprwire|\/home\//i', $source ),
    'the shared plugin implementation contains no site names, hostnames, account paths, or per-site branches'
);

$backfill_source = (string) file_get_contents( dirname( __DIR__ ) . '/src/ArticleImageIndexing/FeaturedImageBackfill.php' );
article_image_expect(
    str_contains( $backfill_source, "public const VERSION        = '2';" )
    && str_contains( $backfill_source, "do_action( 'litespeed_purge_post', \$post_id )" )
    && str_contains( $backfill_source, 'RankMathSitemapCache::schedule_refresh( $post_id )' ),
    'the revised backfill reprocesses prior crops and refreshes affected article and sitemap caches'
);

echo "\n{$passes} passed, " . count( $failures ) . " failed.\n";
exit( empty( $failures ) ? 0 : 1 );
