<?       
  
if (!function_exists('hws_ct_highlight_if_essential_setting_failed')) {
    function hws_ct_highlight_if_essential_setting_failed($result) {
        return $result['status'] ? $result['details'] : '<span style="color: red;">' . $result['details'] . '</span>';
    }
}

 
// Add settings menu and page
add_action('admin_menu', 'hws_ct_hws_add_wp_admin_settings_page');

// Abstract function to add a settings menu and page
function hws_ct_hws_add_wp_admin_settings_page() {
    hws_add_wp_admin_settings_page(
        'Hexa Core Tools',       // Page title
        'Hexa Core Tools',       // Menu title
        'manage_options',        // Capability
        'hws-core-tools',        // Menu slug
        'hws_ct_display_wp_admin_settings_page'    // Callback function
    );
}
function hws_ct_display_wp_admin_settings_page() {
    
    if (ob_get_level() == 0) ob_start();
    
    ?>

    <style>
  /* Updated Minimalist Panel Styles with Depth */
.panel {
    margin-bottom: 20px;
    border: 1px solid #e0e0e0; /* Slightly lighter border for subtlety */
    border-radius: 6px; /* Slightly more rounded corners */
    background-color: #f9f9f9; /* Lighter background for a clean look */
    padding: 20px; /* Increased padding for better spacing */
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05); /* Subtle shadow for depth */
}

.panel-title {
    padding: 15px;
    border-bottom: 1px solid #ddd; /* Lighter border to separate the title */
    font-size: 18px; /* Slightly larger font for emphasis */
    font-weight: 600; /* Bold for better readability */
    color: #333; /* Darker text color for contrast */
    margin-bottom: 10px;
    border-radius: 4px 4px 0 0; /* Round the top corners */
}

.panel-content {
    padding: 15px;
    border-radius: 0 0 4px 4px; /* Round the bottom corners */
}

.button {
    padding: 10px 16px; /* Increased padding for a more prominent button */
    font-size: 14px;
    border-radius: 4px; /* Slightly more rounded for modern look */
    text-decoration: none;
    color: #fff;
    background-color: #0073aa; /* WordPress standard blue color */
    border: none;
    cursor: pointer;
    display: inline-block;
    margin-right: 10px;
    transition: background-color 0.3s ease; /* Smooth hover transition */
}

.button:hover {
    background-color: #005c89; /* Darker shade on hover */
}

/* WP-Config Constants Expand/Collapse */
.wp-config-expandable {
    margin-top: 0px;
    padding-top: 15px;
}


/* Styles for the toggle links */
.wp-config-toggle {
    cursor: pointer;
    color: #3b82f6;
    font-size: 16px;
    margin-bottom: 10px;
    display: inline-block;
    background-color: #e0f2fe;
    padding: 5px 10px;
    border-radius: 5px;
    transition: background-color 0.3s ease;
}

.wp-config-toggle:hover {
    background-color: #bfdbfe;
}

.wp-config-content {
    display: none;
    margin-top: 15px;
    padding: 15px;
    background-color: #ffffff;
    border: 1px solid #d1d5db;
    border-radius: 8px;
}

.wp-config-content ul {
    list-style: none;
    padding-left: 0;
}

.wp-config-content ul li {
    margin-bottom: 8px;
    padding: 8px;
    background-color: #f3f4f6;
    border-radius: 4px;
}

pre {
    background-color: #f3f4f6;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #d1d5db;
    font-size: 13px;
    color: #1f2937;
}

/* Button styles */
.button {
    padding: 10px 15px;
    font-size: 14px;
    border-radius: 5px;
    text-decoration: none;
    color: #ffffff;
    background-color: #3b82f6;
    border: none;
    cursor: pointer;
    display: inline-block;
    margin-right: 10px;
    transition: background-color 0.3s ease;
}

.button:hover {
    background-color: #2563eb;
}


    </style>

    <div class="wrap">
        <h1>Hexa Core Tools - WP-Config Settings</h1>

<? hws_ct_display_settings_system_checks();?>
<? hws_ct_display_settings_check_plugins();?>
<? hws_ct_display_settings_theme_checks();?>
<? hws_ct_display_settings_snippets();?>
<? hws_ct_display_settings_wp_config();?>
<?php
  // Get the buffer contents and clean (erase) the output buffer
  if (ob_get_level() != 0) echo ob_get_clean();
}
?>