<?php

namespace HWS\BaseTools\SitemapTools;

use HWS\BaseTools\AdminDashboard\SeoScreenUi;

defined( 'ABSPATH' ) || exit;

/**
 * "Recommended sitemaps" on the Sitemaps tab: every sitemap Rank Math can
 * make, what is recommended and by which plugin, Rank Math's current setting,
 * a change button only where they differ, and the "more than X" conditions.
 * Reads and writes only through Hexa WP Core's sitemap engine; nothing
 * changes Rank Math until the button is pressed.
 */
final class RecommendationsPanel {
    public static function render(): void {
        SeoScreenUi::assets();
        $nonce = wp_create_nonce( 'hexa_sitemaps' );
        ?>
        <section class="hws-ss hws-ss-section" id="hws-sr" data-nonce="<?php echo esc_attr( $nonce ); ?>">
            <h3><span>Recommended sitemaps</span><span class="hws-ss-pills" data-role="totals"></span></h3>
            <p>What this site should have in its sitemaps, and which plugin recommends each one. Nothing changes in Rank Math until you press a button.</p>
            <div data-role="list"><p class="hws-ss-empty">Loading…</p></div>
            <div data-role="external" hidden>
                <p class="hws-ss-readonly"><b>Read only.</b> These sitemaps belong to other plugins. Their state is shown here; change them in that plugin's own Sitemaps settings.</p>
                <div data-role="external-list"></div>
            </div>
            <details class="hws-ss-hidden" data-role="hidden-wrap" hidden>
                <summary><span data-role="hidden-title">Hidden</span></summary>
                <div class="hws-ss-hidden-head">
                    <p>Sitemaps for page-builder templates, theme parts and shop internals. These almost never belong in Google, so they are kept out of the list above.</p>
                    <button type="button" class="hpc-button" data-junk-off hidden>Turn all off in Rank Math</button>
                </div>
                <div data-role="hidden-list"></div>
            </details>
            <div class="hws-ss-notice" data-role="notice" role="status" aria-live="polite"></div>
        </section>
        <script>
        (function(){
            var root = document.getElementById('hws-sr'); if (!root || root.dataset.ready) return; root.dataset.ready = '1';
            var ss = window.hwsSs, nonce = root.dataset.nonce, list = root.querySelector('[data-role=list]'), notice = root.querySelector('[data-role=notice]');
            var wrap = root.querySelector('[data-role=hidden-wrap]'), hiddenList = root.querySelector('[data-role=hidden-list]'), junkOff = root.querySelector('[data-junk-off]');
            function differsFrom(r){ return r.recommended !== r.current && (r.setting !== '' || r.kind === 'module'); }
            function say(t, bad){ notice.textContent = t || ''; notice.className = 'hws-ss-notice' + (bad ? ' bad' : ''); }
            function state(r){
                if (r.collision_with) return 'warn';
                if (r.recommended === r.current) return r.current ? 'ok' : '';
                return r.recommended ? 'bad' : 'warn';
            }
            function external(r){ return r.owner && r.owner !== 'hws-base-tools'; }
            function row(r, ro){
                var s = state(r), writable = !ro && (r.setting !== '' || r.kind === 'module'), differs = r.recommended !== r.current && writable && !(r.collision_with && !r.current);
                var h = '<div class="hws-ss-row' + (differs ? ' attention' : '') + '" data-id="' + ss.esc(r.id) + '">';
                h += '<div class="hws-ss-top"><span class="hws-ss-dot ' + s + '"></span>';
                h += '<span class="hws-ss-label">' + ss.esc(r.label) + '<small>' + (r.url ? ss.link(r.url) : '') + '</small></span>';
                h += '<span class="hws-ss-pills"><span class="hws-ss-pill ' + (r.recommended ? 'ok' : '') + '">' + (r.recommended ? 'Recommended' : 'Not recommended') + '</span>';
                h += '<span class="hws-ss-pill ' + (r.current ? 'ok' : '') + '">' + (r.current ? 'On in Rank Math' : 'Off in Rank Math') + '</span></span>';
                if (!ro && r.junk) h += '<button type="button" class="hpc-button secondary" data-promote="1">Move to main list</button>';
                else if (!ro && r.promoted) h += '<button type="button" class="hpc-button secondary" data-promote="0">Move to hidden</button>';
                if (differs) h += '<button type="button" class="hpc-button" data-change="' + (r.recommended ? 1 : 0) + '">' + (r.recommended ? 'Turn on in Rank Math' : 'Turn off in Rank Math') + '</button>';
                h += '</div>';
                h += '<div class="hws-ss-sub">Recommended by ' + ss.esc((r.recommended_by || []).join(', ') || 'no plugin') + (r.why ? ': ' + ss.esc(r.why) : '') + '</div>';
                if (!r.junk) (r.warnings || []).concat(r.policy_warnings || []).filter(function(w, i, a){ return a.indexOf(w) === i; }).forEach(function(w){ h += '<div class="hws-ss-sub warn">' + ss.esc(w) + '</div>'; });
                if (!ro && !r.junk && r.condition && (r.kind === 'taxonomy' || r.kind === 'author')) {
                    var c = r.condition, n = r.counts || {};
                    h += '<div class="hws-ss-cond">';
                    if (r.kind === 'taxonomy') h += '<label>Recommend only when the site has more than <input type="number" min="0" data-f="more_than" value="' + ss.esc(c.more_than) + '"> ' + ss.esc(r.label.toLowerCase()) + '</label>';
                    h += '<label>Leave out ' + (r.kind === 'author' ? 'authors' : ss.esc(r.label.toLowerCase())) + ' with fewer than <input type="number" min="1" data-f="min_posts" value="' + ss.esc(c.min_posts) + '"> posts</label>';
                    h += '<div class="hws-ss-cond-foot"><span><b>' + ss.esc(n.would_show) + ' of ' + ss.esc(n.total) + '</b> would show</span>';
                    h += '<label><input type="checkbox" data-f="apply"' + (c.apply ? ' checked' : '') + '> Apply to the live sitemap</label>';
                    h += '<button type="button" class="hpc-button secondary" data-save>Save</button></div></div>';
                }
                return h + '</div>';
            }
            function draw(d){
                var all = d.rows || [], ext = all.filter(function(r){ return !r.junk && external(r); }), rows = all.filter(function(r){ return !r.junk && !external(r); }), junk = all.filter(function(r){ return r.junk; }), diff = rows.filter(differsFrom).length, junkOn = junk.filter(function(r){ return r.current; }).length;
                root.querySelector('[data-role=totals]').innerHTML = '<span class="hws-ss-pill">' + (rows.length + ext.length) + ' sitemaps</span>' + (diff ? '<span class="hws-ss-pill warn">' + diff + ' differ from the recommendation</span>' : '<span class="hws-ss-pill ok">All match</span>');
                rows.sort(function(a, b){ return (b.recommended !== b.current) - (a.recommended !== a.current); });
                list.innerHTML = rows.length ? rows.map(function(r){ return row(r); }).join('') : '<p class="hws-ss-empty">Rank Math is not active, so there are no sitemaps.</p>';
                var groups = {};
                ext.forEach(function(r){ (groups[r.owner] = groups[r.owner] || {label: r.owner_label, url: r.owner_url, rows: []}).rows.push(r); });
                root.querySelector('[data-role=external]').hidden = !ext.length;
                root.querySelector('[data-role=external-list]').innerHTML = Object.keys(groups).map(function(k){
                    var g = groups[k], off = g.rows.filter(function(r){ return r.recommended !== r.current; }).length;
                    return '<div class="hws-ss-extgroup"><div class="hws-ss-exthead"><span class="hws-ss-label">Managed by ' + ss.esc(g.label) + '</span><span class="hws-ss-pills"><span class="hws-ss-pill">Read only</span>' + (off ? '<span class="hws-ss-pill warn">' + off + ' differ</span>' : '<span class="hws-ss-pill ok">All match</span>') + '</span>' + (g.url ? ss.link(g.url, 'Open its Sitemaps settings') : '') + '</div><div class="hws-ss-extbody">' + g.rows.map(function(r){ return row(r, true); }).join('') + '</div></div>';
                }).join('');
                junk.sort(function(a, b){ return b.current - a.current; });
                wrap.hidden = !junk.length;
                root.querySelector('[data-role=hidden-title]').innerHTML = 'Hidden: usually junk <span class="hws-ss-pill">' + junk.length + '</span>' + (junkOn ? ' <span class="hws-ss-pill bad">' + junkOn + ' on in Rank Math</span>' : ' <span class="hws-ss-pill ok">All off</span>');
                junkOff.hidden = !junkOn; junkOff.disabled = false; junkOff.textContent = 'Turn all ' + junkOn + ' off in Rank Math';
                if (junkOn) wrap.open = true;
                hiddenList.innerHTML = junk.map(function(r){ return row(r); }).join('');
            }
            function load(){ return ss.post('hexa_sitemaps_lookup', nonce, {}).then(draw).catch(function(e){ list.innerHTML = '<p class="hws-ss-empty">' + ss.esc(e.message) + '</p>'; }); }
            junkOff.addEventListener('click', function(){
                ss.busy(junkOff, true, 'Turning off…');
                ss.post('hexa_sitemaps_disable_junk', nonce, {}).then(function(r){ say(r.message); return load(); }).catch(function(x){ ss.busy(junkOff, false); say(x.message, true); });
            });
            function onClick(e){
                var b = e.target.closest('button'); if (!b) return;
                var el = b.closest('[data-id]'), id = el.dataset.id;
                if (b.hasAttribute('data-promote')) {
                    ss.busy(b, true, 'Moving…');
                    ss.post('hexa_sitemaps_promote', nonce, {id: id, main: b.dataset.promote}).then(function(r){ say(r.message); return load(); }).catch(function(x){ ss.busy(b, false); say(x.message, true); });
                }
                if (b.hasAttribute('data-change')) {
                    ss.busy(b, true, 'Changing…');
                    ss.post('hexa_sitemaps_change', nonce, {id: id, on: b.dataset.change}).then(function(r){ say(r.message || 'Changed.', r.ok === false); return load(); }).catch(function(x){ ss.busy(b, false); say(x.message, true); });
                }
                if (b.hasAttribute('data-save')) {
                    var f = function(k){ var i = el.querySelector('[data-f=' + k + ']'); return i ? (i.type === 'checkbox' ? (i.checked ? '1' : '') : i.value) : ''; };
                    ss.busy(b, true, 'Saving…');
                    ss.post('hexa_sitemaps_conditions', nonce, {id: id, more_than: f('more_than') || 2, min_posts: f('min_posts') || 1, apply: f('apply')}).then(function(){ say('Saved.'); return load(); }).catch(function(x){ ss.busy(b, false); say(x.message, true); });
                }
            }
            list.addEventListener('click', onClick);
            hiddenList.addEventListener('click', onClick);
            load();
        })();
        </script>
        <?php
    }
}
