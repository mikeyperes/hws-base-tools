<?php
$options=[];$calls=[];$n=0;
function get_option($k,$d=null){global $options;return $options[$k]??$d;}
function update_option($k,$v,$a=null){global $options;$options[$k]=$v;return true;}
function wp_salt($s){return 'test-fixture-salt';}
function sanitize_key($s){return $s;}
function sanitize_text_field($s){return $s;}
function is_email($s){return filter_var($s,FILTER_VALIDATE_EMAIL);}
function wp_json_encode($v){return json_encode($v);}
function home_url($p='/'){return 'https://example.test'.$p;}
function wp_parse_url($u,$c=-1){return parse_url($u,$c);}
class WP_Error {private $c,$m;function __construct($c,$m){$this->c=$c;$this->m=$m;}function get_error_message(){return $this->m;}function get_error_code(){return $this->c;}}
function is_wp_error($r){return $r instanceof WP_Error;}
function wp_remote_retrieve_body($r){return $r['body'];}
function wp_remote_retrieve_response_code($r){return $r['code'];}
function wp_remote_post($u,$a){global $calls;$calls[]=$u;return ['code'=>200,'body'=>json_encode(['access_token'=>'fixture-token'])];}
function wp_remote_request($u,$a){global $calls;$calls[]=$u;
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
echo "$n Search Console assertions passed\n";
