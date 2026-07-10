<?php

namespace HWS\BaseTools\AcfFields;

use HWS\BaseTools\PluginRuntime\PluginMetadata;

final class AcfModule {
    public static function register(): void {
        foreach ( [
            'register-acf-fields-user.php',
            'register-acf-fields-rss.php',
            'register-acf-sponsored-functionality.php',
            'register-acf-website-settings.php',
        ] as $file ) {
            self::load( $file );
        }

        self::load_enabled_legacy_smp_features();

        if ( function_exists( 'hws_base_tools\\activate_snippets' ) ) {
            \hws_base_tools\activate_snippets( 'acf' );
        }
    }

    private static function load_enabled_legacy_smp_features(): void {
        $features = [
            'smp_enable_cpt_organization' => 'src/AcfFields/LegacySmp/register-post-type-organization.php',
            'smp_enable_cpt_teammember'   => 'src/AcfFields/LegacySmp/register-post-type-team-member.php',
            'enable_cpt_testimonial'      => 'src/AcfFields/LegacySmp/register-post-type-testimonial.php',
            'smp_enable_acf_organization' => 'src/AcfFields/LegacySmp/register-acf-organization.php',
            'smp_enable_acf_teammember'   => 'src/AcfFields/LegacySmp/register-acf-team-member.php',
            'enable_acf_testimonial'      => 'src/AcfFields/LegacySmp/register-acf-testimonial.php',
        ];

        foreach ( $features as $option => $file ) {
            if ( get_option( $option, false ) ) {
                self::load( $file );
            }
        }

        // The old HWS-owned user group is disabled unless a site explicitly
        // opts into compatibility mode. Person/publication plugins own it now.
        if ( get_option( 'hws_enable_legacy_smp_user_fields', false ) ) {
            LegacySmpUserFields::register();
        }
    }

    private static function load( string $relative_path ): void {
        $path = PluginMetadata::root_path() . '/' . ltrim( $relative_path, '/\\' );
        if ( is_readable( $path ) ) {
            require_once $path;
        }
    }
}
