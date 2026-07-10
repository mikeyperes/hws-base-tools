<?php
namespace hws_base_tools;

/**
 * Admin Profile Tweaks — on-demand registrar
 * Call hws_base_tools\snippet_clean_user_php() to activate.
 */

/* ---------- Helpers (namespaced) ---------- */
function hws_lock_comment_shortcuts_off($user_id){ update_user_meta($user_id, 'comment_shortcuts', 'false'); }
function hws_lock_toolbar_on($user_id){ update_user_meta($user_id, 'show_admin_bar_front', 'true'); }
function hws_lock_locale_default($user_id){ delete_user_meta($user_id, 'locale'); }
function hws_lock_syntax_highlighting_on($user_id){ update_user_meta($user_id, 'syntax_highlighting', 'true'); }
function hws_lock_elementor_ai_off($user_id){ update_user_meta($user_id, 'elementor_ai_enabled', '0'); }

/** Footer scrub of leftover sections (profile + user-edit) */
function hws_strip_extra_profile_sections() { ?>
    <script>
    (function(){
        function removeSectionByH2Text(match) {
            document.querySelectorAll('h2').forEach(function(h2){
                var txt=(h2.textContent||'').trim();
                if(txt===match){
                    var n=h2.nextElementSibling;
                    while(n && n.tagName!=='H2'){ var rm=n; n=n.nextElementSibling; rm.remove(); }
                    h2.remove();
                }
            });
        }
        ['user-admin-color-wrap','user-comment-shortcuts-wrap','user-admin-bar-front-wrap','user-language-wrap','user-syntax-highlighting-wrap']
          .forEach(function(cls){ document.querySelectorAll('tr.'+cls).forEach(function(tr){ tr.remove(); }); });

        removeSectionByH2Text('Elementor - AI');
        Array.from(document.querySelectorAll('table.form-table tr')).forEach(function(tr){
            var t=(tr.textContent||'').toLowerCase();
            if(t.indexOf('enable elementor ai functionality')!==-1) tr.remove();
        });

        removeSectionByH2Text('Application Passwords');
        var wf=document.getElementById('wfls-user-settings');
        if(wf){
            var n=wf.nextElementSibling;
            while(n && n.tagName!=='H2'){ var rm=n; n=n.nextElementSibling; rm.remove(); }
            wf.remove();
        }
    })();
    </script>
    <style>
        tr.user-admin-color-wrap,
        tr.user-comment-shortcuts-wrap,
        tr.user-admin-bar-front-wrap,
        tr.user-language-wrap,
        tr.user-syntax-highlighting-wrap,
        #wfls-user-settings,
        #wfls-user-settings ~ table.form-table { display:none !important; }
    </style>
<?php }

/* ---------- Registrar: call this to enable everything ---------- */
function snippet_clean_user_php() {

    // Admin Color Scheme (hide + enforce)
    add_action('admin_init', function () {
        remove_action('admin_color_scheme_picker', 'admin_color_scheme_picker');
    });
    add_filter('get_user_option_admin_color', function(){ return 'fresh'; });

    // Keyboard Shortcuts (hide + force OFF)
    add_filter('get_user_option_comment_shortcuts', function(){ return 'false'; });
    add_action('personal_options_update', __NAMESPACE__.'\hws_lock_comment_shortcuts_off');
    add_action('edit_user_profile_update', __NAMESPACE__.'\hws_lock_comment_shortcuts_off');

    // Toolbar (hide + force ON)
    add_filter('get_user_option_show_admin_bar_front', function(){ return 'true'; });
    add_action('personal_options_update', __NAMESPACE__.'\hws_lock_toolbar_on');
    add_action('edit_user_profile_update', __NAMESPACE__.'\hws_lock_toolbar_on');
    // Optional global override:
    // add_filter('show_admin_bar', '__return_true');

    // Language (hide + force Site Default)
    add_filter('get_user_option_locale', function(){ return ''; }); // empty => site default
    add_action('personal_options_update', __NAMESPACE__.'\hws_lock_locale_default');
    add_action('edit_user_profile_update', __NAMESPACE__.'\hws_lock_locale_default');

    // Syntax Highlighting (hide + keep ENABLED)
    add_filter('get_user_option_syntax_highlighting', function(){ return 'true'; });
    add_action('personal_options_update', __NAMESPACE__.'\hws_lock_syntax_highlighting_on');
    add_action('edit_user_profile_update', __NAMESPACE__.'\hws_lock_syntax_highlighting_on');

    // Elementor – AI (unhook + force OFF)
    add_action('admin_init', function () {
        foreach (['show_user_profile','edit_user_profile'] as $hook) {
            global $wp_filter;
            if (empty($wp_filter[$hook])) continue;
            foreach ($wp_filter[$hook]->callbacks ?? [] as $priority => $callbacks) {
                foreach ($callbacks as $cb) {
                    $fn = $cb['function'];
                    $name = is_string($fn) ? $fn
                        : (is_array($fn) ? (is_object($fn[0]) ? get_class($fn[0]).'::'.$fn[1] : implode('::',$fn))
                        : (is_object($fn) ? get_class($fn) : ''));
                    if (stripos($name,'elementor')!==false && stripos($name,'ai')!==false) {
                        remove_action($hook, $cb['function'], $priority);
                    }
                }
            }
        }
    });
    add_filter('get_user_option_elementor_ai_enabled', function(){ return '0'; });
    add_action('personal_options_update', __NAMESPACE__.'\hws_lock_elementor_ai_off');
    add_action('edit_user_profile_update', __NAMESPACE__.'\hws_lock_elementor_ai_off');

    // Application Passwords (disable feature + remove UI)
    add_filter('wp_is_application_passwords_available', '__return_false');
    remove_action('show_user_profile', 'wp_application_passwords_profile_ui');
    remove_action('edit_user_profile', 'wp_application_passwords_profile_ui');

    // Wordfence Login Security (remove UI if hooked)
    add_action('admin_init', function () {
        foreach (['show_user_profile','edit_user_profile'] as $hook) {
            global $wp_filter;
            if (empty($wp_filter[$hook])) continue;
            foreach ($wp_filter[$hook]->callbacks ?? [] as $priority => $callbacks) {
                foreach ($callbacks as $cb) {
                    $fn = $cb['function'];
                    $name = is_string($fn) ? $fn
                        : (is_array($fn) ? (is_object($fn[0]) ? get_class($fn[0]).'::'.$fn[1] : implode('::',$fn))
                        : (is_object($fn) ? get_class($fn) : ''));
                    if (stripos($name,'wordfence')!==false || stripos($name,'wfls')!==false) {
                        remove_action($hook, $cb['function'], $priority);
                    }
                }
            }
        }
    });

    // Final guaranteed UI removal on profile screens
    add_action('admin_print_footer_scripts-profile.php', __NAMESPACE__.'\hws_strip_extra_profile_sections', 99);
    add_action('admin_print_footer_scripts-user-edit.php', __NAMESPACE__.'\hws_strip_extra_profile_sections', 99);
}
