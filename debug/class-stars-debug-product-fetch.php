<?php
/**
 * Stars MYOB – Debug: Fetch All Products from MYOB
 *
 * Fetches every product from the MYOB Inventory/Item endpoint (paginated)
 * and writes the raw JSON to a timestamped log inside the debug/logs/ folder.
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

	/** @var string */
	private $log_file = '';

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

		$this->ensure_log_dir();

		$timestamp      = gmdate( 'Y-m-d_H-i-s' );
		$this->log_file = self::LOG_DIR . 'myob-products-' . $timestamp . '.log';

		// ── Credential check ─────────────────────────────────────────────
		if ( ! $this->connector->has_credentials() ) {
			$this->write_line( '[ERROR] Not connected to MYOB — missing access token, client ID or company file ID.' );
			$this->write_line( 'access_token    : ' . ( get_option( 'MYOB_access_token' )      ? 'SET'   : 'MISSING' ) );
			$this->write_line( 'client_id       : ' . ( get_option( 'WC_MYOB_client_id' )       ? 'SET'   : 'MISSING' ) );
			$this->write_line( 'company_file_id : ' . ( get_option( 'WC_MYOB_company_file_id' ) ? 'SET'   : 'MISSING' ) );

			return $this->result( false, 0, 'Not connected to MYOB. Please validate access first.' );
		}

		// ── Start ─────────────────────────────────────────────────────────
		$this->write_line( '[' . gmdate( 'Y-m-d H:i:s' ) . ' UTC] Starting MYOB product fetch' );
		$this->write_line( 'Endpoint : ' . $this->connector->get_full_endpoint() );
		$config   = get_option( 'woocommerce_MYOB_integrations_settings', [] );
		$username = isset( $config['WC_MYOB_company_file_username'] ) ? trim( $config['WC_MYOB_company_file_username'] ) : '';
		$this->write_line( 'Company file user : ' . ( $username ?: '(empty)' ) );
		$this->write_line( str_repeat( '-', 80 ) );

		$all_products = [];
		$page         = 0;
		$next_url     = null;
		$errors       = [];

		do {
			$skip = $page * self::PAGE_SIZE;
			$url  = $next_url ?? ( $this->connector->get_full_endpoint() . '/Inventory/Item?$top=' . self::PAGE_SIZE . '&$skip=' . $skip );

			$this->write_line( 'Page ' . ( $page + 1 ) . ' → ' . $url );

			$raw       = $this->connector->public_raw_get( $url );
			$http_code = $raw['code'] ?? 0;
			$body      = $raw['body'] ?? '';

			if ( $http_code !== 200 ) {
				$error = 'Page ' . ( $page + 1 ) . ': HTTP ' . $http_code . ' — ' . wp_strip_all_tags( $body );
				$this->write_line( '[ERROR] ' . $error );
				$this->write_line( 'Raw response body:' );
				$this->write_line( $body );
				$errors[] = 'HTTP ' . $http_code;
				break;
			}

			if ( empty( $body ) ) {
				$error = 'Page ' . ( $page + 1 ) . ': empty response body.';
				$this->write_line( '[ERROR] ' . $error );
				$errors[] = $error;
				break;
			}

			$decoded = json_decode( $body );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				$error = 'Page ' . ( $page + 1 ) . ': JSON parse error — ' . json_last_error_msg();
				$this->write_line( '[ERROR] ' . $error );
				$this->write_line( 'Raw body: ' . substr( $body, 0, 500 ) );
				$errors[] = $error;
				break;
			}

			if ( ! isset( $decoded->Items ) ) {
				$error = 'Page ' . ( $page + 1 ) . ': response has no Items key.';
				$this->write_line( '[ERROR] ' . $error );
				$this->write_line( 'Decoded keys: ' . implode( ', ', array_keys( (array) $decoded ) ) );
				$errors[] = $error;
				break;
			}

			$items = $decoded->Items;
			$count = count( $items );
			$this->write_line( 'Page ' . ( $page + 1 ) . ': ' . $count . ' items received.' );

			foreach ( $items as $item ) {
				$all_products[] = $item;
				$this->write_line(
					sprintf(
						'  [%-30s] %-50s | Price: %-10s | Active: %s',
						$item->Number           ?? 'N/A',
						$item->Name             ?? 'N/A',
						$item->BaseSellingPrice ?? 'N/A',
						isset( $item->IsActive ) ? ( $item->IsActive ? 'Yes' : 'No' ) : 'N/A'
					)
				);
			}

			$next_url = ! empty( $decoded->NextPageLink ) ? $decoded->NextPageLink : null;

			$page++;

		} while ( $next_url !== null );

		$total = count( $all_products );

		// ── Write full JSON dump ──────────────────────────────────────────
		$this->write_line( '' );
		$this->write_line( str_repeat( '-', 80 ) );
		$this->write_line( 'SUMMARY' );
		$this->write_line( 'Total products : ' . $total );
		$this->write_line( 'Pages fetched  : ' . $page );
		$this->write_line( 'Status         : ' . ( empty( $errors ) ? 'OK' : 'ERRORS: ' . implode( ', ', $errors ) ) );
		$this->write_line( '[' . gmdate( 'Y-m-d H:i:s' ) . ' UTC] Fetch complete.' );
		$this->write_line( str_repeat( '-', 80 ) );
		$this->write_line( '' );
		$this->write_line( '=== BEGIN RAW JSON DUMP (' . $total . ' products) ===' );
		file_put_contents( $this->log_file, json_encode( $all_products, JSON_PRETTY_PRINT ) . PHP_EOL, FILE_APPEND );
		$this->write_line( '=== END RAW JSON DUMP ===' );

		return $this->result( empty( $errors ), $total,
			empty( $errors )
				? 'Fetched ' . $total . ' products successfully.'
				: 'Completed with errors: ' . implode( ', ', $errors )
		);
	}

	// ── Static helpers ────────────────────────────────────────────────────

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
			// Quickly scan for total line to show in table.
			$total = 'N/A';
			$handle = fopen( $file, 'r' );
			if ( $handle ) {
				while ( ( $line = fgets( $handle ) ) !== false ) {
					if ( strpos( $line, 'Total products :' ) !== false ) {
						$parts = explode( ':', $line, 2 );
						$total = trim( $parts[1] ?? 'N/A' );
						break;
					}
				}
				fclose( $handle );
			}

			$files[] = [
				'name'     => basename( $file ),
				'size'     => size_format( filesize( $file ) ),
				'modified' => gmdate( 'Y-m-d H:i:s', filemtime( $file ) ) . ' UTC',
				'total'    => $total,
			];
		}

		usort( $files, fn( $a, $b ) => strcmp( $b['name'], $a['name'] ) );

		return $files;
	}

	/**
	 * Delete a specific log file by name (validates filename format first).
	 */
	public static function delete_log( string $name ): bool {
		if ( ! preg_match( '/^myob-products-[\d_-]+\.log$/', $name ) ) {
			return false;
		}
		$path = self::LOG_DIR . $name;
		return file_exists( $path ) && unlink( $path );
	}

	// ── Private helpers ───────────────────────────────────────────────────

	private function ensure_log_dir(): void {
		$dir = self::LOG_DIR;
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			file_put_contents( $dir . '.htaccess', "Deny from all\n" );
			file_put_contents( $dir . 'index.php', "<?php // Silence is golden.\n" );
		}
	}

	private function write_line( string $line ): void {
		file_put_contents( $this->log_file, $line . PHP_EOL, FILE_APPEND );
	}

	private function result( bool $success, int $total, string $message ): array {
		return [
			'success'  => $success,
			'log_file' => basename( $this->log_file ),
			'total'    => $total,
			'message'  => $message,
		];
	}
}
