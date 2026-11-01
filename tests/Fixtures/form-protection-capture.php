<?php
// Disposable browser acceptance fixture, copied from canonical tests only for the bounded run.
defined('ABSPATH')||exit;
add_filter('pre_wp_mail',static function($pre,$atts){
    if(!str_starts_with((string)($atts['subject']??''),'HWS form protection acceptance '))return $pre;
    update_option('hws_form_protection_acceptance_receipt',['accepted_at'=>gmdate('c'),'subject'=>$atts['subject'],'recipient'=>$atts['to'],'captured'=>true],false);
    return true;
},PHP_INT_MAX,2);
add_filter('wpcf7_recaptcha_skip',static function($skip){
    $id=(int)get_option('hws_form_protection_acceptance_form',0);
    return $id>0&&(int)($_POST['_wpcf7']??0)===$id?true:$skip;
});
