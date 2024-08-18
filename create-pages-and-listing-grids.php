<? function display_settings_acf_post_and_pages_form(){?><form method="post" action="options.php">
    <?php
    // Render ACF fields within the form
    acf_form(array(
        'post_id' => 'options', // Save to the options page
        'fields' => array(
            'field_page_verified_profiles_badges',
            'field_page_verified_profiles_claim',
            'field_page_verified_profiles_apply',
            'field_page_verified_profiles_welcome',
            'field_single_profile',
            'field_page_profile_archive',
            'field_home_profile_archive',
            'field_single_profile_words_by',
            'field_single_profile_mentioned'
        ),
        'submit_value' => 'Save Settings',
    ));
    ?>
</form>
<script>
jQuery(document).ready(function($) {
    
                $= jQuery;
    // Fetch the status for each ACF field on page load
    $.ajax({
        url: ajaxurl,
        type: 'GET',
        data: {
            action: 'get_settings_pages_and_post_reports'
        },
        success: function(response) {
     
            console.log('AJAX Response:', response); // Debugging: log the entire response
            if (response.success) {
                $.each(response.data, function(field, message) {
                    var statusMessage = '';
                    if (message) {
                        statusMessage = 'Post ID: ' + message.post_id + ' - <a href="' + message.view_link + '">View</a> - <a href="' + message.edit_link + '">Edit</a>';
                    } else {
                        statusMessage = 'No post assigned';
                    }
                    // Inject the status message within the respective ACF field container
                    $('div[data-key="' + field + '"] .acf-input').append('<div class="acf-status">' + statusMessage + '</div>');
                });
            } else {
                console.log('Error in response:', response.data); // Debugging: log error in response
            }
        },
        error: function(response) {
       
            console.log('AJAX error:', response); // Debugging: log AJAX error
        }
    });
});
</script><? }
function display_settings_create_pages_and_listing_grids(){ ?>
    <button id="create-posts-button" class="button button-primary">Create Pages and Listing Grids (Jet Engine posts)</button>
    <div id="acf-loader" style="display:none;">Loading...</div>
    <div id="create-posts-result"></div>


    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#create-posts-button').on('click', function() {
                var resultDiv = $('#create-posts-result');
                var loaderDiv = $('#acf-loader');

                resultDiv.html('');
                loaderDiv.show();

                var data = {
                    'action': 'create_posts_and_listing_grids'
                };

                fetch(ajaxurl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: new URLSearchParams(data)
                })
                .then(response => response.json())
                .then(data => {
                    loaderDiv.hide();
                    resultDiv.html(data.report);
                    if (data.new_posts_created) {
                        resultDiv.append('<p style="color:red;font-weight"bold">Refreshing page in <span id="timer">10</span> seconds...</p>');
                        var seconds = 10;
                        var interval = setInterval(function() {
                            $('#timer').text(--seconds);
                            if (seconds <= 0) {
                                clearInterval(interval);
                                location.reload();
                            }
                        }, 1000);
                    }
                })
                .catch(error => {
                    loaderDiv.hide();
                    resultDiv.html('Error: ' + error);
                });
            });
        });
    </script>
<?php }

// Display the status of ACF fields and associated posts
function display_acf_field_status() {
    $acf_fields = array(
      'field_page_verified_profiles_badges',
            'field_page_verified_profiles_claim',
            'field_page_verified_profiles_apply',
            'field_page_verified_profiles_welcome',
            'field_single_profile',
            'field_page_profile_archive',
            'field_home_profile_archive',
            'field_single_profile_words_by',
            'field_single_profile_mentioned',
        'field_program_name',
    );

    foreach ($acf_fields as $acf_field) {
        $field_value = get_field($acf_field, 'option');
        if ($field_value) {
            $post_id = $field_value;
            echo '<p>' . get_field_label($acf_field) . ': <a target=_blank href="' . get_permalink($post_id) . '">View Post ID ' . $post_id . '</a> - <a target=_blank href="' . get_edit_post_link($post_id) . '">Edit</a></p>';
        } else {
            echo '<p>' . get_field_label($acf_field) . ': No post assigned</p>';
        }
    }
}

// Get the field label for an ACF field
function get_field_label($field_name) {
    $field_object = get_field_object($field_name);
    return $field_object['label'];
}


function create_posts_and_listing_grids() {
    // Define the ACF fields and their corresponding titles
    $acf_fields = [
        'field_page_verified_profiles_badges' => 'Verified Profiles - Badges',
        'field_page_verified_profiles_claim' => 'Verified Profiles - Claim',
        'field_page_verified_profiles_apply' => 'Verified Profiles - Apply',
        'field_page_verified_profiles_welcome' => 'Verified Profiles - Welcome',
        'field_single_profile' => 'Profile - mentions on single.php',
        'field_page_profile_archive' => 'Profile - archive.php',
        'field_home_profile_archive' => 'Profile - archive on home.php',
        'field_single_profile_words_by' => 'Words By - single-profile.php',
        'field_single_profile_mentioned' => 'Mentioned In - single-profile.php',
    ];

    $report = '';
    $new_posts_created = false;

    foreach ($acf_fields as $field_key => $title) {
        // Get the field object to determine the post type
        $field_object = get_field_object($field_key);
        $post_type = $field_object['post_type'][0];

        // Check if the ACF field already has a post assigned
        $post_id = get_field($field_key, 'option');
        if ($post_id) {
            $report .= '<p>ACF field <strong>' . $field_key . '</strong> already has a post assigned (ID: ' . $post_id . ')</p>';
        } else {
            // Prepare post data
            $post_data = array(
                'post_title'    => wp_strip_all_tags($title . ' - auto generated'),
                'post_content'  => '', // Empty content for the new posts
                'post_status'   => 'publish', // You can set this to 'draft' if you want to review before publishing
                'post_author'   => 1, // Change this to the desired author ID
                'post_type'     => $post_type // Set the post type as specified
            );

            // Insert the post into the database
            $new_post_id = wp_insert_post($post_data);
            if (is_wp_error($new_post_id)) {
                $error_message = 'Failed to insert post: ' . $title . ' Error: ' . $new_post_id->get_error_message();
                if (WP_DEBUG) {
                    error_log($error_message);
                }
                $report .= '<p>' . $error_message . '</p>';
            } else {
                $new_posts_created = true;
                $success_message = 'Post created successfully: ' . $title . ' (ID: ' . $new_post_id . ')';
                if (WP_DEBUG) {
                    error_log($success_message);
                }
                $report .= '<p>' . $success_message . '</p>';

                // Assign the new post ID to the ACF field
                update_field($field_key, $new_post_id, 'option');

                // Add post meta data if needed
                // Update serialized data for _listing_data
                $listing_data = 'a:3:{s:6:"source";s:5:"posts";s:9:"post_type";s:' . strlen($post_type) . ':"' . $post_type . '";s:3:"tax";s:8:"category";}';
                update_post_meta($new_post_id, '_listing_data', $listing_data);

                // Update serialized data for _elementor_page_settings
                $elementor_page_settings = 'a:6:{s:14:"listing_source";s:5:"posts";s:17:"listing_post_type";s:' . strlen($post_type) . ':"' . $post_type . '";s:11:"listing_tax";s:8:"category";s:15:"repeater_source";s:10:"jet_engine";s:14:"repeater_field";s:0:"";s:15:"repeater_option";s:0:"";}';
                update_post_meta($new_post_id, '_elementor_page_settings', $elementor_page_settings);

                update_post_meta($new_post_id, '_elementor_template_type', 'jet-listing-items');
            }
        }
    }

    echo json_encode(['report' => $report, 'new_posts_created' => $new_posts_created]);
    wp_die();
}



add_action('wp_ajax_create_posts_and_listing_grids', 'create_posts_and_listing_grids');
add_action('wp_ajax_nopriv_create_posts_and_listing_grids', 'create_posts_and_listing_grids');