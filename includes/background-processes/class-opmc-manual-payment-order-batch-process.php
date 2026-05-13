<?php

/**
 * Import Customer from MYOB to Woo in background process.
 */
class Opmc_Manual_Payment_Order_Batch_Process extends WP_Background_Process {

	
	protected $action = 'manual_payment_order_batch_process';

	/**
	 * Import Customer to Woo
	 * 
	 * @param  [type] $item [description]
	 * @return [type]       [description]
	 */
	protected function task( $order_id ) {
		$connection = new Opmc_Myob_Connector();
		$connection->create_order_for_on_hold_orders( $order_id );
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
	}
}
