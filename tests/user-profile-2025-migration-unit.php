<?php

declare( strict_types=1 );

/** Verifies the profile migration using native Core fields and WordPress metadata. */

$users = [ 1, 2 ];
$user_meta = [
    1 => [
        'urls'          => '',
        'urls_linkedin' => 'https://linkedin.com/in/canonical',
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
        'wikidata_url'            => 'https://www.wikidata.org/wiki/Q42',
        '_wikidata_url'           => 'field_legacy_wikidata',
        'threads_url'             => 'https://www.threads.net/@example',
        '_threads_url'            => 'field_legacy_threads',
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
        'team_member'             => '1',
        '_team_member'            => 'field_legacy_team_member',
        'team_member_title'       => 'Editor',
        '_team_member_title'      => 'field_legacy_team_member_title',
        'what_best_describe_you'  => 'Journalist',
        '_what_best_describe_you' => 'field_legacy_profile_type',
    ],
];
$write_log = [];
$options = [];
$get_users_calls = [];

function get_option( string $key, mixed $default = false ): mixed {
    return $GLOBALS['options'][ $key ] ?? $default;
}

function get_users( array $args = [] ): array {
    $GLOBALS['get_users_calls'][] = $args;
    return $GLOBALS['users'];
}

function get_userdata( int $user_id ): object|false {
    if ( ! in_array( $user_id, $GLOBALS['users'], true ) ) {
        return false;
    }
    return (object) [ 'ID' => $user_id, 'user_url' => '' ];
}

// Core formats the registered gallery fields via the same WordPress media API as a live site.
final class WP_Post {
    public string $post_type = 'attachment';
    public string $post_title = 'Profile photo';
    public string $post_mime_type = 'image/jpeg';
    public string $post_author = '2';
    public string $post_content = '';
    public string $post_excerpt = '';
    public string $post_name = 'profile-photo';
    public string $post_status = 'inherit';
    public int $post_parent = 0;
    public string $post_date_gmt = '';
    public string $post_modified_gmt = '';
    public int $menu_order = 0;
}

function get_post( int $post_id ): ?WP_Post {
    return in_array( $post_id, [ 101, 102 ], true ) ? new WP_Post() : null;
}

function wp_get_attachment_url( int $post_id ): string {
    return 'https://example.test/uploads/' . $post_id . '.jpg';
}

function wp_get_attachment_metadata( int $post_id ): array {
    return [ 'width' => 100, 'height' => 100 ];
}

function get_attached_file( int $post_id ): string {
    return '';
}

function wp_basename( string $path ): string {
    return basename( $path );
}

function get_attachment_link( int $post_id ): string {
    return 'https://example.test/?attachment_id=' . $post_id;
}

function get_post_meta( int $post_id, string $key, bool $single = false ): mixed {
    return get_metadata( 'post', $post_id, $key, $single );
}

function wp_mime_type_icon( int $post_id ): string {
    return '';
}

function get_intermediate_image_sizes(): array {
    return [];
}

function get_user_meta( int $user_id, string $key, bool $single = false ): mixed {
    return get_metadata( 'user', $user_id, $key, $single );
}

function update_user_meta( int $user_id, string $key, mixed $value ): bool {
    return update_metadata( 'user', $user_id, $key, $value );
}

function delete_user_meta( int $user_id, string $key ): bool {
    return delete_metadata( 'user', $user_id, $key );
}

require_once __DIR__ . '/fixtures/native-migration-storage.php';
require_once dirname( __DIR__ ) . '/src/AcfFields/QuoteRepeaterDefinition.php';
require_once dirname( __DIR__ ) . '/src/AcfFields/user-profile-2025.php';
require_once dirname( __DIR__ ) . '/src/AcfFields/UserProfile2025Migration.php';

use Hexa\PluginCore\Fields\Field;
use Hexa\PluginCore\Fields\FieldGroups;
use HWS\BaseTools\AcfFields\UserProfile2025Migration;

$failures = [];
$expect = static function ( bool $condition, string $message ) use ( &$failures ): void {
    echo ( $condition ? 'PASS ' : 'FAIL ' ) . $message . PHP_EOL;
    if ( ! $condition ) {
        $failures[] = $message;
    }
};

FieldGroups::add( \hws_base_tools\hws_user_profile_2025_group() );
FieldGroups::add( \hws_base_tools\hws_user_additional_fields_group() );
foreach ( UserProfile2025Migration::DEPRECATED_GROUP_KEYS as $key ) {
    FieldGroups::add( [ 'key' => $key ] );
}

UserProfile2025Migration::register();
$init_registrations = $GLOBALS['hexa_test_hooks']['hexa_fields/init'] ?? [];
$expect(
    [] !== $init_registrations && PHP_INT_MAX === $init_registrations[0][1],
    'deprecated local groups are suppressed after all field-group registrations'
);

$registered_deprecated_groups = static function (): array {
    return array_values( array_intersect(
        UserProfile2025Migration::DEPRECATED_GROUP_KEYS,
        array_column( FieldGroups::native(), 'key' )
    ) );
};
UserProfile2025Migration::remove_deprecated_local_groups();
$expect( UserProfile2025Migration::DEPRECATED_GROUP_KEYS === $registered_deprecated_groups(), 'deprecated groups remain visible until the canonical option is enabled' );
$options[ UserProfile2025Migration::PROFILE_OPTION ] = 1;
UserProfile2025Migration::remove_deprecated_local_groups();
$expect( [] === $registered_deprecated_groups(), 'all known deprecated profile groups are removed when the canonical option is enabled' );

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
$urls = Field::get( 'urls', 'user_2' );
$expect( [] === $real_report['conflicts'] && [] === $real_report['errors'], 'real migration completes without conflicts or errors' );
$expect( 'https://facebook.com/example' === ( $urls['facebook'] ?? '' ), 'flat Facebook URL reaches the canonical URLs group' );
$expect( 'https://linkedin.com/company/example' === ( $urls['linkedin'] ?? '' ), 'nested social URL reaches the canonical URLs group' );
$expect( 'https://wellfound.com/company/example' === ( $urls['wellfound'] ?? '' ), 'Wellfound remains distinct in the canonical URLs group' );
$expect( 'https://www.wikidata.org/wiki/Q42' === ( $urls['wikidata'] ?? '' ), 'legacy Wikidata URL reaches the canonical URLs group' );
$expect( 'https://www.threads.net/@example' === ( $urls['threads'] ?? '' ), 'legacy Threads URL reaches the canonical URLs group' );
$expect( 'Chief Executive Officer' === Field::get( 'subtitle', 'user_2' ), 'legacy title reaches the canonical subtitle' );
$expect( true === Field::get( 'staff_writer', 'user_2' ), 'nested settings value reaches the canonical direct field' );
$expect( '0' === Field::get( 'muckrack_verified', 'user_2', false ), 'a false boolean is preserved as meaningful profile data' );
$expect( [ 102, 101 ] === array_column( Field::get( 'photos', 'user_2' ) ?: [], 'ID' ), 'legacy profile photo is merged into the canonical gallery' );
$expect( 'field_hws_user_profile_2025_location' === ( $migrated['_location'] ?? '' ), 'same-name legacy fields receive the canonical ACF reference' );
$expect( 'field_hws_user_profile_2025_team_member' === ( $migrated['_team_member'] ?? '' ), 'team-member compatibility flag receives canonical ownership' );
$expect( 'field_hws_user_profile_2025_profile_type' === ( $migrated['_what_best_describe_you'] ?? '' ), 'publication profile classification receives canonical ownership' );
$expect( ! isset( $migrated['facebook_url'], $migrated['job_title'], $migrated['settings'], $migrated['socials'] ), 'migrated deprecated metadata is removed' );
$expect( '101' === Field::get( 'profile_photo', 'user_2', false ), 'primary profile photo remains available under its compatibility field name' );
$expect( 'New York, NY' === Field::get( 'location', 'user_2' ) && 'hello@example.com' === ( Field::get( 'additional', 'user_2' )['public_email'] ?? '' ) && isset( $migrated['photos'] ), 'canonical same-name content is retained' );
$batch_report = UserProfile2025Migration::migrate_all( true, false );
$expect(
    2 === $batch_report['users_scanned']
        && 100 === ( $get_users_calls[0]['number'] ?? 0 )
        && 0 === ( $get_users_calls[0]['offset'] ?? -1 )
        && 'ID' === ( $get_users_calls[0]['orderby'] ?? '' )
        && false === ( $get_users_calls[0]['count_total'] ?? null ),
    'full profile migration scans every user through a bounded deterministic batch'
);

if ( $failures ) {
    exit( 1 );
}

echo 'Canonical user-profile migration verified.' . PHP_EOL;
