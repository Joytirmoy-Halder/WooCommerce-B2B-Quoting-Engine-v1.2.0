<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class B2B_Quote_Emails {

	public static function send_admin_notification( $quote_id, $client_name, $client_email ) {
		// Load WooCommerce mailer
		$mailer = WC()->mailer();

		$admin_email = get_option( 'admin_email' );
		$subject = sprintf( __( 'New B2B Quote Request #%d', 'woo-b2b-quote' ), $quote_id );
		
		// Basic HTML email
		ob_start();
		echo "<p>A new B2B quote request has been received from {$client_name} ({$client_email}).</p>";
		echo "<p>Please log in to your WordPress dashboard to view the details and negotiate pricing.</p>";
		$message = ob_get_clean();

		// Wrap message in WooCommerce email template
		$content = $mailer->wrap_message( $subject, $message );
		
		$headers = array(
			'Content-Type: text/html',
		);

		$mailer->send( $admin_email, $subject, $content, $headers, array() );
	}

	public static function send_client_counter_offer( $quote, $approval_link ) {
		$mailer = WC()->mailer();
		$subject = sprintf( __( 'Counter-Offer for Quote Request #%d', 'woo-b2b-quote' ), $quote->id );

		ob_start();
		echo "<p>Hi {$quote->client_name},</p>";
		echo "<p>We have reviewed your quote request and have a counter-offer for you.</p>";
		if ( ! empty( $quote->negotiation_notes ) ) {
			echo "<blockquote>" . nl2br( esc_html( $quote->negotiation_notes ) ) . "</blockquote>";
		}
		echo "<p>Please click the link below to review the prices and proceed to checkout if acceptable:</p>";
		echo "<p><a href='" . esc_url( $approval_link ) . "' style='display:inline-block;background:#333;color:#fff;padding:10px 15px;text-decoration:none;'>Review and Approve Offer</a></p>";
		
		$message = ob_get_clean();
		$content = $mailer->wrap_message( $subject, $message );
		$headers = array('Content-Type: text/html');

		$mailer->send( $quote->client_email, $subject, $content, $headers, array() );
	}
}
