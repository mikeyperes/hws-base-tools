<?php
declare(strict_types=1);
namespace HWS\BaseTools\FormProtection;
use Hexa\PluginCore\CredentialVault\CredentialStore;
final class Settings {
    public const OPTION='hws_base_tools_form_protection';
    public static function sanitize(array $input):array {
        $on=fn($v)=>in_array($v,[true,1,'1','on','yes'],true);
        return ['enabled'=>$on($input['enabled']??false),'min_fill_seconds'=>max(1,min(30,(int)($input['min_fill_seconds']??3))),'retention_days'=>max(1,min(90,(int)($input['retention_days']??14))),'turnstile_enabled'=>$on($input['turnstile_enabled']??false),'site_key'=>sanitize_text_field((string)($input['site_key']??''))];
    }
    public static function get():array {return self::sanitize((array)get_option(self::OPTION,[]));}
    public static function secret():string {return (string)((new CredentialStore)->get('hws-base-tools','form_turnstile_secret')??'');}
    public static function state():array {
        $s=self::get();$s['secret_present']=(new CredentialStore)->exists('hws-base-tools','form_turnstile_secret');
        $s['turnstile_configured']=$s['turnstile_enabled']&&$s['site_key']!==''&&$s['secret_present'];
        return $s;
    }
    public static function save(array $input):array {
        $s=self::sanitize($input);$secret=isset($input['secret_key'])?trim((string)$input['secret_key']):'';
        if($s['turnstile_enabled']&&($s['site_key']===''||($secret===''&&self::secret()==='')))throw new \InvalidArgumentException('Turnstile needs both its site key and secret key.');
        if($secret!=='')(new CredentialStore)->store('hws-base-tools','form_turnstile_secret',$secret);
        update_option(self::OPTION,$s,false);
        do_action('hws_base_tools_form_protection_settings_saved',self::state());
        do_action('litespeed_purge_all');
        return self::state();
    }
}
