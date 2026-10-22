<?php

namespace hws_base_tools;

use Hexa\PluginCore\SearchQuery\SearchQueryConfiguration;
use Hexa\PluginCore\WpAdminComponents\CoreUi;
use HWS\BaseTools\AdminDashboard\SettingsAutosave;
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
                ? 'Search behavior updated.'
                : 'These settings are off, so the site uses WordPress\'s normal search.',
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
                <p>Configure how the results behind <code>[hws_search_box]</code> match words and which WordPress content is eligible.</p>
            </div>
            <span class="hpc-pill <?php echo $settings['enabled'] ? 'success' : 'warning'; ?>" data-hws-search-query-state>
                <?php echo esc_html( $settings['enabled'] ? 'Engine enabled' : 'Engine disabled' ); ?>
            </span>
        </div>

        <div class="hws-search-query-callout">
            <strong>Strict request scope</strong>
            <span>Only a non-empty public main search query or a Core-marked compatible results template can be changed. Admin, AJAX, REST, cron, feeds, unmarked nested queries, and unrelated requests are rejected before any SQL filter is attached.</span>
        </div>

        <div class="hws-search-query-callout hws-search-query-template-callout">
            <strong>Search-template compatibility</strong>
            <span>Native WordPress result templates and Elementor archive templates work directly; unrelated listing grids remain untouched.</span>
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

        <?php echo SettingsAutosave::notice( 'hws-search-query-notice' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
    <style>
    /* One column, plain lists: each setting is a short heading with its options stacked under it. */
    #hws-search-query-settings{display:flex;flex-direction:column;gap:22px;max-width:760px;min-width:0}
    .hws-search-query-intro h3{font-size:18px;margin:0 0 5px}
    .hws-search-query-intro p{color:#50575e;line-height:1.55;margin:0}
    .hws-search-query-callout{background:#f6f7f7;border-left:3px solid #3157d5;display:flex;flex-direction:column;gap:3px;padding:10px 12px}
    .hws-search-query-template-callout{border-left-color:#24753a}
    .hws-search-query-callout span,.hws-search-query-limit-note span{color:#50575e;line-height:1.5}
    .hws-search-query-top-grid,.hws-search-query-two-column,.hws-search-query-advanced-grid,.hws-search-query-results-row,.hws-search-query-advanced-fields{display:flex;flex-direction:column;gap:22px}
    .hws-search-query-control-block,.hws-search-query-field,.hws-search-query-limit-note{display:flex;flex-direction:column;gap:6px;min-width:0}
    .hws-search-query-label,.hws-search-query-field>span,.hws-search-query-group>legend{color:#1d2327;font-size:14px;font-weight:600;margin:0;padding:0;text-transform:none}
    .hws-search-query-group>legend{margin-bottom:8px}
    .hws-search-query-field select,.hws-search-query-field input[type="number"],.hws-search-query-field textarea{margin:0;max-width:420px;width:100%}
    .hws-search-query-field small,.hws-search-query-check small,.hws-search-query-choice small{color:#646970;line-height:1.45}
    .hws-search-query-group{border:0;margin:0;min-width:0;padding:0}
    .hws-search-query-choice-grid,.hws-search-query-check-grid{display:flex;flex-direction:column;gap:2px}
    .hws-search-query-check-grid.compact{max-height:260px;overflow:auto}
    .hws-search-query-choice,.hws-search-query-check{align-items:flex-start;border-radius:4px;cursor:pointer;display:flex;gap:8px;min-width:0;padding:6px 8px}
    .hws-search-query-choice:hover,.hws-search-query-check:hover{background:#f6f7f7}
    .hws-search-query-choice input,.hws-search-query-check input{margin:2px 0 0}
    .hws-search-query-choice span,.hws-search-query-check>span{display:flex;flex-wrap:wrap;column-gap:8px;row-gap:1px;min-width:0}
    .hws-search-query-choice small,.hws-search-query-check small{flex-basis:100%}
    .hws-search-query-choice strong,.hws-search-query-check strong{color:#1d2327;font-weight:600}
    .hws-search-query-check code{background:transparent;color:#8c8f94;font-size:11px;padding:0}
    .hws-search-query-limit-note strong{color:#1d2327}
    </style>
    <script>
    (function(){
      var root=document.getElementById('hws-search-query-settings');
      if(!root||root.dataset.ready==='1')return;
      root.dataset.ready='1';
      var notice='#hws-search-query-notice';
      var state=root.querySelector('[data-hws-search-query-state]');
      var summary=document.querySelector('[data-hws-search-query-summary]');
      var matching=root.querySelector('[name="word_matching"]');
      function checked(name){return Array.prototype.slice.call(root.querySelectorAll('[name="'+name+'[]"]:checked')).map(function(input){return input.value;});}
      function selected(name,fallback){var input=root.querySelector('[name="'+name+'"]:checked');return input?input.value:fallback;}
      function syncChoices(){root.querySelectorAll('.hws-search-query-choice').forEach(function(card){var input=card.querySelector('input');card.classList.toggle('is-selected',!!input&&input.checked);});var exact=selected('term_logic','all')==='exact';matching.disabled=exact;root.querySelector('[data-hws-search-query-matching-note]').hidden=!exact;}
      root.addEventListener('change',function(event){if(event.target.name==='term_logic')syncChoices();});
      HWSSettingsAutosave.bind(root,{
        notice:notice,
        validate:function(){
          if(!checked('post_types').length){HWSSettingsAutosave.fail(notice,'Select at least one content type.');return false;}
          if(!checked('fields').length&&!checked('taxonomies').length&&!root.querySelector('[name="authors"]').checked&&!root.querySelector('[name="custom_fields"]').value.trim()){HWSSettingsAutosave.fail(notice,'Select at least one searchable field or advanced source.');return false;}
          return true;
        },
        save:function(){
          return HWSSettingsAutosave.post('hws_search_behavior_save',<?php echo wp_json_encode( $nonce ); ?>,{
            enabled:root.querySelector('[name="enabled"]').checked?'1':'0',
            scope:root.querySelector('[name="scope"]').value,
            term_logic:selected('term_logic','all'),
            word_matching:matching.value,
            authors:root.querySelector('[name="authors"]').checked?'1':'0',
            custom_fields:root.querySelector('[name="custom_fields"]').value,
            results_per_page:root.querySelector('[name="results_per_page"]').value,
            orderby:root.querySelector('[name="orderby"]').value,
            post_types:checked('post_types'),fields:checked('fields'),taxonomies:checked('taxonomies')
          }).then(function(data){var enabled=!!(data.settings&&data.settings.enabled);state.textContent=enabled?'Engine enabled':'Engine disabled';state.classList.toggle('success',enabled);state.classList.toggle('warning',!enabled);if(summary)summary.textContent=data.summary||'';return {message:data.message};});
        }
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
