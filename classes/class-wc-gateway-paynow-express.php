<?php
/**
 * Paynow Express Payment Gateway
 *
 * Provides a Paynow Payment Gateway.
 * @todo - Improve the naming of variables where possible
 * @class 		WC_Gateway_PaynowExpress
 * @package		WooCommerce
 * @category	Payment Gateways
 * @author		Webdev
 *
 */
 
class WC_Gateway_PaynowExpress extends WC_Payment_Gateway {

	public $version = WC_PAYNOW_EXPRESS_VERSION;

	protected $callback;
	protected $initiate_transaction_url;
	protected $merchant_id;
	protected $merchant_key;
	protected $merchant_id_usd;
	protected $merchant_key_usd;
	public function __construct() {
        global $woocommerce;
        $this->id			= 'paynowexpress';
        $this->method_title = __( 'Paynow Express', 'woothemes' );
        $this->method_description = 'Have your customers pay using Zimbabwean mobiles payment methods.';
        $this->icon 		= $this->plugin_url() . '/assets/images/icon.png';
		$this->has_fields 	= true;

		// this is the name of the class. Mainly used in the callback to trigger wc-api handler in this class
		$this->callback		=  strtolower( get_class($this) );

		// Setup available countries.
		$this->available_countries = array( 'ZW' );

		// Setup available currency codes.
		$this->available_currencies = array( 'USD' ); // nostro / rtgs ?

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Setup default merchant data.
		$this->merchant_id = $this->settings['merchant_id'];
		$this->merchant_key = $this->settings['merchant_key'];
		$this->initiate_transaction_url = $this->settings['paynow_initiate_transaction_url'];


		$this->title = $this->settings['title'];

		// this is the url paynow will send it's response to
		$this->response_url	= add_query_arg( 'wc-api', $this->callback , home_url( '/' ) );
		
		// register a handler for wc-api calls to this payment method
		add_action( 'woocommerce_api_' . $this->callback , array( &$this, 'paynow_checkout_return_handler' ) );
		
		/* 1.6.6 */
		add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );

		/* 2.0.0 */
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		
		add_action( 'woocommerce_receipt_paynowexpress', array( $this, 'receipt_page' ) );

		// Check if the base currency supports this gateway.
		if ( ! $this->is_valid_for_use() )
			$this->enabled = false;
	}

	/**
     * Initialise Gateway Settings Form Fields
     *
     * @since 1.0.0
     */
    function init_form_fields () {

    	$this->form_fields = array(
			'enabled' => array(
				'title' => __( 'Enable/Disable', 'woothemes' ),
				'label' => __( 'Enable Paynow Express', 'woothemes' ),
				'type' => 'checkbox',
				'description' => __( 'This controls whether or not this gateway is enabled within WooCommerce.', 'woothemes' ),
				'default' => 'yes'
			),
			'title' => array(
				'title' => __( 'Title', 'woothemes' ),
				'type' => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woothemes' ),
				'default' => __( 'Mobile Money', 'woothemes' )
			),
			'description' => array(
				'title' => __( 'Description', 'woothemes' ),
				'type' => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woothemes' ),
				'default' => ''
			),
			'merchant_id' => array(
				'title' => __( 'Merchant ID', 'woothemes' ),
				'type' => 'text',
				'description' => __( 'This is the merchant ID, received from Paynow.', 'woothemes' ),
				'default' => ''
			),
			'merchant_key' => array(
				'title' => __( 'Merchant Key', 'woothemes' ),
				'type' => 'text',
				'description' => __( 'This is the merchant key, received from Paynow.', 'woothemes' ),
				'default' => ''
			),
			'paynow_initiate_transaction_url' => array(
				'title' => __( 'Paynow Initiate Transaction URL', 'woothemes' ),
				'type' => 'text',
				'label' => __( 'Paynow Initiate Transaction URL.', 'woothemes' ),
				'default' => 'https://www.paynow.co.zw/interface/remotetransaction'
			)
		);

    } // End init_form_fields()

    /**
	 * Get the plugin URL
	 *
	 * @since 1.0.0
	 */
	function plugin_url() {
		if( isset( $this->plugin_url ) )
			return $this->plugin_url;

		if ( is_ssl() ) {
			return $this->plugin_url = str_replace( 'http://', 'https://', WP_PLUGIN_URL ) . "/" . plugin_basename( dirname( dirname( __FILE__ ) ) );
		} else {
			return $this->plugin_url = WP_PLUGIN_URL . "/" . plugin_basename( dirname( dirname( __FILE__ ) ) );
		}
	} // End plugin_url()

    /**
     * is_valid_for_use()
     *
     * Check if this gateway is enabled and available in the base currency being traded with.
     *
     * @since 1.0.0
     */
	function is_valid_for_use() {
		global $woocommerce;

		$is_available = false;

        $user_currency = get_option( 'woocommerce_currency' );

        $is_available_currency = in_array( $user_currency, $this->available_currencies );

		if ( $is_available_currency && $this->enabled == 'yes' && $this->settings['merchant_id'] != '' && $this->settings['merchant_key'] != '' && $this->settings['paynow_initiate_transaction_url'] != '' )
			$is_available = true;

        return $is_available;
	} // End is_valid_for_use()

	/**
	 * Admin Panel Options
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {
		$this->log( '' );
		$this->log( '', true );

    	?>

    	<h3><?php _e( 'Paynow Express', 'woothemes' ); ?></h3>
    	<p><?php printf( __( 'Paynow Express works by prompting the user on their mobile make a payment.', 'woothemes' ), '<a href="http://developers.paynow.co.zw/">', '</a>' ); ?></p>

    	<?php
				
    	if ( 'USD' == get_option( 'woocommerce_currency' )) {
    		?><table class="form-table"><?php
			// Generate the HTML For the settings form.
    		$this->generate_settings_html();
    		?></table><!--/.form-table--><?php
		} else {
			?>
			<div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'woothemes' ); ?></strong> <?php echo sprintf( __( 'Choose United States Dollar ($/USD) as your store currency in <a href="%s">Pricing Options</a> to enable the Paynow Express Gateway.', 'woocommerce' ), admin_url( '?page=woocommerce&tab=catalog' ) ); ?></p></div>
		<?php
		} // End check currency
		?>
    	<?php
    } // End admin_options()

    /**
	 * There are no payment fields for Paynow, but we want to show the description if set.
	 *
	 * @since 1.0.0
	 */
    function payment_fields() {
    	if ( isset( $this->settings['description'] ) && ( '' != $this->settings['description'] ) ) {
    		echo wpautop( wptexturize( $this->settings['description'] ) );
		}
		// This was to have the phone number field appear next to the payment method. But since it already appears 
		// in the billing details we can reuse that
		// echo '<div class="form-row form-row-wide"><label>Phone Number <span class="required">*</span></label>
		// <input id="express_mobileNo" name="express_mobileNo" type="text" autocomplete="off">
		// </div>';
    } // End payment_fields()

	/**
	 * Process the payment and return the result.
	 * @param int $order_id
	 * @param string $from tells process payment whether the method call is from paynow return (callback) or not
	 * @since 1.0.0
	 */
	function process_payment( $order_id, $from='' ) {

		$order = wc_get_order( $order_id );
		
		if ( $from == "callback" ) {
			$order->payment_complete();
			return array(
				'result' 	=> 'success',
				'redirect'	=> $order->get_checkout_payment_url( true )
			);
		} else {
			// redirects to paynow checkout page if not invoked by paynow response
			return array(
				'result' 	=> 'success',
				'redirect'	=> $order->get_checkout_payment_url( true )
			);
		}
	}

	/**
	 * Reciept page.
	 *
	 * Display text and a button to direct the user to Paynow.
	 *
	 * @since 1.0.0
	 */
	function receipt_page( $order_id ) {
		global $woocommerce;
		
		//get current order
		$order = wc_get_order( $order_id ); // added code in Woo Commerce that needs to be changed
		$checkout_url = $order->get_checkout_payment_url( );
		
		// Check payment
		if ( ! $order_id ) {
			wp_redirect($checkout_url);
			exit;
		} else {
			$api_request_url =  WC()->api_request_url( $this->callback );
			$listener_url = add_query_arg( 'order_id' , $order_id, $api_request_url );
						
			// Get the return url
			$return_url = $this->return_url = $this->get_return_url( $order );

			// Setup Paynow arguments
			$MerchantId =       $this->merchant_id;
			$MerchantKey =    	$this->merchant_key;
			$ConfirmUrl =       $listener_url;
			$ReturnUrl =        $return_url;
			$Reference =        "Order Number: " . $order->get_order_number();
			$Amount =           $order->get_total();
			$AdditionalInfo =   "";
			$Status =           "Message";
			$custEmail = 		$order->billing_email;
			$custPhone = 		$order->billing_phone;

			//set POST variables
			$values = array(
				'resulturl' => $ConfirmUrl,
				'returnurl' => $ReturnUrl,
				'reference' => $Reference,
				'amount' => $Amount,
				'id' => $MerchantId,
				'additionalinfo' => $AdditionalInfo,
				'authemail' => $custEmail, // customer email
				'status' => $Status,
				'method' => 'ecocash',
				'phone' => $custPhone
			);
						
			// Should probably use static methods to have WC_PaynowExpress_Helper::CreateMsg($a, $b);
			$fields_string = (new WC_PaynowExpress_Helper)->CreateMsg($values, $MerchantKey);

			$url = $this->initiate_transaction_url;
			// $url = "https://www.paynow.co.zw/interface/remotetransaction";
			
			// Send API post request
			$response = wp_remote_request($url, [
				'timeout' => 45,
				'method' => 'POST',
				'body' => $fields_string
			]);

			// get the response from paynow
			$result = $response['body'];
			
			if($result)
			{
				$msg = (new WC_PaynowExpress_Helper)->ParseMsg($result);
				
				// first check status, take appropriate action
				if ( strtolower( $msg["status"] ) == strtolower( ps_error ) ){
					wc_add_notice( __( $msg['error'], 'gateway' ), 'error' );
					wp_redirect($checkout_url);
					exit;
				}
				elseif ( strtolower($msg["status"] ) == strtolower( ps_ok ) ){
				
					//second, check hash
					$validateHash = (new WC_PaynowExpress_Helper)->CreateHash($msg, $MerchantKey);
					if ( $validateHash != $msg["hash"] ) {
						$error =  "Paynow reply hashes do not match : " . $validateHash . " - " . $msg["hash"];
					} else {
						
						$theProcessUrl = $msg["browserurl"];

						// Update order data
						$payment_meta['BrowserUrl'] = $msg["browserurl"];
						$payment_meta['PollUrl'] = $msg["pollurl"];
						$payment_meta['PaynowReference'] = $msg["paynowreference"];
						$payment_meta['Amount'] = $msg["amount"];
						$payment_meta['Status'] = "Sent to Paynow";

						// if the post meta does not exist, wp calls add_post_meta
						update_post_meta( $order_id, '_wc_paynowexpress_payment_meta', $payment_meta );
						
					}
				} else {						
					//unknown status
					$error =  "Invalid status in from Paynow, cannot continue";
				}
			}
			else
			{
			   $error = "Empty response from network request";
			}
			
			// Choose where to go
			if(isset($error))
			{	
				wp_redirect($checkout_url);
				exit;
			}
			else
			{ 
			?>
			<div class="woocommerce-notices-wrapper">
				<ul class="woocommerce-info" role="alert">
					<li>Processing payment. Please check your phone.</li>
				</ul>
			</div>
			<div class="wd-loader-wrapper">
				<div class="wd-loader-content">
					<div class="loader"></div>
					<p style="text-align: center;">Processing payment. Please wait...</p>
				</div>
			</div>
			<style>
				.wd-loader-wrapper {
					position: fixed;
					width: 100vw;
					height: 100vh;
					background: rgba(255, 255, 255, .85);
					z-index: 999999;
					top: 0;
					left: 0;
					display: flex;
					flex-direction: column;
					justify-content: center;
					align-items: center;
					text-align: center;
				}
				.wd-loader-wrapper .loader {
					border: 16px solid #f3f3f3; /* Light grey */
					border-top: 16px solid #3498db; /* Blue */
					border-radius: 50%;
					width: 120px;
					height: 120px;
					animation: spin 2s linear infinite;
					margin: 0 auto;
					margin-bottom: 2rem;
				}

				@keyframes spin {
					0% { transform: rotate(0deg); }
					100% { transform: rotate(360deg); }
				}
			</style>
			<script>
				// so that we limit the number of tries incase there is an issue.
				// var tries = 0; 

				// var overlay = document.createElement('div');
			
				(function pollTransaction(){
					console.log('<?= $ReturnUrl ?>');
					setTimeout(function(){
						var params = {
							method: 'POST',
						};

						fetch('/wp-json/wc-paynow-express/v1/order/<?php echo $order_id; ?>', params)
						.then(function(res) {
							return res.json();
						})
						.then(function(res){
							try {
								var data = JSON.parse(res);

								if ( data.hasOwnProperty('complete') ) {
									if (data.complete) {
										window.location.replace('<?php echo $ReturnUrl; ?>');
									} else {
										window.location.replace(data.url)
									}
								}
							} catch(e) {}
						});

						pollTransaction();
					}, 5000);
				}());
			
			</script>
			<?php
			exit;
			}
		}
	} // End receipt_page()

	/**
	 * log()
	 *
	 * Log system processes.
	 *
	 * @since 1.0.0
	 */

	function log ( $message, $close = false ) {

		static $fh = 0;

		if( $close ) {
            @fclose( $fh );
        } else {
            // If file doesn't exist, create it
            if( !$fh ) {
                $pathinfo = pathinfo( __FILE__ );
                $dir = str_replace( '/classes', '/logs', $pathinfo['dirname'] );
                $fh = @fopen( $dir .'/paynow-express.log', 'w' );
            }

            // If file was successfully created
            if( $fh ) {
                $line = $message ."\n";

                fwrite( $fh, $line );
            }
        }
	} // End log()

	
	/**
	 * Process notify from Paynow
	 * Called from wc-api to process paynow's response
	 *
	 * @since 1.2.0
	 */
	function paynow_checkout_return_handler()
	{
		global $woocommerce;
		
		// Check the request method is POST
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] != 'POST' && !isset($_GET['order_id']) ) {
			return;
		}

		$order_id = $_GET['order_id'];
		
		$order = wc_get_order( $order_id );
		
		$payment_meta = get_post_meta( $order_id, '_wc_paynowexpress_payment_meta', true );
		
		if($payment_meta)
		{
			$url = $payment_meta["PollUrl"];
			
			//execute post
			$response = wp_remote_request($url, [
				'timeout' => 45,
				'method' => 'POST',
				'body' => ''
			]);

			$result = $response['body'];
			
			if($result)
			{
				$msg = (new WC_PaynowExpress_Helper)->ParseMsg($result);

				$MerchantKey =  $this->merchant_key;
				$validateHash = (new WC_PaynowExpress_Helper)->CreateHash($msg, $MerchantKey);
				
				if($validateHash != $msg["hash"]){
					// hashes do not match 
					// look at throwing clean errors
					exit;
				}
				else
				{

					$payment_meta['PollUrl'] = $msg["pollurl"];
					$payment_meta['PaynowReference'] = $msg["paynowreference"];
					$payment_meta['Amount'] = $msg["amount"];
					$payment_meta['Status'] = $msg["status"];

					update_post_meta( $order_id, '_wc_paynowexpress_payment_meta', $payment_meta );
					
					if ( trim(strtolower($msg["status"]) ) == ps_cancelled ){
						$order->update_status( 'cancelled',  __('Payment cancelled on Paynow.', 'woothemes' ) );
						$order->save();
						return;
					}
					elseif ( trim(strtolower($msg["status"] ) ) == ps_failed ){
						$order->update_status( 'failed', __('Payment failed on Paynow.', 'woothemes' ) );
						$order->save();
						return;
					}
					elseif ( trim( strtolower( $msg["status"]) ) == ps_paid || trim( strtolower( $msg["status"] ) ) == ps_awaiting_delivery || trim( strtolower( $msg["status"] ) ) == ps_delivered ){
						$this->process_payment( $order_id, "callback" );
						return;
					}
					else {
						// keep current state (pending payment)
						// unknown status
					}
				}
			}
		}
	}// End wc_paynow_process_paynow_notify()

	function wc_express_check_status(WP_REST_Request $request) {

		$data = [];

		// Check the request method is POST
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] != 'POST' && !isset($request['id']) ) {
			return json_encode( $data );
		}
		
		$order_id = $request['id'];
		
		$order = wc_get_order( $order_id );
		
		$payment_meta = get_post_meta( $order_id, '_wc_paynowexpress_payment_meta', true );
		
		if($payment_meta)
		{
			
			$url = $payment_meta["PollUrl"];
			
			//execute post
			$response = wp_remote_request($url, [
				'timeout' => 45,
				'method' => 'POST',
				'body' => ''
			]);
	
			$result = $response['body'];
			
			if($result)
			{
				$msg = (new WC_PaynowExpress_Helper)->ParseMsg($result);
	
				$MerchantKey =  $this->merchant_key;
				$validateHash = (new WC_PaynowExpress_Helper)->CreateHash($msg, $MerchantKey);
				
				if($validateHash != $msg["hash"]){
				}
				else
				{
					if ( trim( strtolower( $msg["status"]) ) == ps_paid || trim( strtolower( $msg["status"] ) ) == ps_awaiting_delivery || trim( strtolower( $msg["status"] ) ) == ps_delivered ) {	
						$data = array(
							'complete' => true,
							'status' => 'paid',
						);
					} else if ( strtolower( $msg["status"]) == ps_cancelled || strtolower( $msg["status"]) == ps_failed) {
						$data = array(
							'complete' => false,
							'status' => $msg["status"],
							'url' => $order->get_checkout_payment_url( true )
						);
					}
				}
			}
		}

		return json_encode( $data );
	}
	
} // End Class