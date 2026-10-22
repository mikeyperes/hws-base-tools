<?php

declare( strict_types=1 );

namespace HWS\BaseTools\CompanyPages;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use HWS\BaseTools\SiteProfile\SiteIdentity;

defined( 'ABSPATH' ) || exit;

/**
 * Company pages owned by HWS Base Tools (Pages tab): Contact, Headquarters and Logo.
 *
 * - Page types, templates and the page link live here; other plugins read the link with
 *   hws_base_tools_linked_page( 'contact' | 'headquarters' | 'logo' ) and show it read-only.
 * - Templates print only the facts that are filled in (Website Identity), never a broken sentence.
 * - Missing-facts notice: a plugin that has a fact page switched on adds it through the
 *   `hws_base_tools_pages_in_use` filter (key => label). When the facts that page needs are
 *   missing, Base Tools shows an error at the top of its admin pages naming them, with a link.
 */
final class CompanyPages implements ModuleInterface {
    public const IN_USE_FILTER = 'hws_base_tools_pages_in_use';

    /** Page type => facts it needs; any one fact of each inner list is enough. */
    public const REQUIRED_FACTS = [
        'headquarters'  => [ [ 'headquarters_city', 'headquarters_address' ] ],
        'founding_date' => [ [ 'founding_date' ], [ 'founding_location' ] ],
    ];

    public const FACT_LABELS = [
        'headquarters_city'    => 'Headquarters city',
        'headquarters_address' => 'Headquarters address',
        'founding_date'        => 'Founding date',
        'founding_location'    => 'Founding city',
    ];

    public function register(): void {
        require_once __DIR__ . '/company-pages-functions.php';
        add_action( 'admin_notices', [ self::class, 'render_missing_facts_notice' ], 1 );
    }

    /** @return array<string,array<string,mixed>> Base Tools page types (Pages tab). */
    public static function definitions(): array {
        return [
            'contact'      => [ 'title' => 'Contact', 'slug' => 'contact', 'description' => 'Generic: any website. The public contact page.', 'template' => true ],
            'headquarters' => [ 'title' => 'Headquarters', 'slug' => 'headquarters', 'description' => 'Where the company is based. Facts come from Website Identity (headquarters city and address).', 'template' => true ],
            'logo'         => [ 'title' => 'Logo', 'slug' => 'logo', 'description' => 'The official logo and brand files, with usage rules. Logo from Website Identity; files from Brand Assets.', 'template' => true ],
        ];
    }

    /**
     * Missing facts per switched-on page: [ 'Headquarters' => [ 'Headquarters city or Headquarters address' ] ].
     *
     * @return array<string,list<string>>
     */
    public static function missing_facts(): array {
        $in_use = (array) apply_filters( self::IN_USE_FILTER, [] );
        if ( ! $in_use ) {
            return [];
        }
        $identity = SiteIdentity::get();
        $company  = (array) ( $identity['company'] ?? [] );
        $missing  = [];
        foreach ( $in_use as $key => $label ) {
            $key = sanitize_key( (string) $key );
            if ( ! isset( self::REQUIRED_FACTS[ $key ] ) ) {
                continue;
            }
            // Founding Date is a company page; it does not apply to a site that represents a person or nothing.
            if ( 'founding_date' === $key && 'company' !== $identity['type'] ) {
                continue;
            }
            foreach ( self::REQUIRED_FACTS[ $key ] as $any_of ) {
                $filled = array_filter( $any_of, static fn( string $fact ): bool => '' !== trim( (string) ( $company[ $fact ] ?? '' ) ) );
                if ( ! $filled ) {
                    $missing[ (string) $label ][] = implode( ' or ', array_map( static fn( string $fact ): string => self::FACT_LABELS[ $fact ], $any_of ) );
                }
            }
        }
        return $missing;
    }

    /** Error notice at the top of Base Tools' own admin pages only. */
    public static function render_missing_facts_notice(): void {
        $page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'hws-core-tools' !== $page || ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $missing = self::missing_facts();
        if ( ! $missing ) {
            return;
        }
        echo '<div class="notice notice-error hws-missing-facts"><p><strong>Company facts are missing for pages that are switched on.</strong></p><ul style="list-style:disc;margin-left:20px">';
        foreach ( $missing as $page_label => $facts ) {
            echo '<li>' . esc_html( $page_label ) . ' page: missing ' . esc_html( implode( ', ', $facts ) ) . '.</li>';
        }
        echo '</ul><p><a class="button button-primary" href="' . esc_url( SiteIdentity::edit_url() ) . '">Fill them in on Website Identity</a></p></div>';
    }

    /** Smart template: only the facts that are filled in. */
    public static function headquarters_html(): string {
        $c    = (array) ( SiteIdentity::get()['company'] ?? [] );
        $name = esc_html( '' !== $c['name'] ? $c['name'] : get_bloginfo( 'name' ) );
        $city = esc_html( (string) $c['headquarters_city'] );
        $html = '<h2>Where ' . $name . ' is based</h2>';
        if ( '' !== $city ) {
            $place = '' !== (string) $c['headquarters_url'] ? '<a href="' . esc_url( (string) $c['headquarters_url'] ) . '">' . $city . '</a>' : $city;
            $html .= '<p>' . $name . ' is headquartered in ' . $place . '.</p>';
        }
        if ( '' !== (string) $c['headquarters_address'] ) {
            $html .= '<p>' . nl2br( esc_html( (string) $c['headquarters_address'] ) ) . '</p>';
        }
        $html .= self::contact_line( (string) $c['email'], 'To reach us,' );
        return $html;
    }

    public static function logo_html(): string {
        $c     = (array) ( SiteIdentity::get()['company'] ?? [] );
        $name  = esc_html( '' !== $c['name'] ? $c['name'] : get_bloginfo( 'name' ) );
        $logo  = (int) $c['logo'];
        $html  = '<h2>The ' . $name . ' logo</h2>';
        $html .= $logo ? (string) wp_get_attachment_image( $logo, 'large' ) : do_shortcode( '[site_logo key="logo" size="large"]' );
        $files = do_shortcode( '[brand_asset_gallery size="medium" output="grid" columns="4"]' );
        if ( str_contains( $files, '<img' ) ) {
            $html .= '<h2>Brand files</h2>' . $files;
        }
        $html .= '<h2>Using our logo</h2><ul><li>Use the logo as provided, without changing its colors, proportions or wording.</li><li>Do not use the logo in a way that suggests ' . $name . ' endorses or sponsors a product, person or event.</li></ul>';
        $html .= self::contact_line( (string) $c['email'], 'For other uses, ask first:' );
        return $html;
    }

    /** "To reach us, write to x or use our Contact page." Each part only when it exists. */
    private static function contact_line( string $email, string $lead ): string {
        $email   = sanitize_email( $email );
        $contact = hws_base_tools_linked_page( 'contact' );
        $parts   = [];
        if ( '' !== $email ) {
            $parts[] = 'write to <a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
        }
        if ( $contact && 'publish' === get_post_status( $contact ) ) {
            $parts[] = 'use our <a href="' . esc_url( (string) get_permalink( $contact ) ) . '">Contact</a> page';
        }
        return $parts ? '<p>' . esc_html( $lead ) . ' ' . implode( ' or ', $parts ) . '.</p>' : '';
    }
}
