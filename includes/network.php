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
		$overview  = 'settings_page_clean-maintenance-network' === $hook;
		$dashboard = 'index.php' === $hook && is_network_admin();
		if ( ! $overview && ! $dashboard ) {
			return;
		}
		wp_enqueue_style( 'clean-maintenance-admin', plugins_url( 'assets/admin.css', CLEAN_MAINTENANCE_FILE ), array(), CLEAN_MAINTENANCE_VERSION );
		if ( $overview ) {
			wp_enqueue_script( 'clean-maintenance-network', plugins_url( 'assets/admin.js', CLEAN_MAINTENANCE_FILE ), array( 'jquery' ), CLEAN_MAINTENANCE_VERSION, true );
		}
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
 * All sites of the network with their maintenance state.
 *
 * @return array[] Rows, sites in maintenance first.
 */
function clean_maintenance_network_rows() {
	$sites = get_sites(
		array(
			'number'   => 500,
			'deleted'  => 0,
			'archived' => 0,
		)
	);
	$rows  = array();
	foreach ( $sites as $site ) {
		switch_to_blog( (int) $site->blog_id );
		$s      = clean_maintenance_settings( (int) $site->blog_id );
		$st     = clean_maintenance_status( $s, wp_timezone() );
		$url    = home_url( '/' );
		$rows[] = array(
			'id'       => (int) $site->blog_id,
			'name'     => get_bloginfo( 'name' ),
			'url'      => $url,
			'host'     => preg_replace( '#^https?://#', '', untrailingslashit( $url ) ),
			'status'   => $st,
			'group'    => $st[0] ? 'active' : ( 'planned' === $st[2] ? 'planned' : 'open' ),
			'mode'     => $s['mode'],
			'design'   => $s['design'],
			'page'     => (int) $s['page'] ? get_the_title( (int) $s['page'] ) : '',
			'settings' => admin_url( 'options-general.php?page=clean-maintenance' ),
			'preview'  => add_query_arg( 'cm-preview', '1', $url ),
		);
		restore_current_blog();
	}
	usort(
		$rows,
		function ( $a, $b ) {
			return (int) $b['status'][0] <=> (int) $a['status'][0];
		}
	);
	return $rows;
}

/**
 * Short description of what a site shows during maintenance.
 *
 * @param array $r Row from clean_maintenance_network_rows().
 * @return string
 */
function clean_maintenance_shows( $r ) {
	if ( 'classic' === $r['design'] ) {
		return __( 'Classic design', 'clean-maintenance' );
	}
	return $r['page'] ? $r['page'] : __( 'simple notice', 'clean-maintenance' );
}

/**
 * Render the network overview.
 */
function clean_maintenance_render_network() {
	if ( ! current_user_can( 'manage_network_options' ) ) {
		return;
	}
	$rows   = clean_maintenance_network_rows();
	$counts = array(
		'active'  => 0,
		'open'    => 0,
		'planned' => 0,
	);
	foreach ( $rows as $r ) {
		++$counts[ 'active' === $r['group'] ? 'active' : 'open' ];
		if ( 'planned' === $r['group'] ) {
			++$counts['planned'];
		}
	}
	$done    = isset( $_GET['cm-done'] ) ? absint( $_GET['cm-done'] ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
	$filters = array(
		'all'     => array( __( 'All', 'clean-maintenance' ), count( $rows ) ),
		'active'  => array( __( 'In maintenance', 'clean-maintenance' ), $counts['active'] ),
		'open'    => array( __( 'Reachable', 'clean-maintenance' ), $counts['open'] ),
		'planned' => array( __( 'Planned', 'clean-maintenance' ), $counts['planned'] ),
	);
	?>
	<div class="wrap cm-wrap cm-net">
		<h1 class="screen-reader-text"><?php esc_html_e( 'Maintenance mode in the network', 'clean-maintenance' ); ?></h1>

		<header class="cm-hero">
			<span class="cm-hero-icon"><?php echo clean_maintenance_icon( 'network' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
			<div class="cm-hero-text">
				<p class="cm-eyebrow">Clean Maintenance</p>
				<p class="cm-hero-title"><?php esc_html_e( 'Maintenance mode in the network', 'clean-maintenance' ); ?></p>
				<p class="cm-help"><?php esc_html_e( 'Each site sets its period, maintenance page, background, exceptions and roles in its own settings.', 'clean-maintenance' ); ?></p>
			</div>
			<div class="cm-stats">
				<p class="cm-stat is-active"><strong><?php echo (int) $counts['active']; ?></strong> <span><?php esc_html_e( 'in maintenance mode', 'clean-maintenance' ); ?></span></p>
				<p class="cm-stat"><strong><?php echo (int) $counts['open']; ?></strong> <span><?php esc_html_e( 'reachable', 'clean-maintenance' ); ?></span></p>
				<?php if ( $counts['planned'] ) : ?>
					<p class="cm-stat is-planned"><strong><?php echo (int) $counts['planned']; ?></strong> <span><?php esc_html_e( 'Planned', 'clean-maintenance' ); ?></span></p>
				<?php endif; ?>
			</div>
		</header>

		<?php if ( null !== $done ) : ?>
			<div class="notice notice-success is-dismissible"><p>
				<?php
				/* translators: %d: number of changed sites. */
				echo esc_html( sprintf( _n( '%d site changed.', '%d sites changed.', $done, 'clean-maintenance' ), $done ) );
				?>
			</p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=clean_maintenance_network' ) ); ?>">
			<?php wp_nonce_field( 'clean_maintenance_network' ); ?>

			<div class="cm-toolbar">
				<div class="cm-segment" role="group" aria-label="<?php esc_attr_e( 'Show', 'clean-maintenance' ); ?>">
					<?php foreach ( $filters as $key => $f ) : ?>
						<label><input type="radio" name="cm_view" value="<?php echo esc_attr( $key ); ?>" class="cm-view-<?php echo esc_attr( $key ); ?>" <?php checked( 'all', $key ); ?>><span><?php echo esc_html( $f[0] ); ?> <em><?php echo (int) $f[1]; ?></em></span></label>
					<?php endforeach; ?>
				</div>
				<div class="cm-bulk">
					<label class="cm-check"><input type="checkbox" id="cm-all"> <?php esc_html_e( 'Select all', 'clean-maintenance' ); ?></label>
					<select name="cm_mode" aria-label="<?php esc_attr_e( 'Action', 'clean-maintenance' ); ?>">
						<option value="on"><?php esc_html_e( 'Turn selected on', 'clean-maintenance' ); ?></option>
						<option value="off"><?php esc_html_e( 'Turn selected off', 'clean-maintenance' ); ?></option>
					</select>
					<?php submit_button( __( 'Apply', 'clean-maintenance' ), 'secondary', 'cm_bulk', false ); ?>
				</div>
			</div>

			<div class="cm-sites">
				<?php foreach ( $rows as $r ) : ?>
					<article class="cm-site cm-site-<?php echo esc_attr( $r['group'] ); ?>">
						<div class="cm-site-top">
							<label class="cm-check"><input type="checkbox" class="cm-site-check" name="cm_sites[]" value="<?php echo (int) $r['id']; ?>"><span class="screen-reader-text"><?php echo esc_html( $r['name'] ); ?></span></label>
							<?php $state = explode( ' – ', $r['status'][1], 2 ); ?>
							<span class="cm-badge cm-badge-<?php echo esc_attr( $r['group'] ); ?>"><span class="cm-dot" aria-hidden="true"></span><?php echo esc_html( $state[0] ); ?></span>
						</div>
						<h2 class="cm-site-name"><?php echo esc_html( $r['name'] ? $r['name'] : $r['host'] ); ?></h2>
						<a class="cm-site-host" href="<?php echo esc_url( $r['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $r['host'] ); ?></a>
						<?php if ( ! empty( $state[1] ) ) : ?>
							<p class="cm-site-shows cm-site-when"><?php echo clean_maintenance_icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $state[1] ); ?></p>
						<?php endif; ?>
						<p class="cm-site-shows"><?php echo clean_maintenance_icon( 'classic' === $r['design'] ? 'type' : 'page' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( clean_maintenance_shows( $r ) ); ?></p>
						<div class="cm-site-actions">
							<?php if ( 'on' === $r['mode'] ) : ?>
								<a class="button cm-btn cm-btn-off" href="<?php echo esc_url( clean_maintenance_toggle_url( $r['id'], 'off' ) ); ?>"><?php echo clean_maintenance_icon( 'power' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Turn off', 'clean-maintenance' ); ?></a>
							<?php else : ?>
								<a class="button cm-btn cm-btn-on" href="<?php echo esc_url( clean_maintenance_toggle_url( $r['id'], 'on' ) ); ?>"><?php echo clean_maintenance_icon( 'cup' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Turn on', 'clean-maintenance' ); ?></a>
							<?php endif; ?>
							<a class="cm-link" href="<?php echo esc_url( $r['settings'] ); ?>"><?php esc_html_e( 'Settings', 'clean-maintenance' ); ?></a>
							<a class="cm-link cm-icon-link" href="<?php echo esc_url( $r['preview'] ); ?>" target="_blank" rel="noopener" title="<?php esc_attr_e( 'Preview', 'clean-maintenance' ); ?>"><?php echo clean_maintenance_icon( 'eye' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="screen-reader-text"><?php esc_html_e( 'Preview', 'clean-maintenance' ); ?></span></a>
						</div>
					</article>
				<?php endforeach; ?>
				<p class="cm-empty"><?php esc_html_e( 'No sites in this view.', 'clean-maintenance' ); ?></p>
			</div>
		</form>
		<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=clean_maintenance_network_allow' ) ); ?>" class="cm-card cm-allow" id="cm-allow">
			<?php wp_nonce_field( 'clean_maintenance_network_allow' ); ?>
			<h2><?php echo clean_maintenance_icon( 'key' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?> <?php esc_html_e( 'Always let through', 'clean-maintenance' ); ?></h2>
			<p class="cm-lead"><?php esc_html_e( 'Visitors from these addresses see every site of the network normally, without logging in – for example your home network or a VPN.', 'clean-maintenance' ); ?></p>
			<?php if ( isset( $_GET['cm-allow-saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only. ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Saved.', 'clean-maintenance' ); ?></p></div>
			<?php endif; ?>
			<label class="cm-field" for="cm-network-allow"><span><?php esc_html_e( 'IP addresses and ranges', 'clean-maintenance' ); ?></span></label>
			<textarea id="cm-network-allow" name="cm_network_allow" rows="5" class="large-text code" placeholder="192.168.178.0/24   # <?php esc_attr_e( 'home network', 'clean-maintenance' ); ?>"><?php echo esc_textarea( clean_maintenance_network_allow() ); ?></textarea>
			<p class="cm-help"><?php esc_html_e( 'One address or range per line, IPv4 or IPv6, e.g. 203.0.113.7, 192.168.178.0/24 or 2001:db8::/56. Comments start with #. Search engines still get the maintenance page unless they come from these addresses.', 'clean-maintenance' ); ?></p>
			<?php clean_maintenance_ip_hint( 'cm-network-allow' ); ?>
			<p><?php submit_button( __( 'Save', 'clean-maintenance' ), 'primary', 'cm_allow_save', false ); ?></p>
		</form>

		<p class="cm-help cm-foot"><?php esc_html_e( '“Turn on” applies right away and until you turn it off; a scheduled period is replaced. Network administrators always see the sites normally.', 'clean-maintenance' ); ?></p>
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

add_action(
	'network_admin_edit_clean_maintenance_network_allow',
	function () {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'clean-maintenance' ) );
		}
		check_admin_referer( 'clean_maintenance_network_allow' );
		update_site_option( 'clean_maintenance_network_allow', clean_maintenance_sanitize_ip_list( wp_unslash( $_POST['cm_network_allow'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by clean_maintenance_sanitize_ip_list().
		wp_safe_redirect( add_query_arg( 'cm-allow-saved', '1', network_admin_url( 'settings.php?page=clean-maintenance-network' ) ) . '#cm-allow' );
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
	$rows    = clean_maintenance_network_rows();
	$active  = array();
	$planned = array();
	foreach ( $rows as $r ) {
		if ( 'active' === $r['group'] ) {
			$active[] = $r;
		} elseif ( 'planned' === $r['group'] ) {
			$planned[] = $r;
		}
	}
	$open    = count( $rows ) - count( $active );
	?>
	<div class="cm-widget">
		<div class="cm-wstats">
			<p class="cm-stat<?php echo $active ? ' is-active' : ''; ?>"><strong><?php echo (int) count( $active ); ?></strong> <span><?php esc_html_e( 'in maintenance mode', 'clean-maintenance' ); ?></span></p>
			<p class="cm-stat"><strong><?php echo (int) $open; ?></strong> <span><?php esc_html_e( 'reachable', 'clean-maintenance' ); ?></span></p>
			<?php if ( $planned ) : ?>
				<p class="cm-stat is-planned"><strong><?php echo (int) count( $planned ); ?></strong> <span><?php esc_html_e( 'Planned', 'clean-maintenance' ); ?></span></p>
			<?php endif; ?>
		</div>
		<?php if ( $active || $planned ) : ?>
			<ul class="cm-wlist">
				<?php foreach ( array_merge( $active, $planned ) as $r ) : ?>
					<li class="cm-wrow cm-site-<?php echo esc_attr( $r['group'] ); ?>">
						<span class="cm-dot" aria-hidden="true"></span>
						<span class="cm-wsite"><a href="<?php echo esc_url( $r['settings'] ); ?>"><?php echo esc_html( $r['name'] ? $r['name'] : $r['host'] ); ?></a>
						<small><?php echo esc_html( 'scheduled' === $r['mode'] ? $r['status'][1] : $r['host'] ); ?></small></span>
						<?php if ( 'on' === $r['mode'] ) : ?>
							<a class="button button-small cm-btn" href="<?php echo esc_url( clean_maintenance_toggle_url( $r['id'], 'off', 'dashboard' ) ); ?>"><?php esc_html_e( 'Turn off', 'clean-maintenance' ); ?></a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p class="cm-help"><?php esc_html_e( 'All sites are reachable.', 'clean-maintenance' ); ?></p>
		<?php endif; ?>
		<p class="cm-wfoot"><a href="<?php echo esc_url( network_admin_url( 'settings.php?page=clean-maintenance-network' ) ); ?>"><?php esc_html_e( 'Manage all sites →', 'clean-maintenance' ); ?></a></p>
	</div>
	<?php
}
