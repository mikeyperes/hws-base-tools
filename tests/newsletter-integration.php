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
 nl_check(strpos(N::render(['style'=>'simple']),'<style>')!==false,'style printed on use');
 nl_check(strpos(N::render(['style'=>'simple']),'<style>')===false,'style printed once');
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
 echo "PASS ".$GLOBALS['nl_checks']." assertions\n";
} finally {
 $wpdb->query($wpdb->prepare("DELETE FROM {$store->table()} WHERE collection IN (%s,%s)",$list,$list.'-other'));
 foreach(array_merge(range(101,109),[189]) as $n){$ip='192.0.2.'.$n;$wpdb->delete($wpdb->prefix.'hexa_form_rates',['rate_key'=>hash('sha256',$ip.':'.intdiv(time(),600))]);}
 $wpdb->delete($wpdb->prefix.'hexa_form_rates',['rate_key'=>hash('sha256','198.51.100.159:'.intdiv(time(),600))]);
 if($original===null)delete_option(N::OPTION);else update_option(N::OPTION,$original,false);
}
