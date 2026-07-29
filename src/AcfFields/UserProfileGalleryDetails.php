<?php

declare( strict_types=1 );

namespace HWS\BaseTools\AcfFields;

use Hexa\PluginCore\FieldStructures\AcfGalleryDetailsModule;

defined( 'ABSPATH' ) || exit;

final class UserProfileGalleryDetails {
    public const FIELD_KEY = 'field_hws_user_profile_2025_photos';

    public static function module(): AcfGalleryDetailsModule {
        return new AcfGalleryDetailsModule(
            [
                'field_key'          => self::FIELD_KEY,
                'title'              => 'Details',
                'persist_key'        => 'hws-user-profile-photos-details',
                'preview_pixels'     => 112,
                'preview_image_size' => 'medium',
                'allow_remove'       => true,
                'live_refresh'       => true,
            ]
        );
    }
}
