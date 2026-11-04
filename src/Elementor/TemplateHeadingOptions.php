<?php
declare(strict_types=1);
namespace HWS\BaseTools\Elementor;
use Hexa\PluginCore\CoreContracts\ModuleInterface;
defined('ABSPATH') || exit;
final class TemplateHeadingOptions implements ModuleInterface {
    private static array $placements = [];
    public function register(): void {
        add_action('elementor/element/template/section_template/after_section_end', [self::class, 'controls']);
        add_action('elementor/frontend/widget/before_render', [self::class, 'enter']);
        add_action('elementor/frontend/widget/after_render', [self::class, 'leave']);
        add_shortcode('hws_heading_field', [self::class, 'field']);
    }
    public static function controls($widget): void {
        $widget->start_controls_section('hws_heading_options', ['label'=>__('Shared heading options','hws-base-tools')]);
        foreach(['eyebrow'=>'Eyebrow text','intro'=>'Intro text'] as $key=>$label) {
            $widget->add_control('hws_heading_'.$key, ['label'=>__($label,'hws-base-tools'),'type'=>$key==='intro'?\Elementor\Controls_Manager::TEXTAREA:\Elementor\Controls_Manager::TEXT,'default'=>'','dynamic'=>['active'=>true]]);
        }
        $widget->add_control('hws_heading_intro_width', ['label'=>__('Intro max-width (px)','hws-base-tools'),'type'=>\Elementor\Controls_Manager::NUMBER,'min'=>0,'max'=>1600,'default'=>'','selectors'=>['{{WRAPPER}}'=>'--hws-heading-intro-width-override: {{VALUE}}px;'],'description'=>__('Empty follows the heading profile (600px, Contact 520px).','hws-base-tools')]);
        $widget->add_control('hws_heading_variant', ['label'=>__('Title style variant','hws-base-tools'),'type'=>\Elementor\Controls_Manager::SELECT,'default'=>'ui','options'=>['ui'=>'UI','display'=>'Display','newsroom'=>'Newsroom']]);
        $widget->add_control('hws_heading_padding', ['label'=>__('Padding set','hws-base-tools'),'type'=>\Elementor\Controls_Manager::SELECT,'default'=>'default','options'=>['default'=>'Default (40/36, 32/28, 28/24)','newsroom'=>'Newsroom (48, 36, 26)']]);
        $widget->add_control('hws_heading_spacing', ['label'=>__('Text spacing set','hws-base-tools'),'type'=>\Elementor\Controls_Manager::SELECT,'default'=>'default','options'=>['default'=>'Follow title variant','contact'=>'Contact (14px, 520px intro)']]);
        $widget->add_control('hws_heading_role_field', ['label'=>__('Optional role custom field','hws-base-tools'),'type'=>\Elementor\Controls_Manager::TEXT,'default'=>'executive_role','description'=>__('Empty fields produce no role line.','hws-base-tools')]);
        $widget->end_controls_section();
    }
    public static function enter($widget): void {
        if ($widget->get_name() !== 'template') return;
        $s=$widget->get_settings_for_display();
        self::$placements[]=['widget'=>$widget,'settings'=>$s];
        $variant=in_array($s['hws_heading_variant']??'', ['display','newsroom'],true)?$s['hws_heading_variant']:'ui';
        $padding=($s['hws_heading_padding']??'')==='newsroom'?'newsroom':'default';
        $spacing=($s['hws_heading_spacing']??'')==='contact'?'contact':'default';
        $widget->add_render_attribute('_wrapper','class', ['hws-heading-variant-'.$variant,'hws-heading-padding-'.$padding,'hws-heading-spacing-'.$spacing]);
    }
    public static function leave($widget): void {
        $last=end(self::$placements);
        if($last && $last['widget']===$widget) array_pop(self::$placements);
    }
    public static function plain_title(): string {
        if(is_search()) return (string)get_search_query(false);
        $object=get_queried_object();
        if($object instanceof \WP_Term) return (string)$object->name;
        if($object instanceof \WP_User) return (string)$object->display_name;
        if(is_singular() && $object instanceof \WP_Post) return (string)$object->post_title;
        if(is_post_type_archive()) return (string)post_type_archive_title('',false);
        if(is_home()) return get_the_title((int)get_option('page_for_posts'));
        return '';
    }
    public static function field($atts=[]): string {
        $name=sanitize_key((string)($atts['name']??''));
        $placement=end(self::$placements);$s=$placement['settings']??[];
        if($name==='title') return esc_html(self::plain_title());
        if($name==='role'){
            if(!is_singular())return '';
            $key=sanitize_key((string)($s['hws_heading_role_field']??'executive_role'));
            $value=$key!==''?get_post_meta(get_queried_object_id(),$key,true):'';
            return is_scalar($value)?esc_html((string)$value):'';
        }
        if(!in_array($name,['eyebrow','intro'],true))return '';
        $value=$s['hws_heading_'.$name]??'';
        if($name==='intro' && (!is_scalar($value)||trim((string)$value)==='') && is_singular()){
            $object=get_queried_object();
            $value=$object instanceof \WP_Post ? $object->post_excerpt : '';
        }
        return is_scalar($value)?esc_html((string)$value):'';
    }
}
