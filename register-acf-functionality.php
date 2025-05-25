<? /**
 * Registers a custom ACF field "sponsored" with yes/no options.
 */
function register_acf_sponsored_functionality() {

    // Only proceed if ACF is active and the function is available.
    if ( function_exists('acf_add_local_field_group') ) {
        
        acf_add_local_field_group(array(
            'key'      => 'group_sponsored_field',
            'title'    => 'Sponsored Field Group',
            'fields'   => array(
                array(
                    'key'     => 'field_sponsored',
                    'label'   => 'Sponsored',
                    'name'    => 'sponsored',
                    'type'    => 'radio',
                    'choices' => array(
                        'yes' => 'Yes',
                        'no'  => 'No',
                    ),
                    'layout'  => 'horizontal',
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
        ));
    }
}?>