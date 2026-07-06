<?php
/**
 * MYOB order tools admin page.
 *
 * @package Stars_MYOB_Connector
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html__('MYOB Order Tools', 'stars-myob-accountright-connector-for-woocommerce'); ?></h1>
	<p><?php echo esc_html__('Enter a WooCommerce order ID or displayed order number to sync it or fetch its current MYOB document.', 'stars-myob-accountright-connector-for-woocommerce'); ?></p>

	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row">
					<label for="opmc_myob_order_tools_order_number"><?php echo esc_html__('Order ID / Number', 'stars-myob-accountright-connector-for-woocommerce'); ?></label>
				</th>
				<td>
					<input
						type="text"
						id="opmc_myob_order_tools_order_number"
						class="regular-text"
						placeholder="<?php echo esc_attr__('e.g. 43209', 'stars-myob-accountright-connector-for-woocommerce'); ?>"
					/>
					<p class="description"><?php echo esc_html__('Use the Woo order ID or the Woo order number shown in admin.', 'stars-myob-accountright-connector-for-woocommerce'); ?></p>
				</td>
			</tr>
		</tbody>
	</table>

	<p class="submit">
		<button type="button" class="button button-primary" id="opmc_myob_order_tools_sync_now"><?php echo esc_html__('Sync Now', 'stars-myob-accountright-connector-for-woocommerce'); ?></button>
		<button type="button" class="button" id="opmc_myob_order_tools_view_now"><?php echo esc_html__('View Order in MYOB', 'stars-myob-accountright-connector-for-woocommerce'); ?></button>
	</p>

	<div id="opmc_myob_order_tools_message" style="display:none;margin-top:16px;"></div>
	<div id="opmc_myob_order_tools_result" style="display:none;margin-top:16px;"></div>
</div>
