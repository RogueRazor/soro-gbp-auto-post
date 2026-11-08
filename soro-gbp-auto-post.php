<?php
/**
 * Plugin Name: Soro to Google Business Profile Auto Post
 * Description: Automatically cross-posts articles published on your site (e.g. by Soro) to your Google Business Profile as "What's New" posts, with language filtering and featured image support.
 * Version: 1.0.1
 * Author: RogueRazor
 * License: GPL-2.0-or-later
 * Text Domain: soro-gbp-auto-post
 * Update URI: https://github.com/RogueRazor/soro-gbp-auto-post
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SGBP_VERSION', '1.0.1' );
define( 'SGBP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SGBP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SGBP_PLUGIN_DIR . 'includes/class-sgbp-google-api.php';
require_once SGBP_PLUGIN_DIR . 'includes/class-sgbp-language.php';
require_once SGBP_PLUGIN_DIR . 'includes/class-sgbp-logger.php';
require_once SGBP_PLUGIN_DIR . 'includes/class-sgbp-post-sync.php';
require_once SGBP_PLUGIN_DIR . 'includes/class-sgbp-admin.php';

final class SGBP_Plugin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		add_action( 'init', array( $this, 'init' ) );
	}

	public function activate() {
		$defaults = array(
			'client_id'          => '',
			'client_secret'      => '',
			'access_token'       => '',
			'refresh_token'     => '',
			'token_expires'      => 0,
			'locations'         => array(),
			'allowed_languages' => array(),
			'post_categories'   => array(),
			'cta_type'          => 'LEARN_MORE',
			'fallback_image_id' => 0,
			'enabled'           => 1,
		);
		if ( false === get_option( 'sgbp_settings' ) ) {
			add_option( 'sgbp_settings', $defaults );
		}
		if ( false === get_option( 'sgbp_log' ) ) {
			add_option( 'sgbp_log', array() );
		}
	}

	public function init() {
		SGBP_Admin::init();
		SGBP_Post_Sync::init();
	}
}

function sgbp() {
	return SGBP_Plugin::instance();
}

sgbp();
