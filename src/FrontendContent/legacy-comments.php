<?php namespace hws_base_tools;

/**
 * Comments Management System
 *
 * Features:
 * - Efficient database queries (no get_posts -1)
 * - Batch processing with live AJAX reporting
 * - Auto-disable comments on first plugin activation
 * - Clean modern UI
 *
 * @since 8.9.5.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Option keys for tracking first-run and settings
 */
class Comments_Config {
    const OPT_FIRST_RUN      = 'hws_comments_first_run_complete';
    const OPT_BATCH_SIZE     = 50;  // Posts per AJAX batch
}

/**
 * Initialize comments management
 */
function enable_comments_management() {
    // Add admin menu styling (strikethrough on Comments menu)
    add_action( 'admin_head', __NAMESPACE__ . '\\comments_admin_css' );

    // Register AJAX handlers
    add_action( 'wp_ajax_hws_base_tools_toggle_wordpress_comments_batch', __NAMESPACE__ . '\\toggle_wordpress_comments_batch' );
    add_action( 'wp_ajax_hws_base_tools_toggle_wordpress_pingbacks_batch', __NAMESPACE__ . '\\toggle_wordpress_pingbacks_batch' );
    add_action( 'wp_ajax_hws_base_tools_get_comments_stats', __NAMESPACE__ . '\\ajax_get_comments_stats' );
    add_action( 'wp_ajax_hws_base_tools_delete_comments_batch', __NAMESPACE__ . '\\ajax_delete_comments_batch' );

    // First-run: auto-disable comments and pingbacks
    if ( ! get_option( Comments_Config::OPT_FIRST_RUN ) ) {
        hws_comments_first_run_setup();
    }
}

/**
 * First-run setup: disable comments and pingbacks by default
 * This runs ONCE on first plugin activation to close all existing posts too
 */
function hws_comments_first_run_setup() {
    global $wpdb;

    // Disable for future posts
    update_option( 'default_comment_status', 'closed' );
    update_option( 'default_ping_status', 'closed' );

    // Require registration for comments
    update_option( 'comment_registration', 1 );

    // Close comments and pingbacks on ALL existing posts/pages
    $wpdb->query( "
        UPDATE {$wpdb->posts}
        SET comment_status = 'closed', ping_status = 'closed'
        WHERE post_status = 'publish'
        AND post_type IN ('post', 'page')
    " );

    // Clear post caches
    wp_cache_flush();

    // Mark first run complete
    update_option( Comments_Config::OPT_FIRST_RUN, true );

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( '[HWS Comments] First run setup complete - all comments/pingbacks disabled' );
    }
}

/**
 * CSS to style admin Comments menu
 */
function comments_admin_css() {
    ?>
    <style>
        /* Strikethrough Comments menu in red when management is enabled */
        #menu-comments .wp-menu-name {
            color: #d63638 !important;
            text-decoration: line-through !important;
        }
    </style>
    <?php
}

/**
 * Get comment/pingback statistics efficiently using SQL
 */
function get_comments_stats() {
    global $wpdb;

    // Get stats with a single efficient query
    $stats = $wpdb->get_row( "
        SELECT
            COUNT(*) as total_posts,
            SUM( CASE WHEN comment_status = 'open' THEN 1 ELSE 0 END ) as comments_open,
            SUM( CASE WHEN comment_status = 'closed' THEN 1 ELSE 0 END ) as comments_closed,
            SUM( CASE WHEN ping_status = 'open' THEN 1 ELSE 0 END ) as pings_open,
            SUM( CASE WHEN ping_status = 'closed' THEN 1 ELSE 0 END ) as pings_closed
        FROM {$wpdb->posts}
        WHERE post_status = 'publish'
        AND post_type IN ('post', 'page')
    " );

    // Get comment counts
    $comment_counts = wp_count_comments();

    return [
        'total_posts'       => (int) $stats->total_posts,
        'comments_open'     => (int) $stats->comments_open,
        'comments_closed'   => (int) $stats->comments_closed,
        'pings_open'        => (int) $stats->pings_open,
        'pings_closed'      => (int) $stats->pings_closed,
        'total_comments'    => (int) $comment_counts->total_comments,
        'pending_comments'  => (int) $comment_counts->moderated,
        'spam_comments'     => (int) $comment_counts->spam,
        'future_comments'   => get_option( 'default_comment_status', 'open' ),
        'future_pings'      => get_option( 'default_ping_status', 'open' ),
        'require_register'  => get_option( 'comment_registration', 0 ),
    ];
}

/**
 * AJAX: Get current stats
 */
function ajax_get_comments_stats() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    hws_require_ajax_nonce_or_error();

    wp_send_json_success( get_comments_stats() );
}

/**
 * AJAX: Batch toggle comments on/off
 */
function toggle_wordpress_comments_batch() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized' ] );
    }

    hws_require_ajax_nonce_or_error();

    global $wpdb;

    $action     = isset( $_POST['state'] ) ? sanitize_text_field( $_POST['state'] ) : 'disable';
    $offset     = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
    $batch_size = isset( $_POST['batch_size'] ) ? absint( $_POST['batch_size'] ) : Comments_Config::OPT_BATCH_SIZE;

    $new_status = ( $action === 'enable' ) ? 'open' : 'closed';
    $old_status = ( $action === 'enable' ) ? 'closed' : 'open';

    // Get total count of posts needing update
    $total = $wpdb->get_var( $wpdb->prepare( "
        SELECT COUNT(*)
        FROM {$wpdb->posts}
        WHERE post_status = 'publish'
        AND post_type IN ('post', 'page')
        AND comment_status = %s
    ", $old_status ) );

    // Get batch of post IDs
    $post_ids = $wpdb->get_col( $wpdb->prepare( "
        SELECT ID
        FROM {$wpdb->posts}
        WHERE post_status = 'publish'
        AND post_type IN ('post', 'page')
        AND comment_status = %s
        LIMIT %d
    ", $old_status, $batch_size ) );

    $processed = 0;

    if ( ! empty( $post_ids ) ) {
        // Batch update using single query
        $ids_string = implode( ',', array_map( 'intval', $post_ids ) );
        $wpdb->query( $wpdb->prepare( "
            UPDATE {$wpdb->posts}
            SET comment_status = %s
            WHERE ID IN ({$ids_string})
        ", $new_status ) );

        $processed = count( $post_ids );

        // Clear post caches
        foreach ( $post_ids as $id ) {
            clean_post_cache( $id );
        }
    }

    // Also update future posts setting
    update_option( 'default_comment_status', $new_status );

    // Calculate remaining
    $remaining = max( 0, $total - $processed );

    wp_send_json_success( [
        'processed'    => $processed,
        'remaining'    => $remaining,
        'total'        => $total,
        'next_offset'  => $offset + $processed,
        'complete'     => $remaining === 0,
        'new_status'   => $new_status,
        'message'      => $processed > 0
            ? "Updated {$processed} posts. {$remaining} remaining..."
            : "All posts already have comments {$new_status}.",
    ] );
}

/**
 * AJAX: Batch toggle pingbacks on/off
 */
function toggle_wordpress_pingbacks_batch() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized' ] );
    }

    hws_require_ajax_nonce_or_error();

    global $wpdb;

    $action     = isset( $_POST['state'] ) ? sanitize_text_field( $_POST['state'] ) : 'disable';
    $offset     = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
    $batch_size = isset( $_POST['batch_size'] ) ? absint( $_POST['batch_size'] ) : Comments_Config::OPT_BATCH_SIZE;

    $new_status = ( $action === 'enable' ) ? 'open' : 'closed';
    $old_status = ( $action === 'enable' ) ? 'closed' : 'open';

    // Get total count
    $total = $wpdb->get_var( $wpdb->prepare( "
        SELECT COUNT(*)
        FROM {$wpdb->posts}
        WHERE post_status = 'publish'
        AND post_type IN ('post', 'page')
        AND ping_status = %s
    ", $old_status ) );

    // Get batch of post IDs
    $post_ids = $wpdb->get_col( $wpdb->prepare( "
        SELECT ID
        FROM {$wpdb->posts}
        WHERE post_status = 'publish'
        AND post_type IN ('post', 'page')
        AND ping_status = %s
        LIMIT %d
    ", $old_status, $batch_size ) );

    $processed = 0;

    if ( ! empty( $post_ids ) ) {
        $ids_string = implode( ',', array_map( 'intval', $post_ids ) );
        $wpdb->query( $wpdb->prepare( "
            UPDATE {$wpdb->posts}
            SET ping_status = %s
            WHERE ID IN ({$ids_string})
        ", $new_status ) );

        $processed = count( $post_ids );

        foreach ( $post_ids as $id ) {
            clean_post_cache( $id );
        }
    }

    // Update future posts setting
    update_option( 'default_ping_status', $new_status );

    $remaining = max( 0, $total - $processed );

    wp_send_json_success( [
        'processed'    => $processed,
        'remaining'    => $remaining,
        'total'        => $total,
        'next_offset'  => $offset + $processed,
        'complete'     => $remaining === 0,
        'new_status'   => $new_status,
        'message'      => $processed > 0
            ? "Updated {$processed} posts. {$remaining} remaining..."
            : "All posts already have pingbacks {$new_status}.",
    ] );
}

/**
 * Check if users must be registered to comment
 */
function check_users_must_be_registered_to_comment() {
    return (bool) get_option( 'comment_registration', 0 );
}

/**
 * AJAX: Batch delete comments (spam, pending, or all)
 */
function ajax_delete_comments_batch() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized' ] );
    }

    hws_require_ajax_nonce_or_error();

    global $wpdb;

    $type       = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : 'spam';
    $batch_size = isset( $_POST['batch_size'] ) ? absint( $_POST['batch_size'] ) : 50;

    // Map type to comment status
    $status_map = [
        'spam'    => 'spam',
        'pending' => 'hold',
        'trash'   => 'trash',
        'all'     => 'all',
    ];

    $status = isset( $status_map[ $type ] ) ? $status_map[ $type ] : 'spam';

    // Get total count
    if ( $status === 'all' ) {
        $total = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments}" );
        $comment_ids = $wpdb->get_col( $wpdb->prepare( "
            SELECT comment_ID FROM {$wpdb->comments} LIMIT %d
        ", $batch_size ) );
    } else {
        $total = $wpdb->get_var( $wpdb->prepare( "
            SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = %s
        ", $status ) );
        $comment_ids = $wpdb->get_col( $wpdb->prepare( "
            SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = %s LIMIT %d
        ", $status, $batch_size ) );
    }

    $processed = 0;

    if ( ! empty( $comment_ids ) ) {
        foreach ( $comment_ids as $comment_id ) {
            // Use wp_delete_comment for proper cleanup (meta, cache, etc.)
            if ( wp_delete_comment( $comment_id, true ) ) {
                $processed++;
            }
        }
    }

    $remaining = max( 0, $total - $processed );

    wp_send_json_success( [
        'processed'  => $processed,
        'remaining'  => $remaining,
        'total'      => $total,
        'complete'   => $remaining === 0,
        'type'       => $type,
        'message'    => $processed > 0
            ? "Deleted {$processed} {$type} comments. {$remaining} remaining..."
            : "No {$type} comments to delete.",
    ] );
}

/**
 * Display the Comments Dashboard
 */
function display_settings_comments_dashboard() {
    $stats = get_comments_stats();
    ?>
    <style>
        .hws-comments-dashboard {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .hws-comments-dashboard h2 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .hws-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .hws-stat-card {
            background: #f6f7f7;
            border-radius: 4px;
            padding: 15px;
            text-align: center;
        }
        .hws-stat-card .stat-value {
            font-size: 24px;
            font-weight: 600;
            color: #1d2327;
        }
        .hws-stat-card .stat-label {
            font-size: 12px;
            color: #646970;
            text-transform: uppercase;
        }
        .hws-stat-card.status-good { border-left: 4px solid #00a32a; }
        .hws-stat-card.status-bad { border-left: 4px solid #d63638; }
        .hws-stat-card.status-neutral { border-left: 4px solid #dba617; }

        .hws-action-group {
            margin-bottom: 20px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        .hws-action-group h4 {
            margin: 0 0 10px 0;
        }
        .hws-action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        .hws-progress-log {
            width: 100%;
            height: 80px;
            font-family: monospace;
            font-size: 12px;
            background: #1d2327;
            color: #50c878;
            padding: 10px;
            border-radius: 4px;
            resize: none;
            border: none;
        }
        .hws-progress-log:disabled {
            opacity: 1;
        }
    </style>

    <div class="hws-comments-dashboard panel">
        <h2 class="panel-title">💬 Comments Management</h2>

        <!-- Stats Grid -->
        <div class="hws-stats-grid">
            <div class="hws-stat-card <?php echo $stats['comments_open'] > 0 ? 'status-bad' : 'status-good'; ?>" data-comments-stat-card="comments_open">
                <div class="stat-value" data-comments-stat="comments_open"><?php echo $stats['comments_open']; ?> / <?php echo $stats['total_posts']; ?></div>
                <div class="stat-label">Posts with Comments Open</div>
            </div>
            <div class="hws-stat-card <?php echo $stats['pings_open'] > 0 ? 'status-bad' : 'status-good'; ?>" data-comments-stat-card="pings_open">
                <div class="stat-value" data-comments-stat="pings_open"><?php echo $stats['pings_open']; ?> / <?php echo $stats['total_posts']; ?></div>
                <div class="stat-label">Posts with Pingbacks Open</div>
            </div>
            <div class="hws-stat-card status-neutral" data-comments-stat-card="total_comments">
                <div class="stat-value" data-comments-stat="total_comments"><?php echo $stats['total_comments']; ?></div>
                <div class="stat-label">Total Comments</div>
            </div>
            <div class="hws-stat-card <?php echo $stats['pending_comments'] > 0 ? 'status-bad' : 'status-good'; ?>" data-comments-stat-card="pending_comments">
                <div class="stat-value" data-comments-stat="pending_comments"><?php echo $stats['pending_comments']; ?></div>
                <div class="stat-label">Pending Moderation</div>
            </div>
            <div class="hws-stat-card <?php echo $stats['spam_comments'] > 0 ? 'status-bad' : 'status-good'; ?>" data-comments-stat-card="spam_comments">
                <div class="stat-value" data-comments-stat="spam_comments"><?php echo $stats['spam_comments']; ?></div>
                <div class="stat-label">Spam Comments</div>
            </div>
            <div class="hws-stat-card <?php echo $stats['future_comments'] === 'open' ? 'status-bad' : 'status-good'; ?>" data-comments-stat-card="future_comments">
                <div class="stat-value" data-comments-stat="future_comments"><?php echo strtoupper( $stats['future_comments'] ); ?></div>
                <div class="stat-label">New Posts Default</div>
            </div>
        </div>

        <!-- Actions -->
        <div class="hws-action-group">
            <h4>📝 Comments Control</h4>
            <div class="hws-action-buttons">
                <button type="button" class="button button-primary" id="hws-disable-comments"
                        data-action="disable" data-type="comments">
                    Disable All Comments
                </button>
                <button type="button" class="button" id="hws-enable-comments"
                        data-action="enable" data-type="comments">
                    Enable All Comments
                </button>
            </div>
            <textarea id="hws-comments-log" class="hws-progress-log" disabled placeholder="Progress will appear here..."></textarea>
        </div>

        <div class="hws-action-group">
            <h4>🔔 Pingbacks Control</h4>
            <div class="hws-action-buttons">
                <button type="button" class="button button-primary" id="hws-disable-pingbacks"
                        data-action="disable" data-type="pingbacks">
                    Disable All Pingbacks
                </button>
                <button type="button" class="button" id="hws-enable-pingbacks"
                        data-action="enable" data-type="pingbacks">
                    Enable All Pingbacks
                </button>
            </div>
            <textarea id="hws-pingbacks-log" class="hws-progress-log" disabled placeholder="Progress will appear here..."></textarea>
        </div>

        <!-- Quick Links -->
        <div style="margin-top: 15px;">
            <a href="<?php echo esc_url( admin_url( 'options-discussion.php' ) ); ?>" class="button" target="_blank">
                ⚙️ Discussion Settings
            </a>
            <a href="<?php echo esc_url( admin_url( 'edit-comments.php' ) ); ?>" class="button" target="_blank">
                💬 Manage Comments
            </a>
        </div>

        <!-- Delete Comments Section -->
        <div id="hws-comments-delete-section" class="hws-action-group" style="margin-top: 20px; border-left: 4px solid #d63638;<?php echo ( $stats['spam_comments'] > 0 || $stats['pending_comments'] > 0 || $stats['total_comments'] > 0 ) ? '' : ' display:none;'; ?>">
            <h4>🗑️ Delete Comments</h4>
            <p style="color: #646970; margin-bottom: 10px;">
                <strong>Warning:</strong> This will permanently delete comments. This cannot be undone.
            </p>
            <div id="hws-comments-delete-buttons" class="hws-action-buttons">
                <?php if ( $stats['spam_comments'] > 0 ) : ?>
                <button type="button" class="button" id="hws-delete-spam"
                        data-type="spam" data-count="<?php echo $stats['spam_comments']; ?>">
                    Delete Spam (<?php echo $stats['spam_comments']; ?>)
                </button>
                <?php endif; ?>
                <?php if ( $stats['pending_comments'] > 0 ) : ?>
                <button type="button" class="button" id="hws-delete-pending"
                        data-type="pending" data-count="<?php echo $stats['pending_comments']; ?>">
                    Delete Pending (<?php echo $stats['pending_comments']; ?>)
                </button>
                <?php endif; ?>
                <?php if ( $stats['total_comments'] > 0 ) : ?>
                <button type="button" class="button button-link-delete" id="hws-delete-all"
                        data-type="all" data-count="<?php echo $stats['total_comments']; ?>"
                        style="color: #d63638;">
                    Delete ALL Comments (<?php echo $stats['total_comments']; ?>)
                </button>
                <?php endif; ?>
            </div>
            <textarea id="hws-delete-log" class="hws-progress-log" disabled placeholder="Deletion progress will appear here..."></textarea>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        var commentsNonce = typeof window.hwsNonce !== 'undefined'
            ? window.hwsNonce
            : '<?php echo esc_js( wp_create_nonce( HWS_AJAX_NONCE ) ); ?>';

        function setCommentsCardState(key, isBad) {
            var $card = $('[data-comments-stat-card="' + key + '"]');
            if (!$card.length) {
                return;
            }

            if (key === 'total_comments') {
                return;
            }

            $card.toggleClass('status-bad', !!isBad);
            $card.toggleClass('status-good', !isBad);
        }

        function renderDeleteButtons(stats) {
            var buttons = [];

            if (stats.spam_comments > 0) {
                buttons.push(
                    '<button type="button" class="button" id="hws-delete-spam" data-type="spam" data-count="' + stats.spam_comments + '">' +
                    'Delete Spam (' + stats.spam_comments + ')' +
                    '</button>'
                );
            }

            if (stats.pending_comments > 0) {
                buttons.push(
                    '<button type="button" class="button" id="hws-delete-pending" data-type="pending" data-count="' + stats.pending_comments + '">' +
                    'Delete Pending (' + stats.pending_comments + ')' +
                    '</button>'
                );
            }

            if (stats.total_comments > 0) {
                buttons.push(
                    '<button type="button" class="button button-link-delete" id="hws-delete-all" data-type="all" data-count="' + stats.total_comments + '" style="color: #d63638;">' +
                    'Delete ALL Comments (' + stats.total_comments + ')' +
                    '</button>'
                );
            }

            return buttons.join('');
        }

        function applyCommentsStats(stats) {
            $('[data-comments-stat="comments_open"]').text(stats.comments_open + ' / ' + stats.total_posts);
            $('[data-comments-stat="pings_open"]').text(stats.pings_open + ' / ' + stats.total_posts);
            $('[data-comments-stat="total_comments"]').text(stats.total_comments);
            $('[data-comments-stat="pending_comments"]').text(stats.pending_comments);
            $('[data-comments-stat="spam_comments"]').text(stats.spam_comments);
            $('[data-comments-stat="future_comments"]').text(String(stats.future_comments || '').toUpperCase());

            setCommentsCardState('comments_open', stats.comments_open > 0);
            setCommentsCardState('pings_open', stats.pings_open > 0);
            setCommentsCardState('pending_comments', stats.pending_comments > 0);
            setCommentsCardState('spam_comments', stats.spam_comments > 0);
            setCommentsCardState('future_comments', stats.future_comments === 'open');

            var hasDeleteButtons = stats.spam_comments > 0 || stats.pending_comments > 0 || stats.total_comments > 0;
            $('#hws-comments-delete-section').toggle(hasDeleteButtons);
            $('#hws-comments-delete-buttons').html(renderDeleteButtons(stats));
        }

        function refreshCommentsState() {
            return $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hws_base_tools_get_comments_stats',
                    nonce: commentsNonce
                }
            }).done(function(response) {
                if (response && response.success && response.data) {
                    applyCommentsStats(response.data);
                }
            });
        }

        // Batch processing function
        function processBatch(type, action, $log) {
            var ajaxAction = 'hws_base_tools_toggle_wordpress_' + type + '_batch';

            function runBatch() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: ajaxAction,
                        state: action,
                        batch_size: 50,
                        nonce: commentsNonce
                    },
                    success: function(response) {
                        if (!response.success) {
                            $log.val($log.val() + '\n❌ Error: ' + (response.data.message || 'Unknown error'));
                            return;
                        }

                        var data = response.data;
                        var timestamp = new Date().toLocaleTimeString();
                        $log.val($log.val() + '\n[' + timestamp + '] ' + data.message);
                        $log.scrollTop($log[0].scrollHeight);

                        if (!data.complete && data.remaining > 0) {
                            // Continue processing
                            setTimeout(runBatch, 100);
                        } else {
                            $log.val($log.val() + '\n✅ Complete! All ' + type + ' ' + action + 'd.');
                            refreshCommentsState();
                        }
                    },
                    error: function(xhr, status, error) {
                        $log.val($log.val() + '\n❌ AJAX Error: ' + error);
                    }
                });
            }

            // Start processing
            $log.val('Starting to ' + action + ' ' + type + '...');
            runBatch();
        }

        // Delete comments batch function
        function deleteCommentsBatch(type, $log) {
            function runDelete() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hws_base_tools_delete_comments_batch',
                        type: type,
                        batch_size: 50,
                        nonce: commentsNonce
                    },
                    success: function(response) {
                        if (!response.success) {
                            $log.val($log.val() + '\n❌ Error: ' + (response.data.message || 'Unknown error'));
                            return;
                        }

                        var data = response.data;
                        var timestamp = new Date().toLocaleTimeString();
                        $log.val($log.val() + '\n[' + timestamp + '] ' + data.message);
                        $log.scrollTop($log[0].scrollHeight);

                        if (!data.complete && data.remaining > 0) {
                            setTimeout(runDelete, 100);
                        } else {
                            $log.val($log.val() + '\n✅ Complete! All ' + type + ' comments deleted.');
                            refreshCommentsState();
                        }
                    },
                    error: function(xhr, status, error) {
                        $log.val($log.val() + '\n❌ AJAX Error: ' + error);
                    }
                });
            }

            $log.val('Starting to delete ' + type + ' comments...');
            runDelete();
        }

        // Button handlers
        $('#hws-disable-comments, #hws-enable-comments').on('click', function() {
            var action = $(this).data('action');
            var $log = $('#hws-comments-log');
            processBatch('comments', action, $log);
        });

        $('#hws-disable-pingbacks, #hws-enable-pingbacks').on('click', function() {
            var action = $(this).data('action');
            var $log = $('#hws-pingbacks-log');
            processBatch('pingbacks', action, $log);
        });

        // Delete handlers
        $('#hws-delete-spam, #hws-delete-pending').on('click', function() {
            var type = $(this).data('type');
            var count = $(this).data('count');
            if (confirm('Are you sure you want to delete ' + count + ' ' + type + ' comments? This cannot be undone.')) {
                deleteCommentsBatch(type, $('#hws-delete-log'));
            }
        });

        $('#hws-delete-all').on('click', function() {
            var count = $(this).data('count');
            if (confirm('⚠️ WARNING: This will permanently delete ALL ' + count + ' comments!\n\nAre you absolutely sure?')) {
                if (confirm('This is your final warning. Delete all comments?')) {
                    deleteCommentsBatch('all', $('#hws-delete-log'));
                }
            }
        });
    });
    </script>
    <?php
}

/**
 * Legacy function for backward compatibility
 * Used by perform_comments_system_check() calls elsewhere
 */
function perform_comments_system_check() {
    $stats = get_comments_stats();

    $all_comments_disabled = ( $stats['comments_open'] === 0 && $stats['future_comments'] === 'closed' );
    $all_pings_disabled = ( $stats['pings_open'] === 0 && $stats['future_pings'] === 'closed' );

    $report = '<div style="font-family: monospace; font-size: 13px;">';
    $report .= '<strong>Comments:</strong> ';
    $report .= $all_comments_disabled
        ? '<span style="color: green;">✅ All Disabled</span>'
        : '<span style="color: red;">⚠️ ' . $stats['comments_open'] . ' posts have comments open</span>';
    $report .= '<br>';
    $report .= '<strong>Pingbacks:</strong> ';
    $report .= $all_pings_disabled
        ? '<span style="color: green;">✅ All Disabled</span>'
        : '<span style="color: red;">⚠️ ' . $stats['pings_open'] . ' posts have pingbacks open</span>';
    $report .= '</div>';

    return [
        'status'    => $all_comments_disabled && $all_pings_disabled,
        'raw_value' => $report,
    ];
}
