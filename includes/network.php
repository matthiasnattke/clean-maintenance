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
		wp_enqueue_style( 'clean-maintenance-admin', plugins_url( 'assets/admin.css', CLEAN_MAINTENANCE_FILE ), array(), clean_maintenance_asset_ver( 'admin.css' ) );
		wp_enqueue_script( 'clean-maintenance-network', plugins_url( 'assets/admin.js', CLEAN_MAINTENANCE_FILE ), array( 'jquery' ), clean_maintenance_asset_ver( 'admin.js' ), true );
		wp_localize_script( 'clean-maintenance-network', 'cleanMaintenance', clean_maintenance_ajax_data( 'clean_maintenance_network' ) );
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
 * One site card of the network overview.
 *
 * @param array $r Row from clean_maintenance_network_rows().
 */
function clean_maintenance_site_card( $r ) {
	?>
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
	<?php
}

/**
 * How many sites are in maintenance, reachable and scheduled.
 *
 * @param array[] $rows Rows from clean_maintenance_network_rows().
 * @return array{all:int,active:int,open:int,planned:int}
 */
function clean_maintenance_network_counts( array $rows ) {
	$counts = array(
		'all'     => count( $rows ),
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
	return $counts;
}

/**
 * Render the network overview.
 */
function clean_maintenance_render_network() {
	if ( ! current_user_can( 'manage_network_options' ) ) {
		return;
	}
	$rows   = clean_maintenance_network_rows();
	$counts = clean_maintenance_network_counts( $rows );
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
				<p class="cm-stat is-active"><strong data-cm-count="active"><?php echo (int) $counts['active']; ?></strong> <span><?php esc_html_e( 'in maintenance mode', 'clean-maintenance' ); ?></span></p>
				<p class="cm-stat"><strong data-cm-count="open"><?php echo (int) $counts['open']; ?></strong> <span><?php esc_html_e( 'reachable', 'clean-maintenance' ); ?></span></p>
				<?php if ( $counts['planned'] ) : ?>
					<p class="cm-stat is-planned"><strong data-cm-count="planned"><?php echo (int) $counts['planned']; ?></strong> <span><?php esc_html_e( 'Planned', 'clean-maintenance' ); ?></span></p>
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
						<label><input type="radio" name="cm_view" value="<?php echo esc_attr( $key ); ?>" class="cm-view-<?php echo esc_attr( $key ); ?>" <?php checked( 'all', $key ); ?>><span><?php echo esc_html( $f[0] ); ?> <em data-cm-count="<?php echo esc_attr( $key ); ?>"><?php echo (int) $f[1]; ?></em></span></label>
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
					<?php clean_maintenance_site_card( $r ); ?>
				<?php endforeach; ?>
				<p class="cm-empty"><?php esc_html_e( 'No sites in this view.', 'clean-maintenance' ); ?></p>
			</div>
		</form>
		<?php
		$cm_host   = clean_maintenance_network_host();
		$cm_list   = clean_maintenance_network_allow();
		$cm_ip     = clean_maintenance_client_ip();
		$cm_ips    = $cm_host['host'] ? clean_maintenance_resolve_host( $cm_host['host'], isset( $_GET['cm-allow-saved'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$cm_home   = $cm_host['host'] && clean_maintenance_host_matches( $cm_ip, $cm_ips, $cm_host['v6'] );
		$cm_priv   = clean_maintenance_list_has_private( $cm_list );
		$cm_report = clean_maintenance_home_report();
		$cm_athome = clean_maintenance_home_matches( $cm_ip, $cm_report );
		$cm_url    = clean_maintenance_home_url();
		$cm_tunnel = $cm_priv && $cm_ip && ! clean_maintenance_is_private_ip( $cm_ip );
		$cm_server = isset( $_SERVER['SERVER_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_ADDR'] ) ) : '';
		$cm_domain = (string) wp_parse_url( network_home_url(), PHP_URL_HOST );
		?>
		<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=clean_maintenance_network_allow' ) ); ?>" class="cm-card cm-allow" id="cm-allow">
			<?php wp_nonce_field( 'clean_maintenance_network_allow' ); ?>
			<h2><?php echo clean_maintenance_icon( 'key' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?> <?php esc_html_e( 'Always let through', 'clean-maintenance' ); ?></h2>
			<p class="cm-lead"><?php esc_html_e( 'Visitors from your home network see every site of the network normally, without logging in.', 'clean-maintenance' ); ?></p>
			<?php if ( isset( $_GET['cm-allow-saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only. ?>
				<?php if ( 'badhost' === $_GET['cm-allow-saved'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput -- display only, compared with a fixed value. ?>
					<div class="notice notice-warning inline"><p><?php esc_html_e( 'Saved, but the home address was not a valid name and has been left empty.', 'clean-maintenance' ); ?></p></div>
				<?php else : ?>
					<div class="notice notice-success inline"><p><?php esc_html_e( 'Saved.', 'clean-maintenance' ); ?></p></div>
				<?php endif; ?>
			<?php endif; ?>

			<section class="cm-way cm-way-main">
				<header>
					<span class="cm-way-nr">1</span>
					<strong><?php esc_html_e( 'Access pass for a browser', 'clean-maintenance' ); ?></strong>
					<span class="cm-way-tag"><?php esc_html_e( 'Recommended', 'clean-maintenance' ); ?></span>
				</header>
				<p class="cm-help"><?php esc_html_e( 'A cookie that lets one browser see every site – no matter where it is or which address it uses, also with iCloud Private Relay or on the go. It stays for half a year, even after logging out.', 'clean-maintenance' ); ?></p>
				<label class="cm-renew cm-pass-login"><input type="checkbox" name="cm_pass_login" value="1" <?php checked( clean_maintenance_pass_on_login() ); ?>> <?php esc_html_e( 'Give network administrators a pass when they log in', 'clean-maintenance' ); ?></label>
				<div class="cm-copy">
					<input type="text" id="cm-pass-url" class="large-text code" readonly value="<?php echo esc_attr( clean_maintenance_pass_url() ); ?>" aria-label="<?php esc_attr_e( 'Access link', 'clean-maintenance' ); ?>">
					<button type="button" class="button cm-copy-btn" data-target="cm-pass-url" data-done="<?php esc_attr_e( 'Copied', 'clean-maintenance' ); ?>"><?php esc_html_e( 'Copy', 'clean-maintenance' ); ?></button>
				</div>
				<p class="cm-help"><?php esc_html_e( 'To see the maintenance page anyway, add ?cm-preview=1 to the address.', 'clean-maintenance' ); ?></p>
				<p class="cm-help"><?php esc_html_e( 'Open this link once in a browser to give it a pass – handy as a bookmark for private windows, or to show the sites to someone without an account.', 'clean-maintenance' ); ?></p>
				<p class="cm-ip">
					<?php if ( clean_maintenance_has_pass() ) : ?>
						<span class="cm-ip-ok"><?php esc_html_e( 'This browser has a pass', 'clean-maintenance' ); ?></span>
						<a href="<?php echo esc_url( add_query_arg( 'cm-pass', 'off', network_home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Remove it from this browser', 'clean-maintenance' ); ?></a>
					<?php else : ?>
						<span class="cm-ip-no"><?php esc_html_e( 'This browser has no pass yet', 'clean-maintenance' ); ?></span>
					<?php endif; ?>
				</p>
				<label class="cm-renew"><input type="checkbox" name="cm_pass_renew" value="1"> <?php esc_html_e( 'Revoke all passes and create a new link', 'clean-maintenance' ); ?></label>
			</section>

			<section class="cm-way">
				<header>
					<span class="cm-way-nr">2</span>
					<strong><?php esc_html_e( 'Your router reports the home network', 'clean-maintenance' ); ?></strong>
					<span class="cm-way-tag cm-way-tag-soft"><?php esc_html_e( 'Whole home network', 'clean-maintenance' ); ?></span>
				</header>
				<p class="cm-help"><?php esc_html_e( 'For every device at home without a pass – family, guests, TV, podcast apps.', 'clean-maintenance' ); ?></p>
				<p class="cm-help"><?php esc_html_e( 'Whenever its addresses change, your FRITZ!Box calls a secret link and tells the plugin the current network of your home. Nothing in your home network becomes reachable from outside – the router only sends, it does not open anything.', 'clean-maintenance' ); ?></p>
				<div class="cm-copy">
					<input type="text" id="cm-home-url" class="large-text code" readonly value="<?php echo esc_attr( $cm_url ); ?>" aria-label="<?php esc_attr_e( 'Update URL', 'clean-maintenance' ); ?>">
					<button type="button" class="button cm-copy-btn" data-target="cm-home-url" data-done="<?php esc_attr_e( 'Copied', 'clean-maintenance' ); ?>"><?php esc_html_e( 'Copy', 'clean-maintenance' ); ?></button>
				</div>
				<ol class="cm-steps">
					<li><?php esc_html_e( 'FRITZ!Box: Internet → Permit Access → DynDNS → Use DynDNS, provider “User-defined”.', 'clean-maintenance' ); ?></li>
					<li><?php esc_html_e( 'Update URL: paste the link above unchanged – the parts in angle brackets are filled in by the FRITZ!Box.', 'clean-maintenance' ); ?></li>
					<li>
						<?php
						/* translators: %s: domain of the network. */
						echo wp_kses( sprintf( __( 'Domain name: %s. User name and password: anything, e.g. “home” – they are not used.', 'clean-maintenance' ), '<code>' . esc_html( $cm_domain ) . '</code>' ), array( 'code' => array() ) );
						?>
					</li>
				</ol>
				<p class="cm-ip">
					<?php if ( $cm_report['time'] ) : ?>
						<?php
						/* translators: %s: how long ago, e.g. "5 minutes". */
						echo esc_html( sprintf( __( 'Last report %s ago:', 'clean-maintenance' ), human_time_diff( $cm_report['time'] ) ) );
						?>
						<?php foreach ( array_filter( array( $cm_report['prefix'], $cm_report['v4'] ) ) as $cm_a ) : ?>
							<code><?php echo esc_html( $cm_a ); ?></code>
						<?php endforeach; ?>
						<?php if ( $cm_athome ) : ?>
							<span class="cm-ip-ok"><?php esc_html_e( 'You are at home – let through', 'clean-maintenance' ); ?></span>
						<?php else : ?>
							<span class="cm-ip-no"><?php esc_html_e( 'Your current address does not belong to it', 'clean-maintenance' ); ?></span>
						<?php endif; ?>
					<?php else : ?>
						<span class="cm-ip-no"><?php esc_html_e( 'No report yet – save the DynDNS settings in the FRITZ!Box.', 'clean-maintenance' ); ?></span>
					<?php endif; ?>
				</p>
				<?php $cm_log = get_site_option( 'clean_maintenance_home_log', array() ); ?>
				<?php if ( is_array( $cm_log ) && $cm_log ) : ?>
					<details class="cm-log">
						<summary><?php esc_html_e( 'Last calls of the router', 'clean-maintenance' ); ?></summary>
						<table>
							<thead><tr><th><?php esc_html_e( 'When', 'clean-maintenance' ); ?></th><th><?php esc_html_e( 'From', 'clean-maintenance' ); ?></th><th>prefix</th><th>ip4</th></tr></thead>
							<tbody>
								<?php foreach ( $cm_log as $cm_call ) : ?>
									<tr>
										<td><?php echo esc_html( human_time_diff( (int) ( $cm_call['time'] ?? 0 ) ) ); ?></td>
										<td><code><?php echo esc_html( (string) ( $cm_call['from'] ?? '' ) ); ?></code></td>
										<td><code><?php echo esc_html( '' !== (string) ( $cm_call['prefix'] ?? '' ) ? (string) $cm_call['prefix'] : '–' ); ?></code></td>
										<td><code><?php echo esc_html( '' !== (string) ( $cm_call['ip4'] ?? '' ) ? (string) $cm_call['ip4'] : '–' ); ?></code></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</details>
				<?php endif; ?>
				<label class="cm-renew"><input type="checkbox" name="cm_home_renew" value="1"> <?php esc_html_e( 'Create a new link (the old one stops working)', 'clean-maintenance' ); ?></label>
			</section>

			<details class="cm-way" <?php echo $cm_host['host'] ? 'open' : ''; ?>>
				<summary>
					<span class="cm-way-nr">3</span>
					<strong><?php esc_html_e( 'Or a DynDNS name', 'clean-maintenance' ); ?></strong>
					<span class="cm-way-tag cm-way-tag-soft"><?php esc_html_e( 'Alternative', 'clean-maintenance' ); ?></span>
				</summary>
				<p class="cm-help"><?php esc_html_e( 'For routers without a user-defined DynDNS: a name that always points to an address of your home network. Pasted links are cleaned up to the name.', 'clean-maintenance' ); ?></p>
				<div class="cm-host-row">
					<input type="text" id="cm-network-host" name="cm_network_host" class="regular-text code" value="<?php echo esc_attr( $cm_host['host'] ); ?>" placeholder="home.example.net" spellcheck="false" autocomplete="off" inputmode="url" aria-label="<?php esc_attr_e( 'DynDNS name', 'clean-maintenance' ); ?>">
					<span class="cm-host-note" id="cm-host-note" aria-live="polite" data-text="<?php esc_attr_e( 'Cleaned up – only the name is needed.', 'clean-maintenance' ); ?>" hidden></span>
					<label class="cm-v6"><span><?php esc_html_e( 'IPv6 network', 'clean-maintenance' ); ?></span>
						<select name="cm_network_v6">
							<?php foreach ( array( 56 => __( '/56 – whole home network', 'clean-maintenance' ), 64 => __( '/64 – one network segment', 'clean-maintenance' ), 48 => __( '/48 – wide', 'clean-maintenance' ), 128 => __( 'Only the exact address', 'clean-maintenance' ) ) as $cm_len => $cm_label ) : ?>
								<option value="<?php echo esc_attr( $cm_len ); ?>" <?php selected( $cm_host['v6'], $cm_len ); ?>><?php echo esc_html( $cm_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</div>
				<?php if ( $cm_host['host'] ) : ?>
					<p class="cm-ip">
						<?php if ( $cm_ips ) : ?>
							<?php esc_html_e( 'Points to', 'clean-maintenance' ); ?>
							<?php foreach ( $cm_ips as $cm_a ) : ?>
								<code><?php echo esc_html( $cm_a ); ?></code>
							<?php endforeach; ?>
							<?php if ( $cm_home ) : ?>
								<span class="cm-ip-ok"><?php esc_html_e( 'You are at home – let through', 'clean-maintenance' ); ?></span>
							<?php else : ?>
								<span class="cm-ip-no"><?php esc_html_e( 'Your current address does not belong to it', 'clean-maintenance' ); ?></span>
							<?php endif; ?>
						<?php else : ?>
							<span class="cm-ip-no"><?php esc_html_e( 'This name cannot be resolved right now.', 'clean-maintenance' ); ?></span>
						<?php endif; ?>
					</p>
					<?php if ( $cm_ips && ! $cm_home && preg_match( '/\.myfritz\.net$/', $cm_host['host'] ) ) : ?>
						<p class="cm-hint"><?php esc_html_e( 'A MyFRITZ name points to the internet side of the FRITZ!Box, not to your home network. Let the FRITZ!Box report the home network instead (way 2) – no sharing needed.', 'clean-maintenance' ); ?></p>
					<?php endif; ?>
				<?php endif; ?>
			</details>

			<details class="cm-way" <?php echo $cm_list ? 'open' : ''; ?>>
				<summary>
					<span class="cm-way-nr">4</span>
					<strong><?php esc_html_e( 'Fixed addresses and ranges', 'clean-maintenance' ); ?></strong>
					<span class="cm-way-tag cm-way-tag-soft"><?php esc_html_e( 'Manual', 'clean-maintenance' ); ?></span>
				</summary>
				<label class="screen-reader-text" for="cm-network-allow"><?php esc_html_e( 'IP addresses and ranges', 'clean-maintenance' ); ?></label>
				<textarea id="cm-network-allow" name="cm_network_allow" rows="5" class="large-text code" placeholder="203.0.113.7   # <?php esc_attr_e( 'office', 'clean-maintenance' ); ?>"><?php echo esc_textarea( $cm_list ); ?></textarea>
				<p class="cm-help"><?php esc_html_e( 'One address or range per line, IPv4 or IPv6, e.g. 203.0.113.7, 192.168.178.0/24 or 2001:db8::/56. Comments start with #. Search engines still get the maintenance page unless they come from these addresses.', 'clean-maintenance' ); ?></p>
				<?php clean_maintenance_ip_hint( 'cm-network-allow' ); ?>
			</details>

			<section class="cm-way cm-tunnel" id="cm-tunnel" data-public="<?php echo $cm_ip && ! clean_maintenance_is_private_ip( $cm_ip ) ? 1 : 0; ?>" <?php echo $cm_tunnel ? '' : 'hidden'; ?>>
				<header>
					<span class="cm-way-nr">5</span>
					<strong><?php esc_html_e( 'Private ranges need the tunnel', 'clean-maintenance' ); ?></strong>
					<span class="cm-way-tag cm-way-tag-warn"><?php esc_html_e( 'Guide', 'clean-maintenance' ); ?></span>
				</header>
				<p>
					<?php
					/* translators: %s: IP address of the current visitor. */
					echo wp_kses( sprintf( __( 'Private ranges such as 192.168.x.x or 10.x.x.x only match when a request really travels through your VPN. Right now your request arrives from %s – a public address, so the domain is reached over the internet.', 'clean-maintenance' ), '<code>' . esc_html( $cm_ip ) . '</code>' ), array( 'code' => array() ) );
					?>
				</p>
				<ol class="cm-steps">
					<li>
						<strong><?php esc_html_e( 'Route the server through the tunnel', 'clean-maintenance' ); ?></strong>
						<?php
						/* translators: %s: public IP address of the web server. */
						echo wp_kses( sprintf( __( 'In the WireGuard connection of your router, add the server address %s to the networks reached through the tunnel (AllowedIPs). On the server, the connection must accept your home range, e.g. 192.168.178.0/24.', 'clean-maintenance' ), '<code>' . esc_html( $cm_server ? $cm_server . ( false !== strpos( $cm_server, ':' ) ? '/128' : '/32' ) : '…' ) . '</code>' ), array( 'code' => array() ) );
						?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Or resolve the domain locally', 'clean-maintenance' ); ?></strong>
						<?php
						/* translators: %s: domain of the network. */
						echo wp_kses( sprintf( __( 'Let %s point to the tunnel address of the server in your home network – in a local DNS server or in the hosts file of a device. The certificate stays valid because the name does not change.', 'clean-maintenance' ), '<code>' . esc_html( $cm_domain ) . '</code>' ), array( 'code' => array() ) );
						?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Check', 'clean-maintenance' ); ?></strong>
						<?php esc_html_e( 'Reload this page: “Your current address” should now show a private address.', 'clean-maintenance' ); ?>
					</li>
				</ol>
				<p class="cm-help"><?php esc_html_e( 'No tunnel? Then letting the router report the home network is the simpler way.', 'clean-maintenance' ); ?></p>
			</section>
			<p class="cm-tunnel-link"><button type="button" class="button-link" id="cm-tunnel-toggle" <?php echo $cm_tunnel ? 'hidden' : ''; ?>><?php esc_html_e( 'Connected through a VPN? Show the tunnel guide', 'clean-maintenance' ); ?></button></p>

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
		$raw  = sanitize_text_field( wp_unslash( $_POST['cm_network_host'] ?? '' ) );
		$host = clean_maintenance_normalize_host( $raw );
		update_site_option( 'clean_maintenance_pass_login', empty( $_POST['cm_pass_login'] ) ? 0 : 1 );
		if ( ! empty( $_POST['cm_pass_renew'] ) ) {
			delete_site_option( 'clean_maintenance_pass_token' );
		}
		if ( ! empty( $_POST['cm_home_renew'] ) ) {
			delete_site_option( 'clean_maintenance_home_token' );
			delete_site_option( 'clean_maintenance_home' );
			delete_site_option( 'clean_maintenance_home_log' );
		}
		update_site_option(
			'clean_maintenance_network_host',
			array(
				'host' => $host,
				'v6'   => absint( $_POST['cm_network_v6'] ?? 56 ),
			)
		);
		wp_safe_redirect( add_query_arg( 'cm-allow-saved', '' !== $raw && '' === $host ? 'badhost' : '1', network_admin_url( 'settings.php?page=clean-maintenance-network' ) ) . '#cm-allow' );
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

/*
 * Turning a site on or off without reloading the page – the links keep working without
 * JavaScript.
 */
add_action(
	'wp_ajax_clean_maintenance_toggle',
	function () {
		if ( ! is_multisite() || ! current_user_can( 'manage_network_options' ) ) {
			wp_send_json_error( null, 403 );
		}
		check_ajax_referer( 'clean_maintenance_network' );
		$id   = absint( wp_unslash( $_POST['site'] ?? 0 ) );
		$mode = 'on' === sanitize_key( wp_unslash( $_POST['mode'] ?? '' ) ) ? 'on' : 'off';
		if ( ! $id || ! get_site( $id ) ) {
			wp_send_json_error( null, 404 );
		}
		clean_maintenance_set_mode( $id, $mode );
		$rows = clean_maintenance_network_rows();
		$out  = array( 'counts' => clean_maintenance_network_counts( $rows ) );
		if ( 'dashboard' === sanitize_key( wp_unslash( $_POST['context'] ?? '' ) ) ) {
			ob_start();
			clean_maintenance_network_widget();
			$out['widget'] = ob_get_clean();
		} else {
			foreach ( $rows as $r ) {
				if ( $r['id'] === $id ) {
					ob_start();
					clean_maintenance_site_card( $r );
					$out['card'] = ob_get_clean();
				}
			}
		}
		wp_send_json_success( $out );
	}
);
