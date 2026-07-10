<?php namespace hws_base_tools;

function register_acf_website_setting()
{
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    acf_add_local_field_group( array(
        'key'               => 'group_6842076add7ad',
        'title'             => 'Theme Options - General',
        'fields'            => array(

            // Website Information
            array(
                'key'               => 'field_68420a023f1c8',
                'label'             => "Website Informationssss\nname: website\n[get_field field=\"website\" post_id=\"option\"]",
                'name'              => 'website',
                'aria-label'        => '',
                'type'              => 'group',
                'instructions'      => '',
                'required'          => 0,
                'conditional_logic' => 0,
                'wrapper'           => array(
                    'width' => '',
                    'class' => '',
                    'id'    => '',
                ),
                'layout'            => 'block',
                'sub_fields'        => array(

                    // DMCA
                    array(
                        'key'               => 'field_68420a173f1c9',
                        'label'             => "DMCA\nname: dmca\n[get_field field=\"dmca\" post_id=\"option\"]",
                        'name'              => 'dmca',
                        'aria-label'        => '',
                        'type'              => 'wysiwyg',
                        'instructions'      => '',
                        'required'          => 0,
                        'conditional_logic' => 0,
                        'wrapper'           => array(
                            'width' => '',
                            'class' => '',
                            'id'    => '',
                        ),
                        'default_value'     => '',
                        'allow_in_bindings' => 0,
                        'tabs'              => 'all',
                        'toolbar'           => 'full',
                        'media_upload'      => 1,
                        'delay'             => 0,
                    ),

                    // Mission Statement
                    array(
                        'key'               => 'field_6842165ffeba4',
                        'label'             => "Mission Statement\nname: mission_statement\n[get_field field=\"mission_statement\" post_id=\"option\"]",
                        'name'              => 'mission_statement',
                        'aria-label'        => '',
                        'type'              => 'wysiwyg',
                        'instructions'      => '',
                        'required'          => 0,
                        'conditional_logic' => 0,
                        'wrapper'           => array(
                            'width' => '',
                            'class' => '',
                            'id'    => '',
                        ),
                        'default_value'     => '',
                        'allow_in_bindings' => 0,
                        'tabs'              => 'all',
                        'toolbar'           => 'full',
                        'media_upload'      => 1,
                        'delay'             => 0,
                    ),

                    // Biography
                    array(
                        'key'               => 'field_6842167afeba5',
                        'label'             => "Biography\nname: biography\n[get_field field=\"biography\" post_id=\"option\"]",
                        'name'              => 'biography',
                        'aria-label'        => '',
                        'type'              => 'wysiwyg',
                        'instructions'      => '',
                        'required'          => 0,
                        'conditional_logic' => 0,
                        'wrapper'           => array(
                            'width' => '',
                            'class' => '',
                            'id'    => '',
                        ),
                        'default_value'     => '',
                        'allow_in_bindings' => 0,
                        'tabs'              => 'all',
                        'toolbar'           => 'full',
                        'media_upload'      => 1,
                        'delay'             => 0,
                    ),

                    // Biography Short
                    array(
                        'key'               => 'field_684216d5feba6',
                        'label'             => "Biography Short\nname: biography_short\n[get_field field=\"biography_short\" post_id=\"option\"]",
                        'name'              => 'biography_short',
                        'aria-label'        => '',
                        'type'              => 'wysiwyg',
                        'instructions'      => '',
                        'required'          => 0,
                        'conditional_logic' => 0,
                        'wrapper'           => array(
                            'width' => '',
                            'class' => '',
                            'id'    => '',
                        ),
                        'default_value'     => '',
                        'allow_in_bindings' => 0,
                        'tabs'              => 'all',
                        'toolbar'           => 'full',
                        'media_upload'      => 1,
                        'delay'             => 0,
                    ),

                    // User
                    array(
                        'key'               => 'field_68421889dd80b',
                        'label'             => "User\nname: user\n[get_field field=\"user\" post_id=\"option\"]",
                        'name'              => 'user',
                        'aria-label'        => '',
                        'type'              => 'user',
                        'instructions'      => '',
                        'required'          => 0,
                        'conditional_logic' => 0,
                        'wrapper'           => array(
                            'width' => '',
                            'class' => '',
                            'id'    => '',
                        ),
                        'role'              => '',
                        'return_format'     => 'array',
                        'multiple'          => 0,
                        'allow_null'        => 0,
                        'allow_in_bindings' => 0,
                        'bidirectional'     => 0,
                        'bidirectional_target' => array(),
                    ),

                    // Email
                    array(
                        'key'               => 'field_6842192117d00',
                        'label'             => "Email\nname: email\n[get_field field=\"email\" post_id=\"option\"]",
                        'name'              => 'email',
                        'aria-label'        => '',
                        'type'              => 'text',
                        'instructions'      => '',
                        'required'          => 0,
                        'conditional_logic' => 0,
                        'wrapper'           => array(
                            'width' => '',
                            'class' => '',
                            'id'    => '',
                        ),
                        'default_value'     => '',
                        'maxlength'         => '',
                        'allow_in_bindings' => 0,
                        'placeholder'       => '',
                        'prepend'           => '',
                        'append'            => '',
                    ),

                ),
            ),

            // Founder Information
            array(
                'key'               => 'field_684217273a45b',
                'label'             => "Founder Information\nname: founder\n[get_field field=\"founder\" post_id=\"option\"]",
                'name'              => 'founder',
                'aria-label'        => '',
                'type'              => 'group',
                'instructions'      => '',
                'required'          => 0,
                'conditional_logic' => 0,
                'wrapper'           => array(
                    'width' => '',
                    'class' => '',
                    'id'    => '',
                ),
                'layout'            => 'block',
                'sub_fields'        => array(

                    // Founder User
                    array(
                        'key'               => 'field_684218049572f',
                        'label'             => "User\nname: founder.user\n[get_field field=\"founder.user\" post_id=\"option\"]",
                        'name'              => 'user',
                        'aria-label'        => '',
                        'type'              => 'user',
                        'instructions'      => '',
                        'required'          => 0,
                        'conditional_logic' => 0,
                        'wrapper'           => array(
                            'width' => '',
                            'class' => '',
                            'id'    => '',
                        ),
                        'role'              => '',
                        'return_format'     => 'array',
                        'multiple'          => 0,
                        'allow_null'        => 0,
                        'allow_in_bindings' => 0,
                        'bidirectional'     => 0,
                        'bidirectional_target' => array(),
                    ),

                    // Founder Biography
                    array(
                        'key'               => 'field_68421959f8873',
                        'label'             => "Biography\nname: founder.biography\n[get_field field=\"founder.biography\" post_id=\"option\"]",
                        'name'              => 'biography',
                        'aria-label'        => '',
                        'type'              => 'wysiwyg',
                        'instructions'      => '',
                        'required'          => 0,
                        'conditional_logic' => 0,
                        'wrapper'           => array(
                            'width' => '',
                            'class' => '',
                            'id'    => '',
                        ),
                        'default_value'     => '',
                        'allow_in_bindings' => 0,
                        'tabs'              => 'all',
                        'toolbar'           => 'full',
                        'media_upload'      => 1,
                        'delay'             => 0,
                    ),

                ),
            ),

        ),
        'location'          => array(
            array(
                array(
                    'param'    => 'options_page',
                    'operator' => '==',
                    'value'    => 'website-settings',
                ),
            ),
        ),
        'menu_order'        => 0,
        'position'          => 'normal',
        'style'             => 'default',
        'label_placement'   => 'top',
        'instruction_placement' => 'label',
        'hide_on_screen'    => '',
        'active'            => true,
        'description'       => '',
        'show_in_rest'      => 0,
    ) );

    acf_add_options_page( array(
        'page_title' => 'Website Settings',
        'post_id'    => 'option',
        'menu_slug'  => 'website-settings',
        'redirect'   => false,
    ) );
}
