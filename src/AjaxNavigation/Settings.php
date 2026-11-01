<?php

declare(strict_types=1);
namespace HWS\BaseTools\AjaxNavigation;

final class Settings {
    public const OPTION = 'hws_base_tools_ajax_navigation';
    public const DEFAULT_EXCLUDED = ['/wp-admin', '/wp-login.php', '/wp-json', '/feed', '/cart', '/checkout', '/my-account', '/account'];

    public static function get(): array {
        return self::sanitize((array) get_option(self::OPTION, []));
    }

    public static function sanitize(array $input): array {
        $paths = is_array($input['excluded_paths'] ?? null) ? $input['excluded_paths'] : preg_split('/[\r\n]+/', (string) ($input['excluded_paths'] ?? ''));
        $paths = array_filter(array_map(static function ($path): string {
            if (!is_scalar($path)) return '';
            $path = trim((string) $path);
            if (!str_starts_with($path, '/') || str_starts_with($path, '//') || preg_match('/[<>?#\x00-\x20]/', $path)) return '';
            return rtrim($path, '/') ?: '/';
        }, $paths ?: []));
        return [
            'analytics' => ($input['analytics'] ?? 'manual') === 'history' ? 'history' : 'manual',
            'enabled' => in_array($input['enabled'] ?? false, [true, 1, '1', 'on'], true),
            'excluded_paths' => array_values(array_unique(array_merge(self::DEFAULT_EXCLUDED, $paths))),
        ];
    }

    public static function excluded(string $url, array $paths): bool {
        $path = strtolower(rawurldecode((string) parse_url($url, PHP_URL_PATH)));
        if (preg_match('~(?:^|/)(?:feed|wp-admin)(?:/|$)|\.[a-z0-9]{1,8}$~', $path)) return true;
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        foreach (['elementor-preview', 'preview', 'preview_id', 'feed', 'rest_route', 'add-to-cart', 'wc-ajax', 'customize_changeset_uuid'] as $key) {
            if (array_key_exists($key, $query)) return true;
        }
        if (isset($query['action']) && in_array($query['action'], ['elementor', 'edit'], true)) return true;
        foreach ($paths as $prefix) {
            $prefix = strtolower(rtrim($prefix, '/'));
            if ($prefix === '' || $path === $prefix || str_starts_with($path, $prefix . '/')) return true;
        }
        return false;
    }
}
