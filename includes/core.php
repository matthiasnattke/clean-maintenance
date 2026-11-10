<?php
/**
 * Clean Maintenance – settings, state and helpers.
 *
 * Every site stores its settings in the option "clean_maintenance".
 *
 * @package CleanMaintenance
 */

defined( 'ABSPATH' ) || exit;

/**
 * Load the bundled translations (German ships with the plugin).
 */
function clean_maintenance_load_textdomain() {
	load_plugin_textdomain( 'clean-maintenance', false, dirname( plugin_basename( CLEAN_MAINTENANCE_FILE ) ) . '/languages' );
}
add_action( 'init', 'clean_maintenance_load_textdomain' );

/**
 * Default settings.
 *
 * @return array
 */
function clean_maintenance_defaults() {
	return array(
		'mode'            => 'off',   // off | on | scheduled.
		'start'           => '',      // YYYY-MM-DDTHH:MM in the site's time zone.
		'end'             => '',
		'page'            => 0,
		'bg_image'        => 0,
		'bg_color'        => '',
		'exclude'         => "feed\nwp-login\nlogin",
		'roles'           => array( 'administrator', 'editor' ), // May see the site.
		'backend_roles'   => array(),  // May use the dashboard (administrators always).
		'redirect'        => '',       // Target for logged-in users without dashboard access.
		'bypass_bots'     => 0,        // Search engine bots see the normal site.
		'meta_robots'     => 'index',  // index | noindex.
		'notice'          => 1,        // Notice in the admin bar.
		'design'          => 'page',   // page | classic.
		'c_title'         => '',
		'c_heading'       => '',
		'c_heading_color' => '',
		'c_text'          => '',
		'c_text_color'    => '',
		'c_admin_link'    => 0,
		'rest'            => 'maintenance', // REST API for visitors: off | maintenance | always.
		'rest_allow'      => "oembed\npodlove\njetpack\nwpcom\nmy-jetpack\njetpack-boost\nkoko-analytics",
	);
}

/**
 * Settings of the current or another site (multisite).
 *
 * @param int $blog_id Site ID, 0 for the current site.
 * @return array
 */
function clean_maintenance_settings( $blog_id = 0 ) {
	static $cache = array();
	$blog_id = $blog_id ? (int) $blog_id : get_current_blog_id();
	if ( isset( $cache[ $blog_id ] ) ) {
		return $cache[ $blog_id ];
	}
	$switch = is_multisite() && get_current_blog_id() !== $blog_id;
	if ( $switch ) {
		switch_to_blog( $blog_id );
	}
	$o = get_option( 'clean_maintenance', array() );
	if ( $switch ) {
		restore_current_blog();
	}
	$cache[ $blog_id ] = array_merge( clean_maintenance_defaults(), is_array( $o ) ? $o : array() );
	return $cache[ $blog_id ];
}

/**
 * Set only the mode of a site (used by the network overview).
 *
 * @param int    $blog_id Site ID.
 * @param string $mode    off | on | scheduled.
 */
function clean_maintenance_set_mode( $blog_id, $mode ) {
	$switch = is_multisite() && get_current_blog_id() !== (int) $blog_id;
	if ( $switch ) {
		switch_to_blog( (int) $blog_id );
	}
	$o         = get_option( 'clean_maintenance', array() );
	$o         = is_array( $o ) ? $o : array();
	$o['mode'] = in_array( $mode, array( 'off', 'on', 'scheduled' ), true ) ? $mode : 'off';
	update_option( 'clean_maintenance', $o );
	if ( $switch ) {
		restore_current_blog();
	}
}

/**
 * Calendar value as timestamp (time zone of the site).
 *
 * @param string            $value Value like 2026-11-10T14:30.
 * @param DateTimeZone|null $tz    Time zone, default: site time zone.
 * @return int
 */
function clean_maintenance_ts( $value, $tz = null ) {
	if ( ! $value ) {
		return 0;
	}
	$d = date_create_immutable_from_format( 'Y-m-d\TH:i', (string) $value, $tz ? $tz : wp_timezone() );
	return $d ? $d->getTimestamp() : 0;
}

/**
 * Is maintenance mode active according to these settings?
 *
 * @param array|null        $s  Settings.
 * @param DateTimeZone|null $tz Time zone.
 * @return bool
 */
function clean_maintenance_is_active( $s = null, $tz = null ) {
	$s = $s ? $s : clean_maintenance_settings();
	if ( 'on' === $s['mode'] ) {
		return true;
	}
	if ( 'scheduled' !== $s['mode'] ) {
		return false;
	}
	$a = clean_maintenance_ts( $s['start'], $tz );
	$b = clean_maintenance_ts( $s['end'], $tz );
	if ( ! $a && ! $b ) {
		return false;
	}
	$now = time();
	return ( ! $a || $now >= $a ) && ( ! $b || $now < $b );
}

/**
 * State as array( active?, short sentence, state key ).
 *
 * State keys: off, on, active (scheduled and running), planned, ended.
 *
 * @param array|null        $s  Settings.
 * @param DateTimeZone|null $tz Time zone.
 * @return array
 */
function clean_maintenance_status( $s = null, $tz = null ) {
	$s = $s ? $s : clean_maintenance_settings();
	if ( 'on' === $s['mode'] ) {
		return array( true, __( 'On', 'clean-maintenance' ), 'on' );
	}
	if ( 'scheduled' !== $s['mode'] ) {
		return array( false, __( 'Off', 'clean-maintenance' ), 'off' );
	}
	$a   = clean_maintenance_ts( $s['start'], $tz );
	$b   = clean_maintenance_ts( $s['end'], $tz );
	$on  = clean_maintenance_is_active( $s, $tz );
	$key = $on ? 'active' : ( $b && $b <= time() ? 'ended' : 'planned' );
	$t   = array(
		'active'  => __( 'Active (scheduled)', 'clean-maintenance' ),
		'ended'   => __( 'Period ended', 'clean-maintenance' ),
		'planned' => __( 'Planned', 'clean-maintenance' ),
	)[ $key ];
	$z   = $tz ? $tz : wp_timezone();
	$fmt = get_option( 'date_format' ) . ', ' . get_option( 'time_format' );
	if ( $a && $b ) {
		/* translators: 1: start date and time, 2: end date and time. */
		$t .= ' – ' . sprintf( __( 'from %1$s to %2$s', 'clean-maintenance' ), wp_date( $fmt, $a, $z ), wp_date( $fmt, $b, $z ) );
	} elseif ( $a ) {
		/* translators: %s: start date and time. */
		$t .= ' – ' . sprintf( __( 'from %s', 'clean-maintenance' ), wp_date( $fmt, $a, $z ) );
	} elseif ( $b ) {
		/* translators: %s: end date and time. */
		$t .= ' – ' . sprintf( __( 'until %s', 'clean-maintenance' ), wp_date( $fmt, $b, $z ) );
	}
	return array( $on, $t, $key );
}
