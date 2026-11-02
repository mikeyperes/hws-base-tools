<?php
// Run: wp eval-file wp-content/plugins/hws-base-tools/tests/display-conditions.php
use HWS\BaseTools\DisplayConditions\Conditions;
use HWS\BaseTools\DisplayConditions\Migration;
$assert=static function($value,$message){if(!$value)throw new RuntimeException($message);};
add_filter('get_post_metadata',static function($value,$id,$key){return $id===99999999 ? ['zero'=>'0','empty'=>'','video'=>'https://example.com/video'][$key]??'' : $value;},10,3);
foreach(['zero'=>true,'empty'=>false,'video'=>true] as $key=>$expected){$d=Conditions::normalize(['source'=>'custom_field','key'=>$key,'rule'=>'has_value']);$assert(Conditions::matches($d,99999999)===$expected,$key);}
$assert(Conditions::matches(Conditions::normalize(['source'=>'combined','relation'=>'all','conditions'=>[['source'=>'shortcode','key'=>'','rule'=>'is_empty'],['source'=>'custom_field','key'=>'zero','rule'=>'equals','value'=>'0']]]),99999999),'Combined rule');
$method=new ReflectionMethod(Migration::class,'translate');$method->setAccessible(true);
$tag='[elementor-tag name="post-custom-field" settings="'.rawurlencode('{"custom_key":"video"}').'"]';
$d=$method->invoke(null,['jedv_condition'=>'equal','__dynamic__'=>['jedv_field'=>$tag]]);
$assert($d['source']==='custom_field'&&$d['rule']==='equals'&&$d['value']==='','Legacy equality');
try{$method->invoke(null,['jedv_condition'=>'unknown']);throw new Exception('Unknown rule was accepted');}catch(RuntimeException $e){}
add_filter('pre_option_hws_base_tools_display_conditions_enabled',fn()=>true);
foreach(['heading','image'] as $name){$e=Elementor\Plugin::$instance->widgets_manager->get_widget_types($name);$assert(isset($e->get_controls()['hws_display_condition']),$name.' controls');}
foreach(['container','section','column'] as $name){$e=Elementor\Plugin::$instance->elements_manager->get_element_types($name);$assert(isset($e->get_controls()['hws_display_condition']),$name.' controls');}
echo "PASS: sources, zero/empty/equality, combined rules, legacy translation, unknown rejection and all five element controls.\n";
