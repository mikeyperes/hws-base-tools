<?php

namespace HWS\BaseTools\Editorial;

use Hexa\PluginCore\DraftPreview\PublicDraftPreview;

defined( 'ABSPATH' ) || exit;

/**
 * HWS toggle for Core's public draft preview: drafts dated within the last
 * 24 hours open for anyone at their own draft URL. Default off.
 */
final class PublicDraftPreviewFeature {
    public const FEATURE_OPTION = 'enable_public_draft_preview';

    private static bool $active = false;

    public static function activate(): void {
        if ( self::$active || ! class_exists( PublicDraftPreview::class ) ) {
            return;
        }

        self::$active = true;
        ( new PublicDraftPreview() )->register();
    }

    /** @return array<string,mixed> */
    public static function definition(): array {
        return [
            'id'               => self::FEATURE_OPTION,
            'name'             => 'Public Draft Preview (24 hours)',
            'description'      => 'Lets anyone, without logging in, open a draft dated within the last 24 hours at its own draft URL.',
            'info'             => 'Default off. The URL is the one WordPress gives a draft: /?p=ID for posts, /?page_id=ID for pages, /?post_type=TYPE&p=ID for other types. The 24 hours count from the draft\'s date, which WordPress moves to the last save unless a fixed date was set. After that the URL returns 404. The view is never cached, is marked noindex, and has comments closed. Drafts are not listed anywhere, but post IDs are sequential, so anyone who guesses an ID can read a draft that is still inside its 24 hours.',
            'function'         => 'enable_public_draft_preview',
            'scope_admin_only' => false,
            'code_example'     => 'https://example.com/?p=123',
        ];
    }
}
