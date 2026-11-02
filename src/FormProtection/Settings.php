<?php
declare(strict_types=1);
namespace HWS\BaseTools\FormProtection;
use Hexa\PluginCore\CredentialVault\CredentialStore;
final class Settings {
    public const OPTION='hws_base_tools_form_protection';
    public const PUBLIC_KEYS=['enabled','honeypots_enabled','too_fast_enabled','header_check_enabled','min_fill_seconds','retention_days','bot_check','turnstile_enabled','recaptcha_enabled','site_key','recaptcha_site_key','recaptcha_score_threshold'];
    public static function sanitize(array $input):array {
        $on=fn($v)=>in_array($v,[true,1,'1','on','yes','true'],true);
        $provider=(string)($input['bot_check']??($on($input['recaptcha_enabled']??false)?'recaptcha':($on($input['turnstile_enabled']??false)?'turnstile':'none')));
        if(!in_array($provider,['none','turnstile','recaptcha'],true))$provider='none';
        return ['enabled'=>$on($input['enabled']??false),'honeypots_enabled'=>$on($input['honeypots_enabled']??true),'too_fast_enabled'=>$on($input['too_fast_enabled']??true),'header_check_enabled'=>$on($input['header_check_enabled']??false),
            'min_fill_seconds'=>max(1,min(30,(int)($input['min_fill_seconds']??3))),'retention_days'=>max(1,min(90,(int)($input['retention_days']??14))),
            'bot_check'=>$provider,'turnstile_enabled'=>$provider==='turnstile','recaptcha_enabled'=>$provider==='recaptcha',
            'site_key'=>sanitize_text_field((string)($input['site_key']??'')),'recaptcha_site_key'=>sanitize_text_field((string)($input['recaptcha_site_key']??'')),
            'recaptcha_score_threshold'=>max(0.0,min(1.0,(float)($input['recaptcha_score_threshold']??0.5)))];
    }
    public static function get():array {
        $raw=(array)get_option(self::OPTION,[]);
        if(!array_key_exists('bot_check',$raw)&&!array_key_exists('turnstile_enabled',$raw)&&!empty($raw['site_key'])&&self::secret()!=='')$raw['bot_check']='turnstile';
        return self::sanitize($raw);
    }
    public static function secret(string $provider='turnstile'):string {
        return (string)((new CredentialStore)->get('hws-base-tools',$provider==='recaptcha'?'form_recaptcha_secret':'form_turnstile_secret')??'');
    }
    public static function state():array {
        $s=self::get();$store=new CredentialStore;
        $s['secret_present']=$store->exists('hws-base-tools','form_turnstile_secret');
        $s['recaptcha_secret_present']=$store->exists('hws-base-tools','form_recaptcha_secret');
        $s['turnstile_configured']=$s['turnstile_enabled']&&$s['site_key']!==''&&$s['secret_present'];
        $s['recaptcha_configured']=$s['recaptcha_enabled']&&$s['recaptcha_site_key']!==''&&$s['recaptcha_secret_present'];
        return $s;
    }
    public static function save(array $input):array {
        if(isset($input['bot_check'])&&!in_array($input['bot_check'],['none','turnstile','recaptcha'],true))throw new \InvalidArgumentException('Bot check must be none, turnstile or recaptcha.');
        // Legacy switch writes remain supported, but the single provider is authoritative.
        $s=self::sanitize($input);
        $secret=trim((string)($input['secret_key']??''));$recaptcha=trim((string)($input['recaptcha_secret_key']??''));
        if(!array_key_exists('bot_check',$input)&&!array_key_exists('turnstile_enabled',$input)&&$s['site_key']!==''&&($secret!==''||self::secret()!==''))$s=array_replace($s,['bot_check'=>'turnstile','turnstile_enabled'=>true,'recaptcha_enabled'=>false]);
        if($s['turnstile_enabled']&&($s['site_key']===''||($secret===''&&self::secret()==='')))throw new \InvalidArgumentException('Turnstile needs both its site key and secret key.');
        if($s['recaptcha_enabled']&&($s['recaptcha_site_key']===''||($recaptcha===''&&self::secret('recaptcha')==='')))throw new \InvalidArgumentException('reCAPTCHA needs both its site key and secret key.');
        $store=new CredentialStore;
        if($secret!=='')$store->store('hws-base-tools','form_turnstile_secret',$secret);
        if($recaptcha!=='')$store->store('hws-base-tools','form_recaptcha_secret',$recaptcha);
        update_option(self::OPTION,$s,false);
        do_action('hws_base_tools_form_protection_settings_saved',self::state());do_action('litespeed_purge_all');return self::state();
    }
    public static function merge(array $changes):array {
        $s=self::get();
        if(!array_key_exists('bot_check',$changes)){
            foreach(['turnstile_enabled'=>'turnstile','recaptcha_enabled'=>'recaptcha']as$key=>$provider){
                if(!array_key_exists($key,$changes))continue;
                $on=in_array($changes[$key],[true,1,'1','on','yes','true'],true);
                if($on)$changes['bot_check']=$provider;
                elseif($s['bot_check']===$provider)$changes['bot_check']='none';
            }
            // First key setup defaults to Turnstile; an explicitly saved none stays off.
            $raw=(array)get_option(self::OPTION,[]);
            if(!array_key_exists('bot_check',$raw)&&!isset($changes['bot_check'])&&!empty($changes['site_key'])&&(!empty($changes['secret_key'])||self::secret()!==''))$changes['bot_check']='turnstile';
        }
        return array_replace($s,$changes);
    }
}
