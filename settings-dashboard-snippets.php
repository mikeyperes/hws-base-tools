<?php namespace hws_base_tools;

/**
 * HWS Base Tools - Snippets Settings Dashboard
 * 
 * Displays all available snippets with toggle switches for enabling/disabling.
 * Uses abstract toggle switch components from settings-dashboard-website-types.php
 * 
 * @since 9.4
 */

function display_settings_snippets() {
    // Get toggle styles from website types (shared component)
    if ( function_exists( __NAMESPACE__ . '\\output_toggle_switch_styles' ) ) {
        output_toggle_switch_styles();
    }
    ?>

    <style>
        /* Snippets panel specific styles */
        .panel-settings-snippets {
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            margin-bottom: 20px;
            background-color: #f7f7f7;
            padding: 10px 15px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            font-size: 14px;
        }

        .panel-settings-snippets .panel-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }

        .panel-settings-snippets .panel-content {
            padding: 10px 0;
        }

        .hws-snippets-deprecated-notice {
            margin: 0 0 16px;
            padding: 12px 14px;
            border-left: 4px solid #dba617;
            background: #fff8e5;
            color: #664d03;
        }
    </style>

    <!-- Snippets Status Panel -->
    <div class="panel panel-settings-snippets">
        <h2 class="panel-title">Snippets (Deprecated)</h2>
        <div class="panel-content">
            <div class="hws-snippets-deprecated-notice">
                <strong>Deprecated legacy tab.</strong>
                This tab is retained for existing snippet toggles and backwards compatibility. New structured controls belong in the Features tab.
            </div>
            <!-- Snippet Actions and Status -->
            <div style="margin-bottom: 15px;">
                <h3>Available Snippets:</h3>
                <div style="margin-left: 15px;">
                    <?php
                    // Get all snippets from the three categories
                    $snippets_acf       = get_snippets( 'acf' );
                    $snippets_admin     = get_snippets( 'admin' );
                    $snippets_non_admin = get_snippets( 'non_admin' );

                    // Merge all three arrays into one
                    $all_snippets = array_merge( $snippets_acf, $snippets_admin, $snippets_non_admin );

                    // Loop through all snippets and display them with a toggle switch
                    foreach ( $all_snippets as $snippet ) {
                        // Get the current state of the option from the database
                        $is_enabled = get_option( $snippet['id'], false );

                        // Check if snippet is deprecated
                        $is_deprecated = isset( $snippet['deprecated'] ) && $snippet['deprecated'];

                        // Ensure info is a string (fallback to empty)
                        $info_text = '';
                        if ( isset( $snippet['info'] ) ) {
                            if ( is_callable( $snippet['info'] ) ) {
                                $info_text = call_user_func( $snippet['info'] );
                            } elseif ( is_string( $snippet['info'] ) ) {
                                $info_text = $snippet['info'];
                            }
                        }

                        // Build deprecated class and badge
                        $deprecated_class = $is_deprecated ? ' deprecated' : '';
                        $deprecated_badge = $is_deprecated 
                            ? '<span class="hws-snippet-badge deprecated">Pending Delete</span>' 
                            : '';

                        // Render the toggle switch
                        $toggle_html = '';
                        if ( function_exists( __NAMESPACE__ . '\\render_toggle_switch' ) ) {
                            $toggle_html = render_toggle_switch(
                                'toggle-' . $snippet['id'],
                                '',
                                $is_enabled,
                                'window.' . __NAMESPACE__ . '.toggleSnippet(\'' . esc_attr( $snippet['id'] ) . '\')'
                            );
                        } else {
                            // Fallback if render function not available
                            $checked = $is_enabled ? 'checked' : '';
                            $toggle_html = '<input type="checkbox" id="' . esc_attr( $snippet['id'] ) . '" ' . $checked . ' onclick="window.' . __NAMESPACE__ . '.toggleSnippet(\'' . esc_attr( $snippet['id'] ) . '\')">';
                        }

                        // Display the snippet item with toggle
                        echo '<div class="hws-snippet-item' . $deprecated_class . '">
                            <div class="hws-snippet-toggle">
                                ' . $toggle_html . '
                            </div>
                            <div class="hws-snippet-content">
                                <div class="hws-snippet-header">
                                    <code class="hws-snippet-id">' . esc_html( $snippet['id'] ) . '</code>
                                    <span class="hws-snippet-name">' . esc_html( $snippet['name'] ) . '</span>
                                    ' . $deprecated_badge . '
                                </div>
                                <div class="hws-snippet-description">' . esc_html( $snippet['description'] ) . '</div>
                                ' . ( $info_text ? '<div class="hws-snippet-details"><strong>Details:</strong><br>' . wp_kses_post( $info_text ) . '</div>' : '' ) . '
                            </div>
                        </div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

<?php }
?>
