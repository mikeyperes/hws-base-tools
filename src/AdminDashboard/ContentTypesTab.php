<?php

namespace HWS\BaseTools\AdminDashboard;

use Hexa\PluginCore\ContentTypes\ContentTypeRenderer;
use Hexa\PluginCore\EntitySources\EntityFieldInventoryRenderer;
use Hexa\PluginCore\FieldStructures\AcfFieldGroupRenderer;
use HWS\BaseTools\AcfFields\SharedAcfStructures;
use HWS\BaseTools\ContentTypes\SharedContentTypes;
use HWS\BaseTools\SiteProfile\PrimaryEntityIntegration;

defined( 'ABSPATH' ) || exit;

final class ContentTypesTab {
    public static function render(): void {
        echo ( new ContentTypeRenderer() )->render(
            SharedContentTypes::registry(),
            [
                'title' => 'Custom Post Types',
                'description' => 'HWS-owned shared content types. Organization fields and schema are extended by SMC; Knowledge Base and Resources are owned by SMP Publication.',
                'persist_prefix' => 'hws',
            ]
        );

        echo ( new AcfFieldGroupRenderer() )->render(
            SharedAcfStructures::registry(),
            [
                'title'          => 'ACF Structures',
                'description'    => 'Manage site, user, sponsored-content, and RSS field groups from the same shared Core interface.',
                'persist_prefix' => 'hws-acf-structures',
            ]
        );

        echo ( new EntityFieldInventoryRenderer() )->render(
            PrimaryEntityIntegration::manager()->resolve(),
            [
                'title'          => 'All available WordPress and ACF fields',
                'description'    => 'Inspect every WordPress and ACF field available to the selected primary author. Empty fields remain listed, while protected credential values are never printed.',
                'persist_prefix' => 'hws-primary-author-fields',
            ]
        );
    }
}
