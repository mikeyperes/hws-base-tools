<?php
use HWS\BaseTools\Newsletter\NewsletterModule as N;
use hws_base_tools\Login_Masking as L;
$count=0;$check=function($ok,$name)use(&$count){if(!$ok)throw new RuntimeException('FAIL '.$name);$count++;echo "PASS $name\n";};
$original=get_option(N::OPTION,null);$mask=get_option(L::OPT_KEY,null);$uid=get_current_user_id();$list='nl2-http-'.bin2hex(random_bytes(5));$page=0;$password_id=null;$temp_user=0;
global $wpdb;$store=N::store();$rates=$wpdb->get_results('SELECT rate_key,attempts,expires FROM '.$wpdb->prefix.'hexa_form_rates LIMIT 1001',OBJECT_K);if(count($rates)>1000)throw new RuntimeException('Rate fixture snapshot exceeds bound');
$fixture_ips=[];$tick=intdiv(time(),600);
$get=function($url,$args=[]){$r=wp_remote_get($url,array_merge(['timeout'=>30,'redirection'=>0],$args));if(is_wp_error($r))throw new RuntimeException($r->get_error_message());return $r;};
$decode=function($body){preg_match('/name="config" value="([^"]+)"/',$body,$m);if(empty($m))throw new RuntimeException('No form config in HTTP page');return html_entity_decode($m[1],ENT_QUOTES);};
try{
 update_option(N::OPTION,array_merge(N::settings(),['enabled'=>true,'notification_email'=>'']),false);
 $page=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'Newsletter temporary HTTP test','post_name'=>$list,'post_content'=>'[hws_newsletter list="'.$list.'" source="'.$list.'" success="HTTP welcome" duplicate="HTTP already joined" error="HTTP please retry" style="simple"]'],true);if(is_wp_error($page))throw new RuntimeException('Cannot create fixture page');
 $url=get_permalink($page);echo 'Fixture page: '.$url."\n";
 foreach([true,false] as $on){
   update_option(L::OPT_KEY,array_merge((array)$mask,['enabled'=>$on,'hide_wp_admin'=>$on]),false);
   $res=$get(add_query_arg('probe',wp_generate_uuid4(),$url));$check(wp_remote_retrieve_response_code($res)===200,'HTTP form page mask '.(int)$on);
   $body=wp_remote_retrieve_body($res);$config=$decode($body);$check(!str_contains($body,'action="'.admin_url('admin-post.php').'"'),'front-end form action mask '.(int)$on);
   foreach(['created','duplicate','error'] as $state){
     $r=wp_remote_post($url,['timeout'=>30,'redirection'=>0,'body'=>['action'=>'hws_base_tools_newsletter','config'=>$config,'nonce'=>'expired','email'=>$state==='error'?'bad':'http-'.(int)$on.'@example.com']]);
     if(is_wp_error($r))throw new RuntimeException($r->get_error_message());
     $location=wp_remote_retrieve_header($r,'location');$check(wp_remote_retrieve_response_code($r)===303&&str_contains($location,'hws_newsletter_result='),'HTTP PRG '.$state.' mask '.(int)$on);
     $r=$get($location);$html=wp_remote_retrieve_body($r);$css=$state==='created'?'success':$state;
     $text=['created'=>'HTTP welcome','duplicate'=>'HTTP already joined','error'=>'HTTP please retry'][$state];
     $check(wp_remote_retrieve_response_code($r)===200&&preg_match('/class="hws-newsletter__status hws-newsletter__status--'.$css.'"[^>]*>'.preg_quote($text,'/').'<\/div>/',$html)===1,'HTTP configured status '.$state.' mask '.(int)$on);
     $check(str_contains($html,'data-state="'.$state.'"'),'HTTP form state '.$state.' mask '.(int)$on);
     $check(!str_contains(wp_remote_retrieve_header($r,'cache-control'),'max-age=3600'),'result page not cached');
     if($state==='created'){$before=$store->total($list);$get($location);$check($store->total($list)===$before,'refresh cannot resubmit');}
   }
 }
 $r=$get(add_query_arg('hws_newsletter_result','created.9999999999.fake.fake',$url));$check(!str_contains(wp_remote_retrieve_body($r),'class="hws-newsletter__status hws-newsletter__status--success"'),'forged result flag ignored');
 foreach($store->recent($list,100) as $row)$fixture_ips[$row['ip']]=true;
 // Real application-password authentication; new temporary password never leaves memory.
 $user=get_userdata($uid);$created=WP_Application_Passwords::create_new_application_password($uid,['name'=>$list]);if(is_wp_error($created))throw new RuntimeException('Application password creation failed');
 [$password,$entry]=$created;$password_id=$entry['uuid'];$auth=['headers'=>['Authorization'=>'Basic '.base64_encode($user->user_login.':'.$password)]];
 $api=rest_url('hws-base-tools/v1/newsletter/subscribers');
 $r=$get($api);$check(wp_remote_retrieve_response_code($r)===401,'HTTP anonymous read denied');
 $r=$get(add_query_arg(['list'=>$list,'per_page'=>1],$api),$auth);$data=json_decode(wp_remote_retrieve_body($r),true);
 $check(wp_remote_retrieve_response_code($r)===200&&count($data['items']??[])===1&&$data['has_more'],'HTTP authenticated first page');
 $check($data['items'][0]['list']===$list&&!isset($data['items'][0]['collection'])&&is_array($data['items'][0]['headers']),'HTTP public row schema');
 $check(str_contains(wp_remote_retrieve_header($r,'cache-control'),'no-store'),'HTTP subscriber response private');
 $next=$get(add_query_arg(['list'=>$list,'per_page'=>1,'after_id'=>$data['next_after_id'],'upper_id'=>$data['upper_id']],$api),$auth);$tail=json_decode(wp_remote_retrieve_body($next),true);
 $check(count($tail['items'])===1&&!$tail['has_more']&&$tail['items'][0]['id']>$data['items'][0]['id'],'HTTP cursor advances without duplicates');
 foreach([['per_page'=>501],['after_id'=>-1],['list'=>'BAD']] as $bad){$r=$get(add_query_arg($bad,$api),$auth);$check(wp_remote_retrieve_response_code($r)===400,'HTTP invalid read arguments rejected');}
 $r=wp_remote_post($api,array_merge($auth,['timeout'=>30,'body'=>['email'=>'should-not-save@example.com']]));$check(wp_remote_retrieve_response_code($r)===404,'HTTP API has no write method');
 $temp_user=wp_insert_user(['user_login'=>$list,'user_email'=>$list.'@example.com','user_pass'=>wp_generate_password(40),'role'=>'subscriber']);if(is_wp_error($temp_user))throw new RuntimeException('Fixture user failed');
 wp_set_current_user($temp_user);$req=new WP_REST_Request('GET','/hws-base-tools/v1/newsletter/subscribers');$r=rest_do_request($req);$check($r->get_status()===403,'non-admin read forbidden');wp_set_current_user($uid);
 update_option(N::OPTION,array_merge(N::settings(),['enabled'=>false]),false);$r=$get(add_query_arg('list',$list,$api),$auth);$check(wp_remote_retrieve_response_code($r)===200,'stored subscribers readable while collector disabled');
 echo "PASS $count real HTTP/API assertions\n";
}finally{
 foreach($store->recent($list,100) as $row)$fixture_ips[$row['ip']]=true;
 $wpdb->query($wpdb->prepare('DELETE FROM '.$store->table().' WHERE collection=%s',$list));
 foreach(array_keys($fixture_ips) as $ip)foreach(array_unique([$tick,intdiv(time(),600)]) as $bucket){$key=hash('sha256',$ip.':'.$bucket);if(isset($rates[$key]))$wpdb->replace($wpdb->prefix.'hexa_form_rates',(array)$rates[$key]);else $wpdb->delete($wpdb->prefix.'hexa_form_rates',['rate_key'=>$key]);}
 if($password_id)WP_Application_Passwords::delete_application_password($uid,$password_id);
 if($temp_user&&!is_wp_error($temp_user)){require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($temp_user);}
 if($page&&!is_wp_error($page))wp_delete_post($page,true);
 if($original===null)delete_option(N::OPTION);else update_option(N::OPTION,$original,false);
 if($mask===null)delete_option(L::OPT_KEY);else update_option(L::OPT_KEY,$mask,false);
 wp_set_current_user($uid);
 echo "HTTP fixtures removed; collector and login mask restored.\n";
}
