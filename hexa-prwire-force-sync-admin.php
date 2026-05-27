<?php
namespace hws_base_tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const HWS_HPR_FORCE_SYNC_TOKEN = '16dc68d8a5c6119213e61aac5662d7f5c182aed2f5d329e0c89de18fcf0abe59';
const HWS_HPR_SYNC_META = '_hws_hpr_force_sync_results';
const HWS_HPR_SYNC_LOG_OPTION = 'hws_hpr_force_sync_log';

add_action( 'add_meta_boxes', __NAMESPACE__ . '\\hws_hpr_register_force_sync_metabox' );
add_action( 'wp_ajax_hws_hpr_force_sync_publication', __NAMESPACE__ . '\\hws_hpr_ajax_force_sync_publication' );

function hws_hpr_force_sync_admin_enabled(): bool {
    return is_admin() && post_type_exists( 'publication' );
}

function hws_hpr_get_force_sync_token(): string {
    return HWS_HPR_FORCE_SYNC_TOKEN;
}

function hws_hpr_register_force_sync_metabox(): void {
    if ( ! hws_hpr_force_sync_admin_enabled() ) {
        return;
    }

    add_meta_box(
        'hws-hpr-force-sync',
        'Hexa PR Wire Force Sync',
        __NAMESPACE__ . '\\hws_hpr_render_force_sync_metabox',
        'post',
        'normal',
        'high'
    );
}

function hws_hpr_get_publications(): array {
    if ( ! post_type_exists( 'publication' ) ) {
        return [];
    }

    $posts = get_posts(
        [
            'post_type'      => 'publication',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => [ 'menu_order' => 'ASC', 'title' => 'ASC' ],
            'no_found_rows'  => true,
        ]
    );

    $publications = [];
    foreach ( $posts as $post ) {
        $prefix = trim( (string) get_post_meta( $post->ID, 'url_press_release_prefix', true ) );
        $domain = trim( (string) get_post_meta( $post->ID, 'url_nice', true ) );
        $url    = trim( (string) get_post_meta( $post->ID, 'url', true ) );

        if ( '' === $domain && '' !== $prefix ) {
            $domain = (string) wp_parse_url( $prefix, PHP_URL_HOST );
        }

        if ( '' === $domain && '' !== $url ) {
            $domain = (string) wp_parse_url( $url, PHP_URL_HOST );
        }

        $domain = preg_replace( '#^www\.#i', '', strtolower( $domain ) );

        if ( '' === $prefix && '' !== $domain ) {
            $prefix = 'https://' . $domain . '/press-release/';
        }

        if ( '' === $domain || '' === $prefix ) {
            continue;
        }

        $publications[] = [
            'id'     => (int) $post->ID,
            'title'  => get_the_title( $post ),
            'slug'   => $post->post_name,
            'domain' => $domain,
            'prefix' => trailingslashit( $prefix ),
        ];
    }

    return $publications;
}

function hws_hpr_get_recent_press_releases( int $limit = 25 ): array {
    return get_posts(
        [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                [
                    'key'     => 'link_output',
                    'compare' => 'EXISTS',
                ],
            ],
        ]
    );
}

function hws_hpr_build_publication_live_url( array $publication, \WP_Post $post ): string {
    return trailingslashit( $publication['prefix'] ) . $post->post_name . '/';
}

function hws_hpr_build_publication_endpoint( array $publication, \WP_Post $post ): string {
    return add_query_arg(
        [
            'key'         => hws_hpr_get_force_sync_token(),
            'slug'        => $post->post_name,
            'feed_action' => 'force',
        ],
        'https://' . $publication['domain'] . '/wp-json/hpr-distributor/v1/force-sync'
    );
}

function hws_hpr_check_live_publication_url( string $live_url, string $expected_title ): array {
    $response = wp_remote_get(
        $live_url,
        [
            'timeout'     => 30,
            'redirection' => 8,
            'sslverify'   => false,
            'headers'     => [
                'Accept'     => 'text/html',
                'User-Agent' => 'HexaPRWireForceSync/1.0',
            ],
        ]
    );

    if ( is_wp_error( $response ) ) {
        return [
            'ok'          => false,
            'status_code' => 0,
            'title_found' => false,
            'message'     => $response->get_error_message(),
        ];
    }

    $status_code = (int) wp_remote_retrieve_response_code( $response );
    $body        = (string) wp_remote_retrieve_body( $response );
    $body_text   = wp_strip_all_tags( $body );
    $title_found = '' !== $expected_title && false !== stripos( $body_text, $expected_title );

    return [
        'ok'          => 200 === $status_code && $title_found,
        'status_code' => $status_code,
        'title_found' => $title_found,
        'message'     => 200 === $status_code ? ( $title_found ? 'Live URL verified.' : 'Live URL loaded but title was not found.' ) : 'Live URL returned HTTP ' . $status_code . '.',
    ];
}

function hws_hpr_log_force_sync_result( array $entry ): void {
    $log = get_option( HWS_HPR_SYNC_LOG_OPTION, [] );
    $log = is_array( $log ) ? $log : [];

    array_unshift( $log, $entry );
    update_option( HWS_HPR_SYNC_LOG_OPTION, array_slice( $log, 0, 200 ), false );
}

function hws_hpr_store_post_sync_result( int $post_id, int $publication_id, array $result ): void {
    $stored = get_post_meta( $post_id, HWS_HPR_SYNC_META, true );
    $stored = is_array( $stored ) ? $stored : [];
    $stored[ $publication_id ] = $result;
    update_post_meta( $post_id, HWS_HPR_SYNC_META, $stored );
}

function hws_hpr_force_sync_publication( int $post_id, int $publication_id, string $mode = 'force' ): array {
    $post = get_post( $post_id );
    if ( ! $post || 'post' !== $post->post_type ) {
        return [ 'ok' => false, 'message' => 'Invalid press release post.' ];
    }

    $publication = null;
    foreach ( hws_hpr_get_publications() as $candidate ) {
        if ( (int) $candidate['id'] === $publication_id ) {
            $publication = $candidate;
            break;
        }
    }

    if ( ! $publication ) {
        return [ 'ok' => false, 'message' => 'Invalid publication.' ];
    }

    $mode        = 'check' === $mode ? 'check' : 'force';
    $live_url    = hws_hpr_build_publication_live_url( $publication, $post );
    $endpoint_ok = true;
    $endpoint    = null;
    $http_code   = null;
    $payload     = null;
    $error       = '';

    if ( 'force' === $mode ) {
        $endpoint = hws_hpr_build_publication_endpoint( $publication, $post );
        $response = wp_remote_get(
            $endpoint,
            [
                'timeout'     => 180,
                'redirection' => 5,
                'sslverify'   => false,
                'headers'     => [
                    'Accept'        => 'application/json',
                    'Cache-Control' => 'no-cache',
                    'User-Agent'    => 'HexaPRWireForceSync/1.0',
                ],
            ]
        );

        if ( is_wp_error( $response ) ) {
            $endpoint_ok = false;
            $error       = $response->get_error_message();
        } else {
            $http_code = (int) wp_remote_retrieve_response_code( $response );
            $payload   = json_decode( (string) wp_remote_retrieve_body( $response ), true );

            if ( 200 !== $http_code || ! is_array( $payload ) || empty( $payload['success'] ) ) {
                $endpoint_ok = false;
                $error       = is_array( $payload ) && ! empty( $payload['message'] ) ? (string) $payload['message'] : 'Distributor endpoint failed.';
            }
        }
    }

    $live_check = hws_hpr_check_live_publication_url( $live_url, get_the_title( $post ) );
    $summary    = [
        'time_gmt'       => current_time( 'mysql', true ),
        'mode'           => $mode,
        'post_id'        => $post_id,
        'post_title'     => get_the_title( $post ),
        'source_url'     => get_permalink( $post ),
        'publication_id' => $publication_id,
        'publication'    => $publication['title'],
        'domain'         => $publication['domain'],
        'live_url'       => $live_url,
        'endpoint_http'  => $http_code,
        'endpoint_ok'    => $endpoint_ok,
        'endpoint'       => $endpoint ? remove_query_arg( 'key', $endpoint ) : '',
        'matched'        => is_array( $payload ) ? (int) ( $payload['matched_feed_items'] ?? 0 ) : null,
        'new_count'      => is_array( $payload ) && isset( $payload['result']['new_live_urls'] ) ? count( (array) $payload['result']['new_live_urls'] ) : 0,
        'updated_count'  => is_array( $payload ) && isset( $payload['result']['updated_live_urls'] ) ? count( (array) $payload['result']['updated_live_urls'] ) : 0,
        'missing_count'  => is_array( $payload ) && isset( $payload['result']['missing_targets'] ) ? count( (array) $payload['result']['missing_targets'] ) : 0,
        'redirects_disabled' => is_array( $payload ) && isset( $payload['redirect_cleanup']['disabled'] ) ? (int) $payload['redirect_cleanup']['disabled'] : 0,
        'public_status'  => (int) $live_check['status_code'],
        'public_ok'      => (bool) $live_check['ok'],
        'title_found'    => (bool) $live_check['title_found'],
        'ok'             => $endpoint_ok && (bool) $live_check['ok'],
        'message'        => $endpoint_ok ? (string) $live_check['message'] : $error,
    ];

    hws_hpr_store_post_sync_result( $post_id, $publication_id, $summary );
    hws_hpr_log_force_sync_result( $summary );

    return $summary;
}

function hws_hpr_ajax_force_sync_publication(): void {
    $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, HWS_AJAX_NONCE ) ) {
        wp_send_json_error( [ 'message' => 'Invalid nonce.' ], 403 );
    }

    $post_id        = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
    $publication_id = isset( $_POST['publication_id'] ) ? (int) $_POST['publication_id'] : 0;
    $mode           = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'force';

    if ( ! current_user_can( 'edit_post', $post_id ) && ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Insufficient permissions.' ], 403 );
    }

    $result = hws_hpr_force_sync_publication( $post_id, $publication_id, $mode );
    if ( empty( $result['ok'] ) ) {
        wp_send_json_error( $result );
    }

    wp_send_json_success( $result );
}

function hws_hpr_get_result_status_label( ?array $result = null ): string {
    if ( empty( $result ) ) {
        return 'Not checked';
    }

    $status = ! empty( $result['ok'] ) ? 'OK' : 'Issue';
    return sprintf(
        '%s - %s GMT - HTTP %s - %s',
        $status,
        esc_html( (string) ( $result['time_gmt'] ?? '' ) ),
        esc_html( (string) ( $result['public_status'] ?? '' ) ),
        esc_html( (string) ( $result['message'] ?? '' ) )
    );
}

function hws_hpr_render_publication_rows( int $post_id ): void {
    $post         = get_post( $post_id );
    $publications = hws_hpr_get_publications();
    $stored       = get_post_meta( $post_id, HWS_HPR_SYNC_META, true );
    $stored       = is_array( $stored ) ? $stored : [];

    foreach ( $publications as $publication ) {
        $publication_id = (int) $publication['id'];
        $live_url       = $post ? hws_hpr_build_publication_live_url( $publication, $post ) : '';
        $result         = isset( $stored[ $publication_id ] ) && is_array( $stored[ $publication_id ] ) ? $stored[ $publication_id ] : null;
        ?>
        <tr data-hpr-row data-publication-id="<?php echo esc_attr( $publication_id ); ?>">
            <td><input type="checkbox" class="hws-hpr-publication-check" value="<?php echo esc_attr( $publication_id ); ?>" checked></td>
            <td><strong><?php echo esc_html( $publication['title'] ); ?></strong><br><code><?php echo esc_html( $publication['domain'] ); ?></code></td>
            <td><a href="<?php echo esc_url( $live_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $live_url ); ?></a></td>
            <td class="hws-hpr-row-status"><?php echo esc_html( hws_hpr_get_result_status_label( $result ) ); ?></td>
        </tr>
        <?php
    }
}

function hws_hpr_render_force_sync_metabox( \WP_Post $post ): void {
    if ( ! hws_hpr_force_sync_admin_enabled() ) {
        echo '<p>Hexa PR Wire force sync is not available on this site.</p>';
        return;
    }

    $nonce = wp_create_nonce( HWS_AJAX_NONCE );
    ?>
    <div class="hws-hpr-sync-panel" data-post-id="<?php echo esc_attr( $post->ID ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
        <p>Force selected publication sites to pull this press release through their Hexa PR Wire Distributor endpoint, then verify the public URL contains this post title.</p>
        <p>
            <button type="button" class="button button-primary hws-hpr-run" data-mode="force">Force selected links</button>
            <button type="button" class="button hws-hpr-run" data-mode="check">Check selected links only</button>
            <button type="button" class="button hws-hpr-select-all">Select all</button>
            <button type="button" class="button hws-hpr-select-none">Select none</button>
            <span class="hws-hpr-progress" style="margin-left:10px;"></span>
        </p>
        <table class="widefat striped hws-hpr-publication-table">
            <thead><tr><th style="width:40px;"></th><th style="width:220px;">Publication</th><th>Expected URL</th><th style="width:320px;">Status</th></tr></thead>
            <tbody><?php hws_hpr_render_publication_rows( $post->ID ); ?></tbody>
        </table>
    </div>
    <?php hws_hpr_print_force_sync_script(); ?>
    <style>
        .hws-hpr-sync-panel .hws-hpr-row-status { font-size: 12px; }
        .hws-hpr-sync-panel tr.hws-hpr-ok .hws-hpr-row-status { color: #008a20; font-weight: 600; }
        .hws-hpr-sync-panel tr.hws-hpr-error .hws-hpr-row-status { color: #b32d2e; font-weight: 600; }
        .hws-hpr-sync-panel tr.hws-hpr-running .hws-hpr-row-status { color: #996800; font-weight: 600; }
    </style>
    <?php
}

function hws_hpr_print_force_sync_script(): void {
    static $printed = false;
    if ( $printed ) {
        return;
    }
    $printed = true;
    ?>
    <script>
    jQuery(function($) {
        window.hwsHprStatusText = function(data) {
            if (!data) {
                return 'No response data.';
            }
            var parts = [];
            parts.push(data.ok ? 'OK' : 'Issue');
            if (data.mode) parts.push('mode=' + data.mode);
            if (data.matched !== null && typeof data.matched !== 'undefined') parts.push('matched=' + data.matched);
            if (data.new_count) parts.push('new=' + data.new_count);
            if (data.updated_count) parts.push('updated=' + data.updated_count);
            if (data.redirects_disabled) parts.push('redirects disabled=' + data.redirects_disabled);
            if (data.public_status) parts.push('public HTTP=' + data.public_status);
            if (data.message) parts.push(data.message);
            return parts.join(' | ');
        };

        window.hwsHprRunRow = function($panel, $row, mode) {
            var postId = $panel.data('post-id');
            var nonce = $panel.data('nonce') || window.hwsNonce;
            var publicationId = $row.data('publication-id');
            $row.removeClass('hws-hpr-ok hws-hpr-error').addClass('hws-hpr-running');
            $row.find('.hws-hpr-row-status').text('Running...');

            return $.post(ajaxurl, {
                action: 'hws_hpr_force_sync_publication',
                nonce: nonce,
                post_id: postId,
                publication_id: publicationId,
                mode: mode
            }).done(function(response) {
                var data = response && response.data ? response.data : {};
                if (response && response.success) {
                    $row.removeClass('hws-hpr-running hws-hpr-error').addClass('hws-hpr-ok');
                } else {
                    $row.removeClass('hws-hpr-running hws-hpr-ok').addClass('hws-hpr-error');
                }
                $row.find('.hws-hpr-row-status').text(window.hwsHprStatusText(data));
            }).fail(function(xhr) {
                var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : 'AJAX request failed.';
                $row.removeClass('hws-hpr-running hws-hpr-ok').addClass('hws-hpr-error');
                $row.find('.hws-hpr-row-status').text(message);
            });
        };

        function hwsHprRunPanel($panel, mode) {
            var $rows = $panel.find('[data-hpr-row]').filter(function() {
                return $(this).find('.hws-hpr-publication-check').is(':checked');
            });
            var total = $rows.length;
            var index = 0;
            var $progress = $panel.find('.hws-hpr-progress');

            if (!total) {
                $progress.text('No rows selected.');
                return;
            }

            function next() {
                if (index >= total) {
                    $progress.text('Complete: ' + total + '/' + total);
                    return;
                }

                var $row = $rows.eq(index);
                $progress.text('Running ' + (index + 1) + '/' + total + '...');
                window.hwsHprRunRow($panel, $row, mode).always(function() {
                    index++;
                    next();
                });
            }

            next();
        }

        $(document).on('click', '.hws-hpr-run', function() {
            var $panel = $(this).closest('.hws-hpr-sync-panel');
            hwsHprRunPanel($panel, $(this).data('mode') || 'force');
        });

        $(document).on('click', '.hws-hpr-select-all', function() {
            $(this).closest('.hws-hpr-sync-panel').find('.hws-hpr-publication-check').prop('checked', true);
        });

        $(document).on('click', '.hws-hpr-select-none', function() {
            $(this).closest('.hws-hpr-sync-panel').find('.hws-hpr-publication-check').prop('checked', false);
        });
    });
    </script>
    <?php
}

function hws_hpr_display_force_sync_dashboard(): void {
    if ( ! hws_hpr_force_sync_admin_enabled() ) {
        echo '<div class="hws-panel"><div class="hws-panel-body"><p>Hexa PR Wire force sync is not available on this site.</p></div></div>';
        return;
    }

    $posts        = hws_hpr_get_recent_press_releases( 30 );
    $publications = hws_hpr_get_publications();
    $logs         = get_option( HWS_HPR_SYNC_LOG_OPTION, [] );
    $logs         = is_array( $logs ) ? array_slice( $logs, 0, 25 ) : [];
    ?>
    <div class="hws-panel">
        <div class="hws-panel-header">Hexa PR Wire Force Sync</div>
        <div class="hws-panel-body">
            <p>This panel calls each publication site's Distributor force-sync endpoint from Hexa PR Wire. The shared key is used server-side only and is not printed in this UI.</p>
            <div style="background:#f6f7f7;border-left:4px solid #2271b1;padding:12px 14px;margin:0 0 18px;">
                <p><strong>How this works:</strong> each selected publication receives a server-side request to <code>/wp-json/hpr-distributor/v1/force-sync</code> with the current Hexa PR Wire post slug and <code>feed_action=force</code>. The request key is injected server-side and intentionally hidden from this screen.</p>
                <p><strong>Returned data tracked here:</strong> endpoint HTTP status, matched feed item count, new URL count, updated URL count, missing target count, Rank Math redirects disabled, public URL HTTP status, whether the public page contains the post title, and the final success message. Results are stored in post meta <code><?php echo esc_html( HWS_HPR_SYNC_META ); ?></code> and the rolling option log <code><?php echo esc_html( HWS_HPR_SYNC_LOG_OPTION ); ?></code>.</p>
                <p><strong>Expected distributor response:</strong> <code>success</code>, <code>matched_feed_items</code>, <code>result.new_live_urls</code>, <code>result.updated_live_urls</code>, <code>result.missing_targets</code>, <code>result.not_imported_source_urls</code>, and <code>redirect_cleanup.disabled</code>.</p>
            </div>
            <div class="hws-hpr-sync-panel" data-post-id="" data-nonce="<?php echo esc_attr( wp_create_nonce( HWS_AJAX_NONCE ) ); ?>">
                <h3>Recent Press Releases</h3>
                <p>
                    <button type="button" class="button hws-hpr-master-select-posts">Select all posts</button>
                    <button type="button" class="button hws-hpr-master-clear-posts">Clear posts</button>
                </p>
                <div style="max-height:220px;overflow:auto;border:1px solid #ccd0d4;padding:10px;background:#fff;">
                    <?php foreach ( $posts as $post ) : ?>
                        <label style="display:block;margin:0 0 6px;">
                            <input type="checkbox" class="hws-hpr-master-post" value="<?php echo esc_attr( $post->ID ); ?>">
                            <strong><?php echo esc_html( get_the_title( $post ) ); ?></strong>
                            <code><?php echo esc_html( $post->post_name ); ?></code>
                        </label>
                    <?php endforeach; ?>
                </div>

                <h3>Publication Checklist</h3>
                <p>
                    <button type="button" class="button hws-hpr-select-all">Select all publications</button>
                    <button type="button" class="button hws-hpr-select-none">Clear publications</button>
                </p>
                <table class="widefat striped hws-hpr-publication-table">
                    <thead><tr><th style="width:40px;"></th><th>Publication</th><th>Domain</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ( $publications as $publication ) : ?>
                            <tr data-hpr-row data-publication-id="<?php echo esc_attr( $publication['id'] ); ?>">
                                <td><input type="checkbox" class="hws-hpr-publication-check" value="<?php echo esc_attr( $publication['id'] ); ?>" checked></td>
                                <td><strong><?php echo esc_html( $publication['title'] ); ?></strong></td>
                                <td><code><?php echo esc_html( $publication['domain'] ); ?></code></td>
                                <td class="hws-hpr-row-status">Waiting</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p style="margin-top:15px;">
                    <button type="button" class="button button-primary hws-hpr-master-run" data-mode="force">Force selected posts to selected publications</button>
                    <button type="button" class="button hws-hpr-master-run" data-mode="check">Check selected URLs only</button>
                    <span class="hws-hpr-progress" style="margin-left:10px;"></span>
                </p>
                <table class="widefat striped" id="hws-hpr-master-log">
                    <thead><tr><th>Time</th><th>Post</th><th>Publication</th><th>Result</th><th>URL</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="hws-panel">
        <div class="hws-panel-header">Recent Force Sync Log</div>
        <div class="hws-panel-body">
            <table class="widefat striped">
                <thead><tr><th>Time GMT</th><th>Post</th><th>Publication</th><th>Result</th><th>Live URL</th></tr></thead>
                <tbody>
                    <?php if ( empty( $logs ) ) : ?>
                        <tr><td colspan="5">No force sync runs logged yet.</td></tr>
                    <?php else : ?>
                        <?php foreach ( $logs as $entry ) : ?>
                            <tr>
                                <td><?php echo esc_html( (string) ( $entry['time_gmt'] ?? '' ) ); ?></td>
                                <td><?php echo esc_html( (string) ( $entry['post_title'] ?? '' ) ); ?></td>
                                <td><?php echo esc_html( (string) ( $entry['publication'] ?? '' ) ); ?></td>
                                <td><?php echo ! empty( $entry['ok'] ) ? '<span class="status-ok">OK</span>' : '<span class="status-bad">Issue</span>'; ?> <?php echo esc_html( (string) ( $entry['message'] ?? '' ) ); ?></td>
                                <td><a href="<?php echo esc_url( (string) ( $entry['live_url'] ?? '' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( (string) ( $entry['live_url'] ?? '' ) ); ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
    jQuery(function($) {
        $('.hws-hpr-master-select-posts').on('click', function() {
            $('.hws-hpr-master-post').prop('checked', true);
        });
        $('.hws-hpr-master-clear-posts').on('click', function() {
            $('.hws-hpr-master-post').prop('checked', false);
        });
        $('.hws-hpr-master-run').on('click', function() {
            var mode = $(this).data('mode') || 'force';
            var $panel = $(this).closest('.hws-hpr-sync-panel');
            var postIds = $('.hws-hpr-master-post:checked').map(function() { return $(this).val(); }).get();
            var $pubRows = $panel.find('[data-hpr-row]').filter(function() {
                return $(this).find('.hws-hpr-publication-check').is(':checked');
            });
            var tasks = [];
            postIds.forEach(function(postId) {
                $pubRows.each(function() {
                    tasks.push({ postId: postId, row: $(this), publicationId: $(this).data('publication-id') });
                });
            });

            var total = tasks.length;
            var index = 0;
            var $progress = $panel.find('.hws-hpr-progress');
            var $log = $('#hws-hpr-master-log tbody');

            if (!total) {
                $progress.text('Select at least one post and one publication.');
                return;
            }

            function addLog(data) {
                data = data || {};
                var ok = data.ok ? 'OK' : 'Issue';
                var url = data.live_url || '';
                var row = '<tr><td>' + (data.time_gmt || '') + '</td><td>' + (data.post_title || '') + '</td><td>' + (data.publication || '') + '</td><td>' + ok + ' - ' + (data.message || '') + '</td><td><a href="' + url + '" target="_blank" rel="noopener noreferrer">' + url + '</a></td></tr>';
                $log.prepend(row);
            }

            function runNext() {
                if (index >= total) {
                    $progress.text('Complete: ' + total + '/' + total);
                    return;
                }

                var task = tasks[index];
                $panel.attr('data-post-id', task.postId).data('post-id', task.postId);
                $progress.text('Running ' + (index + 1) + '/' + total + '...');
                window.hwsHprRunRow($panel, task.row, mode).always(function(xhr) {
                    var response = xhr && xhr.responseJSON ? xhr.responseJSON : xhr;
                    if (response && response.data) {
                        addLog(response.data);
                    }
                    index++;
                    runNext();
                });
            }

            runNext();
        });
    });
    </script>
    <?php hws_hpr_print_force_sync_script(); ?>
    <?php
}
