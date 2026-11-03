<?php
use HWS\BaseTools\Newsletter\NewsletterModule as N;
$checks=0;$check=function($ok,$label)use(&$checks){if(!$ok)throw new RuntimeException('FAIL '.$label);$checks++;echo "PASS $label\n";};
global $wpdb;$s=N::store();$prefix='nl2-delete-'.bin2hex(random_bytes(4));$original=get_option(N::OPTION,null);$uid=get_current_user_id();$fixture_user=0;$passwords=[];
$die=function(){return function($message,$title,$args){throw new RuntimeException((string)$message,(int)($args['response']??500));};};$redirect=function(){throw new RuntimeException('redirect',302);};
add_filter('wp_die_handler',$die,PHP_INT_MAX);add_filter('wp_redirect',$redirect,-999);
$call=function($path,$params=[]) {$r=new WP_REST_Request('GET','/hws-base-tools/v1/newsletter/'.$path);$r->set_query_params($params);return rest_do_request($r);};
$journal_count=function()use($wpdb,$s,$prefix){return (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.$s->deletions_table().' WHERE collection LIKE %s',$wpdb->esc_like($prefix).'%'));};
$http=function($path,$params=[],$auth=null){$url=add_query_arg($params,rest_url('hws-base-tools/v1/newsletter/'.$path));$args=['timeout'=>25,'redirection'=>0];if($auth)$args['headers']=['Authorization'=>$auth];$r=wp_remote_get($url,$args);if(is_wp_error($r))throw new RuntimeException($r->get_error_message());return $r;};
try{
 update_option(N::OPTION,array_merge(N::settings(),['enabled'=>false,'notification_email'=>'']),false);
 $first=$s->add($prefix,'earlier@example.com');$second=$s->add($prefix,'later@example.com');
 $shared=$prefix.'@example.com';$erase1=$s->add($prefix,$shared);$erase2=$s->add($prefix.'-other',$shared);
 $r=$call('lists');$lists=array_column($r->get_data()['items'],'total','list');$check($r->get_status()===200&&$lists[$prefix]===3&&$lists[$prefix.'-other']===1,'list totals across collections');
 // Exercise the real administrator delete handler, including nonce-bound row/list selection.
 $_POST=['list'=>$prefix,'subscriber_id'=>$second['id'],'_wpnonce'=>wp_create_nonce('hws_newsletter_delete:'.$prefix.':'.$second['id'])];$_REQUEST=$_POST;
 try{do_action('admin_post_hws_base_tools_newsletter_delete');$code=0;}catch(RuntimeException $e){$code=$e->getCode();}
 $check($code===302&&$s->total($prefix)===2,'administrator delete removes one row');
 $check($journal_count()===1,'administrator delete creates one tombstone');
 $page=$s->deleted(0,500);$own=array_values(array_filter($page['items'],fn($v)=>$v['collection']===$prefix));$event=$own[0];
 $check((int)$event['id']===$second['id']&&preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',$event['deleted_at'])===1,'tombstone retains ID list and UTC date');
 $check(array_keys($event)===['deletion_id','id','collection','deleted_at'],'tombstone contains no email or personal metadata');
 $cursor=(int)$event['deletion_id'];
 $check($s->delete($prefix,$first['id']),'older subscriber can be deleted later');
 $r=$call('deleted',['after_id'=>$cursor,'per_page'=>1]);$data=$r->get_data();
 $check($r->get_status()===200&&$data['items'][0]['id']===$first['id'],'deletion cursor discovers late deletion of older subscriber');
 $check($data['next_after_id']>$cursor&&$data['items'][0]['deletion_id']===$data['next_after_id'],'cursor follows deletion sequence not subscriber ID');
 $cursor=$data['next_after_id'];
 $check(!$s->delete($prefix,$first['id'])&&$journal_count()===2,'repeated delete creates no duplicate event');
 $privacy=N::privacy_erase($shared);$check($privacy['items_removed']&&$s->personal_data($shared)===[],'privacy erasure removes email across lists');
 $check($journal_count()===4,'privacy erasure journals both lists');
 $r=$call('deleted',['after_id'=>$cursor,'per_page'=>1]);$a=$r->get_data();$r=$call('deleted',['after_id'=>$a['next_after_id'],'per_page'=>1]);$b=$r->get_data();
 $check($a['items'][0]['id']!==$b['items'][0]['id']&&$b['next_after_id']>$a['next_after_id'],'deleted endpoint cursor pages without repeating rows');
 $r=$call('lists');$lists=array_column($r->get_data()['items'],'total','list');$check($lists[$prefix]===0&&$lists[$prefix.'-other']===0,'known empty lists report zero');
 foreach(['lists','deleted','submissions'] as $route){$r=$call($route);$check($r->get_status()===200&&str_contains($r->get_headers()['Cache-Control'],'no-store'),'disabled read no-store '.$route);}
 // Failure injection proves that neither a missing journal nor a failed deletion can lose half of the transaction.
 $row=$s->add($prefix,'rollback@example.com');
 foreach(['journal','delete'] as $failure){
  $filter=function($sql)use($s,$failure){$needle=$failure==='journal'?'INSERT INTO `'.$s->deletions_table().'`':'DELETE FROM `'.$s->table().'`';return str_starts_with($sql,$needle)?'SELECT * FROM hws_nl2_delete_missing_table':$sql;};
  $before=$journal_count();add_filter('query',$filter);$old=$wpdb->suppress_errors(true);$ok=$s->delete($prefix,$row['id']);$wpdb->suppress_errors($old);remove_filter('query',$filter);
  $check(!$ok&&$s->total($prefix)===1&&$journal_count()===$before,'atomic rollback on '.$failure.' failure');
 }
 $items=$call('submissions',['list'=>$prefix,'limit'=>999])->get_data();$check(count($items['items'])===1&&isset($items['items'][0]['created_at_utc']),'submissions alias caps limit and includes UTC field');
 foreach([['after_id'=>-1],['per_page'=>501],['after_id'=>[]]] as $bad)$check($call('deleted',$bad)->get_status()===400,'invalid deletion paging rejected');
 $check($call('submissions',['list'=>'BAD'])->get_status()===400,'invalid submission list rejected');
 // Real HTTPS authentication matrix. Passwords stay in memory and are individually revoked in finally.
 $make_password=function($user_id)use(&$passwords,$prefix){$r=WP_Application_Passwords::create_new_application_password($user_id,['name'=>$prefix]);if(is_wp_error($r))throw new RuntimeException('Fixture app password failed');$passwords[]=[$user_id,$r[1]['uuid']];return 'Basic '.base64_encode(get_userdata($user_id)->user_login.':'.$r[0]);};
 $admin_auth=$make_password($uid);
 $fixture_user=wp_insert_user(['user_login'=>$prefix,'user_email'=>$prefix.'@example.com','user_pass'=>wp_generate_password(40),'role'=>'subscriber']);if(is_wp_error($fixture_user))throw new RuntimeException('Fixture user failed');
 $low_auth=$make_password($fixture_user);
 foreach(['lists','deleted','submissions'] as $route){
  $check(wp_remote_retrieve_response_code($http($route))===401,'HTTP anonymous denied '.$route);
  $check(wp_remote_retrieve_response_code($http($route,[],$low_auth))===403,'HTTP non-admin application password denied '.$route);
  $r=$http($route,[],$admin_auth);$check(wp_remote_retrieve_response_code($r)===200&&str_contains(wp_remote_retrieve_header($r,'cache-control'),'no-store'),'HTTP admin application password allowed '.$route);
 }
 $bad_auth='Basic '.base64_encode(get_userdata($uid)->user_login.':'.wp_generate_password(32));
 $check(wp_remote_retrieve_response_code($http('lists',[],$bad_auth))===401,'HTTP wrong application password denied');
 $r=$http('deleted',['after_id'=>$cursor,'per_page'=>1],$admin_auth);$body=json_decode(wp_remote_retrieve_body($r),true);
 $check(count($body['items'])===1&&$body['next_after_id']>$cursor&&isset($body['items'][0]['deleted_at_utc']),'live deleted cursor and UTC schema');
 $r=$http('lists',[],$admin_auth);$body=json_decode(wp_remote_retrieve_body($r),true);$map=array_column($body['items'],'total','list');$check($map[$prefix]===1&&$map[$prefix.'-other']===0,'live list totals after erasure');
 // More than 500 rows proves the limit actually caps the response, beyond just argument acceptance.
 for($i=0;$i<501;$i++)$s->add($prefix.'-bulk','bulk-'.$i.'@example.com');
 $r=$call('submissions',['list'=>$prefix.'-bulk','limit'=>999]);$check(count($r->get_data()['items'])===500&&$r->get_data()['has_more'],'submission limit caps at 500 rows');
 $r=$call('submissions',['list'=>$prefix.'-bulk','limit'=>500,'after_id'=>$r->get_data()['next_after_id'],'upper_id'=>$r->get_data()['upper_id']]);$check(count($r->get_data()['items'])===1&&!$r->get_data()['has_more'],'submission cursor retrieves final row');
 WP_Application_Passwords::delete_application_password($uid,$passwords[0][1]);$check(WP_Application_Passwords::get_user_application_password($uid,$passwords[0][1])===null,'temporary admin application password revoked');
 echo "PASS $checks deletion/list/API assertions\n";
}finally{
 foreach($passwords as [$id,$uuid])WP_Application_Passwords::delete_application_password($id,$uuid);
 if($fixture_user&&!is_wp_error($fixture_user)){require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($fixture_user);}
 foreach([$s->table(),$s->deletions_table()] as $table)$wpdb->query($wpdb->prepare("DELETE FROM $table WHERE collection LIKE %s",$wpdb->esc_like($prefix).'%'));
 if($original===null)delete_option(N::OPTION);else update_option(N::OPTION,$original,false);
 remove_filter('wp_die_handler',$die,PHP_INT_MAX);remove_filter('wp_redirect',$redirect,-999);wp_set_current_user($uid);
 echo "Deletion test fixtures removed; collector settings restored.\n";
}
