<?php

declare( strict_types=1 );

namespace HWS\BaseTools\Diagnostics;

use Hexa\PluginCore\IntegrationTests\TestRegistry;
use HWS\BaseTools\AdminDashboard\DashboardRegistry;
use HWS\BaseTools\QueryCompatibility\QueryHookCompatibility;
use HWS\BaseTools\SiteProfile\PrimaryEntityIntegration;

defined( 'ABSPATH' ) || exit;

final class IntegrationTests {
    public static function register( TestRegistry $registry ): void {
        $registry->register(
            'hws.query-hook-compatibility',
            'Third-party query hooks are narrowly guarded',
            static function(): array {
                $audit = QueryHookCompatibility::audit();
                $status = $audit['status'];
                $callbacks = $audit['callbacks'];
                $ptt_state = (string) ( $status['post_type_transfer']['state'] ?? 'missing' );
                $echo_state = (string) ( $status['echo_rss']['state'] ?? 'missing' );
                $elementor_state = (string) ( $status['elementor_search']['state'] ?? 'missing' );

                $ptt_counts_valid = 'guarded' === $ptt_state
                    ? 0 === $callbacks['ptt_parse_vendor_all']
                        && 1 === $callbacks['ptt_parse_guard']
                        && 0 === $callbacks['ptt_query_vendor_all']
                        && 1 === $callbacks['ptt_query_guard']
                        && 1 === $callbacks['ptt_widget_filter']
                        && 1 === $callbacks['ptt_widget_filter_all']
                        && 1 === $callbacks['ptt_query_loop_filter']
                        && 1 === $callbacks['ptt_query_loop_filter_all']
                    : 'inactive' === $ptt_state
                        && 0 === $callbacks['ptt_parse_vendor_all']
                        && 0 === $callbacks['ptt_parse_guard']
                        && 0 === $callbacks['ptt_query_vendor_all']
                        && 0 === $callbacks['ptt_query_guard'];
                $echo_counts_valid = 'guarded' === $echo_state
                    ? 0 === $callbacks['echo_vendor_closure'] && 1 === $callbacks['echo_taxonomy_guard']
                    : 'inactive' === $echo_state
                        && 0 === $callbacks['echo_vendor_closure'] && 0 === $callbacks['echo_taxonomy_guard'];
                $passed = $ptt_counts_valid && $echo_counts_valid
                    && 'capped' === $elementor_state && 1 === $callbacks['elementor_search_cap'];

                return [
                    'passed'   => $passed,
                    'summary'  => $passed ? 'Query compatibility guards and callback counts are exact.' : 'A query-hook vendor signature, state, or callback count requires review.',
                    'expected' => 'PTT guarded/inactive; Echo guarded/inactive; Elementor cap exactly once',
                    'actual'   => 'PTT=' . $ptt_state . '; Echo=' . $echo_state . '; Elementor=' . $elementor_state,
                    'details'  => [ 'status' => $status, 'callbacks' => $callbacks ],
                ];
            },
            [ 'group' => 'HWS Base Tools', 'host' => 'hws-base-tools', 'description' => 'Detects vendor source drift, unsafe broad callbacks, missing guards, and duplicate query hooks.' ]
        );

        $registry->register(
            'hws.primary-entity-contract',
            'HWS primary entity ownership is author-only',
            static function(): array {
                $manager = PrimaryEntityIntegration::manager();
                $sources = $manager->sources();
                $source  = $sources['wordpress_user'] ?? [];
                $mapping = [
                    'personal_website' => 'person',
                    'company_website' => 'organization',
                    'ecommerce_website' => 'organization',
                    'news_outlet' => 'publication',
                    'podcast_website' => 'publication',
                ];
                $mapping_errors = [];
                foreach ( $mapping as $site_type => $entity_type ) {
                    if ( $entity_type !== $manager->entity_type_for_site_type( $site_type ) ) {
                        $mapping_errors[] = $site_type . ' must map to ' . $entity_type;
                    }
                }
                $passed = 1 === count( $sources ) && 'user' === ( $source['kind'] ?? '' ) && [] === $mapping_errors;
                return [
                    'passed' => $passed,
                    'summary' => $passed ? 'HWS exposes one optional WordPress author and derives semantic type from website type.' : 'HWS entity ownership or website-type mapping is invalid.',
                    'expected' => 'One user source; Personal=Person, Company/e-Commerce=Organization, News/Podcast=Publication',
                    'actual' => count( $sources ) . ' source(s); ' . ( $mapping_errors ? implode( '; ', $mapping_errors ) : 'mapping valid' ),
                    'details' => [ 'sources' => array_keys( $sources ) ],
                ];
            },
            [ 'group' => 'HWS Base Tools', 'host' => 'hws-base-tools', 'description' => 'Prevents Verified Profile or Organization post sources from leaking back into HWS ownership.' ]
        );

        $registry->register(
            'hws.primary-entity-resolution',
            'Saved HWS author resolves when enabled',
            static function(): array {
                $manager  = PrimaryEntityIntegration::manager();
                $settings = $manager->settings();
                $entity   = $manager->resolve();
                $passed   = empty( $settings['enabled'] ) || ( is_array( $entity ) && 'user' === ( $entity['kind'] ?? '' ) && ! empty( $entity['id'] ) );
                return [
                    'passed' => $passed,
                    'summary' => empty( $settings['enabled'] ) ? 'No primary author is enabled; this is a supported configuration.' : ( $passed ? 'The configured WordPress author resolves correctly.' : 'The enabled primary author cannot be resolved.' ),
                    'expected' => 'Disabled, or one valid WordPress user',
                    'actual' => empty( $settings['enabled'] ) ? 'Disabled' : ( $entity ? (string) ( $entity['name'] ?? 'Resolved user' ) : 'Unresolved' ),
                    'details' => [ 'source' => (string) ( $settings['source'] ?? '' ), 'object_id' => (string) ( $settings['object_id'] ?? 0 ) ],
                ];
            },
            [ 'group' => 'HWS Base Tools', 'host' => 'hws-base-tools', 'description' => 'Confirms HWS does not require an author and validates it when one is enabled.' ]
        );

        $registry->register(
            'hws.security-navigation',
            'Masked Login is in the Security sidebar group',
            static function(): array {
                $groups = DashboardRegistry::instance()->navigation_groups();
                $security = [];
                $other_groups = [];
                foreach ( $groups as $group ) {
                    if ( 'Security' === (string) ( $group['label'] ?? '' ) ) {
                        $security = (array) ( $group['tabs'] ?? [] );
                    } elseif ( in_array( 'masked-login', (array) ( $group['tabs'] ?? [] ), true ) ) {
                        $other_groups[] = (string) ( $group['label'] ?? '' );
                    }
                }
                $passed = in_array( 'masked-login', $security, true ) && [] === $other_groups;
                return [
                    'passed' => $passed,
                    'summary' => $passed ? 'Masked Login has one clear Security sidebar location.' : 'Masked Login is missing from Security or appears in another group.',
                    'expected' => 'Security > Masked Login only',
                    'actual' => $security ? 'Security > ' . implode( ', ', $security ) : 'Security group missing',
                    'details' => [ 'duplicate_groups' => $other_groups ],
                ];
            },
            [ 'group' => 'HWS Base Tools', 'host' => 'hws-base-tools', 'description' => 'Protects the sidebar placement requested for login security controls.' ]
        );

        $registry->register(
            'hws.primary-entity-ajax',
            'Primary author automatic save route is registered',
            static function(): array {
                $hook = function_exists( 'has_action' ) ? has_action( 'wp_ajax_hws_save_primary_entity' ) : false;
                return [
                    'passed' => false !== $hook,
                    'summary' => false !== $hook ? 'The guarded AJAX save endpoint is registered.' : 'The primary-author save endpoint is missing.',
                    'expected' => 'wp_ajax_hws_save_primary_entity callback',
                    'actual' => false !== $hook ? 'Registered at priority ' . $hook : 'Not registered',
                ];
            },
            [ 'group' => 'HWS Base Tools', 'host' => 'hws-base-tools', 'description' => 'Confirms author selection can save automatically and return the profile preview.' ]
        );
    }
}
