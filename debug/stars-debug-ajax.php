<?php
/**
 * Stars MYOB – Debug AJAX handlers.
 *
 * The UI is now embedded in the MYOB settings page as the "Debug Tools" tab.
 * This file only registers the wp_ajax_* handlers needed by that tab.
 *
 * Loaded from the main plugin file via:
 *   require_once WC_MYOB_INTEGRATION_PLUGINDIR . 'debug/stars-debug-ajax.php';
 *
 * @package Stars_MYOB_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── AJAX: fetch all products ──────────────────────────────────────────────

add_action( 'wp_ajax_stars_myob_debug_fetch_products', function () {
	check_ajax_referer( 'stars_myob_debug', 'security' );

	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	require_once WC_MYOB_INTEGRATION_PLUGINDIR . 'debug/class-stars-debug-product-fetch.php';

	$fetcher = new Stars_Debug_Product_Fetch();
	$result  = $fetcher->run();

	if ( $result['success'] ) {
		wp_send_json_success( $result );
	} else {
		wp_send_json_error( $result );
	}
} );

// ── AJAX: view log file ───────────────────────────────────────────────────

add_action( 'wp_ajax_stars_myob_debug_view_log', function () {
	check_ajax_referer( 'stars_myob_debug', 'security' );

	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_send_json_error( 'Permission denied.' );
	}

	$file = isset( $_POST['file'] ) ? sanitize_file_name( wp_unslash( $_POST['file'] ) ) : '';
	$mode = ( isset( $_POST['mode'] ) && 'summary' === $_POST['mode'] ) ? 'summary' : 'full';

	if ( ! preg_match( '/^myob-products-[\d_-]+\.log$/', $file ) ) {
		wp_send_json_error( 'Invalid file name.' );
	}

	$path = WC_MYOB_INTEGRATION_PLUGINDIR . 'debug/logs/' . $file;

	if ( ! file_exists( $path ) ) {
		wp_send_json_error( 'File not found.' );
	}

	if ( 'summary' === $mode ) {
		$lines  = [];
		$handle = fopen( $path, 'r' );
		if ( $handle ) {
			while ( false !== ( $line = fgets( $handle ) ) ) {
				if ( strpos( $line, '=== BEGIN RAW JSON DUMP' ) !== false ) {
					break;
				}
				$lines[] = rtrim( $line );
			}
			fclose( $handle );
		}
		wp_send_json_success( [ 'content' => implode( PHP_EOL, $lines ) ] );
		return;
	}

	// Full log — cap at 2 MB to avoid giant payloads.
	$content = file_get_contents( $path, false, null, 0, 2 * 1024 * 1024 );
	wp_send_json_success( [ 'content' => $content ] );
} );

// ── AJAX: download log file ───────────────────────────────────────────────

add_action( 'wp_ajax_stars_myob_debug_download_log', function () {
	check_ajax_referer( 'stars_myob_debug' );

	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Permission denied.', '', [ 'response' => 403 ] );
	}

	$file = isset( $_GET['file'] ) ? sanitize_file_name( wp_unslash( $_GET['file'] ) ) : '';

	if ( ! preg_match( '/^myob-products-[\d_-]+\.log$/', $file ) ) {
		wp_die( 'Invalid file name.', '', [ 'response' => 400 ] );
	}

	$path = WC_MYOB_INTEGRATION_PLUGINDIR . 'debug/logs/' . $file;

	if ( ! file_exists( $path ) ) {
		wp_die( 'File not found.', '', [ 'response' => 404 ] );
	}

	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $file . '"' );
	header( 'Content-Length: ' . filesize( $path ) );
	header( 'Cache-Control: no-cache' );
	readfile( $path );
	exit;
} );

// ── AJAX: delete log file ─────────────────────────────────────────────────

add_action( 'wp_ajax_stars_myob_debug_delete_log', function () {
	check_ajax_referer( 'stars_myob_debug', 'security' );

	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_send_json_error( 'Permission denied.' );
	}

	$file = isset( $_POST['file'] ) ? sanitize_file_name( wp_unslash( $_POST['file'] ) ) : '';

	require_once WC_MYOB_INTEGRATION_PLUGINDIR . 'debug/class-stars-debug-product-fetch.php';

	if ( Stars_Debug_Product_Fetch::delete_log( $file ) ) {
		wp_send_json_success();
	} else {
		wp_send_json_error( 'Could not delete file.' );
	}
} );
