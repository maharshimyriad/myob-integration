<?php

/**
* Implements a class that represents a MYOB Invoice made up of physical Items
*/
class Opmc_Myob_Order_To_Invoice {
	
	public $status;
		
	public function __construct( $order, $order_id, $customer_uid, $freight_tax_code, $myob_items, $invoice_guid, $tax_inclusive = true ) {
		$this->line_items = null;
		$this->line_items_discount = null;
		$this->address = '';
		$this->order = $order;
				
		if (null !== $this->order->get_order_number()) {
			$this->order_id = $this->order->get_order_number();
		} else {
			$this->order_id = $order_id;
		}
		$this->customer_uid = $customer_uid;
		$this->freight_tax_code = $freight_tax_code;
		$this->tax_inclusive = true;
		$this->myob_items = $myob_items;
		$this->invoice_guid = $invoice_guid;
	}

	private function get_myob_uid_from_sku( $sku ) {
		foreach ( $this->myob_items as $x ) {
			if ( $x->Number == $sku ) {
				return $x->UID;
			}
		}
	}

	private function get_myob_description_from_sku( $sku, $fallback = '' ) {
		foreach ( $this->myob_items as $x ) {
			if ( $x->Number == $sku ) {
				if (isset($x->Description) && '' !== trim((string) $x->Description)) {
					return (string) $x->Description;
				}

				if (isset($x->Name) && '' !== trim((string) $x->Name)) {
					return (string) $x->Name;
				}

				break;
			}
		}

		return $fallback;
	}

	private function get_shipping_method_value() {
		$fulfilment_method = sanitize_key((string) $this->order->get_meta('_fhs_fulfilment_method'));

		if ('pickup' === $fulfilment_method) {
			return 'Pick Up/ Own Freight';
		}

		if ('delivery' === $fulfilment_method) {
			return 'FHS Freight TBC';
		}

		$shipping_method = trim((string) $this->order->get_shipping_method());

		return '' !== $shipping_method ? $shipping_method : null;
	}


	public function add_line_item( $item_id, $item_data, $tax_code ) {
		$product = $item_data->get_product();
		$sku = $product->get_sku();

		$wc_settings =  get_option('woocommerce_MYOB_integrations_settings'); 
		$WC_MYOB_customer_id_prefix = $wc_settings['WC_MYOB_job_code'];

		$item_meta = $item_data->get_meta();


		if ($product->get_meta('_myob_product_job_code', true)) {
			$job = array(
					'UID' => $product->get_meta('_myob_product_job_code', true),
				);
		} else if (isset($wc_settings['WC_MYOB_job_code']) && !empty($wc_settings['WC_MYOB_job_code'])) {
			$job = array(
					'UID' => $wc_settings['WC_MYOB_job_code'],
				);
		} else {
			$job = null;
		}

		// Opmc_Logger::debug( ' Subtotal ' . $item_data->get_subtotal() );
		// Opmc_Logger::debug( ' Total ' . $item_data->get_total() );
		// Opmc_Logger::debug( ' Qty ' . $item_data->get_quantity() );


		$unit_price = ( $item_data->get_subtotal()+$item_data->get_total_tax() ) / $item_data->get_quantity();

		$line_item = array(
				'Type' => 'Transaction',
				'Description' => $this->get_myob_description_from_sku($sku, ''),
				'ShipQuantity' => $item_data->get_quantity(),
				'UnitPrice' => number_format((float) $unit_price, 2, '.', ''),
				'DiscountPercent' => 0,
				'TaxCode' => array(
					'UID' => $tax_code,
				),
				'Item' => array(
					'UID' => $this->get_myob_uid_from_sku($sku),
				),
				'Job' => $job,
			);

		$this->line_items[] = $line_item;

		// Coupons used in the order LOOP (as they can be multiple)
		$coupon_name = array();
		if (!empty($this->order->get_coupon_codes())) {

			foreach ( $this->order->get_coupon_codes() as $coupon_code ) {
				$coupon_post_obj = get_page_by_title($coupon_code, OBJECT, 'shop_coupon');
				$coupon_id       = $coupon_post_obj->ID;
				$coupon = new WC_Coupon($coupon_id);
				 $get_description = $coupon->get_description();

				if (!empty($get_description)) {

					$coupon_name[] = $get_description;
				} else {
					$coupon_name[] = $coupon_code;
				}
			}

			$str = implode(', ', $coupon_name); 
			$order_ids = $this->order->get_id();
			$discount_line_created = opmc_hpos_get_post_meta($order_ids, 'coupon_check', true);

			$discount_item_uid = get_option('MYOB_discount_item_uids');
			if (!empty($discount_item_uid)) {

				if ('discount_line_created' == $discount_line_created) {
					$tax_code = $wc_settings['WC_MYOB_tax_code_line_items'];
					$line_items = array(
						'Type' => 'Transaction',
						'Description' => 'Discount (' . $str . ')',
						'ShipQuantity' => -1,
						'UnitPrice' => number_format((float) $this->order->get_total_discount(), 2, '.', ''),
						'DiscountPercent' => 0,
						'TaxCode' => array(
							'UID' => $tax_code,
						),
						'Item' => array(
							'UID' => $discount_item_uid,
						),
						'Job' => $job,
					);

					$this->line_items_discount[] = $line_items;
					opmc_hpos_update_post_meta($order_ids, 'coupon_check', 'discount_line_created');
				}
			}
		} // End
	}

	public function add_line_service( $item_id, $item_data, $tax_code ) {
		
		$product = $item_data->get_product();
		$sku = $product->get_sku();
		$wc_settings =  get_option('woocommerce_MYOB_integrations_settings'); 
		$WC_MYOB_customer_id_prefix = $wc_settings['WC_MYOB_job_code'];

		$item_meta = $item_data->get_meta();

		if ($product->get_meta('_myob_product_job_code', true)) {
			$job = array(
					'UID' => $product->get_meta('_myob_product_job_code', true),
				);
		} else if (isset($wc_settings['WC_MYOB_job_code']) && !empty($wc_settings['WC_MYOB_job_code'])) {
			$job = array(
					'UID' => $wc_settings['WC_MYOB_job_code'],
				);
		} else {
			$job = null;
		}

		/** My Custom code for Create service invoice */
		$dollar_format = function ( $x ) {
			return number_format((float) $x, 2, '.', '');
		};
		$unit_price = ( $item_data->get_subtotal()+$item_data->get_total_tax() ) / $item_data->get_quantity();
		$income_account = $wc_settings['WC_MYOB_income_account'];
		$line_item = array(
				'Type' => 'Transaction',
				'Description' => $this->get_myob_description_from_sku($sku, ''),
				'UnitOfMeasure' => null,
				'UnitCount' => $item_data->get_quantity(),
				'UnitPrice' => number_format((float) $unit_price, 2, '.', ''),
				'DiscountPercent' => 0,
				'Total' => $dollar_format($unit_price*$item_data->get_quantity()),
				'Account' => array(
					'UID' => $income_account,
				),
				'Job' => $job,
				'TaxCode' => array(
					'UID' => $tax_code,
				),
			);
		/*End*/
		$this->line_items[] = $line_item;

		// Coupons used in the order LOOP (as they can be multiple)
		$order_ids = $this->order->get_id();
		$coupon_check = opmc_hpos_get_post_meta($order_ids, 'coupon_check', true);


		$coupon_name = array();

		if (!empty($this->order->get_coupon_codes())) {

			foreach ( $this->order->get_coupon_codes() as $coupon_code ) {
				$coupon_post_obj = get_page_by_title($coupon_code, OBJECT, 'shop_coupon');
				$coupon_id       = $coupon_post_obj->ID;
				$coupon = new WC_Coupon($coupon_id);
				 $get_description = $coupon->get_description();

				if (!empty($get_description)) {

					$coupon_name[] = $get_description;
				} else {
					$coupon_name[] = $coupon_code;
				}
			}

			$str = implode(', ', $coupon_name);

			$order_ids = $this->order->get_id();
			$discount_line_created = opmc_hpos_get_post_meta($order_ids, 'coupon_check', true);

			$discount_item_uid = get_option('MYOB_discount_item_uids');
			$config = get_option( 'woocommerce_MYOB_integrations_settings');
			$MYOB_dis_income_account = isset($config['WC_MYOB_dis_account']) ? $config['WC_MYOB_dis_account'] : '';
			if (!empty($discount_item_uid)) {

				if ('discount_line_created' == $discount_line_created) {
					$tax_code = $wc_settings['WC_MYOB_tax_code_line_items'];
					$line_items = array(
						'Type' => 'Transaction',
						'Description' => 'Discount (' . $str . ')',
						'UnitOfMeasure' => null,
						'UnitCount' => -1,
						'UnitPrice' => number_format((float) $this->order->get_total_discount(), 2, '.', ''),
						'DiscountPercent' => 0,
						'Total' => -number_format((float) $this->order->get_total_discount(), 2, '.', ''),
						'Account' => array(
						'UID' => $MYOB_dis_income_account,
					),
						'TaxCode' => array(
							'UID' => $tax_code,
						),
						'Job' => $job,
					);

					$this->line_items_discount[] = $line_items;
					opmc_hpos_update_post_meta($order_ids, 'coupon_check', 'discount_line_created');
				}
			}
		} // End
	}

	public function add_line_professional( $item_id, $item_data, $tax_code ) {
		
		$product = $item_data->get_product();
		$sku = $product->get_sku();

		$wc_settings =  get_option('woocommerce_MYOB_integrations_settings'); 
		$WC_MYOB_customer_id_prefix = $wc_settings['WC_MYOB_job_code'];

		$item_meta = $item_data->get_meta();

		if ($product->get_meta('_myob_product_job_code', true)) {
			$job = array(
					'UID' => $product->get_meta('_myob_product_job_code', true),
				);
		} else if (isset($wc_settings['WC_MYOB_job_code']) && !empty($wc_settings['WC_MYOB_job_code'])) {
			$job = array(
					'UID' => $wc_settings['WC_MYOB_job_code'],
				);
		} else {
			$job = null;
		}

		// Get local time and date
		$wp_dt = gmdate('Y-m-d') . 'T' . gmdate('H:i:s');
		$date_format = 'Y-m-d'; 
		$time_format = 'H:i:s';
		$localdt = get_date_from_gmt($wp_dt, $date_format);
		$localtm = get_date_from_gmt($wp_dt, $time_format);
		

		/** My Custom code for Create professional invoice */
		$dollar_format = function ( $x ) {
			return number_format((float) $x, 2, '.', '');
		};
		$unit_price = ( $item_data->get_subtotal()+$item_data->get_total_tax() ) / $item_data->get_quantity();
		$income_account = $wc_settings['WC_MYOB_income_account'];
		$line_item = array(
				'Type' => 'Transaction',
				'Description' => $this->get_myob_description_from_sku($sku, ''),
				'Date' => $localdt . 'T' . $localtm,
				'UnitOfMeasure' => null,
				'UnitCount' => $item_data->get_quantity(),
				'UnitPrice' => number_format((float) $unit_price, 2, '.', ''),
				'DiscountPercent' => 0,
				'Total' => $dollar_format($unit_price*$item_data->get_quantity()),
				'Account' => array(
					'UID' => $income_account,
				),
				'Job' => $job,
				'TaxCode' => array(
					'UID' => $tax_code,
				),
			);
		/*End*/
		$this->line_items[] = $line_item;

		$shipping = $this->order->get_total_shipping()+ $this->order->get_shipping_tax();   

		if (isset($shipping) && 0 != $shipping) {
			
			$line_item = array(
				'Type' => 'Transaction',
				'Description' => 'Shipping Method (' . $this->order->get_shipping_method() . ')',
				'Date' => $localdt . 'T' . $localtm,
				'UnitOfMeasure' => null,
				'UnitCount' => 1,
				'UnitPrice' =>$dollar_format($this->order->get_total_shipping()+ $this->order->get_shipping_tax()),
				'DiscountPercent' => 0,
				'Total' => $dollar_format($this->order->get_total_shipping()+ $this->order->get_shipping_tax()),
				'Account' => array(
					'UID' => $income_account,
				),
				'Job' => $job,
				'TaxCode' => array(
					'UID' => $tax_code,
				),
			);
			/*End*/
			$this->line_items[] = $line_item;
		}

		// Coupons used in the order LOOP (as they can be multiple)
		$coupon_name = array();
		if (!empty($this->order->get_coupon_codes())) {

			foreach ( $this->order->get_coupon_codes() as $coupon_code ) {
				$coupon_post_obj = get_page_by_title($coupon_code, OBJECT, 'shop_coupon');
				$coupon_id       = $coupon_post_obj->ID;
				$coupon = new WC_Coupon($coupon_id);
				 $get_description = $coupon->get_description();

				if (!empty($get_description)) {

					$coupon_name[] = $get_description;
				} else {
					$coupon_name[] = $coupon_code;
				}
			}

			$str = implode(', ', $coupon_name);

			$order_ids = $this->order->get_id();
			$discount_line_created = opmc_hpos_get_post_meta($order_ids, 'coupon_check', true);

			$discount_item_uid = get_option('MYOB_discount_item_uids');
			$config = get_option( 'woocommerce_MYOB_integrations_settings');
			$MYOB_dis_income_account = isset($config['WC_MYOB_dis_account']) ? $config['WC_MYOB_dis_account'] : '';
			if (!empty($discount_item_uid)) {
				
				if ('discount_line_created' == $discount_line_created) {
					$tax_code = $wc_settings['WC_MYOB_tax_code_line_items'];
					$line_items = array(
						'Type' => 'Transaction',
						'Description' => 'Discount (' . $str . ')',
						'Date' => $localdt . 'T' . $localtm,
						'UnitOfMeasure' => null,
						'UnitCount' => -1,
						'UnitPrice' => number_format((float) $this->order->get_total_discount(), 2, '.', ''),
						'DiscountPercent' => 0,
						'Total' => -number_format((float) $this->order->get_total_discount(), 2, '.', ''),
						'Account' => array(
							'UID' => $MYOB_dis_income_account,
						),
						'TaxCode' => array(
							'UID' => $tax_code,
						),
						'Item' => array(
							'UID' => $discount_item_uid,
						),
						'Job' => $job,
					);

					$this->line_items_discount[] = $line_items;
					opmc_hpos_update_post_meta($order_ids, 'coupon_check', 'discount_line_created');
				}
			}
		} // End
	}

	public function set_address() {

		$use_shipping = '' !== trim((string) $this->order->get_address('shipping')['address_1']);
		$address_type = $use_shipping ? 'shipping' : 'billing';
		$address = $this->order->get_address($address_type);

		$company = 'shipping' === $address_type
			? trim((string) $this->order->get_shipping_company())
			: trim((string) $this->order->get_billing_company());
		$first_name = 'shipping' === $address_type
			? trim((string) $this->order->get_shipping_first_name())
			: trim((string) $this->order->get_billing_first_name());
		$last_name = 'shipping' === $address_type
			? trim((string) $this->order->get_shipping_last_name())
			: trim((string) $this->order->get_billing_last_name());
		$phone = trim((string) $this->order->get_billing_phone());
		$person_line = trim($first_name . ' ' . $last_name);
		if ('' !== $phone) {
			$person_line = trim($person_line . ' ' . $phone);
		}

		$street_parts = array_filter(array(
			trim((string) $address['address_1']),
			trim((string) $address['address_2']),
		));
		$locality_parts = array_filter(array(
			trim((string) $address['city']),
			trim((string) $address['state']),
			trim((string) $address['postcode']),
			trim((string) $address['country']),
		));
		$address_line = implode(', ', array_filter(array(
			implode(' ', $street_parts),
			implode(', ', $locality_parts),
		)));

		$this->address = implode("\n", array_filter(array(
			$company,
			$person_line,
			$address_line,
		)));
	}

	public function generate_post_data() { 

		$dollar_format = function ( $x ) {
			return number_format((float) $x, 2, '.', '');
		};
		
		// Get local time and date
		$wp_dt = gmdate('Y-m-d') . 'T' . gmdate('H:i:s');
		$date_format = 'Y-m-d'; 
		$time_format = 'H:i:s';
		$localdt = get_date_from_gmt($wp_dt, $date_format);
		$localtm = get_date_from_gmt($wp_dt, $time_format);

		if (is_array($this->line_items_discount)) {
			$line_items = array_merge($this->line_items, $this->line_items_discount); 
		} else {
			$line_items = $this->line_items;
		}

		if (!empty($this->order->get_customer_note())) {
			$customer_note = $this->order->get_customer_note();
		} else {
			$customer_note = '';
		}

		$required_date = trim((string) get_post_meta($this->order_id, '__order_required_date', true));
		$promised_date = null;
		if (!empty($required_date)) {
			$required_date_ts = strtotime($required_date);
			if (false !== $required_date_ts) {
				$promised_date = date('Y-m-d 00:00:00', $required_date_ts);
			}
		}

		$res = array(
			'Date'   => $localdt . 'T' . $localtm,
			'CustomerPurchaseOrderNumber' => $this->order_id, 
			'Customer' => array(
				'UID' => $this->customer_uid,
			),
			'ShipToAddress' => $this->address,
			'Terms' => array(
				'PaymentIsDue'      => 'PrePaid',
			),
			'IsTaxInclusive'    => $this->tax_inclusive,
			'Lines' =>  $line_items,
			'Subtotal' =>  $dollar_format($this->order->get_subtotal()),
			'Freight' => $dollar_format($this->order->get_total_shipping()+ $this->order->get_shipping_tax()),
			// "Freight" => $dollar_format($this->order->get_total_shipping()),
			'FreightTaxCode' => array(
				'UID' => $this->freight_tax_code,
			),
			'TotalTax' => $dollar_format($this->order->get_total_tax() + $this->order->get_shipping_tax()),
			'TotalAmount' => $dollar_format($this->order->get_total()),
			'Category' => null,
			'Salesperson' => array(
				'UID' => '01b91321-45fa-481e-aa74-4a659284ed71',
				'Name' => 'Website Sales',
				'DisplayID' => '*None',
			),
			'Comment' => $customer_note,
			'ShippingMethod' => $this->get_shipping_method_value(),
			'PromisedDate' => $promised_date,
			'JournalMemo' => 'WooCommerce Order ' . $this->order_id . ' from ' . $this->order->get_billing_last_name() . ', ' . $this->order->get_billing_first_name() . ', ' . $this->order->get_billing_email(),
			'BillDeliveryStatus' => 'Print',
			'AppliedToDate' => 0,
			'BalanceDueAmount' =>  0,
			'Status' => $this->status,
			'LastPaymentDate' => null,
			'ForeignCurrency' => null,
			'Order' => array(
				'UID' => $this->invoice_guid,
			),
		);

		return $res;
	}
}
