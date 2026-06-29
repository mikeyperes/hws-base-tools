<?php

namespace hws_base_tools;

use Hexa\PluginCore\WpAdminComponents\CoreUi;
use Hexa\PluginCore\WpAdminComponents\DynamicButton;

defined( 'ABSPATH' ) || exit;

const HWS_SITEMAP_NOCACHE_OPTION = 'hws_sitemaps_litespeed_nocache_enabled';

function hws_sitemaps_nocache_option_value(): string {
    $value = get_option( HWS_SITEMAP_NOCACHE_OPTION, null );

    if ( null === $value || false === $value ) {
        global $wpdb;

        $raw = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                HWS_SITEMAP_NOCACHE_OPTION
            )
        );

        if ( null !== $raw ) {
            return (string) $raw;
        }
    }

    return null === $value || false === $value ? 'no' : (string) $value;
}

function hws_sitemaps_nocache_enabled(): bool {
    return 'yes' === hws_sitemaps_nocache_option_value();
}

function hws_sitemaps_is_sitemap_uri( ?string $uri = null ): bool {
    $uri  = null === $uri ? (string) ( $_SERVER['REQUEST_URI'] ?? '' ) : $uri;
    $path = strtolower( (string) wp_parse_url( $uri, PHP_URL_PATH ) );

    if ( '' === $path || false === strpos( $path, 'sitemap' ) ) {
        return false;
    }

    return (bool) preg_match( '#/(sitemap(_index)?|wp-sitemap|[^/]+-sitemap)([^/]*\.xml)?$#', $path );
}

function hws_sitemaps_apply_litespeed_nocache(): void {
    if ( ! hws_sitemaps_nocache_enabled() ) {
        return;
    }

    add_filter( 'rank_math/sitemap/enable_caching', '__return_false' );

    if ( ! hws_sitemaps_is_sitemap_uri() ) {
        return;
    }

    if ( ! defined( 'DONOTCACHEPAGE' ) ) {
        define( 'DONOTCACHEPAGE', true );
    }

    do_action( 'litespeed_control_set_nocache', 'hws-sitemaps' );

    add_action(
        'send_headers',
        static function (): void {
            if ( headers_sent() ) {
                return;
            }

            header( 'X-LiteSpeed-Cache-Control: no-cache' );
            nocache_headers();
        },
        0
    );
}
add_action( 'init', __NAMESPACE__ . '\\hws_sitemaps_apply_litespeed_nocache', 0 );

function hws_sitemaps_rank_math_active(): bool {
    if ( class_exists( '\RankMath\Plugin' ) || defined( 'RANK_MATH_VERSION' ) ) {
        return true;
    }

    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    return function_exists( 'is_plugin_active' ) && is_plugin_active( 'seo-by-rank-math/rank-math.php' );
}

function hws_sitemaps_provider(): string {
    return hws_sitemaps_rank_math_active() ? 'rank_math' : 'wp_core';
}

function hws_sitemaps_provider_label(): string {
    return 'rank_math' === hws_sitemaps_provider() ? 'Rank Math' : 'WordPress Core';
}

function hws_sitemaps_rank_math_module_enabled( string $module ): bool {
    $modules = get_option( 'rank_math_modules', [] );

    if ( is_string( $modules ) ) {
        $decoded = maybe_unserialize( $modules );
        $modules = is_array( $decoded ) ? $decoded : [];
    }

    return in_array( $module, (array) $modules, true );
}

function hws_sitemaps_rank_math_news_sitemap_enabled(): bool {
    return hws_sitemaps_rank_math_active() && hws_sitemaps_rank_math_module_enabled( 'news-sitemap' );
}

function hws_sitemaps_index_url(): string {
    return 'rank_math' === hws_sitemaps_provider()
        ? home_url( '/sitemap_index.xml' )
        : home_url( '/wp-sitemap.xml' );
}

function hws_sitemaps_news_url(): string {
    return home_url( '/news-sitemap.xml' );
}

function hws_sitemaps_url_for_post_type( string $post_type ): string {
    if ( 'rank_math' === hws_sitemaps_provider() ) {
        return home_url( '/' . $post_type . '-sitemap.xml' );
    }

    return home_url( '/wp-sitemap-posts-' . $post_type . '-1.xml' );
}

function hws_sitemaps_rows(): array {
    $rows = [
        [
            'key'       => 'sitemap_index',
            'label'     => 'Sitemap index',
            'post_type' => '',
            'type'      => 'Index',
            'provider'  => hws_sitemaps_provider_label(),
            'url'       => hws_sitemaps_index_url(),
        ],
    ];

    if ( hws_site_type_is_news_outlet() ) {
        $rows[] = [
            'key'                     => 'rank_math_news_sitemap',
            'label'                   => 'News Sitemap',
            'post_type'               => '',
            'type'                    => 'News Outlet',
            'provider'                => 'Rank Math',
            'url'                     => hws_sitemaps_news_url(),
            'requires_rank_math_news' => true,
            'rank_math_news_enabled'  => hws_sitemaps_rank_math_news_sitemap_enabled(),
        ];
    }

    $objects = get_post_types( [ 'public' => true ], 'objects' );
    unset( $objects['attachment'] );

    uasort(
        $objects,
        static function ( $a, $b ): int {
            $priority = [ 'post' => 0, 'page' => 1 ];
            $a_key    = $a->name ?? '';
            $b_key    = $b->name ?? '';
            $a_order  = $priority[ $a_key ] ?? 10;
            $b_order  = $priority[ $b_key ] ?? 10;

            if ( $a_order !== $b_order ) {
                return $a_order <=> $b_order;
            }

            return strcasecmp( (string) ( $a->labels->name ?? $a_key ), (string) ( $b->labels->name ?? $b_key ) );
        }
    );

    foreach ( $objects as $post_type => $object ) {
        $label = 'post' === $post_type ? 'Posts' : ( 'page' === $post_type ? 'Pages' : (string) ( $object->labels->name ?? $post_type ) );
        $rows[] = [
            'key'       => sanitize_key( 'post_type_' . $post_type ),
            'label'     => $label,
            'post_type' => $post_type,
            'type'      => in_array( $post_type, [ 'post', 'page' ], true ) ? 'WordPress' : 'Custom post type',
            'provider'  => hws_sitemaps_provider_label(),
            'url'       => hws_sitemaps_url_for_post_type( (string) $post_type ),
        ];
    }

    return $rows;
}

function hws_sitemaps_flatten_headers( $headers ): array {
    if ( is_object( $headers ) && method_exists( $headers, 'getAll' ) ) {
        $headers = $headers->getAll();
    }

    $flat = [];
    foreach ( (array) $headers as $name => $value ) {
        $flat[ strtolower( (string) $name ) ] = is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value;
    }

    return $flat;
}

function hws_sitemaps_litespeed_cache_state( array $headers ): array {
    $cache         = $headers['x-litespeed-cache'] ?? '';
    $cache_control = $headers['x-litespeed-cache-control'] ?? '';
    $tag           = $headers['x-litespeed-tag'] ?? '';
    $combined      = strtolower( trim( $cache . ' ' . $cache_control . ' ' . $tag ) );
    $no_cache      = false !== strpos( $combined, 'no-cache' ) || false !== strpos( $combined, 'no-store' );
    $cached        = preg_match( '/\b(hit|miss|esi)\b/i', $cache ) || ( '' !== $tag && ! $no_cache );
    $ok            = $no_cache || ! $cached;

    if ( $no_cache ) {
        $message = 'LiteSpeed no-cache header detected.';
    } elseif ( $cached ) {
        $message = 'LiteSpeed cache headers detected: ' . trim( $cache . ' ' . $cache_control );
    } else {
        $message = 'No LiteSpeed cache header detected.';
    }

    return [
        'ok'       => (bool) $ok,
        'cached'   => (bool) $cached,
        'message'  => $message,
        'headers'  => [
            'x-litespeed-cache'         => $cache,
            'x-litespeed-cache-control' => $cache_control,
            'x-litespeed-tag'           => $tag,
        ],
    ];
}

function hws_sitemaps_scan_url( array $row ): array {
    if ( ! empty( $row['requires_rank_math_news'] ) && ! hws_sitemaps_rank_math_news_sitemap_enabled() ) {
        return array_merge(
            $row,
            [
                'active'           => false,
                'status_code'      => 0,
                'content_type'     => '',
                'cache_ok'         => false,
                'cache_applicable' => false,
                'cache_message'    => 'Not checked until Rank Math News Sitemap is enabled.',
                'message'          => hws_sitemaps_rank_math_active()
                    ? 'Rank Math is active, but the News Sitemap module is not enabled.'
                    : 'Rank Math is not active, so the News Sitemap cannot be checked.',
            ]
        );
    }

    $url      = (string) $row['url'];
    $response = wp_remote_get(
        $url,
        [
            'timeout'             => 10,
            'redirection'         => 3,
            'sslverify'           => false,
            'limit_response_size' => 256000,
            'headers'             => [
                'Cache-Control' => 'no-cache',
            ],
        ]
    );

    if ( is_wp_error( $response ) ) {
        return array_merge(
            $row,
            [
                'active'           => false,
                'status_code'      => 0,
                'content_type'     => '',
                'cache_ok'         => false,
                'cache_applicable' => false,
                'cache_message'    => 'Request failed before cache headers could be read.',
                'message'          => $response->get_error_message(),
            ]
        );
    }

    $code         = (int) wp_remote_retrieve_response_code( $response );
    $headers      = hws_sitemaps_flatten_headers( wp_remote_retrieve_headers( $response ) );
    $content_type = $headers['content-type'] ?? '';
    $body         = (string) wp_remote_retrieve_body( $response );
    $looks_xml    = false !== stripos( $content_type, 'xml' )
        || false !== stripos( $body, '<urlset' )
        || false !== stripos( $body, '<sitemapindex' )
        || false !== stripos( $body, '<loc>' );
    $active       = $code >= 200 && $code < 300 && $looks_xml;
    $cache_state  = hws_sitemaps_litespeed_cache_state( $headers );

    return array_merge(
        $row,
        [
            'active'           => $active,
            'status_code'      => $code,
            'content_type'     => $content_type,
            'cache_ok'         => (bool) $cache_state['ok'],
            'cache_applicable' => true,
            'cache_message'    => (string) $cache_state['message'],
            'cache_headers'    => $cache_state['headers'],
            'message'          => $active ? 'Sitemap returned XML.' : 'Sitemap did not return a valid XML response.',
        ]
    );
}

function hws_sitemaps_scan_all(): array {
    return array_map( __NAMESPACE__ . '\\hws_sitemaps_scan_url', hws_sitemaps_rows() );
}

function hws_sitemaps_ajax_scan(): void {
    if ( ! current_user_can( Config::$settings_page_capability ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
    }

    hws_require_ajax_nonce_or_error();

    wp_send_json_success(
        [
            'rows'                 => hws_sitemaps_scan_all(),
            'nocache_enabled'      => hws_sitemaps_nocache_enabled(),
            'provider'             => hws_sitemaps_provider_label(),
            'rank_math_settings'   => admin_url( 'admin.php?page=rank-math-options-sitemap#sitemap-general' ),
            'permalink_settings'   => admin_url( 'options-permalink.php' ),
            'litespeed_settings'   => admin_url( 'admin.php?page=litespeed' ),
        ]
    );
}
add_action( 'wp_ajax_hws_sitemap_scan', __NAMESPACE__ . '\\hws_sitemaps_ajax_scan' );

function hws_sitemaps_ajax_disable_cache(): void {
    if ( ! current_user_can( Config::$settings_page_capability ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
    }

    hws_require_ajax_nonce_or_error();
    update_option( HWS_SITEMAP_NOCACHE_OPTION, 'yes', false );
    wp_cache_delete( HWS_SITEMAP_NOCACHE_OPTION, 'options' );
    wp_cache_delete( 'notoptions', 'options' );

    wp_send_json_success(
        [
            'nocache_enabled' => true,
            'message'         => 'Sitemap no-cache headers are enabled. Sitemap requests will send X-LiteSpeed-Cache-Control: no-cache.',
        ]
    );
}
add_action( 'wp_ajax_hws_sitemap_disable_cache', __NAMESPACE__ . '\\hws_sitemaps_ajax_disable_cache' );

function hws_sitemaps_ajax_purge_litespeed(): void {
    if ( ! current_user_can( Config::$settings_page_capability ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
    }

    hws_require_ajax_nonce_or_error();

    $urls   = wp_list_pluck( hws_sitemaps_rows(), 'url' );
    $purged = [];

    foreach ( $urls as $url ) {
        do_action( 'litespeed_purge_url', $url );
        $purged[] = $url;
    }

    wp_send_json_success(
        [
            'message' => 'LiteSpeed sitemap URL purge hooks fired.',
            'urls'    => $purged,
        ]
    );
}
add_action( 'wp_ajax_hws_sitemap_purge_litespeed', __NAMESPACE__ . '\\hws_sitemaps_ajax_purge_litespeed' );

function hws_sitemaps_ajax_flush_permalinks(): void {
    if ( ! current_user_can( Config::$settings_page_capability ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
    }

    hws_require_ajax_nonce_or_error();
    flush_rewrite_rules( false );

    wp_send_json_success(
        [
            'message' => 'Permalink rewrite rules refreshed.',
            'ran_at'  => current_time( 'mysql' ),
        ]
    );
}
add_action( 'wp_ajax_hws_sitemap_flush_permalinks', __NAMESPACE__ . '\\hws_sitemaps_ajax_flush_permalinks' );

function render_tab_sitemaps(): void {
    CoreUi::render_assets();
    DynamicButton::render_assets();

    $rows       = hws_sitemaps_rows();
    $nonce      = wp_create_nonce( HWS_AJAX_NONCE );
    $nocache_on = hws_sitemaps_nocache_enabled();
    $site_type  = hws_get_site_type_label();
    $news_site  = hws_site_type_is_news_outlet();
    $news_on    = hws_sitemaps_rank_math_news_sitemap_enabled();
    ?>
    <div id="hws-sitemaps" class="hpc-ui hws-sitemaps" data-nonce="<?php echo esc_attr( $nonce ); ?>">
        <style>
            #hws-sitemaps .hws-sitemap-table{border-collapse:collapse;width:100%}
            #hws-sitemaps .hws-sitemap-table th,#hws-sitemaps .hws-sitemap-table td{border-bottom:1px solid var(--hpc-line);padding:10px;text-align:left;vertical-align:top}
            #hws-sitemaps .hws-sitemap-table th{background:#f8fafc;color:#314056;font-size:12px;text-transform:uppercase}
            #hws-sitemaps .hws-sitemap-url{word-break:break-all}
            #hws-sitemaps .hws-sitemap-meta{color:var(--hpc-muted);font-size:12px;margin-top:4px}
            #hws-sitemaps .hws-sitemap-status{min-width:150px}
            #hws-sitemaps .hws-sitemap-log{background:#0f1720;border-radius:8px;color:#dbe7f3;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;min-height:54px;padding:12px;white-space:pre-wrap}
            #hws-sitemaps .hws-sitemap-nocache-on{border-left:4px solid var(--hpc-green)}
            #hws-sitemaps .hws-sitemap-nocache-off{border-left:4px solid var(--hpc-amber)}
        </style>

        <div class="hpc-hero">
            <div>
                <h2>Sitemaps</h2>
                <p>Scan Rank Math or WordPress sitemap endpoints for posts, pages, and public custom post types. Confirm active XML responses and LiteSpeed cache state from response headers.</p>
            </div>
            <div class="hpc-actions">
                <?php echo CoreUi::pill( 'Website type: ' . $site_type, 'dark' ); ?>
                <?php echo CoreUi::pill( 'Provider: ' . hws_sitemaps_provider_label(), 'dark' ); ?>
                <?php if ( $news_site ) : ?>
                    <?php echo CoreUi::pill( $news_on ? 'Rank Math News Sitemap enabled' : 'Rank Math News Sitemap disabled', $news_on ? 'success' : 'danger' ); ?>
                <?php endif; ?>
                <?php echo CoreUi::pill( $nocache_on ? 'Sitemap no-cache enabled' : 'Sitemap no-cache not enabled', $nocache_on ? 'success' : 'warning' ); ?>
            </div>
        </div>

        <div class="hpc-grid two">
            <section class="hpc-card <?php echo $nocache_on ? 'hws-sitemap-nocache-on' : 'hws-sitemap-nocache-off'; ?>">
                <h3>Actions</h3>
                <p>Run a live scan, disable LiteSpeed cache for sitemap requests, purge sitemap URLs, or refresh rewrite rules.</p>
                <div class="hpc-actions">
                    <?php echo DynamicButton::render( [ 'id' => 'hws-sitemap-scan', 'label' => 'Scan Sitemaps', 'working_label' => 'Scanning...', 'success_label' => 'Scanned', 'class' => 'hpc-button', 'attrs' => [ 'data-hws-sitemap-action' => 'scan' ] ] ); ?>
                    <?php echo DynamicButton::render( [ 'id' => 'hws-sitemap-disable-cache', 'label' => 'Disable Cache for Sitemaps', 'working_label' => 'Applying...', 'success_label' => 'No-cache enabled', 'class' => 'hpc-button secondary', 'attrs' => [ 'data-hws-sitemap-action' => 'disable-cache' ] ] ); ?>
                    <?php echo DynamicButton::render( [ 'id' => 'hws-sitemap-purge', 'label' => 'LiteSpeed Purge Sitemaps', 'working_label' => 'Purging...', 'success_label' => 'Purge fired', 'class' => 'hpc-button secondary', 'attrs' => [ 'data-hws-sitemap-action' => 'purge' ] ] ); ?>
                    <?php echo DynamicButton::render( [ 'id' => 'hws-sitemap-flush-permalinks', 'label' => 'Refresh Permalinks', 'working_label' => 'Refreshing...', 'success_label' => 'Refreshed', 'class' => 'hpc-button secondary', 'attrs' => [ 'data-hws-sitemap-action' => 'flush-permalinks' ] ] ); ?>
                </div>
            </section>

            <section class="hpc-card">
                <h3>Settings Links</h3>
                <p>Open related WordPress, Rank Math, and LiteSpeed settings in a new tab.</p>
                <div class="hpc-actions">
                    <?php echo CoreUi::external_link( admin_url( 'admin.php?page=rank-math-options-sitemap#sitemap-general' ), 'Rank Math Sitemaps', 'hpc-button secondary' ); ?>
                    <?php echo CoreUi::external_link( admin_url( 'admin.php?page=rank-math-options-sitemap#post-types' ), 'Rank Math Post Types', 'hpc-button secondary' ); ?>
                    <?php echo CoreUi::external_link( admin_url( 'admin.php?page=rank-math-options-sitemap#news-sitemap' ), 'Rank Math News Sitemap', 'hpc-button secondary' ); ?>
                    <?php echo CoreUi::external_link( admin_url( 'admin.php?page=litespeed' ), 'LiteSpeed Cache', 'hpc-button secondary' ); ?>
                    <?php echo CoreUi::external_link( admin_url( 'options-permalink.php' ), 'Permalink Settings', 'hpc-button secondary' ); ?>
                </div>
            </section>
        </div>

        <section class="hpc-card">
            <h3>Sitemap URLs</h3>
            <table class="hws-sitemap-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Sitemap URL</th>
                        <th>Active</th>
                        <th>LiteSpeed cache</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rows as $row ) : ?>
                        <tr data-sitemap-key="<?php echo esc_attr( $row['key'] ); ?>">
                            <td>
                                <strong><?php echo esc_html( $row['label'] ); ?></strong>
                                <div class="hws-sitemap-meta"><?php echo esc_html( $row['type'] ); ?> / <?php echo esc_html( $row['provider'] ); ?></div>
                            </td>
                            <td class="hws-sitemap-url">
                                <a href="<?php echo esc_url( $row['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $row['url'] ); ?></a>
                            </td>
                            <td class="hws-sitemap-status" data-role="active-status"><?php echo CoreUi::pill( 'Not scanned', 'warning' ); ?></td>
                            <td class="hws-sitemap-status" data-role="cache-status"><?php echo CoreUi::pill( 'Not scanned', 'warning' ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="hpc-card">
            <h3>Activity</h3>
            <div class="hws-sitemap-log" data-role="activity-log">Ready.</div>
        </section>
    </div>

    <script>
    (function($){
        var root = $('#hws-sitemaps');
        if (!root.length || root.data('ready')) return;
        root.data('ready', true);

        var nonce = root.data('nonce') || window.hwsNonce || '';
        var log = root.find('[data-role="activity-log"]');

        function setLog(message) {
            log.text(message || '');
        }

        function ajax(action, data) {
            return $.ajax({
                url: window.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: $.extend({ action: action, nonce: nonce }, data || {})
            });
        }

        function pill(ok, good, bad) {
            return '<span class="hpc-pill ' + (ok ? 'success' : 'danger') + '">' + (ok ? '&#10003; ' + good : '&#10007; ' + bad) + '</span>';
        }

        function warning(text) {
            return '<span class="hpc-pill warning">' + text + '</span>';
        }

        function applyRows(rows) {
            $.each(rows || [], function(index, row) {
                var tr = root.find('tr[data-sitemap-key="' + row.key + '"]');
                if (!tr.length) return;
                tr.find('[data-role="active-status"]').html(pill(!!row.active, 'Active', 'Inactive') + '<div class="hws-sitemap-meta">HTTP ' + (row.status_code || 0) + '</div>');
                if (row.cache_applicable === false) {
                    tr.find('[data-role="cache-status"]').html(warning('Not checked') + '<div class="hws-sitemap-meta"></div>');
                } else {
                    tr.find('[data-role="cache-status"]').html(pill(!!row.cache_ok, 'Not cached', 'Cached') + '<div class="hws-sitemap-meta"></div>');
                }
                tr.find('[data-role="cache-status"] .hws-sitemap-meta').text(row.cache_message || '');
            });
        }

        function runScan(button) {
            if (window.HexaWpCoreDynamicButton) window.HexaWpCoreDynamicButton.start(button, 'Scanning...');
            setLog('Scanning sitemap URLs...');
            ajax('hws_sitemap_scan').done(function(response){
                if (response && response.success) {
                    applyRows(response.data.rows || []);
                    var inactive = (response.data.rows || []).filter(function(row){ return !row.active; }).length;
                    var cached = (response.data.rows || []).filter(function(row){ return !row.cache_ok; }).length;
                    setLog('Scan complete. Inactive: ' + inactive + '. LiteSpeed cached: ' + cached + '.');
                    if (window.HexaWpCoreDynamicButton) window.HexaWpCoreDynamicButton.success(button, 'Scanned');
                } else {
                    setLog(response && response.data && response.data.message ? response.data.message : 'Scan failed.');
                    if (window.HexaWpCoreDynamicButton) window.HexaWpCoreDynamicButton.error(button, 'Failed');
                }
            }).fail(function(){
                setLog('AJAX error while scanning sitemaps.');
                if (window.HexaWpCoreDynamicButton) window.HexaWpCoreDynamicButton.error(button, 'Failed');
            });
        }

        root.on('click', '[data-hws-sitemap-action]', function(){
            var button = this;
            var action = $(button).data('hws-sitemap-action');

            if (action === 'scan') {
                runScan(button);
                return;
            }

            var ajaxAction = '';
            var working = 'Working...';
            if (action === 'disable-cache') { ajaxAction = 'hws_sitemap_disable_cache'; working = 'Applying...'; }
            if (action === 'purge') { ajaxAction = 'hws_sitemap_purge_litespeed'; working = 'Purging...'; }
            if (action === 'flush-permalinks') { ajaxAction = 'hws_sitemap_flush_permalinks'; working = 'Refreshing...'; }
            if (!ajaxAction) return;

            if (window.HexaWpCoreDynamicButton) window.HexaWpCoreDynamicButton.start(button, working);
            setLog(working);

            ajax(ajaxAction).done(function(response){
                if (response && response.success) {
                    setLog(response.data && response.data.message ? response.data.message : 'Done.');
                    if (window.HexaWpCoreDynamicButton) window.HexaWpCoreDynamicButton.success(button);
                    if (action === 'disable-cache' || action === 'purge' || action === 'flush-permalinks') {
                        runScan(root.find('[data-hws-sitemap-action="scan"]').get(0));
                    }
                } else {
                    setLog(response && response.data && response.data.message ? response.data.message : 'Request failed.');
                    if (window.HexaWpCoreDynamicButton) window.HexaWpCoreDynamicButton.error(button);
                }
            }).fail(function(){
                setLog('AJAX error.');
                if (window.HexaWpCoreDynamicButton) window.HexaWpCoreDynamicButton.error(button);
            });
        });

        root.find('[data-role="active-status"]').html(warning('Not scanned'));
        root.find('[data-role="cache-status"]').html(warning('Not scanned'));
    })(jQuery);
    </script>
    <?php
}
