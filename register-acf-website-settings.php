<?php namespace hws_base_tools;

function register_acf_website_settings()
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
                'label'             => 'Website Information',
                'name'              => 'website',
                'type'              => 'group',
                'instructions'      => "name: website<br>[website_url social=\"website\"]",
                'required'          => 0,
                'conditional_logic' => 0,
                'wrapper'           => array(
                    'width' => '',
                    'class' => '',
                    'id'    => '',
                ),
                'layout'            => 'block',
                'sub_fields'        => array(


        // User
        array(
            'key'               => 'field_68421889dd80b',
            'label'             => 'Company',
            'name'              => 'company',
            'type'              => 'user',
            'instructions'      => "This is the main entity of this website. Such as a company, organizations, podcast, news outlet. If this is a website on an individual (person), you can use the person info below",
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
            'bidirectional_target'=> array(),
        ),

         // Email
         array(
            'key'               => 'field_6842192117d00',
            'label'             => 'Display Email',
            'name'              => 'email',
            'type'              => 'text',
            'instructions'      => "Email that will be visible to the public.<br />name: email<br>[website_url social=\"email\"]",
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

                 
                    // DMCA
                    array(
                        'key'               => 'field_68420a173f1c9',
                        'label'             => 'DMCA',
                        'name'              => 'dmca',
                        'type'              => 'wysiwyg',
                        'instructions'      => "name: dmca<br>[website_content field=\"website_dmca\"]",
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
                        'label'             => 'Mission Statement',
                        'name'              => 'mission_statement',
                        'type'              => 'wysiwyg',
                        'instructions'      => "name: mission_statement<br>[website_content field=\"website_mission_statement\"]",
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
                        'label'             => 'Biography',
                        'name'              => 'biography',
                        'type'              => 'wysiwyg',
                        'instructions'       => "name: biography<br>[website_content field=\"website_biography\"]",
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
                        'label'             => 'Biography Short',
                        'name'              => 'biography_short',
                        'type'              => 'wysiwyg',
                        'instructions'       => "name: biography_short<br>[website_content field=\"website_biography_short\"]",
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
                        'label'             => 'Biography Short',
                        'name'              => 'biography_short',
                        'type'              => 'wysiwyg',
                        'instructions'       => "name: biography_short<br>[website_content field=\"website_biography_short\"]",
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
                            'key'               => 'field_684216d5febzz',
                            'label'             => 'Media List Dump',
                            'name'              => 'media_list',
                            'type'              => 'wysiwyg',
                            'instructions'       => "name: biography_short<br>[website_content field=\"website_media_list\"]",
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
    
                
            

                   
                       // Footer Text
                       array(
                        'key'               => 'field_68420a173f1aa',
                        'label'             => 'Footer Text',
                        'name'              => 'footer_text',
                        'type'              => 'wysiwyg',
                        'instructions'      => "name: footer_text<br>[website_content field=\"website_footer_text\"]",
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

            // Founder Information
            array(
                'key'               => 'field_684217273a45b',
                'label'             => 'Founder Information',
                'name'              => 'founder',
                'type'              => 'group',
                'instructions'      => "name: founder<br>[website_url social=\"founder\"]",
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
                        'label'             => 'Founder',
                        'name'              => 'user',
                        'type'              => 'user',
                        'instructions'      => "",
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
                        'bidirectional_target'=> array(),
                    ),

                    // Founder Biography
                    array(
                        'key'               => 'field_68421959f8873',
                        'label'             => 'Biography',
                        'name'              => 'biography',
                        'type'              => 'wysiwyg',
                        'instructions'      => "name: founder.biography<br>[website_url social=\"founder.biography\"]",
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
