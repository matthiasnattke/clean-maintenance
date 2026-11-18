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
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Removing the plugin's own cached values.
	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE meta_key LIKE %s OR meta_key LIKE %s', $wpdb->sitemeta, $wpdb->esc_like( '_site_transient_clean_maintenance_' ) . '%', $wpdb->esc_like( '_site_transient_timeout_clean_maintenance_' ) . '%' ) );
} else {
	delete_option( 'clean_maintenance' );
	if ( ! function_exists( 'CleanShortLinks\\site_settings' ) ) {
		delete_option( 'clean_maintenance_short_links' ); // Shared with Clean Short Links – kept while that is installed.
	}
}
