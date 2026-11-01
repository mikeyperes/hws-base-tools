<?php
declare(strict_types=1);
namespace HWS\BaseTools\FormProtection;
final class Challenge {
    public function __construct(private string $key) {}
    public function issue(string $form,int $now):string {
        $body=$now.'.'.bin2hex(random_bytes(12));
        return $body.'.'.hash_hmac('sha256',$form."\n".$body,$this->key);
    }
    public function reason(string $token,string $form,int $minimum,int $now):?string {
        if($token==='')return 'missing_token';
        if(!preg_match('/^(\\d{1,12})\\.([a-f0-9]{24})\\.([a-f0-9]{64})$/D',$token,$m))return 'invalid_token';
        if(!hash_equals(hash_hmac('sha256',$form."\n".$m[1].'.'.$m[2],$this->key),$m[3]))return 'invalid_token';
        $age=$now-(int)$m[1];if($age<0||$age>7200)return 'invalid_token';
        return $age<$minimum?'too_fast':null;
    }
}
