<?php
declare(strict_types=1);
namespace HWS\BaseTools\FormProtection;
final class Honeypots {
    public const POOL=['website','company_url','fax','address_2','phone_alt','middle_name'];
    public static function names(string $form):array {
        $names=self::POOL;
        usort($names,static fn($a,$b)=>strcmp(hash('sha256',$form."\n".$a),hash('sha256',$form."\n".$b)));
        return array_slice($names,0,2);
    }
    public static function reason(array $post,string $form):?string {
        $fields=$post['hws_form_honeypots']??[];
        if(!is_array($fields))return 'honeypot';
        foreach(self::names($form)as$name){
            $value=$fields[$name]??'';
            if(!is_scalar($value)||(string)$value!=='')return 'honeypot';
        }
        return null;
    }
}
