<?php

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'enable_smp_acf_user' ) ) {
    function enable_smp_acf_user(): void { \hws_base_tools\register_user_custom_fields_2025(); \hws_base_tools\register_user_custom_fields_additional_2025(); }
}
