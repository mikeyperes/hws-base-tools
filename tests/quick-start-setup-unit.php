<?php
namespace Hexa\PluginCore\PluginProvisioning {
    final class PluginProvisioner { public static function plugin_status_by_file($file) { return ['installed'=>true,'active'=>true]; } }
}
namespace {
function check($ok,$message){if(!$ok)throw new \RuntimeException($message);}
$dir=sys_get_temp_dir().'/mcm8-setup-'.getmypid().'/';
mkdir($dir.'wp-admin/includes',0777,true);file_put_contents($dir.'wp-admin/includes/file.php','<?php');file_put_contents($dir.'wp-admin/includes/misc.php','<?php');
define('ABSPATH',$dir);
$options=['permalink_structure'=>'','rewrite_rules'=>[]];
function get_option($key,$default=''){return $GLOBALS['options'][$key]??$default;}
function update_option($key,$value){$GLOBALS['options'][$key]=$value;}
function flush_rewrite_rules($hard){$GLOBALS['options']['rewrite_rules']=['page'=>'index.php'];}
function get_home_path(){return ABSPATH;}
function is_multisite(){return false;}
function insert_with_markers($path,$marker,$rules){$text=file_get_contents($path);$text=preg_replace('/# BEGIN WordPress.*?# END WordPress\n?/s','',$text);file_put_contents($path,$text."# BEGIN WordPress\n".implode("\n",$rules)."\n# END WordPress\n");return true;}
$wp_rewrite=new class{function set_permalink_structure($s){update_option('permalink_structure',$s);}function wp_rewrite_rules(){return get_option('rewrite_rules');}function mod_rewrite_rules(){return "RewriteEngine On\nRewriteRule . /index.php [L]";}};
file_put_contents($dir.'.htaccess',"# BEGIN LSCACHE\nkeep cache\n# END LSCACHE\n# cPanel keep\n");
require dirname(__DIR__).'/lib/hexa-wordpress-plugin-core/src/WordPressOperations/PermalinkOperations.php';
require dirname(__DIR__).'/src/QuickStart/PermalinkSetupService.php';
require dirname(__DIR__).'/src/QuickStart/SiteConfigurationService.php';
$r=(new \HWS\BaseTools\QuickStart\PermalinkSetupService)->repair();check($r['success'],'fresh repair');check(get_option('permalink_structure')==='/%postname%/','postname default');$text=file_get_contents($dir.'.htaccess');check(strpos($text,'keep cache')!==false && strpos($text,'cPanel keep')!==false,'preserve server blocks');
$r=(new \HWS\BaseTools\QuickStart\PermalinkSetupService)->repair();check(substr_count(file_get_contents($dir.'.htaccess'),'# BEGIN WordPress')===1,'idempotent marker');
function apply_filters($name,$value,...$args){return $name==='hws_base_tools_smtp2go_api_key'?'fixture-secret':$value;}
function home_url($path=''){return 'https://example.test'.$path;}
function sanitize_email($s){return trim($s);}function is_email($s){return filter_var($s,FILTER_VALIDATE_EMAIL);}function sanitize_text_field($s){return trim($s);}function get_bloginfo($s){return 'Example';}
function wp_mail_smtp(){return $GLOBALS['mail'];}
$store=new class{public $data=['unrelated'=>['preserve'=>true]];function set($v,$once,$overwrite){check(!$overwrite,'merge settings');$this->data=array_replace_recursive($this->data,$v);}function get($group,$name){return $this->data[$group][$name]??null;}};
$mail=new class($store){function __construct(public $store){}function get_connections_manager(){return $this;}function get_primary_connection(){return $this;}function get_options(){return $this->store;}};
$r=(new \HWS\BaseTools\QuickStart\SiteConfigurationService)->configure_smtp(['smtp_from_email'=>'OWNER@EXAMPLE.TEST','smtp_from_name'=>'Owner']);check($r['success'],'SMTP saved');check($store->data['mail']['from_email']==='owner@example.test','email lowercase');check($store->data['unrelated']['preserve'],'SMTP preserves settings');check(strpos(json_encode($r),'fixture-secret')===false,'no secret output');check($r['data']['email_sent']===false,'no mail sent');
class wfConfig { public static function get($key,$default=''){return $default;} }
require dirname(__DIR__).'/src/Security/WordfencePolicyService.php';
$policy=new \HWS\BaseTools\Security\WordfencePolicyService;
$method=new \ReflectionMethod($policy,'validate_site_license');$method->setAccessible(true);$license=$method->invoke($policy);
check($license['success']===false && $license['attempted']===false,'no anonymous license call');check($license['reason']==='site_license_registration_required' && !empty($license['registration_url']),'supported registration requirement');
foreach(['.htaccess','wp-admin/includes/file.php','wp-admin/includes/misc.php'] as $file)unlink($dir.$file);rmdir($dir.'wp-admin/includes');rmdir($dir.'wp-admin');rmdir($dir);
echo "SETUP-001: fresh permalinks, rewrite block preservation/idempotency, SMTP save/merge/redaction/no-send, Wordfence registration passed\n";
}
