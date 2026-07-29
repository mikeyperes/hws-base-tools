<?php

declare( strict_types=1 );

namespace HWS\BaseTools\AcfFields;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\WpAdminComponents\MediaGalleryDetailsRenderer;

defined( 'ABSPATH' ) || exit;

final class UserProfileGalleryDetails implements ModuleInterface {
    public const FIELD_KEY = 'field_hws_user_profile_2025_photos';

    public function register(): void {
        add_action( 'acf/render_field/key=' . self::FIELD_KEY, [ $this, 'render' ] );
    }

    /** @param array<string,mixed> $field */
    public function render( array $field ): void {
        $value = $field['value'] ?? [];
        if ( ! is_array( $value ) ) {
            $value = [];
        }

        echo MediaGalleryDetailsRenderer::render(
            $value,
            [
                'title'       => 'Details',
                'persist_key' => 'hws-user-profile-photos-details',
            ]
        );
    }
}
