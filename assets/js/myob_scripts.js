jQuery(document).ready(function(){

	var auth = document.getElementById('auth');
	var auth1 = document.getElementById('auth1');
	var account = jQuery('#woocommerce_myob_integrations_WC_MYOB_income_account').val();
	var username = jQuery('#woocommerce_myob_integrations_WC_MYOB_company_file_username').val();
	if (auth || auth1) {
		jQuery('#woocommerce_myob_integrations_WC_MYOB_company_file_username').after('<span class="valid_error"><a href="https://woocommerce.com/document/myob/"><i class = "fa fa-close"></i></a><span>Username Invalid.</span></span>');
		jQuery('#woocommerce_myob_integrations_WC_MYOB_company_file_id').css({ 'border': '2px solid red' });
		
	} else {
		if (account != 0 && username != ''){
			jQuery('#woocommerce_myob_integrations_WC_MYOB_company_file_username').after('<span class="valid_access"><i class = "fa fa-check"></i><span>Username Valid.</span></span>');
			//jQuery('#woocommerce_myob_integrations_WC_MYOB_company_file_id ').css({ 'backgroundColor': '#90ee9069', 'color': '#006400' });
		}
	}

	var app_key = jQuery('#woocommerce_myob_integrations_WC_MYOB_client_id').val();
	var app_secret = jQuery('#woocommerce_myob_integrations_WC_MYOB_client_secret').val();
	var file_name = jQuery('#woocommerce_myob_integrations_WC_MYOB_company_file_id').val();
	if(app_key!='' && app_secret!=''){
		jQuery('#woocommerce_myob_integrations_allow_access').prop('disabled',false);
		jQuery('#woocommerce_myob_integrations_allow_sync').prop('disabled',false);

		jQuery('#woocommerce_myob_integrations_export_product_to_myob').prop('disabled',false); 
	}
		jQuery('#woocommerce_myob_integrations_reload_accounts_list').prop('disabled',false);
	if (file_name != null && file_name != 0) {
		var company_file_pulldown = jQuery('#woocommerce_myob_integrations_WC_MYOB_company_file_id option:selected').text();
		jQuery('p.status_of_conn').text('Access Validated and "' + company_file_pulldown + '" Connected.');
		jQuery('#woocommerce_myob_integrations_allow_access').text('Revalidate Access');
		
		// Enable Myriad Solutionz custom buttons
		jQuery('#woocommerce_myob_integrations_invoice_sync').prop('disabled',false);
		jQuery('#woocommerce_myob_integrations_customers_sync').prop('disabled',false);
		jQuery('#woocommerce_myob_integrations_customer_email_sync_button').prop('disabled',false);
		jQuery('#woocommerce_myob_integrations_product_sku_sync_button').prop('disabled',false);
		jQuery('#woocommerce_myob_integrations_product_sku_view_button').prop('disabled',false);
		jQuery('#woocommerce_myob_integrations_order_number_sync_button').prop('disabled',false);
	}

	function opmcEscapeHtml(value) {
		return jQuery('<div/>').text(value == null ? '' : String(value)).html();
	}

	function opmcCloseProductPopup() {
		jQuery('.opmc-myob-product-modal-overlay').remove();
	}

	function opmcOpenProductPopup(product, tieredPricing, rawItem) {
		var rowsHtml = '';
		var rawJson = opmcEscapeHtml(JSON.stringify(rawItem || {}, null, 2));
		if (tieredPricing && tieredPricing.length) {
			jQuery.each(tieredPricing, function(index, row) {
				rowsHtml += '<tr>' +
					'<td>' + opmcEscapeHtml(row.quantity_from === 0 ? 'Base Price' : row.quantity_from + '+') + '</td>' +
					'<td>' + opmcEscapeHtml(row.levels.LevelA || '-') + '</td>' +
					'<td>' + opmcEscapeHtml(row.levels.LevelB || '-') + '</td>' +
					'<td>' + opmcEscapeHtml(row.levels.LevelC || '-') + '</td>' +
					'<td>' + opmcEscapeHtml(row.levels.LevelD || '-') + '</td>' +
					'<td>' + opmcEscapeHtml(row.levels.LevelE || '-') + '</td>' +
					'<td>' + opmcEscapeHtml(row.levels.LevelF || '-') + '</td>' +
				'</tr>';
			});
		} else {
			rowsHtml = '<tr><td colspan="7">No tiered pricing found.</td></tr>';
		}

		var modalHtml = '' +
			'<div class="opmc-myob-product-modal-overlay">' +
				'<div class="opmc-myob-product-modal">' +
					'<div class="opmc-myob-product-modal-header">' +
						'<h3>MYOB Product Details</h3>' +
						'<button type="button" class="button opmc-myob-product-modal-close">Close</button>' +
					'</div>' +
					'<div class="opmc-myob-product-modal-content">' +
						'<div class="opmc-myob-view-toggle">' +
							'<button type="button" class="button button-primary opmc-myob-view-btn active" data-target="details">Details</button>' +
							'<button type="button" class="button opmc-myob-view-btn" data-target="raw">Raw JSON (/Inventory/Item)</button>' +
						'</div>' +
						'<div class="opmc-myob-view-section opmc-myob-view-details active">' +
							'<p><strong>SKU:</strong> ' + opmcEscapeHtml(product.sku) + '</p>' +
							'<p><strong>Name:</strong> ' + opmcEscapeHtml(product.name) + '</p>' +
							'<p><strong>Description:</strong> ' + opmcEscapeHtml(product.description) + '</p>' +
							'<p><strong>Base Selling Price:</strong> ' + opmcEscapeHtml(product.base_selling_price) + '</p>' +
							'<p><strong>Qty On Hand:</strong> ' + opmcEscapeHtml(product.quantity_on_hand) + '</p>' +
							'<p><strong>Qty Available:</strong> ' + opmcEscapeHtml(product.quantity_available) + '</p>' +
							'<h4>Tiered Pricing</h4>' +
							'<div class="opmc-myob-pricing-table-wrap">' +
								'<table class="widefat striped opmc-myob-pricing-table">' +
									'<thead>' +
										'<tr>' +
											'<th>Quantity</th>' +
											'<th>Level A</th>' +
											'<th>Level B</th>' +
											'<th>Level C</th>' +
											'<th>Level D</th>' +
											'<th>Level E</th>' +
											'<th>Level F</th>' +
										'</tr>' +
									'</thead>' +
									'<tbody>' + rowsHtml + '</tbody>' +
								'</table>' +
							'</div>' +
						'</div>' +
						'<div class="opmc-myob-view-section opmc-myob-view-raw">' +
							'<pre class="opmc-myob-raw-json">' + rawJson + '</pre>' +
						'</div>' +
					'</div>' +
				'</div>' +
			'</div>';

		opmcCloseProductPopup();
		jQuery('body').append(modalHtml);
	}

	function opmcRenderOrderAddress(address) {
		if (!address) {
			return '<p>No address data found.</p>';
		}

		var parts = [];
		if (address.Name) {
			parts.push('<strong>' + opmcEscapeHtml(address.Name) + '</strong>');
		}
		if (address.Line1) {
			parts.push(opmcEscapeHtml(address.Line1));
		}
		if (address.Line2) {
			parts.push(opmcEscapeHtml(address.Line2));
		}
		if (address.Line3) {
			parts.push(opmcEscapeHtml(address.Line3));
		}
		if (address.Line4) {
			parts.push(opmcEscapeHtml(address.Line4));
		}
		if (address.Email) {
			parts.push('Email: ' + opmcEscapeHtml(address.Email));
		}
		if (address.Phone1) {
			parts.push('Phone: ' + opmcEscapeHtml(address.Phone1));
		}

		if (!parts.length) {
			return '<p>No address data found.</p>';
		}

		return '<p>' + parts.join('<br>') + '</p>';
	}

	function opmcRenderOrderLines(lines) {
		var rowsHtml = '';

		if (lines && lines.length) {
			jQuery.each(lines, function(index, line) {
				var itemName = '';
				if (line.Item && line.Item.Name) {
					itemName = line.Item.Name;
				} else if (line.Description) {
					itemName = line.Description;
				}

				rowsHtml += '<tr>' +
					'<td>' + opmcEscapeHtml((index + 1)) + '</td>' +
					'<td>' + opmcEscapeHtml(line.Item && line.Item.Number ? line.Item.Number : '') + '</td>' +
					'<td>' + opmcEscapeHtml(itemName) + '</td>' +
					'<td>' + opmcEscapeHtml(line.Quantity != null ? line.Quantity : '') + '</td>' +
					'<td>' + opmcEscapeHtml(line.UnitPrice != null ? line.UnitPrice : '') + '</td>' +
					'<td>' + opmcEscapeHtml(line.DiscountPercent != null ? line.DiscountPercent : '') + '</td>' +
					'<td>' + opmcEscapeHtml(line.Total != null ? line.Total : '') + '</td>' +
					'<td>' + opmcEscapeHtml(line.Job && line.Job.Name ? line.Job.Name : '') + '</td>' +
				'</tr>';
			});
		} else {
			rowsHtml = '<tr><td colspan="8">No line items found.</td></tr>';
		}

		return '' +
			'<div class="opmc-myob-pricing-table-wrap">' +
				'<table class="widefat striped opmc-myob-pricing-table">' +
					'<thead>' +
						'<tr>' +
							'<th>#</th>' +
							'<th>SKU</th>' +
							'<th>Description</th>' +
							'<th>Qty</th>' +
							'<th>Unit Price</th>' +
							'<th>Discount %</th>' +
							'<th>Line Total</th>' +
							'<th>Job</th>' +
						'</tr>' +
					'</thead>' +
					'<tbody>' + rowsHtml + '</tbody>' +
				'</table>' +
			'</div>';
	}

	function opmcOpenOrderPopup(order, rawOrder) {
		var rawJson = opmcEscapeHtml(JSON.stringify(rawOrder || {}, null, 2));
		var linesHtml = opmcRenderOrderLines(rawOrder && rawOrder.Lines ? rawOrder.Lines : []);
		var billToHtml = opmcRenderOrderAddress(rawOrder && rawOrder.BillTo ? rawOrder.BillTo : null);
		var shipToHtml = opmcRenderOrderAddress(rawOrder && rawOrder.ShipTo ? rawOrder.ShipTo : null);

		var modalHtml = '' +
			'<div class="opmc-myob-product-modal-overlay">' +
				'<div class="opmc-myob-product-modal" style="max-width: 1100px;">' +
					'<div class="opmc-myob-product-modal-header">' +
						'<h3>MYOB ' + opmcEscapeHtml(order.document_type || 'Order') + ' Details</h3>' +
						'<button type="button" class="button opmc-myob-product-modal-close">Close</button>' +
					'</div>' +
					'<div class="opmc-myob-product-modal-content">' +
						'<div class="opmc-myob-view-toggle">' +
							'<button type="button" class="button button-primary opmc-myob-view-btn active" data-target="details">Details</button>' +
							'<button type="button" class="button opmc-myob-view-btn" data-target="raw">Raw JSON</button>' +
						'</div>' +
						'<div class="opmc-myob-view-section opmc-myob-view-details active">' +
							'<h4>Document</h4>' +
							'<table class="widefat striped"><tbody>' +
								'<tr><th>Woo Order ID</th><td>' + opmcEscapeHtml(order.woo_order_id) + '</td></tr>' +
								'<tr><th>Woo Order Number</th><td>' + opmcEscapeHtml(order.woo_order_number) + '</td></tr>' +
								'<tr><th>MYOB Type</th><td>' + opmcEscapeHtml(order.document_type) + '</td></tr>' +
								'<tr><th>MYOB UID</th><td>' + opmcEscapeHtml(order.myob_uid) + '</td></tr>' +
								'<tr><th>MYOB Number</th><td>' + opmcEscapeHtml(order.myob_number) + '</td></tr>' +
								'<tr><th>Customer PO Number</th><td>' + opmcEscapeHtml(order.customer_po_number) + '</td></tr>' +
								'<tr><th>Status</th><td>' + opmcEscapeHtml(order.status) + '</td></tr>' +
								'<tr><th>Date</th><td>' + opmcEscapeHtml(order.date) + '</td></tr>' +
								'<tr><th>Promised Date</th><td>' + opmcEscapeHtml(order.promised_date) + '</td></tr>' +
								'<tr><th>Ship Via</th><td>' + opmcEscapeHtml(order.ship_via) + '</td></tr>' +
								'<tr><th>Tax Inclusive</th><td>' + opmcEscapeHtml(order.is_tax_inclusive) + '</td></tr>' +
								'<tr><th>Row Version</th><td>' + opmcEscapeHtml(order.row_version) + '</td></tr>' +
							'</tbody></table>' +
							'<h4 style="margin-top:16px;">Customer</h4>' +
							'<table class="widefat striped"><tbody>' +
								'<tr><th>Customer Name</th><td>' + opmcEscapeHtml(order.customer_name) + '</td></tr>' +
								'<tr><th>Customer Display ID</th><td>' + opmcEscapeHtml(order.customer_display_id) + '</td></tr>' +
								'<tr><th>Customer UID</th><td>' + opmcEscapeHtml(order.customer_uid) + '</td></tr>' +
								'<tr><th>Bill To Name</th><td>' + opmcEscapeHtml(order.bill_to_name) + '</td></tr>' +
								'<tr><th>Ship To Name</th><td>' + opmcEscapeHtml(order.ship_to_name) + '</td></tr>' +
							'</tbody></table>' +
							'<h4 style="margin-top:16px;">Amounts</h4>' +
							'<table class="widefat striped"><tbody>' +
								'<tr><th>Subtotal</th><td>' + opmcEscapeHtml(order.subtotal) + '</td></tr>' +
								'<tr><th>Total Tax</th><td>' + opmcEscapeHtml(order.total_tax) + '</td></tr>' +
								'<tr><th>Freight</th><td>' + opmcEscapeHtml(order.freight) + '</td></tr>' +
								'<tr><th>Freight Tax</th><td>' + opmcEscapeHtml(order.freight_tax) + '</td></tr>' +
								'<tr><th>Total Amount</th><td>' + opmcEscapeHtml(order.total_amount) + '</td></tr>' +
								'<tr><th>Balance Due</th><td>' + opmcEscapeHtml(order.balance_due_amount) + '</td></tr>' +
							'</tbody></table>' +
							'<h4 style="margin-top:16px;">Addresses</h4>' +
							'<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">' +
								'<div><h5>Bill To</h5>' + billToHtml + '</div>' +
								'<div><h5>Ship To</h5>' + shipToHtml + '</div>' +
							'</div>' +
							'<h4 style="margin-top:16px;">Notes</h4>' +
							'<table class="widefat striped"><tbody>' +
								'<tr><th>Comment</th><td>' + opmcEscapeHtml(order.comment) + '</td></tr>' +
								'<tr><th>Journal Memo</th><td>' + opmcEscapeHtml(order.journal_memo) + '</td></tr>' +
								'<tr><th>Line Count</th><td>' + opmcEscapeHtml(order.line_count) + '</td></tr>' +
							'</tbody></table>' +
							'<h4 style="margin-top:16px;">Lines</h4>' +
							linesHtml +
						'</div>' +
						'<div class="opmc-myob-view-section opmc-myob-view-raw">' +
							'<pre class="opmc-myob-raw-json">' + rawJson + '</pre>' +
						'</div>' +
					'</div>' +
				'</div>' +
			'</div>';

		opmcCloseProductPopup();
		jQuery('body').append(modalHtml);
	}

	jQuery(document).on('click', '.opmc-myob-product-modal-overlay', function(e) {
		if (jQuery(e.target).hasClass('opmc-myob-product-modal-overlay')) {
			opmcCloseProductPopup();
		}
	});

	jQuery(document).on('click', '.opmc-myob-product-modal-close', function() {
		opmcCloseProductPopup();
	});

	jQuery(document).on('click', '.opmc-myob-view-btn', function() {
		var target = jQuery(this).data('target');
		var $container = jQuery(this).closest('.opmc-myob-product-modal-content');
		$container.find('.opmc-myob-view-btn').removeClass('button-primary active');
		jQuery(this).addClass('button-primary active');
		$container.find('.opmc-myob-view-section').removeClass('active');
		if (target === 'raw') {
			$container.find('.opmc-myob-view-raw').addClass('active');
		} else {
			$container.find('.opmc-myob-view-details').addClass('active');
		}
	});
	
	jQuery("#woocommerce_myob_integrations_allow_sync").click( function() {

		var $this = jQuery(this);

		$this.text('Loading... (60 seconds+)');
		$this.prop('disabled', true);

		alert('Inventory quantities will be syncronised from MYOB to WooCommerce in the background once you press OK.   This can take a few minutes to complete.');
		jQuery.ajax({
			type : "post",
			dataType : "json",
			url : ajaxurl,
			data : { action: "MYOB_sync_product_ajax" },
			success: function(response) {
				console.log(response);
				location.reload();
			},
			error: function(xhr, status, error) {
				alert('Error occurred: ' + error);
				console.log(xhr.responseText);
			}
		});
	});


	jQuery("#woocommerce_myob_integrations_invoice_sync").click( function() {

		var $this = jQuery(this);

		$this.text('Loading... (60 seconds+)');
		$this.prop('disabled', true);

		alert('Syncing Invoices');
		jQuery.ajax({
			type : "post",
			dataType : "json",
			url : ajaxurl,
			data : { action: "MYOB_sync_invoices_ajax" },
			success: function(response) {
				console.log(response);
				location.reload();
			}
		});
	});

	jQuery("#woocommerce_myob_integrations_customers_sync").click( function() {

		var $this = jQuery(this);

		$this.text('Loading... (60 seconds+)');
		$this.prop('disabled', true);

		alert('Syncing Customers');
		jQuery.ajax({
			type : "post",
			dataType : "json",
			url : ajaxurl,
			data : { action: "MYOB_sync_customers_ajax" },
			success: function(response) {
				console.log(response);
				location.reload();
			}
		});
	});

	jQuery("#woocommerce_myob_integrations_customer_email_sync_button").click( function() {

		var $this = jQuery(this);
		var customer_email = jQuery('#woocommerce_myob_integrations_customer_email_sync_input').val();

		if ( !customer_email || customer_email.trim() === '' ) {
			alert('Please enter a customer email address.');
			return false;
		}

		$this.text('Syncing...');
		$this.prop('disabled', true);

		jQuery.ajax({
			type : "post",
			dataType : "json",
			url : OpmcMyobScriptAjax.ajaxurl,
			data : { 
				action: "MYOB_sync_customer_by_email_ajax",
				customer_email: customer_email,
				security: OpmcMyobScriptAjax.ajax_nonce
			},
			success: function(response) {
				console.log(response);
				if ( response.success ) {
					alert('Success! ' + response.message);
					jQuery('#woocommerce_myob_integrations_customer_email_sync_input').val('');
					location.reload();
				} else {
					alert('Error: ' + response.message);
					$this.text('Sync Customer');
					$this.prop('disabled', false);
				}
			},
			error: function(xhr, status, error) {
				alert('AJAX Error: ' + error);
				$this.text('Sync Customer');
				$this.prop('disabled', false);
				console.log(xhr.responseText);
			}
		});

		return false;
	});

	jQuery("#woocommerce_myob_integrations_product_sku_sync_button").click( function() {

		var $this = jQuery(this);
		var product_sku = jQuery('#woocommerce_myob_integrations_product_sku_sync_input').val();

		if ( !product_sku || product_sku.trim() === '' ) {
			alert('Please enter a product SKU.');
			return false;
		}

		$this.text('Syncing...');
		$this.prop('disabled', true);

		jQuery.ajax({
			type : "post",
			dataType : "json",
			url : OpmcMyobScriptAjax.ajaxurl,
			data : { 
				action: "MYOB_sync_product_by_sku_ajax",
				product_sku: product_sku,
				security: OpmcMyobScriptAjax.ajax_nonce
			},
			success: function(response) {
				console.log(response);
				if ( response.success ) {
					alert('Success! ' + response.message);
					jQuery('#woocommerce_myob_integrations_product_sku_sync_input').val('');
					location.reload();
				} else {
					alert('Error: ' + response.message);
					$this.text('Sync Product');
					$this.prop('disabled', false);
				}
			},
			error: function(xhr, status, error) {
				alert('AJAX Error: ' + error);
				$this.text('Sync Product');
				$this.prop('disabled', false);
				console.log(xhr.responseText);
			}
		});

		return false;
	});

	jQuery("#woocommerce_myob_integrations_product_sku_view_button").click( function() {

		var $this = jQuery(this);
		var product_sku = jQuery('#woocommerce_myob_integrations_product_sku_view_input').val();

		if (!product_sku || product_sku.trim() === '') {
			alert('Please enter a product SKU.');
			return false;
		}

		$this.text('Loading...');
		$this.prop('disabled', true);

		jQuery.ajax({
			type : "post",
			dataType : "json",
			url : OpmcMyobScriptAjax.ajaxurl,
			data : {
				action: "MYOB_view_product_by_sku_ajax",
				product_sku: product_sku,
				security: OpmcMyobScriptAjax.ajax_nonce
			},
			success: function(response) {
				if (response.success) {
					opmcOpenProductPopup(response.product, response.tiered_pricing || [], response.raw_item || {});
				} else {
					alert('Error: ' + response.message);
				}
				$this.text('View Product');
				$this.prop('disabled', false);
			},
			error: function(xhr, status, error) {
				alert('AJAX Error: ' + error);
				$this.text('View Product');
				$this.prop('disabled', false);
				console.log(xhr.responseText);
			}
		});

		return false;
	});
	
	jQuery("#woocommerce_myob_integrations_order_number_sync_button").click(
	    function() {
	        var $this = jQuery(this);
	        var order_number = jQuery('#woocommerce_myob_integrations_order_number_sync_input').val();
	        
	        if (!order_number || order_number.trim() === ''){
	            alert('Please enter order number.');
	            return false;
	        }
	        		$this.text('Syncing...');
		$this.prop('disabled', true);
		
				jQuery.ajax({
			type : "post",
			dataType : "json",
			url : OpmcMyobScriptAjax.ajaxurl,
			data : { 
				action: "MYOB_sync_order_by_number_ajax",
				order_number: order_number,
				security: OpmcMyobScriptAjax.ajax_nonce
			},
			success: function(response) {
				console.log(response);
				if ( response.success ) {
					alert('Success! ' + response.message);
					jQuery('#woocommerce_myob_integrations_order_number_sync_input').val('');
					location.reload();
				} else {
					alert('Error: ' + response.message);
					$this.text('Sync Order');
					$this.prop('disabled', false);
				}
			},
			error: function(xhr, status, error) {
				alert('AJAX Error: ' + error);
				$this.text('Sync Order');
				$this.prop('disabled', false);
				console.log(xhr.responseText);
			}
		});

		return false;
    
    });

	jQuery("#opmc_myob_order_tools_sync_now").click(function() {
		var $this = jQuery(this);
		var orderNumber = jQuery('#opmc_myob_order_tools_order_number').val();
		var $message = jQuery('#opmc_myob_order_tools_message');
		var $result = jQuery('#opmc_myob_order_tools_result');

		if (!orderNumber || orderNumber.trim() === '') {
			alert('Please enter order number.');
			return false;
		}

		$message.hide().empty();
		$result.hide().empty();
		$this.text('Syncing...');
		$this.prop('disabled', true);

		jQuery.ajax({
			type: "post",
			dataType: "json",
			url: OpmcMyobScriptAjax.ajaxurl,
			data: {
				action: "MYOB_sync_order_by_number_ajax",
				order_number: orderNumber,
				security: OpmcMyobScriptAjax.ajax_nonce
			},
			success: function(response) {
				if (response.success) {
					$message.html('<div class="notice notice-success inline"><p>' + response.message + '</p></div>').show();
				} else {
					$message.html('<div class="notice notice-error inline"><p>' + response.message + '</p></div>').show();
				}
				$this.text('Sync Now');
				$this.prop('disabled', false);
			},
			error: function(xhr, status, error) {
				$message.html('<div class="notice notice-error inline"><p>AJAX Error: ' + error + '</p></div>').show();
				$this.text('Sync Now');
				$this.prop('disabled', false);
				console.log(xhr.responseText);
			}
		});

		return false;
	});

	jQuery("#opmc_myob_order_tools_view_now").click(function() {
		var $this = jQuery(this);
		var orderNumber = jQuery('#opmc_myob_order_tools_order_number').val();
		var $message = jQuery('#opmc_myob_order_tools_message');
		var $result = jQuery('#opmc_myob_order_tools_result');

		if (!orderNumber || orderNumber.trim() === '') {
			alert('Please enter order number.');
			return false;
		}

		$message.hide().empty();
		$result.hide().empty();
		$this.text('Loading...');
		$this.prop('disabled', true);

		jQuery.ajax({
			type: "post",
			dataType: "json",
			url: OpmcMyobScriptAjax.ajaxurl,
			data: {
				action: "MYOB_view_order_by_number_ajax",
				order_number: orderNumber,
				security: OpmcMyobScriptAjax.ajax_nonce
			},
			success: function(response) {
				if (response.success && response.order) {
					$message.html('<div class="notice notice-success inline"><p>' + response.message + '</p></div>').show();
					opmcOpenOrderPopup(response.order, response.raw_item || {});
				} else {
					$message.html('<div class="notice notice-error inline"><p>' + response.message + '</p></div>').show();
				}
				$this.text('View Order in MYOB');
				$this.prop('disabled', false);
			},
			error: function(xhr, status, error) {
				$message.html('<div class="notice notice-error inline"><p>AJAX Error: ' + error + '</p></div>').show();
				$this.text('View Order in MYOB');
				$this.prop('disabled', false);
				console.log(xhr.responseText);
			}
		});

		return false;
	});

	jQuery("#woocommerce_myob_integrations_reload_accounts_list").click( function() {
		var $this = jQuery(this);
		var company_file_username= jQuery('#woocommerce_myob_integrations_WC_MYOB_company_file_username').val();
		var company_file_pulldown = jQuery('#woocommerce_myob_integrations_WC_MYOB_company_file_id option:selected').val();
		var company_file_passwrd = jQuery('#woocommerce_myob_integrations_WC_MYOB_company_file_password').val();
		$this.text('Loading... (20 seconds)');
		$this.prop('disabled', true);

		jQuery.ajax({
			type : "post",
			dataType : "json",
			url : OpmcMyobScriptAjax.ajaxurl,
			data : { company_file_pulldown:company_file_pulldown, company_file_username:company_file_username, company_file_passwrd:company_file_passwrd, action: "MYOB_reload_accounts_list_ajax", security: OpmcMyobScriptAjax.ajax_nonce },
			success: function(response) {
				//alert(response);
				console.log(response);
				location.reload();
			}
		});
	});


	jQuery('#woocommerce_myob_integrations_export_product_to_myob').click( function() {
		var $this = jQuery(this);

		$this.text('Loading...');
		$this.prop('disabled', true);

		jQuery.ajax({
			type : "post",
			dataType : "json",
			url : ajaxurl,
			data : { action: "MYOB_import_product_to_myob" },
			success: function(response) {
				$this.prop('disabled', false);
				console.log(response);
				location.reload();
			}
		});
		console.log($this);
	}); 



	jQuery("#woocommerce_myob_integrations_WC_MYOB_company_file_id").change( function() {
		alert('If you change company file, you may need to reload the accounts list.  To do this, click the Save button, then when the page reloads, click the Reload Accounts List button.');
		//jQuery('#woocommerce_myob_integrations_reload_accounts_list').prop('disabled',true);
	});

	jQuery("#woocommerce_myob_integrations_WC_MYOB_invoice_id_prefix").prop('required', true);
	jQuery("#woocommerce_myob_integrations_WC_MYOB_company_file_username").prop('required', true);

	var isSearchByCompany = jQuery('#woocommerce_myob_integrations_WC_MYOB_search_by_company').prop('checked');
	var isSearchByCustomerName = jQuery('#woocommerce_myob_integrations_WC_MYOB_search_by_customer_name').prop('checked');

	if(isSearchByCompany||isSearchByCustomerName){
		var isEnabled = jQuery(this).is(':checked');
		if(jQuery('#woocommerce_myob_integrations_WC_OPMC_set_default_customer_designation').is(':checked') && isEnabled){
			jQuery('#woocommerce_myob_integrations_WC_OPMC_set_default_customer_designation').prop('checked', false);
			jQuery('#woocommerce_myob_integrations_WC_OPMC_set_default_customer_designation').prop('disabled', true);
		} else {
			jQuery('#woocommerce_myob_integrations_WC_OPMC_set_default_customer_designation').prop('disabled', true);
			jQuery('#woocommerce_myob_integrations_WC_MYOB_search_by_email').prop('disabled', true);
		}
	}

	jQuery('#woocommerce_myob_integrations_WC_MYOB_search_by_company,#woocommerce_myob_integrations_WC_MYOB_search_by_customer_name').change(function(){
		var isEnabled = jQuery(this).is(':checked');
		if(isEnabled){
			if(jQuery('#woocommerce_myob_integrations_WC_OPMC_set_default_customer_designation').is(':checked') ){
				alert('If you enable this, Set MYOB Default Customer Designation will be disabled.');
				jQuery('#woocommerce_myob_integrations_WC_OPMC_set_default_customer_designation').prop('checked', false);
				jQuery('#woocommerce_myob_integrations_WC_OPMC_set_default_customer_designation').prop('disabled', true);
				jQuery('#woocommerce_myob_integrations_WC_MYOB_search_by_email').prop('checked', false);
				jQuery('#woocommerce_myob_integrations_WC_MYOB_search_by_email').prop('disabled', true);
			} else {
				jQuery('#woocommerce_myob_integrations_WC_OPMC_set_default_customer_designation').prop('disabled', true);
				jQuery('#woocommerce_myob_integrations_WC_MYOB_search_by_email').prop('checked', false).prop('disabled', true);
			}
		} else {
			if (!jQuery('#woocommerce_myob_integrations_WC_MYOB_search_by_company').prop('checked') && !jQuery('#woocommerce_myob_integrations_WC_MYOB_search_by_customer_name').prop('checked')) 
			{
   				 jQuery('#woocommerce_myob_integrations_WC_OPMC_set_default_customer_designation').prop('disabled', false);
				 jQuery('#woocommerce_myob_integrations_WC_MYOB_search_by_email').prop('disabled', false);
			}

		}
	});
});

var params = new window.URLSearchParams(window.location.search);
var current_page = params.get('section');
if ('myob_integrations' == current_page) {
	jQuery(window).off('beforeunload');
	jQuery(document).ready(function(){

		jQuery('#view_log').click(function(e){
		   e.preventDefault();
			var selected = jQuery('#logs :selected').val();
			var selected2 = jQuery('#logs :selected').text();
		   	console.log('selected : ' + selected);
		   	console.log('selected2 : ' + selected2);
			jQuery.ajax({
					data: {action: 'opmc_myob_view_debug_logs', selected:selected,  selected2:selected2, security:OpmcMyobScriptAjax.ajax_nonce},
					type: 'post',
					url: OpmcMyobScriptAjax.ajaxurl,
					success: function(data) {
						var obj = jQuery.parseJSON(data);
						if(obj.result == 'success'){
							console.log(obj);
							sessionStorage.setItem("logviewbtnActive", "logview");
							location.reload();
						} else {
							alert('failed');
						}
				}
			});
		});

		var tabcontent = jQuery('#conn_tab_button').text();
		var tabcontent1 = jQuery('#conf_tab_button').text();
		if ('' != tabcontent && '' != tabcontent1) {
		jQuery("button.button-primary.woocommerce-save-button").css( "display", "block" );
		jQuery(".form-table").find("th:gt(3)").show();
		jQuery("p.submit").css({ 'border': '1px solid #ccc', 'border-top': 'none', 'padding': '1% 0% 2% 2%',  'margin':'auto'  });
		jQuery(".tabcontent").css('border-bottom','none');
		} else{
			jQuery("button.button-primary.woocommerce-save-button").css( "display", "none" );
			jQuery("p.submit").css({ 'border': '', 'border-top': '', 'padding': '',  'margin':''  });
			jQuery(".tabcontent").css('border-bottom', '');
			

			jQuery(".form-table").find("th:gt(3)").hide();
			var reval = jQuery('#woocommerce_myob_integrations_allow_access').text();
			if ('Revalidate Access' != reval) {
		    		jQuery('#woocommerce_myob_integrations_WC_MYOB_company_file_username').attr('disabled', true);
		    		jQuery('#woocommerce_myob_integrations_WC_MYOB_company_file_password').attr('disabled', true);
		    		jQuery('#woocommerce_myob_integrations_WC_MYOB_company_file_id').attr('disabled', true);
		    	} else {

		    		jQuery('#woocommerce_myob_integrations_WC_MYOB_company_file_username').removeAttr('disabled', true);
		    		jQuery('#woocommerce_myob_integrations_WC_MYOB_company_file_password').removeAttr('disabled', true);
		    		jQuery('#woocommerce_myob_integrations_WC_MYOB_company_file_id').removeAttr('disabled', true);
		    	}	
		}

	    jQuery('#conn_tab_button').click( function() {
		    var tabcontent = jQuery('#conn_tab_button').text();
		    if (tabcontent == 'Connection Settings') {
		    	jQuery("button.button-primary.woocommerce-save-button").css( "display", "none" );
		    	jQuery(".form-table").find("th:gt(3)").hide();
		    	jQuery("p.submit").css({ 'border': '', 'border-top': '', 'padding': '',  'margin':''  });
		    	jQuery(".tabcontent").css('border-bottom','');
		    	var reval = jQuery('#woocommerce_myob_integrations_allow_access').text();
		    	if ('Revalidate Access' != reval) {
		    		jQuery('#woocommerce_myob_integrations_WC_MYOB_company_file_username').attr('disabled', true);
		    		jQuery('#woocommerce_myob_integrations_WC_MYOB_company_file_password').attr('disabled', true);
		    		jQuery('#woocommerce_myob_integrations_WC_MYOB_company_file_id').attr('disabled', true);
		    	} else {

		    		jQuery('#woocommerce_myob_integrations_WC_MYOB_company_file_username').removeAttr('disabled', true);
		    		jQuery('#woocommerce_myob_integrations_WC_MYOB_company_file_password').removeAttr('disabled', true);
		    		jQuery('#woocommerce_myob_integrations_WC_MYOB_company_file_id').removeAttr('disabled', true);
		    	}	
		    } else {
		    	jQuery("p.submit").css({ 'border': '1px solid #ccc', 'border-top': 'none', 'padding':'1% 0% 2% 2%', 'margin':' auto'  });
		    	jQuery(".tabcontent").css('border-bottom','none');
		    }
		});	

		 jQuery('#conf_tab_button').click( function() {
		    var tabcontent = jQuery('#conf_tab_button').text();
		    if (tabcontent != 'Connection Settings') {
		    	jQuery("button.button-primary.woocommerce-save-button").css( "display", "block" );
		    	jQuery(".form-table").find("th:gt(3)").show();
		    	jQuery("p.submit").css({ 'border': '1px solid #ccc', 'border-top': 'none', 'padding':'1% 0% 2% 2%', 'margin':' auto'  });
		    	jQuery(".tabcontent").css('border-bottom','none');
		    	var reval = jQuery('#woocommerce_myob_integrations_allow_access').text();
		    	if ('Revalidate Access' != reval) {
		    		jQuery('#woocommerce_myob_integrations_WC_MYOB_company_file_username').attr('disabled', true);
		    		jQuery('#woocommerce_myob_integrations_WC_MYOB_company_file_password').attr('disabled', true);
		    		jQuery('#woocommerce_myob_integrations_WC_MYOB_company_file_id').attr('disabled', true);
		    	} else {
		    		
		    		jQuery('#woocommerce_myob_integrations_WC_MYOB_company_file_username').removeAttr('disabled', true);
		    		jQuery('#woocommerce_myob_integrations_WC_MYOB_company_file_password').removeAttr('disabled', true);
		    		jQuery('#woocommerce_myob_integrations_WC_MYOB_company_file_id').removeAttr('disabled', true);
		    	}
		    } else {
		    	jQuery("p.submit").css({ 'border': '', 'border-top': '', 'padding': '',  'margin':''  });
		    	jQuery(".tabcontent").css('border-bottom','');
		    }
		});

		var tablinks = jQuery('.tablinks.active');
		if(tablinks.length && tablinks.attr('id') === 'myob_logs'){
			hide_wc_save_button();
		}
		jQuery('#log_tab_button').click( function() {
			var tabcontent = jQuery('#conf_tab_button').text();
			hide_wc_save_button();
		});

		function hide_wc_save_button(){
			jQuery("button.button-primary.woocommerce-save-button").css( "display", "none" );
			jQuery("p.submit").css({ 'border': '', 'border-top': '', 'padding': '',  'margin':''  });
			jQuery(".tabcontent").css('border-bottom','');
		}
	});
}
