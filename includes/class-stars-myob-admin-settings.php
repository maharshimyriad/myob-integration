<?php
/**
 *  Stars MYOB AccountRight Connector for WooCommerce.
 *
 * @package   Stars_MYOB_Connector
 */

require_once __DIR__ . '/opmc/class-stars-logger.php';
require_once __DIR__ . '/class-stars-myob-connector.php';

if (!class_exists('WC_MYOB_Integrations_Settings')):
	#[\AllowDynamicProperties]
	class WC_MYOB_Integrations_Settings extends WC_Integration
	{
		/**
		 * Init and hook in the integration.
		 */
		private $client_id = '';
		private $connector = null;
		private $company_file_list = null;
		private $income_accounts_list = null;

		public function __construct()
		{
			Opmc_Logger::trace('Creating Settings Object');

			$this->id = 'myob_integrations';
			$this->method_title = __('MYOB AccountRight', 'stars-myob-accountright-connector-for-woocommerce');
			$this->method_description = __('Stars MYOB AccountRight Connector for WooCommerce');
			// Load the settings.
			$this->init_settings();

			$config = get_option('woocommerce_MYOB_integrations_settings');

			// Define user set variables.
			$this->company_file_id = $this->get_option('WC_MYOB_company_file_id');

			$this->MYOB_tax_code_for_new_products = $this->get_option('WC_MYOB_tax_code_new_products');
			$this->MYOB_freight_tax_code = $this->get_option('WC_MYOB_freight_tax_code');
			$this->MYOB_income_account = $this->get_option('WC_MYOB_income_account');

			$this->asset_account = $this->get_option('WC_MYOB_asset_account');
			$this->cogs_account = $this->get_option('WC_MYOB_cogs_account');


			$this->customer_id_prefix = $this->get_option('WC_MYOB_customer_id_prefix');
			$this->guest_customer_display_id = $this->get_option('WC_MYOB_guest_customer_display_id');

			// Actions.
			add_action('woocommerce_update_options_integration_' . $this->id, array($this, 'process_admin_options'));
			// General.
			add_action('admin_notices', array($this, 'admin_notices'));
			// Filters.
			add_filter('woocommerce_settings_api_sanitized_fields_' . $this->id, array($this, 'sanitize_settings'));


			// Init Connector with reference to this object
			$this->connector = new Opmc_Myob_Connector($this);

			// any of the setting parameters that are retrieved via AJAX are stored
			// in transients in order to not overload the MYOB API


			$this->company_files = get_option('WC_MYOB_company_file_list');
			$this->income_accounts = get_option('WC_MYOB_income_accounts_list');
			$this->cogs_accounts = get_option('WC_MYOB_cogs_accounts_list');
			//$this->expense_accounts = $this->transient('WC_MYOB_expense_accounts_list', array($this->connector, 'get_expense_accounts'));
			$this->asset_accounts = get_option('WC_MYOB_asset_accounts_list');

			// TODO re-write to pull from local DB
			$this->tax_codes = get_option('WC_MYOB_tax_codes_list');
			$this->job_codes = get_option('WC_MYOB_job_codes_list');

			$this->init_form_fields();

			$this->access_token = get_option('MYOB_access_token');
			$this->client_id = get_option('WC_MYOB_client_id');
			$this->http_timeout = 60;
			$this->endpoint = 'https://api.myob.com/accountright/';
			$this->full_endpoint = 'https://api.myob.com/accountright/' . $this->company_file_id;
			$this->WC_MYOB_company_file_password = isset($config['WC_MYOB_company_file_password']) ? trim($config['WC_MYOB_company_file_password']) : '';
			/* initiation of logging instance */
			//$this->log = new WC_Logger();
		}

		public function process_admin_options()
		{

			$nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
			if (empty($nonce) || !wp_verify_nonce($nonce, 'woocommerce-settings')) {
				return false;
			}

			if (isset($_POST['woocommerce_myob_integrations_WC_MYOB_company_file_username'])) {
				$new_file_name = trim(sanitize_text_field($_POST['woocommerce_myob_integrations_WC_MYOB_company_file_username']));
			}

			$setting = get_option('woocommerce_MYOB_integrations_settings');
			if (isset($setting['WC_MYOB_company_file_username'])) {
				$old_file_name = $setting['WC_MYOB_company_file_username'];
			}

			if ($old_file_name != $new_file_name) {

				$WC_MYOB_company_file_username = $new_file_name ? $new_file_name : $old_file_name;
				$cftoken = base64_encode($WC_MYOB_company_file_username . ':' . $this->WC_MYOB_company_file_password);

				$headers = array(
					'Authorization' => 'Bearer ' . $this->access_token,
					'x-myobapi-key' => $this->client_id,
					'Accept-Encoding' => 'gzip,deflate',
					'x-myobapi-version' => 'v2',
					'scope' => 'CompanyFile',
					'Content-Type' => 'application/json',
					'x-myobapi-cftoken' => $cftoken,
				);

				$filter = 'IsActive eq true';
				$params = null;
				$params = array('$filter' => urlencode($filter));

				$param_list = '';

				if (null !== $params) {
					$param_list = '?';

					foreach ($params as $key => $value) {
						$param_list .= $key . '=' . $value;
						$param_list .= '&';
					}

					$param_list = rtrim($param_list, '&');
				}

				$uri = $this->full_endpoint . '/GeneralLedger/Job';

				$get_result = wp_remote_get($uri . $param_list, array(
					'headers' => $headers,
					'timeout' => $this->http_timeout,
				));

				if (is_wp_error($get_result)) {
					$this->connector->create_wc_log('[MYOB API Request] [Error] [MYOB is_wp_error on get: ' . $get_result->get_error_message() . ']');
					$this->connector->create_wc_log(print_r($get_result, true));

					$error_string = $get_result->get_error_message();
					$error_string = esc_html($error_string);
					$exception_message = "Oops! We had a problem processing your order.  $error_string";
					throw new Opmc_Myob_Exception(esc_html($exception_message));
				}

				// handle HTTP error or record not found
				$resp_code = $get_result['response']['code'];

				if (401 == $resp_code) {

					$x = get_option('WC_MYOB_api_unauthorized_count');
					update_option('WC_MYOB_api_unauthorized_count', $x + 1);

					$old_file_name = $new_file_name;
				} else {
					$x = get_option('WC_MYOB_api_unauthorized_count');
					if (0 < $x) {
						update_option('WC_MYOB_api_unauthorized_count', 0);
					}
				}
			}

			parent::process_admin_options();
		}


		/**
		 * Wrapper for WP transients
		 */
		public function transient($key, $func)
		{

			Opmc_Logger::trace("transient $key");
			$x = get_transient($key);
			if (false === $x || !is_array($x) || (isset($x[0]) && empty($x[0]))) {
				$x = $func();
				if (is_countable($x) && count($x) > 0) {
					Opmc_Logger::trace('getting from rest');
					set_transient($key, $x, 3600);
				}
			} else {
				Opmc_Logger::trace('getting from cache count is ' . count($x));
			}

			return $x;
		}

		public function generate_custom_settings_html($form_fields, $echo = true)
		{

			if (empty($form_fields)) {
				$form_fields = $this->get_form_fields();
			}
			$first_tab_fields = array();
			$second_tab_fields = array();
			foreach ($form_fields as $key => $form_field) {
				if (isset($form_field['tab']) && 1 == $form_field['tab']) {
					$first_tab_fields[$key] = $form_field;
				} else {
					$second_tab_fields[$key] = $form_field;
				}
			}
			include_once WC_MYOB_INTEGRATION_PLUGINDIR . '/includes/opmc/template-stars-admin-settings.php';
		}

		public function admin_options()
		{
			$this->generate_custom_settings_html($this->get_form_fields(), false);
		}

		/**
		 * Initialize integration settings form fields.
		 *
		 * @return void
		 */
		public function init_form_fields()
		{
			$invoice_type = array(
				'items' => 'Items',
				'service' => 'Service',
				'professional' => 'Professional',
			);

			$sync_type = array(
				'available' => 'Available',
				'stockonhand' => 'Stock on Hand',
			);

			$this->form_fields = array(
				'allow_access' => array(
					'title' => __('Validate Access', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'button',
					'custom_attributes' => array(
						'onclick' => "location.href='" . $this->assemble_myob_auth_url() . "'",
					),
					'description' => __('Click the button to validate (or re-validate) your access from WooCommerce to MYOB.'),
					'desc' => true,
					'desc_tip' => false,
					'tab' => 1,
				),

				'WC_MYOB_company_file_username' => array(
					'title' => __('Company File Username', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'text',
					'description' => __('Enter company file username, save changes, then click on the "Reload Accounts List" button.'),
					'desc' => true,
					'desc_tip' => __('This is the main credential for your MYOB company file, which is usually ‘Admin’ by default and is not the same as your MYOB login. This field is required for this plugin to connect with MYOB’s API and function.'),
					'default' => '',
					'tab' => 1,
				),

				'WC_MYOB_company_file_password' => array(
					'title' => __('Company File Password (optional)', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'password',
					'description' => __('Add company file password (optional)'),
					'desc' => true,
					'desc_tip' => __('This is an optional credential for your MYOB company file (usually separate from your normal MYOB credentials).'),
					'default' => '',
					'tab' => 1,
				),

				'WC_MYOB_company_file_id' => array(
					'title' => __('Company File Pulldown', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'select',
					'description' => __('Set MYOB Company File ID.'),
					'desc' => true,
					'desc_tip' => __('Once you have successfully connected with the MYOB server, your MYOB company file(s) should appear here.'),
					'default' => '',
					'options' => $this->company_files,
					'custom_attributes' => array(
						'class' => 'hide',
					),
					'tab' => 1,
				),

				'reload_accounts_list' => array(
					'title' => __('Connect to Company File', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'button',
					'custom_attributes' => array(
						'onclick' => '',
					),
					//'description'       => __( 'Click the button to reload the lists of valid accounts from MYOB.', 'stars-myob-accountright-connector-for-woocommerce' ),
					'desc_tip' => false,
					'class' => 'button-primary',
					'tab' => 1,
				),

				'WC_MYOB_customer_id_prefix' => array(
					'title' => __('Customer Display ID Prefix (optional)', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'text',
					'description' => __('This is prefixed to the customer display in MYOB when a new customer record is created by Woo.'),
					'desc' => true,
					'desc_tip' => __('For non guest customers in WooCommerce, their corresponding MYOB customer records created in AccountRight will have a display ID that’s a combination of this ID prefix and their customer ID. '),
					'default' => 'WOO-',
					'css' => 'max-width:7em;',
				),
				'WC_MYOB_guest_customer_display_id' => array(
					'title' => __('Guest Customer Display ID', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'text',
					'description' => __('If set, all purchases from "guest" customers on WooCommerce will be assigned to this customer in MYOB.  If blank, each guest purchase will create a new customer record in MYOB.'),
					'desc' => true,
					'desc_tip' => __('When a WooCommerce guest customer has their corresponding MYOB customer record created in AccountRight, this guest ID will be used as the display ID. '),
					'default' => '',
					'css' => 'max-width:12em;',
				),
				'WC_MYOB_invoice_id_prefix' => array(
					'title' => __('Invoice Display ID Prefix (required)', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'text',
					'description' => __('This string is prefixed to the invoice number in MYOB when a new invoice or order is created by Woo.'),
					'desc' => true,
					'desc_tip' => __('If the setting to ‘Allow MYOB to Set The Invoice Number’ is not enabled, MYOB orders and invoices in AccountRight will have an invoice number that’s a combination of this prefix and the WooCommerce order number. (e.g. Prefix: ‘Woo-’ and Order #88 will form an invoice number of ‘Woo-88’)'),
					'default' => 'WOO-',
					'css' => 'max-width:7em;',
				),

				'WC_MYOB_sync_period' => array(
					'title' => __('Sync Period in Days', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'number',
					'description' => __('Set the number of days between MYOB synchronisations', 'stars-myob-accountright-connector-for-woocommerce'),
					'desc_tip' => __('The number of days set here will determine the interval between stock syncs for your WooCommerce products, which will have their inventory levels updated to match their corresponding MYOB products in AccountRight.'),
					'default' => 1,
					'css' => 'max-width:7em;',
					'min' => 1,
				),
				'WC_MYOB_invoice_type' => array(
					'title' => __('Default MYOB Invoice Type', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'select',
					'description' => __('If there is a combination of MYOB product types within an order (Items, Service, Professional), then a MYOB invoice of this type will be created.'),
					'desc' => false,
					'desc_tip' => __('In MYOB AccountRight, orders and invoices are each created as one of three types (Items, Service, Professional). Our plugin allows you to set a type for your WooCommerce products, and if every line item within an order is assigned the same type, a corresponding order or invoice will be created as that type in AccountRight. However, if an order has line items of various types, then your selection here will determine the type of order or invoice created.'),
					'default' => 'items',
					'options' => $invoice_type,
				),


				'WC_MYOB_income_account' => array(
					'title' => __('Income Account', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'select',
					'description' => __('Select the default income account to use for new products created by the plugin automatically in MYOB if they do not already exist.'),
					'desc' => true,
					'desc_tip' => __('The income account selected is included in the body of the API request to the MYOB server when creating new inventory items in AccountRight. In addition to that, this income account will be used in the line item information within orders and invoices created in AccountRight.'),
					'default' => '',
					'options' => $this->income_accounts,
				),
				'WC_MYOB_cogs_account' => array(
					'title' => __('Cost Of Sales Account', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'select',
					'description' => __('Select the default income account to use for new products created by the plugin automatically if they do not exist in MYOB already.   Cost of sales account is only used for "inventoried" items.'),
					'desc' => true,
					'desc_tip' => __('The cost of sales account selected will be used when new inventory items are created in MYOB.'),
					'default' => '',
					'options' => $this->cogs_accounts,
				),
				'WC_MYOB_asset_account' => array(
					'title' => __('Asset Account', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'select',
					'description' => __('Select the default asset account to use for new products created by the plugin automatically if they do not exist in MYOB already. Please ensure that the account selected is registered as a bank account in MYOB.'),
					'desc' => true,
					'desc_tip' => __('The asset account selected will be used when new inventory items are created or adjusted in MYOB. The asset account is also used when creating customer payments in AccountRight, which is necessary to close invoices if the ‘Create Closed Invoices’ setting is selected. Note that for invoice closure to succeed, the asset account must be a bank account.'),
					'default' => '',
					'options' => $this->asset_accounts,
				),


				'WC_MYOB_tax_code_new_products' => array(
					'title' => __('Default Tax Code for New Products', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'select',
					'description' => __('Select the default tax code to use for new products created by the plugin automatically in MYOB if they do not exist.  This setting will be ignored for products already in MYOB and instead the chosen income account in MYOB will be used.'),
					'desc' => true,
					'desc_tip' => __('The tax code you select here will be included in the body of the API requests to MYOB for creating new products in AccountRight. '),
					'default' => '',
					'options' => $this->tax_codes,
				),

				'WC_MYOB_tax_code_line_items' => array(
					'title' => __('Default Tax Code for Line Items', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'select',
					'description' => __('Select the default MYOB tax code to use for line items with an unrecognized tax code. If a line item appears with an unrecognised tax code, then the one selected here will be used for that item.'),
					'desc' => true,
					'desc_tip' => __('When line items from a WooCommerce order are being processed to create a corresponding MYOB order or invoice, their WooCommerce tax classes are first looked at. If the WooCommerce tax class is ‘standard’, the 6th code within your MYOB tax code list will be used; if it’s ‘zero rate’, then the 4th code will be used; otherwise, this code will be used by default. It is advised that you configure MYOB tax codes for your WooCommerce products individually.'),
					'default' => '',
					'options' => $this->tax_codes,
				),

				'WC_MYOB_freight_tax_code' => array(
					'title' => __('Freight Tax Code', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'select',
					'description' => __('Select the default tax code for freight charges.   This setting will be used independent of any settings in MYOB.'),
					'desc' => true,
					'desc_tip' => __('Your selection here will be used when creating MYOB orders and invoices in AccountRight. The selected freight tax code will also be used when creating and updating MYOB customers in AccountRight.'),
					'default' => '',
					'options' => $this->tax_codes,
				),

				'WC_MYOB_job_code' => array(
					'title' => __('Job Code', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'select',
					'description' => __('Select the default job code.'),
					'desc' => true,
					'desc_tip' => __('Your selection here may be used when creating MYOB orders and invoices in AccountRight. If the line items within an order or invoice have a job code assigned to them already (configurable via WooCommerce product settings), then that job code will be used instead. However, if one hasn’t been assigned to a particular product, the job code you select here will be used by default.'),
					'default' => '',
					'options' => $this->job_codes,
				),

				'WC_MYOB_dis_account' => array(
					'title' => __('Discount MYOB Account', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'select',
					'description' => __('Please select the account to use for the MYOB product, named "Discount". Please ensure that the account selected is registered as a bank account in MYOB.'),
					'desc' => true,
					'desc_tip' => __('The income account selected will be used for the MYOB product, named "Discount". This product is auto created in your MYOB account if you enable "Handle Discounts" option in our plugin settings.'),
					'default' => '',
					'options' => $this->income_accounts,
				),

				'allows_handle_discounts' => array(
					'title' => __('Handle Discount', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'checkbox',
					'description' => __('Enabling this option will begin a process to create descount items in MYOB to handle all discount related stuff.'),
					'desc' => true,
					'desc_tip' => false,
					'default' => 'no',
				),

				'WC_MYOB_sync_type_inventory' => array(
					'title' => __('Sync Product Inventory Type', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'select',
					'description' => __('Sync Woo Stock To Either MYOB "Available" or "Stock on Hand".'),
					'desc' => true,
					'desc_tip' => __('In MYOB AccountRight there are two different fields for measuring a product’s stock levels, ‘Available’ and ‘Stock on Hand’. When you click the button to ‘Sync Product Inventory Levels’, the stock values for your products in WooCommerce will be updated to match their corresponding MYOB product stock values, going off of either their ‘Available’ values or their ‘Quantity on Hand’ values in AccountRight.'),
					'default' => 'available',
					'options' => $sync_type,
				),

				'allow_sync' => array(
					'title' => __('Sync Product Inventory Levels', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'button',
					'custom_attributes' => array(
						'onclick' => '',
					),
					'description' => __('Clicking this button will begin a process to update the inventory levels for your WooCommerce products so that they match the inventory levels of their corresponding MYOB products in AccountRight (if they exist there)', 'stars-myob-accountright-connector-for-woocommerce'),
					'desc_tip' => false,
				),
				'invoice_sync' => array(
					'title' => __('Sync Invoices', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'button',
					'custom_attributes' => array(
						'onclick' => '',
					),
					'description' => __('Get Invoices from MYOB', 'stars-myob-accountright-connector-for-woocommerce'),
					'desc_tip' => false,
				),
				'customers_sync' => array(
					'title' => __('Sync Customers UID', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'button',
					'custom_attributes' => array(
						'onclick' => '',
					),
					'description' => __('Get Customer UID from MYOB', 'stars-myob-accountright-connector-for-woocommerce'),
					'desc_tip' => false,
				),


				// Custom Field - Myriad Solutionz
				'customer_email_sync' => array(
					'title' => __('Sync Customer by Email', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'input_button',
					'button_text' => __('Sync Customer', 'stars-myob-accountright-connector-for-woocommerce'),
					'placeholder' => __('Enter customer email', 'stars-myob-accountright-connector-for-woocommerce'),
					'description' => __('Enter a WooCommerce customer email and click the button to sync that customer with MYOB.', 'stars-myob-accountright-connector-for-woocommerce'),
					'desc_tip' => false,
					'class' => 'button-secondary',
				),

				'product_sku_sync' => array(
					'title' => __('Sync Product by SKU', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'input_button',
					'button_text' => __('Sync Product', 'stars-myob-accountright-connector-for-woocommerce'),
					'placeholder' => __('Enter prodcut sku', 'stars-myob-accountright-connector-for-woocommerce'),
					'description' => __('Enter a WooCommerce product sku and click the button to sync that product with MYOB.', 'stars-myob-accountright-connector-for-woocommerce'),
					'desc_tip' => false,
					'class' => 'button-secondary',
				),

				'product_sku_view' => array(
					'title' => __('View Product by SKU', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'input_button',
					'button_text' => __('View Product', 'stars-myob-accountright-connector-for-woocommerce'),
					'placeholder' => __('Enter product sku', 'stars-myob-accountright-connector-for-woocommerce'),
					'description' => __('Enter a product sku and click to view MYOB product details and tiered pricing.', 'stars-myob-accountright-connector-for-woocommerce'),
					'desc_tip' => false,
					'class' => 'button-secondary',
				),
				
			// 		'order_number_sync' => array(
			// 	'title' => __('Sync Order by Number', 'stars-myob-accountright-connector-for-woocommerce'),
			// 	'type' => 'input_button',
			// 	'button_text' => __('Sync Order', 'stars-myob-accountright-connector-for-woocommerce'),
			// 	'placeholder' => __('Enter order number', 'stars-myob-accountright-connector-for-woocommerce'),
			// 	'description' => __('Enter a WooCommerce order number and click the button to sync that order with MYOB.', 'stars-myob-accountright-connector-for-woocommerce'),
			// 	'desc_tip' => false,
			// 	'class' => 'button-secondary',
			// ),

				'WC_OPMC_only_sync_item_inventory' => array(
					'title' => __('Only Sync MYOB Item Inventory', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'checkbox',
					'description' => __('Do not create sales orders, invoices or any other function in MYOB.'),
					'desc' => true,
					'desc_tip' => __('If this setting is enabled, then the only sync item inventory from MYOB to WooCommerce and do not create sales orders, invoices or any other function in MYOB.'),
					'default' => 'no',
				),

				'WC_OPMC_stop_auto_inventory_sync' => array(
					'title' => __('Stop Auto Product Inventory Sync', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'checkbox',
					'description' => __('Stop auto sync products inventory in Woo from MYOB.'),
					'desc' => true,
					'desc_tip' => __('With this setting enabled, plugin stop auto sync products inventory in Woo from MYOB.'),
					'default' => 'no',
				),

				'WC_OPMC_enable_product_pricing_sync' => array(
					'title'       => __( 'Enable Auto Product Pricing Sync', 'stars-myob-accountright-connector-for-woocommerce' ),
					'type'        => 'checkbox',
					'description' => __( 'Automatically sync product tiered/level pricing from the MYOB price matrix every minute.' ),
					'desc'        => true,
					'desc_tip'    => __( 'When enabled, a background cron job runs every minute (in batches of 5 products) to pull the latest price matrix from MYOB AccountRight and update WooCommerce product pricing. Disable this if you want to control pricing syncs manually via the "Sync Product by SKU" tool.' ),
					'default'     => 'yes',
				),

				'WC_OPMC_create_product_to_woo_cron' => array(
					'title' => __('Automatically copy products from MYOB to WooCommerce', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'checkbox',
					'description' => __('Automatically copy products from MYOB to WooCommerce'),
					'desc' => true,
					'desc_tip' => __('With this setting enabled, your MYOB products will be copied across from AccountRight to WooCommerce through a scheduled cron job. If you have a large product catalogue, numbering more than 1000 items, then this process will occur in batches of 1000 items at the frequency selected in the ‘Cron Frequency For Product’ setting.'),
					'default' => 'no',
				),

				'WC_OPMC_do_not_copy_inactive_product_from_myob' => array(
					'title' => __('Do Not copy inactive products from MYOB to WooCommerce', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'checkbox',
					'description' => __('Do Not copy inactive products from MYOB to WooCommerce.'),
					'desc_tip' => __('This setting relates to the setting to ‘Automatically copy products from MYOB to WooCommerce’. If this setting is enabled, then the exclude inactive inventory items from the process for copying across MYOB products to WooCommerce'),
					'default' => 'no',
				),

				'WC_OPMC_fetch_items_frm_to_woo_batch_limit' => array(
					'title' => __('Number Of Records Fetch Product Per Request', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'number',
					'description' => __('Set Number Of Records Fetch Product Per Request.', 'stars-myob-accountright-connector-for-woocommerce'),
					'desc_tip' => false,
					'default' => 25,
					'css' => 'max-width:7em;',
					'custom_attributes' => array(
						'min' => '1',
						'max' => '1000',
					),
				),

				'WC_OPMC_create_product_to_woo_cron_frequency' => array(
					'title' => __('Cron Frequency For Product', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'select',
					'label' => 'Cron Frequency For Product',
					'default' => '',
					'options' => array(
						'hourly' => 'Every Hour',
						'twicedaily' => 'Twice A Day',
						'daily' => 'Once A Day',
					),
					'description' => 'Select cron frequency to sync products',
					'desc_tip' => __('This setting relates to the setting to ‘Automatically copy products from MYOB to WooCommerce’. If that setting is enabled and you’ve more than 1000 MYOB products to copy across, then the frequency selected here will be the interval between sync batches, where 1000 MYOB products are copied across from AccountRight to WooCommerce. '),
				),


				// 'export_product_to_myob' => array(
				//  'title'              => __( 'Export All Products to MYOB', 'stars-myob-accountright-connector-for-woocommerce' ),
				//  'type'               => 'button',
				//  'custom_attributes'  => array(
				//      'onclick' => '',
				//  ),
				//  'description'        => __( 'Export all WooCommerce products to MYOB that are not already in AccountRight.', 'stars-myob-accountright-connector-for-woocommerce' ),
				//  'desc_tip'           => false,
				// ), PLUGINS-1285

				'WC_OPMC_enable_myob_invoice_number' => array(
					'title' => __('Allow MYOB To Set The Invoice Number', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'checkbox',
					'description' => __('Allow MYOB to set the Invoice number, rather than passing the Woo Order number.<br> Be very careful before you disable this setting. Once you disable it, you will not be able to enable it again, as MYOB supports only one way sync for invoice numbers.'),
					'desc_tip' => __('When this plugin creates MYOB orders and invoices in AccountRight based on your WooCommerce orders, they need an optional invoice number. By default this setting is enabled.<br>If you disable this setting, then by default, that number will be a combination of the prefix you’ve set as the ‘Invoice Display ID Prefix’ and the WooCommerce order number (e.g. ‘Woo-88’).<br>Be very careful before you disable this setting. Once you disable it, you will not be able to enable it again, as MYOB supports only one way sync for invoice numbers.'),
					'default' => 'yes',
				),

				'WC_OPMC_enable_product_create' => array(
					'title' => __('Create Product in MYOB if Not Found', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'checkbox',
					'description' => __('Create product in MYOB if not found.'),
					'desc' => true,
					'desc_tip' => __('With this setting enabled, corresponding MYOB products will be created from WooCommerce line items, if they’re not already in AccountRight, when creating MYOB orders and invoices.'),
					'default' => 'yes',
				),

				/* PLUGINS-2273 */

				'WC_OPMC_support_variation_product' => array(
					'title' => __('Support Variation Product in MYOB', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'checkbox',
					'description' => __('With this setting enabled, corresponding MYOB products will be created from WooCommerce variation line items based on variation SKUs, if they’re not already in AccountRight, when creating MYOB orders and invoices.'),
					'desc' => true,
					'desc_tip' => __('With this setting enabled, corresponding MYOB products will be created from WooCommerce variation line items based on variation SKUs, if they’re not already in AccountRight, when creating MYOB orders and invoices.'),
					'default' => 'no',
				),
				/* PLUGINS-2273 End*/

				'WC_OPMC_create_guest_as_customer' => array(
					'title' => __('Create Customer with Email for Guest ', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'checkbox',
					'description' => __('Creates a MYOB customer for guest users in WooCommerce based off their email. If the guest email is taken in MYOB, the customer UID is used instead.'),
					'desc' => true,
					'desc_tip' => __('If no customer ID is found within the WooCommerce order information while this setting is enabled, the plugin will find a MYOB customer in AccountRight with the email found within the order information. '),
					'default' => 'no',
				),

				'WC_OPMC_auto_copy_customer_from_myob' => array(
					'title' => __('Automatically copy Customer Cards from MYOB to WooCommerce', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'checkbox',
					'description' => __('Automatically copy Customer Cards from MYOB to WooCommerce.'),
					'desc' => true,
					'desc_tip' => __('This setting relates to the setting to ‘Automatically copy Customer Cards from MYOB to WooCommerce’. If this setting will be enabled, then MYOB Customers will be copied across from AccountRight to WooCommerce and will be done via cron scheduler.'),
					'default' => 'no',
				),

				'WC_OPMC_create_closed_invoices' => array(
					'title' => __('Create Closed Invoices', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'checkbox',
					'description' => __('If selected, invoices created by WooCommerce will be closed, otherwise they will remain open. Note: a bank account (in asset accounts) must be selected to create closed invoices. If orders are set to be created instead of invoices, this setting will be overridden regardless of selection.'),
					'desc' => true,
					'desc_tip' => __('When a WooCommerce order status goes from ‘pending’ (upon creation) to ‘processing’, a corresponding MYOB invoice will be created in MYOB AccountRight, which is open by default. With this setting, the MYOB invoice created will be closed.'),
					'default' => 'no',
				),

				'WC_OPMC_create_order_instead_of_invoice' => array(
					'title' => __('Create Orders Instead of Invoices', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'checkbox',
					'description' => __('If selected, an MYOB order will be created instead of an MYOB invoice for WooCommerce purchases.'),
					'desc' => true,
					'desc_tip' => __('When a WooCommerce order status goes from ‘pending’ (upon creation) to ‘processing’, a corresponding MYOB order will be created in MYOB AccountRight.  '),
					'default' => 'no',
				),

				'WC_OPMC_create_orders_when_on_hold' => array(
					'title' => __('Create Orders when On-Hold', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'checkbox',
					'description' => __('If selected, an MYOB order will be created when a WooCommerce order is on-hold.'),
					'desc' => true,
					'desc_tip' => __('When a WooCommerce order status goes from ‘pending’ (upon creation) to ‘on-hold’, a corresponding MYOB order will be created in MYOB AccountRight. '),
					'default' => 'no',
				),
				'WC_MYOB_search_by_customer_name' => array(
					'title' => __('Search Customer by Name', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'checkbox',
					'description' => __('Enable this option to allow searching for customers by their name in MYOB AccountRight. Useful when you have multiple customers from the same customer name.'),
					'desc' => true,
					'desc_tip' => __('Enable this option to allow searching for customers by their name in MYOB AccountRight. Useful when you have multiple customers from the same customer name.'),
					'default' => false,
				),

				'WC_MYOB_search_by_company' => array(
					'title' => __('Search Customer by Company Name', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'checkbox',
					'description' => __('Enable this option to allow searching for customers by their company name in MYOB AccountRight. Useful when you have multiple customers from the same company.'),
					'desc' => true,
					'desc_tip' => __('Enable this option to allow searching for customers by their company name in MYOB AccountRight. Useful when you have multiple customers from the same company.'),
					'default' => false,
				),

				'WC_MYOB_search_by_email' => array(
					'title' => __('Search Customer by Email', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'checkbox',
					'description' => __('Enable this option to allow searching for customers by their email in MYOB AccountRight. Useful when you have multiple customers from the same customer email.'),
					'desc' => true,
					'desc_tip' => __('Enable this option to allow searching for customers by their email in MYOB AccountRight. Useful when you have multiple customers from the same customer email.'),
					'default' => 'yes',
				),

				'WC_OPMC_set_default_customer_designation' => array(
					'title' => __('Set MYOB Default Customer Designation', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'checkbox',
					'description' => __('If not selected, the MYOB sets the Customer Designation to Company, if the customer fills out the Company field on Woo Checkout.'),
					'desc' => true,
					'desc_tip' => __('In MYOB AccountRight, customer cards have a selectable field which designates them either as an ‘Individual’ or as a ‘Company’. By default, this plugin will create customers with the ‘Individual’ designation. With this setting enabled, newly created customers in AccountRight will be designated as companies.'),
					'default' => 'yes',
				),

				'WC_OPMC_enable_product_bulk_action' => array(
					'title' => __('Enable Product Sync Bulk Action', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'checkbox',
					'description' => __('Toggle this setting to enable or disable the option to sync multiple products to MYOB from the WooCommerce Products page using bulk actions.'),
					'desc' => true,
					'desc_tip' => __('Toggle this setting to enable or disable the option to sync multiple products to MYOB from the WooCommerce Products page using bulk actions.'),
					'default' => 'no',
				),
				'WC_OPMC_enable_debug_logging' => array(
					'title' => __('Enable Debug Logging', 'stars-myob-accountright-connector-for-woocommerce'),
					'type' => 'checkbox',
					'description' => __('If enabled, detailed debugging logs will be created on your server.   Caution- these logs can quickly become very large and fill your server hard disk!'),
					'desc' => true,
					'desc_tip' => __('With this setting enabled, integration actions, such as API requests to the MYOB server and responses from the MYOB server, will be recorded in your WordPress wp-debug.log file. Though you must ensure that your site has debug logging enabled, as the plugin can only write to it if you’ve enabled it in your site settings.'),
					'default' => 'no',
				),

				'WC_OPMC_tiered_pricing_pro' => array(
					'title'       => __( 'Tiered Pricing Table Pro (Role-Based)', 'stars-myob-accountright-connector-for-woocommerce' ),
					'type'        => 'checkbox',
					'description' => __( 'Enable if you have the <strong>Pro version</strong> of the Tiered Pricing Table plugin. When enabled, all six MYOB price levels (LevelA–LevelF) are synced to role-based pricing rules. When disabled, only LevelA quantity breaks are written to the standard <code>_fixed_price_rules</code> meta key used by the free version.', 'stars-myob-accountright-connector-for-woocommerce' ),
					'desc'        => true,
					'desc_tip'    => __( 'Pro mode writes per-level meta keys (_LevelA_fixed_price_rules, etc.) read by the Pro version of Tiered Pricing Table for role-based pricing. Free mode writes a single _fixed_price_rules key using LevelA prices only, which the free version reads. If unsure, leave this unchecked.', 'stars-myob-accountright-connector-for-woocommerce' ),
					'default'     => 'no',
				),

				'WC_OPMC_sync_log_retention' => array(
					'title'       => __( 'Sync Log Retention Period', 'stars-myob-accountright-connector-for-woocommerce' ),
					'type'        => 'select',
					'description' => __( 'How long to keep entries in the Sync Log before they are automatically removed.' ),
					'desc'        => true,
					'desc_tip'    => __( 'The Sync Log is stored as a flat file on your server. Older entries beyond the selected period are purged automatically each time a new sync event is logged, keeping the file size manageable.' ),
					'default'     => '7',
					'options'     => array(
						'1'   => __( '1 Day',     'stars-myob-accountright-connector-for-woocommerce' ),
						'3'   => __( '3 Days',    'stars-myob-accountright-connector-for-woocommerce' ),
						'7'   => __( '7 Days',    'stars-myob-accountright-connector-for-woocommerce' ),
						'14'  => __( '14 Days',   'stars-myob-accountright-connector-for-woocommerce' ),
						'21'  => __( '21 Days',   'stars-myob-accountright-connector-for-woocommerce' ),
						'30'  => __( '1 Month',   'stars-myob-accountright-connector-for-woocommerce' ),
						'60'  => __( '2 Months',  'stars-myob-accountright-connector-for-woocommerce' ),
						'90'  => __( '3 Months',  'stars-myob-accountright-connector-for-woocommerce' ),
						'180' => __( '6 Months',  'stars-myob-accountright-connector-for-woocommerce' ),
						'270' => __( '9 Months',  'stars-myob-accountright-connector-for-woocommerce' ),
						'365' => __( '1 Year',    'stars-myob-accountright-connector-for-woocommerce' ),
					),
				),
			);

			/**
			 * Filter to add more settings to the MYOB UI.
			 *
			 * @since 1.0
			 *
			 * @param array $form_fields - The original form fields.
			 */
			$this->form_fields = apply_filters('wc_myob_add_more_settings_ui', $this->form_fields);
		}


		/**
		 * Generate Button HTML.
		 */
		public function generate_button_html($key, $data)
		{

			$field = $this->plugin_id . $this->id . '_' . $key;
			$defaults = array(
				'class' => 'button-secondary',
				'css' => '',
				'custom_attributes' => array(),
				'desc_tip' => false,
				'description' => '',
				'title' => '',
				'disable' => true,
			);

			$allowed_html = array(
				'a' => array(
					'href' => array(),
					'title' => array(),
				),
				'br' => array(),
				'em' => array(),
				'strong' => array(),
			);
			$data = wp_parse_args($data, $defaults);
			if (isset($_SERVER['QUERY_STRING'])) {
				$validL = parse_str(sanitize_text_field($_SERVER['QUERY_STRING']), $params);
				$setfont = $params['section'];
				if ('stars-myob-accountright-connector-for-woocommerce' == $setfont) {

					wp_register_style('Font_Awesome', 'https://maxcdn.bootstrapcdn.com/font-awesome/4.6.3/css/font-awesome.min.css', array(), '1.0');
					wp_enqueue_style('Font_Awesome');
				}
			}

			ob_start();
			?>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr($field); ?>"><?php echo esc_html(wp_kses_post($data['title'])); ?></label>
					<?php echo wp_kses_post($this->get_tooltip_html($data)); ?>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text"><span><?php echo esc_html(wp_kses_post($data['title'])); ?></span>
						</legend>
						<button class="<?php echo esc_attr($data['class']); ?>" type="button" name="<?php echo esc_attr($field); ?>"
							id="<?php echo esc_attr($field); ?>" style="<?php echo esc_attr($data['css']); ?>" <?php echo wp_kses($this->get_custom_attribute_html($data), $allowed_html); ?>
							disabled="disabled"><?php echo wp_kses_post($data['title']); ?></button>
						<?php echo wp_kses($this->get_description_html($data), $allowed_html); ?>
					</fieldset>
				</td>
			</tr>
			<?php
			return ob_get_clean();
		}


		/**
		 * Myriadsolutionz Custom Code:
		 * Generate HTML for custom "input + button" field type.
		 */
		public function generate_input_button_html($key, $data)
		{

			$field = $this->plugin_id . $this->id . '_' . $key;

			$defaults = array(
				'class' => 'button-secondary',
				'css' => '',
				'custom_attributes' => array(),
				'desc_tip' => false,
				'description' => '',
				'title' => '',
				'placeholder' => '',
				'button_text' => __('Submit', 'stars-myob-accountright-connector-for-woocommerce'),
				'disable' => false,
			);

			$allowed_html = array(
				'a' => array(
					'href' => array(),
					'title' => array(),
				),
				'br' => array(),
				'em' => array(),
				'strong' => array(),
				'p' => array(
					'class' => array()
				),

			);

			$data = wp_parse_args($data, $defaults);

			$input_id = $field . '_input';
			$button_id = $field . '_button';

			ob_start();
			?>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr($input_id); ?>">
						<?php echo esc_html(wp_kses_post($data['title'])); ?>
					</label>
					<?php echo wp_kses_post($this->get_tooltip_html($data)); ?>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text">
							<span><?php echo esc_html(wp_kses_post($data['title'])); ?></span>
						</legend>

						<input type="text" name="<?php echo esc_attr($input_id); ?>" id="<?php echo esc_attr($input_id); ?>"
							value="" placeholder="<?php echo esc_attr($data['placeholder']); ?>"
							style="min-width: 200px; margin-right: 10px;" />

						<button class="<?php echo esc_attr($data['class']); ?>" type="button"
							name="<?php echo esc_attr($button_id); ?>" id="<?php echo esc_attr($button_id); ?>"
							style="<?php echo esc_attr($data['css']); ?>" <?php echo wp_kses($this->get_custom_attribute_html($data), $allowed_html); ?> 			<?php echo !empty($data['disable']) ? 'disabled="disabled"' : ''; ?>>
							<?php echo wp_kses_post($data['button_text']); ?>
						</button>

						<?php echo wp_kses($this->get_description_html($data), $allowed_html); ?>
					</fieldset>
				</td>
			</tr>
			<?php
			return ob_get_clean();
		}




		/**
		 * Override WooCommerce's tooltip with a plain "More info" text button
		 * that injects a full-width info row below the setting when clicked.
		 * The tip text is stored in data-tip on the button — no hidden span needed.
		 *
		 * @param array $data Field data array.
		 * @return string HTML for the info button, or empty string if no tip.
		 */
		public function get_tooltip_html( $data ) {
			if ( true === $data['desc_tip'] ) {
				$tip = $data['description'] ?? '';
			} elseif ( ! empty( $data['desc_tip'] ) ) {
				$tip = $data['desc_tip'];
			} else {
				return '';
			}

			if ( empty( $tip ) ) {
				return '';
			}

			// Store tip as escaped JSON so it survives any wp_kses pass.
			return '<button type="button" class="opmc-info-btn" aria-expanded="false" '
				. 'data-tip="' . esc_attr( $tip ) . '">'
				. 'More info'
				. '</button>';
		}

		/**
		 * Santize our settings
		 * 
		 * @see process_admin_options()
		 */
		public function sanitize_settings($settings)
		{
			// We're just going to make the api key all upper case characters since that's how our imaginary API works
			if (
				isset($settings) &&
				isset($settings['api_key'])
			) {
				$settings['api_key'] = strtoupper($settings['api_key']);
			}
			return $settings;
		}


		/**
		 * Myob redirect uri.
		 */
		public function WC_MYOB_redirect_uri()
		{
			return WC_MYOB_INTEGRATION_PLUGINURL . 'stars-myob-cronjob.php';
		}


		/**
		 * Assembles the URL that will invoke the authentication process.
		 *
		 * Two modes depending on WC_MYOB_API_REDIRECT_URL:
		 *
		 * A) Direct mode (no intermediary) — WC_MYOB_API_REDIRECT_URL points
		 *    directly to this site's stars-myob-cronjob.php.  MYOB calls us back
		 *    straight away; no `state` trick needed.
		 *
		 * B) Intermediary mode (legacy) — WC_MYOB_API_REDIRECT_URL points to a
		 *    third-party relay page.  The merchant callback URL is passed via
		 *    `state` so the relay knows where to forward the code.
		 */
		public function assemble_myob_auth_url()
		{
			$own_callback = $this->WC_MYOB_redirect_uri(); // stars-myob-cronjob.php URL

			// Direct mode: the registered redirect_uri IS our callback, no relay needed.
			if ( WC_MYOB_API_REDIRECT_URL === $own_callback ) {
				$query = '?client_id=' . WC_MYOB_API_CLIENT_ID
					. '&redirect_uri=' . urlencode( $own_callback )
					. '&response_type=code&scope=CompanyFile';

				return 'https://secure.myob.com/oauth2/account/authorize' . $query;
			}

			// Intermediary / relay mode: redirect_uri points to the relay page,
			// the merchant callback is carried in `state`.
			$query = '?client_id=' . WC_MYOB_API_CLIENT_ID
				. '&redirect_uri=' . urlencode( WC_MYOB_API_REDIRECT_URL )
				. '&response_type=code&scope=CompanyFile'
				. '&state=' . urlencode( $own_callback );

			return 'https://secure.myob.com/oauth2/account/authorize' . $query;
		}

		/**
		 * Generate a URL to our specific settings screen.
		 * 
		 * @since  1.3.4
		 * @return string Generated URL.
		 */
		public function get_settings_url()
		{
			return add_query_arg(
				array(
					'page' => 'wc-settings',
					'tab' => 'integration',
					'section' => 'stars-myob-accountright-connector-for-woocommerce',
				),
				admin_url('admin.php')
			);
		}

		/**
		 * Displays notices in admin settings screen.
		 *
		 * @return string Error notices.
		 */
		public function admin_notices()
		{
			global $post;
			$config = get_option('woocommerce_MYOB_integrations_settings');

			/*
			 * TODO: this needs nonce verification.   Disabled for now.
			 *
			if ( isset( $_POST['save'] ) ) {
				if ( isset($_POST['_wp_http_referer']) && '/wp-admin/admin.php?page=wc-settings&tab=integration&section=myob_integrations' == $_POST['_wp_http_referer'] ) {
					if ( ( empty($config['WC_MYOB_tax_code']) || 0 == $config['WC_MYOB_tax_code'] ) || ( empty($config['WC_MYOB_freight_tax_code']) || 0 == $config['WC_MYOB_tax_code'] ) ) { 
						echo '<script>window.location.reload();</script>';
					}
				}
			}
			 */

			$screen = get_current_screen();
			$shop_page_url = get_permalink(get_option('woocommerce_shop_page_id'));
			$settings_id = 'woocommerce_myob_integrations';
			$url = $this->get_settings_url();

			if (empty($this->company_file_id)) {

				echo '<div class="notice notice-warning"><p><strong>WooCommerce MYOB AccountRight is almost ready.</strong> To get started, <a href="' . esc_url($url) . '"> go to MYOB Account Settings </a> and click Validate Access to sign into MYOB.</p></div>' . "\n";

			} elseif (empty($this->MYOB_tax_code_for_new_products) || empty($this->MYOB_freight_tax_code)) {

				echo '<div class="notice notice-warning"><p><strong>WooCommerce MYOB AccountRight is almost ready.</strong> To get started, <a href="' . esc_url($url) . '"> go to MYOB Account Settings </a> and select asset, income and tax accounts and click save button.</p></div>' . "\n";
			}

			if ('yes' == get_option('WC_MYOB_refresh_token_failed')) {
				echo '<div class="notice notice-error" id="auth1"><p><strong>Store is not connected with MYOB. Please revalidate!</strong></p></div>';
			}

			if (get_option('WC_MYOB_api_unauthorized_count') > 0) {
				echo '<div class="notice notice-error" id="auth"><p><strong>WooCommerce MYOB is not authenticating with the MYOB server correctly.</strong></p>   Please ensure that you have entered your MYOB login credentials correctly, which includes your company file username. You may need to re-attempt authentication by clicking the Validate Access button. <br>Order information will not be sent to MYOB while authentication is failing. This message will stop appearing once a successful integration action with the MYOB server occurs (e.g. Reload Accounts List).</p></div>' . "\n";
			}
		}
	}

endif;

