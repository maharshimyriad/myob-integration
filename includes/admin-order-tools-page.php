<?php
/**
 * MYOB order tools admin page.
 *
 * @package WC_MYOB_Integration
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html__('MYOB Order Tools', 'wc-myob-integration'); ?></h1>
	<p><?php echo esc_html__('Enter a WooCommerce order ID or displayed order number to sync it or fetch its current MYOB document.', 'wc-myob-integration'); ?></p>

	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row">
					<label for="opmc_myob_order_tools_order_number"><?php echo esc_html__('Order ID / Number', 'wc-myob-integration'); ?></label>
				</th>
				<td>
					<input
						type="text"
						id="opmc_myob_order_tools_order_number"
						class="regular-text"
						placeholder="<?php echo esc_attr__('e.g. 43209', 'wc-myob-integration'); ?>"
					/>
					<p class="description"><?php echo esc_html__('Use the Woo order ID or the Woo order number shown in admin.', 'wc-myob-integration'); ?></p>
				</td>
			</tr>
		</tbody>
	</table>

	<p class="submit">
		<button type="button" class="button button-primary" id="opmc_myob_order_tools_sync_now"><?php echo esc_html__('Sync Now', 'wc-myob-integration'); ?></button>
		<button type="button" class="button" id="opmc_myob_order_tools_view_now"><?php echo esc_html__('View Order in MYOB', 'wc-myob-integration'); ?></button>
	</p>

	<div id="opmc_myob_order_tools_message" style="display:none;margin-top:16px;"></div>
	<div id="opmc_myob_order_tools_result" style="display:none;margin-top:16px;"></div>
</div>
