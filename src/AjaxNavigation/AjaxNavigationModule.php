<?php

declare(strict_types=1);
namespace HWS\BaseTools\AjaxNavigation;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;
use HWS\BaseTools\PluginRuntime\PluginMetadata;

final class AjaxNavigationModule implements ModuleInterface {
    public function register(): void {
        add_action('template_redirect', [self::class, 'prepare'], 0);
        add_action('update_option_smp_podcast_playback_settings', [self::class, 'player_settings_changed'], 10, 2);
        add_filter('litespeed_optimize_js_excludes', [self::class, 'optimization_exclusions']);
        add_filter('litespeed_optm_js_defer_exc', [self::class, 'optimization_exclusions']);
        add_filter('script_loader_tag', [self::class, 'track_runtime'], 10, 2);
        AjaxActionRegistry::create(['action_prefix' => 'hws_base_tools', 'nonce_action' => 'hws_base_tools_ajax_navigation', 'nonce_field' => 'nonce'])
            ->register(['ajax_navigation_save' => ['callback' => [self::class, 'save']]]);
    }

    public static function allowed(): bool {
        if (!Settings::get()['enabled'] || self::navigation_conflict() || is_admin() || is_user_logged_in() || is_feed() || is_preview() || is_404() || is_embed() || is_trackback()) return false;
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return false;
        if (Settings::excluded($_SERVER['REQUEST_URI'] ?? '/', Settings::get()['excluded_paths'])) return false;
        foreach (['is_cart', 'is_checkout', 'is_account_page'] as $fn) if (function_exists($fn) && $fn()) return false;
        return (bool) apply_filters('hws_base_tools_ajax_navigation_allowed', true);
    }

    /** Only an enabled player with its navigation switch on owns navigation. */
    public static function navigation_conflict(): bool {
        if (!class_exists(\SMP\Podcast\Settings\PlaybackSettings::class)) return false;
        $player = \SMP\Podcast\Settings\PlaybackSettings::get();
        return !empty($player['enabled']) && !empty($player['ajax_navigation']);
    }

    public static function player_settings_changed(mixed $before, mixed $after): void {
        if (!Settings::get()['enabled']) return;
        $before = is_array($before) ? $before : [];
        $after = is_array($after) ? $after : [];
        if (($before['enabled'] ?? false) !== ($after['enabled'] ?? false)
            || ($before['ajax_navigation'] ?? true) !== ($after['ajax_navigation'] ?? true)) {
            do_action('litespeed_purge_all');
        }
    }

    public static function prepare(): void {
        if (!Settings::get()['enabled']) return;
        if (!self::allowed() || !class_exists('WP_HTML_Tag_Processor')) {
            add_action('wp_head', static function (): void { echo '<meta name="turbo-visit-control" content="reload">'; }, 0);
            return;
        }
        add_action('wp_head', static function (): void {
            echo '<meta name="hws-ajax-navigation" content="1"><meta name="turbo-cache-control" content="no-cache"><meta name="turbo-prefetch" content="false">';
        }, 0);
        add_action('wp_enqueue_scripts', [self::class, 'assets'], 100);
        // Direct loads ignore data-turbo-eval. Drive defers script evaluation to our ordered adapter.
        ob_start([self::class, 'inert_turbo_scripts']);
    }

    public static function inert_turbo_scripts(string $html): string {
        if (!str_contains(strtolower($html), '<html')) return $html;
        if (!class_exists('WP_HTML_Tag_Processor')) return $html;
        $processor = new \WP_HTML_Tag_Processor($html);
        while ($processor->next_tag()) {
            $tag = $processor->get_tag();
            if ($tag === 'SCRIPT') $processor->set_attribute('data-turbo-eval', 'false');
            elseif ($tag === 'STYLE' || ($tag === 'LINK' && strtolower((string) $processor->get_attribute('rel')) === 'stylesheet')) {
                $processor->set_attribute('data-hws-ajax-style', '');
            }
        }
        return $processor->get_updated_html();
    }

    public static function optimization_exclusions(array $paths): array {
        if (!Settings::get()['enabled']) return $paths;
        return array_values(array_unique(array_merge($paths, ['assets/ajax-navigation/navigation.js', 'assets/ajax-navigation/vendor/turbo.js'])));
    }

    public static function track_runtime(string $tag, string $handle): string {
        if (in_array($handle, ['hws-ajax-navigation', 'hws-turbo'], true)) {
            return str_replace('<script ', '<script data-turbo-track="reload" ', $tag);
        }
        return $tag;
    }

    public static function assets(): void {
        $base = plugins_url('assets/ajax-navigation/', PluginMetadata::plugin_file());
        $revision = PluginMetadata::VERSION . '-' . substr(hash_file('sha256', PluginMetadata::root_path() . '/assets/ajax-navigation/navigation.js'), 0, 12);
        wp_enqueue_script('hws-ajax-navigation', $base . 'navigation.js', [], $revision, false);
        wp_add_inline_script('hws-ajax-navigation', 'window.hwsAjaxNavigationConfig=' . wp_json_encode([
            'excludedPaths' => Settings::get()['excluded_paths'], 'analytics' => Settings::get()['analytics'], 'timeout' => 12000,
        ]) . ';', 'before');
        wp_enqueue_script('hws-turbo', $base . 'vendor/turbo.js', ['hws-ajax-navigation'], '8.0.23', false);
    }

    public static function save(AjaxRequest $request): array {
        $settings = Settings::sanitize(['analytics' => $request->text('analytics', 'manual', 'post'), 'enabled' => $request->bool('enabled', false, 'post'), 'excluded_paths' => $request->raw('excluded_paths', '', 'post')]);
        update_option(Settings::OPTION, $settings, false);
        do_action('litespeed_purge_all');
        $blocked = $settings['enabled'] && self::navigation_conflict();
        return ['message' => $blocked
            ? 'Settings saved, but AJAX navigation is blocked: turn off the podcast player AJAX navigation setting first.'
            : 'AJAX navigation settings saved. Page caches cleared.', 'settings' => $settings, 'blocked' => $blocked];
    }
}
