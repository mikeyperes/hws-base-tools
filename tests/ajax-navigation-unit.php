<?php
// Focused executable contract tests, no WordPress installation required.
declare(strict_types=1);
define('ABSPATH', __DIR__);
require __DIR__ . '/../src/AjaxNavigation/Settings.php';
require __DIR__ . '/../lib/hexa-wordpress-plugin-core/src/CoreContracts/ModuleInterface.php';
require __DIR__ . '/../src/AjaxNavigation/AjaxNavigationModule.php';
use HWS\BaseTools\AjaxNavigation\Settings;
use HWS\BaseTools\AjaxNavigation\AjaxNavigationModule;
$checks = 0;
function check($ok, $message) { global $checks; ++$checks; if (!$ok) throw new RuntimeException($message); }
check(!Settings::sanitize([])['enabled'], 'Off by default');
foreach ([true,1,'1','on'] as $value) check(Settings::sanitize(['enabled'=>$value])['enabled'], 'Valid enabled');
foreach ([false,0,'0','false','yes',[],null] as $value) check(!Settings::sanitize(['enabled'=>$value])['enabled'], 'Reject ambiguous enabled');
$paths = Settings::sanitize(['excluded_paths'=>"/private/\n//evil.com\nhttps://evil.com\n<script>\n/private\n/with space\n/foo?bar"])['excluded_paths'];
check(in_array('/private',$paths,true), 'Normalize user prefix');
check(count($paths)===count(Settings::DEFAULT_EXCLUDED)+1, 'Reject malformed prefixes and deduplicate');
foreach (['/wp-admin/','/wp-login.php','/cart/','/checkout/order-pay/1','/my-account','/account/login','/topic/feed/','/a.pdf','/audio.MP3','/?feed=rss2','/?elementor-preview=1','/?preview=true','/?action=elementor','/?add-to-cart=5','/?wc-ajax=checkout','/private/post','/%77p-admin/','/private'] as $url) check(Settings::excluded($url,$paths), 'Must exclude '.$url);
foreach (['/','/about/','/cartography/','/episodes/?s=test','/?s=example','/privateer/'] as $url) check(!Settings::excluded($url,$paths), 'Must allow '.$url);
check(Settings::excluded('/anything', ['/']), 'Root excludes everything');
$GLOBALS['options']=[]; $GLOBALS['flags']=[];
function get_option($key,$default=false) { return $GLOBALS['options'][$key]??$default; }
function is_admin(){return $GLOBALS['flags']['admin']??false;}
function is_user_logged_in(){return $GLOBALS['flags']['login']??false;}
function is_feed(){return $GLOBALS['flags']['feed']??false;}
function is_preview(){return $GLOBALS['flags']['preview']??false;}
function is_404(){return $GLOBALS['flags']['404']??false;}
function is_embed(){return $GLOBALS['flags']['embed']??false;}
function is_trackback(){return $GLOBALS['flags']['trackback']??false;}
function is_cart(){return $GLOBALS['flags']['cart']??false;}
function is_checkout(){return $GLOBALS['flags']['checkout']??false;}
function is_account_page(){return $GLOBALS['flags']['account']??false;}
function apply_filters($name,$value){return $GLOBALS['flags']['filter']??$value;}
$_SERVER['REQUEST_URI']='/about/'; $_SERVER['REQUEST_METHOD']='GET';
check(!AjaxNavigationModule::allowed(),'No assets when switch absent');
$GLOBALS['options'][Settings::OPTION]=['enabled'=>true];
check(AjaxNavigationModule::allowed(),'Public GET eligible');
foreach(['admin','login','feed','preview','404','embed','trackback','cart','checkout','account'] as $flag){$GLOBALS['flags']=[$flag=>true];check(!AjaxNavigationModule::allowed(),'Request guard '.$flag);}
$GLOBALS['flags']=['filter'=>false];check(!AjaxNavigationModule::allowed(),'Extension opt out');
$GLOBALS['flags']=[];$_SERVER['REQUEST_METHOD']='POST';check(!AjaxNavigationModule::allowed(),'POST cannot load Drive');
check(Settings::sanitize([])['analytics']==='manual','Manual analytics default');
check(Settings::sanitize(['analytics'=>'history'])['analytics']==='history','History ownership');
check(Settings::sanitize(['analytics'=>'bad'])['analytics']==='manual','Reject invalid ownership');
check(AjaxNavigationModule::optimization_exclusions(['existing.js'])===['existing.js','assets/ajax-navigation/navigation.js','assets/ajax-navigation/vendor/turbo.js'],'Optimizer exclusions preserve existing paths');
check(str_contains(AjaxNavigationModule::track_runtime('<script src="turbo.js"></script>','hws-turbo'),'data-turbo-track="reload"'),'Runtime changes require document reload');
check(AjaxNavigationModule::track_runtime('<script src="other.js"></script>','other')==='<script src="other.js"></script>','Do not mark unrelated assets');
echo "AJAX navigation: $checks checks passed\n";
