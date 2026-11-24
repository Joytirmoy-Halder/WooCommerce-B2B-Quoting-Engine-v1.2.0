<?php
/**
 * Transactional emails for the quoting workflow.
 *
 * Every dynamic value is escaped before it reaches the HTML body. 1.2.0
 * interpolated the raw submitted name, company and free-text requirements
 * straight into the markup, which allowed HTML injection into staff inboxes.
 *
 * @package WooB2BQuotingEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Email sender.
 */
class B2B_Quote_Emails {

	/**
	 * Resolve the WooCommerce mailer, if available.
	 *
	 * @return WC_Emails|null
	 */
	private static function mailer() {
		if ( ! function_exists( 'WC' ) ) {
			return null;
		}

		$wc = WC();

		if ( ! $wc || ! method_exists( $wc, 'mailer' ) ) {
			return null;
		}

		$mailer = $wc->mailer();

		return is_object( $mailer ) ? $mailer : null;
	}

	/**
	 * Where internal notifications are sent.
	 *
	 * @return string
	 */
	public static function get_admin_recipient() {
		$recipient = get_option( 'b2b_quote_admin_email' );

		if ( ! is_email( $recipient ) ) {
			$recipient = get_option( 'admin_email' );
		}

		return (string) apply_filters( 'b2b_quote_admin_recipient', $recipient );
	}

	/**
	 * Send a WooCommerce-templated HTML email.
	 *
	 * @param string $to      Recipient.
	 * @param string $subject Subject line.
	 * @param string $body    HTML body.
	 * @return bool
	 */
	private static function send( $to, $subject, $body ) {
		$mailer = self::mailer();

		if ( ! $mailer || ! is_email( $to ) ) {
			return false;
		}

		$content = $mailer->wrap_message( $subject, $body );

		return (bool) $mailer->send( $to, $subject, $content, "Content-Type: text/html\r\n", array() );
	}

	/**
	 * Approval URL for a token.
	 *
	 * @param string $token Approval token.
	 * @return string
	 */
	public static function get_approval_url( $token ) {
		$base = B2B_Quote_Ajax::get_quote_page_url();

		if ( '' === $base ) {
			$base = home_url( '/' );
		}

		return add_query_arg( 'b2b_token', rawurlencode( (string) $token ), $base );
	}

	/**
	 * Render the line items of a quote as an HTML table.
	 *
	 * @param array $items Normalised items from B2B_Quote_DB::decode_items().
	 * @return string
	 */
	private static function render_items_table( array $items ) {
		if ( ! $items ) {
			return '<p>' . esc_html__( 'No products were included in this request.', 'woo-b2b-quote' ) . '</p>';
		}

		$rows  = '';
		$total = 0.0;
		$has_negotiated_price = false;

		foreach ( $items as $item ) {
			$product = wc_get_product( $item['product_id'] );
			$name    = $product instanceof WC_Product
				? $product->get_name()
				/* translators: %d: product ID. */
				: sprintf( __( 'Product #%d (no longer available)', 'woo-b2b-quote' ), $item['product_id'] );

			$sku = $product instanceof WC_Product ? $product->get_sku() : '';

			if ( null !== $item['negotiated_price'] ) {
				$has_negotiated_price = true;
				$line_total           = $item['negotiated_price'] * $item['quantity'];
				$total               += $line_total;
				$price_cell           = wp_kses_post( wc_price( $item['negotiated_price'] ) );
				$total_cell           = wp_kses_post( wc_price( $line_total ) );
			} else {
				$price_cell = esc_html__( 'To be quoted', 'woo-b2b-quote' );
				$total_cell = '&mdash;';
			}

			$rows .= sprintf(
				'<tr><td style="padding:8px;border-bottom:1px solid #e5e5e5;">%1$s%2$s</td><td style="padding:8px;border-bottom:1px solid #e5e5e5;text-align:center;">%3$s</td><td style="padding:8px;border-bottom:1px solid #e5e5e5;text-align:right;">%4$s</td><td style="padding:8px;border-bottom:1px solid #e5e5e5;text-align:right;">%5$s</td></tr>',
				esc_html( $name ),
				$sku ? '<br><small>' . esc_html( sprintf( /* translators: %s: product SKU. */ __( 'SKU: %s', 'woo-b2b-quote' ), $sku ) ) . '</small>' : '',
				esc_html( number_format_i18n( $item['quantity'] ) ),
				$price_cell,
				$total_cell
			);
		}

		if ( $has_negotiated_price ) {
			$rows .= sprintf(
				'<tr><td colspan="3" style="padding:8px;text-align:right;"><strong>%1$s</strong></td><td style="padding:8px;text-align:right;"><strong>%2$s</strong></td></tr>',
				esc_html__( 'Quoted total', 'woo-b2b-quote' ),
				wp_kses_post( wc_price( $total ) )
			);
		}

		return sprintf(
			'<table cellspacing="0" cellpadding="0" style="width:100%%;border-collapse:collapse;"><thead><tr><th style="padding:8px;text-align:left;border-bottom:2px solid #333;">%1$s</th><th style="padding:8px;text-align:center;border-bottom:2px solid #333;">%2$s</th><th style="padding:8px;text-align:right;border-bottom:2px solid #333;">%3$s</th><th style="padding:8px;text-align:right;border-bottom:2px solid #333;">%4$s</th></tr></thead><tbody>%5$s</tbody></table>',
			esc_html__( 'Product', 'woo-b2b-quote' ),
			esc_html__( 'Qty', 'woo-b2b-quote' ),
			esc_html__( 'Unit price', 'woo-b2b-quote' ),
			esc_html__( 'Line total', 'woo-b2b-quote' ),
			$rows
		);
	}

	/**
	 * Render the customer detail block.
	 *
	 * @param object $quote Quote row.
	 * @return string
	 */
	private static function render_customer_details( $quote ) {
		$rows = array(
			__( 'Name', 'woo-b2b-quote' )    => $quote->client_name,
			__( 'Email', 'woo-b2b-quote' )   => $quote->client_email,
			__( 'Company', 'woo-b2b-quote' ) => $quote->client_company,
		);

		$html = '<ul style="margin:0 0 16px;padding-left:18px;">';

		foreach ( $rows as $label => $value ) {
			if ( '' === (string) $value ) {
				continue;
			}

			$html .= '<li><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value ) . '</li>';
		}

		$html .= '</ul>';

		if ( '' !== (string) $quote->technical_reqs ) {
			$html .= '<p><strong>' . esc_html__( 'Requirements', 'woo-b2b-quote' ) . '</strong></p>';
			$html .= wpautop( esc_html( $quote->technical_reqs ) );
		}

		return $html;
	}

	/**
	 * Notify the store team that a new request arrived.
	 *
	 * @param int $quote_id Quote ID.
	 * @return bool
	 */
	public static function send_admin_notification( $quote_id ) {
		$quote = B2B_Quote_DB::get_quote( $quote_id );

		if ( ! $quote ) {
			return false;
		}

		$subject = sprintf(
			/* translators: 1: site name, 2: quote ID. */
			__( '[%1$s] New quote request #%2$d', 'woo-b2b-quote' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			(int) $quote->id
		);

		$crm_url = add_query_arg(
			array(
				'page'     => 'b2b-quotes-crm',
				'quote_id' => (int) $quote->id,
			),
			admin_url( 'admin.php' )
		);

		$body  = '<p>' . esc_html__( 'A new quote request has been submitted.', 'woo-b2b-quote' ) . '</p>';
		$body .= self::render_customer_details( $quote );
		$body .= self::render_items_table( B2B_Quote_DB::decode_items( $quote ) );
		$body .= '<p style="margin-top:20px;"><a href="' . esc_url( $crm_url ) . '">' . esc_html__( 'Open this request in the quote CRM', 'woo-b2b-quote' ) . '</a></p>';

		return self::send( self::get_admin_recipient(), $subject, $body );
	}

	/**
	 * Confirm receipt to the customer.
	 *
	 * @param int $quote_id Quote ID.
	 * @return bool
	 */
	public static function send_customer_receipt( $quote_id ) {
		$quote = B2B_Quote_DB::get_quote( $quote_id );

		if ( ! $quote || ! is_email( $quote->client_email ) ) {
			return false;
		}

		$subject = sprintf(
			/* translators: 1: site name, 2: quote ID. */
			__( '[%1$s] We received your quote request #%2$d', 'woo-b2b-quote' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			(int) $quote->id
		);

		$body  = '<p>' . sprintf(
			/* translators: %s: customer name. */
			esc_html__( 'Hi %s,', 'woo-b2b-quote' ),
			esc_html( $quote->client_name )
		) . '</p>';
		$body .= '<p>' . esc_html__( 'Thank you for your interest. We have received the request below and a member of our team will reply with pricing shortly.', 'woo-b2b-quote' ) . '</p>';
		$body .= self::render_items_table( B2B_Quote_DB::decode_items( $quote ) );

		if ( '' !== (string) $quote->technical_reqs ) {
			$body .= '<p><strong>' . esc_html__( 'Your notes', 'woo-b2b-quote' ) . '</strong></p>';
			$body .= wpautop( esc_html( $quote->technical_reqs ) );
		}

		return self::send( $quote->client_email, $subject, $body );
	}

	/**
	 * Send the negotiated counter-offer with a one-click approval link.
	 *
	 * @param int $quote_id Quote ID.
	 * @return bool
	 */
	public static function send_client_counter_offer( $quote_id ) {
		$quote = B2B_Quote_DB::get_quote( $quote_id );

		if ( ! $quote || ! is_email( $quote->client_email ) || '' === (string) $quote->approval_token ) {
			return false;
		}

		$subject = sprintf(
			/* translators: 1: site name, 2: quote ID. */
			__( '[%1$s] Your quote #%2$d is ready', 'woo-b2b-quote' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			(int) $quote->id
		);

		$approval_url = self::get_approval_url( $quote->approval_token );

		$body  = '<p>' . sprintf(
			/* translators: %s: customer name. */
			esc_html__( 'Hi %s,', 'woo-b2b-quote' ),
			esc_html( $quote->client_name )
		) . '</p>';
		$body .= '<p>' . esc_html__( 'We have prepared the following pricing for you.', 'woo-b2b-quote' ) . '</p>';
		$body .= self::render_items_table( B2B_Quote_DB::decode_items( $quote ) );

		if ( '' !== (string) $quote->negotiation_notes ) {
			$body .= '<p><strong>' . esc_html__( 'Notes from our team', 'woo-b2b-quote' ) . '</strong></p>';
			$body .= wpautop( esc_html( $quote->negotiation_notes ) );
		}

		$body .= '<p style="margin:24px 0;"><a href="' . esc_url( $approval_url ) . '" style="background:#007cba;color:#fff;padding:12px 22px;text-decoration:none;border-radius:4px;display:inline-block;">' . esc_html__( 'Accept quote and check out', 'woo-b2b-quote' ) . '</a></p>';
		$body .= '<p><small>' . esc_html__( 'This link can only be used once and loads the agreed prices straight into your basket.', 'woo-b2b-quote' ) . '</small></p>';

		return self::send( $quote->client_email, $subject, $body );
	}
}
