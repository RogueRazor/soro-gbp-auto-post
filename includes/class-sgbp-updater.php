<?php
/**
 * Built-in update client: checks the plugin's GitHub releases and
 * registers available updates with WordPress's native update system.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGBP_Updater {

	const REPO        = 'RogueRazor/soro-gbp-auto-post';
	const PLUGIN_SLUG = 'soro-gbp-auto-post';

	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_update' ) );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_source_dir' ), 10, 4 );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
	}

	private static function plugin_basename() {
		return self::PLUGIN_SLUG . '/' . self::PLUGIN_SLUG . '.php';
	}

	/**
	 * Query the latest GitHub release and add an update entry if newer.
	 *
	 * @param object $transient The update-plugins transient.
	 * @return object
	 */
	public static function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = self::get_latest_release();
		if ( is_wp_error( $release ) ) {
			return $transient;
		}

		$version = ltrim( $release['tag_name'], 'v' );
		if ( ! $version || version_compare( SGBP_VERSION, $version, '>=' ) ) {
			return $transient;
		}

		$package = $release['zipball_url'];
		foreach ( (array) $release['assets'] as $asset ) {
			if ( ! empty( $asset['browser_download_url'] ) && '.zip' === substr( $asset['name'], -4 ) ) {
				$package = $asset['browser_download_url'];
				break;
			}
		}

		$transient->response[ self::plugin_basename() ] = (object) array(
			'slug'        => self::PLUGIN_SLUG,
			'plugin'      => self::plugin_basename(),
			'new_version' => $version,
			'package'     => $package,
			'url'         => 'https://github.com/' . self::REPO,
		);

		return $transient;
	}

	/**
	 * GitHub zipballs extract to a folder named {owner}-{repo}-{sha}.
	 * Rename it to the plugin's real folder so WordPress replaces
	 * the existing directory instead of installing a duplicate.
	 *
	 * @param string $source        Path to the extracted folder.
	 * @param string $remote_source Remote source path.
	 * @param object $upgrader      WP_Upgrader instance.
	 * @param array  $hook_extra    Extra data, contains 'plugin' for plugin updates.
	 * @return string
	 */
	public static function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra = null ) {
		$plugin = isset( $hook_extra['plugin'] ) ? $hook_extra['plugin'] : '';
		if ( $plugin !== self::plugin_basename() ) {
			return $source;
		}

		$source_untrimmed = rtrim( $source, '/' );
		if ( basename( $source_untrimmed ) === self::PLUGIN_SLUG ) {
			return $source;
		}

		$new_source = dirname( $source_untrimmed ) . '/' . self::PLUGIN_SLUG . '/';
		if ( rename( $source_untrimmed, rtrim( $new_source, '/' ) ) ) {
			return $new_source;
		}

		return $source;
	}

	/**
	 * Provide basic update details on the plugin information screen.
	 *
	 * @param false|object|array $result The result object or array.
	 * @param string             $action The action being performed.
	 * @param object             $args   Arguments for the request.
	 * @return false|object|array
	 */
	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ( isset( $args->slug ) && self::PLUGIN_SLUG !== $args->slug ) ) {
			return $result;
		}

		$release = self::get_latest_release();
		if ( is_wp_error( $release ) ) {
			return $result;
		}

		return (object) array(
			'name'          => 'Soro to Google Business Profile Auto Post',
			'slug'          => self::PLUGIN_SLUG,
			'version'       => ltrim( $release['tag_name'], 'v' ),
			'homepage'      => 'https://github.com/' . self::REPO,
			'sections'      => array(
				'description' => 'Automatically cross-posts published articles to your Google Business Profile.',
				'changelog'   => isset( $release['body'] ) ? $release['body'] : '',
			),
			'download_link' => $release['zipball_url'],
		);
	}

	/**
	 * Fetch the latest release from the GitHub API.
	 *
	 * @return array|WP_Error
	 */
	private static function get_latest_release() {
		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::REPO . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept' => 'application/vnd.github+json',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'sgbp_github_api', 'Could not reach GitHub releases API.' );
		}

		$release = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $release['tag_name'] ) ) {
			return new WP_Error( 'sgbp_github_api', 'Malformed GitHub release data.' );
		}

		return $release;
	}
}
