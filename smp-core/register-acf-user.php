<?php

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'enable_smp_acf_user' ) ) {
    function enable_smp_acf_user(): void { \HWS\BaseTools\AcfFields\LegacySmpUserFields::register(); }
}
