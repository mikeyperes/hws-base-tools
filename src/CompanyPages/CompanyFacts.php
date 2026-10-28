<?php

declare(strict_types=1);

namespace HWS\BaseTools\CompanyPages;

use HWS\BaseTools\Founders\Founders;
use HWS\BaseTools\SiteProfile\SiteIdentity;

defined( 'ABSPATH' ) || exit;

/**
 * The site-page building blocks, for Base Tools' page templates and any page:
 *
 *     [hws_site field="name"]                    one site detail from Website Identity ([hws_company] is the older name)
 *     [hws_if field="mission"]...[/hws_if]       text only when the fact, page or shortcode exists
 *     [hws_page_link key="contact"]              link to a key page from any plugin
 *
 * [hws_site] attributes: format (text, link, raw, year), sep, size, fallback,
 * before and after (printed only with a value; after="." is skipped when the
 * value already ends in a period). Details: every Website Identity field for what the site represents (company or person),
 * plus founders (from Base Tools' Founders) and url.
 */
final class CompanyFacts {
    public const SHORTCODE      = 'hws_site';
    /** Older name of [hws_site], kept so existing pages keep working. */
    public const OLD_SHORTCODE  = 'hws_company';
    public const IF_SHORTCODE   = 'hws_if';
    public const LINK_SHORTCODE = 'hws_page_link';
    public const FAQS_SHORTCODE = 'hws_faqs';

    /** Person details that stand in for a company detail when the site represents a person. */
    private const PERSON_FIELDS = [ 'description' => 'bio', 'logo' => 'photo' ];

    public static function register(): void {
        add_shortcode( self::SHORTCODE, [ self::class, 'shortcode' ] );
        add_shortcode( self::OLD_SHORTCODE, [ self::class, 'shortcode' ] );
        add_shortcode( self::IF_SHORTCODE, [ self::class, 'if_shortcode' ] );
        add_shortcode( self::LINK_SHORTCODE, [ self::class, 'link_shortcode' ] );
        add_shortcode( self::FAQS_SHORTCODE, [ self::class, 'faqs_shortcode' ] );
        add_shortcode( 'hws_page_updated', [ self::class, 'page_updated_shortcode' ] );
    }

    /**
     * Raw value of a site detail: from Website Identity for what the site represents (a company
     * or a person), then the website's own settings (name, address, email, tagline).
     */
    public static function value( string $field ) {
        $field = sanitize_key( $field );
        if ( 'founders' === $field ) {
            return Founders::user_ids();
        }
        $type  = (string) SiteIdentity::value( 'type' );
        $value = null;
        if ( 'company' === $type ) {
            $value = SiteIdentity::value( 'company.' . $field );
        } elseif ( 'person' === $type ) {
            $value = SiteIdentity::value( 'person.' . ( self::PERSON_FIELDS[ $field ] ?? $field ) );
        }
        if ( ! self::empty_value( $value ) ) {
            return $value;
        }
        switch ( $field ) {
            case 'name':
                return get_bloginfo( 'name' );
            case 'url':
                return home_url( '/' );
            case 'email':
                return get_option( 'admin_email' );
            case 'description':
                return get_bloginfo( 'description' );
        }
        return '';
    }

    public static function shortcode( $atts ): string {
        $atts  = shortcode_atts( [ 'field' => 'name', 'format' => '', 'sep' => '', 'size' => 'medium', 'fallback' => '', 'before' => '', 'after' => '' ], (array) $atts, self::SHORTCODE );
        $field = sanitize_key( (string) $atts['field'] );
        $value = self::value( $field );
        if ( self::empty_value( $value ) ) {
            return esc_html( (string) $atts['fallback'] );
        }
        $html = self::format_value( $field, $value, $atts );
        if ( '' === trim( wp_strip_all_tags( $html ) ) && 'logo' !== $field ) {
            return esc_html( (string) $atts['fallback'] );
        }
        $after = (string) $atts['after'];
        if ( '' !== $after && '.' === $after[0] && '.' === substr( rtrim( wp_strip_all_tags( $html ) ), -1 ) ) {
            $after = substr( $after, 1 );
        }
        return esc_html( (string) $atts['before'] ) . $html . esc_html( $after );
    }

    /** Whether a condition holds: a site detail has a value, a key page is linked (any plugin), a shortcode is registered. */
    public static function condition( array $atts ): bool {
        $atts = shortcode_atts( [ 'field' => '', 'page' => '', 'shortcode' => '', 'not' => '' ], $atts, self::IF_SHORTCODE );
        $ok   = true;
        if ( '' !== $atts['field'] ) {
            $ok = $ok && ! self::empty_value( self::value( (string) $atts['field'] ) );
        }
        if ( '' !== $atts['page'] ) {
            $ok = $ok && self::published_page( sanitize_key( (string) $atts['page'] ) ) > 0;
        }
        if ( '' !== $atts['shortcode'] ) {
            $ok = $ok && shortcode_exists( sanitize_key( (string) $atts['shortcode'] ) );
        }
        return '' !== $atts['not'] ? ! $ok : $ok;
    }

    public static function if_shortcode( $atts, $content = '' ): string {
        return self::condition( (array) $atts ) ? do_shortcode( (string) $content ) : '';
    }

    /**
     * [hws_faqs]<h2>Question</h2><p>Answer</p>...[/hws_faqs]: the questions inside as a minimalist
     * list that opens on click (one <details> per question), in the site's own fonts and colors.
     * Text before the first question stays as an intro; shortcodes inside run first.
     */
    public static function faqs_shortcode( $atts, $content = '' ): string {
        $parts = preg_split( '#<h[23][^>]*>(.*?)</h[23]>#is', do_shortcode( (string) $content ), -1, PREG_SPLIT_DELIM_CAPTURE );
        $items = '';
        for ( $i = 1, $n = count( $parts ); $i < $n; $i += 2 ) {
            $question = trim( (string) $parts[ $i ] );
            $answer   = trim( (string) ( $parts[ $i + 1 ] ?? '' ) );
            if ( '' === trim( wp_strip_all_tags( $question ) ) ) {
                continue;
            }
            $items .= '<details class="hws-faq"><summary>' . wp_kses_post( $question ) . '</summary><div class="hws-faq__answer">' . $answer . '</div></details>';
        }
        $intro = trim( (string) ( $parts[0] ?? '' ) );
        return self::faqs_styles() . '<div class="hws-faqs">' . ( '' !== trim( wp_strip_all_tags( $intro ) ) ? '<div class="hws-faqs__intro">' . $intro . '</div>' : '' ) . $items . '</div>';
    }

    /** The FAQ list's few styles, printed once with the first list. */
    private static function faqs_styles(): string {
        static $printed = false;
        if ( $printed ) {
            return '';
        }
        $printed = true;
        return '<style id="hws-faqs-css">.hws-faqs{border-top:1px solid rgba(127,127,127,.25)}.hws-faqs__intro{padding:0 0 16px}.hws-faq{border-bottom:1px solid rgba(127,127,127,.25)}.hws-faq summary{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:18px 0;cursor:pointer;font-weight:600;list-style:none}.hws-faq summary::-webkit-details-marker{display:none}.hws-faq summary::after{content:"+";flex:0 0 auto;font-weight:400}.hws-faq[open] summary::after{content:"-"}.hws-faq__answer{padding:0 0 18px}.hws-faq__answer > :first-child{margin-top:0}.hws-faq__answer > :last-child{margin-bottom:0}</style>';
    }

    public static function link_shortcode( $atts ): string {
        $atts    = shortcode_atts( [ 'key' => '', 'text' => '' ], (array) $atts, self::LINK_SHORTCODE );
        $page_id = self::published_page( sanitize_key( (string) $atts['key'] ) );
        if ( ! $page_id ) {
            return '';
        }
        $text = '' !== (string) $atts['text'] ? (string) $atts['text'] : get_the_title( $page_id );
        return '<a href="' . esc_url( (string) get_permalink( $page_id ) ) . '">' . esc_html( $text ) . '</a>';
    }

    /** A published page linked to a key page from Base Tools or any other plugin (0 when none). */
    public static function published_page( string $key ): int {
        $id = CompanyPages::page_id( $key );
        return $id && 'publish' === get_post_status( $id ) ? $id : 0;
    }

    /** Plain names for the site details, as Website Identity labels them. */
    public const LABELS = [
        'name' => 'Name', 'legal_name' => 'Legal name', 'job_title' => 'Job title', 'alternate_names' => 'Other names', 'description' => 'Short description',
        'logo' => 'Logo', 'url' => 'Website address', 'founding_date' => 'Founding date', 'founding_location' => 'Founding city',
        'founding_location_url' => 'Founding city link', 'founders' => 'Founders', 'headquarters_city' => 'Headquarters city',
        'headquarters_address' => 'Headquarters address', 'headquarters_url' => 'Headquarters link', 'parent_name' => 'Parent organization',
        'parent_url' => 'Parent organization website', 'email' => 'Email', 'phone' => 'Phone', 'mission' => 'Mission', 'profiles' => 'Social profiles',
    ];

    /**
     * The site details a piece of page content uses ([hws_site field] or [hws_if field]),
     * as label => value shown as plain text ('' when not set).
     *
     * @return array<string,string>
     */
    public static function used_in( string $html ): array {
        preg_match_all( '/\[(?:hws_site|hws_company|hws_if|smp_company|smp_if)\b[^\]]*\bfield=["\']?([a-z_]+)/', $html, $m );
        $out = [];
        foreach ( array_unique( $m[1] ) as $field ) {
            $out[ self::LABELS[ $field ] ?? $field ] = self::plain( $field );
        }
        return $out;
    }

    /** One fact as short plain text for the admin ('' when not set). */
    /** [hws_page_updated] — the date the current page was last changed, in the site's date format. */
    public static function page_updated_shortcode( $atts ): string {
        $post = get_post();
        if ( ! $post ) {
            return '';
        }
        return esc_html( (string) get_the_modified_date( (string) get_option( 'date_format', 'F j, Y' ), $post ) );
    }

    public static function plain( string $field ): string {
        $value = self::value( $field );
        if ( self::empty_value( $value ) ) {
            return '';
        }
        if ( 'founders' === $field ) {
            $names = [];
            foreach ( (array) $value as $user_id ) {
                $user = get_user_by( 'id', absint( $user_id ) );
                if ( $user ) {
                    $names[] = $user->display_name;
                }
            }
            return self::join_list( $names, '' );
        }
        if ( 'logo' === $field ) {
            return 'Image set';
        }
        if ( 'founding_date' === $field ) {
            $time = strtotime( (string) $value );
            return $time ? date_i18n( (string) get_option( 'date_format', 'F j, Y' ), $time ) : (string) $value;
        }
        if ( is_array( $value ) ) {
            return count( array_filter( $value ) ) . ' added';
        }
        $text = trim( wp_strip_all_tags( (string) $value ) );
        return strlen( $text ) > 90 ? substr( $text, 0, 87 ) . '...' : $text;
    }

    /**
     * The shortcodes site and publication page content is built from, with what each does.
     *
     * @return array<int,array<string,string>>
     */
    public static function reference(): array {
        $type   = (string) SiteIdentity::value( 'type' );
        $fields = [ 'name', 'description', 'url', 'email' ];
        if ( 'company' === $type ) {
            $fields = [ 'name', 'legal_name', 'description', 'logo', 'url', 'email', 'phone', 'mission', 'founding_date', 'founding_location', 'founders', 'headquarters_city', 'headquarters_address', 'headquarters_url', 'parent_name', 'parent_url' ];
        } elseif ( 'person' === $type ) {
            $fields = [ 'name', 'job_title', 'description', 'logo', 'url', 'email' ];
        }
        $what  = [ 'company' => 'a company', 'person' => 'a person' ][ $type ] ?? 'the website itself';
        $items = [ [ 'heading' => 'Site details', 'note' => 'Each one prints a detail of what this website represents (' . $what . '), set in Website Identity, falling back to the website\'s own name, address, email and tagline. A detail that is not set prints nothing.' ] ];
        foreach ( $fields as $field ) {
            $label = 'person' === $type && isset( self::PERSON_FIELDS[ $field ] ) ? [ 'description' => 'Short bio', 'logo' => 'Photo' ][ $field ] : ( self::LABELS[ $field ] ?? $field );
            $items[] = [ 'code' => '[hws_site field="' . $field . '"]', 'description' => $label, 'value' => self::plain( $field ) ];
        }
        $items[] = [ 'heading' => 'Options and building blocks' ];
        $items[] = [ 'code' => '[hws_site field="founding_date" format="year"]', 'description' => 'format: text, link, year or raw. before="..." and after="..." add text only when the detail is set.' ];
        $items[] = [ 'code' => '[hws_if field="mission"]...[/hws_if]', 'description' => 'Shows the text inside only when the detail is set. Also page="contact" (that page exists) or shortcode="name" (that shortcode is available). Add not="1" for the opposite.' ];
        $items[] = [ 'code' => '[hws_page_link key="contact"]', 'description' => 'A link to a site page from any plugin (about, contact, privacy, dmca, editorial_guidelines, become_contributor and the rest). text="..." changes the link text.' ];
        $items[] = [ 'code' => '[hws_page_updated]', 'description' => 'The date this page was last changed. Privacy Policy and Terms of Use use it.' ];
        $items[] = [ 'code' => '[hws_faqs]...[/hws_faqs]', 'description' => 'Questions inside (each a heading followed by its answer) shown as a minimalist list that opens on click. The FAQs page uses it.' ];
        $items[] = [ 'code' => '[hws_site_page_template type="about"]', 'description' => 'The whole default text of a site page, filled in from the details above.' ];
        $items[] = [ 'heading' => 'Lists' ];
        $items[] = [ 'code' => '[hws_publication_founders]', 'description' => 'The founders, with photo, title and short bio.' ];
        $items[] = [ 'code' => '[hws_team_members]', 'description' => 'The team members, when the Team feature is on.' ];
        $items[] = [ 'code' => '[hws_rss_feeds]', 'description' => 'Every RSS feed on the site, with a copy button.' ];
        $items[] = [ 'code' => '[smpi_executive_leadership] [smpi_staff] [smpi_contributors]', 'description' => 'Leadership, staff and contributors, from SMP Publication Integration.' ];
        $items[] = [ 'code' => '[smp_latest_press_releases count="20"] [smp_press_release_form]', 'description' => 'The newest press releases and the press release form, from SMP Publication Integration.' ];
        return $items;
    }

    /** Place facts and the field holding each one's reference page (Wikipedia). */
    private const PLACE_LINKS = [ 'founding_location' => 'founding_location_url', 'headquarters_city' => 'headquarters_url' ];

    /** A place name linked to its reference page in a new tab, or the plain name when there is no link. */
    public static function place_link( string $name_html, string $url ): string {
        return '' !== $url ? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . $name_html . '</a>' : $name_html;
    }

    private static function format_value( string $field, $value, array $atts ): string {
        $format = sanitize_key( (string) $atts['format'] );
        $sep    = (string) $atts['sep'];

        if ( 'founding_date' === $field && 'raw' !== $format ) {
            $time = strtotime( (string) $value );
            return $time ? esc_html( date_i18n( 'year' === $format ? 'Y' : (string) get_option( 'date_format', 'F j, Y' ), $time ) ) : esc_html( (string) $value );
        }
        if ( 'logo' === $field ) {
            return 'url' === $format ? esc_url( (string) wp_get_attachment_image_url( absint( $value ), 'full' ) ) : (string) wp_get_attachment_image( absint( $value ), sanitize_key( (string) $atts['size'] ), false, [ 'class' => 'hws-company-logo', 'loading' => 'lazy' ] );
        }
        if ( 'founders' === $field ) {
            $names = [];
            foreach ( (array) $value as $user_id ) {
                $user = get_user_by( 'id', absint( $user_id ) );
                if ( ! $user ) {
                    continue;
                }
                $profile = CompanyPages::founder_page_id( (int) $user->ID );
                $url     = $profile ? (string) get_permalink( $profile ) : (string) get_author_posts_url( $user->ID );
                $names[] = 'text' === $format ? esc_html( $user->display_name ) : '<a href="' . esc_url( $url ) . '">' . esc_html( $user->display_name ) . '</a>';
            }
            return self::join_list( $names, esc_html( $sep ) );
        }
        if ( 'email' === $field ) {
            $email = sanitize_email( (string) $value );
            return 'text' === $format ? esc_html( $email ) : '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
        }
        if ( in_array( $field, [ 'url', 'parent_url', 'headquarters_url', 'founding_location_url' ], true ) ) {
            $urls = array_map( static fn( $url ): string => 'link' === $format ? '<a href="' . esc_url( (string) $url ) . '"' . ( 'url' === $field ? '' : ' target="_blank" rel="noopener"' ) . '>' . esc_html( (string) $url ) . '</a>' : esc_url( (string) $url ), (array) $value );
            return self::join_list( $urls, esc_html( $sep ) );
        }
        // A place always links to its reference page (Wikipedia), in a new tab, whenever that link is set.
        if ( isset( self::PLACE_LINKS[ $field ] ) && ! in_array( $format, [ 'text', 'raw' ], true ) ) {
            $company = (array) ( SiteIdentity::get()['company'] ?? [] );
            return self::place_link( esc_html( wp_strip_all_tags( (string) $value ) ), (string) ( $company[ self::PLACE_LINKS[ $field ] ] ?? '' ) );
        }
        if ( is_array( $value ) ) {
            return self::join_list( array_map( 'esc_html', array_filter( array_map( 'strval', $value ) ) ), esc_html( $sep ) );
        }
        return in_array( $field, [ 'mission', 'description', 'headquarters_address' ], true ) ? wp_kses_post( nl2br( (string) $value ) ) : esc_html( wp_strip_all_tags( (string) $value ) );
    }

    /**
     * Joins a list as plain English ("A and B", "A, B and C") unless a separator is given (sep="...").
     *
     * @param array<int,string> $items Already escaped items.
     */
    public static function join_list( array $items, string $sep ): string {
        $items = array_values( array_filter( $items, static fn( $item ): bool => '' !== (string) $item ) );
        if ( '' !== $sep || count( $items ) < 2 ) {
            return implode( $sep, $items );
        }
        $last = (string) array_pop( $items );
        return implode( ', ', $items ) . ' and ' . $last;
    }

    private static function empty_value( $value ): bool {
        return null === $value || false === $value || '' === $value || [] === $value || ( is_array( $value ) && [] === array_filter( $value ) );
    }
}
