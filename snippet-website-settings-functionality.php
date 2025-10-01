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
    ], 
    $atts, 
    'website_content' );

    if ( empty( $atts['field'] ) ) {
        return '';
    }

    return get_field( $atts['field'], 'option' ) ?: '';
}














\add_shortcode( 'company', __NAMESPACE__ . '\\company_shortcode' );

/**
 * [company id="..."] — fetch data from the “company” User (ACF Options: website -> company).
 *
 * Supported:
 *   - title | biography | website | url_{platform}
 *   - any direct ACF user field, e.g. [company id="biography"] (already supported)
 *   - any nested group field as group_subfield, e.g. [company id="additional_public_email"]
 *
 * Notes:
 *   - Biography returns HTML as-is; URLs are escaped; other text is escaped.
 */
function company_shortcode( $atts ): string {
    $atts = shortcode_atts( [ 'id' => 'title' ], $atts, 'company' );
    $requested = strtolower( trim( (string) $atts['id'] ) );

    if ( ! function_exists( 'get_field' ) ) {
        return '';
    }

    // Resolve company user from options
    $website = get_field( 'website', 'option' );
    if ( ! ( is_array( $website ) && ! empty( $website['company']['ID'] ) ) ) {
        return '';
    }
    $user_id  = (int) $website['company']['ID'];
    $userdata = get_userdata( $user_id );
    $user_key = 'user_' . $user_id;

    // Common data
    $user_name = $userdata ? $userdata->display_name : '';
    $user_urls = get_field( 'urls', $user_key );       // array of platforms
    $user_bio  = (string) get_field( 'biography', $user_key );
    $user_site = (string) get_field( 'website', $user_key );

    // First: explicit known ids
    switch ( $requested ) {
        case 'title':
            return $user_name ? esc_html( $user_name ) : '';

        case 'biography':
            if ( $user_bio === '' ) {
                $core_bio = $userdata ? (string) $userdata->description : '';
                return $core_bio !== '' ? $core_bio : '';
            }
            return $user_bio; // allow HTML

        case 'website':
            if ( $user_site !== '' ) {
                return esc_url( $user_site );
            }
            if ( is_array( $user_urls ) && ! empty( $user_urls['website'] ) ) {
                return esc_url( (string) $user_urls['website'] );
            }
            $core_url = $userdata ? (string) $userdata->user_url : '';
            return $core_url !== '' ? esc_url( $core_url ) : '';
    }

    // Platform URLs: id="url_*"
    $platform = '';
    if ( ( function_exists('str_starts_with') && str_starts_with( $requested, 'url_' ) )
         || substr( $requested, 0, 4 ) === 'url_' ) {
        $platform = sanitize_key( substr( $requested, 4 ) );
        if ( $platform !== '' && is_array( $user_urls ) && ! empty( $user_urls[ $platform ] ) ) {
            return esc_url( (string) $user_urls[ $platform ] );
        }
    }

    // ---- Generic ACF resolver for user meta ----
    // 1) Try direct field on user (e.g., id="some_field")
    $direct = get_field( $requested, $user_key );
    if ( is_string( $direct ) && $direct !== '' ) {
        // If it looks like a URL, escape as URL; if it's biography-like, allow HTML; else escape text.
        if ( filter_var( $direct, FILTER_VALIDATE_URL ) ) {
            return esc_url( $direct );
        }
        if ( in_array( $requested, ['biography','bio'], true ) ) {
            return $direct; // allow HTML
        }
        return esc_html( $direct );
    }

    // 2) Try group_subfield (e.g., "additional_public_email")
    //    We treat the first token as the group, the rest joined by '_' as the subkey.
    if ( strpos( $requested, '_' ) !== false ) {
        $parts   = explode( '_', $requested );
        $group   = array_shift( $parts );        // e.g. 'additional'
        $subkey  = implode( '_', $parts );       // e.g. 'public_email'

        if ( $group && $subkey ) {
            $group_val = get_field( $group, $user_key ); // should be array
            if ( is_array( $group_val ) && isset( $group_val[ $subkey ] ) ) {
                $val = $group_val[ $subkey ];
                if ( is_string( $val ) && $val !== '' ) {
                    if ( filter_var( $val, FILTER_VALIDATE_URL ) ) {
                        return esc_url( $val );
                    }
                    // Very common cases: email/phone plain text
                    if ( is_email( $val ) ) {
                        return esc_html( $val );
                    }
                    return esc_html( $val );
                }
            }
        }
    }

    return '';
}










/**
 * Admin UI: Inject a “Company User” card with shortcode examples into Theme Options.
 *
 * Hook: admin_enqueue_scripts
 *
 * What this does:
 *  1) Ensures we’re on the Theme Options admin screen (toplevel_page_theme-options).
 *  2) Loads jQuery for admin.
 *  3) Reads ACF Options group "website" and the sub-field "company" (ACF user field, return_format=array).
 *  4) Reads that user’s ACF field "urls" from the user meta scope ("user_{ID}").
 *  5) Localizes these data to JS, then injects a compact info card directly under the
 *     ACF field with data-name="company". The card shows:
 *       • Avatar, name, Edit/View links
 *       • “Developer Shortcodes” box with:
 *           [company id="title"], [company id="biography"], [company id="website"], [company id="url_facebook"]
 *       • A list of the user’s URLs; each link has its shortcode badge shown above it:
 *           [company id="url_{key}"]
 *
 * Requirements:
 *  - Advanced Custom Fields: get_field() available.
 *  - Options page group “website” with sub-field “company” (ACF user, return_format=array).
 *  - Optional ACF user field “urls” on that user (array of platform => url).
 *
 * Notes:
 *  - Admin-only; no effect on front end.
 *  - Defensive early returns if any required data are missing.
 *
 * @param string $hook Admin page hook suffix (unused here).
 * @return void
 */
add_action('admin_enqueue_scripts', function( $hook ) {

    // 1) Only run on Theme Options screen. (Uncomment return to hard-enforce.)
    $screen = get_current_screen();
    if ( $screen->id !== 'toplevel_page_theme-options' ) {
        // return;
    }

    // 2) jQuery
    wp_enqueue_script('jquery');

    // 3) Load Options → website → company (ACF user field, return_format=array)
    $website = function_exists('get_field') ? get_field('website', 'option') : null;
    if ( ! is_array($website) || empty($website['company']['ID']) ) {
        return;
    }

    $user_id = absint($website['company']['ID']);

    // 4) User ACF: urls array in user meta scope
    $urls = get_field('urls', 'user_' . $user_id);
    if ( ! is_array($urls) ) {
        $urls = []; // still render the card without URL rows
    }

    // 5) Build minimal user data for UI
    $user_obj = get_userdata($user_id);
    if ( ! $user_obj ) {
        return;
    }

    $data = [
        'user' => [
            'id'     => $user_id,
            'avatar' => get_avatar_url($user_id, ['size' => 80]),
            'name'   => $user_obj->display_name,
            'edit'   => get_edit_user_link($user_id),
            'view'   => get_author_posts_url($user_id),
        ],
        'urls' => $urls,
    ];

    // 6) Register a no-op handle, localize data, and inject inline JS
    wp_register_script('smp-company-card', false, ['jquery'], null, true);
    wp_enqueue_script('smp-company-card');
    wp_localize_script('smp-company-card', 'SMPCompanyData', $data);

    wp_add_inline_script('smp-company-card', <<<'JS'
/**
 * Admin card injector for Company User + developer shortcodes.
 * Expects global SMPCompanyData = { user:{...}, urls:{...} }
 */
jQuery(function($){
    var d = window.SMPCompanyData || {};
    if (!d.user || !d.user.id) return;

    // Prefer to insert directly under the "company" field.
    var target = $('.acf-field[data-name="company"]').first();

    // Fallback: if "company" field wrapper isn't found, try the parent group "website".
    if (!target.length) {
        target = $('.acf-field-group[data-name="website"]').first();
    }
    if (!target.length) return;

    // Where to insert: right after the field/group label if present, otherwise after the wrapper.
    var insertAfter = target.find('> .acf-label').length ? target.find('> .acf-label') : target;

    // Utility: monospace badge for shortcodes
    var codeBadge = function(text){
        return $('<span>').text(text).css({
            display:'inline-block',
            fontFamily:'ui-monospace,Menlo,Monaco,monospace',
            fontSize:'12px',
            background:'#f3f4f6',
            border:'1px solid #e5e7eb',
            borderRadius:'4px',
            padding:'2px 6px',
            lineHeight:1.8
        });
    };

    // Card container
    var card = $('<div class="smp-company-card">').css({
        padding:'12px',
        border:'1px solid #ddd',
        marginTop:'10px',
        marginBottom:'12px',
        borderRadius:'6px',
        background:'#fff'
    });

    // Header
    $('<div>').css({ display:'flex', alignItems:'center' }).append(
        $('<img>', { src:d.user.avatar, width:80, height:80 })
            .css({ borderRadius:'50%', marginRight:'12px', border:'1px solid #e5e7eb' }),
        $('<div>').append(
            $('<strong>').text(d.user.name),
            $('<div>').css({ marginTop:'8px' }).append(
                $('<a>', { href:d.user.edit, target:'_blank', class:'button', style:'margin-right:6px;' }).text('Edit Profile'),
                $('<a>', { href:d.user.view, target:'_blank', class:'button' }).text('View Profile')
            )
        )
    ).appendTo(card);

    // Developer Shortcodes box (company-specific)
    var dev = $('<div class="smp-dev-shortcodes">').css({
        marginTop:'12px',
        padding:'10px',
        background:'#fff',
        border:'1px dashed #cbd5e1',
        borderRadius:'6px'
    }).append(
        $('<div>').css({ fontWeight:600, marginBottom:'6px' }).text('Developer Shortcodes'),
        $('<p>').css({ margin:'4px 0' }).append(
            $('<span>').css({ display:'block', fontWeight:600, marginBottom:'2px' }).text('title'),
            codeBadge('[company id="title"]')
        ),
        $('<p>').css({ margin:'4px 0' }).append(
            $('<span>').css({ display:'block', fontWeight:600, marginBottom:'2px' }).text('biography'),
            codeBadge('[company id="biography"]')
        ),
        $('<p>').css({ margin:'4px 0' }).append(
            $('<span>').css({ display:'block', fontWeight:600, marginBottom:'2px' }).text('website'),
            codeBadge('[company id="website"]')
        ),
        $('<p>').css({ margin:'4px 0' }).append(
            $('<span>').css({ display:'block', fontWeight:600, marginBottom:'2px' }).text('facebook'),
            codeBadge('[company id="url_facebook"]')
        )
    );
    card.append(dev);

    // URLs list with shortcode badge above each link
    var urlBox = $('<div class="smp-company-urls">').css({ marginTop:'12px' })
                   .append('<p><strong>Company User URLs:</strong></p>');

    $.each(d.urls, function(key, link){
        link = $.trim(link || '');
        if (!link) return;

        var labelText = key.replace(/_/g,' ').replace(/\b\w/g, function(m){ return m.toUpperCase(); });

        urlBox.append(
            $('<div>').css({ margin:'10px 0 12px' }).append(
                $('<div>').css({ margin:'0 0 4px 0' }).append(
                    codeBadge('[company id="url_' + key + '"]')
                ),
                $('<div>').append(
                    $('<strong>').text(labelText + ': '),
                    $('<a>', { href:link, target:'_blank' }).text(link)
                )
            )
        );
    });

    card.append(urlBox);

    // Inject into the form
    insertAfter.after(card);
});
JS
    );
});































/**
 * Admin UI: Inject a “Founder User” card with shortcode examples into Theme Options.
 *
 * Hook: admin_enqueue_scripts
 *
 * What this does:
 *  1) Runs on the Theme Options screen (toplevel_page_theme-options).
 *  2) Loads jQuery for admin.
 *  3) Reads ACF Options group "founder" and its sub-field "user" (ACF user field, return_format=array).
 *  4) Loads that user’s ACF field "urls" from user meta scope ("user_{ID}").
 *  5) Localizes this data to JS and injects a compact card directly under the
 *     ACF group with data-name="founder". The card shows:
 *       • Avatar, name, Edit/View links
 *       • “Developer Shortcodes” box with:
 *           [founder id="title"], [founder id="biography"], [founder id="website"], [founder id="url_facebook"]
 *       • A list of the user’s URLs; each link has its shortcode badge above it:
 *           [founder id="url_{key}"]
 *
 * Requirements:
 *  - ACF available (get_field()).
 *  - Options page contains group "founder" with sub-field "user" (return_format=array).
 *  - Optional ACF user field "urls" on that user (array of platform => url).
 *
 * Notes:
 *  - Admin-only; no front-end impact.
 *  - Early returns if required data are missing.
 *
 * @param string $hook Admin page hook suffix (unused here).
 * @return void
 */
add_action('admin_enqueue_scripts', function( $hook ) {

    // 1) Limit to Theme Options screen (uncomment return to strictly enforce)
    $screen = get_current_screen();
    if ( $screen->id !== 'toplevel_page_theme-options' ) {
        // return;
    }

    // 2) jQuery
    wp_enqueue_script('jquery');

    // 3) Load Options → founder → user (ACF user field, return_format=array)
    $founder = function_exists('get_field') ? get_field('founder', 'option') : null;
    if ( ! is_array($founder) || empty($founder['user']['ID']) ) {
        return;
    }

    $user_id = absint($founder['user']['ID']);

    // 4) User ACF: urls array (from user meta scope)
    $urls = get_field('urls', 'user_' . $user_id);
    if ( ! is_array($urls) ) {
        $urls = []; // still render the card without URL rows
    }

    // 5) Minimal user data
    $user_obj = get_userdata($user_id);
    if ( ! $user_obj ) {
        return;
    }

    $data = [
        'user' => [
            'id'     => $user_id,
            'avatar' => get_avatar_url($user_id, ['size' => 80]),
            'name'   => $user_obj->display_name,
            'edit'   => get_edit_user_link($user_id),
            'view'   => get_author_posts_url($user_id),
        ],
        'urls' => $urls,
    ];

    // 6) Register empty handle, localize data, inject inline JS
    wp_register_script('smp-founder-card', false, ['jquery'], null, true);
    wp_enqueue_script('smp-founder-card');
    wp_localize_script('smp-founder-card', 'SMPFounderData', $data);

    wp_add_inline_script('smp-founder-card', <<<'JS'
/**
 * Admin card injector for Founder User + developer shortcodes.
 * Expects global SMPFounderData = { user:{...}, urls:{...} }
 */
jQuery(function($){
    var d = window.SMPFounderData || {};
    if (!d.user || !d.user.id) return;

    // Target the ACF group wrapper for "founder"
    var group = $('.acf-field-group[data-name="founder"]').first();
    if (!group.length) return;

    // Insert after the group's label if found; else append to group
    var insertAfter = group.find('> .acf-label').length ? group.find('> .acf-label') : group;

    // Utility: monospace badge for shortcodes
    var codeBadge = function(text){
        return $('<span>').text(text).css({
            display:'inline-block',
            fontFamily:'ui-monospace,Menlo,Monaco,monospace',
            fontSize:'12px',
            background:'#f3f4f6',
            border:'1px solid #e5e7eb',
            borderRadius:'4px',
            padding:'2px 6px',
            lineHeight:1.8
        });
    };

    // Card container
    var card = $('<div class="smp-founder-card">').css({
        padding:'12px',
        border:'1px solid #ddd',
        marginTop:'10px',
        marginBottom:'12px',
        borderRadius:'6px',
        background:'#fff'
    });

    // Header: avatar + name + actions
    $('<div>').css({ display:'flex', alignItems:'center' }).append(
        $('<img>', { src:d.user.avatar, width:80, height:80 })
            .css({ borderRadius:'50%', marginRight:'12px', border:'1px solid #e5e7eb' }),
        $('<div>').append(
            $('<strong>').text(d.user.name),
            $('<div>').css({ marginTop:'8px' }).append(
                $('<a>', { href:d.user.edit, target:'_blank', class:'button', style:'margin-right:6px;' }).text('Edit Profile'),
                $('<a>', { href:d.user.view, target:'_blank', class:'button' }).text('View Profile')
            )
        )
    ).appendTo(card);

    // Developer Shortcodes box (founder-specific)
    var dev = $('<div class="smp-dev-shortcodes">').css({
        marginTop:'12px',
        padding:'10px',
        background:'#fff',
        border:'1px dashed #cbd5e1',
        borderRadius:'6px'
    }).append(
        $('<div>').css({ fontWeight:600, marginBottom:'6px' }).text('Developer Shortcodes'),
        $('<p>').css({ margin:'4px 0' }).append(
            $('<span>').css({ display:'block', fontWeight:600, marginBottom:'2px' }).text('title'),
            codeBadge('[founder id="title"]')
        ),
        $('<p>').css({ margin:'4px 0' }).append(
            $('<span>').css({ display:'block', fontWeight:600, marginBottom:'2px' }).text('biography'),
            codeBadge('[founder id="biography"]')
        ),
        $('<p>').css({ margin:'4px 0' }).append(
            $('<span>').css({ display:'block', fontWeight:600, marginBottom:'2px' }).text('website'),
            codeBadge('[founder id="website"]')
        ),
        $('<p>').css({ margin:'4px 0' }).append(
            $('<span>').css({ display:'block', fontWeight:600, marginBottom:'2px' }).text('facebook'),
            codeBadge('[founder id="url_facebook"]')
        )
    );
    card.append(dev);

    // URLs list with shortcode badge above each link
    var urlBox = $('<div class="smp-founder-urls">').css({ marginTop:'12px' })
                   .append('<p><strong>Founder User URLs:</strong></p>');

    $.each(d.urls, function(key, link){
        link = $.trim(link || '');
        if (!link) return;

        var labelText = key.replace(/_/g,' ').replace(/\b\w/g, function(m){ return m.toUpperCase(); });

        urlBox.append(
            $('<div>').css({ margin:'10px 0 12px' }).append(
                $('<div>').css({ margin:'0 0 4px 0' }).append(
                    codeBadge('[founder id="url_' + key + '"]')
                ),
                $('<div>').append(
                    $('<strong>').text(labelText + ': '),
                    $('<a>', { href:link, target:'_blank' }).text(link)
                )
            )
        );
    });

    card.append(urlBox);

    // Inject into the ACF group UI
    insertAfter.after(card);
});
JS
    );
});
































/**
 * Resolve the "founder" user_id from Options, with sane fallbacks.
 * Order:
 *   1) option → founder → user
 *   2) option → website → founder → user (legacy)
 *   3) option → website → company (pragmatic fallback)
 */
function hws_resolve_founder_user_id(): int {
    if ( ! function_exists( 'get_field' ) ) {
        return 0;
    }

    // 1) Primary: option → founder → user
    $founder = get_field( 'founder', 'option' );
    if ( is_array( $founder ) && ! empty( $founder['user'] ) ) {
        $uf = $founder['user'];
        if ( is_array( $uf ) && isset( $uf['ID'] ) ) return (int) $uf['ID'];
        if ( is_object( $uf ) && isset( $uf->ID ) )  return (int) $uf->ID;
        return (int) $uf;
    }

    // 2) Legacy: option → website → founder → user
    $website = get_field( 'website', 'option' );
    if ( is_array( $website ) && ! empty( $website['founder']['user'] ) ) {
        $uf = $website['founder']['user'];
        if ( is_array( $uf ) && isset( $uf['ID'] ) ) return (int) $uf['ID'];
        if ( is_object( $uf ) && isset( $uf->ID ) )  return (int) $uf->ID;
        return (int) $uf;
    }

    // 3) Fallback: option → website → company
    if ( is_array( $website ) && ! empty( $website['company'] ) ) {
        $uf = $website['company'];
        if ( is_array( $uf ) && isset( $uf['ID'] ) ) return (int) $uf['ID'];
        if ( is_object( $uf ) && isset( $uf->ID ) )  return (int) $uf->ID;
        return (int) $uf;
    }

    return 0;
}

/* ---------------------------------------
 * founder shortcode (clean: no debug)
 * --------------------------------------*/
\add_shortcode( 'founder', __NAMESPACE__ . '\\founder_shortcode' );

/**
 * [founder id="title|biography|website|url_x|<acf_field>|group_subfield"]
 */
function founder_shortcode( $atts ): string {
    $atts = shortcode_atts( [ 'id' => 'title' ], $atts, 'founder' );
    $requested = strtolower( trim( (string) $atts['id'] ) );

    if ( ! function_exists( 'get_field' ) ) {
        return '';
    }

    $user_id = hws_resolve_founder_user_id();
    if ( $user_id <= 0 ) return '';

    $userdata = get_userdata( $user_id );
    if ( ! $userdata ) return '';

    $user_key  = 'user_' . $user_id;
    $user_name = $userdata->display_name ?: '';

    // Option-level founder biography (if populated)
    $founder_group = get_field( 'founder', 'option' );
    $founder_group_bio = ( is_array( $founder_group ) && ! empty( $founder_group['biography'] ) && is_string( $founder_group['biography'] ) )
        ? $founder_group['biography']
        : '';

    $user_urls = get_field( 'urls',       $user_key );
    $user_bio  = (string) get_field( 'biography', $user_key );
    $user_site = (string) get_field( 'website',   $user_key );

    switch ( $requested ) {
        case 'title':
            return $user_name ? esc_html( $user_name ) : '';

        case 'biography':
            if ( $founder_group_bio !== '' ) return $founder_group_bio; // HTML allowed
            if ( $user_bio !== '' )          return $user_bio;          // HTML allowed
            $core_bio = (string) $userdata->description;
            return $core_bio !== '' ? $core_bio : '';

        case 'website':
            if ( $user_site !== '' ) return esc_url( $user_site );
            if ( is_array( $user_urls ) && ! empty( $user_urls['website'] ) ) {
                return esc_url( (string) $user_urls['website'] );
            }
            $core_url = (string) $userdata->user_url;
            return $core_url !== '' ? esc_url( $core_url ) : '';
    }

    // url_* platforms
    if ( ( function_exists( 'str_starts_with' ) && str_starts_with( $requested, 'url_' ) )
      || substr( $requested, 0, 4 ) === 'url_' ) {
        $platform = sanitize_key( substr( $requested, 4 ) );
        if ( $platform && is_array( $user_urls ) && ! empty( $user_urls[ $platform ] ) ) {
            return esc_url( (string) $user_urls[ $platform ] );
        }
        return '';
    }

    // Direct user ACF
    $direct = get_field( $requested, $user_key );
    if ( is_string( $direct ) && $direct !== '' ) {
        if ( filter_var( $direct, FILTER_VALIDATE_URL ) ) return esc_url( $direct );
        if ( in_array( $requested, [ 'biography', 'bio' ], true ) ) return $direct; // allow HTML
        return esc_html( $direct );
    }

    // Nested group_subfield on user
    if ( strpos( $requested, '_' ) !== false ) {
        $parts  = explode( '_', $requested );
        $group  = array_shift( $parts );
        $subkey = implode( '_', $parts );
        if ( $group && $subkey ) {
            $group_val = get_field( $group, $user_key );
            if ( is_array( $group_val ) && isset( $group_val[ $subkey ] ) ) {
                $val = $group_val[ $subkey ];
                if ( is_string( $val ) && $val !== '' ) {
                    if ( filter_var( $val, FILTER_VALIDATE_URL ) ) return esc_url( $val );
                    if ( is_email( $val ) ) return esc_html( $val );
                    return esc_html( $val );
                }
            }
        }
    }

    return '';
}
