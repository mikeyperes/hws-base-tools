<?

function disable_elementor_for_non_admins() {
    // Check if the current user is not an administrator
    if (!current_user_can('administrator')) {
        // Remove Elementor action from the admin bar
        remove_action('admin_bar_menu', array(\Elementor\Plugin::$instance->admin, 'add_edit_in_dashboard'), 200);
        // Remove Elementor switch mode action
        remove_action('edit_form_after_title', ['\Elementor\Plugin::$instance->documents_manager', 'add_switch_mode_to_edit_form']);
    }
}
add_action('admin_init', 'disable_elementor_for_non_admins');



//this function is for initial login redirection 
function redirect_profile_manager_after_login( $redirect_to, $request, $user ) {return;
    // Is there a user to check?
    if ( isset( $user->roles ) && is_array( $user->roles ) ) {
        // Check if the user is a Profile Manager
        if ( is_profile_manager(true)) {
            // Redirect them to the 'Profile' post type edit page
            return admin_url( 'edit.php?post_type=profile' );
        } else {
            // Otherwise, redirect to the dashboard or wherever the original request was pointing
            return $redirect_to;
        }
    }

    return $redirect_to;
}
add_filter( 'login_redirect', 'redirect_profile_manager_after_login', 10, 3 );

//this function handles wp-admin and wp-admin/index.php 
function redirect_verified_profile_manager() {return;
    global $pagenow;

    // Check if the current user is a Profile Manager, not doing an AJAX request, and trying to access the dashboard home
    if (is_profile_manager(true) && !wp_doing_ajax() && $pagenow === 'index.php') {
        wp_redirect(admin_url('edit.php?post_type=profile'));
        exit;
    }
}

/**
 * Modifies the labels of the 'profile' custom post type based on the condition of the global variable $x.
 *
 * @param array  $args      The array of arguments for registering the post type.
 * @param string $post_type The post type to check.
 * @return array The modified arguments array.
 */
function modify_profile_post_type_labels( $args, $post_type ) {
    // Check if the current post type is 'profile' and if the global variable $x is true.
    if ( 'profile' === $post_type && is_profile_manager(true) ) {
        // Change the plural label to 'Claimed Profiles'.
        $args['labels']['name'] = 'Claimed Profiles';


        $args['labels']['singular_name'] = 'Claimed Profile';
    }

    // Return the modified arguments.
    return $args;
}



add_action('init', 'create_verified_profile_manager_role');
function create_verified_profile_manager_role() {
	
	if(is_profile_manager(true) || true)
    if (!get_role('verified_profile_manager')) {
        add_role('verified_profile_manager', 'Verified Profile Manager', array(
            'read' => true, // Essential for accessing the admin dashboard
            'edit_posts' => true, // Allows access to posts, needed for some media library interactions
            'edit_pages' => false,
            'edit_others_posts' => false,
            'create_posts' => true, // Allows creating posts
            'manage_categories' => false,
            'publish_posts' => false,
            'edit_themes' => false,
            'install_plugins' => false,
            'update_plugin' => false,
            'update_core' => false,

            // Media capabilities
            'upload_files' => true, // Allows uploading files
            'edit_files' => true, // Sometimes necessary for media library access

            // Capabilities for the 'profile' CPT
            'edit_profile' => true,
            'edit_others_profiles' => false,
            'publish_profiles' => false,
            'read_private_profiles' => true
        ));
    } 
	
if(is_contributor(true))
	if (!get_role('contributor')) {
    add_role('contributor', 'Contributor', array(
        'read' => true, // Essential for accessing the admin dashboard
        'edit_posts' => true, // Allows access to posts, needed for some media library interactions
        'edit_pages' => false,
        'edit_others_posts' => false, // Cannot edit others' posts
		'edit_published_posts' => true, // Allows editing own published posts

        'create_posts' => true, // Allows creating posts
        'manage_categories' => false,
        'publish_posts' => false, // Cannot publish posts
        'edit_themes' => false,
        'install_plugins' => false,
        'update_plugin' => false,
        'update_core' => false,

        // Media capabilities
        'upload_files' => true, // Allows uploading files
        'edit_files' => true, // Sometimes necessary for media library access

        // Removed capabilities related to 'profile' CPT as they are commented out
    ));
} else {
    // Get the contributor role
    $role = get_role('contributor');

    // Set necessary capabilities
    $role->add_cap('edit_posts');
    $role->add_cap('create_posts');
    $role->add_cap('upload_files');
    $role->add_cap('edit_files');
    $role->add_cap('edit_published_posts');
    // Remove capabilities that should not be allowed
    $role->remove_cap('publish_posts');
    $role->remove_cap('edit_others_posts');
    $role->remove_cap('edit_pages');
    $role->remove_cap('manage_categories');
    $role->remove_cap('edit_themes');
    $role->remove_cap('install_plugins');
    $role->remove_cap('update_plugin');
    $role->remove_cap('update_core');

    // Remove capabilities related to 'profile' CPT as they are commented out
}

	
}


add_action('do_meta_boxes', 'remove_meta_boxes',100);

function remove_meta_boxes() {
if(is_profile_manager(true)){
		    remove_meta_box('categorydiv', 'profile', 'side'); // category from profiles
    // Replace 'profile' with your custom post type key
    remove_meta_box('tagsdiv-post_tag', 'profile', 'side');
       remove_meta_box('authordiv', 'profile', 'normal');
	     remove_meta_box('echo_meta_box_function_add', 'profile', 'advanced');
	     remove_meta_box('advanced-sortables', 'profile', 'normal');
	
	
		
		
  	remove_meta_box('imageUrlMetaBox', 'profile', 'side');
// for post, if contributor
	  remove_meta_box('echo_meta_box_function_add', 'post', 'advanced');
			   remove_meta_box('litespeed_meta_boxes', 'post', 'side');
	   remove_meta_box('pageparentdiv', 'post', 'side');
	  	remove_meta_box('imageUrlMetaBox', 'post', 'side');

}
}


function hide_pages_for_profile_manager() {

    if (is_profile_manager(true)) {
		
	
	 if(!is_contributor(true)){
		 remove_menu_page('edit.php'); // Hides Tools
		    remove_menu_page('post-new.php');
	 }
		
		 remove_menu_page('index.php'); // Hide the dashboard for all users
        remove_menu_page('tools.php'); // Hides Tools
		
		
	   remove_menu_page('edit.php?post_type=team-member'); // Hides 'team' Post Type
		remove_menu_page('edit.php?post_type=organization'); 
        remove_menu_page('edit.php?post_type=press-release'); // Hides 'press-release' Post Type
        remove_menu_page('edit.php?post_type=herforward-podcast'); // Hides 'herforward-podcast' Post Type
        remove_menu_page('edit.php?post_type=elementor_library'); // Hides Elementor Library
        remove_menu_page('admin.php?page=theme-general-settings'); // Hides Theme General Settings
		  remove_menu_page('admin.php?page=theme-options-emails'); // Hides Theme General Settings
		
			remove_menu_page('theme-options-emails'); // Hides Theme General Settings
		
		remove_menu_page('theme-general-settings'); // Hides Theme General Settings
		//remove_menu_page('https://herforward.com/wp-admin/post-new.php');
		 
    }
	
	if(is_contributor(true))
	{
		
		
	   remove_menu_page('index.php'); // Hide the dashboard for all users
       remove_menu_page('tools.php'); // Hides Tools
	   remove_menu_page('edit.php?post_type=team-member'); // Hides 'team' Post Type
	   remove_menu_page('edit.php?post_type=organization'); 
       remove_menu_page('edit.php?post_type=press-release'); // Hides 'press-release' Post Type
       remove_menu_page('edit.php?post_type=herforward-podcast'); // Hides 'herforward-podcast' Post Type
       remove_menu_page('edit.php?post_type=elementor_library'); // Hides Elementor Library
       remove_menu_page('admin.php?page=theme-general-settings'); // Hides Theme General Settings
	   remove_menu_page('admin.php?page=theme-options-emails'); // Hides Theme General Settings
	   remove_menu_page('theme-general-settings'); // Hides Theme General Settings
       remove_menu_page('theme-options-emails'); // Hides Theme General Settings

		
		
}
}


function hide_elementor_edit_button_for_non_admins() {
    if (!current_user_can('administrator')) {
        echo '<style>
            #elementor-switch-mode,
			#footer-left
            { display: none !important; }
        </style>';
    }
}

add_action('admin_head', 'hide_elementor_edit_button_for_non_admins');



function hide_add_new_buttons_in_sidebar() {
	if(!is_profile_manager(true))return ;
    // Start the style tag
    $style = '<style type="text/css">';

    // Add the .post-type-post .page-title-action rule only if is_contributor(true) returns false
    if (!is_contributor(true)) {
        $style .= '.post-type-post .page-title-action,';
    }

    // Continue with the rest of the CSS rules
    $style .= '
        .post-type-profile .page-title-action,
        #edit-slug-box, /* edit slug in edit.php */
        #menu-posts li a[href="post-new.php"],
        .subsubsub .count,
        #menu-posts-profile li a[href="post-new.php?post_type=profile"] {
            display: none !important;
        }
    </style>';
	  echo $style;

   
}
// Use 'admin_head' action hook to inject CSS into the admin head section
add_action('admin_head', 'hide_add_new_buttons_in_sidebar');


function modify_profile_post_link() {
	if(!is_profile_manager(true))return;
    global $pagenow;
    // Check if the current admin page is the 'Profiles' list
    if (is_profile_manager() && 'edit.php' === $pagenow && isset($_GET['post_type']) && 'profile' === $_GET['post_type']) {
        ?><script type="text/javascript">
            jQuery(document).ready(function($) {
                // Change the text of the 'Add New' button for the 'profile' post type
              
               
                  $('a.page-title-action[href*="post_type=profile"]').text('View Profile Dashboard');
              $('a.page-title-action[href*="post_type=profile"]').attr('target','_blank');

                // Set up a click event listener on the modified button
                $(document).on('click', 'a.page-title-action[href*="post_type=profile"]', function(e) {
                    e.preventDefault();  // Prevent the default action
			
					         window.open(window.location.origin + '/wp-admin/admin.php?page=profiles-dashboard', '_blank');



                
                });
            });
			
			  jQuery('a.page-title-action[href*="post_type=profile"]').text('Purchase New Profile');
        </script><?php
    }
}
// Use 'admin_head' action hook to inject the jQuery script into the admin head section
add_action('admin_head', 'modify_profile_post_link');


function change_profile_menu_label() {
	if(!is_profile_manager(true))return;
    global $menu, $submenu;
    $menu[70][0] = 'Manager Profile'; // Change 'Profile' to 'Manager Profile' in the menu.
    if ( isset( $submenu['profile.php'] ) ) {
        foreach ( $submenu['profile.php'] as $index => $menu_item ) {
            if ( $menu_item[2] === 'profile.php' ) {
                $submenu['profile.php'][$index][0] = 'Manager Profile';
            }
        }
    }
}
add_action( 'admin_menu', 'change_profile_menu_label' );




function is_profile_manager($strict = false)
{
	
	if((is_admin() && !$strict)|| current_user_can('verified_profile_manager'))return true;
 return false;
}



function is_contributor($strict = false)
{
    
       if (!smp_vp_perform_prechecks()) 
	    // Get current user
    $user = wp_get_current_user();

	        $is_contributor = get_field('is_contributor', 'user_' . $user->ID);
   $is_council_member = get_field('is_council_member', 'user_' . $user->ID);
	
	if((is_admin() && !$strict)|| current_user_can('contributor') || $is_contributor ||$is_council_member )return true;

 return false;
}





add_action('admin_menu', 'hide_pages_for_profile_manager',100000);


function restrict_verified_profile_manager_to_own_posts($query) {
    // Check if we are in the admin area
    if (is_admin()) {
        // Further check if the current user has the 'verified_profile_manager' role
        if (is_profile_manager(true) || is_contributor(true)) {
            // Get the current user's ID
            $user_id = get_current_user_id();

            // If the query is for the 'profile' post type, modify it to only show posts by the current user
            if ($query->is_main_query() && $query->get('post_type') === 'profile') {
                $query->set('author', $user_id);
            }

            // If the query is for the 'post' post type, modify it to only show posts by the current user
            if ($query->is_main_query() && 'post' == $query->get('post_type')) {
                $query->set('author', $user_id);
            }
        }
    }
}

// Make sure to hook this function to a suitable action or filter in the admin area
// For example: add_action('pre_get_posts', 'restrict_verified_profile_manager_to_own_posts');

//add_action('pre_get_posts', 'restrict_verified_profile_manager_to_own_posts');


add_action('init', 'add_profile_capabilities_to_verified_profile_manager');
function add_profile_capabilities_to_verified_profile_manager() {
    $role = get_role('verified_profile_manager');
    if ($role) {
        // Grant capabilities for standard posts
        $role->add_cap('edit_posts');
        $role->add_cap('edit_others_posts');
        $role->add_cap('delete_posts');
        $role->add_cap('delete_others_posts');
        $role->add_cap('read_private_posts');
        $role->add_cap('edit_published_posts');

        // Grant capabilities for 'profile' CPT
        $role->add_cap('edit_profile');
        $role->add_cap('edit_profiles');
        $role->add_cap('edit_published_profiles');
        $role->add_cap('read_profile');
        $role->add_cap('delete_profile');
        $role->add_cap('edit_others_profiles');
        $role->add_cap('publish_profiles');
        $role->add_cap('read_private_profiles');

        // Grant capability to upload files
        $role->add_cap('upload_files');
    }

    // Admin specific capabilities
    if (is_admin()) {
        if ($role) {
            $role->add_cap('publish_posts');
        }
    } 
	 if(is_contributor(true))
	{
     // Grant capability to upload files
        $role->add_cap('upload_files');
}
}

add_action('admin_init', function() {
    $role = get_role('verified_profile_manager');
    if ($role) {
       // error_log(print_r($role->capabilities, true));
    }
});


/*
function reset_admin_capabilities_for_cpt_profile() {
    // Get the administrator role object
    $role = get_role('administrator'); // Replace 'administrator' with your role if different

    // Add capabilities related to 'profile' CPT
    $role->add_cap('edit_profile');
    $role->add_cap('read_profile');
    $role->add_cap('delete_profile');
    $role->add_cap('edit_profiles');
    $role->add_cap('edit_others_profiles');
    $role->add_cap('publish_profiles');
    $role->add_cap('read_private_profiles');
    $role->add_cap('delete_profiles');
    $role->add_cap('delete_private_profiles');
    $role->add_cap('delete_published_profiles');
    $role->add_cap('delete_others_profiles');
    $role->add_cap('edit_private_profiles');
    $role->add_cap('edit_published_profiles');

    // Add standard post type capabilities if needed
    $role->add_cap('edit_posts');
    $role->add_cap('edit_others_posts');
    $role->add_cap('publish_posts');
    $role->add_cap('read_private_posts');
	
	     // Grant capabilities for 'profile' CPT
        $role->add_cap('edit_profile');
		        $role->add_cap('edit_profiles');
		
				        $role->add_cap('edit_published_profiles');
		   $role->add_cap('edit_published_profile');
        $role->add_cap('read_profile');
        $role->add_cap('delete_profile');
        $role->add_cap('edit_profiles');
        $role->add_cap('edit_others_profiles');
        $role->add_cap('publish_profiles');
        $role->add_cap('read_private_profiles');
		
}
add_action('admin_init', 'reset_admin_capabilities_for_cpt_profile');
*/




function restrict_add_new_post_access_for_verified_profile_manager() {
    global $pagenow;

    // Check if the current page is 'post-new.php'
    if (!is_contributor(true) && is_profile_manager(true) && 'post-new.php' == $pagenow) {
        // Check if the current user has the 'verified_profile_manager' role
        if (current_user_can('verified_profile_manager')) {
            // Redirect to a different page, like the dashboard or 'All Posts' page
            wp_redirect(admin_url('edit.php'));
            exit;
        }
    }
}
add_action('admin_init', 'restrict_add_new_post_access_for_verified_profile_manager');