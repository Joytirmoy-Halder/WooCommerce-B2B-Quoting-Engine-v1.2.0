<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class B2B_Quote_Frontend {

	public function __construct() {
		// Hook before the add to cart form starts so we catch Elementor and Quick Views
		add_action( 'woocommerce_before_add_to_cart_form', array( $this, 'hijack_add_to_cart_hooks' ) );
		
		// Loop buttons (archive/shop page) override
		add_filter( 'woocommerce_loop_add_to_cart_link', array( $this, 'modify_loop_add_to_cart_button' ), 10, 3 );
		
		// Render Floating Quote Cart Badge globally
		add_action( 'wp_footer', array( $this, 'render_floating_cart_widget' ) );
		
		// Enqueue frontend styles
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
	}

	public function enqueue_frontend_scripts() {
		wp_enqueue_style( 'b2b-quote-frontend', B2B_QUOTE_PLUGIN_URL . 'assets/css/b2b-quote-frontend.css', array(), B2B_QUOTE_VERSION );
	}

	public function render_floating_cart_widget() {
		if ( is_admin() || is_checkout() || is_cart() ) return;
		
		// ALWAYS render visually hidden, JS will control visibility by ajax fetch to bypass hostinger cache
		$page_id = get_option( 'b2b_quote_page_id' );
		$cart_url = $page_id ? get_permalink( $page_id ) : '#';

		echo '<a href="' . esc_url( $cart_url ) . '" id="b2b-floating-cart" style="display: none; align-items: center; justify-content: center; position: fixed; bottom: 30px; right: 30px; background-color: var(--b2b-quote-primary, #007cba); color: #fff; padding: 12px 20px; border-radius: 50px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); z-index: 99999; text-decoration: none; font-weight: bold; transition: all 0.3s ease;">';
		echo '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16" style="margin-right:8px;"><path d="M0 2.5A.5.5 0 0 1 .5 2H2a.5.5 0 0 1 .485.379L2.89 4H14.5a.5.5 0 0 1 .485.621l-1.5 6A.5.5 0 0 1 13 11H4a.5.5 0 0 1-.485-.379L1.61 3H.5a.5.5 0 0 1-.5-.5zM3.14 5l1.25 5h8.22l1.25-5H3.14zM5 13a1 1 0 1 0 0 2 1 1 0 0 0 0-2zm-2 1a2 2 0 1 1 4 0 2 2 0 0 1-4 0zm9-1a1 1 0 1 0 0 2 1 1 0 0 0 0-2zm-2 1a2 2 0 1 1 4 0 2 2 0 0 1-4 0z"/></svg>';
		echo 'Quote Request Cart <span class="b2b-cart-count" style="background:#fff; color:var(--b2b-quote-primary, #007cba); margin-left:10px; border-radius:50%; width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; font-size:12px;">0</span>';
		echo '</a>';
	}

	/**
	 * Determine if a product is eligible for quoting.
	 * Stub for Phase 4 hierarchical logic.
	 */
	private function is_product_quoteable( $product ) {
		if ( get_option( 'b2b_quote_master_switch' ) === 'yes' ) {
			return true;
		}

		$categories = get_option( 'b2b_quote_categories', array() );
		if ( ! empty( $categories ) && ! is_array( $categories ) ) {
			$categories = array_map( 'trim', explode( ',', (string) $categories ) );
		}
		if ( ! empty( $categories ) && is_array( $categories ) ) {
			$product_cats = wc_get_product_term_ids( $product->get_id(), 'product_cat' );
			if ( count( array_intersect( $categories, $product_cats ) ) > 0 ) {
				return true;
			}
		}

		$products = get_option( 'b2b_quote_products', array() );
		if ( ! empty( $products ) && ! is_array( $products ) ) {
			$products = array_map( 'trim', explode( ',', (string) $products ) );
		}
		if ( ! empty( $products ) && is_array( $products ) && in_array( (string) $product->get_id(), $products ) ) {
			return true;
		}
		
		return false; 
	}

	public function hijack_add_to_cart_hooks() {
		global $product;
		
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return;
		}

		if ( $this->is_product_quoteable( $product ) ) {
			// Inject our button right next to the native add to cart button and hide the native ones.
			add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'render_quote_button_inside_form' ) );
		}
	}

	public function render_quote_button_inside_form() {
		global $product;
		
		$btn_text = get_option( 'b2b_quote_btn_text', 'Add to Quote Request' );
		
		// Output the Add to Quote UI
		echo '<button type="button" class="button alt b2b-add-to-quote" data-product_id="' . esc_attr( $product->get_id() ) . '" style="margin-left: 10px;">';
		echo esc_html( $btn_text );
		echo '</button>';
		
		// Output Social Ordering Block
		$this->render_social_ordering_block( $product );
		
		// Dynamically hide native buttons (Cart, Buy Now, Klarna, etc)
		echo '<style>
			form.cart .single_add_to_cart_button, 
			form.cart .wd-buy-now-btn,
			form.cart .klarna-express-button,
			form.cart .stripe-button-el,
			.klarna-pay-button,
			#klarna-checkout-button { 
				display: none !important; 
			}
			/* Elementor Hyper-Specificity Nuke */
			html body .elementor-widget-container form.cart div.quantity.quantity.quantity,
			html body form.cart div.quantity.quantity.quantity,
			.summary .quantity.quantity.quantity,
			.wd-single-add-cart .quantity.quantity.quantity,
			.wd-entry-summary .quantity.quantity.quantity,
			.qty-wrapper { 
				display: none !important;
				opacity: 0 !important;
				visibility: hidden !important;
				height: 0 !important;
				overflow: hidden !important;
			}
			.b2b-add-to-quote { width: 100%; margin-left: 0 !important; margin-top: 10px; }
		</style>';
	}
	
	public function modify_loop_add_to_cart_button( $link, $product, $args ) {
		if ( $this->is_product_quoteable( $product ) ) {
			$class = isset( $args['class'] ) ? esc_attr( $args['class'] ) : 'button';
			$btn_text = get_option( 'b2b_quote_btn_text', 'Add to Quote Request' );
			
			// Replace "Add to Cart" with custom "Add to Quote Request" HTML
			$link = sprintf( 
				'<a href="#" data-product_id="%s" class="%s b2b-add-to-quote">%s</a>',
				esc_attr( $product->get_id() ),
				esc_attr( $class ),
				esc_html( $btn_text )
			);
			
			// We append the social buttons below in loop contexts too if they fit, but usually loops don't have space.
			// Instead of direct echo, we build it.
			$social_html = $this->get_social_ordering_html( $product );
			$link .= $social_html;
		}
		return $link;
	}
	
	private function render_social_ordering_block( $product ) {
		echo $this->get_social_ordering_html( $product );
	}
	
	private function get_social_ordering_html( $product ) {
		$wa = get_option( 'b2b_quote_social_whatsapp', '' );
		$ms = get_option( 'b2b_quote_social_messenger', '' );
		$tg = get_option( 'b2b_quote_social_telegram', '' );
		$vi = get_option( 'b2b_quote_social_viber', '' );
		$sk = get_option( 'b2b_quote_social_skype', '' );
		$ln = get_option( 'b2b_quote_social_line', '' );
		
		if ( empty($wa) && empty($ms) && empty($tg) && empty($vi) && empty($sk) && empty($ln) ) {
			return '';
		}
		
		$product_safe_title = esc_attr( $product->get_name() );
		$product_link = esc_url( $product->get_permalink() );
		
		ob_start();
		?>
		<div class="b2b-social-order-wrap">
			<?php if ( ! empty($wa) ) : ?>
				<button type="button" class="b2b-social-btn trigger-social-composer" data-platform="whatsapp" data-target="<?php echo esc_attr($wa); ?>" data-pname="<?php echo $product_safe_title; ?>" data-plink="<?php echo $product_link; ?>">
					<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M13.601 2.326A7.85 7.85 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.9 7.9 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.9 7.9 0 0 0 13.6 2.326zM7.994 14.521a6.6 6.6 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.56 6.56 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592m3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.73.73 0 0 0-.529.247c-.182.198-.691.677-.691 1.654s.71 1.916.81 2.049c.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232"/></svg> WhatsApp
				</button>
			<?php endif; ?>
			<?php if ( ! empty($ms) ) : ?>
				<button type="button" class="b2b-social-btn trigger-social-composer" data-platform="messenger" data-target="<?php echo esc_attr($ms); ?>" data-pname="<?php echo $product_safe_title; ?>" data-plink="<?php echo $product_link; ?>">
					<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M8 0a8 8 0 1 0 5.434 13.88l2.257.6A1 1 0 0 0 16 13.52l-.634-2.112A8 8 0 0 0 8 0zm-1.638 10.378-2.316-2.476a.4.4 0 0 1 .286-.67h1.493c.123 0 .237.054.316.148l1.637 1.95 2.15-2.028a.4.4 0 0 1 .58.536l-2.435 2.6a.4.4 0 0 1-.58-.006Z"/></svg> Messenger
				</button>
			<?php endif; ?>
			<?php if ( ! empty($tg) ) : ?>
				<button type="button" class="b2b-social-btn trigger-social-composer" data-platform="telegram" data-target="<?php echo esc_attr($tg); ?>" data-pname="<?php echo $product_safe_title; ?>" data-plink="<?php echo $product_link; ?>">
					<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M8.287 5.906c-.778.324-2.334.994-4.666 2.01-.378.15-.577.298-.595.442-.03.243.275.339.69.47l.175.055c.408.133.958.288 1.243.294.26.006.549-.1.868-.32 2.179-1.471 3.304-2.214 3.374-2.23.05-.012.12-.026.166.016.047.041.042.12.037.141-.03.129-1.227 1.241-1.846 1.817-.193.18-.33.307-.358.336a8.15 8.15 0 0 1-.188.186c-.38.366-.664.64.015 1.088.327.216.589.393.85.571.284.194.568.387.936.629.093.06.183.125.27.187.331.236.63.448.997.414.214-.02.435-.22.547-.82.265-1.417.786-4.486.906-5.751a1.4 1.4 0 0 0-.013-.315.34.34 0 0 0-.114-.217.53.53 0 0 0-.31-.093c-.3.005-.763.166-2.984 1.09z"/></svg> Telegram
				</button>
			<?php endif; ?>
			<?php if ( ! empty($vi) ) : ?>
				<button type="button" class="b2b-social-btn trigger-social-composer" data-platform="viber" data-target="<?php echo esc_attr($vi); ?>" data-pname="<?php echo $product_safe_title; ?>" data-plink="<?php echo $product_link; ?>">
					<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M12.923 10.975c-.157-.148-.372-.158-.58-.027l-1.396.883a.47.47 0 0 1-.502-.008L7.151 9.407a.47.47 0 0 1 0-.785l1.396-1.077c.189-.145.24-.396.113-.594L6.46 3.65c-.104-.162-.303-.238-.485-.183-.342.102-.924.32-1.394.614C4.161 4.35 3.33 6.007 3 7.842c-.22 1.226.042 2.7.595 4.095.498 1.258 1.543 2.502 3.129 3.09 1.15.428 2.378.432 3.393-.11 1.01-.54 1.583-1.42 1.76-2.024.116-.39.06-.576-.02-.656z"/></svg> Viber
				</button>
			<?php endif; ?>
			<?php if ( ! empty($sk) ) : ?>
				<button type="button" class="b2b-social-btn trigger-social-composer" data-platform="skype" data-target="<?php echo esc_attr($sk); ?>" data-pname="<?php echo $product_safe_title; ?>" data-plink="<?php echo $product_link; ?>">
					<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M4.671 16c5.298 0 8.423-3.084 8.423-7.79 0-.154-.01-.302-.023-.448A4.7 4.7 0 0 0 16 4.671 4.67 4.67 0 0 0 11.329 0c-.575 0-1.12.104-1.616.29A8.1 8.1 0 0 0 8 0C2.702 0 0 3.13 0 7.84c0 .178.016.353.036.524A4.7 4.7 0 0 0 0 11.329 4.67 4.67 0 0 0 4.671 16zM7.228 5.679c0-.441.408-.857 1.168-.857.755 0 1.29.349 1.29 1.004 0 .524-.488.756-1.077.94-1.29.39-2.228.601-2.228 1.762 0 .972.825 1.776 2.215 1.776 1.16 0 1.954-.42 2.238-1.096l-1.01-.617c-.122.373-.62.637-1.2.637-.68 0-1.218-.32-1.218-.946 0-.584.53-.787 1.12-.971 1.266-.381 2.18-.636 2.18-1.745 0-.916-.763-1.758-2.247-1.758-1.42 0-2.148.683-2.321 1.294z"/></svg> Skype
				</button>
			<?php endif; ?>
			<?php if ( ! empty($ln) ) : ?>
				<button type="button" class="b2b-social-btn trigger-social-composer" data-platform="line" data-target="<?php echo esc_attr($ln); ?>" data-pname="<?php echo $product_safe_title; ?>" data-plink="<?php echo $product_link; ?>">
					<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M8 0C3.58 0 0 2.868 0 6.4c0 3.167 2.656 5.82 6.138 6.326.257.062.61.189.699.444.08.232.052.593.025.828-.013.111-.166.862-.206 1.071-.05.253-.227 1.089.96.586 1.187-.504 6.395-3.766 8.385-6.855C15.65 8.163 16 7.318 16 6.4 16 2.868 12.42 0 8 0zm-2.028 8.64h-2.19c-.215 0-.388-.175-.388-.39 0-.214.173-.39.388-.39h1.8v-1.92h-1.8c-.215 0-.388-.175-.388-.39 0-.214.173-.39.388-.39h1.8v-1.92h-1.8c-.215 0-.388-.174-.388-.389 0-.215.173-.39.388-.39h2.19c.215 0 .388.175.388.39v4.54c0 .215-.173.39-.388.39zM8.328 8.25c0 .215-.173.39-.388.39s-.388-.175-.388-.39v-4.54c0-.215.173-.39.388-.39s.388.175.388.39v4.54zm3.018.39h-.002a.39.39 0 0 1-.384-.316l-.756-3.411H9.86v3.337c0 .215-.173.39-.388.39s-.388-.175-.388-.39v-4.54c0-.215.173-.39.388-.39s.388.175.388.39l.666 3h.004l.756-3.32a.4.4 0 0 1 .386-.31h.002c.215 0 .388.175.388.39v4.54c0 .215-.173.39-.388.39zm3.89-2.31h-1.8v1.92h2.19c.215 0 .388.175.388.39 0 .214-.173.39-.388.39h-2.578c-.215 0-.388-.175-.388-.39v-4.54c0-.215.173-.39.388-.39h2.19c.215 0 .388.175.388.39 0 .215-.173.39-.388.39h-1.8v1.92h1.8c.215 0 .388.175.388.39 0 .215-.173.39-.388.39z"/></svg> LINE
				</button>
			<?php endif; ?>
			
			<div class="b2b-social-composer">
				<h4>💬 Compose Message</h4>
				<textarea placeholder="Please specify requested quantities and any other details here..."></textarea>
				<button type="button" class="b2b-social-confirm-btn">Confirm Send</button>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
