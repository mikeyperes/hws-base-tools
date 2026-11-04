<?php
namespace Hexa\PluginCore\CoreContracts { interface ModuleInterface {public function register(): void;} }
namespace {
class WP_Term {public $taxonomy='post_tag';public $term_id=7;}
function get_option($key,$default=false){return $GLOBALS['options'][$key]??$default;}
function get_queried_object(){return $GLOBALS['term']??null;}
function is_search(){return $GLOBALS['context']==='search';}
function is_author(){return $GLOBALS['context']==='author';}
function is_category(){return $GLOBALS['context']==='category';}
function is_tag(){return $GLOBALS['context']==='tag';}
function esc_html($s){return htmlspecialchars($s,ENT_QUOTES);}
function esc_attr($s){return esc_html($s);}
function esc_url($s){return $s;}
define('ABSPATH',__DIR__);
require __DIR__.'/../src/FrontendContent/EditorialSurfaces.php';
require __DIR__.'/../src/TeamMembers/TeamMemberDirectory.php';
use HWS\BaseTools\FrontendContent\EditorialSurfaces as S;
function check($ok,$label){if(!$ok)throw new \RuntimeException($label);echo "PASS $label\n";}
$GLOBALS['options']=[S::OPTION=>['current_query'=>true],'posts_per_page'=>12];
$base=['posts_per_page'=>10,'posts_per_archive_page'=>10,'s'=>'news','author'=>42,'paged'=>2,'orderby'=>'date'];
foreach(['search','author','category','tag'] as $context){
 $GLOBALS['context']=$context;$got=S::query($base);$expected=$base;$expected['posts_per_page']=12;$expected['posts_per_archive_page']=12;
 check($got===$expected,"$context site batch and constraints");
}
$GLOBALS['options'][S::OPTION]['posts_per_page']=15;
check(S::query($base)['posts_per_page']===15,'editorial size');
$widget=new class {function get_data($key){return ['posts_per_page'=>18];}function get_settings($key){return $key==='post_query_post_type'?'current_query':null;}};
S::begin_widget($widget);check(S::query($base)['posts_per_page']===18,'explicit widget size');S::end_widget($widget);
check(S::query($base)['posts_per_page']===15,'widget scope cleaned');
$GLOBALS['term']=new WP_Term();check(S::query($base)['tax_query'][0]['terms']===[7],'existing configured taxonomy');unset($GLOBALS['term']);
$GLOBALS['context']='singular';check(S::query($base)===$base,'other contexts unchanged');
$GLOBALS['options'][S::OPTION]['current_query']=false;$GLOBALS['context']='search';check(S::query($base)===$base,'disabled unchanged');
$m=new \ReflectionMethod(HWS\BaseTools\TeamMembers\TeamMemberDirectory::class,'render_item');$m->setAccessible(true);
$item=['name'=>'Example Person','url'=>'/team-member/example/','initials'=>'EP'];
$classic=$m->invoke(null,$item,false,true);$svg=$m->invoke(null,$item,false,true,'svg');
check(str_contains($classic,'&rarr;')&&!str_contains($classic,'<svg')&&!str_contains($classic,'<span>Example Person</span>'),'classic markup preserved');
check(str_contains($svg,'<a href="/team-member/example/"><span>Example Person</span></a>')&&str_contains($svg,'<svg data-arrow')&&!str_contains($svg,'&rarr;'),'SVG markup');
}
