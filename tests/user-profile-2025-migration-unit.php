<?php

declare( strict_types=1 );

$users = [ 1, 2 ];
$user_meta = [
    1 => [
        'urls'          => [ 'linkedin' => 'https://linkedin.com/in/canonical' ],
        '_urls'         => 'field_684253229b32a',
        'linkedin_url'  => 'https://linkedin.com/in/legacy',
        '_linkedin_url' => 'field_legacy_linkedin',
        'job_title'     => 'Founder',
    ],
    2 => [
        'urls'                    => [],
        '_urls'                   => 'field_legacy_urls',
        'facebook_url'            => 'https://facebook.com/example',
        '_facebook_url'           => 'field_legacy_facebook',
        'well_found_url'          => 'https://wellfound.com/company/example',
        'socials'                 => [ 'linkedin' => 'https://linkedin.com/company/example' ],
        '_socials'                => 'field_legacy_socials',
        'job_title'               => 'Chief Executive Officer',
        '_job_title'              => 'field_legacy_title',
        'settings'                => [ 'staff_writer' => '1', 'muckrack_verified' => '0' ],
        '_settings'               => 'field_legacy_settings',
        'muck_rack_url'           => 'https://muckrack.com/example',
        '_muck_rack_url'          => 'field_legacy_muckrack',
        'profile_photo'           => '101',
        '_profile_photo'          => 'field_legacy_photo',
        'photos'                  => [ 102 ],
        '_photos'                 => 'field_legacy_photos',
        'location'                => 'New York, NY',
        '_location'               => 'field_legacy_location',
        'additional'              => [ 'public_email' => 'hello@example.com' ],
        '_additional'             => 'field_legacy_additional',
    ],
];
$write_log = [];
$hooks = [];
$removed_groups = [];
$options = [];

function add_action( string $hook, callable $callback, int $priority = 10 ): void {
    $GLOBALS['hooks'][] = [ $hook, $callback, $priority ];
}

function get_option( string $key, mixed $default = false ): mixed {
    return $GLOBALS['options'][ $key ] ?? $default;
}

function get_users( array $args = [] ): array {
    return $GLOBALS['users'];
}

function get_userdata( int $user_id ): object|false {
    if ( ! in_array( $user_id, $GLOBALS['users'], true ) ) {
        return false;
    }
    return (object) [ 'ID' => $user_id, 'user_url' => '' ];
}

function metadata_exists( string $type, int $user_id, string $key ): bool {
    return array_key_exists( $key, $GLOBALS['user_meta'][ $user_id ] ?? [] );
}

function get_user_meta( int $user_id, string $key, bool $single = false ): mixed {
    return $GLOBALS['user_meta'][ $user_id ][ $key ] ?? '';
}

function get_field( string $field_name, string $post_id, bool $format_value = true ): mixed {
    $user_id = (int) substr( $post_id, 5 );
    return $GLOBALS['user_meta'][ $user_id ][ $field_name ] ?? false;
}

function update_user_meta( int $user_id, string $key, mixed $value ): bool {
    $GLOBALS['write_log'][] = [ 'update_user_meta', $user_id, $key, $value ];
    $GLOBALS['user_meta'][ $user_id ][ $key ] = $value;
    return true;
}

function delete_user_meta( int $user_id, string $key ): bool {
    $GLOBALS['write_log'][] = [ 'delete_user_meta', $user_id, $key ];
    unset( $GLOBALS['user_meta'][ $user_id ][ $key ] );
    return true;
}

function update_field( string $field_key, mixed $value, string $post_id ): bool {
    $field_names = [
        'field_684253229b32a'                    => 'urls',
        'field_684348d1832e2'                    => 'subtitle',
        'field_hws_user_profile_2025_location'   => 'location',
        'field_hws_user_profile_2025_photos'     => 'photos',
        'field_6842_additional_group'            => 'additional',
        'field_hws_additional_staff_writer'      => 'staff_writer',
        'field_hws_additional_muckrack_verified' => 'muckrack_verified',
        'field_hws_additional_muckrack_url'      => 'muckrack_url',
    ];
    $user_id = (int) substr( $post_id, 5 );
    $name = $field_names[ $field_key ] ?? '';
    if ( '' === $name ) {
        return false;
    }
    $GLOBALS['write_log'][] = [ 'update_field', $user_id, $field_key, $value ];
    $GLOBALS['user_meta'][ $user_id ][ $name ] = $value;
    $GLOBALS['user_meta'][ $user_id ][ '_' . $name ] = $field_key;
    // Match ACF's no-op contract: references can be refreshed while the
    // top-level call reports that the field value itself did not change.
    return false;
}

function acf_remove_local_field_group( string $group_key ): void {
    $GLOBALS['removed_groups'][] = $group_key;
}

require_once dirname( __DIR__ ) . '/src/AcfFields/UserProfile2025Migration.php';

use HWS\BaseTools\AcfFields\UserProfile2025Migration;

$failures = [];
$expect = static function ( bool $condition, string $message ) use ( &$failures ): void {
    echo ( $condition ? 'PASS ' : 'FAIL ' ) . $message . PHP_EOL;
    if ( ! $condition ) {
        $failures[] = $message;
    }
};

UserProfile2025Migration::register();
$expect(
    isset( $hooks[0] ) && 'acf/init' === $hooks[0][0] && PHP_INT_MAX === $hooks[0][2],
    'deprecated local groups are suppressed after all ACF registrations'
);

UserProfile2025Migration::remove_deprecated_local_groups();
$expect( [] === $removed_groups, 'deprecated groups remain visible until the canonical option is enabled' );
$options[ UserProfile2025Migration::PROFILE_OPTION ] = 1;
UserProfile2025Migration::remove_deprecated_local_groups();
$expect(
    UserProfile2025Migration::DEPRECATED_GROUP_KEYS === $removed_groups,
    'both known deprecated profile groups are removed when the canonical option is enabled'
);

$snapshot = $user_meta;
$conflict_report = UserProfile2025Migration::migrate_user( 1, true, true );
$expect( [] !== $conflict_report['conflicts'], 'different canonical and legacy values are reported as conflicts' );
$expect( 0 === $conflict_report['legacy_keys_deleted'], 'a conflict prevents legacy deletion for that user' );
$expect( [] === $write_log && $snapshot === $user_meta, 'dry-run with a conflict performs no writes' );

$snapshot = $user_meta;
$dry_report = UserProfile2025Migration::migrate_user( 2, true, true );
$expect( [] === $dry_report['conflicts'] && [] === $dry_report['errors'], 'clean legacy data passes migration preflight' );
$expect( $dry_report['urls_written'] >= 4, 'dry-run reports flat and nested URLs that would be migrated' );
$expect( $dry_report['legacy_keys_deleted'] > 0, 'dry-run reports legacy metadata that would be retired' );
$expect( [] === $write_log && $snapshot === $user_meta, 'clean dry-run performs no writes or deletions' );

$real_report = UserProfile2025Migration::migrate_user( 2, false, true );
$migrated = $user_meta[2];
$expect( [] === $real_report['conflicts'] && [] === $real_report['errors'], 'real migration completes without conflicts or errors' );
$expect( 'https://facebook.com/example' === ( $migrated['urls']['facebook'] ?? '' ), 'flat Facebook URL reaches the canonical URLs group' );
$expect( 'https://linkedin.com/company/example' === ( $migrated['urls']['linkedin'] ?? '' ), 'nested social URL reaches the canonical URLs group' );
$expect( 'https://wellfound.com/company/example' === ( $migrated['urls']['wellfound'] ?? '' ), 'Wellfound remains distinct in the canonical URLs group' );
$expect( 'Chief Executive Officer' === ( $migrated['subtitle'] ?? '' ), 'legacy title reaches the canonical subtitle' );
$expect( '1' === ( $migrated['staff_writer'] ?? '' ), 'nested settings value reaches the canonical direct field' );
$expect( '0' === ( $migrated['muckrack_verified'] ?? '' ), 'a false boolean is preserved as meaningful profile data' );
$expect( [ 102, 101 ] === ( $migrated['photos'] ?? [] ), 'legacy profile photo is merged into the canonical gallery' );
$expect( 'field_hws_user_profile_2025_location' === ( $migrated['_location'] ?? '' ), 'same-name legacy fields receive the canonical ACF reference' );
$expect( ! isset( $migrated['facebook_url'], $migrated['job_title'], $migrated['profile_photo'], $migrated['settings'], $migrated['socials'] ), 'migrated deprecated metadata is removed' );
$expect( isset( $migrated['location'], $migrated['additional'], $migrated['photos'] ), 'canonical same-name content is retained' );

if ( $failures ) {
    exit( 1 );
}

echo 'Canonical user-profile migration verified.' . PHP_EOL;
