<?php
use HWS\BaseTools\FormProtection\Settings;
$f=get_option('hws_form_protection_acceptance_fixture',[]);
if(!$f)throw new RuntimeException('No acceptance fixture found for cleanup');
$receipt=get_option('hws_form_protection_acceptance_receipt',null);
Settings::save($f['before']??[]);
if($f['before']===null)delete_option(Settings::OPTION);
wp_delete_post((int)$f['page_id'],true);wp_delete_post((int)$f['form_id'],true);
$mu=WPMU_PLUGIN_DIR.'/hws-form-protection-acceptance.php';
if(is_file($mu)&&!unlink($mu))throw new RuntimeException('Mail capture fixture could not be removed');
foreach(['hws_form_protection_acceptance_fixture','hws_form_protection_acceptance_form','hws_form_protection_acceptance_receipt']as$key)delete_option($key);
echo 'HWS_ACCEPTANCE:'.wp_json_encode(['cleaned'=>!is_file($mu)&&!get_post((int)$f['page_id'])&&!get_post((int)$f['form_id']),'protection_enabled'=>Settings::get()['enabled'],'mail_capture'=>$receipt]);
