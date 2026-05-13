<?php

/**
 * Required functions.
 */
if ( ! function_exists( 'woothemes_queue_update' ) ) {
	require_once 'woo-includes/woo-functions.php' ;
}

class Opmc_Logger {

	private static function pretty_print( $log ) {
		if ( is_object($log) || is_array($log) ) {
			return print_r($log, true);
		} 
		return $log;
	}

	public static function trace( $log ) {
		if ( defined('OPMC_TRACE') ) {
			error_log(self::pretty_print( $log ) );
		}
	}

	public static function debug( $log ) {
		$config = get_option( 'woocommerce_MYOB_integrations_settings' );
		if (isset($config['WC_OPMC_enable_debug_logging']) && 'yes' == $config['WC_OPMC_enable_debug_logging']) {
			error_log(self::pretty_print($log));
		} 
	}

	public static function error( $log ) {
			error_log(self::pretty_print( $log ) );
	}
}
