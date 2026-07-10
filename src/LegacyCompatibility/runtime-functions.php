<?php

namespace hws_base_tools;

use HWS\BaseTools\FeatureCatalog\RuntimeLogger;
use HWS\BaseTools\FeatureCatalog\SnippetActivator;

if ( ! function_exists( __NAMESPACE__ . '\\activate_snippets' ) ) {
    function activate_snippets( string $type = '' ): void {
        $snippets = function_exists( __NAMESPACE__ . '\\get_snippets' )
            ? get_snippets( $type )
            : [];

        ( new SnippetActivator() )->activate( is_array( $snippets ) ? $snippets : [] );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\write_log' ) ) {
    function write_log( mixed $value, bool $full_debug = false, bool $display_stack = false ): void {
        RuntimeLogger::log( $value, $full_debug, $display_stack );
    }
}
