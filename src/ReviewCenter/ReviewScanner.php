<?php

namespace HWS\BaseTools\ReviewCenter;

use Hexa\PluginCore\PluginProvisioning\PluginProvisioner;

final class ReviewScanner {
    public const MAX_COMMENT_TARGETS = 5000;
    public const MIN_BACKUP_AGE_DAYS = 7;
    public const BACKUPS_RETAINED_PER_SOURCE = 1;

    /** @return array<string,mixed> */
    public static function summary(): array {
        $comments   = self::comments();
        $migration  = self::plugins( self::migration_plugins() );
        $caches     = self::plugins( self::duplicate_cache_plugins() );
        $inactive   = self::inactive_plugins();
        $themes     = self::inactive_themes();
        $sample     = self::sample_content();
        $tools      = self::plugins( self::cleanup_plugins() );
        $files      = self::cleanup_files();

        return [
            'comments'                => (int) $comments['count'],
            'migration_plugins'       => count( $migration['matches'] ),
            'duplicate_cache_plugins' => count( $caches['matches'] ),
            'inactive_plugins'        => count( $inactive['matches'] ),
            'inactive_themes'         => count( $themes['matches'] ),
            'sample_content'          => count( $sample['matches'] ),
            'cleanup_plugins'         => count( $tools['matches'] ),
            'cleanup_files'           => count( $files['matches'] ),
        ];
    }

    /** @return array<string,mixed> */
    public static function comments(): array {
        $count = function_exists( 'get_comments' ) ? (int) get_comments( [ 'count' => true, 'status' => 'all' ] ) : 0;
        $by_status = [];
        foreach ( [ 'approve', 'hold', 'spam', 'trash' ] as $status ) {
            $by_status[ $status ] = function_exists( 'get_comments' ) ? (int) get_comments( [ 'count' => true, 'status' => $status ] ) : 0;
        }

        $ids = function_exists( 'get_comments' )
            ? array_map(
                'intval',
                (array) get_comments( [
                    'number'  => self::MAX_COMMENT_TARGETS + 1,
                    'status'  => 'all',
                    'fields'  => 'ids',
                    'orderby' => 'comment_ID',
                    'order'   => 'ASC',
                ] )
            )
            : [];
        $ids = array_values( array_unique( array_filter( $ids, static fn( int $id ): bool => $id > 0 ) ) );
        sort( $ids, SORT_NUMERIC );
        $complete = $count === count( $ids ) && count( $ids ) <= self::MAX_COMMENT_TARGETS;
        if ( count( $ids ) > self::MAX_COMMENT_TARGETS ) {
            $ids      = array_slice( $ids, 0, self::MAX_COMMENT_TARGETS );
            $complete = false;
        }

        return [
            'success'               => 0 === $count,
            'count'                 => $count,
            'by_status'             => $by_status,
            'matches'               => array_map( static fn( int $id ): array => [ 'id' => $id ], $ids ),
            'destructive_supported' => $complete,
            'unsupported_reason'    => $complete ? '' : 'The complete comment target set could not be captured safely in one bounded scan.',
        ];
    }

    /** @return array<string,mixed> */
    public static function migration(): array {
        return self::plugins( self::migration_plugins() );
    }

    /** @return array<string,mixed> */
    public static function duplicate_caches(): array {
        $result = self::plugins( self::duplicate_cache_plugins() );
        $lite   = PluginProvisioner::plugin_status_by_file( 'litespeed-cache/litespeed-cache.php' );
        $result['litespeed_active'] = ! empty( $lite['active'] );
        $result['success'] = [] === $result['matches'];
        return $result;
    }

    /** @return array<string,mixed> */
    public static function inactive_plugins(): array {
        self::load_plugin_api();
        $matches = [];
        foreach ( function_exists( 'get_plugins' ) ? get_plugins() : [] as $file => $data ) {
            if ( self::is_active_plugin( (string) $file ) || str_starts_with( (string) $file, 'hws-base-tools/' ) ) {
                continue;
            }
            $matches[] = [ 'file' => (string) $file, 'name' => (string) ( $data['Name'] ?? $file ), 'version' => (string) ( $data['Version'] ?? '' ), 'active' => false ];
        }
        usort( $matches, static fn( array $left, array $right ): int => strcmp( (string) $left['file'], (string) $right['file'] ) );
        return self::with_code_removal_scope( [ 'success' => [] === $matches, 'matches' => $matches ] );
    }

    /** @return array<string,mixed> */
    public static function inactive_themes(): array {
        $themes = function_exists( 'wp_get_themes' ) ? wp_get_themes() : [];
        $protected = self::protected_themes( $themes );
        $matches = [];
        foreach ( $themes as $slug => $theme ) {
            if ( in_array( (string) $slug, $protected, true ) ) {
                continue;
            }
            $matches[] = [
                'slug'    => (string) $slug,
                'name'    => is_object( $theme ) && method_exists( $theme, 'get' ) ? (string) $theme->get( 'Name' ) : (string) $slug,
                'version' => is_object( $theme ) && method_exists( $theme, 'get' ) ? (string) $theme->get( 'Version' ) : '',
            ];
        }
        usort( $matches, static fn( array $left, array $right ): int => strcmp( (string) $left['slug'], (string) $right['slug'] ) );
        sort( $protected, SORT_STRING );
        return self::with_code_removal_scope( [ 'success' => [] === $matches, 'matches' => $matches, 'protected' => $protected ] );
    }

    /** @return array<string,mixed> */
    public static function sample_content(): array {
        $matches = [];
        $protected = [];
        foreach ( self::sample_definitions() as $definition ) {
            $slug = (string) $definition['slug'];
            $type = (string) $definition['type'];
            $post = function_exists( 'get_page_by_path' ) ? get_page_by_path( $slug, OBJECT, $type ) : null;
            if ( ! is_object( $post ) || empty( $post->ID ) ) {
                continue;
            }

            $fingerprint = self::sample_fingerprint( $post );
            if ( ! self::matches_sample_definition( $post, $definition ) ) {
                $protected[] = [
                    'id'     => (int) $post->ID,
                    'type'   => $type,
                    'slug'   => $slug,
                    'title'  => (string) ( $post->post_title ?? '' ),
                    'reason' => 'The slug exists, but the title, body, status, ID, or edit history no longer matches untouched WordPress sample content.',
                ];
                continue;
            }

            $matches[] = [
                'id'          => (int) $post->ID,
                'type'        => $type,
                'slug'        => $slug,
                'title'       => (string) $post->post_title,
                'fingerprint' => $fingerprint,
            ];
        }
        return [ 'success' => [] === $matches, 'matches' => $matches, 'protected' => $protected, 'destructive_supported' => true ];
    }

    /** @return array<string,mixed> */
    public static function cleanup_tools(): array {
        return self::plugins( self::cleanup_plugins() );
    }

    /** @return array<string,mixed> */
    public static function cleanup_files(): array {
        $matches = [];
        $protected = [];
        $candidates = [];
        if ( defined( 'WP_CONTENT_DIR' ) ) {
            $candidates[] = WP_CONTENT_DIR . '/debug.log';
        }
        if ( defined( 'ABSPATH' ) ) {
            $candidates[] = ABSPATH . 'error_log';
        }

        foreach ( $candidates as $path ) {
            if ( is_file( $path ) ) {
                $match = self::file_match( $path, 'log' );
                if ( [] !== $match ) {
                    $matches[] = $match;
                }
            }
        }

        if ( defined( 'HWS_BASE_TOOLS_DIR' ) ) {
            $file = HWS_BASE_TOOLS_DIR . '/settings-dashboard-backups.php';
            if ( is_readable( $file ) ) {
                require_once $file;
            }
        }
        if ( function_exists( '\hws_base_tools\hws_scan_backups' ) ) {
            $backups = [];
            foreach ( (array) \hws_base_tools\hws_scan_backups() as $backup ) {
                $path = (string) ( $backup['path'] ?? '' );
                if ( '' !== $path && is_file( $path ) ) {
                    $source = 'backup:' . self::clean_key( (string) ( $backup['plugin'] ?? dirname( $path ) ) );
                    $match  = self::file_match( $path, $source );
                    if ( [] !== $match ) {
                        $backups[] = $match;
                    }
                }
            }

            $grouped = [];
            foreach ( $backups as $backup ) {
                $grouped[ (string) $backup['source'] ][] = $backup;
            }
            foreach ( $grouped as $source_backups ) {
                usort( $source_backups, static fn( array $left, array $right ): int => (int) $right['modified'] <=> (int) $left['modified'] );
                foreach ( $source_backups as $index => $backup ) {
                    $age_days = (int) floor( max( 0, time() - (int) $backup['modified'] ) / 86400 );
                    $backup['age_days'] = $age_days;
                    if ( $index < self::BACKUPS_RETAINED_PER_SOURCE ) {
                        $backup['reason'] = 'Newest backup retained for this backup source.';
                        $protected[]      = $backup;
                    } elseif ( $age_days < self::MIN_BACKUP_AGE_DAYS ) {
                        $backup['reason'] = 'Backup is newer than the minimum cleanup age.';
                        $protected[]      = $backup;
                    } else {
                        $matches[] = $backup;
                    }
                }
            }
        }

        $unique = [];
        foreach ( $matches as $match ) {
            $unique[ (string) $match['path'] ] = $match;
        }
        $protected_unique = [];
        foreach ( $protected as $match ) {
            $protected_unique[ (string) $match['path'] ] = $match;
        }
        ksort( $unique, SORT_STRING );
        ksort( $protected_unique, SORT_STRING );
        return [
            'success'               => [] === $unique,
            'matches'               => array_values( $unique ),
            'protected'             => array_values( $protected_unique ),
            'retained'              => array_values( $protected_unique ),
            'destructive_supported' => true,
            'backup_policy'         => [
                'minimum_age_days' => self::MIN_BACKUP_AGE_DAYS,
                'retain_per_source'=> self::BACKUPS_RETAINED_PER_SOURCE,
            ],
        ];
    }

    /** @param array<string,string> $catalog
     *  @return array<string,mixed>
     */
    private static function plugins( array $catalog ): array {
        self::load_plugin_api();
        $installed = function_exists( 'get_plugins' ) ? get_plugins() : [];
        $matches = [];
        foreach ( $catalog as $file => $label ) {
            if ( ! isset( $installed[ $file ] ) ) {
                continue;
            }
            $matches[] = [
                'file'    => $file,
                'name'    => (string) ( $installed[ $file ]['Name'] ?? $label ),
                'version' => (string) ( $installed[ $file ]['Version'] ?? '' ),
                'active'  => self::is_active_plugin( $file ),
            ];
        }
        usort( $matches, static fn( array $left, array $right ): int => strcmp( (string) $left['file'], (string) $right['file'] ) );
        return self::with_code_removal_scope( [ 'success' => [] === $matches, 'matches' => $matches ] );
    }

    /** @return array<string,string> */
    public static function migration_plugins(): array {
        return [
            'all-in-one-wp-migration/all-in-one-wp-migration.php' => 'All-in-One WP Migration',
            'duplicator/duplicator.php'                           => 'Duplicator',
            'duplicator-pro/duplicator-pro.php'                   => 'Duplicator Pro',
            'wpvivid-backuprestore/wpvivid-backuprestore.php'     => 'WPvivid Backup & Migration',
            'migrate-guru/migrateguru.php'                        => 'Migrate Guru',
            'backup-backup/backup-backup.php'                     => 'Backup Migration',
            'wp-staging/wp-staging.php'                           => 'WP STAGING',
            'wp-migrate-db/wp-migrate-db.php'                     => 'WP Migrate DB',
        ];
    }

    /** @return array<string,string> */
    public static function duplicate_cache_plugins(): array {
        return [
            'wp-rocket/wp-rocket.php'                       => 'WP Rocket',
            'w3-total-cache/w3-total-cache.php'             => 'W3 Total Cache',
            'wp-super-cache/wp-cache.php'                   => 'WP Super Cache',
            'autoptimize/autoptimize.php'                   => 'Autoptimize',
            'sg-cachepress/sg-cachepress.php'               => 'SiteGround Optimizer',
            'breeze/breeze.php'                             => 'Breeze',
            'hummingbird-performance/wp-hummingbird.php'    => 'Hummingbird',
            'wp-fastest-cache/wpFastestCache.php'           => 'WP Fastest Cache',
        ];
    }

    /** @return array<string,string> */
    public static function cleanup_plugins(): array {
        return [
            'wp-optimize/wp-optimize.php'                         => 'WP-Optimize',
            'wp-sweep/wp-sweep.php'                               => 'WP-Sweep',
            'regenerate-thumbnails/regenerate-thumbnails.php'     => 'Regenerate Thumbnails',
            'better-search-replace/better-search-replace.php'     => 'Better Search Replace',
            'media-cleaner/media-cleaner.php'                     => 'Media Cleaner',
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function sample_definitions(): array {
        return [
            [
                'id'         => 1,
                'slug'       => 'hello-world',
                'type'       => 'post',
                'title'      => 'Hello world!',
                'content_parts' => [
                    'Welcome to WordPress.',
                    'This is your first post.',
                    'Edit or delete it, then start writing!',
                ],
            ],
            [
                'id'         => 2,
                'slug'       => 'sample-page',
                'type'       => 'page',
                'title'      => 'Sample Page',
                'content_parts' => [
                    'This is an example page.',
                    "It's different from a blog post because it will stay",
                    'in one place and will show up in your site navigation',
                    '(in most themes).',
                    'Most',
                    'people',
                    'start with an About page that introduces them',
                    'to potential site visitors.',
                    'It might say something like this:',
                    'Hi there!',
                    "I'm a bike messenger by day,",
                    'aspiring actor by night,',
                    'and this is my website.',
                    'I live in Los Angeles,',
                    'have a great dog named Jack,',
                    'and I like piña coladas.',
                    "(And gettin' caught in the rain.)",
                    '...or something like this:',
                    'The XYZ Doohickey Company was founded in 1971,',
                    'and has been providing quality doohickeys',
                    'to the public ever since.',
                    'Located in Gotham City,',
                    'XYZ employs over 2,000 people',
                    'and does all kinds of awesome things',
                    'for the Gotham community.',
                    'As a new WordPress user,',
                    'you should go to your dashboard',
                    'to delete this page and create new pages',
                    'for your content.',
                    'Have fun!',
                ],
            ],
        ];
    }

    /** @param array<string,mixed> $definition */
    private static function matches_sample_definition( object $post, array $definition ): bool {
        $content  = self::normalize_sample_content( (string) ( $post->post_content ?? '' ) );
        $expected = implode( ' ', array_map( 'strval', (array) ( $definition['content_parts'] ?? [] ) ) );
        $created  = (string) ( $post->post_date_gmt ?? '' );
        $modified = (string) ( $post->post_modified_gmt ?? '' );

        return (int) ( $post->ID ?? 0 ) === (int) $definition['id']
            && (string) ( $post->post_name ?? '' ) === (string) $definition['slug']
            && (string) ( $post->post_type ?? '' ) === (string) $definition['type']
            && (string) ( $post->post_title ?? '' ) === (string) $definition['title']
            && 'publish' === (string) ( $post->post_status ?? '' )
            && 0 === (int) ( $post->post_parent ?? 0 )
            && '' === (string) ( $post->post_password ?? '' )
            && '' === (string) ( $post->post_excerpt ?? '' )
            && '' !== $created
            && '0000-00-00 00:00:00' !== $created
            && hash_equals( $created, $modified )
            && hash_equals( $expected, $content );
    }

    private static function sample_fingerprint( object $post ): string {
        return hash(
            'sha256',
            serialize( [
                'id'           => (int) ( $post->ID ?? 0 ),
                'slug'         => (string) ( $post->post_name ?? '' ),
                'type'         => (string) ( $post->post_type ?? '' ),
                'status'       => (string) ( $post->post_status ?? '' ),
                'title'        => (string) ( $post->post_title ?? '' ),
                'content'      => self::normalize_sample_content( (string) ( $post->post_content ?? '' ) ),
                'created_gmt'  => (string) ( $post->post_date_gmt ?? '' ),
                'modified_gmt' => (string) ( $post->post_modified_gmt ?? '' ),
            ] )
        );
    }

    private static function normalize_sample_content( string $content ): string {
        $content = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $content, true ) : strip_tags( $content );
        $content = html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $content = str_replace( "\xc2\xa0", ' ', $content );
        return trim( (string) preg_replace( '/\s+/u', ' ', $content ) );
    }

    /** @return array<string,mixed> */
    private static function file_match( string $path, string $source ): array {
        $real_path = realpath( $path );
        if ( false === $real_path || ! is_file( $real_path ) ) {
            return [];
        }

        $stat = stat( $real_path );
        if ( false === $stat ) {
            return [];
        }

        return [
            'path'     => $real_path,
            'name'     => basename( $real_path ),
            'size'     => (int) $stat['size'],
            'modified' => (int) $stat['mtime'],
            'changed'  => (int) $stat['ctime'],
            'device'   => (int) $stat['dev'],
            'inode'    => (int) $stat['ino'],
            'writable' => is_writable( $real_path ),
            'source'   => $source,
        ];
    }

    /** @param array<string,mixed> $scan
     *  @return array<string,mixed>
     */
    private static function with_code_removal_scope( array $scan ): array {
        $multisite = function_exists( 'is_multisite' ) && is_multisite();
        $scan['destructive_supported'] = ! $multisite;
        $scan['unsupported_reason'] = $multisite
            ? 'Plugin and theme removal is not supported from Review Center on multisite because it can affect other sites in the network.'
            : '';
        return $scan;
    }

    private static function clean_key( string $value ): string {
        return function_exists( 'sanitize_key' )
            ? sanitize_key( $value )
            : (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) );
    }

    /** @param array<string,mixed> $themes
     *  @return array<int,string>
     */
    private static function protected_themes( array $themes ): array {
        $protected = [ (string) get_option( 'stylesheet', '' ), (string) get_option( 'template', '' ) ];
        if ( defined( 'WP_DEFAULT_THEME' ) ) {
            $protected[] = (string) WP_DEFAULT_THEME;
        }
        $defaults = array_values( array_filter( array_keys( $themes ), static fn( string $slug ): bool => str_starts_with( $slug, 'twentytwenty' ) ) );
        rsort( $defaults, SORT_NATURAL );
        if ( isset( $defaults[0] ) ) {
            $protected[] = $defaults[0];
        }
        return array_values( array_unique( array_filter( $protected ) ) );
    }

    private static function is_active_plugin( string $file ): bool {
        return function_exists( 'is_plugin_active' ) && is_plugin_active( $file )
            || function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( $file );
    }

    private static function load_plugin_api(): void {
        if ( defined( 'ABSPATH' ) && ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
    }
}
