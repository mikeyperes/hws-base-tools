<?php

namespace HWS\BaseTools\FeatureCatalog;

final class FeatureValueResolver {
    public static function text( mixed $value, int $max_depth = 4 ): string {
        $depth = 0;

        while ( is_callable( $value ) && $depth < $max_depth ) {
            $value = call_user_func( $value );
            ++$depth;
        }

        return is_scalar( $value ) ? (string) $value : '';
    }
}
