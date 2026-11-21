<?php

namespace HWS\BaseTools\FeatureCatalog;

use Hexa\PluginCore\LogFiles\DebugLogger;

final class RuntimeLogger {
    public static function log( mixed $value, bool $full_debug = false, bool $display_stack = false ): void {
        DebugLogger::write( $value, $full_debug, $display_stack, DebugLogger::ASCII_STACK, '[HWS Base Tools] ', 3 );
    }
}
