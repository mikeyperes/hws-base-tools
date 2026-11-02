<?php
declare(strict_types=1);
namespace HWS\BaseTools\FormProtection;
final class BotCheck {
    public static function action(string $provider):string {return $provider==='recaptcha'?'hws_form':'hws-form';}
    public function __construct(private \Closure $request) {}
    public function reason(string $provider,string $token,array $settings,string $host,string $ip):?string {
        $secret=Settings::secret($provider);if($secret==='')return $provider.'_invalid';
        $url=$provider==='turnstile'?'https://challenges.cloudflare.com/turnstile/v0/siteverify':'https://www.google.com/recaptcha/api/siteverify';
        $data=($this->request)($url,['secret'=>$secret,'response'=>$token,'remoteip'=>$ip]);
        if(!is_array($data)||empty($data['success'])||strtolower((string)($data['hostname']??''))!==strtolower($host)||($data['action']??'')!==self::action($provider))return $provider.'_invalid';
        if($provider==='recaptcha'&&(!isset($data['score'])||!is_numeric($data['score'])||(float)$data['score']<0||(float)$data['score']>1))return 'recaptcha_invalid';
        if($provider==='recaptcha'&&(float)$data['score']<(float)$settings['recaptcha_score_threshold'])return 'recaptcha_score';
        return null;
    }
}
