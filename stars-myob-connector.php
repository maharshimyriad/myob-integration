<?php
/**
 * Plugin Name: Stars MYOB AccountRight Connector for WooCommerce
 * Plugin URI: https://starsdev.com.au/
 * Description: Connect WooCommerce to MYOB AccountRight — automatically create customers and invoices in MYOB when orders are placed.
 * Version: 1.0.0
 * Author: Aditya Dugar
 * Author URI: https://starsdev.com.au/
 * Text Domain: stars-myob-accountright-connector-for-woocommerce
 * Domain Path: /languages
 * WC tested up to: 9.3
 * WC requires at least: 2.6
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package Stars_MYOB_Connector
 */

//define ( 'OPMC_TRACE', '1' );

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}


define('WC_MYOB_INTEGRATION_INIT_VERSION', '1.0.0');
define('WC_MYOB_INTEGRATION_PLUGINURL', plugin_dir_url(__FILE__));
define('WC_MYOB_INTEGRATION_PLUGINDIR', plugin_dir_path(__FILE__));
define('HOME_URL', home_url('/'));
define('TOKEN_URI', 'https://secure.myob.com/oauth2/v1/authorize');

/**
 * Your MYOB API key (client_id).
 * Register at: https://my.myob.com.au → Developer
 * Set the redirect URI there to: WC_MYOB_INTEGRATION_PLUGINURL . 'stars-myob-cronjob.php'
 */
define('WC_MYOB_API_CLIENT_ID', '545dj2wk4r8gde2xs39mg366');

/**
 * OAuth redirect URI — where MYOB sends the user after they approve access.
 *
 * If you registered your own API key with MYOB and set the redirect URI
 * directly to your stars-myob-cronjob.php URL, no intermediary is needed.
 * Change this constant to plugin_dir_url(__FILE__) . 'stars-myob-cronjob.php'
 * and remove the nicer8.com dependency entirely.
 *
 * Current mode: using nicer8.com intermediary (original behaviour).
 * To switch: replace the value below with your own redirect URI and
 *            update WC_MYOB_API_CLIENT_ID to match your registered API key.
 */
define('WC_MYOB_API_REDIRECT_URL', 'https://myob-auth.nicer8.com/myob-authentication.html');
/**
 * Required functions.
 */
if (!function_exists('woothemes_queue_update')) {
	require_once 'woo-includes/woo-functions.php';
}

// Logger and exception classes are standalone — safe to load immediately.
require_once 'includes/opmc/class-stars-logger.php';
require_once 'includes/opmc/class-stars-myob-exception.php';
require_once 'includes/stars-myob-helper-functions.php';
require_once 'stars-hpos-compatibility-helper.php';

if (version_compare(phpversion(), '7.1', '>=')) {
	ini_set('serialize_precision', -1);
}

/**
 * Plugin updates — removed WooCommerce marketplace reference.
 */
// woothemes_queue_update( plugin_basename( __FILE__ ), ... );


//$MYOB_integrations = get_option('woocommerce_MYOB_integrations_settings');


/* handle initial activation */
register_activation_hook(__FILE__, 'opmc_install_myob_integration_plugin');

function opmc_install_myob_integration_plugin()
{
	// Activation always succeeds. If WooCommerce is missing a notice will be
	// shown on the admin dashboard — see opmc_myob_woocommerce_missing_notice().
	update_option('opmc_myob_activated_without_woocommerce', !is_plugin_active('woocommerce/woocommerce.php') ? 'yes' : 'no');
}

/**
 * Show a dismissible admin notice when WooCommerce is not active.
 */
function opmc_myob_woocommerce_missing_notice()
{
	if (is_plugin_active('woocommerce/woocommerce.php')) {
		delete_option('opmc_myob_activated_without_woocommerce');
		return;
	}
	?>
	<div class="notice notice-error">
		<p>
			<strong>Stars MYOB AccountRight Connector</strong> requires
			<a href="<?php echo esc_url(admin_url('plugin-install.php?s=woocommerce&tab=search&type=term')); ?>">WooCommerce</a>
			to be installed and active. The plugin is inactive until WooCommerce is enabled.
		</p>
	</div>
	<?php
}
add_action('admin_notices', 'opmc_myob_woocommerce_missing_notice');

/**
 * Add a "Settings" link on the Plugins page next to Activate/Deactivate.
 */
function stars_myob_plugin_action_links( $links ) {
	$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=integration&section=myob_integrations' ) ) . '">' . __( 'Settings', 'stars-myob-accountright-connector-for-woocommerce' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'stars_myob_plugin_action_links' );

/**
 * Helper — returns true only when WooCommerce is available.
 */
function opmc_myob_woocommerce_is_active()
{
	return class_exists('WooCommerce');
}

// Load debug tools (admin only).
if ( is_admin() ) {
	add_action( 'plugins_loaded', function () {
		if ( class_exists( 'WooCommerce' ) && defined( 'WC_MYOB_INTEGRATION_PLUGINDIR' ) ) {
			require_once WC_MYOB_INTEGRATION_PLUGINDIR . 'debug/stars-debug-ajax.php';
		}
	}, 5 );
}


/**
 *  Main integration class — only instantiated when WooCommerce is active.
 */
if (!class_exists('WC_MYOB_Integration')):
	class WC_MYOB_Integration
	{

		private $connector = null;
		protected static $instance = null;


		/**
		 * Implement Singleton
		 */
		public static function get_instance()
		{
			// If the single instance hasn't been set, set it now.
			if (null == self::$instance) {
				self::$instance = new self();
			}

			return self::$instance;
		}



		/**
		 * Construct the plugin.
		 */
		public function __construct()
		{
			require_once 'includes/class-stars-myob-connector.php';
			$this->connector = new Opmc_Myob_Connector();

			Opmc_Logger::trace('Constructing main class');
			//add_action( 'plugins_loaded', array( $this, 'init' ) );
			$this->init();
			
			// cron schedule setup for reauthorization to MYOB API
			// Register interval first before scheduling
			add_filter('cron_schedules', array($this, 'add_cron_interval'), 10);
			
			// called when a new order is created
			add_action('woocommerce_checkout_order_processed', array($this->connector, 'place_order'), 1, 1);

			// add_action( 'woocommerce_payment_complete', array( $this->connector,'place_order'), 1, 1  );
			// add_action( 'woocommerce_subscription_renewal_payment_complete', array( $this->connector,'place_order'), 1, 1  );

			add_action('woocommerce_order_status_changed', array($this->connector, 'order_from_status_transition_hook'), 1, 3);

			// add_action( 'woocommerce_payment_complete', array( $this->connector,'order_from_payment_complete_hook'), 1, 1  );
			add_action('woocommerce_subscription_renewal_payment_complete', array($this->connector, 'order_from_payment_complete_hook'), 1, 1);

			// Actions for adding a new order action for resyncing woocommerce orders with MYOB.
			add_action('woocommerce_order_actions', array($this, 'add_order_meta_box_actions'));
			add_action('woocommerce_order_action_resync_order_to_myob', array($this->connector, 'resync_order_to_myob'), 1, 1);

			// create a hook to init our cron settings - Schedule on init and admin_init
			add_action('init', array($this, 'init_cron_schedule'), 20);
			add_action('admin_init', array($this, 'init_cron_schedule'), 20);

			//cron job perform action
			add_action('WC_MYOB_access_cron', array($this->connector, 'refresh_token'));
			//define custom time for cron job

			$config = get_option('woocommerce_MYOB_integrations_settings');
			$stop_sync = isset($config['WC_OPMC_stop_auto_inventory_sync']) ? $config['WC_OPMC_stop_auto_inventory_sync'] : '';
			// cron to sync inventory levels daily
			if ('no' == $stop_sync) {
				add_action('WC_MYOB_sync_cron', array($this->connector, 'sync_inventory_data'));
			}

			// cron to sync matrix pricing — only register if the setting is enabled
			$enable_pricing_sync = isset( $config['WC_OPMC_enable_product_pricing_sync'] ) ? $config['WC_OPMC_enable_product_pricing_sync'] : 'yes';
			if ( 'yes' === $enable_pricing_sync ) {
				add_action('opmc_myob_single_product_sync_cron', 'opmc_myob_single_product_sync_cron');
			}

			$config = get_option('woocommerce_MYOB_integrations_settings');
			$start_sync = isset($config['WC_OPMC_auto_copy_customer_from_myob']) ? $config['WC_OPMC_auto_copy_customer_from_myob'] : '';
		
				add_action('WC_OPMC_auto_copy_customer_from_myob_cron', 'opmc_sync_all_customers_from_myob');
			

			// cron to run keep alive transient

			add_action('WC_MYOB_keep_alive_transient_cron', array($this->connector, 'MYOB_keep_alive_transient'));


			//deactivation hook
			//add_action( 'deactivated_plugin', array($this,'MYOB_plugin_deactivation') );

			add_action('add_option_woocommerce_myob_integrations_settings', array($this, 'add_myob_cron'), 10, 2);
			add_action('update_option_woocommerce_myob_integrations_settings', array($this, 'update_myob_cron'), 10, 2);

			add_action('myob_process_product_sync', array($this, 'sync_product_from_myob_to_woo'));
			add_action('admin_notices', array($this, 'admin_notices'));
			// Order Tools and Debug Tools are now embedded in the MYOB settings page tabs.
			// The standalone submenu pages have been removed.

			// Scripts
			add_action('admin_enqueue_scripts', array($this, 'settings_scripts'));

			add_action('wp_ajax_MYOB_sync_product_ajax', 'MYOB_sync_product_ajax');
			add_action('wp_ajax_MYOB_sync_invoices_ajax', 'MYOB_sync_invoices_ajax');
			add_action('wp_ajax_opmc_get_invoice_for_individual', 'opmc_get_invoice_for_individual'); // for invoice download
			add_action('wp_ajax_MYOB_sync_customers_ajax', 'MYOB_sync_customers_ajax');
			add_action('wp_ajax_MYOB_sync_customer_by_email_ajax', 'MYOB_sync_customer_by_email_ajax');
			add_action('wp_ajax_MYOB_sync_product_by_sku_ajax', 'MYOB_sync_product_by_sku_ajax');
			add_action('wp_ajax_MYOB_view_product_by_sku_ajax', 'MYOB_view_product_by_sku_ajax');
			add_action('wp_ajax_MYOB_sync_order_by_number_ajax', 'MYOB_sync_order_by_number_ajax');
			add_action('wp_ajax_MYOB_view_order_by_number_ajax', 'MYOB_view_order_by_number_ajax');
			add_action('wp_ajax_MYOB_import_product_to_myob', 'MYOB_import_product_to_myob');
			add_action('wp_ajax_MYOB_reload_accounts_list_ajax', 'MYOB_reload_accounts_list_ajax');
			add_action('wp_ajax_opmc_myob_view_debug_logs', 'opmc_myob_view_debug_logs');
			add_action('wp_ajax_opmc_myob_clear_sync_log', 'opmc_myob_clear_sync_log');
			add_action('wp_ajax_opmc_myob_get_sync_log', 'opmc_myob_get_sync_log');
			add_action('wp_ajax_stars_myob_disconnect', 'stars_myob_disconnect_ajax');

			add_action('woocommerce_order_status_changed', array($this->connector, 'change_order_status'), 10, 4);
			// add_action( 'save_post', array( $this->connector,'do_insert_product_in_myob' ), 10, 2); PLUGINS-635

			//Run keep alive transient available every 10 minutes
			add_action('admin_init', array($this->connector, 'MYOB_keep_alive_transient'));

			// cron to run keep update company file username is valid
			add_action('WC_MYOB_reload_accounts_list_cron', array($this->connector, 'MYOB_reload_accounts_list_cron'));

			//Job code scripts.
			add_action('add_meta_boxes', array($this, 'myob_meta_box'));
			add_action('save_post', array($this, 'save_myob_meta_box'), 10, 2);

			register_deactivation_hook(__FILE__, array($this, 'MYOB_plugin_deactivation'));
			add_action('admin_init', array($this, 'load_css_and_script_for_order'));

			// Add the action to handle AJAX request to reset the synced count
			add_action('wp_ajax_reset_myob_sync_count', array($this, 'reset_myob_sync_count'));

			add_action('update_option_woocommerce_myob_integrations_settings', array($this, 'MYOB_create_dis_item'), 10, 2);
			// Code for HPOS. Build Generic code fix and test it.
			add_action(
				'before_woocommerce_init',
				function () {
					if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
						\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
					}
				}
			);
			// 			add_action( 'init', [ $this, 'download_pdf' ], 1 );
			add_action('rest_api_init', function () {
				register_rest_route('invoice', '/file_download', [
					'methods'             => 'GET',
					'callback'            => 'stars_myob_download_invoice_pdf',
					'permission_callback' => '__return_true',
				]);
			});
		}



		public function download_pdf()
		{
			if (isset($_GET['pdf'])) {
				// echo 'hello';
				// echo 'invoice id'.$_GET['inv_id'];

				/* $uri = $this->full_endpoint . "/Sale/Invoice/{$type}/{$_GET['inv_id']"."}/";
				$params = [
					'format' => 'pdf',
					'templatename' => 'Pre-Printed Invoice',
				];
				return $this->remote_get_pdf($uri, $params);

				*/

				// Set the appropriate headers for file download
				header('Content-Type: application/pdf');
				header('Content-Disposition: attachment; filename="dummy.pdf"');



				// readfile("https://fhs.myriadsolutionz.com/wp-content/uploads/2025/03/dummy.pdf");
				// Output the response as a file download
				//echo $response;
				exit();
			}

			// global $ms_wc;
			// $logger = wc_get_logger();

			// $user_id = get_current_user_id();
			/*
					$account_number = get_user_meta( $user_id, 'exo_id', true );

					$endpoint = 'debtor/'.$account_number;
					$transaction_id  = isset( $_GET['tid'] ) ? sanitize_key( $_GET['tid'] ) : 1;

					if(isset($_GET['pdf'])){
						// $url = "http://49.176.253.251:8889/debtor/".$exo_debtor_id."/transaction/".$exo_transaction_id."/report"; //params : ?$filter=s.last_updated+ge+'2016-01-01'
						$pdf_endpoint = $endpoint."/transaction/".$transaction_id."/report";
						// echo $pdf_endpoint;
						$response = $ms_wc->exo_api->get_pdf($pdf_endpoint);
						// print_r($response);

						// Set the appropriate headers for file download
						header('Content-Type: application/pdf');
						header('Content-Disposition: attachment; filename="Invoice.pdf"');
						// Output the response as a file download
						echo $response;
						exit();
					}
						*/
		}


		/**
		 * Launch the discount item creting process validation when the store owner enable setting on the admin panel.
		 */
		public function MYOB_create_dis_item($old_values, $new_values)
		{


			$allows_handle_discounts = isset($new_values['allows_handle_discounts']) ? $new_values['allows_handle_discounts'] : 'no';

			$dis_account = isset($new_values['WC_MYOB_dis_account']) ? $new_values['WC_MYOB_dis_account'] : '';

			$asset_account = isset($new_values['WC_MYOB_asset_account']) ? $new_values['WC_MYOB_asset_account'] : '';
			$tax_code = isset($new_values['WC_MYOB_tax_code_new_products']) ? $new_values['WC_MYOB_tax_code_new_products'] : '';
			$cogs_account = isset($new_values['WC_MYOB_cogs_account']) ? $new_values['WC_MYOB_cogs_account'] : '';


			if ('yes' == $allows_handle_discounts && !empty($dis_account) && !empty($tax_code) && !empty($asset_account) && !empty($cogs_account)) {

				$connector = new Opmc_Myob_Connector();
				$connector->create_wc_log('MYOB_create_dis_item()');

				if ($connector) {

					$discount_item_uids = 'null' != get_option('MYOB_discount_item_uids') ? get_option('MYOB_discount_item_uids') : '';

					if (empty($discount_item_uids)) {
						$connector->create_dis_item_in_myob($dis_account, $asset_account, $tax_code, $cogs_account);
					}
				}
			}
		}

		public function load_css_and_script_for_order()
		{
			$plugin_url = plugin_dir_url(__FILE__);
			wp_enqueue_style( 'style1', $plugin_url . 'assets/css/stars-myob.css', array(), '1.6' );
			wp_enqueue_script( 'script2', $plugin_url . 'assets/js/order_page.js', array(), '1.2', true );
		}


		/**
		Enqueue required JavaScript
		*/
		public function settings_scripts()
		{
			wp_enqueue_script( 'opmc_myob_script', plugin_dir_url(__FILE__) . 'assets/js/stars-myob-scripts.js', array(), '1.3', true );
			add_action('init', 'my_script_enqueuer');
			wp_localize_script('opmc_myob_script', 'OpmcMyobScriptAjax', array(
				'ajaxurl'    => admin_url('admin-ajax.php'),
				'ajax_nonce' => wp_create_nonce('opmc_myob_security'),
			));

			// Pass connection state to JS so the "Username Valid" badge only
			// shows when there is an active token + company file ID.
			$_settings   = get_option('woocommerce_MYOB_integrations_settings', array());
			$_cf_id      = isset($_settings['WC_MYOB_company_file_id']) ? $_settings['WC_MYOB_company_file_id'] : '';
			$_cf_user    = isset($_settings['WC_MYOB_company_file_username']) ? trim($_settings['WC_MYOB_company_file_username']) : '';
			$_token      = get_option('MYOB_access_token');
			$_rf_failed  = get_option('WC_MYOB_refresh_token_failed');
			$_unauth     = (int) get_option('WC_MYOB_api_unauthorized_count', 0);
			$_connected  = ! empty($_token) && ! empty($_cf_id) && ! empty($_cf_user)
				&& 'yes' !== $_rf_failed && 0 === $_unauth;

			wp_localize_script('opmc_myob_script', 'starsMyobConnected', $_connected);
		}

		/**
		 * Initialize the plugin.
		 */
		public function init()
		{

			// Checks if WooCommerce is installed.
			if (class_exists('WC_Integration')) {
				// Include our integration class.
				include_once 'includes/class-stars-myob-admin-settings.php';
				// Register the integration.
				add_filter('woocommerce_integrations', array($this, 'add_integration'));
			}
		}

		/**
		 * Add a new integration to WooCommerce.
		 */
		public function add_integration($integrations)
		{
			$integrations[] = 'WC_MYOB_Integrations_Settings';
			return $integrations;
		}

		// Order Tools and Debug Tools page content is now embedded in the
		// MYOB settings page as tabs — no standalone submenu pages needed.


		/**
		 * Adds a new action to the list of WooCommerce order actions, before returning the list.
		 */
		public function add_order_meta_box_actions($actions)
		{
			$actions['resync_order_to_myob'] = __( 'Resync order to MYOB', 'stars-myob-accountright-connector-for-woocommerce' );
			return $actions;
		}


		/*
		 * cron_schedules
		 * check and execute cron job
		 */
		public function init_cron_schedule()
		{
			$connector = new Opmc_Myob_Connector();
			$woocommerce_MYOB_integrations_settings = get_option('woocommerce_MYOB_integrations_settings');
			$WC_MYOB_company_file_id = isset($woocommerce_MYOB_integrations_settings['WC_MYOB_company_file_id']) ? $woocommerce_MYOB_integrations_settings['WC_MYOB_company_file_id'] : '';
			$WC_MYOB_company_file_username = isset($woocommerce_MYOB_integrations_settings['WC_MYOB_company_file_username']) ? $woocommerce_MYOB_integrations_settings['WC_MYOB_company_file_username'] : '';
			if (!empty($WC_MYOB_company_file_id) && !empty($WC_MYOB_company_file_username)) {
				///wp_schedule_event( int $timestamp, string $recurrence, string $hook, array $args = array() )
				if (!wp_next_scheduled('WC_MYOB_access_cron')) {
					//wp_schedule_event(time(), 'WC_MYOB_further_attempt', 'WC_MYOB_access_cron');
					wp_schedule_event(time(), 'WC_MYOB_cron_interval', 'WC_MYOB_access_cron');
					$connector->create_wc_log('[MYOB Cron] [Success] [Added cron job for MYOB integration]');
				}

				if (!wp_next_scheduled('WC_MYOB_sync_cron')) {
					wp_schedule_event(time(), 'WC_MYOB_cron_interval_sync', 'WC_MYOB_sync_cron');
				}

				// Schedule Keep Alive transient twice daily
				if (!wp_next_scheduled('WC_MYOB_keep_alive_transient_cron')) {
					wp_schedule_event(time(), 'twicedaily', 'WC_MYOB_keep_alive_transient_cron');
				}

				// Schedule reload_accounts twice daily
				if (!wp_next_scheduled('WC_MYOB_reload_accounts_list_cron')) {
					wp_schedule_event(time(), 'twicedaily', 'WC_MYOB_reload_accounts_list_cron');
				}

				// Schedule Keep Alive transient twice daily
				if (!wp_next_scheduled('WC_OPMC_auto_copy_customer_from_myob_cron')) {
					wp_schedule_event(time(), 'WC_MYOB_cron_interval_customer_sync', 'WC_OPMC_auto_copy_customer_from_myob_cron');
				}

				// Schedule matrix pricing sync — only if enabled in settings
				$enable_pricing_sync = isset( $config['WC_OPMC_enable_product_pricing_sync'] ) ? $config['WC_OPMC_enable_product_pricing_sync'] : 'yes';
				if ( 'yes' === $enable_pricing_sync ) {
					if (!wp_next_scheduled('opmc_myob_single_product_sync_cron')) {
						wp_schedule_event(time(), 'WC_MYOB_cron_interval_product_pricing', 'opmc_myob_single_product_sync_cron');
						$connector->create_wc_log('[MYOB Cron] [Success] [Added cron job for matrix pricing sync]');
					}
				} else {
					wp_clear_scheduled_hook('opmc_myob_single_product_sync_cron');
				}
			}
		}

		/*
		 * cron_schedules
		 * set interval for function to execute as cron job
		 */
		public function add_cron_interval($schedules)
		{

			$schedules['WC_MYOB_cron_interval'] = array(
				'interval' => 600,
				'display' => __('MYOB 10 Minute Schedule', 'stars-myob-accountright-connector-for-woocommerce'),
			);
			$woocommerce_MYOB_integrations_settings = get_option('woocommerce_MYOB_integrations_settings');
			$WC_MYOB_sync_period = isset($woocommerce_MYOB_integrations_settings['WC_MYOB_sync_period']) ? $woocommerce_MYOB_integrations_settings['WC_MYOB_sync_period'] : '';
			if (empty($WC_MYOB_sync_period)) {
				$WC_MYOB_sync_period = 1;
			}
			$intervalPeriod = 86400 * $WC_MYOB_sync_period;
			$schedules['WC_MYOB_cron_interval_sync'] = array(
				'interval' => $intervalPeriod,
				// translators: %d is the number of days between syncs.
				'display' => sprintf( __( 'MYOB %d Days Schedule', 'stars-myob-accountright-connector-for-woocommerce' ), $WC_MYOB_sync_period ),
			);
			/* For Copy customer card from MYOB in Woo */
			$schedules['WC_MYOB_cron_interval_customer_sync'] = array(
				'interval' => $intervalPeriod,
				// translators: %d is the number of days between syncs.
				'display' => sprintf( __( 'Sync customer in Woo from MYOB %d Days Schedule', 'stars-myob-accountright-connector-for-woocommerce' ), $WC_MYOB_sync_period ),
			);
			
			/* For Matrix Pricing Sync */
			$schedules['WC_MYOB_cron_interval_product_pricing'] = array(
				'interval' => 60, // Daily
				'display' => __('MYOB Matrix Pricing Daily Schedule', 'stars-myob-accountright-connector-for-woocommerce'),
			);
			
			return $schedules;
		}

		/*MYOB deactivation*/
		public function MYOB_plugin_deactivation()
		{
			// remove crons
			$timestamp = wp_next_scheduled('WC_MYOB_acces_cron');
			//$wp_unschedule_event ( $timestamp, 'WC_MYOB_access_cron');

			// delete settings
			// delete_option( 'woocommerce_MYOB_integrations_settings' );

			// delete_option( 'WC_MYOB_client_id' );
			// delete_option( 'WC_MYOB_secret' );
			// delete_option( 'WC_MYOB_code' );
			// delete_option( 'MYOB_access_token' );
			// delete_option( 'MYOB_access_refresh_token' );
			// delete_option( 'MYOB_access_token_type' );
			// delete_option( 'MYOB_access_token_scope' );

			// delete_option( 'WC_MYOB_asset_accounts_list' );
			// delete_option( 'WC_MYOB_cogs_accounts_list' );
			// delete_option( 'WC_MYOB_company_file_list' );
			// delete_option( 'WC_MYOB_expense_accounts_list' );
			// delete_option( 'WC_MYOB_income_accounts_list' );
			// delete_option( 'WC_MYOB_tax_codes_list' );
			// delete_option( 'WC_MYOB_refresh_token_timestamp' );

			// delete_option( 'myob_customer_count' );
			// delete_option( 'myob_customer_pull_next_url' );
			// delete_option( 'myob_customer_synced_count' );
			delete_option('myob_product_count');
			delete_option('myob_product_pull_next_url');
			delete_option('myob_product_synced_count');

			//Clear transients
			delete_transient('MYOB_keep_alive_transient');
			delete_transient('MYOB_keep_alive_run');
			//Clear scheduled events
			wp_clear_scheduled_hook('WC_MYOB_keep_alive_transient_cron');
			wp_clear_scheduled_hook('WC_MYOB_reload_accounts_list_cron');
			wp_clear_scheduled_hook('WC_MYOB_sync_cron');
			wp_clear_scheduled_hook('WC_OPMC_auto_copy_customer_from_myob_cron');

			wp_clear_scheduled_hook('WC_MYOB_access_cron');
			wp_clear_scheduled_hook('myob_process_product_sync');
			wp_clear_scheduled_hook('opmc_myob_single_product_sync_cron');
		}


		public function MYOB_plugin_uninstall()
		{
			// delete settings

			delete_option('woocommerce_MYOB_integrations_settings');

			delete_option('WC_MYOB_client_id');
			delete_option('WC_MYOB_secret');
			delete_option('WC_MYOB_code');
			delete_option('MYOB_access_token');
			delete_option('MYOB_access_refresh_token');
			delete_option('MYOB_access_token_type');
			delete_option('MYOB_access_token_scope');

			delete_option('WC_MYOB_asset_accounts_list');
			delete_option('WC_MYOB_cogs_accounts_list');
			delete_option('WC_MYOB_company_file_list');
			delete_option('WC_MYOB_expense_accounts_list');
			delete_option('WC_MYOB_income_accounts_list');
			delete_option('WC_MYOB_tax_codes_list');
			delete_option('WC_MYOB_income_accounts_list');
			delete_option('WC_MYOB_refresh_token_timestamp');

			// delete_option( 'myob_customer_count' );
			// delete_option( 'myob_customer_pull_next_url' );
			// delete_option( 'myob_customer_synced_count' );
			delete_option('myob_product_count');
			delete_option('myob_product_pull_next_url');
			delete_option('myob_product_synced_count');

			//Clear transients
			delete_transient('MYOB_keep_alive_transient');
			delete_transient('MYOB_keep_alive_run');
			//Clear scheduled events
			wp_clear_scheduled_hook('WC_MYOB_keep_alive_transient_cron');
			wp_clear_scheduled_hook('WC_MYOB_reload_accounts_list_cron');
			wp_clear_scheduled_hook('WC_MYOB_sync_cron');
			wp_clear_scheduled_hook('WC_OPMC_auto_copy_customer_from_myob_cron');
			wp_clear_scheduled_hook('WC_MYOB_access_cron');
			wp_clear_scheduled_hook('myob_process_product_sync');
		}


		public function myob_meta_box($post_type)
		{
			$post_types = array('product');
			if (in_array($post_type, $post_types)) {
				add_meta_box(
					'wf_child_letters'
					,
					__('MYOB AccountRight', 'stars-myob-accountright-connector-for-woocommerce')
					,
					array($this, 'myob_meta_box_content')
					,
					$post_type
					,
					'side'
					,
					'high'
				);
			}
		}

		public function myob_meta_box_content()
		{
			global $post;
			include_once WC_MYOB_INTEGRATION_PLUGINDIR . '/includes/opmc/template-stars-product-meta-box.php';
		}

		public function save_myob_meta_box($post_id)
		{
			// Check if nonce is set
			if (!isset($_POST['myob_job_nonce'])) {
				return $post_id;
			}

			if (!wp_verify_nonce(!empty($_POST['myob_job_nonce']) ? sanitize_text_field(wp_unslash($_POST['myob_job_nonce'])) : '', 'save_myob_nonce')) {
				return $post_id;
			}

			// Check that the logged in user has permission to edit this post
			if (!current_user_can('edit_post')) {
				return $post_id;
			}

			$product_job = !empty($_POST['myob_product_job_code']) ? sanitize_text_field(wp_unslash($_POST['myob_product_job_code'])) : '';
			update_post_meta($post_id, '_myob_product_job_code', $product_job);
		}


		/**
		 * Initiates the process for product synchronisation between MYOB and WooCommerce.
		 */
		public function sync_product_from_myob_to_woo()
		{
			try {
				$connector = new Opmc_Myob_Connector();
				$connector->create_wc_log( 'Call to sync_product_from_myob_to_woo() in myob-integration' );

				$myob_products = $connector->sync_products_from_myob();

				if ( null === $myob_products ) {
					$connector->create_wc_log( '[Product Sync] No products returned from MYOB (null).' );
				} elseif ( empty( $myob_products ) ) {
					$connector->create_wc_log( '[Product Sync] No products returned from MYOB (empty).' );
				} else {
					$connector->create_wc_log( '[Product Sync] Fetched ' . count( $myob_products ) . ' products from MYOB.' );
				}
			} catch ( Exception $e ) {
				$connector = new Opmc_Myob_Connector();
				$connector->create_wc_log( '[Product Sync] Error: ' . $e->getMessage() );
			}
		}
		/**
		 * Schedules the cron job for product synchronisation between MYOB and WooCommerce based on the value of the admin setting.
		 */
		public function add_myob_cron($v, $data)
		{

			$connector = new Opmc_Myob_Connector();
			$connector->create_wc_log("Call to add_myob_cron() in myob-integration with arguments: $v, $data");

			if (isset($data['WC_OPMC_create_product_to_woo_cron']) && ('yes' == $data['WC_OPMC_create_product_to_woo_cron'] && !wp_next_scheduled('myob_process_product_sync'))) {
				wp_schedule_event(time(), $data['WC_OPMC_create_product_to_woo_cron_frequency'], 'myob_process_product_sync');

			}
		}


		/**
		 * Updates the frequency of the cron job for product synchronisation between WooCommerce and MYOB.
		 */
		public function update_myob_cron($old_values, $new_values)
		{

			$connector = new Opmc_Myob_Connector();
			$old_product_cron_frequency = isset($old_values['WC_OPMC_create_product_to_woo_cron_frequency']);
			$new_product_cron_frequency = $new_values['WC_OPMC_create_product_to_woo_cron_frequency'];

			$config = get_option('woocommerce_MYOB_integrations_settings');

			$setting_value = isset($config['WC_OPMC_create_product_to_woo_cron']);
			$setting_state = isset($config['WC_OPMC_create_product_to_woo_cron']);
			$schedule_myob_to_woo_product_sync = isset($new_values['WC_OPMC_create_product_to_woo_cron']) ? $new_values['WC_OPMC_create_product_to_woo_cron'] : 'no';

			$connector->create_wc_log("Call to update_myob_cron() in myob-integration with arguments: $old_product_cron_frequency, $new_product_cron_frequency, $setting_state");
			$connector->create_wc_log("Value of isset(setting): $setting_state, $schedule_myob_to_woo_product_sync");

			if ('yes' == $schedule_myob_to_woo_product_sync) {

				$connector->create_wc_log('update_myob_cron(): SETTING IS SET, PROCEEDING WITH USUAL CHECK');

				if ((!wp_next_scheduled('myob_process_product_sync')) || ($old_values['WC_OPMC_create_product_to_woo_cron_frequency'] != $new_values['WC_OPMC_create_product_to_woo_cron_frequency'])) {

					wp_clear_scheduled_hook('myob_process_product_sync');
					wp_schedule_event(time(), $new_values['WC_OPMC_create_product_to_woo_cron_frequency'], 'myob_process_product_sync');
				} else {
					wp_clear_scheduled_hook('myob_process_product_sync');
					wp_schedule_event(time(), $new_values['WC_OPMC_create_product_to_woo_cron_frequency'], 'myob_process_product_sync');
				}

			} else {
				$connector->create_wc_log('update_myob_cron(): SETTING IS NOT SET, CLEARING myob_process_product_sync HOOK');

				wp_clear_scheduled_hook('myob_process_product_sync');
			}

			// Handle the single product pricing sync cron toggle
			$enable_pricing_sync = isset( $new_values['WC_OPMC_enable_product_pricing_sync'] ) ? $new_values['WC_OPMC_enable_product_pricing_sync'] : 'yes';
			if ( 'yes' === $enable_pricing_sync ) {
				if ( ! wp_next_scheduled( 'opmc_myob_single_product_sync_cron' ) ) {
					wp_schedule_event( time(), 'WC_MYOB_cron_interval_product_pricing', 'opmc_myob_single_product_sync_cron' );
					$connector->create_wc_log( '[MYOB Cron] [Success] [Product pricing sync cron enabled]' );
					$connector->create_sync_log( 'Product pricing sync cron enabled.', 'INFO' );
				}
			} else {
				wp_clear_scheduled_hook( 'opmc_myob_single_product_sync_cron' );
				$connector->create_wc_log( '[MYOB Cron] [Info] [Product pricing sync cron disabled]' );
				$connector->create_sync_log( 'Product pricing sync cron disabled.', 'INFO' );
			}
		}


		/**
		 * Function to display messages to users regarding the status of product synchronisation between WooCommerce and MYOB (both ways).
		 */
		public function admin_notices()
		{
			$connector = new Opmc_Myob_Connector();

			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only page detection, no form data processed.
			if (isset($_GET['tab']) && isset($_GET['section']) && 'integration' == $_GET['tab'] && 'myob_integrations' == $_GET['section']) { // phpcs:enable

				// Update WooCommerce product catalogue by adding new MYOB products
				if ($myob_product_synced_count = get_option('myob_product_synced_count')) {
					$myob_product_total_count = get_option('myob_product_count');

					// Check if sync is complete
					if ($myob_product_synced_count >= $myob_product_total_count) {
						echo '<div class="notice notice-success is-dismissible" id="myob-sync-complete-notice">
						<p><strong>Stars MYOB AccountRight Connector — product import completed!</strong> All '
							. esc_html($myob_product_total_count)
							. ' products have been imported to WooCommerce from MYOB.</p>
						</div>';

						// Add script to reset synced count when notice is dismissed
						?>
						<script type="text/javascript">
							(function ($) {
								$(document).on('click', '#myob-sync-complete-notice .notice-dismiss', function () {
									// Send an AJAX request to reset the synced count to 0
									$.post(ajaxurl, {
										action: 'reset_myob_sync_count'
									});
								});
							})(jQuery);
						</script>
						<?php
					} else {
						// Show the ongoing sync status
						$displayed_myob_product_count = min($myob_product_synced_count, $myob_product_total_count);

						echo '<div class="notice notice-warning">
						<p><strong>WooCommerce MYOB AccountRight is syncing products.</strong> '
							. esc_html($displayed_myob_product_count)
							. ' out of '
							. esc_html($myob_product_total_count)
							. ' products have been synced to WooCommerce from MYOB. (refresh to update)</p>
						</div>' . "\n";
					}
				}

				// Update MYOB product catalogue by adding new WooCommerce products
				if ($woo_product_synced_count = get_option('woo_product_synced_to_myob_count')) {
					$woo_product_total_count = get_option('myob_woo_product_count');
					$displayed_woo_product_count = min($woo_product_synced_count, $woo_product_total_count);

					echo '<div class="notice notice-warning is-dismissible"><p><strong>WooCommerce MYOB AccountRight is syncing products.</strong> '
						. esc_html($displayed_woo_product_count)
						. ' products are Synced To MYOB, out of '
						. esc_html($woo_product_total_count) . '.</p></div>' . "\n";
				}
			}
		}

		public function reset_myob_sync_count()
		{
			// Check if the user has permission to manage WooCommerce settings
			if (!current_user_can('manage_woocommerce')) {
				wp_send_json_error('You do not have permission to perform this action.');
				wp_die();
			}

			// Reset the myob_product_synced_count to 0
			update_option('myob_product_synced_count', 0);
			wp_send_json_success('Product sync count reset to 0.');
		}
	}

	// Boot the plugin once all plugins have loaded, only if WooCommerce is present.
	add_action('plugins_loaded', function () {
		if ( class_exists( 'WooCommerce' ) ) {
			WC_MYOB_Integration::get_instance();
		}
	}, 10);

endif; // class_exists WC_MYOB_Integration



//
//  Hooks are below for Ajax functions in Admin panel
//

/**
 * AJAX handler — disconnect from MYOB by clearing all stored tokens and credentials.
 * Leaves WooCommerce settings (accounts, tax codes etc.) intact so the user
 * doesn't have to re-configure everything after reconnecting.
 */
function stars_myob_disconnect_ajax() {
	check_ajax_referer( 'opmc_myob_security', 'security' );

	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_send_json_error( 'Permission denied.' );
	}

	// Clear OAuth tokens.
	delete_option( 'MYOB_access_token' );
	delete_option( 'MYOB_access_refresh_token' );
	delete_option( 'MYOB_access_token_type' );
	delete_option( 'MYOB_access_token_scope' );
	delete_option( 'WC_MYOB_code' );

	// Clear auth error flags so the UI doesn't show stale error banners.
	delete_option( 'WC_MYOB_refresh_token_failed' );
	delete_option( 'WC_MYOB_refresh_token_timestamp' );
	update_option( 'WC_MYOB_api_unauthorized_count', 0 );

	// Clear company file selection (forces re-selection after reconnect).
	delete_option( 'WC_MYOB_company_file_id' );
	delete_option( 'WC_MYOB_company_file_list' );

	// Remove company_file_id from the main settings array too.
	$settings = get_option( 'woocommerce_MYOB_integrations_settings', array() );
	unset( $settings['WC_MYOB_company_file_id'] );
	update_option( 'woocommerce_MYOB_integrations_settings', $settings );

	// Clear keep-alive transients.
	delete_transient( 'MYOB_keep_alive_transient' );
	delete_transient( 'MYOB_keep_alive_run' );

	wp_send_json_success( array( 'message' => 'Disconnected from MYOB.' ) );
}


/**
 * Launch the sync process when the store owner clicks the sync button on the admin panel
 */
function MYOB_sync_product_ajax()
{
	$connector = new Opmc_Myob_Connector();
	$connector->create_wc_log('MYOB_sync_product_ajax()');
	// echo 'hello';

	if ($connector) {
		// $connector->sync_inventory_data();
		$connector->sync_products_from_myob();
		// $connector->import_product_to_myob();
	}
}

function MYOB_sync_invoices_ajax()
{
	$connector = new Opmc_Myob_Connector();
	/* log to be activated */
	// $connector->create_wc_log( 'MYOB_sync_product_ajax()' );
	// echo 'hello';
	if ($connector) {
		// $connector->sync_inventory_data();
		try {
			$connector->get_invoices_from_myob();
		} catch (Exception $e) {
			print_r($e->getMessage());
			echo '<tr><td colspan="8" style="color: red;">Error displaying invoice: ' . esc_html($e->getMessage()) . '</td></tr>';
		}
		// $connector->import_product_to_myob();
	}
}
//$type,$invoice_uid
function opmc_get_invoice_for_individual()
{

	echo 'hello';
	/*
	$connector = new Opmc_Myob_Connector();
	/* log to be activated 
	// $connector->create_wc_log( 'MYOB_sync_product_ajax()' );
	// echo 'hello';
	if ( $connector ) {
		// $connector->sync_inventory_data();
		$connector->remote_get_pdf($type,$invoice_uid);
		// $connector->import_product_to_myob();
	}
		*/

}

function stars_myob_download_invoice_pdf( WP_REST_Request $request ) {
	$uid  = sanitize_text_field( $request->get_param( 'invoice_uid' ) );
	$type = sanitize_text_field( $request->get_param( 'type' ) );

	if ( empty( $uid ) || empty( $type ) ) {
		return new WP_Error( 'missing_params', 'Missing invoice UID or type.', array( 'status' => 400 ) );
	}

	try {
		$connector = new Opmc_Myob_Connector();
		$response  = $connector->get_invoice_pdf( $type, $uid );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'pdf_error', $response->get_error_message(), array( 'status' => 500 ) );
		}

		$pdf_body = wp_remote_retrieve_body( $response );

		if ( empty( $pdf_body ) ) {
			return new WP_Error( 'empty_pdf', 'The PDF content was empty.', array( 'status' => 500 ) );
		}

		if ( ob_get_length() ) {
			ob_end_clean();
		}

		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="invoice-' . sanitize_file_name( $uid ) . '.pdf"' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw PDF binary, cannot be escaped
		echo $pdf_body;
		exit;

	} catch ( Exception $e ) {
		return new WP_Error( 'pdf_exception', $e->getMessage(), array( 'status' => 500 ) );
	}
}

function MYOB_sync_customers_ajax()
{
	$connector = new Opmc_Myob_Connector();
	/* log to be activated */
	// $connector->create_wc_log( 'MYOB_sync_product_ajax()' );
	// echo 'hello';
	if ($connector) {
		// $connector->sync_inventory_data();
		$connector->get_customer_data_from_myob();
		// $connector->import_product_to_myob();
	}
}

/**
 * Sync a single customer by email
 */
function MYOB_sync_customer_by_email_ajax()
{
	$connector = new Opmc_Myob_Connector();

	$nonce = isset($_POST['security']) ? sanitize_text_field(wp_unslash($_POST['security'])) : '';
	if (empty($nonce) || !wp_verify_nonce($nonce, 'opmc_myob_security')) {
		wp_send_json(['success' => false, 'message' => 'Security verification failed.']);
	}

	$customer_email = isset($_POST['customer_email']) ? sanitize_email(wp_unslash($_POST['customer_email']) : '';
	if (empty($customer_email)) {
		wp_send_json(['success' => false, 'message' => 'Invalid email.']);
	}

	try {
		$result = $connector->get_customer_with_email($customer_email);
		if (!$result) {
			wp_send_json(['success' => false, 'message' => 'Customer not found in MYOB.']);
		}

		$result_array = is_array($result) ? $result : (array) $result;
		$myob_customer_id = $result_array['UID'] ?? '';

		$selling_details = $result_array['SellingDetails'] ?? null;
		$selling_details = is_object($selling_details) ? (array) $selling_details : $selling_details;

		$terms = $selling_details['Terms'] ?? null;
		$terms = is_object($terms) ? (array) $terms : $terms;

		$item_price_level = $selling_details['ItemPriceLevel'] ?? '';
		$payment_due = $terms['PaymentIsDue'] ?? '';

		$wp_user = get_user_by('email', $customer_email);
		if (!$wp_user) {
			wp_send_json(['success' => false, 'message' => 'WordPress user not found.']);
		}

		$user_id = $wp_user->ID;

		if ($myob_customer_id !== '') {
			update_user_meta($user_id, 'myob_customer_id', $myob_customer_id);
		}

		update_user_meta($user_id, 'myob_payment_terms', $payment_due);

		$target_role = str_replace(' ', '', $item_price_level);
		$level_roles = ['LevelA', 'LevelB', 'LevelC', 'LevelD', 'LevelE', 'LevelF'];

		if (in_array($target_role, $level_roles, true)) {

			foreach ($wp_user->roles as $role) {
				if ($role === 'customer') {
					$wp_user->remove_role($role);
					continue;
				}
				if (in_array($role, $level_roles, true) && $role !== $target_role) {
					$wp_user->remove_role($role);
				}
			}

			if (!in_array($target_role, $wp_user->roles, true)) {
				$wp_user->add_role($target_role);
			}
		}

		wp_send_json([
			'success' => true,
			'user_id' => $user_id,
			'myob_customer_id' => $myob_customer_id,
			'item_price_level' => $item_price_level,
			'payment_due' => $payment_due,
			'user_roles_after_sync' => $wp_user->roles,
		]);

	} catch (Exception $e) {
		wp_send_json(['success' => false, 'message' => $e->getMessage()]);
	}
}

/**
 * Sync Single product in MYOB from WooCommerce using SKU
 */
function MYOB_sync_product_by_sku_ajax() {

    $connector = new Opmc_Myob_Connector();

    // ---- SECURITY ----
    $nonce = isset($_POST['security'])
        ? sanitize_text_field(wp_unslash($_POST['security']))
        : '';

    if (empty($nonce) || !wp_verify_nonce($nonce, 'opmc_myob_security')) {

        $connector->create_wc_log('[Error] Security verification failed');

        wp_send_json([
            'success' => false,
            'message' => 'Security verification failed.',
        ]);
    }

    // ---- SKU ----
    $product_sku = isset($_POST['product_sku'])
        ? sanitize_text_field($_POST['product_sku'])
        : '';

    if (empty($product_sku)) {

        $connector->create_wc_log('[Error] Empty product SKU');

        wp_send_json([
            'success' => false,
            'message' => 'Please enter a valid product SKU.',
        ]);
    }

    try {

    opmc_sync_single_product_from_myob_by_sku( $product_sku );
    $connector->create_sync_log( '[Manual] Product synced | SKU=' . $product_sku, 'SUCCESS' );

    wp_send_json([
        'success' => true,
        'message' => 'Product synced successfully from MYOB.',
    ]);

} catch ( Exception $e ) {

    $connector->create_wc_log(
        '[Error] MYOB → Woo sync Failed | ' . $e->getMessage()
    );
    $connector->create_sync_log( '[Manual Error] SKU=' . $product_sku . ' | ' . $e->getMessage(), 'ERROR' );

    wp_send_json([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
}

/**
 * View product details + tiered pricing from MYOB using SKU
 */
function MYOB_view_product_by_sku_ajax() {

	$connector = new Opmc_Myob_Connector();

	$nonce = isset($_POST['security'])
		? sanitize_text_field(wp_unslash($_POST['security']))
		: '';

	if (empty($nonce) || !wp_verify_nonce($nonce, 'opmc_myob_security')) {
		$connector->create_wc_log('[Error] Security verification failed (view product by sku)');

		wp_send_json([
			'success' => false,
			'message' => 'Security verification failed.',
		]);
	}

	$product_sku = isset($_POST['product_sku'])
		? sanitize_text_field(wp_unslash($_POST['product_sku']))
		: '';

	if (empty($product_sku)) {
		wp_send_json([
			'success' => false,
			'message' => 'Please enter a valid product SKU.',
		]);
	}

	try {
		$myob_item = $connector->get_single_product_with_sku($product_sku);

		if (empty($myob_item) || empty($myob_item->UID)) {
			wp_send_json([
				'success' => false,
				'message' => 'Product not found in MYOB for SKU: ' . esc_html($product_sku),
			]);
		}

		$matrix_info = $connector->get_product_metrix_info($myob_item->UID);
		$levels = ['LevelA', 'LevelB', 'LevelC', 'LevelD', 'LevelE', 'LevelF'];
		$tiered_pricing = [];

		if (!empty($matrix_info->SellingPrices) && is_array($matrix_info->SellingPrices)) {
			foreach ($matrix_info->SellingPrices as $row) {
				if (!isset($row->QuantityOver, $row->Levels)) {
					continue;
				}

				$qty_over = (int) $row->QuantityOver;
				$qty_from = ($qty_over > 0) ? ($qty_over + 1) : 0;
				$levels_data = [];

				foreach ($levels as $level) {
					$levels_data[$level] = isset($row->Levels->$level)
						? wc_format_decimal($row->Levels->$level, 2)
						: null;
				}

				$tiered_pricing[] = [
					'quantity_over' => $qty_over,
					'quantity_from' => $qty_from,
					'levels' => $levels_data,
				];
			}
		}

		$product_data = [
			'uid' => isset($myob_item->UID) ? $myob_item->UID : '',
			'sku' => isset($myob_item->Number) ? $myob_item->Number : '',
			'name' => isset($myob_item->Name) ? $myob_item->Name : '',
			'description' => isset($myob_item->Description) ? $myob_item->Description : '',
			'is_active' => isset($myob_item->IsActive) ? (bool) $myob_item->IsActive : false,
			'base_selling_price' => isset($myob_item->BaseSellingPrice) ? wc_format_decimal($myob_item->BaseSellingPrice, 2) : '',
			'quantity_on_hand' => isset($myob_item->QuantityOnHand) ? $myob_item->QuantityOnHand : '',
			'quantity_available' => isset($myob_item->QuantityAvailable) ? $myob_item->QuantityAvailable : '',
			'last_modified' => isset($myob_item->LastModified) ? $myob_item->LastModified : '',
		];

		wp_send_json([
			'success' => true,
			'message' => 'Product details loaded.',
			'product' => $product_data,
			'tiered_pricing' => $tiered_pricing,
			'raw_item' => $myob_item,
		]);

	} catch (Exception $e) {
		$connector->create_wc_log('[Error] MYOB view by SKU failed | ' . $e->getMessage());

		wp_send_json([
			'success' => false,
			'message' => $e->getMessage(),
		]);
	}
}


function MYOB_sync_order_by_number_ajax() {
	$connector = new Opmc_Myob_Connector();
	$connector->create_wc_log('========== MYOB_sync_order_by_number_ajax() - START ==========');

	// Verify nonce for security
	$nonce = isset($_POST['security']) ? sanitize_text_field(wp_unslash($_POST['security'])) : '';
	if (empty($nonce) || !wp_verify_nonce($nonce, 'opmc_myob_security')) {
		$connector->create_wc_log('[Error] Security verification failed - invalid or missing nonce');
		wp_send_json(array(
			'success' => false,
			'message' => 'Security verification failed.',
		));
		exit;
	}
	$connector->create_wc_log('[Security] Nonce verification passed');

	$order_number = isset($_POST['order_number']) ? sanitize_text_field($_POST['order_number']) : '';
	$connector->create_wc_log('[Input] Order number received: ' . esc_html($order_number));

	if (empty($order_number)) {
		$connector->create_wc_log('[Error] Empty order number provided');
		wp_send_json(array(
			'success' => false,
			'message' => 'Please enter a valid order number.',
		));
		exit;
	}

	if ($connector) {
		try {
			$connector->create_wc_log('[Process] Searching for order with number: ' . esc_html($order_number));

			$order = opmc_get_order_by_id_or_number($order_number);

			if (!$order) {
				$connector->create_wc_log('[Error] Order not found with number: ' . esc_html($order_number));
				wp_send_json(array(
					'success' => false,
					'message' => 'Order not found. Please check the order number.',
				));
				exit;
			}
			$order_id = $order->get_id();

			$connector->create_wc_log('[Process] Found order ID: ' . $order_id);
			$connector->create_wc_log('[Process] Starting resync for order: ' . esc_html($order_number));

			if ($connector->backfill_order_metadata($order_id)) {
				$connector->create_wc_log('[Success] Existing MYOB order metadata backfilled for order: ' . esc_html($order_number));
				wp_send_json(array(
					'success' => true,
					'message' => 'Order #' . esc_html($order_number) . ' MYOB PO number was refreshed successfully.',
				));
				exit;
			}

			// Call the resync function
			$connector->resync_order_to_myob($order);

			$connector->create_wc_log('[Success] Order synced successfully: ' . esc_html($order_number));

			wp_send_json(array(
				'success' => true,
				'message' => 'Order #' . esc_html($order_number) . ' has been synced to MYOB successfully!',
			));
			exit;

		} catch (Throwable $e) {
			$error_msg = $e->getMessage();
			$connector->create_wc_log('[Exception] Error syncing order: ' . $error_msg);
			$connector->create_wc_log('[Exception] Trace: ' . $e->getTraceAsString());

			wp_send_json(array(
				'success' => false,
				'message' => 'Error: ' . $error_msg,
			));
			exit;
		}
	}
}

function MYOB_view_order_by_number_ajax() {
	$connector = new Opmc_Myob_Connector();
	$connector->create_wc_log('========== MYOB_view_order_by_number_ajax() - START ==========');

	$nonce = isset($_POST['security']) ? sanitize_text_field(wp_unslash($_POST['security'])) : '';
	if (empty($nonce) || !wp_verify_nonce($nonce, 'opmc_myob_security')) {
		wp_send_json(array(
			'success' => false,
			'message' => 'Security verification failed.',
		));
		exit;
	}

	$order_number = isset($_POST['order_number']) ? sanitize_text_field(wp_unslash($_POST['order_number'])) : '';
	if (empty($order_number)) {
		wp_send_json(array(
			'success' => false,
			'message' => 'Please enter a valid order number.',
		));
		exit;
	}

	try {
		$order = opmc_get_order_by_id_or_number($order_number);
		if (!$order) {
			wp_send_json(array(
				'success' => false,
				'message' => 'Order not found. Please check the order number.',
			));
			exit;
		}

		$order_id = $order->get_id();
		$myob_order = $connector->fetch_orders($order_id);

		if (!is_object($myob_order) || empty($myob_order->UID)) {
			wp_send_json(array(
				'success' => false,
				'message' => 'No MYOB order or quote was found for this WooCommerce order.',
			));
			exit;
		}

		$connector->save_created_order_metadata($order_id, $myob_order);

		$order_data = array(
			'woo_order_id' => $order_id,
			'woo_order_number' => $order->get_order_number(),
			'document_type' => isset($myob_order->URI) && false !== strpos((string) $myob_order->URI, '/Sale/Quote/') ? 'Quote' : 'Order',
			'myob_uid' => isset($myob_order->UID) ? (string) $myob_order->UID : '',
			'myob_number' => isset($myob_order->Number) ? (string) $myob_order->Number : '',
			'customer_po_number' => isset($myob_order->CustomerPurchaseOrderNumber) ? (string) $myob_order->CustomerPurchaseOrderNumber : '',
			'status' => isset($myob_order->Status) ? (string) $myob_order->Status : '',
			'date' => isset($myob_order->Date) ? (string) $myob_order->Date : '',
			'promised_date' => isset($myob_order->PromisedDate) ? (string) $myob_order->PromisedDate : '',
			'is_tax_inclusive' => isset($myob_order->IsTaxInclusive) ? (bool) $myob_order->IsTaxInclusive : null,
			'row_version' => isset($myob_order->RowVersion) ? (string) $myob_order->RowVersion : '',
			'freight' => isset($myob_order->Freight) ? (string) $myob_order->Freight : '',
			'freight_tax' => isset($myob_order->FreightTax) ? (string) $myob_order->FreightTax : '',
			'subtotal' => isset($myob_order->Subtotal) ? (string) $myob_order->Subtotal : '',
			'total_tax' => isset($myob_order->TotalTax) ? (string) $myob_order->TotalTax : '',
			'total_amount' => isset($myob_order->TotalAmount) ? (string) $myob_order->TotalAmount : '',
			'balance_due_amount' => isset($myob_order->BalanceDueAmount) ? (string) $myob_order->BalanceDueAmount : '',
			'comment' => isset($myob_order->Comment) ? (string) $myob_order->Comment : '',
			'journal_memo' => isset($myob_order->JournalMemo) ? (string) $myob_order->JournalMemo : '',
			'ship_via' => isset($myob_order->ShipVia) ? (string) $myob_order->ShipVia : '',
			'customer_name' => isset($myob_order->Customer->Name) ? (string) $myob_order->Customer->Name : '',
			'customer_display_id' => isset($myob_order->Customer->DisplayID) ? (string) $myob_order->Customer->DisplayID : '',
			'customer_uid' => isset($myob_order->Customer->UID) ? (string) $myob_order->Customer->UID : '',
			'bill_to_name' => isset($myob_order->BillTo->Name) ? (string) $myob_order->BillTo->Name : '',
			'ship_to_name' => isset($myob_order->ShipTo->Name) ? (string) $myob_order->ShipTo->Name : '',
			'line_count' => isset($myob_order->Lines) && is_array($myob_order->Lines) ? count($myob_order->Lines) : 0,
		);

		wp_send_json(array(
			'success' => true,
			'message' => 'MYOB order loaded successfully.',
			'order' => $order_data,
			'raw_item' => $myob_order,
		));
		exit;
	} catch (Throwable $e) {
		$connector->create_wc_log('[Exception] Error viewing order: ' . $e->getMessage());

		wp_send_json(array(
			'success' => false,
			'message' => 'Error: ' . $e->getMessage(),
		));
		exit;
	}
}

function opmc_get_order_by_id_or_number($order_number)
{
	$order_number = trim((string) $order_number);
	if ($order_number === '') {
		return false;
	}

	if (is_numeric($order_number)) {
		$order = wc_get_order((int) $order_number);
		if ($order) {
			return $order;
		}
	}

	$orders = wc_get_orders(array(
		'limit' => -1,
		'orderby' => 'date',
		'order' => 'DESC',
	));

	foreach ($orders as $candidate_order) {
		if ((string) $candidate_order->get_order_number() === $order_number) {
			return $candidate_order;
		}
	}

	return false;
}



/**
 * Sync MYOB price matrix data to WooCommerce product meta.
 *
 * Behaviour depends on the "Tiered Pricing Table Pro" setting:
 *  - Pro  mode: writes per-role meta keys (_LevelA_fixed_price_rules, etc.)
 *               recognised by the Pro version of Tiered Pricing Table.
 *  - Free mode: writes a single _fixed_price_rules key using LevelA prices,
 *               recognised by the free version of Tiered Pricing Table.
 *
 * Edge cases handled:
 *  - Empty / missing SellingPrices  → returns early, no meta touched.
 *  - Level price = 0                → treated as "not configured", skipped.
 *  - Only QuantityOver=0 row        → sets regular prices, no quantity breaks.
 *  - Some levels populated, others  → handled per-level independently.
 *
 * @param int    $product_id WooCommerce product ID.
 * @param object $matrix     Decoded MYOB ItemPriceMatrix response object.
 */
function opmc_update_tier_pricing_from_matrix( $product_id, $matrix ) {

    if ( empty( $matrix->SellingPrices ) ) {
        return;
    }

    $settings       = get_option( 'woocommerce_MYOB_integrations_settings', array() );
    $is_pro_mode    = isset( $settings['WC_OPMC_tiered_pricing_pro'] ) && 'yes' === $settings['WC_OPMC_tiered_pricing_pro'];
    $levels         = array( 'LevelA', 'LevelB', 'LevelC', 'LevelD', 'LevelE', 'LevelF' );

    // Preserve any existing WooCommerce sale price.
    $existing_sale = (string) get_post_meta( $product_id, '_sale_price', true );

    // ── Build pricing data from matrix rows ───────────────────────────────
    $level_base_prices  = array(); // QuantityOver=0 price per level
    $level_break_prices = array(); // qty-break prices per level  [level][qty] = price

    foreach ( $matrix->SellingPrices as $row ) {

        if ( ! isset( $row->QuantityOver, $row->Levels ) ) {
            continue;
        }

        $qty_over = (int) $row->QuantityOver;
        // QuantityOver is the threshold — the rule applies from (qty_over + 1).
        // Use 0 as the sentinel for the base-price row.
        $qty_from = ( $qty_over > 0 ) ? ( $qty_over + 1 ) : 0;

        foreach ( $levels as $level ) {

            if ( ! isset( $row->Levels->$level ) ) {
                continue;
            }

            $raw_price = $row->Levels->$level;

            // Skip levels with no price configured in MYOB (0 means unused).
            if ( empty( $raw_price ) || (float) $raw_price <= 0 ) {
                continue;
            }

            $price = (string) wc_format_decimal( $raw_price, 2 );

            if ( $qty_from === 0 ) {
                // Base price row (QuantityOver = 0).
                $level_base_prices[ $level ] = $price;
            } else {
                // Quantity-break row.
                $level_break_prices[ $level ][ $qty_from ] = $price;
            }
        }
    }

    // ── Update WooCommerce regular price from LevelA base price ───────────
    if ( isset( $level_base_prices['LevelA'] ) ) {
        $level_a_base = $level_base_prices['LevelA'];
        update_post_meta( $product_id, '_regular_price', $level_a_base );
        // Only update active price when no sale price is set.
        if ( '' === $existing_sale ) {
            update_post_meta( $product_id, '_price', $level_a_base );
        }
    }

    if ( $is_pro_mode ) {
        // ── PRO MODE: write per-role meta keys ────────────────────────────
        opmc_write_pro_tier_meta( $product_id, $levels, $level_base_prices, $level_break_prices, $existing_sale );
    } else {
        // ── FREE MODE: write single _fixed_price_rules from LevelA ───────
        opmc_write_free_tier_meta( $product_id, $level_base_prices, $level_break_prices );
    }

    wc_delete_product_transients( $product_id );
}

/**
 * Write Pro-version (role-based) tiered pricing meta for all levels.
 *
 * @param int    $product_id
 * @param array  $levels
 * @param array  $level_base_prices   Level => base price string.
 * @param array  $level_break_prices  Level => [ qty => price ] array.
 * @param string $existing_sale       Current WooCommerce sale price.
 */
function opmc_write_pro_tier_meta( $product_id, $levels, $level_base_prices, $level_break_prices, $existing_sale ) {

    foreach ( $levels as $level ) {

        // Base / regular price for this level.
        if ( isset( $level_base_prices[ $level ] ) ) {
            update_post_meta( $product_id, '_' . $level . '_tiered_price_regular_price', $level_base_prices[ $level ] );
        }

        // Quantity-break rules for this level (empty array if no breaks).
        $breaks = isset( $level_break_prices[ $level ] ) ? $level_break_prices[ $level ] : array();

        update_post_meta( $product_id, '_' . $level . '_fixed_price_rules',      $breaks );
        update_post_meta( $product_id, '_' . $level . '_percentage_price_rules', array() );
        update_post_meta( $product_id, '_' . $level . '_tiered_price_discount_type', 'regular_price' );
        update_post_meta( $product_id, '_' . $level . '_tiered_price_pricing_type',  'flat' );
        update_post_meta( $product_id, '_' . $level . '_tiered_price_rules_type',    'fixed' );
        update_post_meta( $product_id, '_' . $level . '_tiered_price_sale_rules',      array() );
        update_post_meta( $product_id, '_' . $level . '_tiered_price_sale_rules_type', 'fixed' );

        // Preserve any existing tiered sale price fields — only write blank if not set.
        foreach ( array( 'sale_price', 'sale_discount', 'sale_percentage', 'sale_from', 'sale_to' ) as $field ) {
            $meta_key = '_' . $level . '_tiered_price_' . $field;
            if ( '' === (string) get_post_meta( $product_id, $meta_key, true ) ) {
                update_post_meta( $product_id, $meta_key, '' );
            }
        }
    }
}

/**
 * Write free-version tiered pricing meta using LevelA prices only.
 *
 * The free "Tiered Pricing Table" plugin reads:
 *   _fixed_price_rules  => array( min_qty => price, ... )
 *   _tiered_price_tier_labels => array( 'fixed' => array(), 'percentage' => array() )
 *
 * @param int    $product_id
 * @param array  $level_base_prices
 * @param array  $level_break_prices
 */
function opmc_write_free_tier_meta( $product_id, $level_base_prices, $level_break_prices ) {

    // Only LevelA breaks are used in free mode.
    $breaks = isset( $level_break_prices['LevelA'] ) ? $level_break_prices['LevelA'] : array();

    update_post_meta( $product_id, '_fixed_price_rules', $breaks );

    // Ensure the tier-labels meta is present (free plugin expects it).
    $existing_labels = get_post_meta( $product_id, '_tiered_price_tier_labels', true );
    if ( empty( $existing_labels ) ) {
        update_post_meta(
            $product_id,
            '_tiered_price_tier_labels',
            array( 'fixed' => array(), 'percentage' => array() )
        );
    }
}







/**
 * Create products in MYOB from WooCommerce
 */
function MYOB_import_product_to_myob()
{
	$connector = new Opmc_Myob_Connector();
	$connector->create_wc_log('MYOB_import_product_to_myob()');
	if ($connector) {
		$connector->import_product_to_myob();
	}
}

/**
 * Create Logs file
 *
 * @param  [int] $order_id [woocommerce order id]
 */
function opmc_myob_view_debug_logs()
{
	if (!check_ajax_referer('opmc_myob_security', 'security', false)) {
		wp_send_json(
			array(
				'threads' => array(),
				'subject' => '',
				'error' => 'There was security vulnerability issues in your request.',
			)
		);
		exit;
	}
	$selected_log = !empty($_POST['selected']) ? sanitize_text_field($_POST['selected']) : '';
	$selected_log_text = !empty($_POST['selected2']) ? sanitize_text_field($_POST['selected2']) : '';
	if ('' != $selected_log) {
		update_option('selected_opmc_myob_log_view_date', $selected_log);
		update_option('selected_opmc_myob_log_view_text', $selected_log_text);
	}
	echo json_encode(
		array(
			'result' => 'success',
		)
	);
	exit;
}

/**
 * AJAX: Return the last N lines of the plugin sync log as JSON.
 * Also returns retention info for the countdown display.
 */
function opmc_myob_get_sync_log() {
	if ( ! check_ajax_referer( 'opmc_myob_security', 'security', false ) ) {
		wp_send_json( array( 'success' => false, 'lines' => array() ) );
	}

	$log_file = WC_MYOB_INTEGRATION_PLUGINDIR . 'stars-myob-sync.log';
	$lines    = array();
	$oldest_ts = null;

	if ( file_exists( $log_file ) ) {
		$raw  = file_get_contents( $log_file ); // phpcs:ignore
		$all  = array_filter( array_reverse( explode( PHP_EOL, $raw ) ), 'strlen' );
		$lines = array_values( array_slice( $all, 0, 500 ) );

		// Find oldest timestamp for countdown
		$all_asc = array_filter( explode( PHP_EOL, $raw ), 'strlen' );
		foreach ( $all_asc as $entry ) {
			if ( preg_match( '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) UTC\]/', $entry, $m ) ) {
				$oldest_ts = $m[1];
				break;
			}
		}
	}

	$settings    = get_option( 'woocommerce_MYOB_integrations_settings', array() );
	$retain_days = isset( $settings['WC_OPMC_sync_log_retention'] ) ? (int) $settings['WC_OPMC_sync_log_retention'] : 7;

	// Next purge = oldest entry timestamp + retention period
	$next_purge_ts = null;
	if ( $oldest_ts ) {
		$next_purge_ts = gmdate( 'Y-m-d H:i:s', strtotime( $oldest_ts . ' UTC' ) + ( $retain_days * DAY_IN_SECONDS ) );
	}

	wp_send_json( array(
		'success'       => true,
		'lines'         => $lines,
		'retain_days'   => $retain_days,
		'oldest_entry'  => $oldest_ts,
		'next_purge_utc' => $next_purge_ts,
		'server_utc'    => gmdate( 'Y-m-d H:i:s' ),
	) );
}

/**
 * AJAX: Clear the plugin sync log file.
 */
function opmc_myob_clear_sync_log() {
	if ( ! check_ajax_referer( 'opmc_myob_security', 'security', false ) ) {
		wp_send_json( array( 'success' => false, 'message' => 'Security check failed.' ) );
	}
	$log_file = WC_MYOB_INTEGRATION_PLUGINDIR . 'stars-myob-sync.log';
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	file_put_contents( $log_file, '' );
	wp_send_json( array( 'success' => true, 'message' => 'Sync log cleared.' ) );
}

/**
 * Reload the accounts list
 */
function MYOB_reload_accounts_list_ajax()
{

	$connector = new Opmc_Myob_Connector();
	$connector->create_wc_log('MYOB_reload_accounts_list_ajax()');
	/* Related to saving data with ajax call */
	$nonce = isset($_POST['security']) ? sanitize_text_field(wp_unslash($_POST['security'])) : '';
	if (empty($nonce) || !wp_verify_nonce($nonce, 'opmc_myob_security')) {
		return false;
	}

	$company_file_pulldown = isset($_POST['company_file_pulldown']) ? sanitize_text_field($_POST['company_file_pulldown']) : '';
	$company_file_username = isset($_POST['company_file_username']) ? sanitize_text_field($_POST['company_file_username']) : '';
	$company_file_passwrd = isset($_POST['company_file_passwrd']) ? sanitize_text_field($_POST['company_file_passwrd']) : '';
	$current_options = get_option('woocommerce_MYOB_integrations_settings', array());

	$desired_options = array(
		'WC_MYOB_company_file_username' => $company_file_username,
		'WC_MYOB_company_file_password' => $company_file_passwrd,
		'WC_MYOB_company_file_id' => $company_file_pulldown,
	);
	$merged_options = array_merge($current_options, $desired_options);

	update_option('woocommerce_MYOB_integrations_settings', $merged_options);
	/* End */
	if ($connector) {
		$connector->reload_accounts_list();
	}
}

/**
 * For Invoice Layouts
 */
add_action('add_meta_boxes', 'so_invoice_type_meta_box');
function so_invoice_type_meta_box()
{

	add_meta_box('so_meta_box', 'MYOB Invoice Type', 'invoice_layout_meta_box', array('product'), 'side', 'high');
}

add_action('save_post', 'so_save_metabox');
function so_save_metabox($post_id)
{
	global $post;
	//Check if nonce is set
	if (!isset($_POST['myob_invoice_nonce'])) {
		return $post_id;
	}

	if (!wp_verify_nonce(!empty($_POST['myob_invoice_nonce']) ? sanitize_text_field($_POST['myob_invoice_nonce']) : '', 'save_myob_nonce')) {
		return $post_id;
	}

	if (isset($_POST['myob_invoice_layout_content'])) {
		$meta_element_class = sanitize_text_field(wp_unslash($_POST['myob_invoice_layout_content']));
		update_post_meta($post->ID, 'invoice_layout_meta_box', $meta_element_class);
	}
}

function invoice_layout_meta_box($post)
{
	$meta_element_class = get_post_meta($post->ID, 'invoice_layout_meta_box', true); //true ensures you get just one value instead of an array
	?>
	<!-- <label>Choose the size of the element :  </label> -->
	<?php wp_nonce_field('save_myob_nonce', 'myob_invoice_nonce'); ?>
	<select name="myob_invoice_layout_content" id="myob_invoice_layout_content">
		<option value="items" <?php selected($meta_element_class, 'items'); ?>>Items</option>
		<option value="service" <?php selected($meta_element_class, 'service'); ?>>Service</option>
		<option value="professional" <?php selected($meta_element_class, 'professional'); ?>>Professional</option>
	</select>
	<?php
}

/**
 * For MYOB tax code on individual product page.
 */
add_action('add_meta_boxes', 'add_product_taxcode_meta_box');
function add_product_taxcode_meta_box()
{

	add_meta_box('product_taxcode_meta_box', 'MYOB Tax Code', 'layout_product_taxcode_meta_box', 'product', 'side', 'high');
}
add_action('save_post', 'save_myob_product_taxcode_metabox');
function save_myob_product_taxcode_metabox($post_id)
{
	global $post;
	//Check if nonce is set
	if (!isset($_POST['myob_product_taxcode_nonce'])) {
		return $post_id;
	}

	if (!wp_verify_nonce(!empty($_POST['myob_product_taxcode_nonce']) ? sanitize_text_field($_POST['myob_product_taxcode_nonce']) : '', 'save_myob_product_taxcode_nonce')) {
		return $post_id;
	}

	if (isset($_POST['myob_product_taxcode_layout_content'])) {
		$meta_element_class = sanitize_text_field(wp_unslash($_POST['myob_product_taxcode_layout_content']));
		update_post_meta($post->ID, 'layout_product_taxcode_meta_box', $meta_element_class);
	}
}

function layout_product_taxcode_meta_box($post)
{

	/* UPDATED CODE BY PREY*/
	$meta_element_class = get_post_meta($post->ID, 'layout_product_taxcode_meta_box', true);
	$myob_tax_codes = get_option('WC_MYOB_tax_codes_list');
	wp_nonce_field('save_myob_product_taxcode_nonce', 'myob_product_taxcode_nonce');
	?>

	<select name="myob_product_taxcode_layout_content">
		<option value=""> Select MYOB Tax Code </option>
		<?php if (is_array($myob_tax_codes)): ?>
			<?php foreach ($myob_tax_codes as $j => $value): ?>
				<?php if ($meta_element_class == $j): ?>
					<option value="<?php echo esc_attr($j); ?>" selected> <?php echo esc_html($value); ?></option>
				<?php else: ?>
					<option value="<?php echo esc_attr($j); ?>"> <?php echo esc_html($value); ?></option>
				<?php endif; ?>
			<?php endforeach; ?>
		<?php endif; ?>
	</select>
	<?php
	/* PRE CODE END */
}


/**
 * For Income account for tracking sales on individual product page.
 */

add_action('add_meta_boxes', 'opmc_myob_product_income_account_meta_box');
function opmc_myob_product_income_account_meta_box()
{

	add_meta_box('product_income_account_meta_box', 'Income Account For Tracking Sales', 'opmc_myob_product_income_account_for_tracking_sales_meta_box', 'product', 'side', 'high');
}
add_action('save_post', 'save_product_income_account_for_tracking_sales_meta_box');
function save_product_income_account_for_tracking_sales_meta_box($post_id)
{
	global $post;
	//Check if nonce is set
	if (!isset($_POST['product_income_account_for_tracking_sales_nonce'])) {
		return $post_id;
	}

	if (!wp_verify_nonce(!empty($_POST['product_income_account_for_tracking_sales_nonce']) ? sanitize_text_field($_POST['product_income_account_for_tracking_sales_nonce']) : '', 'save_product_income_account_for_tracking_sales_nonce')) {
		return $post_id;
	}
	if (isset($_POST['opmc_myob_product_income_account_content'])) {
		$meta_element_class = sanitize_text_field(wp_unslash($_POST['opmc_myob_product_income_account_content']));
		update_post_meta($post->ID, 'opmc_myob_product_income_account_for_tracking_sales', $meta_element_class);
	}
}

function opmc_myob_product_income_account_for_tracking_sales_meta_box($post)
{

	$meta_element_class = get_post_meta($post->ID, 'opmc_myob_product_income_account_for_tracking_sales', true);
	$myob_income_account_codes = get_option('WC_MYOB_income_accounts_list');
	wp_nonce_field('save_product_income_account_for_tracking_sales_nonce', 'product_income_account_for_tracking_sales_nonce');
	?>

	<select name="opmc_myob_product_income_account_content">
		<option value=""> Select Income Account Code </option>
		<?php if (is_array($myob_income_account_codes)): ?>
			<?php foreach ($myob_income_account_codes as $j => $value): ?>
				<?php if ($meta_element_class == $j): ?>
					<option value="<?php echo esc_attr($j); ?>" selected> <?php echo esc_html($value); ?></option>
				<?php else: ?>
					<option value="<?php echo esc_attr($j); ?>"> <?php echo esc_html($value); ?></option>
				<?php endif; ?>
			<?php endforeach; ?>
		<?php endif; ?>
	</select>
	<?php
}

//============BULK ACTIONS================//
add_action('woocommerce_product_bulk_edit_start', 'bbloomer_custom_field_bulk_edit_input');

function bbloomer_custom_field_bulk_edit_input()
{
	?>
	<div class="inline-edit-group">
		<label class="alignleft">
			<span class="title"><?php esc_attr_e('MYOB Tax Code ', 'woocommerce'); ?></span>
			<span class="input-text-wrap">
				<select class="custom_field" name="custom_field">
					<?php
					echo '<option value="">— No change —</option>';
					$myob_tax_codes = get_option('WC_MYOB_tax_codes_list');
					foreach ($myob_tax_codes as $key => $value) {
						echo '<option value="' . esc_attr($key) . '">' . esc_attr($value) . '</option>';
					}
					?>
				</select>
			</span>
		</label>
	</div>
	<?php
}

add_action('woocommerce_product_bulk_edit_save', 'bbloomer_custom_field_bulk_edit_save');

function bbloomer_custom_field_bulk_edit_save($product)
{
	$post_id = $product->get_id();
	if (isset($_REQUEST['custom_field']) && '' != $_REQUEST['custom_field']) {
		$custom_field = sanitize_text_field($_REQUEST['custom_field']);
		update_post_meta($post_id, 'layout_product_taxcode_meta_box', wc_clean($custom_field));
	}
}
//============BULK ACTIONS END==============//

/*
 * For myob_sync indicator product list page
 */
// ADDING A CUSTOM COLUMN TITLE TO ADMIN PRODUCTS LIST
add_filter('manage_edit-product_columns', 'custom_product_column', 15);
function custom_product_column($columns)
{

	//add columns
	$columns['myob_sync'] = __('MYOB Status', 'myob-integration');
	$columns['myob_product_tax'] = __('MYOB Tax Code', 'myob-integration');

	return $columns;
}

add_action('admin_head', 'myob_product_column_width');
function myob_product_column_width()
{
	echo '<style type="text/css">';
	echo 'table.wp-list-table .column-myob_product_tax { width: 15%; text-align: left!important;}';
	echo 'table.wp-list-table .column-myob_sync { width: 15%; text-align: left!important;}';
	echo '</style>';
}

// ADDING THE DATA FOR EACH PRODUCTS BY COLUMN (EXAMPLE)
add_action('manage_product_posts_custom_column', 'custom_product_list_column_content', 10, 2);
function custom_product_list_column_content($column, $product_id)
{

	global $post;
	$connector = new Opmc_Myob_Connector();
	// HERE get the data from your custom field (set the correct meta key below)
	$sync_data = get_post_meta($product_id, 'is_synced', true);


	if (!empty($sync_data) && 'synced' == $sync_data) {
		$sync = 'Synced';
	} else {
		$sync = 'Not Synced';
	}


	$myob_tax_code_for_product = get_post_meta($product_id, 'layout_product_taxcode_meta_box', true);

	$myob_tax_code_display_string = '';
	if (null != $myob_tax_code_for_product) {
		$myob_tax_code_display_string = retrieve_myob_tax_code_for_display($myob_tax_code_for_product);
	}

	switch ($column) {

		case 'myob_sync':
			echo esc_attr($sync);
			break;
		case 'myob_product_tax':
			echo esc_attr($myob_tax_code_display_string);
			break;
	}
}

function retrieve_myob_tax_code_for_display($product_myob_taxcode)
{

	$myob_tax_code_display_string = '';
	$connector = new Opmc_Myob_Connector();
	$myob_tax_codes = get_option('WC_MYOB_tax_codes_list');

	foreach ($myob_tax_codes as $taxcode_uid => $taxcode_name) {
		//$connector->create_wc_log("For $taxcode_name: check if $product_myob_taxcode matches $taxcode_uid");
		if ($taxcode_uid == $product_myob_taxcode) {
			$myob_tax_code_display_string = $taxcode_name;
			$connector->create_wc_log("MATCH FOUND! Setting myob_tax_code_display_string to $myob_tax_code_display_string");
		}
	}

	return $myob_tax_code_display_string;
}

/**
 * For myob_sync indicator individual product page
 */
add_action('add_meta_boxes', 'so_sync_p_meta_box');
function so_sync_p_meta_box()
{

	global $post;
	add_meta_box('so_sync_meta_box', 'MYOB Sync Status', 'sync_myob_indicate_meta_box', 'product', 'side', 'high');
}

function sync_myob_indicate_meta_box($post)
{

	$meta_element_class = get_post_meta($post->ID, 'is_synced', true); //true ensures you get just one value instead of an array

	if (!empty($meta_element_class) && 'synced' == $meta_element_class) {

		$sync = 'Synced';

	} else {

		$sync = 'Not Synced';
	}
	echo esc_attr($sync);
}

add_action('admin_notices', 'myob_limits_notice');
function myob_limits_notice()
{
	$screen = get_current_screen();

	if ('product' == $screen->post_type) {
		global $post;
		$product_id = $post->ID;
		if ($post->ID) {
			$product = wc_get_product($product_id);
			if (isset($product)) {
				$sku = $product->get_sku();
				$title = $product->get_title();
				$title_count = strlen($title);
				$sku_count = strlen($sku);
				if (30 < $sku_count || 30 < $title_count) {
					?>
					<div class="notice is-dismissible notice-warning">
						<p><?php esc_html_e("Alert: This product may get trouble in syncing with MyOb, because it has Product Title/SKU longer than 30 characters which MyOb doesn't support.", 'myob-integration'); ?>
						</p>
					</div>
					<?php
				}
			}
		}
	}
}

/**
 * For sync Woo products to MYOB on bulk bulk action
 */
add_filter('bulk_actions-edit-product', 'bulk_actions_sync_product_to_myob', 20, 1);

function bulk_actions_sync_product_to_myob($actions)
{
	$config = get_option('woocommerce_MYOB_integrations_settings');
	$bulk_p_sync = isset($config['WC_OPMC_enable_product_bulk_action']) ? $config['WC_OPMC_enable_product_bulk_action'] : 'no';

	if ('yes' == $bulk_p_sync) {

		$actions['sync_to_myob'] = __('Sync To MYOB', 'myob-integration');
	}
	return $actions;
}

add_filter('handle_bulk_actions-edit-product', 'handle_bulk_actions_sync_product_to_myob', 10, 3);

function handle_bulk_actions_sync_product_to_myob($redirect_to, $action, $post_ids)
{

	if ('sync_to_myob' !== $action) {
		return $redirect_to; // Exit
	}


	$processed_ids = array();
	$connector = new Opmc_Myob_Connector();

	if ($connector) {

		/* PLUGINS-2273 */
		foreach ($post_ids as $post_id) {

			$product = wc_get_product($post_id);
			$current_status = get_post_status($post_id);

			if ('draft' != $current_status && $product->is_type('variable') && '' != $product->get_sku()) {

				if ('yes' == $support_variation_product) {
					$processed_ids[] = $post_id;
				}

			} else {

				if ('draft' != $current_status && '' != $product->get_sku() && null != $product->get_regular_price()) {

					$processed_ids[] = $post_id;
				}

			}
		} /* PLUGINS-2273 End */

		$connector->verify_woo_inventory_items($processed_ids);
	}

	$redirect_to = add_query_arg(
		array(
			'sync_to_myob' => '1',
			'processed_count' => count($processed_ids),
			'processed_ids' => implode(',', $processed_ids),
		),
		$redirect_to
	);

	return $redirect_to;
}

// The results notice from bulk action on orders
add_action('admin_notices', 'downloads_bulk_action_admin_notice');
function downloads_bulk_action_admin_notice()
{
	if (empty($_REQUEST['sync_to_myob'])) {
		return; // Exit
	}

	//$count = intval( $_REQUEST['processed_count'] );

	printf('<div id="message" class="updated fade"><p>' .
		esc_attr(
			'Products Successfully Synced to MYOB..',
			'Products Successfully Synced to MYOB.',
			'write_downloads'
		) . '</p></div>');
}

/*
 * TEMP DEBUG BLOCK FOR ZERO PRICE ALERT MAIL FLOW.
 * View command:   Get-Content .\stars-zero-price-alert-debug.log -Tail 200
 * Follow command: Get-Content .\stars-zero-price-alert-debug.log -Wait
 * Cleanup command: Remove-Item .\stars-zero-price-alert-debug.log
 * Remove this block after debugging.
 */
if ( ! function_exists( 'opmc_zero_price_debug_log' ) ) {
	function opmc_zero_price_debug_log( $event, $context = array() ) {
		$log_file = __DIR__ . DIRECTORY_SEPARATOR . 'stars-zero-price-alert-debug.log';
		$line     = '[' . gmdate( 'Y-m-d H:i:s' ) . ' UTC] ' . $event;

		if ( ! empty( $context ) ) {
			$encoded = wp_json_encode( $context );
			if ( false !== $encoded ) {
				$line .= ' | ' . $encoded;
			}
		}

		$line .= PHP_EOL;
		@file_put_contents( $log_file, $line, FILE_APPEND );
	}
}

if ( ! function_exists( 'opmc_zero_price_mail_failed_debug' ) ) {
	function opmc_zero_price_mail_failed_debug( $wp_error ) {
		$data = $wp_error instanceof WP_Error ? $wp_error->get_error_data() : array();
		opmc_zero_price_debug_log(
			'wp_mail_failed',
			array(
				'error_messages' => $wp_error instanceof WP_Error ? $wp_error->get_error_messages() : array(),
				'data' => $data,
			)
		);
	}
}
add_action( 'wp_mail_failed', 'opmc_zero_price_mail_failed_debug', 10, 1 );




function opmc_sync_single_product_from_myob_by_sku( $product_sku ) {

    $connector = new Opmc_Myob_Connector();
    opmc_zero_price_debug_log( 'sync_single_product_start', array( 'sku' => $product_sku ) );

    if ( empty( $product_sku ) ) {
        opmc_zero_price_debug_log( 'sync_single_product_exit_empty_sku' );
        throw new Exception( 'Empty product SKU' );
    }

    $myob_item = $connector->get_single_product_with_sku( $product_sku );
    opmc_zero_price_debug_log(
        'myob_item_loaded',
        array(
            'sku' => $product_sku,
            'uid' => isset( $myob_item->UID ) ? (string) $myob_item->UID : '',
            'has_quantity_available' => isset( $myob_item->QuantityAvailable ),
            'has_quantity_on_hand' => isset( $myob_item->QuantityOnHand ),
        )
    );

    if ( empty( $myob_item->UID ) ) {
        opmc_zero_price_debug_log( 'sync_single_product_exit_no_myob_uid', array( 'sku' => $product_sku ) );
        throw new Exception( 'MYOB item UID not found' );
    }

    $matrix_info = $connector->get_product_metrix_info( $myob_item->UID );
    opmc_zero_price_debug_log(
        'matrix_loaded',
        array(
            'sku' => $product_sku,
            'matrix_type' => gettype( $matrix_info ),
            'has_selling_prices_prop' => isset( $matrix_info->SellingPrices ),
            'selling_prices_type' => isset( $matrix_info->SellingPrices ) ? gettype( $matrix_info->SellingPrices ) : 'missing',
            'selling_prices_count' => ( isset( $matrix_info->SellingPrices ) && is_array( $matrix_info->SellingPrices ) ) ? count( $matrix_info->SellingPrices ) : 0,
        )
    );

    if ( empty( $matrix_info->SellingPrices ) ) {
        opmc_zero_price_debug_log( 'sync_single_product_exit_no_selling_prices', array( 'sku' => $product_sku ) );
        throw new Exception( 'No SellingPrices found' );
    }

    $product_id = wc_get_product_id_by_sku( $product_sku );
    opmc_zero_price_debug_log( 'woo_product_lookup', array( 'sku' => $product_sku, 'product_id' => $product_id ) );

    if ( ! $product_id ) {
        opmc_zero_price_debug_log( 'sync_single_product_exit_product_not_found', array( 'sku' => $product_sku ) );
        throw new Exception( 'Woo product not found' );
    }

    $product = wc_get_product( $product_id );
    opmc_zero_price_debug_log(
        'woo_product_loaded',
        array(
            'sku' => $product_sku,
            'product_id' => $product_id,
            'product_type' => $product ? $product->get_type() : 'missing',
            'product_status' => $product ? $product->get_status() : 'missing',
        )
    );

    if ( ! $product ) {
        opmc_zero_price_debug_log( 'sync_single_product_exit_product_could_not_load', array( 'sku' => $product_sku, 'product_id' => $product_id ) );
        throw new Exception( 'Woo product could not be loaded' );
    }

    if ( isset( $myob_item->QuantityAvailable ) ) {
        $qty = isset( $myob_item->QuantityAvailable ) ? (int) $myob_item->QuantityAvailable : 0;
        $connector->create_wc_log( '[Single Product Sync] QuantityAvailable => ' . $qty . ' | SKU=' . $product_sku );
    } elseif ( isset( $myob_item->QuantityOnHand ) ) {
        $qty = isset( $myob_item->QuantityOnHand ) ? (int) $myob_item->QuantityOnHand : 0;
        $connector->create_wc_log( '[Single Product Sync] QuantityOnHand => ' . $qty . ' | SKU=' . $product_sku );
    } else {
        $qty = 0;
        $connector->create_wc_log( '[Single Product Sync] Quantity not found in MYOB item, defaulting to 0 | SKU=' . $product_sku );
    }

    $product->set_stock_quantity( $qty );

    $zero_price_tiers = opmc_get_zero_price_tiers( $matrix_info );
    opmc_zero_price_debug_log(
        'zero_price_tiers_evaluated',
        array(
            'sku' => $product_sku,
            'zero_tiers_count' => is_array( $zero_price_tiers ) ? count( $zero_price_tiers ) : 0,
            'zero_tiers' => $zero_price_tiers,
        )
    );
    if ( ! empty( $zero_price_tiers ) ) {
        opmc_handle_zero_price_alert( $product, $product_sku, $myob_item, $connector, $zero_price_tiers );
    } else {
        opmc_restore_status_after_zero_price_alert( $product, $product_sku, $connector );
    }

    foreach ( $matrix_info->SellingPrices as $row ) {
        if ( (int) $row->QuantityOver === 0 && isset( $row->Levels->LevelA ) ) {
            $price = wc_format_decimal( $row->Levels->LevelA, 2 );
            $product->set_regular_price( $price );
            // Sale price NOT cleared - preserved from WooCommerce
            // Only update active price if no sale price is set
            if ( '' === $product->get_sale_price() ) {
                $product->set_price( $price );
            }
            $product->save();
            break;
        }
    }

    opmc_update_tier_pricing_from_matrix( $product_id, $matrix_info );
    opmc_zero_price_debug_log( 'sync_single_product_end', array( 'sku' => $product_sku, 'product_id' => $product_id ) );

    return true;
}

function opmc_get_zero_price_tiers( $matrix ) {
    opmc_zero_price_debug_log(
        'get_zero_price_tiers_start',
        array(
            'matrix_type' => gettype( $matrix ),
            'has_selling_prices_prop' => isset( $matrix->SellingPrices ),
            'selling_prices_type' => isset( $matrix->SellingPrices ) ? gettype( $matrix->SellingPrices ) : 'missing',
        )
    );

    if ( empty( $matrix->SellingPrices ) || ! is_array( $matrix->SellingPrices ) ) {
        opmc_zero_price_debug_log(
            'get_zero_price_tiers_exit_non_array_or_empty',
            array(
                'is_empty' => empty( $matrix->SellingPrices ),
                'is_array' => isset( $matrix->SellingPrices ) ? is_array( $matrix->SellingPrices ) : false,
                'selling_prices_type' => isset( $matrix->SellingPrices ) ? gettype( $matrix->SellingPrices ) : 'missing',
            )
        );
        return [];
    }

    $zero_tiers = [];
    foreach ( $matrix->SellingPrices as $row ) {
        if ( ! isset( $row->QuantityOver, $row->Levels ) || ! is_object( $row->Levels ) ) {
            continue;
        }
        foreach ( $row->Levels as $level => $value ) {
            if ( $value === null ) {
                continue;
            }
            $price = (float) $value;
            if ( $price <= 0 ) {
                $zero_tiers[] = [
                    'level' => (string) $level,
                    'quantity_over' => (int) $row->QuantityOver,
                    'price' => wc_format_decimal( $price, 2 ),
                ];
            }
        }
    }

    opmc_zero_price_debug_log( 'get_zero_price_tiers_end', array( 'zero_tiers_count' => count( $zero_tiers ), 'zero_tiers' => $zero_tiers ) );
    return $zero_tiers;
}

function opmc_handle_zero_price_alert( $product, $product_sku, $myob_item, $connector, $zero_tiers ) {
    opmc_zero_price_debug_log(
        'handle_zero_price_alert_start',
        array(
            'sku' => $product_sku,
            'product_is_wc_product' => ( $product instanceof WC_Product ),
            'zero_tiers_count' => is_array( $zero_tiers ) ? count( $zero_tiers ) : 0,
        )
    );

    if ( ! $product instanceof WC_Product ) {
        opmc_zero_price_debug_log( 'handle_zero_price_alert_exit_not_wc_product', array( 'sku' => $product_sku ) );
        return;
    }

    if ( empty( $zero_tiers ) || ! is_array( $zero_tiers ) ) {
        opmc_zero_price_debug_log( 'handle_zero_price_alert_exit_empty_or_non_array_tiers', array( 'sku' => $product_sku ) );
        return;
    }

    $product_id   = $product->get_id();
    $product_name = $product->get_name();
    $product_type = $product->get_type();
    $parent_id    = $product->get_parent_id();
    $myob_uid     = isset( $myob_item->UID ) ? (string) $myob_item->UID : '';

    foreach ( $zero_tiers as $tier ) {
        $connector->create_wc_log(
            '[Zero Price Alert] SKU=' . $product_sku .
            ' | Product ID=' . $product_id .
            ' | Level=' . ( $tier['level'] ?? '' ) .
            ' | QtyOver=' . ( $tier['quantity_over'] ?? '' ) .
            ' | Price=' . ( $tier['price'] ?? '' ) .
            ' | Type=' . $product_type .
            ' | Parent ID=' . $parent_id .
            ' | MYOB UID=' . $myob_uid
        );
    }

    $target_status      = '';
    $status_action_note = '';

    if ( $product->is_type( 'variation' ) ) {
        // Variations use "private" post status when disabled in WooCommerce.
        $target_status      = 'private';
        $status_action_note = 'disabled';
    } elseif ( $product->is_type( 'simple' ) ) {
        $target_status      = 'draft';
        $status_action_note = 'draft';
    }

    if ( ! empty( $target_status ) ) {
        $current_status = $product->get_status();
        if ( $current_status !== $target_status ) {
            $stored_previous_status = $product->get_meta( '_opmc_pre_zero_price_status', true );
            if ( empty( $stored_previous_status ) ) {
                $product->update_meta_data( '_opmc_pre_zero_price_status', $current_status );
            }

            $product->set_status( $target_status );
            $product->save();

            $connector->create_wc_log(
                '[Zero Price Alert Action] SKU=' . $product_sku .
                ' | Product ID=' . $product_id .
                ' | Type=' . $product_type .
                ' | Status changed from ' . $current_status . ' to ' . $status_action_note
            );
            opmc_zero_price_debug_log(
                'handle_zero_price_alert_status_changed',
                array(
                    'sku' => $product_sku,
                    'product_id' => $product_id,
                    'from_status' => $current_status,
                    'to_status' => $target_status,
                )
            );
        }
    }

    $admin_email = get_option( 'admin_email' );
    opmc_zero_price_debug_log( 'handle_zero_price_alert_admin_email', array( 'sku' => $product_sku, 'admin_email' => $admin_email ) );
    if ( empty( $admin_email ) ) {
        opmc_zero_price_debug_log( 'handle_zero_price_alert_exit_empty_admin_email', array( 'sku' => $product_sku ) );
        return;
    }

    $site_name = get_bloginfo( 'name' );
    $subject   = 'MYOB Sync Alert: Zero price for SKU ' . $product_sku;

    $rows = array(
        'Site' => $site_name,
        'Product Name' => $product_name,
        'SKU' => $product_sku,
        'Product ID' => $product_id,
        'Product Type' => $product_type,
        'Parent ID' => $parent_id ? $parent_id : '-',
        'MYOB UID' => $myob_uid ? $myob_uid : '-',
        'Detected At' => current_time( 'mysql' ),
    );

    $table_rows = '';
    foreach ( $rows as $label => $value ) {
        $table_rows .= '<tr>' .
            '<th style="text-align:left;border:1px solid #ddd;padding:6px;background:#f7f7f7;">' . esc_html( $label ) . '</th>' .
            '<td style="border:1px solid #ddd;padding:6px;">' . esc_html( (string) $value ) . '</td>' .
        '</tr>';
    }

    $tiers_table_rows = '';
    foreach ( $zero_tiers as $tier ) {
        $tiers_table_rows .= '<tr>' .
            '<td style="border:1px solid #ddd;padding:6px;">' . esc_html( (string) ( $tier['level'] ?? '' ) ) . '</td>' .
            '<td style="border:1px solid #ddd;padding:6px;">' . esc_html( (string) ( $tier['quantity_over'] ?? '' ) ) . '</td>' .
            '<td style="border:1px solid #ddd;padding:6px;">' . esc_html( (string) ( $tier['price'] ?? '' ) ) . '</td>' .
        '</tr>';
    }

    $message  = '<p>Zero price detected during MYOB product sync.</p>';
    $message .= '<table style="border-collapse:collapse;width:100%;">' . $table_rows . '</table>';
    $message .= '<br /><table style="border-collapse:collapse;width:100%;">' .
        '<thead><tr>' .
        '<th style="text-align:left;border:1px solid #ddd;padding:6px;background:#f7f7f7;">Tier</th>' .
        '<th style="text-align:left;border:1px solid #ddd;padding:6px;background:#f7f7f7;">Quantity Over</th>' .
        '<th style="text-align:left;border:1px solid #ddd;padding:6px;background:#f7f7f7;">Price</th>' .
        '</tr></thead><tbody>' . $tiers_table_rows . '</tbody></table>';

//     $headers = array( 'Content-Type: text/html; charset=UTF-8' );
//     $mail_sent = wp_mail( $admin_email, $subject, $message, $headers );
    opmc_zero_price_debug_log(
        'handle_zero_price_alert_mail_attempt',
        array(
            'sku' => $product_sku,
            'to' => $admin_email,
            'subject' => $subject,
            'mail_sent' => (bool) $mail_sent,
        )
    );
	return; // Temporarily disable email sending until we have a better strategy to avoid spamming admins in case of sync issues.
}

function opmc_restore_status_after_zero_price_alert( $product, $product_sku, $connector ) {
    opmc_zero_price_debug_log(
        'restore_status_after_zero_price_alert_start',
        array(
            'sku' => $product_sku,
            'product_is_wc_product' => ( $product instanceof WC_Product ),
        )
    );

    if ( ! $product instanceof WC_Product ) {
        opmc_zero_price_debug_log( 'restore_status_exit_not_wc_product', array( 'sku' => $product_sku ) );
        return;
    }

    $previous_status = $product->get_meta( '_opmc_pre_zero_price_status', true );
    if ( empty( $previous_status ) ) {
        opmc_zero_price_debug_log( 'restore_status_exit_no_previous_status', array( 'sku' => $product_sku ) );
        return;
    }

    $current_status = $product->get_status();
    if ( $current_status !== $previous_status ) {
        $product->set_status( $previous_status );
    }

    $product->delete_meta_data( '_opmc_pre_zero_price_status' );
    $product->save();

    $connector->create_wc_log(
        '[Zero Price Alert Action] SKU=' . $product_sku .
        ' | Product ID=' . $product->get_id() .
        ' | Status restored to ' . $previous_status
    );
    opmc_zero_price_debug_log(
        'restore_status_after_zero_price_alert_end',
        array(
            'sku' => $product_sku,
            'product_id' => $product->get_id(),
            'restored_status' => $previous_status,
        )
    );
}


function opmc_sync_all_customers_from_myob()
{
	$connector = new Opmc_Myob_Connector();
	$connector->create_wc_log('===== CRON: WC → MYOB CUSTOMER SYNC START =====');
	$connector->create_sync_log( 'Customer level sync started.', 'INFO' );

	$level_roles = ['LevelA', 'LevelB', 'LevelC', 'LevelD', 'LevelE', 'LevelF'];

	$users = get_users([
		'role__in' => ['customer', 'LevelA', 'LevelB', 'LevelC', 'LevelD', 'LevelE', 'LevelF'],
		'fields' => ['ID', 'user_email'],
	]);

	foreach ($users as $user) {

		if (empty($user->user_email)) {
			continue;
		}

		try {
			$myob_customer = $connector->get_customer_with_email($user->user_email);
			if (!$myob_customer) {
				continue;
			}

			$customer = is_array($myob_customer) ? $myob_customer : (array) $myob_customer;
			$myob_customer_id = $customer['UID'] ?? '';

			$selling_details = $customer['SellingDetails'] ?? null;
			$selling_details = is_object($selling_details) ? (array) $selling_details : $selling_details;

			if (empty($selling_details)) {
				continue;
			}

			$terms = $selling_details['Terms'] ?? null;
			$terms = is_object($terms) ? (array) $terms : $terms;

			$item_price_level = $selling_details['ItemPriceLevel'] ?? '';
			$payment_due = $terms['PaymentIsDue'] ?? '';

			if ($myob_customer_id !== '') {
				update_user_meta($user->ID, 'myob_customer_id', $myob_customer_id);
			}

			if ($payment_due !== '') {
				update_user_meta($user->ID, 'myob_payment_terms', $payment_due);
			}

			$target_role = str_replace(' ', '', $item_price_level);

			if (in_array($target_role, $level_roles, true)) {

				$wp_user = get_user_by('id', $user->ID);

				foreach ($wp_user->roles as $role) {
					if ($role === 'customer') {
						$wp_user->remove_role($role);
						continue;
					}
					if (in_array($role, $level_roles, true) && $role !== $target_role) {
						$wp_user->remove_role($role);
					}
				}

				if (!in_array($target_role, $wp_user->roles, true)) {
					$wp_user->add_role($target_role);
				}
			}

			$connector->create_wc_log(
				'[CRON] ' . $user->user_email .
				' | UID=' . $myob_customer_id .
				' | Level=' . $item_price_level .
				' | Payment=' . $payment_due
			);
			$connector->create_sync_log( '[Customer] ' . $user->user_email . ' | Level=' . $item_price_level . ' | Payment=' . $payment_due, 'SUCCESS' );

		} catch (Exception $e) {
			$connector->create_wc_log(
				'[CRON ERROR] ' . $user->user_email . ' | ' . $e->getMessage()
			);
			$connector->create_sync_log( '[Customer Error] ' . $user->user_email . ' | ' . $e->getMessage(), 'ERROR' );
		}
	}

	$connector->create_wc_log('===== CRON: WC → MYOB CUSTOMER SYNC END =====');
	$connector->create_sync_log( 'Customer level sync completed.', 'INFO' );
}

function opmc_myob_single_product_sync_cron() {

    if ( get_transient( 'opmc_myob_sync_lock' ) ) {
        return;
    }

    set_transient( 'opmc_myob_sync_lock', 1, 50 );

    $batch_size = 5;
    $offset     = (int) get_option( 'opmc_myob_product_sync_offset', 0 );
    $connector  = new Opmc_Myob_Connector();

    $connector->create_wc_log('[Cron Start] Offset=' . $offset);

    $total_products = wc_get_products([
        'limit'      => -1,
        'return'     => 'ids',
        'status'     => ['publish'],
        'type'       => ['simple', 'variation'],
    ]);


    $total_count = count( $total_products );

    if ( $total_count === 0 ) {
        delete_transient( 'opmc_myob_sync_lock' );
        return;
    }

    if ( $offset >= $total_count ) {
        $offset = 0;
        update_option( 'opmc_myob_product_sync_offset', 0 );
    }

    $product_ids = wc_get_products([
        'limit'   => $batch_size,
        'offset'  => $offset,
        'orderby' => 'ID',
        'order'   => 'ASC',
        'return'  => 'ids',
        'status'  => ['publish'],
        'type'    => ['simple', 'variation'],
    ]);


    foreach ( $product_ids as $product_id ) {

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            continue;
        }

        $sku = $product->get_sku();

        $connector->create_wc_log(
            '[Cron Sync] Product ID=' . $product_id . ' | SKU=' . $sku
        );

        if ( empty( $sku ) ) {
            continue;
        }

        try {

            $connector->create_wc_log(
                '[Cron Found] Woo Product ID=' . $product_id . ' | SKU=' . $sku
            );

            opmc_sync_single_product_from_myob_by_sku( $sku );

            $connector->create_wc_log(
                '[Cron Success] Synced SKU=' . $sku
            );
            $connector->create_sync_log( '[Product] Synced SKU=' . $sku . ' | ID=' . $product_id, 'SUCCESS' );

        } catch ( Exception $e ) {

            $connector->create_wc_log(
                '[Cron Failed] SKU=' . $sku . ' | Reason=' . $e->getMessage()
            );
            $connector->create_sync_log( '[Product Error] SKU=' . $sku . ' | ' . $e->getMessage(), 'ERROR' );
        }
    }

    update_option(
        'opmc_myob_product_sync_offset',
        $offset + $batch_size
    );

    $connector->create_wc_log(
        '[Cron End] New Offset=' . ( $offset + $batch_size )
    );

    delete_transient( 'opmc_myob_sync_lock' );
}





