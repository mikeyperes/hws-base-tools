<?php

namespace HWS\BaseTools\AdminDashboard;

defined( 'ABSPATH' ) || exit;

/**
 * The shared look of the Sitemaps, Search Console and Instant Indexing screens:
 * one column of white rows, a coloured status dot, plain pills and one small
 * fetch helper. Printed once per screen, only on those tabs.
 */
final class SeoScreenUi {
    private static bool $printed = false;

    public static function assets(): void {
        if ( self::$printed ) {
            return;
        }
        self::$printed = true;
        ?>
        <style>
            .hws-ss{max-width:980px}
            .hws-ss-head{margin:4px 0 18px}
            .hws-ss-head h2{margin:0 0 4px;font-size:22px}
            .hws-ss-head p{margin:0;color:#646970}
            .hws-ss-section{margin:0 0 28px}
            .hws-ss-section>h3{font-size:16px;margin:0 0 4px;display:flex;align-items:center;justify-content:space-between;gap:12px}
            .hws-ss-section>p{margin:0 0 10px;color:#646970}
            .hws-ss-group{margin:14px 0 6px;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#787c82}
            .hws-ss-row{background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:12px 14px;margin-bottom:8px}
            .hws-ss-row.attention{border-left:4px solid #dba617}
            .hws-ss-top{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
            .hws-ss-dot{flex:0 0 12px;width:12px;height:12px;border-radius:50%;background:#c3c4c7}
            .hws-ss-dot.ok{background:#00a32a}.hws-ss-dot.bad{background:#d63638}.hws-ss-dot.warn{background:#dba617}
            .hws-ss .hws-ss-label{font-weight:600;font-size:14px;flex:1;min-width:180px;color:#1d2327}
            .hws-ss-label small{display:block;font-weight:400;color:#787c82;font-size:12px;margin-top:2px;word-break:break-all}
            .hws-ss-pills{display:flex;gap:6px;flex-wrap:wrap}
            .hws-ss-pill{font-size:11px;font-weight:600;padding:2px 8px;border-radius:999px;background:#f0f0f1;color:#50575e;white-space:nowrap}
            .hws-ss-pill.ok{background:#edfaef;color:#00701a}.hws-ss-pill.bad{background:#fcf0f1;color:#b32d2e}.hws-ss-pill.warn{background:#fcf9e8;color:#8a6100}
            .hws-ss .hws-ss-sub{margin:6px 0 0 22px;color:#50575e;font-size:12px;font-weight:400;line-height:1.6}
            .hws-ss .hws-ss-sub.warn{color:#8a6100}.hws-ss .hws-ss-sub.bad{color:#b32d2e}
            .hws-ss .hws-ss-cond{margin:10px 0 0 22px;padding:10px 12px;background:#f6f7f7;border-radius:6px;display:grid;gap:8px;font-size:12px;font-weight:400;color:#50575e}
            .hws-ss .hws-ss-cond label{display:flex;align-items:center;gap:6px;font-weight:400}
            .hws-ss .hws-ss-cond input[type=number]{width:56px;min-height:26px;padding:0 6px}
            .hws-ss .hws-ss-cond-foot{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
            .hws-ss-form{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
            .hws-ss-form input[type=url]{flex:1;min-width:260px}
            .hws-ss-result{margin-top:10px;font-size:13px}
            .hws-ss-result:empty{display:none}
            .hws-ss-list{margin:6px 0 0 22px;padding:0;list-style:none;font-size:12px;color:#50575e}
            .hws-ss-list li{margin:2px 0}
            .hws-ss-table{width:100%;border-collapse:collapse;font-size:12px;background:#fff;border:1px solid #dcdcde;border-radius:6px}
            .hws-ss-table th,.hws-ss-table td{padding:8px 10px;border-bottom:1px solid #f0f0f1;text-align:left;vertical-align:top}
            .hws-ss-table th{font-weight:600;color:#1d2327}
            .hws-ss-table td.url{word-break:break-all}
            .hws-ss-empty{color:#787c82;font-size:13px;padding:6px 0}
            .hws-ss-notice{margin-top:8px;font-size:12px;color:#00701a}.hws-ss-notice.bad{color:#b32d2e}
            .hws-ss-notice:empty{display:none}
            .hws-ss-hidden{margin-top:14px;border:1px solid #dcdcde;border-radius:6px;background:#f6f7f7}
            .hws-ss-hidden>summary{cursor:pointer;padding:10px 14px;font-weight:600;font-size:13px;color:#1d2327;display:flex;align-items:center;gap:6px}
            .hws-ss-hidden[open]>summary{border-bottom:1px solid #dcdcde}
            .hws-ss-hidden>div{padding:10px 14px 4px}
            .hws-ss-hidden-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
            .hws-ss-hidden-head p{margin:0;color:#646970;font-size:12px;flex:1;min-width:240px}
            .hws-ss-hidden [data-role=hidden-list]{padding-top:0}
            .hws-ss textarea{width:100%;min-height:110px;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px}
        </style>
        <script>
        window.hwsSs = window.hwsSs || {
            esc: function(v){ var d=document.createElement('div'); d.textContent = v == null ? '' : String(v); return d.innerHTML; },
            post: function(action, nonce, fields){
                var body = new URLSearchParams(); body.set('action', action); body.set('nonce', nonce);
                Object.keys(fields || {}).forEach(function(k){ body.set(k, fields[k]); });
                return fetch(ajaxurl, {method:'POST', credentials:'same-origin', body: body})
                    .then(function(r){ return r.json(); })
                    .then(function(j){ if (!j || !j.success) { throw new Error((j && j.data && j.data.message) || 'The request failed.'); } return j.data; });
            },
            date: function(v){ if (!v) return 'never'; var t = typeof v === 'number' ? new Date(v*1000) : new Date(v); return isNaN(t) ? String(v) : t.toLocaleString(); },
            busy: function(btn, on, label){ if (!btn) return; if (on) { btn.dataset.label = btn.textContent; btn.textContent = label || 'Working…'; btn.disabled = true; } else { btn.textContent = btn.dataset.label || btn.textContent; btn.disabled = false; } }
        };
        </script>
        <?php
    }
}
