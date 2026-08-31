<?php

declare( strict_types=1 );

namespace {
    $test_root = sys_get_temp_dir() . '/hws-favicon-' . bin2hex( random_bytes( 6 ) ) . '/';
    mkdir( $test_root, 0777, true );
    define( 'ABSPATH', $test_root );

    $GLOBALS['favicon_site_icon_url'] = '';
    $GLOBALS['favicon_generator_calls'] = 0;

    function get_site_icon_url( int $size = 512 ): string {
        return $GLOBALS['favicon_site_icon_url'];
    }

    function home_url( string $path = '/' ): string {
        return 'https://favicon.example.test' . $path;
    }

    function sanitize_title( string $value ): string {
        return strtolower( preg_replace( '/[^a-z0-9]+/i', '-', trim( $value ) ) ?? '' );
    }

    function get_bloginfo( string $show = '' ): string {
        return 'Example Site';
    }

    function is_wp_error( mixed $value ): bool {
        return false;
    }
}

namespace hws_base_tools {
    function hws_create_letter_site_icon( string $letter, string $background, string $foreground ): array {
        ++$GLOBALS['favicon_generator_calls'];
        file_put_contents( ABSPATH . 'favicon.ico', 'generated-icon' );
        return [ 'attachment_id' => 42, 'icon_url' => 'https://favicon.example.test/generated.png' ];
    }
}

namespace {
    require dirname( __DIR__ ) . '/src/QuickStart/SiteConfigurationService.php';

    use HWS\BaseTools\QuickStart\SiteConfigurationService;

    $passed = 0;
    $failed = 0;
    function favicon_expect( bool $condition, string $message ): void {
        global $passed, $failed;
        if ( $condition ) {
            ++$passed;
            echo "PASS {$message}\n";
            return;
        }
        ++$failed;
        echo "FAIL {$message}\n";
    }

    $service = new SiteConfigurationService();

    $GLOBALS['favicon_site_icon_url'] = 'https://favicon.example.test/existing-site-icon.png';
    $site_icon = $service->generate_favicon();
    favicon_expect( $site_icon['success'] && 'preserved' === $site_icon['data']['action'] && $site_icon['data']['site_icon_present'], 'an existing WordPress Site Icon is preserved' );
    favicon_expect( 0 === $GLOBALS['favicon_generator_calls'] && ! is_file( ABSPATH . 'favicon.ico' ), 'Site Icon preservation performs no fallback generation or file mutation' );

    $GLOBALS['favicon_site_icon_url'] = '';
    file_put_contents( ABSPATH . 'favicon.ico', 'existing-root-icon' );
    $root_icon = $service->generate_favicon();
    favicon_expect( $root_icon['success'] && 'preserved' === $root_icon['data']['action'] && $root_icon['data']['root_icon_present'], 'an existing physical favicon.ico is preserved' );
    favicon_expect( 'existing-root-icon' === file_get_contents( ABSPATH . 'favicon.ico' ) && 0 === $GLOBALS['favicon_generator_calls'], 'physical favicon preservation leaves the original bytes unchanged' );

    unlink( ABSPATH . 'favicon.ico' );
    $missing = $service->generate_favicon();
    favicon_expect( $missing['success'] && 'generated' === $missing['data']['action'], 'the HWS fallback is generated only when no favicon exists' );
    favicon_expect( 1 === $GLOBALS['favicon_generator_calls'] && 'generated-icon' === file_get_contents( ABSPATH . 'favicon.ico' ), 'missing favicon generation produces and verifies the fallback file' );

    unlink( ABSPATH . 'favicon.ico' );
    rmdir( ABSPATH );

    echo "\n{$passed} passed, {$failed} failed.\n";
    exit( 0 === $failed ? 0 : 1 );
}
