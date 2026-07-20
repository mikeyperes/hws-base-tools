<?php

namespace hws_base_tools;

use Hexa\PluginCore\SearchQuery\SearchQueryConfiguration;
use Hexa\PluginCore\WpAdminComponents\CoreUi;
use HWS\BaseTools\FrontendContent\SearchQueryFeature;

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_hws_search_behavior_save', __NAMESPACE__ . '\\ajax_save_search_behavior' );

function ajax_save_search_behavior(): void {
    if ( ! current_user_can( Config::$settings_page_capability ) ) {
        wp_send_json_error( [ 'message' => 'You are not allowed to change site-search behavior.' ], 403 );
    }

    hws_require_ajax_nonce_or_error();

    $settings = SearchQueryFeature::sanitize_settings(
        [
            'enabled'          => isset( $_POST['enabled'] ) ? wp_unslash( $_POST['enabled'] ) : '0',
            'scope'            => isset( $_POST['scope'] ) ? wp_unslash( $_POST['scope'] ) : '',
            'term_logic'       => isset( $_POST['term_logic'] ) ? wp_unslash( $_POST['term_logic'] ) : '',
            'word_matching'    => isset( $_POST['word_matching'] ) ? wp_unslash( $_POST['word_matching'] ) : '',
            'post_types'       => isset( $_POST['post_types'] ) ? (array) wp_unslash( $_POST['post_types'] ) : [],
            'fields'           => isset( $_POST['fields'] ) ? (array) wp_unslash( $_POST['fields'] ) : [],
            'taxonomies'       => isset( $_POST['taxonomies'] ) ? (array) wp_unslash( $_POST['taxonomies'] ) : [],
            'authors'          => isset( $_POST['authors'] ) ? wp_unslash( $_POST['authors'] ) : '0',
            'custom_fields'    => isset( $_POST['custom_fields'] ) ? wp_unslash( $_POST['custom_fields'] ) : '',
            'results_per_page' => isset( $_POST['results_per_page'] ) ? wp_unslash( $_POST['results_per_page'] ) : '0',
            'orderby'          => isset( $_POST['orderby'] ) ? wp_unslash( $_POST['orderby'] ) : '',
        ]
    );

    update_option( SearchQueryFeature::OPTION_KEY, $settings );

    wp_send_json_success(
        [
            'settings' => $settings,
            'summary'  => search_behavior_summary( $settings ),
            'message'  => $settings['enabled']
                ? 'Search behavior saved and enabled.'
                : 'Search behavior saved. The enhanced query engine is disabled.',
        ]
    );
}

/** @param array<string,mixed> $settings */
function search_behavior_summary( array $settings ): string {
    if ( empty( $settings['enabled'] ) ) {
        return 'Disabled';
    }

    $logic = SearchQueryConfiguration::term_logic_options();
    $matching = SearchQueryConfiguration::word_matching_options();
    $logic_label = $logic[ $settings['term_logic'] ]['label'] ?? 'All words';
    if ( 'exact' === $settings['term_logic'] ) {
        return $logic_label;
    }

    return $logic_label . ' / ' . ( $matching[ $settings['word_matching'] ]['label'] ?? 'Contains wildcard' );
}

function render_search_query_settings(): void {
    if ( ! class_exists( SearchQueryConfiguration::class ) || ! class_exists( CoreUi::class ) ) {
        echo '<div class="notice notice-error"><p>The Hexa WP Core Search Query tools are unavailable.</p></div>';
        return;
    }

    $settings = SearchQueryFeature::settings();
    $post_types = SearchQueryFeature::post_type_choices();
    $taxonomies = SearchQueryFeature::taxonomy_choices();
    $term_logic_options = SearchQueryConfiguration::term_logic_options();
    $matching_options = SearchQueryConfiguration::word_matching_options();
    $field_options = SearchQueryConfiguration::field_options();
    $scope_options = SearchQueryConfiguration::scope_options();
    $ordering_options = SearchQueryConfiguration::ordering_options();
    $nonce = wp_create_nonce( HWS_AJAX_NONCE );
    $summary = search_behavior_summary( $settings );

    ob_start();
    ?>
    <div class="hws-search-query-settings" id="hws-search-query-settings" data-hws-search-query-settings>
        <div class="hws-search-query-intro">
            <div>
                <h3>Search Query Behavior</h3>
                <p>Configure how the results behind <code>[hexa_search]</code> match words and which WordPress content is eligible.</p>
            </div>
            <span class="hpc-pill <?php echo $settings['enabled'] ? 'success' : 'warning'; ?>" data-hws-search-query-state>
                <?php echo esc_html( $settings['enabled'] ? 'Engine enabled' : 'Engine disabled' ); ?>
            </span>
        </div>

        <div class="hws-search-query-callout">
            <strong>Strict request scope</strong>
            <span>Only a non-empty public main search query can be changed. Admin, AJAX, REST, cron, feeds, nested queries, and unrelated requests are rejected before any SQL filter is attached.</span>
        </div>

        <div class="hws-search-query-top-grid">
            <div class="hws-search-query-control-block">
                <span class="hws-search-query-label">Enhanced query engine</span>
                <?php
                echo CoreUi::toggle(
                    'enabled',
                    (bool) $settings['enabled'],
                    'Enable configured search behavior',
                    [
                        'id'      => 'hws-search-query-enabled',
                        'tooltip' => 'Disabled is the compatibility-safe default. The visual search templates continue to work with normal WordPress search.',
                    ]
                ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                ?>
            </div>
            <label class="hws-search-query-field" for="hws-search-query-scope">
                <span>Apply behavior to</span>
                <select id="hws-search-query-scope" name="scope">
                    <?php foreach ( $scope_options as $value => $label ) : ?>
                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['scope'], $value ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
                <small>Shortcode-only is recommended and uses a hidden request marker generated by Hexa WP Core.</small>
            </label>
        </div>

        <fieldset class="hws-search-query-group">
            <legend>How should multiple words work?</legend>
            <div class="hws-search-query-choice-grid">
                <?php foreach ( $term_logic_options as $value => $option ) : ?>
                    <label class="hws-search-query-choice<?php echo $settings['term_logic'] === $value ? ' is-selected' : ''; ?>">
                        <input type="radio" name="term_logic" value="<?php echo esc_attr( $value ); ?>" <?php checked( $settings['term_logic'], $value ); ?>>
                        <span>
                            <strong><?php echo esc_html( $option['label'] ); ?></strong>
                            <small><?php echo esc_html( $option['description'] ); ?></small>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <div class="hws-search-query-two-column">
            <label class="hws-search-query-field" for="hws-search-query-matching">
                <span>How flexible should each word be?</span>
                <select id="hws-search-query-matching" name="word_matching">
                    <?php foreach ( $matching_options as $value => $option ) : ?>
                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['word_matching'], $value ); ?>><?php echo esc_html( $option['label'] ); ?>: <?php echo esc_html( $option['example'] ); ?></option>
                    <?php endforeach; ?>
                </select>
                <small data-hws-search-query-matching-note>Exact phrase ignores this setting because the complete phrase is matched together.</small>
            </label>
            <label class="hws-search-query-field" for="hws-search-query-orderby">
                <span>Result order</span>
                <select id="hws-search-query-orderby" name="orderby">
                    <?php foreach ( $ordering_options as $value => $label ) : ?>
                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['orderby'], $value ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
                <small>Relevance keeps WordPress title-based relevance ordering; the other modes are deterministic.</small>
            </label>
        </div>

        <div class="hws-search-query-two-column hws-search-query-sources">
            <fieldset class="hws-search-query-group">
                <legend>Search these content types</legend>
                <div class="hws-search-query-check-grid">
                    <?php foreach ( $post_types as $post_type => $label ) : ?>
                        <label class="hws-search-query-check">
                            <input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $post_type ); ?>" <?php checked( in_array( $post_type, $settings['post_types'], true ) ); ?>>
                            <span><strong><?php echo esc_html( $label ); ?></strong><code><?php echo esc_html( $post_type ); ?></code></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <fieldset class="hws-search-query-group">
                <legend>Search inside these post fields</legend>
                <div class="hws-search-query-check-grid">
                    <?php foreach ( $field_options as $field => $option ) : ?>
                        <label class="hws-search-query-check">
                            <input type="checkbox" name="fields[]" value="<?php echo esc_attr( $field ); ?>" <?php checked( in_array( $field, $settings['fields'], true ) ); ?>>
                            <span><strong><?php echo esc_html( $option['label'] ); ?></strong><small><?php echo esc_html( $option['description'] ); ?></small></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        </div>

        <?php
        ob_start();
        ?>
        <div class="hws-search-query-advanced-grid">
            <fieldset class="hws-search-query-group">
                <legend>Taxonomy term names</legend>
                <?php if ( [] === $taxonomies ) : ?>
                    <p class="hpc-small">No public taxonomies are registered.</p>
                <?php else : ?>
                    <div class="hws-search-query-check-grid compact">
                        <?php foreach ( $taxonomies as $taxonomy => $label ) : ?>
                            <label class="hws-search-query-check">
                                <input type="checkbox" name="taxonomies[]" value="<?php echo esc_attr( $taxonomy ); ?>" <?php checked( in_array( $taxonomy, $settings['taxonomies'], true ) ); ?>>
                                <span><strong><?php echo esc_html( $label ); ?></strong><code><?php echo esc_html( $taxonomy ); ?></code></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </fieldset>
            <div class="hws-search-query-advanced-fields">
                <div class="hws-search-query-control-block">
                    <span class="hws-search-query-label">Post author</span>
                    <?php
                    echo CoreUi::toggle(
                        'authors',
                        (bool) $settings['authors'],
                        'Search author display names',
                        [ 'id' => 'hws-search-query-authors' ]
                    ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    ?>
                </div>
                <label class="hws-search-query-field" for="hws-search-query-custom-fields">
                    <span>Selected custom field keys</span>
                    <textarea id="hws-search-query-custom-fields" name="custom_fields" rows="3" placeholder="_sku, location, publication_name"><?php echo esc_textarea( implode( ', ', $settings['custom_fields'] ) ); ?></textarea>
                    <small>Comma- or space-separated keys only. Keep this list short; each selected source adds database work.</small>
                </label>
            </div>
        </div>
        <?php
        $advanced_body = (string) ob_get_clean();
        echo CoreUi::detail_card(
            [
                'title'       => 'Advanced Search Sources',
                'body_html'   => $advanced_body,
                'open'        => false,
                'persist_key' => 'hws-search-query-advanced-sources',
                'meta_html'   => CoreUi::pill( 'Opt-in for performance', 'warning' ),
            ]
        ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>

        <div class="hws-search-query-results-row">
            <label class="hws-search-query-field" for="hws-search-query-results-per-page">
                <span>Results per page</span>
                <input id="hws-search-query-results-per-page" type="number" name="results_per_page" value="<?php echo esc_attr( (string) $settings['results_per_page'] ); ?>" min="0" max="100" step="1">
                <small>Use 0 to keep the site-wide WordPress setting. Maximum: 100.</small>
            </label>
            <div class="hws-search-query-limit-note">
                <strong>Query guardrails</strong>
                <span>At most eight unique terms and 80 characters per term are compiled. Quoted phrases remain one term.</span>
            </div>
        </div>

        <?php
        ob_start();
        ?>
        <div class="hws-search-audit-table-wrap">
            <table class="widefat striped hws-search-audit-table">
                <thead><tr><th>Plugin audited</th><th>Matching criteria</th><th>Search sources</th><th>Decision carried into HWS</th></tr></thead>
                <tbody>
                    <?php foreach ( SearchQueryFeature::research_audit() as $row ) : ?>
                        <tr>
                            <th scope="row"><?php echo esc_html( $row['plugin'] ); ?><small>v<?php echo esc_html( $row['version'] ); ?></small></th>
                            <td><?php echo esc_html( $row['matching'] ); ?></td>
                            <td><?php echo esc_html( $row['sources'] ); ?></td>
                            <td><?php echo esc_html( $row['lesson'] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="hpc-small">Audit source: official WordPress.org stable ZIPs and their settings/query implementations. Full comparison: <code>docs/search-query-audit.md</code>.</p>
        </div>
        <?php
        $audit_body = (string) ob_get_clean();
        echo CoreUi::detail_card(
            [
                'title'       => 'Five-Plugin Search Criteria Audit',
                'body_html'   => $audit_body,
                'open'        => false,
                'persist_key' => 'hws-search-query-plugin-audit',
                'meta_html'   => CoreUi::pill( '5 official packages', 'dark' ),
            ]
        ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>

        <div class="hws-search-query-actions">
            <button type="button" class="button button-primary" data-hws-search-query-save>
                <span class="dashicons dashicons-saved" aria-hidden="true"></span>
                <span>Save Search Behavior</span>
            </button>
            <span class="spinner" data-hws-search-query-spinner></span>
            <span class="hws-search-query-status" data-hws-search-query-status role="status" aria-live="polite"></span>
        </div>
    </div>
    <style>
    #hws-search-query-settings{display:grid;gap:18px;min-width:0}
    .hws-search-query-intro{align-items:flex-start;display:flex;gap:18px;justify-content:space-between}
    .hws-search-query-intro h3{font-size:18px;margin:0 0 5px}
    .hws-search-query-intro p{color:#50575e;line-height:1.55;margin:0}
    .hws-search-query-callout{align-items:flex-start;background:#f5f8fc;border:1px solid #cad5e4;border-left:4px solid #3157d5;border-radius:6px;display:grid;gap:4px;padding:12px 14px}
    .hws-search-query-callout span,.hws-search-query-limit-note span{color:#50575e;line-height:1.5}
    .hws-search-query-top-grid,.hws-search-query-two-column,.hws-search-query-advanced-grid,.hws-search-query-results-row{display:grid;gap:16px;grid-template-columns:repeat(2,minmax(0,1fr))}
    .hws-search-query-control-block,.hws-search-query-field,.hws-search-query-limit-note{background:#fff;border:1px solid #d5dce5;border-radius:6px;display:grid;gap:8px;min-width:0;padding:14px}
    .hws-search-query-label,.hws-search-query-field>span{color:#253650;font-size:12px;font-weight:800;text-transform:uppercase}
    .hws-search-query-field select,.hws-search-query-field input[type="number"],.hws-search-query-field textarea{margin:0;max-width:none;width:100%}
    .hws-search-query-field small,.hws-search-query-check small{color:#646970;line-height:1.45}
    .hws-search-query-group{border:0;margin:0;min-width:0;padding:0}
    .hws-search-query-group>legend{color:#172033;font-size:14px;font-weight:800;margin:0 0 10px;padding:0}
    .hws-search-query-choice-grid{display:grid;gap:10px;grid-template-columns:repeat(3,minmax(0,1fr))}
    .hws-search-query-choice{align-items:flex-start;background:#fff;border:1px solid #cbd5e1;border-radius:6px;cursor:pointer;display:flex;gap:10px;min-width:0;padding:13px}
    .hws-search-query-choice.is-selected{border-color:#3157d5;box-shadow:0 0 0 1px #3157d5}
    .hws-search-query-choice input{margin:3px 0 0}
    .hws-search-query-choice span{display:grid;gap:4px;min-width:0}
    .hws-search-query-choice strong{color:#172033}
    .hws-search-query-choice small{color:#59677a;line-height:1.45}
    .hws-search-query-sources{align-items:start}
    .hws-search-query-sources>.hws-search-query-group{background:#f8fafc;border:1px solid #d5dce5;border-radius:6px;padding:14px}
    .hws-search-query-check-grid{display:grid;gap:8px;grid-template-columns:repeat(2,minmax(0,1fr))}
    .hws-search-query-check-grid.compact{max-height:290px;overflow:auto;padding-right:4px}
    .hws-search-query-check{align-items:flex-start;background:#fff;border:1px solid #e1e6ed;border-radius:6px;display:flex;gap:8px;min-width:0;padding:9px 10px}
    .hws-search-query-check input{margin:3px 0 0}
    .hws-search-query-check>span{display:grid;gap:3px;min-width:0}
    .hws-search-query-check code{background:transparent;color:#697586;font-size:11px;padding:0}
    .hws-search-query-advanced-grid{align-items:start}
    .hws-search-query-advanced-fields{display:grid;gap:12px}
    .hws-search-query-results-row{align-items:stretch}
    .hws-search-query-limit-note{align-content:center;background:#f8fafc}
    .hws-search-query-limit-note strong{color:#253650}
    .hws-search-audit-table-wrap{max-width:100%;overflow-x:auto}
    .hws-search-audit-table{min-width:860px}
    .hws-search-audit-table th,.hws-search-audit-table td{line-height:1.45;vertical-align:top}
    .hws-search-audit-table th small{color:#697586;display:block;font-weight:400;margin-top:3px}
    .hws-search-query-actions{align-items:center;border-top:1px solid #d9e0ea;display:flex;gap:9px;padding-top:14px}
    .hws-search-query-actions .button{align-items:center;display:inline-flex;gap:6px}
    .hws-search-query-actions .dashicons{font-size:16px;height:16px;width:16px}
    .hws-search-query-status{font-weight:600}
    .hws-search-query-status.is-success{color:#16803c}
    .hws-search-query-status.is-error{color:#b42336}
    @media(max-width:900px){.hws-search-query-choice-grid,.hws-search-query-top-grid,.hws-search-query-two-column,.hws-search-query-advanced-grid,.hws-search-query-results-row{grid-template-columns:minmax(0,1fr)}}
    @media(max-width:600px){.hws-search-query-check-grid{grid-template-columns:minmax(0,1fr)}.hws-search-query-intro{align-items:stretch;flex-direction:column}}
    </style>
    <script>
    (function(){
      var root=document.getElementById('hws-search-query-settings');
      if(!root||root.dataset.ready==='1')return;
      root.dataset.ready='1';
      var save=root.querySelector('[data-hws-search-query-save]');
      var spinner=root.querySelector('[data-hws-search-query-spinner]');
      var status=root.querySelector('[data-hws-search-query-status]');
      var state=root.querySelector('[data-hws-search-query-state]');
      var summary=document.querySelector('[data-hws-search-query-summary]');
      var matching=root.querySelector('[name="word_matching"]');
      function checked(name){return Array.prototype.slice.call(root.querySelectorAll('[name="'+name+'[]"]:checked')).map(function(input){return input.value;});}
      function selected(name,fallback){var input=root.querySelector('[name="'+name+'"]:checked');return input?input.value:fallback;}
      function setStatus(message,type){status.textContent=message||'';status.classList.toggle('is-success',type==='success');status.classList.toggle('is-error',type==='error');}
      function syncChoices(){root.querySelectorAll('.hws-search-query-choice').forEach(function(card){var input=card.querySelector('input');card.classList.toggle('is-selected',!!input&&input.checked);});var exact=selected('term_logic','all')==='exact';matching.disabled=exact;root.querySelector('[data-hws-search-query-matching-note]').hidden=!exact;}
      root.addEventListener('change',function(event){if(event.target.name==='term_logic')syncChoices();});
      save.addEventListener('click',function(){
        var postTypes=checked('post_types');var fields=checked('fields');
        if(!postTypes.length){setStatus('Select at least one content type.','error');return;}
        if(!fields.length&&!checked('taxonomies').length&&!root.querySelector('[name="authors"]').checked&&!root.querySelector('[name="custom_fields"]').value.trim()){setStatus('Select at least one searchable field or advanced source.','error');return;}
        var body=new URLSearchParams();
        body.set('action','hws_search_behavior_save');body.set('nonce',<?php echo wp_json_encode( $nonce ); ?>);
        body.set('enabled',root.querySelector('[name="enabled"]').checked?'1':'0');
        body.set('scope',root.querySelector('[name="scope"]').value);body.set('term_logic',selected('term_logic','all'));
        body.set('word_matching',matching.value);body.set('authors',root.querySelector('[name="authors"]').checked?'1':'0');
        body.set('custom_fields',root.querySelector('[name="custom_fields"]').value);body.set('results_per_page',root.querySelector('[name="results_per_page"]').value);body.set('orderby',root.querySelector('[name="orderby"]').value);
        postTypes.forEach(function(value){body.append('post_types[]',value);});fields.forEach(function(value){body.append('fields[]',value);});checked('taxonomies').forEach(function(value){body.append('taxonomies[]',value);});
        save.disabled=true;spinner.classList.add('is-active');setStatus('Saving search behavior...','');
        fetch(<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()})
          .then(function(response){return response.json();})
          .then(function(payload){if(!payload||!payload.success||!payload.data)throw new Error(payload&&payload.data&&payload.data.message?payload.data.message:'Save failed.');var enabled=!!payload.data.settings.enabled;state.textContent=enabled?'Engine enabled':'Engine disabled';state.classList.toggle('success',enabled);state.classList.toggle('warning',!enabled);if(summary)summary.textContent=payload.data.summary||'';setStatus(payload.data.message||'Search behavior saved.','success');})
          .catch(function(error){setStatus(error.message||'Search behavior could not be saved.','error');})
          .finally(function(){save.disabled=false;spinner.classList.remove('is-active');});
      });
      syncChoices();
    })();
    </script>
    <?php
    $body = (string) ob_get_clean();
    $meta_class = $settings['enabled'] ? 'success' : 'warning';
    $meta = '<span class="hpc-pill ' . esc_attr( $meta_class ) . '" data-hws-search-query-summary>' . esc_html( $summary ) . '</span>';

    echo CoreUi::collapsible(
        [
            'title'       => 'Search Behavior & Content Sources',
            'body_html'   => $body,
            'open'        => true,
            'persist_key' => 'hws-search-query-behavior',
            'meta_html'   => $meta,
            'class'       => 'hws-search-query-panel',
        ]
    ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
