<?php

declare( strict_types=1 );

$root = dirname( __DIR__ );
$failures = [];
$passes = 0;

$expect = static function ( bool $condition, string $message ) use ( &$failures, &$passes ): void {
    if ( $condition ) {
        ++$passes;
        echo "PASS {$message}\n";
        return;
    }

    $failures[] = $message;
    echo "FAIL {$message}\n";
};

$source = static function ( string $relative ) use ( $root ): string {
    $contents = file_get_contents( $root . '/' . $relative );

    return false === $contents ? '' : $contents;
};

$compatibility = $source( 'src/QueryCompatibility/QueryHookCompatibility.php' );
$feed = $source( 'src/FrontendContent/legacy-base-features.php' );
$team = $source( 'src/TeamMembers/TeamMemberDirectory.php' );
$templates = $source( 'src/BrandTemplates/ElementorTemplateImporter.php' );
$migration = $source( 'src/AcfFields/UserProfile2025Migration.php' );
$core_integration = $source( 'src/PluginRuntime/CoreIntegration.php' );

$expect(
    str_contains( $core_integration, 'new QueryHookCompatibility()' )
        && str_contains( $compatibility, "add_action( 'wp_loaded', [ self::class, 'reconcile' ], PHP_INT_MAX )" ),
    'one HWS Core module reconciles vendor hooks at the late wp_loaded lifecycle'
);
$expect(
    str_contains( $compatibility, "private const PTT_VERSION = '1.6'" )
        && str_contains( $compatibility, "private const PTT_CLASS = 'PTT_Post_Visibility'" )
        && str_contains( $compatibility, "'/post-type-transfer/admin/class-ptt-post-visibility.php'" )
        && str_contains( $compatibility, "'fix_queried_object', 10" )
        && str_contains( $compatibility, "'filter_queries', 99" ),
    'PTT replacement is pinned to its exact version, class, source path, methods, and priorities'
);
$expect(
    str_contains( $compatibility, "'widget_posts_args', self::PTT_CLASS, 'filter_recent_posts_widget', 10" )
        && str_contains( $compatibility, "'query_loop_block_query_vars', self::PTT_CLASS, 'filter_query_loop_block', 10" ),
    'PTT dedicated widget and Query Loop callbacks are inventory requirements'
);
$expect(
    str_contains( $compatibility, 'new \\ReflectionFunction' )
        && str_contains( $compatibility, 'closure_fingerprint' )
        && str_contains( $compatibility, "private const ECHO_VERSION = '5.5.1.2'" )
        && str_contains( $compatibility, "\$query->is_tax( 'coderevolution_post_source' )" ),
    'Echo replacement requires versioned reflected source identity and query-object taxonomy state'
);
$expect(
    str_contains( $compatibility, 'QueryEligibility::allows_main_filtered_frontend_query( $query )' )
        && str_contains( $feed, 'QueryEligibility::allows_main_filtered_frontend_query( $query )' ),
    'all HWS pre_get_posts mutation paths share selected-Core main-query eligibility'
);
$expect(
    str_contains( $compatibility, "'/^[A-Za-z0-9_]{1,64}-[1-9][0-9]{0,19}$/D'" )
        && str_contains( $compatibility, 'ELEMENTOR_SEARCH_DEFAULT_MAX = 100' )
        && str_contains( $compatibility, 'ELEMENTOR_SEARCH_MAX_FILTER' )
        && str_contains( $compatibility, '$current <= 0 || $current > $maximum' ),
    'Elementor enhanced-search cap requires validated provenance and only changes unsafe limits'
);
$expect(
    str_contains( $compatibility, "do_action( 'hws_query_hook_compatibility_drift'" )
        && str_contains( $compatibility, "do_action( 'hws_query_hook_compatibility_critical'" )
        && str_contains( $compatibility, 'quarantine_ptt_callbacks' )
        && str_contains( $compatibility, "? 'quarantined' : 'critical'" ),
    'vendor signature drift is observable without modifying unmatched callbacks'
);
$expect(
    str_contains( $compatibility, 'ptt_visibility_rules_exist()' )
        && str_contains( $compatibility, "WHERE meta_key LIKE %s LIMIT 1" )
        && str_contains( $compatibility, "add_action( 'added_post_meta'" )
        && str_contains( $compatibility, "add_action( 'updated_post_meta'" )
        && str_contains( $compatibility, "add_action( 'deleted_post_meta'" ),
    'PTT rule-presence cache uses one bounded prefix query and invalidates on every metadata mutation path'
);

$hook_files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator( $root . '/src', FilesystemIterator::SKIP_DOTS )
);
foreach ( $iterator as $file ) {
    if ( ! $file instanceof SplFileInfo || ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
        continue;
    }
    $tokens = token_get_all( (string) file_get_contents( $file->getPathname() ) );
    foreach ( $tokens as $index => $token ) {
        if ( ! is_array( $token ) || T_STRING !== $token[0] || 'add_action' !== strtolower( $token[1] ) ) {
            continue;
        }
        $significant = [];
        for ( $cursor = $index + 1, $total = count( $tokens ); $cursor < $total && count( $significant ) < 2; ++$cursor ) {
            $next = $tokens[ $cursor ];
            if ( is_array( $next ) && in_array( $next[0], [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ], true ) ) {
                continue;
            }
            $significant[] = $next;
        }
        $hook_token = $significant[1] ?? null;
        $hook_name = is_array( $hook_token ) && T_CONSTANT_ENCAPSED_STRING === $hook_token[0]
            ? trim( $hook_token[1], "'\"" )
            : '';
        if ( in_array( $hook_name, [ 'pre_get_posts', 'parse_query' ], true ) ) {
            $hook_files[] = str_replace( $root . '/', '', $file->getPathname() );
            break;
        }
    }
}
sort( $hook_files );
$expect(
    [
        'src/FrontendContent/legacy-base-features.php',
        'src/QueryCompatibility/QueryHookCompatibility.php',
    ] === $hook_files,
    'static HWS hook inventory has no unreviewed pre_get_posts or parse_query registrars'
);

$host_sources = '';
foreach ( $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/src', FilesystemIterator::SKIP_DOTS ) ) as $file ) {
    if ( $file instanceof SplFileInfo && $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) {
        $host_sources .= (string) file_get_contents( $file->getPathname() );
    }
}
$expect(
    ! preg_match( '/[\'\"]posts_per_page[\'\"]\s*=>\s*-1/', $host_sources )
        && ! preg_match( '/[\'\"]number[\'\"]\s*=>\s*-1/', $host_sources )
        && ! preg_match( '/[\'\"]limit[\'\"]\s*=>\s*[\'\"]-1[\'\"]/', $host_sources ),
    'HWS-owned source contains no unlimited posts, users, or shortcode query defaults'
);
$expect(
    str_contains( $team, 'DEFAULT_LIMIT = 24' )
        && str_contains( $team, 'MAX_LIMIT = 100' )
        && str_contains( $team, "'posts_per_page'         => 1" )
        && str_contains( $team, 'found_posts' ),
    'Team Member frontend and integrity queries use bounded result sets'
);
$expect(
    str_contains( $templates, 'TEMPLATE_SCAN_BATCH_SIZE = 100' )
        && str_contains( $templates, 'published_template_id_batches()' )
        && str_contains( $templates, "'no_found_rows'          => true" ),
    'Elementor conflict fallback scans all templates in bounded no-count batches'
);
$expect(
    str_contains( $migration, 'USER_BATCH_SIZE = 100' )
        && 1 === preg_match( "/'number'\\s*=>\\s*self::USER_BATCH_SIZE/", $migration )
        && 1 === preg_match( "/'offset'\\s*=>\\s*\\\$offset/", $migration )
        && 1 === preg_match( "/'count_total'\\s*=>\\s*false/", $migration ),
    'profile migration scans all users in deterministic bounded batches'
);

if ( [] !== $failures ) {
    fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
    exit( 1 );
}

echo "PASS: {$passes} static query inventory assertions.\n";
