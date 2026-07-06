<?php

abstract class Opmc_Erp_Connector {
	
	/*
	* Returns true if the credentials to connect to the ERP system are defined
	*/
	abstract public function are_credentials_defined();

	/*
	* Refresh the (typically OAUTH2) token.  Normally done every 5-10 minutes via WP cron
	*/ 
	abstract public function refresh_token();

	/*
	* Get the unique ID for a customer in the ERP system based on a WooCommerce order ID
	*/
	abstract public function get_erp_customer( $order_id );


	/*
	* Create customer in the ERP system based on an order in Woo
	*/
	abstract public function create_customer( $order_id );


	/*
	* Update a customer address in the ERP system based on an order in Woo
	*/
	abstract public function update_customer_address( $order_id, $customer_record );

	/* 
	* Create an inventory item in the the ERP system based on a WooCommerce order item
	*/
	abstract public function create_erp_inventory_item( $order_item );

	/*
	* Get all item/product records from the ERP, or if passed in a list of SKUs get
	* the desired SKUs
	*
	* @param $skus - an array of strings containing the desired skus or null to return all
	*/
	abstract public function get_erp_inventory_items( $skus = null );

	/*
	* Verify that all the items in the Woo order are in the ERP system as inventory items.
	* If not, then the items are created in the ERP so the sale can proceed.
	*/
	abstract public function verify_myob_inventory_items( $order_id );

	/*
	* Create a new invoice in the ERP system based on an Order ID in Woo
	*/
	abstract public function create_invoice( $order_id, $customer_uid );

	/*
	* Place a new order
	*/
	abstract public function place_order( $order_id );

	/*
	* Set an order as paid
	*/
	abstract public function receive_payment( $order_id );

	/*
	* Synchronize inventory levels from the ERP to Woo
	*/
	abstract public function sync_inventory_data( $skus = null );
}
