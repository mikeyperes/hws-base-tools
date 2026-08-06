<?php

declare(strict_types=1);

$root      = dirname( __DIR__ );
$workspace = sys_get_temp_dir() . '/hws-bootstrap-seeder-' . bin2hex( random_bytes( 6 ) );
$site      = $workspace . '/site';
$dry_site  = $workspace . '/dry-site';
$loader    = $root . '/deploy/hws-base-tools-bootstrap.php';
$failures  = [];

foreach ( [ $site, $dry_site ] as $candidate ) {
    mkdir( $candidate . '/wp-content/mu-plugins', 0777, true );
    file_put_contents( $candidate . '/wp-load.php', "<?php\n" );
}
file_put_contents( $site . '/wp-content/mu-plugins/hws-base-tools-bootstrap.php', "<?php // old loader\n" );

$run = static function ( array $arguments ) use ( $root ): array {
    $command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $root . '/deploy/seed-bootstrap-fleet.php' );
    foreach ( $arguments as $argument ) {
        $command .= ' ' . escapeshellarg( $argument );
    }
    $lines = [];
    $code  = 0;
    exec( $command . ' 2>&1', $lines, $code );
    return [ 'code' => $code, 'lines' => $lines ];
};

$applied = $run( [ '--root=' . $site, '--loader=' . $loader, '--apply' ] );
if ( 0 !== $applied['code']
    || hash_file( 'sha256', $loader ) !== hash_file( 'sha256', $site . '/wp-content/mu-plugins/hws-base-tools-bootstrap.php' )
    || glob( $site . '/wp-content/mu-plugins/.hws-base-tools-bootstrap.*' )
) {
    $failures[] = 'Applied seeding did not atomically install and clean the verified loader.';
}

$dry = $run( [ '--root=' . $dry_site, '--loader=' . $loader ] );
if ( 0 !== $dry['code'] || file_exists( $dry_site . '/wp-content/mu-plugins/hws-base-tools-bootstrap.php' ) ) {
    $failures[] = 'Dry-run seeding changed the WordPress fixture.';
}

$outside = $workspace . '/outside-loader.php';
file_put_contents( $outside, "<?php // protected target\n" );
$link = $dry_site . '/wp-content/mu-plugins/hws-base-tools-bootstrap.php';
if ( function_exists( 'symlink' ) && @symlink( $outside, $link ) ) {
    $rejected = $run( [ '--root=' . $dry_site, '--loader=' . $loader, '--apply' ] );
    if ( 0 === $rejected['code'] || "<?php // protected target\n" !== file_get_contents( $outside ) ) {
        $failures[] = 'Symlink target was not rejected without touching its destination.';
    }
}

delete_fixture( $workspace );
if ( [] !== $failures ) {
    foreach ( $failures as $failure ) {
        fwrite( STDERR, 'FAIL: ' . $failure . "\n" );
    }
    exit( 1 );
}
echo "PASS: fleet seeder dry-run, staged apply, cleanup, and symlink rejection verified.\n";

function delete_fixture( string $path ): void {
    if ( ! str_starts_with( $path, sys_get_temp_dir() . '/hws-bootstrap-seeder-' ) || ! file_exists( $path ) ) {
        return;
    }
    if ( is_link( $path ) || is_file( $path ) ) {
        unlink( $path );
        return;
    }
    foreach ( array_diff( scandir( $path ) ?: [], [ '.', '..' ] ) as $entry ) {
        delete_fixture( $path . '/' . $entry );
    }
    rmdir( $path );
}
