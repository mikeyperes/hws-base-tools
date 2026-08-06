<?php

namespace HWS\BaseTools\ReviewCenter;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistAjaxController;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistConfig;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistRenderer;
use HWS\BaseTools\PluginRuntime\PluginMetadata;

final class ReviewCenterModule implements ModuleInterface {
    private static bool $registered = false;
    private static ?GettingStartedChecklistConfig $config = null;

    public function register(): void {
        if ( self::$registered ) {
            return;
        }
        self::$registered = true;
        add_action( 'init', [ self::class, 'register_ajax' ], 30 );
    }

    public static function register_ajax(): void {
        ( new GettingStartedChecklistAjaxController( self::config() ) )->register();
    }

    public static function render(): void {
        ( new GettingStartedChecklistRenderer( self::config() ) )->render();
    }

    public static function config(): GettingStartedChecklistConfig {
        if ( self::$config instanceof GettingStartedChecklistConfig ) {
            return self::$config;
        }

        self::$config = new GettingStartedChecklistConfig( [
            'root_id'              => 'hws-review-center',
            'title'                => 'Quick Review Center',
            'description'          => 'Run a scan, review its exact targets, then type the action-specific confirmation. Cleanup authorizations expire after 15 minutes and destructive actions never run in the batch.',
            'capability'           => PluginMetadata::ADMIN_CAPABILITY,
            'nonce_action'         => 'hws_base_tools_review_center',
            'nonce_field'          => 'nonce',
            'run_action'           => 'hws_review_center_run_item',
            'status_action'        => 'hws_review_center_status',
            'reset_action'         => 'hws_review_center_reset',
            'state_option'         => 'hws_review_center_state',
            'persistence_enabled'  => true,
            'template_id'          => 'review',
            'show_template_picker' => false,
            'show_search'          => true,
            'search_label'         => 'Search Review Center',
            'search_placeholder'   => 'Search comments, migration tools, cache plugins, themes, files...',
            'templates'            => [
                'review' => [
                    'label'       => 'Review',
                    'description' => 'Safe scans with explicitly individual cleanup actions.',
                    'steps'       => self::steps(),
                ],
            ],
        ] );

        return self::$config;
    }

    /** @return array<int,array<string,mixed>> */
    private static function steps(): array {
        return [
            self::group( 'comments', 'Comments', 'Review all approved, pending, spam, and trashed comments.', [
                self::task( 'scan_comments', 'Scan every comment status', 'status_check', 'Counts every WordPress comment, including the default sample comment.', 'Scan' ),
                self::destructive( 'delete_comments', 'Permanently delete reviewed comments', 'Permanently deletes only the exact comment IDs from your unexpired scan.', 'Delete Reviewed Comments' ),
            ] ),
            self::group( 'migration', 'Migration Plugins', 'Review tools that usually remain after a completed migration.', [
                self::task( 'scan_migration', 'Scan migration plugins', 'status_check', 'Finds known migration and staging plugins and shows whether each is active.', 'Scan' ),
                self::destructive( 'remove_migration', 'Deactivate and remove migration plugins', 'Removes only the known migration-plugin matches from the scan.', 'Remove Migration Tools' ),
            ] ),
            self::group( 'cache_plugins', 'Duplicate Caching', 'Review optimization plugins that overlap LiteSpeed Cache.', [
                self::task( 'scan_duplicate_caches', 'Scan overlapping cache plugins', 'status_check', 'Finds known page-cache and optimization plugins that may conflict with LiteSpeed.', 'Scan' ),
                self::destructive( 'remove_duplicate_caches', 'Remove overlapping cache plugins', 'Requires LiteSpeed to be active, then removes only matched cache plugins.', 'Remove Cache Conflicts' ),
            ] ),
            self::group( 'inactive_plugins', 'Inactive Plugins', 'Review installed code that WordPress is not running.', [
                self::task( 'scan_inactive_plugins', 'Scan inactive plugins', 'status_check', 'Lists inactive plugins; HWS Base Tools itself is always protected.', 'Scan' ),
                self::destructive( 'remove_inactive_plugins', 'Remove inactive plugins', 'Permanently removes only plugins that are still inactive at click time.', 'Remove Inactive Plugins' ),
            ] ),
            self::group( 'inactive_themes', 'Inactive Themes', 'Review unused themes while preserving the active child/parent and one WordPress fallback theme.', [
                self::task( 'scan_inactive_themes', 'Scan inactive themes', 'status_check', 'Shows exactly which themes are removable and which are protected.', 'Scan' ),
                self::destructive( 'remove_inactive_themes', 'Remove inactive themes', 'Deletes only the scanned themes that are not active, parent, configured default, or newest WordPress fallback.', 'Remove Inactive Themes' ),
            ] ),
            self::group( 'sample_content', 'Default Sample Content', 'Review WordPress starter posts and pages.', [
                self::task( 'scan_sample_content', 'Scan default sample content', 'status_check', 'Finds only untouched WordPress samples whose IDs, slugs, titles, bodies, status, and edit timestamps match the conservative fingerprint.', 'Scan' ),
                self::destructive( 'remove_sample_content', 'Trash reviewed sample content', 'Moves only the exact conservatively fingerprinted sample post/page matches to Trash.', 'Trash Sample Content' ),
            ] ),
            self::group( 'cleanup_tools', 'Temporary Cleanup Tools', 'Review utilities that are often needed only during launch.', [
                self::task( 'scan_cleanup_tools', 'Scan temporary cleanup plugins', 'status_check', 'Finds known database, thumbnail, search/replace, and media-cleanup utilities.', 'Scan' ),
                self::destructive( 'remove_cleanup_tools', 'Remove temporary cleanup plugins', 'Deactivates and removes only the matched temporary tools.', 'Remove Cleanup Tools' ),
            ] ),
            self::group( 'files', 'Logs & Backup Debris', 'Review known site log files and old backup files while retaining the newest backup from every source.', [
                self::task( 'scan_cleanup_files', 'Scan log and eligible backup files', 'status_check', 'Lists file identity and writability, excluding recent backups and retaining the newest backup per source.', 'Scan' ),
                self::destructive( 'remove_cleanup_files', 'Delete reviewed log and eligible backup files', 'Deletes only the exact unchanged files from your unexpired scan.', 'Delete Reviewed Files' ),
            ] ),
            self::group( 'option_restore', 'Quick Start Option Restore', 'Restore only the approved WordPress options from a verified Quick Start before-state snapshot.', [
                self::task( 'scan_snapshot', 'Check verified before-state snapshot', 'status_check', 'Reports the latest verified, site-scoped Quick Start option snapshot and bounded history.', 'Check' ),
                self::destructive( 'restore_snapshot', 'Restore reviewed WordPress option before-state', 'Restores and verifies only the approved option keys. It does not restore plugin, theme, WordPress, Core, file, or wp-config state.', 'Restore Reviewed Options' ),
            ] ),
        ];
    }

    /** @param array<int,array<string,mixed>> $subtasks */
    private static function group( string $id, string $label, string $description, array $subtasks ): array {
        return [ 'id' => $id, 'label' => $label, 'type' => 'status_check', 'description' => $description, 'subtasks' => $subtasks ];
    }

    /** @return array<string,mixed> */
    private static function task( string $id, string $label, string $type, string $description, string $action_label ): array {
        return [
            'id'           => $id,
            'label'        => $label,
            'type'         => $type,
            'description'  => $description,
            'action_label' => $action_label,
            'callback'     => [ ReviewTaskRunner::class, 'run' ],
            'context'      => [ 'review_task' => $id ],
        ];
    }

    /** @return array<string,mixed> */
    private static function destructive( string $id, string $label, string $description, string $action_label ): array {
        $confirmation = ReviewTaskRunner::confirmation_text( $id );
        return array_merge(
            self::task( $id, $label, 'setup_action', $description, $action_label ),
            [
                'batch_enabled'  => false,
                'destructive'    => true,
                'required_inputs'=> [
                    [
                        'id'             => 'confirmation',
                        'label'          => 'Typed confirmation',
                        'type'           => 'confirmation',
                        'required'       => true,
                        'confirm_text'   => $confirmation,
                        'case_sensitive' => true,
                        'description'    => 'Type “' . $confirmation . '” exactly. A matching unexpired scan is also required.',
                    ],
                ],
            ]
        );
    }
}
