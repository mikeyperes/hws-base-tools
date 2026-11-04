<?php
namespace HWS\BaseTools\Newsletter;
use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\FrontendForms\SubmissionStore;
use HWS\BaseTools\FormProtection\{Settings,BotCheck};
use HWS\BaseTools\PluginRuntime\PluginMetadata;
use Hexa\PluginCore\WpAdminComponents\{CoreUi,TemplateSelectionControl};

final class NewsletterModule implements ModuleInterface {
    public const SUCCESS='Thanks for signing up!';
    public const DUPLICATE='This email is already subscribed to this list.';
    public const ERROR='Signup could not be saved. Try again later.';
    public const MESSAGE_LIMIT=2000;
    public const OPTION='hws_base_tools_newsletter';

    public function register(): void {
        add_action('init',[self::class,'init']);
        add_action('template_redirect',[self::class,'frontend_post'],1);
        add_action('rest_api_init',[self::class,'routes']);
        add_filter('hws_base_tools_dashboard_tabs',function($tabs){$tabs['newsletter']='Newsletter';return $tabs;});
        add_filter('hws_base_tools_render_dashboard_tab',function($handled,$tab){if($tab==='newsletter'){self::admin();return true;}return $handled;},10,2);
        add_action('admin_post_hws_base_tools_newsletter_save',[self::class,'save']);
        add_action('admin_post_hws_base_tools_newsletter_export',[self::class,'export']);
        add_action('admin_post_hws_base_tools_newsletter_delete',[self::class,'delete']);
        add_action('admin_post_hws_base_tools_newsletter',[self::class,'post']);
        add_action('admin_post_nopriv_hws_base_tools_newsletter',[self::class,'post']);
        add_filter('wp_privacy_personal_data_exporters',function($items){$items['hws-newsletter']=['exporter_friendly_name'=>'Newsletter signups','callback'=>[self::class,'privacy_export']];return $items;});
        add_filter('wp_privacy_personal_data_erasers',function($items){$items['hws-newsletter']=['eraser_friendly_name'=>'Newsletter signups','callback'=>[self::class,'privacy_erase']];return $items;});
    }
    public static function settings(): array {return array_replace(['enabled'=>false,'notification_email'=>get_option('admin_email'),'style'=>'none','success'=>self::SUCCESS,'duplicate'=>self::DUPLICATE,'error'=>''],(array)get_option(self::OPTION,[]));}
    public static function store(): SubmissionStore {return new SubmissionStore;}
    public static function init(): void {
        if(isset($_GET['hws_newsletter_result'])){if(!defined('DONOTCACHEPAGE'))define('DONOTCACHEPAGE',true);nocache_headers();do_action('litespeed_control_set_nocache','Newsletter result');}
        add_shortcode('hws_newsletter',[self::class,'render']);
        if((self::settings()['enabled']||get_option('hws_base_tools_newsletter_schema')==='1') && get_option('hws_base_tools_newsletter_schema')!=='2') {self::store()->install();update_option('hws_base_tools_newsletter_schema','2',false);}
        if(defined('WP_CLI')&&WP_CLI) {
            \WP_CLI::add_command('hws newsletter recent',function($args,$assoc){if(!current_user_can('manage_options'))\WP_CLI::error('Use --user with an administrator.');\WP_CLI::line(wp_json_encode(self::store()->recent($assoc['list']??'default',(int)($assoc['limit']??20))));});
            \WP_CLI::add_command('hws newsletter export',function($args,$assoc){if(!current_user_can('manage_options'))\WP_CLI::error('Use --user with an administrator.');self::store()->csv(STDOUT,$assoc['list']??'default');});
            \WP_CLI::add_command('hws newsletter disable',function(){if(!current_user_can('manage_options'))\WP_CLI::error('Use --user with an administrator.');update_option(self::OPTION,array_replace(self::settings(),['enabled'=>false]),false);do_action('litespeed_purge_all');\WP_CLI::success('Newsletter disabled; subscribers retained.');});
            \WP_CLI::add_command('hws newsletter enable',function(){if(!current_user_can('manage_options'))\WP_CLI::error('Use --user with an administrator.');self::store()->install();update_option(self::OPTION,array_replace(self::settings(),['enabled'=>true]),false);\WP_CLI::success('Newsletter enabled.');});
        }
    }
    public static function config(array $a): string { $json=wp_json_encode($a); return base64_encode($json).'.'.hash_hmac('sha256',$json,wp_salt('nonce')); }
    public static function decode(string $token): ?array {
        if(strlen($token)>60000)return null;
        $parts=explode('.',$token,2);$json=base64_decode($parts[0],true);
        if(!$json||!hash_equals(hash_hmac('sha256',$json,wp_salt('nonce')),$parts[1]??''))return null;
        $a=json_decode($json,true);
        if(!is_array($a))return null;
        foreach(['list','name','consent','source','success'] as $key)if(!isset($a[$key])||!is_string($a[$key]))return null;
        try{SubmissionStore::collection($a['list']);}catch(\InvalidArgumentException $e){return null;}
        if(!in_array($a['name'],['on','off'],true)||!in_array($a['consent'],['on','off'],true)||strlen($a['source'])>120||strlen($a['success'])>2000)return null;
        foreach(['duplicate','error'] as $key) {
            if(array_key_exists($key,$a)&&(!is_string($a[$key])||strlen($a[$key])>self::MESSAGE_LIMIT))return null;
            if(!isset($a[$key]))$a[$key]=self::settings()[$key];
        }
        return $a;
    }
    /** Only verified signed configuration may override a response. */
    private static function failure(string $code,int $status,?array $a=null,string $field_message=''): \WP_Error {
        $error=$a['error']??self::settings()['error'];
        return new \WP_Error($code,$error!==''?$error:($field_message!==''?$field_message:self::ERROR),['status'=>$status]);
    }
    public static function css(string $style): string {
        if(!in_array($style,['simple','rounded'],true))return '';
        $root='.hws-newsletter--'.$style;
        $css="$root .hws-newsletter__fields{display:flex;flex-wrap:wrap;gap:12px;align-items:end}$root .hws-newsletter__field{display:flex;flex:1 1 180px;flex-direction:column;gap:6px}$root .hws-newsletter__input{box-sizing:border-box;width:100%;padding:12px;border:1px solid #767676;background:#fff;color:#161616}$root .hws-newsletter__button{padding:12px 20px;border:1px solid #162638;background:#162638;color:#fff;cursor:pointer}$root .hws-newsletter__status{margin-top:12px}$root :focus-visible{outline:2px solid #2673b8;outline-offset:3px}";
        $css.="$root .hws-newsletter__status--success{color:#166534;background:#fff}$root .hws-newsletter__status--error{color:#b91c1c;background:#fff}";
        return $css.($style==='rounded'?"$root .hws-newsletter__input,$root .hws-newsletter__button{border-radius:24px}":'');
    }
    public static function render($atts=[],bool $preview=false): string {
        if(!$preview&&!self::settings()['enabled'])return '';
        $a=shortcode_atts(['list'=>'default','placeholder'=>'Your email address','button'=>'Subscribe','success'=>self::settings()['success'],'duplicate'=>self::settings()['duplicate'],'error'=>self::settings()['error'],'style'=>self::settings()['style'],'id'=>'','class'=>'','name'=>'off','consent'=>'off','consent_text'=>'I agree to receive newsletter emails.','source'=>'newsletter'],(array)$atts,'hws_newsletter');
        foreach(['success','duplicate','error'] as $key)if(!is_string($a[$key])||strlen($a[$key])>self::MESSAGE_LIMIT)return '';
        try {SubmissionStore::collection($a['list']);}catch(\InvalidArgumentException $e){return '';}
        $a['style']=in_array($a['style'],['none','simple','rounded'],true)?$a['style']:'none';
        $uid=$a['id']!==''?sanitize_html_class($a['id']):wp_unique_id('hws-newsletter-');
        $classes=implode(' ',array_filter(array_map('sanitize_html_class',explode(' ',$a['class']))));
        $html='';
        \Hexa\PluginCore\PublicComponents\InlineStyles::enqueue('hws-newsletter-'.$a['style'],self::css($a['style']));
        if(!$preview)wp_enqueue_script('hws-newsletter',plugins_url('assets/newsletter/newsletter.js',PluginMetadata::plugin_file()),[],PluginMetadata::VERSION,true);
        $page_result=$preview?null:self::page_result($a);
        $html.='<form'.($page_result?' data-state="'.esc_attr($page_result['state']==='success'?'created':$page_result['state']).'"':'').' id="'.esc_attr($uid).'" class="hws-newsletter hws-newsletter--'.esc_attr($a['style']).' '.esc_attr($classes).'" method="post" action="'.esc_url(self::page_url()).'" data-endpoint="'.esc_url(rest_url('hws-base-tools/v1/newsletter')).'" data-error="'.esc_attr($a['error']!==''?$a['error']:self::ERROR).'">';
        $html.='<input type="hidden" name="action" value="hws_base_tools_newsletter"><input type="hidden" name="config" value="'.esc_attr(self::config(array_intersect_key($a,array_flip(['list','name','consent','source','success','duplicate','error'])))).'"><input type="hidden" name="nonce" value="'.esc_attr(wp_create_nonce('hws_newsletter')).'"><input type="hidden" name="page_url" value="">';
        $html.='<div class="hws-newsletter__fields">';
        if($a['name']==='on')$html.='<label class="hws-newsletter__field"><span class="hws-newsletter__label">Name</span><input class="hws-newsletter__input" name="name" autocomplete="name" maxlength="200"></label>';
        $html.='<label class="hws-newsletter__field"><span class="hws-newsletter__label">Email</span><input class="hws-newsletter__input" type="email" name="email" autocomplete="email" required maxlength="254" placeholder="'.esc_attr($a['placeholder']).'"></label><button class="hws-newsletter__button" type="submit"'.($preview?' disabled':'').'>'.esc_html($a['button']).'</button></div>';
        $html.='<div class="hws-newsletter__honeypot" hidden aria-hidden="true"><label>Leave empty<input name="company_website" tabindex="-1" autocomplete="off"></label></div>';
        if($a['consent']==='on')$html.='<label class="hws-newsletter__consent"><input type="checkbox" name="consent" value="1" required> '.esc_html($a['consent_text']).'</label>';
        $s=Settings::get();
        if($s['enabled']&&$s['bot_check']==='turnstile'&&!$preview)$html.='<div class="hws-newsletter__turnstile" data-sitekey="'.esc_attr($s['site_key']).'"></div>';
        $result=$preview?null:self::page_result($a);
        return $html.'<div class="hws-newsletter__status'.($result?' hws-newsletter__status--'.esc_attr($result['state']):'').'" role="status" aria-live="polite">'.($result?esc_html($result['message']):'').'</div></form>';
    }
    /** Current front-end URL, never a visitor-supplied redirect destination. */
    public static function page_url(): string {
        $home=wp_parse_url(home_url('/'));
        $origin=$home['scheme'].'://'.$home['host'].(isset($home['port'])?':'.$home['port']:'');
        $uri=wp_unslash($_SERVER['REQUEST_URI']??'/');
        if(!is_string($uri)||!str_starts_with($uri,'/')||str_starts_with($uri,'//'))$uri='/';
        return remove_query_arg('hws_newsletter_result',wp_validate_redirect($origin.$uri,home_url('/')));
    }
    private static function form_key(array $a): string {
        $bound=[];foreach(['list','source','success','duplicate','error','consent','name'] as $key)$bound[$key]=$a[$key];
        return substr(hash('sha256',wp_json_encode($bound)),0,16);
    }
    public static function frontend_post(): void {
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||($_POST['action']??null)!=='hws_base_tools_newsletter')return;
        $p=wp_unslash($_POST);$a=self::decode(is_string($p['config']??null)?$p['config']:'');
        $r=self::submit($p,$_SERVER,false);
        $code=is_wp_error($r)?$r->get_error_code():$r->get_data()['status'];
        // Only a fixed result code, form fingerprint and expiry are placed in the URL. No subscriber data.
        $data=$code.'.'.time().'.'.($a?self::form_key($a):'invalid');
        $flag=$data.'.'.substr(hash_hmac('sha256',$data,wp_salt('nonce')),0,24);
        nocache_headers();wp_safe_redirect(add_query_arg('hws_newsletter_result',$flag,self::page_url()),303);exit;
    }
    public static function page_result(array $a): ?array {
        $flag=$_GET['hws_newsletter_result']??'';
        if(!is_string($flag)||strlen($flag)>150)return null;
        $parts=explode('.',$flag);if(count($parts)!==4)return null;
        [$code,$time,$key,$sig]=$parts;$data=$code.'.'.$time.'.'.$key;
        if(!ctype_digit($time)||(int)$time<time()-600||(int)$time>time()+30||!hash_equals(substr(hash_hmac('sha256',$data,wp_salt('nonce')),0,24),$sig))return null;
        if($key!==self::form_key($a)&&$key!=='invalid')return null;
        if(!in_array($code,['created','duplicate','disabled','rate_limit','nonce','config','spam','consent','email','turnstile','storage'],true))return null;
        nocache_headers();
        if($code==='created')return ['state'=>'success','message'=>$a['success']];
        if($code==='duplicate')return ['state'=>'duplicate','message'=>$a['duplicate']];
        $field=$code==='email'?'Enter a valid email address.':($code==='consent'?'Please agree to receive the newsletter.':'');
        return ['state'=>'error','message'=>self::failure($code,400,$a,$field)->get_error_message()];
    }
    public static function routes(): void {
        register_rest_route('hws-base-tools/v1','/newsletter/subscribers',[
            'methods'=>'GET','permission_callback'=>static function(){return current_user_can(PluginMetadata::ADMIN_CAPABILITY);},
            'callback'=>[self::class,'subscribers'],
            'args'=>[
                'after_id'=>['default'=>0,'validate_callback'=>static function($v){return is_scalar($v)&&ctype_digit((string)$v)&&strlen((string)$v)<19;}],
                'upper_id'=>['validate_callback'=>static function($v){return is_scalar($v)&&ctype_digit((string)$v)&&strlen((string)$v)<19;}],
                'per_page'=>['default'=>100,'validate_callback'=>static function($v){return is_scalar($v)&&ctype_digit((string)$v)&&(int)$v>=1&&(int)$v<=500;}],
                'list'=>['validate_callback'=>static function($v){return is_string($v)&&preg_match('/^[a-z0-9][a-z0-9_-]{0,79}$/D',$v);}],
            ]
        ]);
        $permission=static function(){return current_user_can(PluginMetadata::ADMIN_CAPABILITY);};
        register_rest_route('hws-base-tools/v1','/newsletter/lists',['methods'=>'GET','permission_callback'=>$permission,'callback'=>[self::class,'lists']]);
        register_rest_route('hws-base-tools/v1','/newsletter/deleted',[
            'methods'=>'GET','permission_callback'=>$permission,'callback'=>[self::class,'deleted'],
            'args'=>[
                'after_id'=>['default'=>0,'validate_callback'=>static function($v){return is_scalar($v)&&ctype_digit((string)$v)&&strlen((string)$v)<19;}],
                'per_page'=>['default'=>100,'validate_callback'=>static function($v){return is_scalar($v)&&ctype_digit((string)$v)&&(int)$v>=1&&(int)$v<=500;}]
            ]
        ]);
        // Generic submissions spelling; retain subscribers/per_page for existing readers.
        register_rest_route('hws-base-tools/v1','/newsletter/submissions',[
            'methods'=>'GET','permission_callback'=>$permission,'callback'=>static function($r){$r['per_page']=min(500,(int)$r['limit']);return self::subscribers($r);},
            'args'=>[
                'after_id'=>['default'=>0,'validate_callback'=>static function($v){return is_scalar($v)&&ctype_digit((string)$v)&&strlen((string)$v)<19;}],
                'limit'=>['default'=>100,'validate_callback'=>static function($v){return is_scalar($v)&&ctype_digit((string)$v)&&(int)$v>=1&&strlen((string)$v)<10;}],
                'list'=>['validate_callback'=>static function($v){return is_string($v)&&preg_match('/^[a-z0-9][a-z0-9_-]{0,79}$/D',$v);}],
                'upper_id'=>['validate_callback'=>static function($v){return is_scalar($v)&&ctype_digit((string)$v)&&strlen((string)$v)<19;}]
            ]
        ]);
        register_rest_route('hws-base-tools/v1','/newsletter',['methods'=>'POST','permission_callback'=>'__return_true','callback'=>function($r){return self::submit((array)$r->get_params(),$_SERVER);}]);
        register_rest_route('hws-base-tools/v1','/newsletter/nonce',['methods'=>'GET','permission_callback'=>'__return_true','callback'=>function(){if(!self::settings()['enabled'])return self::failure('disabled',403);$r=new \WP_REST_Response(['nonce'=>wp_create_nonce('hws_newsletter')]);$r->header('Cache-Control','no-store, private');return $r;}]);
    }
    public static function lists() {
        try{$data=in_array(get_option('hws_base_tools_newsletter_schema'),['1','2'],true)?self::store()->lists():[];}
        catch(\Throwable $e){return new \WP_Error('storage','Lists could not be read.',['status'=>503]);}
        $r=new \WP_REST_Response(['items'=>$data]);$r->header('Cache-Control','no-store, private');return $r;
    }
    public static function deleted($request) {
        try{$data=get_option('hws_base_tools_newsletter_schema')==='2'?self::store()->deleted((int)$request['after_id'],(int)$request['per_page']):['items'=>[],'next_after_id'=>(int)$request['after_id'],'has_more'=>false];}
        catch(\Throwable $e){return new \WP_Error('storage','Deleted submissions could not be read.',['status'=>503]);}
        foreach($data['items'] as &$row){$row['id']=(int)$row['id'];$row['deletion_id']=(int)$row['deletion_id'];$row['list']=$row['collection'];$row['deleted_at_utc']=$row['deleted_at'];unset($row['collection'],$row['deleted_at']);}unset($row);
        $r=new \WP_REST_Response($data);$r->header('Cache-Control','no-store, private');return $r;
    }
    public static function subscribers($request) {
        if(!in_array(get_option('hws_base_tools_newsletter_schema'),['1','2'],true))$data=['items'=>[],'upper_id'=>0,'next_after_id'=>0,'has_more'=>false];
        else {
            try{$data=self::store()->scan((int)$request['after_id'],(int)$request['per_page'],$request['list'],isset($request['upper_id'])?(int)$request['upper_id']:null);}
            catch(\Throwable $e){return new \WP_Error('storage','Subscribers could not be read.',['status'=>503]);}
            foreach($data['items'] as &$row){$row['id']=(int)$row['id'];$row['list']=$row['collection'];$row['created_at_utc']=$row['created_at'];unset($row['collection']);$row['consent']=(bool)$row['consent'];$row['headers']=json_decode($row['headers'],true);}
            unset($row);
        }
        $response=new \WP_REST_Response($data);$response->header('Cache-Control','no-store, private');return $response;
    }
    public static function submit(array $p,array $server,bool $require_nonce=true) {
        $a=self::decode(is_string($p['config']??null)?$p['config']:'');
        if(!self::settings()['enabled'])return self::failure('disabled',403,$a);
        if(!self::store()->allow(SubmissionStore::client_ip($server)))return self::failure('rate_limit',429,$a);
        if($require_nonce&&!wp_verify_nonce(is_string($p['nonce']??null)?$p['nonce']:'','hws_newsletter'))return self::failure('nonce',403,$a);
        if(!$a)return self::failure('config',400);
        if(!empty($p['company_website']))return self::failure('spam',400,$a);
        if($a['consent']==='on'&&($p['consent']??'')!=='1')return self::failure('consent',400,$a,'Please agree to receive the newsletter.');
        $email=SubmissionStore::email((string)($p['email']??''));
        if(strlen($email)>254||!is_email($email))return self::failure('email',400,$a,'Enter a valid email address.');
        $s=Settings::get();
        if($s['enabled']&&$s['bot_check']==='turnstile') {
            $bot=new BotCheck(static function($url,$body){$r=wp_safe_remote_post($url,['timeout'=>10,'body'=>$body]);return is_wp_error($r)?null:json_decode(wp_remote_retrieve_body($r),true);});
            if($bot->reason('turnstile',(string)($p['cf-turnstile-response']??''),$s,(string)parse_url(home_url(),PHP_URL_HOST),SubmissionStore::client_ip($server)))return self::failure('turnstile',400,$a);
        }
        $result=self::store()->add($a['list'],$email,['server'=>$server,'page_url'=>(string)(!empty($p['page_url'])?$p['page_url']:($server['HTTP_REFERER']??'')),'name'=>$a['name']==='on'?($p['name']??''):'','source'=>$a['source'],'consent'=>($p['consent']??'')==='1']);
        if($result['status']==='error')return self::failure('storage',503,$a);
        if($result['status']==='created') {
            $to=self::settings()['notification_email'];
            if($to&&is_email($to)) {
                $metadata=SubmissionStore::headers($server,(string)(!empty($p['page_url'])?$p['page_url']:($server['HTTP_REFERER']??'')));
                $host=preg_replace('/^www\./i','',(string)parse_url(home_url(),PHP_URL_HOST));
                $name=self::header_text($a['name']==='on'?(string)($p['name']??''):'');
                $site=self::header_text((string)get_bloginfo('name'))?:$host;
                $subject='Newsletter: '.($name!==''?$name.' ':'').'<'.$email.'> - '.$host;
                $headers=['From: '.$site.' <no-reply@'.$host.'>','Reply-To: '.($name!==''?$name.' ':'').'<'.$email.'>'];
                $body='List: '.$a['list']."\nName: ".$name."\nEmail: ".$email."\nTime: ".SubmissionStore::est(gmdate('Y-m-d H:i:s'))
                    ."\nIP: ".SubmissionStore::client_ip($server)."\nPage URL: ".$metadata['page_url']
                    ."\nSource: ".$a['source']."\nManage signups: ".admin_url('admin.php?page=hws-core-tools&tab=newsletter&list='.rawurlencode($a['list']));
                $sent=wp_mail($to,$subject,$body,$headers);
                do_action('hws_base_tools_newsletter_notification',$sent,$result['id']);
            }
            do_action('hws_base_tools_newsletter_signup',$result['id'],$a['list']);
        }
        return new \WP_REST_Response(['status'=>$result['status'],'message'=>$result['status']==='duplicate'?$a['duplicate']:$a['success']],$result['status']==='created'?201:200);
    }
    /** One header-safe line: no line breaks, angle brackets, quotes or commas. */
    private static function header_text(string $text): string {return trim(mb_substr(preg_replace('/[\x00-\x1F\x7F<>"(),;:\\\\]+/u',' ',$text)??'',0,120));}
    public static function post(): void {$r=self::submit(wp_unslash($_POST),$_SERVER,false);wp_die(esc_html(is_wp_error($r)?$r->get_error_message():$r->get_data()['message']),'Newsletter signup',['response'=>is_wp_error($r)?($r->get_error_data()['status']??400):200,'back_link'=>true]);}
    private static function guard(string $action): void {if(!current_user_can('manage_options'))wp_die('Administrator access required.',403);check_admin_referer($action);}
    public static function save(): void {
        self::guard('hws_newsletter_save'); $email=trim((string)wp_unslash($_POST['notification_email']??''));
        if($email!==''&&!is_email($email))wp_die('Enter a valid notification email, or leave blank.',400);
        $style=sanitize_key($_POST['style']??'none');
        $messages=[];
        foreach(['success'=>self::SUCCESS,'duplicate'=>self::DUPLICATE,'error'=>''] as $key=>$default) {
            $value=wp_unslash($_POST[$key]??$default);
            if(!is_string($value)||strlen($value)>self::MESSAGE_LIMIT)wp_die('Messages must be text of at most 2000 bytes.',400);
            $messages[$key]=$value;
        }
        update_option(self::OPTION,array_merge(['enabled'=>isset($_POST['enabled']),'notification_email'=>$email,'style'=>in_array($style,['none','simple','rounded'],true)?$style:'none'],$messages),false);
        do_action('litespeed_purge_all');wp_safe_redirect(admin_url('admin.php?page=hws-core-tools&tab=newsletter'));exit;
    }
    public static function delete(): void {
        $list=is_string($_POST['list']??null)?$_POST['list']:'';
        $id=absint($_POST['subscriber_id']??0);
        self::guard('hws_newsletter_delete:'.$list.':'.$id);
        try{$deleted=self::store()->delete($list,$id);}catch(\InvalidArgumentException $e){wp_die('Invalid list.',400);}
        if(!$deleted)wp_die('Subscriber not found in this list.',404);
        wp_safe_redirect(admin_url('admin.php?page=hws-core-tools&tab=newsletter&list='.rawurlencode($list)));exit;
    }
    public static function export(): void {
        self::guard('hws_newsletter_export');$list=sanitize_key($_GET['list']??'default');
        try{SubmissionStore::collection($list);}catch(\Throwable $e){wp_die('Invalid list.',400);}
        nocache_headers();header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="newsletter-'.$list.'.csv"');$f=fopen('php://output','w');self::store()->csv($f,$list);fclose($f);exit;
    }
    public static function admin(): void {
        if(!current_user_can('manage_options'))return;
        $s=self::settings();$list=sanitize_key($_GET['list']??'default');try{SubmissionStore::collection($list);}catch(\Throwable $e){$list='default';}
        $ready=in_array(get_option('hws_base_tools_newsletter_schema'),['1','2'],true);
        $lists=[];if($ready){try{$lists=self::store()->lists();}catch(\Throwable $e){$lists=[];}}
        $all=array_sum(array_column($lists,'total'));
        $base=admin_url('admin.php?page=hws-core-tools&tab=newsletter');
        CoreUi::render_assets();
        echo '<div class="hpc-ui hws-nl-tab"><style>.hws-nl-tab{max-width:960px}.hws-nl-tab .hpc-card{margin:0 0 18px}.hws-nl-tab .hws-nl-field{margin:0 0 18px}.hws-nl-tab .hws-nl-field:last-child{margin-bottom:0}.hws-nl-tab .hws-nl-field label{display:block;font-weight:600;margin:0 0 6px}.hws-nl-tab .hws-nl-help{color:#50575e;margin:6px 0 0;font-size:13px}.hws-nl-tab .hws-nl-field input[type=email]{width:100%;max-width:420px}.hws-nl-tab .hws-nl-stats{display:flex;flex-wrap:wrap;gap:12px;margin:12px 0 16px}.hws-nl-tab .hws-nl-stat{flex:1 1 140px;border:1px solid #dcdcde;border-radius:8px;padding:12px 14px;background:#f6f7f7}.hws-nl-tab .hws-nl-stat b{display:block;font-size:22px;line-height:1.2}.hws-nl-tab .hws-nl-stat span{color:#50575e;font-size:12px;text-transform:uppercase;letter-spacing:.04em}.hws-nl-tab .hws-nl-lists{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 12px}.hws-nl-tab .hws-nl-lists a{display:inline-flex;gap:8px;align-items:center;border:1px solid #c3c4c7;border-radius:999px;padding:6px 14px;text-decoration:none;color:#1d2327;background:#fff}.hws-nl-tab .hws-nl-lists a.is-current{background:#1d2327;border-color:#1d2327;color:#fff}.hws-nl-tab .hws-nl-lists em{font-style:normal;opacity:.7}.hws-nl-tab table.widefat td{vertical-align:middle}.hws-nl-tab .hws-nl-bar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin:0 0 12px}.hws-nl-tab .hws-nl-empty{padding:24px;text-align:center;color:#50575e;border:1px dashed #c3c4c7;border-radius:8px}.hws-nl-tab .hws-nl-save{position:sticky;bottom:0;z-index:5;background:#fff;border-top:1px solid #dcdcde;padding:12px 0;margin:0 0 18px}</style>';
        echo CoreUi::card(['title'=>'Newsletter','body_html'=>'<p>Collects email signups from the newsletter form and stores them on this site. It sends no marketing emails. Privacy requests go through WordPress Tools &rsaquo; Export Personal Data and Erase Personal Data.</p><div class="hws-nl-stats"><div class="hws-nl-stat"><b>'.($s['enabled']?'On':'Off').'</b><span>Collector</span></div><div class="hws-nl-stat"><b>'.(int)$all.'</b><span>Signups</span></div><div class="hws-nl-stat"><b>'.count($lists).'</b><span>Lists</span></div><div class="hws-nl-stat"><b>'.esc_html($s['notification_email']!==''?'On':'Off').'</b><span>Email notices</span></div></div>']);
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="hws_base_tools_newsletter_save">';
        wp_nonce_field('hws_newsletter_save');
        echo CoreUi::card(['title'=>'Collector','body_html'=>CoreUi::toggle('enabled',(bool)$s['enabled'],'Collect newsletter signups on this site',['id'=>'hws-nl-enabled']).'<p class="hws-nl-help">While off, every newsletter form on the site is hidden and nothing is collected. Stored signups are kept.</p>']);
        echo CoreUi::card(['title'=>'Signup notice email','body_html'=>'<div class="hws-nl-field"><label for="hws-nl-notify">Send each new signup to</label><input type="email" id="hws-nl-notify" name="notification_email" class="regular-text" value="'.esc_attr($s['notification_email']).'" placeholder="'.esc_attr(get_option('admin_email')).'"><p class="hws-nl-help">Leave blank to send no notices. Each notice comes from no-reply at this domain, and replying goes straight to the subscriber.</p></div>']);
        $templates=[];
        foreach(['none'=>['No plugin styling','Plain HTML that takes the theme\'s own look.'],'simple'=>['Simple','Square fields and a dark button.'],'rounded'=>['Rounded','Pill-shaped fields and button.']] as $key=>$l)$templates[$key]=['label'=>$l[0],'description'=>$l[1],'preview_html'=>'<style>'.self::css($key).'</style>'.self::render(['style'=>$key,'id'=>'hws-nl-sample-'.$key],true)];
        $css=CoreUi::inline_details('Show the CSS for the selected design',\Hexa\PluginCore\WpAdminComponents\CodeDisplay::render(self::css($s['style'])?:'/* No plugin CSS: the theme styles the form. */',['language'=>'css','title'=>'Newsletter CSS']));
        echo CoreUi::card(['title'=>'Design','body_html'=>TemplateSelectionControl::render(['id'=>'hws-nl-style','name'=>'style','value'=>(string)$s['style'],'title'=>'Form design','description'=>'Each sample is the real form with the same CSS the site uses. A shortcode can still pick its own with style="...".','templates'=>$templates,'columns'=>1,'preview_height'=>140]).$css]);
        $msgs='';
        foreach(['success'=>['After a signup',''],'duplicate'=>['When the email is already on the list',''],'error'=>['When the signup fails','Leave blank to use the default message and the specific email or consent hint. A custom message replaces those hints.']] as $key=>$l)$msgs.='<div class="hws-nl-field"><label for="hws-nl-'.$key.'">'.esc_html($l[0]).'</label><textarea id="hws-nl-'.$key.'" name="'.$key.'" rows="2" class="large-text" maxlength="2000" placeholder="'.esc_attr($key==='error'?self::ERROR:'').'">'.esc_textarea($s[$key]).'</textarea>'.($l[1]!==''?'<p class="hws-nl-help">'.esc_html($l[1]).'</p>':'').'</div>';
        echo CoreUi::card(['title'=>'Messages visitors see','body_html'=>$msgs.'<p class="hws-nl-help">Plain text, up to 2000 characters each.</p>']);
        echo '<div class="hws-nl-save"><button class="hpc-button button button-primary">Save settings</button></div></form>';
        $chips='';
        foreach($lists?:[['list'=>'default','total'=>0]] as $row)$chips.='<a class="'.($row['list']===$list?'is-current':'').'" href="'.esc_url($base.'&list='.rawurlencode($row['list'])).'">'.esc_html($row['list']).' <em>'.(int)$row['total'].'</em></a>';
        $body='<div class="hws-nl-lists">'.$chips.'</div>';
        if(!$ready){echo CoreUi::card(['title'=>'Subscribers','body_html'=>'<div class="hws-nl-empty">Turn the collector on to start storing signups.</div>']);echo '</div>';return;}
        $before=absint($_GET['before']??0);$rows=self::store()->recent($list,21,$before);$has_older=count($rows)>20;$rows=array_slice($rows,0,20);
        $body.='<div class="hws-nl-bar"><strong>'.esc_html($list).': '.self::store()->total($list).' subscribers</strong><a class="hpc-button secondary button" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=hws_base_tools_newsletter_export&list='.$list),'hws_newsletter_export')).'">Export CSV</a></div>';
        if(!$rows)$body.='<div class="hws-nl-empty">No signups on this list yet.</div>';
        else {
            $body.='<table class="widefat striped"><thead><tr><th>Email</th><th>Name</th><th>Signed up (EST)</th><th>IP</th><th>Source</th><th></th></tr></thead><tbody>';
            foreach($rows as $r)$body.='<tr><td><strong>'.esc_html($r['email']).'</strong></td><td>'.esc_html($r['name']).'</td><td>'.esc_html(SubmissionStore::est($r['created_at'])).'</td><td>'.esc_html($r['ip']).'</td><td>'.esc_html($r['source']).'</td><td><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" onsubmit="return confirm(\'Delete this subscriber?\')"><input type="hidden" name="action" value="hws_base_tools_newsletter_delete"><input type="hidden" name="list" value="'.esc_attr($list).'"><input type="hidden" name="subscriber_id" value="'.(int)$r['id'].'">'.wp_nonce_field('hws_newsletter_delete:'.$list.':'.$r['id'],'_wpnonce',false,false).'<button class="button-link button-link-delete" type="submit">Delete</button></form></td></tr>';
            $body.='</tbody></table>';
        }
        $nav=($before?'<a href="'.esc_url($base.'&list='.rawurlencode($list)).'">&larr; Newest</a>':'').($has_older?' <a class="hws-newsletter-older" href="'.esc_url($base.'&list='.rawurlencode($list).'&before='.end($rows)['id']).'">Older &rarr;</a>':'');
        if($nav!=='')$body.='<p>'.$nav.'</p>';
        echo CoreUi::card(['title'=>'Subscribers','body_html'=>$body]);
        echo CoreUi::card(['title'=>'Add the form to a page','body_html'=>'<p>Paste this into a page, or an Elementor Shortcode widget. Every option is in the <a href="https://github.com/mikeyperes/hws-base-tools/blob/main/docs/newsletter.md" target="_blank" rel="noopener">newsletter guide</a>.</p><div class="hws-nl-bar"><code>[hws_newsletter list="'.esc_html($list).'"]</code>'.CoreUi::copy_button('[hws_newsletter list="'.$list.'"]').'</div>']);
        echo '</div>';
    }
    public static function privacy_export(string $email,int $page=1): array {
        if(!in_array(get_option('hws_base_tools_newsletter_schema'),['1','2'],true))return ['data'=>[],'done'=>true];
        $rows=self::store()->personal_data($email,$page);$data=[];
        foreach($rows as $r){$fields=[];foreach($r as $name=>$value)$fields[]=['name'=>$name,'value'=>$value];$data[]=['group_id'=>'hws-newsletter','group_label'=>'Newsletter signups','item_id'=>'hws-newsletter-'.$r['id'],'data'=>$fields];}
        return ['data'=>$data,'done'=>count($rows)<100];
    }
    public static function privacy_erase(string $email,int $page=1): array {
        $ok=!in_array(get_option('hws_base_tools_newsletter_schema'),['1','2'],true)||self::store()->erase($email);
        return ['items_removed'=>$ok,'items_retained'=>!$ok,'messages'=>$ok?[]:['Could not erase newsletter data.'],'done'=>$ok];
    }
}
