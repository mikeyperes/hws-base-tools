<?php
declare(strict_types=1);
define('ABSPATH',__DIR__.'/fixture/');
define('ARRAY_A','ARRAY_A');
$checks=0;$failures=[];$options=[];$hooks=[];$routes=[];$assets=[];$registered=[];$admin=true;$scheduled=[];
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
function wp_register_script($key,...$args){global$registered;$registered[$key]=$args;}
function wp_enqueue_script($key,...$args){global$assets;$assets[]=$key;}
function wp_localize_script(...$args){}
function plugins_url($p,$file){return 'https://example.com/plugins/'.$p;}
function home_url($p=''){return 'https://example.com/';}
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
use HWS\BaseTools\FormProtection\{Settings,Challenge,Protection,FormProtectionModule,AttemptLog,Honeypots,HeaderCheck,BotCheck};
$c=new Challenge('fixture-signing-key');$token=$c->issue('wpforms:4',1000);
expect_form($c->reason($token,'wpforms:4',3,1003)===null,'valid form-bound token accepted after minimum');
expect_form($c->reason($token,'wpforms:4',3,1002)==='too_fast','minimum fill time enforced');
expect_form($c->reason($token,'wpforms:5',3,1003)==='invalid_token','cross-form token rejected');
expect_form($c->reason($token.'x','wpforms:4',3,1003)==='invalid_token','tampered token rejected');
expect_form($c->reason($token,'wpforms:4',3,999)==='invalid_token','future token rejected');
expect_form($c->reason($token,'wpforms:4',3,8201)==='invalid_token','expired token rejected');
expect_form($c->reason('','wpforms:4',3,1003)==='missing_token','missing challenge rejected');
$s=Settings::get();expect_form(!$s['enabled']&&!$s['turnstile_enabled'],'new feature and Turnstile are off by default');
$providerCalls=0;$p=new Protection($c,function($provider,$v,$settings)use(&$providerCalls){$providerCalls++;return $v==='valid'?null:$provider.'_invalid';});
expect_form($p->reason(['hws_form_honeypots'=>array_fill_keys(Honeypots::names('wpforms:4'),'bot')],'wpforms:4',$s,1003)===null,'disabled protection changes no submission');
$s['enabled']=true;$names=Honeypots::names('wpforms:4');$post=['hws_form_token'=>$token,'hws_form_honeypots'=>array_fill_keys($names,'')];
expect_form($p->reason($post,'wpforms:4',$s,1003)===null,'normal submission accepted');
expect_form($p->reason($post+['extra'=>'ignored'],'wpforms:4',$s,1002)==='too_fast','fast submission blocked');
expect_form($p->reason(array_replace($post,['hws_form_honeypots'=>array_fill_keys(Honeypots::names('wpforms:4'),'bot')]),'wpforms:4',$s,1003)==='honeypot','bot-filled honeypot blocked');
expect_form($p->reason(array_replace($post,['hws_form_honeypots'=>[$names[0]=>[]]]),'wpforms:4',$s,1003)==='honeypot','array honeypot is not silently accepted');
$s['bot_check']='turnstile';
expect_form($p->reason($post,'wpforms:4',$s,1003)==='turnstile_missing','missing optional Turnstile response blocked when required');
expect_form($p->reason($post+['cf-turnstile-response'=>'invalid'],'wpforms:4',$s,1003)==='turnstile_invalid','failed Turnstile verification blocked');
expect_form($p->reason($post+['cf-turnstile-response'=>'valid'],'wpforms:4',$s,1003)===null,'verified Turnstile accepted');
expect_form($providerCalls===2,'Turnstile verifier called only with a response when enabled');
$module=new FormProtectionModule;$module->register();
foreach(['elementor/widget/render_content','elementor_pro/forms/validation','wpcf7_form_elements','wpcf7_spam','gform_form_tag','gform_validation','wpforms_display_submit_before','wpforms_process_initial_errors']as$hook)expect_form(isset($hooks[$hook]),'native hook registered: '.$hook);
expect_form(isset($hooks['hws_base_tools_form_protection_prune']),'retention cleanup hook registered');
expect_form(FormProtectionModule::markup('wpforms:4')===''&&$assets===[],'off state renders no fields or frontend assets');
$options[Settings::OPTION]=Settings::sanitize(['enabled'=>true]);
$markup=FormProtectionModule::markup('wpforms:4');
expect_form(str_contains($markup,'hws_form_honeypots')&&str_contains($markup,'hws_form_token'),'enabled form receives honeypot and challenge fields');
expect_form($assets===['hws-form-protection'],'only protected form loads its own frontend script');
$widget=new class {public function get_name(){return 'form';}public function get_id(){return 'abc';}};
$html=FormProtectionModule::elementor_markup('<form><input name="post_id" value="12"></form>',$widget);
expect_form(str_contains($html,'elementor:12:abc'),'Elementor binding uses native hidden post_id');
$_SERVER['REMOTE_ADDR']='192.0.2.1';$_POST=['hws_form_honeypots'=>array_fill_keys(Honeypots::POOL,'bot'),'post_id'=>12,'form_id'=>'abc'];
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

// Independent switches, stable names, malformed payloads and every header reason.
expect_form(count($names)===2&&count(array_unique($names))===2&&!array_diff($names,Honeypots::POOL),'two distinct names come from the pool');
expect_form($names===Honeypots::names('wpforms:4'),'honeypot names stay stable per form');
foreach($names as$name)expect_form(Honeypots::reason(['hws_form_honeypots'=>[$name=>'filled']],'wpforms:4')==='honeypot','each honeypot rejects: '.$name);
expect_form(Honeypots::reason(['hws_form_honeypots'=>'bad'],'wpforms:4')==='honeypot','malformed honeypot group rejected');
$off=Settings::sanitize(['enabled'=>true,'honeypots_enabled'=>false,'too_fast_enabled'=>false,'header_check_enabled'=>false]);
expect_form($p->reason(['hws_form_honeypots'=>array_fill_keys($names,'bot'),'hws_form_token'=>[]],'wpforms:4',$off,1000)===null,'all check switches off accepts hostile fields');
expect_form($p->reason($post,'wpforms:4',array_replace($off,['honeypots_enabled'=>true]),1000)===null,'honeypot switch does not enable time check');
expect_form($p->reason([],'wpforms:4',array_replace($off,['too_fast_enabled'=>true]),1003)==='missing_token','time check independently rejects missing token');
$headers=['HTTP_ORIGIN'=>'https://example.com','HTTP_REFERER'=>'https://example.com/contact','HTTP_USER_AGENT'=>'Mozilla/5.0 Chrome/132','HTTP_SEC_FETCH_SITE'=>'same-origin','HTTP_ACCEPT_LANGUAGE'=>'en-US'];
expect_form(HeaderCheck::reason($headers,'example.com')===null,'normal browser headers pass');
expect_form(HeaderCheck::reason(array_diff_key($headers,array_flip(['HTTP_ORIGIN'])),'example.com')===null,'same-site Referer alone passes');
expect_form(HeaderCheck::reason(array_diff_key($headers,array_flip(['HTTP_REFERER'])),'example.com')===null,'same-site Origin alone passes');
$cases=[
 'origin_referer_missing'=>['HTTP_ORIGIN'=>'','HTTP_REFERER'=>''],
 'origin_invalid'=>['HTTP_ORIGIN'=>'https://example.com.attacker.invalid'],
 'referer_invalid'=>['HTTP_REFERER'=>'https://attacker.invalid'],
 'user_agent_missing'=>['HTTP_USER_AGENT'=>''],
 'user_agent_script'=>['HTTP_USER_AGENT'=>'curl/8.0'],
 'sec_fetch_site_invalid'=>['HTTP_SEC_FETCH_SITE'=>'cross-site']
];
foreach($cases as$reason=>$changes){
 $request=array_replace($headers,$changes);
 expect_form(HeaderCheck::reason($request,'example.com')===$reason,'header reason: '.$reason);
 expect_form($p->reason([],'wpforms:4',array_replace($off,['header_check_enabled'=>true]),1003,$request,'example.com')===$reason,'header switch independently enforces: '.$reason);
 $before=count($wpdb->rows);AttemptLog::record('wpforms:4','192.0.2.1',$reason);expect_form(count($wpdb->rows)===$before+1,'header reason is logged: '.$reason);
}
foreach(['curl','Python-requests','Wget','Go-http-client','Java/21','libwww-perl','FriendlyBOT']as$agent)expect_form(HeaderCheck::reason(array_replace($headers,['HTTP_USER_AGENT'=>$agent]),'example.com')==='user_agent_script','script user agent rejected: '.$agent);
foreach(['null','//example.com','https://example.com@evil.invalid','ftp://example.com','https://user@example.com']as$origin)expect_form(HeaderCheck::reason(array_replace($headers,['HTTP_ORIGIN'=>$origin]),'example.com')==='origin_invalid','invalid origin rejected: '.$origin);
expect_form(HeaderCheck::reason(array_diff_key($headers,['HTTP_SEC_FETCH_SITE'=>1]),'example.com')===null,'absent optional Sec-Fetch-Site accepted');
$noLanguage=array_replace($headers,['HTTP_ACCEPT_LANGUAGE'=>'']);
expect_form(HeaderCheck::reason($noLanguage,'example.com')===null&&HeaderCheck::signals($noLanguage)===['accept_language_missing'],'missing language signals without rejection');
$before=count($wpdb->rows);AttemptLog::record('wpforms:4','192.0.2.1','accept_language_missing');expect_form(count($wpdb->rows)===$before+1,'language signal logged');
$before=count($wpdb->queries);AttemptLog::read(10);expect_form(str_contains(end($wpdb->queries),"reason <> 'accept_language_missing'"),'blocked reader excludes non-blocking signals');
AttemptLog::read(10,true);expect_form(str_contains(end($wpdb->queries),"reason = 'accept_language_missing'"),'signals have a separate reader');
// Provider transport, hostname, action, score and failure handling.
Settings::save(['recaptcha_secret_key'=>'recaptcha-fixture-secret']);
$data=['success'=>true,'hostname'=>'example.com','action'=>'hws-form','score'=>0.9];$requests=[];
$verifier=new BotCheck(function($url,$body)use(&$data,&$requests){$requests[]=[$url,$body];return $data;});
foreach(['turnstile','recaptcha']as$provider){
 $data['action']=BotCheck::action($provider);
 expect_form($verifier->reason($provider,'valid',$off,'example.com','192.0.2.1')===null,'verified provider accepted: '.$provider);
 foreach([null,[],['success'=>false],array_replace($data,['hostname'=>'evil.invalid']),array_replace($data,['action'=>'wrong'])]as$bad){$good=$data;$data=$bad;expect_form($verifier->reason($provider,'bad',$off,'example.com','')===$provider.'_invalid','provider response fails closed: '.$provider);$data=$good;}
 expect_form($p->reason([],'wpforms:4',array_replace($off,['bot_check'=>$provider]),1003)===$provider.'_missing','missing provider response: '.$provider);
 expect_form($p->reason([$provider==='turnstile'?'cf-turnstile-response':'g-recaptcha-response'=>[]],'wpforms:4',array_replace($off,['bot_check'=>$provider]),1003)===$provider.'_missing','array provider response rejected: '.$provider);
}
foreach([0.49,0.0]as$score){$data['score']=$score;expect_form($verifier->reason('recaptcha','test',$off,'example.com','')==='recaptcha_score','low reCAPTCHA score rejected');}
foreach([0.5,1.0]as$score){$data['score']=$score;expect_form($verifier->reason('recaptcha','test',$off,'example.com','')===null,'threshold reCAPTCHA score accepted');}
foreach([null,'invalid',-0.1,1.1]as$score){$data['score']=$score;expect_form($verifier->reason('recaptcha','test',$off,'example.com','')==='recaptcha_invalid','invalid reCAPTCHA score rejected');}
$saved=Settings::save(['bot_check'=>'recaptcha','recaptcha_site_key'=>'public-recaptcha']);
expect_form($saved['recaptcha_enabled']&&!$saved['turnstile_enabled'],'saving reCAPTCHA disables Turnstile');
$saved=Settings::save(Settings::merge(['turnstile_enabled'=>'1','site_key'=>'public-turnstile']));
expect_form($saved['bot_check']==='turnstile'&&!$saved['recaptcha_enabled'],'legacy Turnstile switch disables reCAPTCHA');
$saved=Settings::save(Settings::merge(['bot_check'=>'none']));expect_form(!$saved['turnstile_enabled']&&!$saved['recaptcha_enabled'],'none disables both providers');
$state=json_encode($saved);expect_form(!str_contains($state,'fixture-secret'),'status never exposes either secret');
$options[Settings::OPTION]=[];
$saved=Settings::save(Settings::merge(['site_key'=>'public-turnstile']));expect_form($saved['bot_check']==='turnstile','first key setup defaults to Turnstile');
$saved=Settings::save(Settings::merge(['bot_check'=>'none']));$saved=Settings::save(Settings::merge(['site_key'=>'public-changed']));expect_form($saved['bot_check']==='none','explicit none survives key edits');
$onlyHeader=Settings::sanitize(['enabled'=>true,'honeypots_enabled'=>false,'too_fast_enabled'=>false,'header_check_enabled'=>true]);$options[Settings::OPTION]=$onlyHeader;$assets=[];
expect_form(!str_contains(FormProtectionModule::markup('wpforms:4'),'<input')&&$assets===[],'header-only protection loads no inputs or assets');
$options[Settings::OPTION]=Settings::sanitize(['enabled'=>true,'bot_check'=>'recaptcha','recaptcha_site_key'=>'public']);$markup=FormProtectionModule::markup('wpforms:4');
expect_form(str_contains($markup,'g-recaptcha-response')&&!str_contains($markup,'data-hws-turnstile'),'only selected provider markup rendered');
foreach(['turnstile_missing','turnstile_invalid','recaptcha_missing','recaptcha_invalid','recaptcha_score']as$reason){$before=count($wpdb->rows);AttemptLog::record('wpforms:4','192.0.2.1',$reason);expect_form(count($wpdb->rows)===$before+1,'provider reason logged: '.$reason);}
$options[Settings::OPTION]=Settings::sanitize(['enabled'=>true]);$assets=[];FormProtectionModule::register_assets();
expect_form(isset($registered['hws-form-protection'])&&$assets===[],'cached Elementor asset handles are registered without globally enqueueing');
expect_form(FormProtectionModule::elementor_dynamic(false,['widgetType'=>'form']),'protected Elementor forms render dynamically instead of caching protection fields');
expect_form(!FormProtectionModule::elementor_dynamic(false,['widgetType'=>'heading']),'unrelated Elementor widgets keep caching');
$options[Settings::OPTION]=Settings::sanitize(['enabled'=>false]);
expect_form(!FormProtectionModule::elementor_dynamic(false,['widgetType'=>'form']),'disabled protection preserves native Elementor caching');
echo $checks.' checks, '.count($failures)." failures\n";exit($failures?1:0);
