<?php
namespace hws_base_tools;

// register the shortcode
add_action( 'init', function() {
    add_shortcode( 'website_url', __NAMESPACE__ . '\\website_url_shortcode' );
    add_shortcode( 'website_content', __NAMESPACE__ . '\\website_content_shortcode' );

} );


/**
 * Hook into ACF’s user‐select field to render extra info.
 */
function enable_website_settings_functionality() {
    add_action(
        'acf/render_field/name=user',
        __NAMESPACE__ . '\\hf_render_user_info_once',
        10,
        1
    );
}

/**
 * Render avatar, basic info, buttons, *and* ACF “urls” sub-fields as clickable links.
 *
 * @param array $field ACF field settings/values.
 */
function hf_render_user_info_once( $field ) {
    // Only run on the Select2 pass (type "user")
    if ( $field['type'] !== 'user' ) {
        return;
    }

    $user_id = intval( $field['value'] );
    if ( ! $user_id ) {
        return;
    }

    $user = get_userdata( $user_id );
    if ( ! $user ) {
        return;
    }

    $edit_url = get_edit_user_link( $user_id );
    $view_url = get_author_posts_url( $user_id );

    // Basic user info block
    echo '<div class="hf-user-info-wrapper" style="
            background: #fbfbfb;
            border: 1px solid #ddd;
            padding: 12px;
            margin-top: 12px;
            border-radius: 4px;
        ">
        <div class="hf-user-avatar" style="float: left; margin-right: 12px;">'
            . get_avatar( $user_id, 64 ) .
        '</div>

        <div class="hf-user-basic-info" style="overflow: hidden;">
            <p style="margin:0 0 4px;"><strong>Username:</strong> '
                . esc_html( $user->user_login ) . '</p>
            <p style="margin:0 0 4px;"><strong>Name:</strong> '
                . esc_html( $user->display_name ) . '</p>
            <p style="margin:0 0 4px;"><strong>Email:</strong>
                <a href="mailto:' . esc_attr( $user->user_email ) . '">'
                    . esc_html( $user->user_email ) . '</a>
            </p>
            <p style="margin:0 0 4px;"><strong>Role'
                . ( count( $user->roles ) > 1 ? 's' : '' ) . ':</strong> '
                . esc_html( implode( ', ', $user->roles ) ) . '</p>
            <p style="margin:0 0 4px;"><strong>Registered:</strong> '
                . esc_html( date_i18n( get_option('date_format'), strtotime( $user->user_registered ) ) )
            . '</p>
        </div>

        <div style="clear: both; margin-top: 8px;"></div>
        <div class="hf-user-buttons" style="margin-top: 8px;">';
            if ( $edit_url ) {
                echo '<a href="' . esc_url( $edit_url ) . '" target="_blank" class="button" style="margin-right:6px;">
                        Edit User
                      </a>';
            }
            if ( $view_url ) {
                echo '<a href="' . esc_url( $view_url ) . '" target="_blank" class="button">
                        View Profile
                      </a>';
            }
    echo '</div>';

    // --- New: display ACF "urls" group subfields as clickable links ---
    $urls = get_field( 'urls', 'user_' . $user_id ) ?: [];

    // map sub-field names to human labels
    $url_fields = [
        'facebook'   => 'Facebook',
        'instagram'  => 'Instagram',
        'linkedin'   => 'LinkedIn',
        'youtube'    => 'YouTube',
        'tiktok'     => 'TikTok',
        'f6s'        => 'F6S',
        'imdb'       => 'iMDb',
        'muckrack'   => 'MuckRack',
        'wikipedia'  => 'Wikipedia',
        'x'          => 'X',
        'soundcloud' => 'SoundCloud',
        'the_org'    => 'The Org',
        'whatsapp'   => 'WhatsApp',
        'telegram'   => 'Telegram',
        'signal'     => 'Signal',
        'amazon'     => 'Amazon',
        'github'     => 'GitHub',
        'audible'    => 'Audible',
        'threads'    => 'Threads',
        'crunchbase' => 'CrunchBase',
        'website'    => 'Website',
    ];

    // Only show if there's at least one URL
    $has_url = false;
    foreach ( $url_fields as $key => $label ) {
        if ( ! empty( $urls[ $key ] ) ) {
            $has_url = true;
            break;
        }
    }

    if ( $has_url ) {
        echo '<div class="hf-user-urls" style="margin-top:12px;">';
        foreach ( $url_fields as $key => $label ) {
            if ( ! empty( $urls[ $key ] ) ) {
                $url = $urls[ $key ];
                echo '<p style="margin:0 0 4px;"><strong>' 
                    . esc_html( $label ) . ':</strong> '
                    . '<a href="' . esc_url( $url ) 
                        . '" target="_blank" class="hf-user-url urls_' 
                        . esc_attr( $key ) . '">'
                        . esc_html( $url ) . '</a>'
                    . '</p>';
            }
        }
        echo '</div>';
    }

    // Close main wrapper
    echo '</div>';
}





/* shortcode declarations */

/**
 * Shortcode: [website_url social="facebook"]
 * Returns the requested social-URL from the “Website” user picked in Website Settings.
 */
function website_url_shortcode( $atts ) {

    $atts = shortcode_atts( [
        'social' => '',
    ], $atts, 'website_url' );

    $key = sanitize_key( $atts['social'] );
    if ( ! $key ) {
        return '';
    }

    // load Website Settings group (options page)
    $website = get_field( 'website', 'option' );
    if ( ! ( is_array( $website ) && ! empty( $website['user']['ID'] ) ) ) {
        return '';
    }

    // pull that user’s “urls” repeater/array
    $user_id   = $website['user']['ID'];
    $user_urls = get_field( 'urls', 'user_' . $user_id );

    if ( is_array( $user_urls ) && ! empty( $user_urls[ $key ] ) ) {
        return esc_url( $user_urls[ $key ] );
    }

    return '';
}



/**
 * Shortcode: [website_content field="FIELD_NAME"]
 * Returns the raw ACF option field value from the Website Settings page.
 */
function website_content_shortcode( $atts ) {
    $atts = shortcode_atts( [
        'field' => '',
    ], $atts, 'website_content' );

    if ( empty( $atts['field'] ) ) {
        return '';
    }

    return get_field( $atts['field'], 'option' ) ?: '';
}


