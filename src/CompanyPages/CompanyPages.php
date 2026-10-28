<?php

declare( strict_types=1 );

namespace HWS\BaseTools\CompanyPages;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\SiteStructure\PageRedirects;
use Hexa\PluginCore\SiteStructure\PageStructureManager;
use HWS\BaseTools\Founders\FounderCards;
use HWS\BaseTools\Founders\Founders;
use HWS\BaseTools\SiteProfile\SiteIdentity;

defined( 'ABSPATH' ) || exit;

/**
 * Company pages owned by HWS Base Tools (Pages tab): the pages any website has.
 *
 * - About and its children (Mission Statement, Founders with one profile page per founder,
 *   Founding Date, Headquarters, Logo, Parent Network, Team), Contact, Privacy Policy, Terms of
 *   Use, Accessibility, FAQs and RSS Feed. Each page sits under its parent; ensure_pages() sets
 *   them all up (WP-CLI: wp hws-pages ensure).
 * - Wording is in templates/company-pages/, built from [hws_site], [hws_if] and
 *   [hws_page_link] (CompanyFacts), so it prints only the facts that are filled in.
 * - Other plugins read a link with hws_base_tools_linked_page( '<key>' ); a page of theirs can
 *   name a Base Tools page as its parent (Hexa WP Core resolves it).
 * - Missing-facts notice: pages with required facts that are linked, plus pages another plugin
 *   reports through the `hws_base_tools_pages_in_use` filter (key => label).
 */
final class CompanyPages implements ModuleInterface {
    public const IN_USE_FILTER = 'hws_base_tools_pages_in_use';

    /** Post meta naming the founder (user ID) a founder profile page belongs to. */
    public const FOUNDER_META = '_hws_founder_user';

    public const FOUNDER_SHORTCODE = 'hws_founder_profile';

    public function register(): void {
        require_once __DIR__ . '/company-pages-functions.php';
        add_action( 'admin_notices', [ self::class, 'render_missing_facts_notice' ], 1 );
        add_action( 'init', [ CompanyFacts::class, 'register' ] );
        PageRedirects::register();
        add_action( 'init', static function (): void {
            add_shortcode( self::FOUNDER_SHORTCODE, [ self::class, 'founder_profile_shortcode' ] );
        } );
        if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\\WP_CLI' ) ) {
            \WP_CLI::add_command( 'hws-pages ensure', static function (): void {
                foreach ( self::ensure_pages() as $key => $result ) {
                    if ( is_wp_error( $result ) ) {
                        \WP_CLI::warning( $key . ': ' . $result->get_error_message() );
                        continue;
                    }
                    \WP_CLI::log( sprintf( '%s: page %d%s%s %s', $key, $result['page_id'], $result['created'] ? ' created' : '', $result['moved'] ? ' moved' : '', (string) get_permalink( $result['page_id'] ) ) );
                }
            } );
        }
    }

    /** Base Tools' page manager (Hexa WP Core), loading the Pages wiring when the admin tab has not. */
    public static function manager(): PageStructureManager {
        if ( ! function_exists( 'hws_base_tools\\hws_pages_manager' ) ) {
            require_once dirname( __DIR__ ) . '/SiteStructure/legacy-pages.php';
        }
        return \hws_base_tools\hws_pages_manager();
    }

    /** The page linked to a key page from Base Tools or any other plugin (0 when none). */
    public static function page_id( string $key ): int {
        return self::manager()->resolve_page_id( sanitize_key( $key ) );
    }

    /** The founder profile page of one user (0 when none). */
    public static function founder_page_id( int $user_id ): int {
        if ( $user_id <= 0 ) {
            return 0;
        }
        $ids = get_posts( [ 'post_type' => 'page', 'post_status' => [ 'publish', 'draft', 'private', 'pending' ], 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => self::FOUNDER_META, 'meta_value' => $user_id ] ); // phpcs:ignore WordPress.DB.SlowDBQuery
        return $ids ? (int) $ids[0] : 0;
    }

    /** A page already about this founder: its address holds the user's name (michael-peres-mikey-peres), under About first. */
    private static function existing_founder_page( \WP_User $user ): int {
        global $wpdb;
        $like  = '%' . $wpdb->esc_like( (string) $user->user_nicename ) . '%';
        $about = self::page_id( 'about' );
        $sql   = $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_name LIKE %s ORDER BY post_parent = %d DESC, ID ASC LIMIT 1", $like, $about ); // phpcs:ignore WordPress.DB.PreparedSQL
        return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    }

    /**
     * The one setup call: every Base Tools page (parents first, each under its
     * parent, existing pages reused with their content), then one profile page
     * per founder under Founders.
     *
     * @return array<string,array<string,mixed>|\WP_Error>
     */
    public static function ensure_pages(): array {
        // Optional pages (Parent Network) are only placed when they already exist; they are created on request.
        $manager = self::manager();
        $keys    = array_keys( array_filter( self::definitions(), static fn( array $def, string $key ): bool => empty( $def['optional'] ) || self::page_id( $key ) > 0 || $manager->find_page_by_slug( (string) $def['slug'] ) > 0, ARRAY_FILTER_USE_BOTH ) );
        $results = $manager->ensure_pages( $keys );
        $parent  = self::page_id( 'founders' );
        foreach ( Founders::user_ids() as $user_id ) {
            $results[ 'founder_' . $user_id ] = self::ensure_founder_page( (int) $user_id, $parent );
        }
        return $results;
    }

    /** @return array<string,mixed>|\WP_Error */
    public static function ensure_founder_page( int $user_id, int $parent_id ) {
        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            return new \WP_Error( 'hws_founder_missing', 'Founder user not found.' );
        }
        $page_id = self::founder_page_id( $user_id );
        $created = false;
        if ( ! $page_id ) {
            $page_id = self::existing_founder_page( $user );
            if ( $page_id ) {
                update_post_meta( $page_id, self::FOUNDER_META, $user_id );
            }
        }
        if ( ! $page_id ) {
            // A founder profile has its own design in the site redesign: it waits as a draft until that is built.
            $page_id = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => $user->display_name, 'post_name' => sanitize_title( $user->user_nicename ), 'post_parent' => $parent_id, 'post_content' => '[' . self::FOUNDER_SHORTCODE . ' user="' . $user_id . '"]' ], true );
            if ( is_wp_error( $page_id ) ) {
                return $page_id;
            }
            update_post_meta( (int) $page_id, self::FOUNDER_META, $user_id );
            $created = true;
        }
        $moved = $parent_id > 0 && PageStructureManager::place_under_parent( (int) $page_id, $parent_id );
        return [ 'page_key' => 'founder_' . $user_id, 'page_id' => (int) $page_id, 'created' => $created, 'moved' => $moved, 'parent_id' => $parent_id ];
    }

    /** [hws_founder_profile user="ID"]: the founder's card (photo, name, title, bio) from Base Tools' Founders. */
    public static function founder_profile_shortcode( $atts ): string {
        $atts    = shortcode_atts( [ 'user' => 0 ], (array) $atts, self::FOUNDER_SHORTCODE );
        $user_id = absint( $atts['user'] );
        if ( ! $user_id || ! in_array( $user_id, array_map( 'intval', Founders::user_ids() ), true ) ) {
            return '';
        }
        return FounderCards::render( [ $user_id ], FounderCards::ATTRIBUTES, [ 'block' => Founders::BLOCK, 'item' => Founders::ITEM, 'style' => Founders::settings()['style'], 'title' => [ Founders::class, 'title' ] ] );
    }

    /**
     * The company details a page's default content uses, label => value ('' when not set),
     * for the Pages tab's "Details this page uses".
     *
     * @return array<string,string>
     */
    public static function facts_used( string $type ): array {
        $html = self::template_html( $type );
        if ( 'headquarters' === $type ) {
            $html = '[hws_site field="name"][hws_site field="headquarters_city"][hws_site field="headquarters_address"][hws_site field="headquarters_url"][hws_site field="email"]';
        } elseif ( 'logo' === $type ) {
            $html = '[hws_site field="name"][hws_site field="logo"]';
        }
        return CompanyFacts::used_in( $html );
    }

    /** Base Tools' page wording for a page type, from templates/company-pages/<type>.html ('' when none). */
    public static function template_html( string $type ): string {
        $file = dirname( __DIR__, 2 ) . '/templates/company-pages/' . sanitize_key( $type ) . '.html';
        return is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
    }

    /**
     * Base Tools page types (Pages tab): the company pages any website has.
     * Keys match SMP Publication Integration's older keys, so pages it linked are kept.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function definitions(): array {
        $about = [ 'parent' => 'about', 'template' => true ];
        // Pages about an organization: not for a website that represents a person.
        $org   = [ 'not_for' => [ 'person' ] ];
        // Pages with their own design in the site redesign: linked and placed, never filled with the default text.
        $custom = [ 'design' => 'custom' ];
        return [
            'about'               => [ 'title' => 'About', 'slug' => 'about', 'slugs' => [ 'about-us' ], 'description' => 'Who the company is. Parent of every About page.', 'template' => true ] + $custom,
            'mission_statement'   => [ 'title' => 'Mission Statement', 'slug' => 'mission-statement', 'slugs' => [ 'mission', '*mission-statement' ], 'description' => 'The mission, from Website Identity.' ] + $about,
            'founders'            => [ 'title' => 'Founders', 'slug' => 'founders', 'slugs' => [ 'founding-team' ], 'description' => 'The founders, from Base Tools\' Founders. Each founder also gets a profile page under it.' ] + $custom + $org + $about,
            'founding_date'       => [ 'title' => 'Founding Date', 'slug' => 'founding-date', 'description' => 'When and where the company was founded, from Website Identity.', 'required_facts' => [ 'Founding date' => [ 'founding_date' ], 'Founding city' => [ 'founding_location' ] ] ] + $org + $about,
            'headquarters'        => [ 'title' => 'Headquarters', 'slug' => 'headquarters', 'description' => 'Where the company is based. Facts come from Website Identity (headquarters city and address).', 'required_facts' => [ 'Headquarters city or Headquarters address' => [ 'headquarters_city', 'headquarters_address' ] ] ] + $org + $about,
            'logo'                => [ 'title' => 'Logo', 'slug' => 'logo', 'description' => 'The official logo and brand files, with usage rules. Logo from Website Identity; files from Brand Assets.' ] + $about,
            'parent_organization' => [ 'title' => 'Parent Network', 'slug' => 'parent-network', 'slugs' => [ 'parent-organization', 'parent-company' ], 'description' => 'The parent company, from Website Identity. Optional.', 'optional' => true ] + $org + $about,
            'team'                => [ 'title' => 'Team', 'slug' => 'team', 'slugs' => [ 'our-team' ], 'description' => 'Everyone on Base Tools\' Team list.' ] + $custom + $about,
            'contact'             => [ 'title' => 'Contact', 'slug' => 'contact', 'slugs' => [ 'contact-us' ], 'description' => 'Generic: any website. The public contact page.', 'template' => true ] + $custom,
            'privacy'             => [ 'title' => 'Privacy Policy', 'slug' => 'privacy-policy', 'slugs' => [ 'privacy', 'privacy-policy-*' ], 'description' => 'What visitor data is collected and how it is used.', 'template' => true ],
            'terms'               => [ 'title' => 'Terms of Use', 'slug' => 'terms-of-use', 'slugs' => [ 'terms', 'terms-and-conditions', 'terms-of-use-*' ], 'description' => 'The rules for using the website.', 'template' => true ],
            'accessibility'       => [ 'title' => 'Accessibility', 'slug' => 'accessibility', 'description' => 'The accessibility commitment and how to report a barrier.', 'template' => true ],
            'faqs'                => [ 'title' => 'FAQs', 'slug' => 'faqs', 'slugs' => [ 'faq' ], 'description' => 'Common visitor questions.', 'template' => true ],
            'rss_feed'            => [ 'title' => 'RSS Feed', 'slug' => 'rss-feed', 'slugs' => [ 'rss', 'feeds' ], 'description' => 'Every RSS feed, from Base Tools\' feeds.', 'template' => true ],
        ];
    }

    /**
     * Missing facts per switched-on page: [ 'Headquarters' => [ 'Headquarters city or Headquarters address' ] ].
     *
     * @return array<string,list<string>>
     */
    public static function missing_facts(): array {
        $manager  = self::manager();
        $identity = SiteIdentity::get();
        $in_use   = (array) apply_filters( self::IN_USE_FILTER, [] );
        foreach ( self::definitions() as $key => $def ) {
            if ( ! empty( $def['required_facts'] ) && self::page_id( $key ) > 0 ) {
                $in_use[ $key ] = (string) $def['title'];
            }
        }
        $missing = [];
        foreach ( $in_use as $key => $label ) {
            $key = sanitize_key( (string) $key );
            // Founding Date is a company page; it does not apply to a site that represents a person or nothing.
            if ( 'founding_date' === $key && 'company' !== $identity['type'] ) {
                continue;
            }
            $facts = $manager->missing_facts( $key );
            if ( $facts ) {
                $missing[ (string) $label ] = $facts;
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
            $place = CompanyFacts::place_link( $city, (string) $c['headquarters_url'] );
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
