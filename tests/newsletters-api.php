<?php
// Run: wp eval-file wp-content/plugins/hws-base-tools/tests/newsletters-api.php
use Hexa\PluginCore\DataNormalization\SlugConverter as S;
use HWS\BaseTools\Newsletter\{Newsletters as NL, NewsletterModule as N, NewsletterAdmin as A};
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;
$fails=0;$check=function(bool $ok,string $name)use(&$fails){echo($ok?'PASS ':'FAIL ').$name."\n";if(!$ok)$fails++;};
$saved=get_option(NL::OPTION,null);$defaults=get_option(N::OPTION,null);
try {
 update_option(NL::OPTION,[],false);
 $check(S::from_name('Weekly Digest!')==='weekly-digest','slug: punctuation and spaces');
 $check(S::from_name('  Café & Crème — Été ')==='cafe-and-creme-ete','slug: accents, ampersand, dashes');
 $check(S::from_name('Straße Øre','_')==='strasse_ore','slug: special letters and underscore separator');
 $check(S::from_name(str_repeat('ab ',60),'-',20)==='ab-ab-ab-ab-ab-ab-ab','slug: max length never ends on a dash');
 $check(S::is_valid('weekly-digest')&&!S::is_valid('Weekly Digest'),'slug: validity check');
 $a=NL::create(['name'=>'Weekly Digest!']);$check(!is_wp_error($a)&&$a['id']==='weekly-digest','create: id made from name');
 $check(is_wp_error(NL::create(['name'=>'Weekly digest']))&&NL::create(['name'=>'Weekly digest'])->get_error_code()==='exists','create: duplicate id refused');
 $check(NL::create(['name'=>''])->get_error_code()==='name','create: name required');
 $check(NL::create(['name'=>'X','notification_email'=>'bad'])->get_error_code()==='notification_email','create: bad email refused');
 $b=NL::create(['name'=>'VIP','id'=>'VIP List 2026','style'=>'rounded','success'=>'Welcome VIP']);$check($b['id']==='vip-list-2026','create: own id cleaned to slug');
 $check(N::newsletter('vip-list-2026')['style']==='rounded'&&N::newsletter('vip-list-2026')['success']==='Welcome VIP','settings: own design and message used');
 $check(strpos(N::render(['list'=>'vip-list-2026'],true),'hws-newsletter--rounded')!==false,'form: shortcode uses own design');
 $u=NL::update('vip-list-2026',['name'=>'VIP members','style'=>'']);$check($u['name']==='VIP members'&&N::newsletter('vip-list-2026')['style']===N::settings()['style'],'update: blank design falls back to site default');
 do_action('hws_base_tools_create_newsletter',['name'=>'From hook']);do_action('hws_base_tools_create_newsletter',['name'=>'From hook']);
 $check(NL::get('from-hook')!==null&&count(NL::all())===3,'hook: creates once, safe to repeat');
 wp_set_current_user(1);
 $req=new WP_REST_Request('POST','/hws-base-tools/v1/newsletter/newsletters');$req->set_body_params(['name'=>'Rest made']);$res=rest_do_request($req);
 $check($res->get_status()===201&&$res->get_data()['id']==='rest-made','rest: create');
 $res=rest_do_request(new WP_REST_Request('GET','/hws-base-tools/v1/newsletter/newsletters'));$check($res->get_status()===200&&count($res->get_data()['items'])===4,'rest: list');
 $req=new WP_REST_Request('PATCH','/hws-base-tools/v1/newsletter/newsletters/rest-made');$req->set_body_params(['success'=>'Hi']);$res=rest_do_request($req);$check($res->get_status()===200&&NL::get('rest-made')['success']==='Hi','rest: update');
 $res=rest_do_request(new WP_REST_Request('DELETE','/hws-base-tools/v1/newsletter/newsletters/rest-made'));$check($res->get_status()===200&&NL::get('rest-made')===null,'rest: remove');
 wp_set_current_user(0);$res=rest_do_request(new WP_REST_Request('GET','/hws-base-tools/v1/newsletter/newsletters'));$check(in_array($res->get_status(),[401,403],true),'rest: visitors refused');wp_set_current_user(1);
 $r=A::ajax_create(new AjaxRequest(['name'=>'Ajax one','id'=>''],[],[]));$check(is_array($r)&&$r['id']==='ajax-one'&&strpos($r['panel_html'],'data-id="ajax-one"')!==false&&strpos($r['list_html'],'Ajax one')!==false,'ajax: create returns list and panel');
 $r=A::ajax_update(new AjaxRequest(['id'=>'ajax-one','name'=>'Ajax renamed','style'=>'inherit','notification_email'=>'','success'=>'','duplicate'=>'','error'=>''],[],[]));$check(is_array($r)&&NL::get('ajax-one')['name']==='Ajax renamed'&&NL::get('ajax-one')['style']==='','ajax: update');
 $r=A::ajax_open(new AjaxRequest(['id'=>'ajax-one'],[],[]));$check(strpos($r['panel_html'],'Ajax renamed')!==false,'ajax: open panel');
 $r=A::ajax_remove(new AjaxRequest(['id'=>'ajax-one'],[],[]));$check(is_array($r)&&NL::get('ajax-one')===null&&$r['panel_html']==='','ajax: remove');
} finally {
 if($saved===null)delete_option(NL::OPTION);else update_option(NL::OPTION,$saved,false);
 if($defaults!==null)update_option(N::OPTION,$defaults,false);
}
echo $fails?"$fails FAILED\n":"ALL PASS\n";
