<?php
namespace HWS\BaseTools\InstantIndexing;
use HWS\BaseTools\SearchConsole\SearchConsoleService;
final class IndexNowService {
    public static function answer(int $code): array {
        return ['ok'=>in_array($code,[200,202,204],true),'meaning'=>[0=>'No response',200=>'Accepted',202=>'Accepted; key verification pending',204=>'Accepted',400=>'Invalid request',403=>'Bad or inaccessible key',422=>'Host or key-location mismatch',429=>'Too many requests'][$code]??'Unexpected response'];
    }
    public static function status(): array {
        $modules=(array)get_option('rank_math_modules',[]);
        $on=defined('RANK_MATH_VERSION') && in_array('instant-indexing',$modules,true);
        $settings=(array)get_option('rank-math-options-instant-indexing',[]);
        $key=(string)($settings['indexnow_api_key']??'');
        $url=$key?home_url('/'.$key.'.txt'):'';
        if(class_exists('RankMath\\Instant_Indexing\\Api')) {
            $api=\RankMath\Instant_Indexing\Api::get();$key=(string)$api->get_key();$url=(string)$api->get_key_location();
        }
        $root=SearchConsoleService::own_url($url) && preg_match('#^/[^/]+\.txt$#',(string)wp_parse_url($url,PHP_URL_PATH)) && !wp_parse_url($url,PHP_URL_QUERY);
        $code=0;$matches=false;
        if($key && $root){
            $response=wp_safe_remote_get($url,['timeout'=>15,'redirection'=>0,'limit_response_size'=>8192]);
            if(!is_wp_error($response)){$code=wp_remote_retrieve_response_code($response);$matches=200===$code && hash_equals($key,trim(wp_remote_retrieve_body($response)));}
        }
        $types=array_values((array)($settings['bing_post_types']??[]));
        $warnings=[];
        if(!$on)$warnings[]='Rank Math Instant Indexing module is off.';
        if(!$key)$warnings[]='IndexNow key is missing.';
        elseif(!$root)$warnings[]='Key location is not a text file at this site root.';
        elseif(!$matches)$warnings[]='Key file is unreachable or does not contain the configured key.';
        if(!$types)$warnings[]='No content types submit automatically.';
        foreach($types as $type) if(!post_type_exists($type))$warnings[]='Automatic content type no longer exists: '.$type;
        $history=[];
        foreach(array_reverse(array_slice((array)get_option('rank_math_indexnow_log',[]),-20)) as $item){
            $code_item=(int)($item['status']??0);
            $history[]=['url'=>preg_replace('/[?#].*/','',(string)($item['url']??'')),'code'=>$code_item,'time'=>(int)($item['time']??0),'manual'=>!empty($item['manual_submission'])]+self::answer($code_item);
        }
        $installed=[];
        if(!function_exists('get_plugins')) require_once ABSPATH.'wp-admin/includes/plugin.php';
        $active=array_merge((array)get_option('active_plugins',[]),array_keys((array)get_site_option('active_sitewide_plugins',[])));
        foreach(get_plugins() as $file=>$plugin) if(str_starts_with($file,'fast-indexing-api/') || false!==stripos($plugin['Name']??'','Instant Indexing for Google')) $installed[]=['name'=>$plugin['Name'],'installed'=>true,'active'=>in_array($file,$active,true),'version'=>$plugin['Version']];
        return ['module_on'=>$on,'key'=>$key,'key_present'=>''!==$key,'key_url'=>$url,'key_at_root'=>(bool)$root,'key_http_code'=>$code,'key_matches'=>$matches,'automatic_post_types'=>$types,'last_submissions'=>$history,'google_instant_indexing_plugins'=>$installed,'warnings'=>$warnings];
    }
    public static function test(string $url): array {
        if(!SearchConsoleService::own_url($url) || wp_parse_url($url,PHP_URL_QUERY)) return ['ok'=>false,'message'=>'Choose a public page on this site without a query string.'];
        $post_id=url_to_postid($url);
        if($url!==home_url('/') && (!$post_id || 'publish'!==get_post_status($post_id)))return ['ok'=>false,'message'=>'Test page must be published.'];
        if(!defined('RANK_MATH_VERSION') || !in_array('instant-indexing',(array)get_option('rank_math_modules',[]),true))return ['ok'=>false,'message'=>'Rank Math Instant Indexing module is off.'];
        if(!class_exists('RankMath\\Instant_Indexing\\Api'))return ['ok'=>false,'message'=>'Rank Math IndexNow API is unavailable.'];
        $api=\RankMath\Instant_Indexing\Api::get();
        if(!$api->get_key())return ['ok'=>false,'message'=>'IndexNow key is missing.'];
        $api->submit([$url],true);
        $code=(int)$api->get_response_code();
        return ['url'=>$url,'http_code'=>$code,'message'=>$api->get_error()]+self::answer($code);
    }
}
