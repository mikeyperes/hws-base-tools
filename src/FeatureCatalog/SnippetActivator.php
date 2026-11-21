<?php

namespace HWS\BaseTools\FeatureCatalog;

use Hexa\PluginCore\SnippetRegistry\LegacySnippetActivator;

final class SnippetActivator {
    /**
     * @param array<int,array<string,mixed>> $snippets
     */
    public function activate( array $snippets ): void {
        LegacySnippetActivator::activate( $snippets, 'hws_base_tools', [ RuntimeLogger::class, 'log' ], verbose: false );
    }
}
