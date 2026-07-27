<?php namespace hws_base_tools;/**
 * Registers a custom ACF field "sponsored" as a single checkbox (true/false).
 */
function hws_sponsored_acf_group(): array {
        return array(
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
        );
}
