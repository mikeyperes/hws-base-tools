<?php namespace hws_base_tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_ajax_hws_footer_text_save_settings', __NAMESPACE__ . '\\ajax_save_footer_text_settings' );
add_action( 'wp_ajax_hws_footer_text_save_targeted_injection', __NAMESPACE__ . '\\ajax_save_footer_text_targeted_injection' );
add_action( 'acf/save_post', __NAMESPACE__ . '\\maybe_purge_footer_text_cache_after_acf_save', 20 );
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\hws_footer_text_enqueue_editor_assets' );

function hws_footer_text_enqueue_editor_assets(): void {
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    $tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

    if ( 'hws-core-tools' !== $page || 'footer-text' !== $tab ) {
        return;
    }

    wp_enqueue_media();

    if ( function_exists( 'wp_enqueue_editor' ) ) {
        wp_enqueue_editor();
    }

    wp_enqueue_script( 'editor' );
    wp_enqueue_script( 'quicktags' );
}

function hws_purge_footer_text_cache(): void {
    if ( function_exists( 'wp_cache_flush' ) ) {
        @wp_cache_flush();
    }

    if ( function_exists( 'wp_cache_clear_cache' ) ) {
        @wp_cache_clear_cache();
    }

    if ( function_exists( 'w3tc_flush_all' ) ) {
        @w3tc_flush_all();
    }

    if ( function_exists( 'rocket_clean_domain' ) ) {
        @rocket_clean_domain();
    }

    if ( function_exists( 'rocket_clean_minify' ) ) {
        @rocket_clean_minify();
    }

    if ( function_exists( 'do_action' ) ) {
        @do_action( 'litespeed_purge_all' );
        @do_action( 'hws_base_tools_purge_all' );
    }
}

function maybe_purge_footer_text_cache_after_acf_save( $post_id ): void {
    if ( ! is_admin() ) {
        return;
    }

    if ( 'option' !== $post_id && 'options' !== $post_id ) {
        return;
    }

    $page = isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : '';
    $tab  = isset( $_REQUEST['tab'] ) ? sanitize_key( wp_unslash( $_REQUEST['tab'] ) ) : '';

    if ( 'website-settings' !== $page && ! ( 'hws-core-tools' === $page && 'website-types' === $tab ) ) {
        return;
    }

    hws_purge_footer_text_cache();
}

function ajax_save_footer_text_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
        return;
    }

    hws_require_ajax_nonce_or_error();

    $enabled   = ! empty( $_POST['enabled'] );
    $template  = isset( $_POST['template'] ) ? sanitize_key( wp_unslash( $_POST['template'] ) ) : 'whisper';
    $alignment = isset( $_POST['alignment'] ) ? sanitize_key( wp_unslash( $_POST['alignment'] ) ) : 'center';
    $content   = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : null;
    $templates = function_exists( __NAMESPACE__ . '\\hws_get_footer_text_templates' )
        ? hws_get_footer_text_templates()
        : [];
    $alignments = function_exists( __NAMESPACE__ . '\\hws_get_footer_text_alignments' )
        ? hws_get_footer_text_alignments()
        : [ 'left' => 'Left', 'center' => 'Center', 'right' => 'Right' ];

    // Migrate legacy keys before validating.
    if ( function_exists( __NAMESPACE__ . '\\hws_footer_text_legacy_template_map' ) ) {
        $legacy = hws_footer_text_legacy_template_map();
        if ( isset( $legacy[ $template ] ) ) {
            $template = $legacy[ $template ];
        }
    }

    if ( ! isset( $templates[ $template ] ) ) {
        $template = 'whisper';
    }

    if ( ! isset( $alignments[ $alignment ] ) ) {
        $alignment = 'center';
    }

    update_option( 'hws_footer_text_feature_enabled', $enabled ? '1' : '0' );
    update_option( 'hws_footer_text_template', $template );
    update_option( 'hws_footer_text_alignment', $alignment );

    if ( null !== $content && function_exists( __NAMESPACE__ . '\\hws_save_footer_text_raw' ) ) {
        hws_save_footer_text_raw( $content );
    }

    hws_purge_footer_text_cache();

    $rendered_html = function_exists( __NAMESPACE__ . '\\hws_get_footer_text_markup' )
        ? hws_get_footer_text_markup()
        : '';

    wp_send_json_success( [
        'enabled'       => $enabled,
        'template'      => $template,
        'alignment'     => $alignment,
        'label'         => $templates[ $template ]['label'] ?? 'Whisper',
        'rendered_html' => $rendered_html,
        'has_content'   => '' !== trim( wp_strip_all_tags( $rendered_html ) ),
    ] );
}

function ajax_save_footer_text_targeted_injection() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
        return;
    }

    hws_require_ajax_nonce_or_error();

    $enabled   = ! empty( $_POST['enabled'] );
    $selector  = isset( $_POST['selector'] ) ? trim( wp_strip_all_tags( wp_unslash( $_POST['selector'] ) ) ) : '';
    if ( function_exists( __NAMESPACE__ . '\\hws_normalize_footer_text_targeted_selector' ) ) {
        $selector = hws_normalize_footer_text_targeted_selector( $selector );
    }
    $placement = isset( $_POST['placement'] ) ? sanitize_key( wp_unslash( $_POST['placement'] ) ) : 'within';
    $allowed_html = function_exists( __NAMESPACE__ . '\\hws_get_footer_text_inline_allowed_html' )
        ? hws_get_footer_text_inline_allowed_html()
        : [
            'a'      => [
                'href'       => true,
                'title'      => true,
                'target'     => true,
                'rel'        => true,
                'class'      => true,
                'aria-label' => true,
            ],
            'span'   => [
                'class'      => true,
                'title'      => true,
                'aria-label' => true,
            ],
            'strong' => [],
            'b'      => [],
            'em'     => [],
            'i'      => [],
            'small'  => [],
            'br'     => [],
            'sup'    => [],
            'sub'    => [],
        ];
    $content   = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : null;
    $valid     = function_exists( __NAMESPACE__ . '\\hws_get_footer_text_targeted_placements' )
        ? hws_get_footer_text_targeted_placements()
        : [ 'before' => 'Before target', 'after' => 'After target', 'within' => 'Within target' ];

    if ( ! isset( $valid[ $placement ] ) ) {
        $placement = 'within';
    }

    update_option( 'hws_footer_text_targeted_enabled', $enabled ? '1' : '0' );
    update_option( 'hws_footer_text_targeted_selector', $selector );
    update_option( 'hws_footer_text_targeted_placement', $placement );

    if ( null !== $content && function_exists( __NAMESPACE__ . '\\hws_save_footer_text_raw' ) ) {
        hws_save_footer_text_raw( $content );
    }

    hws_purge_footer_text_cache();

    $markup = function_exists( __NAMESPACE__ . '\\hws_get_footer_text_targeted_markup' )
        ? hws_get_footer_text_targeted_markup()
        : '';

    wp_send_json_success( [
        'enabled'     => $enabled,
        'selector'    => $selector,
        'placement'   => $placement,
        'content'     => function_exists( __NAMESPACE__ . '\\hws_get_footer_text_raw' ) ? hws_get_footer_text_raw() : '',
        'markup'      => $markup,
        'has_content' => '' !== trim( wp_strip_all_tags( $markup ) ),
    ] );
}

function display_settings_footer_text() {
    if ( ! function_exists( __NAMESPACE__ . '\\hws_is_footer_text_module_enabled' ) || ! hws_is_footer_text_module_enabled() ) {
        echo '<div class="notice notice-warning"><p>Enable the <strong>Footer Text Module</strong> snippet first to unlock this module.</p></div>';
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
        : 'whisper';
    $active_alignment = function_exists( __NAMESPACE__ . '\\hws_get_footer_text_alignment' )
        ? hws_get_footer_text_alignment()
        : 'center';
    $alignments = function_exists( __NAMESPACE__ . '\\hws_get_footer_text_alignments' )
        ? hws_get_footer_text_alignments()
        : [ 'left' => 'Left', 'center' => 'Center', 'right' => 'Right' ];
    $footer_text_raw = function_exists( __NAMESPACE__ . '\\hws_get_footer_text_raw' )
        ? hws_get_footer_text_raw()
        : '';
    $footer_markup   = function_exists( __NAMESPACE__ . '\\hws_get_footer_text_markup' )
        ? hws_get_footer_text_markup()
        : '';
    $shortcode = '[website_content field="website_footer_text"]';
    $targeted_enabled = function_exists( __NAMESPACE__ . '\\hws_is_footer_text_targeted_injection_enabled' ) && hws_is_footer_text_targeted_injection_enabled();
    $targeted_selector = function_exists( __NAMESPACE__ . '\\hws_get_footer_text_targeted_selector' )
        ? hws_get_footer_text_targeted_selector()
        : '';
    $targeted_placement = function_exists( __NAMESPACE__ . '\\hws_get_footer_text_targeted_placement' )
        ? hws_get_footer_text_targeted_placement()
        : 'within';
    $targeted_placements = function_exists( __NAMESPACE__ . '\\hws_get_footer_text_targeted_placements' )
        ? hws_get_footer_text_targeted_placements()
        : [ 'before' => 'Before target', 'after' => 'After target', 'within' => 'Within target' ];
    $targeted_content = function_exists( __NAMESPACE__ . '\\hws_get_footer_text_targeted_raw' )
        ? hws_get_footer_text_targeted_raw()
        : '';
    $targeted_markup = function_exists( __NAMESPACE__ . '\\hws_get_footer_text_targeted_markup' )
        ? hws_get_footer_text_targeted_markup()
        : '';

    $template_meta_for_js = [];
    foreach ( $templates as $key => $template ) {
        $template_meta_for_js[ $key ] = [
            'label'       => $template['label'] ?? '',
            'description' => $template['description'] ?? '',
            'tier'        => $template['tier'] ?? 'minimal',
        ];
    }
    ?>
    <style>
        .hws-ft-wrap {
            max-width: 1200px;
            margin: 18px 0 32px;
            color: #1d2327;
            display: flex;
            flex-direction: column;
        }

        .hws-ft-overview-card { order: 0; }
        .hws-ft-shared-card { order: 1; }
        .hws-ft-method-one-card { order: 2; }
        .hws-ft-method-two-card { order: 3; }

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

        .hws-ft-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin: 0;
        }

        .hws-ft-item {
            display: grid;
            grid-template-columns: 22px minmax(180px, 1.05fr) minmax(0, 1.7fr);
            align-items: center;
            gap: 20px;
            padding: 15px 18px;
            border: 1px solid #d7dbe0;
            border-radius: 14px;
            background: #fff;
            cursor: pointer;
            box-shadow: 0 1px 0 rgba(15, 23, 42, 0.02);
            transition: background-color .15s ease, border-color .15s ease, box-shadow .15s ease, transform .15s ease;
        }

        .hws-ft-item:hover {
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfd 100%);
            border-color: #c8d0d9;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
        }

        .hws-ft-item:has(input:checked) {
            border-color: #2271b1;
            box-shadow: 0 0 0 1px #2271b1, 0 14px 30px rgba(34, 113, 177, 0.08);
            background: linear-gradient(180deg, #f8fbff 0%, #f2f8ff 100%);
        }

        .hws-ft-item input[type="radio"] {
            margin: 0;
            cursor: pointer;
        }

        .hws-ft-item-body { min-width: 0; }

        .hws-ft-item-name {
            font-weight: 600;
            font-size: 14px;
            color: #1d2327;
            line-height: 1.35;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .hws-ft-item-tier {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 10.5px;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            border: 1px solid currentColor;
            line-height: 1.4;
        }

        .hws-ft-item-tier[data-tier="minimal"] { color: #6b7280; }
        .hws-ft-item-tier[data-tier="light"]   { color: #4b5563; }
        .hws-ft-item-tier[data-tier="medium"]  { color: #2563eb; }
        .hws-ft-item-tier[data-tier="heavy"]   { color: #111827; }

        .hws-ft-item:has(input:checked) .hws-ft-item-name { color: #135e96; }

        .hws-ft-item-desc {
            font-size: 12.5px;
            color: #646970;
            margin-top: 4px;
            line-height: 1.55;
        }

        /* Preview tile container — no padding, no bg; templates own their own surface. */
        .hws-ft-item-mini {
            border: 1px solid #e7eaee;
            border-radius: 12px;
            background: linear-gradient(180deg, #fcfcfd 0%, #f7f8fa 100%);
            color: #1d2327;
            font-size: 13px;
            line-height: 1.6;
            overflow: hidden;
            min-width: 0;
            min-height: 90px;
            box-sizing: border-box;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .hws-ft-item-mini > * { width: 100%; }

        .hws-ft-item-mini p { margin: 0 0 0.4em; }
        .hws-ft-item-mini p:last-child { margin-bottom: 0; }

        .hws-ft-item-mini-empty {
            color: #8a8f94;
            font-style: italic;
            font-size: 12.5px;
            padding: 18px 18px;
        }

        @media (max-width: 720px) {
            .hws-ft-item {
                grid-template-columns: 22px 1fr;
            }
            .hws-ft-item-mini {
                grid-column: 1 / -1;
            }
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

        .hws-ft-wrap .wp-editor-wrap {
            max-width: 100%;
            color: #1d2327;
        }

        .hws-ft-wrap .wp-editor-container {
            border-color: #dcdcde;
            background: #fff;
        }

        .hws-ft-wrap .wp-editor-tabs .wp-switch-editor {
            color: #1d2327;
            background: #f6f7f7;
            border-color: #dcdcde;
            box-shadow: none;
        }

        .hws-ft-wrap .wp-editor-wrap.tmce-active .switch-tmce,
        .hws-ft-wrap .wp-editor-wrap.html-active .switch-html {
            color: #1d2327;
            background: #fff;
            border-bottom-color: #fff;
        }

        .hws-ft-wrap textarea.wp-editor-area,
        .hws-ft-wrap textarea#hws_footer_text_editor {
            display: block;
            width: 100%;
            min-height: 260px;
            color: #1d2327 !important;
            -webkit-text-fill-color: #1d2327;
            background: #fff !important;
            caret-color: #1d2327;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 13px;
            line-height: 1.55;
        }

        .hws-ft-wrap .mce-edit-area,
        .hws-ft-wrap .mce-edit-area iframe {
            background: #fff;
        }

        .hws-ft-target-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 18px;
        }

        @media (min-width: 960px) {
            .hws-ft-target-grid {
                grid-template-columns: minmax(0, 1.1fr) minmax(280px, 0.9fr);
            }
        }

        .hws-ft-field {
            margin-bottom: 14px;
        }

        .hws-ft-field label {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            font-weight: 600;
            color: #1d2327;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .hws-ft-field input[type="text"],
        .hws-ft-field select,
        .hws-ft-field textarea {
            width: 100%;
            max-width: 100%;
        }

        .hws-ft-field textarea {
            min-height: 96px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 12.5px;
        }

        .hws-ft-field-help {
            margin: 5px 0 0;
            color: #646970;
            font-size: 12.5px;
            line-height: 1.5;
        }

        .hws-ft-target-status {
            font-size: 12.5px;
            min-height: 18px;
            color: #2271b1;
        }

        .hws-ft-target-status.error { color: #b32d2e; }

        .hws-ft-target-preview {
            padding: 14px 16px;
            border: 1px solid #d7dbe0;
            border-radius: 10px;
            background: #f8f9fb;
            color: #1d2327;
            font-size: 13px;
            line-height: 1.6;
        }

        .hws-ft-target-preview code {
            display: inline-block;
            max-width: 100%;
            overflow-wrap: anywhere;
            background: #fff;
            border: 1px solid #dcdcde;
            border-radius: 5px;
            padding: 2px 6px;
        }

        .hws-ft-target-modal {
            position: fixed;
            inset: 32px;
            z-index: 100000;
            display: none;
            background: #fff;
            border: 1px solid #1d2327;
            border-radius: 12px;
            box-shadow: 0 22px 70px rgba(0, 0, 0, 0.35);
            overflow: hidden;
        }

        .hws-ft-target-modal.is-open {
            display: flex;
            flex-direction: column;
        }

        .hws-ft-target-modal-header {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: center;
            padding: 12px 16px;
            background: #1d2327;
            color: #fff;
        }

        .hws-ft-target-modal-header h3 {
            margin: 0;
            color: #fff;
            font-size: 15px;
        }

        .hws-ft-target-modal-header p {
            margin: 2px 0 0;
            color: rgba(255,255,255,0.75);
            font-size: 12.5px;
        }

        .hws-ft-target-modal iframe {
            width: 100%;
            flex: 1 1 auto;
            border: 0;
            background: #fff;
        }

        .hws-ft-picked {
            margin-top: 10px;
            padding: 10px 12px;
            border-left: 4px solid #2271b1;
            background: #f0f6fc;
            font-size: 12.5px;
            line-height: 1.6;
            display: none;
        }

        .hws-ft-picked code {
            background: #fff;
            border: 1px solid #c3d9ef;
            border-radius: 5px;
            padding: 2px 6px;
            overflow-wrap: anywhere;
        }

        /* Live Preview tile — same approach: tile container, template owns surface. */
        .hws-ft-preview {
            border: 1px solid #d7dbe0;
            border-radius: 16px;
            background: linear-gradient(180deg, #ffffff 0%, #f8f9fb 100%);
            min-height: 180px;
            color: #1d2327;
            font-size: 14px;
            line-height: 1.65;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.75), 0 12px 26px rgba(15, 23, 42, 0.04);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }

        .hws-ft-preview > * { width: 100%; }

        .hws-ft-preview p { margin: 0 0 0.6em; }
        .hws-ft-preview p:last-child { margin-bottom: 0; }

        .hws-ft-preview-empty {
            color: #8a8f94;
            font-size: 13px;
            font-style: italic;
            line-height: 1.6;
            padding: 22px 24px;
        }

        <?php echo hws_get_footer_text_template_css( 'admin' ); ?>
        <?php echo hws_get_footer_text_template_css( 'admin-mini' ); ?>

        .hws-ft-align-row {
            display: inline-flex;
            border: 1px solid #d7dbe0;
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 1px 0 rgba(15, 23, 42, 0.02);
        }

        .hws-ft-align-row label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            font-size: 13px;
            font-weight: 500;
            color: #50575e;
            cursor: pointer;
            border-right: 1px solid #e7eaee;
            background: #fff;
            transition: background-color .15s ease, color .15s ease;
            line-height: 1.2;
        }

        .hws-ft-align-row label:last-child { border-right: 0; }
        .hws-ft-align-row label:hover { background: #f6f7f9; color: #1d2327; }

        .hws-ft-align-row input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
            width: 0;
            height: 0;
            margin: 0;
        }

        .hws-ft-align-row label:has(input:checked) {
            background: linear-gradient(180deg, #f8fbff 0%, #eaf3ff 100%);
            color: #135e96;
            box-shadow: inset 0 0 0 1px #2271b1;
        }

        .hws-ft-align-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 16px;
            height: 16px;
        }

        .hws-ft-align-icon svg { width: 14px; height: 14px; display: block; }

        @media (max-width: 600px) {
            .hws-ft-card { padding: 16px; }
            .hws-ft-header { gap: 14px; }
            .hws-ft-header-toggle { width: 100%; justify-content: space-between; }
            .hws-ft-align-row { flex-wrap: wrap; }
            .hws-ft-align-row label { flex: 1 1 0; justify-content: center; }
        }
    </style>

    <div class="hws-ft-wrap">

        <div class="hws-ft-card hws-ft-header hws-ft-overview-card">
            <div class="hws-ft-header-text">
                <h2>Footer Text</h2>
                <p class="hws-ft-tagline">Set the text once, then choose one of two flows: add it as a new bottom footer section, or inject it into an existing footer element.</p>
            </div>
        </div>

        <div class="hws-ft-card hws-ft-method-one-card">
            <div class="hws-ft-header" style="margin-bottom:16px;">
                <div class="hws-ft-header-text">
                    <h2>Method 1: Add a new bottom footer section</h2>
                    <p class="hws-ft-tagline">This appends the shared text as its own styled section at the very bottom of the footer.</p>
                </div>
                <div class="hws-ft-header-toggle">
                    <span class="hws-ft-status-text <?php echo $feature_enabled ? 'on' : 'off'; ?>" id="hws-footer-text-status">
                        <?php echo $feature_enabled ? 'Bottom section is active' : 'Bottom section is off'; ?>
                    </span>
                    <?php echo render_toggle_switch( 'hws-footer-text-feature-enabled', '', $feature_enabled ); ?>
                </div>
            </div>
            <h3 class="hws-ft-section-title">Alignment</h3>
            <p class="hws-ft-section-help">Where the text sits within the band. Centered reads as a sign-off; left lines up with the start of your content; right works for short signatures or version stamps.</p>
            <div class="hws-ft-align-row" role="radiogroup" aria-label="Footer text alignment">
                <?php
                $align_icons = [
                    'left'   => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><line x1="2" y1="4" x2="14" y2="4"/><line x1="2" y1="8" x2="10" y2="8"/><line x1="2" y1="12" x2="12" y2="12"/></svg>',
                    'center' => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><line x1="2" y1="4" x2="14" y2="4"/><line x1="4" y1="8" x2="12" y2="8"/><line x1="3" y1="12" x2="13" y2="12"/></svg>',
                    'right'  => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><line x1="2" y1="4" x2="14" y2="4"/><line x1="6" y1="8" x2="14" y2="8"/><line x1="4" y1="12" x2="14" y2="12"/></svg>',
                ];
                foreach ( $alignments as $align_key => $align_label ) : ?>
                    <label>
                        <span class="hws-ft-align-icon"><?php echo $align_icons[ $align_key ] ?? ''; ?></span>
                        <input type="radio" name="hws-footer-text-alignment" value="<?php echo esc_attr( $align_key ); ?>" <?php checked( $active_alignment, $align_key ); ?>>
                        <?php echo esc_html( $align_label ); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="hws-ft-card hws-ft-method-one-card">
            <h3 class="hws-ft-section-title">Style</h3>
            <p class="hws-ft-section-help">Pick a template. Minimal styles disappear into the footer. Heavy styles render as bold dark bands. All extend the existing footer rather than sitting on top of it.</p>
            <div class="hws-ft-list" role="radiogroup" aria-label="Footer text style">
                <?php foreach ( $templates as $template_key => $template ) : ?>
                    <label class="hws-ft-item">
                        <input type="radio" name="hws-footer-text-template" value="<?php echo esc_attr( $template_key ); ?>" <?php checked( $active_template, $template_key ); ?>>
                        <div class="hws-ft-item-body">
                            <div class="hws-ft-item-name">
                                <?php echo esc_html( $template['label'] ); ?>
                                <span class="hws-ft-item-tier" data-tier="<?php echo esc_attr( $template['tier'] ?? 'minimal' ); ?>"><?php echo esc_html( $template['tier'] ?? 'minimal' ); ?></span>
                            </div>
                            <div class="hws-ft-item-desc"><?php echo esc_html( $template['description'] ); ?></div>
                        </div>
                        <div class="hws-ft-item-mini hws-footer-text--<?php echo esc_attr( $template_key ); ?> hws-ft-align--<?php echo esc_attr( $active_alignment ); ?>" data-hws-mini="<?php echo esc_attr( $template_key ); ?>">
                            <?php if ( $footer_markup ) : ?>
                                <?php echo $footer_markup; ?>
                            <?php else : ?>
                                <span class="hws-ft-item-mini-empty">Sample text shows here once saved.</span>
                            <?php endif; ?>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="hws-ft-card hws-ft-shared-card">
            <div class="hws-ft-edit-grid">
                <div>
                    <h3 class="hws-ft-col-title">Shared Footer Text</h3>
                    <textarea
                        id="hws_footer_text_editor"
                        name="hws_footer_text_editor"
                        rows="10"
                        class="hws-footer-text-editor-area"
                        style="width:100%;min-height:260px;"
                    ><?php echo esc_textarea( $footer_text_raw ); ?></textarea>
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
                    <div class="hws-ft-preview hws-footer-text--<?php echo esc_attr( $active_template ); ?> hws-ft-align--<?php echo esc_attr( $active_alignment ); ?>" id="hws-footer-text-preview-live" aria-live="polite">
                        <?php if ( $footer_markup ) : ?>
                            <?php echo $footer_markup; ?>
                        <?php else : ?>
                            <div class="hws-ft-preview-empty">No footer text yet. Type on the left and the preview will update here.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="hws-ft-card hws-ft-method-two-card" id="hws-footer-targeted-injection-card">
            <div class="hws-ft-header" style="margin-bottom:16px;">
                <div class="hws-ft-header-text">
                    <h2>Method 2: Inject into an existing footer element</h2>
                    <p class="hws-ft-tagline">Use the same shared text above, then choose where it should attach inside the existing footer structure.</p>
                </div>
                <div class="hws-ft-header-toggle">
                    <span class="hws-ft-status-text <?php echo $targeted_enabled ? 'on' : 'off'; ?>" id="hws-footer-targeted-status">
                        <?php echo $targeted_enabled ? 'Targeted injection is active' : 'Targeted injection is off'; ?>
                    </span>
                    <?php echo render_toggle_switch( 'hws-footer-targeted-enabled', '', $targeted_enabled ); ?>
                </div>
            </div>

            <div class="hws-ft-target-grid">
                <div>
                    <div class="hws-ft-field">
                        <label for="hws-footer-targeted-selector">Target selector</label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="text" id="hws-footer-targeted-selector" value="<?php echo esc_attr( $targeted_selector ); ?>" placeholder="#site-footer .copyright or .footer-bottom">
                            <button type="button" class="button" id="hws-footer-targeted-pick">Select From Footer</button>
                        </div>
                        <p class="hws-ft-field-help">Use an ID, class, or full CSS selector. The picker can inspect the visible footer and fill this for you.</p>
                        <div class="hws-ft-picked" id="hws-footer-targeted-picked"></div>
                    </div>

                    <div class="hws-ft-field">
                        <label for="hws-footer-targeted-placement">Placement</label>
                        <select id="hws-footer-targeted-placement">
                            <?php foreach ( $targeted_placements as $placement_key => $placement_label ) : ?>
                                <option value="<?php echo esc_attr( $placement_key ); ?>" <?php selected( $targeted_placement, $placement_key ); ?>>
                                    <?php echo esc_html( $placement_label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="hws-ft-field-help"><strong>Before</strong> inserts before the target element. <strong>After</strong> inserts after it. <strong>Within</strong> appends the span inside the target.</p>
                    </div>

                    <div class="hws-ft-field">
                        <label>Shared text source</label>
                        <div class="hws-ft-picked" style="display:block;border-left-color:#00a32a;background:#edfaef;">
                            This method uses the shared footer text editor at the top of this tab. Save the shared text first, then save this selector and placement.
                        </div>
                    </div>

                    <div class="hws-ft-editor-actions">
                        <button type="button" class="button button-primary" id="hws-footer-targeted-save">Save Targeted Injection</button>
                        <span class="hws-ft-target-status" id="hws-footer-targeted-save-status" aria-live="polite"></span>
                    </div>
                </div>

                <div>
                    <h3 class="hws-ft-col-title">Injection Preview</h3>
                    <div class="hws-ft-target-preview">
                        <p><strong>Status:</strong> <span id="hws-footer-targeted-preview-status"><?php echo $targeted_enabled ? 'Enabled' : 'Disabled'; ?></span></p>
                        <p><strong>Selector:</strong> <code id="hws-footer-targeted-preview-selector"><?php echo esc_html( $targeted_selector ?: 'None selected' ); ?></code></p>
                        <p><strong>Placement:</strong> <code id="hws-footer-targeted-preview-placement"><?php echo esc_html( $targeted_placements[ $targeted_placement ] ?? 'Within target' ); ?></code></p>
                        <p><strong>Shared text output:</strong></p>
                        <div id="hws-footer-targeted-preview-markup">
                            <?php echo $targeted_markup ? '<span class="hws-footer-inline-injection">' . $targeted_markup . '</span>' : '<em>No inline content saved yet.</em>'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="hws-ft-target-modal" id="hws-footer-targeted-modal" aria-hidden="true">
            <div class="hws-ft-target-modal-header">
                <div>
                    <h3>Click a footer element</h3>
                    <p>Hover highlights visible footer elements. Click the exact text/area where the inline span should attach.</p>
                </div>
                <button type="button" class="button button-secondary" id="hws-footer-targeted-close">Close</button>
            </div>
            <iframe id="hws-footer-targeted-frame" title="Footer selector picker"></iframe>
        </div>
    </div>

    <script>
    jQuery(function($) {
        var $toggle          = $('#hws-footer-text-feature-enabled');
        var $templateInputs  = $('input[name="hws-footer-text-template"]');
        var $alignmentInputs = $('input[name="hws-footer-text-alignment"]');
        var $status          = $('#hws-footer-text-status');
        var $saving          = $('#hws-footer-text-saving');
        var $preview         = $('#hws-footer-text-preview-live');
        var $miniPreviews    = $('.hws-ft-item-mini');
        var $saveContent     = $('#hws-footer-text-save-content');
        var $copyBtn         = $('#hws-footer-text-copy');
        var $targetToggle    = $('#hws-footer-targeted-enabled');
        var $targetStatus    = $('#hws-footer-targeted-status');
        var $targetSelector  = $('#hws-footer-targeted-selector');
        var $targetPlacement = $('#hws-footer-targeted-placement');
        var $targetSave      = $('#hws-footer-targeted-save');
        var $targetSaveStatus = $('#hws-footer-targeted-save-status');
        var $targetModal     = $('#hws-footer-targeted-modal');
        var $targetFrame     = $('#hws-footer-targeted-frame');
        var $targetPicked    = $('#hws-footer-targeted-picked');
        var templateMeta     = <?php echo wp_json_encode( $template_meta_for_js ); ?>;
        var miniEmptyHtml    = '<span class="hws-ft-item-mini-empty">Sample text shows here once saved.</span>';
        var allAlignClasses  = 'hws-ft-align--left hws-ft-align--center hws-ft-align--right';
        var pickerUrl        = <?php echo wp_json_encode( add_query_arg( 'hws_footer_picker', '1', home_url( '/' ) ) ); ?>;

        var editorId = "hws_footer_text_editor";
        var editorInitAttempts = 0;

        function removeFooterEditor() {
            if (!(window.wp && wp.editor && typeof wp.editor.remove === "function")) return;

            try {
                wp.editor.remove(editorId);
            } catch (error) {
                var editor = window.tinymce && tinymce.get(editorId);
                if (editor) editor.remove();
            }
        }

        function initializeFooterEditor() {
            if (!document.getElementById(editorId)) return;

            if (!(window.wp && wp.editor && typeof wp.editor.initialize === "function")) {
                editorInitAttempts += 1;
                if (editorInitAttempts < 40) window.setTimeout(initializeFooterEditor, 250);
                return;
            }

            removeFooterEditor();
            wp.editor.initialize(editorId, {
                tinymce: {
                    wpautop: true,
                    content_style: "body#tinymce.wp-editor{color:#1d2327;background:#fff;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;font-size:14px;line-height:1.6;} body#tinymce.wp-editor p{color:#1d2327;}"
                },
                quicktags: true,
                mediaButtons: true
            });
        }

        var tabRoot = document.querySelector("[data-hpc-tab-root]");
        if (tabRoot) {
            if (tabRoot.hwsFooterEditorCleanup) {
                tabRoot.removeEventListener("hexa-core-host-tab-before-load", tabRoot.hwsFooterEditorCleanup);
            }
            tabRoot.hwsFooterEditorCleanup = removeFooterEditor;
            tabRoot.addEventListener("hexa-core-host-tab-before-load", tabRoot.hwsFooterEditorCleanup);
        }

        initializeFooterEditor();

        function setBusy(isBusy) {
            $toggle.prop('disabled', isBusy);
            $templateInputs.prop('disabled', isBusy);
            $alignmentInputs.prop('disabled', isBusy);
            $saveContent.prop('disabled', isBusy);
        }

        function getSelectedAlignment() {
            return $alignmentInputs.filter(':checked').val() || 'center';
        }

        function syncEditorToTextarea() {
            if (window.tinymce && typeof tinymce.triggerSave === 'function') {
                tinymce.triggerSave();
            }
        }

        function getEditorContent() {
            var editor = window.tinymce && tinymce.get('hws_footer_text_editor');
            if (editor && !editor.isHidden()) {
                return editor.getContent();
            }
            syncEditorToTextarea();
            return $('#hws_footer_text_editor').val() || '';
        }

        function getSelectedTemplate() {
            return $templateInputs.filter(':checked').val() || 'whisper';
        }

        function hasMeaningfulContent(html) {
            var text = $('<div>').html(html || '').text().replace(/ /g, ' ').trim();
            return text.length > 0;
        }

        var allTemplateClasses = Object.keys(templateMeta || {}).map(function(k) {
            return 'hws-footer-text--' + k;
        }).join(' ');

        function applyPreviewTemplate() {
            var template  = getSelectedTemplate();
            var alignment = getSelectedAlignment();
            if (allTemplateClasses) {
                $preview.removeClass(allTemplateClasses);
            }
            $preview.removeClass(allAlignClasses);
            $preview.addClass('hws-footer-text--' + template);
            $preview.addClass('hws-ft-align--' + alignment);
        }

        function applyAlignmentToMinis() {
            var alignment = getSelectedAlignment();
            $miniPreviews.removeClass(allAlignClasses).addClass('hws-ft-align--' + alignment);
        }

        function refreshMiniPreviews(html, hasContent) {
            $miniPreviews.each(function() {
                var $mini = $(this);
                if (hasContent && html) {
                    $mini.html(html);
                } else {
                    $mini.html(miniEmptyHtml);
                }
            });
            applyAlignmentToMinis();
        }

        function refreshPreview(html, hasContent) {
            applyPreviewTemplate();
            if (hasContent && html) {
                $preview.html(html);
            } else {
                $preview.html('<div class="hws-ft-preview-empty">No footer text yet. Type on the left and the preview will update here.</div>');
            }
            refreshMiniPreviews(html, hasContent);
        }

        function updatePreviewFromEditor() {
            var html = getEditorContent();
            refreshPreview(html, hasMeaningfulContent(html));
            refreshTargetPreview({
                enabled: $targetToggle.is(':checked'),
                selector: $targetSelector.val() || '',
                placement: $targetPlacement.val() || 'within',
                markup: html
            });
        }

        var tinyMceBindAttempts = 0;

        function bindTinyMcePreview() {
            var editor = window.tinymce && tinymce.get('hws_footer_text_editor');
            if (!editor) {
                tinyMceBindAttempts += 1;
                if (tinyMceBindAttempts < 40) {
                    window.setTimeout(bindTinyMcePreview, 300);
                }
                return;
            }
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

        function showTargetSaving(msg, isError) {
            $targetSaveStatus.removeClass('error');
            if (isError) $targetSaveStatus.addClass('error');
            $targetSaveStatus.text(msg || '');
        }

        function targetPlacementLabel(value) {
            var map = <?php echo wp_json_encode( $targeted_placements ); ?> || {};
            return map[value] || 'Within target';
        }

        function refreshTargetPreview(data) {
            data = data || {};
            var enabled = !!data.enabled;
            var selector = data.selector || $targetSelector.val() || '';
            var placement = data.placement || $targetPlacement.val() || 'within';
            var markup = typeof data.markup === 'string' ? data.markup : getEditorContent();

            $('#hws-footer-targeted-preview-status').text(enabled ? 'Enabled' : 'Disabled');
            $('#hws-footer-targeted-preview-selector').text(selector || 'None selected');
            $('#hws-footer-targeted-preview-placement').text(targetPlacementLabel(placement));

            if (typeof markup === 'string' && markup.replace(/\s+/g, '').length) {
                $('#hws-footer-targeted-preview-markup').html('<span class="hws-footer-inline-injection">' + markup + '</span>');
            }
        }

        function saveTargetedInjection() {
            var enabled = $targetToggle.is(':checked') ? 1 : 0;
            var payload = {
                action: 'hws_footer_text_save_targeted_injection',
                nonce: hwsNonce,
                enabled: enabled,
                selector: $targetSelector.val() || '',
                placement: $targetPlacement.val() || 'within',
                content: getEditorContent()
            };

            $targetSave.prop('disabled', true);
            $targetToggle.prop('disabled', true);
            showTargetSaving('Saving targeted injection…', false);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: payload
            }).done(function(response) {
                if (!response || !response.success) { throw response; }

                if (response.data && response.data.selector) {
                    $targetSelector.val(response.data.selector);
                }

                if (enabled) {
                    $targetStatus.removeClass('off').addClass('on').text('Targeted injection is active');
                } else {
                    $targetStatus.removeClass('on').addClass('off').text('Targeted injection is off');
                }

                refreshTargetPreview(response.data || {});
                showTargetSaving('Targeted injection saved.', false);
                window.setTimeout(function() { showTargetSaving('', false); }, 1500);
            }).fail(function(response) {
                console.error('Targeted footer injection save failed', response);
                showTargetSaving('Save failed. Refresh and try again.', true);
            }).always(function() {
                $targetSave.prop('disabled', false);
                $targetToggle.prop('disabled', false);
            });
        }

        function cssEscape(value) {
            if (window.CSS && CSS.escape) return CSS.escape(value);
            return String(value || '').replace(/[^a-zA-Z0-9_-]/g, function(ch) {
                return '\\' + ch;
            });
        }

        function elementTextPreview(el) {
            return (el.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 120);
        }

        function selectorIsUnique(doc, selector) {
            try {
                return doc.querySelectorAll(selector).length === 1;
            } catch (err) {
                return false;
            }
        }

        function stableClassList(el) {
            return Array.prototype.slice.call((el && el.classList) || [])
                .filter(function(cls) {
                    return cls !== 'hws-ft-picker-hover'
                        && !/^hws-ft-picker-/.test(cls)
                        && !/^elementor-(element|widget|column|section|container)$/.test(cls);
                });
        }

        function buildDomPath(el, doc) {
            var parts = [];
            var current = el;

            while (current && current.nodeType === 1 && current !== doc.body && parts.length < 5) {
                var tag = current.tagName.toLowerCase();
                var parent = current.parentElement;

                if (current.id) {
                    parts.unshift(tag + '#' + cssEscape(current.id));
                    break;
                }

                var stableClasses = stableClassList(current);
                if (stableClasses.length) {
                    var classes = stableClasses.slice(0, 3).map(cssEscape).join('.');
                    parts.unshift(tag + '.' + classes);
                } else if (parent) {
                    var siblings = Array.prototype.filter.call(parent.children, function(child) {
                        return child.tagName === current.tagName;
                    });
                    parts.unshift(tag + ':nth-of-type(' + (siblings.indexOf(current) + 1) + ')');
                } else {
                    parts.unshift(tag);
                }

                current = parent;
            }

            return parts.join(' > ');
        }

        function suggestSelector(el, doc) {
            var tag = el.tagName.toLowerCase();

            if (el.classList) {
                el.classList.remove('hws-ft-picker-hover');
            }

            if (el.id) {
                var byId = '#' + cssEscape(el.id);
                if (selectorIsUnique(doc, byId)) {
                    return byId;
                }
            }

            var elementorNode = el.closest('[data-id]');
            if (elementorNode) {
                var elementorId = elementorNode.getAttribute('data-id') || '';
                var elementorSelector = elementorId ? '.elementor-element-' + cssEscape(elementorId) : '';

                if (elementorSelector && elementorNode === el && selectorIsUnique(doc, elementorSelector)) {
                    return elementorSelector;
                }

                var childClassList = stableClassList(el).slice(0, 3);
                if (elementorSelector && childClassList.length) {
                    var scopedElementor = elementorSelector + ' .' + childClassList.map(cssEscape).join('.');
                    if (selectorIsUnique(doc, scopedElementor)) {
                        return scopedElementor;
                    }
                }

                if (elementorSelector && selectorIsUnique(doc, elementorSelector)) {
                    return elementorSelector;
                }
            }

            if (el.classList && el.classList.length) {
                var classList = stableClassList(el).slice(0, 3);

                if (classList.length) {
                    var classSelector = '.' + classList.map(cssEscape).join('.');
                    if (selectorIsUnique(doc, classSelector)) {
                        return classSelector;
                    }

                    var footer = el.closest('footer, [role="contentinfo"], .site-footer, .elementor-location-footer, [data-elementor-type="footer"]');
                    if (footer) {
                        var scoped = footer.id
                            ? '#' + cssEscape(footer.id) + ' ' + classSelector
                            : buildDomPath(footer, doc) + ' ' + classSelector;
                        if (selectorIsUnique(doc, scoped)) {
                            return scoped;
                        }
                    }

                    return tag + classSelector;
                }
            }

            return buildDomPath(el, doc);
        }

        function openFooterPicker() {
            $targetModal.addClass('is-open').attr('aria-hidden', 'false');
            $targetFrame.attr('src', pickerUrl + (pickerUrl.indexOf('?') === -1 ? '?' : '&') + 't=' + Date.now());
        }

        function closeFooterPicker() {
            $targetModal.removeClass('is-open').attr('aria-hidden', 'true');
            $targetFrame.attr('src', 'about:blank');
        }

        function bindFooterPickerFrame() {
            var frame = $targetFrame.get(0);
            var doc;
            var win;
            try {
                doc = frame ? frame.contentDocument : null;
                win = frame ? frame.contentWindow : null;
            } catch (err) {
                showTargetSaving('Picker could not inspect the page. Open the live page and copy a selector manually.', true);
                return;
            }
            if (!doc) return;

            var style = doc.createElement('style');
            style.textContent = '.hws-ft-picker-hover{outline:3px solid #2271b1!important;outline-offset:3px!important;cursor:crosshair!important;}';
            doc.head.appendChild(style);

            var footerRoots = doc.querySelectorAll('footer, [role="contentinfo"], .site-footer, .elementor-location-footer, [data-elementor-type="footer"]');
            if (!footerRoots.length) {
                footerRoots = [doc.body];
            }

            Array.prototype.forEach.call(footerRoots, function(rootNode) {
                rootNode.addEventListener('mouseover', function(event) {
                    if (event.target && event.target.nodeType === 1) {
                        event.target.classList.add('hws-ft-picker-hover');
                    }
                }, true);

                rootNode.addEventListener('mouseout', function(event) {
                    if (event.target && event.target.nodeType === 1) {
                        event.target.classList.remove('hws-ft-picker-hover');
                    }
                }, true);

                rootNode.addEventListener('click', function(event) {
                    event.preventDefault();
                    event.stopPropagation();

                    var el = event.target;
                    if (!el || el.nodeType !== 1) return;

                    var selector = suggestSelector(el, doc);
                    var id = el.id ? '#' + el.id : 'none';
                    var classes = stableClassList(el).join(' ');
                    var text = elementTextPreview(el);

                    $targetSelector.val(selector);
                    $targetPicked.html(
                        '<strong>Picked:</strong> <code>' + $('<div>').text(el.tagName.toLowerCase()).html() + '</code><br>' +
                        '<strong>ID:</strong> <code>' + $('<div>').text(id).html() + '</code><br>' +
                        '<strong>Classes:</strong> <code>' + $('<div>').text(classes || 'none').html() + '</code><br>' +
                        '<strong>Selector:</strong> <code>' + $('<div>').text(selector).html() + '</code><br>' +
                        (text ? '<strong>Text:</strong> ' + $('<div>').text(text).html() : '')
                    ).show();

                    refreshTargetPreview({
                        enabled: $targetToggle.is(':checked'),
                        selector: selector,
                        placement: $targetPlacement.val() || 'within'
                    });

                    closeFooterPicker();
                }, true);
            });

            try {
                win.scrollTo(0, doc.body.scrollHeight);
            } catch (err) {}
        }

        function saveFooterTextSettings(includeContent) {
            var enabled   = $toggle.is(':checked') ? 1 : 0;
            var template  = getSelectedTemplate();
            var alignment = getSelectedAlignment();

            syncEditorToTextarea();

            var payload   = {
                action: 'hws_footer_text_save_settings',
                nonce: hwsNonce,
                enabled: enabled,
                template: template,
                alignment: alignment
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
                    $status.removeClass('off').addClass('on').text('Bottom section is active');
                } else {
                    $status.removeClass('on').addClass('off').text('Bottom section is off');
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
            applyAlignmentToMinis();
            saveFooterTextSettings(false);
        });

        $alignmentInputs.on('change', function() {
            applyPreviewTemplate();
            applyAlignmentToMinis();
            saveFooterTextSettings(false);
        });

        $saveContent.on('click', function() { saveFooterTextSettings(true); });

        $('#hws_footer_text_editor').on('input keyup change', function() {
            updatePreviewFromEditor();
        });

        $(document).on('click', '#hws_footer_text_editor-tmce, #hws_footer_text_editor-html', function() {
            window.setTimeout(function() {
                syncEditorToTextarea();
                bindTinyMcePreview();
                updatePreviewFromEditor();
            }, 180);
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

        $targetToggle.on('change', saveTargetedInjection);
        $targetPlacement.on('change', function() {
            refreshTargetPreview({
                enabled: $targetToggle.is(':checked'),
                selector: $targetSelector.val() || '',
                placement: $targetPlacement.val() || 'within'
            });
        });
        $targetSelector.on('input change', function() {
            refreshTargetPreview({
                enabled: $targetToggle.is(':checked'),
                selector: $targetSelector.val() || '',
                placement: $targetPlacement.val() || 'within'
            });
        });
        $targetSave.on('click', saveTargetedInjection);
        $('#hws-footer-targeted-pick').on('click', openFooterPicker);
        $('#hws-footer-targeted-close').on('click', closeFooterPicker);
        $targetFrame.on('load', bindFooterPickerFrame);

        applyPreviewTemplate();
        applyAlignmentToMinis();
        updatePreviewFromEditor();
        bindTinyMcePreview();
    });
    </script>
    <?php
}
