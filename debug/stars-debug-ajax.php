<?php
/**
 * Stars MYOB – Debug AJAX handlers & admin page.
 *
 * Registered from the main plugin file via:
 *   require_once WC_MYOB_INTEGRATION_PLUGINDIR . 'debug/stars-debug-ajax.php';
 *
 * @package Stars_MYOB_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Admin menu entry (removed — Debug Tools is now a tab in the MYOB settings page) ──

// ── Admin page render ─────────────────────────────────────────────────────

function stars_myob_debug_page(): void {
	require_once WC_MYOB_INTEGRATION_PLUGINDIR . 'debug/class-stars-debug-product-fetch.php';
	$log_files = Stars_Debug_Product_Fetch::get_log_files();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'MYOB Debug Tools', 'stars-myob-connector' ); ?></h1>
		<p><?php esc_html_e( 'Use the tools below for debugging and diagnostics. All output is written to log files inside the plugin debug/logs/ folder.', 'stars-myob-connector' ); ?></p>

		<hr>

		<h2><?php esc_html_e( 'Fetch All Products from MYOB', 'stars-myob-connector' ); ?></h2>
		<p><?php esc_html_e( 'Fetches every product from your MYOB Inventory/Item endpoint and writes the full result to a timestamped log file. This is read-only — nothing in WooCommerce or MYOB is modified.', 'stars-myob-connector' ); ?></p>

		<button type="button" id="stars-debug-fetch-products" class="button button-primary">
			<?php esc_html_e( 'Fetch Products Now', 'stars-myob-connector' ); ?>
		</button>

		<div id="stars-debug-result" style="margin-top:16px;display:none;"></div>

		<?php if ( ! empty( $log_files ) ) : ?>
		<hr>
		<h2><?php esc_html_e( 'Product Debug Logs', 'stars-myob-connector' ); ?></h2>
		<table class="widefat striped" style="max-width:900px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'File', 'stars-myob-connector' ); ?></th>
					<th style="width:80px"><?php esc_html_e( 'Products', 'stars-myob-connector' ); ?></th>
					<th style="width:70px"><?php esc_html_e( 'Size', 'stars-myob-connector' ); ?></th>
					<th style="width:180px"><?php esc_html_e( 'Created (UTC)', 'stars-myob-connector' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'stars-myob-connector' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $log_files as $lf ) : ?>
				<tr>
					<td><code><?php echo esc_html( $lf['name'] ); ?></code></td>
					<td><?php echo esc_html( $lf['total'] ); ?></td>
					<td><?php echo esc_html( $lf['size'] ); ?></td>
					<td><?php echo esc_html( $lf['modified'] ); ?></td>
					<td style="white-space:nowrap;">
						<button type="button"
							class="button button-small stars-debug-view-log"
							data-file="<?php echo esc_attr( $lf['name'] ); ?>"
							data-mode="summary">
							<?php esc_html_e( 'Summary', 'stars-myob-connector' ); ?>
						</button>
						<button type="button"
							class="button button-small stars-debug-view-log"
							data-file="<?php echo esc_attr( $lf['name'] ); ?>"
							data-mode="full">
							<?php esc_html_e( 'Full Log', 'stars-myob-connector' ); ?>
						</button>
						<a href="<?php echo esc_url( wp_nonce_url(
							admin_url( 'admin-ajax.php?action=stars_myob_debug_download_log&file=' . rawurlencode( $lf['name'] ) ),
							'stars_myob_debug'
						) ); ?>" class="button button-small">
							<?php esc_html_e( 'Download', 'stars-myob-connector' ); ?>
						</a>
						<button type="button"
							class="button button-small stars-debug-delete-log"
							data-file="<?php echo esc_attr( $lf['name'] ); ?>"
							style="color:#c0392b;">
							<?php esc_html_e( 'Delete', 'stars-myob-connector' ); ?>
						</button>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>

		<div id="stars-debug-log-content" style="margin-top:20px;display:none;">
			<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
				<h3 id="stars-debug-log-title" style="margin:0;"></h3>
				<button type="button" id="stars-debug-log-close" class="button button-small">✕ Close</button>
			</div>
			<pre id="stars-debug-log-pre"
				style="background:#1e1e1e;color:#d4d4d4;border:1px solid #dcdcde;padding:16px;max-height:600px;overflow:auto;font-size:12px;line-height:1.6;white-space:pre-wrap;word-break:break-all;border-radius:4px;">
			</pre>
		</div>
	</div>

	<script>
	(function($){
		var nonce = '<?php echo esc_js( wp_create_nonce( 'stars_myob_debug' ) ); ?>';

		// ── Fetch products ────────────────────────────────────────────────
		$('#stars-debug-fetch-products').on('click', function(){
			var $btn    = $(this);
			var $result = $('#stars-debug-result');

			$btn.prop('disabled', true).text('<?php esc_html_e( 'Fetching — please wait…', 'stars-myob-connector' ); ?>');
			$result.hide().empty();

			$.ajax({
				url:      ajaxurl,
				type:     'post',
				dataType: 'json',
				data:     { action: 'stars_myob_debug_fetch_products', security: nonce },
				success: function(resp){
					$btn.prop('disabled', false).text('<?php esc_html_e( 'Fetch Products Now', 'stars-myob-connector' ); ?>');
					if ( resp && resp.success ) {
						$result.html(
							'<div class="notice notice-success inline" style="margin:0"><p>' +
							'<strong>Done.</strong> ' + $('<div/>').text(resp.data.message).html() +
							' &nbsp; Log: <code>' + $('<div/>').text(resp.data.log_file).html() + '</code></p></div>'
						).show();
						setTimeout(function(){ location.reload(); }, 2000);
					} else {
						var msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Unknown error.';
						$result.html(
							'<div class="notice notice-error inline" style="margin:0"><p>' +
							$('<div/>').text(msg).html() + '</p></div>'
						).show();
					}
				},
				error: function(xhr){
					$btn.prop('disabled', false).text('<?php esc_html_e( 'Fetch Products Now', 'stars-myob-connector' ); ?>');
					$result.html(
						'<div class="notice notice-error inline" style="margin:0"><p>AJAX error: ' + xhr.status + '</p></div>'
					).show();
				}
			});
		});

		// ── View log (summary or full) ─────────────────────────────────────
		$(document).on('click', '.stars-debug-view-log', function(){
			var file    = $(this).data('file');
			var mode    = $(this).data('mode') || 'full';
			var $wrap   = $('#stars-debug-log-content');
			var $pre    = $('#stars-debug-log-pre');
			var $title  = $('#stars-debug-log-title');

			$pre.text('Loading…');
			$title.text(file + (mode === 'summary' ? '  [summary]' : '  [full log]'));
			$wrap.show()[0].scrollIntoView({ behavior: 'smooth', block: 'start' });

			$.ajax({
				url:      ajaxurl,
				type:     'post',
				dataType: 'json',
				data:     { action: 'stars_myob_debug_view_log', security: nonce, file: file, mode: mode },
				success: function(resp){
					if ( resp && resp.success ) {
						$pre.text(resp.data.content);
					} else {
						$pre.text('Error: ' + (resp && resp.data ? resp.data : 'unknown'));
					}
				}
			});
		});

		// ── Close log ──────────────────────────────────────────────────────
		$('#stars-debug-log-close').on('click', function(){
			$('#stars-debug-log-content').hide();
		});

		// ── Delete log ────────────────────────────────────────────────────
		$(document).on('click', '.stars-debug-delete-log', function(){
			var file = $(this).data('file');
			if ( ! confirm('Delete log file: ' + file + '?') ) { return; }

			var $row = $(this).closest('tr');
			$.ajax({
				url:      ajaxurl,
				type:     'post',
				dataType: 'json',
				data:     { action: 'stars_myob_debug_delete_log', security: nonce, file: file },
				success: function(resp){
					if ( resp && resp.success ) {
						$row.fadeOut();
					} else {
						alert('Delete failed: ' + (resp && resp.data ? resp.data : 'unknown error'));
					}
				}
			});
		});

	}(jQuery));
	</script>
	<?php
}

// ── AJAX: fetch all products ───────────────────────────────────────────────

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

// ── AJAX: view log file ────────────────────────────────────────────────────

add_action( 'wp_ajax_stars_myob_debug_view_log', function () {
	check_ajax_referer( 'stars_myob_debug', 'security' );

	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_send_json_error( 'Permission denied.' );
	}

	$file = isset( $_POST['file'] ) ? sanitize_file_name( wp_unslash( $_POST['file'] ) ) : '';
	$mode = isset( $_POST['mode'] ) && $_POST['mode'] === 'summary' ? 'summary' : 'full';

	if ( ! preg_match( '/^myob-products-[\d_-]+\.log$/', $file ) ) {
		wp_send_json_error( 'Invalid file name.' );
	}

	$path = WC_MYOB_INTEGRATION_PLUGINDIR . 'debug/logs/' . $file;

	if ( ! file_exists( $path ) ) {
		wp_send_json_error( 'File not found.' );
	}

	if ( $mode === 'summary' ) {
		// Return only lines before the JSON dump marker.
		$lines   = [];
		$handle  = fopen( $path, 'r' );
		if ( $handle ) {
			while ( ( $line = fgets( $handle ) ) !== false ) {
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

	// Full log — cap at 2MB.
	$content = file_get_contents( $path, false, null, 0, 2 * 1024 * 1024 );
	wp_send_json_success( [ 'content' => $content ] );
} );

// ── AJAX: download log file ────────────────────────────────────────────────

add_action( 'wp_ajax_stars_myob_debug_download_log', function () {
	check_ajax_referer( 'stars_myob_debug' );

	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Permission denied.', 403 );
	}

	$file = isset( $_GET['file'] ) ? sanitize_file_name( wp_unslash( $_GET['file'] ) ) : '';

	if ( ! preg_match( '/^myob-products-[\d_-]+\.log$/', $file ) ) {
		wp_die( 'Invalid file name.', 400 );
	}

	$path = WC_MYOB_INTEGRATION_PLUGINDIR . 'debug/logs/' . $file;

	if ( ! file_exists( $path ) ) {
		wp_die( 'File not found.', 404 );
	}

	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $file . '"' );
	header( 'Content-Length: ' . filesize( $path ) );
	header( 'Cache-Control: no-cache' );
	readfile( $path );
	exit;
} );

// ── AJAX: delete log file ──────────────────────────────────────────────────

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
