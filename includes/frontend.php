<?php
/**
 * Clean Maintenance – output on the website.
 *
 * Shows the chosen page (without header and footer) with background instead of the site.
 * HTTP 503 with Retry-After so search engines treat the break as temporary.
 * Excluded: dashboard, login, robots.txt, listed slugs/IP addresses and logged-in users
 * with the chosen roles (network administrators always).
 * Preview for administrators: ?cm-preview=1
 *
 * @package CleanMaintenance
 */

defined( 'ABSPATH' ) || exit;

/**
 * May the current user see the normal site?
 *
 * @param array $s Settings.
 * @return bool
 */
function clean_maintenance_bypass( $s ) {
	if ( ! is_user_logged_in() ) {
		return false;
	}
	if ( is_super_admin() ) {
		return true;
	}
	return (bool) array_intersect( (array) wp_get_current_user()->roles, (array) $s['roles'] );
}

/**
 * Exceptions: one slug or IP address per line; "#" starts a comment.
 *
 * @param array $s Settings.
 * @return bool
 */
function clean_maintenance_excluded( $s ) {
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : '/';
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	foreach ( preg_split( '/\R/', (string) $s['exclude'] ) as $line ) {
		$line = trim( preg_replace( '/#.*$/', '', $line ) );
		if ( '' === $line ) {
			continue;
		}
		if ( filter_var( $line, FILTER_VALIDATE_IP ) ) {
			if ( $ip === $line ) {
				return true;
			}
			continue;
		}
		if ( 'feed' === strtolower( $line ) && is_feed() ) {
			return true;
		}
		if ( false !== stripos( $uri, $line ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Detect search engine and preview bots by user agent.
 *
 * @return bool
 */
function clean_maintenance_is_bot() {
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '';
	return '' !== $ua && (bool) preg_match( '/bot\b|bot\/|crawl|slurp|spider|mediapartners|facebookexternalhit|embedly|quora link preview|outbrain|pinterest|vkshare|w3c_validator/', $ua );
}

/**
 * Send 503 and Retry-After (not in preview).
 *
 * @param array $s       Settings.
 * @param bool  $preview Preview mode.
 */
function clean_maintenance_send_headers( $s, $preview ) {
	nocache_headers();
	if ( $preview ) {
		return;
	}
	$end = clean_maintenance_ts( $s['end'] );
	status_header( 503 );
	header( 'Retry-After: ' . ( ( 'scheduled' === $s['mode'] && $end > time() ) ? max( 60, $end - time() ) : HOUR_IN_SECONDS ) );
}

/**
 * Maintenance page in the style of the theme.
 *
 * @param array $s       Settings.
 * @param bool  $preview Preview mode.
 */
function clean_maintenance_render( $s, $preview = false ) {
	do_action( 'clean_maintenance_render' ); // Lets other plugins hide extras such as mobile bars.
	$page = (int) $s['page'] ? get_post( (int) $s['page'] ) : null;
	if ( $page && 'page' !== $page->post_type ) {
		$page = null;
	}

	clean_maintenance_send_headers( $s, $preview );

	// Render the content first so block and layout styles end up in the head.
	if ( $page ) {
		global $wp_query, $wp_the_query;
		$wp_query     = new WP_Query( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the maintenance page replaces the main query.
			array(
				'page_id'     => $page->ID,
				'post_status' => array( 'publish', 'private', 'draft', 'pending', 'future' ),
			)
		);
		$wp_the_query = $wp_query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		if ( $wp_query->have_posts() ) {
			$wp_query->the_post();
		}
		$content = apply_filters( 'the_content', $page->post_content );
	} else {
		/**
		 * Name shown on the simple default maintenance notice.
		 *
		 * @param string $name Site title.
		 */
		$name    = apply_filters( 'clean_maintenance_site_name', get_bloginfo( 'name' ) );
		$content = '<h1 class="wp-block-heading has-text-align-center">' . esc_html( $name ) . '</h1>'
			. '<p class="has-text-align-center">' . esc_html__( 'This website is currently being updated. Please check back soon.', 'clean-maintenance' ) . '</p>';
	}

	if ( 'noindex' === $s['meta_robots'] ) {
		add_filter(
			'wp_robots',
			function ( $r ) {
				return array_merge(
					$r,
					array(
						'noindex'  => true,
						'nofollow' => true,
					)
				);
			}
		);
	}

	$img   = (int) $s['bg_image'] ? wp_get_attachment_image_url( (int) $s['bg_image'], 'full' ) : '';
	$color = (string) $s['bg_color'];
	$bg    = ( $color ? $color : 'transparent' ) . ( $img ? ' url(' . esc_url( $img ) . ') center / cover no-repeat fixed' : '' );
	?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php if ( ! current_theme_supports( 'title-tag' ) ) : ?>
<title><?php echo esc_html( wp_get_document_title() ); ?></title>
	<?php endif; ?>
	<?php wp_head(); ?>
<style id="cm-maintenance-css">
body.cm-maintenance{margin:0;min-height:100vh;<?php echo ( $color || $img ) ? 'background:' . esc_attr( $bg ) . ';' : ''; ?>}
.cm-maintenance .cm-page{min-height:100vh;box-sizing:border-box;display:flex;flex-direction:column;justify-content:center;padding-block:var(--wp--preset--spacing--60,4rem)}
</style>
</head>
<body <?php body_class( 'cm-maintenance' ); ?>>
	<?php wp_body_open(); ?>
<div class="wp-site-blocks">
<main class="cm-page wp-block-group has-global-padding is-layout-constrained">
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered page content. ?>
</main>
</div>
	<?php wp_footer(); ?>
</body>
</html>
	<?php
	exit;
}

/**
 * Classic maintenance page: standalone, without theme – heading, text, background colour or image.
 *
 * @param array $s       Settings.
 * @param bool  $preview Preview mode.
 */
function clean_maintenance_render_classic( $s, $preview = false ) {
	do_action( 'clean_maintenance_render' );
	clean_maintenance_send_headers( $s, $preview );
	header( 'Content-Type: text/html; charset=UTF-8' );
	$img  = (int) $s['bg_image'] ? wp_get_attachment_image_url( (int) $s['bg_image'], 'full' ) : '';
	$name = get_bloginfo( 'name' );
	$desc = get_bloginfo( 'description' );
	$css  = '*{margin:0;padding:0;border:0;font-size:100%;font:inherit;vertical-align:baseline;text-decoration:none}'
		. 'body{line-height:1}em,i{font-style:italic}b,strong{font-weight:700}'
		. 'ol,ul{width:600px;margin:0 auto;text-align:left}ol li{list-style-type:decimal}ul li{list-style:disc}'
		. 'a{color:inherit;text-decoration:underline}body,html{min-height:100%}'
		. 'body{background-color:#fff;color:#1b1f23;font-family:Helvetica Neue,Arial,sans-serif}'
		. '.wrap{width:605px;margin:100px auto 0;text-align:center}'
		. '.wrap h1{font-size:30px;font-weight:700;margin:0 0 90px}'
		. '.wrap h2{font-size:24px;font-weight:400;line-height:45px;margin:0 0 80px}'
		. '.wrap h2 img{max-width:100%;height:auto}'
		. '.wrap .footer_links{color:#616161;margin:0 0 50px}.wrap .footer_links a{color:#000}'
		. '@media only screen and (max-width:550px){.wrap{width:90%;margin:50px auto 0}.wrap h1{margin:0 0 50px}.wrap h2{font-size:18px}ol,ul{width:80%}}';
	if ( $s['c_heading_color'] ) {
		$css .= '.wrap h1{color:' . $s['c_heading_color'] . '}';
	}
	if ( $s['c_text_color'] ) {
		$css .= '.wrap h2{color:' . $s['c_text_color'] . '}';
	}
	if ( $s['bg_color'] ) {
		$css .= 'body{background-color:' . $s['bg_color'] . '}';
	}
	if ( $img ) {
		$css .= '.background{background:url("' . esc_url( $img ) . '") no-repeat center top fixed;background-size:cover}';
	}
	?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="UTF-8">
<title><?php echo esc_html( $s['c_title'] ); ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="author" content="<?php echo esc_attr( $name ); ?>" />
<meta name="description" content="<?php echo esc_attr( $name . ( $desc ? ' - ' . $desc : '' ) ); ?>" />
<meta name="robots" content="<?php echo esc_attr( 'noindex' === $s['meta_robots'] ? 'noindex, nofollow' : 'index, follow' ); ?>" />
<style id="cm-classic-css"><?php echo wp_strip_all_tags( $css ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- colours are sanitized hex values. ?></style>
</head>
<body class="<?php echo esc_attr( $img ? 'background' : '' ); ?>">
<div class="wrap" role="main">
	<?php if ( '' !== $s['c_heading'] ) : ?>
<h1><?php echo esc_html( $s['c_heading'] ); ?></h1>
	<?php endif; ?>
	<?php if ( '' !== trim( $s['c_text'] ) ) : ?>
<h2><?php echo wp_kses_post( $s['c_text'] ); ?></h2>
	<?php endif; ?>
	<?php if ( ! empty( $s['c_admin_link'] ) ) : ?>
<div class="footer_links"><a href="<?php echo esc_url( admin_url() ); ?>"><?php esc_html_e( 'Dashboard', 'clean-maintenance' ); ?></a></div>
	<?php endif; ?>
</div>
</body>
</html>
	<?php
	exit;
}

add_action(
	'template_redirect',
	function () {
		$s       = clean_maintenance_settings();
		$preview = isset( $_GET['cm-preview'] ) && current_user_can( 'manage_options' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preview switch.
		if ( ! $preview ) {
			if ( ! clean_maintenance_is_active( $s ) || clean_maintenance_bypass( $s ) ) {
				return;
			}
			if ( is_robots() || clean_maintenance_excluded( $s ) ) {
				return;
			}
			if ( ! empty( $s['bypass_bots'] ) && clean_maintenance_is_bot() ) {
				return;
			}
		}
		if ( 'classic' === $s['design'] ) {
			clean_maintenance_render_classic( $s, $preview );
		}
		clean_maintenance_render( $s, $preview );
	},
	0
);

/**
 * Show the notice in the admin bar? (Setting "notice", never in the network admin.)
 *
 * @return bool
 */
function clean_maintenance_show_flag() {
	if ( is_network_admin() || ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
		return false;
	}
	$s = clean_maintenance_settings();
	return ! empty( $s['notice'] ) && clean_maintenance_is_active( $s );
}

// Red notice in the admin bar while maintenance mode is active on this site – with a quick switch.
add_action(
	'admin_bar_menu',
	function ( $bar ) {
		if ( ! clean_maintenance_show_flag() ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'cm-maintenance',
				'title' => esc_html__( 'Maintenance mode active', 'clean-maintenance' ),
				'href'  => admin_url( 'options-general.php?page=clean-maintenance' ),
			)
		);
		$bar->add_node(
			array(
				'parent' => 'cm-maintenance',
				'id'     => 'cm-maintenance-off',
				'title'  => esc_html__( 'Turn off maintenance mode', 'clean-maintenance' ),
				'href'   => wp_nonce_url( admin_url( 'admin-post.php?action=clean_maintenance_off' ), 'clean_maintenance_off' ),
			)
		);
		$bar->add_node(
			array(
				'parent' => 'cm-maintenance',
				'id'     => 'cm-maintenance-settings',
				'title'  => esc_html__( 'Settings', 'clean-maintenance' ),
				'href'   => admin_url( 'options-general.php?page=clean-maintenance' ),
			)
		);
	},
	100
);

// Quick switch: turn off maintenance mode for this site and go back.
add_action(
	'admin_post_clean_maintenance_off',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'clean-maintenance' ) );
		}
		check_admin_referer( 'clean_maintenance_off' );
		clean_maintenance_set_mode( get_current_blog_id(), 'off' );
		$back = wp_get_referer();
		wp_safe_redirect( $back ? remove_query_arg( 'cm-preview', $back ) : admin_url() );
		exit;
	}
);

/**
 * Colour of the admin bar notice.
 */
function clean_maintenance_flag_style() {
	if ( clean_maintenance_show_flag() ) {
		wp_add_inline_style( 'admin-bar', '#wpadminbar #wp-admin-bar-cm-maintenance>.ab-item{background:#e4572e;color:#fff}' );
	}
}
add_action( 'wp_enqueue_scripts', 'clean_maintenance_flag_style', 20 );
add_action( 'admin_enqueue_scripts', 'clean_maintenance_flag_style', 20 );

// Dashboard: logged-in users without a matching role are redirected during maintenance.
add_action(
	'admin_init',
	function () {
		if ( wp_doing_ajax() || ! is_user_logged_in() ) {
			return;
		}
		$s = clean_maintenance_settings();
		if ( ! clean_maintenance_is_active( $s ) || is_super_admin() || current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( array_intersect( (array) wp_get_current_user()->roles, (array) $s['backend_roles'] ) ) {
			return;
		}
		wp_safe_redirect( $s['redirect'] ? $s['redirect'] : home_url( '/' ) );
		exit;
	}
);

/*
 * -------------------------------------------------------------------------
 * Lock the REST API for visitors who are not logged in (setting "rest").
 * -------------------------------------------------------------------------
 */

/**
 * Is the REST API currently locked for visitors who are not logged in?
 *
 * @param array|null $s Settings.
 * @return bool
 */
function clean_maintenance_rest_locked( $s = null ) {
	$s = $s ? $s : clean_maintenance_settings();
	$m = isset( $s['rest'] ) ? $s['rest'] : 'maintenance';
	return 'always' === $m || ( 'maintenance' === $m && clean_maintenance_is_active( $s ) );
}

/**
 * Does a REST route belong to one of the namespaces that stay public?
 *
 * @param string $route      Route such as "/wp/v2/users" or "oembed/1.0/embed".
 * @param string $allow_list One namespace per line, "#" starts a comment.
 * @return bool
 */
function clean_maintenance_rest_route_allowed( $route, $allow_list ) {
	$route = '/' . ltrim( (string) $route, '/' );
	foreach ( preg_split( '/\R/', (string) $allow_list ) as $line ) {
		$line = trim( preg_replace( '/#.*$/', '', $line ), " \t/" );
		if ( '' !== $line && 0 === strpos( $route, '/' . $line ) ) {
			return true;
		}
	}
	return false;
}

add_filter(
	'rest_authentication_errors',
	function ( $result ) {
		if ( true === $result || is_wp_error( $result ) || is_user_logged_in() ) {
			return $result;
		}
		$s = clean_maintenance_settings();
		if ( ! clean_maintenance_rest_locked( $s ) ) {
			return $result;
		}
		$qv = isset( $GLOBALS['wp'] ) && is_object( $GLOBALS['wp'] ) ? (array) $GLOBALS['wp']->query_vars : array();
		if ( clean_maintenance_rest_route_allowed( (string) ( $qv['rest_route'] ?? '' ), (string) ( $s['rest_allow'] ?? '' ) ) ) {
			return $result;
		}
		return new WP_Error( 'rest_login_required', __( 'The REST API is only available to logged-in users.', 'clean-maintenance' ), array( 'status' => 401 ) );
	},
	90
);

add_action(
	'init',
	function () {
		if ( ! clean_maintenance_rest_locked() ) {
			return;
		}
		remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
		remove_action( 'template_redirect', 'rest_output_link_header', 11 );
		remove_action( 'xmlrpc_rsd_apis', 'rest_output_rsd' );
	}
);
