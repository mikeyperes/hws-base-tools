<?php

namespace Hexa\PluginCore\Tabs;

use Hexa\PluginCore\Activity\ActivityLogConfig;
use Hexa\PluginCore\Activity\ActivityLogEntry;
use Hexa\PluginCore\Activity\ActivityLogger;
use Hexa\PluginCore\Activity\ActivityLogRenderer;
use Hexa\PluginCore\Support\CoreVersion;
use Hexa\PluginCore\UI\CoreUi;

final class CoreTabRenderer {
    private CoreTabConfig $config;

    public function __construct( CoreTabConfig $config ) {
        $this->config = $config;
    }

    public function render(): void {
        CoreUi::render_assets();

        $core_version = CoreVersion::current( $this->config->core_root() );
        ?>
        <div class="hpc-ui">
            <div class="hpc-shell">
                <section class="hpc-hero">
                    <div>
                        <h2>Hexa WordPress Plugin Core</h2>
                        <p>Shared UI, tabs, activity logs, updater panels, shortcode registries, and agent-facing documentation for Hexa plugins.</p>
                    </div>
                    <div class="hpc-actions" style="align-content:start;justify-content:flex-end;">
                        <?php echo CoreUi::pill( 'Core v' . $core_version, 'dark' ); ?>
                        <?php echo CoreUi::pill( 'Public repo', 'success' ); ?>
                    </div>
                </section>

                <div class="hpc-grid">
                    <?php
                    echo CoreUi::card(
                        [
                            'title'     => 'Source of truth',
                            'body_html' => '<p>The core package owns shared structures. Host plugins pass settings and hook names, then the core renders consistent components.</p>'
                                . '<p><span class="hpc-code">Hexa\\PluginCore\\</span></p>',
                        ]
                    );
                    echo CoreUi::card(
                        [
                            'title'     => 'README files',
                            'body_html' => '<p>Main README: <span class="hpc-path">' . esc_html( $this->config->readme_path() ) . '</span></p>'
                                . '<p>Agent guide: <span class="hpc-path">' . esc_html( $this->config->library_path() ) . '</span></p>',
                            'meta_html' => CoreUi::copy_button( $this->config->readme_path(), 'Copy README path' ),
                        ]
                    );
                    echo CoreUi::card(
                        [
                            'title'     => 'First extracted pieces',
                            'body_html' => '<p>Current extraction order: UI primitives, tabs, activity logs, updater panels, and error-log viewing.</p>'
                                . '<p>' . CoreUi::tooltip( 'This tab is itself rendered by the core tab module, through HWS host filters.' ) . ' Core-rendered tab content.</p>',
                        ]
                    );
                    ?>
                </div>

                <div style="height:14px"></div>

                <?php echo $this->render_ui_primitives_section(); ?>
                <?php echo $this->render_activity_section(); ?>
                <?php echo $this->render_error_logs_section(); ?>
                <?php echo $this->render_agent_docs_section(); ?>

                <?php $this->render_activity_demo(); ?>
            </div>
        </div>
        <?php
    }

    private function render_ui_primitives_section(): string {
        $body = '<div class="hpc-grid two">'
            . CoreUi::subcard(
                [
                    'title'     => 'Cards and subcards',
                    'body_html' => '<p>Use <span class="hpc-code">CoreUi::card()</span> for primary surfaces and <span class="hpc-code">CoreUi::subcard()</span> for grouped controls inside a larger section.</p>',
                ]
            )
            . CoreUi::subcard(
                [
                    'title'     => 'Tooltips and pills',
                    'body_html' => '<p>Use tooltips for compact help and pills for status. Example: ' . CoreUi::tooltip( 'Tooltip text is passed as an input parameter.' ) . ' ' . CoreUi::pill( 'Healthy', 'success' ) . ' ' . CoreUi::pill( 'Warning', 'warning' ) . '</p>',
                ]
            )
            . CoreUi::subcard(
                [
                    'title'     => 'Collapsible sections',
                    'body_html' => '<p>Use <span class="hpc-code">CoreUi::collapsible()</span> for expandable documentation, logs, and advanced settings.</p>',
                ]
            )
            . CoreUi::subcard(
                [
                    'title'     => 'Buttons',
                    'body_html' => '<p>Use standard button tones from core instead of per-plugin CSS. The first HWS swap is this tab plus the error-log viewer foundation.</p>',
                ]
            )
            . '</div>';

        return CoreUi::collapsible(
            [
                'title'     => 'Core UI primitives',
                'open'      => true,
                'meta_html' => CoreUi::pill( 'Reusable UI', 'success' ),
                'body_html' => $body,
            ]
        );
    }

    private function render_activity_section(): string {
        $body = '<p>The activity log component supports three storage modes and always renders the same dark expandable monitor.</p>'
            . '<div class="hpc-grid">'
            . CoreUi::subcard( [ 'title' => 'page', 'body_html' => '<p>Render-only. Data disappears when the page refreshes.</p>' ] )
            . CoreUi::subcard( [ 'title' => 'transient', 'body_html' => '<p>Stored with WordPress transients and a TTL.</p>' ] )
            . CoreUi::subcard( [ 'title' => 'permanent', 'body_html' => '<p>Stored with WordPress options until cleared.</p>' ] )
            . '</div>';

        return CoreUi::collapsible(
            [
                'title'     => 'Activity log structure',
                'open'      => true,
                'meta_html' => CoreUi::pill( 'Dark monitor', 'dark' ),
                'body_html' => $body,
            ]
        );
    }

    private function render_error_logs_section(): string {
        $body = '<p>HWS currently has two log systems: the Overview error-log viewer and the Log Cleaner cron/delete workflow. The reusable core layer now owns reading, tailing, classifying, highlighting, searching, and rendering log files.</p>'
            . '<div class="hpc-grid two">'
            . CoreUi::subcard(
                [
                    'title'     => 'Reusable core classes',
                    'body_html' => '<ul class="hpc-list"><li><span class="hpc-code">ErrorLogSource</span></li><li><span class="hpc-code">ErrorLogReader</span></li><li><span class="hpc-code">ErrorLogClassifier</span></li><li><span class="hpc-code">ErrorLogPanelRenderer</span></li></ul>',
                ]
            )
            . CoreUi::subcard(
                [
                    'title'     => 'HWS first swap',
                    'body_html' => '<p>The Overview tab can now render the error-log viewer from core while keeping HWS delete AJAX intact. The cleaner cron/settings system is the next extraction target.</p>',
                ]
            )
            . '</div>';

        return CoreUi::collapsible(
            [
                'title'     => 'Error logs as a core feature',
                'open'      => true,
                'meta_html' => CoreUi::pill( 'Foundation added', 'warning' ),
                'body_html' => $body,
            ]
        );
    }

    private function render_agent_docs_section(): string {
        $guide = <<<'README'
# Core Implementation Guide

## UI
Use Hexa\PluginCore\UI\CoreUi for cards, subcards, buttons, pills, tooltips, and collapsible sections.

## Tabs
Host plugin exposes a tab-list filter and a render filter. CoreTabModule registers "hexa-core" through those hooks.

## Activity Logs
Use ActivityLogConfig storage modes:
- page
- transient
- permanent

## Error Logs
Use Logs\ErrorLogSource for each file path, ErrorLogReader for tails/classification, and ErrorLogPanelRenderer for the UI.

README;

        $body = '<div class="hpc-grid two">'
            . CoreUi::subcard(
                [
                    'title'     => 'Friendly version',
                    'body_html' => '<p>This core keeps plugin screens consistent. Build the pattern once in core, pass plugin-specific values into it, and then reuse it everywhere.</p>',
                ]
            )
            . CoreUi::subcard(
                [
                    'title'     => 'Technical version',
                    'body_html' => '<p>Core modules are host-neutral. Host plugins provide filter names, paths, slugs, capabilities, and repositories. Core classes render the standardized UI and behavior.</p>',
                ]
            )
            . '</div><pre class="hpc-readme">' . esc_html( $guide ) . '</pre>';

        return CoreUi::collapsible(
            [
                'title'     => 'README-style guide for agents',
                'open'      => false,
                'meta_html' => CoreUi::pill( 'Agent handoff', '' ),
                'body_html' => $body,
            ]
        );
    }

    private function render_activity_demo(): void {
        $config = new ActivityLogConfig(
            [
                'id'          => 'hexa-core-activity-demo',
                'title'       => 'Core Activity Monitor Demo',
                'storage'     => ActivityLogConfig::STORAGE_PAGE,
                'storage_key' => 'hexa_core_activity_demo',
                'collapsed'   => false,
                'max_entries' => 50,
            ]
        );

        $logger = new ActivityLogger( $config );
        $logger->add( new ActivityLogEntry( 'Core tab rendered from shared UI primitives.', [ 'component' => 'CoreUi' ], 'system', 'ui', null, 'success' ) );
        $logger->add( new ActivityLogEntry( 'Activity log loaded in page-only mode.', [ 'storage' => 'page' ], 'system', 'activity', null, 'info', 'Page-only entries disappear on refresh.' ) );
        $logger->add( new ActivityLogEntry( 'Error-log core foundation is available for HWS swap.', [ 'namespace' => 'Hexa\\PluginCore\\Logs' ], 'system', 'logs', null, 'warning' ) );

        ( new ActivityLogRenderer( $config ) )->render( $logger->all() );
    }
}
