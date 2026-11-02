<?php
$options=['rank_math_modules'=>['instant-indexing'],'rank-math-options-instant-indexing'=>['indexnow_api_key'=>'public-proof','bing_post_types'=>['post','removed']],'rank_math_indexnow_log'=>[['url'=>'https://example.test/page/?private=redacted','status'=>422,'time'=>1,'manual_submission'=>true]]];$count=0;$requests=[];
define('RANK_MATH_VERSION','fixture');
function get_option($k,$d=[]){global $options;return $options[$k]??$d;}
function get_site_option($k,$d=[]){return $d;}
function home_url($p='/'){return 'https://example.test'.$p;}
function wp_parse_url($u,$c=-1){return parse_url($u,$c);}
function wp_safe_remote_get($u,$args){global $requests;$requests[]=$u;return ['code'=>200,'body'=>'public-proof'];}
function wp_remote_retrieve_response_code($r){return $r['code'];}
function wp_remote_retrieve_body($r){return $r['body'];}
function is_wp_error($r){return false;}
function post_type_exists($type){return 'post'===$type;}
function get_plugins(){return ['fast-indexing-api/instant-indexing.php'=>['Name'=>'Instant Indexing for Google','Version'=>'1.0']];}
function url_to_postid($u){return strpos($u,'published')!==false?1:0;}
function get_post_status($id){return 'publish';}
require dirname(__DIR__).'/src/SearchConsole/SearchConsoleService.php';
require dirname(__DIR__).'/src/InstantIndexing/IndexNowService.php';
use HWS\BaseTools\InstantIndexing\IndexNowService as I;
function check($v,$m){global $count;++$count;if(!$v)throw new RuntimeException($m);}
foreach([200,202,204] as $code)check(I::answer($code)['ok'],'Accepted codes');
foreach([0,403,422,429] as $code)check(!I::answer($code)['ok'],'Failure codes');
$r=I::status();
check($r['module_on'],'Module read');
check($r['key_matches'],'Actual key response matches');
check($r['automatic_post_types']===['post','removed'],'Automatic types read');
check(count($r['warnings'])===1,'Removed type warning');
check($r['last_submissions'][0]['url']==='https://example.test/page/','History query stripped');
check($r['last_submissions'][0]['code']===422,'History response preserved');
check($r['google_instant_indexing_plugins'][0]['active']===false,'Inactive Google plugin inventoried');
check(!I::test('https://foreign.test/')['ok'],'Foreign test rejected');
check(!I::test('https://example.test/?q=1')['ok'],'Query test rejected');
check(!I::test('https://example.test/draft/')['ok'],'Unpublished test rejected');
$options['rank_math_modules']=[];
check(!I::test('https://example.test/')['ok'],'Disabled module rejected');
check(!$options['rank_math_modules'],'Reads never enable module');
echo "$count Instant Indexing assertions passed\n";
