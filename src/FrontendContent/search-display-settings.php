<?php

namespace hws_base_tools;

use Hexa\PluginCore\Search\SearchBox;
use Hexa\PluginCore\WpAdminComponents\CoreUi;
use HWS\BaseTools\AdminDashboard\SettingsAutosave;
use HWS\BaseTools\FrontendContent\SearchBoxFeature;

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_hws_search_display_save', __NAMESPACE__ . '\\ajax_save_search_display' );

function ajax_save_search_display(): void {
    if ( ! current_user_can( Config::$settings_page_capability ) ) {
        wp_send_json_error( [ 'message' => 'You are not allowed to change site-search settings.' ], 403 );
    }

    hws_require_ajax_nonce_or_error();

    $settings = SearchBoxFeature::sanitize_header(
        [
            'style'       => isset( $_POST['style'] ) ? wp_unslash( $_POST['style'] ) : '',
            'accent'      => isset( $_POST['accent'] ) ? wp_unslash( $_POST['accent'] ) : '',
            'placeholder' => isset( $_POST['placeholder'] ) ? wp_unslash( $_POST['placeholder'] ) : '',
        ]
    );

    update_option( SearchBoxFeature::HEADER_OPTION, $settings );
    if ( function_exists( 'do_action' ) ) {
        do_action( 'litespeed_purge_all' ); // Cached pages carry the old header box.
    }

    wp_send_json_success(
        [
            'settings' => $settings,
            'message'  => 'The header search box now uses: ' . SearchBoxFeature::HEADER_STYLES[ $settings['style'] ] . '.',
        ]
    );
}

function render_tab_search(): void {
    if ( ! class_exists( SearchBox::class ) ) {
        echo '<div class="notice notice-error"><p>The Hexa WP Core search is unavailable.</p></div>';
        return;
    }

    $settings = SearchBoxFeature::header_settings();
    $accent   = '' !== $settings['accent'] ? $settings['accent'] : '#1b2230';
    CoreUi::render_assets();
    ?>
    <div class="hws-search-display-admin hpc-ui" id="hws-search-display-admin">
        <header class="hws-search-display-heading">
            <h2>Site Search</h2>
            <p>How site search matches words, the header search box, and the search box above lists. All three use Hexa WP Core's one search.</p>
        </header>

        <?php render_search_query_settings(); ?>

        <section class="hws-search-head" aria-labelledby="hws-search-head-title" data-hws-search-form>
            <h3 id="hws-search-head-title">Header search box (<code>[hws_search_box]</code>)</h3>
            <p>Goes to the search page; it never opens a popup. Shortcode attributes <code>style</code>, <code>accent</code>, <code>placeholder</code> and <code>label</code> override these for one placement.</p>
            <h4>Look</h4>
            <div class="hws-search-head-options">
                <?php foreach ( SearchBoxFeature::HEADER_STYLES as $style => $label ) : ?>
                    <label class="hws-search-head-option">
                        <input type="radio" name="style" value="<?php echo esc_attr( $style ); ?>" <?php checked( $settings['style'], $style ); ?>>
                        <strong><?php echo esc_html( $label ); ?></strong>
                        <code>style="<?php echo esc_html( $style ); ?>"</code>
                    </label>
                    <div class="hws-search-head-preview" data-hws-search-preview>
                        <?php echo SearchBox::header( [ 'style' => $style, 'accent' => $settings['accent'], 'placeholder' => $settings['placeholder'], 'id' => 'preview-' . $style ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <h4>Accent colour</h4>
            <span class="hws-search-color-control">
                <input type="color" name="accent_picker" value="<?php echo esc_attr( $accent ); ?>" data-hws-search-accent-picker>
                <input type="text" name="accent" value="<?php echo esc_attr( $settings['accent'] ); ?>" placeholder="#1b2230" pattern="^#[0-9a-fA-F]{6}$" data-hws-search-accent>
            </span>
            <h4>Placeholder text</h4>
            <input type="text" name="placeholder" value="<?php echo esc_attr( $settings['placeholder'] ); ?>" maxlength="120" data-hws-search-placeholder class="hws-search-head-text">
            <?php echo SettingsAutosave::notice( 'hws-search-display-notice' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </section>
    </div>

    <style>
    #hws-search-display-admin{width:100%;max-width:760px;color:#1d2327}
    #hws-search-display-admin *{box-sizing:border-box}
    .hws-search-display-heading{margin:0 0 20px;padding:0 0 18px;border-bottom:1px solid #dcdcde}
    .hws-search-display-heading h2{margin:0 0 7px;font-size:22px;line-height:1.25}
    .hws-search-display-heading p{margin:0;color:#50575e;font-size:14px;line-height:1.6}
    .hws-search-head{margin-top:28px;padding-top:18px;border-top:1px solid #dcdcde}
    .hws-search-head h3{margin:0 0 4px;font-size:15px}.hws-search-head>p{margin:0 0 12px;color:#646970}
    .hws-search-head h4{margin:18px 0 6px;font-size:14px}
    .hws-search-head-options{display:flex;flex-direction:column;gap:2px}
    .hws-search-head-option{display:flex;align-items:center;gap:8px;padding:6px 8px;border-radius:4px;cursor:pointer}.hws-search-head-option:hover{background:#f6f7f7}
    .hws-search-head-option code{margin-left:auto;font-size:11px;color:#646970;background:none}
    .hws-search-head-preview{display:flex;justify-content:flex-end;margin:4px 0 14px 32px;padding:14px 18px;border:1px solid #dcdcde;border-radius:6px;background:#fff}
    .hws-search-color-control{display:grid;grid-template-columns:44px minmax(0,320px);gap:7px}
    .hws-search-color-control input[type="color"]{width:44px;height:38px;margin:0;padding:2px;border:1px solid #8c8f94;border-radius:4px;background:#fff}
    .hws-search-color-control input[type="text"],.hws-search-head-text{min-height:38px;width:100%;max-width:371px}
    </style>
    <script>
    (function(){
      var root=document.getElementById('hws-search-display-admin');
      if(!root||root.dataset.ready==='1')return;
      root.dataset.ready='1';
      var form=root.querySelector('[data-hws-search-form]');
      var notice='#hws-search-display-notice';
      var accent=root.querySelector('[data-hws-search-accent]');
      var picker=root.querySelector('[data-hws-search-accent-picker]');
      var placeholder=root.querySelector('[data-hws-search-placeholder]');
      function selectedStyle(){var input=form.querySelector('input[name="style"]:checked');return input?input.value:'icon-reveal';}
      function syncPreviews(){var color=(accent.value||'').trim()||'#1b2230';root.querySelectorAll('[data-hws-search-preview] .hls-head').forEach(function(box){box.style.setProperty('--hls-accent',color);});root.querySelectorAll('[data-hws-search-preview] .hls-head-input').forEach(function(input){input.setAttribute('placeholder',placeholder.value||'Search…');});}
      picker.addEventListener('input',function(){accent.value=picker.value;syncPreviews();});
      accent.addEventListener('input',function(){if(/^#[0-9a-fA-F]{6}$/.test(accent.value))picker.value=accent.value;syncPreviews();});
      placeholder.addEventListener('input',syncPreviews);
      HWSSettingsAutosave.bind(form,{
        notice:notice,
        ignore:'[data-hws-search-preview] *',
        validate:function(){var value=accent.value.trim();if(value&&!/^#[0-9a-fA-F]{6}$/.test(value)){HWSSettingsAutosave.fail(notice,'Accent colour must be a 6-digit hex code like #1b2230.');return false;}return true;},
        save:function(){return HWSSettingsAutosave.post('hws_search_display_save',<?php echo wp_json_encode( wp_create_nonce( HWS_AJAX_NONCE ) ); ?>,{style:selectedStyle(),accent:accent.value.trim(),placeholder:placeholder.value.trim()}).then(function(data){return {message:data.message};});}
      });
      syncPreviews();
    })();
    </script>
    <?php
    render_listing_search_style_settings();
}
