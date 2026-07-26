<?php

declare( strict_types=1 );

namespace HWS\BaseTools\AcfFields;

/**
 * Consolidates deprecated user-profile ACF data into the HWS 2025 fields.
 */
final class UserProfile2025Migration {
    public const PROFILE_OPTION = 'register_user_custom_fields_2025';
    public const ADDITIONAL_OPTION = 'register_user_custom_fields_additional_2025';

    public const PROFILE_GROUP_KEY = 'group_684252fd99081';

    /** @var list<string> */
    public const DEPRECATED_GROUP_KEYS = [
        'group_590d64c31db0a',
        'group_6419bc02b6e93',
    ];

    private const URLS_FIELD_KEY = 'field_684253229b32a';
    private const SUBTITLE_FIELD_KEY = 'field_684348d1832e2';
    private const LOCATION_FIELD_KEY = 'field_hws_user_profile_2025_location';
    private const SCHEMA_FIELD_KEY = 'field_hws_user_profile_2025_schema_markup';
    private const PHOTOS_FIELD_KEY = 'field_hws_user_profile_2025_photos';
    private const ADDITIONAL_FIELD_KEY = 'field_6842_additional_group';
    private const STAFF_WRITER_FIELD_KEY = 'field_hws_additional_staff_writer';
    private const MUCKRACK_VERIFIED_FIELD_KEY = 'field_hws_additional_muckrack_verified';
    private const MUCKRACK_URL_FIELD_KEY = 'field_hws_additional_muckrack_url';

    /** @var array<string,list<string>> */
    private const URL_SOURCES = [
        'facebook'   => [ 'facebook_url', 'socials_facebook', 'social_media_facebook' ],
        'instagram'  => [ 'instagram_url', 'socials_instagram', 'social_media_instagram' ],
        'linkedin'   => [ 'linkedin_url', 'socials_linkedin' ],
        'youtube'    => [ 'youtube_url', 'socials_youtube' ],
        'tiktok'     => [ 'tiktok_url', 'socials_tiktok' ],
        'f6s'        => [ 'f6s_url', 'profiles_f6s' ],
        'imdb'       => [ 'imdb_url', 'profiles_imdb' ],
        'muckrack'   => [ 'muckrack_url', 'muck_rack_url', 'profiles_muckrack' ],
        'wikipedia'  => [ 'wikipedia_url', 'profiles_wikipedia' ],
        'x'          => [ 'twitter_url', 'x_url', 'socials_x' ],
        'soundcloud' => [ 'soundcloud_url', 'socials_soundcloud' ],
        'the_org'    => [ 'the_org_url' ],
        'wellfound'  => [ 'well_found_url', 'wellfound_url' ],
        'whatsapp'   => [ 'whatsapp_url' ],
        'telegram'   => [ 'telegram_url' ],
        'signal'     => [ 'signal_url' ],
        'calendly'   => [ 'calendly_url' ],
        'amazon'     => [ 'amazon_url' ],
        'github'     => [ 'github_url' ],
        'audible'    => [ 'audible_url' ],
        'threads'    => [ 'threads_url' ],
        'crunchbase' => [ 'crunchbase_url', 'profiles_crunchbase' ],
        'website'    => [ 'website_url', 'social_media_website' ],
    ];

    /** @var list<string> */
    private const SUBTITLE_SOURCES = [
        'job_title',
        'author_title',
        'team_member_title',
    ];

    /** @var list<string> */
    private const REUSED_CANONICAL_KEYS = [
        'additional',
        'location',
        'schema_markup',
        'photos',
        'staff_writer',
        'muckrack_verified',
        'muckrack_url',
    ];

    /** @var list<string> */
    private const LEGACY_GROUP_NAMES = [
        'socials',
        'profiles',
        'social_media',
        'settings',
    ];

    public static function register(): void {
        add_action( 'acf/init', [ self::class, 'remove_deprecated_local_groups' ], PHP_INT_MAX );
    }

    public static function remove_deprecated_local_groups(): void {
        if ( ! get_option( self::PROFILE_OPTION, false ) || ! function_exists( 'acf_remove_local_field_group' ) ) {
            return;
        }

        foreach ( self::DEPRECATED_GROUP_KEYS as $group_key ) {
            acf_remove_local_field_group( $group_key );
        }
    }

    /**
     * @return array{
     *   dry_run:bool,delete_legacy:bool,users_scanned:int,users_changed:int,
     *   urls_written:int,subtitles_written:int,direct_fields_written:int,
     *   legacy_keys_deleted:int,conflicts:list<array<string,mixed>>,errors:list<string>
     * }
     */
    public static function migrate_all( bool $dry_run = true, bool $delete_legacy = false ): array {
        $summary = [
            'dry_run'               => $dry_run,
            'delete_legacy'         => $delete_legacy,
            'users_scanned'         => 0,
            'users_changed'         => 0,
            'urls_written'          => 0,
            'subtitles_written'     => 0,
            'direct_fields_written' => 0,
            'legacy_keys_deleted'   => 0,
            'conflicts'             => [],
            'errors'                => [],
        ];

        if ( ! function_exists( 'get_users' ) ) {
            $summary['errors'][] = 'WordPress user APIs are unavailable.';
            return $summary;
        }

        foreach ( get_users( [ 'fields' => 'ID' ] ) as $user_id ) {
            $report = self::migrate_user( (int) $user_id, $dry_run, $delete_legacy );
            ++$summary['users_scanned'];

            if ( $report['changed'] ) {
                ++$summary['users_changed'];
            }

            foreach ( [ 'urls_written', 'subtitles_written', 'direct_fields_written', 'legacy_keys_deleted' ] as $key ) {
                $summary[ $key ] += $report[ $key ];
            }

            foreach ( $report['conflicts'] as $conflict ) {
                $conflict['user_id'] = (int) $user_id;
                $summary['conflicts'][] = $conflict;
            }

            foreach ( $report['errors'] as $error ) {
                $summary['errors'][] = 'User ' . (int) $user_id . ': ' . $error;
            }
        }

        return $summary;
    }

    /**
     * @return array{
     *   changed:bool,urls_written:int,subtitles_written:int,direct_fields_written:int,
     *   legacy_keys_deleted:int,conflicts:list<array<string,mixed>>,errors:list<string>
     * }
     */
    public static function migrate_user( int $user_id, bool $dry_run = true, bool $delete_legacy = false ): array {
        $report = [
            'changed'               => false,
            'urls_written'          => 0,
            'subtitles_written'     => 0,
            'direct_fields_written' => 0,
            'legacy_keys_deleted'   => 0,
            'conflicts'             => [],
            'errors'                => [],
        ];

        if ( $user_id <= 0 || ! get_userdata( $user_id ) ) {
            $report['errors'][] = 'User does not exist.';
            return $report;
        }

        if ( ! function_exists( 'update_field' ) ) {
            $report['errors'][] = 'ACF update_field() is unavailable.';
            return $report;
        }

        $post_id = 'user_' . $user_id;
        $cleanup_direct = [];
        $cleanup_groups = [];

        $urls = self::acf_value( 'urls', $post_id, $user_id );
        $urls = is_array( $urls ) ? $urls : [];
        $original_urls = $urls;

        foreach ( self::URL_SOURCES as $destination => $sources ) {
            $current = self::string_value( $urls[ $destination ] ?? '' );

            foreach ( $sources as $source ) {
                foreach ( self::source_records( $user_id, $source ) as $record ) {
                    $source_value = self::string_value( $record['value'] );
                    if ( '' === $source_value ) {
                        self::schedule_cleanup( $record, $cleanup_direct, $cleanup_groups );
                        continue;
                    }

                    if ( '' === $current ) {
                        $current = $source_value;
                    } elseif ( ! self::equivalent( $current, $source_value ) ) {
                        $report['conflicts'][] = [
                            'destination'       => 'urls.' . $destination,
                            'destination_value' => $current,
                            'source'            => $record['label'],
                            'source_value'      => $source_value,
                        ];
                        continue;
                    }

                    self::schedule_cleanup( $record, $cleanup_direct, $cleanup_groups );
                }
            }

            if ( 'website' === $destination && '' === $current ) {
                $user = get_userdata( $user_id );
                $current = $user ? self::string_value( $user->user_url ?? '' ) : '';
            }

            if ( '' !== $current ) {
                $urls[ $destination ] = $current;
            }
        }

        $urls_changed = $urls !== $original_urls;
        $urls_reference_stale = [] !== $urls && self::field_reference( $user_id, 'urls' ) !== self::URLS_FIELD_KEY;
        if ( $urls_changed || $urls_reference_stale ) {
            $report['changed'] = true;
            $report['urls_written'] = count( array_diff_assoc( $urls, $original_urls ) );
            if ( $urls_reference_stale && ! $urls_changed ) {
                ++$report['direct_fields_written'];
            }
            self::update_field( self::URLS_FIELD_KEY, $urls, $post_id, $dry_run, $report );
        }

        self::migrate_subtitle( $user_id, $post_id, $dry_run, $cleanup_direct, $cleanup_groups, $report );
        self::migrate_direct_from_sources(
            $user_id,
            $post_id,
            'staff_writer',
            self::STAFF_WRITER_FIELD_KEY,
            [ 'settings_staff_writer' ],
            $dry_run,
            $cleanup_direct,
            $cleanup_groups,
            $report
        );
        self::migrate_direct_from_sources(
            $user_id,
            $post_id,
            'muckrack_verified',
            self::MUCKRACK_VERIFIED_FIELD_KEY,
            [ 'profiles_muckrack_verified', 'settings_muckrack_verified' ],
            $dry_run,
            $cleanup_direct,
            $cleanup_groups,
            $report
        );

        self::migrate_muckrack_url( $user_id, $post_id, $urls, $dry_run, $report );
        self::migrate_photos( $user_id, $post_id, $dry_run, $cleanup_direct, $report );

        foreach ( [
            'location'      => self::LOCATION_FIELD_KEY,
            'schema_markup' => self::SCHEMA_FIELD_KEY,
            'additional'    => self::ADDITIONAL_FIELD_KEY,
        ] as $meta_key => $field_key ) {
            self::refresh_direct_field( $user_id, $meta_key, $field_key, $post_id, $dry_run, $report );
        }

        if ( $delete_legacy && [] === $report['conflicts'] && [] === $report['errors'] ) {
            $deleted = self::cleanup_legacy_meta( $user_id, $cleanup_direct, $cleanup_groups, $dry_run );
            $report['legacy_keys_deleted'] = $deleted;
            if ( $deleted > 0 ) {
                $report['changed'] = true;
            }
        }

        return $report;
    }

    /**
     * @param array<string,true>              $cleanup_direct
     * @param array<string,array<string,true>> $cleanup_groups
     * @param array<string,mixed>             $report
     */
    private static function migrate_subtitle(
        int $user_id,
        string $post_id,
        bool $dry_run,
        array &$cleanup_direct,
        array &$cleanup_groups,
        array &$report
    ): void {
        $stored = self::acf_value( 'subtitle', $post_id, $user_id );
        $subtitle = self::string_value( $stored );

        foreach ( self::SUBTITLE_SOURCES as $source ) {
            foreach ( self::source_records( $user_id, $source ) as $record ) {
                $source_value = self::string_value( $record['value'] );
                if ( '' === $source_value ) {
                    self::schedule_cleanup( $record, $cleanup_direct, $cleanup_groups );
                    continue;
                }

                if ( '' === $subtitle ) {
                    $subtitle = $source_value;
                } elseif ( ! self::equivalent( $subtitle, $source_value ) ) {
                    $report['conflicts'][] = [
                        'destination'       => 'subtitle',
                        'destination_value' => $subtitle,
                        'source'            => $record['label'],
                        'source_value'      => $source_value,
                    ];
                    continue;
                }

                self::schedule_cleanup( $record, $cleanup_direct, $cleanup_groups );
            }
        }

        $content_changed = '' !== $subtitle && ! self::equivalent( $subtitle, self::string_value( $stored ) );
        $reference_stale = '' !== $subtitle && self::field_reference( $user_id, 'subtitle' ) !== self::SUBTITLE_FIELD_KEY;
        if ( ! $content_changed && ! $reference_stale ) {
            return;
        }

        $report['changed'] = true;
        $report['subtitles_written'] = 1;
        self::update_field( self::SUBTITLE_FIELD_KEY, $subtitle, $post_id, $dry_run, $report );
    }

    /**
     * @param list<string>                    $sources
     * @param array<string,true>              $cleanup_direct
     * @param array<string,array<string,true>> $cleanup_groups
     * @param array<string,mixed>             $report
     */
    private static function migrate_direct_from_sources(
        int $user_id,
        string $post_id,
        string $destination,
        string $field_key,
        array $sources,
        bool $dry_run,
        array &$cleanup_direct,
        array &$cleanup_groups,
        array &$report
    ): void {
        $has_destination = metadata_exists( 'user', $user_id, $destination );
        $current = $has_destination ? get_user_meta( $user_id, $destination, true ) : '';
        $has_value = self::has_value( $current );
        $content_changed = false;

        foreach ( $sources as $source ) {
            foreach ( self::source_records( $user_id, $source ) as $record ) {
                $source_value = $record['value'];
                if ( ! self::has_value( $source_value ) ) {
                    self::schedule_cleanup( $record, $cleanup_direct, $cleanup_groups );
                    continue;
                }

                if ( ! $has_value ) {
                    $current = $source_value;
                    $has_value = true;
                    $content_changed = true;
                } elseif ( ! self::equivalent_mixed( $current, $source_value ) ) {
                    $report['conflicts'][] = [
                        'destination'       => $destination,
                        'destination_value' => $current,
                        'source'            => $record['label'],
                        'source_value'      => $source_value,
                    ];
                    continue;
                }

                self::schedule_cleanup( $record, $cleanup_direct, $cleanup_groups );
            }
        }

        $reference_stale = $has_value && self::field_reference( $user_id, $destination ) !== $field_key;
        if ( ! $content_changed && ! $reference_stale ) {
            return;
        }

        $report['changed'] = true;
        ++$report['direct_fields_written'];
        self::update_field( $field_key, $current, $post_id, $dry_run, $report );
    }

    /** @param array<string,mixed> $urls @param array<string,mixed> $report */
    private static function migrate_muckrack_url(
        int $user_id,
        string $post_id,
        array $urls,
        bool $dry_run,
        array &$report
    ): void {
        $stored = get_user_meta( $user_id, 'muckrack_url', true );
        $value = self::string_value( $stored );
        $content_changed = false;

        if ( '' === $value ) {
            $value = self::string_value( $urls['muckrack'] ?? '' );
            $content_changed = '' !== $value;
        }

        $reference_stale = '' !== $value && self::field_reference( $user_id, 'muckrack_url' ) !== self::MUCKRACK_URL_FIELD_KEY;
        if ( ! $content_changed && ! $reference_stale ) {
            return;
        }

        $report['changed'] = true;
        ++$report['direct_fields_written'];
        self::update_field( self::MUCKRACK_URL_FIELD_KEY, $value, $post_id, $dry_run, $report );
    }

    /**
     * @param array<string,true>  $cleanup_direct
     * @param array<string,mixed> $report
     */
    private static function migrate_photos(
        int $user_id,
        string $post_id,
        bool $dry_run,
        array &$cleanup_direct,
        array &$report
    ): void {
        $stored = self::acf_value( 'photos', $post_id, $user_id );
        $photos = is_array( $stored ) ? $stored : [];
        $photo_ids = self::attachment_ids( $photos );
        $profile_photo = get_user_meta( $user_id, 'profile_photo', true );
        $profile_photo_exists = metadata_exists( 'user', $user_id, 'profile_photo' );
        $content_changed = false;

        if ( $profile_photo_exists && ! self::has_value( $profile_photo ) ) {
            $cleanup_direct['profile_photo'] = true;
        } elseif ( $profile_photo_exists ) {
            $profile_photo_id = self::attachment_id( $profile_photo );
            if ( $profile_photo_id <= 0 ) {
                $report['conflicts'][] = [
                    'destination'       => 'photos',
                    'destination_value' => $stored,
                    'source'            => 'profile_photo',
                    'source_value'      => $profile_photo,
                ];
            } else {
                if ( ! in_array( $profile_photo_id, $photo_ids, true ) ) {
                    $photo_ids[] = $profile_photo_id;
                    $content_changed = true;
                }
                $cleanup_direct['profile_photo'] = true;
            }
        }

        $has_photos = [] !== $photo_ids;
        $reference_stale = $has_photos && self::field_reference( $user_id, 'photos' ) !== self::PHOTOS_FIELD_KEY;
        if ( ! $content_changed && ! $reference_stale ) {
            return;
        }

        $report['changed'] = true;
        ++$report['direct_fields_written'];
        self::update_field( self::PHOTOS_FIELD_KEY, $photo_ids, $post_id, $dry_run, $report );
    }

    /** @param array<string,mixed> $report */
    private static function refresh_direct_field(
        int $user_id,
        string $meta_key,
        string $field_key,
        string $post_id,
        bool $dry_run,
        array &$report
    ): void {
        if ( ! metadata_exists( 'user', $user_id, $meta_key ) ) {
            return;
        }

        $value = get_user_meta( $user_id, $meta_key, true );
        if ( ! self::has_value( $value ) || self::field_reference( $user_id, $meta_key ) === $field_key ) {
            return;
        }

        $report['changed'] = true;
        ++$report['direct_fields_written'];
        self::update_field( $field_key, $value, $post_id, $dry_run, $report );
    }

    /**
     * @return list<array{storage:string,key:string,group:string,subfield:string,label:string,value:mixed}>
     */
    private static function source_records( int $user_id, string $source ): array {
        $records = [];
        if ( metadata_exists( 'user', $user_id, $source ) ) {
            $records[] = [
                'storage'  => 'direct',
                'key'      => $source,
                'group'    => '',
                'subfield' => '',
                'label'    => $source,
                'value'    => get_user_meta( $user_id, $source, true ),
            ];
        }

        foreach ( self::LEGACY_GROUP_NAMES as $group ) {
            $prefix = $group . '_';
            if ( ! str_starts_with( $source, $prefix ) ) {
                continue;
            }

            $subfield = substr( $source, strlen( $prefix ) );
            $group_value = get_user_meta( $user_id, $group, true );
            if ( is_array( $group_value ) && array_key_exists( $subfield, $group_value ) ) {
                $records[] = [
                    'storage'  => 'group',
                    'key'      => '',
                    'group'    => $group,
                    'subfield' => $subfield,
                    'label'    => $group . '.' . $subfield,
                    'value'    => $group_value[ $subfield ],
                ];
            }
            break;
        }

        return $records;
    }

    /**
     * @param array{storage:string,key:string,group:string,subfield:string,label:string,value:mixed} $record
     * @param array<string,true>                                                            $cleanup_direct
     * @param array<string,array<string,true>>                                               $cleanup_groups
     */
    private static function schedule_cleanup( array $record, array &$cleanup_direct, array &$cleanup_groups ): void {
        if ( 'direct' === $record['storage'] ) {
            if ( ! in_array( $record['key'], self::REUSED_CANONICAL_KEYS, true ) ) {
                $cleanup_direct[ $record['key'] ] = true;
            }
            return;
        }

        $cleanup_groups[ $record['group'] ][ $record['subfield'] ] = true;
    }

    /**
     * @param array<string,true>               $cleanup_direct
     * @param array<string,array<string,true>> $cleanup_groups
     */
    private static function cleanup_legacy_meta( int $user_id, array $cleanup_direct, array $cleanup_groups, bool $dry_run ): int {
        $cleanup_direct = array_diff_key( $cleanup_direct, array_fill_keys( self::REUSED_CANONICAL_KEYS, true ) );
        $planned = count( $cleanup_direct );

        foreach ( $cleanup_groups as $group => $subfields ) {
            $group_value = get_user_meta( $user_id, $group, true );
            if ( is_array( $group_value ) ) {
                $remaining = array_diff_key( $group_value, $subfields );
                if ( $remaining !== $group_value ) {
                    ++$planned;
                }
            }
        }

        foreach ( self::LEGACY_GROUP_NAMES as $group ) {
            $value = get_user_meta( $user_id, $group, true );
            if ( metadata_exists( 'user', $user_id, $group ) && ( ! is_array( $value ) || ! self::array_has_value( $value ) ) ) {
                ++$planned;
            }
        }

        if ( $dry_run ) {
            return $planned;
        }

        $deleted = 0;
        foreach ( array_keys( $cleanup_direct ) as $key ) {
            $exists = metadata_exists( 'user', $user_id, $key ) || metadata_exists( 'user', $user_id, '_' . $key );
            delete_user_meta( $user_id, $key );
            delete_user_meta( $user_id, '_' . $key );
            if ( $exists ) {
                ++$deleted;
            }
        }

        foreach ( $cleanup_groups as $group => $subfields ) {
            $group_value = get_user_meta( $user_id, $group, true );
            if ( ! is_array( $group_value ) ) {
                continue;
            }

            $remaining = array_diff_key( $group_value, $subfields );
            if ( $remaining === $group_value ) {
                continue;
            }

            if ( self::array_has_value( $remaining ) ) {
                update_user_meta( $user_id, $group, $remaining );
            } else {
                delete_user_meta( $user_id, $group );
                delete_user_meta( $user_id, '_' . $group );
            }
            ++$deleted;
        }

        foreach ( self::LEGACY_GROUP_NAMES as $group ) {
            $value = get_user_meta( $user_id, $group, true );
            if ( ! metadata_exists( 'user', $user_id, $group ) || ( is_array( $value ) && self::array_has_value( $value ) ) ) {
                continue;
            }

            delete_user_meta( $user_id, $group );
            delete_user_meta( $user_id, '_' . $group );
            ++$deleted;
        }

        return $deleted;
    }

    /** @param array<string,mixed> $report */
    private static function update_field( string $field_key, mixed $value, string $post_id, bool $dry_run, array &$report ): void {
        if ( $dry_run ) {
            return;
        }

        // ACF returns false when the value is unchanged, even if it refreshed
        // the hidden field-key reference. Verification runs after migration.
        update_field( $field_key, $value, $post_id );
    }

    private static function field_reference( int $user_id, string $meta_key ): string {
        return self::string_value( get_user_meta( $user_id, '_' . $meta_key, true ) );
    }

    private static function acf_value( string $field_name, string $post_id, int $user_id ): mixed {
        if ( function_exists( 'get_field' ) ) {
            // ACF group values use field keys in raw mode and field names in
            // formatted mode. Migrations compare against canonical names.
            $value = get_field( $field_name, $post_id, true );
            if ( false !== $value && null !== $value ) {
                return $value;
            }
        }

        return get_user_meta( $user_id, $field_name, true );
    }

    /** @param array<mixed> $values @return list<int> */
    private static function attachment_ids( array $values ): array {
        $ids = [];
        foreach ( $values as $value ) {
            $id = self::attachment_id( $value );
            if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    private static function attachment_id( mixed $value ): int {
        if ( is_numeric( $value ) ) {
            return max( 0, (int) $value );
        }
        if ( is_array( $value ) ) {
            return max( 0, (int) ( $value['ID'] ?? $value['id'] ?? 0 ) );
        }
        return 0;
    }

    /** @param array<mixed> $values */
    private static function array_has_value( array $values ): bool {
        foreach ( $values as $value ) {
            if ( self::has_value( $value ) ) {
                return true;
            }
        }
        return false;
    }

    private static function has_value( mixed $value ): bool {
        return '' !== $value && null !== $value && [] !== $value;
    }

    private static function string_value( mixed $value ): string {
        return is_scalar( $value ) ? trim( (string) $value ) : '';
    }

    private static function equivalent_mixed( mixed $left, mixed $right ): bool {
        if ( is_scalar( $left ) && is_scalar( $right ) ) {
            return self::equivalent( (string) $left, (string) $right );
        }
        return $left === $right;
    }

    private static function equivalent( string $left, string $right ): bool {
        $left = trim( $left );
        $right = trim( $right );
        if ( $left === $right ) {
            return true;
        }

        if ( preg_match( '#^https?://#i', $left ) && preg_match( '#^https?://#i', $right ) ) {
            return rtrim( $left, '/' ) === rtrim( $right, '/' );
        }

        return false;
    }
}
