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
        add_shortcode('hws_heading_facts', [self::class, 'facts']);
        add_filter('rank_math/frontend/breadcrumb/items', [self::class, 'breadcrumb_items'], 20);
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
        $widget->add_control('hws_heading_facts', ['label'=>__('Heading facts','hws-base-tools'),'type'=>\Elementor\Controls_Manager::TEXTAREA,'default'=>'','description'=>__('One Label|Value per line. Empty hides the facts. Values may contain safe links.','hws-base-tools')]);
        $widget->add_control('hws_heading_facts_style', ['label'=>__('Facts style','hws-base-tools'),'type'=>\Elementor\Controls_Manager::SELECT,'default'=>'about','options'=>['about'=>'About (vertical definition list)','team'=>'Team (wrapping facts row)']]);
        $widget->add_control('hws_heading_breadcrumb_middle', ['label'=>__('Breadcrumb middle crumbs','hws-base-tools'),'type'=>\Elementor\Controls_Manager::TEXTAREA,'default'=>'','description'=>__('Optional: one Label|https://example.com/path/ pair per line. Home and the current title are automatic. Empty preserves Rank Math.','hws-base-tools')]);
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
    /** Data and semantic markup only; the saved heading owns the scoped skin. */
    public static function facts(): string {
        $placement=end(self::$placements);$settings=$placement['settings']??[];
        $raw=$settings['hws_heading_facts']??'';
        if(!is_scalar($raw)||trim((string)$raw)==='')return '';
        $rows='';
        foreach(preg_split('/\R/u',trim((string)$raw))?:[]as$line){
            if(trim($line)==='')continue;
            $parts=explode('|',$line,2);
            if(count($parts)!==2)continue;
            $label=sanitize_text_field(trim($parts[0]));
            $value=wp_kses(trim($parts[1]),['a'=>['href'=>true,'target'=>true,'rel'=>true,'class'=>true]]);
            if($label===''||trim(wp_strip_all_tags($value))==='')continue;
            if(class_exists('WP_HTML_Tag_Processor')){
                $tags=new \WP_HTML_Tag_Processor($value);
                while($tags->next_tag('a')){
                    $url=(string)$tags->get_attribute('href');
                    if($url!==''&&wp_parse_url($url,PHP_URL_HOST)!==wp_parse_url(home_url('/'),PHP_URL_HOST)){
                        $tags->set_attribute('target','_blank');
                        $tags->set_attribute('rel','noopener noreferrer');
                    }
                }
                $value=$tags->get_updated_html();
            }
            $rows.='<div><dt>'.esc_html($label).'</dt><dd>'.$value.'</dd></div>';
        }
        if($rows==='')return '';
        $class=($settings['hws_heading_facts_style']??'about')==='team'?'th-facts':'ph-facts';
        return '<dl class="hws-heading-facts '.$class.'">'.$rows.'</dl>';
    }
    /** Replace only the displayed trail inside the opted-in Template placement. */
    public static function breadcrumb_items(array $items): array {
        $placement=end(self::$placements);
        $raw=$placement['settings']['hws_heading_breadcrumb_middle']??'';
        if(!is_scalar($raw)||trim((string)$raw)==='') return $items;
        $title=self::plain_title();
        if($title==='') return $items;
        $middle=[];
        foreach(preg_split('/\R/u',trim((string)$raw))?:[] as $line){
            if(trim($line)==='') continue;
            $parts=explode('|',$line,2);
            if(count($parts)!==2) return $items;
            $label=sanitize_text_field(trim($parts[0]));
            $url=esc_url_raw(trim($parts[1]),['http','https']);
            $host=wp_parse_url($url,PHP_URL_HOST);
            if($label===''||$url===''||!is_string($host)||$host==='') return $items;
            $middle[]=[$label,$url];
        }
        if(!$middle) return $items;
        return array_merge([[__('Home','hws-base-tools'),home_url('/')]],$middle,[[$title,'']]);
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
