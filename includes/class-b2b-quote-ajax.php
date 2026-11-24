<?php
/**
 * AJAX endpoints for building and submitting a quote request.
 *
 * @package WooB2BQuotingEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Front-end AJAX controller.
 */
class B2B_Quote_Ajax {

	const SESSION_KEY   = 'b2b_quote_cart';
	const NONCE_ACTION  = 'b2b-quote-nonce';
	const MAX_ITEMS     = 100;
	const MAX_QUANTITY  = 999999;
	const THROTTLE_SECS = 20;

	/**
	 * Register endpoints.
	 */
	public function __construct() {
		$callbacks = array(
			'b2b_add_to_quote'      => 'add_to_quote',
			'b2b_remove_from_quote' => 'remove_from_quote',
			'b2b_update_quote_item' => 'update_quote_item',
			'b2b_submit_quote'      => 'submit_quote',
			'b2b_get_quote_count'   => 'get_quote_count',
		);

		foreach ( $callbacks as $action => $callback ) {
			add_action( 'wp_ajax_' . $action, array( $this, $callback ) );
			add_action( 'wp_ajax_nopriv_' . $action, array( $this, $callback ) );
		}
	}

	/**
	 * Safely resolve the WooCommerce session handler.
	 *
	 * 1.2.0 called `WC()->session` directly, which throws a fatal error when the
	 * session handler has not been initialised for the request.
	 *
	 * @return WC_Session|null
	 */
	private static function session() {
		if ( ! function_exists( 'WC' ) ) {
			return null;
		}

		$wc = WC();

		if ( ! $wc || ! isset( $wc->session ) || ! is_object( $wc->session ) ) {
			return null;
		}

		return $wc->session;
	}

	/**
	 * Current quote session contents.
	 *
	 * @return array
	 */
	public static function get_quote_session() {
		$session = self::session();

		if ( ! $session ) {
			return array();
		}

		$quote = $session->get( self::SESSION_KEY );

		return is_array( $quote ) ? $quote : array();
	}

	/**
	 * Persist the quote session.
	 *
	 * @param array $data Quote items keyed by product ID.
	 */
	public static function set_quote_session( $data ) {
		$session = self::session();

		if ( ! $session ) {
			return;
		}

		$session->set( self::SESSION_KEY, is_array( $data ) ? $data : array() );

		if ( ! $session->has_session() ) {
			$session->set_customer_session_cookie( true );
		}
	}

	/**
	 * Permalink of the quote cart page, or an empty string when unavailable.
	 *
	 * @return string
	 */
	public static function get_quote_page_url() {
		$page_id = absint( get_option( 'b2b_quote_page_id' ) );

		if ( ! $page_id ) {
			return '';
		}

		$page = get_post( $page_id );

		if ( ! $page || 'publish' !== $page->post_status ) {
			return '';
		}

		return (string) get_permalink( $page );
	}

	/**
	 * Total units across the quote session.
	 *
	 * @param array $quote_cart Quote items.
	 * @return int
	 */
	private static function total_quantity( array $quote_cart ) {
		$total = 0;

		foreach ( $quote_cart as $item ) {
			$total += isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 0;
		}

		return $total;
	}

	/**
	 * Standard payload returned to the browser after a mutation.
	 *
	 * Includes a freshly minted nonce so that a page served from a full-page
	 * cache cannot get stuck with an expired one.
	 *
	 * @param array $quote_cart Quote items.
	 * @param array $extra      Extra payload values.
	 * @return array
	 */
	private static function response_payload( array $quote_cart, array $extra = array() ) {
		return array_merge(
			array(
				'count'    => count( $quote_cart ),
				'quantity' => self::total_quantity( $quote_cart ),
				'cartUrl'  => self::get_quote_page_url(),
				'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
			),
			$extra
		);
	}

	/**
	 * Resolve a product ID to a product that is genuinely quoteable.
	 *
	 * @param int $product_id Product ID.
	 * @return WC_Product|false
	 */
	private static function get_quoteable_product( $product_id ) {
		$product_id = absint( $product_id );

		if ( ! $product_id || ! function_exists( 'wc_get_product' ) ) {
			return false;
		}

		$product = wc_get_product( $product_id );

		if ( ! $product instanceof WC_Product ) {
			return false;
		}

		if ( 'publish' !== $product->get_status() && ! current_user_can( 'edit_post', $product->get_id() ) ) {
			return false;
		}

		if ( ! B2B_Quote_Rules::is_product_quoteable( $product ) ) {
			return false;
		}

		return $product;
	}

	/**
	 * Transient key used for submission throttling.
	 *
	 * @return string
	 */
	private static function throttle_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return 'b2b_quote_throttle_' . md5( $ip . '|' . get_current_user_id() );
	}

	/**
	 * Return the current quote count.
	 */
	public function get_quote_count() {
		wp_send_json_success( self::response_payload( self::get_quote_session() ) );
	}

	/**
	 * Add a product to the quote request.
	 */
	public function add_to_quote() {
		check_ajax_referer( self::NONCE_ACTION, 'security' );

		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		$quantity   = isset( $_POST['quantity'] ) ? absint( wp_unslash( $_POST['quantity'] ) ) : 1;
		$quantity   = max( 1, min( self::MAX_QUANTITY, $quantity ) );

		$product = self::get_quoteable_product( $product_id );

		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'That product is not available for quote requests.', 'woo-b2b-quote' ) ) );
		}

		$product_id = $product->get_id();
		$quote_cart = self::get_quote_session();

		if ( ! isset( $quote_cart[ $product_id ] ) && count( $quote_cart ) >= self::MAX_ITEMS ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %d: maximum number of line items. */
						__( 'A quote request can hold at most %d different products.', 'woo-b2b-quote' ),
						self::MAX_ITEMS
					),
				)
			);
		}

		if ( isset( $quote_cart[ $product_id ]['quantity'] ) ) {
			$quantity = min( self::MAX_QUANTITY, absint( $quote_cart[ $product_id ]['quantity'] ) + $quantity );
		}

		$quote_cart[ $product_id ] = array(
			'product_id' => $product_id,
			'quantity'   => $quantity,
		);

		self::set_quote_session( $quote_cart );

		do_action( 'b2b_quote_item_added', $product_id, $quantity );

		wp_send_json_success(
			self::response_payload(
				$quote_cart,
				array(
					'message'      => __( 'Added to your quote request.', 'woo-b2b-quote' ),
					'itemQuantity' => $quantity,
				)
			)
		);
	}

	/**
	 * Change the quantity of an existing line, or remove it when set to zero.
	 */
	public function update_quote_item() {
		check_ajax_referer( self::NONCE_ACTION, 'security' );

		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		$quantity   = isset( $_POST['quantity'] ) ? absint( wp_unslash( $_POST['quantity'] ) ) : 0;

		$quote_cart = self::get_quote_session();

		if ( ! $product_id || ! isset( $quote_cart[ $product_id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'That item is no longer in your quote request.', 'woo-b2b-quote' ) ) );
		}

		if ( $quantity < 1 ) {
			unset( $quote_cart[ $product_id ] );
		} else {
			$quote_cart[ $product_id ]['quantity'] = min( self::MAX_QUANTITY, $quantity );
		}

		self::set_quote_session( $quote_cart );

		wp_send_json_success(
			self::response_payload(
				$quote_cart,
				array( 'message' => __( 'Quote request updated.', 'woo-b2b-quote' ) )
			)
		);
	}

	/**
	 * Remove a line from the quote request.
	 */
	public function remove_from_quote() {
		check_ajax_referer( self::NONCE_ACTION, 'security' );

		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		$quote_cart = self::get_quote_session();

		if ( ! $product_id || ! isset( $quote_cart[ $product_id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'That item is no longer in your quote request.', 'woo-b2b-quote' ) ) );
		}

		unset( $quote_cart[ $product_id ] );

		self::set_quote_session( $quote_cart );

		wp_send_json_success(
			self::response_payload(
				$quote_cart,
				array( 'message' => __( 'Item removed.', 'woo-b2b-quote' ) )
			)
		);
	}

	/**
	 * Persist the quote request and notify both sides.
	 */
	public function submit_quote() {
		check_ajax_referer( self::NONCE_ACTION, 'security' );

		// Honeypot: a hidden field that only automated submissions will populate.
		if ( ! empty( $_POST['b2b_confirm_url'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Your request could not be processed.', 'woo-b2b-quote' ) ) );
		}

		if ( get_transient( self::throttle_key() ) ) {
			wp_send_json_error( array( 'message' => __( 'Please wait a few seconds before sending another request.', 'woo-b2b-quote' ) ) );
		}

		$quote_cart = self::get_quote_session();

		if ( ! $quote_cart ) {
			wp_send_json_error( array( 'message' => __( 'Your quote request is empty.', 'woo-b2b-quote' ) ) );
		}

		$client_name    = isset( $_POST['client_name'] ) ? sanitize_text_field( wp_unslash( $_POST['client_name'] ) ) : '';
		$client_email   = isset( $_POST['client_email'] ) ? sanitize_email( wp_unslash( $_POST['client_email'] ) ) : '';
		$client_company = isset( $_POST['client_company'] ) ? sanitize_text_field( wp_unslash( $_POST['client_company'] ) ) : '';
		$technical_reqs = isset( $_POST['technical_reqs'] ) ? sanitize_textarea_field( wp_unslash( $_POST['technical_reqs'] ) ) : '';

		if ( '' === $client_name ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please enter your name.', 'woo-b2b-quote' ),
					'field'   => 'client_name',
				)
			);
		}

		if ( ! is_email( $client_email ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please enter a valid email address.', 'woo-b2b-quote' ),
					'field'   => 'client_email',
				)
			);
		}

		$quote_id = B2B_Quote_DB::insert_quote(
			array(
				'client_name'    => $client_name,
				'client_email'   => $client_email,
				'client_company' => $client_company,
				'technical_reqs' => $technical_reqs,
				'quote_data'     => array_values( $quote_cart ),
			)
		);

		if ( ! $quote_id ) {
			wp_send_json_error( array( 'message' => __( 'We could not save your request. Please try again.', 'woo-b2b-quote' ) ) );
		}

		self::set_quote_session( array() );
		set_transient( self::throttle_key(), 1, self::THROTTLE_SECS );

		do_action( 'b2b_quote_request_created', $quote_id );

		if ( class_exists( 'B2B_Quote_Emails' ) ) {
			B2B_Quote_Emails::send_admin_notification( $quote_id );
			B2B_Quote_Emails::send_customer_receipt( $quote_id );
		}

		wp_send_json_success(
			self::response_payload(
				array(),
				array(
					'message'  => __( 'Thank you. Your quote request has been submitted and our team will be in touch shortly.', 'woo-b2b-quote' ),
					'quoteId'  => $quote_id,
					'complete' => true,
				)
			)
		);
	}
}
