<?php
$options=[];$calls=[];$requests=[];$n=0;$host='example.test';$responses=[];
function get_option($k,$d=null){global $options;return $options[$k]??$d;}
function update_option($k,$v,$a=null){global $options;$options[$k]=$v;return true;}
function wp_salt($s){return 'test-fixture-salt';}
function sanitize_key($s){return $s;}
function sanitize_text_field($s){return $s;}
function is_email($s){return filter_var($s,FILTER_VALIDATE_EMAIL);}
function wp_json_encode($v){return json_encode($v);}
function home_url($p='/'){global $host;return 'https://'.$host.$p;}
function wp_parse_url($u,$c=-1){return parse_url($u,$c);}
class WP_Error {private $c,$m;function __construct($c,$m){$this->c=$c;$this->m=$m;}function get_error_message(){return $this->m;}function get_error_code(){return $this->c;}}
function is_wp_error($r){return $r instanceof WP_Error;}
function wp_remote_retrieve_body($r){return $r['body'];}
function wp_remote_retrieve_response_code($r){return $r['code'];}
function wp_remote_post($u,$a){global $calls;$calls[]=$u;return ['code'=>200,'body'=>json_encode(['access_token'=>'fixture-token'])];}
function wp_remote_request($u,$a){global $calls,$requests,$responses;$calls[]=$u;$requests[]=[$u,$a];
 if(isset($responses[$u]))return $responses[$u];
 if ('PUT' === $a['method'] && (($a['headers']['Content-Length']??null)!=='0' || ($a['body']??null)!=='')) throw new RuntimeException('Empty PUT requires Content-Length 0');
 if(strpos($u,'indexing.googleapis.com')!==false)return ['code'=>403,'body'=>json_encode(['error'=>['message'=>'API has not been used or is disabled.','errors'=>[['reason'=>'accessNotConfigured']]]])];
 if(strpos($u,'siteVerification')!==false)return ['code'=>200,'body'=>json_encode(['items'=>[['site'=>['identifier'=>'example.test'],'owners'=>['OWNER@EXAMPLE.TEST']]]])];
 if(substr($u,-9)==='/sitemaps')return ['code'=>200,'body'=>json_encode(['sitemap'=>[['path'=>'https://example.test/sitemap_index.xml','errors'=>'0','warnings'=>'2','lastDownloaded'=>'2026-11-01T00:00:00Z']]])];
 return ['code'=>200,'body'=>json_encode(['permissionLevel'=>'siteOwner'])];
}
require dirname(__DIR__).'/lib/hexa-wordpress-plugin-core/src/CredentialVault/CredentialStore.php';
require dirname(__DIR__).'/src/SearchConsole/GoogleClient.php';
require dirname(__DIR__).'/src/SearchConsole/SearchConsoleService.php';
use HWS\BaseTools\SearchConsole\GoogleClient as G;
use HWS\BaseTools\SearchConsole\SearchConsoleService as S;
function check($v,$m){global $n;++$n;if(!$v)throw new RuntimeException($m);}
check(!G::configured(),'Starts unconfigured');
check(!G::save('{}')['ok'],'Reject malformed key');
$key=openssl_pkey_new(['private_key_bits'=>2048]);openssl_pkey_export($key,$pem);
check(G::save(json_encode(['type'=>'service_account','client_email'=>'test@example.test','private_key'=>$pem]))['ok'],'Stores valid key');
check(strpos(serialize($options),'BEGIN PRIVATE KEY')===false,'Private key encrypted at rest');
check(S::own_url('https://example.test/page/'),'Own page accepted');
check(!S::own_url('https://example.test.evil.test/'),'Foreign host rejected');
check(!S::own_url('https://user@example.test/'),'Userinfo rejected');
$service=new S;$status=$service->status();
check($status['registered']===true,'Property read succeeds');
check($status['owners']===['owner@example.test'],'Owners normalized');
check($status['sitemaps'][0]['warnings']==='2','Sitemap health preserved');
check(!$service->inspect('https://evil.test/')['ok'],'Foreign inspection rejected');
$r=$service->reindex('https://example.test/');
check($r['http_code']===403 && !$r['accepted'],'403 never success');
check(strpos($r['message'],'disabled')!==false,'Disabled API visible');
check($r['reason']==='accessNotConfigured','Google error reason preserved');
$client=new G;check($client->request('https://www.googleapis.com/webmasters/v3/sites/example/sitemaps/example','PUT')['ok'],'Empty Google PUT supported');check(!$client->request('https://evil.test/')['ok'],'Arbitrary Google destination rejected');
check(!in_array('https://evil.test/',$calls,true),'Secret never sent to foreign host');

eval('namespace Hexa\\PluginCore\\Sitemaps; class RecommendationRegistry { public static function lookup(){return ["rows"=>[["id"=>"index","url"=>\\home_url("/sitemap_index.xml"),"recommended"=>true,"current"=>true,"available"=>true]]];}}');
function property_response($property,$code) {
 global $responses;
 $responses['https://www.googleapis.com/webmasters/v3/sites/'.rawurlencode($property)]=['code'=>$code,'body'=>json_encode($code===200?['permissionLevel'=>'siteOwner']:['error'=>['message'=>'fixture failure','errors'=>[['reason'=>'fixture']]]])];
}
$host='podcast.example.test';$responses=[];
property_response('sc-domain:podcast.example.test',404);
property_response('sc-domain:example.test',200);
$requests=[];$service=new S;$status=$service->status();
check($status['property']==='sc-domain:example.test' && $status['registered']===true,'Parent property discovered');
check($status['owners']===['owner@example.test'],'Owners matched to selected parent');
check(count($status['property_attempts'])===2,'Stop at first accessible domain');
$r=$service->submit('index');check($r['ok'] && $r['property']==='sc-domain:example.test','Submission uses resolved parent');
$r=$service->submit_all();check($r['count']===1 && $r['property']==='sc-domain:example.test','Submit-all shares parent');
$r=$service->inspect(home_url('/page/'));check($r['property']==='sc-domain:example.test','Inspection returns selected parent');
$inspection=end($requests);check(json_decode($inspection[1]['body'],true)['siteUrl']==='sc-domain:example.test','Inspection sends selected parent');
$r=$service->reindex(home_url('/page/'));check($r['property']==='sc-domain:example.test' && !$r['accepted'],'Reindex returns checked parent');
$notification=end($requests);check(!isset(json_decode($notification[1]['body'],true)['siteUrl']),'Indexing payload remains supported');
$reads=array_filter($requests,static fn($r)=>$r[0]==='https://www.googleapis.com/webmasters/v3/sites/sc-domain%3Aexample.test');
check(count($reads)===1,'One resolution per service instance');
check(!$service->inspect('https://example.test/page/')['ok'],'Parent selection never widens input host scope');
$host='deep.podcast.example.test';
check(S::candidates()===['sc-domain:deep.podcast.example.test','sc-domain:podcast.example.test','sc-domain:example.test','https://deep.podcast.example.test/'],'Nearest parents precede URL prefix');
$host='podcast.example.test';property_response('sc-domain:example.test',404);
$r=(new S)->status();check($r['property']==='https://podcast.example.test/' && $r['registered']===true,'URL prefix is final fallback');
check($r['owners']===[],'URL prefix never inherits unrelated domain owners');
property_response('sc-domain:podcast.example.test',200);
check((new S)->status()['property']==='sc-domain:podcast.example.test','Exact domain always wins');
$options['hws_base_tools_search_console_property']='https://podcast.example.test/';
check((new S)->status()['property']==='sc-domain:podcast.example.test','Stored prefix cannot bypass domain priority');
property_response('sc-domain:podcast.example.test',403);
check((new S)->status()['property']==='https://podcast.example.test/','Forbidden exact property permits accessible fallback');
property_response('https://podcast.example.test/',404);
$r=(new S)->status();check($r['registered']===null && $r['property_response']['http_code']===403,'Unknown registration preserved when fallbacks absent');
property_response('sc-domain:podcast.example.test',404);
check((new S)->status()['registered']===false,'All candidates missing is unregistered');
$requests=[];$r=(new S)->reindex(home_url('/page/'));
check(!$r['accepted'] && count(array_filter($requests,static fn($r)=>strpos($r[0],'indexing.googleapis.com')!==false))===0,'Unresolved property prevents notification');
property_response('sc-domain:podcast.example.test',500);
$r=(new S)->status();check($r['registered']===null && count($r['property_attempts'])===1,'Provider failure stops candidate probing');
echo "$n Search Console assertions passed\n";

