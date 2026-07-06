<?php
/**
 * Stars MYOB – Debug: Fetch All Products from MYOB
 *
 * Fetches every product from the MYOB Inventory/Item endpoint (paginated)
 * and writes the raw JSON to a timestamped log inside the debug/ folder.
 *
 * @package Stars_MYOB_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Stars_Debug_Product_Fetch {

	/** Absolute path to the debug log directory. */
	const LOG_DIR = WC_MYOB_INTEGRATION_PLUGINDIR . 'debug/logs/';

	/** Max products per API page. */
	const PAGE_SIZE = 1000;

	/** @var Opmc_Myob_Connector */
	private $connector;

	public function __construct() {
		$this->connector = new Opmc_Myob_Connector();
	}

	// ── Public entry point ────────────────────────────────────────────────

	/**
	 * Fetch all products from MYOB, write them to a debug log, return summary.
	 *
	 * @return array { success: bool, log_file: string, total: int, message: string }
	 */
	public function run(): array {

		if ( ! $this->connector->has_credentials() ) {
			return [
				'success'  => false,
				'log_file' => '',
				'total'    => 0,
				'message'  => 'Not connected to MYOB. Please validate access first.',
			];
		}

		$this->ensure_log_dir();

		$timestamp = gmdate( 'Y-m-d_H-i-s' );
		$log_file  = self::LOG_DIR . 'myob-products-' . $timestamp . '.log';

		$all_products = [];
		$page         = 0;
		$next_url     = null;
		$errors       = [];

		$this->write_line( $log_file, '[' . gmdate( 'Y-m-d H:i:s' ) . ' UTC] Starting MYOB product fetch' );

		do {
			$url = $next_url ?? ( $this->connector->get_full_endpoint() . '/Inventory/Item?$top=' . self::PAGE_SIZE . '&$skip=' . ( $page * self::PAGE_SIZE ) );

			$this->write_line( $log_file, 'Fetching page ' . ( $page + 1 ) . ' → ' . $url );

			$response = $this->connector->public_remote_get( $url );

			if ( is_null( $response ) || ! isset( $response->Items ) ) {
				$error = 'Page ' . ( $page + 1 ) . ': empty or error response.';
				$this->write_line( $log_file, '[ERROR] ' . $error );
				$errors[] = $error;
				break;
			}

			$items = $response->Items;
			$count = count( $items );

			$this->write_line( $log_file, 'Page ' . ( $page + 1 ) . ': received ' . $count . ' items.' );

			foreach ( $items as $item ) {
				$all_products[] = $item;
				$this->write_line(
					$log_file,
					sprintf(
						'  [%s] %s | Price: %s | Active: %s | QtyOnHand: %s',
						$item->Number       ?? 'N/A',
						$item->Name         ?? 'N/A',
						$item->BaseSellingPrice ?? 'N/A',
						isset( $item->IsActive ) ? ( $item->IsActive ? 'Yes' : 'No' ) : 'N/A',
						$item->CurrentValue ?? 'N/A'
					)
				);
			}

			$next_url = isset( $response->NextPageLink ) && ! empty( $response->NextPageLink )
				? $response->NextPageLink
				: null;

			$page++;

		} while ( $next_url !== null );

		$total = count( $all_products );

		// Write full JSON dump at the end of the log.
		$this->write_line( $log_file, '' );
		$this->write_line( $log_file, '=== RAW JSON DUMP (' . $total . ' products) ===' );
		file_put_contents( $log_file, json_encode( $all_products, JSON_PRETTY_PRINT ) . PHP_EOL, FILE_APPEND );

		$this->write_line( $log_file, '' );
		$this->write_line( $log_file, '[' . gmdate( 'Y-m-d H:i:s' ) . ' UTC] Fetch complete. Total products: ' . $total );

		if ( ! empty( $errors ) ) {
			$this->write_line( $log_file, '[ERRORS] ' . implode( ' | ', $errors ) );
		}

		return [
			'success'  => empty( $errors ),
			'log_file' => basename( $log_file ),
			'total'    => $total,
			'message'  => empty( $errors )
				? 'Fetched ' . $total . ' products successfully.'
				: 'Completed with errors: ' . implode( ', ', $errors ),
		];
	}

	// ── Helpers ───────────────────────────────────────────────────────────

	/**
	 * Return a list of existing debug log files (newest first).
	 */
	public static function get_log_files(): array {
		$dir   = self::LOG_DIR;
		$files = [];

		if ( ! is_dir( $dir ) ) {
			return $files;
		}

		foreach ( glob( $dir . 'myob-products-*.log' ) as $file ) {
			$files[] = [
				'name'     => basename( $file ),
				'size'     => size_format( filesize( $file ) ),
				'modified' => gmdate( 'Y-m-d H:i:s', filemtime( $file ) ) . ' UTC',
				'path'     => $file,
			];
		}

		usort( $files, fn( $a, $b ) => strcmp( $b['name'], $a['name'] ) );

		return $files;
	}

	/**
	 * Delete a specific log file by name.
	 */
	public static function delete_log( string $name ): bool {
		// Only allow our own log files.
		if ( ! preg_match( '/^myob-products-[\d_-]+\.log$/', $name ) ) {
			return false;
		}
		$path = self::LOG_DIR . $name;
		if ( file_exists( $path ) ) {
			return unlink( $path );
		}
		return false;
	}

	private function ensure_log_dir(): void {
		$dir = self::LOG_DIR;
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			// Prevent direct browser access.
			file_put_contents( $dir . '.htaccess', "Deny from all\n" );
			file_put_contents( $dir . 'index.php', "<?php // Silence is golden.\n" );
		}
	}

	private function write_line( string $file, string $line ): void {
		file_put_contents( $file, $line . PHP_EOL, FILE_APPEND );
	}
}
