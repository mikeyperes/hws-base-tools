<?php

declare( strict_types=1 );

set_error_handler( static function ( int $severity, string $message, string $file, int $line ): bool {
    throw new ErrorException( $message, 0, $severity, $file, $line );
} );

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

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

$options = [];
$meta    = [];
$hooks   = [];
$posts   = [];

function get_option( string $key, mixed $default = false ): mixed { global $options; return $options[ $key ] ?? $default; }
function update_option( string $key, mixed $value, mixed $autoload = null ): bool { global $options; $options[ $key ] = $value; return true; }
function get_post_meta( int $id, string $key, bool $single = false ): mixed { global $meta; return $meta[ $id ][ $key ] ?? ''; }
function update_post_meta( int $id, string $key, mixed $value ): bool { global $meta; $meta[ $id ][ $key ] = $value; return true; }
function add_action( string $hook, $callback, int $priority = 10, int $args = 1 ): bool { global $hooks; $hooks[] = $hook; return true; }
function add_filter( string $hook, $callback, int $priority = 10, int $args = 1 ): bool { global $hooks; $hooks[] = $hook; return true; }
function do_action( string $hook, ...$args ): void {}
function is_admin(): bool { return false; }
function wp_doing_ajax(): bool { return false; }
function is_post_type_viewable( string $type ): bool { return 'wp_template' !== $type; }
function current_user_can( string $cap, int $id ): bool { return true; }
function get_post( int $id ): ?WP_Post { global $posts; return $posts[ $id ] ?? null; }
function get_permalink( WP_Post $post ): string { return 'https://example.com/?p=' . $post->ID; }
function add_query_arg( string $key, string $value, string $url ): string { return $url . '&' . $key . '=' . $value; }
function esc_url( string $url ): string { return $url; }
function get_post_datetime( WP_Post $post, string $field, string $source ): ?DateTimeImmutable {
    return '' === $post->post_date ? null : new DateTimeImmutable( $post->post_date, new DateTimeZone( 'UTC' ) );
}
function nocache_headers(): void {}
function wp_robots_no_robots( array $robots ): array { return $robots; }

require dirname( __DIR__ ) . '/src/Editorial/PublicDraftPreviewFeature.php';

use HWS\BaseTools\Editorial\PublicDraftPreviewFeature as Links;

$failures = 0;
$expect   = static function ( bool $ok, string $label ) use ( &$failures ): void {
    echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . PHP_EOL;
    $failures += $ok ? 0 : 1;
};

$now = time();

// Key: human-friendly, stable once created.
$key = Links::key();
$expect( 1 === preg_match( '/^[a-z]+-[a-z]+-[a-z]+-[1-9][0-9]$/', $key ), 'the key is three words and a number: ' . $key );
$expect( Links::key() === $key, 'the key stays the same once created' );
$expect( Links::key_matches( $key ) && Links::key_matches( ' ' . $key . ' ' ), 'the right key matches' );
$expect( ! Links::key_matches( 'wrong-key' ) && ! Links::key_matches( null ) && ! Links::key_matches( [ $key ] ), 'a wrong, missing or array key does not' );

// Window and statuses.
$expect( Links::is_eligible( 'draft', $now - 3600, $now ), 'a draft created an hour ago is open' );
$expect( Links::is_eligible( 'pending', $now - 47 * 3600, $now ), 'a pending post created 47 hours ago is open' );
$expect( ! Links::is_eligible( 'pending', $now - 48 * 3600, $now ), 'at exactly 48 hours it closes' );
$expect( ! Links::is_eligible( 'publish', $now - 60, $now ) && ! Links::is_eligible( 'private', $now - 60, $now ), 'published and private posts are never affected' );
$expect( ! Links::is_eligible( 'draft', $now + 600, $now ) && ! Links::is_eligible( 'draft', null, $now ), 'future or unknown creation times are closed' );

// Creation time: recorded once, used instead of the moving draft date.
$post = new WP_Post( [ 'ID' => 7, 'post_status' => 'pending', 'post_date' => gmdate( 'Y-m-d H:i:s', $now - 60 ) ] );
Links::record_created( 7, $post, false );
$meta[7][ Links::CREATED_META ] = (string) ( $now - 50 * 3600 );
Links::record_created( 7, $post, true );
$expect( Links::created_timestamp( $post ) === $now - 50 * 3600, 'the recorded creation time wins over a recent save date' );
$fresh = new WP_Post( [ 'ID' => 8, 'post_status' => 'draft', 'post_date' => gmdate( 'Y-m-d H:i:s', $now - 120 ) ] );
$expect( Links::created_timestamp( $fresh ) === $now - 120, 'without a record, the post date is used' );

// Front-end: only with the key.
$draft = new WP_Post( [ 'ID' => 9, 'post_status' => 'draft', 'post_date' => gmdate( 'Y-m-d H:i:s', $now - 60 ) ] );
$_GET  = [];
$expect( 'draft' === Links::filter_posts_results( [ $draft ], new WP_Query() )[0]->post_status, 'no key: the draft stays private' );
$_GET[ Links::PARAM ] = 'wrong-key';
$expect( 'draft' === Links::filter_posts_results( [ $draft ], new WP_Query() )[0]->post_status, 'wrong key: the draft stays private' );
$_GET[ Links::PARAM ] = $key;
$shown = Links::filter_posts_results( [ $draft ], new WP_Query() );
$expect( 'publish' === $shown[0]->post_status && 'draft' === $draft->post_status, 'right key: the visitor sees it, the stored post keeps its status' );
$expect( 'pending' === Links::filter_posts_results( [ $post ], new WP_Query() )[0]->post_status, 'right key but older than 48 hours: stays private' );
$expect( 'draft' === Links::filter_posts_results( [ $draft ], new WP_Query( [ 'main' => false ] ) )[0]->post_status, 'secondary queries are untouched' );
$expect( 'draft' === Links::filter_posts_results( [ $draft, $fresh ], new WP_Query() )[0]->post_status, 'lists are untouched' );

// Link and row action.
$posts = [ 9 => $draft, 7 => $post ];
$expect( Links::link( 9 ) === 'https://example.com/?p=9&draft_key=' . rawurlencode( $key ), 'the link is the post URL plus the key' );
$expect( null === Links::link( 7 ), 'no link once the 48 hours are over' );
$expect( isset( Links::row_actions( [], $draft )['hws_public_draft_link'] ) && [] === Links::row_actions( [], $post ), 'the Posts list offers the link only while it works' );

echo PHP_EOL . ( 0 === $failures ? 'All public draft link checks passed.' : $failures . ' check(s) failed.' ) . PHP_EOL;
exit( 0 === $failures ? 0 : 1 );
