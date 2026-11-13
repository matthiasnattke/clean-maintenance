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
		if ( ! function_exists( 'CleanShortLinks\\site_settings' ) ) {
			delete_option( 'clean_maintenance_short_links' ); // Shared with Clean Short Links – kept while that is installed.
		}
		restore_current_blog();
	}
	delete_site_option( 'clean_maintenance_network_allow' );
	delete_site_option( 'clean_maintenance_network_host' );
	delete_site_option( 'clean_maintenance_home' );
	delete_site_option( 'clean_maintenance_home_token' );
	delete_site_option( 'clean_maintenance_home_log' );
	delete_site_option( 'clean_maintenance_pass_token' );
	delete_site_option( 'clean_maintenance_pass_login' );
} else {
	delete_option( 'clean_maintenance' );
	if ( ! function_exists( 'CleanShortLinks\\site_settings' ) ) {
		delete_option( 'clean_maintenance_short_links' ); // Shared with Clean Short Links – kept while that is installed.
	}
}
