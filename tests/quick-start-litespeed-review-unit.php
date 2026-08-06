<?php

declare(strict_types=1);

$root = dirname( __DIR__ );
require_once $root . '/src/PluginRuntime/Autoloader.php';
HWS\BaseTools\PluginRuntime\Autoloader::register( $root . '/src' );
require_once $root . '/lib/hexa-wordpress-plugin-core/bootstrap.php';
hexa_plugin_core_register_package( 'hws-launch-tests', $root . '/lib/hexa-wordpress-plugin-core' );
HexaPluginCorePackageRegistry::resolve();

$passed = 0;
$failed = 0;
function launch_expect( bool $condition, string $message ): void {
    global $passed, $failed;
    if ( $condition ) {
        $passed++;
        echo "PASS {$message}\n";
        return;
    }
    $failed++;
    echo "FAIL {$message}\n";
}

$quick_profiles = HWS\BaseTools\QuickStart\QuickStartProfileRegistry::profiles();
launch_expect( array_keys( $quick_profiles ) === [ 'standard', 'diamond_website', 'news_outlet', 'audit_only' ], 'Quick Start exposes standard, Diamond, publication, and dry-run profiles' );

$standard = $quick_profiles['standard']['steps'];
$quick_subtasks = [];
foreach ( $standard as $step ) {
    foreach ( (array) ( $step['subtasks'] ?? [] ) as $subtask ) {
        $quick_subtasks[] = $subtask;
    }
}
$quick_ids = array_column( $quick_subtasks, 'id' );
launch_expect( array_search( 'sync_hexa_core', $quick_ids, true ) < array_search( 'update_plugins', $quick_ids, true ), 'Quick Start synchronizes Core before host plugin updates' );
launch_expect( in_array( 'install_bootstrap_loader', $quick_ids, true ), 'Quick Start installs the secure HWS bootstrap URL loader' );
launch_expect( array_search( 'install_essential_plugins', $quick_ids, true ) < array_search( 'apply_litespeed_profile', $quick_ids, true ), 'Quick Start provisions plugins before dependent LiteSpeed work' );
launch_expect( in_array( 'set_memory_limit', $quick_ids, true ) && str_contains( serialize( $standard ), 'WP_MAX_MEMORY_LIMIT' ), 'Quick Start sets the exact requested 4 GB WordPress front-end and admin memory policy' );
$plugin_stack_ids = array_column( $standard[2]['subtasks'], 'id' );
launch_expect( array_search( 'install_essential_plugins', $plugin_stack_ids, true ) < array_search( 'enable_auto_updates', $plugin_stack_ids, true ), 'Quick Start enables plugin auto-updates after provisioning the complete required stack' );
launch_expect( in_array( 'disable_comments_pings', $quick_ids, true ) && ! in_array( 'delete_comments', $quick_ids, true ), 'Quick Start closes discussion while permanent comment deletion stays out of its batch' );
launch_expect( in_array( 'repair_permalinks', $quick_ids, true ) && in_array( 'verify_live_site', $quick_ids, true ), 'Quick Start hard-repairs permalinks and verifies public pages' );
launch_expect( false === ( $quick_subtasks[ array_search( 'test_smtp', $quick_ids, true ) ]['batch_enabled'] ?? true ), 'mail delivery testing is individual-only' );
$quick_runner = (string) file_get_contents( $root . '/src/QuickStart/QuickStartTaskRunner.php' );
launch_expect(
    str_contains( $quick_runner, "'php_handler'" )
    && str_contains( $quick_runner, "'missing_required_extensions'" )
    && str_contains( $quick_runner, "'opcache'" )
    && str_contains( $quick_runner, "'redis'" )
    && str_contains( $quick_runner, "'php_limits'" )
    && str_contains( $quick_runner, "'limits_available_to_wordpress' => false" ),
    'runtime preflight audits WordPress-visible CloudLinux/PHP settings and marks LVE quotas server-only'
);
$timezone_validator = new ReflectionMethod( HWS\BaseTools\QuickStart\QuickStartTaskRunner::class, 'timezone_is_valid' );
launch_expect( true === $timezone_validator->invoke( null, '', 0 ), 'site identity accepts explicit UTC offset zero as a valid timezone' );
launch_expect( false === $timezone_validator->invoke( null, '', null ), 'site identity still rejects a genuinely missing timezone setting' );

$review = HWS\BaseTools\ReviewCenter\ReviewCenterModule::config();
$review_steps = $review->template_steps( 'review' );
$review_items = [];
foreach ( $review_steps as $step ) {
    foreach ( $step->subtasks as $subtask ) {
        $review_items[ $subtask->id ] = $subtask;
    }
}
launch_expect( isset( $review_items['scan_comments'], $review_items['delete_comments'] ), 'Review Center pairs a comments scan with one-click permanent deletion' );
launch_expect( $review_items['delete_comments']->destructive && ! $review_items['delete_comments']->batch_enabled, 'delete-all-comments is visibly destructive and excluded from batches' );
$unsafe_batch = array_filter( $review_items, static fn( object $item ): bool => $item->destructive && $item->batch_enabled );
launch_expect( [] === $unsafe_batch, 'no destructive Review Center task can run in a batch' );
launch_expect( isset( $review_items['remove_migration'], $review_items['remove_duplicate_caches'], $review_items['remove_inactive_plugins'], $review_items['remove_inactive_themes'], $review_items['restore_snapshot'] ), 'Review Center covers migration tools, cache conflicts, inactive code, and rollback' );

$lite_profiles = HWS\BaseTools\LiteSpeed\LiteSpeedProfileRegistry::definitions();
launch_expect( array_keys( $lite_profiles ) === [ 'compatibility', 'safe_baseline', 'editorial', 'aggressive_test_first' ], 'LiteSpeed exposes four named optimization profiles' );
foreach ( $lite_profiles as $id => $profile ) {
    $settings = array_column( $profile['settings'], 'expected', 'id' );
    launch_expect( 0 === (int) $settings['css_combine'] && 0 === (int) $settings['js_combine'], "{$id} keeps CSS and JavaScript combining disabled" );
    launch_expect( 0 === (int) $settings['crawler'], "{$id} keeps the crawler disabled" );
    launch_expect( [ '404 3600', '500 0' ] === $settings['status_ttl'], "{$id} never caches HTTP 500 responses" );
}
$safe = array_column( $lite_profiles['safe_baseline']['settings'], 'expected', 'id' );
$aggressive = array_column( $lite_profiles['aggressive_test_first']['settings'], 'expected', 'id' );
launch_expect( 0 === (int) $safe['js_defer'] && 1 === (int) $aggressive['js_defer'], 'aggressive JavaScript deferral is isolated from the recommended profile' );
launch_expect( 0 === (int) $safe['guest_mode'] && 1 === (int) $aggressive['guest_mode'], 'guest optimization is isolated from the recommended profile' );

$lite_config = HWS\BaseTools\LiteSpeed\LiteSpeedModule::config();
launch_expect( $lite_config->persistence_enabled() && '' !== $lite_config->status_action() && '' !== $lite_config->reset_action(), 'LiteSpeed checklist persists and exposes real-time status/reset actions' );
launch_expect( HWS\BaseTools\QuickStart\QuickStartModule::config()->state_option() !== $lite_config->state_option() && $lite_config->state_option() !== $review->state_option(), 'each master checklist has isolated persistent state' );

$core_assets = (string) file_get_contents( $root . '/lib/hexa-wordpress-plugin-core/src/GettingStartedChecklist/GettingStartedChecklistAssets.php' );
launch_expect( str_contains( $core_assets, 'statusAction' ) && str_contains( $core_assets, 'applyState(state)' ), 'Core hydrates saved checklist progress in real time' );
launch_expect( str_contains( $core_assets, "batchMode && stepRow.dataset.batchEnabled === '0'" ), 'Core batch runner skips individual-only tasks' );

$legacy = (string) file_get_contents( $root . '/src/AdminDashboard/legacy-getting-started.php' );
launch_expect( substr_count( $legacy, "\n" ) < 100 && str_contains( $legacy, 'QuickStartTaskRunner::run' ), 'legacy Quick Start is now a thin namespaced compatibility facade' );
launch_expect( ! str_contains( $legacy, '$wpdb' ) && ! str_contains( $legacy, 'Plugin_Upgrader' ), 'legacy Quick Start no longer duplicates SQL cleanup or updater machinery' );

$wordfence = (string) file_get_contents( $root . '/src/Security/WordfencePolicyService.php' );
launch_expect( str_contains( $wordfence, 'HWS_WORDFENCE_LICENSE_KEY' ) && ! preg_match( '/[a-f0-9]{40,}/i', $wordfence ), 'Wordfence per-site licensing uses server injection and no hardcoded credential' );

$bootstrap = (string) file_get_contents( $root . '/deploy/hws-base-tools-bootstrap.php' );
launch_expect( str_contains( $bootstrap, "hash_hmac( 'sha256'" ) && str_contains( $bootstrap, 'hash_equals' ), 'bootstrap URL uses an HMAC signature with constant-time verification' );
launch_expect( str_contains( $bootstrap, 'get_transient( $replay_key )' ) && str_contains( $bootstrap, 'set_transient( $replay_key' ), 'bootstrap URL rejects replayed request IDs' );
launch_expect( str_contains( $bootstrap, "'response' => 403" ) && str_contains( $bootstrap, "current_user_can( 'install_plugins' )" ), 'bootstrap admin flow enforces capabilities with a real 403 response' );
launch_expect( str_contains( $bootstrap, '$wp_filesystem->move( $target, $backup' ) && str_contains( $bootstrap, '$wp_filesystem->move( $backup, $target' ), 'bootstrap replacement preserves and restores the existing plugin on failure' );
launch_expect( str_contains( $bootstrap, 'HWS_BOOTSTRAP_SECRET' ) && ! preg_match( '/(?i)(secret|token|key)\s*[=:]\s*[\"\'][A-Za-z0-9+\/=_-]{24,}/', $bootstrap ), 'bootstrap secret is injected and never embedded in source' );
launch_expect( str_contains( $bootstrap, '/releases/latest' ) && str_contains( $bootstrap, '/archive/refs/tags/' ) && ! str_contains( $bootstrap, '/archive/refs/heads/' ), 'bootstrap installs only an immutable published GitHub release' );
launch_expect( str_contains( $bootstrap, 'hws_bootstrap_release_version_mismatch' ) && str_contains( $bootstrap, 'hws_bootstrap_core_integrity_failed' ) && str_contains( $bootstrap, 'TOKEN_PARSE' ), 'bootstrap verifies release version, bundled Core hash, and PHP syntax before replacement' );
launch_expect(
    preg_match( '/clear_plugin_discovery_cache\(\);\s*\$activated\s*=\s*activate_plugin\(\s*PLUGIN\s*\);/', $bootstrap ) === 1
    && str_contains( $bootstrap, "wp_cache_delete( 'plugins', 'plugins' )" ),
    'bootstrap invalidates stale plugin discovery before activating a fresh install'
);
launch_expect( str_contains( $bootstrap, 'register_pending_health_check' ) && str_contains( $bootstrap, 'complete_pending_health_check' ) && str_contains( $bootstrap, 'restore_previous_install' ), 'bootstrap retains rollback state through a next-request health check' );
launch_expect( str_contains( $bootstrap, "ACTION . '|GET|'" ) && str_contains( $bootstrap, 'canonical_home_url()' ), 'signed bootstrap requests are bound to the exact site path, action, and GET method' );
$deployment = (string) file_get_contents( $root . '/src/BootstrapInstaller/BootstrapLoaderDeployment.php' );
launch_expect( str_contains( $deployment, '.stage-' ) && str_contains( $deployment, '.backup-' ) && str_contains( $deployment, 'hash_file' ), 'MU bootstrap deployment uses a verified staged replacement with rollback' );
$generator = (string) file_get_contents( $root . '/deploy/generate-bootstrap-url.php' );
launch_expect( str_contains( $generator, "'format'     => 'text'" ) && str_contains( $generator, "parts['path']" ), 'generated signed bootstrap URLs return a readable result and preserve subdirectory installs' );
launch_expect( str_contains( $generator, "'|GET|'" ) && str_contains( $generator, '$canonical_home' ), 'URL generator signs the same canonical site-specific scope as the loader' );
$seeder = (string) file_get_contents( $root . '/deploy/seed-bootstrap-fleet.php' );
launch_expect( str_contains( $seeder, "'toolkit'" ) && str_contains( $seeder, "'apply'" ) && str_contains( $seeder, 'WP Toolkit' ), 'CLI fleet seeder supports read-only discovery and explicit application' );
launch_expect( str_contains( $seeder, '.stage-' ) && str_contains( $seeder, '.backup-' ) && str_contains( $seeder, 'hash_file' ), 'CLI fleet seeder uses verified staged replacement with rollback' );
launch_expect( str_contains( $seeder, "PHP_SAPI !== 'cli'" ) && str_contains( $seeder, 'is_link( $target )' ), 'fleet seeder cannot run over HTTP and rejects symlink targets' );

echo "\n{$passed} passed, {$failed} failed.\n";
exit( 0 === $failed ? 0 : 1 );
