<?php
namespace hws_base_tools;

/**
 * Place this entire block in your user.php (or include it via functions.php).
 * It injects a “Migrate URLs” button, a “Test” checkbox, and a report area inside the ACF
 * group_6419bc02b6e93 container. It handles AJAX migration of old ACF fields into
 * the new “urls” group by field *name* (“urls” and “settings”).
 *
 * Regardless of “Test,” the code will update the new ACF fields (only if they are empty)
 * and delete old fields only for that one user. Checking “Test (dry run)” still runs the
 * migration (with “TEST:” labels) and does not prevent deletion when “Remove old” is checked.
 *
 * If any old “social” group subfields exist, they overwrite earlier mappings for
 * facebook, instagram, and website (but only if the destination was empty).
 */

/**
 * 1) Enqueue inline script on ACF edit screens to insert the button, checkboxes, and report div
 *    inside the specific ACF group container (group_6419bc02b6e93).
 */
add_action( 'acf/input/admin_footer', __NAMESPACE__ . '\\acf_insert_migration_ui_6419bc02b6e93' );
function acf_insert_migration_ui_6419bc02b6e93() {
    if ( ! is_admin() ) {
        return;
    }

    // Determine which user is being edited via ?user_id= in the URL
    $target_user_id = isset( $_GET['user_id'] ) ? intval( $_GET['user_id'] ) : get_current_user_id();
    ?>
    <script type="text/javascript">
    (function($){
        $(document).ready(function(){
            // Find the <code> element that shows "group_6419bc02b6e93"
            var $groupCode = $('code').filter(function(){
                return $(this).text().trim() === 'group_6419bc02b6e93';
            });

            if ( $groupCode.length ) {
                // The wrapper div two levels up
                var $wrapper = $groupCode.closest('div').closest('div');

                if ( $wrapper.length ) {
                    // Build the migration UI markup:
                    var nonce  = '<?php echo wp_create_nonce( "acf_migration_nonce_6419bc02b6e93" ); ?>';
                    var userId = '<?php echo esc_js( $target_user_id ); ?>';

                    var uiHtml = '\
                        <div style="border:1px solid #ccc;border-radius:4px;padding:16px;margin-top:24px;\
font-family:Arial, sans-serif;background-color:#f0f0f0;">\
                            <h2 style="margin:0 0 12px;font-size:1.25em;color:#333;">Migrate ACF URLs</h2>\
                            <p>\
                                <label style="font-weight:bold;">\
                                    <input type="checkbox" id="acf_test_6419bc02b6e93" value="1" />\
                                    Test (dry run: update fields for this user only; old fields deleted only for that user)\
                                </label>\
                            </p>\
                            <p>\
                                <label style="font-weight:bold;">\
                                    <input type="checkbox" id="acf_remove_old_6419bc02b6e93" value="1" />\
                                    Remove old ACF fields after migration (only for this one user)\
                                </label>\
                            </p>\
                            <p>\
                                <button type="button" class="button button-primary" \
id="acf_migrate_btn_6419bc02b6e93">Migrate URLs</button>\
                            </p>\
                            <div id="acf_migration_report_6419bc02b6e93" style="\
margin-top:12px;padding:8px;border:1px solid #ddd;background:#fafafa;\
max-height:200px;overflow-y:auto;font-size:14px;">\
                                <em>Migration report will appear here...</em>\
                            </div>\
                        </div>';

                    // Append the UI under the ACF group container
                    $wrapper.append(uiHtml);

                    // Store userId and nonce in data attributes
                    $('#acf_migrate_btn_6419bc02b6e93')
                        .data('userId', userId)
                        .data('nonce', nonce);

                    // Attach click handler for AJAX
                    $('#acf_migrate_btn_6419bc02b6e93').on('click', function(){
                        var testRun   = $('#acf_test_6419bc02b6e93').is(':checked') ? 1 : 0;
                        var removeOld = $('#acf_remove_old_6419bc02b6e93').is(':checked') ? 1 : 0;
                        var userId    = $(this).data('userId');
                        var nonce     = $(this).data('nonce');
                        var reportDiv = $('#acf_migration_report_6419bc02b6e93');
                        var btn       = $(this);

                        reportDiv.html('<em>Starting migration for group_6419bc02b6e93…</em>');
                        btn.attr('disabled', true).text('Migrating…');

                        $.ajax({
                            url: ajaxurl,
                            method: 'POST',
                            dataType: 'json',
                            data: {
                                action:     'acf_migrate_6419bc02b6e93',
                                user_id:    userId,
                                remove_old: removeOld,
                                test:       testRun,
                                security:   nonce
                            },
                            success: function(response) {
                                reportDiv.empty();
                                if ( response.success ) {
                                    $.each(response.data.messages, function(i, msg){
                                        reportDiv.append('<div>' + msg + '</div>');
                                    });
                                } else {
                                    reportDiv.append('<div style="color:red;">Error: ' + response.data.error + '</div>');
                                }
                                btn.attr('disabled', false).text('Migrate URLs');
                            },
                            error: function(xhr, status, error) {
                                reportDiv.html('<div style="color:red;">AJAX error: ' + error + '</div>');
                                btn.attr('disabled', false).text('Migrate URLs');
                            }
                        });
                    });
                }
            }
        });
    })(jQuery);
    </script>
    <?php
}

/**
 * 2) Register the AJAX handler for “acf_migrate_6419bc02b6e93”.
 */
add_action( 'wp_ajax_acf_migrate_6419bc02b6e93', __NAMESPACE__ . '\\acf_migration_ajax_6419bc02b6e93' );
function acf_migration_ajax_6419bc02b6e93() {
    // Verify nonce
    if ( ! isset( $_POST['security'] )
      || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ),
                             'acf_migration_nonce_6419bc02b6e93' ) ) {
        wp_send_json_error( [ 'error' => 'Invalid nonce.' ] );
    }

    $user_id    = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
    $remove_old = isset( $_POST['remove_old'] ) && intval( $_POST['remove_old'] ) === 1;
    $test       = isset( $_POST['test'] ) && intval( $_POST['test'] ) === 1;

    if ( $user_id <= 0 || ! current_user_can( 'edit_user', $user_id ) ) {
        wp_send_json_error( [ 'error' => 'Invalid user or insufficient permissions.' ] );
    }

    // Call the converter with the $test parameter (affects only this user)
    $messages = acf_convert_group_6419bc02b6e93( $user_id, $remove_old, $test );
    wp_send_json_success( [ 'messages' => $messages ] );
}

/**
 * 3) Conversion logic for “group_6419bc02b6e93” with an optional $test parameter.
 *    Always updates all old fields into the new ACF "urls" and "settings" groups for
 *    that single user—but only if the destination subfield was empty. If $remove_old===true,
 *    deletes old keys for that user. Appends an “Edit User Profile” link for that user.
 *    In “Test” mode, messages are prefixed with “TEST:” but fields and deletions still
 *    occur for that user.
 *
 * @param int  $user_id    The user whose meta is being migrated.
 * @param bool $remove_old If true, delete old keys after migration for that user.
 * @param bool $test       If true, prefix messages with “TEST:”, but still run updates/deletes.
 * @return array           Status messages.
 */
function acf_convert_group_6419bc02b6e93( $user_id, $remove_old = false, $test = false ) {
    $messages = [];

    // OLD → NEW mapping for this specific ACF group by field *NAME*
    $old_to_new = [
        'facebook_url'    => 'facebook',
        'instagram_url'   => 'instagram',
        'website_url'     => 'website',
        'tiktok_url'      => 'tiktok',
        'crunchbase_url'  => 'crunchbase',
        'f6s_url'         => 'f6s',
        'twitter_url'     => 'x',            // “X” replaces Twitter
        'linkedin_url'    => 'linkedin',
        'well_found_url'  => 'the_org',      // “The Org” replaces Well Found
        'soundcloud_url'  => 'soundcloud',
        'amazon_url'      => 'amazon',
        'youtube_url'     => 'youtube',
        'whatsapp_url'    => 'whatsapp',
        'telegram_url'    => 'telegram',
        'imdb_url'        => 'imdb',
        'github_url'      => 'github',
        'audible_url'     => 'audible',
        'muckrack_url'    => 'muckrack',
    ];

    // OLD boolean keys → NEW “settings” subfields by *NAME*
    $old_booleans = [
        'muckrack_verified' => 'muckrack_verified',
        'staff_writer'      => 'staff_writer',
    ];

    // 1) Load existing ACF “urls” and “settings” values (so we don’t overwrite non-empty):
    $existing_urls     = get_field( 'urls', 'user_' . $user_id ) ?: [];
    $existing_settings = get_field( 'settings', 'user_' . $user_id ) ?: [];

    // Always collect every old field value that needs to be written
    $to_save_urls     = [];
    $to_save_settings = [];

    // 2) Fetch old URL values, but skip if existing subfield is non-empty
    foreach ( $old_to_new as $old_key => $new_sub ) {
        $old_val = get_user_meta( $user_id, $old_key, true );
        if ( '' !== $old_val && null !== $old_val ) {
            // If destination is empty or not set, queue update; otherwise skip
            if ( empty( $existing_urls[ $new_sub ] ) ) {
                $to_save_urls[ $new_sub ] = $old_val;
                $messages[] = ( $test ? "TEST: " : "" )
                             . "Mapped <strong>{$old_key}</strong> → urls['{$new_sub}'] (value='{$old_val}')";
            } else {
                $messages[] = ( $test ? "TEST: " : "" )
                             . "Skipped <strong>{$old_key}</strong> → urls['{$new_sub}'] because destination already has '{$existing_urls[ $new_sub ]}'";
            }
        } else {
            $messages[] = ( $test ? "TEST: " : "" )
                         . "No value for <strong>{$old_key}</strong>";
        }
    }

    // 3) Fetch old boolean values, but skip if existing subfield is non-empty
    foreach ( $old_booleans as $old_key => $new_sub ) {
        $old_val = get_user_meta( $user_id, $old_key, true );
        if ( '' !== $old_val && null !== $old_val ) {
            if ( ! isset( $existing_settings[ $new_sub ] ) || $existing_settings[ $new_sub ] === '' ) {
                $to_save_settings[ $new_sub ] = $old_val ? 1 : 0;
                $messages[] = ( $test ? "TEST: " : "" )
                             . "Mapped <strong>{$old_key}</strong> → settings['{$new_sub}'] (value=" . ( $old_val ? 'true' : 'false' ) . ")";
            } else {
                $messages[] = ( $test ? "TEST: " : "" )
                             . "Skipped <strong>{$old_key}</strong> → settings['{$new_sub}'] because destination already has '{$existing_settings[ $new_sub ]}'";
            }
        } else {
            $messages[] = ( $test ? "TEST: " : "" )
                         . "No value for <strong>{$old_key}</strong>";
        }
    }

    // 4) Check “social” group subfields (override only if destination is empty)
    // Keys: social_media_facebook, social_media_instagram, social_media_website
    $social_fields = [
        'social_media_facebook'  => 'facebook',
        'social_media_instagram' => 'instagram',
        'social_media_website'   => 'website',
    ];
    foreach ( $social_fields as $old_key => $new_sub ) {
        $old_val = get_user_meta( $user_id, $old_key, true );
        if ( '' !== $old_val && null !== $old_val ) {
            if ( empty( $existing_urls[ $new_sub ] ) ) {
                $to_save_urls[ $new_sub ] = $old_val;
                $messages[] = ( $test ? "TEST: " : "" )
                             . "Overrode from <strong>{$old_key}</strong> → urls['{$new_sub}'] (value='{$old_val}')";
            } else {
                $messages[] = ( $test ? "TEST: " : "" )
                             . "Skipped override from <strong>{$old_key}</strong> → urls['{$new_sub}'] because destination already has '{$existing_urls[ $new_sub ]}'";
            }
        }
    }

    // 5) Actually update ACF “urls” group if there’s anything to save
    if ( ! empty( $to_save_urls ) ) {
        // Merge with existing_urls so we don’t wipe other subfields
        $merged_urls = array_merge( $existing_urls, $to_save_urls );
        update_field( 'urls', $merged_urls, 'user_' . $user_id );
        $messages[] = ( $test ? "TEST: " : "" )
                     . "Saved new <strong>urls</strong> group for user {$user_id}.";
    } else {
        $messages[] = ( $test ? "TEST: " : "" )
                     . "No new URLs to save.";
    }

    // 6) Actually update ACF “settings” group if there’s anything to save
    if ( ! empty( $to_save_settings ) ) {
        $merged_settings = array_merge( $existing_settings, $to_save_settings );
        update_field( 'settings', $merged_settings, 'user_' . $user_id );
        $messages[] = ( $test ? "TEST: " : "" )
                     . "Saved new <strong>settings</strong> group for user {$user_id}.";
    } else {
        $messages[] = ( $test ? "TEST: " : "" )
                     . "No new Settings to save.";
    }

    // 7) Delete old ACF meta keys (only for this one user) if remove_old===true
    if ( $remove_old ) {
        // Even if destination had a value, we still delete old key if requested
        foreach ( array_keys( $old_to_new ) as $old_key ) {
            delete_user_meta( $user_id, $old_key );
            $messages[] = ( $test ? "TEST: " : "" )
                         . "Deleted old field <strong>{$old_key}</strong> for user {$user_id}.";
        }
        foreach ( array_keys( $old_booleans ) as $old_key ) {
            delete_user_meta( $user_id, $old_key );
            $messages[] = ( $test ? "TEST: " : "" )
                         . "Deleted old field <strong>{$old_key}</strong> for user {$user_id}.";
        }
        // Also delete “social” subfields
        foreach ( array_keys( $social_fields ) as $old_key ) {
            delete_user_meta( $user_id, $old_key );
            $messages[] = ( $test ? "TEST: " : "" )
                         . "Deleted old field <strong>{$old_key}</strong> for user {$user_id}.";
        }
    } else {
        $messages[] = ( $test ? "TEST: " : "" )
                     . "<em>Old fields retained (remove_old=false).</em>";
    }

    // 8) Add “Edit User Profile” link at the end
    $edit_link = get_edit_user_link( $user_id );
    $messages[] = ( $test ? "TEST: " : "" )
                 . "User edit link: <a href=\"{$edit_link}\" target=\"_blank\">Edit User Profile</a>";

    return $messages;
}

/**
 * ------------------------------------------------------------------------------
 * Migration for old ACF group group_590d64c31db0a → new group_684252fd99081
 * (same new structure as the previous example in this thread).
 *
 * Old fields:
 *   job_title (text)                 → subtitle
 *   socials (group):
 *     socials_facebook, socials_linkedin, socials_x, socials_youtube,
 *     socials_instagram, socials_soundcloud, socials_tiktok
 *   profiles (group):
 *     profiles_wikipedia, profiles_crunchbase, profiles_muckrack,
 *     profiles_muckrack_verified (true_false), profiles_f6s, profiles_imdb
 *
 * New fields (group_684252fd99081):
 *   urls (group) subfields: facebook, instagram, linkedin, x, youtube,
 *     tiktok, f6s, imdb, muckrack, wikipedia, soundcloud, crunchbase, website, …
 *   subtitle (text)
 *   settings (group) subfields: staff_writer, muckrack_verified
 *
 * This code injects a “Migrate ACF Profile” button under the old-group container,
 * copies old → new only if the destination is empty, and (optionally) deletes
 * the old meta keys for that one user. “Test” mode prefixes messages with “TEST:”
 * but still runs the migration and deletion for that user.
 */

/**
 * 1) UI Injection under group_590d64c31db0a
 */
add_action('acf/input/admin_footer', __NAMESPACE__ . '\\acf_insert_migration_ui_590d64c31db0a');
function acf_insert_migration_ui_590d64c31db0a(){
    if(!is_admin()) return;

    // which user are we editing?
    $uid = isset($_GET['user_id']) ? intval($_GET['user_id']) : get_current_user_id();
    $nonce = wp_create_nonce('acf_migration_nonce_590d64c31db0a');
    ?>
    <script>
    (function($){
      $(function(){
        var $code = $('code:contains("group_590d64c31db0a")');
        if(!$code.length) return;
        var $wrap = $code.closest('div').closest('div');
        var html = '\
          <div style="border:1px solid #ccc;border-radius:4px;padding:16px;margin-top:24px;\
font-family:Arial,sans-serif;background:#f0f0f0;">\
            <h2 style="margin:0 0 12px;font-size:1.25em;color:#333;">Migrate ACF Profile</h2>\
            <p><label><input type="checkbox" id="acf_test_590d64c31db0a" /> Test (dry run)</label></p>\
            <p><label><input type="checkbox" id="acf_remove_old_590d64c31db0a" /> Remove old fields</label></p>\
            <p><button id="acf_migrate_btn_590d64c31db0a" class="button button-primary">Migrate Profile</button></p>\
            <div id="acf_report_590d64c31db0a" style="margin-top:12px;padding:8px;border:1px solid #ddd;\
background:#fafafa;max-height:200px;overflow:auto;font-size:14px;">Report...</div>\
          </div>';
        $wrap.append(html);
        $('#acf_migrate_btn_590d64c31db0a').data({uid:<?php echo $uid;?>,nonce:'<?php echo $nonce;?>'})
          .on('click',function(){
            var test = $('#acf_test_590d64c31db0a').is(':checked')?1:0;
            var rem  = $('#acf_remove_old_590d64c31db0a').is(':checked')?1:0;
            var btn  = $(this), rpt = $('#acf_report_590d64c31db0a');
            rpt.html('<em>Running…</em>');
            btn.prop('disabled',true).text('Migrating…');
            $.post(ajaxurl,{
              action:'acf_migrate_590d64c31db0a',
              user_id:btn.data('uid'),
              test:test,
              remove_old:rem,
              security:btn.data('nonce')
            },function(res){
              rpt.empty();
              if(res.success){
                res.data.messages.forEach(function(m){ rpt.append('<div>'+m+'</div>'); });
              } else {
                rpt.append('<div style="color:red;">'+res.data.error+'</div>');
              }
              btn.prop('disabled',false).text('Migrate Profile');
            },'json');
          });
      });
    })(jQuery);
    </script>
    <?php
}

/**
 * 2) AJAX handler
 */
add_action('wp_ajax_acf_migrate_590d64c31db0a', __NAMESPACE__ . '\\acf_migration_ajax_590d64c31db0a');
function acf_migration_ajax_590d64c31db0a(){
  if(
    empty($_POST['security'])
    || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])),
                        'acf_migration_nonce_590d64c31db0a')
  ){
    wp_send_json_error(['error'=>'Invalid nonce.']);
  }
  $user_id = intval($_POST['user_id'] ?? 0);
  $test    = !empty($_POST['test']);
  $remove  = !empty($_POST['remove_old']);
  if($user_id<1||!current_user_can('edit_user',$user_id)){
    wp_send_json_error(['error'=>'Bad user or permissions.']);
  }
  $msgs = acf_convert_group_590d64c31db0a($user_id,$remove,$test);
  wp_send_json_success(['messages'=>$msgs]);
}

/**
 * 3) Conversion logic
 */
function acf_convert_group_590d64c31db0a($user_id,$remove_old=false,$test=false){
  $m = [];

  // load existing destination
  $existing_urls     = get_field('urls','user_'.$user_id)?:[];
  $existing_settings = get_field('settings','user_'.$user_id)?:[];
  $existing_subtitle = get_field('subtitle','user_'.$user_id);

  // 1) subtitle ← job_title
  $job = get_user_meta($user_id,'job_title',true);
  if($job!==''){
    if(empty($existing_subtitle)){
      update_field('subtitle',$job,'user_'.$user_id);
      $m[] = ($test?'TEST: ':'')."Mapped <strong>job_title</strong> → subtitle (value='{$job}')";
    } else {
      $m[] = ($test?'TEST: ':'')."Skipped <strong>job_title</strong> → subtitle (already '{$existing_subtitle}')";
    }
  } else {
    $m[] = ($test?'TEST: ':'')."No value for <strong>job_title</strong>";
  }

  // 2) socials & profiles → urls
  $map_text = [
    'socials_facebook'   => 'facebook',
    'socials_instagram'  => 'instagram',
    'socials_linkedin'   => 'linkedin',
    'socials_x'          => 'x',
    'socials_youtube'    => 'youtube',
    'socials_soundcloud' => 'soundcloud',
    'socials_tiktok'     => 'tiktok',
    'profiles_wikipedia' => 'wikipedia',
    'profiles_crunchbase'=> 'crunchbase',
    'profiles_muckrack'  => 'muckrack',
    'profiles_f6s'       => 'f6s',
    'profiles_imdb'      => 'imdb',
  ];
  $to_urls = [];
  foreach($map_text as $old=>$sub){
    $v = get_user_meta($user_id,$old,true);
    if($v!==''){
      if(empty($existing_urls[$sub])){
        $to_urls[$sub]=$v;
        $m[] = ($test?'TEST: ':'')."Mapped <strong>$old</strong> → urls['$sub'] (value='$v')";
      } else {
        $m[] = ($test?'TEST: ':'')."Skipped <strong>$old</strong> → urls['$sub'] (already '{$existing_urls[$sub]}')";
      }
    } else {
      $m[] = ($test?'TEST: ':'')."No value for <strong>$old</strong>";
    }
  }

  // 3) boolean muckrack_verified
  $bool_map = ['profiles_muckrack_verified'=>'muckrack_verified'];
  $to_set = [];
  foreach($bool_map as $old=>$sub){
    $v = get_user_meta($user_id,$old,true);
    if($v!==''){
      if(!isset($existing_settings[$sub])||$existing_settings[$sub]===''){
        $to_set[$sub]=$v?1:0;
        $m[] = ($test?'TEST: ':'')."Mapped <strong>$old</strong> → settings['$sub'] (value=".($v?'true':'false').")";
      } else {
        $m[] = ($test?'TEST: ':'')."Skipped <strong>$old</strong> → settings['$sub'] (already '{$existing_settings[$sub]}')";
      }
    } else {
      $m[] = ($test?'TEST: ':'')."No value for <strong>$old</strong>";
    }
  }

  // 4) save urls/settings if any
  if($to_urls){
    update_field('urls', array_merge($existing_urls,$to_urls),'user_'.$user_id);
    $m[] = ($test?'TEST: ':'')."Saved new <strong>urls</strong> group for user {$user_id}.";
  } else {
    $m[] = ($test?'TEST: ':'')."No new URLs to save.";
  }
  if($to_set){
    update_field('settings',array_merge($existing_settings,$to_set),'user_'.$user_id);
    $m[] = ($test?'TEST: ':'')."Saved new <strong>settings</strong> group for user {$user_id}.";
  } else {
    $m[] = ($test?'TEST: ':'')."No new Settings to save.";
  }

  // 5) delete old if requested
  if($remove_old){
    foreach(array_keys($map_text) as $old){
      delete_user_meta($user_id,$old);
      $m[] = ($test?'TEST: ':'')."Deleted <strong>$old</strong> for user {$user_id}.";
    }
    foreach(array_keys($bool_map) as $old){
      delete_user_meta($user_id,$old);
      $m[] = ($test?'TEST: ':'')."Deleted <strong>$old</strong> for user {$user_id}.";
    }
  } else {
    $m[] = ($test?'TEST: ':'')."<em>Old fields retained.</em>";
  }

  // 6) edit link
  $m[] = ($test?'TEST: ':'')."User edit link: <a href='".get_edit_user_link($user_id)."' target='_blank'>Edit User Profile</a>";

  return $m;
}
