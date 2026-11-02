<?php
namespace HWS\BaseTools\SearchConsole;
use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
final class SearchConsoleModule implements ModuleInterface {
    public function register(): void {
        add_action('rest_api_init',[$this,'routes']);
        $actions=[];
        foreach(['status','submit','submit-all','inspect','reindex','credential','property'] as $action) $actions[str_replace('-','_',$action)]=static fn()=>self::dispatch($action,wp_unslash($_POST));
        AjaxActionRegistry::create(['nonce_action'=>'hws_base_tools_search_console','action_prefix'=>'hws_base_tools_search_console'])->register($actions);
        if(defined('WP_CLI') && WP_CLI) \WP_CLI::add_command('hws search-console',static function($args,$assoc){
            $action=$args[0]??'status';
            if('credential'===$action) $assoc['json']=stream_get_contents(STDIN);
            \WP_CLI::line(wp_json_encode(self::dispatch($action,$assoc)));
        });
    }
    public static function dispatch(string $action,array $input): array {
        $service=new SearchConsoleService;
        switch($action){
            case 'status':return $service->status();
            case 'submit':return $service->submit(sanitize_key((string)($input['id']??'')));
            case 'submit-all':return $service->submit_all();
            case 'inspect':return $service->inspect(esc_url_raw((string)($input['url']??$input['page']??'')));
            case 'reindex':return $service->reindex(esc_url_raw((string)($input['url']??$input['page']??'')));
            case 'credential':return GoogleClient::save((string)($input['json']??''));
            case 'property':
                $value=(string)($input['property']??'');
                if(!in_array($value,SearchConsoleService::candidates(),true)) return ['ok'=>false,'message'=>'Property must be this site\'s domain, a covering parent domain or HTTPS URL prefix.'];
                update_option('hws_base_tools_search_console_property',$value,false);return ['ok'=>true,'property'=>$value];
            default:return ['ok'=>false,'message'=>'Unknown Search Console action.'];
        }
    }
    public function routes(): void {
        foreach(['status','submit','submit-all','inspect','reindex','credential','property'] as $action){
            register_rest_route('hws-base-tools/v1','/search-console/'.$action,['methods'=>'status'===$action?'GET':'POST','permission_callback'=>static fn()=>current_user_can('manage_options'),'callback'=>static fn($r)=>self::dispatch($action,$r->get_params())]);
        }
    }
    public static function render(): void {echo '<div id="hws-search-console">Search Console engine is ready. Screen design pending.</div>';}
}
