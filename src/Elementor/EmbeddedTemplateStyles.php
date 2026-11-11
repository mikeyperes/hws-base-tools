<?php
declare(strict_types=1);
namespace HWS\BaseTools\Elementor;
use Hexa\PluginCore\CoreContracts\ModuleInterface;
defined('ABSPATH') || exit;
/**
 * Elementor prints the CSS of a template placed by a Template widget or a loop
 * grid only when it renders it, at the end of the body: the page first paints
 * unstyled, then jumps. This enqueues those templates' CSS and their widgets'
 * styles in <head>, so the first paint is already styled. Output is unchanged.
 */
final class EmbeddedTemplateStyles implements ModuleInterface {
    public function register(): void {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue'], 20);
    }
    public static function enqueue(): void {
        if (!is_singular() || !class_exists('\Elementor\Plugin') || !class_exists('\Elementor\Core\Files\CSS\Post')) return;
        $seen = []; $types = [];
        self::walk((int) get_queried_object_id(), $seen, $types, 0);
        unset($seen[(int) get_queried_object_id()]);
        foreach (array_keys($seen) as $id) \Elementor\Core\Files\CSS\Post::create($id)->enqueue();
        foreach (array_keys($types) as $type) {
            if (wp_style_is('widget-' . $type, 'registered')) wp_enqueue_style('widget-' . $type);
        }
    }
    private static function walk(int $id, array &$seen, array &$types, int $depth): void {
        if ($id <= 0 || isset($seen[$id]) || $depth > 6) return;
        $seen[$id] = true;
        $doc = \Elementor\Plugin::$instance->documents->get($id);
        if (!$doc || !$doc->is_built_with_elementor()) return;
        $rows = function (array $els) use (&$rows, &$seen, &$types, $depth): void {
            foreach ($els as $el) {
                $type = (string) ($el['widgetType'] ?? '');
                if ($type !== '') $types[$type] = true;
                $s = (array) ($el['settings'] ?? []);
                // A Template widget, and a loop grid or carousel's card template.
                foreach (['template_id', '_skin_template_id', 'template_id_loop'] as $key) {
                    if (!empty($s[$key]) && is_numeric($s[$key])) self::walk((int) $s[$key], $seen, $types, $depth + 1);
                }
                $rows((array) ($el['elements'] ?? []));
            }
        };
        $rows((array) $doc->get_elements_data());
    }
}
