<?php

namespace hws_base_tools;

use Hexa\PluginCore\ListingSearch\ListingSearchRenderer;
use HWS\BaseTools\AdminDashboard\SettingsAutosave;
use HWS\BaseTools\FrontendContent\ListingSearchFeature;

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_hws_listing_search_style_save', __NAMESPACE__ . '\\ajax_save_listing_search_style' );

function ajax_save_listing_search_style(): void {
    if ( ! current_user_can( Config::$settings_page_capability ) ) {
        wp_send_json_error( [ 'message' => 'You are not allowed to change site-search settings.' ], 403 );
    }

    hws_require_ajax_nonce_or_error();

    $style = isset( $_POST['style'] ) ? sanitize_key( wp_unslash( $_POST['style'] ) ) : '';
    if ( ! isset( ListingSearchFeature::STYLES[ $style ] ) ) {
        wp_send_json_error( [ 'message' => 'Unknown style.' ], 400 );
    }

    update_option( ListingSearchFeature::STYLE_OPTION, $style, false );
    if ( function_exists( 'do_action' ) ) {
        do_action( 'litespeed_purge_all' ); // Cached pages carry the old look.
    }

    wp_send_json_success( [ 'message' => 'The search box above lists now uses ' . ListingSearchFeature::STYLES[ $style ] . '. Cached pages were cleared.' ] );
}

/** The listing search box's look, shown on the Search tab. */
function render_listing_search_style_settings(): void {
    $current = ListingSearchFeature::style();
    $notes   = [
        'none'      => 'Bare: only what the filter lists need to open and close. Style it in the site CSS.',
        'default-1' => 'Compact: a boxed field up to 360 px wide and small filter buttons beside it.',
        'default-2' => 'Editorial: an underlined field and uppercase text filters, no boxes.',
    ];
    ?>
    <section class="hws-listing-style" id="hws-listing-style">
        <h3>Search box above lists (<code>[hws_search]</code>)</h3>
        <p>The search box with filters above category, tag, author and search results. The look chosen below is the site default; site CSS always wins over it.</p>
        <div class="hws-listing-attrs">
            <div class="hws-listing-attrs-head">
                <strong>Shortcode attributes</strong>
                <span>All optional. They change only that one box. Click an example to copy it.</span>
            </div>
            <?php foreach ( ListingSearchFeature::ATTRIBUTES as $group => $attributes ) : ?>
                <div class="hws-listing-attrs-group"><?php echo esc_html( $group ); ?></div>
                <?php foreach ( $attributes as $name => [ $does, $example ] ) : ?>
                    <div class="hws-listing-attr">
                        <code class="hws-listing-attr-name"><?php echo esc_html( $name ); ?></code>
                        <span class="hws-listing-attr-does"><?php echo esc_html( $does ); ?></span>
                        <button type="button" class="hws-listing-attr-example" data-copy="<?php echo esc_attr( '[hws_search ' . $example . ']' ); ?>" title="Copy"><?php echo esc_html( $example ); ?></button>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
        <h4 class="hws-listing-style-heading">Site default look</h4>
        <div class="hws-listing-style-options">
            <?php foreach ( ListingSearchFeature::STYLES as $key => $label ) : ?>
                <label class="hws-listing-style-option">
                    <input type="radio" name="hws_listing_search_style" value="<?php echo esc_attr( $key ); ?>" <?php checked( $current, $key ); ?>>
                    <strong><?php echo esc_html( $label ); ?></strong>
                    <span><?php echo esc_html( $notes[ $key ] ); ?></span>
                </label>
                <?php if ( method_exists( ListingSearchRenderer::class, 'preview' ) ) : ?>
                    <div class="hws-listing-style-preview" aria-hidden="true"><?php echo ListingSearchRenderer::preview( $key ); // phpcs:ignore WordPress.Security.EscapeOutput -- Core builds escaped preview markup. ?></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php echo SettingsAutosave::notice( 'hws-listing-style-notice' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </section>
    <style>
    .hws-listing-style{max-width:760px;margin-top:28px;padding-top:18px;border-top:1px solid #dcdcde}
    .hws-listing-style h3{margin:0 0 4px;font-size:15px}.hws-listing-style>p{margin:0 0 12px;color:#646970}
    .hws-listing-attrs{margin:0 0 22px;border:1px solid #dcdcde;border-radius:6px;background:#fff;overflow:hidden}
    .hws-listing-attrs-head{display:flex;flex-direction:column;gap:2px;padding:12px 16px;border-bottom:1px solid #dcdcde;background:#f6f7f7}
    .hws-listing-attrs-head span{color:#646970;font-size:12px}
    .hws-listing-attrs-group{padding:10px 16px 4px;color:#646970;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase}
    .hws-listing-attr{display:flex;align-items:center;gap:12px;padding:6px 16px;border-top:1px solid #f0f0f1;font-size:13px}
    .hws-listing-attr:hover{background:#fafafa}
    .hws-listing-attr-name{flex:0 0 108px;padding:0;background:none;color:#1d2327;font-size:12px;font-weight:600}
    .hws-listing-attr-does{flex:1 1 auto;min-width:0;color:#50575e}
    .hws-listing-attr-example{flex:0 0 auto;padding:2px 8px;border:1px solid #dcdcde;border-radius:4px;background:#f6f7f7;color:#3c434a;font:12px/1.5 Consolas,Monaco,monospace;cursor:pointer;transition:background-color .2s,border-color .2s}
    .hws-listing-attr-example:hover{border-color:#2271b1;background:#f0f6fc}
    .hws-listing-attr-example.is-copied{border-color:#16803c;background:#edf9f1;color:#16803c}
    @media(max-width:600px){.hws-listing-attr{flex-wrap:wrap;gap:4px 12px}.hws-listing-attr-does{flex-basis:calc(100% - 120px)}}
    .hws-listing-style-heading{margin:0 0 6px;font-size:14px}
    .hws-listing-style-options{display:flex;flex-direction:column;gap:2px}
    .hws-listing-style-option{display:grid;grid-template-columns:auto 1fr;gap:1px 8px;padding:6px 8px;border-radius:4px;cursor:pointer}.hws-listing-style-option:hover{background:#f6f7f7}
    .hws-listing-style-option span{grid-column:2;color:#646970;font-size:12px}
    .hws-listing-style-preview{margin:4px 0 18px 32px;padding:20px;border:1px solid #dcdcde;border-radius:6px;background:#fff;color:#1d2327;font-size:15px;pointer-events:none}
    </style>
    <script>
    (function(){
      var root=document.getElementById('hws-listing-style');if(!root)return;
      root.addEventListener('click',function(e){var b=e.target.closest('.hws-listing-attr-example');if(!b)return;var text=b.getAttribute('data-copy');function done(){b.classList.add('is-copied');HWSSettingsAutosave.done('#hws-listing-style-notice','Copied',text);setTimeout(function(){b.classList.remove('is-copied');},1500);}if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(text).then(done);}});
      HWSSettingsAutosave.bind(root,{
        notice:'#hws-listing-style-notice',
        ignore:'.hws-listing-style-preview *',
        save:function(){var picked=root.querySelector('input[name="hws_listing_search_style"]:checked');return HWSSettingsAutosave.post('hws_listing_search_style_save',<?php echo wp_json_encode( wp_create_nonce( HWS_AJAX_NONCE ) ); ?>,{style:picked?picked.value:''}).then(function(data){return {message:data.message};});}
      });
    })();
    </script>
    <?php
}
