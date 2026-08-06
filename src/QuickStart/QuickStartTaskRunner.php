<?php

namespace HWS\BaseTools\QuickStart;

use Hexa\PluginCore\CorePackageUpdates\CorePackageInstaller;
use Hexa\PluginCore\PluginProvisioning\PluginProvisioner;
use Hexa\PluginCore\WordPressOperations\AutoUpdatePolicy;
use Hexa\PluginCore\WordPressOperations\DiscussionOperations;
use Hexa\PluginCore\WordPressOperations\PermalinkOperations;
use Hexa\PluginCore\WordPressOperations\UpdateOperations;
use HWS\BaseTools\BootstrapInstaller\BootstrapLoaderDeployment;
use HWS\BaseTools\LiteSpeed\LiteSpeedTaskRunner;
use HWS\BaseTools\PluginRuntime\PluginMetadata;
use HWS\BaseTools\Security\WordfencePolicyService;

final class QuickStartTaskRunner {
    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public static function run( array $payload ): array {
        $context = is_array( $payload['context'] ?? null ) ? $payload['context'] : [];
        $task    = self::clean_key( (string) ( $context['quick_start_task'] ?? '' ) );

        $capability = self::authorize_capabilities( $task );
        if ( ! $capability['success'] ) {
            return $capability;
        }

        return match ( $task ) {
            'capture_snapshot'             => self::capture_snapshot(),
            'install_bootstrap_loader'     => ( new BootstrapLoaderDeployment() )->install(),
            'audit_wordpress_runtime'      => self::audit_wordpress_runtime(),
            'audit_site_identity'          => self::audit_site_identity(),
            'audit_permalink'              => self::audit_permalink(),
            'audit_updates'                => self::operation( ( new UpdateOperations() )->status( true ), 'Available updates checked.' ),
            'audit_wordpress_policy'       => self::audit_wordpress_policy(),
            'sync_hexa_core'               => self::sync_hexa_core(),
            'update_wordpress'             => self::operation( ( new UpdateOperations() )->update_core(), 'WordPress core update completed.' ),
            'update_plugins'               => self::operation( ( new UpdateOperations() )->update_plugins(), 'Plugin updates completed.' ),
            'update_themes'                => self::operation( ( new UpdateOperations() )->update_themes(), 'Theme updates completed.' ),
            'enable_auto_updates'          => self::operation( ( new AutoUpdatePolicy() )->apply( [ 'core' => 'all', 'plugins' => true, 'themes' => true ] ), 'Automatic updates enabled.' ),
            'install_essential_plugins'    => ( new PluginStackService() )->ensure( ! empty( $context['include_smp'] ) ),
            'verify_plugin_stack'          => self::verify_plugin_stack( $context ),
            'set_memory_limit'             => ( new SiteConfigurationService() )->set_memory_limit(),
            'disable_debug_settings'       => ( new SiteConfigurationService() )->disable_debug(),
            'disable_comments_pings'       => self::operation( ( new DiscussionOperations() )->close_comments_and_pings(), 'Comments and pings disabled for future and existing content.' ),
            'repair_permalinks'            => self::repair_permalinks(),
            'audit_real_cron'              => self::audit_real_cron(),
            'regenerate_favicon_ico'       => ( new SiteConfigurationService() )->generate_favicon(),
            'enable_recommended_snippets'  => ( new SiteConfigurationService() )->enable_recommended_features(),
            'apply_ui_cleanup'             => self::apply_ui_cleanup(),
            'install_litespeed'            => LiteSpeedTaskRunner::provision(),
            'apply_litespeed_profile'      => LiteSpeedTaskRunner::apply_profile( (string) ( $context['litespeed_profile'] ?? 'safe_baseline' ) ),
            'verify_litespeed'             => LiteSpeedTaskRunner::verify_profile( (string) ( $context['litespeed_profile'] ?? 'safe_baseline' ), true ),
            'audit_litespeed'              => LiteSpeedTaskRunner::audit_profile( (string) ( $context['litespeed_profile'] ?? 'safe_baseline' ) ),
            'configure_wordfence'          => self::operation( ( new WordfencePolicyService() )->apply(), 'Wordfence baseline configured.' ),
            'audit_wordfence'              => self::wordfence_status(),
            'audit_smtp'                   => self::audit_smtp(),
            'test_smtp'                    => ( new SiteConfigurationService() )->test_smtp( (string) ( is_array( $payload['inputs'] ?? null ) ? ( $payload['inputs']['smtp_test_recipient'] ?? '' ) : '' ) ),
            'purge_caches'                 => self::purge_caches(),
            'verify_live_site'             => self::verify_live_site(),
            'final_report'                 => self::final_report(),
            default                        => self::result( false, 'Unknown Quick Start task: ' . $task ),
        };
    }

    /** @return array<string,mixed> */
    private static function capture_snapshot(): array {
        $snapshot = ( new QuickStartSnapshotStore() )->capture();
        return self::result(
            true,
            'Verified WordPress option before-state captured before changes.',
            [
                'snapshot_id' => (string) $snapshot['snapshot_id'],
                'label'        => (string) $snapshot['label'],
                'captured_at'  => (string) $snapshot['captured_at'],
                'wordpress'    => (string) $snapshot['wordpress'],
                'php'          => (string) $snapshot['php'],
                'plugin_count' => count( (array) $snapshot['plugins'] ),
                'theme_count'  => count( (array) $snapshot['themes'] ),
            ]
        );
    }

    /** @return array<int,string> */
    public static function required_capabilities( string $task ): array {
        return match ( self::clean_key( $task ) ) {
            'install_bootstrap_loader',
            'install_essential_plugins',
            'install_litespeed',
            'configure_wordfence' => [ 'manage_options', 'install_plugins', 'activate_plugins' ],
            'sync_hexa_core',
            'update_plugins' => [ 'manage_options', 'update_plugins' ],
            'update_wordpress' => [ 'manage_options', 'update_core' ],
            'update_themes' => [ 'manage_options', 'update_themes' ],
            'enable_auto_updates' => [ 'manage_options', 'update_core', 'update_plugins', 'update_themes' ],
            'disable_comments_pings' => [ 'manage_options', 'edit_others_posts' ],
            'regenerate_favicon_ico' => [ 'manage_options', 'upload_files' ],
            default => [ 'manage_options' ],
        };
    }

    /** @return array<string,mixed> */
    private static function audit_wordpress_runtime(): array {
        $wordpress = isset( $GLOBALS['wp_version'] ) ? (string) $GLOBALS['wp_version'] : '';
        $wp_ok     = '' !== $wordpress && version_compare( $wordpress, PluginMetadata::REQUIRES_WORDPRESS, '>=' );
        $php_ok    = version_compare( PHP_VERSION, PluginMetadata::REQUIRES_PHP, '>=' );
        $content_writable = defined( 'WP_CONTENT_DIR' ) && is_dir( WP_CONTENT_DIR ) && is_writable( WP_CONTENT_DIR );

        $required_extensions = [ 'curl', 'dom', 'fileinfo', 'json', 'mbstring', 'mysqli', 'openssl', 'tokenizer', 'xml' ];
        $optional_extensions = [ 'bz2', 'exif', 'gd', 'imagick', 'intl', 'redis', 'sodium', 'zip' ];
        $extensions = [];
        foreach ( array_merge( $required_extensions, $optional_extensions ) as $extension ) {
            $extensions[ $extension ] = extension_loaded( $extension );
        }
        $missing_required = array_values(
            array_filter( $required_extensions, static fn( string $extension ): bool => empty( $extensions[ $extension ] ) )
        );

        $opcache_loaded  = extension_loaded( 'Zend OPcache' );
        $opcache_enabled = $opcache_loaded && filter_var( (string) ini_get( 'opcache.enable' ), FILTER_VALIDATE_BOOL );
        $lve_visible     = false !== stripos( PHP_SAPI, 'litespeed' )
            || false !== stripos( (string) ( $_SERVER['SERVER_SOFTWARE'] ?? '' ), 'litespeed' )
            || false !== getenv( 'LVE_ID' );
        $limits = [
            'memory_limit'        => (string) ini_get( 'memory_limit' ),
            'upload_max_filesize' => (string) ini_get( 'upload_max_filesize' ),
            'post_max_size'       => (string) ini_get( 'post_max_size' ),
            'max_execution_time'  => (string) ini_get( 'max_execution_time' ),
            'max_input_vars'      => (string) ini_get( 'max_input_vars' ),
        ];
        $ready = $wp_ok && $php_ok && $content_writable && [] === $missing_required;

        return self::result(
            $ready,
            $ready ? 'WordPress runtime is ready.' : 'WordPress runtime needs attention before setup.',
            [
                'wordpress'          => $wordpress,
                'wordpress_required' => PluginMetadata::REQUIRES_WORDPRESS,
                'php'                => PHP_VERSION,
                'php_required'       => PluginMetadata::REQUIRES_PHP,
                'php_handler'        => PHP_SAPI,
                'server_software'    => sanitize_text_field( (string) ( $_SERVER['SERVER_SOFTWARE'] ?? '' ) ),
                'content_writable'   => $content_writable,
                'extensions'         => $extensions,
                'missing_required_extensions' => $missing_required,
                'opcache'            => [ 'loaded' => $opcache_loaded, 'enabled' => $opcache_enabled ],
                'redis'              => [ 'extension_loaded' => ! empty( $extensions['redis'] ) ],
                'php_limits'         => $limits,
                'cloudlinux_lve'     => [
                    'runtime_indicator_visible' => $lve_visible,
                    'limits_available_to_wordpress' => false,
                    'review_required' => true,
                    'message' => 'CloudLinux LVE CPU, process, I/O, and account-memory quotas are server controls and are not reliably exposed to WordPress.',
                ],
            ]
        );
    }

    /** @return array<string,mixed> */
    private static function audit_site_identity(): array {
        $home     = (string) get_option( 'home', '' );
        $siteurl  = (string) get_option( 'siteurl', '' );
        $title    = trim( (string) get_option( 'blogname', '' ) );
        $email    = sanitize_email( (string) get_option( 'admin_email', '' ) );
        $timezone      = (string) get_option( 'timezone_string', '' );
        $offset_setting = get_option( 'gmt_offset', null );
        $offset         = is_numeric( $offset_setting ) ? (float) $offset_setting : 0.0;
        $timezone_valid = self::timezone_is_valid( $timezone, $offset_setting );
        $public   = '1' === (string) get_option( 'blog_public', '1' );
        $https    = str_starts_with( $home, 'https://' ) && str_starts_with( $siteurl, 'https://' );
        $valid    = '' !== $title && '' !== $email && $https && $public && $timezone_valid;

        return self::result(
            $valid,
            $valid ? 'Site identity is production-ready.' : 'Site identity has launch items to review.',
            [
                'home'            => $home,
                'siteurl'         => $siteurl,
                'title_set'       => '' !== $title,
                'admin_email_set' => '' !== $email,
                'timezone'        => '' !== $timezone ? $timezone : 'UTC' . ( $offset >= 0 ? '+' : '' ) . $offset,
                'timezone_valid'  => $timezone_valid,
                'search_indexing' => $public,
                'https'           => $https,
                'environment'     => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production',
            ]
        );
    }

    private static function timezone_is_valid( string $timezone, mixed $offset_setting ): bool {
        if ( '' !== trim( $timezone ) ) {
            return true;
        }
        if ( ! is_numeric( $offset_setting ) ) {
            return false;
        }
        $offset = (float) $offset_setting;
        return $offset >= -14.0 && $offset <= 14.0;
    }

    /** @return array<string,mixed> */
    private static function audit_permalink(): array {
        $status    = ( new PermalinkOperations() )->status();
        $structure = (string) ( $status['items']['structure'] ?? '' );
        $rules     = (int) ( $status['counts']['rules'] ?? 0 );
        $success   = '' !== $structure && $rules > 0;
        return self::result( $success, $success ? 'Pretty permalinks and rewrite rules are present.' : 'Permalinks need a hard repair.', $status );
    }

    /** @return array<string,mixed> */
    private static function audit_wordpress_policy(): array {
        $auto       = ( new AutoUpdatePolicy() )->status();
        $discussion = ( new DiscussionOperations() )->status();
        $permalink  = ( new PermalinkOperations() )->status();
        $memory     = defined( 'WP_MEMORY_LIMIT' ) ? (string) WP_MEMORY_LIMIT : (string) ini_get( 'memory_limit' );
        $debug      = defined( 'WP_DEBUG' ) && WP_DEBUG;
        $installed_plugins = function_exists( 'get_plugins' ) ? count( get_plugins() ) : 0;
        $installed_themes  = function_exists( 'wp_get_themes' ) ? count( wp_get_themes() ) : 0;
        $auto_ok = 'all' === (string) ( $auto['items']['core'] ?? '' )
            && count( (array) ( $auto['items']['plugins'] ?? [] ) ) >= $installed_plugins
            && count( (array) ( $auto['items']['themes'] ?? [] ) ) >= $installed_themes;
        $discussion_ok = 0 === (int) ( $discussion['counts']['open_posts'] ?? -1 )
            && 'closed' === (string) get_option( 'default_comment_status', '' )
            && 'closed' === (string) get_option( 'default_ping_status', '' );
        $permalink_ok = '' !== (string) ( $permalink['items']['structure'] ?? '' )
            && (int) ( $permalink['counts']['rules'] ?? 0 ) > 0;
        $success = ! $debug
            && '4096M' === strtoupper( trim( $memory ) )
            && $auto_ok
            && $discussion_ok
            && $permalink_ok;

        return self::result( $success, $success ? 'WordPress launch policy matches HWS.' : 'WordPress launch policy has differences.', compact( 'memory', 'debug', 'auto', 'discussion', 'permalink' ) );
    }

    /** @return array<string,mixed> */
    private static function sync_hexa_core(): array {
        if ( ! function_exists( '\hws_base_tools\hws_get_hexa_plugin_core_package_config' ) ) {
            return self::result( false, 'HWS Core package configuration is unavailable.' );
        }

        $result = ( new CorePackageInstaller( \hws_base_tools\hws_get_hexa_plugin_core_package_config() ) )->run_registered_hosts();
        return self::operation( $result, 'Hexa WP Core synchronized across registered plugin bundles.' );
    }

    /** @param array<string,mixed> $context */
    private static function verify_plugin_stack( array $context ): array {
        self::load_plugin_policy();
        if ( ! function_exists( '\hws_base_tools\hws_get_monitored_plugins' ) ) {
            return self::result( false, 'HWS plugin policy is unavailable.' );
        }

        $rows    = [];
        $missing = [];
        foreach ( \hws_base_tools\hws_get_monitored_plugins() as $file => $policy ) {
            if ( 'essential' !== (string) ( $policy['category'] ?? '' ) ) {
                continue;
            }
            $status = PluginProvisioner::plugin_status_by_file( (string) $file );
            $ok     = ! empty( $status['installed'] ) && ! empty( $status['active'] );
            $rows[] = [
                'plugin'   => (string) ( $policy['name'] ?? $file ),
                'file'     => (string) $file,
                'installed'=> ! empty( $status['installed'] ),
                'active'   => ! empty( $status['active'] ),
                'licensed' => empty( $policy['pro'] ) ? 'not-required' : 'manual-package',
            ];
            if ( ! $ok ) {
                $missing[] = (string) ( $policy['name'] ?? $file );
            }
        }

        if ( ! empty( $context['include_smp'] ) ) {
            foreach ( [
                'smp-publication-integration/smp-publication-integration.php' => 'SMP Publication Integration',
                'smp-verified-profiles/smp-verified-profiles.php'             => 'SMP Verified Profiles',
            ] as $file => $name ) {
                $status = PluginProvisioner::plugin_status_by_file( $file );
                $rows[] = [ 'plugin' => $name, 'file' => $file, 'installed' => ! empty( $status['installed'] ), 'active' => ! empty( $status['active'] ), 'licensed' => 'not-required' ];
                if ( empty( $status['installed'] ) || empty( $status['active'] ) ) {
                    $missing[] = $name;
                }
            }
        }

        return self::result(
            [] === $missing,
            [] === $missing ? 'Required plugin stack verified.' : count( $missing ) . ' required plugin(s) still need attention.',
            [ 'plugins' => $rows, 'missing' => $missing ]
        );
    }

    /** @return array<string,mixed> */
    private static function repair_permalinks(): array {
        $result = ( new PermalinkOperations() )->repair( '' );
        if ( empty( $result['success'] ) ) {
            return self::operation( $result, 'Permalink repair failed.' );
        }

        $live = self::verify_live_site( true );
        if ( empty( $live['success'] ) ) {
            $result['success'] = false;
            $result['messages'][] = $live['message'];
            $result['live_verification'] = $live['data'] ?? [];
        }
        return self::operation( $result, 'Permalinks rebuilt and a live inner page verified.' );
    }

    /** @return array<string,mixed> */
    private static function audit_real_cron(): array {
        $disabled  = defined( 'DISABLE_WP_CRON' ) && true === DISABLE_WP_CRON;
        $last_seen = (int) get_option( 'hws_cron_last_seen', 0 );
        $recent    = $last_seen > 0 && $last_seen >= time() - DAY_IN_SECONDS;
        $success   = $disabled && $recent;

        return self::result(
            $success,
            $success ? 'Visitor-driven WP-Cron is disabled and cron execution was seen in the last 24 hours.' : 'Real cron handoff needs review; HWS will not disable visitor WP-Cron without verified execution.',
            [
                'wp_cron_disabled' => $disabled,
                'last_cron_seen'   => $last_seen ? gmdate( 'c', $last_seen ) : '',
                'recent_heartbeat' => $recent,
                'wordpress_limit'  => 'The plugin can verify and disable WP-Cron, but server cron scheduling belongs to the hosting layer.',
            ]
        );
    }

    /** @return array<string,mixed> */
    private static function apply_ui_cleanup(): array {
        self::load_ui_cleanup_policy();
        if ( ! function_exists( '\hws_base_tools\get_ui_cleanup_options' ) ) {
            return self::result( false, 'UI Cleanup policy is unavailable.' );
        }

        $recommended = [
            'hide_admin_color_scheme',
            'hide_language_selector',
            'hide_keyboard_shortcuts',
            'hide_elementor_ai',
            'hide_elementor_notes',
            'hide_post_editor_comments',
            'collapse_litespeed_editor_box',
            'hide_rankmath_content_ai',
        ];
        $available = \hws_base_tools\get_ui_cleanup_options();
        $applied   = [];
        foreach ( $recommended as $key ) {
            if ( isset( $available[ $key ] ) ) {
                update_option( 'hws_ui_cleanup_' . $key, true );
                $applied[] = $key;
            }
        }

        return self::result( true, count( $applied ) . ' recommended admin cleanup setting(s) enabled.', [ 'applied' => $applied ] );
    }

    /** @return array<string,mixed> */
    private static function wordfence_status(): array {
        $status = ( new WordfencePolicyService() )->status();
        return self::result( ! empty( $status['success'] ), ! empty( $status['success'] ) ? 'Wordfence baseline verified.' : 'Wordfence needs configuration.', $status );
    }

    /** @return array<string,mixed> */
    private static function audit_smtp(): array {
        $active = function_exists( 'is_plugin_active' ) && is_plugin_active( 'wp-mail-smtp/wp_mail_smtp.php' );
        $option = get_option( 'wp_mail_smtp', [] );
        $mail   = is_array( $option['mail'] ?? null ) ? $option['mail'] : [];
        $smtp   = is_array( $option['smtp'] ?? null ) ? $option['smtp'] : [];
        $mailer = trim( (string) ( $mail['mailer'] ?? '' ) );
        $from   = sanitize_email( (string) ( $mail['from_email'] ?? '' ) );
        $auth_present = ! empty( $smtp['auth'] )
            ? ( '' !== trim( (string) ( $smtp['user'] ?? '' ) ) && '' !== trim( (string) ( $smtp['pass'] ?? '' ) ) )
            : '' !== $mailer && 'mail' !== $mailer;
        $success = $active && '' !== $mailer && '' !== $from && $auth_present;

        return self::result(
            $success,
            $success ? 'SMTP settings are present. Use the individual delivery test for live verification.' : 'SMTP configuration is incomplete.',
            [ 'plugin_active' => $active, 'mailer' => $mailer, 'from_email_set' => '' !== $from, 'authentication_present' => $auth_present ]
        );
    }

    /** @return array<string,mixed> */
    private static function purge_caches(): array {
        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
        }
        do_action( 'litespeed_purge_all' );
        return self::result( true, 'WordPress object cache and LiteSpeed page cache purge requests completed.' );
    }

    /** @return array<string,mixed> */
    private static function verify_live_site( bool $require_inner = false ): array {
        $urls = [ 'homepage' => home_url( '/' ) ];
        $inner = self::inner_url();
        if ( '' !== $inner ) {
            $urls['inner_page'] = $inner;
        }

        $checks = [];
        $success = true;
        foreach ( $urls as $label => $url ) {
            $response = wp_remote_get( $url, [ 'timeout' => 15, 'redirection' => 5, 'user-agent' => 'HWS-Quick-Start/' . PluginMetadata::VERSION ] );
            if ( is_wp_error( $response ) ) {
                $checks[ $label ] = [ 'url' => $url, 'success' => false, 'message' => $response->get_error_message() ];
                $success = false;
                continue;
            }
            $code = (int) wp_remote_retrieve_response_code( $response );
            $ok   = $code >= 200 && $code < 400;
            $checks[ $label ] = [ 'url' => $url, 'success' => $ok, 'status_code' => $code ];
            $success = $success && $ok;
        }

        if ( $require_inner && ! isset( $checks['inner_page'] ) ) {
            $success = false;
            $checks['inner_page'] = [ 'url' => '', 'success' => false, 'message' => 'No published inner page was available for live verification.' ];
        }

        return self::result( $success, $success ? 'Homepage and available inner page are reachable.' : 'One or more public-page checks failed.', $checks );
    }

    /** @return array<string,mixed> */
    private static function final_report(): array {
        $snapshot = ( new QuickStartSnapshotStore() )->latest();
        $state    = get_option( 'hws_quick_start_state', [] );
        $review   = class_exists( '\HWS\BaseTools\ReviewCenter\ReviewScanner' )
            ? \HWS\BaseTools\ReviewCenter\ReviewScanner::summary()
            : [];

        return self::result(
            [] !== $snapshot,
            [] !== $snapshot ? 'Before / after report is ready.' : 'No before snapshot was found for this report.',
            [
                'snapshot'       => $snapshot,
                'checklist_state'=> is_array( $state ) ? $state : [],
                'review_summary' => $review,
                'generated_at'   => gmdate( 'c' ),
            ]
        );
    }

    private static function load_plugin_policy(): void {
        if ( ! defined( 'HWS_BASE_TOOLS_DIR' ) ) {
            return;
        }
        $path = HWS_BASE_TOOLS_DIR . '/settings-dashboard-check-plugins.php';
        if ( is_readable( $path ) ) {
            require_once $path;
        }
    }

    private static function load_ui_cleanup_policy(): void {
        if ( ! defined( 'HWS_BASE_TOOLS_DIR' ) ) {
            return;
        }
        $path = HWS_BASE_TOOLS_DIR . '/settings-dashboard-ui-cleanup.php';
        if ( is_readable( $path ) ) {
            require_once $path;
        }
    }

    /** @return array<string,mixed> */
    private static function authorize_capabilities( string $task ): array {
        $required = self::required_capabilities( $task );
        $missing  = [];
        foreach ( $required as $capability ) {
            if ( ! function_exists( 'current_user_can' ) || ! current_user_can( $capability ) ) {
                $missing[] = $capability;
            }
        }
        if ( [] !== $missing ) {
            return self::result(
                false,
                'You do not have the WordPress capabilities required for this Quick Start task.',
                [ 'code' => 'forbidden', 'required_capabilities' => $required, 'missing_capabilities' => $missing ]
            );
        }
        return [ 'success' => true ];
    }

    /** @return array<string,mixed> */
    private static function operation( mixed $operation, string $fallback_message ): array {
        if ( function_exists( 'is_wp_error' ) && is_wp_error( $operation ) ) {
            return self::result( false, $operation->get_error_message(), [ 'code' => $operation->get_error_code() ] );
        }
        if ( ! is_array( $operation ) ) {
            return self::result( false, $fallback_message, [ 'unexpected_result' => gettype( $operation ) ] );
        }

        $success = array_key_exists( 'success', $operation ) ? (bool) $operation['success'] : true;
        $message = trim( (string) ( $operation['message'] ?? '' ) );
        if ( '' === $message && is_array( $operation['messages'] ?? null ) ) {
            $message = implode( ' ', array_map( 'strval', $operation['messages'] ) );
        }
        if ( '' === $message ) {
            $message = $fallback_message;
        }

        $operation['success'] = $success;
        $operation['message'] = $message;
        if ( ! isset( $operation['data'] ) ) {
            $operation['data'] = array_diff_key( $operation, [ 'success' => true, 'message' => true, 'logs' => true ] );
        }
        return $operation;
    }

    /** @return array<string,mixed> */
    private static function result( bool $success, string $message, array $data = [] ): array {
        return [ 'success' => $success, 'message' => $message, 'data' => $data ];
    }

    private static function inner_url(): string {
        $posts = get_posts( [
            'post_type'        => [ 'page', 'post' ],
            'post_status'      => 'publish',
            'posts_per_page'   => 10,
            'orderby'          => 'date',
            'order'            => 'DESC',
            'suppress_filters' => false,
            'fields'           => 'ids',
        ] );

        foreach ( $posts as $post_id ) {
            $url = (string) get_permalink( (int) $post_id );
            if ( '' !== $url && untrailingslashit( $url ) !== untrailingslashit( home_url( '/' ) ) ) {
                return $url;
            }
        }
        return '';
    }

    private static function clean_key( string $value ): string {
        return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) );
    }
}
