<?php
// Run directly with PHP, not WP-CLI: actual WordPress bootstrap with no the_content filter.
define('WP_DISABLE_FATAL_ERROR_HANDLER',true);
set_exception_handler(function($e){fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");exit(1);});
define('WP_ADMIN',false);
define('WP_INSTALLING',true); // Load real WordPress style APIs without unrelated active-plugin redirects.
require dirname(__DIR__,4).'/wp-load.php';
use HWS\BaseTools\Newsletter\NewsletterModule as N;
spl_autoload_register(function($class){
    foreach(['HWS\\BaseTools\\'=>dirname(__DIR__).'/src/','Hexa\\PluginCore\\'=>dirname(__DIR__).'/lib/hexa-wordpress-plugin-core/src/'] as $prefix=>$root){
        if(str_starts_with($class,$prefix)){require_once $root.str_replace('\\','/',substr($class,strlen($prefix))).'.php';return;}
    }
});
if(defined('WP_CLI')||doing_filter('the_content'))throw new RuntimeException('Test must use web context');
add_filter('pre_option_'.N::OPTION,fn()=>['enabled'=>true,'notification_email'=>'','style'=>'none']);
add_shortcode('hws_newsletter',[N::class,'render']);
$discarded=do_shortcode('[hws_newsletter style="simple"]'); // Simulates an indexer discarding its expansion.
$visible=do_shortcode('[hws_newsletter style="simple"][hws_newsletter style="simple"]');
ob_start();do_action('wp_footer');do_action('wp_footer');$css=ob_get_clean();
if(preg_match_all('/id=["\x27]hws-newsletter-simple-inline-css["\x27]/',$css)!==1)throw new RuntimeException('Styles must print once after discarded expansion');
if(str_contains($visible,'<style'))throw new RuntimeException('Forms must not repeat style blocks');
if(!str_contains($css,N::css('simple')))throw new RuntimeException('Style content differs');
fwrite(STDOUT,"PASS 4 non-CLI/non-the_content style assertions\n");
