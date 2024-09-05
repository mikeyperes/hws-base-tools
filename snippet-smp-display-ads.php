<?php namespace hws_base_tools;



function smp_add_adpushup_script_to_head2() {
    write_log('✅ Function smp_add_adpushup_script_to_head is called.', true);  // Add this for debugging
    
      // Since 'account_ad_code' is within the 'ad_codes' group, access it like this:
      $ad_codes = get_field('ad_codes', 'option'); // Retrieve the group field
      $ad_account_code = $ad_codes['account_ad_code'] ?? ''; // Access the specific subfield

      /*
    if (!$ad_account_code) {
        write_log('🚨 Error: Ad account code not set in ACF fields.', true);
        return;
    }*/
    write_log('✅ DONE', true);  // Add this for debugging

    // Use wp_add_inline_script to add the script in the head
    wp_add_inline_script('jquery', "
        (function(w, d) {
            var s = d.createElement('script');
            s.src = '//cdn.adpushup.com/" . esc_attr($ad_account_code) . "/adpushup.js';
            s.crossOrigin = 'anonymous';
            s.type = 'text/javascript';
            s.async = true;
            (d.getElementsByTagName('head')[0] || d.getElementsByTagName('body')[0]).appendChild(s);
            w.adpushup = w.adpushup || {que: []};
        })(window, document);
    ");
}




function smp_add_adpushup_script_to_head() {
    write_log('✅ Function smp_add_adpushup_script_to_head is called.', true);  // Add this for debugging
    
    $ad_account_code = get_field('account_ad_code', 'option');
    
    if (!$ad_account_code) {
        write_log('🚨 Error: Ad account code not set in ACF fields.', true);
        return;
    }

    echo '<!-- AdPushup script included -->';
    echo '<script data-cfasync="false" type="text/javascript">
    (function(w, d) {
        var s = d.createElement("script");
        s.src = "//cdn.adpushup.com/' . esc_attr($ad_account_code) . '/adpushup.js";
        s.crossOrigin="anonymous"; 
        s.type = "text/javascript"; s.async = true;
        (d.getElementsByTagName("head")[0] || d.getElementsByTagName("body")[0]).appendChild(s);
        w.adpushup = w.adpushup || {que:[]};
    })(window, document);
    </script>';
}

function display_dynamic_ad($atts) {
    $atts = shortcode_atts(array(
        'ad_type' => 'banner', // Default is 'banner'
    ), $atts, 'smp_display_ad');

    // Get ad codes from ACF fields
    $ads = array(
        'banner' => array(
            'desktop' => get_field('banner_desktop', 'option'),
            'tablet'  => get_field('banner_tablet', 'option'),
            'mobile'  => get_field('banner_mobile', 'option'),
        ),
        'sidebar' => array(
            'desktop' => get_field('sidebar_desktop', 'option'),
            'mobile'  => get_field('sidebar_mobile', 'option'),
        ),
        'skyscraper' => array(
            'desktop' => get_field('skyscraper_desktop', 'option'),
        ),
        'mobile_banner' => array(
            'mobile'  => get_field('mobile_banner', 'option'),
        ),
    );

    // Ad selection logic
    return "
    <div id='dynamic-ad-container-{$atts['ad_type']}' class='dynamic-ad-container'></div>
    <script>
        (function() {
            var width = window.innerWidth;
            var adCode = '';

            switch('{$atts['ad_type']}') {
                case 'banner':
                    adCode = width >= 970 ? '{$ads['banner']['desktop']}' :
                             width >= 728 ? '{$ads['banner']['tablet']}' :
                             '{$ads['banner']['mobile']}';
                    break;
                case 'sidebar':
                    adCode = width >= 728 ? '{$ads['sidebar']['desktop']}' : '{$ads['sidebar']['mobile']}';
                    break;
                case 'skyscraper':
                    adCode = '{$ads['skyscraper']['desktop']}';
                    break;
                case 'mobile_banner':
                    adCode = '{$ads['mobile_banner']['mobile']}';
                    break;
                default:
                    adCode = '{$ads['banner']['desktop']}';
            }

            if (adCode) {
                var adContainer = document.getElementById('dynamic-ad-container-{$atts['ad_type']}');
                adContainer.innerHTML = '<div id=\"' + adCode + '\" class=\"_ap_apex_ad\" style=\"width: 100%; height: auto;\"></div>';
                var adpushup = window.adpushup = window.adpushup || {};
                adpushup.que = adpushup.que || [];
                adpushup.que.push(function() {
                    adpushup.triggerAd(adCode);
                });
            }
        })();
    </script>";
}

function snippet_smp_display_ads_register_acfs() {
    add_action( 'acf/include_fields', function() {

    
        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }
    
        acf_add_local_field_group( array(
        'key' => 'group_66d6a31b403a2',
        'title' => 'SMP - Display Ads',
        'fields' => array(
            array(
                'key' => 'field_66d6a38306b9f',
                'label' => 'Ad Codes',
                'name' => 'ad_codes',
                'aria-label' => '',
                'type' => 'group',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ),
                'layout' => 'block',
                'sub_fields' => array(
                    array(
                        'key' => 'field_66d6a6495362b',
                        'label' => 'Account Ad Code',
                        'name' => 'account_ad_code',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array(
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ),
                        'default_value' => '',
                        'maxlength' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),
                    array(
                        'key' => 'field_66d6a39206ba0',
                        'label' => 'Banner Desktop',
                        'name' => 'banner_desktop',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array(
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ),
                        'default_value' => '',
                        'maxlength' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),
                    array(
                        'key' => 'field_66d6a47806ba2',
                        'label' => 'Banner Tablet',
                        'name' => 'banner_tablet',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array(
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ),
                        'default_value' => '',
                        'maxlength' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),
                    array(
                        'key' => 'field_66d6a48006ba3',
                        'label' => 'Banner Mobile',
                        'name' => 'banner_mobile',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array(
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ),
                        'default_value' => '',
                        'maxlength' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),
                    array(
                        'key' => 'field_66d6a48506ba4',
                        'label' => 'Sidebar Desktop',
                        'name' => 'sidebar_desktop',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array(
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ),
                        'default_value' => '',
                        'maxlength' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),
                    array(
                        'key' => 'field_66d6a48f06ba5',
                        'label' => 'Sidebar Mobile',
                        'name' => 'sidebar_mobile',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array(
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ),
                        'default_value' => '',
                        'maxlength' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),
                    array(
                        'key' => 'field_66d6a49606ba6',
                        'label' => 'Skyscraper Desktop',
                        'name' => 'skyscraper_desktop',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array(
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ),
                        'default_value' => '',
                        'maxlength' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),
                    array(
                        'key' => 'field_66d6a4a406ba7',
                        'label' => 'Mobile Banner',
                        'name' => 'mobile_banner',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array(
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ),
                        'default_value' => '',
                        'maxlength' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ),
                ),
            ),
        ),
        'location' => array(
        array(
            array(
                'param' => 'options_page',
                'operator' => '==',
                'value' => 'display-ads-smp',
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
    } );
    
    add_action('plugins_loaded', function() {
        if (function_exists('acf_add_options_page')) {
            \acf_add_options_page(array(
                'page_title' => 'Display Ads (Scale My Publication)',
                'menu_slug' => 'display-ads-smp',
                'redirect' => false,
            ));
        } else {
            write_log('🚫 ACF function acf_add_options_page does not exist. Ensure that the ACF plugin is active.', true);
        }
    });
    
    
}

add_action('plugins_loaded', function() {
    if (function_exists('acf_add_options_page')) {
        \acf_add_options_page(array(
            'page_title' => 'Display Ads (Scale My Publication)',
            'menu_slug' => 'display-ads-smp',
            'redirect' => false,
        ));
    } else {
        write_log('🚫 ACF function acf_add_options_page does not exist. Ensure that the ACF plugin is active.', true);
    }
});




function activate_snippet_smp_display_ads() {
    write_log('✅ hi', true);  // Add this for debugging


    add_action('plugins_loaded', function() {
        add_action('wp_enqueue_scripts', function() {
            wp_add_inline_script('jquery', 'alert("hi");');
        });
    });
    
    // Register ACF fields
    snippet_smp_display_ads_register_acfs();
    write_log('✅ hi2', true);  // Add this for debugging

    // Add AdPushup script to the head
   //add_action('wp_head', 'hws_base_tools\smp_add_adpushup_script_to_head');
    // Hook into wp_enqueue_scripts with the same function name
add_action('wp_enqueue_scripts', 'smp_add_adpushup_script_to_head2');
write_log('✅ hi3', true);  // Add this for debugging


    // Register shortcode for dynamic ad display
    add_shortcode('smp_display_ad', 'hws_base_tools\display_dynamic_ad');
}
