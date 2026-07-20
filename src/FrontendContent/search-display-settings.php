<?php

namespace hws_base_tools;

use Hexa\PluginCore\SearchDisplay\SearchDisplayRenderer;
use Hexa\PluginCore\WpAdminComponents\CoreUi;
use HWS\BaseTools\FrontendContent\SearchDisplayFeature;
use HWS\BaseTools\FrontendContent\SearchQueryFeature;

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_hws_search_display_save', __NAMESPACE__ . '\\ajax_save_search_display' );

function ajax_save_search_display(): void {
    if ( ! current_user_can( Config::$settings_page_capability ) ) {
        wp_send_json_error( [ 'message' => 'You are not allowed to change site-search settings.' ], 403 );
    }

    hws_require_ajax_nonce_or_error();

    $settings = SearchDisplayFeature::sanitize_settings(
        [
            'style'       => isset( $_POST['style'] ) ? wp_unslash( $_POST['style'] ) : '',
            'accent'      => isset( $_POST['accent'] ) ? wp_unslash( $_POST['accent'] ) : '',
            'placeholder' => isset( $_POST['placeholder'] ) ? wp_unslash( $_POST['placeholder'] ) : '',
        ]
    );

    update_option( SearchDisplayFeature::OPTION_KEY, $settings );

    $styles = SearchDisplayRenderer::styles();
    wp_send_json_success(
        [
            'settings'    => $settings,
            'style_label' => $styles[ $settings['style'] ]['label'] ?? 'Pill',
            'shortcode'   => '[' . SearchDisplayFeature::SHORTCODE . ']',
            'message'     => 'Search design saved. The shortcode now uses this template by default.',
        ]
    );
}

function render_tab_search(): void {
    if ( ! class_exists( SearchDisplayRenderer::class ) ) {
        echo '<div class="notice notice-error"><p>The Hexa WP Core Search Display renderer is unavailable.</p></div>';
        return;
    }

    $settings = SearchDisplayFeature::settings();
    $styles = SearchDisplayRenderer::styles();
    $accent_value = '' !== $settings['accent'] ? $settings['accent'] : '#1b2230';
    $nonce = wp_create_nonce( HWS_AJAX_NONCE );
    CoreUi::render_assets();
    ?>
    <div class="hws-search-display-admin hpc-ui" id="hws-search-display-admin">
        <header class="hws-search-display-heading">
            <div>
                <h2>Site Search</h2>
                <p>Control both the public search-box design and the tightly scoped query behavior used by <code>[hexa_search]</code>.</p>
            </div>
            <div class="hws-search-display-current" aria-live="polite">
                <span>Current design</span>
                <strong data-hws-search-current><?php echo esc_html( $styles[ $settings['style'] ]['label'] ?? 'Pill' ); ?></strong>
            </div>
        </header>

        <section class="hws-search-display-shortcode" aria-labelledby="hws-search-shortcode-title">
            <div>
                <h3 id="hws-search-shortcode-title">Use On The Website</h3>
                <p>The short version follows the saved design. Attributes can override it for one placement.</p>
            </div>
            <div class="hws-search-display-code-row">
                <code data-hws-search-shortcode>[hexa_search]</code>
                <button type="button" class="button" data-hws-search-copy title="Copy shortcode">
                    <span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
                    <span>Copy</span>
                </button>
            </div>
            <p class="hws-search-display-example"><code>[hexa_search style="overlay" accent="#2f6df6" placeholder="Search stories..."]</code></p>
        </section>

        <?php render_search_query_settings(); ?>

        <div data-hws-search-form>
            <fieldset class="hws-search-display-templates">
                <legend class="screen-reader-text">Search display template</legend>
                <?php foreach ( $styles as $style => $definition ) :
                    $input_id = 'hws-search-style-' . $style;
                    $selected = $style === $settings['style'];
                    ?>
                    <article class="hws-search-template<?php echo $selected ? ' is-selected' : ''; ?>" data-hws-search-template="<?php echo esc_attr( $style ); ?>">
                        <div class="hws-search-template-heading">
                            <div>
                                <h3><?php echo esc_html( $definition['label'] ); ?></h3>
                                <p><?php echo esc_html( $definition['description'] ); ?></p>
                            </div>
                            <div class="hws-search-template-choice">
                                <span class="hws-search-template-behavior"><?php echo esc_html( $definition['behavior'] ); ?></span>
                                <code><?php echo esc_html( $style ); ?></code>
                                <label for="<?php echo esc_attr( $input_id ); ?>">
                                    <input id="<?php echo esc_attr( $input_id ); ?>" type="radio" name="style" value="<?php echo esc_attr( $style ); ?>" <?php checked( $selected ); ?>>
                                    <span>Select</span>
                                </label>
                            </div>
                        </div>
                        <div class="hws-search-template-preview" data-hws-search-preview>
                            <?php
                            echo SearchDisplayRenderer::render(
                                [
                                    'style'       => $style,
                                    'accent'      => $settings['accent'],
                                    'placeholder' => $settings['placeholder'],
                                    'id'          => 'hws-search-preview-' . $style,
                                    'hidden_fields' => SearchQueryFeature::marker_fields(),
                                ]
                            ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            ?>
                        </div>
                        <p class="hws-search-template-best"><strong>Best for:</strong> <?php echo esc_html( $definition['best_for'] ); ?></p>
                    </article>
                <?php endforeach; ?>
            </fieldset>

            <section class="hws-search-display-controls" aria-labelledby="hws-search-customize-title">
                <div>
                    <h3 id="hws-search-customize-title">Customize The Default</h3>
                    <p>These values apply to <code>[hexa_search]</code>. Shortcode attributes can override them.</p>
                </div>
                <label>
                    <span>Accent color</span>
                    <span class="hws-search-color-control">
                        <input type="color" name="accent_picker" value="<?php echo esc_attr( $accent_value ); ?>" data-hws-search-accent-picker>
                        <input type="text" name="accent" value="<?php echo esc_attr( $settings['accent'] ); ?>" placeholder="#1b2230" pattern="^#[0-9a-fA-F]{6}$" data-hws-search-accent>
                    </span>
                </label>
                <label>
                    <span>Placeholder text</span>
                    <input type="text" name="placeholder" value="<?php echo esc_attr( $settings['placeholder'] ); ?>" maxlength="120" data-hws-search-placeholder>
                </label>
            </section>

            <div class="hws-search-display-actions">
                <button type="button" class="button button-primary" data-hws-search-save>
                    <span class="dashicons dashicons-saved" aria-hidden="true"></span>
                    <span>Save Search Design</span>
                </button>
                <span class="spinner" data-hws-search-spinner></span>
                <span class="hws-search-display-status" data-hws-search-status role="status" aria-live="polite"></span>
            </div>
        </div>
    </div>

    <style>
    #hws-search-display-admin{width:100%;max-width:1180px;color:#1d2327}
    #hws-search-display-admin *{box-sizing:border-box}
    .hws-search-display-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:24px;margin:0 0 20px;padding:0 0 18px;border-bottom:1px solid #dcdcde}
    .hws-search-display-heading h2{margin:0 0 7px;font-size:22px;line-height:1.25}
    .hws-search-display-heading p{max-width:760px;margin:0;color:#50575e;font-size:14px;line-height:1.6}
    .hws-search-display-current{min-width:150px;padding:10px 12px;border:1px solid #c3c4c7;border-radius:6px;background:#fff}
    .hws-search-display-current span{display:block;margin-bottom:3px;color:#646970;font-size:11px;text-transform:uppercase}
    .hws-search-display-current strong{font-size:14px}
    .hws-search-display-shortcode{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:20px;padding:16px;border:1px solid #c3c4c7;border-left:4px solid #2271b1;border-radius:6px;background:#fff}
    .hws-search-display-shortcode h3,.hws-search-display-controls h3{margin:0 0 4px;font-size:15px}
    .hws-search-display-shortcode p,.hws-search-display-controls p{margin:0;color:#646970}
    .hws-search-display-code-row{display:flex;align-items:center;gap:8px;flex:0 0 auto}
    .hws-search-display-code-row code{padding:8px 11px;border:1px solid #dcdcde;border-radius:4px;background:#f6f7f7;color:#1d2327;font-size:13px}
    .hws-search-display-code-row .button,.hws-search-display-actions .button{display:inline-flex;align-items:center;gap:6px}
    .hws-search-display-code-row .dashicons,.hws-search-display-actions .dashicons{width:16px;height:16px;font-size:16px}
    .hws-search-display-example{flex-basis:100%;display:none}
    .hws-search-display-templates{display:grid;grid-template-columns:minmax(0,1fr);gap:16px;margin:0;padding:0;border:0}
    .hws-search-template{min-width:0;overflow:hidden;border:1px solid #c3c4c7;border-radius:8px;background:#fff;transition:border-color .15s,box-shadow .15s}
    .hws-search-template.is-selected{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1}
    .hws-search-template-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;padding:18px 20px}
    .hws-search-template-heading h3{margin:0 0 7px;font-size:18px;line-height:1.25}
    .hws-search-template-heading p{max-width:720px;margin:0;color:#646970;line-height:1.55}
    .hws-search-template-choice{display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-wrap:wrap}
    .hws-search-template-choice label{display:inline-flex;align-items:center;gap:5px;font-weight:600;cursor:pointer}
    .hws-search-template-choice input{margin:0}
    .hws-search-template-choice code{padding:4px 7px;border:1px solid #dcdcde;border-radius:4px;background:#f6f7f7;color:#50575e}
    .hws-search-template-behavior{padding:4px 9px;border:1px solid #8c8f94;border-radius:999px;color:#3c434a;font-size:11px;font-weight:700;text-transform:uppercase}
    .hws-search-template-preview{display:flex;min-height:150px;align-items:center;padding:30px 46px;border-top:1px solid #2c3338;border-bottom:1px solid #2c3338;background:#10141c}
    .hws-search-template-best{margin:0;padding:14px 20px;color:#50575e}
    .hws-search-display-controls{display:grid;grid-template-columns:minmax(230px,1fr) minmax(190px,.65fr) minmax(240px,1fr);gap:18px;align-items:end;margin-top:20px;padding:18px;border:1px solid #c3c4c7;border-radius:8px;background:#fff}
    .hws-search-display-controls>label{display:grid;gap:7px;font-weight:600}
    .hws-search-display-controls input[type="text"]{width:100%;min-height:38px}
    .hws-search-color-control{display:grid;grid-template-columns:44px minmax(0,1fr);gap:7px}
    .hws-search-color-control input[type="color"]{width:44px;height:38px;margin:0;padding:2px;border:1px solid #8c8f94;border-radius:4px;background:#fff}
    .hws-search-display-actions{display:flex;align-items:center;gap:8px;margin-top:16px}
    .hws-search-display-status{font-weight:600}
    .hws-search-display-status.is-success{color:#008a20}
    .hws-search-display-status.is-error{color:#b32d2e}
    @media(max-width:782px){
      .hws-search-display-heading,.hws-search-display-shortcode,.hws-search-template-heading{align-items:stretch;flex-direction:column}
      .hws-search-display-current{min-width:0}
      .hws-search-display-code-row{justify-content:space-between}
      .hws-search-display-code-row code{min-width:0;overflow-wrap:anywhere}
      .hws-search-template-choice{justify-content:flex-start}
      .hws-search-template-preview{min-height:130px;padding:24px 18px}
      .hws-search-display-controls{grid-template-columns:minmax(0,1fr)}
    }
    </style>
    <script>
    (function(){
      var root=document.getElementById('hws-search-display-admin');
      if(!root||root.dataset.ready==='1')return;
      root.dataset.ready='1';
      var form=root.querySelector('[data-hws-search-form]');
      var save=root.querySelector('[data-hws-search-save]');
      var spinner=root.querySelector('[data-hws-search-spinner]');
      var status=root.querySelector('[data-hws-search-status]');
      var accent=root.querySelector('[data-hws-search-accent]');
      var picker=root.querySelector('[data-hws-search-accent-picker]');
      var placeholder=root.querySelector('[data-hws-search-placeholder]');
      function selectedStyle(){var input=form.querySelector('input[name="style"]:checked');return input?input.value:'pill';}
      function setStatus(message,type){status.textContent=message||'';status.classList.toggle('is-success',type==='success');status.classList.toggle('is-error',type==='error');}
      function syncSelection(){root.querySelectorAll('[data-hws-search-template]').forEach(function(card){card.classList.toggle('is-selected',card.dataset.hwsSearchTemplate===selectedStyle());});}
      function syncPreviews(){var color=(accent.value||'').trim()||'#1b2230';root.querySelectorAll('.hexa-search,.hexa-search-overlay').forEach(function(widget){widget.style.setProperty('--sd-accent',color);});root.querySelectorAll('.hexa-search .sd-input,.hexa-search-overlay .sd-input').forEach(function(input){input.setAttribute('placeholder',placeholder.value||'Search...');});}
      form.addEventListener('change',function(event){if(event.target.name==='style'){syncSelection();setStatus('Preview selected. Save to make it the default.','');}});
      picker.addEventListener('input',function(){accent.value=picker.value;syncPreviews();});
      accent.addEventListener('input',function(){if(/^#[0-9a-fA-F]{6}$/.test(accent.value))picker.value=accent.value;syncPreviews();});
      placeholder.addEventListener('input',syncPreviews);
      var copy=root.querySelector('[data-hws-search-copy]');
      copy.addEventListener('click',function(){
        var value=root.querySelector('[data-hws-search-shortcode]').textContent;
        if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(value).then(function(){setStatus('Shortcode copied.','success');}).catch(function(){setStatus('Copy failed. Select the shortcode and copy it manually.','error');});return;}
        var field=document.createElement('textarea');field.value=value;field.setAttribute('readonly','');field.style.position='fixed';field.style.opacity='0';document.body.appendChild(field);field.select();var copied=false;try{copied=document.execCommand('copy');}catch(error){}document.body.removeChild(field);setStatus(copied?'Shortcode copied.':'Copy failed. Select the shortcode and copy it manually.',copied?'success':'error');
      });
      save.addEventListener('click',function(){
        var body=new URLSearchParams();
        body.set('action','hws_search_display_save');
        body.set('nonce',<?php echo wp_json_encode( $nonce ); ?>);
        body.set('style',selectedStyle());
        body.set('accent',accent.value.trim());
        body.set('placeholder',placeholder.value.trim());
        save.disabled=true;spinner.classList.add('is-active');setStatus('Saving search design...','');
        fetch(<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()})
          .then(function(response){return response.json();})
          .then(function(payload){if(!payload||!payload.success||!payload.data)throw new Error(payload&&payload.data&&payload.data.message?payload.data.message:'Save failed.');root.querySelector('[data-hws-search-current]').textContent=payload.data.style_label||selectedStyle();setStatus(payload.data.message||'Search design saved.','success');})
          .catch(function(error){setStatus(error.message||'Search design could not be saved.','error');})
          .finally(function(){save.disabled=false;spinner.classList.remove('is-active');});
      });
      syncSelection();syncPreviews();
    })();
    </script>
    <?php
}
