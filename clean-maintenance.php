<?php
/**
 * Plugin Name:       Clean Maintenance
 * Plugin URI:        https://couch-blog.de/projekte/clean-maintenance/
 * Description:       A lean maintenance mode: your own maintenance page with background, permanent or scheduled, exceptions for slugs, IP addresses and roles, HTTP 503 for search engines and an optional REST API lock. In a multisite network every site has its own maintenance mode, and the network admin shows them all at a glance.
 * Version:           0.6.1
 * Network:           true
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Matthias Nattke
 * Author URI:        https://couch-blog.de
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       clean-maintenance
 * Domain Path:       /languages
 *
 * @package CleanMaintenance
 */

defined( 'ABSPATH' ) || exit;

define( 'CLEAN_MAINTENANCE_VERSION', '0.6.1' );
define( 'CLEAN_MAINTENANCE_FILE', __FILE__ );

require_once __DIR__ . '/includes/core.php';
require_once __DIR__ . '/includes/frontend.php';

if ( is_admin() ) {
	require_once __DIR__ . '/includes/admin.php';
	if ( is_multisite() ) {
		require_once __DIR__ . '/includes/network.php';
	}
}
