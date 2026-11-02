<?php
// Run with wp eval-file on an explicitly authorized Hexa-owned site, as an administrator.
// The private page has no email, webhook, collection, or other post-submit actions.
use HWS\BaseTools\FormProtection\Settings;
if(!current_user_can('manage_options'))throw new RuntimeException('Administrator required.');
if(!class_exists('ElementorPro\Plugin'))throw new RuntimeException('Active Elementor Pro required.');
$key='hws_form_protection_elementor_fixture';
if(get_option($key))throw new RuntimeException('An Elementor acceptance fixture already exists.');
$tag=bin2hex(random_bytes(5));$id='a'.substr($tag,0,6);
$doc=\Elementor\Plugin::$instance->documents->create('wp-page',['post_title'=>'HWS private form acceptance '.$tag,'post_status'=>'private','post_name'=>'hws-private-form-'.$tag]);
if(is_wp_error($doc))throw new RuntimeException($doc->get_error_message());
$elements=[['id'=>'c'.substr($tag,0,6),'elType'=>'container','settings'=>[],'elements'=>[['id'=>$id,'elType'=>'widget','widgetType'=>'form','settings'=>[
 'form_name'=>'HWS private acceptance '.$tag,'form_fields'=>[['_id'=>'name123','custom_id'=>'name','field_type'=>'text','field_label'=>'Name','required'=>'true','width'=>'100'],['_id'=>'email12','custom_id'=>'email','field_type'=>'email','field_label'=>'Email','required'=>'true','width'=>'100']],
 'submit_actions'=>[],'button_text'=>'Send test','success_message'=>'Private acceptance passed.'
],'elements'=>[]]]]];
if(!$doc->save(['elements'=>$elements,'settings'=>['post_status'=>'private','template'=>'elementor_canvas']])){wp_delete_post($doc->get_main_id(),true);throw new RuntimeException('Elementor document save failed.');}
$page=$doc->get_main_id();update_post_meta($page,'rank_math_robots',['noindex','nofollow']);
$fixture=['page_id'=>$page,'widget_id'=>$id,'form_key'=>'elementor:'.$page.':'.$id,'url'=>get_permalink($page),'before'=>get_option(Settings::OPTION,null)];
update_option($key,$fixture,false);
echo wp_json_encode(array_diff_key($fixture,['before'=>1]));
