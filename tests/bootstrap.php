<?php
/**
 * Minimal WordPress stand-ins so the plugin logic can be tested without a WordPress install.
 *
 * Only what the tested functions need. Anything that would touch the database
 * reads from the global $cm_test_options instead.
 *
 * @package CleanMaintenance
 */

// phpcs:disable

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );

$GLOBALS['cm_test_options']  = array();
$GLOBALS['cm_test_is_feed']  = false;
$GLOBALS['cm_test_settings_errors'] = array();

function add_action( ...$args ) {}
function add_filter( ...$args ) {}
function load_plugin_textdomain( ...$args ) {}
function plugin_basename( $file ) { return 'clean-maintenance/' . basename( $file ); }
function __( $text, $domain = 'default' ) { return $text; }
function esc_html__( $text, $domain = 'default' ) { return $text; }
function _n( $single, $plural, $n, $domain = 'default' ) { return 1 === $n ? $single : $plural; }
function get_option( $name, $default = false ) {
	$defaults = array(
		'date_format' => 'Y-m-d',
		'time_format' => 'H:i',
	);
	if ( array_key_exists( $name, $GLOBALS['cm_test_options'] ) ) {
		return $GLOBALS['cm_test_options'][ $name ];
	}
	return $defaults[ $name ] ?? $default;
}
function update_option( $name, $value ) { $GLOBALS['cm_test_options'][ $name ] = $value; return true; }
function wp_timezone() { return new DateTimeZone( 'Europe/Berlin' ); }
function wp_timezone_string() { return 'Europe/Berlin'; }
function wp_date( $format, $ts, $tz = null ) { return ( new DateTimeImmutable( '@' . $ts ) )->setTimezone( $tz ? $tz : wp_timezone() )->format( $format ); }
function get_current_blog_id() { return 1; }
function is_multisite() { return false; }
function get_site_option( $name, $default = false ) { return $default; }
function is_feed() { return $GLOBALS['cm_test_is_feed']; }
function wp_unslash( $v ) { return is_string( $v ) ? stripslashes( $v ) : $v; }
function sanitize_text_field( $v ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', wp_strip_all_tags( (string) $v ) ) ); }
function sanitize_textarea_field( $v ) { return trim( wp_strip_all_tags( (string) $v ) ); }
function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_hex_color( $c ) { return preg_match( '|^#([A-Fa-f0-9]{3}){1,2}$|', (string) $c ) ? $c : null; }
function esc_url_raw( $u ) { return filter_var( $u, FILTER_VALIDATE_URL ) ? $u : ''; }
function wp_kses_post( $v ) { return strip_tags( (string) $v, '<p><a><strong><em><br>' ); }
function add_settings_error( $setting, $code, $message ) { $GLOBALS['cm_test_settings_errors'][] = $code; }
function wp_roles() {
	return new class() {
		public function get_names() {
			return array(
				'administrator' => 'Administrator',
				'editor'        => 'Editor',
				'subscriber'    => 'Subscriber',
			);
		}
	};
}

define( 'CLEAN_MAINTENANCE_FILE', dirname( __DIR__ ) . '/clean-maintenance.php' );
require dirname( __DIR__ ) . '/includes/core.php';
require dirname( __DIR__ ) . '/includes/frontend.php';
require dirname( __DIR__ ) . '/includes/admin.php';
