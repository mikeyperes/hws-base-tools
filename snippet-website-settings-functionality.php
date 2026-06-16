<?php
namespace hws_base_tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Internal: hard gate so admin-only UI injections only run on:
 * /wp-admin/admin.php?page=website-settings
 *
 * We keep this separate so hf_render_user_info_once() and admin_enqueue_scripts hooks
 * can share the same guard and avoid running on CPT edit screens.
 *
 * @return bool
 */
function hws_is_website_settings_admin_page(): bool {
	if ( ! is_admin() ) {
		return false;
	}

	global $pagenow;

	// Must be admin.php?page=website-settings
	if ( $pagenow !== 'admin.php' ) {
		return false;
	}

	if ( empty( $_GET['page'] ) || $_GET['page'] !== 'website-settings' ) {
		return false;
	}

	return true;
}

// register the shortcode
// NOTE: This must be at file level, not inside a hook, because this file
// is included AFTER init fires (inside an init callback). Shortcodes register
// immediately when add_shortcode is called - they don't need a hook.
add_shortcode( 'website_url', __NAMESPACE__ . '\\website_url_shortcode' );
add_shortcode( 'website_content', __NAMESPACE__ . '\\website_content_shortcode' );
add_shortcode( 'founder', __NAMESPACE__ . '\\founder_shortcode' );
add_shortcode( 'company', __NAMESPACE__ . '\\company_shortcode' );
add_shortcode( 'hws_brand_asset', __NAMESPACE__ . '\\hws_brand_asset_shortcode' );
add_shortcode( 'site_logo', __NAMESPACE__ . '\\hws_brand_asset_shortcode' );
add_shortcode( 'brand_asset_gallery', __NAMESPACE__ . '\\hws_brand_gallery_shortcode' );
add_shortcode( 'site_gallery', __NAMESPACE__ . '\\hws_brand_gallery_shortcode' );

function hws_get_brand_highlight_background_color(): string {
	$legacy_background = sanitize_hex_color( (string) get_option( 'hws_brand_highlight_text_color', '#facc15' ) );
	$background        = sanitize_hex_color( (string) get_option( 'hws_brand_highlight_background_color', $legacy_background ?: '#facc15' ) );

	return $background ?: '#facc15';
}

function hws_get_brand_highlight_text_color(): string {
	$has_background_option = get_option( 'hws_brand_highlight_background_color', null ) !== null;
	$default_text          = $has_background_option ? (string) get_option( 'hws_brand_highlight_text_color', '#111827' ) : '#111827';
	$text                  = sanitize_hex_color( $default_text );

	return $text ?: '#111827';
}

function hws_is_brand_highlight_enabled(): bool {
	return (string) get_option( 'hws_brand_highlight_enabled', '1' ) === '1';
}

function hws_print_brand_color_css_variables(): void {
	$background_color = hws_get_brand_highlight_background_color();
	$text_color       = hws_get_brand_highlight_text_color();
	$enabled          = hws_is_brand_highlight_enabled();
	$css              = ':root{--hws-highlight-background-color:' . $background_color . ';--hws-highlight-text-color:' . $text_color . ';}';

	if ( $enabled ) {
		$css .= '::selection{background:' . $background_color . ' !important;color:' . $text_color . ' !important;text-shadow:none !important;}';
		$css .= '::-moz-selection{background:' . $background_color . ' !important;color:' . $text_color . ' !important;text-shadow:none !important;}';
		$css .= 'mark,.hws-highlight,.hws-highlight-text{background-color:var(--hws-highlight-background-color) !important;color:var(--hws-highlight-text-color) !important;}';
	}

	echo "\n" . '<style id="hws-brand-color-vars">' . esc_html( $css ) . '</style>' . "\n";
}
add_action( 'wp_head', __NAMESPACE__ . '\\hws_print_brand_color_css_variables', 20 );
add_action( 'admin_head', __NAMESPACE__ . '\\hws_print_brand_color_css_variables', 20 );

function hws_get_brand_asset_definitions(): array {
	return [
		'logo' => [
			'label' => 'Logo',
			'option' => 'hws_brand_asset_logo_id',
			'legacy_options' => [ 'hws_brand_asset_icon_text_id' ],
			'core' => 'custom_logo',
			'description' => 'Primary WordPress logo. Syncs to the WordPress Custom Logo.',
		],
		'logo_1x1' => [
			'label' => 'Logo 1:1',
			'option' => 'hws_brand_asset_logo_1x1_id',
			'legacy_options' => [ 'hws_brand_asset_icon_1x1_id' ],
			'core' => '',
			'description' => 'Square logo variant for social profiles, avatars, and app tiles.',
		],
		'logo_text' => [
			'label' => 'Logo with text',
			'option' => 'hws_brand_asset_logo_text_id',
			'legacy_options' => [],
			'core' => '',
			'description' => 'Horizontal logo with readable brand text.',
		],
		'logo_dark' => [
			'label' => 'Logo dark background',
			'option' => 'hws_brand_asset_logo_dark_id',
			'legacy_options' => [ 'hws_brand_asset_icon_dark_id' ],
			'core' => '',
			'description' => 'Logo prepared for dark backgrounds.',
		],
		'logo_dark_1x1' => [
			'label' => 'Logo dark background 1:1',
			'option' => 'hws_brand_asset_logo_dark_1x1_id',
			'legacy_options' => [ 'hws_brand_asset_icon_dark_1x1_id' ],
			'core' => '',
			'description' => 'Square dark-background logo variant.',
		],
		'logo_text_dark' => [
			'label' => 'Logo with text dark background',
			'option' => 'hws_brand_asset_logo_text_dark_id',
			'legacy_options' => [ 'hws_brand_asset_icon_text_dark_id' ],
			'core' => '',
			'description' => 'Horizontal text logo prepared for dark backgrounds.',
		],
	];
}

function hws_normalize_brand_asset_key( string $key ): string {
	$key = sanitize_key( $key );
	$aliases = [
		'icon' => 'logo',
		'icon_1x1' => 'logo_1x1',
		'icon_text' => 'logo',
		'icon_dark' => 'logo_dark',
		'icon_dark_1x1' => 'logo_dark_1x1',
		'icon_text_dark' => 'logo_text_dark',
	];

	return $aliases[ $key ] ?? $key;
}

function hws_get_brand_asset_definition( string $key ): ?array {
	$definitions = hws_get_brand_asset_definitions();
	$normalized  = hws_normalize_brand_asset_key( $key );

	if ( ! isset( $definitions[ $normalized ] ) ) {
		return null;
	}

	$definition        = $definitions[ $normalized ];
	$definition['key'] = $normalized;

	return $definition;
}

function hws_get_brand_asset_attachment_id( string $key ): int {
	$definition = hws_get_brand_asset_definition( $key );
	if ( ! $definition ) {
		return 0;
	}

	$attachment_id = (int) get_option( $definition['option'], 0 );
	if ( $attachment_id ) {
		return $attachment_id;
	}

	if ( ( $definition['core'] ?? '' ) === 'custom_logo' ) {
		$core_attachment_id = (int) get_theme_mod( 'custom_logo', 0 );
		if ( $core_attachment_id ) {
			return $core_attachment_id;
		}
	}

	foreach ( (array) ( $definition['legacy_options'] ?? [] ) as $legacy_option ) {
		$legacy_attachment_id = (int) get_option( $legacy_option, 0 );
		if ( $legacy_attachment_id ) {
			return $legacy_attachment_id;
		}
	}

	return 0;
}

function hws_get_brand_asset_image_size( string $size ) {
	$size = trim( $size );
	if ( preg_match( '/^(\d{2,4})x(\d{2,4})$/', $size, $matches ) ) {
		return [ (int) $matches[1], (int) $matches[2] ];
	}

	return $size !== '' ? sanitize_key( $size ) : 'full';
}

function hws_get_brand_asset_url( string $key, string $size = 'full' ): string {
	$attachment_id = hws_get_brand_asset_attachment_id( $key );
	if ( ! $attachment_id ) {
		return '';
	}

	$url = wp_get_attachment_image_url( $attachment_id, hws_get_brand_asset_image_size( $size ) );
	return $url ? (string) $url : '';
}

function hws_normalize_brand_gallery_ids( $ids ): array {
	if ( is_string( $ids ) ) {
		$ids = preg_split( '/[,\s]+/', $ids );
	}

	if ( ! is_array( $ids ) ) {
		return [];
	}

	$normalized = [];
	foreach ( $ids as $item ) {
		if ( is_array( $item ) ) {
			$item = $item['ID'] ?? $item['id'] ?? 0;
		} elseif ( is_object( $item ) ) {
			$item = $item->ID ?? $item->id ?? 0;
		}

		$id = absint( $item );
		if ( $id && ! in_array( $id, $normalized, true ) ) {
			$normalized[] = $id;
		}
	}

	return $normalized;
}

function hws_get_brand_gallery_ids(): array {
	$ids = hws_normalize_brand_gallery_ids( get_option( 'hws_brand_asset_gallery_ids', [] ) );
	if ( ! empty( $ids ) ) {
		return $ids;
	}

	if ( function_exists( '\\get_field' ) ) {
		$acf_value = \get_field( 'brand_assets_gallery', 'option', false );
		$ids       = hws_normalize_brand_gallery_ids( $acf_value );
	}

	return $ids;
}

function hws_update_brand_gallery_ids( array $ids ): void {
	$ids = hws_normalize_brand_gallery_ids( $ids );
	update_option( 'hws_brand_asset_gallery_ids', $ids, false );

	if ( function_exists( '\\update_field' ) ) {
		\update_field( 'field_hws_brand_assets_gallery', $ids, 'option' );
	}
}

add_filter( 'acf/update_value/key=field_hws_brand_assets_gallery', function( $value ) {
	update_option( 'hws_brand_asset_gallery_ids', hws_normalize_brand_gallery_ids( $value ), false );

	return $value;
}, 20 );

function hws_brand_gallery_shortcode( $atts ): string {
	$atts = shortcode_atts(
		[
			'size' => 'medium',
			'output' => 'grid',
			'class' => '',
			'columns' => 4,
			'loading' => 'lazy',
		],
		$atts,
		'brand_asset_gallery'
	);

	$ids = hws_get_brand_gallery_ids();
	if ( empty( $ids ) ) {
		return '';
	}

	$size = hws_get_brand_asset_image_size( (string) $atts['size'] );
	if ( $atts['output'] === 'ids' ) {
		return esc_html( implode( ',', $ids ) );
	}

	if ( $atts['output'] === 'urls' ) {
		$urls = [];
		foreach ( $ids as $id ) {
			$url = wp_get_attachment_image_url( $id, $size );
			if ( $url ) {
				$urls[] = esc_url( $url );
			}
		}

		return implode( "\n", $urls );
	}

	$columns = max( 1, min( 8, (int) $atts['columns'] ) );
	$class   = trim( 'hws-brand-gallery ' . sanitize_html_class( (string) $atts['class'] ) );
	$html    = '<div class="' . esc_attr( $class ) . '" style="display:grid;grid-template-columns:repeat(' . (int) $columns . ',minmax(0,1fr));gap:12px;">';

	foreach ( $ids as $id ) {
		$image = wp_get_attachment_image(
			$id,
			$size,
			false,
			[
				'loading' => sanitize_key( (string) $atts['loading'] ),
				'alt'     => get_post_meta( $id, '_wp_attachment_image_alt', true ),
			]
		);

		if ( $image ) {
			$html .= '<figure style="margin:0;">' . $image . '</figure>';
		}
	}

	$html .= '</div>';

	return $html;
}

function hws_brand_asset_shortcode( $atts ): string {
	$atts = shortcode_atts(
		[
			'key' => 'logo',
			'type' => '',
			'size' => 'full',
			'output' => 'img',
			'class' => '',
			'alt' => '',
			'loading' => 'lazy',
		],
		$atts,
		'hws_brand_asset'
	);

	$key = hws_normalize_brand_asset_key( (string) ( $atts['type'] !== '' ? $atts['type'] : $atts['key'] ) );
	$attachment_id = hws_get_brand_asset_attachment_id( $key );
	if ( ! $attachment_id ) {
		return '';
	}

	$size = hws_get_brand_asset_image_size( (string) $atts['size'] );
	if ( $atts['output'] === 'url' ) {
		$url = wp_get_attachment_image_url( $attachment_id, $size );
		return $url ? esc_url( $url ) : '';
	}

	$definition = hws_get_brand_asset_definition( $key );
	$attributes = [
		'class' => sanitize_html_class( (string) $atts['class'] ),
		'alt' => $atts['alt'] !== '' ? sanitize_text_field( (string) $atts['alt'] ) : ( $definition['label'] ?? 'Site logo' ),
		'loading' => sanitize_key( (string) $atts['loading'] ),
	];

	if ( $attributes['class'] === '' ) {
		unset( $attributes['class'] );
	}

	return wp_get_attachment_image( $attachment_id, $size, false, $attributes );
}


/**
 * Hook into ACF’s user‐select field to render extra info.
 */
function enable_website_settings_functionality() {
	add_action(
		'acf/render_field/name=user',
		__NAMESPACE__ . '\\hf_render_user_info_once',
		10,
		1
	);
}


/**
 * Color code Founder (green) and Company/Website (blue) sections on Website Settings page
 */
function hws_website_settings_color_coding_css() {
	if ( ! hws_is_website_settings_admin_page() ) {
		return;
	}
	?>
	<style>
	/* FOUNDER SECTION - GREEN */
	.acf-field[data-key="field_684217273a45b"] {
		background: #edfaef !important;
		border: 2px solid #00a32a !important;
		border-radius: 8px !important;
		padding: 15px !important;
		margin-bottom: 20px !important;
	}
	.acf-field[data-key="field_684217273a45b"] > .acf-label > label {
		color: #00a32a !important;
		font-size: 16px !important;
		font-weight: 600 !important;
	}
	.acf-field[data-key="field_684217273a45b"] > .acf-label > label::before {
		content: "👤 ";
	}
	.acf-field[data-key="field_684217273a45b"] .acf-fields > .acf-field {
		background: #fff !important;
		border: 1px solid #c3e6c7 !important;
		border-radius: 4px !important;
		padding: 12px !important;
		margin-bottom: 10px !important;
	}
	/* COMPANY/WEBSITE SECTION - BLUE */
	.acf-field[data-key="field_68420a023f1c8"] {
		background: #f0f6fc !important;
		border: 2px solid #2271b1 !important;
		border-radius: 8px !important;
		padding: 15px !important;
		margin-bottom: 20px !important;
	}
	.acf-field[data-key="field_68420a023f1c8"] > .acf-label > label {
		color: #2271b1 !important;
		font-size: 16px !important;
		font-weight: 600 !important;
	}
	.acf-field[data-key="field_68420a023f1c8"] > .acf-label > label::before {
		content: "🏢 ";
	}
	.acf-field[data-key="field_68420a023f1c8"] .acf-fields > .acf-field {
		background: #fff !important;
		border: 1px solid #c5d9ed !important;
		border-radius: 4px !important;
		padding: 12px !important;
		margin-bottom: 10px !important;
	}
	</style>
	<?php
}
add_action( 'admin_head', __NAMESPACE__ . '\\hws_website_settings_color_coding_css' );
/**
 * Render avatar, basic info, buttons, *and* ACF “urls” sub-fields as clickable links.
 *
 * @param array $field ACF field settings/values.
 */
function hf_render_user_info_once( $field ) {

	// HARD ENFORCE: only run on Website Settings page
	if ( ! hws_is_website_settings_admin_page() ) {
		return;
	}

	// Only run on the Select2 pass (type "user")
	if ( $field['type'] !== 'user' ) {
		return;
	}

	$user_id = intval( $field['value'] );
	if ( ! $user_id ) {
		return;
	}

	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return;
	}

	$edit_url = get_edit_user_link( $user_id );
	$view_url = get_author_posts_url( $user_id );

	// Basic user info block
	echo '<div class="hf-user-info-wrapper" style="
            background: #fbfbfb;
            border: 1px solid #ddd;
            padding: 12px;
            margin-top: 12px;
            border-radius: 4px;
        ">
        <div class="hf-user-avatar" style="float: left; margin-right: 12px;">'
			. get_avatar( $user_id, 64 ) .
		'</div>

        <div class="hf-user-basic-info" style="overflow: hidden;">
            <p style="margin:0 0 4px;"><strong>Username:</strong> '
				. esc_html( $user->user_login ) . '</p>
            <p style="margin:0 0 4px;"><strong>Name:</strong> '
				. esc_html( $user->display_name ) . '</p>
            <p style="margin:0 0 4px;"><strong>Email:</strong>
                <a href="mailto:' . esc_attr( $user->user_email ) . '">'
					. esc_html( $user->user_email ) . '</a>
            </p>
            <p style="margin:0 0 4px;"><strong>Role'
				. ( count( $user->roles ) > 1 ? 's' : '' ) . ':</strong> '
				. esc_html( implode( ', ', $user->roles ) ) . '</p>
            <p style="margin:0 0 4px;"><strong>Registered:</strong> '
				. esc_html( date_i18n( get_option('date_format'), strtotime( $user->user_registered ) ) )
			. '</p>
        </div>

        <div style="clear: both; margin-top: 8px;"></div>
        <div class="hf-user-buttons" style="margin-top: 8px;">';
	if ( $edit_url ) {
		echo '<a href="' . esc_url( $edit_url ) . '" target="_blank" class="button" style="margin-right:6px;">
                        Edit User
                      </a>';
	}
	if ( $view_url ) {
		echo '<a href="' . esc_url( $view_url ) . '" target="_blank" class="button">
                        View Profile
                      </a>';
	}
	echo '</div>';

	// --- New: display ACF "urls" group subfields as clickable links ---
	$urls = get_field( 'urls', 'user_' . $user_id ) ?: [];

	// map sub-field names to human labels
	$url_fields = [
		'facebook'   => 'Facebook',
		'instagram'  => 'Instagram',
		'linkedin'   => 'LinkedIn',
		'youtube'    => 'YouTube',
		'tiktok'     => 'TikTok',
		'f6s'        => 'F6S',
		'imdb'       => 'iMDb',
		'muckrack'   => 'MuckRack',
		'wikipedia'  => 'Wikipedia',
		'x'          => 'X',
		'soundcloud' => 'SoundCloud',
		'the_org'    => 'The Org',
		'whatsapp'   => 'WhatsApp',
		'telegram'   => 'Telegram',
		'signal'     => 'Signal',
		'amazon'     => 'Amazon',
		'github'     => 'GitHub',
		'audible'    => 'Audible',
		'threads'    => 'Threads',
		'crunchbase' => 'CrunchBase',
		'website'    => 'Website',
	];

	// Only show if there's at least one URL
	$has_url = false;
	foreach ( $url_fields as $key => $label ) {
		if ( ! empty( $urls[ $key ] ) ) {
			$has_url = true;
			break;
		}
	}

	if ( $has_url ) {
		echo '<div class="hf-user-urls" style="margin-top:12px;">';
		foreach ( $url_fields as $key => $label ) {
			if ( ! empty( $urls[ $key ] ) ) {
				$url = $urls[ $key ];
				echo '<p style="margin:0 0 4px;"><strong>'
					. esc_html( $label ) . ':</strong> '
					. '<a href="' . esc_url( $url )
					. '" target="_blank" class="hf-user-url urls_'
					. esc_attr( $key ) . '">'
					. esc_html( $url ) . '</a>'
					. '</p>';
			}
		}
		echo '</div>';
	}

	// Close main wrapper
	echo '</div>';
}





/* shortcode declarations */

/**
 * Shortcode: [website_url social="facebook"]
 * Returns the requested social-URL from the “Website” user picked in Website Settings.
 */
function website_url_shortcode( $atts ) {

	$atts = shortcode_atts( [
		'social' => '',
	], $atts, 'website_url' );

	$key = sanitize_key( $atts['social'] );
	if ( ! $key ) {
		return '';
	}

	// load Website Settings group (options page)
	$website = get_field( 'website', 'option' );
	if ( ! ( is_array( $website ) && ! empty( $website['user']['ID'] ) ) ) {
		return '';
	}

	// pull that user’s “urls” repeater/array
	$user_id   = $website['user']['ID'];
	$user_urls = get_field( 'urls', 'user_' . $user_id );

	if ( is_array( $user_urls ) && ! empty( $user_urls[ $key ] ) ) {
		return esc_url( $user_urls[ $key ] );
	}

	return '';
}



/**
 * Shortcode: [website_content field="FIELD_NAME"]
 * Returns the raw ACF option field value from the Website Settings page.
 */
function website_content_shortcode( $atts ) {
	$atts = shortcode_atts( [
		'field' => '',
	],
	$atts,
	'website_content' );

	if ( empty( $atts['field'] ) ) {
		return '';
	}

	return get_field( $atts['field'], 'option' ) ?: '';
}















/**
 * [company id="..."] — fetch data from the “company” User (ACF Options: website -> company).
 *
 * Supported:
 *   - title | biography | website | url_{platform}
 *   - any direct ACF user field, e.g. [company id="biography"] (already supported)
 *   - any nested group field as group_subfield, e.g. [company id="additional_public_email"]
 *
 * Notes:
 *   - Biography returns HTML as-is; URLs are escaped; other text is escaped.
 */
function company_shortcode( $atts ): string {
	$atts = shortcode_atts( [ 'id' => 'title' ], $atts, 'company' );
	$requested = strtolower( trim( (string) $atts['id'] ) );

	if ( ! function_exists( 'get_field' ) ) {
		return '';
	}

	// Resolve company user from options
	$website = get_field( 'website', 'option' );
	if ( ! ( is_array( $website ) && ! empty( $website['company']['ID'] ) ) ) {
		return '';
	}
	$user_id  = (int) $website['company']['ID'];
	$userdata = get_userdata( $user_id );
	$user_key = 'user_' . $user_id;

	// Common data
	$user_urls = get_field( 'urls', $user_key );       // array of platforms
	$user_bio  = (string) get_field( 'biography', $user_key );
	$user_site = (string) get_field( 'website', $user_key );

	// Core WordPress user fields
	switch ( $requested ) {
		case 'title':
		case 'name':
			return esc_html( $userdata->display_name ?: '' );

		case 'first_name':
			return esc_html( $userdata->first_name ?: '' );

		case 'last_name':
			return esc_html( $userdata->last_name ?: '' );

		case 'email':
			return esc_html( $userdata->user_email ?: '' );

		case 'summary':
		case 'bio':
			// WordPress core description field (not ACF)
			return $userdata->description ?: '';

		case 'avatar':
			return esc_url( get_avatar_url( $user_id, [ 'size' => 200 ] ) ?: '' );

		case 'biography':
			// ACF biography field
			if ( $user_bio === '' ) {
				$core_bio = (string) $userdata->description;
				return $core_bio !== '' ? $core_bio : '';
			}
			return $user_bio; // allow HTML

		case 'website':
			if ( $user_site !== '' ) {
				return esc_url( $user_site );
			}
			if ( is_array( $user_urls ) && ! empty( $user_urls['website'] ) ) {
				return esc_url( (string) $user_urls['website'] );
			}
			$core_url = $userdata ? (string) $userdata->user_url : '';
			return $core_url !== '' ? esc_url( $core_url ) : '';
	}

	// Platform URLs: id="url_*"
	if ( ( function_exists('str_starts_with') && str_starts_with( $requested, 'url_' ) )
		 || substr( $requested, 0, 4 ) === 'url_' ) {
		$platform = sanitize_key( substr( $requested, 4 ) );
		if ( $platform !== '' && is_array( $user_urls ) && ! empty( $user_urls[ $platform ] ) ) {
			return esc_url( (string) $user_urls[ $platform ] );
		}
		return '';
	}

	// Education repeater handling
	if ( $requested === 'education' ) {
		return hws_render_education_shortcode( $atts, $user_key );
	}
	
	// SameAs handling
	if ( $requested === 'sameas' ) {
		return hws_render_sameas_shortcode( $atts, $user_key );
	}

	// Entity type
	if ( $requested === 'entity_type' ) {
		$val = get_field( 'entity_type', $user_key );
		return esc_html( $val ?: 'person' );
	}

	// Inception date (organization)
	if ( $requested === 'inception_date' ) {
		$val = get_field( 'inception_date', $user_key );
		return esc_html( $val ?: '' );
	}

	// Headquarters location
	if ( $requested === 'headquarters_location' ) {
		$hq = get_field( 'headquarters', $user_key );
		return is_array( $hq ) && ! empty( $hq['location'] ) ? esc_html( $hq['location'] ) : '';
	}

	// Headquarters wiki URL
	if ( $requested === 'headquarters_wiki' ) {
		$hq = get_field( 'headquarters', $user_key );
		return is_array( $hq ) && ! empty( $hq['wiki_url'] ) ? esc_url( $hq['wiki_url'] ) : '';
	}

	// ---- Generic ACF resolver for user meta ----
	// 1) Try direct field on user (e.g., id="some_field")
	$direct = get_field( $requested, $user_key );
	if ( is_string( $direct ) && $direct !== '' ) {
		// If it looks like a URL, escape as URL; if it's biography-like, allow HTML; else escape text.
		if ( filter_var( $direct, FILTER_VALIDATE_URL ) ) {
			return esc_url( $direct );
		}
		if ( in_array( $requested, ['biography','bio'], true ) ) {
			return $direct; // allow HTML
		}
		return esc_html( $direct );
	}

	// 2) Try group_subfield (e.g., "additional_public_email")
	//    We treat the first token as the group, the rest joined by '_' as the subkey.
	if ( strpos( $requested, '_' ) !== false ) {
		$parts   = explode( '_', $requested );
		$group   = array_shift( $parts );        // e.g. 'additional'
		$subkey  = implode( '_', $parts );       // e.g. 'public_email'

		if ( $group && $subkey ) {
			$group_val = get_field( $group, $user_key ); // should be array
			if ( is_array( $group_val ) && isset( $group_val[ $subkey ] ) ) {
				$val = $group_val[ $subkey ];
				if ( is_string( $val ) && $val !== '' ) {
					if ( filter_var( $val, FILTER_VALIDATE_URL ) ) {
						return esc_url( $val );
					}
					// Very common cases: email/phone plain text
					if ( is_email( $val ) ) {
						return esc_html( $val );
					}
					return esc_html( $val );
				}
			}
		}
	}

	return '';
}










/**
 * Admin UI: Inject a “Company User” card with shortcode examples into Theme Options.
 *
 * Hook: admin_enqueue_scripts
 *
 * What this does:
 *  1) Ensures we’re on the Theme Options admin screen (toplevel_page_theme-options).
 *  2) Loads jQuery for admin.
 *  3) Reads ACF Options group "website" and the sub-field "company" (ACF user field, return_format=array).
 *  4) Reads that user’s ACF field "urls" from the user meta scope ("user_{ID}").
 *  5) Localizes these data to JS, then injects a compact info card directly under the
 *     ACF field with data-name="company". The card shows:
 *       • Avatar, name, Edit/View links
 *       • “Developer Shortcodes” box with:
 *           [company id="title"], [company id="biography"], [company id="website"], [company id="url_facebook"]
 *       • A list of the user’s URLs; each link has its shortcode badge shown above it:
 *           [company id="url_{key}"]
 *
 * Requirements:
 *  - Advanced Custom Fields: get_field() available.
 *  - Options page group “website” with sub-field “company” (ACF user, return_format=array).
 *  - Optional ACF user field “urls” on that user (array of platform => url).
 *
 * Notes:
 *  - Admin-only; no effect on front end.
 *  - Defensive early returns if any required data are missing.
 *
 * @param string $hook Admin page hook suffix (unused here).
 * @return void
 */
add_action('admin_enqueue_scripts', function( $hook ) {

	// NOTE: Hard-enforced to only run on /wp-admin/admin.php?page=website-settings
	// This preserves original documentation while preventing injection on CPT edit screens.

	// 1) Only run on Theme Options screen. (Uncomment return to hard-enforce.)
	$screen = get_current_screen();
	if ( ! hws_is_website_settings_admin_page() ) {
		// return;
		return;
	}

	// 2) jQuery
	wp_enqueue_script('jquery');

	// 3) Load Options → website → company (ACF user field, return_format=array)
	$website = function_exists('get_field') ? get_field('website', 'option') : null;
	if ( ! is_array($website) || empty($website['company']['ID']) ) {
		return;
	}

	$user_id = absint($website['company']['ID']);

	// 4) User ACF: urls array in user meta scope
	$urls = get_field('urls', 'user_' . $user_id);
	if ( ! is_array($urls) ) {
		$urls = []; // still render the card without URL rows
	}

	// 5) Build minimal user data for UI
	$user_obj = get_userdata($user_id);
	if ( ! $user_obj ) {
		return;
	}

	$data = [
		'user' => [
			'id'     => $user_id,
			'avatar' => get_avatar_url($user_id, ['size' => 80]),
			'name'   => $user_obj->display_name,
			'edit'   => get_edit_user_link($user_id),
			'view'   => get_author_posts_url($user_id),
		],
		'urls' => $urls,
	];

	// 6) Register a no-op handle, localize data, and inject inline JS
	wp_register_script('smp-company-card', false, ['jquery'], null, true);
	wp_enqueue_script('smp-company-card');
	wp_localize_script('smp-company-card', 'SMPCompanyData', $data);

	wp_add_inline_script('smp-company-card', <<<'JS'
/**
 * Admin card injector for Company User + developer shortcodes.
 * Expects global SMPCompanyData = { user:{...}, urls:{...} }
 */
jQuery(function($){
    var d = window.SMPCompanyData || {};
    if (!d.user || !d.user.id) return;

    // Prefer to insert directly under the "company" field.
    var target = $('.acf-field[data-name="company"]').first();

    // Fallback: if "company" field wrapper isn't found, try the parent group "website".
    if (!target.length) {
        target = $('.acf-field-group[data-name="website"]').first();
    }
    if (!target.length) return;

    // Where to insert: right after the field/group label if present, otherwise after the wrapper.
    var insertAfter = target.find('> .acf-label').length ? target.find('> .acf-label') : target;

    // Utility: monospace badge for shortcodes
    var codeBadge = function(text){
        return $('<span>').text(text).css({
            display:'inline-block',
            fontFamily:'ui-monospace,Menlo,Monaco,monospace',
            fontSize:'12px',
            background:'#f3f4f6',
            border:'1px solid #e5e7eb',
            borderRadius:'4px',
            padding:'2px 6px',
            lineHeight:1.8
        });
    };

    // Utility: shortcode row
    var shortcodeRow = function(label, shortcode) {
        return $('<div>').css({ display:'flex', alignItems:'center', margin:'4px 0', gap:'8px' }).append(
            $('<span>').css({ minWidth:'100px', fontWeight:500, fontSize:'13px' }).text(label),
            codeBadge(shortcode)
        );
    };

    // Card container - BLUE theme for Company
    var card = $('<div class="smp-company-card">').css({
        padding:'12px',
        border:'2px solid #2271b1',
        marginTop:'10px',
        marginBottom:'12px',
        borderRadius:'6px',
        background:'#f0f6fc'
    });
    
    // Card header label
    $('<div>').css({
        background:'#2271b1',
        color:'#fff',
        padding:'6px 12px',
        margin:'-12px -12px 12px -12px',
        borderRadius:'4px 4px 0 0',
        fontWeight:600,
        fontSize:'14px'
    }).text('🏢 Company User').appendTo(card);

    // Header
    $('<div>').css({ display:'flex', alignItems:'center' }).append(
        $('<img>', { src:d.user.avatar, width:80, height:80 })
            .css({ borderRadius:'50%', marginRight:'12px', border:'1px solid #e5e7eb' }),
        $('<div>').append(
            $('<strong>').text(d.user.name),
            $('<div>').css({ marginTop:'8px' }).append(
                $('<a>', { href:d.user.edit, target:'_blank', class:'button', style:'margin-right:6px;' }).text('Edit Profile'),
                $('<a>', { href:d.user.view, target:'_blank', class:'button' }).text('View Profile')
            )
        )
    ).appendTo(card);

    // Developer Shortcodes box (company-specific) - EXPANDED
    var dev = $('<div class="smp-dev-shortcodes">').css({
        marginTop:'12px',
        padding:'12px',
        background:'#f9fafb',
        border:'1px dashed #cbd5e1',
        borderRadius:'6px'
    });

    // Section: WordPress User Fields
    dev.append(
        $('<div>').css({ fontWeight:600, marginBottom:'8px', fontSize:'14px', borderBottom:'1px solid #e5e7eb', paddingBottom:'6px' }).text('Developer Shortcodes')
    );
    
    // Core WordPress fields
    dev.append(
        $('<div>').css({ marginBottom:'12px' }).append(
            $('<div>').css({ fontWeight:600, fontSize:'12px', color:'#6b7280', marginBottom:'6px', textTransform:'uppercase' }).text('WordPress User Fields'),
            shortcodeRow('Display Name', '[company id="title"]'),
            shortcodeRow('Name (alias)', '[company id="name"]'),
            shortcodeRow('First Name', '[company id="first_name"]'),
            shortcodeRow('Last Name', '[company id="last_name"]'),
            shortcodeRow('Email', '[company id="email"]'),
            shortcodeRow('Summary/Bio', '[company id="summary"]'),
            shortcodeRow('Avatar URL', '[company id="avatar"]'),
            shortcodeRow('Website', '[company id="website"]')
        )
    );

    // ACF fields
    dev.append(
        $('<div>').css({ marginBottom:'8px' }).append(
            $('<div>').css({ fontWeight:600, fontSize:'12px', color:'#6b7280', marginBottom:'6px', textTransform:'uppercase' }).text('ACF Fields'),
            shortcodeRow('Biography', '[company id="biography"]'),
            shortcodeRow('Public Email', '[company id="additional_public_email"]'),
            shortcodeRow('Public Phone', '[company id="additional_public_phone"]'),
            shortcodeRow('Title/Position', '[company id="additional_title"]')
        )
    );

    // URL fields hint
    dev.append(
        $('<div>').css({ fontSize:'12px', color:'#6b7280', marginTop:'8px', fontStyle:'italic' }).html('URLs: <code>[company id="url_{platform}"]</code> — facebook, linkedin, instagram, youtube, tiktok, x, etc.')
    );

    card.append(dev);

    // URLs list with shortcode badge above each link
    var urlBox = $('<div class="smp-company-urls">').css({ marginTop:'12px' })
                   .append('<p><strong>Company User URLs:</strong></p>');

    $.each(d.urls, function(key, link){
        link = $.trim(link || '');
        if (!link) return;

        var labelText = key.replace(/_/g,' ').replace(/\b\w/g, function(m){ return m.toUpperCase(); });

        urlBox.append(
            $('<div>').css({ margin:'10px 0 12px' }).append(
                $('<div>').css({ margin:'0 0 4px 0' }).append(
                    codeBadge('[company id="url_' + key + '"]')
                ),
                $('<div>').append(
                    $('<strong>').text(labelText + ': '),
                    $('<a>', { href:link, target:'_blank' }).text(link)
                )
            )
        );
    });

    card.append(urlBox);

    // Inject into the form
    insertAfter.after(card);
});
JS
	);
});































/**
 * Admin UI: Inject a “Founder User” card with shortcode examples into Theme Options.
 *
 * Hook: admin_enqueue_scripts
 *
 * What this does:
 *  1) Runs on the Theme Options screen (toplevel_page_theme-options).
 *  2) Loads jQuery for admin.
 *  3) Reads ACF Options group "founder" and its sub-field "user" (ACF user field, return_format=array).
 *  4) Loads that user’s ACF field "urls" from user meta scope ("user_{ID}").
 *  5) Localizes this data to JS and injects a compact card directly under the
 *     ACF group with data-name="founder". The card shows:
 *       • Avatar, name, Edit/View links
 *       • “Developer Shortcodes” box with:
 *           [founder id="title"], [founder id="biography"], [founder id="website"], [founder id="url_facebook"]
 *       • A list of the user’s URLs; each link has its shortcode badge above it:
 *           [founder id="url_{key}"]
 *
 * Requirements:
 *  - ACF available (get_field()).
 *  - Options page contains group "founder" with sub-field "user" (return_format=array).
 *  - Optional ACF user field "urls" on that user (array of platform => url).
 *
 * Notes:
 *  - Admin-only; no front-end impact.
 *  - Early returns if required data are missing.
 *
 * @param string $hook Admin page hook suffix (unused here).
 * @return void
 */
add_action('admin_enqueue_scripts', function( $hook ) {

	// NOTE: Hard-enforced to only run on /wp-admin/admin.php?page=website-settings
	// This preserves original documentation while preventing injection on CPT edit screens.

	// 1) Limit to Theme Options screen (uncomment return to strictly enforce)
	$screen = get_current_screen();
	if ( ! hws_is_website_settings_admin_page() ) {
		// return;
		return;
	}

	// 2) jQuery
	wp_enqueue_script('jquery');

	// 3) Load Options → founder → founder_user (new field name) or user (legacy)
	$founder = function_exists('get_field') ? get_field('founder', 'option') : null;
	
	// Try new field name first, fall back to legacy
	$user_id = 0;
	if ( is_array($founder) && ! empty($founder['founder_user']['ID']) ) {
		$user_id = absint($founder['founder_user']['ID']);
	} elseif ( is_array($founder) && ! empty($founder['user']['ID']) ) {
		$user_id = absint($founder['user']['ID']);
	}
	
	if ( ! $user_id ) {
		return;
	}

	// 4) User ACF: urls array (from user meta scope)
	$urls = get_field('urls', 'user_' . $user_id);
	if ( ! is_array($urls) ) {
		$urls = []; // still render the card without URL rows
	}

	// 5) Minimal user data
	$user_obj = get_userdata($user_id);
	if ( ! $user_obj ) {
		return;
	}

	$data = [
		'user' => [
			'id'     => $user_id,
			'avatar' => get_avatar_url($user_id, ['size' => 80]),
			'name'   => $user_obj->display_name,
			'edit'   => get_edit_user_link($user_id),
			'view'   => get_author_posts_url($user_id),
		],
		'urls' => $urls,
	];

	// 6) Register empty handle, localize data, inject inline JS
	wp_register_script('smp-founder-card', false, ['jquery'], null, true);
	wp_enqueue_script('smp-founder-card');
	wp_localize_script('smp-founder-card', 'SMPFounderData', $data);

	wp_add_inline_script('smp-founder-card', <<<'JS'
/**
 * Admin card injector for Founder User + developer shortcodes.
 * Expects global SMPFounderData = { user:{...}, urls:{...} }
 */
jQuery(function($){
    var d = window.SMPFounderData || {};
    if (!d.user || !d.user.id) return;

    // Target the ACF group wrapper for "founder"
    var group = $('.acf-field-group[data-name="founder"]').first();
    if (!group.length) return;

    // Insert after the group's label if found; else append to group
    var insertAfter = group.find('> .acf-label').length ? group.find('> .acf-label') : group;

    // Utility: monospace badge for shortcodes
    var codeBadge = function(text){
        return $('<span>').text(text).css({
            display:'inline-block',
            fontFamily:'ui-monospace,Menlo,Monaco,monospace',
            fontSize:'12px',
            background:'#f3f4f6',
            border:'1px solid #e5e7eb',
            borderRadius:'4px',
            padding:'2px 6px',
            lineHeight:1.8
        });
    };

    // Utility: shortcode row
    var shortcodeRow = function(label, shortcode) {
        return $('<div>').css({ display:'flex', alignItems:'center', margin:'4px 0', gap:'8px' }).append(
            $('<span>').css({ minWidth:'100px', fontWeight:500, fontSize:'13px' }).text(label),
            codeBadge(shortcode)
        );
    };

    // Card container - GREEN theme for Founder
    var card = $('<div class="smp-founder-card">').css({
        padding:'12px',
        border:'2px solid #00a32a',
        marginTop:'10px',
        marginBottom:'12px',
        borderRadius:'6px',
        background:'#edfaef'
    });
    
    // Card header label
    $('<div>').css({
        background:'#00a32a',
        color:'#fff',
        padding:'6px 12px',
        margin:'-12px -12px 12px -12px',
        borderRadius:'4px 4px 0 0',
        fontWeight:600,
        fontSize:'14px'
    }).text('👤 Founder User').appendTo(card);

    // Header: avatar + name + actions
    $('<div>').css({ display:'flex', alignItems:'center' }).append(
        $('<img>', { src:d.user.avatar, width:80, height:80 })
            .css({ borderRadius:'50%', marginRight:'12px', border:'1px solid #e5e7eb' }),
        $('<div>').append(
            $('<strong>').text(d.user.name),
            $('<div>').css({ marginTop:'8px' }).append(
                $('<a>', { href:d.user.edit, target:'_blank', class:'button', style:'margin-right:6px;' }).text('Edit Profile'),
                $('<a>', { href:d.user.view, target:'_blank', class:'button' }).text('View Profile')
            )
        )
    ).appendTo(card);

    // Developer Shortcodes box (founder-specific) - EXPANDED
    var dev = $('<div class="smp-dev-shortcodes">').css({
        marginTop:'12px',
        padding:'12px',
        background:'#f9fafb',
        border:'1px dashed #cbd5e1',
        borderRadius:'6px'
    });

    // Section: WordPress User Fields
    dev.append(
        $('<div>').css({ fontWeight:600, marginBottom:'8px', fontSize:'14px', borderBottom:'1px solid #e5e7eb', paddingBottom:'6px' }).text('Developer Shortcodes')
    );
    
    // Core WordPress fields
    dev.append(
        $('<div>').css({ marginBottom:'12px' }).append(
            $('<div>').css({ fontWeight:600, fontSize:'12px', color:'#6b7280', marginBottom:'6px', textTransform:'uppercase' }).text('WordPress User Fields'),
            shortcodeRow('Display Name', '[founder id="title"]'),
            shortcodeRow('Name (alias)', '[founder id="name"]'),
            shortcodeRow('First Name', '[founder id="first_name"]'),
            shortcodeRow('Last Name', '[founder id="last_name"]'),
            shortcodeRow('Email', '[founder id="email"]'),
            shortcodeRow('Summary/Bio', '[founder id="summary"]'),
            shortcodeRow('Avatar URL', '[founder id="avatar"]'),
            shortcodeRow('Website', '[founder id="website"]')
        )
    );

    // ACF fields
    dev.append(
        $('<div>').css({ marginBottom:'8px' }).append(
            $('<div>').css({ fontWeight:600, fontSize:'12px', color:'#6b7280', marginBottom:'6px', textTransform:'uppercase' }).text('ACF Fields'),
            shortcodeRow('Biography', '[founder id="biography"]'),
            shortcodeRow('Public Email', '[founder id="additional_public_email"]'),
            shortcodeRow('Public Phone', '[founder id="additional_public_phone"]'),
            shortcodeRow('Title/Position', '[founder id="additional_title"]')
        )
    );

    // URL fields hint
    dev.append(
        $('<div>').css({ fontSize:'12px', color:'#6b7280', marginTop:'8px', fontStyle:'italic' }).html('URLs: <code>[founder id="url_{platform}"]</code> — facebook, linkedin, instagram, youtube, tiktok, x, etc.')
    );

    card.append(dev);

    // URLs list with shortcode badge above each link
    var urlBox = $('<div class="smp-founder-urls">').css({ marginTop:'12px' })
                   .append('<p><strong>Founder User URLs:</strong></p>');

    $.each(d.urls, function(key, link){
        link = $.trim(link || '');
        if (!link) return;

        var labelText = key.replace(/_/g,' ').replace(/\b\w/g, function(m){ return m.toUpperCase(); });

        urlBox.append(
            $('<div>').css({ margin:'10px 0 12px' }).append(
                $('<div>').css({ margin:'0 0 4px 0' }).append(
                    codeBadge('[founder id="url_' + key + '"]')
                ),
                $('<div>').append(
                    $('<strong>').text(labelText + ': '),
                    $('<a>', { href:link, target:'_blank' }).text(link)
                )
            )
        );
    });

    card.append(urlBox);

    // Inject into the ACF group UI
    insertAfter.after(card);
});
JS
	);
});
































/**
 * Resolve the "founder" user_id from Options, with sane fallbacks.
 * Order:
 *   1) option → founder → founder_user (new field name)
 *   2) option → founder → user (legacy field name)
 *   3) option → website → company (pragmatic fallback)
 */
function hws_resolve_founder_user_id(): int {
	if ( ! function_exists( 'get_field' ) ) {
		return 0;
	}

	// 1) Primary: option → founder → founder_user (new field name)
	$founder = get_field( 'founder', 'option' );
	if ( is_array( $founder ) && ! empty( $founder['founder_user'] ) ) {
		$uf = $founder['founder_user'];
		if ( is_array( $uf ) && isset( $uf['ID'] ) ) return (int) $uf['ID'];
		if ( is_object( $uf ) && isset( $uf->ID ) )  return (int) $uf->ID;
		return (int) $uf;
	}

	// 2) Legacy: option → founder → user
	if ( is_array( $founder ) && ! empty( $founder['user'] ) ) {
		$uf = $founder['user'];
		if ( is_array( $uf ) && isset( $uf['ID'] ) ) return (int) $uf['ID'];
		if ( is_object( $uf ) && isset( $uf->ID ) )  return (int) $uf->ID;
		return (int) $uf;
	}

	// 3) Fallback: option → website → company
	$website = get_field( 'website', 'option' );
	if ( is_array( $website ) && ! empty( $website['company'] ) ) {
		$uf = $website['company'];
		if ( is_array( $uf ) && isset( $uf['ID'] ) ) return (int) $uf['ID'];
		if ( is_object( $uf ) && isset( $uf->ID ) )  return (int) $uf->ID;
		return (int) $uf;
	}

	return 0;
}

/* ---------------------------------------
 * founder shortcode (clean: no debug)
 * --------------------------------------*/

/**
 * [founder id="title|name|first_name|last_name|email|summary|biography|website|avatar|url_x|<acf_field>|group_subfield"]
 * 
 * Core WordPress fields:
 *   - title / name      → display_name
 *   - first_name        → first_name
 *   - last_name         → last_name
 *   - email             → user_email
 *   - summary / bio     → description (WP bio field)
 *   - avatar            → avatar URL
 *   - website           → user_url or ACF website field
 * 
 * ACF fields:
 *   - biography         → ACF biography (with option-level override)
 *   - url_{platform}    → urls group subfield (facebook, linkedin, etc.)
 *   - {any_acf_field}   → Direct ACF field lookup
 *   - {group}_{subfield}→ Nested ACF group field
 */
function founder_shortcode( $atts ): string {
	$atts = shortcode_atts( [ 'id' => 'title' ], $atts, 'founder' );
	$requested = strtolower( trim( (string) $atts['id'] ) );

	if ( ! function_exists( 'get_field' ) ) {
		return '';
	}

	$user_id = hws_resolve_founder_user_id();
	if ( $user_id <= 0 ) return '';

	$userdata = get_userdata( $user_id );
	if ( ! $userdata ) return '';

	$user_key  = 'user_' . $user_id;

	// Option-level founder biography (if populated)
	$founder_group = get_field( 'founder', 'option' );
	$founder_group_bio = ( is_array( $founder_group ) && ! empty( $founder_group['biography'] ) && is_string( $founder_group['biography'] ) )
		? $founder_group['biography']
		: '';

	$user_urls = get_field( 'urls',       $user_key );
	$user_bio  = (string) get_field( 'biography', $user_key );
	$user_site = (string) get_field( 'website',   $user_key );

	// Core WordPress user fields
	switch ( $requested ) {
		case 'title':
		case 'name':
			return esc_html( $userdata->display_name ?: '' );

		case 'first_name':
			return esc_html( $userdata->first_name ?: '' );

		case 'last_name':
			return esc_html( $userdata->last_name ?: '' );

		case 'email':
			return esc_html( $userdata->user_email ?: '' );

		case 'summary':
		case 'bio':
			// WordPress core description field (not ACF)
			return esc_html( $userdata->description ?: '' );

		case 'avatar':
			return esc_url( get_avatar_url( $user_id, [ 'size' => 200 ] ) ?: '' );

		case 'biography':
			// ACF biography with option-level override
			if ( $founder_group_bio !== '' ) return $founder_group_bio; // HTML allowed
			if ( $user_bio !== '' )          return $user_bio;          // HTML allowed
			$core_bio = (string) $userdata->description;
			return $core_bio !== '' ? $core_bio : '';

		case 'website':
			if ( $user_site !== '' ) return esc_url( $user_site );
			if ( is_array( $user_urls ) && ! empty( $user_urls['website'] ) ) {
				return esc_url( (string) $user_urls['website'] );
			}
			$core_url = (string) $userdata->user_url;
			return $core_url !== '' ? esc_url( $core_url ) : '';
	}

	// url_* platforms
	if ( ( function_exists( 'str_starts_with' ) && str_starts_with( $requested, 'url_' ) )
	  || substr( $requested, 0, 4 ) === 'url_' ) {
		$platform = sanitize_key( substr( $requested, 4 ) );
		if ( $platform && is_array( $user_urls ) && ! empty( $user_urls[ $platform ] ) ) {
			return esc_url( (string) $user_urls[ $platform ] );
		}
		return '';
	}

	// Education repeater handling
	if ( $requested === 'education' ) {
		return hws_render_education_shortcode( $atts, $user_key );
	}
	
	// SameAs handling
	if ( $requested === 'sameas' ) {
		return hws_render_sameas_shortcode( $atts, $user_key );
	}

	// Entity type
	if ( $requested === 'entity_type' ) {
		$val = get_field( 'entity_type', $user_key );
		return esc_html( $val ?: 'person' );
	}

	// Inception date (organization)
	if ( $requested === 'inception_date' ) {
		$val = get_field( 'inception_date', $user_key );
		return esc_html( $val ?: '' );
	}

	// Headquarters location
	if ( $requested === 'headquarters_location' ) {
		$hq = get_field( 'headquarters', $user_key );
		return is_array( $hq ) && ! empty( $hq['location'] ) ? esc_html( $hq['location'] ) : '';
	}

	// Headquarters wiki URL
	if ( $requested === 'headquarters_wiki' ) {
		$hq = get_field( 'headquarters', $user_key );
		return is_array( $hq ) && ! empty( $hq['wiki_url'] ) ? esc_url( $hq['wiki_url'] ) : '';
	}

	// Direct user ACF
	$direct = get_field( $requested, $user_key );
	if ( is_string( $direct ) && $direct !== '' ) {
		if ( filter_var( $direct, FILTER_VALIDATE_URL ) ) return esc_url( $direct );
		if ( in_array( $requested, [ 'biography', 'bio' ], true ) ) return $direct; // allow HTML
		return esc_html( $direct );
	}

	// Nested group_subfield on user
	if ( strpos( $requested, '_' ) !== false ) {
		$parts  = explode( '_', $requested );
		$group  = array_shift( $parts );
		$subkey = implode( '_', $parts );
		if ( $group && $subkey ) {
			$group_val = get_field( $group, $user_key );
			if ( is_array( $group_val ) && isset( $group_val[ $subkey ] ) ) {
				$val = $group_val[ $subkey ];
				if ( is_string( $val ) && $val !== '' ) {
					if ( filter_var( $val, FILTER_VALIDATE_URL ) ) return esc_url( $val );
					if ( is_email( $val ) ) return esc_html( $val );
					return esc_html( $val );
				}
			}
		}
	}

	return '';
}

/**
 * Render education repeater content
 * 
 * Supports these attributes:
 *   format: 'html' (default), 'json', 'array'
 *   index: specific index to get (0-based)
 *   field: specific field to get from entry (college, wiki_url, year, designation, major)
 *
 * @param array  $atts     Shortcode attributes
 * @param string $user_key ACF user key (e.g., 'user_123')
 * @return string
 */
function hws_render_education_shortcode( $atts, $user_key ): string {
	$format = isset( $atts['format'] ) ? strtolower( trim( $atts['format'] ) ) : 'html';
	$index  = isset( $atts['index'] ) ? (int) $atts['index'] : null;
	$field  = isset( $atts['field'] ) ? sanitize_key( $atts['field'] ) : null;
	
	$education = get_field( 'education', $user_key );
	
	if ( empty( $education ) || ! is_array( $education ) ) {
		return '';
	}
	
	// If specific index requested
	if ( $index !== null ) {
		if ( ! isset( $education[ $index ] ) ) {
			return '';
		}
		$entry = $education[ $index ];
		
		// If specific field from that index
		if ( $field && isset( $entry[ $field ] ) ) {
			$val = $entry[ $field ];
			if ( $field === 'wiki_url' && $val ) {
				return esc_url( $val );
			}
			return esc_html( $val );
		}
		
		// Return single entry as HTML
		return hws_format_education_entry_html( $entry );
	}
	
	// If specific field requested from ALL entries (comma-separated)
	if ( $field && $index === null ) {
		$values = [];
		foreach ( $education as $entry ) {
			if ( isset( $entry[ $field ] ) && $entry[ $field ] !== '' ) {
				$values[] = $field === 'wiki_url' ? esc_url( $entry[ $field ] ) : esc_html( $entry[ $field ] );
			}
		}
		return implode( ', ', $values );
	}
	
	// Format: JSON
	if ( $format === 'json' ) {
		return wp_json_encode( $education );
	}
	
	// Format: Array (for developers using do_shortcode)
	if ( $format === 'array' ) {
		// Can't really return an array from shortcode, so return serialized
		return serialize( $education );
	}
	
	// Default: HTML output
	$output = '<div class="hws-education-list">';
	foreach ( $education as $i => $entry ) {
		$output .= hws_format_education_entry_html( $entry, $i );
	}
	$output .= '</div>';
	
	return $output;
}


/**
 * Format a single education entry as HTML
 *
 * @param array $entry Education entry data
 * @param int   $index Optional index for CSS class
 * @return string
 */
function hws_format_education_entry_html( $entry, $index = 0 ): string {
	$college     = isset( $entry['college'] ) ? esc_html( $entry['college'] ) : '';
	$wiki_url    = isset( $entry['wiki_url'] ) ? esc_url( $entry['wiki_url'] ) : '';
	$year        = isset( $entry['year'] ) ? esc_html( $entry['year'] ) : '';
	$designation = isset( $entry['designation'] ) ? esc_html( $entry['designation'] ) : '';
	$major       = isset( $entry['major'] ) ? esc_html( $entry['major'] ) : '';
	
	if ( empty( $college ) && empty( $designation ) && empty( $major ) ) {
		return '';
	}
	
	$html = '<div class="hws-education-entry hws-education-entry-' . $index . '">';
	
	// College name (linked if wiki_url exists)
	if ( $college ) {
		$html .= '<div class="hws-education-college">';
		if ( $wiki_url ) {
			$html .= '<a href="' . $wiki_url . '" target="_blank" rel="noopener">' . $college . '</a>';
		} else {
			$html .= $college;
		}
		$html .= '</div>';
	}
	
	// Designation and Major
	if ( $designation || $major ) {
		$html .= '<div class="hws-education-degree">';
		if ( $designation ) {
			$html .= '<span class="hws-education-designation">' . $designation . '</span>';
		}
		if ( $designation && $major ) {
			$html .= ' in ';
		}
		if ( $major ) {
			$html .= '<span class="hws-education-major">' . $major . '</span>';
		}
		$html .= '</div>';
	}
	
	// Year
	if ( $year ) {
		$html .= '<div class="hws-education-year">' . $year . '</div>';
	}
	
	$html .= '</div>';
	
	return $html;
}


/**
 * Render sameAs content
 * 
 * Supports these attributes:
 *   format: 'text' (default), 'json', 'array', 'ul'
 *
 * @param array  $atts     Shortcode attributes
 * @param string $user_key ACF user key (e.g., 'user_123')
 * @return string
 */
function hws_render_sameas_shortcode( $atts, $user_key ): string {
	$format = isset( $atts['format'] ) ? strtolower( trim( $atts['format'] ) ) : 'text';
	
	$sameas = get_field( 'sameas', $user_key );
	
	if ( empty( $sameas ) || ! is_string( $sameas ) ) {
		return '';
	}
	
	// Split by newlines and filter empty
	$urls = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $sameas ) ) );
	
	if ( empty( $urls ) ) {
		return '';
	}
	
	// Format: JSON
	if ( $format === 'json' ) {
		return wp_json_encode( array_values( $urls ) );
	}
	
	// Format: Array (serialized for shortcode)
	if ( $format === 'array' ) {
		return serialize( $urls );
	}
	
	// Format: UL (unordered list)
	if ( $format === 'ul' ) {
		$output = '<ul class="hws-sameas-list">';
		foreach ( $urls as $url ) {
			$output .= '<li class="hws-sameas-item"><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $url ) . '</a></li>';
		}
		$output .= '</ul>';
		return $output;
	}
	
	// Default: text (newline separated)
	return implode( "\n", array_map( 'esc_url', $urls ) );
}
