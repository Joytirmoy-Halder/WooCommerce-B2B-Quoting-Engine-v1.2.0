<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class B2B_Quote_Shortcode {

	public function __construct() {
		add_shortcode( 'b2b_quote_cart', array( $this, 'render_shortcode' ) );
		
		// Enqueue scripts specifically for the shortcode page or globally if needed
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		
		// Attempt to block page caching plugins on the quote cart page
		add_action( 'template_redirect', array( $this, 'prevent_caching' ) );
	}

	public function prevent_caching() {
		$page_id = get_option( 'b2b_quote_page_id' );
		if ( $page_id && is_page( $page_id ) ) {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
			nocache_headers();
		}
	}

	public function enqueue_scripts() {
		wp_enqueue_script( 'b2b-quote-frontend-js', B2B_QUOTE_PLUGIN_URL . 'assets/js/b2b-quote-frontend.js', array( 'jquery' ), time(), true );
		wp_localize_script( 'b2b-quote-frontend-js', 'b2b_quote_params', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'b2b-quote-nonce' )
		) );
	}

	public function render_shortcode( $atts ) {
		ob_start();
		
		$quote_cart = class_exists('B2B_Quote_Ajax') ? B2B_Quote_Ajax::get_quote_session() : array();

		if ( empty( $quote_cart ) ) {
			echo '<div class="woocommerce-info">Your quote list is currently empty.</div>';
			return ob_get_clean();
		}
		?>
		<div class="b2b-quote-shortcode-wrapper">
			<table class="shop_table shop_table_responsive cart b2b-quote-cart-table">
				<thead>
					<tr>
						<th class="product-remove">&nbsp;</th>
						<th class="product-thumbnail">&nbsp;</th>
						<th class="product-name">Product</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $quote_cart as $item ) : 
						$product = wc_get_product( $item['product_id'] );
						if ( ! $product ) continue;
					?>
						<tr class="b2b-quote-cart-item" data-product_id="<?php echo esc_attr( $item['product_id'] ); ?>">
							<td class="product-remove">
								<a href="#" class="b2b-remove-item" data-product_id="<?php echo esc_attr( $item['product_id'] ); ?>" style="display:inline-block; font-size: 20px; font-weight: bold; width: 24px; height: 24px; line-height: 22px; text-align: center; color: #cc0000; border-radius: 50%; border: 2px solid #cc0000; text-decoration: none;">&times;</a>
							</td>
							<td class="product-thumbnail">
								<?php echo $product->get_image(); ?>
							</td>
							<td class="product-name" data-title="Product">
								<?php echo wp_kses_post( $product->get_name() ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<div class="b2b-quote-form-container">
				<h3>Submit Quote Request</h3>
				<form id="b2b-quote-submit-form">
					<p class="form-row form-row-first">
						<label for="b2b_client_name">Name <abbr class="required" title="required">*</abbr></label>
						<input type="text" class="input-text" name="client_name" id="b2b_client_name" required>
					</p>
					<p class="form-row form-row-last">
						<label for="b2b_client_email">Email <abbr class="required" title="required">*</abbr></label>
						<input type="email" class="input-text" name="client_email" id="b2b_client_email" required>
					</p>
					<p class="form-row form-row-wide">
						<label for="b2b_client_company">Company</label>
						<input type="text" class="input-text" name="client_company" id="b2b_client_company">
					</p>
					<p class="form-row form-row-wide">
						<label for="b2b_technical_reqs">Technical Requirements</label>
						<textarea class="input-text" name="technical_reqs" id="b2b_technical_reqs" rows="4"></textarea>
					</p>
					<p class="form-row">
						<button type="submit" class="button b2b-submit-quote-btn" name="b2b_submit_quote">Submit Request</button>
					</p>
					<div id="b2b-quote-notices"></div>
				</form>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
