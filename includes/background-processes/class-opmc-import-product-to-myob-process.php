<?php

/**
 * Import Customer from MYOB to Woo in background process.
 */
class Opmc_Import_Product_To_Myob_Process extends WP_Background_Process {


	protected $action = 'cron_import_product_to_myob_process';

	/**
	 * Import Customer to Woo
	 * 
	 * @param  [type] $item [description]
	 * @return [type]       [description]
	 */
	protected function task( $item ) {
		// sleep( 1 );
		$connector = new Opmc_Myob_Connector();
		$search_product = $connector->search_product_item($item);
		if ( false == $search_product) {
			$connector->sync_to_myob( $item );
		}
		$product = wc_get_product($item);
		$product_name = $product ? $product->get_name() : $item;
		$synced_product = get_option('woo_product_synced_to_myob_count');
		update_option('woo_product_synced_to_myob_count', $synced_product + 1 );
		$total_queue_products = get_option('myob_woo_product_count');
		$connector->create_wc_log('[Export Product] [Success] [Product - ' . print_r($product_name, 1) . ' exported to MYOB.]');
		if (( $synced_product + 1 ) === $total_queue_products ) {
			$connector->create_wc_log('[Export Product] [Completed] [Product export queue completed for ' . print_r($total_queue_products, 1) . ' products.]');
		}
		return false;
	}


	/**
	 * Complete
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		parent::complete();
		// Show notice to user or perform some other arbitrary task...
	}

	public function is_queue_empty() {
		 return parent::is_queue_empty();
	}

	public function empty_data() {
		$this->data = array();
	}
}
