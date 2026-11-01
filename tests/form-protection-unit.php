<?php
declare(strict_types=1);
define('ABSPATH',__DIR__.'/fixture/');
define('ARRAY_A','ARRAY_A');
$checks=0;$failures=[];$options=[];$hooks=[];$routes=[];$assets=[];$admin=true;$scheduled=[];
function expect_form($ok,$message){global$checks,$failures;$checks++;echo($ok?'PASS ':'FAIL ').$message."\n";if(!$ok)$failures[]=$message;}
function get_option($key,$default=false){global$options;return $options[$key]??$default;}
function update_option($key,$value,$autoload=null){global$options;$options[$key]=$value;return true;}
function delete_option($key){global$options;unset($options[$key]);return true;}
function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$v));}
function sanitize_text_field($v){return trim(strip_tags((string)$v));}
function esc_attr($v){return htmlspecialchars((string)$v,ENT_QUOTES);}
function esc_html($v){return esc_attr($v);}
function absint($v){return abs((int)$v);}
function wp_salt($scheme){return 'unit-fixture-only';}
function wp_unslash($v){return $v;}
function wp_json_encode($v){return json_encode($v);}
function current_user_can($v){global$admin;return $admin;}
function apply_filters($key,$value,...$args){global$hooks;foreach($hooks[$key]??[]as$cb)$value=$cb($value,...$args);return $value;}
function add_action($key,$cb,$priority=10,$args=1){global$hooks;$hooks[$key][]=$cb;}
function add_filter($key,$cb,$priority=10,$args=1){add_action($key,$cb,$priority,$args);}
function do_action($key,...$args){global$hooks;foreach($hooks[$key]??[]as$cb)$cb(...$args);}
function register_rest_route($ns,$route,$args){global$routes;$routes[$route]=$args;}
function __return_true(){return true;}
function wp_enqueue_script($key,...$args){global$assets;$assets[]=$key;}
function wp_localize_script(...$args){}
function plugins_url($p,$file){return 'https://example.com/plugins/'.$p;}
function rest_url($p){return 'https://example.com/wp-json/'.$p;}
function wp_next_scheduled($hook,$args=[]){global$scheduled;return $scheduled[$hook]??false;}
function wp_schedule_event($at,$schedule,$hook,$args=[]){global$scheduled;$scheduled[$hook]=$at;return true;}
function wp_generate_uuid4(){return '00000000-0000-4000-8000-000000000001';}
class WP_REST_Response {public array $headers=[];public function __construct(public mixed $data=null){}public function header($k,$v){$this->headers[$k]=$v;}}
class WP_Error {public function __construct(public string $code,public string $message,public array $data=[]){}}
class WPCF7_ContactForm {public static function get_current(){return new self;}public function id(){return 7;}}
$wpdb=new class {
    public string $prefix='wp_';public array $rows=[];public array $queries=[];
    public function esc_like($s){return $s;}
    public function prepare($sql,...$args){foreach($args as$a)$sql=preg_replace('/%[sd]/',is_numeric($a)?(string)$a:"'".$a."'",$sql,1);return $sql;}
    public function get_var($sql){$this->queries[]=$sql;return str_starts_with($sql,'SHOW TABLES')?'wp_hws_base_tools_form_blocks':100;}
    public function get_results($sql,$mode){$this->queries[]=$sql;return $this->rows;}
    public function insert($table,$row,$formats){$this->rows[]=$row;return 1;}
    public function query($sql){$this->queries[]=$sql;return 1;}
};
spl_autoload_register(function($class){
    $map=['HWS\\BaseTools\\'=>__DIR__.'/../src/','Hexa\\PluginCore\\'=>__DIR__.'/../lib/hexa-wordpress-plugin-core/src/'];
    foreach($map as$prefix=>$root)if(str_starts_with($class,$prefix)){require $root.str_replace('\\','/',substr($class,strlen($prefix))).'.php';return;}
});
use HWS\BaseTools\FormProtection\{Settings,Challenge,Protection,FormProtectionModule,AttemptLog};
$c=new Challenge('fixture-signing-key');$token=$c->issue('wpforms:4',1000);
expect_form($c->reason($token,'wpforms:4',3,1003)===null,'valid form-bound token accepted after minimum');
expect_form($c->reason($token,'wpforms:4',3,1002)==='too_fast','minimum fill time enforced');
expect_form($c->reason($token,'wpforms:5',3,1003)==='invalid_token','cross-form token rejected');
expect_form($c->reason($token.'x','wpforms:4',3,1003)==='invalid_token','tampered token rejected');
expect_form($c->reason($token,'wpforms:4',3,999)==='invalid_token','future token rejected');
expect_form($c->reason($token,'wpforms:4',3,8201)==='invalid_token','expired token rejected');
expect_form($c->reason('','wpforms:4',3,1003)==='missing_token','missing challenge rejected');
$s=Settings::get();expect_form(!$s['enabled']&&!$s['turnstile_enabled'],'new feature and Turnstile are off by default');
$providerCalls=0;$p=new Protection($c,function($v)use(&$providerCalls){$providerCalls++;return $v==='valid';});
expect_form($p->reason(['hws_form_website'=>'bot'],'wpforms:4',$s,1003)===null,'disabled protection changes no submission');
$s['enabled']=true;$post=['hws_form_token'=>$token,'hws_form_website'=>''];
expect_form($p->reason($post,'wpforms:4',$s,1003)===null,'normal submission accepted');
expect_form($p->reason($post+['extra'=>'ignored'],'wpforms:4',$s,1002)==='too_fast','fast submission blocked');
expect_form($p->reason(array_replace($post,['hws_form_website'=>'bot']),'wpforms:4',$s,1003)==='honeypot','bot-filled honeypot blocked');
expect_form($p->reason(array_replace($post,['hws_form_website'=>[]]),'wpforms:4',$s,1003)==='honeypot','array honeypot is not silently accepted');
$s['turnstile_enabled']=true;
expect_form($p->reason($post,'wpforms:4',$s,1003)==='turnstile','missing optional Turnstile response blocked when required');
expect_form($p->reason($post+['cf-turnstile-response'=>'invalid'],'wpforms:4',$s,1003)==='turnstile','failed Turnstile verification blocked');
expect_form($p->reason($post+['cf-turnstile-response'=>'valid'],'wpforms:4',$s,1003)===null,'verified Turnstile accepted');
expect_form($providerCalls===2,'Turnstile verifier called only with a response when enabled');
$module=new FormProtectionModule;$module->register();
foreach(['elementor/widget/render_content','elementor_pro/forms/validation','wpcf7_form_elements','wpcf7_spam','gform_form_tag','gform_validation','wpforms_display_submit_before','wpforms_process_initial_errors']as$hook)expect_form(isset($hooks[$hook]),'native hook registered: '.$hook);
expect_form(isset($hooks['hws_base_tools_form_protection_prune']),'retention cleanup hook registered');
expect_form(FormProtectionModule::markup('wpforms:4')===''&&$assets===[],'off state renders no fields or frontend assets');
$options[Settings::OPTION]=Settings::sanitize(['enabled'=>true]);
$markup=FormProtectionModule::markup('wpforms:4');
expect_form(str_contains($markup,'hws_form_website')&&str_contains($markup,'hws_form_token'),'enabled form receives honeypot and challenge fields');
expect_form($assets===['hws-form-protection'],'only protected form loads its own frontend script');
$widget=new class {public function get_name(){return 'form';}public function get_id(){return 'abc';}};
$html=FormProtectionModule::elementor_markup('<form><input name="post_id" value="12"></form>',$widget);
expect_form(str_contains($html,'elementor:12:abc'),'Elementor binding uses native hidden post_id');
$_SERVER['REMOTE_ADDR']='192.0.2.1';$_POST=['hws_form_website'=>'bot','post_id'=>12,'form_id'=>'abc'];
$handler=new class {public array $errors=[];public function add_error($id,$text){$this->errors[$id]=$text;}};
FormProtectionModule::elementor_validate(null,$handler);expect_form(isset($handler->errors['hws_form_protection']),'Elementor native validation stops bot');
$submission=new class {public array $logs=[];public function get_contact_form(){return new WPCF7_ContactForm;}public function add_spam_log($log){$this->logs[]=$log;}};
expect_form(FormProtectionModule::cf7_spam(false,$submission),'Contact Form 7 spam hook stops bot');
expect_form(count($submission->logs)===1,'Contact Form 7 receives native spam evidence');
$count=count($wpdb->rows);expect_form(FormProtectionModule::cf7_spam(true,$submission)&&count($wpdb->rows)===$count,'existing plugin spam result is preserved without duplicate logging');
$result=FormProtectionModule::gravity_validate(['is_valid'=>true,'form'=>['id'=>3]]);
expect_form(!$result['is_valid'],'Gravity Forms native validation stops bot');
expect_form(isset(FormProtectionModule::wpforms_validate([],['id'=>4])[4]['header']),'WPForms native validation stops bot');
foreach($wpdb->rows as$row)expect_form(array_keys($row)===['occurred_at','form_key','ip','reason'],'blocked log contains metadata only');
expect_form(count(array_filter($wpdb->queries,fn($q)=>str_contains($q,'DELETE')&&str_contains($q,'occurred_at')))>0,'retention deletes old records');
expect_form(count(array_filter($wpdb->queries,fn($q)=>str_contains($q,'OFFSET 4999')))>0,'blocked log has a 5,000-record cap');
$before=count($wpdb->queries);AttemptLog::read(20);$read=array_slice($wpdb->queries,$before);
expect_form(count(array_filter($read,fn($q)=>str_starts_with($q,'DELETE')||str_starts_with($q,'INSERT')))===0,'log reads never write');
FormProtectionModule::routes();$admin=false;
expect_form(!$routes['/form-protection/blocked']['permission_callback'](),'blocked REST endpoint denies non-administrator');
$admin=true;expect_form($routes['/form-protection/blocked']['permission_callback'](),'blocked REST endpoint admits administrator');
$request=new class {public function get_param($key){return 'wpforms:4';}};
$response=$routes['/form-protection/challenge']['callback']($request);
expect_form(str_contains($response->headers['Cache-Control'],'no-store'),'challenge responses cannot be cached');
try{Settings::save(['enabled'=>true,'turnstile_enabled'=>true]);expect_form(false,'missing Turnstile keys refused');}catch(InvalidArgumentException){expect_form(true,'missing Turnstile keys refused');}
Settings::save(['enabled'=>false,'secret_key'=>'fixture-secret-value']);
$state=json_encode(Settings::state());expect_form(!str_contains($state,'fixture-secret-value')&&Settings::state()['secret_present'],'secret is stored but never returned by status');
expect_form(isset($scheduled['hws_base_tools_form_protection_prune']),'settings save schedules daily retention through Core');
echo $checks.' checks, '.count($failures)." failures\n";exit($failures?1:0);
