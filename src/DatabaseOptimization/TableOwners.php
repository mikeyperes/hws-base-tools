<?php

namespace HWS\BaseTools\DatabaseOptimization;

/**
 * Works out which plugin created each table. Uses WP-Optimize's list of
 * 27,000+ known plugin tables (GPLv2+, bundled in data/table-owners.json.gz),
 * then falls back to matching the table name against installed plugin folders.
 */
final class TableOwners {
    /** Tables created by libraries that many plugins bundle. */
    private const SHARED = [
        'actionscheduler_' => 'Action Scheduler',
    ];

    /** Table-name prefixes the bundled list gets wrong or misses, checked first. */
    private const OVERRIDES = [
        'e_submissions' => 'elementor-pro',
        'e_notes'       => 'elementor-pro',
        'e_events'      => 'elementor',
        'bp_'           => 'buddypress',
        'youzify_'      => 'youzify',
    ];

    /** @var array<string,string[]>|null */
    private static ?array $map = null;

    /** @var array<string,array{name:string,active:bool}>|null */
    private static ?array $installed = null;

    /**
     * @return array{name:string,state:string,slug:string}
     *   state: core, active, inactive, missing (plugin not installed) or unknown.
     */
    public static function owner( string $table, bool $core ): array {
        global $wpdb;
        if ( $core ) {
            return [ 'name' => 'WordPress', 'state' => 'core', 'slug' => '' ];
        }
        $suffix = strtolower( substr( $table, strlen( $wpdb->prefix ) ) );

        foreach ( self::SHARED as $prefix => $label ) {
            if ( str_starts_with( $suffix, $prefix ) ) {
                return [ 'name' => $label, 'state' => 'active', 'slug' => '' ];
            }
        }

        $candidates = self::map()[ $suffix ] ?? [];
        foreach ( self::OVERRIDES as $prefix => $slug ) {
            if ( str_starts_with( $suffix, $prefix ) ) {
                $candidates = [ $slug ];
                break;
            }
        }
        if ( $candidates ) {
            $best = null;
            foreach ( $candidates as $slug ) {
                $found = self::installed_match( (string) $slug );
                if ( $found && ( null === $best || ( $found['active'] && ! $best['active'] ) ) ) {
                    $best = $found;
                }
            }
            if ( $best ) {
                return [ 'name' => $best['name'], 'state' => $best['active'] ? 'active' : 'inactive', 'slug' => $best['slug'] ];
            }

            return [ 'name' => self::pretty( (string) $candidates[0] ), 'state' => 'missing', 'slug' => (string) $candidates[0] ];
        }

        // Not in the list: a newer table of a known family (rank_math_link_genius_* is Rank Math).
        $family = self::family( $suffix );
        if ( '' !== $family ) {
            $found = self::installed_match( $family );

            return $found
                ? [ 'name' => $found['name'], 'state' => $found['active'] ? 'active' : 'inactive', 'slug' => $found['slug'] ]
                : [ 'name' => self::pretty( $family ), 'state' => 'missing', 'slug' => $family ];
        }

        // Then match the first word of the table name to a plugin folder.
        $token = (string) strtok( $suffix, '_' );
        if ( strlen( $token ) >= 3 ) {
            foreach ( self::installed() as $slug => $plugin ) {
                $flat = str_replace( '-', '', $slug );
                if ( str_starts_with( $flat, $token ) || str_starts_with( $token, $flat ) ) {
                    return [ 'name' => $plugin['name'], 'state' => $plugin['active'] ? 'active' : 'inactive', 'slug' => $slug ];
                }
            }
        }

        return strlen( $token ) >= 4
            ? [ 'name' => self::pretty( $token ) . ' (guessed from the name)', 'state' => 'missing', 'slug' => '' ]
            : [ 'name' => '', 'state' => 'unknown', 'slug' => '' ];
    }

    /** @var array<string,array<string,int>>|null */
    private static ?array $families = null;

    /**
     * The plugin most often behind known tables that share this table's
     * leading words, trying the longest shared prefix first.
     */
    private static function family( string $suffix ): string {
        if ( null === self::$families ) {
            self::$families = [];
            foreach ( self::map() as $key => $slugs ) {
                $parts = explode( '_', (string) $key );
                for ( $depth = 1; $depth <= min( 3, count( $parts ) - 1 ); $depth++ ) {
                    $prefix = implode( '_', array_slice( $parts, 0, $depth ) );
                    $slug   = (string) ( $slugs[0] ?? '' );
                    if ( '' !== $slug ) {
                        self::$families[ $prefix ][ $slug ] = ( self::$families[ $prefix ][ $slug ] ?? 0 ) + 1;
                    }
                }
            }
        }
        $parts = explode( '_', $suffix );
        for ( $depth = min( 3, count( $parts ) - 1 ); $depth >= 1; $depth-- ) {
            $prefix = implode( '_', array_slice( $parts, 0, $depth ) );
            if ( 1 === $depth && strlen( $prefix ) < 3 ) {
                break;
            }
            $slugs = self::$families[ $prefix ] ?? [];
            if ( $slugs ) {
                arsort( $slugs );
                $total = array_sum( $slugs );
                $top   = (string) array_key_first( $slugs );
                // Only trust a prefix that clearly belongs to one plugin.
                if ( $slugs[ $top ] >= 2 && $slugs[ $top ] / $total >= 0.6 ) {
                    return $top;
                }
            }
        }

        return '';
    }

    /** @return array{name:string,active:bool,slug:string}|null */
    private static function installed_match( string $slug ): ?array {
        $installed = self::installed();
        if ( isset( $installed[ $slug ] ) ) {
            return $installed[ $slug ] + [ 'slug' => $slug ];
        }
        // Paid editions often use another folder (media-cleaner / media-cleaner-pro, wp-smushit / wp-smush-pro).
        $base = self::base( $slug );
        foreach ( $installed as $folder => $plugin ) {
            if ( self::base( $folder ) === $base ) {
                return $plugin + [ 'slug' => $folder ];
            }
        }

        return null;
    }

    private static function base( string $slug ): string {
        $slug = (string) preg_replace( '/-(pro|premium|plus|lite|free)$/', '', strtolower( $slug ) );

        return [ 'wp-smushit' => 'smush', 'wp-smush' => 'smush' ][ $slug ] ?? $slug;
    }

    /** @return array<string,array{name:string,active:bool}> */
    private static function installed(): array {
        if ( null !== self::$installed ) {
            return self::$installed;
        }
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        self::$installed = [];
        foreach ( get_plugins() as $file => $data ) {
            $folder = strtolower( (string) strtok( $file, '/' ) );
            // Backup copies such as "plugin.bak" are not the owner.
            if ( str_contains( $folder, '.bak' ) || isset( self::$installed[ $folder ] ) && self::$installed[ $folder ]['active'] ) {
                continue;
            }
            self::$installed[ $folder ] = [ 'name' => (string) $data['Name'], 'active' => is_plugin_active( $file ) ];
        }

        return self::$installed;
    }

    /** @return array<string,string[]> */
    private static function map(): array {
        if ( null !== self::$map ) {
            return self::$map;
        }
        $file = __DIR__ . '/data/table-owners.json.gz';
        $raw  = is_readable( $file ) ? @gzdecode( (string) file_get_contents( $file ) ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        $map  = is_string( $raw ) ? json_decode( $raw, true ) : null;

        return self::$map = is_array( $map ) ? $map : [];
    }

    private static function pretty( string $slug ): string {
        $known = [ 'buddypress' => 'BuddyPress', 'woocommerce' => 'WooCommerce', 'wordpress-seo' => 'Yoast SEO', 'gpt3-ai-content-generator' => 'AI Power (GPT AI Content Generator)', 'jetformbuilder' => 'JetFormBuilder', 'userswp' => 'UsersWP', 'elementor-pro' => 'Elementor Pro' ];
        if ( isset( $known[ $slug ] ) ) {
            return $known[ $slug ];
        }
        $words = array_map(
            static fn( string $w ): string => in_array( $w, [ 'wp', 'seo', 'cf7', 'ai', 'api', 'smtp', 'css', 'js', 'ssl', 'gdpr', 'faq', 'pdf', 'rss', 'url' ], true ) ? strtoupper( $w ) : ucfirst( $w ),
            explode( '-', $slug )
        );

        return implode( ' ', $words );
    }
}
