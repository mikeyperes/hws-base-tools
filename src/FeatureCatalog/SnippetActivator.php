<?php

namespace HWS\BaseTools\FeatureCatalog;

final class SnippetActivator {
    /**
     * @param array<int,array<string,mixed>> $snippets
     */
    public function activate( array $snippets ): void {
        foreach ( $snippets as $snippet ) {
            $id       = isset( $snippet['id'] ) ? (string) $snippet['id'] : '';
            $function = isset( $snippet['function'] ) ? (string) $snippet['function'] : '';

            if ( '' === $id || '' === $function || ! get_option( $id, false ) ) {
                continue;
            }

            $callback = 'hws_base_tools\\' . ltrim( $function, '\\' );
            if ( ! is_callable( $callback ) ) {
                RuntimeLogger::log( "Enabled feature {$id} has no callable {$callback}.", true );
                continue;
            }

            call_user_func( $callback );
            RuntimeLogger::log( "Activated feature {$id}.", false );
        }
    }
}
