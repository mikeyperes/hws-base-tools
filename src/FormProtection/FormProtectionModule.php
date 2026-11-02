<?php
declare(strict_types=1);
namespace HWS\BaseTools\FormProtection;
use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\WpAdminAjax\{AjaxActionRegistry,AjaxRequest};
use HWS\BaseTools\PluginRuntime\PluginMetadata;
final class FormProtectionModule implements ModuleInterface {
    private static array $failed=[];
    public function register():void {
        add_filter('elementor/widget/render_content',[self::class,'elementor_markup'],10,2);
        add_action('elementor_pro/forms/validation',[self::class,'elementor_validate'],10,2);
        add_filter('wpcf7_form_elements',[self::class,'cf7_markup']);
        add_filter('wpcf7_spam',[self::class,'cf7_spam'],10,2);
        add_filter('gform_form_tag',[self::class,'gravity_markup'],10,2);
        add_filter('gform_validation',[self::class,'gravity_validate']);
        add_filter('gform_validation_message',[self::class,'gravity_message'],10,2);
        add_action('wpforms_display_submit_before',[self::class,'wpforms_markup']);
        add_filter('wpforms_process_initial_errors',[self::class,'wpforms_validate'],10,2);
        add_action('rest_api_init',[self::class,'routes']);
        add_action('hws_base_tools_form_protection_prune',[AttemptLog::class,'prune']);
        add_filter('litespeed_optimize_js_excludes',[self::class,'optimization_exclusions']);
        add_filter('litespeed_optm_js_defer_exc',[self::class,'optimization_exclusions']);
        add_action('hws_base_tools_form_protection_settings_saved',[self::class,'settings_saved']);
        AjaxActionRegistry::create(['action_prefix'=>'hws_base_tools','nonce_action'=>'hws_base_tools_form_protection','nonce_field'=>'nonce'])->register(['form_protection_save'=>['callback'=>[self::class,'save']]]);
        if(defined('WP_CLI')&&WP_CLI){
            \WP_CLI::add_command('hws forms signals',function($args,$assoc){\WP_CLI::line(wp_json_encode(AttemptLog::read((int)($assoc['limit']??100),true)));});
            \WP_CLI::add_command('hws forms blocked',function($args,$assoc){\WP_CLI::line(wp_json_encode(AttemptLog::read((int)($assoc['limit']??100))));});
            \WP_CLI::add_command('hws forms protection',function($args,$assoc){
                $action=$args[0]??'status';
                if($action==='status'){\WP_CLI::line(wp_json_encode(Settings::state()));return;}
                if($action!=='set')\WP_CLI::error('Use status or set.');
                if(!current_user_can('manage_options'))\WP_CLI::error('Use --user with an administrator for setting changes.');
                if(array_intersect(['secret_key','recaptcha_secret_key','secret','recaptcha_secret'],array_keys($assoc)))\WP_CLI::error('Save the secret through the protected settings page, never in command arguments.');
                $changes=array_intersect_key($assoc,array_flip(Settings::PUBLIC_KEYS));$s=Settings::merge($changes);
                try{\WP_CLI::line(wp_json_encode(Settings::save($s)));}catch(\Throwable $e){\WP_CLI::error($e->getMessage());}
            });
        }
    }
    public static function message():string {return 'Spam protection could not validate this submission. Reload the page and try again.';}
    private static function challenge():Challenge {return new Challenge(wp_salt('nonce'));}
    public static function ip():string {
        $ip=(string)apply_filters('hws_base_tools_form_protection_client_ip',$_SERVER['REMOTE_ADDR']??'');
        return filter_var($ip,FILTER_VALIDATE_IP)?$ip:'';
    }
    private static function blocked(string $form):bool {
        $settings=Settings::get();if(!$settings['enabled'])return false;
        $post=wp_unslash($_POST);
        $host=strtolower((string)parse_url(home_url('/'),PHP_URL_HOST));
        $bot=new BotCheck(static function(string $url,array $body):?array{
            $r=wp_safe_remote_post($url,['timeout'=>10,'body'=>$body]);
            if(is_wp_error($r)||wp_remote_retrieve_response_code($r)!==200)return null;
            $data=json_decode(wp_remote_retrieve_body($r),true);return is_array($data)?$data:null;
        });
        $protection=new Protection(self::challenge(),static fn($provider,$token,$s)=>$bot->reason($provider,$token,$s,$host,self::ip()));
        if($settings['header_check_enabled'])foreach(HeaderCheck::signals($_SERVER)as$signal)AttemptLog::record($form,self::ip(),$signal);
        $reason=$protection->reason($post,$form,$settings,time(),$_SERVER,$host);
        if($reason===null)return false;
        self::$failed[$form]=$reason;AttemptLog::record($form,self::ip(),$reason);return true;
    }
    public static function markup(string $form):string {
        $s=Settings::state();if(!$s['enabled'])return '';
        $hasJs=$s['too_fast_enabled']||$s['bot_check']!=='none';
        if($hasJs)self::assets();
        $html='<div data-hws-form-protection="'.esc_attr($form).'" data-bot-check="'.esc_attr($s['bot_check']).'" data-sitekey="'.esc_attr($s[$s['bot_check']==='recaptcha'?'recaptcha_site_key':'site_key']).'">';
        if($s['honeypots_enabled']){
            $html.='<div hidden aria-hidden="true">';
            foreach(Honeypots::names($form)as$name)$html.='<label>Leave this field empty<input type="text" name="hws_form_honeypots['.esc_attr($name).']" value="" autocomplete="off" tabindex="-1"></label>';
            $html.='</div>';
        }
        if($s['too_fast_enabled'])$html.='<input type="hidden" name="hws_form_token" value="">';
        if($s['bot_check']==='turnstile')$html.='<div data-hws-turnstile></div>';
        if($s['bot_check']==='recaptcha')$html.='<input type="hidden" name="g-recaptcha-response" value="">';
        return $html.'</div>';
    }
    public static function assets():void {
        wp_enqueue_script('hws-form-protection',plugins_url('assets/form-protection/protection.js',PluginMetadata::plugin_file()),[],PluginMetadata::VERSION,true);
        wp_localize_script('hws-form-protection','hwsFormProtection',['challenge_url'=>rest_url('hws-base-tools/v1/form-protection/challenge')]);
        // Provider SDKs are loaded by this script only for the selected provider on an actual form.
    }
    public static function elementor_markup(string $content,$widget):string {
        if(!Settings::get()['enabled']||$widget->get_name()!=='form')return $content;
        // Native hidden post_id is authoritative for templates as well as ordinary pages.
        if(!preg_match('/name=["\\\']post_id["\\\'][^>]*value=["\\\'](\\d+)/',$content,$m))return $content;
        return preg_replace('/<\\/form>/i',self::markup('elementor:'.$m[1].':'.$widget->get_id()).'</form>',$content,1);
    }
    public static function elementor_validate($record,$handler):void {
        $form='elementor:'.absint($_POST['post_id']??0).':'.sanitize_key((string)($_POST['form_id']??''));
        if(self::blocked($form))$handler->add_error('hws_form_protection',self::message());
    }
    public static function cf7_markup(string $html):string {
        $form=\WPCF7_ContactForm::get_current();
        return $form?$html.self::markup('contact-form-7:'.$form->id()):$html;
    }
    public static function cf7_spam(bool $spam,$submission):bool {
        if($spam)return true;$form=$submission->get_contact_form();
        if(!$form||!self::blocked('contact-form-7:'.$form->id()))return false;
        if(method_exists($submission,'add_spam_log'))$submission->add_spam_log(['agent'=>'hws-base-tools','reason'=>self::message()]);
        return true;
    }
    public static function gravity_markup(string $tag,array $form):string {return $tag.self::markup('gravityforms:'.$form['id']);}
    public static function gravity_validate(array $result):array {
        if(self::blocked('gravityforms:'.$result['form']['id']))$result['is_valid']=false;
        return $result;
    }
    public static function gravity_message(string $message,array $form):string {
        return isset(self::$failed['gravityforms:'.$form['id']])?'<div class="validation_error">'.esc_html(self::message()).'</div>':$message;
    }
    public static function wpforms_markup(array $form):void {echo self::markup('wpforms:'.$form['id']);}
    public static function wpforms_validate(array $errors,array $form):array {
        if(self::blocked('wpforms:'.$form['id']))$errors[$form['id']]['header']=self::message();
        return $errors;
    }
    public static function optimization_exclusions(array $paths):array {
        if(!Settings::get()['enabled'])return $paths;
        return array_values(array_unique(array_merge($paths,['assets/form-protection/protection.js','challenges.cloudflare.com/turnstile','google.com/recaptcha','gstatic.com/recaptcha'])));
    }
    public static function routes():void {
        register_rest_route('hws-base-tools/v1','/form-protection/challenge',['methods'=>'GET','permission_callback'=>'__return_true','callback'=>function($request){
            $form=(string)$request->get_param('form_key');
            if(!Settings::get()['enabled']||!Settings::get()['too_fast_enabled'])return new \WP_Error('protection_off','Form protection is off.',['status'=>404]);
            if(!preg_match('/^(?:elementor:\\d+:[a-zA-Z0-9_-]+|(?:contact-form-7|gravityforms|wpforms):\\d+)$/D',$form))return new \WP_Error('invalid_form','Unknown form key.',['status'=>400]);
            $r=new \WP_REST_Response(['token'=>self::challenge()->issue($form,time())]);
            $r->header('Cache-Control','no-store, private, max-age=0');return $r;
        }]);
        register_rest_route('hws-base-tools/v1','/form-protection/blocked',['methods'=>'GET','permission_callback'=>fn()=>current_user_can('manage_options'),'callback'=>fn($r)=>new \WP_REST_Response(AttemptLog::read((int)($r->get_param('limit')??100)))]);
    }
    public static function save(AjaxRequest $request):array {
        if(!current_user_can('manage_options'))throw new \RuntimeException('Administrator permission required.');
        $changes=[];
        foreach(array_merge(Settings::PUBLIC_KEYS,['secret_key','recaptcha_secret_key'])as$key){$value=$request->raw($key,null);if($value!==null)$changes[$key]=$value;}
        $s=Settings::merge($changes);
        return ['success'=>true,'message'=>'Form protection settings saved.','settings'=>Settings::save($s)];
    }
    public static function settings_saved(array $state):void {
        $log=new \Hexa\PluginCore\ActivityLog\ActivityLogger(new \Hexa\PluginCore\ActivityLog\ActivityLogConfig(['storage'=>'permanent','storage_key'=>'hws_base_tools_form_protection_activity','max_entries'=>50]));
        $safe=$state;unset($safe['site_key'],$safe['recaptcha_site_key']);$log->add(new \Hexa\PluginCore\ActivityLog\ActivityLogEntry('Form protection settings saved',$safe));
        \Hexa\PluginCore\WpCronTasks\WpCronTask::schedule_existing('hws_base_tools_form_protection_prune','daily',null,[],false);
        AttemptLog::prune();
    }
}
