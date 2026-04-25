<?php namespace hws_base_tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_ajax_hws_footer_text_save_settings', __NAMESPACE__ . '\\ajax_save_footer_text_settings' );

function ajax_save_footer_text_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
        return;
    }

    hws_require_ajax_nonce_or_error();

    $enabled  = ! empty( $_POST['enabled'] );
    $template = isset( $_POST['template'] ) ? sanitize_key( wp_unslash( $_POST['template'] ) ) : 'quiet-inline';
    $templates = function_exists( __NAMESPACE__ . '\\hws_get_footer_text_templates' )
        ? hws_get_footer_text_templates()
        : [];

    if ( ! isset( $templates[ $template ] ) ) {
        $template = 'quiet-inline';
    }

    update_option( 'hws_footer_text_feature_enabled', $enabled ? '1' : '0' );
    update_option( 'hws_footer_text_template', $template );

    wp_send_json_success( [
        'enabled'  => $enabled,
        'template' => $template,
        'label'    => $templates[ $template ]['label'] ?? 'Quiet Inline',
    ] );
}

function display_settings_footer_text() {
    if ( ! function_exists( __NAMESPACE__ . '\\hws_is_footer_text_module_enabled' ) || ! hws_is_footer_text_module_enabled() ) {
        echo '<div class="notice notice-warning"><p>Enable the <strong>Auto Inject Footer Text</strong> snippet first to unlock this module.</p></div>';
        return;
    }

    if ( function_exists( __NAMESPACE__ . '\\output_toggle_switch_styles' ) ) {
        output_toggle_switch_styles();
    }

    $feature_enabled = function_exists( __NAMESPACE__ . '\\hws_is_footer_text_feature_enabled' ) && hws_is_footer_text_feature_enabled();
    $templates       = function_exists( __NAMESPACE__ . '\\hws_get_footer_text_templates' )
        ? hws_get_footer_text_templates()
        : [];
    $active_template = function_exists( __NAMESPACE__ . '\\hws_get_footer_text_template' )
        ? hws_get_footer_text_template()
        : 'quiet-inline';
    $footer_markup   = function_exists( __NAMESPACE__ . '\\hws_get_footer_text_markup' )
        ? hws_get_footer_text_markup()
        : '';
    $website_settings_url = admin_url( 'admin.php?page=website-settings' );
    ?>
    <style>
        .hws-footer-text-hero {
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1px solid #d7dee7;
            border-radius: 10px;
            padding: 20px 24px;
            margin-bottom: 22px;
        }

        .hws-footer-text-hero h3 {
            margin: 0 0 8px;
            font-size: 18px;
            color: #1d2327;
        }

        .hws-footer-text-hero p {
            margin: 0;
            color: #50575e;
            font-size: 14px;
            line-height: 1.6;
        }

        .hws-footer-text-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
            margin-bottom: 22px;
        }

        .hws-footer-text-card {
            background: #fff;
            border: 1px solid #dcdcde;
            border-radius: 10px;
            padding: 16px;
        }

        .hws-footer-text-card .value {
            font-size: 20px;
            font-weight: 600;
            color: #1d2327;
        }

        .hws-footer-text-card .label {
            margin-top: 4px;
            color: #646970;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .hws-footer-text-panel {
            background: #fff;
            border: 1px solid #dcdcde;
            border-radius: 10px;
            margin-bottom: 22px;
            overflow: hidden;
        }

        .hws-footer-text-panel-header {
            background: #f6f7f7;
            border-bottom: 1px solid #dcdcde;
            padding: 14px 18px;
            font-weight: 600;
            color: #1d2327;
        }

        .hws-footer-text-panel-body {
            padding: 18px;
        }

        .hws-footer-text-toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .hws-footer-text-toggle-copy h4 {
            margin: 0 0 4px;
            font-size: 15px;
            color: #1d2327;
        }

        .hws-footer-text-toggle-copy p {
            margin: 0;
            color: #646970;
            font-size: 13px;
            line-height: 1.6;
        }

        .hws-footer-text-status {
            display: inline-block;
            margin-top: 10px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }

        .hws-footer-text-status.on {
            background: #edfaef;
            color: #116329;
        }

        .hws-footer-text-status.off {
            background: #f6f7f7;
            color: #646970;
        }

        .hws-footer-text-template-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
        }

        .hws-footer-template-option {
            position: relative;
        }

        .hws-footer-template-option input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .hws-footer-template-card {
            border: 1px solid #dcdcde;
            border-radius: 10px;
            background: #fff;
            padding: 14px;
            cursor: pointer;
            transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
            min-height: 210px;
        }

        .hws-footer-template-option input:checked + .hws-footer-template-card {
            border-color: #2271b1;
            box-shadow: 0 0 0 1px #2271b1;
            transform: translateY(-1px);
        }

        .hws-footer-template-card h4 {
            margin: 0 0 6px;
            color: #1d2327;
            font-size: 15px;
        }

        .hws-footer-template-card p {
            margin: 0 0 14px;
            color: #646970;
            font-size: 13px;
            line-height: 1.55;
        }

        .hws-footer-template-preview {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fbfbfc;
            padding: 14px;
            color: #4b5563;
            font-size: 12.5px;
            line-height: 1.65;
        }

        .hws-footer-template-preview p {
            margin: 0;
            color: inherit;
            font-size: inherit;
            line-height: inherit;
        }

        .hws-footer-template-preview.divider {
            border-top: 2px solid #d7dee7;
            padding-top: 16px;
        }

        .hws-footer-template-preview.fine-print {
            text-align: center;
            font-size: 11.5px;
            color: #6b7280;
        }

        .hws-footer-text-preview-live {
            border: 1px solid #dcdcde;
            border-radius: 10px;
            background: #fff;
            padding: 18px;
            color: #1d2327;
        }

        .hws-footer-text-preview-live-empty {
            color: #646970;
            font-size: 13px;
            line-height: 1.6;
        }

        .hws-footer-text-saving {
            margin-top: 12px;
            font-size: 13px;
            color: #2271b1;
            min-height: 18px;
        }
    </style>

    <div class="hws-footer-text-hero">
        <h3>Footer Text Module</h3>
        <p>The snippet toggle only unlocks this module. Use the controls below to decide whether the footer text actually renders on the site and which minimalist template it should use.</p>
    </div>

    <div class="hws-footer-text-grid">
        <div class="hws-footer-text-card">
            <div class="value"><?php echo $feature_enabled ? 'On' : 'Off'; ?></div>
            <div class="label">Output Status</div>
        </div>
        <div class="hws-footer-text-card">
            <div class="value"><?php echo esc_html( $templates[ $active_template ]['label'] ?? 'Quiet Inline' ); ?></div>
            <div class="label">Selected Template</div>
        </div>
        <div class="hws-footer-text-card">
            <div class="value"><?php echo $footer_markup ? 'Ready' : 'Empty'; ?></div>
            <div class="label">Footer Content</div>
        </div>
    </div>

    <div class="hws-footer-text-panel">
        <div class="hws-footer-text-panel-header">Output Control</div>
        <div class="hws-footer-text-panel-body">
            <div class="hws-footer-text-toggle-row">
                <div class="hws-footer-text-toggle-copy">
                    <h4>Render Footer Text On The Frontend</h4>
                    <p>Leave the module enabled in Snippets, but control the real frontend output here.</p>
                    <span class="hws-footer-text-status <?php echo $feature_enabled ? 'on' : 'off'; ?>" id="hws-footer-text-status"><?php echo $feature_enabled ? 'Frontend output enabled' : 'Frontend output disabled'; ?></span>
                </div>
                <?php echo render_toggle_switch( 'hws-footer-text-feature-enabled', '', $feature_enabled ); ?>
            </div>
        </div>
    </div>

    <div class="hws-footer-text-panel">
        <div class="hws-footer-text-panel-header">Template</div>
        <div class="hws-footer-text-panel-body">
            <div class="hws-footer-text-template-grid">
                <?php foreach ( $templates as $template_key => $template ) : ?>
                    <label class="hws-footer-template-option">
                        <input type="radio" name="hws-footer-text-template" value="<?php echo esc_attr( $template_key ); ?>" <?php checked( $active_template, $template_key ); ?>>
                        <div class="hws-footer-template-card">
                            <h4><?php echo esc_html( $template['label'] ); ?></h4>
                            <p><?php echo esc_html( $template['description'] ); ?></p>
                            <div class="hws-footer-template-preview <?php echo esc_attr( $template_key === 'fine-divider' ? 'divider' : ( $template_key === 'fine-print' ? 'fine-print' : '' ) ); ?>">
                                <p>Copyright <?php echo esc_html( gmdate( 'Y' ) ); ?> Zach Eikenberry. All rights reserved.</p>
                            </div>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="hws-footer-text-saving" id="hws-footer-text-saving"></div>
        </div>
    </div>

    <div class="hws-footer-text-panel">
        <div class="hws-footer-text-panel-header">Current Footer Text Content</div>
        <div class="hws-footer-text-panel-body">
            <div class="hws-footer-text-preview-live">
                <?php if ( $footer_markup ) : ?>
                    <?php echo $footer_markup; ?>
                <?php else : ?>
                    <div class="hws-footer-text-preview-live-empty">
                        No footer text is currently set. Add content on <a href="<?php echo esc_url( $website_settings_url ); ?>" target="_blank">Website Settings</a> and then enable output here.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    jQuery(function($) {
        var $toggle = $('#hws-footer-text-feature-enabled');
        var $templateInputs = $('input[name="hws-footer-text-template"]');
        var $status = $('#hws-footer-text-status');
        var $saving = $('#hws-footer-text-saving');

        function setBusy(isBusy) {
            $toggle.prop('disabled', isBusy);
            $templateInputs.prop('disabled', isBusy);
        }

        function saveFooterTextSettings() {
            var enabled = $toggle.is(':checked') ? 1 : 0;
            var template = $templateInputs.filter(':checked').val() || 'quiet-inline';

            setBusy(true);
            $saving.text('Saving…');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'hws_footer_text_save_settings',
                    nonce: hwsNonce,
                    enabled: enabled,
                    template: template
                }
            }).done(function(response) {
                if (!response || !response.success) {
                    throw response;
                }

                if (enabled) {
                    $status.removeClass('off').addClass('on').text('Frontend output enabled');
                } else {
                    $status.removeClass('on').addClass('off').text('Frontend output disabled');
                }

                $saving.text('Saved.');
                window.setTimeout(function() {
                    $saving.text('');
                }, 1200);
            }).fail(function(response) {
                console.error('Footer text settings save failed', response);
                $saving.text('Save failed. Refresh and try again.');
            }).always(function() {
                setBusy(false);
            });
        }

        $toggle.on('change', saveFooterTextSettings);
        $templateInputs.on('change', saveFooterTextSettings);
    });
    </script>
    <?php
}
