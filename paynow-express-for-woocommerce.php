<?php
/*
	Plugin Name: WooCommerce Paynow Express Gateway
	Plugin URI: http://www.paynow.co.zw/
	Description: An express payment gateway for Zimbabwean payment system, Paynow.
	Author: Webdev
	Version: 1.0.0
	Author URI: http://www.paynow.co.zw/
	Requires at least: 3.5
	Tested up to: 3.9.1
*/

add_action( 'plugins_loaded', 'woocommerce_paynow_express_init' );

/**
 * Initialize the gateway.
 *
 * @since 1.0.0
 */

function woocommerce_paynow_express_init() {
	load_plugin_textdomain( 'WC_PaynowExpress_express', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) );

	// Check if woocommerce is installed and available for use
	$active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) );

	if ( ! in_array( 'woocommerce/woocommerce.php', $active_plugins ) ) {
		if ( ! is_multisite() ) return; // nothing more to do. Plugin not available
		
		$site_wide_plugins = array_keys( get_site_option( 'active_sitewide_plugins', array() ) );
		
		if ( ! in_array( 'woocommerce/woocommerce.php', $site_wide_plugins ) ) return;
		
	};

	class WC_PaynowExpress {

		/**
		 * @var Singleton The reference the *Singleton* instance of this class
		 */
		private static $instance;

		/**
		 * Returns the *Singleton* instance of this class.
		 *
		 * @return Singleton The *Singleton* instance.
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		public function __clone(){}

		private function __wakeup() {}

		public function __construct()
		{
			$this->init();
		}

		public function init()
		{
			require_once dirname( __FILE__ ) . '/classes/class-wc-gateway-paynow-express.php';
			require_once dirname( __FILE__ ) . '/classes/class-wc-gateway-paynow-express-helper.php';
			require_once dirname( __FILE__ ) . '/includes/constants.php';

			add_filter('woocommerce_payment_gateways', array ($this, 'woocommerce_paynow_add_gateway' ) );

			add_action( 'rest_api_init', function () {
				register_rest_route( 'wc-paynow-express/v1', '/order/(?P<id>\d+)', array(
					'methods' => 'POST',
					'callback' => array(new WC_Gateway_PaynowExpress(), 'wc_express_check_status'),
					'permission_callback' => '__return_true'
				));

				register_rest_route('wc-paynow-express/v1', '/payment-result/(?P<order_id>\d+)', array(
					'methods' => 'POST',
					'callback' => array(new WC_Gateway_PaynowExpress(), 'wc_express_listen_status'),
					'args' => array(
						'order_id' => array(
							'validate_callback' => array(new WC_Gateway_PaynowExpress(), 'validate_order_id'),
						),
					),
					'permission_callback' => '__return_true'
					
				));
			} );

		}

		/**
		 * Add the gateway to WooCommerce
		 *
		 * @since 1.0.0
		 */
		function woocommerce_paynow_add_gateway( $methods ) {
			$methods[] = 'WC_Gateway_PaynowExpress';
			return $methods;
		} // End woocommerce_paynow_add_gateway()

	}

	WC_PaynowExpress::get_instance();
	
} // End woocommerce_paynow_init()