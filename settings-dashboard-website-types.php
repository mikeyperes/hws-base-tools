<?php namespace hws_base_tools;

/**
 * HWS Base Tools - Website Types Settings
 * 
 * Provides preset configurations for different website types.
 * Uses abstract toggle switch components for reusability.
 * 
 * @since 9.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get all website type presets
 * Each preset contains a list of snippet IDs that should be enabled
 * 
 * @return array Array of website type presets
 */
function get_website_type_presets() {
    return [
        'website_settings' => [
            'name'        => 'Website Settings & User Fields',
            'description' => 'Core site configuration: global settings page, user profile fields, and extended user metadata.',
            'icon'        => '⚙️',
            'snippets'    => [
                'register_acf_website_settings',
                'register_user_custom_fields_2025',
                'register_user_custom_fields_additional_2025',
            ],
        ],
        'person_website' => [
            'name'        => 'Person Website',
            'description' => 'Personal website configuration with team members, organizations, and testimonials.',
            'icon'        => '👤',
            'snippets'    => [
                'smp_enable_cpt_teammember',
                'smp_enable_cpt_organization',
                'enable_cpt_testimonial',
                'enable_acf_testimonial',
                'smp_enable_acf_organization',
                'smp_enable_acf_teammember',
            ],
        ],
        // Future presets can be added here
        // 'business_website' => [ ... ],
        // 'ecommerce_website' => [ ... ],
    ];
}

/**
 * Render abstract toggle switch HTML
 * Reusable component for toggle switches throughout the plugin
 * 
 * @param string $id         Unique identifier for the toggle
 * @param string $label      Label text for the toggle
 * @param bool   $checked    Whether the toggle is checked
 * @param string $onclick    JavaScript onclick handler
 * @param string $extra_class Additional CSS class
 * @return string HTML for the toggle switch
 */
function render_toggle_switch( $id, $label = '', $checked = false, $onclick = '', $extra_class = '' ) {
    // Build the checked attribute
    $checked_attr = $checked ? 'checked' : '';
    
    // Build onclick attribute if provided
    $onclick_attr = $onclick ? ' onclick="' . esc_attr( $onclick ) . '"' : '';
    
    // Output the toggle HTML
    $html = '<label class="hws-toggle-switch ' . esc_attr( $extra_class ) . '">';
    $html .= '<input type="checkbox" id="' . esc_attr( $id ) . '" ' . $checked_attr . $onclick_attr . '>';
    $html .= '<span class="hws-toggle-slider"></span>';
    if ( $label ) {
        $html .= '<span class="hws-toggle-label">' . esc_html( $label ) . '</span>';
    }
    $html .= '</label>';
    
    return $html;
}

/**
 * Output toggle switch CSS styles
 * Call this once on pages that use toggle switches
 */
function output_toggle_switch_styles() {
    ?>
    <style>
        /* === HWS Toggle Switch Styles === */
        .hws-toggle-switch {
            position: relative;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
            user-select: none;
        }
        
        .hws-toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
            position: absolute;
        }
        
        .hws-toggle-slider {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
            background-color: #ccc;
            border-radius: 24px;
            transition: background-color 0.3s ease;
        }
        
        .hws-toggle-slider::before {
            content: '';
            position: absolute;
            width: 18px;
            height: 18px;
            left: 3px;
            top: 3px;
            background-color: white;
            border-radius: 50%;
            transition: transform 0.3s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        
        .hws-toggle-switch input:checked + .hws-toggle-slider {
            background-color: #00a32a;
        }
        
        .hws-toggle-switch input:checked + .hws-toggle-slider::before {
            transform: translateX(20px);
        }
        
        .hws-toggle-switch input:focus + .hws-toggle-slider {
            box-shadow: 0 0 0 2px rgba(0, 163, 42, 0.2);
        }
        
        .hws-toggle-switch input:disabled + .hws-toggle-slider {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .hws-toggle-label {
            margin-left: 10px;
            font-size: 13px;
            color: #1d2327;
        }
        
        /* Snippet item with toggle */
        .hws-snippet-item {
            display: flex;
            align-items: flex-start;
            padding: 12px 15px;
            margin-bottom: 10px;
            background: #fff;
            border: 1px solid #dcdcdc;
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        
        .hws-snippet-item:hover {
            border-color: #2271b1;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        }
        
        .hws-snippet-item.deprecated {
            background: #fff8e5;
            border-color: #dba617;
        }
        
        .hws-snippet-toggle {
            flex-shrink: 0;
            margin-right: 15px;
            margin-top: 2px;
        }
        
        .hws-snippet-content {
            flex: 1;
            min-width: 0;
        }
        
        .hws-snippet-header {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 4px;
        }
        
        .hws-snippet-id {
            background: #e0e0e0;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-family: monospace;
            color: #555;
        }
        
        .hws-snippet-name {
            font-weight: 600;
            font-size: 14px;
            color: #1d2327;
        }
        
        .hws-snippet-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .hws-snippet-badge.deprecated {
            background: #d63638;
            color: #fff;
        }
        
        .hws-snippet-description {
            font-size: 13px;
            color: #646970;
            font-style: italic;
            margin-bottom: 4px;
        }
        
        .hws-snippet-details {
            font-size: 12px;
            color: #777;
        }
        
        .hws-snippet-details strong {
            color: #555;
        }
        
        /* Website Type Card */
        .hws-website-type-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .hws-website-type-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .hws-website-type-icon {
            font-size: 32px;
            margin-right: 15px;
        }
        
        .hws-website-type-info h3 {
            margin: 0 0 5px;
            font-size: 18px;
            color: #1d2327;
        }
        
        .hws-website-type-info p {
            margin: 0;
            font-size: 13px;
            color: #646970;
        }
        
        .hws-website-type-snippets {
            margin-bottom: 20px;
        }
        
        .hws-website-type-snippets h4 {
            font-size: 14px;
            color: #1d2327;
            margin: 0 0 10px;
        }
        
        .hws-website-type-actions {
            display: flex;
            gap: 10px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .hws-btn {
            padding: 10px 20px;
            border-radius: 4px;
            border: 1px solid #2271b1;
            background: #2271b1;
            color: #fff;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .hws-btn:hover {
            background: #135e96;
            border-color: #135e96;
        }
        
        .hws-btn-success {
            background: #00a32a;
            border-color: #00a32a;
        }
        
        .hws-btn-success:hover {
            background: #008a21;
            border-color: #008a21;
        }
    </style>
    <?php
}

/**
 * Display Website Types settings page
 */
function display_settings_website_types() {
    // Output the toggle switch styles
    output_toggle_switch_styles();
    
    // Get all presets
    $presets = get_website_type_presets();
    
    // Get all available snippets for reference
    $snippets_acf       = get_snippets( 'acf' );
    $snippets_admin     = get_snippets( 'admin' );
    $snippets_non_admin = get_snippets( 'non_admin' );
    $all_snippets       = array_merge( $snippets_acf, $snippets_admin, $snippets_non_admin );
    
    // Create a lookup array by snippet ID
    $snippets_by_id = [];
    foreach ( $all_snippets as $snippet ) {
        $snippets_by_id[ $snippet['id'] ] = $snippet;
    }
    ?>
    
    <div class="hws-panel">
        <div class="hws-panel-header">🌐 Website Types</div>
        <div class="hws-panel-body">
            <p style="margin-top:0; color:#646970;">
                Select a website type to quickly enable all recommended snippets for that configuration.
            </p>
            
            <?php foreach ( $presets as $preset_id => $preset ) : ?>
                <div class="hws-website-type-card" data-preset="<?php echo esc_attr( $preset_id ); ?>">
                    <div class="hws-website-type-header">
                        <span class="hws-website-type-icon"><?php echo $preset['icon']; ?></span>
                        <div class="hws-website-type-info">
                            <h3><?php echo esc_html( $preset['name'] ); ?></h3>
                            <p><?php echo esc_html( $preset['description'] ); ?></p>
                        </div>
                    </div>
                    
                    <div class="hws-website-type-snippets">
                        <h4>Included Snippets:</h4>
                        <?php foreach ( $preset['snippets'] as $snippet_id ) : 
                            // Get snippet details from lookup
                            $snippet = isset( $snippets_by_id[ $snippet_id ] ) ? $snippets_by_id[ $snippet_id ] : null;
                            if ( ! $snippet ) continue;
                            
                            // Check if snippet is enabled
                            $is_enabled = get_option( $snippet_id, false );
                            
                            // Check if deprecated
                            $is_deprecated = isset( $snippet['deprecated'] ) && $snippet['deprecated'];
                            
                            // Get info text
                            $info_text = '';
                            if ( isset( $snippet['info'] ) ) {
                                if ( is_callable( $snippet['info'] ) ) {
                                    $info_text = call_user_func( $snippet['info'] );
                                } elseif ( is_string( $snippet['info'] ) ) {
                                    $info_text = $snippet['info'];
                                }
                            }
                        ?>
                            <div class="hws-snippet-item <?php echo $is_deprecated ? 'deprecated' : ''; ?>">
                                <div class="hws-snippet-toggle">
                                    <?php echo render_toggle_switch(
                                        'toggle-' . $snippet_id,
                                        '',
                                        $is_enabled,
                                        'window.' . __NAMESPACE__ . '.toggleSnippet(\'' . esc_attr( $snippet_id ) . '\')'
                                    ); ?>
                                </div>
                                <div class="hws-snippet-content">
                                    <div class="hws-snippet-header">
                                        <code class="hws-snippet-id"><?php echo esc_html( $snippet_id ); ?></code>
                                        <span class="hws-snippet-name"><?php echo esc_html( $snippet['name'] ); ?></span>
                                        <?php if ( $is_deprecated ) : ?>
                                            <span class="hws-snippet-badge deprecated">Pending Delete</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="hws-snippet-description"><?php echo esc_html( $snippet['description'] ); ?></div>
                                    <?php if ( $info_text ) : ?>
                                        <div class="hws-snippet-details">
                                            <strong>Details:</strong><br>
                                            <?php echo wp_kses_post( $info_text ); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="hws-website-type-actions">
                        <button type="button" class="hws-btn hws-btn-success" 
                                onclick="window.<?php echo __NAMESPACE__; ?>.enablePreset('<?php echo esc_attr( $preset_id ); ?>')">
                            ✅ Enable All Snippets
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script type="text/javascript">
    (function($) {
        // Ensure namespace exists
        window.<?php echo __NAMESPACE__; ?> = window.<?php echo __NAMESPACE__; ?> || {};
        
        /**
         * Enable all snippets for a preset
         * @param {string} presetId The preset identifier
         */
        window.<?php echo __NAMESPACE__; ?>.enablePreset = function(presetId) {
            // Get all snippet IDs from the preset card
            var $card = $('[data-preset="' + presetId + '"]');
            var snippetIds = [];
            
            // Collect all snippet IDs from toggles
            $card.find('.hws-snippet-toggle input[type="checkbox"]').each(function() {
                var id = $(this).attr('id').replace('toggle-', '');
                snippetIds.push(id);
            });
            
            if (snippetIds.length === 0) {
                alert('No snippets found for this preset.');
                return;
            }
            
            // Confirm action
            if (!confirm('Enable all ' + snippetIds.length + ' snippets for this website type?')) {
                return;
            }
            
            // Enable each snippet via AJAX
            var completed = 0;
            var errors = [];
            
            snippetIds.forEach(function(snippetId) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: '<?php echo __NAMESPACE__; ?>_toggle_snippet',
                        snippet_id: snippetId,
                        enable: true,
                        nonce: hwsNonce
                    },
                    success: function(response) {
                        completed++;
                        if (!response.success) {
                            errors.push(snippetId + ': ' + response.data);
                        } else {
                            // Update the toggle UI
                            $('#toggle-' + snippetId).prop('checked', true);
                        }
                        
                        // Check if all done
                        if (completed === snippetIds.length) {
                            if (errors.length > 0) {
                                alert('Completed with some errors:\n' + errors.join('\n'));
                            } else {
                                alert('✅ All ' + snippetIds.length + ' snippets have been enabled!');
                            }
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        completed++;
                        errors.push(snippetId + ': AJAX error');
                        
                        if (completed === snippetIds.length) {
                            alert('Completed with errors:\n' + errors.join('\n'));
                        }
                    }
                });
            });
        };
    })(jQuery);
    </script>
    
    <?php
}
?>
