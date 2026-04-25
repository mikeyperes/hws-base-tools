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
    $content  = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : null;
    $templates = function_exists( __NAMESPACE__ . '\\hws_get_footer_text_templates' )
        ? hws_get_footer_text_templates()
        : [];

    if ( ! isset( $templates[ $template ] ) ) {
        $template = 'quiet-inline';
    }

    update_option( 'hws_footer_text_feature_enabled', $enabled ? '1' : '0' );
    update_option( 'hws_footer_text_template', $template );

    if ( null !== $content && function_exists( __NAMESPACE__ . '\\hws_save_footer_text_raw' ) ) {
        hws_save_footer_text_raw( $content );
    }

    $rendered_html = function_exists( __NAMESPACE__ . '\\hws_get_footer_text_markup' )
        ? hws_get_footer_text_markup()
        : '';

    wp_send_json_success( [
        'enabled'       => $enabled,
        'template'      => $template,
        'label'         => $templates[ $template ]['label'] ?? 'Quiet Inline',
        'rendered_html' => $rendered_html,
        'has_content'   => '' !== trim( wp_strip_all_tags( $rendered_html ) ),
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
    $footer_text_raw = function_exists( __NAMESPACE__ . '\\hws_get_footer_text_raw' )
        ? hws_get_footer_text_raw()
        : '';
    $footer_markup   = function_exists( __NAMESPACE__ . '\\hws_get_footer_text_markup' )
        ? hws_get_footer_text_markup()
        : '';
    $shortcode = '[website_content field="website_footer_text"]';
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

        .hws-footer-text-editor-wrap {
            margin-top: 14px;
        }

        .hws-footer-text-editor-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 14px;
        }

        .hws-footer-text-inline-preview {
            margin-top: 18px;
            border-top: 1px solid #e5e7eb;
            padding-top: 18px;
        }

        .hws-footer-text-inline-preview h4 {
            margin: 0 0 10px;
            font-size: 14px;
            color: #1d2327;
        }

        .hws-footer-text-editor-note {
            margin: 0 0 14px;
            color: #50575e;
            font-size: 13px;
            line-height: 1.6;
        }

        .hws-footer-text-editor-note code {
            background: #fff;
            border: 1px solid #dcdcde;
            border-radius: 6px;
            padding: 2px 6px;
            font-size: 12px;
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

        .hws-footer-text-preview-live.hws-footer-text--fine-divider {
            border-top: 2px solid #d7dee7;
            padding-top: 20px;
        }

        .hws-footer-text-preview-live.hws-footer-text--fine-print {
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            line-height: 1.75;
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
        <h3>Footer Text</h3>
        <p>Write the footer text here, decide if it should be visible on the live site, and choose how quietly it should look in the footer.</p>
    </div>

    <div class="hws-footer-text-grid">
        <div class="hws-footer-text-card">
            <div class="value"><?php echo $feature_enabled ? 'On' : 'Off'; ?></div>
            <div class="label">Live Website</div>
        </div>
        <div class="hws-footer-text-card">
            <div class="value"><?php echo esc_html( $templates[ $active_template ]['label'] ?? 'Quiet Inline' ); ?></div>
            <div class="label">Current Style</div>
        </div>
        <div class="hws-footer-text-card">
            <div class="value"><?php echo $footer_markup ? 'Ready' : 'Empty'; ?></div>
            <div class="label">Saved Text</div>
        </div>
    </div>

    <div class="hws-footer-text-panel">
        <div class="hws-footer-text-panel-header">Show On Website</div>
        <div class="hws-footer-text-panel-body">
            <div class="hws-footer-text-toggle-row">
                <div class="hws-footer-text-toggle-copy">
                    <h4>Show This Footer Text On The Live Site</h4>
                    <p>Turn this on to display the saved footer text in the website footer. Turn it off to keep the text saved but hidden.</p>
                    <span class="hws-footer-text-status <?php echo $feature_enabled ? 'on' : 'off'; ?>" id="hws-footer-text-status"><?php echo $feature_enabled ? 'Visible on the live site' : 'Hidden on the live site'; ?></span>
                </div>
                <?php echo render_toggle_switch( 'hws-footer-text-feature-enabled', '', $feature_enabled ); ?>
            </div>
        </div>
    </div>

    <div class="hws-footer-text-panel">
        <div class="hws-footer-text-panel-header">Footer Text Content</div>
        <div class="hws-footer-text-panel-body">
            <p class="hws-footer-text-editor-note">
                Edit the actual footer text here.
                Shortcode: <code><?php echo esc_html( $shortcode ); ?></code>
            </p>
            <div class="hws-footer-text-editor-wrap">
                <?php
                wp_editor(
                    $footer_text_raw,
                    'hws_footer_text_editor',
                    [
                        'textarea_name' => 'hws_footer_text_editor',
                        'textarea_rows' => 10,
                        'media_buttons' => true,
                        'teeny'         => false,
                        'quicktags'     => true,
                    ]
                );
                ?>
            </div>
            <div class="hws-footer-text-editor-actions">
                <button type="button" class="button button-primary" id="hws-footer-text-save-content">Save Footer Text</button>
                <span style="color:#646970; font-size:12px;">This saves the same content used by the shortcode and the auto-injected footer.</span>
            </div>
            <div class="hws-footer-text-inline-preview">
                <h4>Live Preview</h4>
                <div class="hws-footer-text-preview-live hws-footer-text--<?php echo esc_attr( $active_template ); ?>" id="hws-footer-text-preview-live">
                    <?php if ( $footer_markup ) : ?>
                        <?php echo $footer_markup; ?>
                    <?php else : ?>
                        <div class="hws-footer-text-preview-live-empty">
                            No footer text is currently set yet. Write it above and save it here.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="hws-footer-text-panel">
        <div class="hws-footer-text-panel-header">Choose A Style</div>
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

    <script>
    jQuery(function($) {
        var $toggle = $('#hws-footer-text-feature-enabled');
        var $templateInputs = $('input[name="hws-footer-text-template"]');
        var $status = $('#hws-footer-text-status');
        var $saving = $('#hws-footer-text-saving');
        var $preview = $('#hws-footer-text-preview-live');
        var $saveContent = $('#hws-footer-text-save-content');

        function setBusy(isBusy) {
            $toggle.prop('disabled', isBusy);
            $templateInputs.prop('disabled', isBusy);
            $saveContent.prop('disabled', isBusy);
        }

        function getEditorContent() {
            if (window.tinymce && tinymce.get('hws_footer_text_editor')) {
                return tinymce.get('hws_footer_text_editor').getContent();
            }

            return $('#hws_footer_text_editor').val() || '';
        }

        function getSelectedTemplate() {
            return $templateInputs.filter(':checked').val() || 'quiet-inline';
        }

        function hasMeaningfulContent(html) {
            var text = $('<div>').html(html || '').text().replace(/\u00a0/g, ' ').trim();
            return text.length > 0;
        }

        function applyPreviewTemplate() {
            var template = getSelectedTemplate();
            $preview.removeClass('hws-footer-text--quiet-inline hws-footer-text--fine-divider hws-footer-text--fine-print')
                .addClass('hws-footer-text--' + template);
        }

        function refreshPreview(html, hasContent) {
            applyPreviewTemplate();

            if (hasContent && html) {
                $preview.html(html);
                return;
            }

            $preview.html('<div class="hws-footer-text-preview-live-empty">No footer text is currently set yet. Write it above and save it here.</div>');
        }

        function updatePreviewFromEditor() {
            var html = getEditorContent();
            refreshPreview(html, hasMeaningfulContent(html));
        }

        function bindTinyMcePreview() {
            if (!(window.tinymce && tinymce.get('hws_footer_text_editor'))) {
                window.setTimeout(bindTinyMcePreview, 300);
                return;
            }

            var editor = tinymce.get('hws_footer_text_editor');

            if (editor._hwsPreviewBound) {
                return;
            }

            editor._hwsPreviewBound = true;
            editor.on('keyup change input SetContent Paste Undo Redo', function() {
                updatePreviewFromEditor();
            });
        }

        function saveFooterTextSettings(includeContent) {
            var enabled = $toggle.is(':checked') ? 1 : 0;
            var template = $templateInputs.filter(':checked').val() || 'quiet-inline';
            var payload = {
                action: 'hws_footer_text_save_settings',
                nonce: hwsNonce,
                enabled: enabled,
                template: template
            };

            if (includeContent) {
                payload.content = getEditorContent();
            }

            setBusy(true);
            $saving.text(includeContent ? 'Saving footer text…' : 'Saving…');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: payload
            }).done(function(response) {
                if (!response || !response.success) {
                    throw response;
                }

                if (enabled) {
                    $status.removeClass('off').addClass('on').text('Visible on the live site');
                } else {
                    $status.removeClass('on').addClass('off').text('Hidden on the live site');
                }

                if (includeContent) {
                    refreshPreview(response.data.rendered_html, response.data.has_content);
                }

                $saving.text(includeContent ? 'Footer text saved.' : 'Saved.');
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

        $toggle.on('change', function() { saveFooterTextSettings(false); });
        $templateInputs.on('change', function() {
            applyPreviewTemplate();
            saveFooterTextSettings(false);
        });
        $saveContent.on('click', function() { saveFooterTextSettings(true); });
        $('#hws_footer_text_editor').on('input keyup change', function() {
            updatePreviewFromEditor();
        });

        applyPreviewTemplate();
        updatePreviewFromEditor();
        bindTinyMcePreview();
    });
    </script>
    <?php
}
