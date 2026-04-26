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

    $template_meta_for_js = [];
    foreach ( $templates as $key => $template ) {
        $template_meta_for_js[ $key ] = [
            'label'       => $template['label'] ?? '',
            'description' => $template['description'] ?? '',
        ];
    }
    ?>
    <style>
        .hws-ft-wrap {
            max-width: 1200px;
            margin: 18px 0 32px;
            color: #1d2327;
        }

        .hws-ft-card {
            background: #fff;
            border: 1px solid #dcdcde;
            border-radius: 12px;
            padding: 20px 22px;
            margin-bottom: 18px;
            box-shadow: 0 1px 0 rgba(0, 0, 0, 0.02);
        }

        .hws-ft-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            flex-wrap: wrap;
        }

        .hws-ft-header-text { min-width: 0; }

        .hws-ft-header h2 {
            margin: 0 0 4px;
            font-size: 18px;
            line-height: 1.3;
            color: #1d2327;
        }

        .hws-ft-tagline {
            margin: 0;
            color: #50575e;
            font-size: 13px;
            line-height: 1.55;
        }

        .hws-ft-header-toggle {
            display: flex;
            align-items: center;
            gap: 12px;
            white-space: nowrap;
        }

        .hws-ft-status-text {
            font-size: 13px;
            font-weight: 500;
        }

        .hws-ft-status-text.on  { color: #116329; }
        .hws-ft-status-text.off { color: #646970; }

        .hws-ft-section-title {
            margin: 0 0 4px;
            font-size: 12px;
            font-weight: 600;
            color: #1d2327;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .hws-ft-section-help {
            margin: 0 0 14px;
            color: #50575e;
            font-size: 13px;
            line-height: 1.55;
        }

        .hws-ft-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 0 0 10px;
        }

        .hws-ft-pill { position: relative; }

        .hws-ft-pill input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .hws-ft-pill-label {
            display: inline-block;
            padding: 8px 16px;
            border: 1px solid #c3c4c7;
            border-radius: 999px;
            background: #f6f7f7;
            color: #50575e;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color .18s ease, color .18s ease, border-color .18s ease, box-shadow .18s ease;
        }

        .hws-ft-pill-label:hover { background: #eef0f1; color: #1d2327; }

        .hws-ft-pill input:checked + .hws-ft-pill-label {
            background: #2271b1;
            border-color: #2271b1;
            color: #fff;
            box-shadow: 0 0 0 1px #2271b1;
        }

        .hws-ft-pill input:focus-visible + .hws-ft-pill-label {
            outline: 2px solid #2271b1;
            outline-offset: 2px;
        }

        .hws-ft-pill-help {
            margin: 0;
            color: #50575e;
            font-size: 12.5px;
            min-height: 18px;
        }

        .hws-ft-edit-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 22px;
        }

        @media (min-width: 960px) {
            .hws-ft-edit-grid {
                grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr);
            }
        }

        .hws-ft-col-title {
            margin: 0 0 8px;
            font-size: 12px;
            font-weight: 600;
            color: #646970;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .hws-ft-editor-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 12px;
            flex-wrap: wrap;
        }

        .hws-ft-saving {
            font-size: 12.5px;
            color: #2271b1;
            min-height: 18px;
        }

        .hws-ft-saving.error { color: #b32d2e; }

        .hws-ft-shortcode {
            margin-top: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            font-size: 12.5px;
            color: #646970;
        }

        .hws-ft-shortcode code {
            background: #f6f7f7;
            border: 1px solid #dcdcde;
            border-radius: 6px;
            padding: 3px 8px;
            font-size: 12px;
            color: #1d2327;
        }

        .hws-ft-copy.button {
            font-size: 12px;
            padding: 0 10px;
            line-height: 24px;
            height: 26px;
            min-height: 26px;
        }

        .hws-ft-copy.button.copied {
            color: #116329;
            border-color: #116329;
        }

        .hws-ft-preview {
            border: 1px solid #dcdcde;
            border-radius: 10px;
            background: #fff;
            padding: 18px 20px;
            min-height: 140px;
            color: #1d2327;
            font-size: 14px;
            line-height: 1.65;
        }

        .hws-ft-preview.hws-footer-text--fine-divider {
            border-top: 2px solid #d7dee7;
        }

        .hws-ft-preview.hws-footer-text--fine-print {
            text-align: center;
            font-size: 12.5px;
            color: #6b7280;
            line-height: 1.75;
        }

        .hws-ft-preview p { margin: 0 0 0.6em; }
        .hws-ft-preview p:last-child { margin-bottom: 0; }

        .hws-ft-preview a {
            color: #2271b1;
            text-decoration: underline;
            text-underline-offset: 0.12em;
        }

        .hws-ft-preview-empty {
            color: #8a8f94;
            font-size: 13px;
            font-style: italic;
            line-height: 1.6;
        }

        @media (max-width: 600px) {
            .hws-ft-card { padding: 16px; }
            .hws-ft-header { gap: 14px; }
            .hws-ft-header-toggle { width: 100%; justify-content: space-between; }
        }
    </style>

    <div class="hws-ft-wrap">

        <div class="hws-ft-card hws-ft-header">
            <div class="hws-ft-header-text">
                <h2>Footer Text</h2>
                <p class="hws-ft-tagline">Edit the footer text, choose a style, and toggle whether it shows on the live site.</p>
            </div>
            <div class="hws-ft-header-toggle">
                <span class="hws-ft-status-text <?php echo $feature_enabled ? 'on' : 'off'; ?>" id="hws-footer-text-status">
                    <?php echo $feature_enabled ? 'Visible on the live site' : 'Hidden on the live site'; ?>
                </span>
                <?php echo render_toggle_switch( 'hws-footer-text-feature-enabled', '', $feature_enabled ); ?>
            </div>
        </div>

        <div class="hws-ft-card">
            <h3 class="hws-ft-section-title">Style</h3>
            <p class="hws-ft-section-help">Pick how the footer text is presented. The live preview below updates immediately.</p>
            <div class="hws-ft-pills" role="radiogroup" aria-label="Footer text style">
                <?php foreach ( $templates as $template_key => $template ) : ?>
                    <label class="hws-ft-pill">
                        <input type="radio" name="hws-footer-text-template" value="<?php echo esc_attr( $template_key ); ?>" <?php checked( $active_template, $template_key ); ?>>
                        <span class="hws-ft-pill-label"><?php echo esc_html( $template['label'] ); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="hws-ft-pill-help" id="hws-footer-text-style-help">
                <?php echo esc_html( $templates[ $active_template ]['description'] ?? '' ); ?>
            </p>
        </div>

        <div class="hws-ft-card">
            <div class="hws-ft-edit-grid">
                <div>
                    <h3 class="hws-ft-col-title">Editor</h3>
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
                    <div class="hws-ft-editor-actions">
                        <button type="button" class="button button-primary" id="hws-footer-text-save-content">Save Footer Text</button>
                        <span class="hws-ft-saving" id="hws-footer-text-saving" aria-live="polite"></span>
                    </div>
                    <div class="hws-ft-shortcode">
                        <span>Shortcode:</span>
                        <code id="hws-footer-text-shortcode"><?php echo esc_html( $shortcode ); ?></code>
                        <button type="button" class="button hws-ft-copy" id="hws-footer-text-copy" data-default="Copy" data-copied="Copied">Copy</button>
                    </div>
                </div>

                <div>
                    <h3 class="hws-ft-col-title">Live Preview</h3>
                    <div class="hws-ft-preview hws-footer-text--<?php echo esc_attr( $active_template ); ?>" id="hws-footer-text-preview-live" aria-live="polite">
                        <?php if ( $footer_markup ) : ?>
                            <?php echo $footer_markup; ?>
                        <?php else : ?>
                            <div class="hws-ft-preview-empty">No footer text yet. Type on the left and the preview will update here.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    jQuery(function($) {
        var $toggle         = $('#hws-footer-text-feature-enabled');
        var $templateInputs = $('input[name="hws-footer-text-template"]');
        var $status         = $('#hws-footer-text-status');
        var $saving         = $('#hws-footer-text-saving');
        var $preview        = $('#hws-footer-text-preview-live');
        var $saveContent    = $('#hws-footer-text-save-content');
        var $styleHelp      = $('#hws-footer-text-style-help');
        var $copyBtn        = $('#hws-footer-text-copy');
        var templateMeta    = <?php echo wp_json_encode( $template_meta_for_js ); ?>;

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
            var text = $('<div>').html(html || '').text().replace(/ /g, ' ').trim();
            return text.length > 0;
        }

        function applyPreviewTemplate() {
            var template = getSelectedTemplate();
            $preview.removeClass('hws-footer-text--quiet-inline hws-footer-text--fine-divider hws-footer-text--fine-print')
                .addClass('hws-footer-text--' + template);
            if (templateMeta && templateMeta[template]) {
                $styleHelp.text(templateMeta[template].description || '');
            }
        }

        function refreshPreview(html, hasContent) {
            applyPreviewTemplate();
            if (hasContent && html) {
                $preview.html(html);
                return;
            }
            $preview.html('<div class="hws-ft-preview-empty">No footer text yet. Type on the left and the preview will update here.</div>');
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
            if (editor._hwsPreviewBound) return;
            editor._hwsPreviewBound = true;
            editor.on('keyup change input SetContent Paste Undo Redo', function() {
                updatePreviewFromEditor();
            });
        }

        function showSaving(msg, isError) {
            $saving.removeClass('error');
            if (isError) $saving.addClass('error');
            $saving.text(msg || '');
        }

        function saveFooterTextSettings(includeContent) {
            var enabled  = $toggle.is(':checked') ? 1 : 0;
            var template = getSelectedTemplate();
            var payload  = {
                action: 'hws_footer_text_save_settings',
                nonce: hwsNonce,
                enabled: enabled,
                template: template
            };

            if (includeContent) payload.content = getEditorContent();

            setBusy(true);
            showSaving(includeContent ? 'Saving footer text…' : 'Saving…', false);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: payload
            }).done(function(response) {
                if (!response || !response.success) { throw response; }

                if (enabled) {
                    $status.removeClass('off').addClass('on').text('Visible on the live site');
                } else {
                    $status.removeClass('on').addClass('off').text('Hidden on the live site');
                }

                if (includeContent) {
                    refreshPreview(response.data.rendered_html, response.data.has_content);
                }

                showSaving(includeContent ? 'Footer text saved.' : 'Saved.', false);
                window.setTimeout(function() { showSaving('', false); }, 1500);
            }).fail(function(response) {
                console.error('Footer text settings save failed', response);
                showSaving('Save failed. Refresh and try again.', true);
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

        $copyBtn.on('click', function() {
            var btn = $(this);
            var text = $('#hws-footer-text-shortcode').text();
            var done = function(ok) {
                if (!ok) { btn.text('Copy failed'); return; }
                btn.addClass('copied').text(btn.data('copied') || 'Copied');
                window.setTimeout(function() {
                    btn.removeClass('copied').text(btn.data('default') || 'Copy');
                }, 1200);
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function() { done(true); }, function() { done(false); });
                return;
            }

            try {
                var temp = document.createElement('textarea');
                temp.value = text;
                document.body.appendChild(temp);
                temp.select();
                var ok = document.execCommand('copy');
                document.body.removeChild(temp);
                done(ok);
            } catch (err) {
                done(false);
            }
        });

        applyPreviewTemplate();
        updatePreviewFromEditor();
        bindTinyMcePreview();
    });
    </script>
    <?php
}
