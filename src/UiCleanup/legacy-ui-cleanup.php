<?php namespace hws_base_tools;

/**
 * HWS Base Tools - UI Cleanup Settings
 *
 * Hides unnecessary/cluttery UI elements from WordPress admin pages,
 * including user profile and post editor screens.
 *
 * @since 10.7
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Register AJAX handlers
add_action( 'wp_ajax_hws_toggle_ui_cleanup', __NAMESPACE__ . '\\ajax_toggle_ui_cleanup' );
add_action( 'wp_ajax_hws_ui_cleanup_bulk', __NAMESPACE__ . '\\ajax_ui_cleanup_bulk' );

/**
 * UI Cleanup Options Configuration
 *
 * Each option defines:
 *   - label: Display name for the toggle
 *   - description: What it hides
 *   - css_selectors: CSS selectors to hide (on user profile pages)
 *   - js_hide: Text to find via jQuery for hiding (for elements without good CSS selectors)
 *   - js_input_id: Input ID to target the containing row
 *   - default: Default state (false = show, true = hide)
 *   - section: Grouping category
 */
function get_ui_cleanup_options(): array {
    return [
        // ========================================
        // WordPress user/profile and editor screens
        // ========================================
        'hide_admin_color_scheme' => [
            'label'         => 'Admin Color Scheme',
            'description'   => 'Hides the "Administration Color Scheme" picker on user profile',
            'css_selectors' => '.user-admin-color-wrap',
            'default'       => false,
            'section'       => 'wordpress',
        ],
        'hide_language_selector' => [
            'label'         => 'Language Selector',
            'description'   => 'Hides the "Language" dropdown on user profile',
            'css_selectors' => '.user-language-wrap',
            'default'       => false,
            'section'       => 'wordpress',
        ],
        'hide_keyboard_shortcuts' => [
            'label'         => 'Keyboard Shortcuts',
            'description'   => 'Hides the "Keyboard Shortcuts" option for comment moderation',
            'css_selectors' => '.user-comment-shortcuts-wrap',
            'default'       => false,
            'section'       => 'wordpress',
        ],
        'hide_syntax_highlighting' => [
            'label'         => 'Syntax Highlighting',
            'description'   => 'Hides the "Syntax Highlighting" toggle for code editing',
            'css_selectors' => '.user-syntax-highlighting-wrap',
            'default'       => false,
            'section'       => 'wordpress',
        ],

        // ========================================
        // User Profile - Elementor
        // ========================================
        'hide_elementor_ai' => [
            'label'         => 'Elementor AI Section',
            'description'   => 'Hides the "Elementor - AI" settings section on user profile',
            'js_hide'       => 'Elementor - AI',
            'js_input_id'   => 'elementor_enable_ai',
            'default'       => false,
            'section'       => 'elementor',
        ],
        'hide_elementor_notes' => [
            'label'         => 'Elementor Notes Section',
            'description'   => 'Hides the "Elementor Notes" settings section on user profile',
            'js_hide'       => 'Elementor Notes',
            'js_input_id'   => 'elementor_pro_enable_notes_notifications',
            'default'       => false,
            'section'       => 'elementor',
        ],

        'hide_wordfence_app_passwords' => [
            'label'         => 'Application Passwords',
            'description'   => 'Hides the WordPress core "Application Passwords" section on profile and user-edit screens.',
            'css_selectors' => '#application-passwords-section, .application-passwords',
            'js_hide'       => 'Application Passwords',
            'default'       => false,
            'section'       => 'wordpress',
        ],
        'hide_classic_editor_default_editor' => [
            'label'         => 'Classic Editor Default Editor',
            'description'   => 'Hides the Classic Editor plugin default editor selector on profile and user-edit screens.',
            'css_selectors' => '.classic-editor-user-options',
            'js_input_id'   => 'classic-editor-user-settings',
            'default'       => false,
            'section'       => 'wordpress',
        ],
        'hide_simple_local_avatar_rating' => [
            'label'         => 'Local Avatar Rating',
            'description'   => 'Hides the Simple Local Avatar rating controls on profile and user-edit screens.',
            'css_selectors' => 'tr.ratings-row, #simple-local-avatar-ratings',
            'default'       => false,
            'section'       => 'wordpress',
        ],
        'hide_woocommerce_customer_billing_info' => [
            'label'         => 'WooCommerce Customer Billing & Shipping',
            'description'   => 'Completely hides the Customer billing address and Customer shipping address sections on profile and user-edit screens.',
            'css_selectors' => '#fieldset-billing, #fieldset-shipping',
            'js_hide'       => [ 'Customer billing address', 'Customer shipping address' ],
            'default'       => false,
            'section'       => 'woocommerce',
        ],
        'hide_post_editor_comments' => [
            'label'         => 'Post Editor Comments',
            'description'   => 'Hides the Comments metabox on post and page editor screens.',
            'css_selectors' => '#commentsdiv, #commentsdiv-hide, label[for="commentsdiv-hide"]',
            'default'       => false,
            'section'       => 'wordpress',
        ],
        'hide_post_attributes_box' => [
            'label'         => 'Post Attributes Box',
            'description'   => 'Hides the Post Attributes metabox on post and page editor screens.',
            'css_selectors' => '#pageparentdiv, #pageparentdiv-hide, label[for="pageparentdiv-hide"]',
            'default'       => false,
            'section'       => 'wordpress',
        ],
        'hide_litespeed_editor_box' => [
            'label'         => 'LiteSpeed Post Editor Box',
            'description'   => 'Hides the LiteSpeed metabox on post and page editor screens.',
            'css_selectors' => '#litespeed_meta_boxes, #litespeed_meta_boxes-hide, label[for="litespeed_meta_boxes-hide"], .postbox[id*="litespeed"]',
            'default'       => false,
            'section'       => 'wordpress',
        ],
        'collapse_litespeed_editor_box' => [
            'label'         => 'LiteSpeed Collapsed By Default',
            'description'   => 'Keeps the LiteSpeed metabox loaded but forces it closed on post and page editor screens.',
            'default'       => false,
            'section'       => 'wordpress',
        ],
        'collapse_post_attributes_box' => [
            'label'         => 'Post Attributes Collapsed By Default',
            'description'   => 'Keeps the Post Attributes box loaded but forces it closed on post and page editor screens.',
            'default'       => false,
            'section'       => 'wordpress',
        ],

        // ========================================
        // User Profile - Wordfence
        // ========================================
        'hide_wordfence_2fa' => [
            'label'         => 'Wordfence 2FA Section',
            'description'   => 'Hides the "Wordfence Login Security" 2FA settings section',
            'css_selectors' => '#wfls-user-settings',
            'js_hide'       => 'Wordfence Login Security',
            'default'       => false,
            'section'       => 'wordfence',
        ],

        // ========================================
        // Rank Math SEO
        // ========================================
        'hide_rankmath_content_ai' => [
            'label'         => 'Rank Math Content AI',
            'description'   => 'Disables the Content AI module in Rank Math (hides the Content AI panel from the dashboard and post editor)',
            'callback'      => 'apply_rankmath_content_ai_cleanup',
            'default'       => true,
            'section'       => 'rankmath',
        ],
        'hide_rankmath_admin_footer' => [
            'label'         => 'Rank Math Admin Footer',
            'description'   => 'Suppresses the Rank Math admin footer credit and the WordPress version/update footer text.',
            'callback'      => 'apply_rankmath_admin_footer_cleanup',
            'default'       => false,
            'section'       => 'rankmath',
        ],
    ];
}

/**
 * Get the current state of a UI cleanup option
 */
function get_ui_cleanup_option( string $key ): bool {
    $options = get_ui_cleanup_options();
    $default = isset( $options[ $key ]['default'] ) ? $options[ $key ]['default'] : false;
    return (bool) get_option( 'hws_ui_cleanup_' . $key, $default );
}

/**
 * Display the UI Cleanup settings tab
 */
function display_settings_ui_cleanup() {
    $options = get_ui_cleanup_options();

    // Group options by section
    $sections = [
        'wordpress'  => [
            'title' => 'WordPress User & Editor Screens',
            'icon'  => '🔷',
            'items' => [],
        ],
        'elementor'  => [
            'title' => 'Elementor',
            'icon'  => '🟣',
            'items' => [],
        ],
        'wordfence'  => [
            'title' => 'Wordfence',
            'icon'  => '🛡️',
            'items' => [],
        ],
        'woocommerce' => [
            'title' => 'WooCommerce',
            'icon'  => '',
            'items' => [],
        ],
        'rankmath'   => [
            'title' => 'Rank Math SEO',
            'icon'  => '📈',
            'items' => [],
        ],
    ];

    foreach ( $options as $key => $opt ) {
        $section = $opt['section'] ?? 'wordpress';
        if ( isset( $sections[ $section ] ) ) {
            $sections[ $section ]['items'][ $key ] = $opt;
        }
    }
    ?>
    <style>
        /* UI Cleanup Tab Styles */
        .hws-ui-cleanup-intro {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 20px 25px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        .hws-ui-cleanup-intro h3 {
            margin: 0 0 10px;
            font-size: 18px;
            color: #fff;
        }
        .hws-ui-cleanup-intro p {
            margin: 0;
            opacity: 0.9;
            font-size: 14px;
        }

        .hws-ui-section {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        .hws-ui-section-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .hws-ui-section-header h4 {
            margin: 0;
            font-size: 15px;
            font-weight: 600;
        }
        .hws-ui-section-icon {
            font-size: 20px;
        }

        .hws-ui-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.15s;
        }
        .hws-ui-item:last-child {
            border-bottom: none;
        }
        .hws-ui-item:hover {
            background: #fafafa;
        }
        .hws-ui-item-info {
            flex: 1;
        }
        .hws-ui-item-label {
            font-weight: 500;
            font-size: 14px;
            color: #1d2327;
            margin-bottom: 3px;
        }
        .hws-ui-item-desc {
            font-size: 12px;
            color: #646970;
        }

        /* Toggle Switch Styles */
        .hws-ui-toggle {
            position: relative;
            width: 50px;
            height: 26px;
            flex-shrink: 0;
        }
        .hws-ui-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .hws-ui-toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.3s;
            border-radius: 26px;
        }
        .hws-ui-toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .hws-ui-toggle input:checked + .hws-ui-toggle-slider {
            background-color: #2271b1;
        }
        .hws-ui-toggle input:checked + .hws-ui-toggle-slider:before {
            transform: translateX(24px);
        }
        .hws-ui-toggle input:disabled + .hws-ui-toggle-slider {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Status Badge */
        .hws-ui-status {
            display: inline-block;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: 8px;
            font-weight: 500;
        }
        .hws-ui-status-hidden {
            background: #d63638;
            color: #fff;
        }
        .hws-ui-status-visible {
            background: #e0e0e0;
            color: #646970;
        }

        /* Bulk Actions */
        .hws-ui-bulk-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .hws-ui-bulk-btn {
            padding: 8px 16px;
            border-radius: 4px;
            border: 1px solid #2271b1;
            background: #f6f7f7;
            color: #2271b1;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s;
        }
        .hws-ui-bulk-btn:hover {
            background: #2271b1;
            color: #fff;
        }
        .hws-ui-bulk-btn.danger {
            border-color: #d63638;
            color: #d63638;
        }
        .hws-ui-bulk-btn.danger:hover {
            background: #d63638;
            color: #fff;
        }
    </style>

    <div class="hws-ui-cleanup-intro">
        <h3>🧹 UI Cleanup</h3>
        <p>Hide unnecessary or cluttery elements from WordPress profile, user-edit, and post editor screens. Toggle each option to hide or collapse the matching UI element.</p>
    </div>

    <div class="hws-ui-bulk-actions">
        <button type="button" class="hws-ui-bulk-btn" id="hws-ui-hide-all">Hide All</button>
        <button type="button" class="hws-ui-bulk-btn danger" id="hws-ui-show-all">Show All (Reset)</button>
    </div>

    <?php foreach ( $sections as $section_key => $section ) : ?>
        <?php if ( ! empty( $section['items'] ) ) : ?>
            <div class="hws-ui-section" data-section="<?php echo esc_attr( $section_key ); ?>">
                <div class="hws-ui-section-header">
                    <span class="hws-ui-section-icon"><?php echo $section['icon']; ?></span>
                    <h4><?php echo esc_html( $section['title'] ); ?></h4>
                </div>

                <?php foreach ( $section['items'] as $key => $opt ) :
                    $is_hidden = get_ui_cleanup_option( $key );
                ?>
                    <div class="hws-ui-item" data-option="<?php echo esc_attr( $key ); ?>">
                        <div class="hws-ui-item-info">
                            <div class="hws-ui-item-label">
                                <?php echo esc_html( $opt['label'] ); ?>
                                <span class="hws-ui-status <?php echo $is_hidden ? 'hws-ui-status-hidden' : 'hws-ui-status-visible'; ?>">
                                    <?php echo $is_hidden ? 'Hidden' : 'Visible'; ?>
                                </span>
                            </div>
                            <div class="hws-ui-item-desc"><?php echo esc_html( $opt['description'] ); ?></div>
                        </div>
                        <label class="hws-ui-toggle">
                            <input type="checkbox"
                                   class="hws-ui-toggle-input"
                                   data-option="<?php echo esc_attr( $key ); ?>"
                                   <?php checked( $is_hidden ); ?>>
                            <span class="hws-ui-toggle-slider"></span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <script>
    jQuery(document).ready(function($) {
        // Toggle individual option
        $('.hws-ui-toggle-input').on('change', function() {
            var $toggle = $(this);
            var option = $toggle.data('option');
            var enabled = $toggle.is(':checked') ? 1 : 0;
            var $item = $toggle.closest('.hws-ui-item');
            var $status = $item.find('.hws-ui-status');

            // Disable toggle during save
            $toggle.prop('disabled', true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hws_toggle_ui_cleanup',
                    nonce: hwsNonce,
                    option: option,
                    enabled: enabled
                },
                success: function(response) {
                    $toggle.prop('disabled', false);
                    if (response.success) {
                        // Update status badge
                        if (enabled) {
                            $status.removeClass('hws-ui-status-visible').addClass('hws-ui-status-hidden').text('Hidden');
                        } else {
                            $status.removeClass('hws-ui-status-hidden').addClass('hws-ui-status-visible').text('Visible');
                        }
                    } else {
                        // Revert on error
                        $toggle.prop('checked', !enabled);
                        console.error('Failed to save UI cleanup option');
                    }
                },
                error: function() {
                    $toggle.prop('disabled', false);
                    $toggle.prop('checked', !enabled);
                    console.error('AJAX error saving UI cleanup option');
                }
            });
        });

        // Hide All button
        $('#hws-ui-hide-all').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text('Hiding...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hws_ui_cleanup_bulk',
                    nonce: hwsNonce,
                    mode: 'hide_all'
                },
                success: function(response) {
                    $btn.prop('disabled', false).text('Hide All');
                    if (response.success) {
                        // Update all toggles and badges
                        $('.hws-ui-toggle-input').prop('checked', true);
                        $('.hws-ui-status').removeClass('hws-ui-status-visible').addClass('hws-ui-status-hidden').text('Hidden');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Hide All');
                }
            });
        });

        // Show All button
        $('#hws-ui-show-all').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text('Resetting...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hws_ui_cleanup_bulk',
                    nonce: hwsNonce,
                    mode: 'show_all'
                },
                success: function(response) {
                    $btn.prop('disabled', false).text('Show All (Reset)');
                    if (response.success) {
                        // Update all toggles and badges
                        $('.hws-ui-toggle-input').prop('checked', false);
                        $('.hws-ui-status').removeClass('hws-ui-status-hidden').addClass('hws-ui-status-visible').text('Visible');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Show All (Reset)');
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * AJAX: Toggle individual UI cleanup option
 */
function ajax_toggle_ui_cleanup() {
    // Verify nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], HWS_AJAX_NONCE ) ) {
        wp_send_json_error( 'Invalid nonce' );
    }

    // Check permissions
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    // Get option key
    $option = isset( $_POST['option'] ) ? sanitize_key( $_POST['option'] ) : '';
    $enabled = isset( $_POST['enabled'] ) && intval( $_POST['enabled'] ) === 1;

    // Validate option exists
    $options = get_ui_cleanup_options();
    if ( ! isset( $options[ $option ] ) ) {
        wp_send_json_error( 'Invalid option' );
    }

    // Save the option
    update_option( 'hws_ui_cleanup_' . $option, $enabled ? '1' : '0' );

    wp_send_json_success( [ 'option' => $option, 'enabled' => $enabled ] );
}

/**
 * AJAX: Bulk toggle UI cleanup options
 */
function ajax_ui_cleanup_bulk() {
    // Verify nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], HWS_AJAX_NONCE ) ) {
        wp_send_json_error( 'Invalid nonce' );
    }

    // Check permissions
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    $mode = isset( $_POST['mode'] ) ? sanitize_key( $_POST['mode'] ) : '';
    $options = get_ui_cleanup_options();
    $value = ( $mode === 'hide_all' ) ? '1' : '0';

    foreach ( array_keys( $options ) as $key ) {
        update_option( 'hws_ui_cleanup_' . $key, $value );
    }

    wp_send_json_success( [ 'mode' => $mode, 'count' => count( $options ) ] );
}

/**
 * Inject CSS/JS to hide selected UI elements on user profile pages
 *
 * Hooked to admin_head
 */
function inject_ui_cleanup_css() {
    // Run on user/profile screens and classic post editor screens.
    global $pagenow;
    if ( ! in_array( $pagenow, [ 'user-edit.php', 'profile.php', 'post.php', 'post-new.php' ], true ) ) {
        return;
    }

    $options = get_ui_cleanup_options();
    $css_rules = [];
    $js_hide_headers = [];
    $js_hide_inputs = [];
    $collapse_editor_boxes = [];

    foreach ( $options as $key => $opt ) {
        if ( get_ui_cleanup_option( $key ) ) {
            // Add CSS selectors for elements with good class/ID selectors
            if ( ! empty( $opt['css_selectors'] ) ) {
                $css_rules[] = $opt['css_selectors'];
            }
            // Add JS hide targets (for h2 headers that need text matching)
            if ( ! empty( $opt['js_hide'] ) ) {
                foreach ( (array) $opt['js_hide'] as $header_text ) {
                    if ( is_scalar( $header_text ) && '' !== trim( (string) $header_text ) ) {
                        $js_hide_headers[] = trim( (string) $header_text );
                    }
                }
            }
            // Add input IDs to hide their containing rows
            if ( ! empty( $opt['js_input_id'] ) ) {
                $js_hide_inputs[] = $opt['js_input_id'];
            }
        }
    }

    if ( get_ui_cleanup_option( 'collapse_litespeed_editor_box' ) ) {
        $collapse_editor_boxes[] = '#litespeed_meta_boxes';
    }
    if ( get_ui_cleanup_option( 'collapse_post_attributes_box' ) ) {
        $collapse_editor_boxes[] = '#pageparentdiv';
    }

    // Output CSS if any rules exist
    if ( ! empty( $css_rules ) ) {
        echo "<style id='hws-ui-cleanup-css'>\n";
        echo "/* HWS UI Cleanup - Auto-generated */\n";
        echo implode( ",\n", $css_rules ) . " {\n";
        echo "    display: none !important;\n";
        echo "}\n";
        echo "</style>\n";
    }

    // Output JS for elements that need text-based matching
    if ( ! empty( $js_hide_headers ) || ! empty( $js_hide_inputs ) || ! empty( $collapse_editor_boxes ) ) {
        ?>
        <script id='hws-ui-cleanup-js'>
        jQuery(document).ready(function($) {
            <?php foreach ( $js_hide_headers as $text ) :
                $escaped = esc_js( $text );
            ?>
            // Hide: <?php echo $escaped; ?>

            $('h2').filter(function() {
                return $(this).text().trim() === '<?php echo $escaped; ?>';
            }).each(function() {
                var $h2 = $(this);
                // Hide the h2 itself
                $h2.hide();
                // Hide the following table.form-table
                $h2.next('table.form-table').hide();
                // Also hide if wrapped in a th/tr
                $h2.closest('tr').hide();
                // Hide parent th if h2 is inside
                $h2.closest('th').closest('tr').hide();
            });
            <?php endforeach; ?>

            <?php foreach ( $js_hide_inputs as $input_id ) :
                $escaped = esc_js( $input_id );
            ?>
            // Hide input row: <?php echo $escaped; ?>

            $('#<?php echo $escaped; ?>').closest('tr').hide();
            <?php endforeach; ?>

            <?php if ( ! empty( $collapse_editor_boxes ) ) : ?>
            // Force selected editor metaboxes into collapsed mode without removing them.
            var hwsCollapseEditorBoxes = <?php echo wp_json_encode( array_values( $collapse_editor_boxes ) ); ?>;
            var hwsCollapseObserver = null;
            var hwsCollapseObserverTimer = null;

            function hwsResolveEditorPostboxes(selector) {
                return $(selector)
                    .filter(".postbox")
                    .add($(selector).closest(".postbox"))
                    .filter(".postbox");
            }

            function hwsCollapseEditorPostbox(selector) {
                var $boxes = hwsResolveEditorPostboxes(selector);
                $boxes.each(function() {
                    var $box = $(this);
                    $box.addClass("closed");
                    $box.children(".inside").hide();
                    $box.find("> .postbox-header .handlediv, > .handlediv").attr("aria-expanded", "false");
                });
            }

            function hwsRunEditorPostboxCollapse() {
                hwsCollapseEditorBoxes.forEach(hwsCollapseEditorPostbox);
            }

            function hwsScheduleEditorPostboxCollapse() {
                window.clearTimeout(hwsCollapseObserverTimer);
                hwsCollapseObserverTimer = window.setTimeout(hwsRunEditorPostboxCollapse, 25);
            }

            hwsRunEditorPostboxCollapse();
            setTimeout(hwsRunEditorPostboxCollapse, 50);
            setTimeout(hwsRunEditorPostboxCollapse, 300);
            setTimeout(hwsRunEditorPostboxCollapse, 1000);
            setTimeout(hwsRunEditorPostboxCollapse, 2500);
            $(window).on("load", hwsRunEditorPostboxCollapse);
            $(document).on("postbox-toggled", hwsRunEditorPostboxCollapse);
            hwsCollapseEditorBoxes.forEach(function(selector) {
                $(document).on("click", selector + " .handlediv, " + selector + " .postbox-header", hwsScheduleEditorPostboxCollapse);
            });
            if (window.MutationObserver) {
                hwsCollapseObserver = new MutationObserver(hwsScheduleEditorPostboxCollapse);
                hwsCollapseEditorBoxes.forEach(function(selector) {
                    hwsResolveEditorPostboxes(selector).each(function() {
                        hwsCollapseObserver.observe(this, {
                            attributes: true,
                            attributeFilter: ["class", "style"]
                        });
                    });
                });
            }
            <?php endif; ?>

            <?php if ( in_array( 'Wordfence Login Security', $js_hide_headers ) ) : ?>
            // Special handling for Wordfence 2FA section
            $('#wfls-user-settings').hide().next('table.form-table').hide();
            $('table.form-table').has('#wordfence-ls').hide();
            <?php endif; ?>

            <?php if ( in_array( 'Application Passwords', $js_hide_headers ) ) : ?>
            // Special handling for Application Passwords
            $('#application-passwords-section, .application-passwords').hide();
            $('h2').filter(function() {
                return $(this).text().trim() === 'Application Passwords';
            }).each(function() {
                var $h2 = $(this);
                var $section = $h2.closest('#application-passwords-section, .application-passwords');
                if ($section.length) {
                    $section.hide();
                    return;
                }
                $h2.hide();
                $h2.nextUntil('h2').hide();
            });
            <?php endif; ?>
        });
        </script>
        <?php
    }
}
add_action( 'admin_head', __NAMESPACE__ . '\\inject_ui_cleanup_css', 999 );

/**
 * Remove WooCommerce customer address fields only from user-profile screens.
 *
 * @param mixed $fieldsets WooCommerce profile fieldsets.
 * @return mixed
 */
function hws_hide_woocommerce_customer_profile_fieldsets( $fieldsets ) {
    global $pagenow;

    return in_array( (string) $pagenow, [ 'profile.php', 'user-edit.php' ], true ) ? [] : $fieldsets;
}

function apply_woocommerce_customer_billing_cleanup(): void {
    if ( ! get_ui_cleanup_option( 'hide_woocommerce_customer_billing_info' ) ) {
        return;
    }

    add_filter( 'woocommerce_customer_meta_fields', __NAMESPACE__ . '\\hws_hide_woocommerce_customer_profile_fieldsets', PHP_INT_MAX );
}

apply_woocommerce_customer_billing_cleanup();

/**
 * Suppress Rank Math admin footer text without relying on visual hiding.
 *
 * WordPress always prints the #wpfooter wrapper, but these filters remove the
 * Rank Math credit text and the right-side version/update footer content.
 */
function apply_rankmath_admin_footer_cleanup() {
    static $applied = false;

    if ( $applied ) {
        return;
    }

    if ( ! get_ui_cleanup_option( 'hide_rankmath_admin_footer' ) ) {
        return;
    }

    add_filter( 'admin_footer_text', function( $text ) {
        $text = is_string( $text ) ? $text : '';
        if ( stripos( $text, 'rankmath' ) !== false || stripos( $text, 'rank math' ) !== false ) {
            return '';
        }
        return $text;
    }, PHP_INT_MAX );

    add_filter( 'update_footer', '__return_empty_string', PHP_INT_MAX );
    $applied = true;
}

/**
 * Remove Rank Math Content AI from module arrays/options.
 *
 * @param mixed $modules Rank Math module array.
 * @return mixed
 */
function hws_remove_rankmath_content_ai_module( $modules ) {
    if ( ! is_array( $modules ) ) {
        return $modules;
    }

    unset( $modules["content-ai"] );

    foreach ( $modules as $key => $module ) {
        if ( $module === "content-ai" ) {
            unset( $modules[ $key ] );
            continue;
        }

        if ( is_array( $module ) && isset( $module["slug"] ) && $module["slug"] === "content-ai" ) {
            unset( $modules[ $key ] );
        }
    }

    return $modules;
}

/**
 * Apply Rank Math Content AI cleanup
 *
 * Disables the Content AI module by filtering Rank Math's module list.
 * Also hides the Content AI metabox and dashboard widget via CSS.
 * Hooked early so the module never loads.
 *
 * @since 10.8.0
 */
function apply_rankmath_content_ai_cleanup() {
    static $applied = false;

    if ( $applied ) {
        return;
    }

    // — Only act if the toggle is enabled (default: true)
    if ( ! get_ui_cleanup_option( 'hide_rankmath_content_ai' ) ) {
        return;
    }

    // — Filter: Remove Content AI from Rank Math active modules/options.
    add_filter( 'rank_math/modules', __NAMESPACE__ . '\\hws_remove_rankmath_content_ai_module', PHP_INT_MAX );
    add_filter( 'option_rank_math_modules', __NAMESPACE__ . '\\hws_remove_rankmath_content_ai_module', PHP_INT_MAX );
    add_filter( 'default_option_rank_math_modules', __NAMESPACE__ . '\\hws_remove_rankmath_content_ai_module', PHP_INT_MAX );

    // — CSS fallback: Hide Content AI elements from the dashboard and editor
    add_action( 'admin_head', function() {
        echo "<style id='hws-rankmath-content-ai-cleanup'>
/* HWS: Hide Rank Math Content AI UI elements */
.rank-math-content-ai-tab,
.rank-math-content-ai-score,
#rank-math-content-ai-metabox,
#rank_math_metabox_content_ai,
#rank_math_metabox_content_ai-hide,
label[for='rank_math_metabox_content_ai-hide'],
.rank-math-toolbar .content-ai,
[data-module='content-ai'],
.rank-math-content-ai-wrapper,
.rank-math-tab-content-content-ai,
.rank-math-content-ai-data,
.rank-math-content-ai-warning-wrapper,
.rank-math-ca-credits,
#rank-math-ca-wrap,
#rank-math-pro-cta,
[id$='-content-ai-view'],
button[id$='contentAI'],
#wp-admin-bar-rank-math-content-ai-page,
#adminmenu a[href*='rank-math-content-ai'],
#adminmenu a[href*='content-ai'] { display: none !important; }
	</style>\n";
    }, 999 );

    $applied = true;
}

// — Run the Content AI cleanup immediately at include time + admin_init fallback
// — Rank Math may process modules during init, so register the filter as early as possible
if ( get_ui_cleanup_option( 'hide_rankmath_content_ai' ) ) {
    apply_rankmath_content_ai_cleanup();
}
if ( get_ui_cleanup_option( 'hide_rankmath_admin_footer' ) ) {
    apply_rankmath_admin_footer_cleanup();
}
add_action( 'admin_init', function() {
    if ( function_exists( __NAMESPACE__ . '\\get_ui_cleanup_option' ) && get_ui_cleanup_option( 'hide_rankmath_content_ai' ) ) {
        apply_rankmath_content_ai_cleanup();
    }
    if ( function_exists( __NAMESPACE__ . '\\get_ui_cleanup_option' ) && get_ui_cleanup_option( 'hide_rankmath_admin_footer' ) ) {
        apply_rankmath_admin_footer_cleanup();
    }
}, 1 );
