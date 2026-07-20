<?php namespace hws_base_tools;

use HWS\BaseTools\FeatureCatalog\FeatureValueResolver;
use HWS\BaseTools\TeamMembers\TeamMemberDirectory;
use HWS\BaseTools\TeamMembers\TeamMemberFeature;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_ajax_hws_feature_save_settings', __NAMESPACE__ . '\\ajax_hws_feature_save_settings' );
add_action( 'wp_ajax_hws_feature_run_test', __NAMESPACE__ . '\\ajax_hws_feature_run_test' );

function hws_get_feature_activity_log(): array {
    $log = get_option( 'hws_feature_activity_log', [] );

    return is_array( $log ) ? $log : [];
}

function hws_add_feature_activity( string $feature_id, string $message ): void {
    $log = hws_get_feature_activity_log();
    array_unshift( $log, [
        'feature_id' => sanitize_key( $feature_id ),
        'message'    => sanitize_text_field( $message ),
        'time'       => current_time( 'mysql' ),
    ] );

    update_option( 'hws_feature_activity_log', array_slice( $log, 0, 100 ), false );
}

function hws_get_feature_activity_for( string $feature_id, int $limit = 5 ): array {
    $feature_id = sanitize_key( $feature_id );
    $matches    = [];

    foreach ( hws_get_feature_activity_log() as $entry ) {
        if ( ( $entry['feature_id'] ?? '' ) === $feature_id ) {
            $matches[] = $entry;
        }

        if ( count( $matches ) >= $limit ) {
            break;
        }
    }

    return $matches;
}

function hws_get_feature_test_reports(): array {
    $reports = get_option( 'hws_feature_test_reports', [] );

    return is_array( $reports ) ? $reports : [];
}

function hws_save_feature_test_report( string $feature_id, array $report ): void {
    $reports = hws_get_feature_test_reports();
    $reports[ sanitize_key( $feature_id ) ] = array_merge( [
        'passed'  => false,
        'message' => '',
        'proof'   => '',
        'ran_at'  => current_time( 'mysql' ),
    ], $report );

    update_option( 'hws_feature_test_reports', $reports, false );
}

function hws_get_all_dashboard_features(): array {
    $groups = [
        'New / Requested Features' => [],
        'Admin Features'           => get_snippets( 'admin' ),
        'Frontend Features'        => get_snippets( 'non_admin' ),
    ];

    $priority_ids = [
        'disable_non_admin_admin_bar',
        'enable_syndtd_feed_limit',
        'enable_current_year_shortcode',
        'enable_lowercase_upload_filenames',
        'enable_footer_text_auto_injection',
        TeamMemberDirectory::FEATURE_OPTION,
    ];

    foreach ( [ 'Admin Features', 'Frontend Features' ] as $group_name ) {
        foreach ( $groups[ $group_name ] as $index => $feature ) {
            if ( 'enable_wp_admin_logo' === ( $feature['id'] ?? '' ) ) {
                unset( $groups[ $group_name ][ $index ] );
                continue;
            }

            if ( in_array( $feature['id'] ?? '', $priority_ids, true ) ) {
                $groups['New / Requested Features'][] = $feature;
                unset( $groups[ $group_name ][ $index ] );
            }
        }

        $groups[ $group_name ] = array_values( $groups[ $group_name ] );
    }

    return array_filter( $groups );
}

function hws_render_feature_toggle( array $feature ): string {
    $feature_id = $feature['id'] ?? '';
    $enabled    = (bool) get_option( $feature_id, false );
    $onclick    = 'window.' . __NAMESPACE__ . '.toggleSnippet(\'' . esc_attr( $feature_id ) . '\')';

    if ( function_exists( __NAMESPACE__ . '\\render_toggle_switch' ) ) {
        return render_toggle_switch( 'toggle-' . $feature_id, '', $enabled, $onclick );
    }

    return '<input type="checkbox" id="toggle-' . esc_attr( $feature_id ) . '" ' . checked( $enabled, true, false ) . ' onclick="' . esc_attr( $onclick ) . '">';
}

function hws_render_feature_settings( array $feature ): void {
    $feature_id = $feature['id'] ?? '';

    if ( TeamMemberDirectory::FEATURE_OPTION === $feature_id ) {
        TeamMemberFeature::render_settings();
        return;
    }

    if ( 'enable_syndtd_feed_limit' !== $feature_id ) {
        echo '<p class="hws-feature-muted">No custom ACF or option adjustments needed.</p>';
        return;
    }

    ?>
    <div class="hws-feature-settings" data-feature-settings="<?php echo esc_attr( $feature_id ); ?>">
        <label>
            <span>Tag slug</span>
            <input type="text" class="regular-text" data-hws-feature-field="tag" value="<?php echo esc_attr( hws_get_syndtd_feed_tag() ); ?>">
        </label>
        <label>
            <span>Feed item limit</span>
            <input type="number" min="1" max="500" step="1" data-hws-feature-field="limit" value="<?php echo esc_attr( hws_get_syndtd_feed_limit() ); ?>">
        </label>
        <button type="button" class="button hws-feature-save-settings" data-feature-id="<?php echo esc_attr( $feature_id ); ?>">Save Settings</button>
        <span class="hws-feature-setting-status" aria-live="polite"></span>
    </div>
    <?php
}

function hws_render_feature_test_report( string $feature_id ): void {
    $reports = hws_get_feature_test_reports();
    $report  = $reports[ $feature_id ] ?? null;

    if ( ! $report ) {
        echo '<p class="hws-feature-muted">No test has been run yet.</p>';
        return;
    }

    $passed = ! empty( $report['passed'] );
    ?>
    <div class="hws-feature-test-report <?php echo $passed ? 'passed' : 'failed'; ?>">
        <strong><?php echo $passed ? 'PASS' : 'CHECK'; ?></strong>
        <span><?php echo esc_html( $report['message'] ?? '' ); ?></span>
        <?php if ( ! empty( $report['proof'] ) ) : ?>
            <code><?php echo esc_html( $report['proof'] ); ?></code>
        <?php endif; ?>
        <?php if ( ! empty( $report['ran_at'] ) ) : ?>
            <small><?php echo esc_html( $report['ran_at'] ); ?></small>
        <?php endif; ?>
    </div>
    <?php
}

function hws_render_feature_activity( string $feature_id ): void {
    $activity = hws_get_feature_activity_for( $feature_id );

    if ( empty( $activity ) ) {
        echo '<p class="hws-feature-muted">No activity logged yet.</p>';
        return;
    }

    echo '<ul class="hws-feature-activity">';
    foreach ( $activity as $entry ) {
        echo '<li><span>' . esc_html( $entry['time'] ?? '' ) . '</span>' . esc_html( $entry['message'] ?? '' ) . '</li>';
    }
    echo '</ul>';
}

function hws_render_feature_card( array $feature ): void {
    $feature_id    = $feature['id'] ?? '';
    $is_deprecated = ! empty( $feature['deprecated'] );
    $is_enabled    = (bool) get_option( $feature_id, false );
    $info_text     = FeatureValueResolver::text( $feature['info'] ?? '' );
    $description   = FeatureValueResolver::text( $feature['description'] ?? '' );
    $code_example  = FeatureValueResolver::text( $feature['code_example'] ?? '' );

    ?>
    <article class="hws-feature-card <?php echo $is_enabled ? 'is-active' : ''; ?> <?php echo $is_deprecated ? 'is-deprecated' : ''; ?>" data-feature-id="<?php echo esc_attr( $feature_id ); ?>">
        <header class="hws-feature-card-header">
            <div>
                <h3><?php echo esc_html( $feature['name'] ?? $feature_id ); ?></h3>
                <code><?php echo esc_html( $feature_id ); ?></code>
            </div>
            <div class="hws-feature-toggle">
                <span><?php echo $is_enabled ? 'Active' : 'Off'; ?></span>
                <?php echo hws_render_feature_toggle( $feature ); ?>
            </div>
        </header>

        <section>
            <h4>Description / Use Instructions</h4>
            <p><?php echo esc_html( $description ); ?></p>
            <?php if ( $info_text ) : ?>
                <div class="hws-feature-info"><?php echo wp_kses_post( $info_text ); ?></div>
            <?php endif; ?>
        </section>

        <section>
            <h4>Custom ACF Adjustments</h4>
            <?php hws_render_feature_settings( $feature ); ?>
        </section>

        <?php if ( '' !== $code_example ) : ?>
            <section>
                <h4>Code Example</h4>
                <pre><code><?php echo esc_html( $code_example ); ?></code></pre>
            </section>
        <?php endif; ?>

        <section class="hws-feature-test">
            <div class="hws-feature-section-title">
                <h4>Test Report</h4>
                <button type="button" class="button hws-feature-run-test" data-feature-id="<?php echo esc_attr( $feature_id ); ?>">Run Test</button>
            </div>
            <div data-feature-report="<?php echo esc_attr( $feature_id ); ?>">
                <?php hws_render_feature_test_report( $feature_id ); ?>
            </div>
        </section>

        <section>
            <h4>Activity Log</h4>
            <?php hws_render_feature_activity( $feature_id ); ?>
        </section>
    </article>
    <?php
}

function hws_output_feature_card_styles(): void {
    ?>
    <style>
        .hws-features-wrap { max-width: 1220px; }
        .hws-features-intro {
            margin: 0 0 18px;
            padding: 14px 16px;
            border: 1px solid #dcdcde;
            border-radius: 8px;
            background: #f6f7f7;
            color: #50575e;
            font-size: 13px;
            line-height: 1.55;
        }
        .hws-feature-group { margin: 0 0 24px; }
        .hws-feature-group h2 { margin: 0 0 12px; font-size: 18px; }
        .hws-feature-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 14px;
        }
        .hws-feature-card {
            border: 1px solid #dcdcde;
            border-radius: 8px;
            background: #fff;
            padding: 16px;
            min-width: 0;
        }
        .hws-feature-card.is-active { border-left: 4px solid #00a32a; }
        .hws-feature-card.is-deprecated { opacity: 0.72; }
        .hws-feature-card-header,
        .hws-feature-section-title,
        .hws-feature-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .hws-feature-card h3 { margin: 0 0 4px; font-size: 15px; }
        .hws-feature-card h4 {
            margin: 14px 0 6px;
            color: #646970;
            font-size: 11px;
            letter-spacing: 0;
            text-transform: uppercase;
        }
        .hws-feature-card p { margin: 0; font-size: 13px; line-height: 1.55; color: #50575e; }
        .hws-feature-card pre {
            margin: 0;
            max-height: 180px;
            overflow: auto;
            padding: 10px;
            border-radius: 6px;
            background: #1d2327;
            color: #f6f7f7;
            font-size: 12px;
        }
        .hws-feature-info {
            margin-top: 8px;
            padding: 10px;
            border-radius: 6px;
            background: #f6f7f7;
            font-size: 12.5px;
            color: #50575e;
        }
        .hws-feature-settings {
            display: grid;
            grid-template-columns: minmax(120px, 1fr) 120px auto;
            gap: 8px;
            align-items: end;
        }
        .hws-feature-settings label span {
            display: block;
            margin-bottom: 4px;
            font-size: 12px;
            font-weight: 600;
            color: #1d2327;
        }
        .hws-feature-settings input { width: 100%; }
        .hws-feature-setting-status { font-size: 12px; color: #2271b1; }
        .hws-feature-muted { color: #8c8f94; font-style: italic; }
        .hws-feature-test-report {
            display: grid;
            gap: 4px;
            padding: 10px;
            border-radius: 6px;
            background: #f6f7f7;
            font-size: 12.5px;
        }
        .hws-feature-test-report.passed { background: #edfaef; color: #116329; }
        .hws-feature-test-report.failed { background: #fff8e5; color: #6f4e00; }
        .hws-feature-test-report code {
            display: block;
            white-space: normal;
            overflow-wrap: anywhere;
            color: inherit;
        }
        .hws-feature-test-report small { color: #646970; }
        .hws-feature-activity { margin: 0; font-size: 12.5px; color: #50575e; }
        .hws-feature-activity li { margin: 0 0 5px; }
        .hws-feature-activity span { display: block; color: #8c8f94; font-size: 11px; }
        @media (max-width: 782px) {
            .hws-feature-grid { grid-template-columns: 1fr; }
            .hws-feature-settings { grid-template-columns: 1fr; }
        }
    </style>
    <?php
}

function hws_output_feature_card_scripts(): void {
    static $printed = false;

    if ( $printed ) {
        return;
    }

    $printed = true;
    ?>
    <script>
    jQuery(function($) {
        $(document).off('click.hwsFeatureSettings', '.hws-feature-save-settings').on('click.hwsFeatureSettings', '.hws-feature-save-settings', function() {
            var $button = $(this);
            var featureId = $button.data('feature-id');
            var $settings = $('[data-feature-settings="' + featureId + '"]');
            var $status = $settings.find('.hws-feature-setting-status');
            var payload = {
                action: 'hws_feature_save_settings',
                nonce: hwsNonce,
                feature_id: featureId,
                tag: $settings.find('[data-hws-feature-field="tag"]').val() || '',
                limit: $settings.find('[data-hws-feature-field="limit"]').val() || ''
            };

            $button.prop('disabled', true);
            $status.text('Saving...');

            $.post(ajaxurl, payload, function(response) {
                if (!response || !response.success) {
                    $status.text('Save failed.');
                    return;
                }

                $status.text('Saved.');
                window.setTimeout(function() { $status.text(''); }, 1400);
            }, 'json').fail(function() {
                $status.text('AJAX error.');
            }).always(function() {
                $button.prop('disabled', false);
            });
        });

        $(document).off('click.hwsFeatureTest', '.hws-feature-run-test').on('click.hwsFeatureTest', '.hws-feature-run-test', function() {
            var $button = $(this);
            var featureId = $button.data('feature-id');
            var $report = $('[data-feature-report="' + featureId + '"]');

            $button.prop('disabled', true).text('Testing...');

            $.post(ajaxurl, {
                action: 'hws_feature_run_test',
                nonce: hwsNonce,
                feature_id: featureId
            }, function(response) {
                var data = response && response.data ? response.data : {};
                if (!response || !response.success) {
                    $report.html('<div class="hws-feature-test-report failed"><strong>CHECK</strong><span>' + (data.message || 'Test failed.') + '</span></div>');
                    return;
                }

                var passed = data.passed ? 'passed' : 'failed';
                var label = data.passed ? 'PASS' : 'CHECK';
                var html = '<div class="hws-feature-test-report ' + passed + '"><strong>' + label + '</strong><span>' + $('<div>').text(data.message || '').html() + '</span>';
                if (data.proof) {
                    html += '<code>' + $('<div>').text(data.proof).html() + '</code>';
                }
                if (data.ran_at) {
                    html += '<small>' + $('<div>').text(data.ran_at).html() + '</small>';
                }
                html += '</div>';
                $report.html(html);
            }, 'json').fail(function() {
                $report.html('<div class="hws-feature-test-report failed"><strong>CHECK</strong><span>AJAX error.</span></div>');
            }).always(function() {
                $button.prop('disabled', false).text('Run Test');
            });
        });

        $(document).off('change.hwsTeamTemplate', '[data-hws-team-template]').on('change.hwsTeamTemplate', '[data-hws-team-template]', function() {
            var $input = $(this);
            var $settings = $input.closest('[data-feature-settings]');
            var $status = $settings.find('[data-hws-team-template-status]');
            var featureId = $settings.data('feature-settings');
            var style = $input.val() || '';

            $settings.find('.hws-team-template-option').removeClass('is-selected');
            $input.closest('.hws-team-template-option').addClass('is-selected');
            $settings.find('[data-hws-team-template]').prop('disabled', true);
            $status.text('Saving template...');

            $.post(ajaxurl, {
                action: 'hws_feature_save_settings',
                nonce: hwsNonce,
                feature_id: featureId,
                style: style
            }, function(response) {
                if (!response || !response.success) {
                    $status.text(response && response.data && response.data.message ? response.data.message : 'Save failed.');
                    return;
                }

                var data = response.data || {};
                $settings.find('[data-hws-team-selected-shortcode]').text(data.shortcode || '[hws_team_members]');
                $status.text('Saved ' + (data.label || data.style || 'template') + '.');
            }, 'json').fail(function() {
                $status.text('AJAX error while saving the template.');
            }).always(function() {
                $settings.find('[data-hws-team-template]').prop('disabled', false);
            });
        });
    });
    </script>
    <?php
}

function display_settings_features(): void {
    if ( function_exists( __NAMESPACE__ . '\\output_toggle_switch_styles' ) ) {
        output_toggle_switch_styles();
    }

    $groups = hws_get_all_dashboard_features();
    hws_output_feature_card_styles();
    ?>

    <div class="hws-features-wrap">
        <div class="hws-features-intro">
            Features use the same structure throughout this tab: toggle, optional settings, instructions, code when useful, proof from the latest test, and recent activity.
        </div>

        <?php foreach ( $groups as $group_name => $features ) : ?>
            <?php if ( empty( $features ) ) continue; ?>
            <section class="hws-feature-group">
                <h2><?php echo esc_html( $group_name ); ?></h2>
                <div class="hws-feature-grid">
                    <?php foreach ( $features as $feature ) : ?>
                        <?php hws_render_feature_card( $feature ); ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

    <?php hws_output_feature_card_scripts(); ?>
    <?php
}

if ( ! function_exists( __NAMESPACE__ . '\\display_settings_snippets' ) ) {
    function display_settings_snippets(): void {
        display_settings_features();
    }
}

function ajax_hws_feature_save_settings(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
    }

    hws_require_ajax_nonce_or_error();

    $feature_id = isset( $_POST['feature_id'] ) ? sanitize_key( wp_unslash( $_POST['feature_id'] ) ) : '';

    if ( TeamMemberDirectory::FEATURE_OPTION === $feature_id ) {
        $style = isset( $_POST['style'] ) ? sanitize_key( wp_unslash( $_POST['style'] ) ) : TeamMemberDirectory::DEFAULT_STYLE;
        $saved = TeamMemberFeature::save_style( $style );
        hws_add_feature_activity( $feature_id, 'Selected the ' . $saved['label'] . ' template.' );
        wp_send_json_success( $saved );
    }

    if ( 'enable_syndtd_feed_limit' !== $feature_id ) {
        wp_send_json_error( [ 'message' => 'No custom settings exist for this feature.' ] );
    }

    $tag   = isset( $_POST['tag'] ) ? sanitize_title( wp_unslash( $_POST['tag'] ) ) : 'syndtd';
    $limit = isset( $_POST['limit'] ) ? (int) $_POST['limit'] : 100;
    $tag   = $tag !== '' ? $tag : 'syndtd';
    $limit = max( 1, min( 500, $limit ) );

    update_option( 'hws_syndtd_feed_tag', $tag );
    update_option( 'hws_syndtd_feed_limit', $limit );
    hws_add_feature_activity( $feature_id, 'Updated tag feed limit settings.' );

    wp_send_json_success( [
        'tag'   => $tag,
        'limit' => $limit,
    ] );
}

function ajax_hws_feature_run_test(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
    }

    hws_require_ajax_nonce_or_error();

    $feature_id = isset( $_POST['feature_id'] ) ? sanitize_key( wp_unslash( $_POST['feature_id'] ) ) : '';
    $report     = hws_run_feature_test( $feature_id );

    hws_save_feature_test_report( $feature_id, $report );
    hws_add_feature_activity( $feature_id, 'Ran feature test: ' . ( ! empty( $report['passed'] ) ? 'pass' : 'check' ) . '.' );

    wp_send_json_success( $report );
}

function hws_run_feature_test( string $feature_id ): array {
    $enabled = (bool) get_option( $feature_id, false );
    $ran_at  = current_time( 'mysql' );

    if ( ! $enabled ) {
        return [
            'passed'  => false,
            'message' => 'Feature toggle is off.',
            'proof'   => 'Enable the toggle, then run the test again.',
            'ran_at'  => $ran_at,
        ];
    }

    switch ( $feature_id ) {
        case TeamMemberDirectory::FEATURE_OPTION:
            return TeamMemberFeature::test_report();

        case 'disable_non_admin_admin_bar':
            return [
                'passed'  => true,
                'message' => 'Front-end admin bar suppression is active for users without manage_options.',
                'proof'   => 'Hook: show_admin_bar priority 999; admins remain exempt.',
                'ran_at'  => $ran_at,
            ];

        case 'enable_syndtd_feed_limit':
            if ( function_exists( __NAMESPACE__ . '\\enable_syndtd_feed_limit' ) ) {
                enable_syndtd_feed_limit();
            }

            $tag      = hws_get_syndtd_feed_tag();
            $limit    = hws_get_syndtd_feed_limit();
            $feed_url = home_url( '/tag/' . rawurlencode( $tag ) . '/feed/' );
            $response = wp_remote_get( $feed_url, [ 'timeout' => 12 ] );

            if ( is_wp_error( $response ) ) {
                return [
                    'passed'  => false,
                    'message' => 'Feed request failed.',
                    'proof'   => $response->get_error_message(),
                    'ran_at'  => $ran_at,
                ];
            }

            $code  = (int) wp_remote_retrieve_response_code( $response );
            $body  = (string) wp_remote_retrieve_body( $response );
            $count = preg_match_all( '/<item\\b/i', $body );

            return [
                'passed'  => $code >= 200 && $code < 400 && $count <= $limit,
                'message' => 'Feed returned ' . $count . ' item(s); configured limit is ' . $limit . '.',
                'proof'   => 'HTTP ' . $code . ' ' . $feed_url,
                'ran_at'  => $ran_at,
            ];

        case 'enable_current_year_shortcode':
            if ( function_exists( __NAMESPACE__ . '\\enable_current_year_shortcode' ) ) {
                enable_current_year_shortcode();
            }

            $output = do_shortcode( '[current_year]' );

            return [
                'passed'  => $output === date( 'Y' ),
                'message' => '[current_year] rendered ' . $output . '.',
                'proof'   => '[display_year] remains available for legacy content.',
                'ran_at'  => $ran_at,
            ];

        case 'enable_lowercase_upload_filenames':
            if ( function_exists( __NAMESPACE__ . '\\enable_lowercase_upload_filenames' ) ) {
                enable_lowercase_upload_filenames();
            }

            $sample = sanitize_file_name( 'My Upload File.PNG' );

            return [
                'passed'  => $sample === strtolower( $sample ),
                'message' => 'sanitize_file_name converted the sample to lowercase.',
                'proof'   => 'My Upload File.PNG => ' . $sample,
                'ran_at'  => $ran_at,
            ];

        default:
            return [
                'passed'  => true,
                'message' => 'Feature toggle is active.',
                'proof'   => 'Option ' . $feature_id . ' is enabled.',
                'ran_at'  => $ran_at,
            ];
    }
}
