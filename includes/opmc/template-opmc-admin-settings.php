<?php 

$enable_config_tab = false;
$active_tab_id = 'conn_tab_button';
$woo_settings = get_option('woocommerce_MYOB_integrations_settings');
$company_file_id = isset($woo_settings['WC_MYOB_company_file_id']) ? $woo_settings['WC_MYOB_company_file_id'] : '';
$company_file_username = isset($woo_settings['WC_MYOB_company_file_username']) ? $woo_settings['WC_MYOB_company_file_username'] : ''; 

$unauthorized_count = get_option( 'WC_MYOB_api_unauthorized_count' );
$refresh_token_failed = get_option( 'WC_MYOB_refresh_token_failed' );

if (!empty($company_file_username) && !empty($company_file_id) && false != $company_file_id ) {
	if ('yes' != $refresh_token_failed && !( $unauthorized_count > 0 )) {
		$enable_config_tab = true;
		$active_tab_id = 'conf_tab_button';
	}
}

$fields = wp_kses_allowed_html( 'post' );

		$fields['form'] = array(
'action'    => true,
					'accept'            => true,
					'accept-charset' => true,
					'enctype'        => true,
					'method'         => true,
					'name'           => true,
					'target'         => true,
);
		$fields['script'] = array(
		  'src' => true,
		  'height' => true,
		  'width' => true,
		);

		$fields['input'] = array(
		'class' => array(),
		'id'    => array(),
		'name'  => array(),
		'value' => array(),
		'type'  => array(),
		'onclick' => array(),
		'style' => array(),
		'checked' => array(),
		'script' => array( 'type' => array() ),

		);

		$fields['button'] = array(
		'class' => array(),
		'id'    => array(),
		'name'  => array(),
		'value' => array(),
		'type'  => array(),
		'onclick' => array(),
		'onfocus' => array(),
		'onblur' => array(),
		//'script' => array('type' => array()),

		);
		$fields['select'] = array(
		'class'  => array(),
		'id'     => array(),
		'name'   => array(),
		'value'  => array(),
		'type'   => array(),
		'style' => array(),
		'onclick' => array(),
		'script' => array( 'type' => array() ),
		);
		
		$fields['option'] = array(
			'selected' => array(),
			'class'  => array(),
			'id'     => array(),
			'name'   => array(),
			'value'  => array(),
			'type'   => array(),
			'style' => array(),
			'onclick' => array(),
			'script' => array( 'type' => array() ),
			
		);
		$fields['iframe'] = array(
			'align'       => true,
			'frameborder' => true,
			'height'      => true,
			'width'       => true,
			'sandbox'     => true,
			'seamless'    => true,
			'scrolling'   => true,
			'srcdoc'      => true,
			'src'         => true,
			'class'       => true,
			'id'          => true,
			'style'       => true,
			'border'      => true,
);

		?>
<div class="tab">
	<button type="button" class="tablinks" onclick="openCity(event, 'myob_connection_settings')" id="conn_tab_button">Connection Settings</button>

	<?php if ($enable_config_tab) : ?>

	<button type="button" class="tablinks" onclick="openCity(event, 'myob_configuration')" id="conf_tab_button">Configuration</button>
	<button type="button" class="tablinks" onclick="openCity(event, 'myob_logs')" id="log_tab_button">Logs</button>

	<?php endif ?>

</div>

<div id="myob_connection_settings" class="tabcontent">
	<div class="status_of_conn_d"><h3>Connection Settings</h3><p class="status_of_conn"></p> </div>

	<table class="form-table">

		<?php
		foreach ( $first_tab_fields as $sk => $sv ) {
			$input_type = $this->get_field_type( $sv );
			if ( method_exists( $this, 'generate_' . $input_type . '_html' ) ) {
				$html = $this->{'generate_' . $input_type . '_html'}( $sk, $sv );
			} else {
				$html = $this->generate_text_html( $sk, $sv );
			}
			echo wp_kses ($html, $fields);
		}
		?>
	
	</table>
</div>
<?php if ($enable_config_tab) : ?>
<div id="myob_configuration" class="tabcontent">
	<h3>Configuration</h3>
	<table class="form-table">
		<?php

		foreach ( $second_tab_fields as $sk => $sv ) {

			$config_tab_input_type = $this->get_field_type( $sv );

			if ( method_exists( $this, 'generate_' . $config_tab_input_type . '_html' ) ) {
				$config_tab_html = $this->{'generate_' . $config_tab_input_type . '_html'}( $sk, $sv );
			} else {
				$config_tab_html = $this->generate_text_html( $sk, $sv );
			}
			
			echo wp_kses($config_tab_html, $fields);
		}
		?>
			
	</table>
</div>

<div id="myob_logs" class="tabcontent">
	<?php
		$myobs = array();
		$result = WC_Log_Handler_File::get_log_files();
	foreach ( $result as $value ) {
		$val = explode( '-', $value );
		if ( 'MYOB' == $val[0] || 'myob' == $val[0] ) {
			$myobs[] = array( $val[2] . '-' . $val[3] . '-' . $val[4], $value );
		}
	}
	if ($myobs) :
		?>
	<div class="header-section">
		<h3 class="header-title">Logs</h3>
		<div class="log-selection">
		<?php
			$selected_log_file_name = get_option( 'selected_opmc_myob_log_view_date', '' );
			$selected_log_view_text = get_option( 'selected_opmc_myob_log_view_text', gmdate('Y-m-d') );
		?>
			<button type="submit" class="button" id="view_log" style="float: right;"> view</button>
			<select class="select2-selection select2-selection--single" name="logs" id="logs" style="float: right; margin: 0px 4px 0 0;">
			<?php
			foreach ( $myobs as $value ) {
				if ( ! empty( $selected_log_view_text == $value[0] ) ) :
					$selected_log_file_name = $value[1];
					?>
						<option value="<?php echo esc_attr( $selected_log_file_name ); ?>" selected> <?php echo esc_html( $selected_log_view_text ); ?></option>
					<?php else : ?>
							<option value="<?php echo esc_attr( $value[1] ); ?>"> <?php echo esc_html( $value[0] ); ?></option>
					<?php endif; ?>
				<?php } ?>
			</select>
		</div>
	</div>
	<div>
		<?php
		$logUrl = WC_LOG_DIR . $selected_log_file_name;

		if (file_exists($logUrl)) :
			$logs_file = file_get_contents($logUrl);
				
			if (false !== $logs_file) {
				$logs_data = explode(PHP_EOL, $logs_file);
				$logs_array = array_reverse($logs_data);
				$log_entries = array_filter($logs_array, function ( $value ) {
					return !empty(trim($value));
				});
				$log_entries = array_values($log_entries);
			}
			?>
				<table class="wp-list-table widefat fixed striped table-view-list">
					<thead>
						<tr>
							<th class="column-date">Date</th>
							<th class="column-tags">Process</th>
							<th class="column-tags">Status</th>
							<th>Message</th>
						</tr>
					</thead>
					<tbody>
						<?php
						if (empty($log_entries)) :
							?>
							<tr>
								<td>There are currently no logs to view.</td>
							</tr>
							<?php
						else :
							foreach ( $log_entries as $log_entry ) :
								$log_parts = preg_split('/ - | NOTICE /', $log_entry, 2);

								if (strpos($log_parts[0], '+00:00') !== false) {
									$date = explode( '+00:00', $log_parts[0] );
									$log_date = isset($log_parts[0]) ? rtrim(str_replace('T', ' at ', $date[0] )) : '';
								} else {
									$log_date = isset($log_parts[0]) ? rtrim(str_replace('@', 'at', $log_parts[0]), ' - ') : '';
								}
								$log_message_array = isset($log_parts[1]) ? explodeLogMessage($log_parts[1]) : '';
								
								if (is_array($log_message_array)) {
									$log_name = $log_message_array[0];
									$log_state = $log_message_array[1];
									$log_message = $log_message_array[2];
								} else {
									continue;
								}
								?>
							  <tr>
								  <td><?php echo esc_html( $log_date ); ?></td>
								  <td><?php echo esc_html( $log_name ); ?></td>
								  <td><?php echo esc_html( $log_state ); ?></td>
								  <td><?php echo esc_html( $log_message ); ?></td>
							  </tr>
							<?php
							endforeach;
						endif;
						?>
					</tbody>
				</table>
		<?php
			endif;
		?>
	</div>
	<?php else : ?>
		<p>No Logs to preview</p>
	<?php endif; ?>
</div>

<?php endif ?>

<script>
	sessionState = sessionStorage.getItem("logviewbtnActive");
	console.log(sessionState);
	if( sessionState === "logview" ) {
		document.getElementById("log_tab_button").click();
	} else {
		document.getElementById("<?php echo esc_html($active_tab_id); ?>").click();
		sessionStorage.removeItem('logviewbtnActive');
	}

	jQuery('#conn_tab_button, #conf_tab_button').click(function(){
		sessionStorage.removeItem('logviewbtnActive');
	});


	function openCity(evt, cityName) {
		var i, tabcontent, tablinks;
		tabcontent = document.getElementsByClassName("tabcontent");
		for (i = 0; i < tabcontent.length; i++) {
		  tabcontent[i].style.display = "none";
		}
		tablinks = document.getElementsByClassName("tablinks");
		for (i = 0; i < tablinks.length; i++) {
		  tablinks[i].className = tablinks[i].className.replace(" active", "");
		}
		document.getElementById(cityName).style.display = "block";
		evt.currentTarget.className += " active";
	}
</script>
