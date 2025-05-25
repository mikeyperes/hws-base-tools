<?php namespace hws_base_tools;/**
 * Registers a custom ACF field "sponsored" as a single checkbox (true/false).
 */
function register_acf_sponsored_functionality() {

    // Only proceed if ACF is active and the function is available.
    if ( function_exists('acf_add_local_field_group') ) {
        
        acf_add_local_field_group( array(
            'key'      => 'group_sponsored_field',
            'title'    => 'Sponsored Field Group',
            'fields'   => array(
                array(
                    'key'               => 'field_sponsored',
                    'label'             => 'Sponsored',
                    'name'              => 'sponsored',
                    'type'              => 'true_false',
                    'instructions'      => 'Check if this post is sponsored.',
                    'default_value'     => 0,             // 0 = unchecked, 1 = checked
                    'ui'                => 1,             // Enables the toggle switch UI
                    'ui_on_text'        => 'Yes',
                    'ui_off_text'       => 'No',
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param'    => 'post_type',
                        'operator' => '==',
                        'value'    => 'post',
                    ),
                ),
            ),
        ) );
    }
}