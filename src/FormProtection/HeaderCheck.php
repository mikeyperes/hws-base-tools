<?php
declare(strict_types=1);
namespace HWS\BaseTools\FormProtection;
final class HeaderCheck {
    public const REASONS=['origin_referer_missing','origin_invalid','referer_invalid','user_agent_missing','user_agent_script','sec_fetch_site_invalid'];
    public static function reason(array $server,string $host):?string {
        $origin=trim((string)($server['HTTP_ORIGIN']??''));$referer=trim((string)($server['HTTP_REFERER']??''));
        if($origin===''&&$referer==='')return 'origin_referer_missing';
        foreach(['origin'=>$origin,'referer'=>$referer]as$name=>$value){
            if($value==='')continue;
            $parts=parse_url($value);
            if(!$parts||!in_array(strtolower((string)($parts['scheme']??'')),['http','https'],true)||isset($parts['user'])||isset($parts['pass'])||strtolower((string)($parts['host']??''))!==strtolower($host))return $name.'_invalid';
        }
        $agent=trim((string)($server['HTTP_USER_AGENT']??''));
        if($agent==='')return 'user_agent_missing';
        if(preg_match('/curl|python|wget|go-http|java|libwww|bot/i',$agent))return 'user_agent_script';
        if(isset($server['HTTP_SEC_FETCH_SITE'])&&strtolower(trim((string)$server['HTTP_SEC_FETCH_SITE']))!=='same-origin')return 'sec_fetch_site_invalid';
        return null;
    }
    public static function signals(array $server):array {
        return trim((string)($server['HTTP_ACCEPT_LANGUAGE']??''))===''?['accept_language_missing']:[];
    }
}
