<?php
declare(strict_types=1);
namespace HWS\BaseTools\FormProtection;
final class Protection {
    public function __construct(private Challenge $challenge,private \Closure $bot) {}
    public function reason(array $post,string $form,array $settings,int $now,array $server=[],string $host=''):?string {
        if(empty($settings['enabled']))return null;
        if(!empty($settings['honeypots_enabled'])&&($reason=Honeypots::reason($post,$form))!==null)return $reason;
        if(!empty($settings['too_fast_enabled'])){
            $token=$post['hws_form_token']??'';
            if(!is_string($token))return 'invalid_token';
            $reason=$this->challenge->reason($token,$form,(int)$settings['min_fill_seconds'],$now);
            if($reason!==null)return $reason;
        }
        if(!empty($settings['header_check_enabled'])&&($reason=HeaderCheck::reason($server,$host))!==null)return $reason;
        $provider=$settings['bot_check']??'none';
        if(in_array($provider,['turnstile','recaptcha'],true)){
            $token=$post[$provider==='turnstile'?'cf-turnstile-response':'g-recaptcha-response']??'';
            if(!is_string($token)||trim($token)==='')return $provider.'_missing';
            return ($this->bot)($provider,$token,$settings);
        }
        return null;
    }
}
