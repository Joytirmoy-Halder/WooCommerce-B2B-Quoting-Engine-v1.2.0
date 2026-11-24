<?php
/**
 * Front-end output: quote buttons, floating cart and direct-message ordering.
 *
 * @package WooB2BQuotingEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Storefront integration.
 */
class B2B_Quote_Frontend {

	/**
	 * Whether any social trigger was rendered this request.
	 *
	 * @var bool
	 */
	private $needs_composer = false;

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'woocommerce_before_add_to_cart_form', array( $this, 'hijack_add_to_cart_hooks' ) );
		add_filter( 'woocommerce_loop_add_to_cart_link', array( $this, 'modify_loop_add_to_cart_button' ), 10, 3 );
		add_action( 'wp_footer', array( $this, 'render_floating_cart_widget' ) );
		add_action( 'wp_footer', array( $this, 'render_social_composer' ), 20 );
	}

	/**
	 * The configured button label.
	 *
	 * @return string
	 */
	public static function get_button_text() {
		$text = get_option( 'b2b_quote_btn_text' );

		if ( ! is_string( $text ) || '' === trim( $text ) ) {
			$text = __( 'Add to Quote Request', 'woo-b2b-quote' );
		}

		return $text;
	}

	/**
	 * Enqueue the stylesheet and script.
	 *
	 * Assets are registered once here rather than in two classes, and are
	 * versioned with the plugin version. 1.2.0 passed time() as the version,
	 * which produced a unique URL on every page view and permanently defeated
	 * browser and CDN caching.
	 */
	public function enqueue_assets() {
		wp_enqueue_style(
			'b2b-quote-frontend',
			B2B_QUOTE_PLUGIN_URL . 'assets/css/b2b-quote-frontend.css',
			array(),
			B2B_QUOTE_VERSION
		);

		wp_add_inline_style( 'b2b-quote-frontend', B2B_Quote_Settings::get_frontend_inline_css() );

		wp_enqueue_script(
			'b2b-quote-frontend',
			B2B_QUOTE_PLUGIN_URL . 'assets/js/b2b-quote-frontend.js',
			array( 'jquery' ),
			B2B_QUOTE_VERSION,
			true
		);

		wp_localize_script(
			'b2b-quote-frontend',
			'b2bQuoteParams',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( B2B_Quote_Ajax::NONCE_ACTION ),
				'cartUrl'    => B2B_Quote_Ajax::get_quote_page_url(),
				'hideNative' => true,
				'hideQty'    => 'yes' === get_option( 'b2b_quote_hide_quantity', 'no' ),
				'i18n'       => array(
					'adding'       => __( 'Adding…', 'woo-b2b-quote' ),
					'added'        => __( 'Added to your quote request.', 'woo-b2b-quote' ),
					'viewRequest'  => __( 'View quote request', 'woo-b2b-quote' ),
					'genericError' => __( 'Something went wrong. Please try again.', 'woo-b2b-quote' ),
					'networkError' => __( 'We could not reach the server. Please check your connection and try again.', 'woo-b2b-quote' ),
					'submitting'   => __( 'Submitting…', 'woo-b2b-quote' ),
					'emptyMessage' => __( 'Please add the quantities or details you need before sending.', 'woo-b2b-quote' ),
					'copied'       => __( 'Your message was copied to the clipboard. Paste it into the chat window that just opened.', 'woo-b2b-quote' ),
					'copyFailed'   => __( 'Please copy your message manually, then paste it into the chat window.', 'woo-b2b-quote' ),
					'confirmEmpty' => __( 'Remove this product from your quote request?', 'woo-b2b-quote' ),
				),
			)
		);
	}

	/**
	 * On a quoteable single product, attach our button to the add-to-cart form.
	 */
	public function hijack_add_to_cart_hooks() {
		global $product;

		if ( ! $product instanceof WC_Product || ! B2B_Quote_Rules::is_product_quoteable( $product ) ) {
			return;
		}

		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'render_quote_button_inside_form' ) );
	}

	/**
	 * Render the quote button plus direct-message triggers inside the cart form.
	 */
	public function render_quote_button_inside_form() {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		printf(
			'<button type="button" class="button alt b2b-add-to-quote" data-product_id="%1$s">%2$s</button>',
			esc_attr( $product->get_id() ),
			esc_html( self::get_button_text() )
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped while building.
		echo $this->get_social_ordering_html( $product );
	}

	/**
	 * Swap the archive add-to-cart link for a quote button.
	 *
	 * The direct-message block is deliberately not rendered here. 1.2.0 appended
	 * a full composer widget to every product in the loop, which duplicated the
	 * same element IDs dozens of times on a single archive page.
	 *
	 * @param string     $link    Button HTML.
	 * @param WC_Product $product Product.
	 * @param array      $args    Button args.
	 * @return string
	 */
	public function modify_loop_add_to_cart_button( $link, $product, $args = array() ) {
		if ( ! $product instanceof WC_Product || ! B2B_Quote_Rules::is_product_quoteable( $product ) ) {
			return $link;
		}

		$classes  = isset( $args['class'] ) ? (string) $args['class'] : 'button';
		$cart_url = B2B_Quote_Ajax::get_quote_page_url();

		return sprintf(
			'<a href="%1$s" data-product_id="%2$s" class="%3$s b2b-add-to-quote">%4$s</a>',
			esc_url( $cart_url ? $cart_url : '#' ),
			esc_attr( $product->get_id() ),
			esc_attr( $classes ),
			esc_html( self::get_button_text() )
		);
	}

	/**
	 * Floating quote cart badge.
	 *
	 * Starts hidden and is revealed by JavaScript after an AJAX count, so the
	 * markup stays safe to serve from a full-page cache.
	 */
	public function render_floating_cart_widget() {
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}

		if ( 'yes' !== get_option( 'b2b_quote_show_floating_cart', 'yes' ) ) {
			return;
		}

		if ( function_exists( 'is_checkout' ) && ( is_checkout() || is_cart() ) ) {
			return;
		}

		$cart_url = B2B_Quote_Ajax::get_quote_page_url();

		if ( '' === $cart_url ) {
			return;
		}
		?>
		<a href="<?php echo esc_url( $cart_url ); ?>" id="b2b-floating-cart" class="b2b-floating-cart" hidden>
			<svg class="b2b-floating-cart__icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M0 2.5A.5.5 0 0 1 .5 2H2a.5.5 0 0 1 .485.379L2.89 4H14.5a.5.5 0 0 1 .485.621l-1.5 6A.5.5 0 0 1 13 11H4a.5.5 0 0 1-.485-.379L1.61 3H.5a.5.5 0 0 1-.5-.5zM3.14 5l1.25 5h8.22l1.25-5H3.14zM5 13a1 1 0 1 0 0 2 1 1 0 0 0 0-2zm-2 1a2 2 0 1 1 4 0 2 2 0 0 1-4 0zm9-1a1 1 0 1 0 0 2 1 1 0 0 0 0-2zm-2 1a2 2 0 1 1 4 0 2 2 0 0 1-4 0z"/></svg>
			<span class="b2b-floating-cart__label"><?php esc_html_e( 'Quote Request Cart', 'woo-b2b-quote' ); ?></span>
			<span class="b2b-cart-count">0</span>
		</a>
		<?php
	}

	/**
	 * Supported direct-message platforms.
	 *
	 * Replaces six near-identical copy-pasted blocks. Themes and add-ons can
	 * filter this to add a platform or swap an icon.
	 *
	 * @return array
	 */
	public static function get_social_platforms() {
		$platforms = array(
			'whatsapp'  => array(
				'label'  => __( 'WhatsApp', 'woo-b2b-quote' ),
				'option' => 'b2b_quote_social_whatsapp',
				'icon'   => '<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M13.601 2.326A7.85 7.85 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.9 7.9 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.9 7.9 0 0 0 13.6 2.326zM7.994 14.521a6.6 6.6 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.56 6.56 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592m3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.73.73 0 0 0-.529.247c-.182.198-.691.677-.691 1.654s.71 1.916.81 2.049c.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232"/></svg>',
			),
			'messenger' => array(
				'label'  => __( 'Messenger', 'woo-b2b-quote' ),
				'option' => 'b2b_quote_social_messenger',
				'icon'   => '<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M8 0a8 8 0 1 0 5.434 13.88l2.257.6A1 1 0 0 0 16 13.52l-.634-2.112A8 8 0 0 0 8 0zm-1.638 10.378-2.316-2.476a.4.4 0 0 1 .286-.67h1.493c.123 0 .237.054.316.148l1.637 1.95 2.15-2.028a.4.4 0 0 1 .58.536l-2.435 2.6a.4.4 0 0 1-.58-.006Z"/></svg>',
			),
			'telegram'  => array(
				'label'  => __( 'Telegram', 'woo-b2b-quote' ),
				'option' => 'b2b_quote_social_telegram',
				'icon'   => '<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M8.287 5.906c-.778.324-2.334.994-4.666 2.01-.378.15-.577.298-.595.442-.03.243.275.339.69.47l.175.055c.408.133.958.288 1.243.294.26.006.549-.1.868-.32 2.179-1.471 3.304-2.214 3.374-2.23.05-.012.12-.026.166.016.047.041.042.12.037.141-.03.129-1.227 1.241-1.846 1.817-.193.18-.33.307-.358.336a8.15 8.15 0 0 1-.188.186c-.38.366-.664.64.015 1.088.327.216.589.393.85.571.284.194.568.387.936.629.093.06.183.125.27.187.331.236.63.448.997.414.214-.02.435-.22.547-.82.265-1.417.786-4.486.906-5.751a1.4 1.4 0 0 0-.013-.315.34.34 0 0 0-.114-.217.53.53 0 0 0-.31-.093c-.3.005-.763.166-2.984 1.09z"/></svg>',
			),
			'viber'     => array(
				'label'  => __( 'Viber', 'woo-b2b-quote' ),
				'option' => 'b2b_quote_social_viber',
				'icon'   => '<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M12.923 10.975c-.157-.148-.372-.158-.58-.027l-1.396.883a.47.47 0 0 1-.502-.008L7.151 9.407a.47.47 0 0 1 0-.785l1.396-1.077c.189-.145.24-.396.113-.594L6.46 3.65c-.104-.162-.303-.238-.485-.183-.342.102-.924.32-1.394.614C4.161 4.35 3.33 6.007 3 7.842c-.22 1.226.042 2.7.595 4.095.498 1.258 1.543 2.502 3.129 3.09 1.15.428 2.378.432 3.393-.11 1.01-.54 1.583-1.42 1.76-2.024.116-.39.06-.576-.02-.656z"/></svg>',
			),
			'skype'     => array(
				'label'  => __( 'Skype', 'woo-b2b-quote' ),
				'option' => 'b2b_quote_social_skype',
				'icon'   => '<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M4.671 16c5.298 0 8.423-3.084 8.423-7.79 0-.154-.01-.302-.023-.448A4.7 4.7 0 0 0 16 4.671 4.67 4.67 0 0 0 11.329 0c-.575 0-1.12.104-1.616.29A8.1 8.1 0 0 0 8 0C2.702 0 0 3.13 0 7.84c0 .178.016.353.036.524A4.7 4.7 0 0 0 0 11.329 4.67 4.67 0 0 0 4.671 16zM7.228 5.679c0-.441.408-.857 1.168-.857.755 0 1.29.349 1.29 1.004 0 .524-.488.756-1.077.94-1.29.39-2.228.601-2.228 1.762 0 .972.825 1.776 2.215 1.776 1.16 0 1.954-.42 2.238-1.096l-1.01-.617c-.122.373-.62.637-1.2.637-.68 0-1.218-.32-1.218-.946 0-.584.53-.787 1.12-.971 1.266-.381 2.18-.636 2.18-1.745 0-.916-.763-1.758-2.247-1.758-1.42 0-2.148.683-2.321 1.294z"/></svg>',
			),
			'line'      => array(
				'label'  => __( 'LINE', 'woo-b2b-quote' ),
				'option' => 'b2b_quote_social_line',
				'icon'   => '<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M8 0C3.58 0 0 2.868 0 6.4c0 3.167 2.656 5.82 6.138 6.326.257.062.61.189.699.444.08.232.052.593.025.828-.013.111-.166.862-.206 1.071-.05.253-.227 1.089.96.586 1.187-.504 6.395-3.766 8.385-6.855C15.65 8.163 16 7.318 16 6.4 16 2.868 12.42 0 8 0zm-2.028 8.64h-2.19c-.215 0-.388-.175-.388-.39 0-.214.173-.39.388-.39h1.8v-1.92h-1.8c-.215 0-.388-.175-.388-.39 0-.214.173-.39.388-.39h1.8v-1.92h-1.8c-.215 0-.388-.174-.388-.389 0-.215.173-.39.388-.39h2.19c.215 0 .388.175.388.39v4.54c0 .215-.173.39-.388.39zM8.328 8.25c0 .215-.173.39-.388.39s-.388-.175-.388-.39v-4.54c0-.215.173-.39.388-.39s.388.175.388.39v4.54zm3.018.39h-.002a.39.39 0 0 1-.384-.316l-.756-3.411H9.86v3.337c0 .215-.173.39-.388.39s-.388-.175-.388-.39v-4.54c0-.215.173-.39.388-.39s.388.175.388.39l.666 3h.004l.756-3.32a.4.4 0 0 1 .386-.31h.002c.215 0 .388.175.388.39v4.54c0 .215-.173.39-.388.39zm3.89-2.31h-1.8v1.92h2.19c.215 0 .388.175.388.39 0 .214-.173.39-.388.39h-2.578c-.215 0-.388-.175-.388-.39v-4.54c0-.215.173-.39.388-.39h2.19c.215 0 .388.175.388.39 0 .215-.173.39-.388.39h-1.8v1.92h1.8c.215 0 .388.175.388.39 0 .215-.173.39-.388.39z"/></svg>',
			),
		);

		return (array) apply_filters( 'b2b_quote_social_platforms', $platforms );
	}

	/**
	 * Platforms that have a handle or number configured.
	 *
	 * @return array
	 */
	public static function get_enabled_social_platforms() {
		$enabled = array();

		foreach ( self::get_social_platforms() as $key => $platform ) {
			if ( empty( $platform['option'] ) ) {
				continue;
			}

			$value = trim( (string) get_option( $platform['option'], '' ) );

			if ( '' === $value ) {
				continue;
			}

			$platform['value'] = $value;
			$enabled[ $key ]   = $platform;
		}

		return $enabled;
	}

	/**
	 * Build the direct-message trigger row for a product.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	private function get_social_ordering_html( $product ) {
		$platforms = self::get_enabled_social_platforms();

		if ( ! $platforms ) {
			return '';
		}

		$this->needs_composer = true;

		$html = '<div class="b2b-social-order-wrap"><span class="b2b-social-order-label">' . esc_html__( 'Or order directly via:', 'woo-b2b-quote' ) . '</span>';

		foreach ( $platforms as $key => $platform ) {
			$html .= sprintf(
				'<button type="button" class="b2b-social-btn b2b-social-btn--%1$s trigger-social-composer" data-platform="%1$s" data-target="%2$s" data-pname="%3$s" data-plink="%4$s">%5$s<span>%6$s</span></button>',
				esc_attr( $key ),
				esc_attr( $platform['value'] ),
				esc_attr( $product->get_name() ),
				esc_url( $product->get_permalink() ),
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static plugin-authored SVG markup.
				isset( $platform['icon'] ) ? $platform['icon'] : '',
				esc_html( $platform['label'] )
			);
		}

		return $html . '</div>';
	}

	/**
	 * One shared composer dialog for the whole page.
	 */
	public function render_social_composer() {
		if ( ! $this->needs_composer ) {
			return;
		}
		?>
		<div class="b2b-social-composer" id="b2b-social-composer" hidden>
			<div class="b2b-social-composer__backdrop" data-b2b-close></div>
			<div class="b2b-social-composer__dialog" role="dialog" aria-modal="true" aria-labelledby="b2b-social-composer-title">
				<button type="button" class="b2b-social-composer__close" data-b2b-close aria-label="<?php esc_attr_e( 'Close', 'woo-b2b-quote' ); ?>">&times;</button>
				<h4 id="b2b-social-composer-title"><?php esc_html_e( 'Compose your message', 'woo-b2b-quote' ); ?></h4>
				<p class="b2b-social-composer__product"></p>
				<label class="screen-reader-text" for="b2b-social-composer-message"><?php esc_html_e( 'Message', 'woo-b2b-quote' ); ?></label>
				<textarea id="b2b-social-composer-message" rows="5" placeholder="<?php esc_attr_e( 'Please specify requested quantities and any other details here…', 'woo-b2b-quote' ); ?>"></textarea>
				<p class="b2b-social-composer__hint" role="status" aria-live="polite"></p>
				<button type="button" class="button alt b2b-social-confirm-btn"><?php esc_html_e( 'Open chat with this message', 'woo-b2b-quote' ); ?></button>
			</div>
		</div>
		<?php
	}
}
