<?php
use HWS\BaseTools\FormProtection\Settings;
$key='hws_form_protection_elementor_fixture';$f=get_option($key);
if(!$f)throw new RuntimeException('No Elementor acceptance fixture.');
wp_delete_post((int)$f['page_id'],true);
if($f['before']===null)delete_option(Settings::OPTION);else update_option(Settings::OPTION,$f['before'],false);
delete_option($key);do_action('litespeed_purge_all');
echo wp_json_encode(['page_deleted'=>get_post((int)$f['page_id'])===null,'settings_restored'=>get_option(Settings::OPTION,null)===$f['before'],'fixture_removed'=>!get_option($key),'notification_actions'=>[]]);
