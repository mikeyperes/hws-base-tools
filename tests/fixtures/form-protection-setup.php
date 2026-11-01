<?php
// Executed with wp eval-file on the selected HWS-owned test site.
use HWS\BaseTools\FormProtection\Settings;
if(!class_exists('WPCF7_ContactForm'))throw new RuntimeException('Contact Form 7 is required for the acceptance fixture.');
if(get_option('hws_form_protection_acceptance_fixture'))throw new RuntimeException('An acceptance fixture already exists.');
$before=get_option(Settings::OPTION,null);
$tag=bin2hex(random_bytes(6));$title='HWS form protection acceptance '.$tag;
add_filter('pre_wp_mail',fn()=>true,PHP_INT_MAX);
$form=WPCF7_ContactForm::get_template(['title'=>$title]);
$form->set_properties(['form'=>'<label>Name [text* your-name]</label><label>Email [email* your-email]</label><label>Message [textarea* your-message]</label>[submit "Send acceptance test"]','mail'=>['active'=>true,'recipient'=>'capture@example.invalid','sender'=>'Acceptance <no-reply@'.preg_replace('/^www\\./','',(string)parse_url(home_url('/'),PHP_URL_HOST)).'>','subject'=>$title,'body'=>'[your-name] [your-email] [your-message]','additional_headers'=>'Reply-To: [your-email]','use_html'=>false,'exclude_blank'=>false]]);
$form->save();$formId=$form->id();
$pageId=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>$title,'post_name'=>'hws-form-acceptance-'.$tag,'post_content'=>'[contact-form-7 id="'.$formId.'" title="Acceptance"]'],true);
if(is_wp_error($pageId))throw new RuntimeException($pageId->get_error_message());
update_post_meta($pageId,'rank_math_robots',['noindex','nofollow']);
$fixture=['form_id'=>$formId,'page_id'=>$pageId,'url'=>get_permalink($pageId),'before'=>$before,'subject'=>$title];
update_option('hws_form_protection_acceptance_fixture',$fixture,false);
update_option('hws_form_protection_acceptance_form',$formId,false);
$mu=WPMU_PLUGIN_DIR.'/hws-form-protection-acceptance.php';
if(is_file($mu))throw new RuntimeException('Acceptance mail capture path already exists.');
if(!is_dir(WPMU_PLUGIN_DIR))wp_mkdir_p(WPMU_PLUGIN_DIR);
if(file_put_contents($mu,file_get_contents(__DIR__.'/form-protection-capture.php'))===false)throw new RuntimeException('Could not install bounded mail capture fixture');
Settings::save(array_replace(Settings::get(),['enabled'=>true,'min_fill_seconds'=>3,'turnstile_enabled'=>false]));
echo 'HWS_ACCEPTANCE:'.wp_json_encode(['form_id'=>$formId,'page_id'=>$pageId,'url'=>$fixture['url'],'subject'=>$title]);
