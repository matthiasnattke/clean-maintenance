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
	}
);

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
	$out['exclude']         = sanitize_textarea_field( $in['exclude'] ?? '' );
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
 * Render the settings page.
 */
function clean_maintenance_render_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s      = clean_maintenance_settings();
	$status = clean_maintenance_status( $s );
	$pages  = get_pages(
		array(
			'post_status' => array( 'publish', 'private', 'draft' ),
			'sort_column' => 'post_title',
		)
	);
	$img    = (int) $s['bg_image'];
	$imgurl = $img ? wp_get_attachment_image_url( $img, 'medium' ) : '';
	$n      = 'clean_maintenance';
	$label  = 'display:inline-block;min-width:11em;margin:.2em 1em .2em 0';
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Maintenance mode', 'clean-maintenance' ); ?></h1>
		<?php settings_errors( 'clean_maintenance' ); ?>
		<p class="cm-status<?php echo $status[0] ? ' is-active' : ''; ?>"><?php echo esc_html( $status[1] ); ?></p>
		<?php if ( is_multisite() && current_user_can( 'manage_network_options' ) ) : ?>
			<p><a href="<?php echo esc_url( network_admin_url( 'settings.php?page=clean-maintenance-network' ) ); ?>"><?php esc_html_e( 'Overview of all sites in the network', 'clean-maintenance' ); ?></a></p>
		<?php endif; ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'clean_maintenance' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Mode', 'clean-maintenance' ); ?></th>
					<td><fieldset>
						<?php
						$modes = array(
							'off'       => __( 'Off', 'clean-maintenance' ),
							'on'        => __( 'On – right away, until I turn it off', 'clean-maintenance' ),
							'scheduled' => __( 'Scheduled', 'clean-maintenance' ),
						);
						foreach ( $modes as $v => $l ) :
							?>
							<label class="cm-block"><input type="radio" name="<?php echo esc_attr( $n ); ?>[mode]" value="<?php echo esc_attr( $v ); ?>" <?php checked( $s['mode'], $v ); ?>> <?php echo esc_html( $l ); ?></label>
						<?php endforeach; ?>
					</fieldset></td>
				</tr>
				<tr class="cm-when">
					<th scope="row"><?php esc_html_e( 'Period', 'clean-maintenance' ); ?></th>
					<td>
						<label><?php esc_html_e( 'Start', 'clean-maintenance' ); ?> <input type="datetime-local" name="<?php echo esc_attr( $n ); ?>[start]" value="<?php echo esc_attr( $s['start'] ); ?>"></label>
						&nbsp; <label><?php esc_html_e( 'End', 'clean-maintenance' ); ?> <input type="datetime-local" name="<?php echo esc_attr( $n ); ?>[end]" value="<?php echo esc_attr( $s['end'] ); ?>"></label>
						<p class="description">
							<?php
							/* translators: %s: time zone of the site, e.g. Europe/Berlin. */
							echo esc_html( sprintf( __( 'Time zone of the site (%s). Without a start the mode applies right away, without an end until you turn it off.', 'clean-maintenance' ), wp_timezone_string() ) );
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Design', 'clean-maintenance' ); ?></th>
					<td><fieldset>
						<label class="cm-block"><input type="radio" name="<?php echo esc_attr( $n ); ?>[design]" value="page" <?php checked( $s['design'], 'page' ); ?>> <?php esc_html_e( 'Page from WordPress – in the style of the theme', 'clean-maintenance' ); ?></label>
						<label class="cm-block"><input type="radio" name="<?php echo esc_attr( $n ); ?>[design]" value="classic" <?php checked( $s['design'], 'classic' ); ?>> <?php esc_html_e( 'Classic – heading, text and background, independent of the theme', 'clean-maintenance' ); ?></label>
					</fieldset></td>
				</tr>
				<tr class="cm-classic">
					<th scope="row"><label for="cm-c-title"><?php esc_html_e( 'Title in the browser tab', 'clean-maintenance' ); ?></label></th>
					<td><input id="cm-c-title" type="text" class="regular-text" name="<?php echo esc_attr( $n ); ?>[c_title]" value="<?php echo esc_attr( $s['c_title'] ); ?>"></td>
				</tr>
				<tr class="cm-classic">
					<th scope="row"><label for="cm-c-heading"><?php esc_html_e( 'Heading', 'clean-maintenance' ); ?></label></th>
					<td><input id="cm-c-heading" type="text" class="regular-text" name="<?php echo esc_attr( $n ); ?>[c_heading]" value="<?php echo esc_attr( $s['c_heading'] ); ?>">
					<p class="cm-gap"><input type="text" class="cm-color" name="<?php echo esc_attr( $n ); ?>[c_heading_color]" value="<?php echo esc_attr( $s['c_heading_color'] ); ?>" data-default-color=""></p></td>
				</tr>
				<tr class="cm-classic">
					<th scope="row"><label for="cm-c-text"><?php esc_html_e( 'Text', 'clean-maintenance' ); ?></label></th>
					<td><textarea id="cm-c-text" name="<?php echo esc_attr( $n ); ?>[c_text]" rows="6" class="large-text"><?php echo esc_textarea( $s['c_text'] ); ?></textarea>
					<p class="cm-gap"><input type="text" class="cm-color" name="<?php echo esc_attr( $n ); ?>[c_text_color]" value="<?php echo esc_attr( $s['c_text_color'] ); ?>" data-default-color=""></p>
					<p class="description"><?php esc_html_e( 'HTML is allowed (paragraphs, links, emphasis).', 'clean-maintenance' ); ?></p></td>
				</tr>
				<tr class="cm-classic">
					<th scope="row"><?php esc_html_e( 'Dashboard link', 'clean-maintenance' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[c_admin_link]" value="1" <?php checked( ! empty( $s['c_admin_link'] ) ); ?>> <?php esc_html_e( 'Show a “Dashboard” link below the text', 'clean-maintenance' ); ?></label></td>
				</tr>
				<tr class="cm-page-row">
					<th scope="row"><label for="cm-page"><?php esc_html_e( 'Maintenance page', 'clean-maintenance' ); ?></label></th>
					<td>
						<select id="cm-page" name="<?php echo esc_attr( $n ); ?>[page]">
							<option value="0"><?php esc_html_e( '– simple notice with the site title –', 'clean-maintenance' ); ?></option>
							<?php foreach ( $pages as $p ) : ?>
								<option value="<?php echo (int) $p->ID; ?>" <?php selected( (int) $s['page'], (int) $p->ID ); ?>><?php echo esc_html( ( $p->post_title ? $p->post_title : '#' . $p->ID ) . ( 'publish' !== $p->post_status ? ' (' . $p->post_status . ')' : '' ) ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php if ( (int) $s['page'] ) : ?>
							<a href="<?php echo esc_url( get_edit_post_link( (int) $s['page'] ) ); ?>"><?php esc_html_e( 'Edit page', 'clean-maintenance' ); ?></a> ·
						<?php endif; ?>
						<a href="<?php echo esc_url( add_query_arg( 'cm-preview', '1', home_url( '/' ) ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Preview', 'clean-maintenance' ); ?></a>
						<p class="description"><?php esc_html_e( 'Shown everywhere without header and footer. Best create a page of its own as a draft or private – then it is not visible anywhere else.', 'clean-maintenance' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Background', 'clean-maintenance' ); ?></th>
					<td>
						<input type="hidden" id="cm-bg-image" name="<?php echo esc_attr( $n ); ?>[bg_image]" value="<?php echo $img ? (int) $img : ''; ?>">
						<img id="cm-bg-preview" src="<?php echo esc_url( $imgurl ); ?>" alt="" class="<?php echo $imgurl ? '' : 'hidden'; ?>">
						<button type="button" class="button" id="cm-bg-pick"><?php esc_html_e( 'Choose image', 'clean-maintenance' ); ?></button>
						<button type="button" class="button-link<?php echo $imgurl ? '' : ' hidden'; ?>" id="cm-bg-remove"><?php esc_html_e( 'Remove image', 'clean-maintenance' ); ?></button>
						<p class="cm-gap"><input type="text" class="cm-color" name="<?php echo esc_attr( $n ); ?>[bg_color]" value="<?php echo esc_attr( $s['bg_color'] ); ?>" data-default-color=""></p>
						<p class="description"><?php esc_html_e( 'The image fills the whole background; the colour lies beneath it or applies on its own.', 'clean-maintenance' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="cm-exclude"><?php esc_html_e( 'Exceptions', 'clean-maintenance' ); ?></label></th>
					<td>
						<textarea id="cm-exclude" name="<?php echo esc_attr( $n ); ?>[exclude]" rows="6" class="large-text code"><?php echo esc_textarea( $s['exclude'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Exclude the feed, pages, archives or IP addresses from maintenance mode. One slug or IP per line; comments start with # and may follow at the end of a line.', 'clean-maintenance' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Roles with access to the site', 'clean-maintenance' ); ?></th>
					<td>
						<fieldset>
							<?php foreach ( wp_roles()->get_names() as $role => $role_name ) : ?>
								<label style="<?php echo esc_attr( $label ); ?>"><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[roles][]" value="<?php echo esc_attr( $role ); ?>" <?php checked( in_array( $role, (array) $s['roles'], true ) ); ?>> <?php echo esc_html( translate_user_role( $role_name ) ); ?></label>
							<?php endforeach; ?>
						</fieldset>
						<p class="description"><?php esc_html_e( 'Logged-in users with these roles see the normal site. Network administrators always have access.', 'clean-maintenance' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Roles with access to the dashboard', 'clean-maintenance' ); ?></th>
					<td>
						<fieldset>
							<?php
							foreach ( wp_roles()->get_names() as $role => $role_name ) :
								if ( 'administrator' === $role ) {
									continue;
								}
								?>
								<label style="<?php echo esc_attr( $label ); ?>"><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[backend_roles][]" value="<?php echo esc_attr( $role ); ?>" <?php checked( in_array( $role, (array) $s['backend_roles'], true ) ); ?>> <?php echo esc_html( translate_user_role( $role_name ) ); ?></label>
							<?php endforeach; ?>
						</fieldset>
						<p class="description"><?php esc_html_e( 'During maintenance only administrators and these roles may use the dashboard; everyone else is redirected.', 'clean-maintenance' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="cm-redirect"><?php esc_html_e( 'Redirect', 'clean-maintenance' ); ?></label></th>
					<td>
						<input id="cm-redirect" type="url" class="regular-text" name="<?php echo esc_attr( $n ); ?>[redirect]" value="<?php echo esc_attr( $s['redirect'] ); ?>" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>">
						<p class="description"><?php esc_html_e( 'Where logged-in users without dashboard access are sent. Empty = home page.', 'clean-maintenance' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Search engine bots', 'clean-maintenance' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[bypass_bots]" value="1" <?php checked( ! empty( $s['bypass_bots'] ) ); ?>> <?php esc_html_e( 'Bots see the normal site', 'clean-maintenance' ); ?></label>
					<p class="description"><?php esc_html_e( 'Otherwise search engines also get the maintenance page with status 503 (temporary), which protects your ranking.', 'clean-maintenance' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="cm-robots"><?php esc_html_e( 'Robots meta tag', 'clean-maintenance' ); ?></label></th>
					<td>
						<select id="cm-robots" name="<?php echo esc_attr( $n ); ?>[meta_robots]">
							<option value="index" <?php selected( $s['meta_robots'], 'index' ); ?>>index, follow</option>
							<option value="noindex" <?php selected( $s['meta_robots'], 'noindex' ); ?>>noindex, nofollow</option>
						</select>
						<p class="description"><?php esc_html_e( 'Instruction for search engines on the maintenance page.', 'clean-maintenance' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'REST API for visitors', 'clean-maintenance' ); ?></th>
					<td><fieldset>
						<?php
						$rest_modes = array(
							'off'         => __( 'Open – as WordPress intends', 'clean-maintenance' ),
							'maintenance' => __( 'Locked for visitors who are not logged in during maintenance', 'clean-maintenance' ),
							'always'      => __( 'Always locked for visitors who are not logged in', 'clean-maintenance' ),
						);
						foreach ( $rest_modes as $v => $l ) :
							?>
							<label class="cm-block"><input type="radio" name="<?php echo esc_attr( $n ); ?>[rest]" value="<?php echo esc_attr( $v ); ?>" <?php checked( $s['rest'], $v ); ?>> <?php echo esc_html( $l ); ?></label>
						<?php endforeach; ?>
					</fieldset>
					<p class="cm-gap"><label for="cm-rest-allow"><?php esc_html_e( 'Still public (one namespace per line, e.g. oembed or podlove; comments with #):', 'clean-maintenance' ); ?></label></p>
					<textarea id="cm-rest-allow" name="<?php echo esc_attr( $n ); ?>[rest_allow]" rows="5" class="large-text code"><?php echo esc_textarea( $s['rest_allow'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Locked requests without login get “401”. Logged-in users, the block editor and Jetpack keep working. The REST API hints in the page head are removed as well.', 'clean-maintenance' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Notice', 'clean-maintenance' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[notice]" value="1" <?php checked( ! empty( $s['notice'] ) ); ?>> <?php esc_html_e( 'Red “Maintenance mode active” notice in the admin bar, with a switch to turn it off', 'clean-maintenance' ); ?></label></td>
				</tr>
			</table>
			<?php submit_button( __( 'Save settings', 'clean-maintenance' ) ); ?>
		</form>
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
		wp_add_inline_style(
			'wp-color-picker',
			'.cm-status{display:inline-block;padding:.35em .8em;border-radius:999px;background:#eef0f4;color:#3c434a}'
			. '.cm-status.is-active{background:#fbe3dc;color:#a3341a}'
			. '.cm-block{display:block;margin:.2em 0}.cm-gap{margin-top:.6em}'
			. '#cm-bg-preview{max-width:220px;height:auto;border-radius:6px;display:block;margin-bottom:.5em}#cm-bg-preview.hidden{display:none}#cm-bg-remove{margin-left:.6em}'
		);
		wp_enqueue_script(
			'clean-maintenance-admin',
			plugins_url( 'assets/admin.js', CLEAN_MAINTENANCE_FILE ),
			array( 'jquery', 'wp-color-picker' ),
			CLEAN_MAINTENANCE_VERSION,
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
