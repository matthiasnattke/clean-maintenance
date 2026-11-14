<?php
/**
 * Dashboard widget of a site: status, quick switch, maintenance page and design – the
 * everyday settings without leaving the dashboard.
 *
 * @package CleanMaintenance
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_dashboard_setup',
	function () {
		if ( current_user_can( 'manage_options' ) ) {
			wp_add_dashboard_widget( 'clean_maintenance_site', __( 'Maintenance mode', 'clean-maintenance' ), 'clean_maintenance_site_widget' );
		}
	}
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( 'index.php' === $hook && ! is_network_admin() && current_user_can( 'manage_options' ) ) {
			wp_enqueue_style( 'clean-maintenance-admin', plugins_url( 'assets/admin.css', CLEAN_MAINTENANCE_FILE ), array(), clean_maintenance_asset_ver( 'admin.css' ) );
		}
	}
);

/**
 * The widget.
 */
function clean_maintenance_site_widget() {
	$s      = clean_maintenance_settings();
	$status = clean_maintenance_status( $s );
	$pages  = get_pages(
		array(
			'post_status' => array( 'publish', 'private', 'draft' ),
			'sort_column' => 'post_title',
		)
	);
	$saved  = isset( $_GET['cm-widget'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only shows a notice.
	$modes  = array(
		'off'       => __( 'Off', 'clean-maintenance' ),
		'on'        => __( 'On', 'clean-maintenance' ),
		'scheduled' => __( 'Scheduled', 'clean-maintenance' ),
	);
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cm-widget cm-site-widget">
		<?php wp_nonce_field( 'clean_maintenance_widget' ); ?>
		<input type="hidden" name="action" value="clean_maintenance_widget">
		<p class="cm-pill<?php echo $status[0] ? ' is-active' : ''; ?> cm-state-<?php echo esc_attr( $status[2] ); ?>"><span class="cm-dot" aria-hidden="true"></span><?php echo esc_html( $status[1] ); ?></p>
		<?php if ( $saved ) : ?>
			<p class="cm-widget-saved"><?php esc_html_e( 'Saved.', 'clean-maintenance' ); ?></p>
		<?php endif; ?>

		<fieldset class="cm-segment cm-widget-modes">
			<legend class="screen-reader-text"><?php esc_html_e( 'Mode', 'clean-maintenance' ); ?></legend>
			<?php foreach ( $modes as $v => $label ) : ?>
				<label><input type="radio" name="mode" value="<?php echo esc_attr( $v ); ?>" <?php checked( $s['mode'], $v ); ?>><span><?php echo esc_html( $label ); ?></span></label>
			<?php endforeach; ?>
		</fieldset>
		<div class="cm-row cm-widget-when">
			<label class="cm-field"><span><?php esc_html_e( 'Start', 'clean-maintenance' ); ?></span><input type="datetime-local" name="start" value="<?php echo esc_attr( $s['start'] ); ?>"></label>
			<label class="cm-field"><span><?php esc_html_e( 'End', 'clean-maintenance' ); ?></span><input type="datetime-local" name="end" value="<?php echo esc_attr( $s['end'] ); ?>"></label>
		</div>

		<fieldset class="cm-segment cm-widget-design">
			<legend class="screen-reader-text"><?php esc_html_e( 'Design', 'clean-maintenance' ); ?></legend>
			<label><input type="radio" name="design" value="page" <?php checked( $s['design'], 'page' ); ?>><span><?php esc_html_e( 'Page from WordPress', 'clean-maintenance' ); ?></span></label>
			<label><input type="radio" name="design" value="classic" <?php checked( $s['design'], 'classic' ); ?>><span><?php esc_html_e( 'Classic', 'clean-maintenance' ); ?></span></label>
		</fieldset>
		<div class="cm-widget-page">
			<label class="cm-field" for="cm-widget-page"><span><?php esc_html_e( 'Maintenance page', 'clean-maintenance' ); ?></span></label>
			<select id="cm-widget-page" name="page">
				<option value="0"><?php esc_html_e( '– simple notice with the site title –', 'clean-maintenance' ); ?></option>
				<?php foreach ( $pages as $p ) : ?>
					<?php $state = 'publish' === $p->post_status ? '' : ' (' . get_post_status_object( $p->post_status )->label . ')'; ?>
					<option value="<?php echo esc_attr( (string) $p->ID ); ?>" <?php selected( (int) $s['page'], $p->ID ); ?>><?php echo esc_html( get_the_title( $p ) . $state ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="cm-widget-classic">
			<label class="cm-field"><span><?php esc_html_e( 'Heading', 'clean-maintenance' ); ?></span><input type="text" name="c_heading" value="<?php echo esc_attr( $s['c_heading'] ); ?>" class="widefat"></label>
			<label class="cm-field"><span><?php esc_html_e( 'Text', 'clean-maintenance' ); ?></span><textarea name="c_text" rows="2" class="widefat"><?php echo esc_textarea( $s['c_text'] ); ?></textarea></label>
		</div>

		<p class="cm-widget-actions">
			<?php submit_button( __( 'Save', 'clean-maintenance' ), 'primary', 'submit', false ); ?>
			<a href="<?php echo esc_url( add_query_arg( 'cm-preview', '1', home_url( '/' ) ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Preview', 'clean-maintenance' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'options-general.php?page=clean-maintenance' ) ); ?>"><?php esc_html_e( 'All settings', 'clean-maintenance' ); ?></a>
		</p>
	</form>
	<?php
}

add_action(
	'admin_post_clean_maintenance_widget',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'clean-maintenance' ) );
		}
		check_admin_referer( 'clean_maintenance_widget' );
		$p = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Everything goes through clean_maintenance_sanitize() below.
		// Start from all current settings and change only what the widget shows.
		$s = clean_maintenance_settings();
		foreach ( array( 'mode', 'start', 'end', 'design', 'page', 'c_heading', 'c_text' ) as $key ) {
			if ( isset( $p[ $key ] ) ) {
				$s[ $key ] = $p[ $key ];
			}
		}
		update_option( 'clean_maintenance', clean_maintenance_sanitize( $s ) );
		wp_safe_redirect( add_query_arg( 'cm-widget', '1', admin_url( 'index.php' ) ) . '#clean_maintenance_site' );
		exit;
	}
);
