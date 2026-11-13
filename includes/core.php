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
	// Bundled German translation; once listed on WordPress.org, translate.wordpress.org takes over.
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
		'exclude'         => "feed\nwp-login\nlogin", // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- setting name, not a query argument.
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
 * How short links behave with maintenance mode – one rule for both Clean Maintenance and
 * Clean Short Links, stored once per site.
 *
 * during: redirect (as usual), open (targets of short links are let through), pause (short
 *         links show the maintenance page too).
 * dead:   never, temporary (for temporarily unreachable targets), always (instead of the
 *         error page for every short link that does not work) – show the maintenance page.
 *
 * @param mixed $in Raw value.
 * @return array{during:string,dead:string}
 */
function clean_maintenance_sanitize_short_link_rules( $in ) {
	$in = is_array( $in ) ? $in : array();
	return array(
		'during' => in_array( $in['during'] ?? '', array( 'redirect', 'open', 'pause' ), true ) ? $in['during'] : 'redirect',
		'dead'   => in_array( $in['dead'] ?? '', array( 'never', 'temporary', 'always' ), true ) ? $in['dead'] : 'never',
	);
}

/**
 * The short link rules of the current site.
 *
 * @return array{during:string,dead:string}
 */
function clean_maintenance_short_link_rules() {
	return clean_maintenance_sanitize_short_link_rules( get_option( 'clean_maintenance_short_links', array() ) );
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

/**
 * Client IP address as seen by the web server.
 *
 * @return string
 */
function clean_maintenance_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
}

/**
 * Whether a line is an IP address or an address range (CIDR, IPv4 or IPv6).
 *
 * @param string $line E.g. 203.0.113.7, 192.168.178.0/24 or 2001:db8::/48.
 * @return bool
 */
function clean_maintenance_is_ip_rule( $line ) {
	$parts = explode( '/', trim( (string) $line ), 2 );
	if ( ! filter_var( $parts[0], FILTER_VALIDATE_IP ) ) {
		return false;
	}
	if ( ! isset( $parts[1] ) ) {
		return true;
	}
	$max = false !== strpos( $parts[0], ':' ) ? 128 : 32;
	return ctype_digit( $parts[1] ) && (int) $parts[1] <= $max;
}

/**
 * Whether an IP address matches an address or range.
 *
 * @param string $ip   Client address.
 * @param string $rule Address or CIDR range.
 * @return bool
 */
function clean_maintenance_ip_matches( $ip, $rule ) {
	if ( ! $ip || ! clean_maintenance_is_ip_rule( $rule ) ) {
		return false;
	}
	$parts = explode( '/', trim( $rule ), 2 );
	$a     = inet_pton( $ip );
	$b     = inet_pton( $parts[0] );
	if ( false === $a || false === $b || strlen( $a ) !== strlen( $b ) ) {
		return false;
	}
	$bits  = isset( $parts[1] ) ? (int) $parts[1] : strlen( $a ) * 8;
	$bytes = intdiv( $bits, 8 );
	if ( substr( $a, 0, $bytes ) !== substr( $b, 0, $bytes ) ) {
		return false;
	}
	$rest = $bits % 8;
	if ( ! $rest ) {
		return true;
	}
	$mask = ( 0xFF << ( 8 - $rest ) ) & 0xFF;
	return ( ord( $a[ $bytes ] ) & $mask ) === ( ord( $b[ $bytes ] ) & $mask );
}

/**
 * Whether an IP address matches any address or range in a list
 * (one per line, comments with #, other lines are ignored).
 *
 * @param string $ip   Client address.
 * @param string $list List of rules.
 * @return bool
 */
function clean_maintenance_ip_in_list( $ip, $list ) {
	foreach ( preg_split( '/\R/', (string) $list ) as $line ) {
		$line = trim( preg_replace( '/#.*$/', '', $line ) );
		if ( '' !== $line && clean_maintenance_ip_matches( $ip, $line ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Addresses and ranges that may see every site of the network
 * (Network Admin → Settings → Maintenance mode).
 *
 * @return string
 */
function clean_maintenance_network_allow() {
	return is_multisite() ? (string) get_site_option( 'clean_maintenance_network_allow', '' ) : '';
}

/**
 * Keep only valid addresses and ranges (plus comments) in an IP list.
 *
 * @param string $text Raw input.
 * @return string
 */
function clean_maintenance_sanitize_ip_list( $text ) {
	$out = array();
	foreach ( preg_split( '/\R/', sanitize_textarea_field( (string) $text ) ) as $line ) {
		$rule = trim( preg_replace( '/#.*$/', '', $line ) );
		if ( '' === $rule ? '' !== trim( $line ) : clean_maintenance_is_ip_rule( $rule ) ) {
			$out[] = trim( $line );
		}
	}
	return implode( "\n", $out );
}

/**
 * Whether an address is private or local (home network, VPN, loopback).
 *
 * @param string $ip Address.
 * @return bool
 */
function clean_maintenance_is_private_ip( $ip ) {
	return (bool) filter_var( $ip, FILTER_VALIDATE_IP ) && ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
}

/**
 * Whether a list contains private ranges – they only match when requests
 * really travel through a VPN tunnel or the local network.
 *
 * @param string $list List of rules.
 * @return bool
 */
function clean_maintenance_list_has_private( $list ) {
	foreach ( preg_split( '/\R/', (string) $list ) as $line ) {
		$rule = explode( '/', trim( preg_replace( '/#.*$/', '', $line ) ) )[0];
		if ( '' !== $rule && clean_maintenance_is_private_ip( $rule ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Whether a value is a host name such as example.myfritz.net.
 *
 * @param string $host Host name.
 * @return bool
 */
function clean_maintenance_is_hostname( $host ) {
	$host = trim( (string) $host );
	return '' !== $host && false !== strpos( $host, '.' ) && ! filter_var( $host, FILTER_VALIDATE_IP )
		&& (bool) filter_var( $host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME );
}

/**
 * Turn whatever was pasted into a bare host name:
 * "https://abc.myfritz.net:42243/path" becomes "abc.myfritz.net".
 *
 * @param string $value Pasted value (URL, host with port, …).
 * @return string Host name, or '' when nothing usable is left.
 */
function clean_maintenance_normalize_host( $value ) {
	$value = strtolower( trim( (string) $value ) );
	$value = preg_replace( '#^[a-z][a-z0-9+.-]*://#', '', $value ); // Scheme.
	$value = preg_replace( '#^[^@/]*@#', '', $value );              // user:password@.
	$value = preg_replace( '#[/?\#].*$#', '', $value );            // Path, query, fragment.
	$value = preg_replace( '#:\d*$#', '', $value );                // Port.
	$value = rtrim( $value, '.' );
	return clean_maintenance_is_hostname( $value ) ? $value : '';
}

/**
 * Network-wide home address: a host name (MyFRITZ, DynDNS) that always points
 * to the current public address of the home network.
 *
 * @return array{host: string, v6: int} Host name and IPv6 prefix length.
 */
function clean_maintenance_network_host() {
	if ( ! is_multisite() ) {
		return array( 'host' => '', 'v6' => 56 );
	}
	$o  = get_site_option( 'clean_maintenance_network_host', array() );
	$o  = is_array( $o ) ? $o : array();
	$v6 = (int) ( $o['v6'] ?? 56 );
	return array(
		'host' => clean_maintenance_is_hostname( $o['host'] ?? '' ) ? (string) $o['host'] : '',
		'v6'   => in_array( $v6, array( 48, 56, 64, 128 ), true ) ? $v6 : 56,
	);
}

/**
 * Current addresses of a host name (A and AAAA records), cached for five minutes.
 *
 * @param string $host  Host name.
 * @param bool   $fresh Skip the cache.
 * @return string[]
 */
function clean_maintenance_resolve_host( $host, $fresh = false ) {
	if ( ! clean_maintenance_is_hostname( $host ) ) {
		return array();
	}
	$key    = 'clean_maintenance_host_' . md5( strtolower( $host ) );
	$cached = $fresh ? false : get_site_transient( $key );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$ips = array();
	if ( function_exists( 'dns_get_record' ) ) {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- an unknown name must not print a warning.
		$records = @dns_get_record( $host, DNS_A | DNS_AAAA );
		foreach ( (array) $records as $r ) {
			$ip = $r['ip'] ?? ( $r['ipv6'] ?? '' );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				$ips[] = $ip;
			}
		}
	}
	if ( ! $ips ) {
		$ips = (array) gethostbynamel( $host );
	}
	$ips = array_values( array_unique( array_filter( $ips ) ) );
	set_site_transient( $key, $ips, $ips ? 5 * MINUTE_IN_SECONDS : MINUTE_IN_SECONDS );
	return $ips;
}

/**
 * Whether an address belongs to a resolved home address: IPv4 exactly,
 * IPv6 within the same prefix (devices in the home network share it).
 *
 * @param string   $ip        Client address.
 * @param string[] $addresses Resolved addresses.
 * @param int      $v6        IPv6 prefix length.
 * @return bool
 */
function clean_maintenance_host_matches( $ip, $addresses, $v6 = 56 ) {
	foreach ( (array) $addresses as $a ) {
		$rule = false !== strpos( $a, ':' ) ? $a . '/' . (int) $v6 : $a;
		if ( clean_maintenance_ip_matches( $ip, $rule ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Whether an address may see every site of the network (fixed list or home address).
 *
 * @param string $ip Client address.
 * @return bool
 */
function clean_maintenance_network_allowed( $ip ) {
	if ( ! $ip || ! is_multisite() ) {
		return false;
	}
	if ( clean_maintenance_ip_in_list( $ip, clean_maintenance_network_allow() ) ) {
		return true;
	}
	$home = clean_maintenance_home_report();
	if ( clean_maintenance_home_matches( $ip, $home ) ) {
		return true;
	}
	$h = clean_maintenance_network_host();
	return $h['host'] && clean_maintenance_host_matches( $ip, clean_maintenance_resolve_host( $h['host'] ), $h['v6'] );
}

/*
 * ===== Home network reported by the router (FRITZ!Box DynDNS) =====
 *
 * The router calls a secret link whenever its addresses change and sends the
 * IPv6 prefix of the home network (<ip6lanprefix>) and its IPv4 address
 * (<ipaddr>). Nothing in the home network has to be reachable from outside.
 */

/**
 * Last report of the router.
 *
 * @return array{prefix: string, v4: string, time: int}
 */
function clean_maintenance_home_report() {
	$o = is_multisite() ? get_site_option( 'clean_maintenance_home', array() ) : array();
	$o = is_array( $o ) ? $o : array();
	return array(
		'prefix' => (string) ( $o['prefix'] ?? '' ),
		'v4'     => (string) ( $o['v4'] ?? '' ),
		'time'   => (int) ( $o['time'] ?? 0 ),
	);
}

/**
 * Secret of the report link; created on first use.
 *
 * @param bool $create Create one if there is none yet.
 * @return string
 */
function clean_maintenance_home_token( $create = false ) {
	if ( ! is_multisite() ) {
		return '';
	}
	$t = (string) get_site_option( 'clean_maintenance_home_token', '' );
	if ( '' === $t && $create ) {
		$t = wp_generate_password( 32, false );
		update_site_option( 'clean_maintenance_home_token', $t );
	}
	return $t;
}

/**
 * Link the router calls, with the FRITZ!Box placeholders.
 *
 * @return string
 */
function clean_maintenance_home_url() {
	$t = clean_maintenance_home_token( true );
	// Placeholders must stay literal – the router replaces them.
	return network_site_url( 'wp-admin/admin-post.php' ) . '?action=clean_maintenance_home&token=' . rawurlencode( $t ) . '&prefix=<ip6lanprefix>&ip4=<ipaddr>';
}

/**
 * Clean up a reported IPv6 prefix such as "2001:db8:1:700::/56".
 * Only global prefixes between /32 and /64 are accepted.
 *
 * @param string $value Reported value.
 * @return string Prefix or ''.
 */
function clean_maintenance_clean_prefix( $value ) {
	$value = trim( (string) $value );
	if ( ! clean_maintenance_is_ip_rule( $value ) || false === strpos( $value, '/' ) || false === strpos( $value, ':' ) ) {
		return '';
	}
	list( $addr, $len ) = explode( '/', $value );
	$len                = (int) $len;
	if ( $len < 32 || $len > 64 || clean_maintenance_is_private_ip( $addr ) ) {
		return '';
	}
	return strtolower( $addr ) . '/' . $len;
}

/**
 * Clean up a reported IPv4 address: only public addresses count. Behind
 * DS-Lite or carrier-grade NAT the router knows no public IPv4 address.
 *
 * @param string $value Reported value.
 * @return string
 */
function clean_maintenance_clean_v4( $value ) {
	$value = trim( (string) $value );
	if ( ! filter_var( $value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) || clean_maintenance_is_private_ip( $value )
		|| clean_maintenance_ip_matches( $value, '100.64.0.0/10' ) || clean_maintenance_ip_matches( $value, '192.0.0.0/24' ) ) {
		return '';
	}
	return $value;
}

/**
 * Whether an address belongs to the reported home network.
 *
 * @param string $ip   Client address.
 * @param array  $home Report (see clean_maintenance_home_report()).
 * @return bool
 */
function clean_maintenance_home_matches( $ip, $home ) {
	return ( '' !== $home['prefix'] && clean_maintenance_ip_matches( $ip, $home['prefix'] ) )
		|| ( '' !== $home['v4'] && $ip === $home['v4'] );
}

/**
 * Merge a report into the previous one: empty values keep what an earlier call sent.
 *
 * @param array  $old    Previous report.
 * @param string $prefix Reported IPv6 prefix or ''.
 * @param string $v4     Reported IPv4 address or ''.
 * @param int    $time   Time of the report.
 * @return array
 */
function clean_maintenance_merge_home_report( $old, $prefix, $v4, $time ) {
	return array(
		'prefix' => '' !== $prefix ? $prefix : (string) ( $old['prefix'] ?? '' ),
		'v4'     => '' !== $v4 ? $v4 : (string) ( $old['v4'] ?? '' ),
		'time'   => (int) $time,
	);
}

/**
 * Keep the last calls of the router for the status display (newest first).
 *
 * @param string $from   Address the call came from.
 * @param string $prefix Raw value of "prefix".
 * @param string $v4     Raw value of "ip4".
 */
function clean_maintenance_log_home_call( $from, $prefix, $v4 ) {
	$log = get_site_option( 'clean_maintenance_home_log', array() );
	$log = is_array( $log ) ? $log : array();
	array_unshift(
		$log,
		array(
			'time'   => time(),
			'from'   => $from,
			'prefix' => $prefix,
			'ip4'    => $v4,
		)
	);
	update_site_option( 'clean_maintenance_home_log', array_slice( $log, 0, 4 ) );
}

/**
 * Receive a report from the router (no login; protected by the secret).
 */
function clean_maintenance_receive_home_report() {
	nocache_headers();
	header( 'Content-Type: text/plain; charset=utf-8' );
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- called by a router, protected by the secret token.
	$token = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );
	$known = clean_maintenance_home_token();
	if ( '' === $known || ! hash_equals( $known, $token ) ) {
		status_header( 403 );
		echo 'badauth';
		exit;
	}
	$raw_prefix = substr( sanitize_text_field( wp_unslash( $_GET['prefix'] ?? '' ) ), 0, 64 );
	$raw_v4     = substr( sanitize_text_field( wp_unslash( $_GET['ip4'] ?? '' ) ), 0, 64 );
	// phpcs:enable
	$from   = clean_maintenance_client_ip();
	$prefix = clean_maintenance_clean_prefix( $raw_prefix );
	$v4     = clean_maintenance_clean_v4( $raw_v4 );
	if ( '' === $v4 ) {
		// No usable IPv4 value: a call over IPv4 comes from the router's public address itself.
		$v4 = clean_maintenance_clean_v4( $from );
	}
	clean_maintenance_log_home_call( $from, $raw_prefix, $raw_v4 );
	if ( '' === $prefix && '' === $v4 ) {
		status_header( 400 );
		echo 'noaddress';
		exit;
	}
	// The FRITZ!Box sends IPv4 and IPv6 in separate calls – keep what the other call reported.
	$old = clean_maintenance_home_report();
	update_site_option(
		'clean_maintenance_home',
		clean_maintenance_merge_home_report( $old, $prefix, $v4, time() )
	);
	echo esc_html( trim( 'good ' . ( $v4 ? $v4 : $prefix ) ) );
	exit;
}
add_action( 'admin_post_nopriv_clean_maintenance_home', 'clean_maintenance_receive_home_report' );
add_action( 'admin_post_clean_maintenance_home', 'clean_maintenance_receive_home_report' );

/*
 * ===== Access pass: a cookie that lets one browser through =====
 *
 * Works regardless of the address (iCloud Private Relay, mobile, travelling).
 * A browser gets it from a secret link or – optionally – when a network
 * administrator logs in; logging out does not remove it. Renewing the secret
 * revokes every pass at once.
 */

const CLEAN_MAINTENANCE_PASS_COOKIE = 'clean_maintenance_pass';

/**
 * Secret of the access link; created on first use.
 *
 * @param bool $create Create one if there is none yet.
 * @return string
 */
function clean_maintenance_pass_token( $create = false ) {
	if ( ! is_multisite() ) {
		return '';
	}
	$t = (string) get_site_option( 'clean_maintenance_pass_token', '' );
	if ( '' === $t && $create ) {
		$t = wp_generate_password( 32, false );
		update_site_option( 'clean_maintenance_pass_token', $t );
	}
	return $t;
}

/**
 * Value of the pass cookie – derived from the secret, never the secret itself.
 *
 * @param string $token Secret.
 * @return string
 */
function clean_maintenance_pass_value( $token ) {
	return '' === $token ? '' : hash_hmac( 'sha256', 'clean-maintenance-pass', $token );
}

/**
 * Whether the current browser carries a valid pass.
 *
 * @return bool
 */
function clean_maintenance_has_pass() {
	$value = isset( $_COOKIE[ CLEAN_MAINTENANCE_PASS_COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ CLEAN_MAINTENANCE_PASS_COOKIE ] ) ) : '';
	$want  = clean_maintenance_pass_value( clean_maintenance_pass_token() );
	return '' !== $value && '' !== $want && hash_equals( $want, $value );
}

/**
 * Whether administrators get a pass when they log in (default: yes).
 *
 * @return bool
 */
function clean_maintenance_pass_on_login() {
	return is_multisite() && (bool) get_site_option( 'clean_maintenance_pass_login', 1 );
}

/**
 * Give the current browser a pass for half a year.
 */
function clean_maintenance_set_pass() {
	$value = clean_maintenance_pass_value( clean_maintenance_pass_token( true ) );
	if ( '' === $value || headers_sent() ) {
		return;
	}
	$_COOKIE[ CLEAN_MAINTENANCE_PASS_COOKIE ] = $value;
	setcookie(
		CLEAN_MAINTENANCE_PASS_COOKIE,
		$value,
		array(
			'expires'  => time() + 180 * DAY_IN_SECONDS,
			'path'     => '/',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		)
	);
}

/**
 * Secret access link (opens the network's main site and sets the pass).
 *
 * @return string
 */
function clean_maintenance_pass_url() {
	return add_query_arg( 'cm-pass', rawurlencode( clean_maintenance_pass_token( true ) ), network_home_url( '/' ) );
}

// The access link: set the pass and continue without the secret in the address bar.
add_action(
	'init',
	function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- protected by the secret token.
		$given = isset( $_GET['cm-pass'] ) ? sanitize_text_field( wp_unslash( $_GET['cm-pass'] ) ) : '';
		if ( '' === $given ) {
			return;
		}
		$token = clean_maintenance_pass_token();
		if ( 'off' === $given && ! headers_sent() ) {
			// Forget the pass in this browser, e.g. to see the sites like a visitor.
			setcookie( CLEAN_MAINTENANCE_PASS_COOKIE, '', time() - YEAR_IN_SECONDS, '/' );
		} elseif ( '' !== $token && hash_equals( $token, $given ) ) {
			clean_maintenance_set_pass();
		}
		nocache_headers();
		wp_safe_redirect( remove_query_arg( 'cm-pass' ) );
		exit;
	},
	1
);

// Network administrators: pass at login, and on every site they visit while logged in.
add_action(
	'wp_login',
	function ( $login, $user ) {
		if ( clean_maintenance_pass_on_login() && is_super_admin( $user->ID ) ) {
			clean_maintenance_set_pass();
		}
	},
	10,
	2
);
add_action(
	'init',
	function () {
		if ( clean_maintenance_pass_on_login() && is_user_logged_in() && is_super_admin() && ! clean_maintenance_has_pass() ) {
			clean_maintenance_set_pass();
		}
	}
);
