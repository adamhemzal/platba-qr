<?php
/*
  Plugin Name: platba-qr
  Description: Loads QR code from qr-platba.cz and inserts it in woocommerce checkout with correct payment info
  Version: 0.1.0
  Author: Niall Brown
  License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly

add_action( 'plugins_loaded', 'init_platba_qr_class' );

function init_platba_qr_class() {
    class WC_Platba_Qr extends WC_Payment_Gateway {

        function __construct() {
          $this->id = 'platba_qr';   // Unique ID for your gateway, e.g., ‘your_gateway’
          //$this->icon // If you want to show an image next to the gateway’s name on the frontend, enter a URL to an image.
          $this->has_fields = false; // Bool. Can be set to true if you want payment fields to show on the checkout (if doing a direct integration).
          $this->method_title = 'QR Code'; // Title of the payment method shown on the admin page.
          $this->method_description = 'Pay by scanning a QR code with an internet banking app'; // Description for the payment method shown on the admin page.

          // Load the settings
          $this->init_form_fields();
          $this->init_settings();

          // Define user set variables
          $this->title        = $this->get_option( 'title' );
          $this->description  = $this->get_option( 'description' );
          $this->instructions = $this->get_option( 'instructions' );
          $this->account_pref = $this->get_option( 'account_pref' );
          $this->account_num  = $this->get_option( 'account_num' );
          $this->bank_code    = $this->get_option( 'bank_code' );

          // Actions
          add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
          add_action( 'woocommerce_thankyou_bacs', array( $this, 'thankyou_page' ) );
          add_action( 'woocommerce_checkout_order_review', array( $this, 'sww_add_qr_to_checkout' ), 10, 2 );

          // Customer Emails
          add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );

        }  //construct

	public function sww_add_qr_to_checkout() {
        $customer_name = WC()->customer->get_first_name() . " " . WC()->customer->get_last_name();
        $order_total   = WC()->cart->total;
        $account_pref  = $this->get_option( 'account_pref' );
        $account_num   = $this->get_option( 'account_num' );
        $bank_code     = $this->get_option( 'bank_code' );
		$qr_url = "http://api.paylibo.com/paylibo/generator/czech/image?accountPrefix=${account_pref}&accountNumber=${account_num}&bankCode=${bank_code}&amount=${order_total}&currency=CZK&message=${customer_name}&size=200";
   		echo "<div>Pay now with your mobile banking app";
    	echo "<span id='niall'><img alt='QR platba' src='${qr_url}'/></span>";
    	echo "</div>";
	}

        // Build the administration fields for this specific payment option
        public function init_form_fields() {
          $this->form_fields = array
            (
             'enabled' => array
             (
              'title' => __( 'Enable/Disable', 'woocommerce' ),
              'type' => 'checkbox',
              'label' => __( 'Enable QR Payment', 'woocommerce' ),
              'default' => 'yes'
              ),
             'title' => array
             (
              'title' => __( 'Title', 'woocommerce' ),
              'type' => 'text',
              'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
              'default' => __( 'QR Payment', 'woocommerce' ),
              'desc_tip' => true,
              ),
             'description' => array
             (
              'title' => __( 'Description', 'woocommerce' ),
              'type' => 'textarea',
              'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
              'default' => __( 'Make your payment directly using QR code.', 'woocommerce'),
              'desc_tip' => true,
              ),
             'instructions' => array
             (
              'title' => __('Instructions', 'woocommerce'),
              'type' => 'textarea',
              'description' => __('Instructions that will be added to the thank you page and emails.', 'woocommerce'),
              'default' => __( "Scan the QR code in your banking app in your mobile device. Your order won't be shipped until the funds have arrived in our bank account.", 'woocommerce'),
              'desc_tip' => true,
              ),
             'account_pref' => array
             (
              'title' => __('Bank account number prefix', 'woocommerce'),
              'type' => 'text',
              'description' => __('Enter your CZK bank account number prefix here.', 'woocommerce'),
              'default' => __( '', 'woocommerce'),
              'desc_tip' => true,
              ),
             'account_num' => array
             (
              'title' => __('Bank account number', 'woocommerce'),
              'type' => 'text',
              'description' => __('Enter your CZK bank account number here, without prefix or bank code.', 'woocommerce'),
              'default' => __( '', 'woocommerce'),
              'desc_tip' => true,
              ),
             'bank_code' => array
             (
              'title' => __('Bank code', 'woocommerce'),
              'type' => 'text',
              'description' => __('Enter your bank code here.', 'woocommerce'),
              'default' => __( '', 'woocommerce'),
              'desc_tip' => true,
              ),
             );
        }  // init_form_fields

        function process_payment( $order_id ) {
          global $woocommerce;
          $order = new WC_Order( $order_id );
          
          // Mark as on-hold (we're awaiting the payment)
          $order->update_status('on-hold', __( 'Awaiting payment', 'woocommerce' ));
          
          // Reduce stock levels
          $order->reduce_order_stock();
          
          // Remove cart
          $woocommerce->cart->empty_cart();
          
          // Return thankyou redirect
          return array(
                       'result' => 'success',
                       'redirect' => $this->get_return_url( $order )
                       );
        }

        /**
         * Output for the order received page.
         */
        public function thankyou_page() {
            if ($this->instructions) {
                echo wpautop(wptexturize($this->instructions)). PHP_EOL;
            }
            if ($this->account_num) {
                echo "<br/>Please scan this QR code using your mobile banking app and complete the payment.";
                echo "<div class='qr_image_class'><img alt='QR platba' src='http://api.paylibo.com/paylibo/generator/czech/image?accountNumber='" . $this->account_num . "'&bankCode=0800&amount=1599.00&currency=CZK&vs='" . $order_id . "'&message=odomu.cz&size=200'/></div>";
                echo "<style>.qr_image_class{width:100%;display:block;padding:10px;} .qr_image_class > img{display:block;margin:0 auto;}</style>";
            }
        }

    } // class

    function add_platba_qr_class( $methods ) {
      $methods[] = 'WC_Platba_Qr'; 
      return $methods;
    }

    add_filter( 'woocommerce_payment_gateways', 'add_platba_qr_class' );

/*
    add_filter('woocommerce_thankyou_order_received_text', 'woo_change_order_received_text', 10, 2 );
    function woo_change_order_received_text( $str, $order ) {
       $new_str = 'test';
       return $new_str;
    }
*/
    
}

?>
