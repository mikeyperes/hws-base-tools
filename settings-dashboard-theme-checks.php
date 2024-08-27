<?php
function hws_ct_display_settings_theme_checks()
{
    // Check if output buffering is already active
    if (ob_get_level() == 0) {
        ob_start();
    }
    ?>
    <!-- Theme Status Panel -->
    <div class="panel">
        <h2 class="panel-title">Theme Checks</h2>
        <div class="panel-content">
            <!-- Active Theme and Auto-Updates Status -->
            <div style="margin-bottom: 15px;">
                <strong>Active Theme:</strong>
                <div style="margin-left: 15px;">
                    <?php
                    // Check if "Hello Elementor" theme is active
                    $hello_elementor_active = is_theme_active('Hello Elementor');
                    display_check_status($hello_elementor_active, 'Hello Elementor');

                    // Check if auto-updates are enabled for "Hello Elementor"
                    $hello_elementor_auto_update = is_theme_auto_update_enabled('hello-elementor'); // Use correct theme slug
                    display_check_status($hello_elementor_auto_update, 'Auto-Updates Enabled');
                    ?>
                </div>
            </div>

            <!-- List All Themes -->
            <div style="margin-bottom: 15px;">
                <strong>Installed Themes:</strong>
                <div style="margin-left: 15px;">
                    <?php
                    // Get all themes
                    $all_themes = wp_get_themes();
                    $active_theme = wp_get_theme();
                    $theme_count = count($all_themes);

                    // Loop through all themes and display their status
                    foreach ($all_themes as $theme_name => $theme_data) {
                        $is_active = ($theme_name === $active_theme->get_stylesheet());
                        $status = $is_active ? 'Active' : 'Inactive';
                        $focus_style = $is_active ? 'font-weight: bold;' : 'color: #555;';
                        echo "<div style='$focus_style'>{$theme_data->get('Name')} - {$theme_data->get('Version')} - $status</div>";
                    }
                    ?>
                </div>
            </div>

            <!-- Warning if More Than 2 Themes Installed -->
            <?php if ($theme_count > 2): ?>
                <div style="color: red;">
                    <strong>Warning:</strong> There are more than 2 themes installed on the site.
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php

    // Get the buffer contents and clean (erase) the output buffer
    if (ob_get_level() != 0) {
        echo ob_get_clean();
    }
}
?>
