<?php

namespace hws_base_tools;

use Hexa\PluginCore\WpAdminComponents\CoreUi;
use HWS\BaseTools\AdminDashboard\SettingsAutosave;
use HWS\BaseTools\SitemapTools\FeedSitemapSettings;
use HWS\BaseTools\SitemapTools\SitemapFeedRegistry;
use HWS\BaseTools\SitemapTools\XmlInspector;

defined( 'ABSPATH' ) || exit;

/** Settings, Sitemaps and Settings, RSS Feeds: every sitemap or feed, its check and what it contains; feeds also have an on/off switch. */

function hws_sitemap_feeds_guard(): void {
    if ( ! current_user_can( Config::$settings_page_capability ) ) {
        wp_send_json_error( [ 'message' => 'You are not allowed to change these settings.' ], 403 );
    }
    hws_require_ajax_nonce_or_error();
}

/** Adds the display dates to a stored check result. */
function hws_sitemap_feeds_present( array $result ): array {
    $format = 'M j, Y g:i a';
    $result['display'] = [
        'checked_at'   => ! empty( $result['checked_at'] ) ? wp_date( $format, (int) $result['checked_at'] ) : '',
        'latest_item'  => ! empty( $result['latest_item'] ) ? wp_date( $format, (int) $result['latest_item'] ) : '',
        'latest_saved' => ! empty( $result['latest_saved'] ) ? wp_date( $format, (int) $result['latest_saved'] ) : '',
    ];
    return $result;
}

function hws_sitemap_feeds_ajax_check(): void {
    hws_sitemap_feeds_guard();
    $kind = 'sitemap' === sanitize_key( (string) ( $_POST['kind'] ?? '' ) ) ? 'sitemap' : 'feed';
    $key  = sanitize_key( (string) wp_unslash( $_POST['key'] ?? '' ) );
    $list = 'sitemap' === $kind ? SitemapFeedRegistry::sitemaps() : SitemapFeedRegistry::feeds();
    if ( ! isset( $list[ $key ] ) ) {
        wp_send_json_error( [ 'message' => 'That ' . $kind . ' is no longer listed. Reload the page.' ] );
    }

    $result = 'sitemap' === $kind ? XmlInspector::sitemap( $key, $list[ $key ] ) : XmlInspector::feed( $key, $list[ $key ] );
    SitemapFeedRegistry::save_result( $kind . ':' . $key, $result );

    wp_send_json_success( [ 'result' => hws_sitemap_feeds_present( $result ) ] );
}
add_action( 'wp_ajax_hws_sitemap_check', __NAMESPACE__ . '\\hws_sitemap_feeds_ajax_check' );

function hws_sitemap_feeds_ajax_toggle(): void {
    hws_sitemap_feeds_guard();
    $key   = sanitize_key( (string) wp_unslash( $_POST['key'] ?? '' ) );
    $on    = '1' === (string) ( $_POST['on'] ?? '' );
    $feeds = SitemapFeedRegistry::feeds();
    if ( ! isset( $feeds[ $key ] ) || empty( $feeds[ $key ]['toggle'] ) ) {
        wp_send_json_error( [ 'message' => 'This feed cannot be switched here.' ] );
    }

    SitemapFeedRegistry::set_disabled( $key, ! $on );
    if ( '' !== $feeds[ $key ]['url'] ) {
        do_action( 'litespeed_purge_url', $feeds[ $key ]['url'] );
    }
    do_action( 'litespeed_purge_all' ); // Page heads announce the active feeds.

    wp_send_json_success( [ 'message' => $feeds[ $key ]['label'] . ( $on ? ' feed is on.' : ' feed is off and now answers "not found".' ) . ' Cached pages were cleared.' ] );
}
add_action( 'wp_ajax_hws_sitemap_feed_toggle', __NAMESPACE__ . '\\hws_sitemap_feeds_ajax_toggle' );

function hws_sitemap_feeds_ajax_save_settings(): void {
    hws_sitemap_feeds_guard();
    $kind   = 'sitemap' === sanitize_key( (string) ( $_POST['kind'] ?? '' ) ) ? 'sitemap' : 'feed';
    $limits = json_decode( (string) wp_unslash( $_POST['limits'] ?? '{}' ), true );
    FeedSitemapSettings::save(
        $kind,
        [
            'limits' => is_array( $limits ) ? $limits : [],
            'cache'  => sanitize_text_field( (string) wp_unslash( $_POST['cache'] ?? 'default' ) ),
            'clear'  => '1' === (string) ( $_POST['clear'] ?? '' ),
            'size'   => (int) ( $_POST['size'] ?? 0 ),
        ]
    );
    $what = 'feed' === $kind ? 'feeds' : 'sitemaps';
    foreach ( 'feed' === $kind ? SitemapFeedRegistry::feeds() : SitemapFeedRegistry::sitemaps() as $entry ) {
        do_action( 'litespeed_purge_url', $entry['url'] );
    }
    wp_send_json_success( [ 'message' => 'Settings for ' . $what . ' saved. Cached ' . $what . ' were cleared so the change shows now.' ] );
}
add_action( 'wp_ajax_hws_sitemap_settings_save', __NAMESPACE__ . '\\hws_sitemap_feeds_ajax_save_settings' );

function hws_sitemaps_ajax_disable_cache(): void {
    hws_sitemap_feeds_guard();
    $on = '1' === (string) ( $_POST['on'] ?? '1' );
    update_option( HWS_SITEMAP_NOCACHE_OPTION, $on ? 'yes' : 'no', false );
    wp_cache_delete( HWS_SITEMAP_NOCACHE_OPTION, 'options' );
    wp_send_json_success( [ 'message' => $on ? 'Sitemaps are no longer cached, so search engines always get the current list.' : 'Sitemaps may be cached again.' ] );
}
add_action( 'wp_ajax_hws_sitemap_disable_cache', __NAMESPACE__ . '\\hws_sitemaps_ajax_disable_cache' );

function hws_sitemaps_ajax_purge_litespeed(): void {
    hws_sitemap_feeds_guard();
    $feeds = 'feed' === sanitize_key( (string) ( $_POST['kind'] ?? '' ) );
    $urls  = array_filter( array_column( $feeds ? SitemapFeedRegistry::feeds() : SitemapFeedRegistry::sitemaps(), 'url' ) );
    foreach ( $urls as $url ) {
        do_action( 'litespeed_purge_url', $url );
    }
    wp_send_json_success( [ 'message' => 'Cleared the cached copy of ' . count( $urls ) . ( $feeds ? ' feeds.' : ' sitemaps.' ) ] );
}
add_action( 'wp_ajax_hws_sitemap_purge_litespeed', __NAMESPACE__ . '\\hws_sitemaps_ajax_purge_litespeed' );

function hws_sitemaps_ajax_flush_permalinks(): void {
    hws_sitemap_feeds_guard();
    flush_rewrite_rules( false );
    wp_send_json_success( [ 'message' => 'Web addresses were refreshed.' ] );
}
add_action( 'wp_ajax_hws_sitemap_flush_permalinks', __NAMESPACE__ . '\\hws_sitemaps_ajax_flush_permalinks' );

/** The rows for one list, with their last stored check, for the screen's script. */
function hws_sitemap_feeds_rows( string $kind ): array {
    $results = SitemapFeedRegistry::results();
    $list    = 'sitemap' === $kind ? SitemapFeedRegistry::sitemaps() : SitemapFeedRegistry::feeds();
    $rows    = [];
    foreach ( $list as $key => $entry ) {
        $stored = $results[ $kind . ':' . $key ] ?? null;
        $rows[] = [
            'key'       => $key,
            'kind'      => $kind,
            'label'     => $entry['label'],
            'group'     => $entry['group'],
            'url'       => $entry['url'],
            'note'      => $entry['note'],
            'available' => (bool) $entry['available'],
            'settings'  => $entry['settings'],
            'settings_label' => $entry['settings_label'],
            'toggle'    => 'feed' === $kind && ! empty( $entry['toggle'] ),
            'on'        => 'feed' !== $kind || ! SitemapFeedRegistry::is_disabled( $key ),
            'result'    => is_array( $stored ) ? hws_sitemap_feeds_present( $stored ) : null,
        ];
    }
    return $rows;
}

/** LiteSpeed's own cache rule for feeds or sitemaps, in words. */
function hws_sitemap_feeds_litespeed_default( string $kind ): string {
    if ( ! defined( 'LSCWP_V' ) ) {
        return 'LiteSpeed Cache is not active';
    }
    if ( 'feed' === $kind && ! get_option( 'litespeed.conf.cache-feed' ) ) {
        return 'not cached';
    }
    $ttl = (int) get_option( 'feed' === $kind ? 'litespeed.conf.cache-ttl_feed' : 'litespeed.conf.cache-ttl_pub', 0 );
    if ( $ttl <= 0 ) {
        return 'not cached';
    }
    return $ttl % 86400 === 0 ? ( $ttl / 86400 ) . ( 86400 === $ttl ? ' day' : ' days' ) : ( $ttl % 3600 === 0 ? ( $ttl / 3600 ) . ' hours' : round( $ttl / 60 ) . ' minutes' );
}

function render_tab_sitemaps(): void {
    hws_sitemap_feeds_render( 'sitemap' );
}

function render_tab_rss_feeds(): void {
    hws_sitemap_feeds_render( 'feed' );
}

function hws_sitemap_feeds_render( string $kind ): void {
    CoreUi::render_assets();
    $data = [
        'nonce' => wp_create_nonce( HWS_AJAX_NONCE ),
        'kind'  => $kind,
        'rows'  => hws_sitemap_feeds_rows( $kind ),
    ];
    $settings          = FeedSitemapSettings::get();
    $litespeed_default = hws_sitemap_feeds_litespeed_default( $kind );
    ?>
    <div id="hws-sf" class="hpc-ui hws-sf">
        <style>
            #hws-sf{max-width:980px}
            #hws-sf .hws-sf-head{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:4px 0 18px}
            #hws-sf .hws-sf-head h2{margin:0 0 4px;font-size:22px}
            #hws-sf .hws-sf-head p{margin:0;color:#646970}
            #hws-sf .hws-sf-summary{color:#50575e;font-size:13px;margin-top:6px}
            #hws-sf .hws-sf-section{margin:0 0 28px}
            #hws-sf .hws-sf-section>h3{font-size:16px;margin:0 0 4px}
            #hws-sf .hws-sf-section>p{margin:0 0 10px;color:#646970}
            #hws-sf .hws-sf-group{margin:14px 0 6px;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#787c82}
            #hws-sf .hws-sf-row{background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:12px 14px;margin-bottom:8px}
            #hws-sf .hws-sf-top{display:flex;align-items:center;gap:10px}
            #hws-sf .hws-sf-icon{flex:0 0 22px;width:22px;height:22px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;background:#c3c4c7}
            #hws-sf .hws-sf-icon.healthy{background:#00a32a}#hws-sf .hws-sf-icon.failed{background:#d63638}#hws-sf .hws-sf-icon.off{background:#787c82}
            #hws-sf .hws-sf-label{font-weight:600;font-size:14px;flex:1;min-width:0}
            #hws-sf .hws-sf-actions{display:flex;align-items:center;gap:12px}
            #hws-sf .hws-sf-url{margin:4px 0 0 32px;font-size:12px;word-break:break-all}
            #hws-sf .hws-sf-url a{color:#2271b1;text-decoration:none}
            #hws-sf .hws-sf-meta{margin:6px 0 0 32px;color:#50575e;font-size:12px;line-height:1.6}
            #hws-sf .hws-sf-meta b{color:#1d2327;font-weight:600}
            #hws-sf .hws-sf-summary-line{margin:4px 0 0 32px;font-size:12px}
            #hws-sf .hws-sf-http{display:inline-flex;align-items:center;gap:6px;margin:6px 0 0 32px;padding:3px 10px 3px 4px;border-radius:999px;font-size:12px;font-weight:600}
            #hws-sf .hws-sf-http.ok{background:#edfaef;color:#00701a}#hws-sf .hws-sf-http.bad{background:#fcf0f1;color:#b32d2e}
            #hws-sf .hws-sf-http b{display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border-radius:50%;color:#fff;font-size:11px}
            #hws-sf .hws-sf-http.ok b{background:#00a32a}#hws-sf .hws-sf-http.bad b{background:#d63638}
            #hws-sf .hws-sf-http span{font-weight:400;color:#50575e}
            #hws-sf .hws-sf-summary-line.failed{color:#b32d2e}#hws-sf .hws-sf-summary-line.healthy{color:#00701a}
            #hws-sf details.hws-sf-more{margin:8px 0 0 32px}
            #hws-sf details.hws-sf-more>summary{cursor:pointer;color:#2271b1;font-size:12px}
            #hws-sf .hws-sf-panel{margin-top:8px;border-top:1px solid #f0f0f1;padding-top:8px}
            #hws-sf .hws-sf-checks{list-style:none;margin:0;padding:0;font-size:12px}
            #hws-sf .hws-sf-checks li{margin:2px 0}
            #hws-sf .hws-sf-checks .ok{color:#00a32a}#hws-sf .hws-sf-checks .bad{color:#d63638}#hws-sf .hws-sf-checks .warn{color:#996800}
            #hws-sf .hws-sf-kv{width:100%;border-collapse:collapse;font-size:12px;margin-top:6px}
            #hws-sf .hws-sf-kv th,#hws-sf .hws-sf-kv td{border-bottom:1px solid #f0f0f1;padding:5px 6px;text-align:left;vertical-align:top}
            #hws-sf .hws-sf-kv th{width:200px;font-weight:600;color:#1d2327;font-family:ui-monospace,Menlo,Consolas,monospace}
            #hws-sf .hws-sf-kv td{word-break:break-word;color:#3c434a}
            #hws-sf pre.hws-sf-body{max-height:260px;overflow:auto;background:#f6f7f7;border:1px solid #e0e0e0;border-radius:4px;padding:8px;font-size:11px;white-space:pre-wrap;word-break:break-all}
            #hws-sf .hws-sf-advanced{margin:8px 0 0 32px}
            #hws-sf .hws-sf-advanced select{max-width:100%;margin:6px 0}
            #hws-sf .hws-sf-tools{display:flex;flex-direction:column;gap:10px}
            #hws-sf .hws-sf-list{list-style:none;margin:0;padding:0;max-width:560px}
            #hws-sf .hws-sf-list li{margin:0;padding:7px 0;border-bottom:1px solid #f0f0f1}
            #hws-sf .hws-sf-list label{display:flex;align-items:center;justify-content:space-between;gap:16px}
            #hws-sf .hws-sf-list label.hws-sf-check{justify-content:flex-start;gap:8px}
            #hws-sf .hws-sf-list select{min-width:220px}
            #hws-sf .hws-sf-owner{background:#fcf9e8;border-left:4px solid #dba617;padding:8px 12px;margin:0 0 8px;max-width:560px}
            #hws-sf .hws-sf-hint{font-size:12px;color:#787c82;margin-top:6px}
            #hws-sf .hws-sf-tools .hpc-actions{margin:0}
            #hws-sf .hws-sf-notice{margin-top:6px;font-size:12px;color:#00701a}
            #hws-sf .hws-sf-notice.error{color:#b32d2e}
        </style>

        <div class="hws-sf-head">
            <div>
                <?php if ( 'feed' === $kind ) : ?>
                    <h2>RSS Feeds</h2>
                    <p>Every RSS feed this site serves, whether each one works, and what it contains. Turn a feed off to make its address answer "not found" and remove it from the page head. Feeds are on until turned off.</p>
                <?php else : ?>
                    <h2>Sitemaps</h2>
                    <p>Every XML sitemap search engines read to find the site's pages, whether each one works, and what it lists. Rank Math builds them when it is active.</p>
                <?php endif; ?>
                <div class="hws-sf-summary" data-role="summary"></div>
            </div>
            <button type="button" class="hpc-button" data-action="check-all">Check everything</button>
        </div>

        <section class="hws-sf-section">
            <div data-list="<?php echo esc_attr( $kind ); ?>"></div>
        </section>

        <section class="hws-sf-section hws-sf-settings" id="hws-sf-settings">
            <h3>Settings</h3>
            <?php if ( 'feed' === $kind ) : ?>
                <p>How many of the newest items each feed shows. Site default is WordPress's "Syndication feeds show the most recent" (<?php echo esc_html( (string) get_option( 'posts_per_rss' ) ); ?>).</p>
                <ul class="hws-sf-list">
                    <?php foreach ( SitemapFeedRegistry::feeds() as $key => $feed ) : ?>
                        <?php if ( ! $feed['available'] ) { continue; } ?>
                        <li><label><span><?php echo esc_html( $feed['label'] ); ?></span>
                            <select name="limit_<?php echo esc_attr( $key ); ?>" data-limit="<?php echo esc_attr( $key ); ?>">
                                <?php foreach ( FeedSitemapSettings::FEED_LIMITS as $value => $label ) : ?>
                                    <option value="<?php echo esc_attr( (string) $value ); ?>"<?php selected( FeedSitemapSettings::feed_limit( $key ), $value ); ?>><?php echo esc_html( 0 === $value ? $label . ' (' . get_option( 'posts_per_rss' ) . ')' : $label ); ?></option>
                                <?php endforeach; ?>
                            </select></label></li>
                    <?php endforeach; ?>
                </ul>
                <p class="hws-sf-hint">Unlimited lists every published item and can make a large feed slow to load.</p>
            <?php else : ?>
                <p>How many addresses each sitemap page lists before it continues on a second page<?php echo SitemapFeedRegistry::rank_math_module( 'sitemap' ) ? ' (Rank Math\'s "Links per sitemap")' : ''; ?>.</p>
                <ul class="hws-sf-list">
                    <li><label><span>Addresses per sitemap page</span>
                        <select name="size">
                            <?php foreach ( FeedSitemapSettings::SITEMAP_SIZES as $value => $label ) : ?>
                                <option value="<?php echo esc_attr( (string) $value ); ?>"<?php selected( $settings['sitemap_size'], $value ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select></label></li>
                </ul>
            <?php endif; ?>

            <h3 style="margin-top:22px">Caching</h3>
            <p><?php echo esc_html( 'feed' === $kind ? 'How long LiteSpeed keeps a saved copy of each feed before building it again.' : 'How long LiteSpeed keeps a saved copy of each sitemap before building it again.' ); ?></p>
            <?php $owner = 'feed' === $kind ? FeedSitemapSettings::feed_cache_owner() : null; ?>
            <?php if ( $owner ) : ?>
                <p class="hws-sf-owner">Feeds are never cached because <?php echo esc_html( $owner['label'] ); ?> is on. Turn it off there to choose a cache length here.<?php if ( '' !== $owner['url'] ) : ?> <a href="<?php echo esc_url( $owner['url'] ); ?>" target="_blank" rel="noopener">Open that setting</a><?php endif; ?></p>
            <?php endif; ?>
            <ul class="hws-sf-list">
                <li><label><span>Keep a cached copy for</span>
                    <select name="cache"<?php disabled( (bool) $owner ); ?>>
                        <?php foreach ( FeedSitemapSettings::CACHE_TIMES as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( (string) $value ); ?>"<?php selected( $settings[ $kind . '_cache' ], (string) $value ); ?>><?php echo esc_html( 'default' === $value ? $label . ' (' . $litespeed_default . ')' : $label ); ?></option>
                        <?php endforeach; ?>
                    </select></label></li>
                <li><label class="hws-sf-check"><input type="checkbox" name="clear" value="1"<?php checked( $settings[ $kind . '_clear' ] ); ?>> Clear the cached copy when a post is published, updated or unpublished</label></li>
            </ul>
            <?php echo SettingsAutosave::notice( 'hws-sf-settings-notice' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </section>

        <section class="hws-sf-section">
            <h3>Tools</h3>
            <div class="hws-sf-tools">
                <div class="hpc-actions">
                    <button type="button" class="hpc-button secondary" data-action="purge"><?php echo esc_html( 'feed' === $kind ? 'Clear cached feeds' : 'Clear cached sitemaps' ); ?></button>
                    <button type="button" class="hpc-button secondary" data-action="flush">Refresh web addresses</button>
                    <?php if ( 'feed' === $kind ) : ?>
                        <?php echo CoreUi::external_link( admin_url( 'admin.php?page=rank-math-options-general#setting-panel-others' ), 'Rank Math feed text' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php else : ?>
                        <?php echo CoreUi::external_link( admin_url( 'admin.php?page=rank-math-options-sitemap' ), 'Rank Math sitemap settings' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endif; ?>
                </div>
                <div class="hws-sf-notice" data-role="tools-notice" role="status" aria-live="polite"></div>
            </div>
        </section>
    </div>

    <script>
    (function(){
        var root = document.getElementById('hws-sf');
        if (!root || root.dataset.ready) return;
        root.dataset.ready = '1';
        var data = <?php echo wp_json_encode( $data ); ?>;
        var rows = {};

        function esc(value){ var d = document.createElement('div'); d.textContent = value == null ? '' : String(value); return d.innerHTML; }
        function num(n){ return Number(n || 0).toLocaleString(); }
        function post(action, fields){
            var body = new URLSearchParams(); body.set('action', action); body.set('nonce', data.nonce);
            Object.keys(fields || {}).forEach(function(k){ body.set(k, fields[k]); });
            return fetch(window.ajaxurl, { method:'POST', credentials:'same-origin', body: body })
                .then(function(r){ return r.json().catch(function(){ throw new Error('The server answered HTTP ' + r.status + '.'); }); })
                .then(function(p){ if (!p || !p.success) throw new Error(p && p.data && p.data.message ? p.data.message : 'The request failed.'); return p.data; });
        }

        function kvTable(fields){
            var html = '';
            Object.keys(fields || {}).forEach(function(name){
                if (/#count$/.test(name)) return;
                var value = fields[name];
                if (value && typeof value === 'object') {
                    value = Object.keys(value).map(function(k){ var v = value[k]; return k + ': ' + (v && typeof v === 'object' ? JSON.stringify(v) : v); }).join('\n');
                }
                var count = fields[name + '#count'] ? ' <span style="color:#787c82">×' + fields[name + '#count'] + '</span>' : '';
                html += '<tr><th>' + esc(name) + count + '</th><td style="white-space:pre-wrap">' + esc(value) + '</td></tr>';
            });
            return html ? '<table class="hws-sf-kv">' + html + '</table>' : '<p>No fields.</p>';
        }

        function meta(row, r){
            var parts = [];
            var isFeed = row.kind === 'feed';
            if (r.feed_items !== null && r.feed_items !== undefined) {
                if (isFeed) parts.push('<b>' + num(r.feed_items) + '</b> items in the feed');
                else parts.push('<b>' + num(r.feed_items) + '</b> ' + (r.sitemap_type === 'index' ? 'sitemaps listed' : 'addresses'));
            }
            if (r.total !== null && r.total !== undefined) parts.push('<b>' + num(r.total) + '</b> published');
            var newest = r.display && (r.display.latest_item || r.display.latest_saved);
            if (newest) parts.push('last published <b>' + esc(newest) + '</b>');
            if (r.extra) Object.keys(r.extra).forEach(function(k){ parts.push(num(r.extra[k]) + ' ' + k); });
            if (r.display && r.display.checked_at) parts.push('checked ' + esc(r.display.checked_at));
            return parts.join(' · ');
        }

        // The server's answer in plain view: green check for 200, red X for anything else.
        function httpBadge(r){
            var res = r.response || {};
            if (!res.code && !res.error) return '';
            var ok = res.code === 200;
            var text = res.error ? 'No answer: ' + res.error : res.code + ' ' + (res.message || '');
            return '<div class="hws-sf-http ' + (ok ? 'ok' : 'bad') + '"><b>' + (ok ? '✓' : '✗') + '</b>Server response: ' + esc(text) + (res.time_ms ? ' <span>in ' + num(res.time_ms) + ' ms</span>' : '') + '</div>';
        }

        function responseHtml(r){
            var res = r.response || {};
            if (res.error) return '<p class="bad">' + esc(res.error) + '</p>';
            var summary = { 'Status': (res.code || '') + ' ' + (res.message || ''), 'Time': num(res.time_ms) + ' ms', 'Size': num(res.bytes) + ' bytes', 'Content type': res.content_type || 'none', 'Cache': res.cache || '' };
            return kvTable(summary) + '<details style="margin-top:6px"><summary>All headers</summary>' + kvTable(res.headers || {}) + '</details>'
                + '<details style="margin-top:6px"><summary>Start of the response</summary><pre class="hws-sf-body">' + esc(res.body || '') + '</pre></details>';
        }

        function checksHtml(r){
            return '<ul class="hws-sf-checks">' + (r.checks || []).map(function(c){
                var cls = c.ok ? 'ok' : (c.warning ? 'warn' : 'bad'), mark = c.ok ? '✓' : (c.warning ? '!' : '✗');
                return '<li><span class="' + cls + '">' + mark + '</span> ' + esc(c.label) + (c.detail ? ' <span style="color:#787c82">(' + esc(c.detail) + ')</span>' : '') + '</li>';
            }).join('') + '</ul>';
        }

        function advancedHtml(row, r){
            var options = '<option value="channel">' + (row.kind === 'feed' ? 'General fields (channel)' : 'Fields found across entries') + '</option>';
            (r.items || []).forEach(function(item, i){
                var title = item.title || item.loc || ('Entry ' + (i + 1));
                options += '<option value="' + i + '">' + (row.kind === 'feed' ? 'Item ' : 'Entry ') + (i + 1) + ': ' + esc(String(title).slice(0, 80)) + '</option>';
            });
            var coverage = Object.keys(r.coverage || {}).map(function(name){ return '<tr><th>' + esc(name) + '</th><td>' + num(r.coverage[name]) + ' of ' + num(r.feed_items) + ' items</td></tr>'; }).join('');
            return '<label style="font-size:12px">Show <select data-role="field-view">' + options + '</select></label>'
                + '<div data-role="field-table"></div>'
                + (coverage ? '<details style="margin-top:6px"><summary style="font-size:12px;cursor:pointer;color:#2271b1">Which fields each item has</summary><table class="hws-sf-kv">' + coverage + '</table></details>' : '');
        }

        function showFields(el, row, r, value){
            var table = el.querySelector('[data-role="field-table"]');
            if (!table) return;
            if (value === 'channel') {
                if (row.kind === 'feed') { table.innerHTML = kvTable(r.channel || {}); return; }
                var seen = {}; (r.items || []).forEach(function(it){ Object.keys(it).forEach(function(k){ if (!/#count$/.test(k)) seen[k] = (seen[k] || 0) + 1; }); });
                table.innerHTML = kvTable(Object.keys(seen).reduce(function(o, k){ o[k] = seen[k] + ' of the first ' + (r.items || []).length + ' entries'; return o; }, {}));
                return;
            }
            table.innerHTML = kvTable((r.items || [])[Number(value)] || {});
        }

        function render(row){
            var el = rows[row.kind + ':' + row.key];
            var r = row.result;
            var state = !row.available ? 'unavailable' : (r ? r.status : 'unchecked');
            if (row.kind === 'feed' && !row.on) state = 'off';
            var icon = { healthy:'✓', failed:'✗', off:'–', unavailable:'–', unchecked:'?' }[state] || '?';
            var title = { healthy:'Working', failed:'Not working', off:'Turned off', unavailable:'Not available', unchecked:'Not checked yet' }[state];
            var toggle = row.toggle && row.available ? '<label class="hpc-toggle"><input type="checkbox" data-role="switch"' + (row.on ? ' checked' : '') + '><span class="hpc-toggle-ui" aria-hidden="true"></span><span class="hpc-toggle-label">Active</span></label>' : '';
            var validate = row.available && row.url ? '<button type="button" class="hpc-button secondary" data-role="validate">Validate</button>' : '';
            var settings = row.settings ? '<a href="' + esc(row.settings) + '" target="_blank" rel="noopener" style="font-size:12px">' + esc(row.settings_label || 'Settings') + '</a>' : '';
            var html = '<div class="hws-sf-top"><span class="hws-sf-icon ' + state + '" title="' + esc(title) + '">' + icon + '</span>'
                + '<span class="hws-sf-label">' + esc(row.label) + '</span>'
                + '<span class="hws-sf-actions">' + settings + toggle + validate + '</span></div>';
            if (row.url) html += '<div class="hws-sf-url"><a href="' + esc(row.url) + '" target="_blank" rel="noopener">' + esc(row.url) + '</a></div>';
            if (row.note) html += '<div class="hws-sf-meta">' + esc(row.note) + '</div>';
            if (r && row.available) {
                html += httpBadge(r);
                if (r.summary) html += '<div class="hws-sf-summary-line ' + esc(r.status) + '">' + esc(r.summary) + '</div>';
                var m = meta(row, r); if (m) html += '<div class="hws-sf-meta">' + m + '</div>';
                html += '<details class="hws-sf-more"><summary>Checks</summary><div class="hws-sf-panel">' + checksHtml(r) + '</div></details>';
                html += '<details class="hws-sf-more"><summary>Server response</summary><div class="hws-sf-panel">' + responseHtml(r) + '</div></details>';
                if ((r.items && r.items.length) || (r.channel && Object.keys(r.channel).length)) {
                    html += '<div class="hws-sf-advanced"><label class="hpc-toggle"><input type="checkbox" data-role="advanced"><span class="hpc-toggle-ui" aria-hidden="true"></span><span class="hpc-toggle-label">Advanced: fields</span></label><div data-role="advanced-panel" hidden></div></div>';
                }
            }
            html += '<div class="hws-sf-notice" data-role="notice" role="status" aria-live="polite"></div>';
            el.innerHTML = html;
            el.dataset.state = state;
            summary();
        }

        function check(row){
            var el = rows[row.kind + ':' + row.key];
            var button = el.querySelector('[data-role="validate"]');
            if (button) { button.disabled = true; button.textContent = 'Checking…'; }
            return post('hws_sitemap_check', { kind: row.kind, key: row.key })
                .then(function(d){ row.result = d.result; render(row); })
                .catch(function(e){ render(row); var n = el.querySelector('[data-role="notice"]'); n.className = 'hws-sf-notice error'; n.textContent = e.message; });
        }

        function summary(){
            var all = Object.keys(rows).map(function(k){ return rows[k].dataset.state; });
            var count = function(s){ return all.filter(function(x){ return x === s; }).length; };
            var parts = [count('healthy') + ' working', count('failed') + ' not working'];
            if (count('off')) parts.push(count('off') + ' turned off');
            if (count('unchecked')) parts.push(count('unchecked') + ' not checked yet');
            root.querySelector('[data-role="summary"]').textContent = parts.join(' · ');
        }

        (function(){
            var list = root.querySelector('[data-list="' + data.kind + '"]');
            var group = null;
            data.rows.forEach(function(row){
                if (row.group !== group) { group = row.group; var g = document.createElement('div'); g.className = 'hws-sf-group'; g.textContent = group; list.appendChild(g); }
                var el = document.createElement('div'); el.className = 'hws-sf-row'; el._row = row;
                rows[data.kind + ':' + row.key] = el; list.appendChild(el); render(row);
            });
        })();

        root.addEventListener('click', function(e){
            var el = e.target.closest('.hws-sf-row');
            if (e.target.matches('[data-role="validate"]') && el) { check(el._row); return; }
            var action = e.target.getAttribute('data-action');
            if (action === 'check-all') {
                var button = e.target; button.disabled = true;
                var queue = Object.keys(rows).map(function(k){ return rows[k]._row; }).filter(function(r){ return r.available && r.url; });
                var done = 0;
                (function next(){
                    if (!queue.length) { button.disabled = false; button.textContent = 'Check everything'; return; }
                    button.textContent = 'Checking ' + (++done) + ' of ' + (done + queue.length - 1) + '…';
                    check(queue.shift()).then(next);
                })();
                return;
            }
            if (action === 'purge' || action === 'flush') {
                var notice = root.querySelector('[data-role="tools-notice"]');
                notice.className = 'hws-sf-notice'; notice.textContent = 'Working…';
                post(action === 'purge' ? 'hws_sitemap_purge_litespeed' : 'hws_sitemap_flush_permalinks', { kind: data.kind })
                    .then(function(d){ notice.textContent = d.message; })
                    .catch(function(err){ notice.className = 'hws-sf-notice error'; notice.textContent = err.message; });
            }
        });

        var settingsBox = document.getElementById('hws-sf-settings');
        if (settingsBox && window.HWSSettingsAutosave) {
            HWSSettingsAutosave.bind(settingsBox, {
                notice: '#hws-sf-settings-notice',
                save: function(){
                    var limits = {};
                    settingsBox.querySelectorAll('select[data-limit]').forEach(function(sel){ limits[sel.getAttribute('data-limit')] = sel.value; });
                    var size = settingsBox.querySelector('select[name="size"]'), cache = settingsBox.querySelector('select[name="cache"]'), clear = settingsBox.querySelector('input[name="clear"]');
                    return HWSSettingsAutosave.post('hws_sitemap_settings_save', data.nonce, { kind: data.kind, limits: JSON.stringify(limits), size: size ? size.value : '', cache: cache.value, clear: clear.checked ? '1' : '' }).then(function(d){ return { message: d.message }; });
                }
            });
            // Its own named selects would otherwise trip the row handler below.
        }

        root.addEventListener('change', function(e){
            if (settingsBox && settingsBox.contains(e.target)) return;
            var el = e.target.closest('.hws-sf-row');
            if (!el) return;
            var row = el._row;
            if (e.target.matches('[data-role="switch"]')) {
                var on = e.target.checked;
                var n = el.querySelector('[data-role="notice"]');
                n.className = 'hws-sf-notice'; n.textContent = 'Saving…';
                post('hws_sitemap_feed_toggle', { key: row.key, on: on ? '1' : '0' })
                    .then(function(d){ row.on = on; return check(row).then(function(){ var m = el.querySelector('[data-role="notice"]'); m.textContent = d.message; }); })
                    .catch(function(err){ e.target.checked = !on; n.className = 'hws-sf-notice error'; n.textContent = err.message; });
                return;
            }
            if (e.target.matches('[data-role="advanced"]')) {
                var panel = el.querySelector('[data-role="advanced-panel"]');
                panel.hidden = !e.target.checked;
                if (e.target.checked && !panel.innerHTML) { panel.innerHTML = advancedHtml(row, row.result); showFields(panel, row, row.result, 'channel'); }
                return;
            }
            if (e.target.matches('[data-role="field-view"]')) {
                showFields(el.querySelector('[data-role="advanced-panel"]'), row, row.result, e.target.value);
            }
        });
    })();
    </script>
    <?php
}
