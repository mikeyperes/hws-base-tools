<?php
namespace HWS\BaseTools\Newsletter;
use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\FrontendForms\SubmissionStore;
use HWS\BaseTools\FormProtection\{Settings,BotCheck};
use HWS\BaseTools\PluginRuntime\PluginMetadata;

final class NewsletterModule implements ModuleInterface {
    public const OPTION='hws_base_tools_newsletter';

    public function register(): void {
        add_action('init',[self::class,'init']);
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
    public static function settings(): array {return array_replace(['enabled'=>false,'notification_email'=>get_option('admin_email'),'style'=>'none'],(array)get_option(self::OPTION,[]));}
    public static function store(): SubmissionStore {return new SubmissionStore;}
    public static function init(): void {
        add_shortcode('hws_newsletter',[self::class,'render']);
        if(self::settings()['enabled'] && get_option('hws_base_tools_newsletter_schema')!=='1') {self::store()->install();update_option('hws_base_tools_newsletter_schema','1',false);}
        if(defined('WP_CLI')&&WP_CLI) {
            \WP_CLI::add_command('hws newsletter recent',function($args,$assoc){if(!current_user_can('manage_options'))\WP_CLI::error('Use --user with an administrator.');\WP_CLI::line(wp_json_encode(self::store()->recent($assoc['list']??'default',(int)($assoc['limit']??20))));});
            \WP_CLI::add_command('hws newsletter export',function($args,$assoc){if(!current_user_can('manage_options'))\WP_CLI::error('Use --user with an administrator.');self::store()->csv(STDOUT,$assoc['list']??'default');});
            \WP_CLI::add_command('hws newsletter disable',function(){if(!current_user_can('manage_options'))\WP_CLI::error('Use --user with an administrator.');update_option(self::OPTION,array_replace(self::settings(),['enabled'=>false]),false);do_action('litespeed_purge_all');\WP_CLI::success('Newsletter disabled; subscribers retained.');});
            \WP_CLI::add_command('hws newsletter enable',function(){if(!current_user_can('manage_options'))\WP_CLI::error('Use --user with an administrator.');self::store()->install();update_option(self::OPTION,array_replace(self::settings(),['enabled'=>true]),false);\WP_CLI::success('Newsletter enabled.');});
        }
    }
    public static function config(array $a): string { $json=wp_json_encode($a); return base64_encode($json).'.'.hash_hmac('sha256',$json,wp_salt('nonce')); }
    public static function decode(string $token): ?array {
        if(strlen($token)>10000)return null;
        $parts=explode('.',$token,2);$json=base64_decode($parts[0],true);
        if(!$json||!hash_equals(hash_hmac('sha256',$json,wp_salt('nonce')),$parts[1]??''))return null;
        $a=json_decode($json,true);
        if(!is_array($a))return null;
        foreach(['list','name','consent','source','success'] as $key)if(!isset($a[$key])||!is_string($a[$key]))return null;
        try{SubmissionStore::collection($a['list']);}catch(\InvalidArgumentException $e){return null;}
        if(!in_array($a['name'],['on','off'],true)||!in_array($a['consent'],['on','off'],true)||strlen($a['source'])>120||strlen($a['success'])>2000)return null;
        return $a;
    }
    public static function css(string $style): string {
        if(!in_array($style,['simple','rounded'],true))return '';
        $root='.hws-newsletter--'.$style;
        $css="$root .hws-newsletter__fields{display:flex;flex-wrap:wrap;gap:12px;align-items:end}$root .hws-newsletter__field{display:flex;flex:1 1 180px;flex-direction:column;gap:6px}$root .hws-newsletter__input{box-sizing:border-box;width:100%;padding:12px;border:1px solid #767676;background:#fff;color:#161616}$root .hws-newsletter__button{padding:12px 20px;border:1px solid #162638;background:#162638;color:#fff;cursor:pointer}$root .hws-newsletter__status{margin-top:12px}$root :focus-visible{outline:2px solid #2673b8;outline-offset:3px}";
        return $css.($style==='rounded'?"$root .hws-newsletter__input,$root .hws-newsletter__button{border-radius:24px}":'');
    }
    public static function render($atts=[],bool $preview=false): string {
        if(!$preview&&!self::settings()['enabled'])return '';
        $a=shortcode_atts(['list'=>'default','placeholder'=>'Your email address','button'=>'Subscribe','success'=>'Thanks for signing up!','style'=>self::settings()['style'],'id'=>'','class'=>'','name'=>'off','consent'=>'off','consent_text'=>'I agree to receive newsletter emails.','source'=>'newsletter'],(array)$atts,'hws_newsletter');
        try {SubmissionStore::collection($a['list']);}catch(\InvalidArgumentException $e){return '';}
        $a['style']=in_array($a['style'],['none','simple','rounded'],true)?$a['style']:'none';
        $uid=$a['id']!==''?sanitize_html_class($a['id']):wp_unique_id('hws-newsletter-');
        $classes=implode(' ',array_filter(array_map('sanitize_html_class',explode(' ',$a['class']))));
        $html='';
        \Hexa\PluginCore\PublicComponents\InlineStyles::enqueue('hws-newsletter-'.$a['style'],self::css($a['style']));
        if(!$preview)wp_enqueue_script('hws-newsletter',plugins_url('assets/newsletter/newsletter.js',PluginMetadata::plugin_file()),[],PluginMetadata::VERSION,true);
        $html.='<form id="'.esc_attr($uid).'" class="hws-newsletter hws-newsletter--'.esc_attr($a['style']).' '.esc_attr($classes).'" method="post" action="'.esc_url(admin_url('admin-post.php')).'" data-endpoint="'.esc_url(rest_url('hws-base-tools/v1/newsletter')).'">';
        $html.='<input type="hidden" name="action" value="hws_base_tools_newsletter"><input type="hidden" name="config" value="'.esc_attr(self::config($a)).'"><input type="hidden" name="nonce" value="'.esc_attr(wp_create_nonce('hws_newsletter')).'"><input type="hidden" name="page_url" value="">';
        $html.='<div class="hws-newsletter__fields">';
        if($a['name']==='on')$html.='<label class="hws-newsletter__field"><span class="hws-newsletter__label">Name</span><input class="hws-newsletter__input" name="name" autocomplete="name" maxlength="200"></label>';
        $html.='<label class="hws-newsletter__field"><span class="hws-newsletter__label">Email</span><input class="hws-newsletter__input" type="email" name="email" autocomplete="email" required maxlength="254" placeholder="'.esc_attr($a['placeholder']).'"></label><button class="hws-newsletter__button" type="submit"'.($preview?' disabled':'').'>'.esc_html($a['button']).'</button></div>';
        $html.='<div class="hws-newsletter__honeypot" hidden aria-hidden="true"><label>Leave empty<input name="company_website" tabindex="-1" autocomplete="off"></label></div>';
        if($a['consent']==='on')$html.='<label class="hws-newsletter__consent"><input type="checkbox" name="consent" value="1" required> '.esc_html($a['consent_text']).'</label>';
        $s=Settings::get();
        if($s['enabled']&&$s['bot_check']==='turnstile'&&!$preview)$html.='<div class="hws-newsletter__turnstile" data-sitekey="'.esc_attr($s['site_key']).'"></div>';
        return $html.'<div class="hws-newsletter__status" role="status" aria-live="polite"></div></form>';
    }
    public static function routes(): void {
        register_rest_route('hws-base-tools/v1','/newsletter',['methods'=>'POST','permission_callback'=>'__return_true','callback'=>function($r){return self::submit((array)$r->get_params(),$_SERVER);}]);
        register_rest_route('hws-base-tools/v1','/newsletter/nonce',['methods'=>'GET','permission_callback'=>'__return_true','callback'=>function(){if(!self::settings()['enabled'])return new \WP_Error('disabled','Newsletter signup is disabled.',['status'=>403]);$r=new \WP_REST_Response(['nonce'=>wp_create_nonce('hws_newsletter')]);$r->header('Cache-Control','no-store, private');return $r;}]);
    }
    public static function submit(array $p,array $server,bool $require_nonce=true) {
        if(!self::settings()['enabled'])return new \WP_Error('disabled','Newsletter signup is disabled.',['status'=>403]);
        if(!self::store()->allow(SubmissionStore::client_ip($server)))return new \WP_Error('rate_limit','Too many attempts. Try again in ten minutes.',['status'=>429]);
        if($require_nonce&&!wp_verify_nonce(is_string($p['nonce']??null)?$p['nonce']:'','hws_newsletter'))return new \WP_Error('nonce','This form expired. Reload and try again.',['status'=>403]);
        $a=self::decode(is_string($p['config']??null)?$p['config']:'');
        if(!$a)return new \WP_Error('config','Invalid signup form.',['status'=>400]);
        if(!empty($p['company_website']))return new \WP_Error('spam','Signup rejected.',['status'=>400]);
        if($a['consent']==='on'&&($p['consent']??'')!=='1')return new \WP_Error('consent','Please agree to receive the newsletter.',['status'=>400]);
        $email=SubmissionStore::email((string)($p['email']??''));
        if(strlen($email)>254||!is_email($email))return new \WP_Error('email','Enter a valid email address.',['status'=>400]);
        $s=Settings::get();
        if($s['enabled']&&$s['bot_check']==='turnstile') {
            $bot=new BotCheck(static function($url,$body){$r=wp_safe_remote_post($url,['timeout'=>10,'body'=>$body]);return is_wp_error($r)?null:json_decode(wp_remote_retrieve_body($r),true);});
            if($bot->reason('turnstile',(string)($p['cf-turnstile-response']??''),$s,(string)parse_url(home_url(),PHP_URL_HOST),SubmissionStore::client_ip($server)))return new \WP_Error('turnstile','Please complete the security check.',['status'=>400]);
        }
        $result=self::store()->add($a['list'],$email,['server'=>$server,'page_url'=>(string)(!empty($p['page_url'])?$p['page_url']:($server['HTTP_REFERER']??'')),'name'=>$a['name']==='on'?($p['name']??''):'','source'=>$a['source'],'consent'=>($p['consent']??'')==='1']);
        if($result['status']==='error')return new \WP_Error('storage','Signup could not be saved. Try again later.',['status'=>503]);
        if($result['status']==='created') {
            $to=self::settings()['notification_email'];
            if($to&&is_email($to)) {
                $metadata=SubmissionStore::headers($server,(string)(!empty($p['page_url'])?$p['page_url']:($server['HTTP_REFERER']??'')));
                $body='List: '.$a['list']."\nEmail: ".$email."\nTime: ".SubmissionStore::est(gmdate('Y-m-d H:i:s'))
                    ."\nIP: ".SubmissionStore::client_ip($server)."\nPage URL: ".$metadata['page_url']
                    ."\nSource: ".$a['source']."\nManage signups: ".admin_url('admin.php?page=hws-core-tools&tab=newsletter&list='.rawurlencode($a['list']));
                $sent=wp_mail($to,'New newsletter signup',$body);
                do_action('hws_base_tools_newsletter_notification',$sent,$result['id']);
            }
            do_action('hws_base_tools_newsletter_signup',$result['id'],$a['list']);
        }
        return new \WP_REST_Response(['status'=>$result['status'],'message'=>$result['status']==='duplicate'?'This email is already subscribed to this list.':$a['success']],$result['status']==='created'?201:200);
    }
    public static function post(): void {$r=self::submit(wp_unslash($_POST),$_SERVER,false);wp_die(esc_html(is_wp_error($r)?$r->get_error_message():$r->get_data()['message']),'Newsletter signup',['response'=>is_wp_error($r)?($r->get_error_data()['status']??400):200,'back_link'=>true]);}
    private static function guard(string $action): void {if(!current_user_can('manage_options'))wp_die('Administrator access required.',403);check_admin_referer($action);}
    public static function save(): void {
        self::guard('hws_newsletter_save'); $email=trim((string)wp_unslash($_POST['notification_email']??''));
        if($email!==''&&!is_email($email))wp_die('Enter a valid notification email, or leave blank.',400);
        $style=sanitize_key($_POST['style']??'none');
        update_option(self::OPTION,['enabled'=>isset($_POST['enabled']),'notification_email'=>$email,'style'=>in_array($style,['none','simple','rounded'],true)?$style:'none'],false);
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
        echo '<h2>Newsletter</h2><p>Collect signups only. No marketing emails are sent. Use WordPress Tools > Export/Erase Personal Data for privacy requests.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="hws_base_tools_newsletter_save">';
        wp_nonce_field('hws_newsletter_save');
        echo '<h3>Enable signup collector</h3><label><input type="checkbox" name="enabled" '.checked($s['enabled'],true,false).'> Enabled</label><h3>Signup notification email</h3><p>Blank disables notifications. Defaults to the site administrator email.</p><input type="email" name="notification_email" value="'.esc_attr($s['notification_email']).'"><h3>Default style</h3><select name="style">';
        foreach(['none'=>'No plugin styling','simple'=>'Simple','rounded'=>'Rounded'] as $key=>$label)echo '<option value="'.$key.'" '.selected($s['style'],$key,false).'>'.$label.'</option>';
        echo '</select><p><button class="button button-primary">Save settings</button></p></form><h3>Selected style preview</h3>'.self::render(['style'=>$s['style']],true).'<h3>Exact selected style CSS</h3>'.\Hexa\PluginCore\WpAdminComponents\CodeDisplay::render(self::css($s['style'])?:'/* No plugin CSS */',['language'=>'css','title'=>'Selected newsletter style']);
        echo '<h3>List</h3><form method="get"><input type="hidden" name="page" value="hws-core-tools"><input type="hidden" name="tab" value="newsletter"><label>List ID <input name="list" pattern="[a-z0-9][a-z0-9_-]{0,79}" value="'.esc_attr($list).'"></label><button class="button">View list</button></form>';
        if(get_option('hws_base_tools_newsletter_schema')!=='1'){echo '<p>Enable the collector to create its storage.</p>';return;}
        echo '<p>Total in '.esc_html($list).': '.self::store()->total($list).'</p><p><a class="button" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=hws_base_tools_newsletter_export&list='.$list),'hws_newsletter_export')).'">Export CSV</a></p><table class="widefat"><thead><tr><th>Email</th><th>Name</th><th>Time (EST)</th><th>IP</th><th>Source</th><th>Delete</th></tr></thead><tbody>';
        $before=absint($_GET['before']??0);$rows=self::store()->recent($list,21,$before);$has_older=count($rows)>20;$rows=array_slice($rows,0,20);
        foreach($rows as $r)echo '<tr><td>'.esc_html($r['email']).'</td><td>'.esc_html($r['name']).'</td><td>'.esc_html(SubmissionStore::est($r['created_at'])).'</td><td>'.esc_html($r['ip']).'</td><td>'.esc_html($r['source']).'</td><td><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="hws_base_tools_newsletter_delete"><input type="hidden" name="list" value="'.esc_attr($list).'"><input type="hidden" name="subscriber_id" value="'.(int)$r['id'].'">'.wp_nonce_field('hws_newsletter_delete:'.$list.':'.$r['id'],'_wpnonce',false,false).'<button class="button" type="submit">Delete subscriber</button></form></td></tr>';
        echo '</tbody></table>';
        $base=admin_url('admin.php?page=hws-core-tools&tab=newsletter&list='.rawurlencode($list));
        if($before)echo '<p><a href="'.esc_url($base).'">Newest submissions</a></p>';
        if($has_older)echo '<p><a class="hws-newsletter-older" href="'.esc_url($base.'&before='.end($rows)['id']).'">Older submissions</a></p>';
        echo '<p>Shortcode: <code>[hws_newsletter list="'.esc_attr($list).'"]</code></p>';
    }
    public static function privacy_export(string $email,int $page=1): array {
        if(get_option('hws_base_tools_newsletter_schema')!=='1')return ['data'=>[],'done'=>true];
        $rows=self::store()->personal_data($email,$page);$data=[];
        foreach($rows as $r){$fields=[];foreach($r as $name=>$value)$fields[]=['name'=>$name,'value'=>$value];$data[]=['group_id'=>'hws-newsletter','group_label'=>'Newsletter signups','item_id'=>'hws-newsletter-'.$r['id'],'data'=>$fields];}
        return ['data'=>$data,'done'=>count($rows)<100];
    }
    public static function privacy_erase(string $email,int $page=1): array {
        $ok=get_option('hws_base_tools_newsletter_schema')!=='1'||self::store()->erase($email);
        return ['items_removed'=>$ok,'items_retained'=>!$ok,'messages'=>$ok?[]:['Could not erase newsletter data.'],'done'=>$ok];
    }
}
