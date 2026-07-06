<?php
	$wp_path = preg_replace('/wp-content(?!.*wp-content).*/', '', __DIR__);

	include $wp_path . 'wp-load.php';

	require_once 'includes/opmc/class-stars-logger.php';
	require_once 'includes/class-stars-myob-connector.php';
	$conn = new Opmc_Myob_Connector();
	$conn->create_wc_log('Callback from MYOB');

	// ── Resolve credentials ────────────────────────────────────────────────
	// Direct mode: MYOB calls this file directly as the registered redirect_uri.
	//   $_REQUEST will only contain `code` (and optionally `businessId`).
	//   We read the API key/secret from the plugin constants / WP options.
	//
	// Relay (intermediary) mode: the relay page forwards everything here,
	//   including api_client_id, api_secret and api_redirect_uri as params.
	//   We use those forwarded values so the relay flow keeps working unchanged.

	$code = isset( $_REQUEST['code'] ) ? sanitize_textarea_field( $_REQUEST['code'] ) : '';

	if ( ! empty( $_REQUEST['api_client_id'] ) ) {
		// Relay mode — credentials supplied by the intermediary page.
		$api_client_id    = sanitize_textarea_field( $_REQUEST['api_client_id'] );
		$api_secret       = sanitize_textarea_field( $_REQUEST['api_secret'] );
		$api_redirect_uri = sanitize_textarea_field( $_REQUEST['api_redirect_uri'] );
	} else {
		// Direct mode — pull credentials from plugin constants / options.
		$api_client_id    = defined( 'WC_MYOB_API_CLIENT_ID' ) ? WC_MYOB_API_CLIENT_ID : get_option( 'WC_MYOB_client_id' );
		$api_secret       = get_option( 'WC_MYOB_secret' );
		$api_redirect_uri = defined( 'WC_MYOB_API_REDIRECT_URL' ) ? WC_MYOB_API_REDIRECT_URL : plugin_dir_url( __FILE__ ) . 'stars-myob-cronjob.php';
	}

	$api_client_id_static = defined( 'WC_MYOB_API_CLIENT_ID' ) ? WC_MYOB_API_CLIENT_ID : '545dj2wk4r8gde2xs39mg366';

if ( ! empty( $code ) && ! empty( $api_client_id ) && ! empty( $api_secret ) && ! empty( $api_redirect_uri ) ) {

	?>
<!DOCTYPE html>
<html lang="en">
<head>
	<title>Stars MYOB Authentication</title>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php
	function myob_validation_scripts() {
		wp_enqueue_style( 'bootstrapcdn452', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css', array(), '4.5.2');
		wp_enqueue_script( 'ajaxgoogleapis351', 'https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js', array( 'jquery' ), '3.5.1', true );
		wp_enqueue_script( 'cdnjscloudflare1160', 'https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js', array( 'jquery' ), '1.16.0', true );
		wp_enqueue_script( 'bootstrapcdn452', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js', array( 'jquery' ), '4.5.2', true );
	}
	add_action( 'wp_enqueue_scripts', 'myob_validation_scripts' );
	wp_head();
	?>
</head>
<body>
<div class="container" style="margin-top:50px">
	<div class="row">
		<div class="col">
			<?php echo '<img src="' . esc_url( plugins_url( 'assets/images/myob_logo.png', __FILE__ ) ) . '" class="img-fluid mx-auto d-block" alt="Stars"> '; ?>
		</div>
		<div class="col">
			<?php echo '<img src="' . esc_url( plugins_url( 'assets/images/myob_logo.png', __FILE__ ) ) . '" class="img-fluid mx-auto d-block" alt="MYOB"> '; ?>
		</div>
	</div>
	<?php

	if ( $api_client_id != $api_client_id_static ) {
		?>
		<div class="alert alert-danger text-center" style="margin-top:30px">
			<div class="d-inline-block"><strong>Error E01:</strong> ID Wrong!</div>
		</div>
		<div class="row" style="margin-top:30px">
		<div class="col">
			<a href="<?php echo esc_url(@get_admin_url(null, 'admin.php?page=wc-settings&tab=integration&section=myob_integrations')); ?>" class="btn btn-primary btn-lg mx-auto d-block" role="button">Return to plugin Page</a>
		</div>
		</div>
		</div>
		</body>
		</html>
		<?php
		exit;
	}

	update_option( 'WC_MYOB_code', $code );
	update_option( 'WC_MYOB_client_id', $api_client_id );
	update_option( 'WC_MYOB_secret', $api_secret );
	update_option( 'WC_MYOB_api_unauthorized_count', 0 );
	   
	if ( get_option( 'WC_MYOB_code' ) ) {           
		// build up the params for generating token 
		$params = array(
			'client_id'             =>  $api_client_id,
			'client_secret'         =>  $api_secret,
			'grant_type'            =>  'authorization_code',
			'code'                  =>  urldecode($code),
			'redirect_uri'          =>  $api_redirect_uri,
			'scope'                 => 'CompanyFile',
		); 

		$query = 'client_id=' . $params['client_id'] . '&redirect_uri=' . urlencode( $params['redirect_uri'] ) .
				'&client_secret=' . $params['client_secret'] .
				'&grant_type=authorization_code' .
				'&code=' . urlencode($params['code']) .
				'&scope=CompanyFile';


		$params = http_build_query( $params );

		$conn->create_wc_log( print_r($query, 1) ); 
		$conn->create_wc_log( 'Getting token' );    
				
		// generate token post through api
		$response  = wp_remote_post( 'https://secure.myob.com/oauth2/v1/authorize', array(
			'body' => $query,                
		)); 
		$conn->create_wc_log('AUTH RESPONSE');
		$conn->create_wc_log(print_r($response, 1));
		

		if ( '200' == $response['response']['code'] ) {         

			$tokenData = json_decode( $response['body'] );      
			$conn->create_wc_log('TOKEN DATA FOLLOWS');
			$conn->create_wc_log(print_r($tokenData, 1));

			// update token related data in option table
			update_option( 'MYOB_access_token', $tokenData->access_token );
			update_option( 'MYOB_access_refresh_token', $tokenData->refresh_token );
			$conn->create_wc_log('REFRESH TOKEN IS');
			$conn->create_wc_log(print_r($tokenData->refresh_token, 1));
			$tok = get_option( 'MYOB_access_refresh_token' );
			$conn->create_wc_log(print_r($tok, 1));


			update_option( 'MYOB_access_token_type', $tokenData->token_type );
			update_option( 'MYOB_access_token_scope', $tokenData->scope );
				
			// TODO use the library
			$res = get_company_file( $tokenData->access_token, $api_client_id );

			$current_options = get_option( 'woocommerce_MYOB_integrations_settings', array() ); 

			if ( '' != $res ) {

				update_option( 'WC_MYOB_company_file_id', $res );

				$desired_options = array( 'WC_MYOB_company_file_id' => $res );
				$merged_options = array_merge( $current_options, $desired_options );
			
				update_option( 'woocommerce_MYOB_integrations_settings', $merged_options );

				update_option( 'WC_MYOB_company_file_list', $conn->get_company_file() );
				update_option( 'WC_MYOB_refresh_token_timestamp', time());
				
			} 
			?>
			<div class="alert alert-success text-center" style="margin-top:30px">
				<div class="d-inline-block"><strong>Success!</strong> Authentication successful.</div>
			</div>
			<?php
		} else {
			?>
				<div class="alert alert-danger text-center" style="margin-top:30px">
					<div class="d-inline-block"><strong>Error E100:</strong> Invalid URL for MYOB authorisation.</div>
				</div>
				<?php
		}           
	} 
} else {
	?>
		<div class="alert alert-danger text-center" style="margin-top:30px">
			<div class="d-inline-block"><strong>Error E100:</strong> Invalid URL for MYOB authorisation.</div>
		</div>
		<?php
}

function get_company_file( $access_token, $api_client_id ) {
	$conn = new Opmc_Myob_Connector();
	$conn->create_wc_log('Getting company file');

	$headers = array(
			'Authorization'           => 'bearer ' . $access_token,
			'x-myobapi-key'           => $api_client_id,   
			'Accept-Encoding'         => 'gzip,deflate',
			'x-myobapi-version'       => 'v2',
			'scope'                   => 'CompanyFile',
			'x-myobapi-cftoken'       => base64_encode('Administrator:'),
		);
		
	$conn->create_wc_log(print_r($headers, 1));
	$res = wp_remote_get( 'https://ar1.api.myob.com/accountright/' , array(
		'headers' =>  $headers,        
	)); 
	$res_body =  json_decode($res['body']);
	$conn->create_wc_log('==== Company File results ====');
	$conn->create_wc_log(print_r($res_body, 1));
		$fileNames = array();
		$fileUriID = array();
	foreach ($res_body as $filesId) {
		if (property_exists($filesId, 'Name') && property_exists($filesId, 'Id')) {
				$fileNames[] = $filesId->Name; 
				$fileUriID[] = $filesId->Id;
		}
	}
	return $fileUriID[0];
}
?>
	<div class="row" style="margin-top:30px">
		<div class="col">
			<a href="<?php echo esc_url(@get_admin_url(null, 'admin.php?page=wc-settings&tab=integration&section=myob_integrations')); ?>" class="btn btn-primary btn-lg mx-auto d-block" role="button">Return to plugin Page</a>
		</div>
	</div>
</div>
</body>
</html>
