<?php
namespace HWS\BaseTools\SeoSettings;
use Hexa\PluginCore\CoreContracts\ModuleInterface;
use HWS\BaseTools\FrontendContent\EditorialSurfaces;
use HWS\BaseTools\PluginRuntime\PluginMetadata;
/** Optional archive metadata templates. Empty settings preserve native SEO output. */
final class TermMetadata implements ModuleInterface {
 public const OPTION='hws_base_tools_term_metadata';
 public const ACTION='hws_base_tools_editorial_save';
 public function register(): void {
 add_filter('rank_math/frontend/description',[self::class,'description'],20);
 add_filter('rank_math/frontend/canonical',[self::class,'canonical'],20);
 add_filter('rank_math/frontend/robots',[self::class,'robots'],20);
 add_action('admin_post_'.self::ACTION,[self::class,'save']);
 }
 public static function rule(): array {$t=get_queried_object();$rules=(array)get_option(self::OPTION,[]);return $t instanceof \WP_Term?(array)($rules[$t->taxonomy]??[]):[];}
 public static function description($original): string {
 $r=self::rule();$t=get_queried_object();if(empty($r['description'])||!$t instanceof \WP_Term)return (string)$original;
 $n=max(0,(int)$t->count);$text=strtr($r['description'],['{term}'=>$t->name,'{count}'=>number_format_i18n($n),'{site_name}'=>get_bloginfo('name'),'{noun}'=>$n===1?($r['singular']??'post'):($r['plural']??'posts')]);$text=trim(wp_strip_all_tags($text));$limit=max(80,min(320,(int)($r['limit']??155)));
 return function_exists('mb_strlen')&&mb_strlen($text)>$limit?rtrim(mb_substr($text,0,$limit-3)," \t\n\r\0\x0B,.;:-").'…':$text;
 }
 public static function canonical($original){if(empty(self::rule()['canonical']))return $original;$url=get_term_link(get_queried_object());return is_wp_error($url)?$original:$url;}
 public static function robots(array $robots): array {if(empty(self::rule()['index_follow']))return $robots;$robots['index']='index';$robots['follow']='follow';unset($robots['noindex'],$robots['nofollow']);return $robots;}
 public static function save(): void {
 if(!current_user_can(PluginMetadata::ADMIN_CAPABILITY))wp_die('Permission denied');check_admin_referer(self::ACTION);
 $input=json_decode(wp_unslash((string)($_POST['configuration']??'')),true);if(!is_array($input))wp_die('Expected JSON configuration');
 $surface=(array)($input['surfaces']??[]);$safe=['taxonomy'=>sanitize_key($surface['taxonomy']??'post_tag'),'posts_per_page'=>max(1,min(100,(int)($surface['posts_per_page']??10))),'current_query'=>!empty($surface['current_query']),'page_anchors'=>!empty($surface['page_anchors']),'type_labels'=>array_map('sanitize_text_field',(array)($surface['type_labels']??[]))];
 $rules=[];foreach((array)($input['metadata']??[]) as $tax=>$rule){$tax=sanitize_key($tax);if(!taxonomy_exists($tax))continue;$rules[$tax]=['description'=>sanitize_text_field($rule['description']??''),'singular'=>sanitize_text_field($rule['singular']??'post'),'plural'=>sanitize_text_field($rule['plural']??'posts'),'limit'=>max(80,min(320,(int)($rule['limit']??155))),'canonical'=>!empty($rule['canonical']),'index_follow'=>!empty($rule['index_follow'])];}
 update_option(EditorialSurfaces::OPTION,$safe,false);update_option(self::OPTION,$rules,false);do_action('litespeed_purge_all');wp_safe_redirect(admin_url('admin.php?page='.PluginMetadata::ADMIN_PAGE_SLUG.'&tab=seo'));exit;
 }
 public static function render(): void {
 $value=['surfaces'=>EditorialSurfaces::settings(),'metadata'=>get_option(self::OPTION,[])];
 echo '<h3>Editorial surfaces and archive metadata</h3><p>Unstyled archive fields, page contents lists and optional SEO templates. All automatic changes are off until configured. See docs/editorial-surfaces.md for the JSON format.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="'.esc_attr(self::ACTION).'">';wp_nonce_field(self::ACTION);echo '<label>Configuration<textarea name="configuration" rows="15" class="large-text code">'.esc_textarea(wp_json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)).'</textarea></label><p><button class="button button-primary">Save editorial configuration</button></p></form>';
 }
}
