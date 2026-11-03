<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGBP_Post_Sync {

	const META_KEY = '_sgbp_gbp_post_name';

	public static function init() {
		add_action( 'transition_post_status', array( __CLASS__, 'on_transition' ), 10, 3 );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_action' ), 10, 2 );
		add_action( 'admin_init', array( __CLASS__, 'handle_manual_post' ) );
	}

	private static function settings() {
		return wp_parse_args(
			get_option( 'sgbp_settings', array() ),
			array(
				'locations'         => array(),
				'allowed_languages' => array(),
				'post_categories'   => array(),
				'cta_type'          => 'LEARN_MORE',
				'fallback_image_id' => 0,
				'enabled'           => 1,
			)
		);
	}

	public static function on_transition( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}
		if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
			return;
		}
		if ( wp_is_post_revision( $post->ID ) || wp_is_post_autosave( $post->ID ) ) {
			return;
		}
		self::sync_post( $post->ID );
	}

	public static function row_action( $actions, $post ) {
		if ( 'post' === $post->post_type && current_user_can( 'manage_options' ) ) {
			$url = wp_nonce_url(
				admin_url( 'admin.php?page=soro-gbp-auto-post&sgbp_sync_post=' . $post->ID ),
				'sgbp_sync_' . $post->ID
			);
			$actions['sgbp_sync'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Post to Google Business', 'soro-gbp-auto-post' ) . '</a>';
		}
		return $actions;
	}

	public static function handle_manual_post() {
		if ( ! isset( $_GET['sgbp_sync_post'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$post_id = absint( $_GET['sgbp_sync_post'] );
		check_admin_referer( 'sgbp_sync_' . $post_id );
		self::sync_post( $post_id );
		wp_safe_redirect( admin_url( 'admin.php?page=soro-gbp-auto-post&sgbp_synced=' . $post_id ) );
		exit;
	}

	public static function sync_post( $post_id ) {
		$settings = self::settings();

		if ( empty( $settings['enabled'] ) ) {
			return;
		}
		if ( ! SGBP_Google_API::is_connected() ) {
			SGBP_Logger::log( array( 'post_id' => $post_id, 'title' => get_the_title( $post_id ), 'status' => 'skipped', 'message' => __( 'Google account not connected.', 'soro-gbp-auto-post' ) ) );
			return;
		}
		if ( empty( $settings['locations'] ) ) {
			SGBP_Logger::log( array( 'post_id' => $post_id, 'title' => get_the_title( $post_id ), 'status' => 'skipped', 'message' => __( 'No Google Business locations selected.', 'soro-gbp-auto-post' ) ) );
			return;
		}

		$lang = SGBP_Language::detect( $post_id );
		if ( ! empty( $settings['allowed_languages'] ) && ! in_array( $lang, (array) $settings['allowed_languages'], true ) ) {
			SGBP_Logger::log( array(
				'post_id' => $post_id,
				'title'   => get_the_title( $post_id ),
				'lang'    => $lang ? $lang : __( 'unknown', 'soro-gbp-auto-post' ),
				'status'  => 'skipped',
				'message' => __( 'Language not selected for cross-posting.', 'soro-gbp-auto-post' ),
			) );
			return;
		}

		if ( ! empty( $settings['post_categories'] ) ) {
			$post_cats = wp_get_post_categories( $post_id, array( 'fields' => 'ids' ) );
			if ( empty( array_intersect( (array) $settings['post_categories'], $post_cats ) ) ) {
				SGBP_Logger::log( array( 'post_id' => $post_id, 'title' => get_the_title( $post_id ), 'status' => 'skipped', 'message' => __( 'Post not in selected categories.', 'soro-gbp-auto-post' ) ) );
				return;
			}
		}

		$post = get_post( $post_id );
		$summary = self::build_summary( $post );
		if ( '' === $summary ) {
			SGBP_Logger::log( array( 'post_id' => $post_id, 'title' => get_the_title( $post_id ), 'status' => 'skipped', 'message' => __( 'Post has no content to share.', 'soro-gbp-auto-post' ) ) );
			return;
		}

		$image_url = self::get_image_url( $post_id, $settings );
		$body = SGBP_Google_API::build_local_post_body( array(
			'language_code' => $lang ? $lang : get_locale(),
			'summary'       => $summary,
			'cta_type'      => $settings['cta_type'] ? $settings['cta_type'] : 'LEARN_MORE',
			'url'           => get_permalink( $post_id ),
			'image_url'     => $image_url,
		) );

		$success = array();
		$errors  = array();
		foreach ( (array) $settings['locations'] as $location_name ) {
			$response = SGBP_Google_API::create_local_post( $location_name, $body );
			if ( is_wp_error( $response ) ) {
				$errors[ $location_name ] = $response->get_error_message();
			} elseif ( ! empty( $response['name'] ) ) {
				$success[] = $location_name;
				update_post_meta( $post_id, self::META_KEY, $response['name'] );
			} else {
				$errors[ $location_name ] = __( 'Unknown API response.', 'soro-gbp-auto-post' );
			}
		}

		if ( $errors ) {
			SGBP_Logger::log( array(
				'post_id' => $post_id,
				'title'   => get_the_title( $post_id ),
				'lang'    => $lang,
				'status'  => 'error',
				'message' => __( 'Google API error: ', 'soro-gbp-auto-post' ) . implode( ' | ', $errors ),
			) );
		}
		if ( $success ) {
			SGBP_Logger::log( array(
				'post_id' => $post_id,
				'title'   => get_the_title( $post_id ),
				'lang'    => $lang,
				'status'  => 'success',
				'message' => sprintf( _n( 'Posted to %d location.', 'Posted to %d locations.', count( $success ), 'soro-gbp-auto-post' ), count( $success ) ),
			) );
		}
	}

	private static function build_summary( $post ) {
		$parts = array( $post->post_title );
		if ( $post->post_excerpt ) {
			$excerpt = $post->post_excerpt;
		} else {
			$content = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
			$excerpt = wp_trim_words( $content, 40, '…' );
		}
		if ( $excerpt ) {
			$parts[] = $excerpt;
		}
		$summary = trim( implode( "\n\n", $parts ) );
		if ( mb_strlen( $summary ) > 1500 ) {
			$summary = mb_substr( $summary, 0, 1497 ) . '…';
		}
		return $summary;
	}

	private static function get_image_url( $post_id, $settings ) {
		$attachment_id = get_post_thumbnail_id( $post_id );
		if ( ! $attachment_id && ! empty( $settings['fallback_image_id'] ) ) {
			$attachment_id = absint( $settings['fallback_image_id'] );
		}
		if ( ! $attachment_id ) {
			return '';
		}
		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) {
			return wp_get_attachment_url( $attachment_id );
		}
		$check = wp_check_filetype( $file );
		$ext  = strtolower( (string) $check['ext'] );
		$needs_conversion = ! in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true );
		$needs_resize = false;

		if ( function_exists( 'getimagesize' ) ) {
			$size = @getimagesize( $file );
			if ( $size && ( $size[0] < 400 || $size[1] < 300 ) ) {
				$needs_resize = true;
			}
		}
		if ( filesize( $file ) > 5 * MB_IN_BYTES ) {
			$needs_resize = true;
		}
		if ( ! $needs_conversion && ! $needs_resize ) {
			return wp_get_attachment_url( $attachment_id );
		}

		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			return wp_get_attachment_url( $attachment_id );
		}
		if ( $needs_conversion ) {
			$editor->set_quality( 85 );
		}
		if ( filesize( $file ) > 5 * MB_IN_BYTES ) {
			$max_dimension = 1600;
			$editor->resize( $max_dimension, $max_dimension, false );
		}
		$upload = wp_upload_dir();
		$target_dir = trailingslashit( $upload['basedir'] ) . 'sgbp';
		if ( ! wp_mkdir_p( $target_dir ) ) {
			return wp_get_attachment_url( $attachment_id );
		}
		$target = $target_dir . '/sgbp-' . $attachment_id . '.jpg';
		$result = $editor->save( $target, 'image/jpeg' );
		if ( is_wp_error( $result ) || filesize( $result['path'] ) > 5 * MB_IN_BYTES ) {
			return wp_get_attachment_url( $attachment_id );
		}
		return trailingslashit( $upload['baseurl'] ) . 'sgbp/' . basename( $result['path'] );
	}
}
