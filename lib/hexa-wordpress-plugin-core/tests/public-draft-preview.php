<?php

declare(strict_types=1);

set_error_handler( static function ( int $severity, string $message, string $file, int $line ): bool {
    throw new ErrorException( $message, 0, $severity, $file, $line );
} );

define( 'ABSPATH', __DIR__ . '/' );

class WP_Post {
    public int $ID = 0;
    public string $post_type = 'post';
    public string $post_status = 'draft';
    public string $post_date = '';

    public function __construct( array $fields = [] ) {
        foreach ( $fields as $key => $value ) {
            $this->{$key} = $value;
        }
    }
}

class WP_Query {
    public array $flags;

    public function __construct( array $flags = [] ) {
        $this->flags = array_merge( [ 'main' => true, 'singular' => true, 'preview' => false, 'feed' => false, 'embed' => false ], $flags );
    }
    public function is_main_query(): bool { return $this->flags['main']; }
    public function is_singular(): bool { return $this->flags['singular']; }
    public function is_preview(): bool { return $this->flags['preview']; }
    public function is_feed(): bool { return $this->flags['feed']; }
    public function is_embed(): bool { return $this->flags['embed']; }
}

$filters = [];
$actions = [];
$can_edit = false;

function add_filter( string $hook, $callback, int $priority = 10, int $args = 1 ): bool {
    global $filters;
    $filters[ $hook ][] = $callback;
    return true;
}
function do_action( string $hook, ...$args ): void {
    global $actions;
    $actions[] = $hook;
}
function is_admin(): bool { return false; }
function wp_doing_ajax(): bool { return false; }
function is_post_type_viewable( string $type ): bool { return 'wp_template' !== $type; }
function current_user_can( string $cap, int $id ): bool { global $can_edit; return $can_edit; }
function get_post_datetime( WP_Post $post, string $field, string $source ): ?DateTimeImmutable {
    return '' === $post->post_date ? null : new DateTimeImmutable( $post->post_date, new DateTimeZone( 'UTC' ) );
}
function nocache_headers(): void {}
function wp_robots_no_robots( array $robots ): array { return $robots; }

require dirname( __DIR__ ) . '/src/CoreContracts/ModuleInterface.php';
require dirname( __DIR__ ) . '/src/DraftPreview/PublicDraftPreview.php';

use Hexa\PluginCore\DraftPreview\PublicDraftPreview;

$assertions = 0;
$expect = static function ( bool $condition, string $message ) use ( &$assertions ): void {
    $assertions++;
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$now = 1790000000;
$expect( PublicDraftPreview::is_eligible( 'draft', $now - 3600, $now ), 'A draft dated an hour ago is visible.' );
$expect( ! PublicDraftPreview::is_eligible( 'draft', $now - 86400, $now ), 'A draft dated exactly a day ago is not.' );
$expect( ! PublicDraftPreview::is_eligible( 'draft', $now + 600, $now ), 'A future-dated draft is not.' );
$expect( ! PublicDraftPreview::is_eligible( 'pending', $now - 60, $now ), 'Other statuses are excluded by default.' );
$expect( PublicDraftPreview::is_eligible( 'pending', $now - 60, $now, 86400, [ 'draft', 'pending' ] ), 'Hosts may allow more statuses.' );
$expect( ! PublicDraftPreview::is_eligible( 'draft', null, $now ), 'A draft without a date is not visible.' );

$module = new PublicDraftPreview();
$module->register();
$expect( isset( $filters['posts_results'] ), 'Registering hooks posts_results.' );

$recent = new WP_Post( [ 'ID' => 7, 'post_date' => gmdate( 'Y-m-d H:i:s', time() - 600 ) ] );
$result = $module->filter_posts_results( [ $recent ], new WP_Query() );
$expect( 'publish' === $result[0]->post_status && 7 === $result[0]->ID, 'A recent draft is shown as published.' );
$expect( $result[0] !== $recent && 'draft' === $recent->post_status, 'The original (cached) post keeps its draft status.' );
$expect( in_array( 'litespeed_control_set_nocache', $actions, true ) && isset( $filters['wp_robots'], $filters['comments_open'] ), 'The view is uncached, noindexed, and closed to comments.' );

$old = new WP_Post( [ 'ID' => 8, 'post_date' => gmdate( 'Y-m-d H:i:s', time() - 90000 ) ] );
$expect( 'draft' === $module->filter_posts_results( [ $old ], new WP_Query() )[0]->post_status, 'An old draft stays hidden.' );

$fresh = static fn(): WP_Post => new WP_Post( [ 'ID' => 9, 'post_date' => gmdate( 'Y-m-d H:i:s', time() - 60 ) ] );
$expect( 'draft' === $module->filter_posts_results( [ $fresh() ], new WP_Query( [ 'main' => false ] ) )[0]->post_status, 'Secondary queries are untouched.' );
$expect( 'draft' === $module->filter_posts_results( [ $fresh() ], new WP_Query( [ 'singular' => false ] ) )[0]->post_status, 'Archive queries are untouched.' );
$expect( 'draft' === $module->filter_posts_results( [ $fresh() ], new WP_Query( [ 'preview' => true ] ) )[0]->post_status, 'Native previews are untouched.' );
$expect( 'draft' === $module->filter_posts_results( [ new WP_Post( [ 'post_type' => 'wp_template', 'post_date' => gmdate( 'Y-m-d H:i:s', time() - 60 ) ] ) ], new WP_Query() )[0]->post_status, 'Non-viewable post types are untouched.' );
$expect( 2 === count( $module->filter_posts_results( [ $fresh(), $fresh() ], new WP_Query() ) ) , 'Multi-post results pass through.' );

$can_edit = true;
$expect( 'draft' === $module->filter_posts_results( [ $fresh() ], new WP_Query() )[0]->post_status, 'Editors keep the native draft flow.' );

echo "PASS: public draft preview ({$assertions} assertions).\n";
