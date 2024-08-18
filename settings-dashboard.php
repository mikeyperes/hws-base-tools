<?php
// Generic function to add a settings page under "Settings"
if (!function_exists('add_settings_menu')) {
    function add_settings_menu($page_title, $menu_title, $capability, $menu_slug, $callback_function) {
        add_options_page(
            $page_title,      // Page title
            $menu_title,      // Menu title
            $capability,      // Capability required to access this page
            $menu_slug,       // Menu slug
            $callback_function // Callback function to display the page content
        );
    }
}

// Specific usage example for Verified Profiles
function add_verified_profiles_menu() {
    add_generic_settings_menu(
        'Verified Profiles',       // Page title
        'Verified Profiles',       // Menu title
        'manage_options',          // Capability
        'verified-profiles',       // Menu slug
        'verified_profiles_page'   // Callback function
    );
}
add_action('admin_menu', 'add_verified_profiles_menu');
//add_action('admin_menu', 'add_another_settings_menu');



// Callback function to display content on the settings page
function verified_profiles_page() {?><div class="wrap">
        
        

        <h1>Verified Profiles</h1>
  
           
        <h2>ACF Field Groups Status</h2>
        <ul>
            <li><?php verified_profiles_dashboard_display_check_status(is_acf_field_group_imported('group_656ea6b4d7088'), 'Profile - Admin fields are imported.', 'Profile - Admin fields are not imported.'); ?></li>
             <li><?php verified_profiles_dashboard_display_check_status(is_acf_field_group_imported('group_656ea59dc5ad8'), 'Profile - Organization - Public fields are imported.', 'Profile - Organization - Public fields are not imported.'); ?></li>
            <li><?php verified_profiles_dashboard_display_check_status(is_acf_field_group_imported('group_656eb036374de'), 'Profile - Person - Public fields are imported.', 'Profile - Person - Public fields are not imported.'); ?></li>
            <li><?php verified_profiles_dashboard_display_check_status(is_acf_field_group_imported('group_66b7bdf713e77'), 'Post - Verified Profile - Admin fields are imported.', 'Post - Verified Profile - Admin fields are not imported.'); ?></li>
            <li><?php verified_profiles_dashboard_display_check_status(is_acf_field_group_imported('group_65a8b25062d91'), 'User - Profile Manager fields are imported.', 'User - Profile Manager fields are not imported.'); ?></li>
            <li><?php verified_profiles_dashboard_display_check_status(is_acf_field_group_imported('group_658602c9eaa49'), 'User - Verified Profile Manager - Admin fields are imported.', 'User - Verified Profile Manager - Admin fields are not imported.'); ?></li>
            <li style="opacity:.5"><?php verified_profiles_dashboard_display_check_status(is_acf_field_group_imported('group_verified_profiles_settings'), 'Verified Profiles Settings fields are imported.', 'Verified Profiles Settings fields are not imported.'); ?></li>

        </ul>



<h2>Pre-checks</h2>

<h3>Theme</h3>
<ul>
    <li><?php display_check_status(is_hello_elementor_theme_active(), 'Hello Elementor theme is active.', 'Hello Elementor theme is not active. Please activate it to use the Hello World Plugin.'); ?></li>
    <li style="margin-left: 20px;"><?php display_check_status(is_theme_auto_update_enabled("hello-elementor"), 'Hello Elementor theme auto updates are enabled.', 'Hello Elementor theme auto updates are not enabled. Please enable them.'); ?></li>
</ul>

<h3>Plugins</h3>
<?php 
$plugins = get_plugins_list();
foreach ($plugins as $plugin => $name): 
    list($is_installed, $is_active, $is_auto_update_enabled) = check_plugin_status($plugin);
?>
    <ul>
        <li><?php display_check_status($is_installed, "{$name} Plugin exists.", "{$name} Plugin does not exist. Please install it to use the Hello World Plugin."); ?></li>
        <li style="margin-left: 20px;"><?php display_check_status($is_active, "{$name} Plugin is active.", "{$name} Plugin is not active. Please activate it to use the Hello World Plugin."); ?></li>
        <li style="margin-left: 20px;"><?php display_check_status($is_auto_update_enabled, "{$name} Plugin auto updates are enabled.", "{$name} Plugin auto updates are not enabled. Please enable them."); ?></li>
    </ul>
<?php endforeach; ?>

<h3>Other Checks</h3>
<ul>
    <li><?php display_check_status(does_post_type_exist('profile'), '"Profile" Custom Post Type is active.', '"Profile" Custom Post Type is not active. Please register it to use the Hello World Plugin.'); ?></li>
    <li style="margin-left: 20px;"><?php display_check_status(does_taxonomy_exist('category'), 'Categories are enabled for "profile" CPT.', 'Categories are not enabled for "profile" CPT.'); ?>
        <ul style="margin-left: 20px;">
            <li><?php display_check_status(does_term_exist('person', 'category'), 'Category "Person" exists.', 'Category "Person" does not exist.'); ?></li>
            <li><?php display_check_status(does_term_exist('organization', 'category'), 'Category "Orgnization" exists.', 'Category "Organization" does not exist.'); ?></li>
        </ul>
    </li>
    <li><?php display_check_status(does_taxonomy_exist('post_tag'), 'Tags are enabled for "profile" CPT.', 'Tags are not enabled for "profile" CPT.'); ?></li>
    <li><?php display_check_status(does_user_exist('unclaimed-profile'), 'The "unclaimed-profile" user exists.', 'The "unclaimed-profile" user does not exist. Please create this user to use the Hello World Plugin.'); ?></li>
</ul>

<button id="create-categories-button" class="button button-primary">Create Verified Profile Categories (person and company)</button>

<script type="text/javascript">
    document.getElementById('create-categories-button').addEventListener('click', function() {
        var data = {
            'action': 'create_verified_profile_categories'
        };

        fetch(ajaxurl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: new URLSearchParams(data)
        })
        .then(response => response.text())
        .then(data => {
            location.reload();
        })
        .catch(error => {
            console.error('Error:', error);
        });
    });
</script>



<h2>ACF Field Management</h2>
       <? display_settings_create_pages_and_listing_grids(); ?>
       
       
        <h2>Settings</h2>
       
       <? display_settings_acf_post_and_pages_form();?>


    </div><?php
}

// Ensure ACF form functions are available
if (function_exists('acf_form_head')) {
    add_action('admin_head', 'acf_form_head');
}

// Hook the add_verified_profiles_menu function to the admin_menu action
add_action('admin_menu', 'add_verified_profiles_menu');

// Function to check if the ACF field group is imported
function is_acf_field_group_imported($key) {
    $groups = acf_get_local_field_groups();
    foreach ($groups as $group) {
        if ($group['key'] === $key) {
            return true;
        }
    }
    return false;

}