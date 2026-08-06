<?php

declare(strict_types=1);

namespace hws_base_tools {
    function hws_scan_backups(): array {
        return (array) ( $GLOBALS['hws_test_backups'] ?? [] );
    }
}

namespace {
    $root = dirname( __DIR__ );
    $passes = 0;
    $failures = [];
    $options = [
        'home'                       => 'https://example.test',
        'siteurl'                    => 'https://example.test',
        'default_comment_status'     => 'open',
        'default_ping_status'        => 'open',
        'auto_update_plugins'        => [ 'one/plugin.php' ],
        'auto_update_themes'         => [ 'theme-one' ],
        'permalink_structure'        => '/%postname%/',
        'hws_litespeed_active_profile'=> 'safe_baseline',
        'stylesheet'                 => 'active-theme',
        'template'                   => 'active-theme',
    ];
    $user_meta = [];
    $current_user_id = 11;
    $current_blog_id = 3;
    $current_network_id = 1;
    $current_caps = [ '*' => true ];
    $multisite = false;
    $comment_ids = [];
    $deleted_comments = [];
    $sample_posts = [];
    $trashed_posts = [];
    $fail_option_updates = [];
    $uuid_counter = 0;
    $custom_profiles = null;

    function safety_expect( bool $condition, string $message ): void {
        global $passes, $failures;
        if ( $condition ) {
            $passes++;
            echo "PASS {$message}\n";
            return;
        }
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }

    function get_option( string $key, mixed $default = false ): mixed {
        return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default;
    }

    function update_option( string $key, mixed $value, mixed $autoload = null ): bool {
        if ( ! empty( $GLOBALS['fail_option_updates'][ $key ] ) ) {
            return false;
        }
        $changed = ! array_key_exists( $key, $GLOBALS['options'] ) || $GLOBALS['options'][ $key ] !== $value;
        $GLOBALS['options'][ $key ] = $value;
        return $changed;
    }

    function delete_option( string $key ): bool {
        if ( ! array_key_exists( $key, $GLOBALS['options'] ) ) {
            return false;
        }
        unset( $GLOBALS['options'][ $key ] );
        return true;
    }

    function get_current_user_id(): int {
        return (int) $GLOBALS['current_user_id'];
    }

    function get_current_blog_id(): int {
        return (int) $GLOBALS['current_blog_id'];
    }

    function get_current_network_id(): int {
        return (int) $GLOBALS['current_network_id'];
    }

    function get_user_meta( int $user_id, string $key, bool $single = false ): mixed {
        return $GLOBALS['user_meta'][ $user_id ][ $key ] ?? '';
    }

    function update_user_meta( int $user_id, string $key, mixed $value ): bool {
        $GLOBALS['user_meta'][ $user_id ][ $key ] = $value;
        return true;
    }

    function delete_user_meta( int $user_id, string $key, mixed $value = null ): bool {
        if ( ! array_key_exists( $key, $GLOBALS['user_meta'][ $user_id ] ?? [] ) ) {
            return false;
        }
        if ( null !== $value && $GLOBALS['user_meta'][ $user_id ][ $key ] !== $value ) {
            return false;
        }
        unset( $GLOBALS['user_meta'][ $user_id ][ $key ] );
        return true;
    }

    function current_user_can( string $capability, mixed ...$args ): bool {
        return ! empty( $GLOBALS['current_caps']['*'] ) || ! empty( $GLOBALS['current_caps'][ $capability ] );
    }

    function sanitize_key( string $value ): string {
        return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) );
    }

    function sanitize_text_field( mixed $value ): string {
        return trim( strip_tags( (string) $value ) );
    }

    function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
        if ( 'hws_base_tools_quick_start_profiles' === $hook && is_array( $GLOBALS['custom_profiles'] ) ) {
            return $GLOBALS['custom_profiles'];
        }
        return $value;
    }

    function current_time( string $format ): string {
        return '12:00:00';
    }

    function wp_generate_uuid4(): string {
        $GLOBALS['uuid_counter']++;
        return sprintf( '00000000-0000-4000-8000-%012d', $GLOBALS['uuid_counter'] );
    }

    function wp_json_encode( mixed $value, int $flags = 0 ): string|false {
        return json_encode( $value, $flags );
    }

    function get_plugins(): array {
        return [
            'inactive/plugin.php' => [ 'Name' => 'Inactive', 'Version' => '1.0.0' ],
            'hws-base-tools/hws-base-tools.php' => [ 'Name' => 'HWS', 'Version' => '1.0.0' ],
        ];
    }

    function is_plugin_active( string $file ): bool {
        return false;
    }

    function is_plugin_active_for_network( string $file ): bool {
        return false;
    }

    function wp_get_themes(): array {
        return [];
    }

    function is_multisite(): bool {
        return (bool) $GLOBALS['multisite'];
    }

    function get_comments( array $args = [] ): array|int {
        $ids = array_values( $GLOBALS['comment_ids'] );
        if ( ! empty( $args['count'] ) ) {
            return 'all' === (string) ( $args['status'] ?? 'all' ) ? count( $ids ) : 0;
        }
        $number = (int) ( $args['number'] ?? count( $ids ) );
        return $number > 0 ? array_slice( $ids, 0, $number ) : $ids;
    }

    function wp_delete_comment( int $comment_id, bool $force_delete = false ): bool {
        $key = array_search( $comment_id, $GLOBALS['comment_ids'], true );
        if ( false === $key ) {
            return false;
        }
        unset( $GLOBALS['comment_ids'][ $key ] );
        $GLOBALS['comment_ids'] = array_values( $GLOBALS['comment_ids'] );
        $GLOBALS['deleted_comments'][] = $comment_id;
        return true;
    }

    function is_wp_error( mixed $value ): bool {
        return false;
    }

    function get_page_by_path( string $slug, string $output, string $type ): ?object {
        return $GLOBALS['sample_posts'][ $slug ] ?? null;
    }

    function wp_strip_all_tags( string $content, bool $remove_breaks = false ): string {
        return strip_tags( $content );
    }

    function wp_trash_post( int $post_id ): object|false {
        $GLOBALS['trashed_posts'][ $post_id ] = true;
        return (object) [ 'ID' => $post_id ];
    }

    function get_post_status( int $post_id ): string {
        return ! empty( $GLOBALS['trashed_posts'][ $post_id ] ) ? 'trash' : 'publish';
    }

    if ( ! defined( 'OBJECT' ) ) {
        define( 'OBJECT', 'OBJECT' );
    }

    require_once $root . '/src/PluginRuntime/Autoloader.php';
    HWS\BaseTools\PluginRuntime\Autoloader::register( $root . '/src' );
    require_once $root . '/lib/hexa-wordpress-plugin-core/bootstrap.php';
    hexa_plugin_core_register_package( 'hws-safety-tests', $root . '/lib/hexa-wordpress-plugin-core' );
    HexaPluginCorePackageRegistry::resolve();

    $review = HWS\BaseTools\ReviewCenter\ReviewCenterModule::config();
    $destructive = [];
    foreach ( $review->template_steps( 'review' ) as $step ) {
        foreach ( $step->subtasks as $subtask ) {
            if ( $subtask->destructive ) {
                $destructive[ $subtask->id ] = $subtask;
            }
        }
    }
    $confirmation_phrases = [];
    foreach ( $destructive as $id => $subtask ) {
        $input = $subtask->required_inputs[0] ?? [];
        $phrase = (string) ( $input['confirm_text'] ?? '' );
        $confirmation_phrases[] = $phrase;
        safety_expect( 'confirmation' === (string) ( $input['type'] ?? '' ) && '' !== $phrase, "{$id} has a typed confirmation field" );
    }
    safety_expect( count( $confirmation_phrases ) === count( array_unique( $confirmation_phrases ) ), 'destructive Review actions use distinct typed phrases' );
    safety_expect(
        in_array( 'delete_plugins', HWS\BaseTools\ReviewCenter\ReviewTaskRunner::required_capabilities( 'remove_inactive_plugins' ), true )
        && in_array( 'delete_themes', HWS\BaseTools\ReviewCenter\ReviewTaskRunner::required_capabilities( 'remove_inactive_themes' ), true )
        && in_array( 'moderate_comments', HWS\BaseTools\ReviewCenter\ReviewTaskRunner::required_capabilities( 'delete_comments' ), true ),
        'Review mutations enforce their native WordPress capabilities'
    );

    $clock = 1000;
    $scan_store = new HWS\BaseTools\ReviewCenter\ReviewScanStore( static fn(): int => $GLOBALS['clock'] );
    $first_scan = [ 'matches' => [ [ 'id' => 10 ], [ 'id' => 20 ] ], 'destructive_supported' => true ];
    safety_expect( ! empty( $scan_store->save( 'scan_x', 'cleanup_x', $first_scan )['success'] ), 'scan store records exact targets for the current user' );
    $changed = $scan_store->authorize_and_consume( 'scan_x', 'cleanup_x', [ 'matches' => [ [ 'id' => 10 ], [ 'id' => 30 ] ], 'destructive_supported' => true ] );
    safety_expect( 'scan_changed' === (string) ( $changed['code'] ?? '' ), 'mutated scan targets are rejected and consumed' );

    $scan_store->save( 'scan_x', 'cleanup_x', $first_scan );
    $current_user_id = 12;
    $other_user = $scan_store->authorize_and_consume( 'scan_x', 'cleanup_x', $first_scan );
    safety_expect( 'scan_required' === (string) ( $other_user['code'] ?? '' ), 'a different user cannot consume another administrator scan' );
    $current_user_id = 11;
    $scan_store->save( 'scan_x', 'cleanup_x', $first_scan );
    $clock = 1000 + HWS\BaseTools\ReviewCenter\ReviewScanStore::TTL_SECONDS;
    $expired = $scan_store->authorize_and_consume( 'scan_x', 'cleanup_x', $first_scan );
    safety_expect( 'scan_expired' === (string) ( $expired['code'] ?? '' ), 'scan authorization expires server-side at the TTL boundary' );
    $clock = 2000;
    $scan_store->save( 'scan_x', 'cleanup_x', $first_scan );
    $consumed = $scan_store->authorize_and_consume( 'scan_x', 'cleanup_x', $first_scan );
    $replayed = $scan_store->authorize_and_consume( 'scan_x', 'cleanup_x', $first_scan );
    safety_expect(
        ! empty( $consumed['success'] ) && 'scan_required' === (string) ( $replayed['code'] ?? '' ),
        'a matching authorization is atomically consumed before mutation and cannot be replayed'
    );

    $comment_ids = [ 1, 2 ];
    $scan_result = HWS\BaseTools\ReviewCenter\ReviewTaskRunner::run( [ 'context' => [ 'review_task' => 'scan_comments' ] ] );
    safety_expect( ! empty( $scan_result['success'] ) && ! empty( $scan_result['data']['scan_digest'] ), 'comment scan returns a server-stored digest' );
    $missing_confirmation = HWS\BaseTools\ReviewCenter\ReviewTaskRunner::run( [ 'context' => [ 'review_task' => 'delete_comments' ] ] );
    safety_expect(
        'confirmation_required' === (string) ( $missing_confirmation['data']['code'] ?? '' )
        && [] === $deleted_comments,
        'destructive callbacks enforce typed confirmation even when called directly'
    );
    $comment_ids = [ 1, 3 ];
    $delete_result = HWS\BaseTools\ReviewCenter\ReviewTaskRunner::run( [
        'context' => [ 'review_task' => 'delete_comments' ],
        'inputs'  => [ 'confirmation' => HWS\BaseTools\ReviewCenter\ReviewTaskRunner::confirmation_text( 'delete_comments' ) ],
    ] );
    safety_expect( 'scan_changed' === (string) ( $delete_result['data']['code'] ?? '' ) && [] === $deleted_comments, 'comment mutation after review prevents all deletion' );
    HWS\BaseTools\ReviewCenter\ReviewTaskRunner::run( [ 'context' => [ 'review_task' => 'scan_comments' ] ] );
    $delete_result = HWS\BaseTools\ReviewCenter\ReviewTaskRunner::run( [
        'context' => [ 'review_task' => 'delete_comments' ],
        'inputs'  => [ 'confirmation' => HWS\BaseTools\ReviewCenter\ReviewTaskRunner::confirmation_text( 'delete_comments' ) ],
    ] );
    safety_expect( ! empty( $delete_result['success'] ) && [ 1, 3 ] === $deleted_comments, 'exact reviewed comment IDs are deleted after matching confirmation' );

    $multisite = true;
    $multisite_result = HWS\BaseTools\ReviewCenter\ReviewTaskRunner::run( [
        'context' => [ 'review_task' => 'remove_inactive_plugins' ],
        'inputs'  => [ 'confirmation' => HWS\BaseTools\ReviewCenter\ReviewTaskRunner::confirmation_text( 'remove_inactive_plugins' ) ],
    ] );
    safety_expect( 'multisite_unsupported' === (string) ( $multisite_result['data']['code'] ?? '' ), 'multisite plugin removal fails closed with an explicit unsupported result' );
    $multisite = false;

    $sample_content = 'Welcome to WordPress. This is your first post. Edit or delete it, then start writing!';
    $sample_posts['hello-world'] = (object) [
        'ID' => 1, 'post_name' => 'hello-world', 'post_type' => 'post', 'post_title' => 'Hello world!',
        'post_content' => $sample_content, 'post_status' => 'publish', 'post_parent' => 0, 'post_password' => '',
        'post_excerpt' => '', 'post_date_gmt' => '2026-01-01 00:00:00', 'post_modified_gmt' => '2026-01-01 00:00:00',
    ];
    $sample_scan = HWS\BaseTools\ReviewCenter\ReviewScanner::sample_content();
    safety_expect( 1 === count( $sample_scan['matches'] ), 'untouched canonical Hello World content matches the conservative fingerprint' );
    HWS\BaseTools\ReviewCenter\ReviewTaskRunner::run( [ 'context' => [ 'review_task' => 'scan_sample_content' ] ] );
    $trash_result = HWS\BaseTools\ReviewCenter\ReviewTaskRunner::run( [
        'context' => [ 'review_task' => 'remove_sample_content' ],
        'inputs'  => [ 'confirmation' => HWS\BaseTools\ReviewCenter\ReviewTaskRunner::confirmation_text( 'remove_sample_content' ) ],
    ] );
    safety_expect(
        ! empty( $trash_result['success'] )
        && [ 1 ] === (array) ( $trash_result['data']['trashed'] ?? [] )
        && false === (bool) ( $trash_result['data']['permanent'] ?? true ),
        'reviewed sample content uses WordPress Trash instead of force deletion'
    );
    $sample_posts['hello-world']->post_modified_gmt = '2026-01-02 00:00:00';
    $sample_scan = HWS\BaseTools\ReviewCenter\ReviewScanner::sample_content();
    safety_expect( [] === $sample_scan['matches'] && 1 === count( $sample_scan['protected'] ), 'edited content sharing a sample slug is protected' );

    $tmp_dir = sys_get_temp_dir() . '/hws-review-safety-' . getmypid();
    mkdir( $tmp_dir );
    $newest_old = $tmp_dir . '/newest-old.zip';
    $older = $tmp_dir . '/older.zip';
    $recent = $tmp_dir . '/recent.zip';
    file_put_contents( $newest_old, 'newest' );
    file_put_contents( $older, 'older' );
    file_put_contents( $recent, 'recent' );
    touch( $newest_old, time() - 10 * 86400 );
    touch( $older, time() - 20 * 86400 );
    touch( $recent, time() - 86400 );
    $hws_test_backups = [
        [ 'plugin' => 'source-a', 'path' => $newest_old ],
        [ 'plugin' => 'source-a', 'path' => $older ],
        [ 'plugin' => 'source-b', 'path' => $recent ],
    ];
    $file_scan = HWS\BaseTools\ReviewCenter\ReviewScanner::cleanup_files();
    safety_expect(
        [ realpath( $older ) ] === array_column( $file_scan['matches'], 'path' )
        && 2 === count( $file_scan['protected'] ),
        'backup cleanup retains the newest backup per source and excludes recent backups'
    );
    unlink( $newest_old );
    unlink( $older );
    unlink( $recent );
    rmdir( $tmp_dir );

    $snapshot_store = new HWS\BaseTools\QuickStart\QuickStartSnapshotStore();
    unset( $options['rewrite_rules'] );
    for ( $index = 0; $index < 7; $index++ ) {
        $snapshot_store->capture();
    }
    $storage = $options[ HWS\BaseTools\QuickStart\QuickStartSnapshotStore::OPTION ];
    safety_expect(
        HWS\BaseTools\QuickStart\QuickStartSnapshotStore::MAX_HISTORY === count( $storage['snapshots'] )
        && ! empty( $snapshot_store->status()['verified'] ),
        'versioned before-state history is verified and bounded'
    );
    $options['default_comment_status'] = 'closed';
    $options['rewrite_rules'] = [ 'new' => 'index.php' ];
    $restore = $snapshot_store->restore_options();
    safety_expect(
        ! empty( $restore['success'] )
        && 'open' === $options['default_comment_status']
        && ! array_key_exists( 'rewrite_rules', $options ),
        'restore verifies approved values and restores an originally missing option by deleting it'
    );

    $saved_storage = $options[ HWS\BaseTools\QuickStart\QuickStartSnapshotStore::OPTION ];
    $options[ HWS\BaseTools\QuickStart\QuickStartSnapshotStore::OPTION ]['snapshots'][0]['options']['default_comment_status']['value'] = 'tampered';
    safety_expect(
        empty( $snapshot_store->status()['verified'] )
        && 'snapshot_integrity' === (string) ( $snapshot_store->restore_options()['code'] ?? '' ),
        'tampered before-state payloads cannot be restored'
    );
    $options[ HWS\BaseTools\QuickStart\QuickStartSnapshotStore::OPTION ] = $saved_storage;
    $options['home'] = 'https://other.example.test';
    safety_expect( empty( $snapshot_store->status()['verified'] ), 'before-state restore is bound to the captured site scope' );
    $options['home'] = 'https://example.test';

    $options['default_comment_status'] = 'before-failure';
    $options['default_ping_status'] = 'before-failure-ping';
    $snapshot_store->capture();
    $options['default_comment_status'] = 'live-comment';
    $options['default_ping_status'] = 'live-ping';
    $fail_option_updates['default_ping_status'] = true;
    $restore = $snapshot_store->restore_options();
    unset( $fail_option_updates['default_ping_status'] );
    safety_expect(
        empty( $restore['success'] )
        && ! empty( $restore['rollback_verified'] )
        && 'live-comment' === $options['default_comment_status']
        && 'live-ping' === $options['default_ping_status'],
        'failed option verification rolls the attempted restore back to its immediate pre-restore state'
    );

    $current_caps = [ 'manage_options' => true ];
    $forbidden = HWS\BaseTools\QuickStart\QuickStartTaskRunner::run( [ 'context' => [ 'quick_start_task' => 'update_wordpress' ] ] );
    safety_expect( 'forbidden' === (string) ( $forbidden['data']['code'] ?? '' ), 'Quick Start enforces the native capability for each server-side task' );
    $current_caps = [ '*' => true ];

    $batch_calls = [ 'read_fail' => 0, 'mutate_ok' => 0, 'mutate_fail' => 0, 'after' => 0 ];
    $custom_profiles = [
        'safety' => [
            'label' => 'Safety',
            'description' => 'Safety batch',
            'steps' => [
                [
                    'id' => 'phase',
                    'label' => 'Phase',
                    'type' => 'setup_action',
                    'subtasks' => [
                        [ 'id' => 'read_fail', 'label' => 'Read fail', 'type' => 'status_check', 'callback' => static function(): array { $GLOBALS['batch_calls']['read_fail']++; return [ 'success' => false, 'message' => 'read failed' ]; } ],
                        [ 'id' => 'mutate_ok', 'label' => 'Mutation ok', 'type' => 'config_mutation', 'callback' => static function(): array { $GLOBALS['batch_calls']['mutate_ok']++; return [ 'success' => true, 'message' => 'ok' ]; } ],
                        [ 'id' => 'mutate_fail', 'label' => 'Mutation fail', 'type' => 'setup_action', 'callback' => static function(): array { $GLOBALS['batch_calls']['mutate_fail']++; return [ 'success' => false, 'message' => 'mutation failed' ]; } ],
                        [ 'id' => 'after', 'label' => 'After', 'type' => 'setup_action', 'callback' => static function(): array { $GLOBALS['batch_calls']['after']++; return [ 'success' => true, 'message' => 'unsafe continuation' ]; } ],
                    ],
                ],
            ],
        ],
    ];
    $batch = ( new HWS\BaseTools\QuickStart\QuickStartBatchService() )->run( 'safety' );
    safety_expect(
        ! empty( $batch['aborted'] )
        && 1 === $batch_calls['read_fail']
        && 1 === $batch_calls['mutate_ok']
        && 1 === $batch_calls['mutate_fail']
        && 0 === $batch_calls['after'],
        'Quick Start continues past a read-only failure but stops before every later task after a mutation failure'
    );

    echo "\n{$passes} passed, " . count( $failures ) . " failed.\n";
    exit( [] === $failures ? 0 : 1 );
}
