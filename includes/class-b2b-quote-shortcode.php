<?php
/**
 * The [b2b_quote_cart] shortcode.
 *
 * @package WooB2BQuotingEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Quote cart shortcode.
 */
class B2B_Quote_Shortcode {

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_shortcode( 'b2b_quote_cart', array( $this, 'render_shortcode' ) );
		add_action( 'template_redirect', array( $this, 'prevent_caching' ) );
	}

	/**
	 * Keep the quote cart page out of full-page caches.
	 *
	 * The contents are per-visitor session data, so a cached copy would leak one
	 * visitor's request to the next.
	 */
	public function prevent_caching() {
		$page_id = absint( get_option( 'b2b_quote_page_id' ) );

		if ( ! $page_id || ! is_page( $page_id ) ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		nocache_headers();
	}

	/**
	 * Render the quote cart and request form.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'title' => '',
			),
			$atts,
			'b2b_quote_cart'
		);

		$quote_cart = B2B_Quote_Ajax::get_quote_session();

		ob_start();
		?>
		<div class="b2b-quote-cart-wrapper">
			<?php if ( '' !== $atts['title'] ) : ?>
				<h2 class="b2b-quote-cart-title"><?php echo esc_html( $atts['title'] ); ?></h2>
			<?php endif; ?>

			<div id="b2b-quote-notices" class="b2b-quote-notices" role="status" aria-live="polite"></div>

			<?php if ( ! $quote_cart ) : ?>
				<div class="b2b-quote-empty">
					<p><?php esc_html_e( 'Your quote request is currently empty.', 'woo-b2b-quote' ); ?></p>
					<?php
					$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '';

					if ( $shop_url ) :
						?>
						<a class="button" href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'Browse products', 'woo-b2b-quote' ); ?></a>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<?php $this->render_items_table( $quote_cart ); ?>
				<?php $this->render_request_form(); ?>
			<?php endif; ?>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Output the editable line-item table.
	 *
	 * 1.2.0 stored a quantity but never displayed it, so a bulk-quoting plugin
	 * gave the customer no way to see or change how many units they wanted.
	 *
	 * @param array $quote_cart Session items.
	 */
	private function render_items_table( array $quote_cart ) {
		?>
		<table class="shop_table b2b-quote-cart-table">
			<thead>
				<tr>
					<th class="b2b-col-image"><span class="screen-reader-text"><?php esc_html_e( 'Image', 'woo-b2b-quote' ); ?></span></th>
					<th class="b2b-col-name"><?php esc_html_e( 'Product', 'woo-b2b-quote' ); ?></th>
					<th class="b2b-col-price"><?php esc_html_e( 'List price', 'woo-b2b-quote' ); ?></th>
					<th class="b2b-col-qty"><?php esc_html_e( 'Quantity', 'woo-b2b-quote' ); ?></th>
					<th class="b2b-col-remove"><span class="screen-reader-text"><?php esc_html_e( 'Remove', 'woo-b2b-quote' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $quote_cart as $product_id => $item ) {
					$product = wc_get_product( absint( $product_id ) );

					if ( ! $product instanceof WC_Product ) {
						continue;
					}

					$quantity = max( 1, absint( isset( $item['quantity'] ) ? $item['quantity'] : 1 ) );
					$price    = $product->get_price_html();
					?>
					<tr class="b2b-quote-item" data-product_id="<?php echo esc_attr( $product->get_id() ); ?>">
						<td class="b2b-col-image">
							<a href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php echo wp_kses_post( $product->get_image( 'woocommerce_gallery_thumbnail' ) ); ?></a>
						</td>
						<td class="b2b-col-name" data-title="<?php esc_attr_e( 'Product', 'woo-b2b-quote' ); ?>">
							<a href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
							<?php if ( $product->get_sku() ) : ?>
								<span class="b2b-quote-sku"><?php echo esc_html( sprintf( /* translators: %s: product SKU. */ __( 'SKU: %s', 'woo-b2b-quote' ), $product->get_sku() ) ); ?></span>
							<?php endif; ?>
						</td>
						<td class="b2b-col-price" data-title="<?php esc_attr_e( 'List price', 'woo-b2b-quote' ); ?>">
							<?php echo $price ? wp_kses_post( $price ) : esc_html__( 'On request', 'woo-b2b-quote' ); ?>
						</td>
						<td class="b2b-col-qty" data-title="<?php esc_attr_e( 'Quantity', 'woo-b2b-quote' ); ?>">
							<label class="screen-reader-text" for="b2b-qty-<?php echo esc_attr( $product->get_id() ); ?>">
								<?php esc_html_e( 'Quantity', 'woo-b2b-quote' ); ?>
							</label>
							<input
								type="number"
								id="b2b-qty-<?php echo esc_attr( $product->get_id() ); ?>"
								class="b2b-quote-qty"
								name="b2b_quote_qty[<?php echo esc_attr( $product->get_id() ); ?>]"
								value="<?php echo esc_attr( $quantity ); ?>"
								min="1"
								step="1"
								inputmode="numeric"
							>
						</td>
						<td class="b2b-col-remove">
							<button type="button" class="b2b-remove-item" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: product name. */ __( 'Remove %s from your quote request', 'woo-b2b-quote' ), $product->get_name() ) ); ?>">&times;</button>
						</td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Output the request form.
	 */
	private function render_request_form() {
		$current_user = wp_get_current_user();
		$name         = $current_user->exists() ? $current_user->display_name : '';
		$email        = $current_user->exists() ? $current_user->user_email : '';
		?>
		<form id="b2b-quote-submit-form" class="b2b-quote-form" method="post" novalidate>
			<h3 class="b2b-quote-form-title"><?php esc_html_e( 'Request your quote', 'woo-b2b-quote' ); ?></h3>

			<p class="b2b-quote-field">
				<label for="b2b_client_name">
					<?php esc_html_e( 'Your name', 'woo-b2b-quote' ); ?> <span class="required">*</span>
				</label>
				<input type="text" id="b2b_client_name" name="client_name" value="<?php echo esc_attr( $name ); ?>" autocomplete="name" required>
			</p>

			<p class="b2b-quote-field">
				<label for="b2b_client_email">
					<?php esc_html_e( 'Email address', 'woo-b2b-quote' ); ?> <span class="required">*</span>
				</label>
				<input type="email" id="b2b_client_email" name="client_email" value="<?php echo esc_attr( $email ); ?>" autocomplete="email" required>
			</p>

			<p class="b2b-quote-field">
				<label for="b2b_client_company"><?php esc_html_e( 'Company', 'woo-b2b-quote' ); ?></label>
				<input type="text" id="b2b_client_company" name="client_company" value="" autocomplete="organization">
			</p>

			<p class="b2b-quote-field">
				<label for="b2b_technical_reqs"><?php esc_html_e( 'Requirements, delivery dates or technical notes', 'woo-b2b-quote' ); ?></label>
				<textarea id="b2b_technical_reqs" name="technical_reqs" rows="5"></textarea>
			</p>

			<p class="b2b-quote-honeypot" aria-hidden="true">
				<label for="b2b_confirm_url"><?php esc_html_e( 'Leave this field empty', 'woo-b2b-quote' ); ?></label>
				<input type="text" id="b2b_confirm_url" name="b2b_confirm_url" value="" tabindex="-1" autocomplete="off">
			</p>

			<?php do_action( 'b2b_quote_form_fields' ); ?>

			<p class="b2b-quote-submit">
				<button type="submit" class="button alt b2b-quote-submit-btn">
					<?php esc_html_e( 'Submit quote request', 'woo-b2b-quote' ); ?>
				</button>
			</p>
		</form>
		<?php
	}
}
