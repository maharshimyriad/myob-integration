<?php
if (!defined('ABSPATH')) {
	exit;
}

if (!current_user_can('manage_woocommerce')) {
	wp_die(esc_html__('You do not have permission to access this page.', 'wc-myob-integration'));
}

$connector = new Opmc_Myob_Connector();
$result = null;
$error_message = '';

$defaults = array(
	'document_type' => 'quote',
	'layout_type' => 'item',
	'customer_display_id' => 'TEST',
	'customer_uid' => '',
	'item_number' => 'UNIAL150',
	'item_uid' => '',
	'quantity' => '1',
	'unit_price' => '',
	'tax_code_uid' => '',
	'freight' => '0.00',
	'total_tax' => '0.00',
	'customer_po_number' => 'WEB-TEST-' . gmdate('Ymd-His'),
	'ship_to_address' => 'Test Customer, 11-15 Martha St, Sydney, NSW, 2000, AU',
	'shipping_method' => 'FHS Freight TBC',
	'comment' => 'MYOB API test sale created from the WooCommerce admin test page.',
	'journal_memo' => 'MYOB API test sale',
	'description_mode' => 'omit',
	'description_value' => '',
);

$form = $defaults;
if (!empty($_POST['myob_test_sale'])) {
	$form = array(
		'document_type' => isset($_POST['document_type']) ? sanitize_key($_POST['document_type']) : $defaults['document_type'],
		'layout_type' => isset($_POST['layout_type']) ? sanitize_key($_POST['layout_type']) : $defaults['layout_type'],
		'customer_display_id' => isset($_POST['customer_display_id']) ? sanitize_text_field(wp_unslash($_POST['customer_display_id'])) : '',
		'customer_uid' => isset($_POST['customer_uid']) ? sanitize_text_field(wp_unslash($_POST['customer_uid'])) : '',
		'item_number' => isset($_POST['item_number']) ? sanitize_text_field(wp_unslash($_POST['item_number'])) : '',
		'item_uid' => isset($_POST['item_uid']) ? sanitize_text_field(wp_unslash($_POST['item_uid'])) : '',
		'quantity' => isset($_POST['quantity']) ? wc_format_decimal(wp_unslash($_POST['quantity']), 2) : '1',
		'unit_price' => isset($_POST['unit_price']) ? wc_format_decimal(wp_unslash($_POST['unit_price']), 2) : '',
		'tax_code_uid' => isset($_POST['tax_code_uid']) ? sanitize_text_field(wp_unslash($_POST['tax_code_uid'])) : '',
		'freight' => isset($_POST['freight']) ? wc_format_decimal(wp_unslash($_POST['freight']), 2) : '0.00',
		'total_tax' => isset($_POST['total_tax']) ? wc_format_decimal(wp_unslash($_POST['total_tax']), 2) : '0.00',
		'customer_po_number' => isset($_POST['customer_po_number']) ? sanitize_text_field(wp_unslash($_POST['customer_po_number'])) : '',
		'ship_to_address' => isset($_POST['ship_to_address']) ? sanitize_textarea_field(wp_unslash($_POST['ship_to_address'])) : '',
		'shipping_method' => isset($_POST['shipping_method']) ? sanitize_text_field(wp_unslash($_POST['shipping_method'])) : '',
		'comment' => isset($_POST['comment']) ? sanitize_textarea_field(wp_unslash($_POST['comment'])) : '',
		'journal_memo' => isset($_POST['journal_memo']) ? sanitize_text_field(wp_unslash($_POST['journal_memo'])) : '',
		'description_mode' => isset($_POST['description_mode']) ? sanitize_key($_POST['description_mode']) : 'omit',
		'description_value' => isset($_POST['description_value']) ? sanitize_textarea_field(wp_unslash($_POST['description_value'])) : '',
	);
}

if (!empty($_POST['myob_test_sale']) && check_admin_referer('opmc_myob_test_sale_action', 'opmc_myob_test_sale_nonce')) {
	$form['dry_run'] = isset($_POST['preview_test_sale']);

	try {
		$result = $connector->create_test_sale($form);
	} catch (Throwable $e) {
		$error_message = $e->getMessage();
	}
}
?>
<div class="wrap">
	<h1><?php esc_html_e('MYOB API Test', 'wc-myob-integration'); ?></h1>
	<p><?php esc_html_e('Use this page to resend a controlled MYOB sale payload without creating a new WooCommerce order each time.', 'wc-myob-integration'); ?></p>
	<p><?php esc_html_e('If you want MYOB to use the item default description, keep Description Mode set to "Omit field".', 'wc-myob-integration'); ?></p>
	<p><?php esc_html_e('For debugging, compare "Omit field" against "Use MYOB item description". If omit stays blank but explicit MYOB item description works, the API is not auto-filling descriptions.', 'wc-myob-integration'); ?></p>

	<?php if ($error_message) : ?>
		<div class="notice notice-error"><p><?php echo esc_html($error_message); ?></p></div>
	<?php endif; ?>

	<?php if ($result && empty($result['dry_run'])) : ?>
		<div class="notice notice-success"><p><?php echo esc_html('MYOB request sent to ' . $result['endpoint'] . '.'); ?></p></div>
	<?php elseif ($result) : ?>
		<div class="notice notice-info"><p><?php echo esc_html('Payload preview generated for ' . $result['endpoint'] . '.'); ?></p></div>
	<?php endif; ?>

	<form method="post">
		<?php wp_nonce_field('opmc_myob_test_sale_action', 'opmc_myob_test_sale_nonce'); ?>
		<input type="hidden" name="myob_test_sale" value="1" />
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="document_type"><?php esc_html_e('Document Type', 'wc-myob-integration'); ?></label></th>
					<td>
						<select name="document_type" id="document_type">
							<option value="quote" <?php selected($form['document_type'], 'quote'); ?>>Quote</option>
							<option value="order" <?php selected($form['document_type'], 'order'); ?>>Order</option>
							<option value="invoice" <?php selected($form['document_type'], 'invoice'); ?>>Invoice</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="layout_type"><?php esc_html_e('Layout Type', 'wc-myob-integration'); ?></label></th>
					<td>
						<select name="layout_type" id="layout_type">
							<option value="item" <?php selected($form['layout_type'], 'item'); ?>>Item</option>
							<option value="service" <?php selected($form['layout_type'], 'service'); ?>>Service</option>
							<option value="professional" <?php selected($form['layout_type'], 'professional'); ?>>Professional</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="customer_display_id"><?php esc_html_e('Customer Display ID', 'wc-myob-integration'); ?></label></th>
					<td>
						<input type="text" class="regular-text" name="customer_display_id" id="customer_display_id" value="<?php echo esc_attr($form['customer_display_id']); ?>" />
						<p class="description"><?php esc_html_e('Preferred lookup key. Example: TEST or a live MYOB customer display ID.', 'wc-myob-integration'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="customer_uid"><?php esc_html_e('Customer UID', 'wc-myob-integration'); ?></label></th>
					<td><input type="text" class="regular-text" name="customer_uid" id="customer_uid" value="<?php echo esc_attr($form['customer_uid']); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="item_number"><?php esc_html_e('Item Number', 'wc-myob-integration'); ?></label></th>
					<td>
						<input type="text" class="regular-text" name="item_number" id="item_number" value="<?php echo esc_attr($form['item_number']); ?>" />
						<p class="description"><?php esc_html_e('Preferred lookup key. Example: UNIAL150.', 'wc-myob-integration'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="item_uid"><?php esc_html_e('Item UID', 'wc-myob-integration'); ?></label></th>
					<td><input type="text" class="regular-text" name="item_uid" id="item_uid" value="<?php echo esc_attr($form['item_uid']); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="quantity"><?php esc_html_e('Quantity', 'wc-myob-integration'); ?></label></th>
					<td><input type="number" step="0.01" min="0.01" name="quantity" id="quantity" value="<?php echo esc_attr($form['quantity']); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="unit_price"><?php esc_html_e('Unit Price', 'wc-myob-integration'); ?></label></th>
					<td>
						<input type="number" step="0.01" name="unit_price" id="unit_price" value="<?php echo esc_attr($form['unit_price']); ?>" />
						<p class="description"><?php esc_html_e('Leave blank to use the MYOB item BaseSellingPrice if available.', 'wc-myob-integration'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="tax_code_uid"><?php esc_html_e('Tax Code UID', 'wc-myob-integration'); ?></label></th>
					<td>
						<input type="text" class="regular-text" name="tax_code_uid" id="tax_code_uid" value="<?php echo esc_attr($form['tax_code_uid']); ?>" />
						<p class="description"><?php esc_html_e('Leave blank to use the item tax code or the plugin default line tax code.', 'wc-myob-integration'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="freight"><?php esc_html_e('Freight', 'wc-myob-integration'); ?></label></th>
					<td><input type="number" step="0.01" name="freight" id="freight" value="<?php echo esc_attr($form['freight']); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="total_tax"><?php esc_html_e('Total Tax', 'wc-myob-integration'); ?></label></th>
					<td><input type="number" step="0.01" name="total_tax" id="total_tax" value="<?php echo esc_attr($form['total_tax']); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="description_mode"><?php esc_html_e('Description Mode', 'wc-myob-integration'); ?></label></th>
					<td>
						<select name="description_mode" id="description_mode">
							<option value="omit" <?php selected($form['description_mode'], 'omit'); ?>>Omit field</option>
							<option value="blank" <?php selected($form['description_mode'], 'blank'); ?>>Send blank</option>
							<option value="myob_item" <?php selected($form['description_mode'], 'myob_item'); ?>>Use MYOB item description</option>
							<option value="custom" <?php selected($form['description_mode'], 'custom'); ?>>Send custom text</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="description_value"><?php esc_html_e('Custom Description', 'wc-myob-integration'); ?></label></th>
					<td><textarea name="description_value" id="description_value" class="large-text" rows="3"><?php echo esc_textarea($form['description_value']); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="customer_po_number"><?php esc_html_e('Customer PO Number', 'wc-myob-integration'); ?></label></th>
					<td><input type="text" class="regular-text" name="customer_po_number" id="customer_po_number" value="<?php echo esc_attr($form['customer_po_number']); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="ship_to_address"><?php esc_html_e('Ship To Address', 'wc-myob-integration'); ?></label></th>
					<td><textarea name="ship_to_address" id="ship_to_address" class="large-text" rows="3"><?php echo esc_textarea($form['ship_to_address']); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="shipping_method"><?php esc_html_e('Shipping Method', 'wc-myob-integration'); ?></label></th>
					<td><input type="text" class="regular-text" name="shipping_method" id="shipping_method" value="<?php echo esc_attr($form['shipping_method']); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="comment"><?php esc_html_e('Comment', 'wc-myob-integration'); ?></label></th>
					<td><textarea name="comment" id="comment" class="large-text" rows="2"><?php echo esc_textarea($form['comment']); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="journal_memo"><?php esc_html_e('Journal Memo', 'wc-myob-integration'); ?></label></th>
					<td><input type="text" class="regular-text" name="journal_memo" id="journal_memo" value="<?php echo esc_attr($form['journal_memo']); ?>" /></td>
				</tr>
			</tbody>
		</table>
		<p class="submit">
			<button type="submit" name="preview_test_sale" class="button button-secondary"><?php esc_html_e('Preview Payload', 'wc-myob-integration'); ?></button>
			<button type="submit" name="send_test_sale" class="button button-primary"><?php esc_html_e('Send To MYOB', 'wc-myob-integration'); ?></button>
		</p>
	</form>

	<?php if ($result) : ?>
		<h2><?php esc_html_e('Resolved Data', 'wc-myob-integration'); ?></h2>
		<table class="widefat striped" style="max-width:900px;">
			<tbody>
				<tr><td><strong>Endpoint</strong></td><td><?php echo esc_html($result['endpoint']); ?></td></tr>
				<tr><td><strong>Customer UID</strong></td><td><?php echo esc_html($result['resolved_customer']['uid']); ?></td></tr>
				<tr><td><strong>Customer Display ID</strong></td><td><?php echo esc_html($result['resolved_customer']['display_id']); ?></td></tr>
				<tr><td><strong>Item UID</strong></td><td><?php echo esc_html($result['resolved_item']['uid']); ?></td></tr>
				<tr><td><strong>Item Number</strong></td><td><?php echo esc_html($result['resolved_item']['number']); ?></td></tr>
				<tr><td><strong>Preferred MYOB Item Description</strong></td><td><?php echo esc_html($result['resolved_item']['preferred_description']); ?></td></tr>
			</tbody>
		</table>

		<h2><?php esc_html_e('Debug Notes', 'wc-myob-integration'); ?></h2>
		<textarea class="large-text code" rows="8" readonly><?php echo esc_textarea(implode("\n", $result['debug_notes'])); ?></textarea>

		<h2><?php esc_html_e('MYOB Item Description Candidates', 'wc-myob-integration'); ?></h2>
		<textarea class="large-text code" rows="10" readonly><?php echo esc_textarea(wp_json_encode($result['resolved_item']['description_candidates'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></textarea>

		<h2><?php esc_html_e('Resolved MYOB Item', 'wc-myob-integration'); ?></h2>
		<textarea class="large-text code" rows="18" readonly><?php echo esc_textarea(wp_json_encode($result['resolved_item']['raw'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></textarea>

		<h2><?php esc_html_e('Payload', 'wc-myob-integration'); ?></h2>
		<textarea class="large-text code" rows="22" readonly><?php echo esc_textarea(wp_json_encode($result['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></textarea>

		<?php if (!empty($result['response'])) : ?>
			<h2><?php esc_html_e('MYOB Response', 'wc-myob-integration'); ?></h2>
			<textarea class="large-text code" rows="18" readonly><?php echo esc_textarea(wp_json_encode($result['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></textarea>
		<?php endif; ?>
	<?php endif; ?>
</div>
