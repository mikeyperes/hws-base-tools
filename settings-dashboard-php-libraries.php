<?php namespace hws_base_tools;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Determine the status of required PHP extensions.
 *
 * @return array<string,bool> Associative array of extension names => loaded status.
 */
function hws_ct_get_php_extension_status(): array {
    return [
        'PDF'        => extension_loaded( 'pdf' ),
        'Brotli'     => extension_loaded( 'brotli' ),
        'Xdebug'     => extension_loaded( 'xdebug' ),
        'Redis'      => extension_loaded( 'redis' ),
        'Imagick'    => extension_loaded( 'imagick' ),
        'MySQLi'     => extension_loaded( 'mysqli' ),
        'MySQLnd'    => extension_loaded( 'mysqlnd' ),
        'Memcache'   => extension_loaded( 'memcache' ),
        'Memcached'  => extension_loaded( 'memcached' ),
        'HTTP'       => extension_loaded( 'http' ),
        'BZ2'        => extension_loaded( 'bz2' ),
        'DOM'        => extension_loaded( 'dom' ),
        'XMLWriter'  => extension_loaded( 'xmlwriter' ),
        'XMLReader'  => extension_loaded( 'xmlreader' ),
        'OPcache'    => extension_loaded( 'opcache' ),
        'OAuth'      => extension_loaded( 'oauth' ),
        'IMAP'       => extension_loaded( 'imap' ),
    ];
}


/**
 * Render the PHP Environment Info panel.
 */
function hws_ct_display_php_info() {
    // Get current PHP version
    $php_version      = phpversion();
    $required_version = '8.4.0';

    // Determine if current version meets requirement
    $is_modern = version_compare( $php_version, $required_version, '>=' );

    // Get extension statuses
    $ext_status = hws_ct_get_php_extension_status();
    ?>
    <div class="panel">
        <h2 class="panel-title">PHP Environment Info</h2>
        <div class="panel-content">
            <p>
                <strong>PHP Version:</strong>
                <span style="color: <?php echo $is_modern ? 'inherit' : 'red'; ?>;">
                    <?php echo esc_html( $php_version ); ?>
                    <?php if ( ! $is_modern ) : ?>
                        <em>(Update to PHP <?php echo esc_html( $required_version ); ?>+ recommended)</em>
                    <?php endif; ?>
                </span>
            </p>

            <ul>
                <?php foreach ( $ext_status as $name => $loaded ) : ?>
                    <li>
                        <strong><?php echo esc_html( strtoupper( $name ) ); ?>:</strong>
                        <?php if ( $loaded ) : ?>
                            Enabled
                        <?php else : ?>
                            <span style="color: red;">Disabled</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php
}

// Hook into admin_notices to display the info
//add_action( 'admin_notices', __NAMESPACE__ . '\\hws_ct_display_php_info' );
