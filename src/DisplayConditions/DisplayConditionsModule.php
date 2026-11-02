<?php
namespace HWS\BaseTools\DisplayConditions;
use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\WpAdminTabs\TabDefinition;
final class DisplayConditionsModule implements ModuleInterface {
    public function register(): void {
        add_filter('hws_base_tools_dashboard_tabs', static function($tabs) {
            $tabs['display-conditions'] = new TabDefinition('display-conditions','Display Conditions',[Admin::class,'render'],'manage_options'); return $tabs;
        });
        add_filter('hws_base_tools_render_dashboard_tab',static function($handled,$id){ if($id!=='display-conditions')return $handled; Admin::render();return true;},10,2);
        add_action('admin_post_hws_base_tools_display_conditions',[Admin::class,'save']);
        add_action('elementor/element/after_section_end',[self::class,'controls'],100,3);
        foreach(['widget','container','section','column'] as $type) add_filter("elementor/frontend/{$type}/should_render",[self::class,'should_render'],10,2);
        add_filter('elementor/element/is_dynamic_content',static function($dynamic,$data){ return $dynamic || (get_option(Conditions::ENABLED,false) && !empty($data['settings']['hws_display_condition'])); },10,2);
        if(defined('WP_CLI') && WP_CLI) \WP_CLI::add_command('hws display-conditions',new Command());
    }
    public static function controls($element,$section_id,$args): void {
        if(!get_option(Conditions::ENABLED,false) || (($args['tab']??'')!=='advanced' && !in_array($section_id,['section_custom_css','section_custom_css_pro','section_custom_css_promotion'],true)) || $section_id==='hws_display_conditions_section') return;
        if ($element->get_controls('hws_display_conditions_section')) {
            foreach(['hws_display_conditions_section','hws_display_condition','hws_display_condition_hide'] as $id) $element->remove_control($id);
        }
        $options=[''=>'None']; foreach(Conditions::all() as $slug=>$d)$options[$slug]=$d['name'];
        $element->start_controls_section('hws_display_conditions_section',['label'=>'Display condition','tab'=>'advanced']);
        $element->add_control('hws_display_condition',['label'=>'Condition','type'=>'select','options'=>$options,'default'=>'']);
        $element->add_control('hws_display_condition_hide',['label'=>'Hide when matched','type'=>'switcher','label_on'=>'Hide','label_off'=>'Show','return_value'=>'yes','default'=>'']);
        $element->end_controls_section();
    }
    public static function should_render($render,$element): bool {
        if(!get_option(Conditions::ENABLED,false))return (bool)$render;
        if(class_exists('Elementor\Plugin') && (\Elementor\Plugin::$instance->editor->is_edit_mode() || \Elementor\Plugin::$instance->preview->is_preview_mode()))return true;
        if(!$render)return false;
        $slug=$element->get_settings('hws_display_condition'); $all=Conditions::all();
        if(!$slug || !isset($all[$slug]))return true;
        $match=Conditions::matches($all[$slug],(int)get_the_ID());
        return $element->get_settings('hws_display_condition_hide')==='yes' ? !$match : $match;
    }
}
