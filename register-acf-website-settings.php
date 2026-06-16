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

            // Founder Information - MOVED TO TOP
            array(
                'key'               => 'field_684217273a45b',
                'label'             => 'Founder Information',
                'name'              => 'founder',
                'type'              => 'group',
                'instructions'      => "Select the founder/owner of this website. Their profile data will be accessible via shortcodes.",
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
                        'name'              => 'founder_user',
                        'type'              => 'user',
                        'instructions'      => "Select the founder. Use <code>[founder id=\"title\"]</code> to display their name.",
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
                        'allow_null'        => 1,
                        'allow_in_bindings' => 0,
                        'bidirectional'     => 0,
                        'bidirectional_target'=> array(),
                    ),

                    // Founder Biography Override
                    array(
                        'key'               => 'field_68421959f8873',
                        'label'             => 'Biography (Override)',
                        'name'              => 'biography',
                        'type'              => 'wysiwyg',
                        'instructions'      => "Optional: Override the founder's user bio.<br>Shortcode: <code>[founder id=\"biography\"]</code>",
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

            // Website Information
            array(
                'key'               => 'field_68420a023f1c8',
                'label'             => 'Website Information',
                'name'              => 'website',
                'type'              => 'group',
                'instructions'      => "General website settings and the main company/organization entity.",
                'required'          => 0,
                'conditional_logic' => 0,
                'wrapper'           => array(
                    'width' => '',
                    'class' => '',
                    'id'    => '',
                ),
                'layout'            => 'block',
                'sub_fields'        => array(


        // Company User
        array(
            'key'               => 'field_68421889dd80b',
            'label'             => 'Company',
            'name'              => 'company',
            'type'              => 'user',
            'instructions'      => "Select the main entity (company, organization, publication). Use <code>[company id=\"title\"]</code> to display their name.",
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
            'allow_null'        => 1,
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
            'instructions'      => "Public contact email.<br>Shortcode: <code>[website_content field=\"website_email\"]</code>",
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
                        'instructions'      => "DMCA policy content.<br>Shortcode: <code>[website_content field=\"website_dmca\"]</code>",
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
                        'instructions'      => "Website mission statement.<br>Shortcode: <code>[website_content field=\"website_mission_statement\"]</code>",
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
                        'instructions'      => "Website biography/about content.<br>Shortcode: <code>[website_content field=\"website_biography\"]</code>",
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
                        'instructions'      => "Short bio for excerpts.<br>Shortcode: <code>[website_content field=\"website_biography_short\"]</code>",
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

                    // Media List Dump
                    array(
                        'key'               => 'field_684216d5febzz',
                        'label'             => 'Media List Dump',
                        'name'              => 'media_list',
                        'type'              => 'wysiwyg',
                        'instructions'      => "Media appearances list.<br>Shortcode: <code>[website_content field=\"website_media_list\"]</code>",
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
                        'instructions'      => "Footer content.<br>Shortcode: <code>[website_content field=\"website_footer_text\"]</code>",
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

            array(
                'key'               => 'field_hws_brand_assets_gallery',
                'label'             => 'Brand Assets Gallery',
                'name'              => 'brand_assets_gallery',
                'type'              => 'gallery',
                'instructions'      => "Shared brand image gallery. Managed from HWS Base Tools → Brand Assets.<br>Shortcode: <code>[brand_asset_gallery]</code>",
                'required'          => 0,
                'conditional_logic' => 0,
                'wrapper'           => array(
                    'width' => '',
                    'class' => '',
                    'id'    => '',
                ),
                'return_format'     => 'id',
                'library'           => 'all',
                'min'               => '',
                'max'               => '',
                'min_width'         => '',
                'min_height'        => '',
                'min_size'          => '',
                'max_width'         => '',
                'max_height'        => '',
                'max_size'          => '',
                'mime_types'        => 'jpg,jpeg,png,gif,webp,svg',
                'insert'            => 'append',
                'preview_size'      => 'thumbnail',
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
