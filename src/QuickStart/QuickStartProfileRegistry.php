<?php

namespace HWS\BaseTools\QuickStart;

/**
 * HWS-owned launch policy expressed through the reusable Core checklist.
 */
final class QuickStartProfileRegistry {
    public const DEFAULT_PROFILE = 'standard';

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function profiles(): array {
        $standard = self::steps( 'safe_baseline' );

        $profiles = [
            'standard' => [
                'label'       => 'Standard Website',
                'description' => 'The complete, safe HWS launch policy for a normal production website.',
                'steps'       => $standard,
            ],
            'diamond_website' => [
                'label'       => 'Diamond Website',
                'description' => 'The standard production policy with the Diamond website profile recorded in the final report.',
                'steps'       => self::steps( 'safe_baseline', [ 'site_profile' => 'diamond_website' ] ),
            ],
            'news_outlet' => [
                'label'       => 'News / Publication',
                'description' => 'A publication launch policy with an editorial LiteSpeed profile and the SMP plugin stack.',
                'steps'       => self::steps( 'editorial', [ 'site_profile' => 'news_outlet', 'include_smp' => true ] ),
            ],
            'audit_only' => [
                'label'       => 'Dry Run',
                'description' => 'Inspects the full launch surface without changing WordPress, plugins, content, or cache settings.',
                'steps'       => self::audit_steps(),
            ],
        ];

        return function_exists( 'apply_filters' )
            ? (array) apply_filters( 'hws_base_tools_quick_start_profiles', $profiles )
            : $profiles;
    }

    /**
     * @param array<string,mixed> $profile_context
     * @return array<int,array<string,mixed>>
     */
    private static function steps( string $litespeed_profile, array $profile_context = [] ): array {
        $context = array_merge( [ 'litespeed_profile' => $litespeed_profile ], $profile_context );

        return [
            self::group(
                'preflight',
                '1. Preflight & Option Before-State',
                'Record the approved WordPress option before-state and confirm WordPress is ready for a guarded setup run.',
                [
                    self::task( 'capture_snapshot', 'Capture verified option before-state', 'setup_action', 'Stores a versioned, site-scoped history record. Only its approved WordPress options are restorable; software and files are inventory context only.', 'Capture', $context ),
                    self::task( 'install_bootstrap_loader', 'Install the HWS bootstrap URL', 'setup_action', 'Installs and verifies the secure MU-plugin loader that can fetch HWS Base Tools from GitHub on demand.', 'Install', $context ),
                    self::task( 'audit_wordpress_runtime', 'Check WordPress, PHP & CloudLinux visibility', 'status_check', 'Checks versions, PHP handler, extensions, OPcache, Redis, upload/runtime limits, required write access, and reports LVE quotas as server-only when WordPress cannot read them.', 'Check', $context ),
                    self::task( 'audit_site_identity', 'Check site identity', 'status_check', 'Checks the live URL, title, administration email, timezone, indexing state, and environment type.', 'Check', $context ),
                    self::task( 'audit_permalink', 'Check inner-page routing', 'status_check', 'Checks the permalink structure and verifies that rewrite rules are present.', 'Check', $context ),
                ]
            ),
            self::group(
                'updates',
                '2. Update Everything',
                'Synchronize the shared runtime first, then install every current WordPress, plugin, and theme update.',
                [
                    self::task( 'sync_hexa_core', 'Update Hexa WP Core everywhere', 'setup_action', 'Downloads Core once and synchronizes every active Hexa plugin bundle registered on this site.', 'Update', $context ),
                    self::task( 'update_wordpress', 'Update WordPress core now', 'setup_action', 'Installs the currently offered WordPress core update and verifies the resulting version.', 'Update', $context ),
                    self::task( 'update_plugins', 'Update all plugins now', 'setup_action', 'Installs every currently offered plugin update, including GitHub-backed Hexa plugins.', 'Update', $context ),
                    self::task( 'update_themes', 'Update all themes now', 'setup_action', 'Installs every currently offered theme update.', 'Update', $context ),
                ]
            ),
            self::group(
                'plugin_stack',
                '3. Required Plugin Stack',
                'Install and activate the HWS-required plugin stack for this site profile.',
                [
                    self::task( 'install_essential_plugins', 'Install and activate required plugins', 'setup_action', 'Uses the shared provisioner and the selected HWS site profile. Paid plugins remain review items when no licensed package is available.', 'Install', $context ),
                    self::task( 'enable_auto_updates', 'Enable future automatic updates', 'config_mutation', 'Enables all WordPress core, plugin, and theme auto-updates after the required stack is installed.', 'Apply', $context ),
                    self::task( 'verify_plugin_stack', 'Verify plugin stack', 'status_check', 'Reports missing, inactive, outdated, or conflicting plugins after provisioning.', 'Verify', $context ),
                ]
            ),
            self::group(
                'wordpress_baseline',
                '4. WordPress Baseline',
                'Apply the WordPress-owned launch settings that are safe to automate from a plugin.',
                [
                    self::task( 'set_memory_limit', 'Set WordPress memory to 4 GB', 'config_mutation', 'Sets both WP_MEMORY_LIMIT and WP_MAX_MEMORY_LIMIT to exactly 4096M and verifies the file values.', 'Apply', $context ),
                    self::task( 'disable_debug_settings', 'Disable production debug output', 'config_mutation', 'Disables WP_DEBUG, WP_DEBUG_DISPLAY, and WP_DEBUG_LOG.', 'Apply', $context ),
                    self::task( 'disable_comments_pings', 'Disable all comments and pings', 'config_mutation', 'Closes future discussion and all existing post comment and ping statuses. Existing comments are handled separately in Review Center.', 'Apply', $context ),
                    self::task( 'repair_permalinks', 'Hard-repair permalinks', 'setup_action', 'Preserves the chosen permalink structure, rebuilds rewrite rules, and verifies a live inner page.', 'Repair', $context ),
                    self::task( 'audit_real_cron', 'Check real cron handoff', 'status_check', 'Reports whether visitor-driven WP-Cron is disabled and whether a recent external cron heartbeat is known. It never disables WP-Cron without that proof.', 'Check', $context ),
                ]
            ),
            self::group(
                'site_finish',
                '5. Site & Brand',
                'Finish the basic HWS site identity and administration policy.',
                [
                    self::task( 'regenerate_favicon_ico', 'Generate Site Icon and favicon.ico', 'setup_action', 'Creates the current HWS letter icon and verifies both the WordPress Site Icon and physical ICO.', 'Generate', $context ),
                    self::task( 'enable_recommended_snippets', 'Enable recommended HWS features', 'feature_toggle', 'Enables the maintained Going Live feature set.', 'Enable', $context ),
                    self::task( 'apply_ui_cleanup', 'Apply recommended admin cleanup', 'feature_toggle', 'Enables the recommended HWS WordPress-admin cleanup policy.', 'Apply', $context ),
                ]
            ),
            self::group(
                'performance',
                '6. LiteSpeed & Performance',
                'Provision LiteSpeed, apply the profile selected by this Quick Start, and verify cache behavior.',
                [
                    self::task( 'install_litespeed', 'Install and activate LiteSpeed Cache', 'setup_action', 'Installs LiteSpeed Cache from WordPress.org when missing and activates it.', 'Install', $context ),
                    self::task( 'apply_litespeed_profile', 'Apply LiteSpeed profile', 'config_mutation', 'Applies the HWS LiteSpeed profile attached to this Quick Start profile.', 'Apply', $context ),
                    self::task( 'verify_litespeed', 'Verify LiteSpeed and Redis', 'status_check', 'Verifies saved settings, cache integration, cache headers, and Redis availability without assuming Redis is usable.', 'Verify', $context ),
                ]
            ),
            self::group(
                'security_mail',
                '7. Security & Mail',
                'Apply the WordPress-level Wordfence policy and verify mail configuration without storing credentials in source code.',
                [
                    self::task( 'configure_wordfence', 'Configure Wordfence baseline', 'config_mutation', 'Installs or activates Wordfence, sets safe alert policy, and uses an environment-provided fleet license only when one is configured.', 'Configure', $context ),
                    self::task( 'audit_smtp', 'Check SMTP configuration', 'status_check', 'Checks WP Mail SMTP, sender identity, mailer selection, and authentication presence without exposing secrets.', 'Check', $context ),
                    self::task(
                        'test_smtp',
                        'Send SMTP verification email',
                        'status_check',
                        'Runs the guarded SMTP2GO authentication and delivery test. This task is intentionally individual because it sends mail.',
                        'Send Test',
                        $context,
                        [
                            'batch_enabled' => false,
                            'required_inputs' => [
                                [
                                    'id'          => 'smtp_test_recipient',
                                    'label'       => 'Test recipient',
                                    'type'        => 'email',
                                    'required'    => true,
                                    'value'       => self::default_email(),
                                    'description' => 'No message is sent until this individual action is clicked.',
                                ],
                            ],
                        ]
                    ),
                ]
            ),
            self::group(
                'final_verification',
                '8. Final Verification',
                'Purge generated caches and verify the public site after all preceding work.',
                [
                    self::task( 'purge_caches', 'Purge WordPress and LiteSpeed caches', 'setup_action', 'Purges supported application caches after configuration changes.', 'Purge', $context ),
                    self::task( 'verify_live_site', 'Verify homepage and inner page', 'status_check', 'Requests the public homepage and one published inner page and reports the HTTP results.', 'Verify', $context ),
                    self::task( 'final_report', 'Build before / after report', 'status_check', 'Summarizes the verified option before-state, task state, remaining review items, and live verification.', 'Build Report', $context ),
                ]
            ),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function audit_steps(): array {
        $context = [ 'dry_run' => true, 'litespeed_profile' => 'safe_baseline' ];

        return [
            self::group( 'preflight', 'Site Readiness', 'Read-only WordPress launch checks.', [
                self::task( 'audit_wordpress_runtime', 'Check WordPress, PHP & CloudLinux visibility', 'status_check', 'Checks runtime, extensions, cache extensions, PHP limits, and the WordPress-visible boundary for LVE quotas.', 'Check', $context ),
                self::task( 'audit_site_identity', 'Check site identity', 'status_check', 'Checks core site identity settings.', 'Check', $context ),
                self::task( 'audit_permalink', 'Check inner-page routing', 'status_check', 'Checks permalink configuration and rewrite rules.', 'Check', $context ),
                self::task( 'audit_updates', 'Check available updates', 'status_check', 'Reports pending WordPress, plugin, theme, HWS, and Core updates.', 'Check', $context ),
                self::task( 'verify_plugin_stack', 'Check plugin stack', 'status_check', 'Reports plugin-policy differences.', 'Check', $context ),
                self::task( 'audit_wordpress_policy', 'Check WordPress launch policy', 'status_check', 'Checks memory, debug, discussion, cron, and automatic updates.', 'Check', $context ),
                self::task( 'audit_litespeed', 'Check LiteSpeed profile', 'status_check', 'Compares current LiteSpeed settings with the safe baseline.', 'Check', $context ),
                self::task( 'audit_wordfence', 'Check Wordfence', 'status_check', 'Checks activation and WordPress-visible configuration.', 'Check', $context ),
                self::task( 'audit_smtp', 'Check SMTP', 'status_check', 'Checks WordPress mail configuration without sending a message.', 'Check', $context ),
                self::task( 'verify_live_site', 'Check public pages', 'status_check', 'Requests the homepage and an inner page.', 'Check', $context ),
            ] ),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $subtasks
     * @return array<string,mixed>
     */
    private static function group( string $id, string $label, string $description, array $subtasks ): array {
        return [
            'id'          => $id,
            'label'       => $label,
            'type'        => 'setup_action',
            'description' => $description,
            'subtasks'    => $subtasks,
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    private static function task( string $id, string $label, string $type, string $description, string $action_label, array $context, array $overrides = [] ): array {
        return array_merge(
            [
                'id'           => $id,
                'label'        => $label,
                'type'         => $type,
                'description'  => $description,
                'action_label' => $action_label,
                'callback'     => [ QuickStartTaskRunner::class, 'run' ],
                'context'      => array_merge( $context, [ 'quick_start_task' => $id ] ),
            ],
            $overrides
        );
    }

    private static function default_email(): string {
        if ( function_exists( 'wp_get_current_user' ) ) {
            $user = wp_get_current_user();
            if ( is_object( $user ) && function_exists( 'is_email' ) && is_email( (string) ( $user->user_email ?? '' ) ) ) {
                return (string) $user->user_email;
            }
        }

        $email = function_exists( 'get_option' ) ? (string) get_option( 'admin_email', '' ) : '';
        return function_exists( 'sanitize_email' ) ? (string) sanitize_email( $email ) : $email;
    }
}
