<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGBP_Google_API {

	const AUTH_URL        = 'https://accounts.google.com/o/oauth2/v2/auth';
	const TOKEN_URL       = 'https://oauth2.googleapis.com/token';
	const SCOPE           = 'https://www.googleapis.com/auth/business.manage';
	const ACCOUNTS_BASE   = 'https://mybusinessaccountmanagement.googleapis.com/v1';
	const INFO_BASE       = 'https://mybusinessbusinessinformation.googleapis.com/v1';
	const POSTS_BASE      = 'https://mybusiness.googleapis.com/v4';

	public static function settings() {
		return wp_parse_args(
			get_option( 'sgbp_settings', array() ),
			array(
				'client_id'      => '',
				'client_secret'  => '',
				'access_token'   => '',
				'refresh_token'  => '',
				'token_expires'  => 0,
				'locations'     => array(),
			)
		);
	}

	public static function update_settings( $fields ) {
		$settings = self::settings();
		foreach ( $fields as $key => $value ) {
			$settings[ $key ] = $value;
		}
		update_option( 'sgbp_settings', $settings );
	}

	public static function is_configured() {
		$s = self::settings();
		return ! empty( $s['client_id'] ) && ! empty( $s['client_secret'] );
	}

	public static function is_connected() {
		$s = self::settings();
		return ! empty( $s['refresh_token'] ) || ( ! empty( $s['access_token'] ) && time() < (int) $s['token_expires'] );
	}

	public static function redirect_uri() {
		return admin_url( 'admin.php?page=soro-gbp-auto-post&sgbp_oauth=callback' );
	}

	public static function get_connect_url() {
		$s = self::settings();
		return add_query_arg(
			array(
				'client_id'     => $s['client_id'],
				'redirect_uri'  => rawurlencode( self::redirect_uri() ),
				'response_type' => 'code',
				'scope'         => self::SCOPE,
				'access_type'   => 'offline',
				'prompt'        => 'consent',
				'state'         => wp_create_nonce( 'sgbp_oauth' ),
			),
			self::AUTH_URL
		);
	}

	public static function exchange_code( $code ) {
		$s = self::settings();
		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 30,
				'body'    => array(
					'code'          => $code,
					'client_id'     => $s['client_id'],
					'client_secret' => $s['client_secret'],
					'redirect_uri'  => self::redirect_uri(),
					'grant_type'    => 'authorization_code',
				),
			)
		);
		return self::parse_token_response( $response );
	}

	public static function refresh_token() {
		$s = self::settings();
		if ( empty( $s['refresh_token'] ) ) {
			return new WP_Error( 'sgbp_no_refresh_token', __( 'Not connected to Google. Reconnect on the settings page.', 'soro-gbp-auto-post' ) );
		}
		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 30,
				'body'    => array(
					'refresh_token' => $s['refresh_token'],
					'client_id'     => $s['client_id'],
					'client_secret' => $s['client_secret'],
					'grant_type'    => 'refresh_token',
				),
			)
		);
		return self::parse_token_response( $response, true );
	}

	private static function parse_token_response( $response, $keep_refresh = false ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			$err  = isset( $body['error_description'] ) ? $body['error_description'] : wp_remote_retrieve_body( $response );
			return new WP_Error( 'sgbp_token_error', $err );
		}
		$fields = array(
			'access_token'  => $body['access_token'],
			'token_expires' => time() + (int) $body['expires_in'] - 60,
		);
		if ( ! empty( $body['refresh_token'] ) && ! $keep_refresh ) {
			$fields['refresh_token'] = $body['refresh_token'];
		}
		self::update_settings( $fields );
		return true;
	}

	public static function get_access_token() {
		$s = self::settings();
		if ( empty( $s['access_token'] ) && empty( $s['refresh_token'] ) ) {
			return new WP_Error( 'sgbp_not_connected', __( 'Google account is not connected.', 'soro-gbp-auto-post' ) );
		}
		if ( empty( $s['access_token'] ) || time() >= (int) $s['token_expires'] ) {
			$result = self::refresh_token();
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$s = self::settings();
		}
		return $s['access_token'];
	}

	private static function request( $method, $url, $body = null ) {
		$token = self::get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}
		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			$msg = isset( $data['error']['message'] ) ? $data['error']['message'] : wp_remote_retrieve_body( $response );
			return new WP_Error( 'sgbp_api_error', $msg, array( 'status' => $code ) );
		}
		return $data;
	}

	public static function get_accounts() {
		$data = self::request( 'GET', self::ACCOUNTS_BASE . '/accounts' );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		return isset( $data['accounts'] ) ? $data['accounts'] : array();
	}

	public static function get_locations() {
		$accounts = self::get_accounts();
		if ( is_wp_error( $accounts ) ) {
			return $accounts;
		}
		$locations = array();
		foreach ( $accounts as $account ) {
			$name     = $account['name'];
			$response = self::request( 'GET', self::INFO_BASE . '/' . $name . '/locations?readMask=name,title' );
			if ( is_wp_error( $response ) ) {
				continue;
			}
			if ( ! empty( $response['locations'] ) ) {
				foreach ( $response['locations'] as $location ) {
					// Ensure the full resource name (accounts/{id}/locations/{id}) is used.
					if ( empty( $location['name'] ) ) {
						continue;
						}
					if ( 0 !== strpos( $location['name'], 'accounts/' ) ) {
						$location['name'] = $name . '/' . ltrim( $location['name'], '/' );
					}
					$locations[] = $location;
				}
			}
		}
		return $locations;
	}

	public static function create_local_post( $location_name, $post ) {
		return self::request( 'POST', self::POSTS_BASE . '/' . $location_name . '/localPosts', $post );
	}

	public static function build_local_post_body( $args ) {
		$body = array(
			'topicType' => 'STANDARD',
			'languageCode' => $args['language_code'],
			'summary'    => $args['summary'],
			'callToAction' => array(
				'actionType' => $args['cta_type'],
				'url'        => $args['url'],
			),
		);
		if ( ! empty( $args['image_url'] ) ) {
			$body['media'] = array(
				array(
					'mediaFormat' => 'PHOTO',
					'sourceUrl'   => $args['image_url'],
				),
			);
		}
		return $body;
	}
}
