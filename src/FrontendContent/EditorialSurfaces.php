<?php
namespace HWS\BaseTools\FrontendContent;
use WP_Term;
use WP_Post;
use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\ShortcodeRegistry\{ShortcodeRegistry,ShortcodeDefinition};
/** Explicit unstyled editorial surfaces. Settings affect no site until configured. */
final class EditorialSurfaces implements ModuleInterface {
 public const OPTION='hws_base_tools_editorial_surfaces';
 public static function settings(): array {return (array)get_option(self::OPTION,[]);}
 public function register(): void {
 $registry=new ShortcodeRegistry();
 foreach(['hws_archive_field'=>'field','hws_archive_breadcrumbs'=>'breadcrumbs','hws_archive_cloud'=>'cloud','hws_archive_item_field'=>'item_field','hws_archive_item_excerpt'=>'excerpt','hws_page_breadcrumbs'=>'page_breadcrumbs','hws_page_toc'=>'toc','hws_page_field'=>'page_field'] as $tag=>$method){
 $registry->add(new ShortcodeDefinition($tag,ucwords(str_replace('_',' ',$tag)),'['.$tag.']','Unstyled editorial field or navigation.','Uses current WordPress context.','0','Current context'));
 add_shortcode($tag,[self::class,$method]);}
 add_filter('elementor/query/get_query_args/current_query',[self::class,'query'],35);
 add_action('elementor/frontend/widget/before_render',[self::class,'begin_widget']);
 add_action('elementor/frontend/widget/after_render',[self::class,'end_widget']);
 add_filter('the_content',[self::class,'anchors'],12);
 }
 public static function page_field(array $atts=[]): string {return ($atts['name']??'')==='deck'&&self::page()?esc_html(self::page()->post_excerpt):'';}
 /** Explicit widget sizes, scoped to the widget currently rendering. */
 private static array $widget_sizes = [];
 public static function begin_widget($widget): void {
     if (empty(self::settings()['current_query'])) return;
     $raw = (array) $widget->get_data('settings');
     foreach (['post_query_', 'posts_', 'query_'] as $prefix) {
         if ($widget->get_settings($prefix . 'post_type') !== 'current_query') continue;
         $size = $raw[$prefix . 'posts_per_page'] ?? $raw['posts_per_page'] ?? null;
         self::$widget_sizes[spl_object_id($widget)] = is_numeric($size) && (int)$size !== 0 ? (int)$size : null;
         break;
     }
 }
 public static function end_widget($widget): void {
     unset(self::$widget_sizes[spl_object_id($widget)]);
 }
 public static function query(array $vars): array {
     $s = self::settings();
     if (empty($s['current_query'])) return $vars;
     $term = self::term();
     if (!$term && !is_search() && !is_author() && !is_category() && !is_tag()) return $vars;
     $sizes = self::$widget_sizes;
     $size = $sizes ? end($sizes) : null;
     // Explicit widget size, configured editorial size, then the site's Reading setting.
     $size ??= $s['posts_per_page'] ?? get_option('posts_per_page', 10);
     $vars['posts_per_page'] = (int)$size === -1 ? -1 : max(1, (int)$size);
     // WordPress gives an archive-specific value precedence over posts_per_page.
     if (isset($vars['posts_per_archive_page'])) $vars['posts_per_archive_page'] = $vars['posts_per_page'];
     if ($term) {
         $vars['post_status'] = 'publish';
         $vars['ignore_sticky_posts'] = true;
         $vars['tax_query'] = [['taxonomy'=>$term->taxonomy,'field'=>'term_id','terms'=>[(int)$term->term_id],'operator'=>'IN']];
     }
     return $vars;
 }
 public static function anchors(string $content): string {
 if(empty(self::settings()['page_anchors'])||!is_singular('page')||!in_the_loop()||!is_main_query())return $content;
 $rows=self::heading_rows();$index=0;
 return (string)preg_replace_callback('/<h([23])\b([^>]*)>/iu',static function($m)use(&$index,$rows){$row=$rows[$index++]??null;if(!$row||preg_match('/\sid\s*=/iu',$m[2]))return $m[0];return sprintf('<h%1$s%2$s id="%3$s">',$m[1],$m[2],esc_attr($row['id']));},$content);
 }
public static function term(): ?WP_Term {
    $queried = get_queried_object();
    if ($queried instanceof WP_Term && $queried->taxonomy === (self::settings()['taxonomy']??'post_tag')) {
        return $queried;
    }

    return null;
}
public static function url(?WP_Term $term = null): string {
    $term ??= self::term();
    if (!$term) {
        return '';
    }
    $url = get_term_link($term);
    return is_wp_error($url) ? '' : (string) $url;
}
public static function total_terms(): int {
    $count = wp_count_terms(['taxonomy' => self::settings()['taxonomy']??'post_tag', 'hide_empty' => true]);
    return is_wp_error($count) ? 0 : max(0, (int) $count);
}
public static function field(array $atts = []): string {
    $atts = shortcode_atts(['name' => ''], $atts, 'hws_archive_field');
    $term = self::term();
    if (!$term) {
        return '';
    }

    $name = sanitize_key((string) $atts['name']);
    $count = max(0, (int) $term->count);
    $total = self::total_terms();
    $page = max(1, absint(get_query_var('paged')));
    $pages = max(1, (int) ceil($count / max(1,(int)(self::settings()['posts_per_page']??10))));

    return match ($name) {
        'term_name' => esc_html($term->name),
        'term_count' => esc_html(number_format_i18n($count)),
        'total_terms' => esc_html(number_format_i18n($total)),
        'archive_url' => esc_url(self::url($term)),
        'results_heading' => esc_html(sprintf(__('Posts tagged %s', 'hws-base-tools'), $term->name)),
        'meta' => esc_html(sprintf(
            _n('%1$s post tagged · one of %2$s tags', '%1$s posts tagged · one of %2$s tags', $count, 'hws-base-tools'),
            number_format_i18n($count),
            number_format_i18n($total)
        )),
        'pagination_summary' => esc_html(sprintf(
            __('Page %1$s of %2$s · %3$s posts tagged %4$s', 'hws-base-tools'),
            number_format_i18n(min($page, $pages)),
            number_format_i18n($pages),
            number_format_i18n($count),
            $term->name
        )),
        default => '',
    };
}
public static function breadcrumbs(array $atts = []): string {
    $class=esc_attr((string)($atts['class']??'hws-archive-breadcrumbs'));
    $term = self::term();
    if (!$term) {
        return '';
    }

    return sprintf(
        '<nav class="' . $class . '" aria-label="%1$s"><ol>'
        . '<li><a href="%2$s">%3$s</a></li><li aria-hidden="true">›</li>'
        . '<li><span>%4$s</span></li><li aria-hidden="true">›</li>'
        . '<li aria-current="page">%5$s</li></ol></nav>',
        esc_attr__('Breadcrumb', 'hws-base-tools'),
        esc_url(home_url('/')),
        esc_html__('Home', 'hws-base-tools'),
        esc_html__('Tags', 'hws-base-tools'),
        esc_html($term->name)
    );
}
public static function cloud(array $atts = []): string {
    $class=esc_attr((string)($atts['class']??'hws-archive-cloud'));
    $atts = shortcode_atts(['limit' => 10], $atts, 'hws_archive_cloud');
    $limit = min(30, max(1, absint($atts['limit'])));
    $current = self::term();
    $terms = get_terms([
        'taxonomy' => self::settings()['taxonomy']??'post_tag',
        'hide_empty' => true,
        'number' => $limit,
        'orderby' => 'count',
        'order' => 'DESC',
    ]);
    if (is_wp_error($terms) || $terms === []) {
        return '';
    }

    $largest = max(1, max(array_map(static fn(WP_Term $term): int => (int) $term->count, $terms)));
    $links = [];
    foreach ($terms as $term) {
        if (!$term instanceof WP_Term) {
            continue;
        }
        $url = get_term_link($term);
        if (is_wp_error($url)) {
            continue;
        }
        $scale = min(4, max(1, (int) ceil(((int) $term->count / $largest) * 4)));
        $is_current = $current instanceof WP_Term && (int) $current->term_id === (int) $term->term_id;
        $content = sprintf(
            '<span>%1$s</span><em>%2$s<span class="screen-reader-text"> %3$s</span></em>',
            esc_html($term->name),
            esc_html(number_format_i18n((int) $term->count)),
            esc_html(_n('post', 'posts', (int) $term->count, 'hws-base-tools'))
        );
        $links[] = $is_current
            ? sprintf('<span class="' . $class . '__item ' . $class . '__item--current ' . $class . '__item--s%1$d" aria-current="page">%2$s</span>', $scale, $content)
            : sprintf('<a class="' . $class . '__item ' . $class . '__item--s%1$d" href="%2$s">%3$s</a>', $scale, esc_url($url), $content);
    }

    return '<nav class="' . $class . '" aria-label="' . esc_attr__('Browse tags', 'hws-base-tools') . '">'
        . implode('', $links)
        . '</nav><p class="' . $class . '__note">'
        . esc_html(sprintf(__('%1$s of %2$s tags, sized by post count.', 'hws-base-tools'), count($links), self::total_terms()))
        . '</p>';
}
public static function item_field(array $atts = []): string {
    $atts = shortcode_atts(['name' => ''], $atts, 'hws_archive_item_field');
    $name = sanitize_key((string) $atts['name']);
    $post_id = get_the_ID();
    if (!$post_id) {
        return '';
    }

    if($name==='type_label'){$type=get_post_type($post_id);$object=get_post_type_object($type);return esc_html(self::settings()['type_labels'][$type]??($object->labels->singular_name??''));}

    if ($name === 'topic') {
        $default = absint(get_option('default_category'));
        $terms = wp_get_post_categories($post_id, ['fields' => 'all']);
        foreach ($terms as $term) {
            if ($term instanceof WP_Term && (int) $term->term_id !== $default) {
                return esc_html($term->name);
            }
        }
    }

    return '';
}
public static function excerpt(array $atts = []): string {
    $atts = shortcode_atts(['words' => 30], $atts, 'hws_archive_item_excerpt');
    $post_id = get_the_ID();
    if (!$post_id) {
        return '';
    }
    $words = min(60, max(12, absint($atts['words'])));
    $excerpt = trim(wp_strip_all_tags((string) get_the_excerpt($post_id)));
    if ($excerpt === '') {
        $excerpt = trim(wp_strip_all_tags(strip_shortcodes((string) get_post_field('post_content', $post_id))));
    }
    return $excerpt === '' ? '' : esc_html(wp_trim_words($excerpt, $words, '…'));
}
public static function page(): ?WP_Post {
    $post = get_post();
    return $post instanceof WP_Post && $post->post_type === 'page' ? $post : null;
}
public static function page_breadcrumbs(array $atts = []): string {
    $class=esc_attr((string)($atts['class']??'hws-page-breadcrumbs'));
    $page = self::page();
    if (!$page) {
        return '';
    }

    $items = [sprintf('<li><a href="%1$s">%2$s</a></li>', esc_url(home_url('/')), esc_html__('Home', 'hws-base-tools'))];
    $ancestors = array_reverse(get_post_ancestors($page));
    foreach ($ancestors as $ancestor_id) {
        $items[] = '<li aria-hidden="true">›</li>';
        $items[] = sprintf('<li><a href="%1$s">%2$s</a></li>', esc_url(get_permalink($ancestor_id)), esc_html(get_the_title($ancestor_id)));
    }
    $items[] = '<li aria-hidden="true">›</li>';
    $items[] = sprintf('<li aria-current="page">%s</li>', esc_html(get_the_title($page)));
    return '<nav class="' . $class . '" aria-label="' . esc_attr__('Breadcrumb', 'hws-base-tools') . '"><ol>' . implode('', $items) . '</ol></nav>';
}
public static function heading_rows(?WP_Post $page = null): array {
    $page ??= self::page();
    if (!$page) {
        return [];
    }
    preg_match_all('/<h([23])\b[^>]*>(.*?)<\/h\1>/isu', (string) $page->post_content, $matches, PREG_SET_ORDER);
    $rows = [];
    $used = [];
    foreach ($matches as $match) {
        $text = trim(wp_strip_all_tags((string) $match[2]));
        if ($text === '') {
            continue;
        }
        $base = sanitize_title($text) ?: 'section';
        $id = $base;
        $suffix = 2;
        while (isset($used[$id])) {
            $id = $base . '-' . $suffix++;
        }
        $used[$id] = true;
        $rows[] = ['level' => (int) $match[1], 'text' => $text, 'id' => $id];
    }
    return $rows;
}
public static function toc(array $atts = []): string {
    $class=esc_attr((string)($atts['class']??'hws-page-toc'));
    $rows = self::heading_rows();
    if ($rows === []) {
        return '<p class="' . $class . '__empty">' . esc_html__('This page is a short read.', 'hws-base-tools') . '</p>';
    }
    $items = [];
    foreach ($rows as $row) {
        $items[] = sprintf(
            '<li class="' . $class . '__level-%1$d"><a href="#%2$s">%3$s</a></li>',
            $row['level'],
            esc_attr($row['id']),
            esc_html($row['text'])
        );
    }
    return '<nav class="' . $class . '" aria-label="' . esc_attr__('On this page', 'hws-base-tools') . '"><ol>' . implode('', $items) . '</ol></nav>';
}
}
