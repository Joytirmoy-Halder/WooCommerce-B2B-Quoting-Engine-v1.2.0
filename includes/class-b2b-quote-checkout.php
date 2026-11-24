<?php
/**
 * Converts an approved quote into a WooCommerce order.
 *
 * @package WooB2BQuotingEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Quote to checkout bridge.
 */
class B2B_Quote_Checkout {

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'process_approval_token' ) );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_negotiated_prices' ), 20 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_meta' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );
		add_action( 'woocommerce_checkout_order_created', array( $this, 'link_order_to_quote' ) );
	}

	/**
	 * Redeem an approval token and send the customer to checkout.
	 *
	 * The token itself is the capability, so no nonce is required, but it is
	 * single use: it is cleared from the row as soon as it is redeemed.
	 */
	public function process_approval_token() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The signed token is the credential.
		if ( empty( $_GET['b2b_token'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The signed token is the credential.
		$token = sanitize_text_field( wp_unslash( $_GET['b2b_token'] ) );

		$quote = B2B_Quote_DB::get_quote_by_token( $token, array( B2B_Quote_DB::STATUS_WAITING_APPROVAL ) );

		if ( ! $quote ) {
			wp_die(
				esc_html__( 'This quote link is no longer valid. It may have already been used or replaced by a newer quote. Please contact us and we will resend it.', 'woo-b2b-quote' ),
				esc_html__( 'Quote link unavailable', 'woo-b2b-quote' ),
				array(
					'response'  => 410,
					'back_link' => true,
				)
			);
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$items = B2B_Quote_DB::decode_items( $quote );

		if ( ! $items ) {
			wp_die(
				esc_html__( 'This quote does not contain any products we can add to your basket. Please contact us.', 'woo-b2b-quote' ),
				esc_html__( 'Quote unavailable', 'woo-b2b-quote' ),
				array(
					'response'  => 410,
					'back_link' => true,
				)
			);
		}

		WC()->cart->empty_cart();

		$added = 0;

		foreach ( $items as $item ) {
			$product = wc_get_product( $item['product_id'] );

			if ( ! $product instanceof WC_Product || ! $product->is_purchasable() ) {
				continue;
			}

			$cart_item_data = array( 'b2b_quote_id' => (int) $quote->id );

			/*
			 * Only override the price when a price was actually negotiated.
			 *
			 * 1.2.0 defaulted to `0` for every line and then called set_price(0),
			 * so following an approval link produced a completely free order.
			 * Lines without an agreed price now simply keep the catalogue price.
			 */
			if ( null !== $item['negotiated_price'] ) {
				$cart_item_data['b2b_quote_price'] = (float) $item['negotiated_price'];
			}

			$parent_id    = $product->get_parent_id();
			$variation_id = 0;
			$variation    = array();
			$product_id   = $product->get_id();

			if ( $product->is_type( 'variation' ) && $parent_id ) {
				$variation_id = $product->get_id();
				$product_id   = $parent_id;
				$variation    = method_exists( $product, 'get_variation_attributes' ) ? (array) $product->get_variation_attributes() : array();
			}

			$result = WC()->cart->add_to_cart( $product_id, $item['quantity'], $variation_id, $variation, $cart_item_data );

			if ( $result ) {
				++$added;
			}
		}

		if ( ! $added ) {
			wp_die(
				esc_html__( 'None of the products on this quote can be purchased right now. Please contact us.', 'woo-b2b-quote' ),
				esc_html__( 'Quote unavailable', 'woo-b2b-quote' ),
				array(
					'response'  => 409,
					'back_link' => true,
				)
			);
		}

		B2B_Quote_DB::update_quote(
			$quote->id,
			array(
				'status'         => B2B_Quote_DB::STATUS_ACCEPTED,
				'approval_token' => '',
			)
		);

		do_action( 'b2b_quote_approved', (int) $quote->id );

		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	/**
	 * Apply agreed prices to the cart.
	 *
	 * @param WC_Cart $cart Cart instance.
	 */
	public function apply_negotiated_prices( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		// WooCommerce can fire this hook more than once per request.
		if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) {
			return;
		}

		if ( ! $cart instanceof WC_Cart ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( ! isset( $cart_item['b2b_quote_price'] ) || '' === $cart_item['b2b_quote_price'] ) {
				continue;
			}

			if ( empty( $cart_item['data'] ) || ! $cart_item['data'] instanceof WC_Product ) {
				continue;
			}

			$cart_item['data']->set_price( max( 0, (float) $cart_item['b2b_quote_price'] ) );
		}
	}

	/**
	 * Show the originating quote on cart and checkout line items.
	 *
	 * @param array $item_data Existing meta rows.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public function display_cart_item_meta( $item_data, $cart_item ) {
		if ( empty( $cart_item['b2b_quote_id'] ) ) {
			return $item_data;
		}

		$item_data[] = array(
			'key'     => __( 'Quote', 'woo-b2b-quote' ),
			'value'   => '#' . absint( $cart_item['b2b_quote_id'] ),
			'display' => '',
		);

		return $item_data;
	}

	/**
	 * Persist quote references onto the order line item.
	 *
	 * @param WC_Order_Item_Product $item          Order line item.
	 * @param string                $cart_item_key Cart item key.
	 * @param array                 $values        Cart item values.
	 * @param WC_Order              $order         Order object.
	 */
	public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( ! empty( $values['b2b_quote_id'] ) ) {
			$item->add_meta_data( '_b2b_quote_id', absint( $values['b2b_quote_id'] ), true );
		}

		if ( isset( $values['b2b_quote_price'] ) && '' !== $values['b2b_quote_price'] ) {
			$item->add_meta_data( '_b2b_quote_price', (float) $values['b2b_quote_price'], true );
		}
	}

	/**
	 * Store the order ID against the quote so the CRM can link to it.
	 *
	 * @param WC_Order $order Newly created order.
	 */
	public function link_order_to_quote( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$quote_id = 0;

		foreach ( $order->get_items() as $item ) {
			$candidate = absint( $item->get_meta( '_b2b_quote_id', true ) );

			if ( $candidate ) {
				$quote_id = $candidate;
				break;
			}
		}

		if ( ! $quote_id ) {
			return;
		}

		$order->update_meta_data( '_b2b_quote_id', $quote_id );
		$order->save();

		B2B_Quote_DB::update_quote( $quote_id, array( 'order_id' => $order->get_id() ) );
	}
}
