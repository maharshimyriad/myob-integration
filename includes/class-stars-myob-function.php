<?php



/**
 *  Stars MYOB AccountRight Connector for WooCommerce.
 *
 * @package   Stars_MYOB_Connector
 */



class WC_Myob_API_Functions {

	/** 
	 * Class Instance
	 * 
	 * @var object Class Instance
	 */

	private static $instance;

	

	/**
	 * Myob Api Endpoints
	 * 
	 * @var Mypb 
	 */

	private $endpoints = array(
			'myob_code'  => 'https://secure.myob.com/oauth2/account/authorize',
			'myob_token' => 'https://secure.myob.com/oauth2/v1/authorize',
	);



	/**
	 * Get the class instance
	 */
	public static function get_instance() {
		if (null === self::$instance) {
			self::$instance = new self();
			return self::$instance;
		} else {
			return self::$instance;
		}
	}


	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {

		$this->wc_myob_client_id = get_option('wc_myob_client_id');

		$this->wc_myob_client_secret = get_option('wc_myob_client_secret');

		$this->myob_code = get_option('wc_myob_code');

		//add_action( 'admin_notices', array( $this, 'wc_myob_auth' ) );
	}

	/**

	 * Authorize myob access .

	 */
	public function wc_myob_auth() {

		$params = array(
			'client_id'             =>  $this->wc_myob_client_id,
			'redirect_uri'          =>  $this->wc_myob_redirect_uri(),
			'response_type'         =>  'code',
		);

		$params = http_build_query($params);

		$url =  $this->endpoints['myob_code'] . '?' . $params;

		echo '<div class="error wc_myob_authorize"><p>click here to <a href="' . esc_url($url) . '" class="wc_myob_authorize_code">authorize myob access</a></p> <strong>Set redirect_uri ' . esc_html($this->wc_myob_redirect_uri()) . '</strong></div>';
	} 
	
	/**
	 * Myob redirect uri.
	*/
	
	public function wc_myob_redirect_uri() {
		return WC_MYOB_INTEGRATION_PLUGINURL . 'stars-myob-cronjob.php';
	}
}

new WC_Myob_API_Functions();


