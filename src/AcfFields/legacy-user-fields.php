<?php namespace hws_base_tools;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * Register ACF User Fields
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * This file registers three ACF field groups on user profile screens:
 *
 * 1. "User - Additional"               → additional_public_email, additional_public_phone, additional_title,
 *                                          staff_writer, muckrack_verified, muckrack_url
 * 2. "User - General Fields"           → URLs group (22 platforms), subtitle
 * 3. "Schema.org Structured Data"      → entity_type, education, inception_date, headquarters, sameas
 *
 * All fields support BOTH shortcode prefixes:
 *   [company id="..."]  — pulls from company user
 *   [founder id="..."]  — pulls from founder user
 *
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * COMPLETE SHORTCODE REFERENCE:
 *
 * — User - Additional —
 *   [company id="additional_public_email"]   /  [founder id="additional_public_email"]
 *   [company id="additional_public_phone"]   /  [founder id="additional_public_phone"]
 *   [company id="additional_title"]          /  [founder id="additional_title"]
 *   staff_writer, muckrack_verified, muckrack_url are top-level user meta fields.
 *
 * — User - General Fields: URLs —
 *   [company id="url_facebook"]              /  [founder id="url_facebook"]
 *   [company id="url_instagram"]             /  [founder id="url_instagram"]
 *   [company id="url_linkedin"]              /  [founder id="url_linkedin"]
 *   [company id="url_youtube"]               /  [founder id="url_youtube"]
 *   [company id="url_tiktok"]                /  [founder id="url_tiktok"]
 *   [company id="url_f6s"]                   /  [founder id="url_f6s"]
 *   [company id="url_imdb"]                  /  [founder id="url_imdb"]
 *   [company id="url_muckrack"]              /  [founder id="url_muckrack"]
 *   [company id="url_wikipedia"]             /  [founder id="url_wikipedia"]
 *   [company id="url_x"]                     /  [founder id="url_x"]
 *   [company id="url_soundcloud"]            /  [founder id="url_soundcloud"]
 *   [company id="url_the_org"]               /  [founder id="url_the_org"]
 *   [company id="url_whatsapp"]              /  [founder id="url_whatsapp"]
 *   [company id="url_telegram"]              /  [founder id="url_telegram"]
 *   [company id="url_signal"]                /  [founder id="url_signal"]
 *   [company id="url_calendly"]              /  [founder id="url_calendly"]
 *   [company id="url_amazon"]                /  [founder id="url_amazon"]
 *   [company id="url_github"]                /  [founder id="url_github"]
 *   [company id="url_audible"]               /  [founder id="url_audible"]
 *   [company id="url_threads"]               /  [founder id="url_threads"]
 *   [company id="url_crunchbase"]            /  [founder id="url_crunchbase"]
 *   [company id="url_website"]               /  [founder id="url_website"]
 *
 * — User - General Fields: Other —
 *   [company id="subtitle"]                  /  [founder id="subtitle"]
 *
 * — Schema.org Structured Data —
 *   [company id="entity_type"]               /  [founder id="entity_type"]
 *   [company id="education"]                 /  [founder id="education"]
 *   [company id="inception_date"]            /  [founder id="inception_date"]
 *   [company id="headquarters_location"]     /  [founder id="headquarters_location"]
 *   [company id="headquarters_wiki"]         /  [founder id="headquarters_wiki"]
 *   [company id="sameas"]                    /  [founder id="sameas"]
 *
 * ═══════════════════════════════════════════════════════════════════════════
 */


// ═══════════════════════════════════════════════════════════════════════════
// GROUP 1: USER - ADDITIONAL
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Register ACF User fields: "additional"
 *
 * Group: additional
 *  - public_email      (email)
 *  - public_phone      (text)
 *  - title             (text)
 *  - staff_writer      (true_false toggle)
 *  - muckrack_verified (true_false toggle)
 *  - muckrack_url      (url)
 *
 * Shortcode usage:
 *  - [company id="additional_public_email"]  or  [founder id="additional_public_email"]
 *  - [company id="additional_public_phone"]  or  [founder id="additional_public_phone"]
 *  - [company id="additional_title"]         or  [founder id="additional_title"]
 */
function register_user_custom_fields_additional_2025() {

    // — Bail if ACF is not available
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    acf_add_local_field_group( array(
        'key'                   => 'group_6842_additional_user_fields_2025',
        'title'                 => 'User - Additional',
        'fields'                => array(
            array(
                'key'               => 'field_6842_additional_group',
                'label'             => 'Additional',
                'name'              => 'additional',
                'aria-label'        => '',
                'type'              => 'group',
                // — Group-level instructions showing the shortcode pattern
                'instructions'      => 'Additional user profile fields.<br><strong>Shortcodes:</strong> <code>[company id="additional_{field}"]</code> or <code>[founder id="additional_{field}"]</code><br>Example: <code>[company id="additional_public_email"]</code>, <code>[founder id="additional_title"]</code>',
                'required'          => 0,
                'conditional_logic' => 0,
                'wrapper'           => array(
                    'width' => '',
                    'class' => '',
                    'id'    => '',
                ),
                'layout'            => 'block',
                'sub_fields'        => array(

                    // — Public Email
                    array(
                        'key'               => 'field_6842_additional_public_email',
                        'label'             => 'Public Email',
                        'name'              => 'public_email',
                        'type'              => 'email',
                        // — Both company and founder shortcodes documented
                        'instructions'      => 'Shortcode: <code>[company id="additional_public_email"]</code> or <code>[founder id="additional_public_email"]</code>',
                        'required'          => 0,
                        'conditional_logic' => 0,
                        'wrapper'           => array(
                            'width' => '',
                            'class' => '',
                            'id'    => '',
                        ),
                        'placeholder'       => 'name@example.com',
                    ),

                    // — Public Phone Number
                    array(
                        'key'               => 'field_6842_additional_public_phone',
                        'label'             => 'Public Phone Number',
                        'name'              => 'public_phone',
                        'type'              => 'text',
                        // — Both company and founder shortcodes documented
                        'instructions'      => 'Shortcode: <code>[company id="additional_public_phone"]</code> or <code>[founder id="additional_public_phone"]</code>',
                        'required'          => 0,
                        'conditional_logic' => 0,
                        'wrapper'           => array(
                            'width' => '',
                            'class' => '',
                            'id'    => '',
                        ),
                        'placeholder'       => '555-123-4567',
                    ),

                    // — Title (WYSIWYG editor for rich text formatting)
                    array(
                        'key'               => 'field_6842_additional_title',
                        'label'             => 'Title',
                        'name'              => 'title',
                        'type'              => 'wysiwyg',
                        // — Both company and founder shortcodes documented
                        'instructions'      => 'Shortcode: <code>[company id="additional_title"]</code> or <code>[founder id="additional_title"]</code>',
                        'required'          => 0,
                        'conditional_logic' => 0,
                        'wrapper'           => array(
                            'width' => '',
                            'class' => '',
                            'id'    => '',
                        ),
                        'default_value'     => '',
                        'tabs'              => 'all',
                        'toolbar'           => 'basic',
                        'media_upload'      => 0,
                        'delay'             => 0,
                    ),
                ),
            ),
            array(
                'key'               => 'field_hws_additional_staff_writer',
                'label'             => 'Staff Writer',
                'name'              => 'staff_writer',
                'type'              => 'true_false',
                'instructions'      => '',
                'required'          => 0,
                'conditional_logic' => 0,
                'wrapper'           => array(
                    'width' => '',
                    'class' => '',
                    'id'    => '',
                ),
                'message'           => '',
                'default_value'     => 0,
                'ui'                => 1,
                'ui_on_text'        => 'Yes',
                'ui_off_text'       => 'No',
            ),
            array(
                'key'               => 'field_hws_additional_muckrack_verified',
                'label'             => 'MuckRack Verified',
                'name'              => 'muckrack_verified',
                'type'              => 'true_false',
                'instructions'      => '',
                'required'          => 0,
                'conditional_logic' => 0,
                'wrapper'           => array(
                    'width' => '',
                    'class' => '',
                    'id'    => '',
                ),
                'message'           => '',
                'default_value'     => 0,
                'ui'                => 1,
                'ui_on_text'        => 'Yes',
                'ui_off_text'       => 'No',
            ),
            array(
                'key'               => 'field_hws_additional_muckrack_url',
                'label'             => 'MuckRack URL',
                'name'              => 'muckrack_url',
                'type'              => 'url',
                'instructions'      => '',
                'required'          => 0,
                'conditional_logic' => 0,
                'wrapper'           => array(
                    'width' => '',
                    'class' => '',
                    'id'    => '',
                ),
                'default_value'     => '',
                'placeholder'       => 'https://muckrack.com/...',
            ),
        ),

        // — Show on all user profile screens
        'location'              => array(
            array(
                array(
                    'param'     => 'user_form',
                    'operator'  => '==',
                    'value'     => 'all',
                ),
            ),
        ),

        'menu_order'            => 0,
        'position'              => 'normal',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
        'hide_on_screen'        => '',
        'active'                => true,
        'description'           => '',
        'show_in_rest'          => 0,
    ) );
}


// ═══════════════════════════════════════════════════════════════════════════
// GROUP 2: USER - GENERAL FIELDS
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Register ACF User fields: "general"
 *
 * Group: urls (22 platform sub_fields)
 *   facebook, instagram, linkedin, youtube, tiktok, f6s, imdb, muckrack,
 *   wikipedia, x, soundcloud, the_org, whatsapp, telegram, signal,
 *   calendly, amazon, github, audible, threads, crunchbase, website
 *
 * Top-level: subtitle
 *
 * Shortcode pattern for URLs:
 *   [company id="url_{platform}"]  or  [founder id="url_{platform}"]
 *
 * Shortcode for subtitle:
 *   [company id="subtitle"]  or  [founder id="subtitle"]
 */
function register_user_custom_fields_2025()
{
    // — Bail if ACF is not available
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    acf_add_local_field_group( array(
        'key' => 'group_684252fd99081',
        'title' => '',
        'fields' => array(

            // ─────────────────────────────────────────────────────────────
            // URLs Group — 22 social media / web URL fields
            // ─────────────────────────────────────────────────────────────
            array(
                'key' => 'field_684253229b32a',
                'label' => 'URLs',
                'name' => 'urls',
                'aria-label' => '',
                'type' => 'group',
                // — Group-level instructions showing the URL shortcode pattern
                'instructions' => 'Social media and web URLs.<br><strong>Shortcodes:</strong> <code>[company id="url_{platform}"]</code> or <code>[founder id="url_{platform}"]</code><br>Example: <code>[company id="url_facebook"]</code>, <code>[founder id="url_linkedin"]</code>',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ),
                'layout' => 'block',
                'sub_fields' => array(

                    // — Facebook
                    array(
                        'key' => 'field_6842532c9b32b',
                        'label' => 'Facebook',
                        'name' => 'facebook',
                        'aria-label' => '',
                        'type' => 'text',
                        // — Both company and founder shortcodes documented
                        'instructions' => 'Shortcode: <code>[company id="url_facebook"]</code> or <code>[founder id="url_facebook"]</code>',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),

                    // — Instagram
                    array(
                        'key' => 'field_684253319b32c',
                        'label' => 'Instagram',
                        'name' => 'instagram',
                        'aria-label' => '',
                        'type' => 'text',
                        // — Both company and founder shortcodes documented
                        'instructions' => 'Shortcode: <code>[company id="url_instagram"]</code> or <code>[founder id="url_instagram"]</code>',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),

                    // — LinkedIn
                    array(
                        'key' => 'field_684253709b32d',
                        'label' => 'LinkedIn',
                        'name' => 'linkedin',
                        'aria-label' => '',
                        'type' => 'text',
                        // — Both company and founder shortcodes documented
                        'instructions' => 'Shortcode: <code>[company id="url_linkedin"]</code> or <code>[founder id="url_linkedin"]</code>',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),

                    // — YouTube
                    array(
                        'key' => 'field_684253859b32e',
                        'label' => 'YouTube',
                        'name' => 'youtube',
                        'aria-label' => '',
                        'type' => 'text',
                        // — Both company and founder shortcodes documented
                        'instructions' => 'Shortcode: <code>[company id="url_youtube"]</code> or <code>[founder id="url_youtube"]</code>',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),

                    // — TikTok
                    array(
                        'key' => 'field_6842538b9b32f',
                        'label' => 'TikTok',
                        'name' => 'tiktok',
                        'aria-label' => '',
                        'type' => 'text',
                        // — Both company and founder shortcodes documented
                        'instructions' => 'Shortcode: <code>[company id="url_tiktok"]</code> or <code>[founder id="url_tiktok"]</code>',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),

                    // — F6S
                    array(
                        'key' => 'field_684253939b330',
                        'label' => 'F6S',
                        'name' => 'f6s',
                        'aria-label' => '',
                        'type' => 'text',
                        // — Both company and founder shortcodes documented
                        'instructions' => 'Shortcode: <code>[company id="url_f6s"]</code> or <code>[founder id="url_f6s"]</code>',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),

                    // — iMDb
                    array(
                        'key' => 'field_6842539a9b331',
                        'label' => 'iMDb',
                        'name' => 'imdb',
                        'aria-label' => '',
                        'type' => 'text',
                        // — Both company and founder shortcodes documented
                        'instructions' => 'Shortcode: <code>[company id="url_imdb"]</code> or <code>[founder id="url_imdb"]</code>',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),

                    // — MuckRack
                    array(
                        'key' => 'field_684253a19b332',
                        'label' => 'MuckRack',
                        'name' => 'muckrack',
                        'aria-label' => '',
                        'type' => 'text',
                        // — Both company and founder shortcodes documented
                        'instructions' => 'Shortcode: <code>[company id="url_muckrack"]</code> or <code>[founder id="url_muckrack"]</code>',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),

                    // — Wikipedia
                    array(
                        'key' => 'field_684253b368923',
                        'label' => 'Wikipedia',
                        'name' => 'wikipedia',
                        'aria-label' => '',
                        'type' => 'text',
                        // — Both company and founder shortcodes documented
                        'instructions' => 'Shortcode: <code>[company id="url_wikipedia"]</code> or <code>[founder id="url_wikipedia"]</code>',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),

                    // — X (Twitter)
                    array(
                        'key' => 'field_684254170a2bc',
                        'label' => 'X',
                        'name' => 'x',
                        'aria-label' => '',
                        'type' => 'text',
                        // — Both company and founder shortcodes documented
                        'instructions' => 'Shortcode: <code>[company id="url_x"]</code> or <code>[founder id="url_x"]</code>',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),

                    // — SoundCloud
                    array(
                        'key' => 'field_6842542bf309c',
                        'label' => 'SoundCloud',
                        'name' => 'soundcloud',
                        'aria-label' => '',
                        'type' => 'text',
                        // — Both company and founder shortcodes documented
                        'instructions' => 'Shortcode: <code>[company id="url_soundcloud"]</code> or <code>[founder id="url_soundcloud"]</code>',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),

                    // — The Org
                    array(
                        'key' => 'field_6842543e3e795',
                        'label' => 'The Org',
                        'name' => 'the_org',
                        'aria-label' => '',
                        'type' => 'text',
                        // — Both company and founder shortcodes documented
                        'instructions' => 'Shortcode: <code>[company id="url_the_org"]</code> or <code>[founder id="url_the_org"]</code>',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),

                    // — WhatsApp
                    array(
                        'key' => 'field_684347c9ff6e6',
                        'label' => 'WhatsApp',
                        'name' => 'whatsapp',
                        'aria-label' => '',
                        'type' => 'text',
                        // — Both company and founder shortcodes documented
                        'instructions' => 'Shortcode: <code>[company id="url_whatsapp"]</code> or <code>[founder id="url_whatsapp"]</code>',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),

                    // — Telegram
                    array(
                        'key' => 'field_684347d0ff6e7',
                        'label' => 'Telegram',
                        'name' => 'telegram',
                        'aria-label' => '',
                        'type' => 'text',
                        // — Both company and founder shortcodes documented
                        'instructions' => 'Shortcode: <code>[company id="url_telegram"]</code> or <code>[founder id="url_telegram"]</code>',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),

                    // — Signal
                    array(
                        'key' => 'field_684347d7ff6e8',
                        'label' => 'Signal',
                        'name' => 'signal',
                        'aria-label' => '',
                        'type' => 'text',
                        // — Both company and founder shortcodes documented
                        'instructions' => 'Shortcode: <code>[company id="url_signal"]</code> or <code>[founder id="url_signal"]</code>',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),

                    // — Calendly
                    array(
                        'key' => 'field_6843473dfse8',
                        'label' => 'Calendly',
                        'name' => 'calendly',
                        'aria-label' => '',
                        'type' => 'text',
                        // — Both company and founder shortcodes documented
                        'instructions' => 'Shortcode: <code>[company id="url_calendly"]</code> or <code>[founder id="url_calendly"]</code>',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),

                    // — Amazon
                    array(
                        'key' => 'field_684347edff6e9',
                        'label' => 'Amazon',
                        'name' => 'amazon',
                        'aria-label' => '',
                        'type' => 'text',
                        // — Both company and founder shortcodes documented
                        'instructions' => 'Shortcode: <code>[company id="url_amazon"]</code> or <code>[founder id="url_amazon"]</code>',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),

                    // — GitHub
                    array(
                        'key' => 'field_684347f3ff6ea',
                        'label' => 'GitHub',
                        'name' => 'github',
                        'aria-label' => '',
                        'type' => 'text',
                        // — Both company and founder shortcodes documented
                        'instructions' => 'Shortcode: <code>[company id="url_github"]</code> or <code>[founder id="url_github"]</code>',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),

                    // — Audible
                    array(
                        'key' => 'field_68434830ff6eb',
                        'label' => 'Audible',
                        'name' => 'audible',
                        'aria-label' => '',
                        'type' => 'text',
                        // — Both company and founder shortcodes documented
                        'instructions' => 'Shortcode: <code>[company id="url_audible"]</code> or <code>[founder id="url_audible"]</code>',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),

                    // — Threads
                    array(
                        'key' => 'field_684348705db23',
                        'label' => 'Threads',
                        'name' => 'threads',
                        'aria-label' => '',
                        'type' => 'text',
                        // — Both company and founder shortcodes documented
                        'instructions' => 'Shortcode: <code>[company id="url_threads"]</code> or <code>[founder id="url_threads"]</code>',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),

                    // — CrunchBase
                    array(
                        'key' => 'field_68434891375bb',
                        'label' => 'CrunchBase',
                        'name' => 'crunchbase',
                        'aria-label' => '',
                        'type' => 'text',
                        // — Both company and founder shortcodes documented
                        'instructions' => 'Shortcode: <code>[company id="url_crunchbase"]</code> or <code>[founder id="url_crunchbase"]</code>',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),

                    // — Website
                    array(
                        'key' => 'field_684348b2480e0',
                        'label' => 'Website',
                        'name' => 'website',
                        'aria-label' => '',
                        'type' => 'text',
                        // — Both company and founder shortcodes documented
                        'instructions' => 'Shortcode: <code>[company id="url_website"]</code> or <code>[founder id="url_website"]</code>',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),
                ),
            ),

            // ─────────────────────────────────────────────────────────────
            // Subtitle — top-level field
            // ─────────────────────────────────────────────────────────────
            array(
                'key' => 'field_684348d1832e2',
                'label' => 'Subtitle',
                'name' => 'subtitle',
                'aria-label' => '',
                'type' => 'text',
                // — Both company and founder shortcodes documented
                'instructions' => 'Shortcode: <code>[company id="subtitle"]</code> or <code>[founder id="subtitle"]</code>',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                'default_value' => '',
                'maxlength' => '',
                'allow_in_bindings' => 0,
                'placeholder' => '',
                'prepend' => '',
                'append' => '',
            ),

        ),

        // — Show on all user roles
        'location' => array(
            array(
                array(
                    'param' => 'user_role',
                    'operator' => '==',
                    'value' => 'all',
                ),
            ),
        ),

        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'hide_on_screen' => '',
        'active' => true,
        'description' => '',
        'show_in_rest' => 0,
    ) );
}


// ═══════════════════════════════════════════════════════════════════════════
// LEGACY: OLD PROFILE FIELD GROUP (disabled — returns immediately)
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Legacy user custom fields registration.
 * This function is disabled (returns early) — kept for reference only.
 */
function register_user_custom_fields(){
    // — Disabled: legacy field group no longer in use
    return;

    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    acf_add_local_field_group( array(
        'key' => 'group_590d64c31db0a',
        'title' => 'Profile',
        'fields' => array(
            array(
                'key' => 'field_590bebe0280ad',
                'label' => 'Title',
                'name' => 'job_title',
                'aria-label' => '',
                'type' => 'text',
                'instructions' => 'ex: Activist, Journalist ...',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                'default_value' => '',
                'placeholder' => '',
                'prepend' => '',
                'append' => '',
                'formatting' => 'html',
                'maxlength' => '',
            ),
            array(
                'key' => 'field_66822d0c39f9f',
                'label' => 'Location',
                'name' => 'location',
                'aria-label' => '',
                'type' => 'text',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                'default_value' => '',
                'maxlength' => '',
                'placeholder' => '',
                'prepend' => '',
                'append' => '',
            ),
            array(
                'key' => 'field_66822ba5423ab',
                'label' => 'Socials',
                'name' => 'socials',
                'aria-label' => '',
                'type' => 'group',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                'layout' => 'block',
                'sub_fields' => array(
                    array(
                        'key' => 'field_66822be1423ac',
                        'label' => 'Facebook',
                        'name' => 'facebook',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),
                    array(
                        'key' => 'field_66822beb423ad',
                        'label' => 'LinkedIn',
                        'name' => 'linkedin',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),
                    array(
                        'key' => 'field_66822bf0423ae',
                        'label' => 'X',
                        'name' => 'x',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),
                    array(
                        'key' => 'field_66822bf4423af',
                        'label' => 'YouTube',
                        'name' => 'youtube',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),
                    array(
                        'key' => 'field_66822bfc423b0',
                        'label' => 'Instagram',
                        'name' => 'instagram',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),
                    array(
                        'key' => 'field_66c57bff4da4e',
                        'label' => 'SoundCloud',
                        'name' => 'soundcloud',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),
                    array(
                        'key' => 'field_66c57c125b4ee',
                        'label' => 'TikTok',
                        'name' => 'tiktok',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),
                ),
            ),
            array(
                'key' => 'field_66822cd7b0e18',
                'label' => 'Profiles',
                'name' => 'profiles',
                'aria-label' => '',
                'type' => 'group',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                'layout' => 'block',
                'sub_fields' => array(
                    array(
                        'key' => 'field_66822ce7b0e19',
                        'label' => 'Wikipedia',
                        'name' => 'wikipedia',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),
                    array(
                        'key' => 'field_66822cefb0e1a',
                        'label' => 'Crunchbase',
                        'name' => 'crunchbase',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),
                    array(
                        'key' => 'field_66822d01b0e1b',
                        'label' => 'MuckRack',
                        'name' => 'muckrack',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),
                    array(
                        'key' => 'field_66822df8613b4',
                        'label' => 'F6S',
                        'name' => 'f6s',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),
                    array(
                        'key' => 'field_66c57c3cf9bb0',
                        'label' => 'iMDb',
                        'name' => 'imdb',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                        'default_value' => '',
                        'maxlength' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),
                ),
            ),
            array(
                'key' => 'field_66822e117671a',
                'label' => 'Schema Markup',
                'name' => 'schema_markup',
                'aria-label' => '',
                'type' => 'textarea',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                'default_value' => '',
                'maxlength' => '',
                'rows' => '',
                'placeholder' => '',
                'new_lines' => '',
            ),
            array(
                'key' => 'field_66824b8521a57',
                'label' => 'Photos',
                'name' => 'photos',
                'aria-label' => '',
                'type' => 'gallery',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array( 'width' => '', 'class' => '', 'id' => '' ),
                'return_format' => 'array',
                'library' => 'all',
                'min' => '',
                'max' => '',
                'min_width' => '',
                'min_height' => '',
                'min_size' => '',
                'max_width' => '',
                'max_height' => '',
                'max_size' => '',
                'mime_types' => '',
                'insert' => 'append',
                'preview_size' => 'medium',
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'user_role',
                    'operator' => '==',
                    'value' => 'all',
                ),
            ),
        ),
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'seamless',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'hide_on_screen' => '',
        'active' => true,
        'description' => '',
        'show_in_rest' => 0,
    ) );

}
