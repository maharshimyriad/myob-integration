<?php
/**
 * MYOB salespersons admin page.
 *
 * @package WC_MYOB_Integration
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html__('MYOB Salespersons', 'wc-myob-integration'); ?></h1>
	<p><?php echo esc_html__('Load salesperson records from MYOB and inspect their details.', 'wc-myob-integration'); ?></p>

	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row">
					<label for="opmc_myob_salesperson_search"><?php echo esc_html__('Search', 'wc-myob-integration'); ?></label>
				</th>
				<td>
					<input
						type="text"
						id="opmc_myob_salesperson_search"
						class="regular-text"
						placeholder="<?php echo esc_attr__('Optional name, display ID, email, or UID', 'wc-myob-integration'); ?>"
					/>
					<p class="description"><?php echo esc_html__('Leave blank to load all salespersons returned by MYOB.', 'wc-myob-integration'); ?></p>
				</td>
			</tr>
		</tbody>
	</table>

	<p class="submit">
		<button type="button" class="button button-primary" id="opmc_myob_load_salespersons"><?php echo esc_html__('Load Salespersons', 'wc-myob-integration'); ?></button>
	</p>

	<div id="opmc_myob_salespersons_message" style="display:none;margin-top:16px;"></div>
	<div id="opmc_myob_salespersons_result" style="display:none;margin-top:16px;"></div>
</div>

<script type="text/javascript">
	(function ($) {
		function escapeHtml(value) {
			return $('<div/>').text(value == null ? '' : String(value)).html();
		}

		function renderRows(items) {
			var rows = '';

			$.each(items, function (_, item) {
				rows += '<tr>'
					+ '<td>' + escapeHtml(item.Name || '') + '</td>'
					+ '<td>' + escapeHtml(item.DisplayID || '') + '</td>'
					+ '<td>' + escapeHtml(item.UID || '') + '</td>'
					+ '<td>' + escapeHtml(item.Email || '') + '</td>'
					+ '<td>' + escapeHtml(item.Phone || '') + '</td>'
					+ '<td>' + escapeHtml(item.IsActive ? 'Yes' : 'No') + '</td>'
					+ '</tr>';
			});

			return rows;
		}

		$('#opmc_myob_load_salespersons').on('click', function () {
			var $message = $('#opmc_myob_salespersons_message');
			var $result = $('#opmc_myob_salespersons_result');
			var search = $('#opmc_myob_salesperson_search').val();

			$message
				.removeClass('notice-error notice-success')
				.addClass('notice notice-warning inline')
				.html('<p>Loading salesperson details from MYOB...</p>')
				.show();

			$result.hide().empty();

			$.post(OpmcMyobScriptAjax.ajaxurl, {
				action: 'MYOB_view_salespersons_ajax',
				security: OpmcMyobScriptAjax.ajax_nonce,
				search: search
			}).done(function (response) {
				if (!response || !response.success) {
					$message
						.removeClass('notice-warning notice-success')
						.addClass('notice-error')
						.html('<p>' + escapeHtml(response && response.message ? response.message : 'Unable to load salespersons.') + '</p>');
					return;
				}

				var tableHtml = '<table class="widefat striped">'
					+ '<thead><tr><th>Name</th><th>Display ID</th><th>UID</th><th>Email</th><th>Phone</th><th>Active</th></tr></thead>'
					+ '<tbody>' + renderRows(response.salespersons || []) + '</tbody></table>';

				var rawHtml = '<h2 style="margin-top:24px;">Raw Response</h2><pre style="max-height:480px;overflow:auto;background:#fff;border:1px solid #ccd0d4;padding:12px;">'
					+ escapeHtml(JSON.stringify(response.salespersons, null, 2))
					+ '</pre>';

				$message
					.removeClass('notice-warning notice-error')
					.addClass('notice-success')
					.html('<p>' + escapeHtml(response.message) + '</p>');

				$result.html(tableHtml + rawHtml).show();
			}).fail(function () {
				$message
					.removeClass('notice-warning notice-success')
					.addClass('notice-error')
					.html('<p>Request failed while loading salespersons.</p>');
			});
		});
	})(jQuery);
</script>
