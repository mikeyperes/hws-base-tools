<?php
namespace HWS\BaseTools\SitemapTools;
use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\Sitemaps\RecommendationRegistry;

final class Recommendations implements ModuleInterface {
    public function register(): void {
        add_filter(RecommendationRegistry::FILTER,[self::class,'recommend'],10,2);
        add_filter('hexa_plugin_core_sitemap_check',[self::class,'check'],10,2);
        add_action('rest_api_init',static function(){
            register_rest_route('hws-base-tools/v1','/sitemaps/google-news-approved',[
                'methods'=>'POST','permission_callback'=>static fn()=>current_user_can('manage_options'),
                'args'=>['approved'=>['type'=>'boolean','required'=>true]],
                'callback'=>static function($r){update_option('hws_base_tools_google_news_approved',(bool)$r['approved'],false);return ['approved'=>(bool)$r['approved']];}
            ]);
        });
    }
    public static function recommend(array $rows,string $site_type): array {
        if (!$site_type) $site_type=(string)get_option('hws_site_type','');
        foreach ($rows as &$row) $row['recommended_by']=['HWS Base Tools'];
        unset($row);
        $active=array_merge((array)get_option('active_plugins',[]),array_keys((array)get_site_option('active_sitewide_plugins',[])));
        $has=static function(string $slug)use($active):bool {foreach($active as $file) if (str_starts_with($file,$slug.'/')) return true;return false;};
        $rule=static function(array $types,string $plugin,bool $on)use(&$rows):void {
            foreach($types as $type){$id='post_type_'.$type;if(isset($rows[$id])){$rows[$id]['recommended']=$on;$rows[$id]['recommended_by']=[$plugin];}}
        };
        $rule(['team-member'],'HWS Base Tools',(bool)get_option('smp_enable_cpt_teammember',false));
        $rule(['testimonial'],'HWS Base Tools',(bool)get_option('enable_cpt_testimonial',false));
        $rule(['services'],'HWS Base Tools',(bool)get_option('hws_enable_cpt_services',false));
        $rules=[
            'smp-verified-profiles'=>['SMP Verified Profiles',['verified-profile','profile']],
            'seo-for-public-figures'=>['SEO for Public Figures',['book','organization','testimonial','interview','article','contributing-profile']],
            'smc-organization-profile'=>['SMC Organization Profile',['organization']],
            'hexa-pr-wire-distributor'=>['Hexa PR Wire syndication',['press-release']],
            'woocommerce'=>['WooCommerce',['product']],
        ];
        foreach($rules as $slug=>[$label,$types]) if($has($slug)) $rule($types,$label,true);
        foreach($active as $file) if(false!==strpos($file,'podcast')) $rule(['episode','episodes','podcast','podcast-episode','podcast-guest','podcast_guest'],'Podcast plugin',true);
        $publication=$has('smp-publication-integration');
        $rows['news']['recommended']=$publication && (bool)get_option('hws_base_tools_google_news_approved',false);
        $rows['news']['recommended_by']=[$publication?'SMP Publication':'HWS Base Tools'];
        $rows['news']['google_news_approved']=(bool)get_option('hws_base_tools_google_news_approved',false);
        $titles=(array)get_option('rank-math-options-titles',[]);
        $address=(array)($titles['local_address']??[]);
        $real=!empty($address['streetAddress']) && !empty($address['addressLocality']) && !empty($address['addressCountry']);
        $rows['local']['recommended']=$real;
        $rows['local']['recommended_by']=['HWS Base Tools (business address)'];
        if(isset($rows['post_type_rank_math_locations'])) $rows['post_type_rank_math_locations']['recommended']=$real;
        if(isset($rows['taxonomy_product_tag'])) {$rows['taxonomy_product_tag']['recommended']=false;$rows['taxonomy_product_tag']['recommended_by']=['WooCommerce'];}
        if(isset($rows['taxonomy_product_cat'])) {$rows['taxonomy_product_cat']['recommended']=$has('woocommerce') && $rows['taxonomy_product_cat']['counts']['total'] > $rows['taxonomy_product_cat']['condition']['more_than'];$rows['taxonomy_product_cat']['recommended_by']=['WooCommerce'];}
        foreach ((array)apply_filters('hws_base_tools_sitemaps',[]) as $id=>$extra) {
            if (!is_array($extra) || empty($extra['url'])) continue;
            if (in_array($extra['url'],array_column($rows,'url'),true)) continue;
            $rows[$id]=array_merge(['id'=>$id,'label'=>$id,'kind'=>'external','name'=>'','url'=>'','setting'=>'','recommended'=>true,'recommended_by'=>[$extra['group']??'External plugin'],'current'=>false,'available'=>true],$extra);
        }
        return (array)apply_filters('hws_base_tools_sitemap_recommendations',$rows,$site_type);
    }
    public static function check($result,array $row) {
        if(is_array($result)) return $result;
        return XmlInspector::sitemap($row['id'],array_merge(['available'=>true,'group'=>'Sitemaps','note'=>'','kind'=>'','total'=>null,'latest'=>null], $row));
    }
}
