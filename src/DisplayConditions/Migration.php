<?php
namespace HWS\BaseTools\DisplayConditions;
/** Preflight the entire document set before writing; unknown rules never disappear. */
final class Migration {
    private static function translate(array $rule): array {
        $op=$rule['jedv_condition']??'';
        if(!in_array($op,['exists','equal'],true))throw new \RuntimeException('Unsupported operator: '.$op);
        $tag=$rule['__dynamic__']['jedv_field']??'';
        if(!$tag)throw new \RuntimeException('Missing dynamic source');
        preg_match('/name="([^" ]+)"/',$tag,$n); preg_match('/settings="([^"]+)"/',$tag,$m);
        $settings=json_decode(rawurldecode($m[1]??''),true)?:[];
        $d=match($n[1]??'') {
            'shortcode'=>['source'=>'shortcode','key'=>$settings['shortcode']??''],
            'post-custom-field'=>['source'=>'custom_field','key'=>$settings['custom_key']??$settings['key']??''],
            'acf-text'=>self::acf($settings),
            'post-featured-image'=>['source'=>'featured_image','key'=>''],
            default=>throw new \RuntimeException('Unsupported dynamic tag: '.($n[1]??'')),
        };
        $d['rule']=$op==='exists'?'has_value':'equals'; $d['value']=(string)($rule['jedv_value']??'');
        return Conditions::normalize($d);
    }
    private static function acf(array $s): array {
        $key=$s['key']??'';
        if(str_starts_with($key,'options:'))throw new \RuntimeException('ACF option source requires manual mapping: '.$key);
        if(str_starts_with($key,'field_') && function_exists('acf_get_field'))$key=acf_get_field($key)['name']??'';
        return ['source'=>'custom_field','key'=>$key];
    }
    private static function name(array $d): string {
        if($d['source']==='combined')return 'Combined '. $d['relation'].' conditions';
        if($d['source']==='featured_image')return 'Post featured image '.$d['rule'];
        $key=$d['key'];
        if($d['source']==='shortcode') {
            preg_match('/^\[([a-z_]+)/',$key,$short); preg_match('/(?:field|service)="([^"\]]+)"/',$key,$arg);
            $key=str_replace(['mpp_','_presence','_platform_url'],'',$short[1]??'shortcode').' '.($arg[1]??$key);
        }
        $label=ucwords(str_replace(['_','-'],' ',$key));
        return $label. match($d['rule']){'has_value'=>' has a value','is_empty'=>' is empty',default=>' equals '.($d['value']===''?'empty':$d['value'])};
    }
    public static function run(bool $dry): array {
        global $wpdb;
        $ids=$wpdb->get_col("SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key='_elementor_data'");
        $all=Conditions::all(); $plans=[]; $report=[]; $errors=[];
        foreach($ids as $id){
            if(wp_is_post_revision($id))continue;
            $raw=get_post_meta($id,'_elementor_data',true); $data=json_decode($raw,true); if(!is_array($data))continue;
            $count=0; $removed=0;
            $walk=function(&$nodes)use(&$walk,&$all,&$count,&$removed,&$errors,$id){foreach($nodes as &$node){
                $s=&$node['settings']; if(!is_array($s))$s=[];
                $keys=array_filter(array_keys($s),fn($k)=>str_starts_with($k,'jedv_'));
                if(($s['jedv_enabled']??'')==='yes'){
                    try {
                        $rules=$s['jedv_conditions']??[]; if(!$rules)throw new \RuntimeException('No conditions');
                        $parts=array_map([self::class,'translate'],$rules);
                        $d=count($parts)===1?$parts[0]:['source'=>'combined','relation'=>strtoupper($s['jedv_relation']??'AND')==='OR'?'any':'all','conditions'=>$parts];
                        $slug=''; foreach($all as $existing=>$definition){unset($definition['name']); if($definition===$d){$slug=$existing;break;}}
                        if(!$slug){$name=self::name($d);$slug=sanitize_title($name);if(isset($all[$slug]))$slug.='-'.substr(hash('sha256',wp_json_encode($d)),0,8);$all[$slug]=$d+['name'=>$name];}
                        if(!empty($s['hws_display_condition']))throw new \RuntimeException('Existing HWS condition requires review');
                        $s['hws_display_condition']=$slug; $s['hws_display_condition_hide']=($s['jedv_type']??'show')==='hide'?'yes':''; ++$count;
                    }catch(\RuntimeException|\InvalidArgumentException $e){$errors[]=['document'=>(int)$id,'element'=>$node['id'],'error'=>$e->getMessage()];continue;}
                }
                foreach($keys as $key){unset($s[$key]);++$removed;}
                if(isset($s['__dynamic__']))foreach(array_keys($s['__dynamic__']) as $key)if(str_starts_with($key,'jedv_'))unset($s['__dynamic__'][$key]);
                if(!empty($node['elements']))$walk($node['elements']);
            }};
            $walk($data);
            if($count||$removed){$plans[$id]=[$raw,$data];$report[]=['template'=>(int)$id,'title'=>get_the_title($id),'migrated'=>$count,'removed_keys'=>$removed];}
        }
        if(!$dry){
            foreach($plans as $id=>[$raw,$data])if(get_post_meta($id,'_elementor_data',true)!==$raw)throw new \RuntimeException('Document changed during migration: '.$id);
            update_option(Conditions::OPTION,$all,false);
            foreach($plans as $id=>[$raw,$data]){update_post_meta($id,'_elementor_data',wp_slash(wp_json_encode($data)));delete_post_meta($id,'_elementor_element_cache');}
            Conditions::clear();
        }
        return ['dry_run'=>$dry,'conditions'=>$all,'templates'=>$report,'unsupported'=>$errors];
    }
}
