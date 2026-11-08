<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGBP_Admin {

	const PAGE_SLUG = 'soro-gbp-auto-post';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
	}

	public static function add_menu() {
		add_options_page(
			__( 'Soro to Google Business Profile', 'soro-gbp-auto-post' ),
			__( 'Soro → Google Business', 'soro-gbp-auto-post' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function handle_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_GET['page'], $_GET['sgbp_oauth'] ) && self::PAGE_SLUG === $_GET['page'] && 'callback' === $_GET['sgbp_oauth'] ) {
			SGBP_Logger::log( array(
				'status'  => 'info',
				'title'   => 'OAuth callback',
				'message' => 'Callback request received. code=' . ( isset( $_GET['code'] ) ? 'present' : 'missing' ) . ', state=' . ( isset( $_GET['state'] ) ? 'present' : 'missing' ) . ', URI=' . esc_url_raw( set_url_scheme( 'http://' . ( isset( $_SERVER['HTTP_HOST'] ) ? wp_unslash( $_SERVER['HTTP_HOST'] ) : '' ) . ( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '' ) ) ),
			) );
			if ( ! isset( $_GET['code'] ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&sgbp_msg=oauth_failed' ) );
				exit;
			}
			$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
			if ( ! wp_verify_nonce( $state, 'sgbp_oauth' ) ) {
				SGBP_Logger::log( array(
					'status'  => 'error',
					'title'   => 'OAuth callback',
					'message' => 'Invalid OAuth state (nonce check failed). This usually means the browser session or a caching plugin dropped the state parameter.',
				) );
				wp_die( esc_html__( 'Invalid OAuth state.', 'soro-gbp-auto-post' ) );
			}
			$code = sanitize_text_field( wp_unslash( $_GET['code'] ) );
			$result = SGBP_Google_API::exchange_code( $code );
			if ( is_wp_error( $result ) ) {
				SGBP_Logger::log( array(
					'status'  => 'error',
					'title'   => 'OAuth connect',
					'message' => 'Token exchange failed: ' . $result->get_error_message(),
				) );
				wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&sgbp_msg=oauth_error&sgbp_err=' . rawurlencode( $result->get_error_message() ) ) );
				exit;
			}
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&sgbp_msg=oauth_success' ) );
			exit;
		}

		if ( ! isset( $_POST['sgbp_action'] ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['sgbp_action'] ) );

		if ( 'save_settings' === $action ) {
			check_admin_referer( 'sgbp_save_settings' );
			$settings = SGBP_Google_API::settings();
			$new = array(
				'client_id'          => isset( $_POST['sgbp_client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['sgbp_client_id'] ) ) : '',
				'client_secret'      => isset( $_POST['sgbp_client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['sgbp_client_secret'] ) ) : '',
				'cta_type'           => isset( $_POST['sgbp_cta_type'] ) ? sanitize_key( wp_unslash( $_POST['sgbp_cta_type'] ) ) : 'LEARN_MORE',
				'allowed_languages'  => isset( $_POST['sgbp_languages'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['sgbp_languages'] ) ) : array(),
				'post_categories'    => isset( $_POST['sgbp_categories'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['sgbp_categories'] ) ) : array(),
				'fallback_image_id'  => isset( $_POST['sgbp_fallback_image_id'] ) ? absint( $_POST['sgbp_fallback_image_id'] ) : 0,
				'enabled'           => isset( $_POST['sgbp_enabled'] ) ? 1 : 0,
				'locations'         => isset( $settings['locations'] ) ? $settings['locations'] : array(),
			);
			update_option( 'sgbp_settings', array_merge( $settings, $new ) );
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&sgbp_msg=saved' ) );
			exit;
		}

		if ( 'refresh_locations' === $action ) {
			check_admin_referer( 'sgbp_refresh_locations' );
			$locations = SGBP_Google_API::get_locations();
			if ( is_wp_error( $locations ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&sgbp_msg=locations_error&sgbp_err=' . rawurlencode( $locations->get_error_message() ) ) );
				exit;
			}
			update_option( 'sgbp_available_locations', $locations );
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&sgbp_msg=locations_refreshed' ) );
			exit;
		}

		if ( 'save_locations' === $action ) {
			check_admin_referer( 'sgbp_save_locations' );
			$settings = SGBP_Google_API::settings();
			$selected = isset( $_POST['sgbp_locations'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['sgbp_locations'] ) ) : array();
			$settings['locations'] = array_values( array_filter( $selected ) );
			update_option( 'sgbp_settings', $settings );
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&sgbp_msg=saved' ) );
			exit;
		}

		if ( 'disconnect' === $action ) {
			check_admin_referer( 'sgbp_disconnect' );
			$settings = SGBP_Google_API::settings();
			$settings['access_token']  = '';
			$settings['refresh_token']  = '';
			$settings['token_expires']  = 0;
			update_option( 'sgbp_settings', $settings );
			delete_option( 'sgbp_available_locations' );
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&sgbp_msg=disconnected' ) );
			exit;
		}

		if ( 'clear_log' === $action ) {
			check_admin_referer( 'sgbp_clear_log' );
			SGBP_Logger::clear();
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&sgbp_msg=log_cleared' ) );
			exit;
		}
	}

	private static function messages() {
		$messages = array(
			'saved'               => __( 'Settings saved.', 'soro-gbp-auto-post' ),
			'oauth_success'       => __( 'Google account connected successfully.', 'soro-gbp-auto-post' ),
			'oauth_error'         => __( 'Could not connect to Google. Check your Client ID, Client Secret, and that the redirect URI matches exactly.', 'soro-gbp-auto-post' ),
			'oauth_failed'        => __( 'Google returned no authorization code.', 'soro-gbp-auto-post' ),
			'disconnected'        => __( 'Google account disconnected.', 'soro-gbp-auto-post' ),
			'locations_refreshed' => __( 'Business locations refreshed.', 'soro-gbp-auto-post' ),
			'locations_error'     => __( 'Could not load locations: ', 'soro-gbp-auto-post' ),
			'log_cleared'         => __( 'Log cleared.', 'soro-gbp-auto-post' ),
		);
		return apply_filters( 'sgbp_admin_messages', $messages );
	}

	public static function render_page() {
		$settings = SGBP_Google_API::settings();
		$all_settings = wp_parse_args( get_option( 'sgbp_settings', array() ), array(
			'allowed_languages' => array(),
			'post_categories'   => array(),
			'cta_type'          => 'LEARN_MORE',
			'fallback_image_id' => 0,
			'enabled'           => 1,
		) );
		$available_locations = get_option( 'sgbp_available_locations', array() );
		$connected = SGBP_Google_API::is_connected();
		$messages = self::messages();

		if ( isset( $_GET['sgbp_msg'] ) && isset( $messages[ $_GET['sgbp_msg'] ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$key = sanitize_key( wp_unslash( $_GET['sgbp_msg'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$msg = $messages[ $key ];
			if ( isset( $_GET['sgbp_err'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$msg .= ' ' . sanitize_text_field( rawurldecode( wp_unslash( $_GET['sgbp_err'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
			$error_keys = array( 'oauth_error', 'oauth_failed', 'locations_error' );
			$class = in_array( $key, $error_keys, true ) ? 'notice-error' : 'notice-success';
			echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Soro to Google Business Profile', 'soro-gbp-auto-post' ); ?></h1>

			<form method="post">
				<?php wp_nonce_field( 'sgbp_save_settings' ); ?>
				<input type="hidden" name="sgbp_action" value="save_settings" />
				<h2><?php esc_html_e( 'Google API credentials', 'soro-gbp-auto-post' ); ?></h2>
				<p><?php esc_html_e( 'Redirect URI (add this in Google Cloud Console under Authorized redirect URIs):', 'soro-gbp-auto-post' ); ?></p>
				<code><?php echo esc_html( SGBP_Google_API::redirect_uri() ); ?></code>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="sgbp_client_id"><?php esc_html_e( 'Client ID', 'soro-gbp-auto-post' ); ?></label></th>
						<td><input type="text" class="regular-text" id="sgbp_client_id" name="sgbp_client_id" value="<?php echo esc_attr( $settings['client_id'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="sgbp_client_secret"><?php esc_html_e( 'Client Secret', 'soro-gbp-auto-post' ); ?></label></th>
						<td><input type="password" class="regular-text" autocomplete="off" id="sgbp_client_secret" name="sgbp_client_secret" value="<?php echo esc_attr( $settings['client_secret'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Connection', 'soro-gbp-auto-post' ); ?></th>
						<td>
							<?php if ( $connected ) : ?>
								<p style="color:#00a32a;font-weight:bold;"><?php esc_html_e( 'Connected to Google', 'soro-gbp-auto-post' ); ?></p>
							<?php else : ?>
								<p><?php esc_html_e( 'Not connected. Save your credentials first, then click Connect.', 'soro-gbp-auto-post' ); ?></p>
							<?php endif; ?>
							<?php if ( SGBP_Google_API::is_configured() ) : ?>
								<a class="button button-secondary" href="<?php echo esc_url( SGBP_Google_API::get_connect_url() ); ?>"><?php esc_html_e( 'Connect Google account', 'soro-gbp-auto-post' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sgbp_enabled"><?php esc_html_e( 'Enable auto-posting', 'soro-gbp-auto-post' ); ?></label></th>
						<td><input type="checkbox" id="sgbp_enabled" name="sgbp_enabled" <?php checked( ! empty( $all_settings['enabled'] ) ); ?> />
							<span class="description"><?php esc_html_e( 'Cross-post new published articles to Google Business Profile.', 'soro-gbp-auto-post' ); ?></span></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Filters', 'soro-gbp-auto-post' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Languages to post', 'soro-gbp-auto-post' ); ?></th>
						<td>
							<?php foreach ( SGBP_Language::available_languages() as $code => $label ) : ?>
								<label style="display:block;">
									<input type="checkbox" name="sgbp_languages[]" value="<?php echo esc_attr( $code ); ?>" <?php checked( in_array( $code, (array) $all_settings['allowed_languages'], true ) ); ?> />
									<?php echo esc_html( $label . ' (' . $code . ')' ); ?>
								</label>
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( 'Leave all unchecked to post in all languages. Articles in other languages are skipped.', 'soro-gbp-auto-post' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Only these categories', 'soro-gbp-auto-post' ); ?></th>
						<td>
							<div style="max-height:200px;overflow:auto;">
								<?php foreach ( get_categories( array( 'hide_empty' => false ) ) as $category ) : ?>
									<label style="display:block;">
										<input type="checkbox" name="sgbp_categories[]" value="<?php echo esc_attr( $category->term_id ); ?>" <?php checked( in_array( (int) $category->term_id, array_map( 'intval', (array) $all_settings['post_categories'] ), true ) ); ?> />
										<?php echo esc_html( $category->name ); ?>
									</label>
								<?php endforeach; ?>
							</div>
							<p class="description"><?php esc_html_e( 'Leave all unchecked to post articles from all categories.', 'soro-gbp-auto-post' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sgbp_cta_type"><?php esc_html_e( 'Call-to-action button', 'soro-gbp-auto-post' ); ?></label></th>
						<td>
							<select id="sgbp_cta_type" name="sgbp_cta_type">
								<?php foreach ( array( 'LEARN_MORE', 'BOOK', 'SIGN_UP', 'CALL', 'ORDER' ) as $cta ) : ?>
									<option value="<?php echo esc_attr( $cta ); ?>" <?php selected( $all_settings['cta_type'], $cta ); ?>><?php echo esc_html( $cta ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'The button on the Google post links to your article.', 'soro-gbp-auto-post' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sgbp_fallback_image_id"><?php esc_html_e( 'Fallback image ID', 'soro-gbp-auto-post' ); ?></label></th>
						<td>
							<input type="number" min="0" id="sgbp_fallback_image_id" name="sgbp_fallback_image_id" value="<?php echo esc_attr( $all_settings['fallback_image_id'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Media library attachment ID used when an article has no featured image. Leave 0 for text-only posts.', 'soro-gbp-auto-post' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save settings', 'soro-gbp-auto-post' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'Business locations', 'soro-gbp-auto-post' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'sgbp_refresh_locations' ); ?>
				<input type="hidden" name="sgbp_action" value="refresh_locations" />
				<?php submit_button( __( 'Refresh locations', 'soro-gbp-auto-post' ), 'secondary', 'submit', false ); ?>
			</form>
			<?php if ( ! empty( $available_locations ) ) : ?>
				<form method="post" style="margin-top:12px;">
					<?php wp_nonce_field( 'sgbp_save_locations' ); ?>
					<input type="hidden" name="sgbp_action" value="save_locations" />
					<?php foreach ( $available_locations as $location ) : ?>
						<label style="display:block;">
							<input type="checkbox" name="sgbp_locations[]" value="<?php echo esc_attr( $location['name'] ); ?>" <?php checked( in_array( $location['name'], (array) $settings['locations'], true ) ); ?> />
							<?php
							$location_label = isset( $location['locationName'] ) ? $location['locationName'] : ( isset( $location['storeCode'] ) ? $location['storeCode'] : $location['name'] );
							if ( ! empty( $location['address']['addressLines'] ) ) {
								$location_label .= ' — ' . implode( ', ', (array) $location['address']['addressLines'] );
							}
							echo esc_html( $location_label );
							?>
						</label>
					<?php endforeach; ?>
					<?php submit_button( __( 'Save locations', 'soro-gbp-auto-post' ) ); ?>
				</form>
			<?php elseif ( $connected ) : ?>
				<p><?php esc_html_e( 'Click "Refresh locations" to load your Google Business Profile locations.', 'soro-gbp-auto-post' ); ?></p>
			<?php endif; ?>

			<?php if ( $connected ) : ?>
				<form method="post" style="margin-top:12px;">
					<?php wp_nonce_field( 'sgbp_disconnect' ); ?>
					<input type="hidden" name="sgbp_action" value="disconnect" />
					<?php submit_button( __( 'Disconnect Google account', 'soro-gbp-auto-post' ), 'delete', 'submit', false ); ?>
				</form>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Posting log', 'soro-gbp-auto-post' ); ?></h2>
			<table class="widefat striped" style="max-width:900px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Time', 'soro-gbp-auto-post' ); ?></th>
						<th><?php esc_html_e( 'Article', 'soro-gbp-auto-post' ); ?></th>
						<th><?php esc_html_e( 'Language', 'soro-gbp-auto-post' ); ?></th>
						<th><?php esc_html_e( 'Status', 'soro-gbp-auto-post' ); ?></th>
						<th><?php esc_html_e( 'Message', 'soro-gbp-auto-post' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php $entries = SGBP_Logger::get_entries(); ?>
					<?php if ( empty( $entries ) ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No entries yet.', 'soro-gbp-auto-post' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $entries as $entry ) : ?>
							<tr>
								<td><?php echo esc_html( $entry['time'] ); ?></td>
								<td>
									<?php if ( ! empty( $entry['post_id'] ) ) : ?>
										<a href="<?php echo esc_url( get_edit_post_link( (int) $entry['post_id'] ) ); ?>"><?php echo esc_html( $entry['title'] ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $entry['title'] ); ?>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $entry['lang'] ); ?></td>
								<td><?php echo esc_html( $entry['status'] ); ?></td>
								<td><?php echo esc_html( $entry['message'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			<form method="post" style="margin-top:10px;">
				<?php wp_nonce_field( 'sgbp_clear_log' ); ?>
				<input type="hidden" name="sgbp_action" value="clear_log" />
				<?php submit_button( __( 'Clear log', 'soro-gbp-auto-post' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}
}
