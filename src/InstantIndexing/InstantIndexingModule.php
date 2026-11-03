<?php
namespace HWS\BaseTools\InstantIndexing;
use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
final class InstantIndexingModule implements ModuleInterface {
    public function register(): void {
        add_action('rest_api_init',static function(){
            register_rest_route('hws-base-tools/v1','/instant-indexing',['methods'=>'GET','permission_callback'=>static fn()=>current_user_can('manage_options'),'callback'=>static fn()=>IndexNowService::status()]);
            register_rest_route('hws-base-tools/v1','/instant-indexing/test',['methods'=>'POST','permission_callback'=>static fn()=>current_user_can('manage_options'),'args'=>['url'=>['required'=>true,'type'=>'string']],'callback'=>static fn($r)=>IndexNowService::test(esc_url_raw((string)$r['url']))]);
        });
        AjaxActionRegistry::create(['nonce_action'=>'hws_base_tools_instant_indexing','action_prefix'=>'hws_base_tools_instant_indexing'])->register([
            'status'=>static fn()=>IndexNowService::status(),
            'history'=>static fn()=>IndexNowService::history(absint($_POST['offset']??0)),
            'test'=>static fn()=>IndexNowService::test(esc_url_raw(wp_unslash((string)($_POST['url']??'')))),
        ]);
        if(defined('WP_CLI') && WP_CLI)\WP_CLI::add_command('hws instant-indexing',static function($args,$assoc){
            $verb=$args[0]??'status';
            if(!in_array($verb,['status','test'],true))\WP_CLI::error('Use status or test --page=<public URL>.');
            \WP_CLI::line(wp_json_encode('test'===$verb?IndexNowService::test((string)($assoc['page']??'')):IndexNowService::status()));
        });
    }
    public static function render(): void { InstantIndexingScreen::render(); }
}
