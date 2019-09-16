<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              codeboxr.com
 * @since             1.0.0
 * @package           cbxwoofreeproductquickcheckout
 *
 * @wordpress-plugin
 * Plugin Name:       CBX Woo Free Product Quick Checkout
 * Plugin URI:        https://github.com/manchumahara/cbxwoofreeproductquickcheckout
 * Description:       Quick checkout for woocommerce free products
 * Version:           1.0.2
 * Author:            Codeboxr Team
 * Author URI:        https://codeboxr.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       cbxwfpquickcheckout
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}


defined( 'CBXWOOFREEPRODUCTQUICKCHECKOUT_PLUGIN_NAME' ) or define( 'CBXWOOFREEPRODUCTQUICKCHECKOUT_PLUGIN_NAME', 'cbxwoofreeproductquickcheckout' );
defined( 'CBXWOOFREEPRODUCTQUICKCHECKOUT_PLUGIN_VERSION' ) or define( 'CBXWOOFREEPRODUCTQUICKCHECKOUT_PLUGIN_VERSION', '1.0.2' );
defined( 'CBXWOOFREEPRODUCTQUICKCHECKOUT_BASE_NAME' ) or define( 'CBXWOOFREEPRODUCTQUICKCHECKOUT_BASE_NAME', plugin_basename( __FILE__ ) );
defined( 'CBXWOOFREEPRODUCTQUICKCHECKOUT_ROOT_PATH' ) or define( 'CBXWOOFREEPRODUCTQUICKCHECKOUT_ROOT_PATH', plugin_dir_path( __FILE__ ) );
defined( 'CBXWOOFREEPRODUCTQUICKCHECKOUT_ROOT_URL' ) or define( 'CBXWOOFREEPRODUCTQUICKCHECKOUT_ROOT_URL', plugin_dir_url( __FILE__ ) );

class CBXWooFreeProductQuickCheckout {
	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $plugin_name The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $version The current version of this plugin.
	 */
	private $version;

	public function __construct() {
		$this->plugin_name = CBXWOOFREEPRODUCTQUICKCHECKOUT_PLUGIN_NAME;
		$this->version     = CBXWOOFREEPRODUCTQUICKCHECKOUT_PLUGIN_VERSION;

		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );

		add_action( 'wp', array( $this, 'free_checkout_fields' ) );
		add_filter( 'woocommerce_product_single_add_to_cart_text', array( $this, 'change_add_to_cart_button' ), 20, 2 );
		add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'change_add_to_cart_button' ), 20, 2 );

		add_filter( 'woocommerce_get_price_html', array( $this, 'price_free_zero_empty' ), 100, 2 );


		// remove coupon forms since why would you want a coupon for a free cart??

		// Remove the "Additional Info" order notes
		add_filter( 'woocommerce_enable_order_notes_field', array( $this, 'woocommerce_enable_order_notes_field' ) );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'custom_override_checkout_fields' ), 9999 );
		add_filter( 'woocommerce_default_address_fields', array( $this, 'custom_override_default_address_fields' ), 9999 );
		add_filter( 'woocommerce_add_to_cart_redirect', array( $this, 'redirect_add_to_cart' ) );

		//guest checkout
		add_filter( 'pre_option_woocommerce_enable_guest_checkout', array( $this, 'enable_guest_checkout_based_on_product' ) );
		add_filter( 'woocommerce_email_enabled_new_order', array( $this, 'disable_email_new_order' ), 10, 2 );
		add_filter( 'woocommerce_email_recipient_customer_completed_order', array( $this, 'product_cat_avoid_processing_email_notification' ), 10, 2 );
		add_filter( 'woocommerce_email_recipient_customer_processing_order', array( $this, 'product_cat_avoid_processing_email_notification' ), 10, 2 );

		//add fields to advance tab
		add_filter( 'woocommerce_get_sections_advanced', array( $this, 'freequick_add_section' ) );
		add_filter( 'woocommerce_get_settings_advanced', array( $this, 'freequick_all_settings' ), 10, 2 );

		//add setting link to plugin listing
		add_filter( 'plugin_action_links_' . CBXWOOFREEPRODUCTQUICKCHECKOUT_BASE_NAME, array($this, 'plugin_action_links') );

	}//end of constructor

	/**
	 * Load translation text domain
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'cbxwfpquickcheckout', false, basename( dirname( __FILE__ ) ) . '/languages/' );
	}//end function load_plugin_textdomain

	/**
	 * Removes coupon form, order notes, and several billing fields if the checkout doesn't require payment.
	 *
	 * REQUIRES PHP 5.3+
	 *
	 * Tutorial: http://skyver.ge/c
	 */
	public function free_checkout_fields() {
		// first, bail if WC isn't active since we're hooked into a general WP hook
		if ( ! function_exists( 'WC' ) ) {
			return;
		}
		// bail if the cart needs payment, we don't want to do anything
		if ( WC()->cart && WC()->cart->needs_payment() ) {
			return;
		}
		// now continue only if we're at checkout
		// is_checkout() was broken as of WC 3.2 in ajax context, double-check for is_ajax
		// I would check WOOCOMMERCE_CHECKOUT but testing shows it's not set reliably
		if ( function_exists( 'is_checkout' ) && ( is_checkout() || is_ajax() ) ) {
			// remove coupon forms since why would you want a coupon for a free cart??
			remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );


			// Unset the fields we don't want in a free checkout
			//add_filter( 'woocommerce_checkout_fields', array($this, 'custom_override_checkout_fields'), 9999 );
			//add_filter( 'woocommerce_default_address_fields', array($this,  'custom_override_default_address_fields'), 9999 );
		}
	}//end method free_checkout_fields

	/**
	 * Change Add to Cart text to Download for free products for digital product
	 *
	 * @param $button_text
	 * @param $product
	 *
	 * @return string
	 */
	public function change_add_to_cart_button( $button_text, $product ) {
		if ( ( '' === $product->get_price() || 0 == $product->get_price() ) && $product->is_downloadable( 'yes' ) ) {
			$addtocartlabel = sanitize_text_field(get_option('cbxwfpquickcheckout_addtocartlabel', ''));
			if($addtocartlabel != ''){
				$button_text = esc_html($addtocartlabel);
			}
		}

		return $button_text;
	}//end method change_add_to_cart_button

	/**
	 * Remove Price with text Free for free products
	 *
	 * @param $price
	 * @param $product
	 *
	 * @return string
	 */
	public function price_free_zero_empty( $price, $product ) {
		if ( '' === $product->get_price() || 0 == $product->get_price() ) {
			$freeprice = sanitize_text_field(get_option('cbxwfpquickcheckout_freeprice', ''));
			if($freeprice != ''){
				$price = '<span class="woocommerce-Price-amount woocommerce-Price-amount-free amount">' . esc_html($freeprice). '</span>';
			}
		}

		return $price;
	}//end method price_free_zero_empty

	public function woocommerce_enable_order_notes_field( $bool ) {
		if ( WC()->cart && WC()->cart->needs_payment() ) {
			return $bool;
		}

		$remove_note_field = sanitize_text_field(get_option('cbxwfpquickcheckout_remove_note_field', 'no'));
		if($remove_note_field == 'yes') return false;


		return $bool;
	}//end method woocommerce_enable_order_notes_field

	/**
	 * @param $fields
	 *
	 * @return mixed
	 */
	public function custom_override_checkout_fields( $fields ) {

		if ( WC()->cart && WC()->cart->needs_payment() ) {
			return $fields;
		}

		$remove_billing_fields = sanitize_text_field(get_option('cbxwfpquickcheckout_remove_billing_fields', 'no'));

		if($remove_billing_fields == 'yes'){
			// add or remove billing fields you do not want
			// fields: http://docs.woothemes.com/document/tutorial-customising-checkout-fields-using-actions-and-filters/#section-2
			$billing_keys = array(
				'billing_company',
				'billing_country',
				'billing_phone',
				'billing_address_1',
				'billing_address_2',
				'billing_city',
				'billing_state',
				'billing_postcode',
			);
			// unset each of those unwanted fields
			foreach ( $billing_keys as $key ) {
				unset( $fields['billing'][ $key ] );
			}

			$shipping_keys = array(
				'shipping_company',
				'shipping_country',
				'shipping_phone',
				'shipping_address_1',
				'shipping_address_2',
				'shipping_city',
				'shipping_state',
				'shipping_postcode',
			);

			foreach ( $shipping_keys as $key ) {
				unset( $fields['shipping'][ $key ] );
			}
		}

		return $fields;
	}//end method custom_override_checkout_fields

	/**
	 * @param $address_fields
	 *
	 * @return mixed
	 */
	public function custom_override_default_address_fields( $address_fields ) {

		if ( WC()->cart && WC()->cart->needs_payment() ) {
			return $address_fields;
		}

		$remove_billing_fields = sanitize_text_field(get_option('cbxwfpquickcheckout_remove_billing_fields', 'no'));
		if($remove_billing_fields == 'yes'){
			$fields = array(
				'company',
				'country',
				//'phone',
				'address_1',
				'address_2',
				'city',
				'state',
				'postcode',
			);

			foreach ( $fields as $field ) {
				$address_fields[ $field ]['required'] = false;
				if ( isset( $address_fields[ $field ]['validate'] ) ) {
					unset( $address_fields[ $field ]['validate'] );
				}
			}
		}

		return $address_fields;
	}//end method custom_override_default_address_fields

	/**
	 * Redirect to checkout for free products
	 *
	 * @return string
	 */
	public function redirect_add_to_cart() {
		global $woocommerce;
		if ( WC()->cart && WC()->cart->needs_payment() ) {
			return wc_get_cart_url();
		}

		$redirect_checkout = sanitize_text_field(get_option('cbxwfpquickcheckout_redirect_checkout', 'no'));
		if($redirect_checkout == 'yes') return wc_get_checkout_url();

		return wc_get_cart_url();

	}//end method redirect_add_to_cart

	/**
	 * Allow guest checkout for free orders
	 *
	 * @param $value
	 *
	 * @return string
	 */
	public function enable_guest_checkout_based_on_product( $value ) {
		if ( WC()->cart && WC()->cart->needs_payment() ) {
			return $value;
		}

		$guest_checkout = sanitize_text_field(get_option('cbxwfpquickcheckout_guest_checkout', 'no'));
		if($guest_checkout == 'yes') return 'yes';

		return $value;
	}//end method enable_guest_checkout_based_on_product

	/**
	 * Disable amdin email for new order
	 *
	 * @param $enabled
	 * @param $order
	 *
	 * @return bool
	 */
	public function disable_email_new_order( $enabled, $order ) {
		if ( $order instanceof WC_Order ) {

			$order_total = floatval( $order->get_total() );

			if ( $order_total == 0 ) {
				sanitize_text_field($admin_neworder = get_option('cbxwfpquickcheckout_admin_neworder', 'no'));
				if($admin_neworder == 'yes') return false;
			}

		}

		return $enabled;
	}//end method disable_email_new_order

	/**
	 * Disable customer email for free order
	 *
	 * @param $recipient
	 * @param $order
	 *
	 * @return string
	 */
	public function product_cat_avoid_processing_email_notification( $recipient, $order ) {
		if ( is_admin() ) {
			return $recipient;
		}



		if ( $order instanceof WC_Order ) {

			$order_total = floatval( $order->get_total() );

			if ( $order_total == 0 ) {
				$customer_ordercomplete = sanitize_text_field(get_option('cbxwfpquickcheckout_customer_ordercomplete', 'no'));

				if($customer_ordercomplete == 'yes' ) return '';
			}

		}

		return $recipient;
	}//end emthod product_cat_avoid_processing_email_notification

	/**
	 * Add section in woocommerce setting advance tab
	 *
	 * @param $sections
	 *
	 * @return mixed
	 */
	public function freequick_add_section( $sections ) {
		$sections['cbxwfpquickcheckout'] = esc_html__( 'CBX Woo Free Product Quick Checkout', 'cbxwfpquickcheckout' );
		return $sections;
	}//end method freequick_add_section

	public function freequick_all_settings( $settings, $current_section ) {

		/**
		 * Check the current section is what we want
		 **/
		if ( $current_section == 'cbxwfpquickcheckout' ) {
			$settings_slider = array();
			// Add Title to the Settings
			$settings_slider[] = array( 'name' => esc_html__( 'CBX Woo Free Product Quick Checkout Settings', 'cbxwfpquickcheckout' ), 'type' => 'title', 'id' => 'cbxwfpquickcheckout' );
			// Add first checkbox option
			$settings_slider[] = array(
				'name'     => esc_html__( 'New Order Email for Admin', 'cbxwfpquickcheckout' ),
				'id'       => 'cbxwfpquickcheckout_admin_neworder',
				'type'     => 'checkbox',
				'css'      => 'min-width:300px;',
				'desc'     => esc_html__( 'Disable', 'cbxwfpquickcheckout' ),
			);
			$settings_slider[] = array(
				'name'     => esc_html__( 'Order Complete Email for Customer', 'cbxwfpquickcheckout' ),
				'id'       => 'cbxwfpquickcheckout_customer_ordercomplete',
				'type'     => 'checkbox',
				'css'      => 'min-width:300px;',
				'desc'     => esc_html__( 'Disable', 'cbxwfpquickcheckout' ),
			);

			$settings_slider[] = array(
				'name'     => esc_html__( 'Enable Guest Checkout', 'cbxwfpquickcheckout' ),
				'id'       => 'cbxwfpquickcheckout_guest_checkout',
				'type'     => 'checkbox',
				'css'      => 'min-width:300px;',
				'desc'     => esc_html__( 'Yes', 'cbxwfpquickcheckout' ),
			);

			$settings_slider[] = array(
				'name'     => esc_html__( 'Remove Note Field', 'cbxwfpquickcheckout' ),
				'id'       => 'cbxwfpquickcheckout_remove_note_field',
				'type'     => 'checkbox',
				'css'      => 'min-width:300px;',
				'desc'     => esc_html__( 'Yes', 'cbxwfpquickcheckout' ),
			);

			$settings_slider[] = array(
				'name'     => esc_html__( 'Ignore Cart Page, Redirect to Checkout', 'cbxwfpquickcheckout' ),
				'id'       => 'cbxwfpquickcheckout_redirect_checkout',
				'type'     => 'checkbox',
				'css'      => 'min-width:300px;',
				'desc'     => esc_html__( 'Yes', 'cbxwfpquickcheckout' ),
			);

			$settings_slider[] = array(
				'name'     => esc_html__( 'Hide Billing & Shipping Fields', 'cbxwfpquickcheckout' ),
				'id'       => 'cbxwfpquickcheckout_remove_billing_fields',
				'type'     => 'checkbox',
				'css'      => 'min-width:300px;',
				'desc'     => esc_html__( 'Yes', 'cbxwfpquickcheckout' ),
			);



			$settings_slider[] = array(
				'name'     => esc_html__( 'Change Price for Free Product', 'cbxwfpquickcheckout' ),
				'desc'     => esc_html__( 'Keep empty to ignore', 'cbxwfpquickcheckout' ),
				'id'       => 'cbxwfpquickcheckout_freeprice',
				'type'     => 'text',
				'default'  => esc_html__('Free', 'cbxwfpquickcheckout')
			);

			$settings_slider[] = array(
				'name'     => esc_html__( 'Change Add to Cart Label','cbxwfpquickcheckout' ),
				'desc'     => esc_html__( 'For only downloadable product, Keep empty to ignore', 'cbxwfpquickcheckout' ),
				'id'       => 'cbxwfpquickcheckout_addtocartlabel',
				'type'     => 'text',
				'default'  => esc_html__('Download', 'cbxwfpquickcheckout')
			);

			$settings_slider[] = array( 'type' => 'sectionend', 'id' => 'cbxwfpquickcheckout' );

			return $settings_slider;

			/**
			 * If not, return the standard settings
			 **/
		} else {
			return $settings;
		}

	}//end method freequick_all_settings

	/**
	 * Show action links on the plugin screen.
	 *
	 * @param   mixed $links Plugin Action links.
	 *
	 * @return  array
	 */
	public static function plugin_action_links( $links ) {
		$action_links = array(
			'settings' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=advanced&section=cbxwfpquickcheckout' ) . '" aria-label="' . esc_attr__( 'View settings', 'cbxwfpquickcheckout' ) . '">' . esc_html__( 'Settings', 'cbxwfpquickcheckout' ) . '</a>',
		);

		return array_merge( $action_links, $links );
	}//end method plugin_action_links

}//end class CBXWooFreeProductQuickCheckout

new CBXWooFreeProductQuickCheckout();