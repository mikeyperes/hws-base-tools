<?php
namespace HWS\BaseTools\DisplayConditions;
use Hexa\PluginCore\WpAdminComponents\CoreUi;
final class Admin {
    public static function save(): void {
        if(!current_user_can('manage_options'))wp_die('Not allowed.',403);
        check_admin_referer('hws_base_tools_display_conditions');
        try {
            if(isset($_POST['toggle'])) update_option(Conditions::ENABLED,isset($_POST['enabled']),false);
            elseif(isset($_POST['delete'])) { $all=Conditions::all(); unset($all[sanitize_title(wp_unslash($_POST['slug']))]); update_option(Conditions::OPTION,$all,false); }
            else {
                $d=isset($_POST['source']) ? ['name'=>sanitize_text_field(wp_unslash($_POST['condition_name']??'')),'source'=>sanitize_key($_POST['source']),'key'=>wp_unslash($_POST['key']??''),'rule'=>sanitize_key($_POST['rule']??''),'value'=>wp_unslash($_POST['value']??'')] : json_decode(wp_unslash($_POST['definition']??''),true);
                if(!is_array($d))throw new \InvalidArgumentException('Definition must be a JSON object.');
                Conditions::save(sanitize_title(wp_unslash($_POST['slug']??'')),$d);
            }
            Conditions::clear();
        } catch(\InvalidArgumentException $e){wp_die(esc_html($e->getMessage()));}
        wp_safe_redirect(admin_url('admin.php?page=hws-core-tools&tab=display-conditions')); exit;
    }
    private static function start(): void {
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        echo '<input type="hidden" name="action" value="hws_base_tools_display_conditions">';
        wp_nonce_field('hws_base_tools_display_conditions');
    }
    public static function render(): void {
        if(!current_user_can('manage_options'))return;
        CoreUi::render_assets();
        echo '<div class="hpc-ui"><h2>Display Conditions</h2><p>Define once, then choose a condition under Advanced in Elementor. The editor always shows every element.</p>';
        self::start(); echo '<h3>Module</h3><input type="hidden" name="toggle" value="1">';
        echo CoreUi::toggle('enabled',(bool)get_option(Conditions::ENABLED,false),'Enable Display Conditions');
        submit_button('Save module setting'); echo '</form>';
        foreach(Conditions::all() as $slug=>$d){
            self::start(); echo '<h3>'.esc_html($d['name']).'</h3><label>Slug <input readonly name="slug" value="'.esc_attr($slug).'"></label>';
            self::fields($d);
            submit_button('Save condition','primary','submit',false); echo ' <button class="button" name="delete" value="1">Delete condition</button></form>';
        }
        self::start(); echo '<h3>Add condition</h3><p><label>Slug <input required name="slug"></label></p>';
        self::fields(['name'=>'','source'=>'custom_field','key'=>'','rule'=>'has_value','value'=>'']);
        submit_button('Add condition'); echo '</form></div>';
    }
    private static function fields(array $d): void {
        if($d['source']==='combined'){
            echo '<p><label>Combined definition (JSON)<br><textarea name="definition" rows="12" cols="75">'.esc_textarea(wp_json_encode($d,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)).'</textarea></label></p>';return;
        }
        echo '<p><label>Name <input name="condition_name" required value="'.esc_attr($d['name']).'"></label></p>';
        foreach(['source'=>['custom_field'=>'Current post custom field','shortcode'=>'Shortcode output','featured_image'=>'Featured image'],'rule'=>['has_value'=>'Has a value','is_empty'=>'Is empty','equals'=>'Equals a given value']] as $field=>$choices){
            echo '<p><label>'.esc_html(ucfirst($field)).' <select name="'.esc_attr($field).'">';
            foreach($choices as $value=>$label)echo '<option value="'.esc_attr($value).'" '.selected($d[$field],$value,false).'>'.esc_html($label).'</option>';
            echo '</select></label></p>';
        }
        echo '<p><label>Custom field key or shortcode <input class="large-text" name="key" value="'.esc_attr($d['key']).'"></label></p><p>Leave blank for featured image. Shortcodes run for the current post.</p><p><label>Equals value <input name="value" value="'.esc_attr($d['value']).'"></label></p>';
    }
}
