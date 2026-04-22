<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class B2B_Quote_Checkout {

	public function __construct() {
		add_action( 'template_redirect', array( $this, 'process_approval_token' ) );
	}

	public function process_approval_token() {
		if ( ! isset( $_GET['b2b_token'] ) || empty( $_GET['b2b_token'] ) ) {
			return;
		}

		$token = sanitize_text_field( $_GET['b2b_token'] );

		global $wpdb;
		$table_name = B2B_Quote_DB::get_table_name();
		$quote = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE approval_token = %s AND status = 'negotiating'", $token ) );

		if ( ! $quote ) {
			wp_die( 'Invalid, expired, or already processed approval token.' );
		}

		// Programmatically convert to cart
		$this->convert_quote_to_order( $quote );
	}

	private function convert_quote_to_order( $quote ) {
		// Clear current cart just in case
		WC()->cart->empty_cart();

		$quote_data = json_decode( $quote->quote_data, true );

		foreach ( $quote_data as $item ) {
			$product_id = $item['product_id'];
			$quantity = $item['quantity'];
			$price = isset( $item['negotiated_price'] ) ? $item['negotiated_price'] : 0;
			
			// Add custom cart item data to identify it needs price override
			WC()->cart->add_to_cart( $product_id, $quantity, 0, array(), array( 'b2b_custom_price' => $price ) );
		}

		// Update DB status to accepted (so link cannot be re-used to reset cart endlessly to this state if we don't want)
		// Or we can leave it 'negotiating' until actual WooCommerce order is complete. Let's leave it as 'negotiating' and WC will create the order, then we can hook into wc order complete to mark this as 'accepted'.
		// Actually, marking it 'accepted' now allows them to hit checkout. We'll just update it here.
		global $wpdb;
		$table_name = B2B_Quote_DB::get_table_name();
		$wpdb->update(
			$table_name,
			array( 'status' => 'accepted' ), 
			array( 'id' => $quote->id ),
			array( '%s' ),
			array( '%d' )
		);

		// Redirect to checkout
		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}
}

// Ensure custom price is applied to those cart items globally
add_action( 'woocommerce_before_calculate_totals', 'b2b_apply_custom_quote_prices', 10, 1 );
function b2b_apply_custom_quote_prices( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

	foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
		if ( isset( $cart_item['b2b_custom_price'] ) ) {
			$cart_item['data']->set_price( $cart_item['b2b_custom_price'] );
		}
	}
}
