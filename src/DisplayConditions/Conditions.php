<?php
namespace HWS\BaseTools\DisplayConditions;

/** Site definitions are independent of Elementor and reusable from PHP. */
final class Conditions {
    public const OPTION = 'hws_base_tools_display_conditions';
    public const ENABLED = 'hws_base_tools_display_conditions_enabled';
    public static function all(): array { return (array) get_option(self::OPTION, []); }
    public static function normalize(array $d, int $depth = 0): array {
        if ($depth > 8) throw new \InvalidArgumentException('Conditions are nested too deeply.');
        $source = $d['source'] ?? 'custom_field';
        if ($source === 'combined') {
            if (!in_array($d['relation'] ?? '', ['all','any'], true) || empty($d['conditions']) || !is_array($d['conditions'])) throw new \InvalidArgumentException('Combined conditions need all/any and child conditions.');
            return ['source'=>'combined','relation'=>$d['relation'],'conditions'=>array_map(fn($v)=>self::normalize($v,$depth+1), $d['conditions'])];
        }
        if (!in_array($source, ['custom_field','shortcode','featured_image'], true)) throw new \InvalidArgumentException('Unknown source.');
        $rule = $d['rule'] ?? 'has_value';
        if (!in_array($rule, ['has_value','is_empty','equals'], true)) throw new \InvalidArgumentException('Unknown rule.');
        $key = (string) ($d['key'] ?? '');
        if ($source === 'custom_field' && $key === '') throw new \InvalidArgumentException('Custom field key is required.');
        return ['source'=>$source,'key'=>$key,'rule'=>$rule,'value'=>(string)($d['value']??'')];
    }
    public static function save(string $slug, array $definition): void {
        if (!$slug || sanitize_title($slug) !== $slug) throw new \InvalidArgumentException('Use a nonempty slug.');
        $d = self::normalize($definition);
        $d['name'] = sanitize_text_field($definition['name'] ?? ucwords(str_replace('-', ' ', $slug)));
        $all = self::all(); $all[$slug] = $d; update_option(self::OPTION, $all, false); self::clear();
    }
    public static function clear(): void {
        if (class_exists('Elementor\Plugin')) \Elementor\Plugin::$instance->files_manager->clear_cache();
        do_action('litespeed_purge_all');
    }
    public static function matches(array $d, int $post_id): bool {
        if (($d['source']??'') === 'combined') {
            $values = array_map(fn($child)=>self::matches($child,$post_id),$d['conditions']);
            return $d['relation'] === 'all' ? !in_array(false,$values,true) : in_array(true,$values,true);
        }
        $value = match($d['source']) {
            'custom_field' => get_post_meta($post_id,$d['key'],true),
            'featured_image' => get_post_thumbnail_id($post_id),
            'shortcode' => do_shortcode($d['key']),
            default => '',
        };
        // The string "0" is a value, matching JetEngine exists semantics.
        $present = is_array($value) ? count($value)>0 : ($value !== null && $value !== false && trim((string)$value) !== '');
        return match($d['rule']) {
            'has_value' => $present,
            'is_empty' => !$present,
            'equals' => is_scalar($value) && (string)$value === $d['value'],
            default => false,
        };
    }
}
