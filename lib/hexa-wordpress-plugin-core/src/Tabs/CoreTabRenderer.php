<?php

namespace Hexa\PluginCore\Tabs;

use Hexa\PluginCore\Activity\ActivityLogConfig;
use Hexa\PluginCore\Activity\ActivityLogEntry;
use Hexa\PluginCore\Activity\ActivityLogger;
use Hexa\PluginCore\Activity\ActivityLogRenderer;
use Hexa\PluginCore\Support\CoreVersion;

final class CoreTabRenderer {
    private CoreTabConfig $config;

    public function __construct( CoreTabConfig $config ) {
        $this->config = $config;
    }

    public function render(): void {
        $dom = 'hexa-core-tab-' . md5( $this->config->core_root() );
        ?>
        <div id="<?php echo esc_attr( $dom ); ?>" class="hexa-core-tab">
            <style>
                #<?php echo esc_attr( $dom ); ?>{--hc-border:#dcdcde;--hc-muted:#646970;--hc-panel:#fff;--hc-soft:#f6f7f7;color:#1d2327}
                #<?php echo esc_attr( $dom ); ?> .hc-hero{background:#111827;border-radius:8px;color:#f8fafc;margin:0 0 18px;padding:22px}
                #<?php echo esc_attr( $dom ); ?> .hc-hero h2{color:#fff;font-size:24px;margin:0 0 8px}
                #<?php echo esc_attr( $dom ); ?> .hc-grid{display:grid;gap:16px;grid-template-columns:repeat(3,minmax(0,1fr));margin:0 0 18px}
                #<?php echo esc_attr( $dom ); ?> .hc-card{background:var(--hc-panel);border:1px solid var(--hc-border);border-radius:8px;padding:16px}
                #<?php echo esc_attr( $dom ); ?> .hc-card h3{font-size:16px;margin:0 0 10px}
                #<?php echo esc_attr( $dom ); ?> .hc-card p{color:#3c434a;font-size:14px;line-height:1.55;margin:0 0 10px}
                #<?php echo esc_attr( $dom ); ?> code{background:#eef0f2;padding:2px 5px}
                #<?php echo esc_attr( $dom ); ?> details{background:#fff;border:1px solid var(--hc-border);border-radius:8px;margin:0 0 12px;overflow:hidden}
                #<?php echo esc_attr( $dom ); ?> summary{cursor:pointer;font-size:15px;font-weight:700;padding:14px 16px}
                #<?php echo esc_attr( $dom ); ?> .hc-detail-body{border-top:1px solid var(--hc-border);padding:16px}
                #<?php echo esc_attr( $dom ); ?> pre{background:#0f1720;border-radius:8px;color:#dbe7f3;max-height:360px;overflow:auto;padding:14px;white-space:pre-wrap}
                #<?php echo esc_attr( $dom ); ?> .hc-list{margin:0 0 0 20px}
                #<?php echo esc_attr( $dom ); ?> .hc-path{word-break:break-all}
                @media(max-width:1000px){#<?php echo esc_attr( $dom ); ?> .hc-grid{grid-template-columns:1fr}}
            </style>

            <section class="hc-hero">
                <h2>Hexa WordPress Plugin Core</h2>
                <p>Shared source-of-truth library for plugin tabs, activity logs, update panels, shortcode registries, and agent-facing documentation.</p>
                <p>Core version in this package: <code><?php echo esc_html( CoreVersion::current( $this->config->core_root() ) ); ?></code></p>
            </section>

            <div class="hc-grid">
                <div class="hc-card">
                    <h3>README location</h3>
                    <p>Core README: <code class="hc-path"><?php echo esc_html( $this->config->readme_path() ); ?></code></p>
                    <p>Agent library file: <code class="hc-path"><?php echo esc_html( $this->config->library_path() ); ?></code></p>
                </div>
                <div class="hc-card">
                    <h3>Activity logs</h3>
                    <p>Use the dark expandable log for updater progress, tests, imports, background jobs, and admin actions.</p>
                    <p>Storage modes: <code>page</code>, <code>transient</code>, and <code>permanent</code>.</p>
                </div>
                <div class="hc-card">
                    <h3>Tabs</h3>
                    <p>The core tab registers through host-provided filters, so the host dashboard keeps control while the tab content comes from core.</p>
                    <p>Default tab ID: <code><?php echo esc_html( $this->config->tab_id() ); ?></code></p>
                </div>
            </div>

            <details open>
                <summary>Friendly version</summary>
                <div class="hc-detail-body">
                    <p>The core is the shared toolkit used by Hexa plugins. Instead of rebuilding tabs, logs, updater panels, and shortcode docs in every plugin, each plugin loads this package and lets the package provide the standard pieces.</p>
                    <p>The activity log is meant to be readable while work is happening. It starts dark, can collapse, and can show short messages first with details hidden until needed.</p>
                </div>
            </details>

            <details>
                <summary>Technical version</summary>
                <div class="hc-detail-body">
                    <ul class="hc-list">
                        <li><code>Hexa\PluginCore\Activity</code> provides entries, storage config, persistence, and a dark renderer.</li>
                        <li><code>Hexa\PluginCore\Tabs\CoreTabModule</code> injects a core tab through configured host filters.</li>
                        <li><code>Hexa\PluginCore\Updater</code> has two layers: host plugin updater and vendored core package updater.</li>
                        <li>Host plugins provide identity and hook names. Core classes provide the implementation.</li>
                    </ul>
                </div>
            </details>

            <details>
                <summary>README-style guide</summary>
                <div class="hc-detail-body">
                    <pre><?php echo esc_html( $this->readme_guide() ); ?></pre>
                </div>
            </details>

            <?php $this->render_activity_demo(); ?>
        </div>
        <?php
    }

    private function render_activity_demo(): void {
        $config = new ActivityLogConfig(
            [
                'id'          => 'hexa-core-activity-demo',
                'title'       => 'Hexa Core Activity Log Demo',
                'storage'     => ActivityLogConfig::STORAGE_PAGE,
                'storage_key' => 'hexa_core_activity_demo',
                'collapsed'   => false,
                'max_entries' => 50,
            ]
        );

        $logger = new ActivityLogger( $config );
        $logger->add( new ActivityLogEntry( 'Core tab registered.', [ 'tab_id' => $this->config->tab_id() ], 'system', 'tabs', null, 'success' ) );
        $logger->add( new ActivityLogEntry( 'Activity log renderer loaded in page-only mode.', [ 'storage' => 'page' ], 'system', 'activity', null, 'info', 'Page-only logs are removed when the admin page refreshes.' ) );
        $logger->add( new ActivityLogEntry( 'Transient and permanent modes are available for long-running monitors.', [ 'transient' => 'set_transient', 'permanent' => 'update_option' ], 'system', 'activity', null, 'warning' ) );

        ( new ActivityLogRenderer( $config ) )->render( $logger->all() );
    }

    private function readme_guide(): string {
        return <<<'README'
# Hexa WordPress Plugin Core Quick Guide

## Activity Logs

Use ActivityLogConfig to choose storage:

- page: render-only; removed on refresh
- transient: stored with WordPress transients and a TTL
- permanent: stored with WordPress options until cleared

Use ActivityLogger to add entries. Use ActivityLogRenderer to render the dark expandable UI.

## Tabs

Expose a host tab filter and host render filter. Register CoreTabModule with those hook names.

CoreTabModule adds a tab named "Hexa WordPress Plugin Core" and renders this documentation page from the shared core.

## Updaters

Use UpdaterPanelRenderer for the host WordPress plugin.
Use CorePackagePanelRenderer for the vendored Hexa WordPress Plugin Core library.

README;
    }
}
