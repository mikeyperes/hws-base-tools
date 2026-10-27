<?php

declare( strict_types=1 );

namespace HWS\BaseTools\SiteProfile;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\FieldStructures\AcfSettingsPanel;
use Hexa\PluginCore\Fields\Field;
use Hexa\PluginCore\Fields\FieldGroups;

defined( 'ABSPATH' ) || exit;

/**
 * The website's basic identity: whether it represents a company, a person or neither,
 * and that company's or person's basics. Generic for any HWS site; publication-specific
 * information (DMCA, policies, publication pages) belongs to SMP Publication Integration.
 *
 * Stored as option fields through the Hexa WP Core field layer (works with or without ACF).
 * Read with hws_site_identity() or [hws_site_identity field="..."]; see README "Site identity".
 */
final class SiteIdentity implements ModuleInterface {
    public const GROUP     = 'group_hws_site_identity';
    public const CHOICE    = 'hws_site_represents';
    public const COMPANY   = 'hws_company';
    public const PERSON    = 'hws_person';
    public const SHORTCODE = 'hws_site_identity';
    public const TYPES     = [ 'company' => 'A company', 'person' => 'A person', 'none' => 'Neither' ];

    /** Basics keys per type, in form order (read API returns exactly these). */
    public const COMPANY_KEYS = [ 'name', 'legal_name', 'alternate_names', 'description', 'logo', 'url', 'founding_date', 'founding_location', 'founding_location_url', 'founders', 'headquarters_city', 'headquarters_address', 'headquarters_url', 'parent_name', 'parent_url', 'email', 'phone', 'mission', 'profiles' ];
    public const PERSON_KEYS  = [ 'name', 'user', 'job_title', 'bio', 'photo', 'profiles' ];
    /** Keys saved one item per line and returned as ordered lists. Parent names and URLs use matching positions. */
    public const LIST_KEYS    = [ 'alternate_names', 'profiles', 'parent_name', 'parent_url' ];

    public function register(): void {
        require_once __DIR__ . '/site-identity-functions.php';
        FieldGroups::add( self::field_group() );
        add_action( 'init', static fn() => add_shortcode( self::SHORTCODE, [ self::class, 'shortcode' ] ) );
        SiteSetupCommand::register();
    }

    public static function panel(): AcfSettingsPanel {
        return new AcfSettingsPanel(
            [
                'page_slug' => 'hws-core-tools', 'tab' => 'website-types', 'post_id' => 'option',
                'field_groups' => [ self::GROUP ],
                'title' => 'Website Identity',
                'description' => 'Who this website represents. Other plugins (SMP first) read these basics from here.',
                'submit_value' => 'Save Website Identity', 'updated_message' => 'Website identity saved.',
                'persist_key' => 'hws-site-identity', 'open' => true,
            ]
        );
    }

    public static function render_panel(): string {
        return self::panel()->render() . self::toggle_script();
    }

    public static function edit_url(): string {
        return admin_url( 'options-general.php?page=hws-core-tools&tab=website-types' );
    }

    /**
     * The saved identity: [ 'type' => company|person|none|'', 'company' => [...], 'person' => [...] ].
     * Images are attachment IDs, users are user IDs; alternate names, profiles, parent names and parent URLs are lists.
     *
     * @return array<string,mixed>
     */
    public static function get(): array {
        $type    = self::read( self::CHOICE );
        $type    = is_string( $type ) && isset( self::TYPES[ $type ] ) ? $type : '';
        return [
            'type'    => $type,
            'company' => self::normalize( (array) ( self::read( self::COMPANY ) ?: [] ), self::COMPANY_KEYS ),
            'person'  => self::normalize( (array) ( self::read( self::PERSON ) ?: [] ), self::PERSON_KEYS ),
        ];
    }

    /**
     * One value. "type", "company.name", "person.job_title", or a bare key ("name") read from
     * the type the site represents. Returns null when unknown.
     */
    public static function value( string $path ): mixed {
        $identity = self::get();
        if ( 'type' === $path ) {
            return $identity['type'];
        }
        $parts = explode( '.', $path, 2 );
        if ( 1 === count( $parts ) ) {
            if ( ! in_array( $identity['type'], [ 'company', 'person' ], true ) ) {
                return null;
            }
            $parts = [ $identity['type'], $parts[0] ];
        }
        return $identity[ $parts[0] ][ $parts[1] ] ?? null;
    }

    /** [hws_site_identity field="company.name"] — plain text; lists joined by sep; images as <img>. */
    public static function shortcode( $atts ): string {
        $atts  = shortcode_atts( [ 'field' => 'name', 'sep' => ', ', 'size' => 'medium' ], (array) $atts, self::SHORTCODE );
        $field = sanitize_text_field( (string) $atts['field'] );
        $value = self::value( $field );
        $key   = (string) substr( (string) strrchr( '.' . $field, '.' ), 1 );
        if ( in_array( $key, [ 'logo', 'photo' ], true ) ) {
            return $value ? (string) wp_get_attachment_image( (int) $value, sanitize_key( (string) $atts['size'] ) ) : '';
        }
        if ( in_array( $key, [ 'founders', 'user' ], true ) ) {
            $names = [];
            foreach ( (array) $value as $id ) {
                $user = get_userdata( (int) $id );
                if ( $user ) {
                    $names[] = $user->display_name;
                }
            }
            return esc_html( implode( (string) $atts['sep'], $names ) );
        }
        if ( is_array( $value ) ) {
            return esc_html( implode( (string) $atts['sep'], $value ) );
        }
        return esc_html( (string) $value );
    }

    private static function read( string $name ): mixed {
        return self::started() ? Field::get( $name, 'option' ) : self::stored( $name );
    }

    /** False during plugin startup, before ACF has started: reading fields then would start ACF too early and crash. */
    public static function started(): bool {
        return ! function_exists( 'get_field' ) || (bool) did_action( 'acf/init' );
    }

    /** The saved value straight from the options table (a group's sub-fields are stored as options_<group>_<key>). */
    private static function stored( string $name ): mixed {
        $keys = [ self::COMPANY => self::COMPANY_KEYS, self::PERSON => self::PERSON_KEYS ][ $name ] ?? null;
        if ( null === $keys ) {
            return get_option( 'options_' . $name, null );
        }
        $out = [];
        foreach ( $keys as $key ) {
            $out[ $key ] = get_option( 'options_' . $name . '_' . $key, '' );
        }
        return $out;
    }

    /** One item per line or pipe, shared by the CLI and read API, including legacy saved values.
     * @return array<int,string>
     */
    public static function list_values( mixed $value ): array {
        $values = is_array( $value ) ? $value : preg_split( '/\r\n|\r|\n|\s*\|\s*/', (string) $value );
        return array_values( array_filter( array_map( 'trim', array_map( 'strval', $values ?: [] ) ), static fn( string $item ): bool => '' !== $item ) );
    }

    /** @param array<string,mixed> $raw @param array<int,string> $keys @return array<string,mixed> */
    private static function normalize( array $raw, array $keys ): array {
        $out = [];
        foreach ( $keys as $key ) {
            $value = $raw[ $key ] ?? '';
            if ( in_array( $key, self::LIST_KEYS, true ) ) {
                $value = self::list_values( $value );
            } elseif ( in_array( $key, [ 'logo', 'photo', 'user' ], true ) ) {
                $value = is_array( $value ) ? (int) ( $value['ID'] ?? $value['id'] ?? 0 ) : (int) $value;
            } elseif ( 'founders' === $key ) {
                // One definition: the Founders tab (HWS\BaseTools\Founders\Founders).
                $value = \HWS\BaseTools\Founders\Founders::user_ids();
            } else {
                $value = is_scalar( $value ) ? trim( (string) $value ) : '';
            }
            $out[ $key ] = $value;
        }
        // An address that is only the city adds nothing: pages show the city once.
        if ( isset( $out['headquarters_address'], $out['headquarters_city'] ) && '' !== $out['headquarters_address'] && 0 === strcasecmp( trim( (string) $out['headquarters_address'] ), trim( (string) $out['headquarters_city'] ) ) ) {
            $out['headquarters_address'] = '';
        }
        return $out;
    }

    /** @return array<string,mixed> */
    public static function field_group(): array {
        $text  = static fn( string $name, string $label, string $instructions = '', string $type = 'text' ): array => [ 'key' => 'field_hws_identity_' . $name, 'label' => $label, 'name' => $name, 'type' => $type, 'instructions' => $instructions ];
        $image = static fn( string $name, string $label ): array => [ 'key' => 'field_hws_identity_' . $name, 'label' => $label, 'name' => $name, 'type' => 'image', 'return_format' => 'id', 'preview_size' => 'thumbnail', 'library' => 'all' ];
        $lines = static fn( string $name, string $label, string $instructions ): array => [ 'key' => 'field_hws_identity_' . $name, 'label' => $label, 'name' => $name, 'type' => 'textarea', 'rows' => 4, 'new_lines' => '', 'instructions' => $instructions ];

        $company = [
            $text( 'name', 'Name' ),
            $text( 'legal_name', 'Legal name' ),
            $lines( 'alternate_names', 'Other names', 'One per line.' ),
            [ 'key' => 'field_hws_identity_description', 'label' => 'Short description', 'name' => 'description', 'type' => 'textarea', 'rows' => 3, 'new_lines' => '' ],
            $image( 'logo', 'Logo' ),
            $text( 'url', 'Website', '', 'url' ),
            [ 'key' => 'field_hws_identity_founding_date', 'label' => 'Founding date', 'name' => 'founding_date', 'type' => 'date_picker', 'display_format' => 'F j, Y', 'return_format' => 'Y-m-d' ],
            $text( 'founding_location', 'Founding city' ),
            $text( 'founding_location_url', 'Founding city Wikipedia page', 'The founding city\'s Wikipedia page (https://en.wikipedia.org/wiki/...). Used in the schema.', 'url' ),
            [ 'key' => 'field_hws_identity_founders_note', 'label' => 'Founders', 'name' => '', 'type' => 'message', 'message' => 'Founders are picked in one place: the <a href="' . esc_url( admin_url( 'options-general.php?page=hws-core-tools&tab=founders' ) ) . '">Founders tab</a>.' ],
            $text( 'headquarters_city', 'Headquarters city' ),
            [ 'key' => 'field_hws_identity_headquarters_address', 'label' => 'Headquarters address', 'name' => 'headquarters_address', 'type' => 'textarea', 'rows' => 2, 'new_lines' => '' ],
            $text( 'headquarters_url', 'Headquarters city Wikipedia page', 'The headquarters city\'s Wikipedia page (https://en.wikipedia.org/wiki/...). Used in the schema.', 'url' ),
            $text( 'parent_name', 'Parent organizations', 'One per line.', 'textarea' ),
            $lines( 'parent_url', 'Parent organization links', 'One URL per line, in the same order as the parent organizations.' ),
            $text( 'email', 'Contact email', '', 'email' ),
            $text( 'phone', 'Phone' ),
            [ 'key' => 'field_hws_identity_mission', 'label' => 'Mission', 'name' => 'mission', 'type' => 'textarea', 'rows' => 4, 'new_lines' => '', 'instructions' => 'One paragraph.' ],
            $lines( 'profiles', 'Online profiles', 'One link per line (social, Crunchbase, LinkedIn, ...).' ),
        ];
        $person = [
            $text( 'name', 'Name' ),
            [ 'key' => 'field_hws_identity_user', 'label' => 'User account', 'name' => 'user', 'type' => 'user', 'multiple' => 0, 'allow_null' => 1, 'return_format' => 'id', 'role' => '' ],
            $text( 'job_title', 'Job title' ),
            [ 'key' => 'field_hws_identity_bio', 'label' => 'Short bio', 'name' => 'bio', 'type' => 'textarea', 'rows' => 4, 'new_lines' => '' ],
            $image( 'photo', 'Photo' ),
            $lines( 'profiles', 'Online profiles', 'One link per line.' ),
        ];
        // Person sub-fields need their own keys (sub-field keys are global).
        $person = array_map( static fn( array $f ): array => [ 'key' => str_replace( 'field_hws_identity_', 'field_hws_identity_person_', $f['key'] ) ] + $f, $person );

        return [
            'key' => self::GROUP, 'title' => 'Website Identity', 'active' => true,
            'location' => [ [ [ 'param' => 'options_page', 'operator' => '==', 'value' => 'hws-site-identity' ] ] ],
            'label_placement' => 'top', 'instruction_placement' => 'label', 'style' => 'seamless',
            'fields' => [
                [ 'key' => 'field_hws_site_represents', 'label' => 'This website represents', 'name' => self::CHOICE, 'type' => 'radio', 'choices' => self::TYPES, 'layout' => 'horizontal', 'allow_null' => 0, 'default_value' => 'none', 'return_format' => 'value' ],
                [ 'key' => 'field_hws_company', 'label' => 'Company basics', 'name' => self::COMPANY, 'type' => 'group', 'layout' => 'block', 'wrapper' => [ 'class' => 'hws-identity-company', 'id' => '' ], 'sub_fields' => $company ],
                [ 'key' => 'field_hws_person', 'label' => 'Person basics', 'name' => self::PERSON, 'type' => 'group', 'layout' => 'block', 'wrapper' => [ 'class' => 'hws-identity-person', 'id' => '' ], 'sub_fields' => $person ],
            ],
        ];
    }

    /** Shows only the form of the chosen type (ACF and the native field engine both carry data-name and wrapper classes). */
    private static function toggle_script(): string {
        $choice = '[data-name="' . self::CHOICE . '"]';
        return '<style>[data-hws-represents] .hws-identity-company,[data-hws-represents] .hws-identity-person{display:none!important}[data-hws-represents="company"] .hws-identity-company,[data-hws-represents="person"] .hws-identity-person{display:block!important}</style>'
            . '<script>(function(){function sync(){var c=document.querySelector(\'' . $choice . ' input[type=radio]:checked\');document.body.setAttribute("data-hws-represents",c?c.value:"none");}document.addEventListener("change",function(e){if(e.target&&e.target.closest&&e.target.closest(\'' . $choice . '\'))sync();});sync();document.addEventListener("DOMContentLoaded",sync);})();</script>';
    }
}
