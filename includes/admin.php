<?php
/**
 * Clean Maintenance – settings per site ("Settings → Maintenance mode").
 *
 * @package CleanMaintenance
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	function () {
		add_options_page(
			__( 'Maintenance mode', 'clean-maintenance' ),
			__( 'Maintenance mode', 'clean-maintenance' ),
			'manage_options',
			'clean-maintenance',
			'clean_maintenance_render_settings'
		);
	}
);

add_action(
	'admin_init',
	function () {
		register_setting(
			'clean_maintenance',
			'clean_maintenance',
			array(
				'type'              => 'array',
				'sanitize_callback' => 'clean_maintenance_sanitize',
				'default'           => array(),
			)
		);
		// The short link rules are shared with Clean Short Links – only part of this form while it is active.
		if ( clean_maintenance_short_links_active() ) {
			register_setting(
				'clean_maintenance',
				'clean_maintenance_short_links',
				array(
					'type'              => 'array',
					'sanitize_callback' => 'clean_maintenance_sanitize_short_link_rules',
					'default'           => array(),
				)
			);
		}
	}
);

/**
 * Is Clean Short Links active on this site?
 *
 * @return bool
 */
function clean_maintenance_short_links_active() {
	return function_exists( 'CleanShortLinks\\site_settings' );
}

/**
 * The shared rules for short links as tiles – the same choice appears in Clean Short Links.
 */
function clean_maintenance_short_links_card() {
	$r = clean_maintenance_short_link_rules();
	$n = 'clean_maintenance_short_links';
	$groups = array(
		'during' => array(
			__( 'While maintenance mode is on', 'clean-maintenance' ),
			array(
				'redirect' => array( 'external', __( 'Redirect as usual', 'clean-maintenance' ), __( 'Short links work; pages of this site show the maintenance page.', 'clean-maintenance' ) ),
				'open'     => array( 'key', __( 'Let targets through', 'clean-maintenance' ), __( 'Pages that active short links lead to stay reachable.', 'clean-maintenance' ) ),
				'pause'    => array( 'cup', __( 'Pause short links', 'clean-maintenance' ), __( 'Short links show the maintenance page too.', 'clean-maintenance' ) ),
			),
		),
		'dead'   => array(
			__( 'Short links that do not work', 'clean-maintenance' ),
			array(
				'never'     => array( 'page', __( 'Error page', 'clean-maintenance' ), __( '“Not found” or “gone” as usual.', 'clean-maintenance' ) ),
				'temporary' => array( 'calendar', __( 'Maintenance page when temporary', 'clean-maintenance' ), __( 'For targets that are only temporarily unreachable.', 'clean-maintenance' ) ),
				'always'    => array( 'cup', __( 'Always the maintenance page', 'clean-maintenance' ), __( 'Instead of every error page – also without maintenance mode.', 'clean-maintenance' ) ),
			),
		),
	);
	?>
	<section class="cm-card" id="cm-sec-links">
		<h2><?php echo clean_maintenance_icon( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Short links', 'clean-maintenance' ); ?></h2>
		<p class="cm-lead"><?php esc_html_e( 'How Clean Short Links and the maintenance mode work together on this site. The same setting appears in the short link settings.', 'clean-maintenance' ); ?></p>
		<?php foreach ( $groups as $key => $g ) : ?>
			<fieldset class="cm-sub">
				<legend class="cm-field"><span><?php echo esc_html( $g[0] ); ?></span></legend>
				<div class="cm-tiles cm-tiles-3">
					<?php foreach ( $g[1] as $v => $m ) : ?>
						<label class="cm-tile">
							<input type="radio" name="<?php echo esc_attr( $n . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $v ); ?>" <?php checked( $r[ $key ], $v ); ?>>
							<span class="cm-tile-ico"><?php echo clean_maintenance_icon( $m[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<span class="cm-tile-title"><?php echo esc_html( $m[1] ); ?></span>
							<span class="cm-tile-text"><?php echo esc_html( $m[2] ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>
		<?php endforeach; ?>
		<p class="cm-help"><?php esc_html_e( 'A fallback of the short link and the default target of the short link settings always come first.', 'clean-maintenance' ); ?></p>
	</section>
	<?php
}

/**
 * Sanitize the settings form.
 *
 * @param mixed $in Raw input.
 * @return array
 */
function clean_maintenance_sanitize( $in ) {
	$in          = is_array( $in ) ? $in : array();
	$out         = array();
	$roles       = array_keys( wp_roles()->get_names() );
	$mode        = sanitize_key( $in['mode'] ?? 'off' );
	$out['mode'] = in_array( $mode, array( 'off', 'on', 'scheduled' ), true ) ? $mode : 'off';
	foreach ( array( 'start', 'end' ) as $k ) {
		$v         = sanitize_text_field( $in[ $k ] ?? '' );
		$out[ $k ] = preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $v ) ? $v : '';
	}
	$out['page']            = absint( $in['page'] ?? 0 );
	$out['bg_image']        = absint( $in['bg_image'] ?? 0 );
	$out['bg_color']        = (string) sanitize_hex_color( $in['bg_color'] ?? '' );
	$out['exclude']         = sanitize_textarea_field( $in['exclude'] ?? '' ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- setting name, not a query argument.
	$out['roles']           = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $in['roles'] ?? array() ) ), $roles ) );
	$out['backend_roles']   = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $in['backend_roles'] ?? array() ) ), $roles ) );
	$out['redirect']        = esc_url_raw( trim( (string) ( $in['redirect'] ?? '' ) ) );
	$out['bypass_bots']     = empty( $in['bypass_bots'] ) ? 0 : 1;
	$out['meta_robots']     = 'noindex' === ( $in['meta_robots'] ?? '' ) ? 'noindex' : 'index';
	$out['notice']          = empty( $in['notice'] ) ? 0 : 1;
	$rest                   = sanitize_key( $in['rest'] ?? 'maintenance' );
	$out['rest']            = in_array( $rest, array( 'off', 'maintenance', 'always' ), true ) ? $rest : 'maintenance';
	$out['rest_allow']      = sanitize_textarea_field( $in['rest_allow'] ?? '' );
	$out['design']          = 'classic' === ( $in['design'] ?? '' ) ? 'classic' : 'page';
	$out['c_title']         = sanitize_text_field( $in['c_title'] ?? '' );
	$out['c_heading']       = sanitize_text_field( $in['c_heading'] ?? '' );
	$out['c_heading_color'] = (string) sanitize_hex_color( $in['c_heading_color'] ?? '' );
	$out['c_text']          = wp_kses_post( $in['c_text'] ?? '' );
	$out['c_text_color']    = (string) sanitize_hex_color( $in['c_text_color'] ?? '' );
	$out['c_admin_link']    = empty( $in['c_admin_link'] ) ? 0 : 1;
	if ( 'scheduled' === $out['mode'] ) {
		$a = clean_maintenance_ts( $out['start'] );
		$b = clean_maintenance_ts( $out['end'] );
		if ( ! $a && ! $b ) {
			add_settings_error( 'clean_maintenance', 'when', __( 'Please choose a start and/or end for the scheduled maintenance mode.', 'clean-maintenance' ) );
		} elseif ( $a && $b && $b <= $a ) {
			add_settings_error( 'clean_maintenance', 'when', __( 'The end is before the start – please check.', 'clean-maintenance' ) );
		}
	}
	return $out;
}

/**
 * Small inline icons for the settings page.
 *
 * @param string $name Icon name.
 * @return string SVG markup (static, no user data).
 */
function clean_maintenance_icon( $name ) {
	$paths = array(
		'cup'      => '<path d="M5 9h11v4a5.5 5.5 0 0 1-11 0z"/><path d="M16 10h1.5a2.5 2.5 0 0 1 0 5H16"/><path d="M9 11.5v3M12 11.5v3"/><path d="M8 3c-.8 1 .8 1.8 0 3M11.5 3c-.8 1 .8 1.8 0 3"/><path d="M4 21h14"/>',
		'power'    => '<path d="M12 3v8"/><path d="M6.3 6.3a8 8 0 1 0 11.4 0"/>',
		'calendar' => '<rect x="3" y="4.5" width="18" height="16" rx="2"/><path d="M16 2.5v4M8 2.5v4M3 10h18"/>',
		'page'     => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
		'type'     => '<path d="M5 6V4h14v2M12 4v16M9 20h6"/>',
		'eye'      => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
		'brush'    => '<path d="M14.5 4.5 19.5 9.5 11 18l-5-5z"/><path d="M6 13c-2 0-3 1.5-3 3.5 0 1.2-.5 2.5-1 3.5 2.5 0 6-1 6-4"/>',
		'key'      => '<circle cx="8" cy="14" r="4"/><path d="m11 11 9-9M17 5l2 2M15 7l2 2"/>',
		'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
		'bell'     => '<path d="M6 16V11a6 6 0 1 1 12 0v5l2 2H4z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
		'external' => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
		'pencil'   => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m14 6 4 4"/>',
		'link'     => '<path d="M10 14a4.5 4.5 0 0 0 6.4 0l3-3a4.5 4.5 0 0 0-6.4-6.4l-1 1"/><path d="M14 10a4.5 4.5 0 0 0-6.4 0l-3 3a4.5 4.5 0 0 0 6.4 6.4l1-1"/>',
		'network'  => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return '<svg class="cm-ico" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

/**
 * A switch (checkbox) with label and optional help text.
 *
 * @param string $name    Field key.
 * @param bool   $checked Current value.
 * @param string $label   Label.
 * @param string $help    Help text.
 */
function clean_maintenance_switch( $name, $checked, $label, $help = '' ) {
	?>
	<label class="cm-switch">
		<input type="checkbox" name="clean_maintenance[<?php echo esc_attr( $name ); ?>]" value="1" <?php checked( $checked ); ?>>
		<span class="cm-switch-track" aria-hidden="true"></span>
		<span class="cm-switch-text"><strong><?php echo esc_html( $label ); ?></strong>
		<?php if ( $help ) : ?>
			<span class="cm-help"><?php echo esc_html( $help ); ?></span>
		<?php endif; ?>
		</span>
	</label>
	<?php
}

/**
 * "Your address" hint with a button that adds it to a list.
 *
 * @param string $target ID of the textarea the address is added to.
 */
function clean_maintenance_ip_hint( $target ) {
	$ip = clean_maintenance_client_ip();
	if ( ! $ip ) {
		return;
	}
	$allowed = clean_maintenance_network_allowed( $ip );
	?>
	<p class="cm-ip">
		<?php
		/* translators: %s: IP address of the current visitor. */
		echo wp_kses( sprintf( __( 'Your current address: %s', 'clean-maintenance' ), '<code>' . esc_html( $ip ) . '</code>' ), array( 'code' => array() ) );
		?>
		<button type="button" class="button-link cm-ip-add" data-target="<?php echo esc_attr( $target ); ?>" data-ip="<?php echo esc_attr( $ip ); ?>"><?php esc_html_e( 'Add', 'clean-maintenance' ); ?></button>
		<?php if ( $allowed ) : ?>
			<span class="cm-ip-ok"><?php esc_html_e( 'Allowed network-wide', 'clean-maintenance' ); ?></span>
		<?php endif; ?>
	</p>
	<?php
}

/**
 * Render the settings page.
 */
function clean_maintenance_render_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s       = clean_maintenance_settings();
	$status  = clean_maintenance_status( $s );
	$pages   = get_pages(
		array(
			'post_status' => array( 'publish', 'private', 'draft' ),
			'sort_column' => 'post_title',
		)
	);
	$img     = (int) $s['bg_image'];
	$imgurl  = $img ? wp_get_attachment_image_url( $img, 'medium' ) : '';
	$n       = 'clean_maintenance';
	$preview = add_query_arg( 'cm-preview', '1', home_url( '/' ) );
	$network = is_multisite() && current_user_can( 'manage_network_options' );
	$roles   = wp_roles()->get_names();

	$sections = array(
		'cm-sec-status'  => __( 'Status', 'clean-maintenance' ),
		'cm-sec-look'    => __( 'Appearance', 'clean-maintenance' ),
		'cm-sec-access'  => __( 'Access', 'clean-maintenance' ),
		'cm-sec-search'  => __( 'Search engines & API', 'clean-maintenance' ),
		'cm-sec-comfort' => __( 'Comfort', 'clean-maintenance' ),
	);
	if ( clean_maintenance_short_links_active() ) {
		$sections = array_slice( $sections, 0, 4, true ) + array( 'cm-sec-links' => __( 'Short links', 'clean-maintenance' ) ) + $sections;
	}
	?>
	<div class="wrap cm-wrap">
		<h1 class="screen-reader-text"><?php esc_html_e( 'Maintenance mode', 'clean-maintenance' ); ?></h1>

		<header class="cm-hero cm-state-<?php echo esc_attr( $status[2] ); ?>">
			<span class="cm-hero-icon"><?php echo clean_maintenance_icon( 'cup' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
			<div class="cm-hero-text">
				<p class="cm-eyebrow">Clean Maintenance</p>
				<p class="cm-hero-title"><?php esc_html_e( 'Maintenance mode', 'clean-maintenance' ); ?></p>
				<p class="cm-pill<?php echo $status[0] ? ' is-active' : ''; ?>"><span class="cm-dot" aria-hidden="true"></span><?php echo esc_html( $status[1] ); ?></p>
			</div>
			<div class="cm-hero-actions">
				<a class="button button-secondary cm-btn" href="<?php echo esc_url( $preview ); ?>" target="_blank" rel="noopener"><?php echo clean_maintenance_icon( 'eye' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Preview', 'clean-maintenance' ); ?></a>
				<?php if ( $network ) : ?>
					<a class="button button-secondary cm-btn" href="<?php echo esc_url( network_admin_url( 'settings.php?page=clean-maintenance-network' ) ); ?>"><?php echo clean_maintenance_icon( 'network' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'All sites', 'clean-maintenance' ); ?></a>
				<?php endif; ?>
			</div>
		</header>

		<?php settings_errors( 'clean_maintenance' ); ?>

		<div class="cm-layout">
			<nav class="cm-nav" aria-label="<?php esc_attr_e( 'Sections', 'clean-maintenance' ); ?>">
				<?php foreach ( $sections as $id => $title ) : ?>
					<a href="#<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $title ); ?></a>
				<?php endforeach; ?>
				<div class="cm-about">
					<p><strong>Clean Maintenance</strong>
					<?php
					/* translators: %s: version number. */
					echo esc_html( sprintf( __( 'Version %s', 'clean-maintenance' ), CLEAN_MAINTENANCE_VERSION ) );
					?>
					</p>
					<p><a href="https://couch-blog.de/projekte/clean-maintenance/" target="_blank" rel="noopener"><?php esc_html_e( 'Project page', 'clean-maintenance' ); ?></a> · <a href="https://github.com/matthiasnattke/clean-maintenance" target="_blank" rel="noopener">GitHub</a></p>
				</div>
			</nav>

			<form method="post" action="options.php" class="cm-form">
				<?php settings_fields( 'clean_maintenance' ); ?>

				<section class="cm-card" id="cm-sec-status">
					<h2><?php echo clean_maintenance_icon( 'power' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Status', 'clean-maintenance' ); ?></h2>
					<p class="cm-lead"><?php esc_html_e( 'When should visitors see the maintenance page?', 'clean-maintenance' ); ?></p>
					<fieldset class="cm-tiles cm-tiles-3">
						<legend class="screen-reader-text"><?php esc_html_e( 'Mode', 'clean-maintenance' ); ?></legend>
						<?php
						$modes = array(
							'off'       => array( 'power', __( 'Off', 'clean-maintenance' ), __( 'Visitors see the normal site.', 'clean-maintenance' ) ),
							'on'        => array( 'cup', __( 'On', 'clean-maintenance' ), __( 'Right away, until you turn it off.', 'clean-maintenance' ) ),
							'scheduled' => array( 'calendar', __( 'Scheduled', 'clean-maintenance' ), __( 'Start and end from the calendar.', 'clean-maintenance' ) ),
						);
						foreach ( $modes as $v => $m ) :
							?>
							<label class="cm-tile cm-tile-<?php echo esc_attr( $v ); ?>">
								<input type="radio" name="<?php echo esc_attr( $n ); ?>[mode]" value="<?php echo esc_attr( $v ); ?>" <?php checked( $s['mode'], $v ); ?>>
								<span class="cm-tile-ico"><?php echo clean_maintenance_icon( $m[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<span class="cm-tile-title"><?php echo esc_html( $m[1] ); ?></span>
								<span class="cm-tile-text"><?php echo esc_html( $m[2] ); ?></span>
							</label>
						<?php endforeach; ?>
					</fieldset>
					<div class="cm-when cm-sub">
						<div class="cm-row">
							<label class="cm-field"><span><?php esc_html_e( 'Start', 'clean-maintenance' ); ?></span><input type="datetime-local" name="<?php echo esc_attr( $n ); ?>[start]" value="<?php echo esc_attr( $s['start'] ); ?>"></label>
							<label class="cm-field"><span><?php esc_html_e( 'End', 'clean-maintenance' ); ?></span><input type="datetime-local" name="<?php echo esc_attr( $n ); ?>[end]" value="<?php echo esc_attr( $s['end'] ); ?>"></label>
						</div>
						<p class="cm-help">
							<?php
							/* translators: %s: time zone of the site, e.g. Europe/Berlin. */
							echo esc_html( sprintf( __( 'Time zone of the site (%s). Without a start the mode applies right away, without an end until you turn it off.', 'clean-maintenance' ), wp_timezone_string() ) );
							?>
						</p>
					</div>
				</section>

				<section class="cm-card" id="cm-sec-look">
					<h2><?php echo clean_maintenance_icon( 'brush' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Appearance', 'clean-maintenance' ); ?></h2>
					<p class="cm-lead"><?php esc_html_e( 'What visitors see while you work.', 'clean-maintenance' ); ?></p>
					<fieldset class="cm-tiles cm-tiles-2">
						<legend class="screen-reader-text"><?php esc_html_e( 'Design', 'clean-maintenance' ); ?></legend>
						<label class="cm-tile">
							<input type="radio" name="<?php echo esc_attr( $n ); ?>[design]" value="page" <?php checked( $s['design'], 'page' ); ?>>
							<span class="cm-tile-ico"><?php echo clean_maintenance_icon( 'page' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<span class="cm-tile-title"><?php esc_html_e( 'Page from WordPress', 'clean-maintenance' ); ?></span>
							<span class="cm-tile-text"><?php esc_html_e( 'In the style of your theme, edited like any other page.', 'clean-maintenance' ); ?></span>
						</label>
						<label class="cm-tile">
							<input type="radio" name="<?php echo esc_attr( $n ); ?>[design]" value="classic" <?php checked( $s['design'], 'classic' ); ?>>
							<span class="cm-tile-ico"><?php echo clean_maintenance_icon( 'type' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<span class="cm-tile-title"><?php esc_html_e( 'Classic', 'clean-maintenance' ); ?></span>
							<span class="cm-tile-text"><?php esc_html_e( 'Heading, text and background – independent of the theme.', 'clean-maintenance' ); ?></span>
						</label>
					</fieldset>

					<div class="cm-page-row cm-sub">
						<label class="cm-field" for="cm-page"><span><?php esc_html_e( 'Maintenance page', 'clean-maintenance' ); ?></span></label>
						<div class="cm-inline">
							<select id="cm-page" name="<?php echo esc_attr( $n ); ?>[page]">
								<option value="0"><?php esc_html_e( '– simple notice with the site title –', 'clean-maintenance' ); ?></option>
								<?php foreach ( $pages as $p ) : ?>
									<option value="<?php echo (int) $p->ID; ?>" <?php selected( (int) $s['page'], (int) $p->ID ); ?>><?php echo esc_html( ( $p->post_title ? $p->post_title : '#' . $p->ID ) . ( 'publish' !== $p->post_status ? ' (' . $p->post_status . ')' : '' ) ); ?></option>
								<?php endforeach; ?>
							</select>
							<?php if ( (int) $s['page'] ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( (int) $s['page'] ) ); ?>"><?php esc_html_e( 'Edit page', 'clean-maintenance' ); ?></a>
							<?php endif; ?>
						</div>
						<p class="cm-help"><?php esc_html_e( 'Shown everywhere without header and footer. Best create a page of its own as a draft or private – then it is not visible anywhere else.', 'clean-maintenance' ); ?></p>
					</div>

					<div class="cm-classic cm-sub">
						<label class="cm-field"><span><?php esc_html_e( 'Title in the browser tab', 'clean-maintenance' ); ?></span>
							<input id="cm-c-title" type="text" class="regular-text" name="<?php echo esc_attr( $n ); ?>[c_title]" value="<?php echo esc_attr( $s['c_title'] ); ?>"></label>
						<div class="cm-row cm-row-color">
							<label class="cm-field cm-grow"><span><?php esc_html_e( 'Heading', 'clean-maintenance' ); ?></span>
								<input id="cm-c-heading" type="text" class="regular-text" name="<?php echo esc_attr( $n ); ?>[c_heading]" value="<?php echo esc_attr( $s['c_heading'] ); ?>"></label>
							<div class="cm-field"><span><?php esc_html_e( 'Colour', 'clean-maintenance' ); ?></span><input type="text" class="cm-color" name="<?php echo esc_attr( $n ); ?>[c_heading_color]" value="<?php echo esc_attr( $s['c_heading_color'] ); ?>" data-default-color=""></div>
						</div>
						<div class="cm-row cm-row-color">
							<label class="cm-field cm-grow"><span><?php esc_html_e( 'Text', 'clean-maintenance' ); ?></span>
								<textarea id="cm-c-text" name="<?php echo esc_attr( $n ); ?>[c_text]" rows="5" class="large-text"><?php echo esc_textarea( $s['c_text'] ); ?></textarea></label>
							<div class="cm-field"><span><?php esc_html_e( 'Colour', 'clean-maintenance' ); ?></span><input type="text" class="cm-color" name="<?php echo esc_attr( $n ); ?>[c_text_color]" value="<?php echo esc_attr( $s['c_text_color'] ); ?>" data-default-color=""></div>
						</div>
						<p class="cm-help"><?php esc_html_e( 'HTML is allowed (paragraphs, links, emphasis).', 'clean-maintenance' ); ?></p>
						<?php clean_maintenance_switch( 'c_admin_link', ! empty( $s['c_admin_link'] ), __( 'Show a “Dashboard” link below the text', 'clean-maintenance' ) ); ?>
					</div>

					<div class="cm-sub">
						<span class="cm-field"><span><?php esc_html_e( 'Background', 'clean-maintenance' ); ?></span></span>
						<div class="cm-bg">
							<div class="cm-bg-swatch"<?php echo $s['bg_color'] ? ' style="background:' . esc_attr( $s['bg_color'] ) . '"' : ''; ?>>
								<img id="cm-bg-preview" src="<?php echo esc_url( $imgurl ); ?>" alt="" class="<?php echo $imgurl ? '' : 'hidden'; ?>">
							</div>
							<div class="cm-bg-controls">
								<input type="hidden" id="cm-bg-image" name="<?php echo esc_attr( $n ); ?>[bg_image]" value="<?php echo $img ? (int) $img : ''; ?>">
								<p><button type="button" class="button" id="cm-bg-pick"><?php esc_html_e( 'Choose image', 'clean-maintenance' ); ?></button>
								<button type="button" class="button-link<?php echo $imgurl ? '' : ' hidden'; ?>" id="cm-bg-remove"><?php esc_html_e( 'Remove image', 'clean-maintenance' ); ?></button></p>
								<p><input type="text" class="cm-color" id="cm-bg-color" name="<?php echo esc_attr( $n ); ?>[bg_color]" value="<?php echo esc_attr( $s['bg_color'] ); ?>" data-default-color=""></p>
								<p class="cm-help"><?php esc_html_e( 'The image fills the whole background; the colour lies beneath it or applies on its own.', 'clean-maintenance' ); ?></p>
							</div>
						</div>
					</div>
				</section>

				<section class="cm-card" id="cm-sec-access">
					<h2><?php echo clean_maintenance_icon( 'key' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Access', 'clean-maintenance' ); ?></h2>
					<p class="cm-lead"><?php esc_html_e( 'Who still gets in – and what stays reachable for everyone.', 'clean-maintenance' ); ?></p>

					<fieldset class="cm-sub">
						<legend class="cm-field"><span><?php esc_html_e( 'Roles with access to the site', 'clean-maintenance' ); ?></span></legend>
						<div class="cm-chips">
							<?php foreach ( $roles as $role => $role_name ) : ?>
								<label class="cm-chip"><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[roles][]" value="<?php echo esc_attr( $role ); ?>" <?php checked( in_array( $role, (array) $s['roles'], true ) ); ?>><span><?php echo esc_html( translate_user_role( $role_name ) ); ?></span></label>
							<?php endforeach; ?>
						</div>
						<p class="cm-help"><?php esc_html_e( 'Logged-in users with these roles see the normal site. Network administrators always have access.', 'clean-maintenance' ); ?></p>
					</fieldset>

					<fieldset class="cm-sub">
						<legend class="cm-field"><span><?php esc_html_e( 'Roles with access to the dashboard', 'clean-maintenance' ); ?></span></legend>
						<div class="cm-chips">
							<?php
							foreach ( $roles as $role => $role_name ) :
								if ( 'administrator' === $role ) {
									continue;
								}
								?>
								<label class="cm-chip"><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[backend_roles][]" value="<?php echo esc_attr( $role ); ?>" <?php checked( in_array( $role, (array) $s['backend_roles'], true ) ); ?>><span><?php echo esc_html( translate_user_role( $role_name ) ); ?></span></label>
							<?php endforeach; ?>
						</div>
						<p class="cm-help"><?php esc_html_e( 'During maintenance only administrators and these roles may use the dashboard; everyone else is redirected.', 'clean-maintenance' ); ?></p>
					</fieldset>

					<div class="cm-sub">
						<label class="cm-field" for="cm-redirect"><span><?php esc_html_e( 'Redirect', 'clean-maintenance' ); ?></span></label>
						<input id="cm-redirect" type="url" class="regular-text" name="<?php echo esc_attr( $n ); ?>[redirect]" value="<?php echo esc_attr( $s['redirect'] ); ?>" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>">
						<p class="cm-help"><?php esc_html_e( 'Where logged-in users without dashboard access are sent. Empty = home page.', 'clean-maintenance' ); ?></p>
					</div>

					<div class="cm-sub">
						<label class="cm-field" for="cm-exclude"><span><?php esc_html_e( 'Exceptions', 'clean-maintenance' ); ?></span></label>
						<textarea id="cm-exclude" name="<?php echo esc_attr( $n ); ?>[exclude]" rows="6" class="large-text code"><?php echo esc_textarea( $s['exclude'] ); ?></textarea>
						<p class="cm-help"><?php esc_html_e( 'Exclude the feed, pages, archives, IP addresses or address ranges from maintenance mode. One slug, IP address or range (e.g. 192.168.178.0/24) per line; comments start with # and may follow at the end of a line.', 'clean-maintenance' ); ?></p>
						<?php clean_maintenance_ip_hint( 'cm-exclude' ); ?>
					</div>
				</section>

				<section class="cm-card" id="cm-sec-search">
					<h2><?php echo clean_maintenance_icon( 'globe' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Search engines & API', 'clean-maintenance' ); ?></h2>
					<p class="cm-lead"><?php esc_html_e( 'How search engines and other programs are treated.', 'clean-maintenance' ); ?></p>

					<div class="cm-sub">
						<?php clean_maintenance_switch( 'bypass_bots', ! empty( $s['bypass_bots'] ), __( 'Bots see the normal site', 'clean-maintenance' ), __( 'Otherwise search engines also get the maintenance page with status 503 (temporary), which protects your ranking.', 'clean-maintenance' ) ); ?>
					</div>

					<div class="cm-sub">
						<label class="cm-field" for="cm-robots"><span><?php esc_html_e( 'Robots meta tag', 'clean-maintenance' ); ?></span></label>
						<select id="cm-robots" name="<?php echo esc_attr( $n ); ?>[meta_robots]">
							<option value="index" <?php selected( $s['meta_robots'], 'index' ); ?>>index, follow</option>
							<option value="noindex" <?php selected( $s['meta_robots'], 'noindex' ); ?>>noindex, nofollow</option>
						</select>
						<p class="cm-help"><?php esc_html_e( 'Instruction for search engines on the maintenance page.', 'clean-maintenance' ); ?></p>
					</div>

					<fieldset class="cm-sub">
						<legend class="cm-field"><span><?php esc_html_e( 'REST API for visitors', 'clean-maintenance' ); ?></span></legend>
						<div class="cm-segment">
							<?php
							$rest_modes = array(
								'off'         => __( 'Open', 'clean-maintenance' ),
								'maintenance' => __( 'Locked during maintenance', 'clean-maintenance' ),
								'always'      => __( 'Always locked', 'clean-maintenance' ),
							);
							foreach ( $rest_modes as $v => $l ) :
								?>
								<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[rest]" value="<?php echo esc_attr( $v ); ?>" <?php checked( $s['rest'], $v ); ?>><span><?php echo esc_html( $l ); ?></span></label>
							<?php endforeach; ?>
						</div>
						<p class="cm-help"><?php esc_html_e( 'Locked requests without login get “401”. Logged-in users, the block editor and Jetpack keep working. The REST API hints in the page head are removed as well.', 'clean-maintenance' ); ?></p>
						<label class="cm-field cm-gap" for="cm-rest-allow"><span><?php esc_html_e( 'Still public (one namespace per line, e.g. oembed or podlove; comments with #):', 'clean-maintenance' ); ?></span></label>
						<textarea id="cm-rest-allow" name="<?php echo esc_attr( $n ); ?>[rest_allow]" rows="4" class="large-text code"><?php echo esc_textarea( $s['rest_allow'] ); ?></textarea>
					</fieldset>
				</section>

				<?php
				if ( clean_maintenance_short_links_active() ) {
					clean_maintenance_short_links_card();
				}
				?>

				<section class="cm-card" id="cm-sec-comfort">
					<h2><?php echo clean_maintenance_icon( 'bell' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Comfort', 'clean-maintenance' ); ?></h2>
					<p class="cm-lead"><?php esc_html_e( 'Small helpers in the dashboard.', 'clean-maintenance' ); ?></p>
					<div class="cm-sub">
						<?php clean_maintenance_switch( 'notice', ! empty( $s['notice'] ), __( 'Notice', 'clean-maintenance' ), __( 'Red “Maintenance mode active” notice in the admin bar, with a switch to turn it off', 'clean-maintenance' ) ); ?>
					</div>
				</section>

				<div class="cm-savebar">
					<?php submit_button( __( 'Save settings', 'clean-maintenance' ), 'primary large', 'submit', false ); ?>
					<a href="<?php echo esc_url( $preview ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Preview', 'clean-maintenance' ); ?> <?php echo clean_maintenance_icon( 'external' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
				</div>
			</form>
		</div>
	</div>
	<?php
}

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( 'settings_page_clean-maintenance' !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'clean-maintenance-admin', plugins_url( 'assets/admin.css', CLEAN_MAINTENANCE_FILE ), array( 'wp-color-picker' ), clean_maintenance_asset_ver( 'admin.css' ) );
		wp_enqueue_script(
			'clean-maintenance-admin',
			plugins_url( 'assets/admin.js', CLEAN_MAINTENANCE_FILE ),
			array( 'jquery', 'wp-color-picker' ),
			clean_maintenance_asset_ver( 'admin.js' ),
			true
		);
		wp_localize_script(
			'clean-maintenance-admin',
			'cleanMaintenance',
			array(
				'pickTitle'  => __( 'Choose background image', 'clean-maintenance' ),
				'pickButton' => __( 'Use image', 'clean-maintenance' ),
			)
		);
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( CLEAN_MAINTENANCE_FILE ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=clean-maintenance' ) ) . '">' . esc_html__( 'Settings', 'clean-maintenance' ) . '</a>' );
		return $links;
	}
);
