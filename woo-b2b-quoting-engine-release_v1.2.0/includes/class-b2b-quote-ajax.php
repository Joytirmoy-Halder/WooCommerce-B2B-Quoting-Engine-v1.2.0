<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class B2B_Quote_Ajax {

	public function __construct() {
		// AJAX for adding to quote
		add_action( 'wp_ajax_b2b_add_to_quote', array( $this, 'add_to_quote' ) );
		add_action( 'wp_ajax_nopriv_b2b_add_to_quote', array( $this, 'add_to_quote' ) );
		
		// AJAX for removing from quote
		add_action( 'wp_ajax_b2b_remove_from_quote', array( $this, 'remove_from_quote' ) );
		add_action( 'wp_ajax_nopriv_b2b_remove_from_quote', array( $this, 'remove_from_quote' ) );

		// AJAX for submitting quote
		add_action( 'wp_ajax_b2b_submit_quote', array( $this, 'submit_quote' ) );
		add_action( 'wp_ajax_nopriv_b2b_submit_quote', array( $this, 'submit_quote' ) );
		
		// AJAX for checking quote state asynchronously to avoid caching
		add_action( 'wp_ajax_b2b_get_quote_count', array( $this, 'get_quote_count' ) );
		add_action( 'wp_ajax_nopriv_b2b_get_quote_count', array( $this, 'get_quote_count' ) );
	}

	/**
	 * Get current quote session data
	 */
	public static function get_quote_session() {
		if ( isset( WC()->session ) ) {
			$quote = WC()->session->get( 'b2b_quote_cart' );
			return is_array( $quote ) ? $quote : array();
		}
		return array();
	}

	/**
	 * Set quote session data
	 */
	public static function set_quote_session( $data ) {
		if ( isset( WC()->session ) ) {
			WC()->session->set( 'b2b_quote_cart', $data );
			// Force WooCommerce to set the session cookie so the quote cart persists across page loads
			if ( ! WC()->session->has_session() ) {
				WC()->session->set_customer_session_cookie( true );
			}
		}
	}

	public function get_quote_count() {
		$quote_cart = self::get_quote_session();
		wp_send_json_success( array( 'count' => count( $quote_cart ) ) );
	}

	public function add_to_quote() {
		check_ajax_referer( 'b2b-quote-nonce', 'security' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$quantity   = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => 'Invalid product ID.' ) );
		}

		$quote_cart = self::get_quote_session();
		
		// If product already in quote, update quantity
		if ( isset( $quote_cart[ $product_id ] ) ) {
			$quote_cart[ $product_id ]['quantity'] += $quantity;
		} else {
			$quote_cart[ $product_id ] = array(
				'product_id' => $product_id,
				'quantity'   => $quantity,
			);
		}

		self::set_quote_session( $quote_cart );

		$page_id = get_option( 'b2b_quote_page_id' );
		$cart_url = $page_id ? get_permalink( $page_id ) : '';

		wp_send_json_success( array( 
			'message'  => 'Product added to quote.',
			'count'    => count( $quote_cart ),
			'cart_url' => $cart_url
		) );
	}
	
	public function remove_from_quote() {
		check_ajax_referer( 'b2b-quote-nonce', 'security' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

		$quote_cart = self::get_quote_session();
		if ( isset( $quote_cart[ $product_id ] ) ) {
			unset( $quote_cart[ $product_id ] );
			self::set_quote_session( $quote_cart );
			wp_send_json_success( array( 
				'message' => 'Item removed.',
				'count'   => count( $quote_cart ) 
			) );
		}
		wp_send_json_error( array( 'message' => 'Item not found.' ) );
	}

	public function submit_quote() {
		check_ajax_referer( 'b2b-quote-nonce', 'security' );
		
		$quote_cart = self::get_quote_session();
		if ( empty( $quote_cart ) ) {
			wp_send_json_error( array( 'message' => 'Your quote cart is empty.' ) );
		}

		$client_name    = isset( $_POST['client_name'] ) ? sanitize_text_field( $_POST['client_name'] ) : '';
		$client_email   = isset( $_POST['client_email'] ) ? sanitize_email( $_POST['client_email'] ) : '';
		$client_company = isset( $_POST['client_company'] ) ? sanitize_text_field( $_POST['client_company'] ) : '';
		$technical_reqs = isset( $_POST['technical_reqs'] ) ? sanitize_textarea_field( $_POST['technical_reqs'] ) : '';

		if ( empty( $client_name ) || empty( $client_email ) ) {
			wp_send_json_error( array( 'message' => 'Name and Email are required.' ) );
		}

		global $wpdb;
		$table_name = B2B_Quote_DB::get_table_name();

		$inserted = $wpdb->insert(
			$table_name,
			array(
				'client_name'    => $client_name,
				'client_email'   => $client_email,
				'client_company' => $client_company,
				'technical_reqs' => $technical_reqs,
				'quote_data'     => wp_json_encode( $quote_cart ),
				'status'         => 'pending',
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $inserted ) {
			$quote_id = $wpdb->insert_id;
			// Clear session
			self::set_quote_session( null );
			
			// Trigger Email
			if ( class_exists( 'B2B_Quote_Emails' ) ) {
				B2B_Quote_Emails::send_admin_notification( $quote_id, $client_name, $client_email );
			}

			wp_send_json_success( array( 'message' => 'Quote request submitted successfully!' ) );
		}

		wp_send_json_error( array( 'message' => 'Database error occurred.' ) );
	}
}
