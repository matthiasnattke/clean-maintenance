<?php
/**
 * Clean Maintenance – central overview in the network admin
 * ("Network Admin → Settings → Maintenance mode").
 *
 * Shows all sites with state and period, turns single or several sites on/off
 * and links to the settings and preview of each site.
 *
 * @package CleanMaintenance
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'network_admin_menu',
	function () {
		add_submenu_page(
			'settings.php',
			__( 'Maintenance mode', 'clean-maintenance' ),
			__( 'Maintenance mode', 'clean-maintenance' ),
			'manage_network_options',
			'clean-maintenance-network',
			'clean_maintenance_render_network'
		);
	}
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( 'settings_page_clean-maintenance-network' !== $hook ) {
			return;
		}
		wp_enqueue_script( 'clean-maintenance-network', plugins_url( 'assets/admin.js', CLEAN_MAINTENANCE_FILE ), array( 'jquery' ), CLEAN_MAINTENANCE_VERSION, true );
	}
);

add_filter(
	'network_admin_plugin_action_links_' . plugin_basename( CLEAN_MAINTENANCE_FILE ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( network_admin_url( 'settings.php?page=clean-maintenance-network' ) ) . '">' . esc_html__( 'Overview', 'clean-maintenance' ) . '</a>' );
		return $links;
	}
);

/**
 * Colours of a state badge.
 *
 * @param array $status Result of clean_maintenance_status().
 * @return string Inline style.
 */
function clean_maintenance_badge_style( $status ) {
	$bg = $status[0] ? '#fbe3dc' : ( 'planned' === $status[2] ? '#fff4d6' : '#eef0f4' );
	$fg = $status[0] ? '#a3341a' : '#3c434a';
	return 'display:inline-block;padding:.2em .7em;border-radius:999px;background:' . $bg . ';color:' . $fg;
}

/**
 * URL that turns maintenance mode on or off for a site.
 *
 * @param int    $id   Site ID.
 * @param string $mode on | off.
 * @param string $back Optional "dashboard".
 * @return string
 */
function clean_maintenance_toggle_url( $id, $mode, $back = '' ) {
	$args = array(
		'action'     => 'clean_maintenance_network',
		'cm_sites[]' => (int) $id,
		'cm_mode'    => $mode,
	);
	if ( $back ) {
		$args['cm_back'] = $back;
	}
	return wp_nonce_url( add_query_arg( $args, network_admin_url( 'edit.php' ) ), 'clean_maintenance_network' );
}

/**
 * Render the network overview.
 */
function clean_maintenance_render_network() {
	if ( ! current_user_can( 'manage_network_options' ) ) {
		return;
	}
	$sites = get_sites(
		array(
			'number'   => 500,
			'deleted'  => 0,
			'archived' => 0,
		)
	);
	$rows  = array();
	$on    = 0;
	foreach ( $sites as $site ) {
		switch_to_blog( (int) $site->blog_id );
		$s      = clean_maintenance_settings( (int) $site->blog_id );
		$st     = clean_maintenance_status( $s, wp_timezone() );
		$rows[] = array(
			'id'       => (int) $site->blog_id,
			'name'     => get_bloginfo( 'name' ),
			'url'      => home_url( '/' ),
			'status'   => $st,
			'mode'     => $s['mode'],
			'page'     => (int) $s['page'] ? get_the_title( (int) $s['page'] ) : '',
			'settings' => admin_url( 'options-general.php?page=clean-maintenance' ),
			'preview'  => add_query_arg( 'cm-preview', '1', home_url( '/' ) ),
		);
		restore_current_blog();
		if ( $st[0] ) {
			++$on;
		}
	}
	// Sites without active maintenance mode first, otherwise network order.
	usort(
		$rows,
		function ( $a, $b ) {
			return (int) $a['status'][0] <=> (int) $b['status'][0];
		}
	);
	$done = isset( $_GET['cm-done'] ) ? absint( $_GET['cm-done'] ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Maintenance mode in the network', 'clean-maintenance' ); ?></h1>
		<?php if ( null !== $done ) : ?>
			<div class="notice notice-success is-dismissible"><p>
				<?php
				/* translators: %d: number of changed sites. */
				echo esc_html( sprintf( _n( '%d site changed.', '%d sites changed.', $done, 'clean-maintenance' ), $done ) );
				?>
			</p></div>
		<?php endif; ?>
		<p>
			<?php
			/* translators: 1: sites in maintenance mode, 2: all sites. */
			echo esc_html( sprintf( __( '%1$d of %2$d sites are in maintenance mode right now.', 'clean-maintenance' ), $on, count( $rows ) ) );
			echo ' ';
			esc_html_e( 'Each site sets its period, maintenance page, background, exceptions and roles in its own settings.', 'clean-maintenance' );
			?>
		</p>
		<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=clean_maintenance_network' ) ); ?>">
			<?php wp_nonce_field( 'clean_maintenance_network' ); ?>
			<div class="tablenav top">
				<select name="cm_mode">
					<option value="on"><?php esc_html_e( 'Turn selected on', 'clean-maintenance' ); ?></option>
					<option value="off"><?php esc_html_e( 'Turn selected off', 'clean-maintenance' ); ?></option>
				</select>
				<?php submit_button( __( 'Apply', 'clean-maintenance' ), 'action', 'cm_bulk', false ); ?>
			</div>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<td class="manage-column check-column"><input type="checkbox" id="cm-all"></td>
						<th scope="col"><?php esc_html_e( 'Site', 'clean-maintenance' ); ?></th>
						<th scope="col"><?php esc_html_e( 'State', 'clean-maintenance' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Maintenance page', 'clean-maintenance' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'clean-maintenance' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $r ) : ?>
						<tr>
							<th scope="row" class="check-column"><input type="checkbox" class="cm-site" name="cm_sites[]" value="<?php echo (int) $r['id']; ?>"></th>
							<td><strong><?php echo esc_html( $r['name'] ); ?></strong><br><a href="<?php echo esc_url( $r['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( preg_replace( '#^https?://#', '', untrailingslashit( $r['url'] ) ) ); ?></a></td>
							<td><span style="<?php echo esc_attr( clean_maintenance_badge_style( $r['status'] ) ); ?>"><?php echo esc_html( $r['status'][1] ); ?></span></td>
							<td>
								<?php if ( $r['page'] ) : ?>
									<?php echo esc_html( $r['page'] ); ?>
								<?php else : ?>
									<span style="color:#787c82"><?php esc_html_e( 'simple notice', 'clean-maintenance' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( 'on' === $r['mode'] ) : ?>
									<a class="button button-small" href="<?php echo esc_url( clean_maintenance_toggle_url( $r['id'], 'off' ) ); ?>"><?php esc_html_e( 'Turn off', 'clean-maintenance' ); ?></a>
								<?php else : ?>
									<a class="button button-small" href="<?php echo esc_url( clean_maintenance_toggle_url( $r['id'], 'on' ) ); ?>"><?php esc_html_e( 'Turn on', 'clean-maintenance' ); ?></a>
								<?php endif; ?>
								<a class="button button-small" href="<?php echo esc_url( $r['settings'] ); ?>"><?php esc_html_e( 'Settings', 'clean-maintenance' ); ?></a>
								<a class="button-link" style="margin-left:.4em" href="<?php echo esc_url( $r['preview'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Preview', 'clean-maintenance' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</form>
		<p class="description" style="margin-top:1em"><?php esc_html_e( '“Turn on” applies right away and until you turn it off; a scheduled period is replaced. Network administrators always see the sites normally.', 'clean-maintenance' ); ?></p>
	</div>
	<?php
}

add_action(
	'network_admin_edit_clean_maintenance_network',
	function () {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'clean-maintenance' ) );
		}
		check_admin_referer( 'clean_maintenance_network' );
		$mode  = 'on' === sanitize_key( wp_unslash( $_REQUEST['cm_mode'] ?? '' ) ) ? 'on' : 'off';
		$sites = array_filter( array_map( 'absint', (array) wp_unslash( $_REQUEST['cm_sites'] ?? array() ) ) );
		foreach ( $sites as $id ) {
			if ( get_site( $id ) ) {
				clean_maintenance_set_mode( $id, $mode );
			}
		}
		$back = 'dashboard' === sanitize_key( wp_unslash( $_REQUEST['cm_back'] ?? '' ) )
			? network_admin_url()
			: add_query_arg( 'cm-done', count( $sites ), network_admin_url( 'settings.php?page=clean-maintenance-network' ) );
		wp_safe_redirect( $back );
		exit;
	}
);

/*
 * ===== Widget in the network admin dashboard =====
 */

add_action(
	'wp_network_dashboard_setup',
	function () {
		wp_add_dashboard_widget( 'cm_network_widget', __( 'Maintenance mode', 'clean-maintenance' ), 'clean_maintenance_network_widget' );
	}
);

/**
 * Render the dashboard widget.
 */
function clean_maintenance_network_widget() {
	$active  = array();
	$planned = array();
	$open    = 0;
	$sites   = get_sites(
		array(
			'number'   => 500,
			'deleted'  => 0,
			'archived' => 0,
		)
	);
	foreach ( $sites as $site ) {
		switch_to_blog( (int) $site->blog_id );
		$s   = clean_maintenance_settings( (int) $site->blog_id );
		$st  = clean_maintenance_status( $s, wp_timezone() );
		$row = array(
			'id'       => (int) $site->blog_id,
			'host'     => preg_replace( '#^https?://#', '', untrailingslashit( home_url( '/' ) ) ),
			'status'   => $st,
			'mode'     => $s['mode'],
			'settings' => admin_url( 'options-general.php?page=clean-maintenance' ),
		);
		restore_current_blog();
		if ( $st[0] ) {
			$active[] = $row;
		} else {
			++$open;
			if ( 'planned' === $st[2] ) {
				$planned[] = $row;
			}
		}
	}
	?>
	<p style="display:flex;gap:1.5em;margin-top:0">
		<span><strong style="font-size:1.6em;color:<?php echo esc_attr( $active ? '#a3341a' : '#3c434a' ); ?>"><?php echo (int) count( $active ); ?></strong> <?php esc_html_e( 'in maintenance mode', 'clean-maintenance' ); ?></span>
		<span><strong style="font-size:1.6em"><?php echo (int) $open; ?></strong> <?php esc_html_e( 'reachable', 'clean-maintenance' ); ?></span>
	</p>
	<?php if ( $active ) : ?>
		<ul style="margin:0">
			<?php foreach ( $active as $r ) : ?>
				<li style="display:flex;justify-content:space-between;gap:1em;padding:.35em 0;border-top:1px solid #f0f0f1">
					<span><a href="<?php echo esc_url( $r['settings'] ); ?>"><?php echo esc_html( $r['host'] ); ?></a>
					<?php if ( 'scheduled' === $r['mode'] ) : ?>
						<br><small style="color:#787c82"><?php echo esc_html( $r['status'][1] ); ?></small>
					<?php endif; ?>
					</span>
					<a class="button button-small" href="<?php echo esc_url( clean_maintenance_toggle_url( $r['id'], 'off', 'dashboard' ) ); ?>"><?php esc_html_e( 'Turn off', 'clean-maintenance' ); ?></a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<?php if ( $planned ) : ?>
		<p style="margin:.8em 0 .3em"><strong><?php esc_html_e( 'Planned', 'clean-maintenance' ); ?></strong></p>
		<ul style="margin:0">
			<?php foreach ( $planned as $r ) : ?>
				<li style="padding:.25em 0"><a href="<?php echo esc_url( $r['settings'] ); ?>"><?php echo esc_html( $r['host'] ); ?></a> <small style="color:#787c82"><?php echo esc_html( $r['status'][1] ); ?></small></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<p style="margin-bottom:0"><a href="<?php echo esc_url( network_admin_url( 'settings.php?page=clean-maintenance-network' ) ); ?>"><?php esc_html_e( 'Manage all sites →', 'clean-maintenance' ); ?></a></p>
	<?php
}
