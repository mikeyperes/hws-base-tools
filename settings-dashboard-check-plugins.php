<?php namespace hws_base_tools;

/**
 * Plugin Status Monitoring System
 * 
 * Smart, abstract plugin monitoring with easy extensibility.
 * Simply add plugins to the $monitored_plugins array.
 * 
 * @since 8.9.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Register AJAX handler for plugin installation (from WordPress.org → install + activate)
add_action( 'wp_ajax_hws_install_plugin', __NAMESPACE__ . '\\ajax_install_plugin' );

// Register AJAX handler for activating an already-installed plugin
add_action( 'wp_ajax_hws_activate_plugin', __NAMESPACE__ . '\\ajax_activate_plugin' );

/**
 * AJAX handler to install a plugin from WordPress.org
 */
function ajax_install_plugin() {
    // Check permissions
    if ( ! current_user_can( 'install_plugins' ) ) {
        wp_send_json_error( 'Unauthorized - you do not have permission to install plugins.' );
        return;
    }
    
    // Verify nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], HWS_AJAX_NONCE ) ) {
        wp_send_json_error( 'Security check failed.' );
        return;
    }
    
    // Get plugin slug
    $slug = isset( $_POST['slug'] ) ? sanitize_text_field( $_POST['slug'] ) : '';
    
    if ( empty( $slug ) ) {
        wp_send_json_error( 'No plugin slug provided.' );
        return;
    }
    
    // Load required files
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    
    // Get plugin info from WordPress.org
    $api = plugins_api( 'plugin_information', [
        'slug'   => $slug,
        'fields' => [
            'sections' => false,
        ],
    ]);
    
    if ( is_wp_error( $api ) ) {
        wp_send_json_error( 'Plugin not found on WordPress.org: ' . $api->get_error_message() );
        return;
    }
    
    // Use a silent skin to prevent output
    $skin = new \WP_Ajax_Upgrader_Skin();
    $upgrader = new \Plugin_Upgrader( $skin );
    
    // Install the plugin
    $result = $upgrader->install( $api->download_link );
    
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( 'Installation failed: ' . $result->get_error_message() );
        return;
    }
    
    if ( $result === false ) {
        wp_send_json_error( 'Installation failed.' );
        return;
    }
    
    // Activate the plugin
    $plugin_file = $upgrader->plugin_info();
    if ( $plugin_file ) {
        $activate_result = activate_plugin( $plugin_file );
        if ( is_wp_error( $activate_result ) ) {
            wp_send_json_success( [
                'message'   => 'Installed but activation failed: ' . $activate_result->get_error_message(),
                'activated' => false,
            ]);
            return;
        }
    }
    
    wp_send_json_success( [
        'message'   => 'Plugin installed and activated successfully.',
        'activated' => true,
    ]);
}


/**
 * AJAX handler to activate an already-installed plugin.
 *
 * Abstract/reusable: any panel can call this with a plugin_file parameter.
 * Uses the same nonce (HWS_AJAX_NONCE) as install handler for consistency.
 *
 * Expected POST params:
 *   - plugin_file  (string) e.g. 'wordfence/wordfence.php'
 *   - nonce        (string) wp_create_nonce( HWS_AJAX_NONCE )
 *
 * @since 10.8.4
 */
function ajax_activate_plugin() {
    // — Check permissions
    if ( ! current_user_can( 'activate_plugins' ) ) {
        wp_send_json_error( 'Unauthorized — you do not have permission to activate plugins.' );
        return;
    }

    // — Verify nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], HWS_AJAX_NONCE ) ) {
        wp_send_json_error( 'Security check failed.' );
        return;
    }

    // — Get plugin file path (e.g. 'wordfence/wordfence.php')
    $plugin_file = isset( $_POST['plugin_file'] ) ? sanitize_text_field( $_POST['plugin_file'] ) : '';

    if ( empty( $plugin_file ) ) {
        wp_send_json_error( 'No plugin file provided.' );
        return;
    }

    // — Load required files
    require_once ABSPATH . 'wp-admin/includes/plugin.php';

    // — Check if the plugin file actually exists
    if ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) ) {
        wp_send_json_error( 'Plugin file not found: ' . $plugin_file );
        return;
    }

    // — Check if already active
    if ( is_plugin_active( $plugin_file ) ) {
        wp_send_json_success( [
            'message'   => 'Plugin is already active.',
            'activated' => true,
        ]);
        return;
    }

    // — Activate the plugin
    $result = activate_plugin( $plugin_file );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( 'Activation failed: ' . $result->get_error_message() );
        return;
    }

    wp_send_json_success( [
        'message'   => 'Plugin activated successfully.',
        'activated' => true,
    ]);
}


/**
 * Get list of monitored plugins
 * 
 * Easy to extend - just add to this array!
 * 
 * Format:
 * 'plugin-folder/plugin-file.php' => [
 *     'name'        => 'Display Name',
 *     'should_be'   => 'active' | 'inactive',  // Expected state
 *     'auto_update' => true | false,           // Should auto-update be enabled?
 *     'download'    => 'url' | 'manual',       // How to get it
 *     'category'    => 'essential' | 'optional', // Importance level
 *     'pro'         => true | false,            // Pro/paid plugin? (won't auto-install)
 * ]
 */
function hws_get_monitored_plugins() {
    return [
        // === OPTIONAL PRO PLUGINS ===
        'advanced-custom-fields-pro/acf.php' => [
            'name'        => 'Advanced Custom Fields Pro',
            'should_be'   => 'active',
            'auto_update' => true,
            'download'    => 'manual',
            'category'    => 'optional',
            'pro'         => true,
        ],
        // === ESSENTIAL ACTIVE (auto-installed by Quick Setup where possible) ===
        'elementor/elementor.php' => [
            'name'        => 'Elementor',
            'should_be'   => 'active',
            'auto_update' => true,
            'download'    => 'https://wordpress.org/plugins/elementor/',
            'category'    => 'essential',
            'pro'         => false,
        ],
        'elementor-pro/elementor-pro.php' => [
            'name'        => 'Elementor Pro',
            'should_be'   => 'active',
            'auto_update' => true,
            'download'    => 'manual',
            'category'    => 'essential',
            'pro'         => true,
        ],
        'classic-editor/classic-editor.php' => [
            'name'        => 'Classic Editor',
            'should_be'   => 'active',
            'auto_update' => true,
            'download'    => 'https://wordpress.org/plugins/classic-editor/',
            'category'    => 'essential',
            'pro'         => false,
        ],
        'wordfence/wordfence.php' => [
            'name'        => 'Wordfence',
            'should_be'   => 'active',
            'auto_update' => false,  // — Security plugin: review updates manually
            'download'    => 'https://wordpress.org/plugins/wordfence/',
            'category'    => 'essential',
            'pro'         => false,
        ],
        'wp-mail-smtp/wp_mail_smtp.php' => [
            'name'        => 'WP Mail SMTP',
            'should_be'   => 'active',
            'auto_update' => true,
            'download'    => 'https://wordpress.org/plugins/wp-mail-smtp/',
            'category'    => 'essential',
            'pro'         => false,
        ],
        'seo-by-rank-math/rank-math.php' => [
            'name'        => 'Rank Math SEO',
            'should_be'   => 'active',
            'auto_update' => true,
            'download'    => 'https://wordpress.org/plugins/seo-by-rank-math/',
            'category'    => 'essential',
            'pro'         => false,
        ],
        'wp-user-avatars/wp-user-avatars.php' => [
            'name'        => 'WP User Avatars',
            'should_be'   => 'active',
            'auto_update' => true,
            'download'    => 'https://wordpress.org/plugins/wp-user-avatars/',
            'category'    => 'essential',
            'pro'         => false,
        ],
        'litespeed-cache/litespeed-cache.php' => [
            'name'        => 'LiteSpeed Cache',
            'should_be'   => 'active',
            'auto_update' => true,
            'download'    => 'https://wordpress.org/plugins/litespeed-cache/',
            'category'    => 'essential',
            'pro'         => false,
        ],

        // === OPTIONAL ===
        'wp-optimize/wp-optimize.php' => [
            'name'        => 'WP Optimize',
            'should_be'   => 'inactive',
            'auto_update' => true,
            'download'    => 'https://wordpress.org/plugins/wp-optimize/',
            'category'    => 'optional',
            'pro'         => false,
        ],
        'regenerate-thumbnails/regenerate-thumbnails.php' => [
            'name'        => 'Regenerate Thumbnails',
            'should_be'   => 'inactive',
            'auto_update' => true,
            'download'    => 'https://wordpress.org/plugins/regenerate-thumbnails/',
            'category'    => 'optional',
            'pro'         => false,
        ],
    ];
}


/**
 * Get red flag plugins that should NOT be installed
 */
function hws_get_red_flag_plugins() {
    return [
        'wp-file-manager/file-manager.php' => [
            'name'   => 'WP File Manager',
            'reason' => 'Critical security vulnerability - allows remote file access',
        ],
        'duplicator/duplicator.php' => [
            'name'   => 'Duplicator',
            'reason' => 'Often left installed after migration - remove when done',
        ],
    ];
}


/**
 * Check plugin status
 * 
 * @param string $plugin_path Plugin path (folder/file.php)
 * @return array Status array with installed, active, auto_update keys
 */
function hws_check_plugin_status( $plugin_path ) {
    $all_plugins = get_plugins();
    $active_plugins = get_option( 'active_plugins', [] );
    $auto_updates = (array) get_site_option( 'auto_update_plugins', [] );
    
    return [
        'installed'   => isset( $all_plugins[ $plugin_path ] ),
        'active'      => in_array( $plugin_path, $active_plugins, true ),
        'auto_update' => in_array( $plugin_path, $auto_updates, true ),
        'version'     => isset( $all_plugins[ $plugin_path ] ) ? $all_plugins[ $plugin_path ]['Version'] : null,
    ];
}


/**
 * Render Plugins Tab
 */
function render_tab_plugins() {
    $monitored = hws_get_monitored_plugins();
    $red_flags = hws_get_red_flag_plugins();
    
    // Get update info
    $plugin_updates = get_plugin_updates();
    $theme_updates = get_theme_updates();
    $core_updates = get_core_updates();
    $core_update_available = ! empty( $core_updates ) && isset( $core_updates[0]->response ) && $core_updates[0]->response === 'upgrade';
    ?>
    
    <!-- Red Flag Alerts -->
    <?php
    $found_red_flags = [];
    foreach ( $red_flags as $plugin_path => $info ) {
        $status = hws_check_plugin_status( $plugin_path );
        if ( $status['installed'] ) {
            $found_red_flags[ $plugin_path ] = $info;
        }
    }
    
    if ( ! empty( $found_red_flags ) ) :
    ?>
    <div class="hws-red-flag">
        <h4>🚨 RED FLAG - Remove These Plugins!</h4>
        <?php foreach ( $found_red_flags as $path => $info ) : ?>
            <p>
                <strong><?php echo esc_html( $info['name'] ); ?></strong><br>
                <span style="color: #666;"><?php echo esc_html( $info['reason'] ); ?></span><br>
                <a href="<?php echo wp_nonce_url( admin_url( 'plugins.php?action=delete-selected&checked[]=' . $path ), 'bulk-plugins' ); ?>" class="hws-btn hws-btn-danger" style="margin-top: 5px;">Delete Plugin</a>
            </p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- Updates Center -->
    <div class="hws-panel">
        <div class="hws-panel-header">📦 Updates Center</div>
        <div class="hws-panel-body">
            <div class="hws-status-grid">
                <div class="hws-status-card <?php echo $core_update_available ? 'bad' : 'good'; ?>">
                    <div class="value"><?php global $wp_version; echo $wp_version; ?></div>
                    <div class="label">WordPress <?php echo $core_update_available ? '(Update!)' : ''; ?></div>
                </div>
                <div class="hws-status-card <?php echo count( $plugin_updates ) > 0 ? 'warn' : 'good'; ?>">
                    <div class="value"><?php echo count( $plugin_updates ); ?></div>
                    <div class="label">Plugin Updates</div>
                </div>
                <div class="hws-status-card <?php echo count( $theme_updates ) > 0 ? 'warn' : 'good'; ?>">
                    <div class="value"><?php echo count( $theme_updates ); ?></div>
                    <div class="label">Theme Updates</div>
                </div>
            </div>
            
            <?php if ( count( $plugin_updates ) > 0 || count( $theme_updates ) > 0 || $core_update_available ) : ?>
            <a href="<?php echo admin_url( 'update-core.php' ); ?>" class="hws-btn" target="_blank">Go to Updates Page</a>
            <a href="<?php echo admin_url( 'update-core.php?force-check=1' ); ?>" class="hws-btn hws-btn-secondary" target="_blank">Force Update Check</a>
            <?php else : ?>
            <p class="status-ok">✅ Everything is up to date!</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Monitored Plugins Status -->
    <div class="hws-panel">
        <div class="hws-panel-header">🔌 Plugin Status</div>
        <div class="hws-panel-body">
            <table class="hws-plugin-table">
                <thead>
                    <tr>
                        <th>Plugin</th>
                        <th>Installed</th>
                        <th>Status</th>
                        <th>Auto-Update</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $missing_plugins = [];
                    foreach ( $monitored as $plugin_path => $config ) : 
                        $status = hws_check_plugin_status( $plugin_path );
                        $expected_active = ( $config['should_be'] === 'active' );
                        
                        // Track missing plugins for batch install
                        if ( ! $status['installed'] && $config['download'] !== 'manual' ) {
                            // Extract slug from download URL
                            if ( preg_match( '/wordpress\.org\/plugins\/([^\/]+)/', $config['download'], $matches ) ) {
                                $missing_plugins[ $matches[1] ] = $config['name'];
                            }
                        }
                        
                        // Determine status correctness
                        $installed_ok = $status['installed'];
                        $active_ok = ( $expected_active && $status['active'] ) || ( ! $expected_active && ! $status['active'] );
                        $autoupdate_ok = ( $config['auto_update'] && $status['auto_update'] ) || ( ! $config['auto_update'] );
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html( $config['name'] ); ?></strong>
                            <?php
                            // — Category badge (essential / optional)
                            $cat = $config['category'] ?? 'essential';
                            $cat_colors = [ 'essential' => '#2271b1', 'optional' => '#646970' ];
                            ?>
                            <span style="background:<?php echo $cat_colors[ $cat ] ?? '#646970'; ?>;color:#fff;font-size:9px;padding:1px 5px;border-radius:3px;margin-left:4px;vertical-align:middle;"><?php echo strtoupper( $cat ); ?></span>
                            <?php if ( ! empty( $config['pro'] ) ) : ?>
                                <span style="background:#8c5e00;color:#fff;font-size:9px;padding:1px 5px;border-radius:3px;margin-left:2px;vertical-align:middle;">PRO</span>
                            <?php endif; ?>
                            <?php if ( $status['version'] ) : ?>
                                <br><small style="color: #666;">v<?php echo esc_html( $status['version'] ); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( $status['installed'] ) : ?>
                                <span class="status-ok">✅ Installed</span>
                            <?php else : ?>
                                <span class="status-bad">❌ Not Installed</span>
                                <?php if ( $config['download'] !== 'manual' && preg_match( '/wordpress\.org\/plugins\/([^\/]+)/', $config['download'], $slug_match ) ) : ?>
                                    <br><input type="checkbox" class="hws-missing-plugin-checkbox" value="<?php echo esc_attr( $slug_match[1] ); ?>" data-name="<?php echo esc_attr( $config['name'] ); ?>" style="margin-top: 5px;" checked>
                                    <small style="color: #666;">Include in batch</small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( ! $status['installed'] ) : ?>
                                <span style="color: #999;">—</span>
                            <?php elseif ( $active_ok ) : ?>
                                <?php if ( $status['active'] ) : ?>
                                    <span class="status-ok">✅ Active</span>
                                <?php else : ?>
                                    <span class="status-ok">✅ Inactive (Correct)</span>
                                <?php endif; ?>
                            <?php else : ?>
                                <?php if ( $status['active'] ) : ?>
                                    <span class="status-bad">❌ Active (Should be Inactive)</span>
                                <?php else : ?>
                                    <span class="status-bad">❌ Inactive (Should be Active)</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( ! $status['installed'] ) : ?>
                                <span style="color: #999;">—</span>
                            <?php elseif ( $status['auto_update'] ) : ?>
                                <span class="status-ok">✅ Enabled</span>
                            <?php else : ?>
                                <span class="<?php echo $config['auto_update'] ? 'status-bad' : 'status-ok'; ?>">
                                    <?php echo $config['auto_update'] ? '❌ Disabled' : '✅ Disabled (OK)'; ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( ! $status['installed'] ) : ?>
                                <?php if ( $config['download'] === 'manual' ) : ?>
                                    <span style="color: #666; font-size: 12px;">Upload manually</span>
                                <?php else : ?>
                                    <a href="<?php echo esc_url( $config['download'] ); ?>" target="_blank" class="hws-btn hws-btn-secondary" style="padding: 4px 8px; font-size: 11px;">Download</a>
                                <?php endif; ?>
                            <?php elseif ( $status['active'] && ! $expected_active ) : ?>
                                <?php
                                $deactivate_url = wp_nonce_url(
                                    admin_url( 'plugins.php?action=deactivate&plugin=' . urlencode( $plugin_path ) ),
                                    'deactivate-plugin_' . $plugin_path
                                );
                                ?>
                                <a href="<?php echo esc_url( $deactivate_url ); ?>" class="hws-btn hws-btn-secondary" style="padding: 4px 8px; font-size: 11px;">Deactivate</a>
                            <?php elseif ( ! $status['active'] && $expected_active ) : ?>
                                <?php
                                $activate_url = wp_nonce_url(
                                    admin_url( 'plugins.php?action=activate&plugin=' . urlencode( $plugin_path ) ),
                                    'activate-plugin_' . $plugin_path
                                );
                                ?>
                                <a href="<?php echo esc_url( $activate_url ); ?>" class="hws-btn" style="padding: 4px 8px; font-size: 11px;">Activate</a>
                            <?php else : ?>
                                <span style="color: #00a32a;">✓</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div style="margin-top: 15px;">
                <a href="<?php echo admin_url( 'plugins.php' ); ?>" class="hws-btn hws-btn-secondary" target="_blank">Manage All Plugins</a>
                <button type="button" id="enable-plugin-auto-updates" class="hws-btn">Enable Auto-Updates for All</button>
                
                <?php if ( ! empty( $missing_plugins ) ) : ?>
                <button type="button" id="hws-batch-install-plugins" class="hws-btn" style="background: #2271b1; margin-left: 10px;">
                    📥 Install Selected Missing Plugins
                </button>
                <?php endif; ?>
            </div>
            
            <?php if ( ! empty( $missing_plugins ) ) : ?>
            <div id="hws-batch-install-status" style="margin-top: 15px; padding: 10px; background: #f0f6fc; border-radius: 4px; display: none;">
                <strong>Installation Progress:</strong>
                <div id="hws-batch-install-log" style="margin-top: 10px; font-family: monospace; font-size: 12px;"></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Theme Info -->
    <?php
    if ( function_exists( __NAMESPACE__ . '\\display_settings_theme_checks' ) ) {
        display_settings_theme_checks();
    }
    ?>
    
    <script>
    jQuery(document).ready(function($) {
        // Enable auto-updates for ALL plugins
        $('#enable-plugin-auto-updates').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text('Enabling...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hws_enable_all_auto_updates',
                    nonce: '<?php echo wp_create_nonce( HWS_AJAX_NONCE ); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $btn.text('✅ ' + response.data.message);
                    } else {
                        $btn.prop('disabled', false).text('Enable Auto-Updates for All');
                        alert('Error: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    $btn.prop('disabled', false).text('Enable Auto-Updates for All');
                    alert('AJAX request failed: ' + status + ' - ' + error + '\n' + (xhr.responseText || '').substring(0, 200));
                }
            });
        });
        
        // Batch install missing plugins
        $('#hws-batch-install-plugins').on('click', function() {
            var selectedPlugins = [];
            $('.hws-missing-plugin-checkbox:checked').each(function() {
                selectedPlugins.push({
                    slug: $(this).val(),
                    name: $(this).data('name')
                });
            });
            
            if (selectedPlugins.length === 0) {
                alert('Please select at least one plugin to install.');
                return;
            }
            
            if (!confirm('Install ' + selectedPlugins.length + ' plugin(s)?\n\nThis will download and install from WordPress.org.')) {
                return;
            }
            
            var $btn = $(this);
            var $status = $('#hws-batch-install-status');
            var $log = $('#hws-batch-install-log');
            
            $btn.prop('disabled', true).text('Installing...');
            $status.show();
            $log.html('');
            
            // Install plugins one by one
            var installNext = function(index) {
                if (index >= selectedPlugins.length) {
                    $log.append('<br><strong style="color: green;">✅ All installations complete!</strong>');
                    $btn.text('✅ Complete');
                    return;
                }
                
                var plugin = selectedPlugins[index];
                $log.append('Installing ' + plugin.name + '...<br>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hws_install_plugin',
                        slug: plugin.slug,
                        nonce: '<?php echo wp_create_nonce( HWS_AJAX_NONCE ); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $log.append('<span style="color: green;">✅ ' + plugin.name + ' installed successfully</span><br>');
                        } else {
                            $log.append('<span style="color: red;">❌ ' + plugin.name + ': ' + response.data + '</span><br>');
                        }
                        installNext(index + 1);
                    },
                    error: function() {
                        $log.append('<span style="color: red;">❌ ' + plugin.name + ': AJAX Error</span><br>');
                        installNext(index + 1);
                    }
                });
            };
            
            installNext(0);
        });
    });
    </script>
    <?php
}


/**
 * Force update check AJAX handler
 */
function hws_ct_force_update_check() {
    wp_clean_update_cache();
    wp_update_plugins();
    wp_update_themes();

    $plugin_updates = get_plugin_updates();
    $last_checked = date( 'Y-m-d H:i:s' );
    $plugins_list = [];

    foreach ( $plugin_updates as $plugin_file => $plugin_data ) {
        $plugins_list[] = $plugin_data->Name;
    }

    wp_send_json( [
        'last_checked'         => $last_checked,
        'plugins_with_updates' => count( $plugin_updates ),
        'plugins_list'         => $plugins_list,
    ] );
}
add_action( 'wp_ajax_hws_base_tools_force_update_check', __NAMESPACE__ . '\\hws_ct_force_update_check' );
