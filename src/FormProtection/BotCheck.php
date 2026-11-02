<?php
declare(strict_types=1);
namespace HWS\BaseTools\FormProtection;
final class BotCheck {
    public static function action(string $provider):string {return $provider==='recaptcha'?'hws_form':'hws-form';}
    public function __construct(private \Closure $request) {}
    public function reason(string $provider,string $token,array $settings,string $host,string $ip):?string {
        $secret=Settings::secret($provider);if($secret==='')return ($provider==='turnstile'?'turnstile':'recaptcha_invalid');
        $url=$provider==='turnstile'?'https://challenges.cloudflare.com/turnstile/v0/siteverify':'https://www.google.com/recaptcha/api/siteverify';
        $data=($this->request)($url,['secret'=>$secret,'response'=>$token,'remoteip'=>$ip]);
        // Cloudflare's official always-pass development pair returns example.com and no action.
        // This exception is restricted to that exact configured public test pair; real keys stay strict.
        if($provider==='turnstile'&&($settings['site_key']??'')==='1x00000000000000000000AA'&&$secret==='1x0000000000000000000000000000000AA'&&is_array($data)&&!empty($data['success'])&&($data['hostname']??'')==='example.com'&&empty($data['action']))return null;
        if(!is_array($data)||empty($data['success'])||strtolower((string)($data['hostname']??''))!==strtolower($host)||($data['action']??'')!==self::action($provider))return ($provider==='turnstile'?'turnstile':'recaptcha_invalid');
        if($provider==='recaptcha'&&(!isset($data['score'])||!is_numeric($data['score'])||(float)$data['score']<0||(float)$data['score']>1))return 'recaptcha_invalid';
        if($provider==='recaptcha'&&(float)$data['score']<(float)$settings['recaptcha_score_threshold'])return 'recaptcha_score';
        return null;
    }
}
