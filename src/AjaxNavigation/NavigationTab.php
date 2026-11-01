<?php

declare(strict_types=1);
namespace HWS\BaseTools\AjaxNavigation;
use Hexa\PluginCore\WpAdminComponents\CoreUi;

final class NavigationTab {
    public static function render(): void {
        CoreUi::render_assets();
        $settings = Settings::get();
        ?>
        <div class="hpc-ui" id="hws-ajax-navigation-settings">
        <?php echo CoreUi::card([
            'title' => 'AJAX Navigation',
            'body_html' => '<p>Turbo Drive loads internal pages without a full reload. Off by default. Logged-in visitors, editors, protected paths, downloads and forms use their normal behavior.</p>'
                . CoreUi::toggle('enabled', $settings['enabled'], 'Enable AJAX navigation on this site', ['id' => 'hws-ajax-enabled'])
                . '<h3>Analytics page views</h3><p>Choose one owner. Manual sends one gtag page_view per completed swap; disable browser-history page views in GA4 Enhanced Measurement. History uses GA4 history measurement and sends no extra event.</p><select id="hws-ajax-analytics"><option value="manual"' . selected($settings['analytics'], 'manual', false) . '>Manual page views</option><option value="history"' . selected($settings['analytics'], 'history', false) . '>GA4 history measurement</option></select>'
                . '<h3>Excluded paths</h3><p>One path prefix per line. Built-in safety exclusions cannot be removed. Use / to exclude the entire site.</p>'
                . '<textarea id="hws-ajax-excluded" rows="10" class="large-text">' . esc_textarea(implode("\n", $settings['excluded_paths'])) . '</textarea>'
                . '<p><button type="button" class="button button-primary" id="hws-ajax-save">Save navigation settings</button></p><p id="hws-ajax-status" role="status"></p>',
        ]);
        echo CoreUi::card([
            'title' => 'Test before enabling for visitors',
            'body_html' => '<ul><li>Open every page type directly, then arrive by clicking a link.</li><li>Check title, description, canonical, Open Graph and structured data in the live document and compare with View Source (which always fetches the URL separately).</li><li>Check Elementor menus, widgets, loops, motion, sticky elements, popups and form validation.</li><li>Check Back, Forward, scroll restoration, keyboard focus and permanent elements.</li><li>Check excluded paths, failed requests and timeouts fall back to a normal load.</li><li>Confirm one GA4 page view per navigation in your configured analytics property. GA4 history measurement owns this by default.</li><li>Repeat with LiteSpeed optimization. Keep these two navigation scripts excluded from combine, defer and delay; keep page-dependent Elementor scripts out of aggregation.</li><li>Disable the switch immediately if any page breaks.</li></ul><p>Documentation: <a href="https://github.com/mikeyperes/hws-base-tools/blob/main/docs/ajax-navigation.md" target="_blank" rel="noopener">AJAX navigation, events and opt-outs</a>.</p>',
        ]); ?>
        <script>
        (() => {
            const root = document.getElementById('hws-ajax-navigation-settings');
            root.querySelector('#hws-ajax-save').addEventListener('click', async () => {
                const data = new FormData(), status = root.querySelector('#hws-ajax-status');
                data.set('action', 'hws_base_tools_ajax_navigation_save');
                data.set('nonce', <?php echo wp_json_encode(wp_create_nonce('hws_base_tools_ajax_navigation')); ?>);
                data.set('analytics', root.querySelector('#hws-ajax-analytics').value);
                data.set('enabled', root.querySelector('#hws-ajax-enabled').checked ? '1' : '0');
                data.set('excluded_paths', root.querySelector('#hws-ajax-excluded').value);
                status.textContent = 'Saving...';
                try {
                    const response = await fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>, { method: 'POST', credentials: 'same-origin', body: data });
                    const json = await response.json();
                    status.textContent = json.data?.message || 'Settings were not saved.';
                } catch { status.textContent = 'Settings were not saved. Retry when the connection is restored.'; }
            });
        })();
        </script>
        </div>
        <?php
    }
}
