<?php
declare( strict_types=1 );

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
$options = [];
$admin = false;
$hooks = [];
$shortcodes = [];
function get_option( $key, $default = false ) { global $options; return $options[ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { global $options; $options[ $key ] = $value; return true; }
function is_admin() { global $admin; return $admin; }
function sanitize_hex_color( $value ) { return preg_match( '/^#(?:[a-f0-9]{3}|[a-f0-9]{6})$/i', $value ) ? $value : null; }
function sanitize_html_class( $value ) { return preg_replace( '/[^a-zA-Z0-9_-]/', '', $value ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function shortcode_atts( $defaults, $attributes, $name = '' ) { return array_intersect_key( $attributes, $defaults ) + $defaults; }
function add_action( $name, $callback, $priority = 10 ) { global $hooks; $hooks[ $name ] = [ $callback, $priority ]; }
function add_shortcode( $name, $callback ) { global $shortcodes; $shortcodes[ $name ] = $callback; }
require_once dirname( __DIR__ ) . '/lib/hexa-wordpress-plugin-core/src/CoreContracts/ModuleInterface.php';
require_once dirname( __DIR__ ) . '/src/FrontendContent/ScrollToTop.php';

use HWS\BaseTools\FrontendContent\ScrollToTop;

$count = 0;
function check( $condition, $message ) {
    global $count;
    if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); }
    ++$count;
}
function reset_button() {
    $property = new ReflectionProperty( ScrollToTop::class, 'rendered' );
    $property->setAccessible( true );
    $property->setValue( null, false );
}

(new ScrollToTop())->register();
check( isset( $shortcodes['hws_scroll_to_top'] ), 'shortcode registered' );
check( PHP_INT_MAX === $hooks['wp_footer'][1], 'automatic fallback runs after footer templates' );
check( ScrollToTop::enabled(), 'feature defaults on with no stored option' );
check( ScrollToTop::defaults() === ScrollToTop::settings(), 'missing settings use documented defaults' );
check( 'bottom_right' === ScrollToTop::settings()['position'], 'default bottom right' );
check( 24 === ScrollToTop::settings()['offset'] && 48 === ScrollToTop::settings()['size'], 'default offset and size' );
check( 300 === ScrollToTop::settings()['show_after'] && ! ScrollToTop::settings()['hide_mobile'], 'default distance and mobile visibility' );
check( '' === ScrollToTop::settings()['background'] && '' === ScrollToTop::settings()['icon_color'], 'colors inherit by default' );
$options[ ScrollToTop::FEATURE_OPTION ] = false;
check( '' === ScrollToTop::shortcode(), 'disabled shortcode emits no markup or assets' );
ob_start(); ScrollToTop::automatic(); $disabled = ob_get_clean();
check( '' === $disabled, 'disabled automatic output emits no assets' );
$options[ ScrollToTop::FEATURE_OPTION ] = true;
$admin = true;
check( '' === ScrollToTop::shortcode(), 'no frontend output on admin screens' );
$admin = false;
$output = ScrollToTop::shortcode();
check( 1 === substr_count( $output, '<button ' ), 'one accessible native button' );
check( str_contains( $output, 'aria-label="Back to top"' ) && str_contains( $output, 'type="button"' ), 'button label and native keyboard semantics' );
check( str_contains( $output, ' hidden ' ) && str_contains( $output, 'aria-hidden="true"' ), 'initially hidden with decorative icon' );
check( str_contains( $output, 'hws-scroll-to-top-css' ) && str_contains( $output, 'hws-scroll-to-top-js' ), 'enabled output includes assets' );
check( '' === ScrollToTop::shortcode(), 'repeat shortcode emits nothing' );
ob_start(); ScrollToTop::automatic(); $duplicate = ob_get_clean();
check( '' === $duplicate, 'shortcode suppresses automatic button and assets' );
reset_button();
ob_start(); ScrollToTop::automatic(); $automatic = ob_get_clean();
check( $automatic === $output, 'automatic and shortcode use identical defaults and assets' );
check( '' === ScrollToTop::shortcode(), 'automatic output cannot be duplicated later' );
reset_button();
$custom = ScrollToTop::shortcode( [
    'position' => 'bottom_left', 'offset' => '32', 'size' => '64', 'show_after' => '500',
    'hide_mobile' => '1', 'background' => '#123456', 'icon_color' => '#fff',
    'hover_background' => '#654321', 'hover_icon_color' => '#000', 'class' => 'footer-top bad<script>',
] );
foreach ( [ '--bottom_left', '--hide-mobile', '--hws-stt-offset:32px', '--hws-stt-size:64px',
    'data-show-after="500"', '--hws-stt-bg:#123456', '--hws-stt-icon:#fff',
    '--hws-stt-hover-bg:#654321', '--hws-stt-hover-icon:#000', 'footer-top badscript' ] as $value ) {
    check( str_contains( $custom, $value ), 'shortcode override: ' . $value );
}
check( ! str_contains( $custom, 'bad<script>' ), 'custom classes sanitized' );
$saved = ScrollToTop::save_settings( [ 'position' => 'invalid', 'offset' => '-5', 'size' => '500', 'show_after' => '999999', 'hide_mobile' => '0', 'background' => 'red;display:none', 'style' => 'none' ] );
check( $saved === ScrollToTop::settings(), 'settings round-trip normalized values' );
check( 'bottom_right' === $saved['position'] && 0 === $saved['offset'] && 120 === $saved['size'] && 20000 === $saved['show_after'], 'bounds and invalid position normalized' );
check( '' === $saved['background'] && ! $saved['hide_mobile'], 'invalid colors rejected and false string honored' );
reset_button();
$unstyled = ScrollToTop::shortcode();
check( str_contains( $unstyled, 'hws-scroll-to-top--none' ), 'no plugin styling option keeps button markup' );
check( ! str_contains( $unstyled, '<svg class="hws-stt-ring"' ) && ! str_contains( $output, '<svg class="hws-stt-ring"' ), 'progress ring only belongs to the layered depth style' );
check( 'default' === ScrollToTop::normalize( [ 'style' => 'bogus' ] )['style'], 'unknown style falls back to round button' );
check( array_key_exists( 'ring_color', ScrollToTop::defaults() ), 'ring color setting exists' );
reset_button();
$depth = ScrollToTop::shortcode( [ 'style' => 'depth', 'ring_color' => '#ff0136', 'background' => '#1a1617' ] );
check( str_contains( $depth, 'hws-scroll-to-top--depth' ) && str_contains( $depth, '--hws-stt-ring:#ff0136' ), 'layered depth style with ring color variable' );
check( 1 === substr_count( $depth, '<button ' ) && str_contains( $depth, 'aria-label="Back to top"' ), 'layered depth keeps one labeled native button' );
check( 2 === substr_count( $depth, 'aria-hidden="true"' ) && str_contains( $depth, 'pathLength="100"' ), 'ring and icon are decorative; ring path is 100 units' );
check( 'depth' === ScrollToTop::save_settings( [ 'style' => 'depth', 'ring_color' => 'nope' ] )['style'] && '' === ScrollToTop::settings()['ring_color'], 'depth style saves and invalid ring color is rejected' );
$css = ScrollToTop::css();
check( str_contains( $css, '.hws-scroll-to-top--depth:active' ) && str_contains( $css, '.hws-scroll-to-top--depth:focus-visible' ), 'depth style has press and keyboard focus states' );
check( str_contains( $css, 'prefers-reduced-motion' ) && str_contains( $css, 'animation:none' ), 'depth motion stops under reduced motion' );
$js = file_get_contents( dirname( __DIR__ ) . '/assets/js/scroll-to-top.js' );
check( str_contains( $js, "behavior: reduced ? 'instant' : 'smooth'" ), 'reduced motion bypasses smooth scrolling' );
check( str_contains( $js, 'strokeDashoffset' ) && str_contains( $js, "'resize'" ), 'script fills the ring with scroll progress' );
check( str_contains( ScrollToTop::css(), '--e-global-color-primary' ) && str_contains( ScrollToTop::css(), 'max-width:767px' ), 'global color fallbacks and mobile rule' );
echo "PASS: Scroll to top, $count checks.\n";
