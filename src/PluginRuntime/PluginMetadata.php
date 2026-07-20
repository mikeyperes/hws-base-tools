<?php

namespace HWS\BaseTools\PluginRuntime;

final class PluginMetadata {
    public const NAME = 'Hexa Web Systems - Website Base Tool';
    public const SLUG = 'hws-base-tools';
    public const ADMIN_PAGE_SLUG = 'hws-core-tools';
    public const ADMIN_CAPABILITY = 'manage_options';
    public const MAIN_FILE = 'hws-base-tools.php';
    public const GITHUB_REPOSITORY = 'mikeyperes/hws-base-tools';
    public const GITHUB_BRANCH = 'main';
    public const VERSION = '10.18.128';
    public const REQUIRES_WORDPRESS = '6.0';
    public const REQUIRES_PHP = '8.1';
    public const TESTED_WORDPRESS = '7.0';

    public static function root_path(): string {
        return defined( 'HWS_BASE_TOOLS_DIR' )
            ? HWS_BASE_TOOLS_DIR
            : dirname( __DIR__, 2 );
    }

    public static function plugin_file(): string {
        return self::root_path() . '/' . self::MAIN_FILE;
    }
}
