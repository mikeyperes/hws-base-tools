<?php

namespace hws_base_tools;
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * PHP EXTENSION / LIBRARY CHECK FOR WORDPRESS
 * ═══════════════════════════════════════════════════════════════════════════
 * @since 10.9.0
 */
if ( ! function_exists( __NAMESPACE__ . '\\hws_check_php_extensions' ) ) {
function hws_check_php_extensions() {
    return [
        [ 'name' => 'mysqli',    'loaded' => extension_loaded('mysqli'),       'purpose' => 'MySQL database (required)',       'required' => true ],
        [ 'name' => 'curl',      'loaded' => extension_loaded('curl'),         'purpose' => 'HTTP requests / API calls',      'required' => true ],
        [ 'name' => 'json',      'loaded' => extension_loaded('json'),         'purpose' => 'JSON parsing (required)',         'required' => true ],
        [ 'name' => 'mbstring',  'loaded' => extension_loaded('mbstring'),     'purpose' => 'Multibyte string support',       'required' => true ],
        [ 'name' => 'openssl',   'loaded' => extension_loaded('openssl'),      'purpose' => 'SSL/TLS encryption',             'required' => true ],
        [ 'name' => 'xml',       'loaded' => extension_loaded('xml'),          'purpose' => 'XML parsing (RSS, sitemaps)',     'required' => true ],
        [ 'name' => 'dom',       'loaded' => extension_loaded('dom'),          'purpose' => 'DOM manipulation',               'required' => true ],
        [ 'name' => 'fileinfo',  'loaded' => extension_loaded('fileinfo'),     'purpose' => 'File type detection',            'required' => true ],
        [ 'name' => 'tokenizer', 'loaded' => extension_loaded('tokenizer'),    'purpose' => 'PHP tokenizer (WP internals)',   'required' => true ],
        [ 'name' => 'imagick',   'loaded' => extension_loaded('imagick'),      'purpose' => 'Advanced image processing',      'required' => false ],
        [ 'name' => 'gd',        'loaded' => extension_loaded('gd'),           'purpose' => 'Image processing (fallback)',    'required' => false ],
        [ 'name' => 'zip',       'loaded' => extension_loaded('zip'),          'purpose' => 'Plugin/theme ZIP handling',      'required' => false ],
        [ 'name' => 'intl',      'loaded' => extension_loaded('intl'),         'purpose' => 'Internationalization',           'required' => false ],
        [ 'name' => 'exif',      'loaded' => extension_loaded('exif'),         'purpose' => 'Image EXIF metadata',            'required' => false ],
        [ 'name' => 'sodium',    'loaded' => extension_loaded('sodium'),       'purpose' => 'Modern cryptography (WP 5.2+)',  'required' => false ],
        [ 'name' => 'opcache',   'loaded' => extension_loaded('Zend OPcache'),'purpose' => 'PHP bytecode caching',           'required' => false ],
        [ 'name' => 'redis',     'loaded' => extension_loaded('redis'),        'purpose' => 'Redis object cache',             'required' => false ],
        [ 'name' => 'bcmath',    'loaded' => extension_loaded('bcmath'),       'purpose' => 'Arbitrary precision math',       'required' => false ],
        [ 'name' => 'iconv',     'loaded' => extension_loaded('iconv'),        'purpose' => 'Character encoding conversion',  'required' => false ],
        [ 'name' => 'simplexml', 'loaded' => extension_loaded('simplexml'),    'purpose' => 'Simple XML parsing',             'required' => false ],
        [ 'name' => 'xmlreader', 'loaded' => extension_loaded('xmlreader'),    'purpose' => 'XML stream reader',              'required' => false ],
        [ 'name' => 'zlib',      'loaded' => extension_loaded('zlib'),         'purpose' => 'Gzip compression',               'required' => false ],
    ];
}
}


/**
 * ═══════════════════════════════════════════════════════════════════════════
 * GOING LIVE CHECKLIST — RECOMMENDED SNIPPET IDS
 * ═══════════════════════════════════════════════════════════════════════════
 * @since 10.9.0
 */
if ( ! function_exists( __NAMESPACE__ . '\\hws_get_going_live_snippets' ) ) {
function hws_get_going_live_snippets() {
    return [
        'register_acf_website_settings',
        'register_user_custom_fields_2025',
        'register_user_custom_fields_additional_2025',
        'enable_website_settings_functionality',
        'enable_auto_update_plugins',
        'enable_auto_update_themes',
        'enable_elementor_social_icon_cleanup',
        'enable_wp_admin_logo',
    ];
}
}


/**
 * ═══════════════════════════════════════════════════════════════════════════
 * ROBUST REDIS STATUS CHECK
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Reads LiteSpeed Cache's effective host/port config to connect (instead of
 * hardcoding 127.0.0.1:6379). Falls back to defaults if LiteSpeed is absent.
 *
 * Returns detailed array with extension, connection, litespeed, server info.
 *
 * @since 10.9.1
 * @return array { active: bool, extension: bool, connected: bool, litespeed_enabled: bool, info: array, error: string }
 */
if ( ! function_exists( __NAMESPACE__ . '\\hws_check_redis_status' ) ) {
function hws_check_redis_status() {
    return ( new \HWS\BaseTools\LegacyCompatibility\GenericLibrary\CacheDiagnostics() )->redis_status();
}
}


/**
 * ═══════════════════════════════════════════════════════════════════════════
 * BROTLI SUPPORT CHECK
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Brotli is a server-level feature on LiteSpeed/OpenLiteSpeed.
 * Detection: check Accept-Encoding header from client + check if
 * server advertises br via a self-request, or check ini settings.
 *
 * @since 10.9.1
 * @return array { enabled: bool, details: string }
 */
if ( ! function_exists( __NAMESPACE__ . '\\hws_check_brotli_support' ) ) {
function hws_check_brotli_support() {
    // — Method 1: Check if PHP brotli extension is loaded
    $php_ext = function_exists( 'brotli_compress' );

    // — Method 2: Check server response headers via self-request
    $server_br = false;
    $details   = [];
    $test_url  = home_url( '/' );

    $response = wp_remote_get( $test_url, [
        'timeout'   => 5,
        'headers'   => [ 'Accept-Encoding' => 'br, gzip, deflate' ],
        'sslverify' => false,
    ] );

    if ( ! is_wp_error( $response ) ) {
        $encoding = wp_remote_retrieve_header( $response, 'content-encoding' );
        if ( stripos( $encoding, 'br' ) !== false ) {
            $server_br = true;
            $details[] = 'Server responds with Content-Encoding: br';
        }

        // — Also check LiteSpeed-specific header
        $x_ls = wp_remote_retrieve_header( $response, 'x-litespeed-cache' );
        if ( $x_ls ) {
            $details[] = 'LiteSpeed cache header detected';
        }
    }

    // — Method 3: Check if LiteSpeed server is present (Brotli built-in)
    $server_sw = $_SERVER['SERVER_SOFTWARE'] ?? '';
    if ( stripos( $server_sw, 'LiteSpeed' ) !== false ) {
        $details[] = 'LiteSpeed server (Brotli built-in)';
    }

    $enabled = $server_br || $php_ext;
    if ( $php_ext ) $details[] = 'PHP brotli extension loaded';

    return [
        'enabled' => $enabled,
        'details' => ! empty( $details ) ? implode( ' · ', $details ) : 'Not detected',
    ];
}
}


/**
 * ═══════════════════════════════════════════════════════════════════════════
 * LITESPEED CACHE INFO — READ ALL RELEVANT SETTINGS
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Reads effective LiteSpeed Cache settings through LiteSpeed's Conf API.
 *
 * @since 10.9.1
 * @return array|false  Settings array or false if plugin not active
 */
if ( ! function_exists( __NAMESPACE__ . '\\hws_get_litespeed_info' ) ) {
function hws_get_litespeed_info() {
    return ( new \HWS\BaseTools\LegacyCompatibility\GenericLibrary\CacheDiagnostics() )->litespeed_info();
}
}


/**
 * ═══════════════════════════════════════════════════════════════════════════
 * GOING LIVE CHECKLIST — SETTINGS & SERVER CHECKS
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Returns an array of named checks each with: label, pass (bool), value (string).
 * Used by the GLC panel to show full site readiness.
 *
 * @since 10.9.1
 * @return array [ [ 'label' => '...', 'pass' => bool, 'value' => '...' ], ... ]
 */
if ( ! function_exists( __NAMESPACE__ . '\\hws_get_glc_settings_checks' ) ) {
function hws_get_glc_settings_checks() {
    $checks = [];

    // ─── WORDPRESS SETTINGS ────────────────────────────────────────────

    // — WP_MEMORY_LIMIT > 512MB
    $mem_limit = defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : '40M';
    $mem_bytes = wp_convert_hr_to_bytes( $mem_limit );
    $checks[] = [
        'label' => 'WP Memory Limit > 512MB',
        'pass'  => $mem_bytes >= 536870912,
        'value' => $mem_limit,
    ];

    // — Comments disabled
    $comments_closed = get_option( 'default_comment_status' ) === 'closed';
    $checks[] = [
        'label' => 'Comments Disabled',
        'pass'  => $comments_closed,
        'value' => $comments_closed ? 'Closed' : 'Open',
    ];

    // — Pingbacks disabled
    $pings_closed = get_option( 'default_ping_status' ) === 'closed';
    $checks[] = [
        'label' => 'Pingbacks Disabled',
        'pass'  => $pings_closed,
        'value' => $pings_closed ? 'Closed' : 'Open',
    ];

    // — SMTP / Email Authentication active
    $smtp = function_exists( __NAMESPACE__ . '\\check_smtp_auth_status_and_mailer' )
        ? check_smtp_auth_status_and_mailer()
        : [ 'status' => false, 'mailer' => '', 'raw_value' => '' ];
    $checks[] = [
        'label' => 'Email / SMTP Authenticated',
        'pass'  => (bool) $smtp['status'],
        'value' => $smtp['status'] ? ucfirst( $smtp['mailer'] ) : ( $smtp['raw_value'] ?: 'Not configured' ),
    ];

    // — WP_DEBUG off
    $debug_on = defined( 'WP_DEBUG' ) && WP_DEBUG;
    $checks[] = [
        'label' => 'WP_DEBUG Off',
        'pass'  => ! $debug_on,
        'value' => $debug_on ? 'ON' : 'Off',
    ];

    // — WP_DEBUG_DISPLAY off
    $debug_display = defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY;
    $checks[] = [
        'label' => 'WP_DEBUG_DISPLAY Off',
        'pass'  => ! $debug_display,
        'value' => $debug_display ? 'ON' : 'Off',
    ];

    // — WP_DEBUG_LOG off
    $debug_log = defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
    $checks[] = [
        'label' => 'WP_DEBUG_LOG Off',
        'pass'  => ! $debug_log,
        'value' => $debug_log ? 'ON' : 'Off',
    ];

    // — Wordfence alert email set
    if ( function_exists( __NAMESPACE__ . '\\check_wordfence_notification_email' ) ) {
        $wf = check_wordfence_notification_email();
        $checks[] = [
            'label' => 'Wordfence Alert Email Set',
            'pass'  => (bool) ( $wf['status'] ?? false ),
            'value' => ( $wf['raw_value'] ?? $wf['details'] ?? 'Not set' ),
        ];
    }

    // — display_errors off (should be off in production)
    $display_errors = ini_get( 'display_errors' );
    $de_off = ( ! $display_errors || $display_errors === '0' || strtolower( $display_errors ) === 'off' );
    $checks[] = [
        'label' => 'display_errors Off',
        'pass'  => $de_off,
        'value' => $de_off ? 'Off' : 'ON (' . $display_errors . ')',
    ];

    // — Individual log file checks (each < 10MB)
    $max_log = 10 * 1024 * 1024; // 10MB
    $log_files = [
        'debug.log'          => WP_CONTENT_DIR . '/debug.log',
        'error_log (root)'   => ABSPATH . 'error_log',
        'error_log (admin)'  => ABSPATH . 'wp-admin/error_log',
    ];
    foreach ( $log_files as $label => $path ) {
        $size = file_exists( $path ) ? filesize( $path ) : 0;
        $checks[] = [
            'label' => $label . ' < 10MB',
            'pass'  => $size < $max_log,
            'value' => file_exists( $path ) ? size_format( $size ) : 'Not found (good)',
        ];
    }

    // — DISABLE_WP_CRON (should be true for production with real cron)
    $cron_disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
    $checks[] = [
        'label' => 'WP Cron Disabled (real cron)',
        'pass'  => $cron_disabled,
        'value' => $cron_disabled ? 'Disabled (good)' : 'WP Cron active',
    ];

    // ─── SERVER & PHP ──────────────────────────────────────────────────

    // — Cloudflare active (check headers + nameservers)
    if ( function_exists( __NAMESPACE__ . '\\check_cloudflare_active' ) ) {
        $cf = check_cloudflare_active();
        $checks[] = [
            'label' => 'Cloudflare Active',
            'pass'  => (bool) ( $cf['status'] ?? false ),
            'value' => $cf['raw_value'] ?? 'Unknown',
        ];
    }

    // — PHP SAPI = litespeed
    $sapi = php_sapi_name();
    $checks[] = [
        'label' => 'PHP SAPI: LiteSpeed',
        'pass'  => ( $sapi === 'litespeed' ),
        'value' => $sapi,
    ];

    // — PHP version >= 8.1
    $php_ver = phpversion();
    $checks[] = [
        'label' => 'PHP ≥ 8.1',
        'pass'  => version_compare( $php_ver, '8.1', '>=' ),
        'value' => $php_ver,
    ];

    // — Imagick available
    $imagick = extension_loaded( 'imagick' );
    $checks[] = [
        'label' => 'Imagick Library',
        'pass'  => $imagick,
        'value' => $imagick ? 'Available' : 'Missing',
    ];

    // — MyISAM tables (scoped to current WP prefix only)
    if ( function_exists( __NAMESPACE__ . '\\check_myisam_tables' ) ) {
        $myisam = check_myisam_tables();
        $checks[] = [
            'label' => 'No MyISAM Tables',
            'pass'  => (bool) ( $myisam['status'] ?? false ),
            'value' => $myisam['raw_value'] ?? 'Unknown',
        ];
    }

    // — Redis active (safe access with null-coalescing on every key)
    if ( function_exists( __NAMESPACE__ . '\\hws_check_redis_status' ) ) {
        $redis     = hws_check_redis_status();
        $r_active  = $redis['active'] ?? false;
        $r_error   = $redis['error'] ?? '';
        $r_info    = $redis['info'] ?? [];
        $redis_val = $r_active
            ? 'Active (v' . ( $r_info['version'] ?? '?' ) . ', ' . ( $r_info['used_memory'] ?? '' ) . ')'
            : ( $r_error ?: 'Inactive' );
        $checks[] = [
            'label' => 'Redis Active',
            'pass'  => (bool) $r_active,
            'value' => $redis_val,
        ];
    }

    // — post_max_size >= 128MB
    $post_max     = ini_get( 'post_max_size' );
    $post_max_b   = wp_convert_hr_to_bytes( $post_max );
    $checks[] = [
        'label' => 'post_max_size ≥ 128MB',
        'pass'  => $post_max_b >= 134217728,
        'value' => $post_max,
    ];

    // — upload_max_filesize >= 128MB
    $upload_max   = ini_get( 'upload_max_filesize' );
    $upload_max_b = wp_convert_hr_to_bytes( $upload_max );
    $checks[] = [
        'label' => 'upload_max_filesize ≥ 128MB',
        'pass'  => $upload_max_b >= 134217728,
        'value' => $upload_max,
    ];

    // — Brotli enabled
    $brotli = hws_check_brotli_support();
    $checks[] = [
        'label' => 'Brotli Compression',
        'pass'  => $brotli['enabled'],
        'value' => $brotli['details'],
    ];

    // ─── THEMES & PLUGINS ──────────────────────────────────────────────

    // — No more than 2 themes installed
    $all_themes  = wp_get_themes();
    $theme_count = count( $all_themes );
    $checks[] = [
        'label' => 'Max 2 Themes Installed',
        'pass'  => $theme_count <= 2,
        'value' => $theme_count . ' theme(s)',
    ];

    // — All themes updated
    $theme_updates = get_site_transient( 'update_themes' );
    $outdated_themes = ! empty( $theme_updates->response ) ? count( $theme_updates->response ) : 0;
    $checks[] = [
        'label' => 'All Themes Updated',
        'pass'  => $outdated_themes === 0,
        'value' => $outdated_themes > 0 ? $outdated_themes . ' update(s) available' : 'Up to date',
    ];

    // — All plugins updated
    $plugin_updates  = get_site_transient( 'update_plugins' );
    $outdated_plugins = ! empty( $plugin_updates->response ) ? count( $plugin_updates->response ) : 0;
    $checks[] = [
        'label' => 'All Plugins Updated',
        'pass'  => $outdated_plugins === 0,
        'value' => $outdated_plugins > 0 ? $outdated_plugins . ' update(s) available' : 'Up to date',
    ];

    // — Detect default Twenty* themes (should be removed)
    $twenty_themes = [];
    $twenty_slugs  = [ 'twentytwentyfive', 'twentytwentyfour', 'twentytwentythree', 'twentytwentytwo', 'twentytwentyone', 'twentytwenty', 'twentynineteen' ];
    foreach ( $all_themes as $slug => $theme ) {
        if ( in_array( $slug, $twenty_slugs, true ) ) {
            $twenty_themes[] = $theme->get( 'Name' );
        }
    }
    $checks[] = [
        'label' => 'No Default Twenty* Themes',
        'pass'  => empty( $twenty_themes ),
        'value' => empty( $twenty_themes )
            ? 'None found'
            : implode( ', ', $twenty_themes ),
    ];

    return $checks;
}
}
