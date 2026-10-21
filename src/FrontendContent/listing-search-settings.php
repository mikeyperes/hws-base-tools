<?php

namespace hws_base_tools;

use Hexa\PluginCore\ListingSearch\ListingSearchRenderer;
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

    wp_send_json_success( [ 'message' => 'Saved: ' . ListingSearchFeature::STYLES[ $style ] . '. Cached pages were cleared.' ] );
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
        <p>The look of the search box with filters above category, tag, author and search results. Site CSS always wins over it.</p>
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
        <span class="hws-listing-style-status" role="status" aria-live="polite"></span>
    </section>
    <style>
    .hws-listing-style{max-width:760px;margin-top:28px;padding-top:18px;border-top:1px solid #dcdcde}
    .hws-listing-style h3{margin:0 0 4px;font-size:15px}.hws-listing-style>p{margin:0 0 12px;color:#646970}
    .hws-listing-style-options{display:flex;flex-direction:column;gap:2px}
    .hws-listing-style-option{display:grid;grid-template-columns:auto 1fr;gap:1px 8px;padding:6px 8px;border-radius:4px;cursor:pointer}.hws-listing-style-option:hover{background:#f6f7f7}
    .hws-listing-style-option span{grid-column:2;color:#646970;font-size:12px}
    .hws-listing-style-preview{margin:4px 0 18px 32px;padding:20px;border:1px solid #dcdcde;border-radius:6px;background:#fff;color:#1d2327;font-size:15px;pointer-events:none}.hws-listing-style-status{display:block;margin-top:10px;font-weight:600;color:#008a20}
    </style>
    <script>
    (function(){
      var root=document.getElementById('hws-listing-style');if(!root)return;var status=root.querySelector('.hws-listing-style-status');
      root.addEventListener('change',function(e){if(e.target.name!=='hws_listing_search_style')return;status.textContent='Saving…';
        var body=new URLSearchParams();body.append('action','hws_listing_search_style_save');body.append('nonce',<?php echo wp_json_encode( wp_create_nonce( HWS_AJAX_NONCE ) ); ?>);body.append('style',e.target.value);
        fetch(ajaxurl,{method:'POST',credentials:'same-origin',body:body}).then(function(r){return r.json();}).then(function(p){status.textContent=p&&p.data&&p.data.message?p.data.message:'Could not save.';}).catch(function(){status.textContent='Could not save.';});});
    })();
    </script>
    <?php
}
