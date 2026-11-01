<?php
declare(strict_types=1);
namespace HWS\BaseTools\FormProtection;
final class Protection {
    public function __construct(private Challenge $challenge,private \Closure $turnstile) {}
    public function reason(array $post,string $form,array $settings,int $now):?string {
        if(empty($settings['enabled']))return null;
        $honey=$post['hws_form_website']??'';
        if(!is_scalar($honey)||trim((string)$honey)!=='')return 'honeypot';
        $token=$post['hws_form_token']??'';
        if(!is_string($token))return 'invalid_token';
        $reason=$this->challenge->reason($token,$form,(int)$settings['min_fill_seconds'],$now);
        if($reason!==null)return $reason;
        if(!empty($settings['turnstile_enabled'])){
            $response=$post['cf-turnstile-response']??'';
            if(!is_string($response)||$response===''||!($this->turnstile)($response))return 'turnstile';
        }
        return null;
    }
}
