<?php

namespace HWS\BaseTools\AdminDashboard;

use Hexa\PluginCore\ContentTypes\ContentTypeRenderer;
use Hexa\PluginCore\FieldStructures\AcfFieldGroupRenderer;
use HWS\BaseTools\AcfFields\SharedAcfStructures;
use HWS\BaseTools\ContentTypes\SharedContentTypes;

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
    }
}
