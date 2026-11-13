<?php
/**
 * Clean Maintenance – tests without dependencies.
 *
 *   php tests/run.php
 *
 * Exit code 0 when all tests pass, 1 otherwise.
 *
 * @package CleanMaintenance
 */

// phpcs:disable

require __DIR__ . '/bootstrap.php';

$cm_pass = 0;
$cm_fail = array();

function test( $name, callable $fn ) {
	global $cm_pass, $cm_fail;
	$GLOBALS['cm_test_options']         = array();
	$GLOBALS['cm_test_is_feed']         = false;
	$GLOBALS['cm_test_settings_errors'] = array();
	try {
		$fn();
		++$cm_pass;
		echo "  ✓ {$name}\n";
	} catch ( Throwable $e ) {
		$cm_fail[] = $name;
		echo "  ✗ {$name}\n    " . $e->getMessage() . "\n";
	}
}

function same( $expected, $actual, $label = '' ) {
	if ( $expected !== $actual ) {
		throw new Exception( ( $label ? $label . ': ' : '' ) . 'expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
	}
}

function settings( array $over = array() ) {
	return array_merge( clean_maintenance_defaults(), $over );
}

function at( $offset ) {
	return ( new DateTimeImmutable( '@' . ( time() + $offset ) ) )->setTimezone( wp_timezone() )->format( 'Y-m-d\TH:i' );
}

echo "Settings and scheduling\n";

test( 'Defaults contain all keys', function () {
	$d = clean_maintenance_defaults();
	foreach ( array( 'mode', 'start', 'end', 'page', 'exclude', 'roles', 'rest', 'rest_allow', 'design' ) as $k ) {
		same( true, array_key_exists( $k, $d ), $k );
	}
	same( 'off', $d['mode'] );
	same( 'maintenance', $d['rest'] );
} );

test( 'Calendar value is read in the site time zone', function () {
	$expected = ( new DateTimeImmutable( '2026-11-10 14:30', new DateTimeZone( 'Europe/Berlin' ) ) )->getTimestamp();
	same( $expected, clean_maintenance_ts( '2026-11-10T14:30' ) );
	same( 0, clean_maintenance_ts( '' ) );
	same( 0, clean_maintenance_ts( 'no date' ) );
} );

test( 'Mode off and on', function () {
	same( false, clean_maintenance_is_active( settings( array( 'mode' => 'off' ) ) ) );
	same( true, clean_maintenance_is_active( settings( array( 'mode' => 'on' ) ) ) );
} );

test( 'Scheduled: running, planned, ended, without period', function () {
	same( true, clean_maintenance_is_active( settings( array( 'mode' => 'scheduled', 'start' => at( -3600 ), 'end' => at( 3600 ) ) ) ), 'running' );
	same( false, clean_maintenance_is_active( settings( array( 'mode' => 'scheduled', 'start' => at( 7200 ) ) ) ), 'planned' );
	same( false, clean_maintenance_is_active( settings( array( 'mode' => 'scheduled', 'end' => at( -7200 ) ) ) ), 'ended' );
	same( true, clean_maintenance_is_active( settings( array( 'mode' => 'scheduled', 'end' => at( 7200 ) ) ) ), 'end only' );
	same( false, clean_maintenance_is_active( settings( array( 'mode' => 'scheduled' ) ) ), 'without period' );
} );

test( 'Status returns the right state keys', function () {
	same( 'off', clean_maintenance_status( settings() )[2] );
	same( 'on', clean_maintenance_status( settings( array( 'mode' => 'on' ) ) )[2] );
	same( 'active', clean_maintenance_status( settings( array( 'mode' => 'scheduled', 'start' => at( -60 ), 'end' => at( 3600 ) ) ) )[2] );
	same( 'planned', clean_maintenance_status( settings( array( 'mode' => 'scheduled', 'start' => at( 7200 ) ) ) )[2] );
	same( 'ended', clean_maintenance_status( settings( array( 'mode' => 'scheduled', 'start' => at( -7200 ), 'end' => at( -3600 ) ) ) )[2] );
	same( true, false !== strpos( clean_maintenance_status( settings( array( 'mode' => 'scheduled', 'start' => at( 7200 ) ) ) )[1], 'from' ), 'text names the start' );
} );

test( 'Stored settings are merged with the defaults', function () {
	update_option( 'clean_maintenance', array( 'mode' => 'on' ) );
	$s = clean_maintenance_settings( 7 ); // Own site ID so the static cache does not apply.
	same( 'on', $s['mode'] );
	same( 'maintenance', $s['rest'] );
} );

echo "\nExceptions and bots\n";

test( 'A slug from the exception list is let through', function () {
	$_SERVER['REQUEST_URI'] = '/725-jahre-peitz/';
	same( true, clean_maintenance_excluded( settings( array( 'exclude' => "feed\n725-jahre-peitz # parade" ) ) ) );
	$_SERVER['REQUEST_URI'] = '/blog/';
	same( false, clean_maintenance_excluded( settings( array( 'exclude' => "feed\n725-jahre-peitz" ) ) ) );
} );

test( 'Comments and blank lines are ignored', function () {
	$_SERVER['REQUEST_URI'] = '/irgendwas/';
	same( false, clean_maintenance_excluded( settings( array( 'exclude' => "# just a comment\n\n   " ) ) ) );
} );

test( 'IP address from the exception list', function () {
	$_SERVER['REQUEST_URI'] = '/';
	$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
	same( true, clean_maintenance_excluded( settings( array( 'exclude' => '203.0.113.7' ) ) ) );
	$_SERVER['REMOTE_ADDR'] = '203.0.113.8';
	same( false, clean_maintenance_excluded( settings( array( 'exclude' => '203.0.113.7' ) ) ) );
} );

test( 'The feed is detected via is_feed', function () {
	$_SERVER['REQUEST_URI']     = '/?feed=rss2';
	$GLOBALS['cm_test_is_feed'] = true;
	same( true, clean_maintenance_excluded( settings( array( 'exclude' => 'feed' ) ) ) );
} );

test( 'Search engine bots are detected', function () {
	$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
	same( true, clean_maintenance_is_bot() );
	$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 Safari/605.1.15';
	same( false, clean_maintenance_is_bot() );
} );

echo "\nAddress ranges\n";

test( 'IPv4 ranges match only inside the range', function () {
	same( true, clean_maintenance_ip_matches( '192.168.178.33', '192.168.178.0/24' ), 'home network' );
	same( false, clean_maintenance_ip_matches( '192.168.179.1', '192.168.178.0/24' ), 'next network' );
	same( true, clean_maintenance_ip_matches( '10.8.0.5', '10.8.0.0/24' ), 'VPN' );
	same( true, clean_maintenance_ip_matches( '10.8.0.5', '10.0.0.0/8' ), 'wide range' );
	same( true, clean_maintenance_ip_matches( '203.0.113.7', '203.0.113.7' ), 'single address' );
	same( false, clean_maintenance_ip_matches( '203.0.113.8', '203.0.113.7/32' ), 'host mask' );
	same( true, clean_maintenance_ip_matches( '203.0.113.130', '203.0.113.128/25' ), 'odd mask' );
	same( false, clean_maintenance_ip_matches( '203.0.113.127', '203.0.113.128/25' ), 'odd mask outside' );
} );

test( 'IPv6 ranges and mixed families', function () {
	same( true, clean_maintenance_ip_matches( '2001:db8:1234:5600::42', '2001:db8:1234:5600::/56' ) );
	same( false, clean_maintenance_ip_matches( '2001:db8:1234:5700::42', '2001:db8:1234:5600::/56' ) );
	same( false, clean_maintenance_ip_matches( '192.168.178.33', '2001:db8::/32' ), 'IPv4 vs. IPv6 range' );
	same( false, clean_maintenance_ip_matches( '', '192.168.178.0/24' ), 'no address' );
} );

test( 'Invalid rules are not treated as addresses', function () {
	same( true, clean_maintenance_is_ip_rule( '192.168.178.0/24' ) );
	same( true, clean_maintenance_is_ip_rule( '2001:db8::/128' ) );
	same( false, clean_maintenance_is_ip_rule( '192.168.178.0/33' ) );
	same( false, clean_maintenance_is_ip_rule( '725-jahre-peitz' ) );
	same( false, clean_maintenance_is_ip_rule( '10.8.0.0/abc' ) );
} );

test( 'A range in the exception list lets the whole network through', function () {
	$_SERVER['REQUEST_URI'] = '/';
	$_SERVER['REMOTE_ADDR'] = '192.168.178.33';
	same( true, clean_maintenance_excluded( settings( array( 'exclude' => "feed\n192.168.178.0/24 # home" ) ) ) );
	$_SERVER['REMOTE_ADDR'] = '198.51.100.1';
	same( false, clean_maintenance_excluded( settings( array( 'exclude' => "feed\n192.168.178.0/24 # home" ) ) ) );
} );

test( 'Home address: IPv4 exactly, IPv6 within the prefix', function () {
	$home = array( '198.51.100.20', '2001:db8:4400:7e00::1' );
	same( true, clean_maintenance_host_matches( '198.51.100.20', $home ), 'IPv4 of the router' );
	same( false, clean_maintenance_host_matches( '198.51.100.21', $home ), 'other IPv4' );
	same( true, clean_maintenance_host_matches( '2001:db8:4400:7e07:abcd::9', $home, 56 ), 'device in the /56' );
	same( false, clean_maintenance_host_matches( '2001:db8:4400:7e07:abcd::9', $home, 64 ), 'other /64' );
	same( false, clean_maintenance_host_matches( '2001:db8:4400:7f00::9', $home, 56 ), 'outside the /56' );
	same( false, clean_maintenance_host_matches( '198.51.100.20', array() ), 'nothing resolved' );
} );

test( 'Pasted URLs become a bare host name', function () {
	same( 'jjnrknqfpjr5zu7i.myfritz.net', clean_maintenance_normalize_host( 'https://jjnrknqfpjr5zu7i.myfritz.net:42243' ) );
	same( 'abc.myfritz.net', clean_maintenance_normalize_host( '  HTTPS://Abc.MyFritz.net:42243/#/login?x=1 ' ) );
	same( 'abc.myfritz.net', clean_maintenance_normalize_host( 'abc.myfritz.net:8443' ) );
	same( 'abc.myfritz.net', clean_maintenance_normalize_host( 'http://user:pw@abc.myfritz.net/' ) );
	same( 'home.example.org', clean_maintenance_normalize_host( 'home.example.org.' ) );
	same( '', clean_maintenance_normalize_host( 'https://192.168.178.1:443' ) );
	same( '', clean_maintenance_normalize_host( 'not a host' ) );
} );

test( 'Host names and private ranges are recognised', function () {
	same( true, clean_maintenance_is_hostname( 'abc123.myfritz.net' ) );
	same( false, clean_maintenance_is_hostname( '192.168.178.1' ) );
	same( false, clean_maintenance_is_hostname( 'localhost' ) );
	same( false, clean_maintenance_is_hostname( 'not a host' ) );
	same( true, clean_maintenance_is_private_ip( '192.168.178.33' ) );
	same( true, clean_maintenance_is_private_ip( 'fd00::1' ) );
	same( false, clean_maintenance_is_private_ip( '8.8.8.8' ) );
	same( true, clean_maintenance_list_has_private( "# home\n192.168.178.0/24 # VPN" ) );
	same( false, clean_maintenance_list_has_private( "203.0.113.7\n# 10.0.0.0/8" ) );
} );

test( 'IP lists keep only valid rules and comments', function () {
	same( "192.168.178.0/24 # home\n# VPN\n10.8.0.0/24", clean_maintenance_sanitize_ip_list( "192.168.178.0/24 # home\n# VPN\n10.8.0.0/24\nnonsense\n10.0.0.0/99" ) );
	same( true, clean_maintenance_ip_in_list( '10.8.0.9', "# VPN\n10.8.0.0/24" ) );
	same( false, clean_maintenance_ip_in_list( '10.9.0.9', "# VPN\n10.8.0.0/24" ) );
} );

echo "\nREST-API\n";

test( 'Public namespaces', function () {
	$allow = clean_maintenance_defaults()['rest_allow'];
	same( true, clean_maintenance_rest_route_allowed( '/oembed/1.0/embed', $allow ), 'oembed' );
	same( true, clean_maintenance_rest_route_allowed( 'podlove-web-player/5.10.0', $allow ), 'podlove-web-player via podlove' );
	same( true, clean_maintenance_rest_route_allowed( '/jetpack/v4/connection', $allow ), 'jetpack' );
	same( false, clean_maintenance_rest_route_allowed( '/wp/v2/users', $allow ), 'wp/v2/users' );
	same( false, clean_maintenance_rest_route_allowed( '/', $allow ), 'index' );
	same( false, clean_maintenance_rest_route_allowed( '', $allow ), 'empty' );
} );

test( 'Comments, blank lines and slashes in the list', function () {
	same( true, clean_maintenance_rest_route_allowed( '/wp/v2/posts', "# comment\n\n /wp/v2/ # posts" ) );
	same( false, clean_maintenance_rest_route_allowed( '/wp/v2/posts', '# wp/v2' ) );
} );

test( 'Lock depending on the setting', function () {
	same( false, clean_maintenance_rest_locked( settings( array( 'rest' => 'off', 'mode' => 'on' ) ) ), 'open' );
	same( true, clean_maintenance_rest_locked( settings( array( 'rest' => 'maintenance', 'mode' => 'on' ) ) ), 'during maintenance' );
	same( false, clean_maintenance_rest_locked( settings( array( 'rest' => 'maintenance', 'mode' => 'off' ) ) ), 'without maintenance' );
	same( true, clean_maintenance_rest_locked( settings( array( 'rest' => 'always', 'mode' => 'off' ) ) ), 'always' );
} );

echo "\nInput sanitizing\n";

test( 'Invalid values fall back to safe defaults', function () {
	$out = clean_maintenance_sanitize(
		array(
			'mode'        => 'broken',
			'start'       => '10.11.2026',
			'rest'        => 'maybe',
			'bg_color'    => 'red',
			'roles'       => array( 'editor', 'hacker' ),
			'meta_robots' => 'whatever',
			'design'      => 'colourful',
			'page'        => '-5',
		)
	);
	same( 'off', $out['mode'] );
	same( '', $out['start'] );
	same( 'maintenance', $out['rest'] );
	same( '', $out['bg_color'] );
	same( array( 'editor' ), $out['roles'] );
	same( 'index', $out['meta_robots'] );
	same( 'page', $out['design'] );
	same( 5, $out['page'] );
} );

test( 'Valid values are kept', function () {
	$out = clean_maintenance_sanitize(
		array(
			'mode'       => 'scheduled',
			'start'      => '2026-11-10T08:00',
			'end'        => '2026-11-10T18:00',
			'rest'       => 'always',
			'rest_allow' => "oembed\npodlove",
			'bg_color'   => '#BFE1FF',
			'notice'     => '1',
		)
	);
	same( 'scheduled', $out['mode'] );
	same( '2026-11-10T08:00', $out['start'] );
	same( 'always', $out['rest'] );
	same( "oembed\npodlove", $out['rest_allow'] );
	same( '#BFE1FF', $out['bg_color'] );
	same( 1, $out['notice'] );
	same( array(), $GLOBALS['cm_test_settings_errors'] );
} );

test( 'End before start adds a settings error', function () {
	clean_maintenance_sanitize( array( 'mode' => 'scheduled', 'start' => '2026-11-10T18:00', 'end' => '2026-11-10T08:00' ) );
	same( array( 'when' ), $GLOBALS['cm_test_settings_errors'] );
} );

echo "\nVersion\n";

test( 'Version number matches everywhere', function () {
	$root   = dirname( __DIR__ );
	$main   = file_get_contents( $root . '/clean-maintenance.php' );
	$readme = file_get_contents( $root . '/readme.txt' );
	preg_match( '/^ \* Version:\s+(\S+)/m', $main, $h );
	preg_match( "/define\( 'CLEAN_MAINTENANCE_VERSION', '([^']+)' \)/", $main, $c );
	preg_match( '/^Stable tag:\s+(\S+)/m', $readme, $r );
	same( $h[1], $c[1], 'constant' );
	same( $h[1], $r[1], 'Stable tag in readme.txt' );
	same( 1, preg_match( '/^= ' . preg_quote( $h[1], '/' ) . ' =$/m', $readme ), 'changelog entry' );
} );

echo "\n" . $cm_pass . ' passed, ' . count( $cm_fail ) . " failed\n";
exit( $cm_fail ? 1 : 0 );
