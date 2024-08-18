<?php
// Include the plugin.php file if it hasn't been included yet
if (!function_exists('is_plugin_active')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}




// Plugins list
function hws_ct_get_plugins_list() {
    return [
        'advanced-custom-fields/acf.php' => 'ACF',
        'elementor/elementor.php' => 'Elementor',
        'elementor-pro/elementor-pro.php' => 'Elementor Pro',
        'jet-engine/jet-engine.php' => 'JetEngine',
        'seo-by-rank-math/rank-math.php' => 'Rank Math SEO',
        'seo-by-rank-math-pro/rank-math-pro.php' => 'Rank Math Pro',
        'woo-checkout-field-editor-pro/woocommerce-checkout-field-editor-pro.php' => 'Checkout Field Editor for WooCommerce',
        'woocommerce/woocommerce.php' => 'WooCommerce',
        'woocommerce-subscriptions/woocommerce-subscriptions.php' => 'WooCommerce Subscriptions',
        'woocommerce-gateway-stripe/woocommerce-gateway-stripe.php' => 'Payment Plugins for Stripe WooCommerce',
    ];
}

// Perform plugin and theme prechecks
function hws_ct_perform_prechecks() {
    $plugins = hws_ct_get_plugins_list();
    $messages = [];

    foreach ($plugins as $plugin => $name) {
        list($is_installed, $is_active, $is_auto_update_enabled) = check_plugin_status($plugin);
        if (!$is_installed) {
            $messages[] = "<p><strong>{$name} not installed:</strong> The {$name} plugin is not installed.</p>";
        } elseif (!$is_active) {
            $messages[] = "<p><strong>{$name} not enabled:</strong> The {$name} plugin is not active. Please activate it to use the Hello World Plugin.</p>";
        } elseif (!$is_auto_update_enabled) {
            $messages[] = "<p><strong>{$name} auto updates not enabled:</strong> Please enable automatic updates for the {$name} plugin.</p>";
        }
    }

    if (!does_post_type_exist('profile')) {
        $messages[] = '<p><strong>"Profile" Custom Post Type not enabled:</strong> The "profile" custom post type is not active. Please register it to use the Hello World Plugin.</p>';
    }

    if (!does_user_exist('unclaimed-profile')) {
        $messages[] = '<p><strong>"Unclaimed-profile" user not found:</strong> The "unclaimed-profile" user does not exist. Please create this user to use the Hello World Plugin.</p>';
    }

    if (!is_theme_active("Hello Elementor")) {
        $messages[] = '<p><strong>Hello Elementor theme not active:</strong> The Hello Elementor theme is not active. Please activate it to use the Hello World Plugin.</p>';
    } elseif (!is_theme_auto_update_enabled("hello-elementor")) {
        $messages[] = '<p><strong>Hello Elementor theme auto updates not enabled:</strong> Please enable automatic updates for the Hello Elementor theme.</p>';
    }

    if (!empty($messages)) {
        add_action('admin_notices', function() use ($messages) {
            echo '<div class="notice notice-error is-dismissible">';
            foreach ($messages as $message) {
                echo $message;
            }
            echo '</div>';
        });
    }
}


// Function to display the check status
function hws_ct_dashboard_display_check_status($is_active, $active_message, $inactive_message) {
    if ($is_active) {
        echo '<span style="color: green;">&#x2705; ' . $active_message . '</span>';
    } else {
        echo '<span style="color: red;">&#x274C; ' . $inactive_message . '</span>';
    }
}