<?php

declare( strict_types=1 );

namespace HWS\BaseTools\Pages;

defined( 'ABSPATH' ) || exit;

/** Canonical catalog. Stable keys preserve existing assignments. */
final class PageTypes {
    public static function definitions(): array {
        $about = [ 'parent' => 'about', 'template' => true ];
        // Pages about an organization: not for a website that represents a person.
        $org   = [ 'not_for' => [ 'person' ] ];
        // Pages with their own design in the site redesign: linked and placed, never filled with the default text.
        $custom = [ 'design' => 'custom' ];
        $pages = [
            'about'               => [ 'title' => 'About', 'slug' => 'about', 'slugs' => [ 'about-us' ], 'description' => 'Who the company is. Parent of every About page.', 'template' => true ] + $custom,
            'mission_statement'   => [ 'title' => 'Mission Statement', 'slug' => 'mission-statement', 'slugs' => [ 'mission', '*mission-statement' ], 'description' => 'The mission, from Website Identity.' ] + $about,
            'founders'            => [ 'title' => 'Founders', 'slug' => 'founders', 'slugs' => [ 'founding-team' ], 'description' => 'The founders, from Base Tools\' Founders. Each founder also gets a profile page under it.' ] + $custom + $org + $about,
            'founding_date'       => [ 'title' => 'Founding Date', 'slug' => 'founding-date', 'description' => 'When and where the company was founded, from Website Identity.', 'required_facts' => [ 'Founding date' => [ 'founding_date' ], 'Founding city' => [ 'founding_location' ] ] ] + $org + $about,
            'headquarters'        => [ 'title' => 'Headquarters', 'slug' => 'headquarters', 'description' => 'Where the company is based. Facts come from Website Identity (headquarters city and address).', 'required_facts' => [ 'Headquarters city or Headquarters address' => [ 'headquarters_city', 'headquarters_address' ] ] ] + $org + $about,
            'logo'                => [ 'title' => 'Logo', 'slug' => 'logo', 'description' => 'The official logo and brand files, with usage rules. Logo from Website Identity; files from Brand Assets.' ] + $about,
            'parent_organization' => [ 'title' => 'Parent Network', 'slug' => 'parent-network', 'slugs' => [ 'parent-organization', 'parent-company' ], 'description' => 'The parent company, from Website Identity. Optional.', 'optional' => true ] + $org + $about,
            'team'                => [ 'title' => 'Team', 'slug' => 'team', 'slugs' => [ 'our-team' ], 'description' => 'Everyone on Base Tools\' Team list.' ] + $custom + $about,
            'contact'             => [ 'title' => 'Connect', 'slug' => 'connect', 'slugs' => [ 'contact', 'contact-us' ], 'description' => 'Generic: any website. The public contact page.', 'template' => true ] + $custom,
            'privacy'             => [ 'title' => 'Privacy Policy', 'slug' => 'privacy-policy', 'slugs' => [ 'privacy', 'privacy-policy-*' ], 'description' => 'What visitor data is collected and how it is used.', 'template' => true ],
            'cookies'             => [ 'title' => 'Cookie Policy', 'slug' => 'cookie-policy', 'slugs' => [ 'cookies', 'cookie-policy-*' ], 'description' => 'Which cookies the website uses and how to control them.', 'template' => true ],
            'terms'               => [ 'title' => 'Terms of Use', 'slug' => 'terms-of-use', 'slugs' => [ 'terms', 'terms-and-conditions', 'terms-of-use-*' ], 'description' => 'The rules for using the website.', 'template' => true ],
            'accessibility'       => [ 'title' => 'Accessibility', 'slug' => 'accessibility', 'description' => 'The accessibility commitment and how to report a barrier.', 'template' => true ],
            'faqs'                => [ 'title' => 'FAQs', 'slug' => 'faqs', 'slugs' => [ 'faq' ], 'description' => 'Common visitor questions.', 'template' => true ],
            'rss_feed'            => [ 'title' => 'RSS Feed', 'slug' => 'rss-feed', 'slugs' => [ 'rss', 'feeds' ], 'description' => 'Every RSS feed, from Base Tools\' feeds.', 'template' => true ],
            'sitemap'             => [ 'title' => 'Sitemap', 'slug' => 'sitemap', 'slugs' => [ 'site-map' ], 'description' => 'Every page of the site, from Rank Math\'s HTML sitemap.', 'template' => true, 'content_source' => 'Rank Math HTML sitemap' ],
            // Company pages: off by default, switched on per site.
            'services'            => [ 'title' => 'Services', 'slug' => 'services', 'description' => 'Every service, from Base Tools\' Services.', 'optional' => true, 'content_source' => 'HWS Base Tools Services records' ] + $custom + $org,
            'knowledge_base'      => [ 'title' => 'Knowledge Base', 'slug' => 'knowledge-base', 'slugs' => [ 'blog', 'resources' ], 'description' => 'The site\'s articles.', 'optional' => true, 'content_source' => 'WordPress posts' ] + $custom + $org,
            'client_portal'       => [ 'title' => 'Client Portal', 'slug' => 'client-portal', 'slugs' => [ 'document-portal' ], 'description' => 'Where clients sign in or send documents.', 'optional' => true, 'content_source' => 'Site client portal link or form' ] + $custom + $org,
            'pay_invoice'         => [ 'title' => 'Pay Invoice', 'slug' => 'pay-invoice', 'description' => 'Where clients pay an invoice.', 'optional' => true, 'content_source' => 'Site payment link or form' ] + $custom + $org,
            'service_forms'       => [ 'title' => 'Service Forms', 'slug' => 'service-forms', 'description' => 'The forms clients fill in to start a service.', 'optional' => true, 'content_source' => 'Site forms' ] + $custom + $org,
            'downloadable_assets' => [ 'title' => 'Downloadable Assets', 'slug' => 'downloadable-assets', 'description' => 'Files clients can download.', 'optional' => true, 'content_source' => 'Site media files' ] + $custom + $org,
        ];

        foreach ( $pages as $key => &$page ) {
            $page += [ 'slugs' => [], 'parent' => '', 'design' => 'text', 'enabled_by_default' => empty( $page['optional'] ), 'conditions' => $page['not_for'] ?? [], 'kind' => 'page', 'content_source' => 'templates/company-pages/' . $key . '.html' ];
        }
        unset( $page );
        foreach ( [
            'about' => 'Site design and Website Identity',
            'founders' => '[hws_publication_founders]',
            'team' => '[hws_team_members]',
            'contact' => 'Site contact form and Website Identity',
            'headquarters' => 'CompanyPages::headquarters_html',
            'logo' => 'CompanyPages::logo_html',
        ] as $key => $source ) {
            $pages[$key]['content_source'] = $source;
        }
        $pages['founders']['title_source'] = 'HWS\\BaseTools\\Founders\\Founders::settings.heading';
        $pages += [
            'category' => [ 'title' => 'Category', 'slug' => '/category/<name>/', 'kind' => 'layout', 'content_source' => 'WordPress native category query' ],
            'tag' => [ 'title' => 'Tag', 'slug' => '/tag/<name>/', 'kind' => 'layout', 'content_source' => 'WordPress native tag query' ],
            'homepage' => [ 'title' => 'Homepage', 'slug' => '/', 'kind' => 'layout', 'content_source' => 'WordPress front page' ],
            'founder_profile' => [ 'title' => 'Founder profile', 'slug' => '<name>', 'parent' => 'founders', 'kind' => 'profile', 'content_source' => 'hws_founder_profile', 'repeat_for' => 'founders' ],
            'team_member_profile' => [ 'title' => 'Team member profile', 'slug' => '<name>', 'parent' => 'team', 'kind' => 'profile', 'content_source' => 'HWS Base Tools team member records', 'repeat_for' => 'team_members' ],
            'search_results' => [ 'title' => 'Search results', 'slug' => '/?s=', 'kind' => 'layout', 'content_source' => 'WordPress search query' ],
            '404' => [ 'title' => '404', 'slug' => '', 'kind' => 'layout', 'content_source' => 'WordPress 404 query', 'design_mode' => [ 'website' => 'simple', 'publication' => 'artistic' ] ],
        ];
        foreach ( $pages as &$page ) {
            $page += [ 'slugs' => [], 'parent' => '', 'design' => 'custom', 'enabled_by_default' => true, 'conditions' => [] ];
        }
        unset( $page );
        return $pages;
    }

    /** Query layouts and repeated profiles have their own runtime, never ordinary-page creation. */
    public static function pages(): array {
        return array_filter( self::definitions(), static fn( array $page ): bool => 'page' === $page['kind'] );
    }
}
