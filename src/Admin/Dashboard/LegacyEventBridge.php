<?php

namespace HWS\BaseTools\Admin\Dashboard;

use HWS\BaseTools\Core\Module;

final class LegacyEventBridge implements Module {
    /**
     * @var bool
     */
    private static $registered = false;

    public function register() {
        self::register_hooks();
    }

    /**
     * Register the minimal legacy dashboard bridge hooks once.
     */
    public static function register_hooks() {
        if ( self::$registered ) {
            return;
        }

        add_action( 'admin_head', [ __CLASS__, 'render_admin_head_script' ] );
        add_action( 'wp_ajax_hws_base_tools_modify_wp_config_constants', [ __CLASS__, 'handle_modify_wp_config_constants' ] );
        add_action( 'wp_ajax_modify_wp_config_constants', [ __CLASS__, 'handle_modify_wp_config_constants' ] );
        add_action( 'wp_ajax_hws_base_tools_toggle_snippet', [ __CLASS__, 'handle_toggle_snippet' ] );

        self::$registered = true;
    }

    /**
     * Inject the shared dashboard nonce plus the legacy snippet toggle helper.
     */
    public static function render_admin_head_script() {
        if ( ! isset( $_GET['page'] ) || 'hws-core-tools' !== $_GET['page'] ) {
            return;
        }
        ?>
        <script>
        window.hwsNonce = window.hwsNonce || '<?php echo esc_js( wp_create_nonce( HWS_AJAX_NONCE ) ); ?>';
        window.hws_base_tools = window.hws_base_tools || {};

        (function($) {
            'use strict';

            function getNonce() {
                return window.hwsNonce || '';
            }

            function getAjaxErrorMessage(response) {
                if (!response || typeof response.data === 'undefined') {
                    return 'Unknown error';
                }

                if (typeof response.data === 'string') {
                    return response.data;
                }

                if (response.data && response.data.message) {
                    return response.data.message;
                }

                return 'Unknown error';
            }

            window.hws_base_tools.toggleSnippet = function(snippetId) {
                var $checkbox = $('#toggle-' + snippetId);

                if ($checkbox.length === 0) {
                    $checkbox = $('#' + snippetId);
                }

                if ($checkbox.length === 0) {
                    alert('Snippet toggle not found: ' + snippetId);
                    return;
                }

                var isChecked = $checkbox.prop('checked');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'hws_base_tools_toggle_snippet',
                        snippet_id: snippetId,
                        enable: isChecked,
                        nonce: getNonce()
                    }
                }).done(function(response) {
                    if (response.success) {
                        return;
                    }

                    $checkbox.prop('checked', !isChecked);
                    alert('Error: ' + getAjaxErrorMessage(response));
                }).fail(function(jqXHR, textStatus, errorThrown) {
                    $checkbox.prop('checked', !isChecked);
                    console.error('Snippet toggle AJAX error:', textStatus, errorThrown, jqXHR.responseText);
                    alert('AJAX request failed: ' + textStatus + ', ' + errorThrown);
                });
            };
        })(jQuery);
        </script>
        <?php
    }

    /**
     * Handle wp-config constant updates through the explicit dashboard action.
     */
    public static function handle_modify_wp_config_constants() {
        try {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( [ 'message' => 'Unauthorized' ] );
                return;
            }

            \hws_base_tools\hws_require_ajax_nonce_or_error();

            $constants = isset( $_POST['constants'] ) ? $_POST['constants'] : [];
            if ( empty( $constants ) ) {
                wp_send_json_error( [ 'message' => 'No constants provided' ] );
                return;
            }

            $sanitized_constants = [];

            foreach ( $constants as $key => $value ) {
                $sanitized_key = sanitize_text_field( wp_unslash( $key ) );

                if ( is_array( $value ) ) {
                    $value = wp_unslash( $value );
                    $sanitized_constants[ $sanitized_key ] = [
                        'type'  => isset( $value['type'] ) ? sanitize_key( $value['type'] ) : '',
                        'value' => isset( $value['value'] ) ? sanitize_text_field( $value['value'] ) : '',
                    ];
                } else {
                    $sanitized_constants[ $sanitized_key ] = sanitize_text_field( wp_unslash( $value ) );
                }
            }

            $result = \hws_base_tools\modify_wp_config_constants( $sanitized_constants );

            if ( ! empty( $result['status'] ) ) {
                wp_send_json_success( [ 'message' => $result['message'] ] );
                return;
            }

            wp_send_json_error( [ 'message' => $result['message'] ] );
        } catch ( \Exception $exception ) {
            \hws_base_tools\write_log( 'wp-config modify error: ' . $exception->getMessage(), true );
            wp_send_json_error( [ 'message' => 'Error: ' . $exception->getMessage() ] );
        }
    }

    /**
     * Toggle a stored snippet option from the dashboard.
     */
    public static function handle_toggle_snippet() {
        try {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( 'Unauthorized' );
                return;
            }

            \hws_base_tools\hws_require_ajax_nonce_or_error();

            if ( ! isset( $_POST['snippet_id'] ) || empty( $_POST['snippet_id'] ) ) {
                wp_send_json_error( 'No snippet ID provided' );
                return;
            }

            $snippet_id = sanitize_text_field( wp_unslash( $_POST['snippet_id'] ) );
            $enable     = isset( $_POST['enable'] ) ? filter_var( wp_unslash( $_POST['enable'] ), FILTER_VALIDATE_BOOLEAN ) : false;

            \hws_base_tools\write_log( 'Toggle snippet called with ID: ' . $snippet_id . ', enable: ' . ( $enable ? 'true' : 'false' ) );

            $all_snippets = array_merge(
                \hws_base_tools\get_snippets( 'acf' ),
                \hws_base_tools\get_snippets( 'admin' ),
                \hws_base_tools\get_snippets( 'non_admin' )
            );

            foreach ( $all_snippets as $snippet ) {
                if ( empty( $snippet['id'] ) || $snippet['id'] !== $snippet_id ) {
                    continue;
                }

                $current_value      = get_option( $snippet_id );
                $current_value_bool = filter_var( $current_value, FILTER_VALIDATE_BOOLEAN );

                \hws_base_tools\write_log( "Current value of '{$snippet_id}': " . var_export( $current_value, true ) );

                if ( $current_value_bool === $enable ) {
                    \hws_base_tools\write_log( "No update required for '{$snippet_id}'. Current value is the same as the new value." );
                    wp_send_json_error( "No update required for '{$snippet_id}'. Current value is the same." );
                    return;
                }

                $updated = update_option( $snippet_id, $enable );

                if ( $updated ) {
                    \hws_base_tools\write_log( "Option '{$snippet_id}' updated successfully." );
                    wp_send_json_success( "Option '{$snippet_id}' updated successfully." );
                    return;
                }

                global $wpdb;
                $db_error = $wpdb->last_error;

                \hws_base_tools\write_log( "Failed to update option '{$snippet_id}'. Database error: {$db_error}" );
                wp_send_json_error( "Failed to update option '{$snippet_id}'. Database error: {$db_error}" );
                return;
            }

            \hws_base_tools\write_log( "Invalid snippet ID: {$snippet_id}" );
            wp_send_json_error( "Invalid snippet ID: {$snippet_id}" );
        } catch ( \Exception $exception ) {
            \hws_base_tools\write_log( 'toggle_snippet error: ' . $exception->getMessage(), true );
            wp_send_json_error( 'Error: ' . $exception->getMessage() );
        }
    }
}
