<?php
/**
 * Clean Maintenance – remove all settings when the plugin is deleted.
 *
 * @package CleanMaintenance
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( is_multisite() ) {
	$clean_maintenance_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $clean_maintenance_sites as $clean_maintenance_site ) {
		switch_to_blog( (int) $clean_maintenance_site );
		delete_option( 'clean_maintenance' );
		restore_current_blog();
	}
	delete_site_option( 'clean_maintenance_network_allow' );
	delete_site_option( 'clean_maintenance_network_host' );
	delete_site_option( 'clean_maintenance_home' );
	delete_site_option( 'clean_maintenance_home_token' );
} else {
	delete_option( 'clean_maintenance' );
}
