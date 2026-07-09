<?php
/**
 * MYOB connector
 *
 * @package Stars_MYOB_Connector
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
require_once WC_MYOB_INTEGRATION_PLUGINDIR . '/includes/class-stars-myob-loader.php';
require_once WC_MYOB_INTEGRATION_PLUGINDIR . '/includes/class-stars-myob-item-order.php';
require_once WC_MYOB_INTEGRATION_PLUGINDIR . '/includes/class-stars-myob-item-invoice.php';
require_once WC_MYOB_INTEGRATION_PLUGINDIR . '/includes/opmc/class-stars-erp-connector.php';
require_once WC_MYOB_INTEGRATION_PLUGINDIR . '/includes/class-stars-myob-order-to-invoice.php';

if (!class_exists('Opmc_Myob_Connector')):
	#[\AllowDynamicProperties]
	class Opmc_Myob_Connector extends Opmc_Erp_Connector
	{
		/**
		 * The loader that's responsible for maintaining and registering all hooks that power
		 * the plugin.
		 *
		 * @since    1.0.0
		 * @var      Ms_Wc_Loader    $loader    Maintains and registers all hooks for the plugin.
		 */
		protected $loader;

		public $order_batch_process;
		public $mp_order_batch_process;
		public $product_import_process;
		public $import_product_to_myob_process;
		private $order_debug_context = null;


		/**
		 * Construct the plugin.
		 */
		public function __construct()
		{
			$this->http_timeout = 60;

			$config = get_option('woocommerce_MYOB_integrations_settings');

			$this->loader = new Ms_Wc_Loader();

			$this->tax_code_new_product = get_option('WC_MYOB_tax_code_new_products');
			$this->tax_code_line_item = get_option('WC_MYOB_tax_code_line_items');

			$this->tax_codes = get_option('WC_MYOB_tax_codes_list');
			$this->freight_tax_code = get_option('WC_MYOB_freight_tax_code');

			$this->client_id = get_option('WC_MYOB_client_id');
			$this->client_secret = get_option('WC_MYOB_secret');
			$this->company_file_id = get_option('WC_MYOB_company_file_id');
			$this->income_account = get_option('WC_MYOB_income_account');
			$this->myob_code = get_option('WC_MYOB_code');
			$this->refresh_token = get_option('MYOB_access_refresh_token');
			$this->access_token = get_option('MYOB_access_token');
			// print_r($this->access_token); 

			// TODO add config variable
			$this->customer_id_prefix = get_option('WC_MYOB_customer_id_prefix');
			$this->guest_customer_display_id = get_option('WC_MYOB_guest_customer_display_id');
			$this->cogs_account = get_option('WC_MYOB_cogs_account');
			$this->asset_account = get_option('WC_MYOB_asset_account');


			$this->tax_code_new_product = isset($config['WC_MYOB_tax_code_new_products']) ? $config['WC_MYOB_tax_code_new_products'] : '';
			$this->tax_code_line_item = isset($config['WC_MYOB_tax_code_line_items']) ? $config['WC_MYOB_tax_code_line_items'] : '';

			$this->freight_tax_code = isset($config['WC_MYOB_freight_tax_code']) ? $config['WC_MYOB_freight_tax_code'] : '';

			$this->company_file_id = isset($config['WC_MYOB_company_file_id']) ? $config['WC_MYOB_company_file_id'] : '';
			$this->income_account = isset($config['WC_MYOB_income_account']) ? $config['WC_MYOB_income_account'] : '';
			//$this->myob_code = get_option('WC_MYOB_code');
			//$this->refresh_token = get_option('MYOB_access_refresh_token');
			//$this->access_token = get_option('MYOB_access_token');
			//$WC_MYOB_company_file_username = isset($config['WC_MYOB_company_file_username']) ? trim($config['WC_MYOB_company_file_username']) : '';
			//$WC_MYOB_company_file_password = isset($config['WC_MYOB_company_file_password']) ? trim($config['WC_MYOB_company_file_password']) : '';
			//$this->cftoken = base64_encode($WC_MYOB_company_file_username . ':' . $WC_MYOB_company_file_password);

			// TODO add config variable
			$this->customer_id_prefix = isset($config['WC_MYOB_customer_id_prefix']) ? $config['WC_MYOB_customer_id_prefix'] : '';
			$this->guest_customer_display_id = isset($config['WC_MYOB_guest_customer_display_id']) ? $config['WC_MYOB_guest_customer_display_id'] : '';
			$this->cogs_account = isset($config['WC_MYOB_cogs_account']) ? $config['WC_MYOB_cogs_account'] : '';
			$this->asset_account = isset($config['WC_MYOB_asset_account']) ? $config['WC_MYOB_asset_account'] : '';

			$this->enable_product_create = isset($config['WC_OPMC_enable_product_create']) ? $config['WC_OPMC_enable_product_create'] : 'no';

			/* PLUGINS-2273 */
			$this->support_variation_product = isset($config['WC_OPMC_support_variation_product']) ? $config['WC_OPMC_support_variation_product'] : 'no';
			/* PLUGINS-2273 End */

			$this->create_closed_invoices = isset($config['WC_OPMC_create_closed_invoices']) ? $config['WC_OPMC_create_closed_invoices'] : 'no';
			$this->create_order_instead_of_invoice = isset($config['WC_OPMC_create_order_instead_of_invoice']) ? $config['WC_OPMC_create_order_instead_of_invoice'] : 'no';
			$this->create_orders_when_on_hold = isset($config['WC_OPMC_create_orders_when_on_hold']) ? $config['WC_OPMC_create_orders_when_on_hold'] : 'no';
			$this->enable_guest_customer_create = isset($config['WC_OPMC_create_guest_as_customer']) ? $config['WC_OPMC_create_guest_as_customer'] : 'no';
			$this->search_customer_by_customer_name = isset($config['WC_MYOB_search_by_customer_name']) ? $config['WC_MYOB_search_by_customer_name'] : 'no';
			$this->search_customer_by_company = isset($config['WC_MYOB_search_by_company']) ? $config['WC_MYOB_search_by_company'] : 'no';
			$this->search_customer_by_email = isset($config['WC_MYOB_search_by_email']) ? $config['WC_MYOB_search_by_email'] : 'no';
			$this->invoice_id_prefix = isset($config['WC_MYOB_invoice_id_prefix']) ? $config['WC_MYOB_invoice_id_prefix'] : '';

			$this->endpoint = 'https://api.myob.com/accountright/';
			$this->full_endpoint = 'https://api.myob.com/accountright/' . $this->company_file_id;

			$this->http_code = null;

			require_once plugin_dir_path(__FILE__) . 'background-processes/class-stars-order-batch-process.php';
			require_once plugin_dir_path(__FILE__) . 'background-processes/class-stars-product-import-process.php';
			require_once plugin_dir_path(__FILE__) . 'background-processes/class-stars-manual-payment-order-batch-process.php';
			require_once plugin_dir_path(__FILE__) . 'background-processes/class-stars-import-product-to-myob-process.php';


			$this->order_batch_process = new Opmc_Order_Batch_Process();
			$this->product_import_process = new Opmc_Product_Import_Process();
			$this->mp_order_batch_process = new Opmc_Manual_Payment_Order_Batch_Process();
			$this->import_product_to_myob_process = new Opmc_Import_Product_To_Myob_Process();
			/* initiation of logging instance */
			$this->log = new WC_Logger();
			$this->enable_debug_logging = isset($config['WC_OPMC_enable_debug_logging']) ? $config['WC_OPMC_enable_debug_logging'] : 'no';
			$this->enable_product_bulk_action = isset($config['WC_OPMC_enable_product_bulk_action']) ? $config['WC_OPMC_enable_product_bulk_action'] : 'no';

			$this->enable_only_sync_item_inventory = isset($config['WC_OPMC_only_sync_item_inventory']) ? $config['WC_OPMC_only_sync_item_inventory'] : 'no';

			// Hook into admin_notices to display the notice
			add_action('admin_notices', array($this, 'show_myob_sync_notice'));

			// if (isset($_GET['test'])) {
			//  $this->import_product_to_myob();
			// } PLUGINS-635
		}


		private function define_admin_hooks()
		{
			$this->loader->add_filter('manage_users_columns', $this, 'custom_column_for_myob_user_id', 10, 3);


		}
		public function custom_column_for_myob_user_id($columns)
		{
			$columns['myob_uid'] = esc_html__('Myob User ID');
			return $columns;
		}

		public function create_wc_log($apiresult)
		{
			if ('yes' == $this->enable_debug_logging) {
				$this->log->add('MYOB-Integration', $apiresult);
			}
		}

		// ── Debug helpers (used by Stars_Debug_Product_Fetch) ─────────────

		/**
		 * Returns true when the minimum credentials needed for API calls are set.
		 */
		public function has_credentials(): bool {
			return ! empty( $this->company_file_id )
				&& ! empty( $this->access_token )
				&& ! empty( $this->client_id );
		}

		/**
		 * Returns the fully-qualified MYOB API base URL including company file ID.
		 */
		public function get_full_endpoint(): string {
			return $this->full_endpoint;
		}

		/**
		 * Public wrapper around the private remote_get_json for debug use.
		 *
		 * @param  string     $url    Full URL to request.
		 * @param  array|null $params Optional OData query parameters.
		 * @return object|null
		 */
		public function public_remote_get( string $url, ?array $params = null ) {
			return $this->remote_get_json( $url, $params );
		}

		/**
		 * Make a raw GET request and return [ 'code' => int, 'body' => string ].
		 * Does NOT throw — always returns the raw HTTP result for diagnostic use.
		 *
		 * @param  string $url Full URL including any query string.
		 * @return array{ code: int, body: string }
		 */
		public function public_raw_get( string $url ): array {
			// Refresh token if stale (same logic as remote_get_json).
			if ( ( 600 + (int) get_option( 'WC_MYOB_refresh_token_timestamp' ) ) < time() ) {
				$this->refresh_token();
			}

			$result = wp_remote_get( $url, [
				'headers' => $this->create_headers(),
				'timeout' => $this->http_timeout,
			] );

			if ( is_wp_error( $result ) ) {
				return [
					'code' => 0,
					'body' => 'WP_Error: ' . $result->get_error_message(),
				];
			}

			return [
				'code' => (int) wp_remote_retrieve_response_code( $result ),
				'body' => wp_remote_retrieve_body( $result ),
			];
		}

		// ─────────────────────────────────────────────────────────────────

		/**
		 * Write a sync event to the plugin's own sync log file.
		 * Stored in the plugin directory as stars-myob-sync.log.
		 * Shown in the "Sync Log" tab of the plugin settings UI.
		 * Automatically purges entries older than the configured retention period.
		 *
		 * @param string $message  Log message.
		 * @param string $level    One of: INFO, SUCCESS, WARNING, ERROR.
		 */
		public function create_sync_log( $message, $level = 'INFO' ) {
			$log_file = WC_MYOB_INTEGRATION_PLUGINDIR . 'stars-myob-sync.log';
			$level    = strtoupper( $level );
			$line     = '[' . gmdate( 'Y-m-d H:i:s' ) . ' UTC] [' . $level . '] ' . ( is_scalar( $message ) ? $message : wp_json_encode( $message ) ) . PHP_EOL;

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $log_file, $line, FILE_APPEND | LOCK_EX );

			// Purge entries older than the configured retention period.
			$settings      = get_option( 'woocommerce_MYOB_integrations_settings', array() );
			$retain_days   = isset( $settings['WC_OPMC_sync_log_retention'] ) ? (int) $settings['WC_OPMC_sync_log_retention'] : 7;
			$cutoff        = gmdate( 'Y-m-d H:i:s', time() - ( $retain_days * DAY_IN_SECONDS ) );

			if ( ! file_exists( $log_file ) ) {
				return;
			}

			$raw   = file_get_contents( $log_file ); // phpcs:ignore
			$lines = explode( PHP_EOL, $raw );
			$kept  = array();

			foreach ( $lines as $entry ) {
				if ( empty( trim( $entry ) ) ) {
					continue;
				}
				// Extract timestamp: [2025-01-01 12:00:00 UTC]
				if ( preg_match( '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) UTC\]/', $entry, $m ) ) {
					if ( $m[1] >= $cutoff ) {
						$kept[] = $entry;
					}
					// Lines older than cutoff are dropped (purged).
				} else {
					$kept[] = $entry; // Keep lines that don't match the pattern.
				}
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $log_file, implode( PHP_EOL, $kept ) . PHP_EOL, LOCK_EX );
		}

		/**
		 * Always writes to WooCommerce logs (WooCommerce > Status > Logs),
		 * independent from plugin debug toggle.
		 */
		private function create_wc_debug_log($message, $level = 'debug')
		{
			$logger = wc_get_logger();
			$context = array('source' => 'myob-integration');
			$message = is_scalar($message) ? (string) $message : wp_json_encode($message);

			if ('error' === $level) {
				$logger->error($message, $context);
			} elseif ('warning' === $level) {
				$logger->warning($message, $context);
			} else {
				$logger->debug($message, $context);
			}
		}

		private function stringify_debug_value($value)
		{
			if (is_scalar($value) || null === $value) {
				return (string) $value;
			}

			return print_r($value, true);
		}

		private function set_order_debug_context($order_id, $flow = '')
		{
			$this->order_debug_context = array(
				'order_id' => (int) $order_id,
				'flow' => (string) $flow,
			);
		}

		private function clear_order_debug_context()
		{
			$this->order_debug_context = null;
		}

		private function write_order_debug_log($message, $level = 'INFO')
		{
			if (empty($this->order_debug_context) || !is_array($this->order_debug_context)) {
				return;
			}

			$path = WC_MYOB_INTEGRATION_PLUGINDIR . 'order-debug.log';
			$line = sprintf(
				"%s [%s] [order:%d] [flow:%s] %s%s",
				gmdate('Y-m-d\\TH:i:s\\Z'),
				strtoupper((string) $level),
				(int) ($this->order_debug_context['order_id'] ?? 0),
				(string) ($this->order_debug_context['flow'] ?? 'general'),
				rtrim($this->stringify_debug_value($message)),
				PHP_EOL
			);

			error_log($line, 3, $path);
		}

		/**
		 * Detects MYOB insufficient stock errors that block invoice creation.
		 */
		private function is_myob_insufficient_stock_error($message)
		{
			$message = strtolower((string) $message);
			return (false !== strpos($message, 'inventory_insufficientstockmultiplelocation'))
				|| (false !== strpos($message, 'errorcode') && false !== strpos($message, '4051'));
		}

		/**
		 * Checks whether an invoice would fail because ordered qty exceeds MYOB available qty.
		 */
		private function get_invoice_stock_shortages($order, $myob_items)
		{
			$shortages = array();
			$available_by_sku = array();

			if (is_array($myob_items)) {
				foreach ($myob_items as $item) {
					if (is_object($item) && isset($item->Number)) {
						$available_by_sku[(string) $item->Number] = isset($item->QuantityAvailable) ? (float) $item->QuantityAvailable : 0.0;
					}
				}
			}

			foreach ($order->get_items() as $order_item) {
				$product = $order_item->get_product();
				if (!$product) {
					continue;
				}

				$sku = (string) $product->get_sku();
				$required = (float) $order_item->get_quantity();
				$available = array_key_exists($sku, $available_by_sku) ? (float) $available_by_sku[$sku] : 0.0;

				if ($sku !== '' && $required > $available) {
					$shortages[] = array(
						'sku' => $sku,
						'required' => $required,
						'available' => $available,
					);
				}
			}

			return $shortages;
		}

		private function is_customer_create_request($uri)
		{
			return is_string($uri) && strpos($uri, '/Contact/Customer') === 0;
		}

		private function extract_customer_create_debug_context($uri, $params)
		{
			if (!$this->is_customer_create_request($uri) || !is_array($params)) {
				return array();
			}

			$selling_details = array();
			if (isset($params['SellingDetails']) && is_array($params['SellingDetails'])) {
				$selling_details = $params['SellingDetails'];
			}

			$context = array(
				'company_file_id' => (string) $this->company_file_id,
				'full_endpoint' => (string) ($this->endpoint . $this->company_file_id . $uri),
				'settings_company_file_id' => '',
				'company_name' => isset($params['CompanyName']) ? (string) $params['CompanyName'] : '',
				'tax_code' => isset($selling_details['TaxCode']) ? $selling_details['TaxCode'] : null,
				'freight_tax_code' => isset($selling_details['FreightTaxCode']) ? $selling_details['FreightTaxCode'] : null,
			);

			$config = get_option('woocommerce_MYOB_integrations_settings', array());
			if (is_array($config) && !empty($config['WC_MYOB_company_file_id'])) {
				$context['settings_company_file_id'] = (string) $config['WC_MYOB_company_file_id'];
			}

			return $context;
		}

		private function log_live_customer_create_tax_code_diagnostics($customer_create_debug_context, $response_body)
		{
			if (empty($customer_create_debug_context) || !is_array($customer_create_debug_context)) {
				return;
			}

			$response_body = (string) $response_body;
			if (
				strpos($response_body, 'TaxCodeNotFound') === false
				&& strpos($response_body, 'FreightTaxCode') === false
				&& strpos($response_body, 'TaxCode.UID') === false
			) {
				return;
			}

			try {
				$live_tax_codes = $this->get_tax_codes();
				$tax_code_uid = '';
				$freight_tax_code_uid = '';

				if (
					isset($customer_create_debug_context['tax_code'])
					&& is_array($customer_create_debug_context['tax_code'])
					&& !empty($customer_create_debug_context['tax_code']['UID'])
				) {
					$tax_code_uid = (string) $customer_create_debug_context['tax_code']['UID'];
				}

				if (
					isset($customer_create_debug_context['freight_tax_code'])
					&& is_array($customer_create_debug_context['freight_tax_code'])
					&& !empty($customer_create_debug_context['freight_tax_code']['UID'])
				) {
					$freight_tax_code_uid = (string) $customer_create_debug_context['freight_tax_code']['UID'];
				}

				$diagnostic = array(
					'live_tax_code_count' => is_array($live_tax_codes) ? count($live_tax_codes) : 0,
					'selected_tax_code_uid' => $tax_code_uid,
					'selected_tax_code_exists_live' => is_array($live_tax_codes) && $tax_code_uid !== '' && isset($live_tax_codes[$tax_code_uid]) ? 'yes' : 'no',
					'selected_tax_code_label_live' => is_array($live_tax_codes) && $tax_code_uid !== '' && isset($live_tax_codes[$tax_code_uid]) ? $live_tax_codes[$tax_code_uid] : '',
					'selected_freight_tax_code_uid' => $freight_tax_code_uid,
					'selected_freight_tax_code_exists_live' => is_array($live_tax_codes) && $freight_tax_code_uid !== '' && isset($live_tax_codes[$freight_tax_code_uid]) ? 'yes' : 'no',
					'selected_freight_tax_code_label_live' => is_array($live_tax_codes) && $freight_tax_code_uid !== '' && isset($live_tax_codes[$freight_tax_code_uid]) ? $live_tax_codes[$freight_tax_code_uid] : '',
					'live_tax_codes' => $live_tax_codes,
				);

				$this->create_wc_log('[MYOB Customer Create Debug] [Live Tax Code Diagnostics]');
				$this->create_wc_log(print_r($diagnostic, true));
			} catch (Exception $e) {
				$this->create_wc_log('[MYOB Customer Create Debug] [Live Tax Code Diagnostics Failed]');
				$this->create_wc_log($e->getMessage());
			} catch (Throwable $e) {
				$this->create_wc_log('[MYOB Customer Create Debug] [Live Tax Code Diagnostics Failed]');
				$this->create_wc_log($e->getMessage());
			}
		}

		/**
		 * Return true if the required credentials for MYOB access
		 * have been defined
		 */
		public function are_credentials_defined()
		{

			if (
				!empty($this->company_file_id)
				&& !empty($this->access_token)
				&& !empty($this->client_id)
			) {
				return true;
			}

			return false;
		}

		public function admin_notice_error()
		{
			$class = 'notice notice-error';
			$message = 'An error has occurred with an MYOB transaction.';

			printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
		}

		/**
		 * Build the HTTP message with the appropriate authentication headers
		 */
		private function create_headers()
		{

			$config = get_option('woocommerce_MYOB_integrations_settings');
			$WC_MYOB_company_file_username = isset($config['WC_MYOB_company_file_username']) ? trim($config['WC_MYOB_company_file_username']) : '';
			$WC_MYOB_company_file_password = isset($config['WC_MYOB_company_file_password']) ? trim($config['WC_MYOB_company_file_password']) : '';
			$cftoken = base64_encode($WC_MYOB_company_file_username . ':' . $WC_MYOB_company_file_password);
			// print_r($config);exit;
			$x = array(
				'Authorization' => 'Bearer ' . $this->access_token,
				'x-myobapi-key' => $this->client_id,
				'Accept-Encoding' => 'gzip,deflate',
				'x-myobapi-version' => 'v2',
				'scope' => 'CompanyFile',
				'Content-Type' => 'application/json',
				'x-myobapi-cftoken' => $cftoken,
			);
			// print_r($x);
			return $x;
		}


		/**
		 * Wrapper for a generic POST call to MYOB API
		 *
		 * @return NULL on error
		 * @return decoded JSON on success
		 * @return false on empty response
		 */
		private function remote_post_json($uri, $params)
		{


			if ((600 + get_option('WC_MYOB_refresh_token_timestamp')) < time()) {
				$this->create_wc_log('[MYOB Connection] [Info] [Token expired - refreshing]');
				$this->refresh_token();
			}
			$customer_create_debug_context = $this->extract_customer_create_debug_context($uri, $params);
			if (!empty($customer_create_debug_context)) {
				$this->create_wc_log('[MYOB Customer Create Debug] [Request Context]');
				$this->create_wc_log(print_r($customer_create_debug_context, true));
			}
			$this->write_order_debug_log('[MYOB API Request] [POST] [URI] ' . $uri);
			$this->write_order_debug_log('[MYOB API Request] [POST] [Payload]');
			$this->write_order_debug_log($params);
			$testREQ = array('headers' => $this->create_headers(), 'body' => json_encode($params), 'timeout' => $this->http_timeout);
			$debugStr = print_r($testREQ, true);

			$resp = wp_remote_post($this->endpoint . $this->company_file_id . $uri, array(
				'headers' => $this->create_headers(),
				'body' => json_encode($params),
				'timeout' => $this->http_timeout,
			));

			if (is_wp_error($resp)) {
				$error_string = $resp->get_error_message();
				echo '<div id="message" class="error"><p>' . esc_html($error_string) . '</p></div>';
				$this->create_wc_log("[MYOB API Request] [Error] [MYOB is_wp_error on post: $error_string]");
				$this->create_wc_log(print_r($resp, true));

				$error_string = esc_html($error_string);
				$exception_message = "Oops! We had a problem processing your order.  $error_string ";
				throw new Opmc_Myob_Exception(esc_html($exception_message));
			}

			$resp_code = $resp['response']['code'];
			//$locationString = $resp['headers']['data']['location'];
			$this->http_code = $resp_code;
			$this->write_order_debug_log('[MYOB API Response] [POST] [HTTP Code] ' . $resp_code);
			$this->write_order_debug_log('[MYOB API Response] [POST] [Body]');
			$this->write_order_debug_log(isset($resp['body']) ? $resp['body'] : '');
			//Opmc_Logger::error('locationString: '.$locationString);

			if (200 !== $resp_code && 201 != $resp_code) {
				$this->create_wc_log("[MYOB API Request] [Error] [MYOB Error $resp_code returned from MYOB server]");
				$this->create_wc_log(print_r($resp, true));
				if (!empty($customer_create_debug_context)) {
					$this->create_wc_log('[MYOB Customer Create Debug] [Failed Response]');
					$this->create_wc_log(print_r(array(
						'response_code' => $resp_code,
						'response_body' => isset($resp['body']) ? $resp['body'] : '',
						'request_context' => $customer_create_debug_context,
					), true));
					$this->log_live_customer_create_tax_code_diagnostics(
						$customer_create_debug_context,
						isset($resp['body']) ? $resp['body'] : ''
					);
				}

				if (401 == $resp_code) {
					$this->create_wc_log('[MYOB API Request] [Error] [MYOB 401 returned, attempting to re-authorize connection to MYOB]');

					$x = get_option('WC_MYOB_api_unauthorized_count');
					update_option('WC_MYOB_api_unauthorized_count', $x + 1);

					$this->refresh_token();
					return false;
				}

				if (empty($resp['body'])) {
					return false;
				}

				$error_array = json_decode($resp['body'], true);

				foreach ($error_array['Errors'] as $value) {
					$Name = esc_html($value['Name']);
					$Message = esc_html($value['Message']);
					$AdditionalDetails = esc_html($value['AdditionalDetails']);
					$ErrorCode = $value['ErrorCode'];
				}

				if (isset($ErrorCode) && 50 == $ErrorCode) {

					$resp_code = esc_html($resp_code);
					if (strpos($AdditionalDetails, 'FreightTaxCode') !== false) {

						$exception_message = "Oops! We had a problem processing your order. <br /> Here are the possible errors: <br /> Error code: $resp_code <br /> Name: $Name <br /> Message: $Message <br /> Error Type: FreightTaxCode not validated with MYOB";
						throw new Opmc_Myob_Exception(esc_html($exception_message));

					} elseif (strpos($AdditionalDetails, 'Item') !== false) {

						$exception_message = "Oops! We had a problem processing your order. <br /> Here is the posible errors: <br /> Error code: $resp_code <br /> Name: $Name <br /> Message: $Message <br /> Error Type: Item SKU not validate with MYOB";
						throw new Opmc_Myob_Exception(esc_html($exception_message));
					} elseif (strpos($AdditionalDetails, 'Job') !== false) {

						$exception_message = "Oops! We had a problem processing your order. <br /> Here is the possible errors: <br /> Error code: $resp_code <br /> Name: $Name <br /> Message: $Message <br /> <br/><strong>Error Type: Invalid Job Code</strong><br/><br/><strong>Solution:</strong> The Job code referenced in this order does not exist in MYOB. Please:<br/>1. Check System Settings for MYOB Job Code<br/>2. Check Individual Product Job Code setting<br/>3. Verify the Job exists in MYOB (GeneralLedger > Job)<br/>4. Clear the Job Code setting and retry if the Job should not be used<br/>Check the error log for more details about which Job UID failed.";
						error_log('[MYOB Order] Job validation failed - Details: ' . $AdditionalDetails . ' - Message: ' . $Message);
						throw new Opmc_Myob_Exception(esc_html($exception_message));
					} elseif (strpos($AdditionalDetails, 'TaxCode') !== false) {

						$exception_message = "Oops! We had a problem processing your order. <br /> Here is the posible errors: <br /> Error code: $resp_code <br /> Name: $Name <br /> Message: $Message <br /> Error Type: TaxCode not validate with MYOB";
						throw new Opmc_Myob_Exception(esc_html($exception_message));
					}

					$this->create_wc_log(print_r($error_array, true));
				} //if(isset($ErrorCode) && 50 == $ErrorCode)

				$exception_message = "Oops! We had a problem processing your order. <br /> Here is the posible errors: <br /> Error code: $resp_code <br /> Name: $Name <br /> Message: $Message <br /> AdditionalDetails: $AdditionalDetails";
				throw new Opmc_Myob_Exception(esc_html($exception_message));
			}

			$x = get_option('WC_MYOB_api_unauthorized_count');
			if (0 < $x) {
				update_option('WC_MYOB_api_unauthorized_count', 0);
			}
			if (!empty($customer_create_debug_context)) {
				$this->create_wc_log('[MYOB Customer Create Debug] [Success Response]');
				$this->create_wc_log(print_r(array(
					'response_code' => $resp_code,
					'request_context' => $customer_create_debug_context,
				), true));
			}

			return json_decode($resp['body']);
		}

		public function create_customer_via_api(array $params)
		{
			return $this->remote_post_json('/Contact/Customer?returnBody=true', $params);
		}


		/**
		 * Wrapper for a generic POST call to MYOB API
		 *
		 * @return NULL on error
		 * @return decoded JSON on success
		 * @return false on empty response
		 */
		private function remote_put_json($uri, $params)
		{


			if ((600 + get_option('WC_MYOB_refresh_token_timestamp')) < time()) {
				$this->create_wc_log('[MYOB Connection] [Info] [Token expired - refreshing]');
				$this->refresh_token();
			}

			$resp = wp_remote_request($this->endpoint . $this->company_file_id . $uri, array(
				'method' => 'PUT',
				'body' => json_encode($params),
				'headers' => $this->create_headers(),
				'timeout' => $this->http_timeout,
			));

			if (is_wp_error($resp)) {
				$error_string = $resp->get_error_message();
				echo '<div id="message" class="error"><p>' . esc_html($error_string) . '</p></div>';
				$this->create_wc_log("[MYOB API Request] [Error] [MYOB is_wp_error on put: $error_string]");
				$this->create_wc_log(print_r($resp, true));

				$error_string = esc_html($error_string);
				$exception_message = "Oops! We had a problem processing your order.  $error_string";
				throw new Opmc_Myob_Exception(esc_html($exception_message));
			}

			$resp_code = $resp['response']['code'];
			$this->http_code = $resp_code;


			if (200 !== $resp_code && 201 != $resp_code) {
				$this->create_wc_log("[MYOB API Request] [Error] [MYOB Error $resp_code returned from MYOB server. " . print_r($resp, 1) . ']');
				$this->create_wc_log(print_r($resp, 1));

				if (401 == $resp_code) {
					$this->create_wc_log('[MYOB API Request] [Error] [MYOB 401 returned, attempting to re-authorize connection to MYOB]');

					$x = get_option('WC_MYOB_api_unauthorized_count');
					update_option('WC_MYOB_api_unauthorized_count', $x + 1);

					$this->refresh_token();
					return false;
				}

				$error_array = json_decode($resp['body'], true);
				foreach ($error_array['Errors'] as $value) {
					$Name = esc_html($value['Name']);
					$Message = esc_html($value['Message']);
					$AdditionalDetails = esc_html($value['AdditionalDetails']);
				}

				$resp_code = esc_html($resp_code);
				$exception_message = '[MYOB API Request] [Error] [Oops! We had a problem processing your order.' . PHP_EOL . 'Here are the possible errors:' . PHP_EOL . "Error code: $resp_code" . PHP_EOL . 'Name: ' . $Name . PHP_EOL . 'Message: ' . $Message . PHP_EOL . 'AdditionalDetails: ' . $AdditionalDetails . ']';
				throw new Opmc_Myob_Exception(esc_html($exception_message));
			}

			if (empty($resp['body'])) {
				return false;
			}

			$x = get_option('WC_MYOB_api_unauthorized_count');
			if (0 < $x) {
				update_option('WC_MYOB_api_unauthorized_count', 0);
			}

			return json_decode($resp['body']);
		}


		/**
		 * Perform a get request to MYOB and return result as JSON
		 *
		 * @return JSON response
		 * @return NULL on error response
		 * @return false on empty response
		 */

		private function remote_get_json($uri, $params = null)
		{

			if ((600 + get_option('WC_MYOB_refresh_token_timestamp')) < time()) {
				$this->create_wc_log('[MYOB Connection] [Info] [Token expired - refreshing]');
				$this->refresh_token();
			}

			$param_list = '';

			if (null !== $params) {
				$param_list = '?';

				foreach ($params as $key => $value) {
					$param_list .= $key . '=' . $value;
					$param_list .= '&';
				}

				$param_list = rtrim($param_list, '&');
			}

			$debug_endpoint = $uri . $param_list;
			$this->create_wc_log("ENDPOINT CALLED: $debug_endpoint");
			$this->write_order_debug_log("ENDPOINT CALLED: $debug_endpoint");
			if (null !== $params) {
				$this->write_order_debug_log('[MYOB API Request] [GET] [Params]');
				$this->write_order_debug_log($params);
			}

			$get_result = wp_remote_get($uri . $param_list, array(
				'headers' => $this->create_headers(),
				'timeout' => $this->http_timeout,
			));

			if (is_wp_error($get_result)) {
				$this->create_wc_log('[MYOB API Request] [Error] [MYOB is_wp_error on get: ' . $get_result->get_error_message() . ']');
				$this->create_wc_log(print_r($get_result, true));

				$error_string = esc_html($get_result->get_error_message());
				$exception_message = "Oops! We had a problem processing your order.  $error_string";
				throw new Opmc_Myob_Exception(esc_html($exception_message));
			}


			// handle HTTP error or record not found
			$resp_code = $get_result['response']['code'];
			$this->http_code = $resp_code;
			$this->write_order_debug_log('[MYOB API Response] [GET] [HTTP Code] ' . $resp_code);
			$this->write_order_debug_log('[MYOB API Response] [GET] [Body]');
			$this->write_order_debug_log(isset($get_result['body']) ? $get_result['body'] : '');

			if (200 !== $resp_code && 201 != $resp_code) {
				$this->create_wc_log("[MYOB API Request] [Error] [MYOB Error $resp_code returned from MYOB server]");
				$this->create_wc_log(print_r($get_result, 1));
				if (401 == $resp_code) {

					$x = get_option('WC_MYOB_api_unauthorized_count');
					update_option('WC_MYOB_api_unauthorized_count', $x + 1);

					$this->create_wc_log('[MYOB API Request] [Error] [MYOB 401 returned, attempting to re-authorize connection to MYOB]');
					$this->refresh_token();
					return false;
				}

				$error_array = json_decode($get_result['body'], true);
				foreach ($error_array['Errors'] as $value) {
					$Name = esc_html($value['Name']);
					$Message = esc_html($value['Message']);
					$AdditionalDetails = esc_html($value['AdditionalDetails']);
				}

				$resp_code = esc_html($resp_code);
				$exception_message = '[MYOB API Request] [Error] [Oops! We had a problem processing your order.' . PHP_EOL . 'Here are the possible errors:' . PHP_EOL . "Error code: $resp_code" . PHP_EOL . 'Name: ' . $Name . PHP_EOL . 'Message: ' . $Message . PHP_EOL . 'AdditionalDetails: ' . $AdditionalDetails . ']';
				throw new Opmc_Myob_Exception(esc_html($exception_message));
			}

			if (empty($get_result['body'])) {
				return false;
			}

			$x = get_option('WC_MYOB_api_unauthorized_count');
			if (0 < $x) {
				update_option('WC_MYOB_api_unauthorized_count', 0);
			}

			return json_decode($get_result['body']);
		}


		/**
		 * Generic helper function to get a list of data from the remote MYOB server
		 * using HTTP get
		 *
		 * @param $api - path to API
		 * @param $params - array of parameters to put on GET request
		 * @param $name_field - optional function to populate name field from result data
		 * @param $uid_field - optional function to populate UID field from result data
		 *
		 * @return an array of UID => Name suitable for populating a SELECT control
		 */
		private function get_remote_list($api, $params = null, callable $name_field = null, callable $uid_field = null, callable $items_array = null)
		{

			if (!$this->are_credentials_defined()) {
				return null;
			}

			// define default functions to populate name and UID
			if (null === $name_field) {
				$name_field = function ($x) {
					return $x->DisplayID . ' - ' . $x->Name;
				};
			}

			if (null === $uid_field) {
				$uid_field = function ($x) {
					return $x->UID;
				};
			}

			if (null === $items_array) {
				$items_array = function ($x) {
					return $x->Items;
				};
			}

			$resp = $this->remote_get_json($api, $params);


			// TODO if response is NULL, keep the old value in the cache
			if (null !== $resp && false !== $resp) {
				$name = array();
				$uid = array();

				foreach ($items_array($resp) as $item) {
					$name[] = $name_field($item);
					$uid[] = $uid_field($item);
				}
				$x = array_combine($uid, $name);
				return $x;
			}

			return array('');
		}


		public function get_company_file()
		{
			if (empty($this->access_token) || empty($this->client_id)) {
				$this->create_wc_log('[MYOB Company File] Missing access token or client id; cannot load company file list.');
				return array();
			}

			$headers = array(
				'Authorization' => 'Bearer ' . $this->access_token,
				'x-myobapi-key' => $this->client_id,
				'Accept-Encoding' => 'gzip,deflate',
				'x-myobapi-version' => 'v2',
				'Content-Type' => 'application/json',
			);

			$response = wp_remote_get($this->endpoint, array(
				'timeout' => $this->http_timeout,
				'headers' => $headers,
			));

			if (is_wp_error($response)) {
				$this->create_wc_log('[MYOB Company File] Request failed: ' . $response->get_error_message());
				return array();
			}

			$status_code = isset($response['response']['code']) ? (int) $response['response']['code'] : 0;
			$this->create_wc_log('[MYOB Company File] Response code: ' . $status_code);

			if (empty($response['body'])) {
				$this->create_wc_log('[MYOB Company File] Empty response body.');
				return array();
			}

			$company_files = json_decode($response['body']);
			if (empty($company_files) || !is_array($company_files)) {
				$this->create_wc_log('[MYOB Company File] Unexpected response body: ' . substr($response['body'], 0, 1000));
				return array();
			}

			$options = array();
			foreach ($company_files as $company_file) {
				if (isset($company_file->Id, $company_file->Name)) {
					$options[$company_file->Id] = $company_file->Name;
				}
			}

			$this->create_wc_log('[MYOB Company File] Loaded ' . count($options) . ' company file(s).');
			return $options;
		}


		public function get_income_accounts()
		{
			$x = $this->get_remote_list($this->full_endpoint . '/GeneralLedger/Account', array('$filter' => "Type eq 'Income' and IsHeader eq false"));
			//$this->create_wc_log('get_income_accounts' . print_r($x, true));
			return $x;
		}


		public function get_expense_accounts()
		{
			$x = $this->get_remote_list($this->full_endpoint . '/GeneralLedger/Account', array('$filter' => "Type eq 'Expense' and IsHeader eq false"));
			//          $this->create_wc_log('Successfully get_expense_accounts' );
			$this->create_wc_log('[MYOB API Request] [Success] [Expenses Accounts fetched successfully.]');
			return $x;
		}


		public function get_asset_accounts()
		{
			$x = $this->get_remote_list($this->full_endpoint . '/GeneralLedger/Account', array('$filter' => "Classification eq 'Asset' and IsHeader eq false"));
			//          $this->create_wc_log('Successfully get_asset_accounts');
			$this->create_wc_log('[MYOB API Request] [Success] [Asset Accounts fetched successfully.]');
			return $x;
		}


		public function get_cogs_accounts()
		{
			$x = $this->get_remote_list($this->full_endpoint . '/GeneralLedger/Account', array('$filter' => "Type eq 'CostOfSales' and IsHeader eq false"));
			//          $this->create_wc_log('Successfully get_cogs_accounts');
			$this->create_wc_log('[MYOB API Request] [Success] [Cost of Sales Accounts fetched successfully.]');
			return $x;
		}


		public function get_tax_codes()
		{
			return $this->get_remote_list($this->full_endpoint . '/GeneralLedger/TaxCode', null, function ($x) {
				return $x->Code . ' - ' . $x->Description;
			});
		}


		public function get_job_codes()
		{
			$filter = 'IsActive eq true';
			$params = array('$filter' => urlencode($filter));
			$jobs = $this->remote_get_json($this->full_endpoint . '/GeneralLedger/Job', $params);
			$job_codes = array();

			if (!empty($jobs)) {
				foreach ($jobs->Items as $key => $job) {
					if (true != $job->IsHeader) {
						$job_codes[$job->UID] = $job->Number;
					}
				}
			}

			return $job_codes;
		}


		public function reload_accounts_list()
		{
			update_option('WC_MYOB_company_file_list', $this->get_company_file());
			update_option('WC_MYOB_income_accounts_list', $this->get_income_accounts());
			update_option('WC_MYOB_expense_accounts_list', $this->get_expense_accounts());
			update_option('WC_MYOB_asset_accounts_list', $this->get_asset_accounts());
			update_option('WC_MYOB_cogs_accounts_list', $this->get_cogs_accounts());
			update_option('WC_MYOB_tax_codes_list', $this->get_tax_codes());
			update_option('WC_MYOB_job_codes_list', $this->get_job_codes());
			/**
			 * Do add_myob_taxcode_meta_boxes
			 *
			 * @since 3.6
			 */
			do_action('add_myob_taxcode_meta_boxes');
			// do_action('restrict_manage_posts');
		}

		/*
		 * cron_schedules
		 * execute as cron job function
		 */
		public function refresh_token()
		{
			$this->create_wc_log('refresh_token()');


			if (get_option('MYOB_access_token')) {
				// build up the params for generating token
				$params = array(
					'client_id' => $this->client_id,
					'client_secret' => $this->client_secret,
					'grant_type' => 'refresh_token',
					'refresh_token' => get_option('MYOB_access_refresh_token'),
				);

				$query = 'client_id=' . $params['client_id'] .
					'&client_secret=' . $params['client_secret'] .
					'&grant_type=refresh_token' .
					'&refresh_token=' . urlencode(get_option('MYOB_access_refresh_token'));


				// generate token post through api
				$response = wp_remote_post(TOKEN_URI, array(
					'body' => $params,
				));

				// CAUTION:  this could probably call remote_post_json but does not do so, due to
				// the risk of an endless loop if that function receives a 401.

				if (!is_wp_error($response) && (200 == $response['response']['code'] || 201 == $response['response']['code'])) {
					$tokenData = json_decode($response['body']);
					// print_r($tokenData);
					// update token related data in option table
					update_option('MYOB_access_token', $tokenData->access_token);
					$this->access_token = $tokenData->access_token;
					update_option('MYOB_access_refresh_token', $tokenData->refresh_token);
					update_option('MYOB_access_token_type', $tokenData->token_type);
					update_option('MYOB_access_token_scope', isset($tokenData->scope) ? $tokenData->scope : '');
					update_option('WC_MYOB_refresh_token_timestamp', time());
					update_option('WC_MYOB_refresh_token_failed', 'no');
					$this->create_wc_log('[MYOB Connection] [Info] [Token refresh successfully]');
				} else {
					update_option('WC_MYOB_refresh_token_failed', 'yes');
					$this->create_wc_log('[MYOB Connection] [Info] [Token refresh failed]');
				}
			}
		}




		/**
		 * Get the customer UID from MYOB
		 *
		 * @return null if customer not found
		 * @return UID of customer in MYOB if found
		 */
		public function get_erp_customer($order_id)
		{

			$order = new WC_Order($order_id);
			$customer_id = $order->get_customer_id();
			$this->create_wc_log('Get Woo customer_id ' . $customer_id);
			$first_name = $order->get_billing_first_name();
			$last_name = $order->get_billing_last_name();
			$company_name = $order->get_billing_company();
			// handle guest account
			$display_id = $this->customer_id_prefix . $customer_id;

			if ('yes' == $this->search_customer_by_company && '' != $company_name) {
				$customer = $this->get_customer_with_company($company_name);
				if (null != $customer) {
					return $customer;
				}
			} elseif ('yes' == $this->search_customer_by_customer_name) {
				$customer = $this->get_customer_with_name($first_name, $last_name);
				if (null != $customer) {
					return $customer;
				}

			} else {
				if (0 == $customer_id) {
					$customer_create_with_email = $this->enable_guest_customer_create;
					if ('yes' == $customer_create_with_email) {

						if ('yes' == $this->search_customer_by_email) {
							$customer = $this->get_customer_with_email($order->get_billing_email());
							if (null != $customer) {
								return $customer;
							}
						}
					} else {

						if (empty($this->guest_customer_display_id) || ctype_space($this->guest_customer_display_id)) {
							$display_id = $this->customer_id_prefix . $order->get_order_number() . '-GUEST';
						} else {

							$string = $this->guest_customer_display_id;
							$display_id = substr($string, 0, 15);
						}
					}
				}
			}

			$this->create_wc_log('Get customer display_id: ' . $display_id);
			$filter = 'DisplayID eq ' . "'" . $display_id . "'";

			$params = array('$filter' => urlencode($filter));
			$cust = $this->remote_get_json($this->full_endpoint . '/Contact/Customer', $params);

			// TODO add error check

			if (false !== $cust && null !== $cust && isset($cust->Items) && count($cust->Items) === 1) {
				$cust_uid = $cust->Items[0]->UID;
				$res = $cust->Items[0];
				$this->create_wc_log('[Customer Import] [Info] [Customer DisplayId: ' . $cust_uid . ' found in MYOB.]');
				return $res;

			} else {
				if ('yes' == $this->search_customer_by_email) {
					$this->create_wc_log('Customer DisplayId not found and current user is logged in, so search customer by user billing email.');
					$this->create_wc_log('Customer billing_email ' . print_r($order->get_billing_email(), true));

					$customer = $this->get_customer_with_email($order->get_billing_email());

					if (null != $customer) {
						$this->create_wc_log('[Customer Import] [Info] [Customer email: ' . $customer->UID . ' found in MYOB.]');
						return $customer;
					}
				}
			}
			return null;
		}


		public function get_customer_with_company($company_name)
		{
			$filter = 'CompanyName%20eq%20%27' . $company_name . '%27';

			$params = array('$filter' => $filter);

			$cust = $this->remote_get_json($this->full_endpoint . '/Contact/Customer', $params);
			$this->create_wc_log('Customer By Comapny Name : ' . print_r($cust, 1));
			if (false !== $cust && null !== $cust && isset($cust->Items) && count($cust->Items) > 0) {
				$cust_uid = $cust->Items[0]->UID;
				// print_r($cust_uid);exit;

				$this->create_wc_log('[Customer Import] [Info] [Customer ' . $cust_uid . ' found in MYOB.]');
				$res = $cust->Items[0];
				return $res;
			}
			return null;
		}
		public function get_customer_with_name($first_name, $last_name)
		{
			$full_name = $first_name . ' ' . $last_name;
			$filter = 'concat(concat(FirstName, \' \'), LastName) eq \'' . $full_name . '\'';


			$params = array('$filter' => urlencode($filter));

			$cust = $this->remote_get_json($this->full_endpoint . '/Contact/Customer', $params);
			$this->create_wc_log('Customer By Customer Name : ' . print_r($cust, 1));
			if (false !== $cust && null !== $cust && isset($cust->Items) && count($cust->Items) > 0) {
				$cust_uid = $cust->Items[0]->UID;
				// print_r($cust_uid);exit;

				$this->create_wc_log('Customer ' . $cust_uid . ' found in MYOB.');
				$res = $cust->Items[0];
				return $res;
			}
			return null;


		}

		public function get_customer_with_email($email)
		{

			$filter = 'Addresses/any(x: x/Email eq ' . "'" . $email . "')";

			/**
			 * Filter to modify the MYOB search email filter.
			 *
			 * @since 1.0
			 * @param array $filter - Current filter.
			 * @param string $email - Email being searched.
			 */
			$filter = apply_filters('wc_myob_set_search_email_filter', $filter, $email);

			$params = array('$filter' => urlencode($filter));

			$cust = $this->remote_get_json($this->full_endpoint . '/Contact/Customer', $params);
			if (false !== $cust && null !== $cust && isset($cust->Items) && count($cust->Items) > 0) {
				$cust_uid = $cust->Items[0]->UID;
				// print_r($cust_uid);exit;
				$this->create_wc_log('[Customer Import] [Info] [Customer found ' . print_r($cust_uid, true) . ']');
				$res = $cust->Items[0];
				return $res;
			}
			return null;
		}
		

        public function get_single_product_with_sku( $product_sku ) {
        
            $params = array(
                '$filter' => "Number eq '{$product_sku}'",
                '$top'    => 10,
            );
        
            $response = $this->remote_get_json(
                $this->full_endpoint . '/Inventory/Item',
                $params
            );
        
            if ( empty( $response->Items ) ) {
                return null;
            }
        
            return $response->Items[0];
        }





		/** Create customer in MYOB Account Right
		 *
		 * @return null if customer not found
		 * @return new customer record created
		 */
		public function create_customer($order_id)
		{
			$this->create_wc_log('create_customer()');

			if (null === $order_id) {
				$this->create_wc_log('[Customer Export] [Error] [Input is null on create customer]');
				return null;
			}

			if (empty($this->tax_code_new_product) || empty($this->freight_tax_code)) {
				$this->create_wc_log('[Customer Export] [Error] [Tax Code or Freight tax code is not set.]');
				return null;
			}

			$order = new WC_Order($order_id);

			// handle guest account
			$customer_id = $order->get_customer_id();
			$billing_company = $order->get_billing_company();
			$first_name = $order->get_billing_first_name();
			$last_name = $order->get_billing_last_name();
			$emailId = $order->get_billing_email();
			$get_billing_address_1 = $order->get_billing_address_1();
			$get_billing_city = $order->get_billing_city();
			$get_billing_state = $order->get_billing_state();
			$get_billing_postcode = $order->get_billing_postcode();
			$get_billing_country = $order->get_billing_country();
			$get_billing_phone = $order->get_billing_phone();
			$customer_create_with_email = $this->enable_guest_customer_create;
			if (0 === $customer_id) {
				if (empty($this->guest_customer_display_id) || ctype_space($this->guest_customer_display_id)) {

					$string = $this->customer_id_prefix . $order->get_order_number() . '-GUEST';
					$display_id = substr($string, 0, 15);

				} else if ('yes' == $customer_create_with_email) {

					$string = $this->customer_id_prefix . $order->get_order_number();
					$display_id = substr($string, 0, 15);

				} else {

					$string = $this->guest_customer_display_id;
					$display_id = substr($string, 0, 15);
					$first_name = 'Guest';
					$last_name = 'guest';
					$emailId = get_option('admin_email');
					$get_billing_address_1 = get_option('woocommerce_store_address');
					$get_billing_city = get_option('woocommerce_store_city');
					$get_billing_country = get_option('woocommerce_default_country');
					$get_billing_country = explode(':', $get_billing_country);
					$get_billing_state = $get_billing_country[1];
					$get_billing_postcode = get_option('woocommerce_store_postcode');
					$get_billing_country = $get_billing_country[0];
				}
			} else {
				$display_id = $this->customer_id_prefix . $customer_id;
				if ('' != $display_id && 'yes' == $this->search_customer_by_company && '' != $billing_company) {
					$display_id = $display_id . '-' . $billing_company;
				}
			}

			$customer_address = array(
				array(
					'Location' => 1,
					'Street' => $get_billing_address_1,
					'City' => $get_billing_city,
					'State' => $get_billing_state,
					'PostCode' => $get_billing_postcode,
					'Country' => $get_billing_country,
					'Email' => $emailId,
					'Phone1' => $get_billing_phone,
					'ContactName' => $first_name . ' ' . $last_name,
				),
			);

			/**
			 * Filter to alter the customer's address in MYOB.
			 *
			 * @since 1.0
			 *
			 * @param array $customer_address - The original address.
			 */
			$customer_address = apply_filters('wc_myob_alter_customer_address', $customer_address);

			$params = array(
				'LastName' => $last_name,
				'FirstName' => $first_name,
				'DisplayID' => $display_id,
				'SellingDetails' => array(
					'Terms' => array(
						'PaymentIsDue' => 'PrePaid',
					),
					'TaxCode' => array(
						'UID' => $this->tax_code_new_product,
					),
					'FreightTaxCode' => array(
						'UID' => $this->freight_tax_code,
					),
				),
				'Addresses' => $customer_address,
			);

			// For customer designation type
			$wc_setting = get_option('woocommerce_MYOB_integrations_settings');

			$WC_OPMC_set_default_customer_designation = $wc_setting['WC_OPMC_set_default_customer_designation'];

			if (empty($billing_company) || 'yes' == $WC_OPMC_set_default_customer_designation) {
				$params['IsIndividual'] = true;
			} else {
				$params['IsIndividual'] = false;
				$params['CompanyName'] = $billing_company;
				$params['ContactName'] = $first_name . ' ' . $last_name;
			}

			$this->create_wc_log('Post data for create customer.');
			$this->create_wc_log(print_r($params, true));

			$resp = $this->remote_post_json('/Contact/Customer?returnBody=true', $params);

			if (null !== $resp && false != $resp) {
				$this->create_wc_log('[Customer Export] [Success] [Create customer successfully]');
				return $resp;
			}
			$this->create_wc_log('[Customer Export] [Error] [Create customer failed]');
			return null;
		}


		/**
		 * Update the address of a customer based on their information in a WooCommerce Order
		 *
		 * TODO: finish implemention - issues with MYOB API for update
		 */
		public function update_customer_address($order_id, $customer_record)
		{

			return null;

			$this->create_wc_log('Updating customer address');

			if (null === $order_id || empty($customer_record)) {
				$this->create_wc_log('[Customer Export] [Error] [Input is null on update customer]');
				return null;
			}

			$order = new WC_Order($order_id);

			$displayId = $this->customer_id_prefix . $order->get_customer_id();

			$row_version = $customer_record->RowVersion + 1;

			$display_id = $this->customer_id_prefix . $order->get_customer_id();

			$customer_address = array(
				array(
					'Location' => 1,
					'Street' => $order->get_billing_address_1(),
					'City' => $order->get_billing_city(),
					'State' => $order->get_billing_state(),
					'PostCode' => $order->get_billing_postcode(),
					'Country' => $order->get_billing_country(),
					'Email' => $order->get_billing_email(),
					'Phone1' => $order->get_billing_phone(),
					'ContactName' => $customer_record->FirstName . ' ' . $customer_record->LastName,
				),
			);

			/**
			 * Filter to modify the customer's address in MYOB.
			 *
			 * @since 1.0
			 *
			 * @param array $customer_address - The original address.
			 */
			$customer_address = apply_filters('wc_myob_alter_customer_address', $customer_address);

			$params = array(
				'UID' => $customer_record->UID,
				'DisplayID' => $customer_record->DisplayID,
				'LastName' => $customer_record->LastName,
				'FirstName' => $customer_record->FirstName,
				'SellingDetails' => array(
					'Terms' => array(
						'PaymentIsDue' => 'PrePaid',
					),
					'TaxCode' => array(
						'UID' => $this->tax_code_new_product,
					),
					'FreightTaxCode' => array(
						'UID' => $this->freight_tax_code,
					),
				),
				'RowVersion' => 1,
				'Addresses' => $customer_address,
			);

			// For customer designation type

			$wc_setting = get_option('woocommerce_MYOB_integrations_settings');

			$WC_OPMC_set_default_customer_designation = $wc_setting['WC_OPMC_set_default_customer_designation'];
			if (!empty($WC_OPMC_set_default_customer_designation) && 'yes' == $WC_OPMC_set_default_customer_designation) {

				$params['IsIndividual'] = true;

			} else {
				$params['IsIndividual'] = false;
				$params['CompanyName'] = $first_name . ' ' . $last_name;
			} //

			$this->create_wc_log('Post data for update customer.');
			$this->create_wc_log(print_r($params, true));

			$resp = $this->remote_put_json('/Contact/Customer', $params);

			if (null !== $resp && false != $resp) {
				$this->create_wc_log('[Customer Export] [Success] [Updating customer successfully]');
				return $resp->UID;
			}

			$this->create_wc_log('[Customer Export] [Error] [Updating customer failed]');
			return null;
		}


		/**
		 * Create a new product in MYOB based on a line item in a WooCommerce order
		 *
		 */
		public function create_erp_inventory_item($order_item)
		{
			$this->create_wc_log('create_erp_inventory_item()');

			$product = $order_item->get_product();
			$productId = $product->get_id();
			$name = $product->get_name();
			$sku = $product->get_sku();
			$price = $product->get_regular_price();
			$qty = $product->get_stock_quantity();
			$cartQty = $order_item['qty'];
			$myob_product = array(
				'Number' => $sku,
				'Name' => $name,
				'IsActive' => 'true',
				'IsSold' => 'true',
				'IncomeAccount' => array(
					'UID' => $this->income_account,
				),
				'SellingDetails' => array(
					'BaseSellingPrice' => $price,
					'SellingUnitOfMeasure' => null,
					'ItemsPerSellingUnit' => null,
					'IsTaxInclusive' => true,
					'CalculateSalesTaxOn' => 'ActualSellingPrice',
					'TaxCode' => array(
						'UID' => $this->tax_code_new_product,
					),
				),
			);

			/* if stock is selected to be managed in Woo, the item will be created as
			 * inventoried in MYOB.    Note that you cannot convert a non-inventoried item
			 * to inventories in MYOB once it has been created.
			 */
			if ($product->managing_stock()) {

				if (empty($this->cogs_account) || empty($this->asset_account)) {
					$this->create_wc_log('[Inventory Export] [Error] [Cost of Sales or Asset Accounts not set. It must be set for inventoried product]');
				}

				$myob_product['IsInventoried'] = 'true';

				$myob_product['CostOfSalesAccount'] = array('UID' => $this->cogs_account);

				$myob_product['AssetAccount'] = array('UID' => $this->asset_account);

				if ($qty < 1) {
					$qty = 1;
				} else {
					$qty = $qty + $cartQty;
				}
				$myob_product['QuantityOnHand'] = $qty;
				$myob_product['QuantityAvailable'] = $qty;
			}
			$this->create_wc_log('Create product here is the cart qty' . $cartQty);
			$this->create_wc_log('Post data for create product');
			$this->create_wc_log(print_r($myob_product, true));

			$res = $this->remote_post_json('/Inventory/Item', $myob_product);

			$item = $this->get_erp_inventory_items($sku);

			$product_uid = $item[0]->UID;

			/*
			 *  Adjust inventory
			 */
			if ($product->managing_stock()) {
				$query = array(
					'Date' => gmdate('Y-m-d') . 'T' . gmdate('H:i:s'),
					'Memo' => 'Created by WooCommerce',
					'Lines' => array(
						array(
							'Quantity' => $qty,
							'Item' => array(
								'UID' => $product_uid,
							),
							'Account' => array(
								'UID' => $this->asset_account,
							),
						),
					),
				);
				$this->create_wc_log('Post data for Adjust inventory');
				$this->create_wc_log(print_r($query, true));

				$res = $this->remote_post_json('/Inventory/Adjustment', $query);

			}

			// TODO handle result codes
		}


		/**
		 * Fetch MYOB account right Inventory Items
		 *
		 * @param $skus - Array of SKUS to retrieve, or string for single SKU.  If null will
		 *                return a list of all skus in the MYOB system.
		 *
		 * @return an array of product records from MYOB
		 */
		public function get_erp_inventory_items($skus = null)
		{
			$this->create_wc_log('get_erp_inventory_items()');

			$this->create_wc_log('Value of skus argument: ' . print_r($skus, 1));

			$product_records = array();

			// $items = null;
			$params = null;


			if (null !== $skus) {

				// Build API request filter from SKU(s).
				if (is_array($skus)) {
					$filter = 'Number eq ' . "'" . $skus[0] . "'";

					$i = 0;
					foreach ($skus as $sku) {
						if (0 != $i) {
							$filter .= ' or Number eq ' . "'" . $sku . "'";
						}
						$i++;
					}
				} else {
					$filter = 'Number eq ' . "'" . $skus . "'";
				}

				if ('' != $filter) {
					$params = array('$filter' => urlencode($filter));
				}

				$items[] = $this->remote_get_json($this->full_endpoint . '/Inventory/Item', $params);

				$product_records = $items[0]->Items;

			} else {
				// Get all the products listed in WooCommerce.
				$retrieved_skus = null;

				$args = array('post_type' => 'product', 'posts_per_page' => -1);
				$loop = new WP_Query($args);

				// Form SKU list.
				if ($loop->have_posts()) {
					while ($loop->have_posts()) {
						$loop->the_post();
						$product = wc_get_product();
						$sku = $product->get_sku();
						if (null != $sku) {
							$retrieved_skus[] = $sku;
						}
					}
				} else {

					$retrieved_skus = array();
				}

				if (null !== $retrieved_skus) {

					$woo_sku_count = count($retrieved_skus);
					$batch_size = 60;

					if ($woo_sku_count > $batch_size) {

						$woo_item_devide_sku = array_chunk($retrieved_skus, $batch_size, true);
						$chunkcount = count($woo_item_devide_sku);
						$j = 0;

						// foreach ($woo_item_devide_sku as $keys => $skus) {

						// 	$first_skus = reset($skus);
						// 	$filter = 'Number eq ' . "'" . $first_skus . "'";

						// 	$i = 0;
						// 	foreach ( $skus as $sku ) {
						// 		if ( 0 != $i ) {
						// 			$filter .= ' or Number eq ' . "'" . $sku . "'";
						// 		}
						// 		$i++;
						// 	}

						// 	if ('' != $filter) {

						// 		$params = array( '$filter' => urlencode($filter) );
						// 	}

						// 	$items = $this->remote_get_json($this->full_endpoint . '/Inventory/Item', $params);
						// 	$itemsarray = json_decode(json_encode($items), true);
						//     // error_log($items);

						// 	foreach ($itemsarray['Items'] as $item) {
						// 		$product_records[] = $item;
						// 	}

						// 	$j++;

						// } //foreach ($woo_item_devide_sku as $keys => $skus)
						foreach ($woo_item_devide_sku as $keys => $skus) {
							try {
								$first_skus = reset($skus);
								$filter = 'Number eq ' . "'" . $first_skus . "'";

								$i = 0;
								foreach ($skus as $sku) {
									if (0 != $i) {
										$filter .= ' or Number eq ' . "'" . $sku . "'";
									}
									$i++;
								}

								if ('' != $filter) {
									$params = array('$filter' => urlencode($filter));
								}

								$items = $this->remote_get_json($this->full_endpoint . '/Inventory/Item', $params);

								if (is_null($items)) {
									throw new Exception("API returned null response for SKU filter: $filter");
								}

								$itemsarray = json_decode(json_encode($items), true);

								if (!isset($itemsarray['Items']) || !is_array($itemsarray['Items'])) {
									throw new Exception("Invalid response structure for SKU filter: $filter");
								}

								foreach ($itemsarray['Items'] as $item) {
									$product_records[] = $item;
								}

							} catch (Exception $e) {
								// Log the error for debugging purposes
								error_log("Error processing SKUs: " . $e->getMessage());
								error_log("Error occurred for SKU group: " . json_encode($skus));
							}
						}

					} else {

						if (is_array($retrieved_skus)) {

							$filter = 'Number eq ' . "'" . $retrieved_skus[0] . "'";

							$i = 0;
							foreach ($retrieved_skus as $sku) {
								if (0 != $i) {
									$filter .= ' or Number eq ' . "'" . $sku . "'";
								}
								$i++;
							}

						} else {

							$filter = 'Number eq ' . "'" . $skus . "'";
						}

						if ('' != $filter) {
							$params = array('$filter' => urlencode($filter));
						}

						$items = $this->remote_get_json($this->full_endpoint . '/Inventory/Item', $params);
						$itemsarray = json_decode(json_encode($items), true);

						foreach ($itemsarray['Items'] as $item) {
							$product_records[] = $item;
						}
					}

				} // if (null !== $retrieved_skus)

			} // else part of if ( null !== $skus ) 

			if (null != $product_records) {
				if (0 === count($product_records)) {
					return null;
				}

				return $product_records;

			} else {
				return null;
			}
		}



		/**
		 * Return a single MYOB inventory item from an array of MYOB inventory items as returned by
		 * the get_erp_inventory_items function
		 *
		 * @param $sku - the SKU to search for in the result set
		 * @param $myob_items - the result set to search
		 *
		 * @return MYOB inventory item object if found
		 * @return null if not found
		 */
		private function get_myob_item_from_sku($sku, $myob_items)
		{
			if (null != $myob_items) {
				foreach ($myob_items as $x) {
					if ($x['Number'] == $sku) {
						return $x;
					}
				}
			}
			return null;
		}

		/**
		 * Ensure that all SKUs in the order exist in MYOB.   If not, then create them.
		 *
		 */
		public function verify_myob_inventory_items($order_id)
		{
			$this->create_wc_log('verify_erp_inventory_items');

			$order = new WC_Order($order_id);
			$order_items = $order->get_items();
			$item_uid = array();
			$line = array();
			$i = 0;

			//
			// Build list of skus in the Woo order
			$skus = array();

			foreach ($order_items as $item_data) {
				$product = $item_data->get_product();
				/* PLUGINS-2273 */

				if ($product->is_type('variable') && 'yes' == $this->support_variation_product) {

					foreach ($product->get_children() as $key => $variation_id) {
						$variation = wc_get_product($variation_id);
						$skus[] = $variation->get_sku();
					}
				} /* PLUGINS-2273 End */

				$sku = $product->get_sku();
				$skus[] = $sku;
			}


			// Get the product records for all the skus we assembled in the list previously
			$myob_items = $this->get_erp_inventory_items($skus);

			if (null !== $myob_items) {
				$this->create_wc_log('[Order Export] [Info] [Found ' . count($myob_items) . ' items of ' . count($order_items) . ' requested.]');
			} else {
				$this->create_wc_log('[Order Export] [Error] [Found no items of ' . count($order_items) . ' requested.]');
			}

			// iterate through the list of skus and find any that are in the Woo order
			// but are not in MYOB.  Create any of the missing items in MYOB.

			foreach ($skus as $sku) {
				$found = 0;

				if (null !== $myob_items) { // if nothing matched MYOB then don't even search
					foreach ($myob_items as $item) {
						if ($item->Number == $sku) {
							$found++;
						}
					}
				}

				if (0 == $found) {
					if ('yes' == $this->enable_product_create) {
						$this->create_wc_log("SKU $sku not found, creating item in MYOB");
						// sku not found, find it in the Woo order array and then create in MYOB
						foreach ($order_items as $key => $woo_item) {
							$product = $woo_item->get_product();
							// $this->create_wc_log($woo_item);
							/* PLUGINS-2273 */

							if ('variation' == $product->get_type()) {

								if ('yes' != $this->support_variation_product) {
									$this->create_wc_log('support_variation_product ' . $this->support_variation_product);

									continue;

								} else {

									$variable_products = new WC_Product_Variation($product);
									$this->create_wc_log($woo_item);
									if ($sku == $variable_products->get_sku()) {

										$this->create_wc_log(' variable_products ' . $variable_products->get_sku());
										// we found the data in the Woo order, create in MYOB
										$this->create_erp_inventory_item($woo_item);
									}
								}

							} else {

								if ($sku == $product->get_sku()) {
									// we found the data in the Woo order, create in MYOB
									$this->create_wc_log(' Other Product ' . $product->get_sku());

									$this->create_erp_inventory_item($woo_item);
								}
							}
							/* PLUGINS-2273 End */
						}
					}
				}
			}

			return $this->get_erp_inventory_items($skus);
		}


		// /**
		//  * Creates either a MYOB order or invoice from WooCommerce upon a completed payment.
		//  * 
		//  * @param $order_id - The id for the WooCommerce order.
		//  */ 
		// public function order_from_payment_complete_hook( $order_id) {
		//  $this->create_wc_log("ORDER WITH ID $order_id RECEIVED FROM woocommerce_payment_complete hook");
		//  $this->add_myob_order_to_queue($order_id);
		// }
		
		
		/**
		 * Checks if order should be created instead of invoice, based on payment method.
		 * 
		 * @param $order_id - The id for the WooCommerce order.
		 */
		public function is_create_order_instead_of_invoice($order_id) {
		$create_order = 'yes';
		$wc_order = wc_get_order($order_id);
		$payment_method = '';
		$payment_method_title = '';

		if ($wc_order) {
			$payment_method = strtolower(trim((string) $wc_order->get_payment_method()));
			$payment_method_title = strtolower(trim((string) $wc_order->get_payment_method_title()));

			if ('pay_later' === $payment_method || 'on account' === $payment_method_title) {
				$create_order = 'no';
			}
		}

		$this->create_wc_debug_log(
			sprintf(
				'[Checkout Decision] order_id=%d customer_id=%d payment_method=%s payment_method_title=%s final_create_order=%s',
				(int) $order_id,
				(int) ($wc_order ? $wc_order->get_customer_id() : 0),
				$payment_method !== '' ? $payment_method : 'empty',
				$payment_method_title !== '' ? $payment_method_title : 'empty',
				(string) $create_order
			)
		);

		return $create_order;
		}

		/**
		 * Checks if quote should be created for on-account payments.
		 * Even if "Create Orders Instead of Invoices" is enabled, on-account orders get quotes.
		 * 
		 * @param $order_id - The id for the WooCommerce order.
		 */
		public function should_create_quote_for_company($order_id) {
			$wc_order = wc_get_order($order_id);
			$payment_method = '';
			$payment_method_title = '';
			$should_create_quote = false;

			if ($wc_order) {
				$payment_method = strtolower(trim((string) $wc_order->get_payment_method()));
				$payment_method_title = strtolower(trim((string) $wc_order->get_payment_method_title()));
				$should_create_quote = ('pay_later' === $payment_method || 'on account' === $payment_method_title);
			}

			$this->create_wc_debug_log(
				sprintf(
					'[Quote Decision] order_id=%d customer_id=%d payment_method=%s payment_method_title=%s create_quote=%s',
					(int) $order_id,
					(int) ($wc_order ? $wc_order->get_customer_id() : 0),
					$payment_method !== '' ? $payment_method : 'empty',
					$payment_method_title !== '' ? $payment_method_title : 'empty',
					$should_create_quote ? 'yes' : 'no'
				)
			);

			return $should_create_quote;
		}


		/**
		 * Creates either a MYOB order or invoice from WooCommerce upon an order's status changing.
		 * 
		 * Checks for:
		 *     'pending' to 'on-hold'
		 *     'pending' to 'processing'.
		 * 
		 * @param $order_id   - The id for the WooCommerce order.
		 * @param $old_status - The status of the WooCommerce order before the update.
		 * @param $new_status - The status of the WooCommerce order after the update.
		 */
		public function order_from_status_transition_hook($order_id, $old_status, $new_status)
		{
			$should_create_quote = $this->should_create_quote_for_company($order_id);
			$this->create_wc_debug_log(
				sprintf(
					'[Status Transition] order_id=%d old_status=%s new_status=%s only_sync_inventory=%s create_orders_on_hold=%s create_quote=%s',
					(int) $order_id,
					(string) $old_status,
					(string) $new_status,
					(string) $this->enable_only_sync_item_inventory,
					(string) $this->create_orders_when_on_hold,
					$should_create_quote ? 'yes' : 'no'
				)
			);
			$this->create_wc_log("ORDER WITH ID $order_id RECEIVED FROM woocommerce_order_status_changed");
			$this->create_wc_log("Order $order_id status changed from $old_status to $new_status");
			$this->create_wc_log('Only sync item inventory setting is : ' . print_r($this->enable_only_sync_item_inventory, 1));
			if ('yes' != $this->enable_only_sync_item_inventory) {
				// Manual admin status changes should sync immediately.
				if ('pending' === $old_status && ('processing' === $new_status || 'completed' === $new_status)) {
					$this->create_wc_log("Manual transition $old_status -> $new_status detected for order $order_id. Syncing to MYOB now.");
					$this->place_order((int) $order_id);
					return;
				}

				if ('pending' == $old_status && 'on-hold' == $new_status && 'yes' == $this->create_orders_when_on_hold) {
					if (!is_null(WC()->cart)) {
						WC()->cart->empty_cart();
					}
					$this->add_myob_mp_order_to_queue($order_id);
				} else if ('pending' == $old_status && 'processing' == $new_status) {
					if (!is_null(WC()->cart)) {
						WC()->cart->empty_cart();
					}
					$this->add_myob_order_to_queue($order_id);
				} else if ('failed' == $old_status && 'processing' == $new_status) {
					$this->add_myob_order_to_queue($order_id);
				} else if ('on-hold' == $old_status && ('processing' == $new_status || 'completed' == $new_status)) {
					$this->create_wc_log("Order $order_id moved from on-hold to $new_status. Invoice conversion is disabled; keeping MYOB document as " . ($should_create_quote ? 'quote' : 'order') . '.');
				}
			} // if ('yes' != $this->enable_only_sync_item_inventory)
		}


		/**
		 * Function to sync an order to MYOB when the the resync_order_to_myob order action is triggered.
		 */
		public function resync_order_to_myob($wc_order)
		{
			$this->create_wc_log('Resyncing order with id: ' . $wc_order->get_id());

			$this->add_myob_order_to_queue($wc_order->get_id());
		}


		/**
		 * Takes the tax class for a single line item in WooCommerce and converts it to a MYOB tax code.
		 * Note: in WooCommerce orders, the default tax class (GST) is an empty '',
		 *       and this function is extensible to other tax codes.
		 * 
		 * @param $wc_tax_class - The tax class for a given line item within a WooCommerce order. 
		 */
		public function convert_wc_tax($wc_tax_class)
		{

			// List the MYOB tax code UIDs to map to wc_tax_class.
			$myob_tax_codes = array();
			foreach ($this->tax_codes as $code => $name) {
				$myob_tax_codes[] = $code;
			}

			// Determine MYOB tax code to return based on wc_tax_class.
			$myob_tax_code;
			if ('' == $wc_tax_class) {
				$myob_tax_code = $myob_tax_codes[5];
			} else if ('zero-rate' == $wc_tax_class) {
				$myob_tax_code = $myob_tax_codes[3];
			} else {
				$myob_tax_code = $this->tax_code_line_item;
			}
			// TODO: extend to support other tax classes.

			$this->create_wc_log("Value of wc_tax_class: $wc_tax_class");
			$this->create_wc_log("Value of myob_tax_code: $myob_tax_code");

			return $myob_tax_code;
		}



		/**
		 * Create an invoice in MYOB account right
		 * IEEE802.11 was here
		 */
		public function create_invoice($order_id, $customer_uid)
		{

			$this->create_wc_log('Only sync item inventory setting is : ' . print_r($this->enable_only_sync_item_inventory, 1));
			if ('yes' != $this->enable_only_sync_item_inventory) {
				$this->create_wc_log("MYOB_create_invoice() for $order_id $customer_uid");
				$order = wc_get_order($order_id);
				$order_items = $order->get_items();

				//$this->create_wc_log(print_r($order_items, true));

				// Ensure all line items exist in MYOB, if not, create skeleton product records
				$myob_items = $this->verify_myob_inventory_items($order_id);
				$this->create_wc_log('Printing myob_items items for create invoice');
				$this->create_wc_log(print_r($myob_items, true));

				$invoice_stock_shortages = $this->get_invoice_stock_shortages($order, $myob_items);
				if (!empty($invoice_stock_shortages)) {
					$this->create_wc_debug_log(
						'[Invoice Precheck] Insufficient MYOB stock for invoice. Shortages=' . wp_json_encode($invoice_stock_shortages),
						'warning'
					);
					throw new Opmc_Myob_Exception(
						'Inventory_InsufficientStockMultipleLocation precheck: insufficient MYOB QuantityAvailable for one or more SKUs.'
					);
				}

				$this->create_wc_log('Start creating MYOB Invoice Object.....');

				//
				// Create an invoice object to build the POST data
				//
				$invoice = new Opmc_Myob_Item_Invoice($order, $order_id, $customer_uid, $this->freight_tax_code, $myob_items);

				$this->create_wc_log('Successfully created MYOB Invoice Object!');

				$this->create_wc_log('Start adding line items to invoice.....');

				$get_invoice_type = array();
				// Add the line items to the invoice
				foreach ($order_items as $item_id => $item_data) {
					$product_id = $item_data->get_product_id();
					$get_invoice_type[] = get_post_meta($product_id, 'invoice_layout_meta_box');
				}

				$invoice_type = array_column($get_invoice_type, '0');
				$wc_settings = get_option('woocommerce_MYOB_integrations_settings');
				$default_invoice_type_setting = $wc_settings['WC_MYOB_invoice_type'];
				$this->create_wc_log('default_invoice_type_setting');
				$this->create_wc_log(print_r($default_invoice_type_setting, true));

				foreach ($order_items as $item_id => $item_data) {

					// MYOB Tax Code handling

					$myob_tax;
					$product_id = $item_data->get_product_id();
					$manual_myob_tax = get_post_meta($product_id, 'layout_product_taxcode_meta_box', true);

					if (empty($manual_myob_tax) || null == $manual_myob_tax) {
						$this->create_wc_log('No manual tax set for product: using automated myob tax assignment');
						$myob_tax = $this->convert_wc_tax($item_data->get_tax_class());
					} else {
						$this->create_wc_log('Manual tax set for product: using assigned myob tax code');
						$myob_tax = $manual_myob_tax;
					}


					if (
						(in_array('items', $invoice_type) && in_array('service', $invoice_type))
						|| (in_array('items', $invoice_type) && in_array('professional', $invoice_type))
						|| (in_array('service', $invoice_type) && in_array('professional', $invoice_type))
						|| (in_array('service', $invoice_type) && in_array('items', $invoice_type))
						|| (in_array('professional', $invoice_type) && in_array('items', $invoice_type))
						|| (in_array('professional', $invoice_type) && in_array('service', $invoice_type))
					) {

						if (!empty($default_invoice_type_setting)) {

							if ('items' == $default_invoice_type_setting) {

								$invoice->add_line_item($item_id, $item_data, $myob_tax);
							} elseif ('service' == $default_invoice_type_setting) {
								$invoice->add_line_service($item_id, $item_data, $myob_tax);

							} elseif ('professional' == $default_invoice_type_setting) {
								$invoice->add_line_professional($item_id, $item_data, $myob_tax);
							} else {
								$invoice->add_line_item($item_id, $item_data, $myob_tax);
							}
						} else {
							$invoice->add_line_item($item_id, $item_data, $myob_tax);
						}

					} elseif (in_array('items', $invoice_type)) {

						$invoice->add_line_item($item_id, $item_data, $myob_tax);

					} elseif (in_array('service', $invoice_type)) {

						$invoice->add_line_service($item_id, $item_data, $myob_tax);

					} elseif (in_array('professional', $invoice_type)) {

						$invoice->add_line_professional($item_id, $item_data, $myob_tax);

					} else {

						$invoice->add_line_item($item_id, $item_data, $myob_tax);
					}
				}

				$this->create_wc_log('Successfully added line items to invoice!');

				$invoice->set_address();

				$leave_open = true;

				if (true === $leave_open) {
					$invoice->status = 'Open';
				} else {
					$invoice->status = 'Closed';
				}

				$this->create_wc_log('Start generating invoice post data.....');
				$post_data = $invoice->generate_post_data();
				$invoice_date = $post_data['Date'];

				$this->create_wc_log(print_r($post_data, 1));
				$this->create_wc_log('Successfully generated invoice post data!');
				if (
					(in_array('items', $invoice_type) && in_array('service', $invoice_type))
					|| (in_array('items', $invoice_type) && in_array('professional', $invoice_type))
					|| (in_array('service', $invoice_type) && in_array('professional', $invoice_type))
					|| (in_array('service', $invoice_type) && in_array('items', $invoice_type))
					|| (in_array('professional', $invoice_type) && in_array('items', $invoice_type))
					|| (in_array('professional', $invoice_type) && in_array('service', $invoice_type))
				) {

					if (!empty($default_invoice_type_setting)) {

						if ('items' == $default_invoice_type_setting) {
							$apiurl = '/Sale/Invoice/Item';
							opmc_hpos_update_post_meta($order_id, 'invoice_type', 'items');
							$this->create_wc_log('default_invoice_type Calling /Sale/Invoice/Item');

						} elseif ('service' == $default_invoice_type_setting) {

							$apiurl = '/Sale/Invoice/Service';
							opmc_hpos_update_post_meta($order_id, 'invoice_type', 'service');
							$this->create_wc_log('default_invoice_type Calling /Sale/Invoice/service');

						} elseif ('professional' == $default_invoice_type_setting) {

							$apiurl = '/Sale/Invoice/Professional';
							opmc_hpos_update_post_meta($order_id, 'invoice_type', 'professional');
							$this->create_wc_log('default_invoice_type Calling /Sale/Invoice/Professional');

						} else {

							$apiurl = '/Sale/Invoice/Item';
							opmc_hpos_update_post_meta($order_id, 'invoice_type', 'items');
							$this->create_wc_log('default_invoice_type Calling /Sale/Invoice/Item');
						}
					} else {
						$apiurl = '/Sale/Invoice/Item';
						opmc_hpos_update_post_meta($order_id, 'invoice_type', 'items');
					}

				} elseif (in_array('item', $invoice_type)) {
					$apiurl = '/Sale/Invoice/Item';
					opmc_hpos_update_post_meta($order_id, 'invoice_type', 'items');
					$this->create_wc_log('Calling /Sale/Invoice/Item');

				} elseif (in_array('service', $invoice_type)) {
					$apiurl = '/Sale/Invoice/Service';
					opmc_hpos_update_post_meta($order_id, 'invoice_type', 'service');
					$this->create_wc_log('Calling /Sale/Invoice/Service');

				} elseif (in_array('professional', $invoice_type)) {
					$apiurl = '/Sale/Invoice/Professional';
					opmc_hpos_update_post_meta($order_id, 'invoice_type', 'professional');
					$this->create_wc_log('Calling /Sale/Invoice/Professional');

				} else {
					$apiurl = '/Sale/Invoice/Item';
					opmc_hpos_update_post_meta($order_id, 'invoice_type', 'items');
					$this->create_wc_log('Calling /Sale/Invoice/Item');
				}

				// If the payment method is on account, create quote instead of invoice
				if ($this->should_create_quote_for_company($order_id)) {
					$apiurl = str_replace('/Sale/Invoice/', '/Sale/Quote/', $apiurl);
					$this->create_wc_log('Payment method is on account - Converting invoice endpoint to quote: ' . $apiurl);
				}

				$response = $this->remote_post_json($apiurl, $post_data);

				$this->create_wc_log(print_r($response, true));

				if (!is_null(WC()->cart)) {
					WC()->cart->empty_cart();
				}

				$config = get_option('woocommerce_MYOB_integrations_settings');
				$this->create_closed_invoices = isset($config['WC_OPMC_create_closed_invoices']) ? $config['WC_OPMC_create_closed_invoices'] : 'no';

				$this->create_wc_log('Value of create_closed_invoices: ' . $this->create_closed_invoices);

				//$this->create_wc_log('POST REQUEST BODY (OPMC Invoice)');
				//$this->create_wc_log(print_r($invoice, true));

				// Fetch created invoice with invoice number
				$invoice_data = $this->fetch_invoice($order_id, $invoice_date);
				if (!is_null($invoice_data) && isset($invoice_data->Number)) {
					// Ensure the Number is a string or convert it appropriately

					$order->add_order_note('Invoice successfully synced and fetched from MYOB. Invoice Number: ' . $order_id);
					$this->create_wc_log('Invoice successfully synced and fetched from MYOB. Invoice Number: ' . $order_id);
				} else {
					// If fetching the invoice failed, add a failure note
					$order->add_order_note('Failed to fetch invoice from MYOB.');
				}

				if (null != $invoice_data) {
					if ('yes' == $this->create_closed_invoices) {
						$this->create_customer_payment($order_id, $invoice_data);
					}
				}

			} // if ('yes' != $this->enable_only_sync_item_inventory
		}


		/**
		 * Create an MYOB order
		 */
		public function create_order($order_id, $customer_uid)
		{
			$wc_order = wc_get_order($order_id);
			$customer_id = $wc_order ? $wc_order->get_customer_id() : 0;
			$is_company = $this->should_create_quote_for_company($order_id);
			$this->set_order_debug_context($order_id, 'create_order');
			$this->write_order_debug_log('Starting MYOB order creation.');
			
			$this->create_wc_debug_log(
				sprintf(
					'[Create Order Start] order_id=%d customer_id=%d customer_uid=%s is_company=%s',
					(int) $order_id,
					(int) $customer_id,
					$customer_uid ? 'set' : 'empty',
					$is_company ? 'yes' : 'no'
				)
			);
			
			$this->create_wc_log("MYOB_create_order() for order_id: $order_id and customer_uid: $customer_uid");
			$this->create_wc_log('Only sync item inventory setting is : ' . print_r($this->enable_only_sync_item_inventory, 1));
			if ('yes' != $this->enable_only_sync_item_inventory) {

				$wc_order = wc_get_order($order_id);
				$order_items = $wc_order->get_items();


				// Ensure all line items exist in MYOB, if not, create skeleton product records
				$myob_items = $this->verify_myob_inventory_items($order_id);

				$this->create_wc_log('Start creating new Opmc_Myob_Item_Order object.....');


				// Create an order object to build the POST data
				$myob_order = new Opmc_Myob_Item_Order($wc_order, $order_id, $customer_uid, $this->freight_tax_code, $myob_items);

				//              $this->create_wc_log('Successfully Created new Opmc_Myob_Item_Order object!');
				$this->create_wc_log('[Order Export] [Start] [Order #' . print_r($order_id, 1) . ' export has been started.]');

				$this->create_wc_log('Start adding line items to Opmc_Myob_Item_Order object.....');

				$get_invoice_type = array();
				// Add the line items to the invoice
				foreach ($order_items as $item_id => $item_data) {
					$product_id = $item_data->get_product_id();
					$get_invoice_type[] = get_post_meta($product_id, 'invoice_layout_meta_box');
				}

				$invoice_type = array_column($get_invoice_type, '0');
				$wc_settings = get_option('woocommerce_MYOB_integrations_settings');
				$default_invoice_type_setting = $wc_settings['WC_MYOB_invoice_type'];
				$this->create_wc_log('default_invoice_type_setting');
				$this->create_wc_log($default_invoice_type_setting);

				foreach ($order_items as $item_id => $item_data) {

					// MYOB Tax Code handling

					$myob_tax;
					$product_id = $item_data->get_product_id();
					$manual_myob_tax = get_post_meta($product_id, 'layout_product_taxcode_meta_box', true);

					if (empty($manual_myob_tax) || null == $manual_myob_tax) {
						$this->create_wc_log('No manual tax set for product: using automated myob tax assignment');
						$myob_tax = $this->convert_wc_tax($item_data->get_tax_class());
					} else {
						$this->create_wc_log('Manual tax set for product: using assigned myob tax code');
						$myob_tax = $manual_myob_tax;
					}


					if (
						(in_array('items', $invoice_type) && in_array('service', $invoice_type))
						|| (in_array('items', $invoice_type) && in_array('professional', $invoice_type))
						|| (in_array('service', $invoice_type) && in_array('professional', $invoice_type))
						|| (in_array('service', $invoice_type) && in_array('items', $invoice_type))
						|| (in_array('professional', $invoice_type) && in_array('items', $invoice_type))
						|| (in_array('professional', $invoice_type) && in_array('service', $invoice_type))
					) {

						if (!empty($default_invoice_type_setting)) {

							if ('items' == $default_invoice_type_setting) {

								$myob_order->add_line_item($item_id, $item_data, $myob_tax);
							} elseif ('service' == $default_invoice_type_setting) {
								$myob_order->add_line_service($item_id, $item_data, $myob_tax);

							} elseif ('professional' == $default_invoice_type_setting) {
								$myob_order->add_line_professional($item_id, $item_data, $myob_tax);
							} else {
								$myob_order->add_line_item($item_id, $item_data, $myob_tax);
							}
						} else {
							$myob_order->add_line_item($item_id, $item_data, $myob_tax);
						}

					} elseif (in_array('items', $invoice_type)) {

						$myob_order->add_line_item($item_id, $item_data, $myob_tax);

					} elseif (in_array('service', $invoice_type)) {

						$myob_order->add_line_service($item_id, $item_data, $myob_tax);

					} elseif (in_array('professional', $invoice_type)) {

						$myob_order->add_line_professional($item_id, $item_data, $myob_tax);

					} else {

						$myob_order->add_line_item($item_id, $item_data, $myob_tax);
					}
				}

				$this->create_wc_log('Successfully added line items to Opmc_Myob_Item_Order object!');

				$this->create_wc_log('Start adding address to Opmc_Myob_Item_Order object.....');

				$myob_order->set_address();

				$this->create_wc_log('Successfully added address to Opmc_Myob_Item_Order object!');

				$leave_open = true;

				if (true === $leave_open) {
					$myob_order->status = 'Open';
				} else {
					$myob_order->status = 'Closed';
				}

				$this->create_wc_log('Start generating post data for Opmc_Myob_Item_Order object.....');


				$post_data = $myob_order->generate_post_data();
				$this->create_wc_log(print_r($post_data, 1));
				$this->write_order_debug_log('[Order Export] [Post Data]');
				$this->write_order_debug_log($post_data);
				$this->create_wc_log('Successfully generated invoice post data!');

				if (
					(in_array('items', $invoice_type) && in_array('service', $invoice_type))
					|| (in_array('items', $invoice_type) && in_array('professional', $invoice_type))
					|| (in_array('service', $invoice_type) && in_array('professional', $invoice_type))
					|| (in_array('service', $invoice_type) && in_array('items', $invoice_type))
					|| (in_array('professional', $invoice_type) && in_array('items', $invoice_type))
					|| (in_array('professional', $invoice_type) && in_array('service', $invoice_type))
				) {

					if (!empty($default_invoice_type_setting)) {

						if ('items' == $default_invoice_type_setting) {

							$apiurl = '/Sale/Order/Item';
							$this->create_wc_log('default_invoice_type Calling /Sale/Invoice/Item');

						} elseif ('service' == $default_invoice_type_setting) {
							$apiurl = '/Sale/Order/Service';
							$this->create_wc_log('default_invoice_type Calling /Sale/Invoice/service');

						} elseif ('professional' == $default_invoice_type_setting) {
							$apiurl = '/Sale/Order/Professional';
							$this->create_wc_log('default_invoice_type Calling /Sale/Invoice/Professional');

						} else {

							$apiurl = '/Sale/Order/Item';
							$this->create_wc_log('default_invoice_type Calling /Sale/Invoice/Item');
						}
					} else {
						$apiurl = '/Sale/Order/Item';
					}

				} elseif (in_array('item', $invoice_type)) {

					$apiurl = '/Sale/Order/Item';
					$this->create_wc_log('Calling /Sale/Invoice/Item');

				} elseif (in_array('service', $invoice_type)) {

					$apiurl = '/Sale/Order/Service';
					$this->create_wc_log('Calling /Sale/Invoice/Service');

				} elseif (in_array('professional', $invoice_type)) {

					$apiurl = '/Sale/Order/Professional';
					$this->create_wc_log('Calling /Sale/Invoice/Professional');

				} else {

					$apiurl = '/Sale/Order/Item';
					$this->create_wc_log('Calling /Sale/Invoice/Item');
				}

				// If the payment method is on account, create quote instead of order
				$final_endpoint = $apiurl;
				if ($this->should_create_quote_for_company($order_id)) {
					$final_endpoint = str_replace('/Sale/Order/', '/Sale/Quote/', $apiurl);
					$this->create_wc_log('Payment method is on account - Converting order endpoint to quote: ' . $final_endpoint);
				}

				$this->create_wc_debug_log(
					sprintf(
						'[API Call] Sending %s to endpoint: %s',
						$is_company ? 'QUOTE' : 'ORDER',
						$final_endpoint
					)
				);

				$response = $this->remote_post_json($final_endpoint, $post_data);
				$this->create_wc_log('Calling /Sale/Order/Item');
				$this->create_wc_log(print_r($response, true));
				$this->write_order_debug_log('[Order Export] [POST Response]');
				$this->write_order_debug_log($response);
				
				// Debug response
				$response_status = is_object($response) && isset($response->UID) ? 'success' : 'failed';
				$response_id = is_object($response) && isset($response->UID) ? $response->UID : 'N/A';
				$this->create_wc_debug_log(
					sprintf(
						'[API Response] status=%s uid=%s',
						$response_status,
						$response_id
					)
				);

				if (!is_null(WC()->cart)) {
					WC()->cart->empty_cart();
				}

				// Fetch the created order from MYOB using the fetch_orders function
				$order_data = $this->fetch_orders($order_id);
				if (!is_null($order_data) && isset($order_data->Number)) {
				$this->save_created_order_metadata($order_id, $order_data);
				// Order/Quote creation was successful
                $doc_type = $is_company ? 'Quote' : 'Order';
                $doc_number_label = $is_company ? 'Quote Number' : 'Order Number';
                
                $customer_po_number = isset($order_data->CustomerPurchaseOrderNumber)
                	? sanitize_text_field((string) $order_data->CustomerPurchaseOrderNumber)
                	: '';
                
                $note = $doc_type . ' successfully created and fetched from MYOB. ' . $doc_number_label . ': ' . $order_data->Number;
                
                if ($customer_po_number !== '') {
                	$note .= ' and Customer PO Number: ' . $customer_po_number;
                }
                
                $wc_order->add_order_note($note);
                $this->create_wc_log($note);
				$this->create_wc_debug_log(
					sprintf(
						'[Create Order Success] order_id=%d type=%s myob_number=%s',
						(int) $order_id,
						$doc_type,
						$order_data->Number
					)
				);
			} else {
				// Order/Quote creation failed
				$doc_type = $is_company ? 'Quote' : 'Order';
				$note = 'Failed to fetch the ' . $doc_type . ' from MYOB after creation.';
				$wc_order->add_order_note($note);
				$this->create_wc_debug_log(
					sprintf(
						'[Create Order Failed] order_id=%d type=%s reason=fetch_failed',
						(int) $order_id,
						$doc_type
					),
					'error'
				);
			}

			$this->clear_order_debug_context();

		} //if ('yes' != $this->enable_only_sync_item_inventory)
	}

	/**
	 * Enqueue order for background processing.
	 */
	public function add_myob_order_to_queue($order_id)
		{
			$order = wc_get_order($order_id);
			$note_to_add = "This order has been resynced to the MYOB queue for processing. The result will be processed by a scheduled cron job and displayed shortly.";

			// Add the note to the order
			$order->add_order_note($note_to_add);

			// Add the order to the queue for processing
			$this->order_batch_process->push_to_queue($order_id);
			$this->order_batch_process->save()->dispatch();
		}

		/**
		 * Enqueue order for background processing from manual payment (mp).
		 */
		public function add_myob_mp_order_to_queue($order_id)
		{
			$this->mp_order_batch_process->push_to_queue($order_id);
			$this->mp_order_batch_process->save()->dispatch();
		}



		public function create_order_for_on_hold_orders($order_id)
		{
			$this->create_wc_log('create_order_for_on_hold_orders()');
			try {
				// Find the customer and create if not found
				$customer = $this->get_erp_customer($order_id);
				$this->create_wc_log('get_erp_customer');

				if (null === $customer || null === $customer->UID) {
					$customer = $this->create_customer($order_id);
					$customer_uid = $customer->UID;
				} else {
					$this->update_customer_address($order_id, $customer);
				}

				if (null !== $customer->UID) {
					try {

						$order = $this->create_order($order_id, $customer->UID);

					} catch (Opmc_Myob_Exception $e) {

						$order = new WC_Order($order_id);
						$note = '<span>Unable to create invoice with error.</span>
								<span class="moretext"> ' . $e->getMessage() . ' 
								</span>
								<span class="moreless-button">Read more</span>';
						$order->add_order_note($note);
						$this->create_wc_log('add_order_note: ' . $note);
						$this->send_text_mail_to_admin($order_id);

					} catch (Throwable $e) {

						$order = new WC_Order($order_id);
						$note = '<span>Unable to create invoice with error.</span>
								<span class="moretext"> ' . $e->getMessage() . ' 
								</span>
								<span class="moreless-button">Read more</span>';
						$order->add_order_note($note);
						$this->create_wc_log('add_order_note: ' . $note);
						$this->send_text_mail_to_admin($order_id);

					}
				} else {
					$this->create_order_note($order_id, 'Customer UID Not Found');
				}

			} catch (Opmc_Myob_Exception $e) {
				$this->create_order_note($order_id, $e->getMessage());
			} catch (Throwable $e) {
				$this->create_order_note($order_id, $e->getMessage());
			}
		}



		/**
		 * Place the order in MYOB.   Will create a new customer record if the customer (identified by email address)
		 * is not in MYOB.
		 */
		public function place_order($order_id, $has_manual_payment = false)
		{
			$this->set_order_debug_context($order_id, 'place_order');
			$this->write_order_debug_log('Starting place_order().');

			$this->create_order_instead_of_invoice = 'yes';

			$this->create_wc_log("Creating order $order_id");
			$this->create_wc_debug_log(
				sprintf(
					'[Place Order Start] order_id=%s manual_payment=%s mode=order_or_quote_only',
					is_scalar($order_id) ? (string) $order_id : gettype($order_id),
					$has_manual_payment ? 'yes' : 'no'
				)
			);
			if (is_int($order_id) == false) {
				if (get_class($order_id) == 'WC_Subscription') {

					$this->create_wc_log('Subscription order detected');
					/*
					The action that calls this function when a subscription renewal payment is made has 2 parameters: WC_subscription and WC_order so it will call this twice.
					The subscription input for this order causes it to crash while the order input will work fine.
					As a result if a WC_subscription is detected just end the function and let the WC_order function call do allthe work.
					*/
					$this->write_order_debug_log('Subscription object detected, skipping this invocation.');
					$this->clear_order_debug_context();
					return;
				}
			}

			$wc_order_for_status = wc_get_order($order_id);
			$current_status = $wc_order_for_status ? $wc_order_for_status->get_status() : '';
			if ('pending' === $current_status) {
				$this->create_wc_log("Skipping MYOB sync for order $order_id because status is pending.");
				$this->create_wc_debug_log(
					sprintf(
						'[Place Order Skipped] order_id=%d reason=pending_status',
						(int) $order_id
					)
				);
				$this->write_order_debug_log('Order skipped because status is pending.');
				$this->clear_order_debug_context();
				return;
			}

			try {

				// Find the customer and create if not found
				$customer = $this->get_erp_customer($order_id);
				$this->create_wc_log('get_erp_customer');

				if (null === $customer || null === $customer->UID) {
					$customer = $this->create_customer($order_id);
					$customer_uid = $customer->UID;
				} else {
					$this->update_customer_address($order_id, $customer);
				}

				// create the invoice
				if (null !== $customer->UID) {

					try {
						// Always create orders (or quotes for on-account payments), never invoices
						$wc_order = wc_get_order($order_id);
						$customer_id = $wc_order ? $wc_order->get_customer_id() : 0;
						$is_company = $this->should_create_quote_for_company($order_id);
						$this->create_wc_debug_log(
							sprintf(
								'[Place Order Action] Creating MYOB %s for Woo order #%d (customer_id=%d)',
								$is_company ? 'QUOTE' : 'ORDER',
								(int) $order_id,
								(int) $customer_id
							)
						);
						$order = $this->create_order($order_id, $customer->UID);
					} catch (Opmc_Myob_Exception $e) {
						$this->write_order_debug_log('[Place Order Error] ' . $e->getMessage(), 'ERROR');
						$this->create_wc_debug_log('[Place Order Error] Opmc_Myob_Exception for Woo order #' . (int) $order_id . ': ' . $e->getMessage(), 'error');
					
					// Check if this is a Job validation error
					if (strpos($e->getMessage(), 'Invalid Job Code') !== false || strpos($e->getMessage(), 'Job') !== false) {
						$this->create_wc_debug_log(
							sprintf(
								'[Job Validation Error] order_id=%d - The order contains an invalid or non-existent Job UID. Check product job codes and global job code settings.',
								(int) $order_id
							),
							'error'
						);
					}
						$order = new WC_Order($order_id);
						$note = '<span>An error has occurred when syncing your order with MYOB.</span>
							<span class="moretext"> ' . $e->getMessage() . ' 
							</span>
							<span class="moreless-button">Read more</span>';
						$this->create_wc_log('[Order Export] [Error] [An error has occurred when syncing your order #' . $order_id . ' with MYOB. ' . print_r($e->getMessage(), 1) . ']');

						$order->add_order_note($note);
						$this->create_wc_log('add_order_note: ' . $note);
						$this->send_text_mail_to_admin($order_id);

					} catch (Throwable $e) {
						$this->write_order_debug_log('[Place Order Throwable] ' . $e->getMessage(), 'ERROR');
						$this->create_wc_debug_log('[Place Order Error] Throwable for Woo order #' . (int) $order_id . ': ' . $e->getMessage(), 'error');

						$order = new WC_Order($order_id);
						$note = '<span>An error has occurred when syncing your order with MYOB.</span>
							<span class="moretext"> ' . $e->getMessage() . ' 
							</span>
							<span class="moreless-button">Read more</span>';
						$this->create_wc_log('[Order Export] [Error] [An error has occurred when syncing your order #' . $order_id . ' with MYOB. ' . print_r($e->getMessage(), 1) . ']');
						$order->add_order_note($note);
						$this->create_wc_log('add_order_note: ' . $note);
						$this->send_text_mail_to_admin($order_id);

					}

				} else {
					$this->create_order_note($order_id, 'Customer UID Not Found');
					$this->create_wc_debug_log(
						sprintf(
							'[Place Order Failed] order_id=%d reason=customer_uid_not_found',
							(int) $order_id
						),
						'error'
					);
				}

			} catch (Opmc_Myob_Exception $e) {
				$this->write_order_debug_log('[Place Order Exception] ' . $e->getMessage(), 'ERROR');
				$this->create_order_note($order_id, $e->getMessage());
				$this->create_wc_debug_log(
					sprintf(
						'[Place Order Exception] order_id=%d exception=%s',
						(int) $order_id,
						$e->getMessage()
					),
					'error'
				);
			} catch (Throwable $e) {
				$this->write_order_debug_log('[Place Order Throwable] ' . $e->getMessage(), 'ERROR');
				$this->create_order_note($order_id, $e->getMessage());
				$this->create_wc_debug_log(
					sprintf(
						'[Place Order Throwable] order_id=%d error=%s',
						(int) $order_id,
						$e->getMessage()
					),
					'error'
				);
			}
			
			$this->create_wc_debug_log(
				sprintf(
					'[Place Order Complete] order_id=%d',
					(int) $order_id
				)
			);
			$this->write_order_debug_log('Completed place_order().');
			$this->clear_order_debug_context();
		}


		/**
		 * Creates a note for a WooCommerce order when an error occurs and updates the order to reflect the failure.
		 */
		public function create_order_note($order_id, $error_msg)
		{
			// Retrieve the order using the wc_get_order function
			$order = wc_get_order($order_id);

			try {
				// Check if the order object is retrieved successfully
				if (!$order) {
					// Log an error message and throw an exception if the order retrieval fails
					error_log("Failed to get order for order_id: $order_id. Error message: $error_msg");
					throw new Opmc_Myob_Exception("Failed to get order for order_id: $order_id");
				}

				// Create a note with the error message
				$note = 'There was an error in this transaction: ' . $error_msg;
				$order->add_order_note($note);

				// Log the error message using the create_wc_log method
				$this->create_wc_log('[Order Export] [Error] [There was an error in this transaction: ' . print_r($error_msg, 1) . ']');

				// Optional: Update the order status to failed
				// $order->update_status( 'failed' );

				// Optional: Send an email notification to the customer about the order failure
				// $this->send_order_fail_mail_to_customer( $order );
			} catch (Opmc_Myob_Exception $e) {
				// Handle the exception: Log it using the create_wc_log method
				error_log('Exception caught: ' . $e->getMessage());
				$this->create_wc_log('[Order Export] [Exception] [Exception caught: ' . $e->getMessage() . ']');
			}
		}



		/*
		public function send_order_fail_mail_to_customer( $order ){

			$wc_emails = WC()->mailer()->get_emails(); // Get all WC_emails objects instances
			$customer_email = $order->get_billing_email(); // The customer email
			if (isset($wc_emails['WC_Email_Failed_Order'])) {
				$wc_emails['WC_Email_Failed_Order']->recipient = $customer_email;
				// Sending the email from this instance
				$wc_emails['WC_Email_Failed_Order']->trigger( $order_id );
			}
		}*/

		public function send_text_mail_to_admin($order_id)
		{
			$admin_email = get_option('admin_email');
			wp_mail($admin_email, 'Invoice Creating Failed for Order : ' . $order_id, 'Unable to Create Invoice in MYOB for Order : ' . $order_id);
		}


		public function change_order_status($order_id, $old_status, $new_status, $order)
		{
			$this->create_wc_log('changed status ');
			if ('failed' == $old_status && 'on-hold' == $new_status) {
				$order->update_status('failed');
			}
		}


		public function receive_payment($order_id)
		{
			$this->create_wc_log("mark_order_paid for $order_id");

			// apply payment to account specified by merchant
		}



		/**
		 * Update the WooCommerce stock levels and pricing based on the data in MYOB.
		 *
		 * The product SKU is used as the primary reference however, when an item is sold the
		 * UID is used as the reference.   This allows a SKU to remain the same but to point
		 * to a new/updated product in MYOB.
		 *
		 * @param $skus - The list of skus to udpate, or update all skus if null
		 */
		public function sync_inventory_data($skus = null)
		{
			$this->create_wc_log('sync_inventory_data()');
			// get all products from MYOB
			$myob_inventory = $this->get_erp_inventory_items($skus);

			$args = array('post_type' => 'product', 'posts_per_page' => -1);
			$woocommerce_MYOB_integrations_settings = get_option('woocommerce_MYOB_integrations_settings');
			$WC_MYOB_sync_type_inventory = $woocommerce_MYOB_integrations_settings['WC_MYOB_sync_type_inventory'];
			// get all the products from Woo
			$loop = new WP_Query($args);
			if ($loop->have_posts()) {

				while ($loop->have_posts()) {

					$loop->the_post();

					//$itemUID = opmc_hpos_get_post_meta(get_the_ID(),'item_uid',true);
					$product = wc_get_product();
					$this->create_wc_log('Sku =>' . $product->get_sku());

					$sku = $product->get_sku();

					/* PLUGINS-2273 */

					if ($product->is_type('variable')) {

						if ('yes' == $this->support_variation_product) {

							foreach ($product->get_children() as $key => $variation_id) {

								$variation = wc_get_product($variation_id);
								$skus = $variation->get_sku();
								$itemss = $this->get_myob_item_from_sku($skus, $myob_inventory);

								if (null != $itemss) {

									$this->create_wc_log('Item found in MYOB Item UID: ' . print_r($itemss['UID'], 1));

									if (!empty($WC_MYOB_sync_type_inventory) && 'available' == $WC_MYOB_sync_type_inventory) {

										$qtys = (int) $itemss['QuantityAvailable'];
										$this->create_wc_log('QuantityAvailable => ' . $qtys);

									} else {

										$qtys = (int) $itemss['QuantityOnHand'];
										$this->create_wc_log('QuantityOnHand => ' . $qtys);
									} //end

									if ($skus == $itemss['Number']) {
										$variation->set_stock_quantity($qtys);
										if (isset($itemss['SellingDetails']['BaseSellingPrice'])) {
											$base_price = wc_format_decimal($itemss['SellingDetails']['BaseSellingPrice'], 2);
											$variation->set_regular_price($base_price);
											// Only update active price if no sale price is set - preserve WooCommerce sale price
											if ( '' === $variation->get_sale_price() ) {
												$variation->set_price($base_price);
											}
										}
										// Sale price NOT cleared - preserved from WooCommerce

										$variation->save();
										update_post_meta($product->get_id(), 'is_synced', 'synced');
										$this->create_wc_log('SKU ' . $product->get_id() . ' found in MYOB and sync successfully.');
									}
								}
							}
						}

					} else {

						// find in MYOB array based on the SKU
						$item = $this->get_myob_item_from_sku($sku, $myob_inventory);

						if (null != $item) {
							$this->create_wc_log('[Product Sync] [Info] [Item found in MYOB Item UID: ' . print_r($item['UID'], 1) . ']');
							//$this->create_wc_log(print_r($item,1));

							// update the UID in Woo if not set - note we do not use UID
							// to identify the product in most scenarios, instead we use the SKU.
							$item_uid = get_post_meta(get_the_ID(), '_item_myob_uid', true);

							if ('' === $item_uid) {
								update_post_meta(get_the_ID(), '_item_myob_uid', $item['UID']);
								update_post_meta(get_the_ID(), '_item_myob_last_sync', time());
							}

							// Set QuantityAvailable in Woo
							//$qty = $item->QuantityAvailable;

							$this->create_wc_log('sync_type_inventory => ' . $WC_MYOB_sync_type_inventory);

							if (!empty($WC_MYOB_sync_type_inventory) && 'available' == $WC_MYOB_sync_type_inventory) {

								$qty = (int) $item['QuantityAvailable'];
								$this->create_wc_log('QuantityAvailable => ' . $qty);

							} else {

								$qty = (int) $item['QuantityOnHand'];
								$this->create_wc_log('QuantityOnHand => ' . $qty);
							} //end

							//$this->create_wc_log('Sku ' . $sku . ' Qty ' . $qty); 

							$product->set_stock_quantity($qty);
							if (isset($item['SellingDetails']['BaseSellingPrice'])) {
								$base_price = wc_format_decimal($item['SellingDetails']['BaseSellingPrice'], 2);
								$product->set_regular_price($base_price);
								// Only update active price if no sale price is set - preserve WooCommerce sale price
								if ( '' === $product->get_sale_price() ) {
									$product->set_price($base_price);
								}
							}
							// Sale price NOT cleared - preserved from WooCommerce

							update_post_meta($product->get_id(), 'is_synced', 'synced');
							$this->create_wc_log('[Product Sync] [Info] [SKU ' . $sku . ' found in MYOB and sync successfully.]');
							$product->save();
						}
					}
					/* PLUGINS-2273 End */
					// TODO
					// Set BaseSellingPrice
					// Set IncomeAccount etc
					// Set SellingDetails->TaxCode
				}
			}
		}

		/*
		 * cron_schedules
		 * check and execute cron job
		 */
		public function cron_schedule_paypal_email()
		{
			if (!wp_next_scheduled('WC_MYOB_access_cron')) {
				wp_schedule_event(time(), 'WC_MYOB_further_attempt', 'WC_MYOB_access_cron');
				$this->create_wc_log('cron_schedule_paypal_email');
			}
		}

		/*
		 * cron_schedules
		 * set interval for function to execute as cron job
		 */
		public function MYOB_cron_schedule($schedules)
		{

			if (!isset($schedules['WC_MYOB_further_attempt'])) {
				$this->log('cron_schedule');
				$schedules['WC_MYOB_further_attempt'] = array(
					'interval' => 300,
					'display' => __('MYOB Sync', 'stars-myob-accountright-connector-for-woocommerce'),
				);
			}

			return $schedules;
		}

		public function MYOB_keep_alive_transient()
		{
			//   $this->create_wc_log('Keep Alive Transient is running.....');
			//First time running transient
			if (false == get_transient('MYOB_keep_alive_transient') && false == get_transient('MYOB_keep_alive_run')) {
				$this->create_wc_log('transient has been set and should run');
				set_transient('MYOB_keep_alive_transient', 'active', 600);
				set_transient('MYOB_keep_alive_run', 1, 600);

				$connector = new Opmc_Myob_Connector();

				if ($connector) {
					$connector->refresh_token();
				}
			}

			// Wait transient to be expired then run again
			// if ( 'active' == get_transient('MYOB_keep_alive_transient') && 1 == get_transient('MYOB_keep_alive_run')) {
			// 	  $this->create_wc_log('Dont run transient, wait to be expired then run again');
			// }
		}

		public function do_insert_product_in_myob($post_id)
		{
			// if ('no' == $this->enable_product_create) {
			//  return false;
			// } PLUGINS-635

			$post_status = get_post_status($post_id);

			if ('auto-draft' == $post_status) {
				return;
			}
			/* Autosave, do nothing */
			if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
				return;
			}
			/* Check user permissions */
			if (!current_user_can('edit_post', $post_id)) {
				return;
			}
			if ('product' == get_post_type($post_id)) {
				$update = get_post_meta($post_id, '_odoo_id', true);
				if ($update) {
					$this->sync_to_myob($post_id, (int) $update);
				} else {
					$this->sync_to_myob($post_id);
				}
			}
			return;
		}

		public function sync_to_myob($post_id, $odoo_product_id = 0)
		{
			$this->create_wc_log("Call to sync_to_myob() in Opmc_Myob_Connector with arguments: $post_id $odoo_product_id");

			if ('no' == $this->enable_product_create) {
				return false;
			}

			$product = wc_get_product($post_id);
			$product_id = $item_data->get_product_id();
			$manual_income_account = get_post_meta($product_id, 'opmc_myob_product_income_account_for_tracking_sales', true);

			if (!empty($manual_income_account)) {
				$this->create_wc_log('Manual income account set for bulk product process : using assigned myob income account');
				$myob_income_account = $manual_income_account;
			} else {
				$this->create_wc_log('No manual income account set for bulk product process : using default WC settings income account');
				$myob_income_account = $this->income_account;
			}

			// if ( !$product ) {
			//  return false;
			// } PLUGINS-635

			if ($product->get_sku() == '') {
				$error_msg = '[Product Sync] [Error] [Error for product with id =>' . $product->get_id() . ' Error message : Invalid SKU - syncing blocked]';
				$this->create_wc_log($error_msg);
				return false;
			}
			$name = $product->get_name();
			$sku = $product->get_sku();
			$price = $product->get_regular_price(); // Use regular price to avoid overwriting MYOB BaseSellingPrice with a temporary sale price
			$qty = $product->get_stock_quantity();

			$myob_product = array(
				'Number' => $sku,
				'Name' => $name,
				'IsActive' => 'true',
				'IsSold' => 'true',
				'IncomeAccount' => array('UID' => $myob_income_account),
				'SellingDetails' => array(
					'BaseSellingPrice' => $price,
					'SellingUnitOfMeasure' => null,
					'ItemsPerSellingUnit' => null,
					'IsTaxInclusive' => true,
					'CalculateSalesTaxOn' => 'ActualSellingPrice',
					'TaxCode' => array(
						'UID' => $this->tax_code_new_product,
					),
				),
			);
			/* if stock is selected to be managed in Woo, the item will be created as
			 * inventoried in MYOB.    Note that you cannot convert a non-inventoried item
			 * to inventories in MYOB once it has been created.
			 */
			if ($product->managing_stock()) {

				if (empty($this->cogs_account) || empty($this->asset_account)) {
					Opmc_Logger::error('Cost of Sales and Asset Accounts must be set for inventoried product');
				}

				$myob_product['IsInventoried'] = 'true';

				$myob_product['CostOfSalesAccount'] = array('UID' => $this->cogs_account);

				$myob_product['AssetAccount'] = array('UID' => $this->asset_account);

				if ($qty < 1) {
					$qty = 1;
				}
				$myob_product['QuantityOnHand'] = $qty;
				$myob_product['QuantityAvailable'] = $qty;
			}
			$this->create_wc_log('Post data for create product');
			$this->create_wc_log(print_r($myob_product, true));

			$res = $this->remote_post_json('/Inventory/Item?returnBody=true', $myob_product);

			if (null != $res) {
				update_post_meta($post_id, '_myob_number', $res->Number);
				update_post_meta($post_id, '_myob_uid', $res->UID);
				update_post_meta($post_id, '_myob_row_version', $res->RowVersion);
			}


			$item = $this->get_erp_inventory_items($sku);

			$product_uid = $item[0]->UID;

			/*
			 *  Adjust inventory
			 */
			if ($product->managing_stock()) {
				$query = array(
					'Date' => gmdate('Y-m-d') . 'T' . gmdate('H:i:s'),
					'Memo' => 'Created by WooCommerce',
					'Lines' => array(
						array(
							'Quantity' => $qty,
							'Item' => array(
								'UID' => $product_uid,
							),
							'Account' => array(
								'UID' => $this->asset_account,
							),
						),
					),
				);
				$this->create_wc_log('Post data for Adjust inventory');
				$this->create_wc_log(print_r($query, true));


				$res = $this->remote_post_json('/Inventory/Adjustment', $query);

			}
		}

		public function create_customer_payment($order_id, $invoice)
		{

			$this->create_wc_log('Start creating customer payment...');


			$query = array(
				'PayFrom' => 'Account',
				'Account' => array(
					'UID' => $this->asset_account,
				),
				'Customer' => array(
					'UID' => $invoice->Customer->UID,
				),
				'PayeeAddress' => $invoice->Customer->Name,
				'StatementParticulars' => '',
				'PaymentNumber' => $invoice->Number,
				'Date' => $invoice->Date,
				'AmountPaid' => $invoice->BalanceDueAmount,
				'Memo' => 'Payment; ' . $invoice->Customer->Name,
				'Invoices' => array(
					array(
						'UID' => $invoice->UID,
						'AmountApplied' => $invoice->BalanceDueAmount,
						'Type' => 'Invoice',
					),
				),
				'DeliveryStatus' => 'Print',
				'ForeignCurrency' => null,

			);
			$this->create_wc_log(print_r($invoice->Customer, true));


			$this->create_wc_log('Post data for customer payment');
			$this->create_wc_log(print_r($query, true));

			$this->remote_post_json('/Sale/CustomerPayment/', $query);
		}

		public function fetch_invoice($order_id, $invoice_date)
		{
			$wc_settings = get_option('woocommerce_MYOB_integrations_settings');
			$enable_myob_invoice_number = $wc_settings['WC_OPMC_enable_myob_invoice_number'];

			if ('yes' == $enable_myob_invoice_number) {

				$filter = 'CustomerPurchaseOrderNumber eq ' . "'" . $order_id . "'";
			} else {
				$invoice_number = $this->invoice_id_prefix . $order_id;
				$filter = 'Number eq ' . "'" . $invoice_number . "'";
			}

			$params = array('$filter' => urlencode($filter));
			$invoice_type_meta = opmc_hpos_get_post_meta($order_id, 'invoice_type', true);
			if (!empty($invoice_type_meta)) {

				if ('items' == $invoice_type_meta) {

					$invoice = $this->remote_get_json($this->full_endpoint . '/Sale/Invoice/Item', $params);

				} elseif ('professional' == $invoice_type_meta) {

					$invoice = $this->remote_get_json($this->full_endpoint . '/Sale/Invoice/Professional', $params);

				} else {

					$invoice = $this->remote_get_json($this->full_endpoint . '/Sale/Invoice/Service', $params);
				}
			} else {

				$invoice = $this->remote_get_json($this->full_endpoint . '/Sale/Invoice/Item', $params);
			}


			$this->create_wc_log('GET REQUEST RESPONSE BODY (MYOB Invoice)');
			//$this->create_wc_log(print_r($invoice, true));

			$invoice_item = null;

			$this->create_wc_log('Checking GET response invoice items...');

			// Extract invoice with date matching $invoice_date when retrieving multiple invoices with the same number.
			foreach ($invoice->Items as $item) {
				$this->create_wc_log('Required Date: ' . $invoice_date . ': Item Date: ' . $item->Date);
				if ($invoice_date == $item->Date) {
					$invoice_item = $item;
					$this->create_wc_log('MATCHING INVOICE ITEM FOUND!');
				}
			}
			$this->create_wc_log('Finished checking GET response invoice items');


			if (false !== $invoice && null !== $invoice && isset($invoice->Items) && count($invoice->Items) > 0) {
				$invoice_uid = $invoice_item->UID;

				$this->create_wc_log('Customer ' . $invoice_uid . ' found.');
				$res = $invoice_item;
				return $res;
			}
			$this->create_wc_log('Customer not found.');
			return null;
		}


		public function import_product_to_myob()
		{
			global $wpdb;
			$posts = $wpdb->get_results("SELECT   wp_posts.ID FROM wp_posts  LEFT JOIN wp_postmeta ON (wp_posts.ID = wp_postmeta.post_id AND wp_postmeta.meta_key = '_myob_uid' ) WHERE 1=1  AND ( 
				wp_postmeta.post_id IS NULL
			) AND wp_posts.post_type = 'product' AND (wp_posts.post_status = 'publish' OR wp_posts.post_status = 'future' OR wp_posts.post_status = 'draft' OR wp_posts.post_status = 'pending' OR wp_posts.post_status = 'private') GROUP BY wp_posts.ID ORDER BY wp_posts.post_date DESC", 'ARRAY_A');
			$this->import_product_to_myob_process->empty_data();
			update_option('woo_product_synced_to_myob_count', 0);

			foreach ($posts as $post) {
				// print_r($post);exit;
				$this->import_product_to_myob_process->push_to_queue($post['ID']);
			}
			update_option('myob_woo_product_count', count($posts));
			// print_r($this->import_product_to_myob_process);exit;
			$this->import_product_to_myob_process->save()->dispatch();
			$this->create_wc_log('[Export Product] [start] [Product export queue started for ' . print_r(count($posts), 1) . ' products.]');
		}



		public function search_product_item($product_id)
		{
			$sku = get_post_meta($product_id, '_sku', true);
			$product = $this->get_erp_inventory_items($sku);
			if (empty($product)) {
				return false;
			} else {
				update_post_meta($product_id, '_myob_number', $product[0]->Number);
				update_post_meta($product_id, '_myob_uid', $product[0]->UID);
				update_post_meta($product_id, '_myob_row_version', $product[0]->RowVersion);
				return true;
			}
		}


		/**
		 * Determines the form of the endpoint used in the API call to MYOB when retrieving MYOB products for synchronisation with WooCommerce.
		 */
		// public function sync_products_from_myob() {
		// 	$config = get_option('woocommerce_MYOB_integrations_settings');
		// 	$limit = 25;

		// 	if (isset($config['WC_OPMC_fetch_items_frm_to_woo_batch_limit'])) {
		// 		$limit = $config['WC_OPMC_fetch_items_frm_to_woo_batch_limit'];
		// 	}

		// 	$previous_limit = get_option('myob_product_sync_limit', 25);

		// 	if ($limit !== $previous_limit) {
		// 		$next_url = false;
		// 		update_option('myob_product_sync_limit', $limit);
		// 	} else {
		// 		$next_url = get_option('myob_product_pull_next_url');
		// 	}

		// 	if (!$next_url) {
		// 		update_option('myob_product_synced_count', 0);
		// 		$not_copy_inactive_product = isset($config['WC_OPMC_do_not_copy_inactive_product_from_myob']) ? $config['WC_OPMC_do_not_copy_inactive_product_from_myob'] : '';
		// 		$filter = ($not_copy_inactive_product === 'yes') ? '&$filter= IsActive eq true' : '';
		// 		$next_url = $this->full_endpoint . '/Inventory/Item?$top=' . $limit . $filter;
		// 	}

		// 	$total_fetched = 0;
		// 	set_transient('myob_sync_in_progress', "Fetching MYOB product data... Currently fetched $total_fetched products.", 60 * 60);

		// 	while ($next_url) {
		// 		$fetched_count = $this->fetch_product_api($next_url);
		// 		$total_fetched += $fetched_count;
		// 		$next_url = get_option('myob_product_pull_next_url');

		// 		if ($limit < 500) {
		// 			usleep(500000); // 0.5-second delay
		// 		}

		// 		set_transient('myob_sync_in_progress', "Fetching MYOB product data... Currently fetched $total_fetched products.", 60 * 60);

		// 		if (!get_transient('myob_sync_in_progress')) {
		// 			$this->create_wc_log('[Product Import Queue] [Process Interrupted] [The process was interrupted.]');
		// 			break;
		// 		}
		// 	}

		// 	update_option('myob_sync_completed_notice', "MYOB product data sync completed successfully. Total products fetched: " . get_option('myob_product_count') . ". Importing of products will start shortly.");
		// 	delete_transient('myob_sync_in_progress');
		// 	$this->create_wc_log('[Product Import Queue Ended] [Process Completed] [Product data queue for importing completed successfully.]');
		// }		
		public function sync_products_from_myob()
		{
			try {
				$config = get_option('woocommerce_MYOB_integrations_settings');
				$limit = 25;

				if (isset($config['WC_OPMC_fetch_items_frm_to_woo_batch_limit'])) {
					$limit = $config['WC_OPMC_fetch_items_frm_to_woo_batch_limit'];
				}

				$previous_limit = get_option('myob_product_sync_limit', 25);

				if ($limit !== $previous_limit) {
					$next_url = false;
					update_option('myob_product_sync_limit', $limit);
				} else {
					$next_url = get_option('myob_product_pull_next_url');
				}

				if (!$next_url) {
					update_option('myob_product_synced_count', 0);
					$not_copy_inactive_product = isset($config['WC_OPMC_do_not_copy_inactive_product_from_myob']) ? $config['WC_OPMC_do_not_copy_inactive_product_from_myob'] : '';
					$filter = ($not_copy_inactive_product === 'yes') ? '&$filter= IsActive eq true' : '';
					$next_url = $this->full_endpoint . '/Inventory/Item?$top=' . $limit . $filter;
				}

				$total_fetched = 0;
				set_transient('myob_sync_in_progress', "Fetching MYOB product data... Currently fetched $total_fetched products.", 60 * 60);

				while ($next_url) {
					try {
						$fetched_count = $this->fetch_product_api($next_url);
						if ($fetched_count === null) {
							throw new Exception("Failed to fetch products from MYOB");
						}
						$total_fetched += $fetched_count;
						$next_url = get_option('myob_product_pull_next_url');

						if ($limit < 500) {
							usleep(500000); // 0.5-second delay
						}

						set_transient('myob_sync_in_progress', "Fetching MYOB product data... Currently fetched $total_fetched products.", 60 * 60);

						if (!get_transient('myob_sync_in_progress')) {
							$this->create_wc_log('[Product Import Queue] [Process Interrupted] [The process was interrupted.]');
							break;
						}
					} catch (Exception $e) {
						$this->create_wc_log('[Error in sync_products_from_myob] ' . $e->getMessage());
						// Optionally, you could break here or continue to the next iteration
						// break;
					}
				}

				if ($total_fetched > 0) {
					update_option('myob_sync_completed_notice', "MYOB product data sync completed successfully. Total products fetched: " . get_option('myob_product_count') . ". Importing of products will start shortly.");
					delete_transient('myob_sync_in_progress');
					$this->create_wc_log('[Product Import Queue Ended] [Process Completed] [Product data queue for importing completed successfully.]');
				} else {
					$this->create_wc_log('[Product Import Queue Ended] [No Products Fetched] [No products were fetched during the sync process.]');
				}

			} catch (Exception $e) {
				$this->create_wc_log('[Critical Error in sync_products_from_myob] ' . $e->getMessage());
				$this->create_wc_log('[Stack Trace] ' . $e->getTraceAsString());
			}
		}
		/**
		 * Retrieves a batch of products from MYOB to be synchronized with the WooCommerce product catalogue.
		 *
		 * @param  string $url URL to fetch the products from MYOB.
		 * @return int         The number of products fetched.
		 */
		public function fetch_product_api($url)
		{
			// $url_parts = parse_url('https://arl2.api.myob.com/accountright/28c887fd-503d-4fd1-8909-c2d4eac48f06/Inventory/Item?$top=2000&$skip=0&$filter=%20IsActive%20eq%20true');
			$url_parts = parse_url($this->full_endpoint . '/Inventory/Item?$top=2000&$skip=0&$filter=%20IsActive%20eq%20true');
			$params = isset($url_parts['query']) ? $url_parts['query'] : '';

			$this->create_wc_log('[Product Import Queue] [Fetch products data] [Fetching products from MYOB API with parameters: ' . $params . ']');

			$products = $this->remote_get_json($url);
			if (is_null($products) || !isset($products->Items)) {
				$this->create_wc_log('[Product Import Queue] [Error] [Failed to fetch products from MYOB API. URL: ' . $params . ']');
				return 0;
			}
			// echo "<pre>";
			// print_r($products);exit; 
			// echo "</pre>";

			$local_data = file_get_contents(WC_MYOB_INTEGRATION_PLUGINDIR . 'assets/json/products.json');
			$local_files = json_decode($local_data, true);

			$fetched_count = 0;

			foreach ($products->Items as $key => $item) {
				$this->product_import_process->push_to_queue($item->UID);
				$local_files[$item->UID] = $item;
				$fetched_count++;
			}

			file_put_contents(WC_MYOB_INTEGRATION_PLUGINDIR . 'assets/json/products.json', json_encode($local_files));

			$this->create_wc_log('[Product Import Queue] [Product data fetched] [Fetched ' . $fetched_count . ' products from MYOB and added to local JSON file.]');

			if (is_null($products->NextPageLink)) {
				$this->product_import_process->save()->dispatch();
				$this->create_wc_log('[Product Import Queue] [Queue completed] [Product import queue has been completed, total of ' . count($local_files) . ' products.]');
			}

			update_option('myob_product_pull_next_url', $products->NextPageLink);
			update_option('myob_product_count', count($local_files));

			return $fetched_count;
		}


		/**
		 * Display an admin notice during the MYOB product sync process.
		 */
		// Admin Notice Function
		public function show_myob_sync_notice()
		{
			static $notice_shown = false;
			if ($notice_shown) {
				return;
			}

			if (
				isset($_GET['page']) && $_GET['page'] === 'wc-settings' &&
				isset($_GET['tab']) && $_GET['tab'] === 'integration' &&
				isset($_GET['section']) && $_GET['section'] === 'myob_integrations'
			) {

				// Check if the transient is set for ongoing sync
				$notice = get_transient('myob_sync_in_progress');
				if ($notice) {
					echo '<div class="notice notice-info">';
					echo '<p>' . esc_html($notice) . '</p>';
					echo '</div>';
					$notice_shown = true;
				} else {
					$completed_notice = get_option('myob_sync_completed_notice');
					if ($completed_notice) {
						// Display the notice with a close button (dismissible)
						echo '<div class="notice notice-success is-dismissible">';
						echo '<p>' . esc_html($completed_notice) . '</p>';
						echo '</div>';
						$notice_shown = true;

						// Delete the completed notice option after it is shown
						delete_option('myob_sync_completed_notice');
					}
				}
			}
		}

		/**
		 * Retrieves image information for an MYOB product's photo, which is used when synchronising products from MYOB to WooCommerce.
		 */
		public function get_product_image_info($item_UID)
		{

			$this->create_wc_log("Call to get_product_image_info() in Opmc_Myob_Connector with arguments: $item_UID");

			return $this->remote_get_json($this->full_endpoint . '/Inventory/Item/' . $item_UID . '/Photo');
		}
		// public function get_product_metrix_info( $item_UID ) {

		// 	$this->create_wc_log("Call to get_product_image_info() in Opmc_Myob_Connector with arguments: $item_UID");

		// 	return $this->remote_get_json($this->full_endpoint . '/Inventory/ItemPriceMatrix/'. $item_UID);
		// }
		public function get_product_metrix_info($item_UID)
		{

			$this->create_wc_log("Call to get_product_image_info() in Opmc_Myob_Connector with arguments: $item_UID");
			$get_result = wp_remote_get($this->full_endpoint . '/Inventory/ItemPriceMatrix/' . $item_UID, array(
				'headers' => $this->create_headers(),
				'timeout' => $this->http_timeout,
			));

			if (is_wp_error($get_result)) {
				$this->create_wc_log('[MYOB API Request] [Error] [MYOB is_wp_error on get: ' . $get_result->get_error_message() . ']');
				$this->create_wc_log(print_r($get_result, true));

				$error_string = esc_html($get_result->get_error_message());
				$exception_message = "Oops! We had a problem processing your order.  $error_string";
				throw new Opmc_Myob_Exception(esc_html($exception_message));
			}
			if (empty($get_result)) {
				return false;
			}
			return json_decode($get_result['body']);
		}
		/* Start Automatically copy Customer Cards from MYOB to WooCommerce */
		public function get_customer_card_from_MYOB()
		{

			$myob_retrieval_limit = 4000;
			//$next_page_link = null;
			$pagination_string = '/?$top=' . $myob_retrieval_limit;
			$cust = $this->remote_get_json($this->full_endpoint . '/Contact/Customer' . $pagination_string);

			if (false !== $cust && null !== $cust && isset($cust->Items) && count($cust->Items) > 0) {

				$next_page_link = $cust->NextPageLink;
				update_option('next_page_link_cust', $next_page_link);
				$res[] = $cust->Items;
				$next_page_link = get_option('next_page_link_cust');


				while (null != $next_page_link) {

					$cust = $this->remote_get_json($next_page_link);
					$next_page_link = $cust->NextPageLink;
					update_option('next_page_link_cust', $next_page_link);
					$res[] = $cust->Items;
				}
				return $res;
			}

			return null;
		}

		public function get_customer_card_email_from_MYOB($u_email)
		{

			$filter = 'Addresses/any(x: x/Email eq ' . "'" . $u_email . "')";
			$params = array('$filter' => urlencode($filter));

			$cust = $this->remote_get_json($this->full_endpoint . '/Contact/Customer', $params);

			if (false !== $cust && null !== $cust && isset($cust->Items) && count($cust->Items) > 0) {
				$res = $cust->Items;
				return (array) $res;
			}
			return null;
		}

// 		public function create_customer_in_Woo_from_MYOB()
// 		{
// 			$connector = new Opmc_Myob_Connector();
// 			$args1 = get_users(array('role__in' => array('customer', 'subscriber')));

// 			$u_email = array();
// 			$res = $this->get_customer_card_from_MYOB();
// 			$myob_emails = array();

// 			foreach ($args1 as $user) {
// 				$u_email[] = $user->user_email;
// 			}

// 			$res_arr = (array) $res;
// 			foreach ($res_arr as $res_user) {
// 				foreach ($res_user as $res_users) {
// 					$res_arrs = (array) $res_users;

// 					if (isset($res_arrs['Addresses'][0]) && is_object($res_arrs['Addresses'][0])) {
// 						$emails = explode(';', $res_arrs['Addresses'][0]->Email);
// 						$primary_email = filter_var(trim($emails[0]), FILTER_VALIDATE_EMAIL);
// 						if ($primary_email) {
// 							$myob_emails[] = $primary_email;
// 						} else {
// 							if (isset($res_arrs["CompanyName"])) {
// 								$connector->create_wc_log('[MYOB customer import to Woo] [Failed] [Invalid email found: ' . $res_arrs["CompanyName"] . ']');
// 							} else {
// 								$connector->create_wc_log('[MYOB customer import to Woo] [Failed] [Invalid email found, no company name found]');
// 							}
// 						}
// 					} else {
// 						if (isset($res_arrs["CompanyName"])) {
// 							$connector->create_wc_log('[MYOB customer import to Woo] [Failed] [Missing address: ' . $res_arrs["CompanyName"] . ']');
// 						} else {
// 							$connector->create_wc_log('[MYOB customer import to Woo] [Failed] [Missing address, no company name found]');
// 						}
// 					}
// 				}
// 			}

// 			$myob_arr = array_diff($myob_emails, $u_email);
// 			$count_created = 0;
// 			foreach ($myob_arr as $user) {
// 				if ($count_created >= 1)
// 					break;
// 				$get_myob_cust = $this->get_customer_card_email_from_MYOB($user);

// 				if (isset($get_myob_cust)) {
// 					foreach ($get_myob_cust as $myob_cust) {
// 						$first_name = !empty($myob_cust->FirstName) ? $myob_cust->FirstName : '';
// 						$last_name = !empty($myob_cust->LastName) ? $myob_cust->LastName : '';
// 						$company_name = !empty($myob_cust->CompanyName) ? $myob_cust->CompanyName : '';

// 						if ((!empty($first_name) || !empty($company_name)) && isset($myob_cust->Addresses[0]->Email)) {

// 							if (empty($first_name) && !empty($company_name)) {
// 								$first_name = $company_name;
// 								$last_name = '';
// 							}

// 							$password = wp_generate_password(12, false);

// 							$emails = explode(';', $myob_cust->Addresses[0]->Email);
// 							$primary_email = filter_var(trim($emails[0]), FILTER_VALIDATE_EMAIL);
// 							if (!$primary_email) {
// 								$connector->create_wc_log('[MYOB customer import to Woo] [Failed] [Invalid email: ' . $emails[0] . ']');
// 								continue;
// 							}

// 							$level = '';
// 							if (isset($myob_cust->SellingDetails->ItemPriceLevel)) {
// 								$level = trim($myob_cust->SellingDetails->ItemPriceLevel);
// 							}

// 							$connector->create_wc_log('[MYOB Debug] Raw ItemPriceLevel from MYOB: ' . print_r($level, true));

// 							if (empty($level) || strtolower($level) === 'base selling price') {
// 								$role = 'customer';
// 							} else {
// 								$role = str_replace(' ', '', $level);
// 							}

// 							$connector->create_wc_log('[MYOB Debug] Cleaned role before validation: ' . $role);

// 							$wp_roles = wp_roles()->get_names();
// 							$connector->create_wc_log('[MYOB Debug] All available roles: ' . implode(', ', array_keys($wp_roles)));

// 							if (!array_key_exists($role, $wp_roles)) {
// 								$connector->create_wc_log('[MYOB Debug] Role "' . $role . '" not found, defaulting to customer');
// 								$role = 'customer';
// 							}

// 							$connector->create_wc_log('[MYOB Debug] Final role assigned: ' . $role);


// 							$user_data = array(
// 								'user_login' => $first_name,
// 								'user_pass' => $password,
// 								'user_email' => $primary_email,
// 								'role' => $role,
// 								'first_name' => $first_name,
// 								'last_name' => $last_name,
// 							);


// 							$user_id = wp_insert_user($user_data);

// 							if (!is_wp_error($user_id)) {
// 								$count_created++;
// 								break 2;
// 							}

// 							if (is_wp_error($user_id)) {
// 								$connector->create_wc_log('[MYOB customer import to Woo] [Failed] [Error creating user: ' . $user_id->get_error_message() . ']');
// 								continue;
// 							}

// 							$customer = new WC_Customer($user_id);

// 							/* Update Billing Address */
// 							if (isset($myob_cust->Addresses[0])) {
// 								$address = $myob_cust->Addresses[0];
// 								$customer->set_billing_first_name($myob_cust->FirstName);
// 								$customer->set_billing_last_name($myob_cust->LastName);
// 								$customer->set_billing_address_1($address->Street);
// 								$customer->set_billing_city($address->City);
// 								$customer->set_billing_state($address->State);
// 								$customer->set_billing_country($address->Country);
// 								$customer->set_billing_phone($address->Phone1);
// 								$customer->set_billing_postcode($address->PostCode);
// 								$customer->set_billing_email($primary_email);
// 							}

// 							/* Update Shipping Address */
// 							if (isset($myob_cust->Addresses[0])) {
// 								$address = $myob_cust->Addresses[0];
// 								$customer->set_shipping_first_name($myob_cust->FirstName);
// 								$customer->set_shipping_last_name($myob_cust->LastName);
// 								$customer->set_shipping_address_1($address->Street);
// 								$customer->set_shipping_city($address->City);
// 								$customer->set_shipping_state($address->State);
// 								$customer->set_shipping_country($address->Country);
// 								$customer->set_shipping_phone($address->Phone1);
// 								$customer->set_shipping_postcode($address->PostCode);
// 							}

// 							$connector->create_wc_log('[MYOB customer import to Woo] [Success] [MYOB customer successfully imported: ' . $myob_cust->FirstName . ' ' . $myob_cust->LastName . ']');
// 							$customer->save();
// 						} else {
// 							$connector->create_wc_log('[MYOB customer import to Woo] [Failed] [MYOB customer missing required fields, name or address]');
// 						}
// 					}
// 				} else {
// 					$connector->create_wc_log('[MYOB customer import to Woo] [Failed] [get_myob_cust is null or empty]');
// 				}
// 			}
// 		}

		/**
		 * Update an invoice in MYOB account right
		 * IEEE802.11 was here
		 */
		public function update_invoice($order_id, $customer_uid, $invoice_guid)
		{
			$this->create_wc_log("MYOB_create_invoice() for $order_id $customer_uid");
			$order = wc_get_order($order_id);
			$order_items = $order->get_items();

			$this->create_wc_log(print_r($order_items, true));

			// Ensure all line items exist in MYOB, if not, create skeleton product records
			$myob_items = $this->verify_myob_inventory_items($order_id);
			$this->create_wc_log('Printing myob_items items for create invoice');
			$this->create_wc_log(print_r($myob_items, true));
			$this->create_wc_log('Start creating MYOB Invoice Object.....');

			//
			// Create an invoice object to build the POST data
			//
			$invoice = new Opmc_Myob_Order_To_Invoice($order, $order_id, $customer_uid, $this->freight_tax_code, $myob_items, $invoice_guid);

			$this->create_wc_log('Successfully created MYOB Invoice Object!');

			$this->create_wc_log('Start adding line items to invoice.....');

			$get_invoice_type = array();
			// Add the line items to the invoice
			foreach ($order_items as $item_id => $item_data) {
				$product_id = $item_data->get_product_id();
				$get_invoice_type[] = get_post_meta($product_id, 'invoice_layout_meta_box');
			}

			$invoice_type = array_column($get_invoice_type, '0');
			$wc_settings = get_option('woocommerce_MYOB_integrations_settings');
			$default_invoice_type_setting = $wc_settings['WC_MYOB_invoice_type'];
			$this->create_wc_log('default_invoice_type_setting');
			$this->create_wc_log(print_r($default_invoice_type_setting, true));

			foreach ($order_items as $item_id => $item_data) {

				// MYOB Tax Code handling

				$myob_tax;
				$product_id = $item_data->get_product_id();
				$manual_myob_tax = get_post_meta($product_id, 'layout_product_taxcode_meta_box', true);

				if (empty($manual_myob_tax) || null == $manual_myob_tax) {
					$this->create_wc_log('No manual tax set for product: using automated myob tax assignment');
					$myob_tax = $this->convert_wc_tax($item_data->get_tax_class());
				} else {
					$this->create_wc_log('Manual tax set for product: using assigned myob tax code');
					$myob_tax = $manual_myob_tax;
				}


				if (
					(in_array('items', $invoice_type) && in_array('service', $invoice_type))
					|| (in_array('items', $invoice_type) && in_array('professional', $invoice_type))
					|| (in_array('service', $invoice_type) && in_array('professional', $invoice_type))
					|| (in_array('service', $invoice_type) && in_array('items', $invoice_type))
					|| (in_array('professional', $invoice_type) && in_array('items', $invoice_type))

					|| (in_array('professional', $invoice_type) && in_array('service', $invoice_type))
				) {

					if (!empty($default_invoice_type_setting)) {

						if ('items' == $default_invoice_type_setting) {

							$invoice->add_line_item($item_id, $item_data, $myob_tax);
						} elseif ('service' == $default_invoice_type_setting) {
							$invoice->add_line_service($item_id, $item_data, $myob_tax);

						} elseif ('professional' == $default_invoice_type_setting) {
							$invoice->add_line_professional($item_id, $item_data, $myob_tax);
						} else {
							$invoice->add_line_item($item_id, $item_data, $myob_tax);
						}
					} else {
						$invoice->add_line_item($item_id, $item_data, $myob_tax);
					}

				} elseif (in_array('items', $invoice_type)) {

					$invoice->add_line_item($item_id, $item_data, $myob_tax);

				} elseif (in_array('service', $invoice_type)) {

					$invoice->add_line_service($item_id, $item_data, $myob_tax);

				} elseif (in_array('professional', $invoice_type)) {

					$invoice->add_line_professional($item_id, $item_data, $myob_tax);

				} else {

					$invoice->add_line_item($item_id, $item_data, $myob_tax);
				}
			}

			$this->create_wc_log('Successfully added line items to invoice!');

			$invoice->set_address();

			$leave_open = true;

			if (true === $leave_open) {
				$invoice->status = 'Open';
			} else {
				$invoice->status = 'Closed';
			}

			$this->create_wc_log('Start generating invoice post data.....');
			$post_data = $invoice->generate_post_data();
			$invoice_date = $post_data['Date'];

			$this->create_wc_log(print_r($post_data, 1));
			$this->create_wc_log('Successfully generated invoice post data!');
			if (
				(in_array('items', $invoice_type) && in_array('service', $invoice_type))
				|| (in_array('items', $invoice_type) && in_array('professional', $invoice_type))
				|| (in_array('service', $invoice_type) && in_array('professional', $invoice_type))
				|| (in_array('service', $invoice_type) && in_array('items', $invoice_type))
				|| (in_array('professional', $invoice_type) && in_array('items', $invoice_type))
				|| (in_array('professional', $invoice_type) && in_array('service', $invoice_type))
			) {

				if (!empty($default_invoice_type_setting)) {

					if ('items' == $default_invoice_type_setting) {
						$apiurl = '/Sale/Invoice/Item';
						opmc_hpos_update_post_meta($order_id, 'invoice_type', 'items');
						$this->create_wc_log('default_invoice_type Calling /Sale/Invoice/Item');

					} elseif ('service' == $default_invoice_type_setting) {

						$apiurl = '/Sale/Invoice/Service';
						opmc_hpos_update_post_meta($order_id, 'invoice_type', 'service');
						$this->create_wc_log('default_invoice_type Calling /Sale/Invoice/service');

					} elseif ('professional' == $default_invoice_type_setting) {

						$apiurl = '/Sale/Invoice/Professional';
						opmc_hpos_update_post_meta($order_id, 'invoice_type', 'professional');
						$this->create_wc_log('default_invoice_type Calling /Sale/Invoice/Professional');

					} else {

						$apiurl = '/Sale/Invoice/Item';
						opmc_hpos_update_post_meta($order_id, 'invoice_type', 'items');
						$this->create_wc_log('default_invoice_type Calling /Sale/Invoice/Item');
					}
				} else {
					$apiurl = '/Sale/Invoice/Item';
					opmc_hpos_update_post_meta($order_id, 'invoice_type', 'items');
				}

			} elseif (in_array('item', $invoice_type)) {
				$apiurl = '/Sale/Invoice/Item';
				opmc_hpos_update_post_meta($order_id, 'invoice_type', 'items');
				$this->create_wc_log('Calling /Sale/Invoice/Item');

			} elseif (in_array('service', $invoice_type)) {
				$apiurl = '/Sale/Invoice/Service';
				opmc_hpos_update_post_meta($order_id, 'invoice_type', 'service');
				$this->create_wc_log('Calling /Sale/Invoice/Service');

			} elseif (in_array('professional', $invoice_type)) {
				$apiurl = '/Sale/Invoice/Professional';
				opmc_hpos_update_post_meta($order_id, 'invoice_type', 'professional');
				$this->create_wc_log('Calling /Sale/Invoice/Professional');

			} else {
				$apiurl = '/Sale/Invoice/Item';
				opmc_hpos_update_post_meta($order_id, 'invoice_type', 'items');
				$this->create_wc_log('Calling /Sale/Invoice/Item');
			}

			// If the payment method is on account, create quote instead of invoice
			if ($this->should_create_quote_for_company($order_id)) {
				$apiurl = str_replace('/Sale/Invoice/', '/Sale/Quote/', $apiurl);
				$this->create_wc_log('Payment method is on account - Converting invoice endpoint to quote: ' . $apiurl);
			}

			$response = $this->remote_post_json($apiurl, $post_data);

			$this->create_wc_log(print_r($response, true));

			$config = get_option('woocommerce_MYOB_integrations_settings');
			$this->create_closed_invoices = isset($config['WC_OPMC_create_closed_invoices']) ? $config['WC_OPMC_create_closed_invoices'] : 'no';

			$this->create_wc_log('Value of create_closed_invoices: ' . $this->create_closed_invoices);

			$this->create_wc_log('POST REQUEST BODY (OPMC Invoice)');
			$this->create_wc_log(print_r($invoice, true));

			if ('yes' == $this->create_closed_invoices) {
				//Fetch created invoice with invoice number
				$invoice_data = $this->fetch_invoice($order_id, $invoice_date);
				if (null != $invoice_data) {

					$this->create_customer_payment($order_id, $invoice_data);

				}
			}
		}

		public function conver_order_to_invoice_processing($order_id)
		{
			$this->create_wc_log('conver_order_to_invoice_processing()');

			try {
				// Find the customer and create if not found
				$customer = $this->get_erp_customer($order_id);
				$this->create_wc_log('get_erp_customer');

				if (null === $customer || null === $customer->UID) {
					$customer = $this->create_customer($order_id);
					$customer_uid = $customer->UID;
				} else {
					$this->update_customer_address($order_id, $customer);
				}

				if (null !== $customer->UID) {
					try {

						$fetch_orders = $this->fetch_orders($order_id);

						if (isset($fetch_orders)) {

							$invoice_guid = $fetch_orders->UID;

							if (!empty($invoice_guid)) {
								try {
									$res = $this->update_invoice($order_id, $customer->UID, $invoice_guid);
								} catch (Opmc_Myob_Exception $e) {

									$this->create_order_note($order_id, $e->getMessage());
								} catch (Throwable $e) {
									$this->create_order_note($order_id, $e->getMessage());
								}
							}
						}

					} catch (Opmc_Myob_Exception $e) {

						$order = new WC_Order($order_id);
						$note = '<span>Unable to create invoice with error.</span>
								<span class="moretext"> ' . $e->getMessage() . ' 
								</span>
								<span class="moreless-button">Read more</span>';
						$order->add_order_note($note);
						$this->create_wc_log('add_order_note: ' . $note);
						$this->send_text_mail_to_admin($order_id);

					} catch (Throwable $e) {

						$order = new WC_Order($order_id);
						$note = '<span>Unable to create invoice with error.</span>
								<span class="moretext"> ' . $e->getMessage() . ' 
								</span>
								<span class="moreless-button">Read more</span>';
						$order->add_order_note($note);
						$this->create_wc_log('add_order_note: ' . $note);
						$this->send_text_mail_to_admin($order_id);

					}
				} else {
					$this->create_order_note($order_id, 'Customer UID Not Found');
				}

			} catch (Opmc_Myob_Exception $e) {
				$this->create_order_note($order_id, $e->getMessage());
			} catch (Throwable $e) {
				$this->create_order_note($order_id, $e->getMessage());
			}
		}

		public function save_created_order_metadata($order_id, $order_data)
		{
			if (!is_object($order_data)) {
				return;
			}

			if (isset($order_data->Number) && '' !== (string) $order_data->Number) {
				$myob_number = sanitize_text_field((string) $order_data->Number);
				opmc_hpos_update_post_meta($order_id, '_myob_number', $myob_number);
			}

			if (isset($order_data->CustomerPurchaseOrderNumber) && '' !== (string) $order_data->CustomerPurchaseOrderNumber) {
				$customer_po_number = sanitize_text_field((string) $order_data->CustomerPurchaseOrderNumber);
				opmc_hpos_update_post_meta($order_id, 'customer_po_number', $customer_po_number);
				opmc_hpos_update_post_meta($order_id, 'CustomerPurchaseOrderNumber', $customer_po_number);
			}

			if (isset($order_data->UID) && '' !== (string) $order_data->UID) {
				opmc_hpos_update_post_meta($order_id, '_myob_uid', sanitize_text_field((string) $order_data->UID));
			}

			if (isset($order_data->RowVersion) && '' !== (string) $order_data->RowVersion) {
				opmc_hpos_update_post_meta($order_id, '_myob_row_version', sanitize_text_field((string) $order_data->RowVersion));
			}
		}

		public function backfill_order_metadata($order_id)
		{
			$order_data = $this->fetch_orders($order_id);

			if (!is_object($order_data) || !isset($order_data->Number) || '' === (string) $order_data->Number) {
				return false;
			}

			$this->save_created_order_metadata($order_id, $order_data);

			$wc_order = wc_get_order($order_id);
			if ($wc_order) {
				$wc_order->add_order_note('MYOB order metadata refreshed. Number: ' . sanitize_text_field((string) $order_data->Number));
			}

			return true;
		}

		private function fetch_sales_document_by_type($order_id, $base_endpoint, $params, $invoice_type_meta)
		{
			if (!empty($invoice_type_meta)) {
				if ('items' == $invoice_type_meta) {
					return $this->remote_get_json($this->full_endpoint . $base_endpoint . '/Item', $params);
				} elseif ('professional' == $invoice_type_meta) {
					return $this->remote_get_json($this->full_endpoint . $base_endpoint . '/Professional', $params);
				}

				return $this->remote_get_json($this->full_endpoint . $base_endpoint . '/Service', $params);
			}

			return $this->remote_get_json($this->full_endpoint . $base_endpoint . '/Item', $params);
		}

		public function fetch_orders($order_id)
		{
			$this->write_order_debug_log('Fetching created MYOB order for Woo order #' . (int) $order_id . '.');

			$wc_order = wc_get_order($order_id);
			$order_number = $wc_order ? $wc_order->get_order_number() : $order_id;
			$customer_po_number = 'WEB-' . $order_number;
			$filter = 'CustomerPurchaseOrderNumber eq ' . "'" . $customer_po_number . "'";
			$this->write_order_debug_log('Fetching MYOB order using CustomerPurchaseOrderNumber=' . $customer_po_number . '.');

			$params = array('$filter' => urlencode($filter));
			$invoice_type_meta = opmc_hpos_get_post_meta($order_id, 'invoice_type', true);
			$is_quote = $this->should_create_quote_for_company($order_id);
			$preferred_endpoint = $is_quote ? '/Sale/Quote' : '/Sale/Order';
			$fallback_endpoint = $is_quote ? '/Sale/Order' : '/Sale/Quote';
			$endpoints_to_try = array($preferred_endpoint, $fallback_endpoint);

			foreach ($endpoints_to_try as $base_endpoint) {
				$this->write_order_debug_log('Trying MYOB sales document endpoint: ' . $base_endpoint);
				$invoice = $this->fetch_sales_document_by_type($order_id, $base_endpoint, $params, $invoice_type_meta);
				$invoice_item = null;

				if (false !== $invoice && null !== $invoice && isset($invoice->Items) && count($invoice->Items) > 0) {
					foreach ($invoice->Items as $item) {
						$invoice_item = $item;
					}

					if ($invoice_item && isset($invoice_item->UID)) {
						$this->create_wc_log('Customer ' . $invoice_item->UID . ' found.');
						return $invoice_item;
					}
				}
			}

			$this->create_wc_log('Customer not found.');
			return null;
		}

		public function MYOB_reload_accounts_list_cron()
		{
			$this->create_wc_log(' Reload Accounts List Cron running.....');
			$this->create_wc_log('reload_accounts_list_cron Execute');
			$this->get_job_codes();
		}

		/**
		 * Create a new product in MYOB based on a on bulk action in a WooCommerce.
		 *
		 */
		public function create_erp_woo_inventory_item($woo_item)
		{
			$this->create_wc_log('create_erp_inventory_item()');

			$product = wc_get_product($woo_item);
			$productId = $product->get_id();
			$name = $product->get_name();
			$sku = $product->get_sku();
			$price = $product->get_regular_price();
			$qty = $product->get_stock_quantity();
			//$cartQty = $order_item['qty'];
			$manual_income_account = get_post_meta($productId, 'opmc_myob_product_income_account_for_tracking_sales', true);

			if (!empty($manual_income_account)) {
				$this->create_wc_log('Manual income account set for product: using assigned myob income account');
				$myob_income_account = $manual_income_account;
			} else {
				$this->create_wc_log('No manual income account set for product: using default WC settings income account');
				$myob_income_account = $this->income_account;
			}

			$myob_product = array(
				'Number' => $sku,
				'Name' => $name,
				'IsActive' => 'true',
				'IsSold' => 'true',
				'IncomeAccount' => array('UID' => $myob_income_account),
				'SellingDetails' => array(
					'BaseSellingPrice' => $price,
					'SellingUnitOfMeasure' => null,
					'ItemsPerSellingUnit' => null,
					'IsTaxInclusive' => true,
					'CalculateSalesTaxOn' => 'ActualSellingPrice',
					'TaxCode' => array(
						'UID' => $this->tax_code_new_product,
					),
				),
			);

			/* if stock is selected to be managed in Woo, the item will be created as
			 * inventoried in MYOB.    Note that you cannot convert a non-inventoried item
			 * to inventories in MYOB once it has been created.
			 */

			if ($product->managing_stock()) {

				if (empty($this->cogs_account) || empty($this->asset_account)) {
					$this->create_wc_log('Cost of Sales and Asset Accounts must be set for inventoried product');
				}

				$myob_product['IsInventoried'] = 'true';

				$myob_product['CostOfSalesAccount'] = array('UID' => $this->cogs_account);

				$myob_product['AssetAccount'] = array('UID' => $this->asset_account);

				if ($qty < 1) {
					$qty = 1;
				} else {
					$qty = $qty;
				}
				$myob_product['QuantityOnHand'] = $qty;
				$myob_product['QuantityAvailable'] = $qty;
			}
			$this->create_wc_log('Create product here is the cart qty' . $qty);
			$this->create_wc_log('Post data for create product');
			$this->create_wc_log(print_r($myob_product, true));

			$res = $this->remote_post_json('/Inventory/Item', $myob_product);

			$item = $this->get_erp_inventory_items($sku);

			$product_uid = $item[0]->UID;

			/*
			 *  Adjust inventory
			 */
			if ($product->managing_stock()) {
				$query = array(
					'Date' => gmdate('Y-m-d') . 'T' . gmdate('H:i:s'),
					'Memo' => 'Created by WooCommerce',
					'Lines' => array(
						array(
							'Quantity' => $qty,
							'Item' => array(
								'UID' => $product_uid,
							),
							'Account' => array(
								'UID' => $this->asset_account,
							),
						),
					),
				);
				$this->create_wc_log('Post data for Adjust inventory');
				$this->create_wc_log(print_r($query, true));

				$res = $this->remote_post_json('/Inventory/Adjustment', $query);
				if (isset($res)) {
					update_post_meta($product->get_id(), 'is_synced', 'synced');
				} else {
					delete_post_meta($product->get_id(), 'is_synced', 'synced');
				}

			}
			// TODO handle result codes
		}

		/**
		 * Ensure that all SKUs in the order exist in MYOB. If not, then create them.
		 *
		 */
		public function verify_woo_inventory_items($post_ids)
		{
			$this->create_wc_log('verify_erp_inventory_items');

			//$product = wc_get_product( $post_id );

			//
			// Build list of skus in the Woo order
			$skus = array();

			foreach ($post_ids as $post_id) {
				$product = wc_get_product($post_id);

				/* PLUGINS-2273 */
				if ($product->is_type('variable')) {

					if ('yes' == $this->support_variation_product) {

						foreach ($product->get_children() as $key => $variation_id) {
							$variation = wc_get_product($variation_id);
							$skus[] = $variation->get_sku();
						}
					}
				} else {
					$sku = $product->get_sku();

					$skus[] = $sku;
				}
				/* PLUGINS-2273 End */
			}



			// Get the product records for all the skus we assembled in the list previously
			$myob_items = $this->get_erp_inventory_items($skus);

			if (null !== $myob_items) {
				$this->create_wc_log('Requested item found.');
			} else {
				$this->create_wc_log('Requested item returning null.');
			}

			// iterate through the list of skus and find any that are in the Woo order
			// but are not in MYOB.  Create any of the missing items in MYOB.

			foreach ($skus as $sku) {
				$found = 0;

				if (null !== $myob_items) { // if nothing matched MYOB then don't even search
					foreach ($myob_items as $item) {
						if ($item->Number == $sku) {
							$found++;
						}
					}
				}

				if (0 == $found) {

					$this->create_wc_log("SKU $sku not found, creating item in MYOB");
					// sku not found, find it in the Woo order array and then create in MYOB
					foreach ($post_ids as $woo_item) {
						$product = wc_get_product($woo_item);
						/* PLUGINS-2273 */
						$this->create_wc_log($woo_item);

						if ('variation' == $product->get_type()) {

							if ('yes' == $this->support_variation_product) {

								$variable_products = new WC_Product_Variation($product);

								if ($sku == $variable_products->get_sku()) {
									// we found the data in the Woo order, create in MYOB
									$this->create_erp_woo_inventory_item($woo_item);
								}
							}

						} else {

							$this->create_wc_log($woo_item);
							if ($sku == $product->get_sku()) {
								// we found the data in the Woo order, create in MYOB

								$this->create_erp_woo_inventory_item($woo_item);
							}
						}
						/* PLUGINS-2273 End */
					}
				}
			}

			return $this->get_erp_inventory_items($skus);
		}

		/**
		 * Create a new product in MYOB based on a line item in a WooCommerce order
		 *
		 */

		public function create_dis_item_in_myob($dis_account, $asset_account, $tax_code, $cogs_account)
		{
			$this->create_wc_log('create_dis_item_in_myob() called');

			$config = get_option('woocommerce_MYOB_integrations_settings');
			$MYOB_dis_income_account = isset($dis_account) ? $dis_account : '';
			$asset_account = isset($asset_account) ? $asset_account : '';
			$tax_code_new_product = isset($tax_code) ? $tax_code : '';
			$cogs_account = isset($cogs_account) ? $cogs_account : '';

			$sku = 'DIS';
			$item = $this->get_erp_inventory_items($sku);
			if (!empty($item)) {

				$product_uid = $item[0]->UID;
				update_option('MYOB_discount_item_uids', $product_uid);
				$this->create_wc_log('DIS item already exists in MYOB. MYOB item UID: ' . $product_uid);

			} else {

				$this->create_wc_log('DIS item not found in MYOB will be proceeding for creating DIS item in MYOB.');
				$myob_product = array(
					'Number' => $sku,
					'Name' => 'Discounts',
					'IsActive' => 'true',
					'IsSold' => 'true',
					'IncomeAccount' => array(
						'UID' => $MYOB_dis_income_account,
					),
					'SellingDetails' => array(
						'BaseSellingPrice' => 0,
						'SellingUnitOfMeasure' => null,
						'ItemsPerSellingUnit' => null,
						'IsTaxInclusive' => true,
						'CalculateSalesTaxOn' => 'ActualSellingPrice',
						'TaxCode' => array(
							'UID' => $tax_code_new_product,
						),
					),
				);

				/* if stock is selected to be managed in Woo, the item will be created as
				 * inventoried in MYOB. Note that you cannot convert a non-inventoried item
				 * to inventories in MYOB once it has been created.
				 */

				if (empty($cogs_account) || empty($asset_account)) {
					$this->create_wc_log('Cost of Sales and Asset Accounts must be set for inventoried product');
				}

				$myob_product['IsInventoried'] = 'true';

				$myob_product['CostOfSalesAccount'] = array('UID' => $cogs_account);

				$myob_product['AssetAccount'] = array('UID' => $asset_account);

				$myob_product['QuantityOnHand'] = 1000;
				$myob_product['QuantityAvailable'] = 1000;

				$this->create_wc_log('Create product here is the cart qty');
				$this->create_wc_log('Post data for create product');
				$this->create_wc_log(print_r($myob_product, true));

				$res = $this->remote_post_json('/Inventory/Item', $myob_product);

				$item = $this->get_erp_inventory_items($sku);

				$product_uid = $item[0]->UID;
				update_option('MYOB_discount_item_uids', $product_uid);

				/*
				 *  Adjust inventory
				 */
				$query = array(
					'Date' => gmdate('Y-m-d') . 'T' . gmdate('H:i:s'),
					'Memo' => 'Created by WooCommerce',
					'Lines' => array(
						array(
							'Quantity' => 1000,
							'Item' => array(
								'UID' => $product_uid,
							),
							'Account' => array(
								'UID' => $asset_account,
							),
						),
					),
				);

				$this->create_wc_log('Post data for Adjust inventory');
				$this->create_wc_log(print_r($query, true));

				$res = $this->remote_post_json('/Inventory/Adjustment', $query);
			}
		}

		public function get_customer_data_from_myob()
		{

			$users = get_users(['role__in' => ['customer']]);

			foreach ($users as $user) {
				$res = $this->get_customer_with_email($user->user_email);
				$terms_array = (string) $res->SellingDetails->Terms->PaymentIsDue;
				update_user_meta($user->ID, 'myob_payment_terms', $terms_array);
				update_user_meta($user->id, 'myob_customer_id', $res->UID);
			}
		}


		/* Myriad Get Customer With ABN custom function */
		public function get_customer_with_abn($abn)
		{


			$abn = trim((string) $abn);
			if ($abn === '') {
				return null;
			}


			$filter = "SellingDetails/ABN eq '" . $abn . "'";

			if (function_exists('apply_filters')) {
				$filter = apply_filters('wc_myob_set_search_abn_filter', $filter, $abn);
			}

			$params = array('$filter' => urlencode($filter));

			$cust = $this->remote_get_json($this->full_endpoint . '/Contact/Customer', $params);

			if (false !== $cust && null !== $cust && isset($cust->Items) && count($cust->Items) > 0) {
				$cust_uid = $cust->Items[0]->UID;
				$this->create_wc_log('[Customer Import] [Info] [Customer found by ABN ' . print_r($cust_uid, true) . ']');
				return $cust->Items[0];
			}

			return null;
		}



		/* Myriad Get Invoice custom function */
		public function get_invoices_from_myob()
		{

			global $wpdb;
			$table_name = $wpdb->prefix . 'myob_invoices';

			try {
				// Ensure the table exists
				if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
					$this->create_myob_invoices_table();
				}

				$top = 500;
				$skip = 0;
				$total_count = null;

				do {
					$url = $this->full_endpoint . "/Sale/Invoice?\$top={$top}&\$skip={$skip}";
					$response = $this->remote_get_json($url);

					if (is_wp_error($response) || empty($response)) {
						error_log('Failed to fetch MYOB invoices from URL ' . $url . ': ' . print_r($response, true));
						return false;
					}

					if (is_null($total_count) && isset($response->Count)) {
						$total_count = (int) $response->Count;
					}

					$invoices = $response->Items ?? [];

					foreach ($invoices as $invoice) {

						$data = array(
							'myob_uid' => sanitize_text_field($invoice->UID ?? ''),
							'invoice_number' => sanitize_text_field($invoice->Number ?? ''),
							'date' => !empty($invoice->Date) ? date('Y-m-d', strtotime($invoice->Date)) : null,
							'due_date' => !empty($invoice->PromisedDate) ? date('Y-m-d', strtotime($invoice->PromisedDate)) : null,
							'po_number' => sanitize_text_field($invoice->CustomerPurchaseOrderNumber ?? ''),
							'amount' => floatval($invoice->TotalAmount ?? 0),
							'outstanding' => floatval($invoice->BalanceDueAmount ?? 0),
							'status' => sanitize_text_field($invoice->Status ?? ''),
							'customer_id' => sanitize_text_field($invoice->Customer->UID ?? ''),
							'customer_display_id' => sanitize_text_field($invoice->Customer->DisplayID ?? ''),
							'invoice_type' => sanitize_text_field($invoice->InvoiceType ?? ''),
						);

						$existing_invoice = $wpdb->get_row(
							$wpdb->prepare("SELECT id FROM $table_name WHERE myob_uid = %s", $data['myob_uid'])
						);

						if ($existing_invoice) {
							$wpdb->update(
								$table_name,
								$data,
								['myob_uid' => $data['myob_uid']],
								['%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s'],
								['%s']
							);
						} else {
							$wpdb->insert(
								$table_name,
								$data,
								['%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s']
							);
						}
					}

					$skip += $top;

				} while ($total_count === null || $skip < $total_count);
				return true;

			} catch (Exception $e) {
				print_r('MYOB invoice sync error: ' . $e->getMessage());
				return false;
			}
		}


		private function create_myob_invoices_table()
		{
			global $wpdb;
			$table_name = $wpdb->prefix . 'myob_invoices';
			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                myob_uid varchar(100) NOT NULL, -- Unique MYOB UID
                invoice_number varchar(50) NULL,
                date date NULL,
                due_date date NULL,
                po_number varchar(50) DEFAULT NULL,
                amount decimal(10,2) NULL,
                outstanding decimal(10,2) NULL,
                status varchar(50) NULL,
                customer_id varchar(36) NULL,
                customer_display_id varchar(50) NULL,
                invoice_type varchar(50) NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY unique_myob_uid (myob_uid)
            ) $charset_collate;";

			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		}

		//this is a function for downloading a pdf of a indicvidule invoice
		private function remote_get_pdf($uri, $params = null)
		{
			// Refresh token if expired
			if ((600 + (int) get_option('WC_MYOB_refresh_token_timestamp')) < time()) {
				$this->create_wc_log('[MYOB Connection] [Info] Token expired - refreshing');
				$this->refresh_token();
			}

			// Construct query string from params
			$param_list = '';
			if (!empty($params) && is_array($params)) {
				$param_list = '?' . http_build_query($params);
			}

			$full_url = $uri . $param_list;
			$this->create_wc_log("MYOB PDF Endpoint Called: $full_url");

			// Set headers and expect PDF
			$headers = $this->create_headers();
			$headers['Accept'] = 'application/pdf';

			// Make the request
			$response = wp_remote_get($full_url, [
				'headers' => $headers,
				'timeout' => $this->http_timeout,
			]);
			// Handle WP Error
			if (is_wp_error($response)) {
				$this->create_wc_log('[MYOB PDF Request] [Error] ' . $response->get_error_message());
				throw new Opmc_Myob_Exception('PDF fetch failed: ' . $response->get_error_message());
			}

			// Check HTTP code
			$resp_code = wp_remote_retrieve_response_code($response);
			$this->http_code = $resp_code;

			if (!in_array($resp_code, [200, 201])) {
				$this->create_wc_log("[MYOB PDF Request] [Error] HTTP Code: $resp_code");
				$this->create_wc_log(print_r($response, true));
				throw new Opmc_Myob_Exception('Failed to fetch invoice PDF. HTTP Code: ' . $resp_code);
			}

			return $response;
		}

		public function get_invoice_pdf($type, $invoice_uid)
		{
			print_r($type);
			print_r($invoice_uid);
			$uri = $this->full_endpoint . "/Sale/Invoice/{$type}/{$invoice_uid}/";
			$params = [
				'format' => 'pdf',
				'templatename' => 'Pre-Printed Invoice',
			];
			return $this->remote_get_pdf($uri, $params);
		}

	}
endif;
