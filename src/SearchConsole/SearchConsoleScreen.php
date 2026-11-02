<?php

namespace HWS\BaseTools\SearchConsole;

use HWS\BaseTools\AdminDashboard\SeoScreenUi;

defined( 'ABSPATH' ) || exit;

/**
 * The Search Console tab: is the site registered, who owns it, every sitemap
 * Google knows with its health, submit, check a page, request reindexing and
 * open Search Console. All data comes from the Search Console engine's AJAX
 * actions; the Google key is write-only here.
 */
final class SearchConsoleScreen {
    public static function render(): void {
        SeoScreenUi::assets();
        ?>
        <div class="hws-ss" id="hws-sc" data-nonce="<?php echo esc_attr( wp_create_nonce( 'hws_base_tools_search_console' ) ); ?>" data-home="<?php echo esc_attr( home_url( '/' ) ); ?>">
            <div class="hws-ss-head">
                <h2>Search Console</h2>
                <p>Whether Google knows this site, who owns it in Search Console, and how Google reads its sitemaps.</p>
            </div>

            <section class="hws-ss-section">
                <h3>Registration</h3>
                <div data-role="status"><p class="hws-ss-empty">Asking Google…</p></div>
            </section>

            <section class="hws-ss-section">
                <h3><span>Sitemaps in Google</span><button type="button" class="hpc-button" data-action="submit-all">Submit all recommended sitemaps</button></h3>
                <p>Every sitemap Google has for this site and what it found when it last read it.</p>
                <div data-role="sitemaps"></div>
                <div class="hws-ss-notice" data-role="submit-notice" role="status" aria-live="polite"></div>
            </section>

            <section class="hws-ss-section">
                <h3>Check a page</h3>
                <p>See whether Google has a page in its index, or ask Google to read it again.</p>
                <div class="hws-ss-form">
                    <input type="url" data-role="page" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>">
                    <button type="button" class="hpc-button" data-action="inspect">Check in Google</button>
                    <button type="button" class="hpc-button secondary" data-action="reindex">Request reindexing</button>
                </div>
                <div class="hws-ss-result" data-role="page-result" role="status" aria-live="polite"></div>
            </section>

            <section class="hws-ss-section" data-role="key" hidden>
                <h3>Google key</h3>
                <p>Paste the shared Hexa Google service-account key. It is stored encrypted and never shown again.</p>
                <textarea data-role="key-json" autocomplete="off" spellcheck="false"></textarea>
                <p><button type="button" class="hpc-button" data-action="credential">Save key</button></p>
                <div class="hws-ss-notice" data-role="key-notice" role="status" aria-live="polite"></div>
            </section>
        </div>
        <script>
        (function(){
            var root = document.getElementById('hws-sc'); if (!root || root.dataset.ready) return; root.dataset.ready = '1';
            var ss = window.hwsSs, nonce = root.dataset.nonce, q = function(r){ return root.querySelector('[data-role="' + r + '"]'); };
            function act(name, fields){ return ss.post('hws_base_tools_search_console_' + name, nonce, fields); }
            function note(el, text, bad){ el.textContent = text || ''; el.className = 'hws-ss-notice' + (bad ? ' bad' : ''); }
            function health(s){
                if (s.isPending) return ['warn', 'Waiting for Google'];
                if (+s.errors > 0) return ['bad', s.errors + ' errors'];
                if (+s.warnings > 0) return ['warn', s.warnings + ' warnings'];
                return ['ok', 'Healthy'];
            }
            function drawStatus(d){
                q('key').hidden = !!d.configured;
                if (!d.configured) { q('status').innerHTML = '<div class="hws-ss-row attention"><div class="hws-ss-top"><span class="hws-ss-dot bad"></span><span class="hws-ss-label">No Google key on this site yet</span></div><div class="hws-ss-sub">Add the shared key below to connect.</div></div>'; q('sitemaps').innerHTML = ''; return; }
                var reg = d.registered, dot = reg === true ? 'ok' : (reg === false ? 'bad' : 'warn');
                var title = reg === true ? 'Registered in Search Console' : (reg === false ? 'Not registered in Search Console' : 'Google did not say whether it is registered');
                var h = '<div class="hws-ss-row' + (reg === true ? '' : ' attention') + '"><div class="hws-ss-top"><span class="hws-ss-dot ' + dot + '"></span><span class="hws-ss-label">' + title + '<small>' + ss.esc(d.property || '') + '</small></span>';
                if (d.permission) h += '<span class="hws-ss-pills"><span class="hws-ss-pill ' + (d.permission === 'siteOwner' ? 'ok' : '') + '">' + ss.esc(d.permission === 'siteOwner' ? 'Full owner' : d.permission) + '</span></span>';
                if (d.console_url) h += '<a class="hpc-button secondary hpc-external" href="' + ss.esc(d.console_url) + '" target="_blank" rel="noopener noreferrer">Open Search Console</a>';
                h += '</div>';
                var owners = d.owners || [];
                h += '<div class="hws-ss-sub"><b>Owners</b></div><ul class="hws-ss-list">' + (owners.length ? owners.map(function(o){ return '<li>' + ss.esc(typeof o === 'string' ? o : (o.email || o.owner || JSON.stringify(o))) + '</li>'; }).join('') : '<li>None listed</li>') + '</ul>';
                h += '</div>';
                q('status').innerHTML = h;
                var maps = d.sitemaps || [];
                q('sitemaps').innerHTML = maps.length ? maps.map(function(s){ var x = health(s); return '<div class="hws-ss-row"><div class="hws-ss-top"><span class="hws-ss-dot ' + x[0] + '"></span><span class="hws-ss-label"><a href="' + ss.esc(s.path) + '" target="_blank" rel="noopener">' + ss.esc(s.path) + '</a></span><span class="hws-ss-pills"><span class="hws-ss-pill ' + x[0] + '">' + ss.esc(x[1]) + '</span></span></div><div class="hws-ss-sub">Last read ' + ss.esc(ss.date(s.lastDownloaded)) + ' · last submitted ' + ss.esc(ss.date(s.lastSubmitted)) + '</div></div>'; }).join('') : '<p class="hws-ss-empty">Google has no sitemaps for this site yet. Submit them above.</p>';
            }
            function load(){ return act('status', {}).then(drawStatus).catch(function(e){ q('status').innerHTML = '<p class="hws-ss-empty">' + ss.esc(e.message) + '</p>'; }); }
            function pageUrl(){ return (q('page').value || '').trim() || root.dataset.home; }
            function drawPage(r, kind){
                var ok = r.ok !== false, d = r.data || {}, lines = [];
                if (kind === 'inspect' && ok) {
                    var idx = (((d.inspectionResult || {}).indexStatusResult) || {});
                    lines.push('<b>' + ss.esc(idx.coverageState || 'No answer from Google') + '</b>');
                    if (idx.lastCrawlTime) lines.push('Last read by Google ' + ss.esc(ss.date(idx.lastCrawlTime)));
                    if (idx.googleCanonical) lines.push('Google treats it as ' + ss.esc(idx.googleCanonical));
                } else {
                    lines.push('<b>' + ss.esc(r.notice || r.message || (ok ? 'Sent to Google.' : 'Google refused it.')) + '</b>');
                    if (r.reason) lines.push(ss.esc(r.reason));
                }
                q('page-result').innerHTML = '<div class="hws-ss-row' + (ok ? '' : ' attention') + '"><div class="hws-ss-top"><span class="hws-ss-dot ' + (ok ? 'ok' : 'bad') + '"></span><span class="hws-ss-label">' + lines.join('<br>') + '</span></div></div>';
            }
            root.addEventListener('click', function(e){
                var b = e.target.closest('button[data-action]'); if (!b) return;
                var a = b.dataset.action;
                if (a === 'submit-all') { ss.busy(b, true, 'Submitting…'); act('submit_all', {}).then(function(r){ var res = r.results || [], bad = res.filter(function(x){ return !x.ok; }).length; note(q('submit-notice'), (res.length - bad) + ' of ' + res.length + ' sent to Google' + (bad ? ', ' + bad + ' refused.' : '.'), bad > 0); ss.busy(b, false); return load(); }).catch(function(x){ ss.busy(b, false); note(q('submit-notice'), x.message, true); }); }
                if (a === 'inspect' || a === 'reindex') { ss.busy(b, true, a === 'inspect' ? 'Checking…' : 'Sending…'); act(a, {url: pageUrl()}).then(function(r){ ss.busy(b, false); drawPage(r, a); }).catch(function(x){ ss.busy(b, false); drawPage({ok:false, message:x.message}, a); }); }
                if (a === 'credential') { ss.busy(b, true, 'Saving…'); act('credential', {json: q('key-json').value}).then(function(r){ q('key-json').value = ''; ss.busy(b, false); note(q('key-notice'), r.ok === false ? r.message : 'Key saved.', r.ok === false); return load(); }).catch(function(x){ ss.busy(b, false); note(q('key-notice'), x.message, true); }); }
            });
            load();
        })();
        </script>
        <?php
    }
}
