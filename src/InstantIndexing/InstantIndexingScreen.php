<?php

namespace HWS\BaseTools\InstantIndexing;

use HWS\BaseTools\AdminDashboard\SeoScreenUi;

defined( 'ABSPATH' ) || exit;

/**
 * The Instant Indexing tab: whether Rank Math tells search engines about new
 * pages straight away (IndexNow), whether its key file works, what it sends
 * on its own, the latest answers, and one test send. Data comes from the
 * Instant Indexing engine's AJAX actions; nothing here changes a setting.
 */
final class InstantIndexingScreen {
    public static function render(): void {
        SeoScreenUi::assets();
        ?>
        <div class="hws-ss" id="hws-ii" data-nonce="<?php echo esc_attr( wp_create_nonce( 'hws_base_tools_instant_indexing' ) ); ?>" data-home="<?php echo esc_attr( home_url( '/' ) ); ?>">
            <div class="hws-ss-head">
                <h2>Instant Indexing</h2>
                <p>Rank Math tells Bing, Yandex and other search engines about new and changed pages the moment they are published. This shows whether that works.</p>
            </div>
            <section class="hws-ss-section">
                <h3><span>Status</span><?php echo \Hexa\PluginCore\WpAdminComponents\CoreUi::external_link( admin_url( 'admin.php?page=rank-math-options-instant-indexing' ), 'Rank Math settings' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h3>
                <div data-role="status"><p class="hws-ss-empty">Checking…</p></div>
            </section>
            <section class="hws-ss-section">
                <h3>Send a test page</h3>
                <p>Sends one published page now and shows the answer.</p>
                <div class="hws-ss-form">
                    <input type="url" data-role="page" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>">
                    <button type="button" class="hpc-button" data-action="test">Send</button>
                </div>
                <div class="hws-ss-result" data-role="test-result" role="status" aria-live="polite"></div>
            </section>
            <section class="hws-ss-section">
                <h3>Latest sends</h3>
                <div data-role="history"></div>
            </section>
        </div>
        <script>
        (function(){
            var root = document.getElementById('hws-ii'); if (!root || root.dataset.ready) return; root.dataset.ready = '1';
            var ss = window.hwsSs, nonce = root.dataset.nonce, q = function(r){ return root.querySelector('[data-role="' + r + '"]'); };
            function item(ok, title, sub){ return '<div class="hws-ss-row' + (ok ? '' : ' attention') + '"><div class="hws-ss-top"><span class="hws-ss-dot ' + (ok ? 'ok' : 'bad') + '"></span><span class="hws-ss-label">' + ss.esc(title) + (sub ? '<small>' + sub + '</small>' : '') + '</span></div></div>'; }
            function draw(d){
                var types = d.automatic_post_types || [], h = '';
                h += item(!!d.module_on, d.module_on ? 'Instant Indexing is on in Rank Math' : 'Instant Indexing is off in Rank Math');
                h += item(!!(d.key_present && d.key_at_root && d.key_matches && +d.key_http_code === 200), d.key_matches ? 'Key file found and matches' : 'Key file is missing or does not match', d.key_url ? ss.link(d.key_url) : '');
                h += item(types.length > 0, types.length ? 'Sends automatically: ' + types.join(', ') : 'Nothing is sent automatically');
                (d.google_instant_indexing_plugins || []).forEach(function(p){ if (p.installed) h += item(!!p.active, 'Google indexing plugin "' + p.name + '"' + (p.active ? ' is active' : ' is installed but off'), p.version ? 'Version ' + ss.esc(p.version) : ''); });
                (d.warnings || []).forEach(function(w){ h += '<div class="hws-ss-sub warn">' + ss.esc(w) + '</div>'; });
                q('status').innerHTML = h;
                var rows = d.last_submissions || [];
                q('history').innerHTML = rows.length ? '<table class="hws-ss-table"><thead><tr><th>When</th><th>Page</th><th>Answer</th></tr></thead><tbody>' + rows.map(function(r){ return '<tr><td>' + ss.esc(ss.date(r.time)) + (r.manual ? ' (manual)' : '') + '</td><td class="url">' + ss.link(r.url) + '</td><td><span class="hws-ss-pill ' + (r.ok ? 'ok' : 'bad') + '">' + ss.esc(r.code) + '</span> ' + ss.esc(r.meaning || '') + '</td></tr>'; }).join('') + '</tbody></table>' : '<p class="hws-ss-empty">Nothing sent yet.</p>';
            }
            function load(){ return ss.post('hws_base_tools_instant_indexing_status', nonce, {}).then(draw).catch(function(e){ q('status').innerHTML = '<p class="hws-ss-empty">' + ss.esc(e.message) + '</p>'; }); }
            root.addEventListener('click', function(e){
                var b = e.target.closest('button[data-action=test]'); if (!b) return;
                ss.busy(b, true, 'Sending…');
                ss.post('hws_base_tools_instant_indexing_test', nonce, {url: (q('page').value || '').trim() || root.dataset.home}).then(function(r){ ss.busy(b, false); q('test-result').innerHTML = item(r.ok !== false, (r.http_code ? r.http_code + ': ' : '') + (r.meaning || r.message || 'Sent.')); return load(); }).catch(function(x){ ss.busy(b, false); q('test-result').innerHTML = item(false, x.message); });
            });
            load();
        })();
        </script>
        <?php
    }
}
