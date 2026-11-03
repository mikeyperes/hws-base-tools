<?php
// Run: wp eval-file wp-content/plugins/hws-base-tools/tests/newsletter-integration.php --user=<admin>
use HWS\BaseTools\Newsletter\NewsletterModule as N;
use Hexa\PluginCore\FrontendForms\SubmissionStore as S;
$GLOBALS['nl_checks']=0;
function nl_check($ok,$name){if(!$ok)throw new RuntimeException('FAIL '.$name);$GLOBALS['nl_checks']++;echo "PASS $name\n";}
global $wpdb;
$original=get_option(N::OPTION,null);$list='nl1-test-'.bin2hex(random_bytes(4));$store=N::store();$server=['REMOTE_ADDR'=>'192.0.2.189','HTTP_USER_AGENT'=>'nl1-fixture','HTTP_REFERER'=>'https://example.com/form','HTTP_ACCEPT_LANGUAGE'=>'en-US','HTTP_COOKIE'=>'secret','HTTP_AUTHORIZATION'=>'secret'];
try {
 update_option(N::OPTION,['enabled'=>false,'notification_email'=>'','style'=>'none'],false);
 nl_check(N::render([])==='','disabled shortcode');
 nl_check(N::submit([],$server)->get_error_code()==='disabled','disabled endpoint');
 $store->install();update_option('hws_base_tools_newsletter_schema','1',false);
 update_option(N::OPTION,['enabled'=>true,'notification_email'=>'nl1-notice@example.com','style'=>'none'],false);
 $html=N::render(['list'=>$list,'name'=>'on','consent'=>'on','class'=>'custom','id'=>'nl1']);
 nl_check(strpos($html,'<style>')===false,'default no CSS');
 nl_check(strpos($html,'name="name"')!==false && strpos($html,'name="consent"')!==false && strpos($html,'role="status"')!==false,'name consent and accessible status');
 N::render(['style'=>'simple']);nl_check(wp_style_is('hws-newsletter-simple','enqueued'),'style enqueued on use');
 N::render(['style'=>'simple']);nl_check(count(wp_styles()->get_data('hws-newsletter-simple','after'))===1,'inline CSS registered once');
 preg_match('/name="config" value="([^"]+)"/',$html,$m);
 $p=['config'=>html_entity_decode($m[1]),'nonce'=>wp_create_nonce('hws_newsletter'),'email'=>'NL1-TEST@EXAMPLE.COM','name'=>'=SUM(1,2)','consent'=>'1','page_url'=>'https://example.com/signup'];
 nl_check(N::decode($p['config'].'tampered')===null,'signed configuration cannot change');
 $fresh=function($n)use($server){return array_replace($server,['REMOTE_ADDR'=>'192.0.2.'.$n]);};
 nl_check(N::submit(array_replace($p,['nonce'=>'bad']),$fresh(101))->get_error_code()==='nonce','nonce rejected');
 nl_check(N::submit(array_replace($p,['email'=>'bad']),$fresh(102))->get_error_code()==='email','invalid email rejected');
 nl_check(N::submit(array_replace($p,['consent'=>'']),$fresh(103))->get_error_code()==='consent','consent required server-side');
 nl_check(N::submit(array_replace($p,['company_website'=>'spam']),$fresh(104))->get_error_code()==='spam','honeypot rejected');
 $mail=[];$mock=function($pre,$args)use(&$mail){$mail[]=$args;return true;};add_filter('pre_wp_mail',$mock,10,2);
 $r=N::submit($p,$fresh(105));nl_check(!is_wp_error($r)&&$r->get_data()['status']==='created','new signup');
 nl_check(count($mail)===1&&$mail[0]['to']==='nl1-notice@example.com','notification on new signup');
 foreach(['IP: 192.0.2.105','Page URL: https://example.com/signup','Source: newsletter','admin.php?page=hws-core-tools&tab=newsletter&list='] as $part)nl_check(str_contains($mail[0]['message'],$part),'notification metadata '.$part);
 $host=preg_replace('/^www\./i','',(string)parse_url(home_url(),PHP_URL_HOST));$h=implode("\n",(array)$mail[0]['headers']);
 nl_check($mail[0]['subject']==='Newsletter: =SUM 1 2 <nl1-test@example.com> - '.$host,'notice subject names subscriber and site');
 nl_check(str_contains($h,'<no-reply@'.$host.'>')&&str_contains($h,'Reply-To: =SUM 1 2 <nl1-test@example.com>'),'notice from no-reply with subscriber reply-to');
 $inj=N::submit(array_replace($p,['email'=>'nl1-inject@example.com','name'=>"Eve\r\nBcc: evil@example.com"]),$fresh(120));$last=end($mail);
 nl_check(!is_wp_error($inj)&&!preg_match('/[\r\n]/',$last['subject'].implode('',(array)$last['headers']))&&!str_contains(implode("\n",(array)$last['headers']),"\nBcc"),'notice headers resist injection');
 $mail=array_slice($mail,0,1);foreach($store->recent($list,50) as $row)if($row['email']==='nl1-inject@example.com')$store->delete($list,(int)$row['id']);
 nl_check(N::submit(array_replace($p,['email'=>'nl1-test@example.com']),$fresh(106))->get_data()['status']==='duplicate','normalized duplicate result');
 nl_check($store->total($list)===1&&count($mail)===1,'duplicate has no row or notification');
 $other=$store->add($list.'-other','nl1-test@example.com');nl_check($other['status']==='created','duplicate scope is per list');
 $row=$store->recent($list)[0];$headers=json_decode($row['headers'],true);
 nl_check($row['ip']==='192.0.2.105'&&count($headers)===4&&!str_contains($row['headers'],'secret'),'allowlisted headers only');
 nl_check(S::client_ip(['REMOTE_ADDR'=>'203.0.113.2','HTTP_CF_CONNECTING_IP'=>'1.2.3.4','HTTP_X_FORWARDED_FOR'=>'1.2.3.4'])==='203.0.113.2','spoofed proxy headers ignored');
 nl_check(S::client_ip(['REMOTE_ADDR'=>'173.245.48.4','HTTP_CF_CONNECTING_IP'=>'2001:db8::1'])==='2001:db8::1','trusted Cloudflare IPv6 visitor');
 nl_check(S::in_cidr('2606:4700::1','2606:4700::/32')&&!S::in_cidr('2607::1','2606:4700::/32'),'IPv6 CIDR boundary');
 nl_check(S::est('2026-07-01 12:00:00')==='2026-07-01 07:00:00 EST','fixed EST in summer');
 $f=fopen('php://temp','w+');$store->csv($f,$list);rewind($f);$csv=stream_get_contents($f);fclose($f);
 nl_check(strpos($csv,"'=SUM(1,2)")!==false&&strpos($csv,'timestamp_est')!==false,'CSV formula escaping and timestamps');
 foreach(['=1','+1','-1','@x',"\t=1","\r=1",'  =1'] as $v)nl_check(S::csv_cell($v)[0]==="'",'formula prefix '.bin2hex($v));
 nl_check(N::privacy_export('nl1-test@example.com')['done']&&count(N::privacy_export('nl1-test@example.com')['data'])===2,'privacy export across lists');
 $ip='198.51.100.159';for($i=0;$i<10;$i++)nl_check($store->allow($ip),'rate attempt '.$i);
 nl_check(!$store->allow($ip),'rate limit 11th attempt');
 update_option(N::OPTION,['enabled'=>true,'notification_email'=>'','style'=>'none'],false);
 $p['email']='nl1-blank@example.com';nl_check(N::submit($p,$fresh(107))->get_data()['status']==='created'&&count($mail)===1,'blank notification disables notice');

 remove_filter('pre_wp_mail',$mock,10);
 update_option(N::OPTION,['enabled'=>true,'notification_email'=>'nl1-notice@example.com','style'=>'none'],false);
 $failed=function(){return false;};add_filter('pre_wp_mail',$failed);
 $p['email']='nl1-mailfailed@example.com';
 nl_check(N::submit($p,$fresh(108))->get_data()['status']==='created','failed notification retains signup');
 remove_filter('pre_wp_mail',$failed);
 $protection=get_option(\HWS\BaseTools\FormProtection\Settings::OPTION,null);
 $turnstile=function(){return ['enabled'=>true,'bot_check'=>'turnstile','site_key'=>'nl1-public-test-key'];};
 add_filter('pre_option_'.\HWS\BaseTools\FormProtection\Settings::OPTION,$turnstile);
 nl_check(strpos(N::render(['list'=>$list]),'hws-newsletter__turnstile')!==false,'existing Turnstile configuration inherited');
 nl_check(N::submit($p,$fresh(109))->get_error_code()==='turnstile','missing Turnstile token fails closed');
 remove_filter('pre_option_'.\HWS\BaseTools\FormProtection\Settings::OPTION,$turnstile);

 wp_set_current_user(1);ob_start();N::admin();$admin=ob_get_clean();nl_check(strpos($admin,'Export CSV')!==false&&strpos($admin,'Exact selected style CSS')!==false,'admin renders export and CSS preview');
 wp_set_current_user(0);ob_start();N::admin();nl_check(ob_get_clean()==='','admin capability guard');wp_set_current_user(1);
 nl_check(N::privacy_erase('nl1-test@example.com')['items_removed']&&$store->total($list)===2,'privacy erasure removes across lists');

 // Real REST dispatcher and admin-post action hooks.
 $entry=$list.'-entry';$saved_server=$_SERVER;$saved_get=$_GET;$saved_post=$_POST;$saved_request=$_REQUEST;
 $_SERVER['REMOTE_ADDR']='198.51.100.120';
 $dispatch=function($method,$path,$params=[]){$r=new WP_REST_Request($method,'/hws-base-tools/v1/'.$path);$r->set_body_params($params);return rest_do_request($r);};
 $cfg=['list'=>$entry,'name'=>'off','consent'=>'off','source'=>'entry-test','success'=>'Joined'];
 $payload=['config'=>N::config($cfg),'nonce'=>wp_create_nonce('hws_newsletter'),'email'=>'nl1-entry@example.com'];
 update_option(N::OPTION,['enabled'=>false,'notification_email'=>'','style'=>'none'],false);
 nl_check($dispatch('POST','newsletter',$payload)->get_status()===403,'REST disabled 403');
 nl_check($dispatch('GET','newsletter/nonce')->get_status()===403,'GET nonce disabled 403');
 update_option(N::OPTION,['enabled'=>true,'notification_email'=>'','style'=>'none'],false);
 $nonce=$dispatch('GET','newsletter/nonce');
 nl_check($nonce->get_status()===200&&wp_verify_nonce($nonce->get_data()['nonce'],'hws_newsletter'),'GET nonce valid 200');
 nl_check(str_contains($nonce->get_headers()['Cache-Control'],'no-store'),'GET nonce cannot be cached');
 nl_check($dispatch('POST','newsletter',$payload)->get_status()===201,'REST created 201');
 nl_check($dispatch('POST','newsletter',$payload)->get_status()===200,'REST duplicate 200');
 nl_check($dispatch('POST','newsletter',array_replace($payload,['email'=>'bad']))->get_status()===400,'REST validation 400');
 nl_check($dispatch('POST','newsletter',array_replace($payload,['nonce'=>'bad']))->get_status()===403,'REST nonce 403');
 foreach([[],array_replace($cfg,['list'=>'../bad']),array_replace($cfg,['name'=>[]]),array_replace($cfg,['consent'=>true]),array_replace($cfg,['source'=>[]]),array_replace($cfg,['success'=>[]])] as $bad){
   nl_check($dispatch('POST','newsletter',array_replace($payload,['config'=>N::config($bad)]))->get_status()===400,'REST malformed signed configuration 400');
 }
 $_SERVER['REMOTE_ADDR']='198.51.100.121';for($i=0;$i<10;$i++)$dispatch('POST','newsletter',array_replace($payload,['email'=>'bad']));
 nl_check($dispatch('POST','newsletter',$payload)->get_status()===429,'REST rate limit 429');
 $_SERVER['REMOTE_ADDR']='198.51.100.122';
 $die_filter=function(){return function($message,$title,$args){throw new RuntimeException((string)$message,(int)($args['response']??500));};};
 add_filter('wp_die_handler',$die_filter,PHP_INT_MAX);
 $invoke=function($action,$post=[],$get=[]) {
   $_POST=$post;$_GET=$get;$_REQUEST=array_merge($get,$post);
   try{do_action('admin_post_'.$action);throw new RuntimeException('Handler did not terminate',599);}
   catch(RuntimeException $e){return $e;}
 };
 // A nonce from three ticks ago is older than its entire accepted lifetime.
 wp_set_current_user(0);
 $expired=substr(wp_hash((wp_nonce_tick('hws_newsletter')-3).'|hws_newsletter|'.get_current_user_id().'|'.wp_get_session_token(),'nonce'),-12,10);
 nl_check(!wp_verify_nonce($expired,'hws_newsletter'),'test nonce is expired');
 nl_check($dispatch('POST','newsletter',array_replace($payload,['nonce'=>$expired]))->get_status()===403,'REST rejects expired nonce');
 $fallback=array_replace($payload,['nonce'=>$expired,'email'=>'nl1-fallback@example.com']);
 $r=$invoke('nopriv_hws_base_tools_newsletter',$fallback);nl_check($r->getCode()===200&&str_contains($r->getMessage(),'Joined'),'admin-post accepts expired baked nonce with valid signed config');
 nl_check($invoke('nopriv_hws_base_tools_newsletter',array_replace($fallback,['company_website'=>'spam']))->getCode()===400,'fallback honeypot enforced');
 nl_check($invoke('nopriv_hws_base_tools_newsletter',array_replace($fallback,['config'=>'bad']))->getCode()===400,'fallback signed config enforced');
 wp_set_current_user(1);
 $r=$invoke('hws_base_tools_newsletter_save',['_wpnonce'=>wp_create_nonce('hws_newsletter_save'),'notification_email'=>'invalid']);
 nl_check($r->getCode()===400,'save invalid email rejected');
 wp_set_current_user(0);
 nl_check($invoke('hws_base_tools_newsletter_save')->getCode()===403,'save non-admin rejected');
 nl_check($invoke('hws_base_tools_newsletter_export')->getCode()===403,'export without capability rejected');
 wp_set_current_user(1);
 nl_check($invoke('hws_base_tools_newsletter_export')->getCode()===403,'export without nonce rejected');
 // >20 rows and cursor paging through the actual admin renderer.
 for($i=0;$i<23;$i++)$store->add($entry,'nl1-page-'.$i.'@example.com');
 $_GET=['list'=>$entry];ob_start();N::admin();$first=ob_get_clean();
 preg_match('/before=(\d+)/',$first,$cursor);
 nl_check(count($store->recent($entry,20))===20&&isset($cursor[1]),'admin has older-page cursor after 20 rows');
 $_GET=['list'=>$entry,'before'=>$cursor[1]];ob_start();N::admin();$second=ob_get_clean();
 nl_check(str_contains($second,'Newest submissions')&&!str_contains($second,'hws-newsletter-older'),'admin older page shows remaining rows and newest link');
 $id=(int)$store->recent($entry,1)[0]['id'];
 $delete=['list'=>$entry,'subscriber_id'=>$id,'_wpnonce'=>wp_create_nonce('hws_newsletter_delete:'.$entry.':'.$id)];
 wp_set_current_user(0);nl_check($invoke('hws_base_tools_newsletter_delete',$delete)->getCode()===403,'delete without capability rejected');wp_set_current_user(1);
 nl_check($invoke('hws_base_tools_newsletter_delete',array_replace($delete,['_wpnonce'=>'bad']))->getCode()===403,'delete without nonce rejected');
 nl_check(!$store->delete($list,$id),'delete is bound to collection');
 $redirect=function(){throw new RuntimeException('redirect',302);};add_filter('wp_redirect',$redirect,-999);
 $before_count=$store->total($entry);$r=$invoke('hws_base_tools_newsletter_delete',$delete);remove_filter('wp_redirect',$redirect,-999);
 nl_check($r->getCode()===302&&$store->total($entry)===$before_count-1,'delete handler removes exactly one subscriber');
 remove_filter('wp_die_handler',$die_filter,PHP_INT_MAX);
 require __DIR__.'/newsletter-concurrency.php';
 $race=nl_concurrent(array_replace($payload,['email'=>'nl1-concurrent@example.com']));
 $statuses=array_column($race,'http');sort($statuses);
 nl_check($statuses===[200,201],'concurrent REST requests produce one create and one duplicate');
 $matches=array_filter($store->recent($entry,100),fn($row)=>$row['email']==='nl1-concurrent@example.com');
 nl_check(count($matches)===1,'concurrent duplicate leaves exactly one row');
 $_SERVER=$saved_server;$_GET=$saved_get;$_POST=$saved_post;$_REQUEST=$saved_request;

 echo "PASS ".$GLOBALS['nl_checks']." assertions\n";
} finally {
 $wpdb->query($wpdb->prepare("DELETE FROM {$store->table()} WHERE collection LIKE %s",$wpdb->esc_like($list).'%'));
 $wpdb->query($wpdb->prepare("DELETE FROM {$store->deletions_table()} WHERE collection LIKE %s",$wpdb->esc_like($list).'%'));
 foreach(array_merge(range(101,109),[189]) as $n){$ip='192.0.2.'.$n;$wpdb->delete($wpdb->prefix.'hexa_form_rates',['rate_key'=>hash('sha256',$ip.':'.intdiv(time(),600))]);}
 $wpdb->delete($wpdb->prefix.'hexa_form_rates',['rate_key'=>hash('sha256','198.51.100.159:'.intdiv(time(),600))]);
 foreach(range(120,131) as $n)$wpdb->delete($wpdb->prefix.'hexa_form_rates',['rate_key'=>hash('sha256','198.51.100.'.$n.':'.intdiv(time(),600))]);
 if($original===null)delete_option(N::OPTION);else update_option(N::OPTION,$original,false);
}
