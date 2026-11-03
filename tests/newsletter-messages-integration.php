<?php
// Run with wp eval-file ... --user=<administrator>. Fixtures and settings restore in finally.
use HWS\BaseTools\Newsletter\NewsletterModule as N;
use HWS\BaseTools\FormProtection\Settings as P;
$count=0;
$check=function($ok,$label)use(&$count){if(!$ok)throw new RuntimeException('FAIL '.$label);echo 'PASS '.$label."\n";$count++;};
$original=get_option(N::OPTION,null);$original_user=get_current_user_id();
$list='nl2-'.bin2hex(random_bytes(6));$store=N::store();global $wpdb;
$globals=[$_POST,$_GET,$_REQUEST,$_SERVER];$ip='192.0.2.240';$keys=[];
$reset=function()use($wpdb,$ip,&$keys){$key=hash('sha256',$ip.':'.intdiv(time(),600));$keys[$key]=true;$wpdb->delete($wpdb->prefix.'hexa_form_rates',['rate_key'=>$key]);};
$protection=fn()=>['enabled'=>false];add_filter('pre_option_'.P::OPTION,$protection);
$die=function(){return function($m,$t,$a){throw new RuntimeException((string)$m,(int)($a['response']??500));};};
$redirect=function(){throw new RuntimeException('redirect',302);};
add_filter('wp_die_handler',$die,PHP_INT_MAX);add_filter('wp_redirect',$redirect,-999);
$invoke=function($action,$p){$_POST=$p;$_GET=[];$_REQUEST=$p;try{do_action($action);}catch(RuntimeException $e){return $e;}throw new RuntimeException('handler did not terminate');};
$defaults=['enabled'=>true,'notification_email'=>'','style'=>'none'];
$cfg=['list'=>$list,'name'=>'off','consent'=>'off','source'=>'nl2','success'=>N::SUCCESS];
$payload=function($c,$email='nl2@example.com'){return ['config'=>N::config($c),'nonce'=>wp_create_nonce('hws_newsletter'),'email'=>$email];};
$dispatch=function($p)use($reset,$ip){$reset();$_SERVER['REMOTE_ADDR']=$ip;$r=new WP_REST_Request('POST','/hws-base-tools/v1/newsletter');$r->set_body_params($p);return rest_do_request($r);};
$extract=function($html){preg_match('/name="config" value="([^"]+)"/',$html,$m);return N::decode(html_entity_decode($m[1]??'',ENT_QUOTES));};
try {
 update_option(N::OPTION,$defaults,false);
 $a=$extract(N::render([]));$check($a['success']===N::SUCCESS&&$a['duplicate']===N::DUPLICATE&&$a['error']==='','existing site defaults');
 $custom=['success'=>'Welcome & hello','duplicate'=>'Already joined','error'=>'Try later'];
 update_option(N::OPTION,array_merge($defaults,$custom),false);
 $a=$extract(N::render([]));foreach($custom as $k=>$v)$check($a[$k]===$v,'site default '.$k);
 $over=['success'=>'Per-form welcome','duplicate'=>'Per-form duplicate','error'=>'Per-form failure'];
 $a=$extract(N::render(array_merge(['list'=>$list],$over)));foreach($over as $k=>$v)$check($a[$k]===$v,'signed shortcode '.$k);
 $p=$payload($a);$r=$dispatch($p);$check($r->get_status()===201&&$r->get_data()['message']===$over['success'],'REST created message');
 $r=$dispatch($p);$check($r->get_status()===200&&$r->get_data()['message']===$over['duplicate'],'REST duplicate message');
 $r=$dispatch(array_merge($p,['email'=>'bad']));$check($r->get_status()===400&&$r->get_data()['message']===$over['error'],'REST custom field failure');
 foreach([['nonce'=>'bad'],['company_website'=>'bot']] as $bad){$r=$dispatch(array_merge($p,$bad));$check($r->get_data()['message']===$over['error'],'REST security override');}
 $reset();$_SERVER['REMOTE_ADDR']=$ip;for($i=0;$i<10;$i++)N::submit(array_merge($p,['email'=>'bad']),$_SERVER);
 $r=N::submit($p,$_SERVER);$check($r->get_error_code()==='rate_limit'&&$r->get_error_message()===$over['error'],'rate failure override');
 $turnstile=fn()=>['enabled'=>true,'bot_check'=>'turnstile','site_key'=>'test'];add_filter('pre_option_'.P::OPTION,$turnstile,20);
 $r=$dispatch($p);$check($r->get_data()['message']===$over['error']&&$r->get_data()['code']==='turnstile','Turnstile failure override');remove_filter('pre_option_'.P::OPTION,$turnstile,20);
 // Force only the fixture INSERT to fail, preserving the actual store and REST dispatcher.
 $fail_insert=function($query)use($store){if(str_starts_with($query,'INSERT INTO `'.$store->table().'`'))return 'SELECT * FROM hws_nl2_intentionally_missing_table';return $query;};
 add_filter('query',$fail_insert);$prior=$wpdb->suppress_errors(true);$r=$dispatch($payload($a,'storage@example.com'));$wpdb->suppress_errors($prior);remove_filter('query',$fail_insert);
 $check($r->get_status()===503&&$r->get_data()['message']===$over['error'],'REST storage failure override');
 wp_set_current_user(0);$reset();$r=$invoke('admin_post_nopriv_hws_base_tools_newsletter',$payload($a,'fallback@example.com'));$check($r->getCode()===200&&$r->getMessage()===$over['success'],'admin-post created text');
 $reset();$r=$invoke('admin_post_nopriv_hws_base_tools_newsletter',$payload($a,'fallback@example.com'));$check($r->getMessage()===$over['duplicate'],'admin-post duplicate text');
 $reset();$r=$invoke('admin_post_nopriv_hws_base_tools_newsletter',array_merge($payload($a),['email'=>'bad']));$check($r->getMessage()===$over['error'],'admin-post failure text');
 update_option(N::OPTION,$defaults,false);
 $old=N::decode(N::config($cfg));$check($old['duplicate']===N::DUPLICATE&&$old['error']==='','old signed configuration fallback');
 $r=$dispatch($payload($cfg));$check($r->get_data()['message']===N::DUPLICATE,'old config duplicate response');
 foreach(['email'=>'Enter a valid email address.','consent'=>'Please agree to receive the newsletter.'] as $field=>$text){$c=array_merge($cfg,['consent'=>$field==='consent'?'on':'off']);$p=$payload($c);if($field==='email')$p['email']='bad';$r=$dispatch($p);$check($r->get_data()['message']===$text,'specific '.$field.' instruction retained');}
 $r=$dispatch(array_merge($payload($cfg),['nonce'=>'bad']));$check($r->get_data()['message']===N::ERROR,'default generic security text');
 $r=$dispatch(array_merge($payload($cfg),['duplicate'=>'UNTRUSTED','error'=>'UNTRUSTED']));$check($r->get_data()['message']===N::DUPLICATE,'unsigned message fields ignored');
 foreach(['success','duplicate','error'] as $key){
   $check(N::render([$key=>str_repeat('a',2001)])==='','shortcode length rejects '.$key);
   $check(N::render([$key=>[]])==='','shortcode type rejects '.$key);
   $check(N::decode(N::config(array_merge($cfg,[$key=>str_repeat('a',2001)])))===null,'signed length rejects '.$key);
   $check(N::decode(N::config(array_merge($cfg,[$key=>[]])))===null,'signed type rejects '.$key);
 }
 $large=array_fill_keys(['success','duplicate','error'],str_repeat('é',1000));$check($extract(N::render($large))['error']===$large['error'],'maximum UTF-8 messages survive signing');
 $check(N::decode(N::config(array_merge($cfg,$over)).'x')===null,'tampered configuration rejected');
 $xss='</textarea><script>alert("x")</script><img src=x onerror=alert(1)>&';
 $a=array_merge($cfg,['success'=>$xss,'duplicate'=>$xss,'error'=>$xss]);
 $html=N::render($a);$check(!str_contains($html,$xss)&&str_contains($html,'&lt;script&gt;'),'shortcode attribute escaping');
 $reset();$r=$invoke('admin_post_nopriv_hws_base_tools_newsletter',$payload($a,'xss@example.com'));$check($r->getMessage()===esc_html($xss)&&!str_contains($r->getMessage(),'<script>'),'admin-post HTML escaping');
 wp_set_current_user($original_user);$base=['_wpnonce'=>wp_create_nonce('hws_newsletter_save'),'notification_email'=>'','style'=>'none'];
 $r=$invoke('admin_post_hws_base_tools_newsletter_save',array_merge($base,$custom));$check($r->getCode()===302&&get_option(N::OPTION)['duplicate']===$custom['duplicate'],'valid admin save');
 $saved=get_option(N::OPTION);
 foreach(['success','duplicate','error'] as $key)foreach([str_repeat('x',2001),['bad']] as $bad){$r=$invoke('admin_post_hws_base_tools_newsletter_save',array_merge($base,[$key=>$bad]));$check($r->getCode()===400&&get_option(N::OPTION)===$saved,'invalid admin '.$key.' preserves settings');}
 $r=$invoke('admin_post_hws_base_tools_newsletter_save',array_merge($base,['_wpnonce'=>'bad']));$check($r->getCode()===403&&get_option(N::OPTION)===$saved,'admin nonce rejected');
 wp_set_current_user(0);$r=$invoke('admin_post_hws_base_tools_newsletter_save',$base);$check($r->getCode()===403&&get_option(N::OPTION)===$saved,'non-admin save rejected');
 wp_set_current_user($original_user);$r=$invoke('admin_post_hws_base_tools_newsletter_save',array_merge($base,['success'=>wp_slash($xss),'duplicate'=>wp_slash($xss),'error'=>wp_slash($xss)]));
 ob_start();N::admin();$html=ob_get_clean();$check(str_contains($html,esc_textarea($xss))&&!str_contains($html,$xss),'admin textarea escaping');
 $check(get_option(N::OPTION)['success']===$xss,'admin unslashes text exactly once');
 foreach(['simple','rounded'] as $style){$check(str_contains(N::css($style),'status--success{color:#166534;background:#fff}')&&str_contains(N::css($style),'status--error{color:#b91c1c;background:#fff}'),'accessible colors '.$style);}
 $check(N::css('none')==='','unstyled option unchanged');
 echo 'PASS '.$count." newsletter message assertions\n";
} finally {
 $wpdb->query($wpdb->prepare('DELETE FROM '.$store->table().' WHERE collection=%s',$list));
 foreach($keys as $key=>$unused)$wpdb->delete($wpdb->prefix.'hexa_form_rates',['rate_key'=>$key]);
 if($original===null)delete_option(N::OPTION);else update_option(N::OPTION,$original,false);
 wp_set_current_user($original_user);[$_POST,$_GET,$_REQUEST,$_SERVER]=$globals;
 remove_filter('pre_option_'.P::OPTION,$protection);remove_filter('wp_die_handler',$die,PHP_INT_MAX);remove_filter('wp_redirect',$redirect,-999);
}
