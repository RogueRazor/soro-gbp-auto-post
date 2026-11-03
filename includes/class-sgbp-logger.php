<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGBP_Logger {

	const OPTION = 'sgbp_log';
	const MAX_ENTRIES = 100;

	public static function log( $entry ) {
		$log = get_option( self::OPTION, array() );
		$entry = wp_parse_args(
			$entry,
			array(
				'time'    => current_time( 'mysql' ),
				'post_id' => 0,
				'title'   => '',
				'lang'    => '',
				'status'  => '',
				'message' => '',
			)
		);
		array_unshift( $log, $entry );
		$log = array_slice( $log, 0, self::MAX_ENTRIES );
		update_option( self::OPTION, $log );
	}

	public static function get_entries() {
		return get_option( self::OPTION, array() );
	}

	public static function clear() {
		update_option( self::OPTION, array() );
	}
}
