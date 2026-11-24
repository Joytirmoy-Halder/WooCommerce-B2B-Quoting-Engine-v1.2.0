<?php
/**
 * WooCommerce settings tab.
 *
 * Uses native WooCommerce settings sections and the documented
 * WC_Admin_Settings API. 1.2.0 rendered every field on one screen and then used
 * JavaScript to hide each `h2` inside `#mainform` and rebuild fake tabs, which
 * broke as soon as another plugin added a heading and relied on a hardcoded
 * tab index.
 *
 * @package WooB2BQuotingEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings screen.
 */
class B2B_Quote_Settings {

	const TAB_ID = 'b2b_quoting';

	const DEFAULT_PRIMARY         = '#007cba';
	const DEFAULT_SECONDARY       = '#005a8c';
	const DEFAULT_TEXT            = '#ffffff';
	const DEFAULT_PADDING         = '10px 20px';
	const DEFAULT_SOCIAL_BG       = '#f0f0f0';
	const DEFAULT_SOCIAL_BG_HOVER = '#e0e0e0';
	const DEFAULT_SOCIAL_TEXT     = '#333333';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
		add_action( 'woocommerce_sections_' . self::TAB_ID, array( $this, 'output_sections' ) );
		add_action( 'woocommerce_settings_' . self::TAB_ID, array( $this, 'output_settings' ) );
		add_action( 'woocommerce_settings_save_' . self::TAB_ID, array( $this, 'save_settings' ) );
		add_action( 'woocommerce_admin_field_b2b_quote_preview', array( $this, 'render_preview_field' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Add the tab.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public function add_settings_tab( $tabs ) {
		$tabs[ self::TAB_ID ] = __( 'B2B Quoting', 'woo-b2b-quote' );

		return $tabs;
	}

	/**
	 * Sections inside the tab.
	 *
	 * @return array
	 */
	public function get_sections() {
		return array(
			''          => __( 'General', 'woo-b2b-quote' ),
			'design'    => __( 'Button design', 'woo-b2b-quote' ),
			'messaging' => __( 'Direct message ordering', 'woo-b2b-quote' ),
		);
	}

	/**
	 * Render the section navigation.
	 */
	public function output_sections() {
		global $current_section;

		$sections = $this->get_sections();
		$last_key = array_key_last( $sections );

		echo '<ul class="subsubsub">';

		foreach ( $sections as $id => $label ) {
			$url = add_query_arg(
				array(
					'page'    => 'wc-settings',
					'tab'     => self::TAB_ID,
					'section' => sanitize_title( $id ),
				),
				admin_url( 'admin.php' )
			);

			printf(
				'<li><a href="%1$s" class="%2$s">%3$s</a>%4$s</li>',
				esc_url( $url ),
				esc_attr( (string) $current_section === (string) $id ? 'current' : '' ),
				esc_html( $label ),
				$last_key === $id ? '' : ' | '
			);
		}

		echo '</ul><br class="clear" />';
	}

	/**
	 * Render fields for the active section.
	 */
	public function output_settings() {
		global $current_section;

		WC_Admin_Settings::output_fields( $this->get_settings( (string) $current_section ) );
	}

	/**
	 * Save fields for the active section.
	 */
	public function save_settings() {
		global $current_section;

		WC_Admin_Settings::save_fields( $this->get_settings( (string) $current_section ) );

		$this->sanitize_stored_options();
	}

	/**
	 * Field definitions per section.
	 *
	 * @param string $section Section ID.
	 * @return array
	 */
	public function get_settings( $section = '' ) {
		if ( 'design' === $section ) {
			$settings = $this->get_design_settings();
		} elseif ( 'messaging' === $section ) {
			$settings = $this->get_messaging_settings();
		} else {
			$settings = $this->get_general_settings();
		}

		return (array) apply_filters( 'b2b_quote_settings_fields', $settings, $section );
	}

	/**
	 * Product category options for the targeting field.
	 *
	 * @return array
	 */
	private function get_category_options() {
		$options = array();

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return $options;
		}

		foreach ( $terms as $term ) {
			$options[ $term->term_id ] = $term->name;
		}

		return $options;
	}

	/**
	 * Currently selected products, so the AJAX search field can preselect them.
	 *
	 * @return array
	 */
	private function get_selected_product_options() {
		$options = array();
		$stored  = get_option( 'b2b_quote_products', array() );

		if ( is_string( $stored ) ) {
			$stored = explode( ',', $stored );
		}

		foreach ( (array) $stored as $product_id ) {
			$product_id = absint( $product_id );
			$product    = $product_id ? wc_get_product( $product_id ) : false;

			if ( $product instanceof WC_Product ) {
				$options[ $product_id ] = wp_strip_all_tags( $product->get_formatted_name() );
			}
		}

		return $options;
	}

	/**
	 * General section.
	 *
	 * @return array
	 */
	private function get_general_settings() {
		$page_id = absint( get_option( 'b2b_quote_page_id' ) );

		if ( $page_id && get_post( $page_id ) ) {
			$page_note = sprintf(
				/* translators: %s: link to the quote cart page. */
				__( 'Quote requests are collected on %s. Add the [b2b_quote_cart] shortcode to any page to move it.', 'woo-b2b-quote' ),
				'<a href="' . esc_url( (string) get_edit_post_link( $page_id ) ) . '">' . esc_html( (string) get_the_title( $page_id ) ) . '</a>'
			);
		} else {
			$page_note = __( 'No quote cart page was found. Create a page containing the [b2b_quote_cart] shortcode.', 'woo-b2b-quote' );
		}

		return array(
			array(
				'title' => __( 'Quote request behaviour', 'woo-b2b-quote' ),
				'type'  => 'title',
				'desc'  => $page_note,
				'id'    => 'b2b_quote_general_section',
			),
			array(
				'title'    => __( 'Enable for all products', 'woo-b2b-quote' ),
				'desc'     => __( 'Replace add-to-cart with a quote request across the whole catalogue', 'woo-b2b-quote' ),
				'id'       => 'b2b_quote_master_switch',
				'type'     => 'checkbox',
				'default'  => 'no',
				'desc_tip' => __( 'When enabled, the category and product targeting below is ignored.', 'woo-b2b-quote' ),
			),
			array(
				'title'    => __( 'Quoteable categories', 'woo-b2b-quote' ),
				'id'       => 'b2b_quote_categories',
				'type'     => 'multiselect',
				'class'    => 'wc-enhanced-select',
				'css'      => 'min-width: 350px;',
				'options'  => $this->get_category_options(),
				'desc_tip' => __( 'Child categories match automatically, so selecting a parent covers everything beneath it.', 'woo-b2b-quote' ),
			),
			array(
				'title'             => __( 'Quoteable products', 'woo-b2b-quote' ),
				'id'                => 'b2b_quote_products',
				'type'              => 'multiselect',
				'class'             => 'wc-product-search',
				'css'               => 'min-width: 350px;',
				'options'           => $this->get_selected_product_options(),
				'desc_tip'          => __( 'Selecting a variable product also covers all of its variations.', 'woo-b2b-quote' ),
				'custom_attributes' => array(
					'data-placeholder' => __( 'Search for a product', 'woo-b2b-quote' ),
					'data-action'      => 'woocommerce_json_search_products_and_variations',
				),
			),
			array(
				'title'   => __( 'Button label', 'woo-b2b-quote' ),
				'id'      => 'b2b_quote_btn_text',
				'type'    => 'text',
				'default' => __( 'Add to Quote Request', 'woo-b2b-quote' ),
				'css'     => 'min-width: 350px;',
			),
			array(
				'title'    => __( 'Notification email', 'woo-b2b-quote' ),
				'id'       => 'b2b_quote_admin_email',
				'type'     => 'email',
				'default'  => get_option( 'admin_email' ),
				'css'      => 'min-width: 350px;',
				'desc_tip' => __( 'Where new quote requests are sent. Defaults to the site admin email.', 'woo-b2b-quote' ),
			),
			array(
				'title'   => __( 'Floating cart badge', 'woo-b2b-quote' ),
				'desc'    => __( 'Show a floating quote request badge on the storefront', 'woo-b2b-quote' ),
				'id'      => 'b2b_quote_show_floating_cart',
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'title'    => __( 'Hide the quantity field', 'woo-b2b-quote' ),
				'desc'     => __( 'Hide the native quantity input on quoteable products', 'woo-b2b-quote' ),
				'id'       => 'b2b_quote_hide_quantity',
				'type'     => 'checkbox',
				'default'  => 'no',
				'desc_tip' => __( 'Leave this off for bulk quoting. Hiding the field forces every request to a quantity of one.', 'woo-b2b-quote' ),
			),
			array(
				'title'    => __( 'Remove data on uninstall', 'woo-b2b-quote' ),
				'desc'     => __( 'Delete the quote requests table, settings and quote cart page when the plugin is deleted', 'woo-b2b-quote' ),
				'id'       => 'b2b_quote_delete_data',
				'type'     => 'checkbox',
				'default'  => 'no',
				'desc_tip' => __( 'This cannot be undone. Leave it off if you may reinstall later.', 'woo-b2b-quote' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'b2b_quote_general_section',
			),
		);
	}

	/**
	 * Design section.
	 *
	 * @return array
	 */
	private function get_design_settings() {
		return array(
			array(
				'title' => __( 'Button design', 'woo-b2b-quote' ),
				'type'  => 'title',
				'desc'  => __( 'These values are published as CSS custom properties so your theme can override them.', 'woo-b2b-quote' ),
				'id'    => 'b2b_quote_design_section',
			),
			array(
				'title' => __( 'Live preview', 'woo-b2b-quote' ),
				'type'  => 'b2b_quote_preview',
				'id'    => 'b2b_quote_preview',
			),
			array(
				'title'   => __( 'Background', 'woo-b2b-quote' ),
				'id'      => 'b2b_quote_color_primary',
				'type'    => 'color',
				'default' => self::DEFAULT_PRIMARY,
			),
			array(
				'title'   => __( 'Background on hover', 'woo-b2b-quote' ),
				'id'      => 'b2b_quote_color_secondary',
				'type'    => 'color',
				'default' => self::DEFAULT_SECONDARY,
			),
			array(
				'title'   => __( 'Text colour', 'woo-b2b-quote' ),
				'id'      => 'b2b_quote_text_color',
				'type'    => 'color',
				'default' => self::DEFAULT_TEXT,
			),
			array(
				'title'   => __( 'Text colour on hover', 'woo-b2b-quote' ),
				'id'      => 'b2b_quote_text_color_hover',
				'type'    => 'color',
				'default' => self::DEFAULT_TEXT,
			),
			array(
				'title'   => __( 'Border colour', 'woo-b2b-quote' ),
				'id'      => 'b2b_quote_border_color',
				'type'    => 'color',
				'default' => self::DEFAULT_SECONDARY,
			),
			array(
				'title'             => __( 'Border width (px)', 'woo-b2b-quote' ),
				'id'                => 'b2b_quote_border_width',
				'type'              => 'number',
				'default'           => '0',
				'custom_attributes' => array(
					'min'  => '0',
					'max'  => '20',
					'step' => '1',
				),
			),
			array(
				'title'             => __( 'Border radius (px)', 'woo-b2b-quote' ),
				'id'                => 'b2b_quote_border_radius',
				'type'              => 'number',
				'default'           => '4',
				'custom_attributes' => array(
					'min'  => '0',
					'max'  => '100',
					'step' => '1',
				),
			),
			array(
				'title'    => __( 'Padding', 'woo-b2b-quote' ),
				'id'       => 'b2b_quote_padding',
				'type'     => 'text',
				'default'  => self::DEFAULT_PADDING,
				'desc_tip' => __( 'Up to four CSS length values, for example 10px 20px. Invalid values fall back to the default.', 'woo-b2b-quote' ),
			),
			array(
				'title'             => __( 'Font size (px)', 'woo-b2b-quote' ),
				'id'                => 'b2b_quote_font_size',
				'type'              => 'number',
				'default'           => '16',
				'custom_attributes' => array(
					'min'  => '8',
					'max'  => '48',
					'step' => '1',
				),
			),
			array(
				'title'   => __( 'Font weight', 'woo-b2b-quote' ),
				'id'      => 'b2b_quote_font_weight',
				'type'    => 'select',
				'default' => '700',
				'options' => array(
					'400' => __( 'Normal (400)', 'woo-b2b-quote' ),
					'500' => __( 'Medium (500)', 'woo-b2b-quote' ),
					'600' => __( 'Semi-bold (600)', 'woo-b2b-quote' ),
					'700' => __( 'Bold (700)', 'woo-b2b-quote' ),
					'800' => __( 'Extra bold (800)', 'woo-b2b-quote' ),
				),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'b2b_quote_design_section',
			),
		);
	}

	/**
	 * Messaging section.
	 *
	 * @return array
	 */
	private function get_messaging_settings() {
		$settings = array(
			array(
				'title' => __( 'Direct message ordering', 'woo-b2b-quote' ),
				'type'  => 'title',
				'desc'  => __( 'Leave a field blank to hide that platform. WhatsApp and Viber expect a full international number; the others expect a username.', 'woo-b2b-quote' ),
				'id'    => 'b2b_quote_messaging_section',
			),
		);

		foreach ( B2B_Quote_Frontend::get_social_platforms() as $platform ) {
			if ( empty( $platform['option'] ) ) {
				continue;
			}

			$settings[] = array(
				'title'   => isset( $platform['label'] ) ? $platform['label'] : $platform['option'],
				'id'      => $platform['option'],
				'type'    => 'text',
				'default' => '',
				'css'     => 'min-width: 300px;',
			);
		}

		$settings[] = array(
			'title'   => __( 'Button background', 'woo-b2b-quote' ),
			'id'      => 'b2b_quote_social_bg_color',
			'type'    => 'color',
			'default' => self::DEFAULT_SOCIAL_BG,
		);

		$settings[] = array(
			'title'   => __( 'Button background on hover', 'woo-b2b-quote' ),
			'id'      => 'b2b_quote_social_bg_color_hover',
			'type'    => 'color',
			'default' => self::DEFAULT_SOCIAL_BG_HOVER,
		);

		$settings[] = array(
			'title'   => __( 'Button text colour', 'woo-b2b-quote' ),
			'id'      => 'b2b_quote_social_text_color',
			'type'    => 'color',
			'default' => self::DEFAULT_SOCIAL_TEXT,
		);

		$settings[] = array(
			'type' => 'sectionend',
			'id'   => 'b2b_quote_messaging_section',
		);

		return $settings;
	}

	/**
	 * Custom field type rendering the live button preview.
	 *
	 * @param array $field Field definition.
	 */
	public function render_preview_field( $field ) {
		$title = isset( $field['title'] ) ? $field['title'] : '';
		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php echo esc_html( $title ); ?></th>
			<td class="forminp">
				<div id="b2b-live-preview-box" class="b2b-live-preview">
					<button type="button" id="b2b_preview_btn" class="b2b-preview-btn"><?php echo esc_html( B2B_Quote_Frontend::get_button_text() ); ?></button>
				</div>
				<p class="description"><?php esc_html_e( 'Updates as you change the values below. Remember to save.', 'woo-b2b-quote' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Load admin assets on our settings tab only.
	 *
	 * @param string $hook_suffix Current admin screen.
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( 'woocommerce_page_wc-settings' !== $hook_suffix ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen check.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		if ( self::TAB_ID !== $tab ) {
			return;
		}

		wp_enqueue_style(
			'b2b-quote-settings',
			B2B_QUOTE_PLUGIN_URL . 'assets/css/b2b-quote-settings.css',
			array(),
			B2B_QUOTE_VERSION
		);

		wp_enqueue_script(
			'b2b-quote-settings',
			B2B_QUOTE_PLUGIN_URL . 'assets/js/b2b-quote-settings.js',
			array( 'jquery' ),
			B2B_QUOTE_VERSION,
			true
		);
	}

	/**
	 * Re-sanitise stored values after WooCommerce has saved them.
	 */
	private function sanitize_stored_options() {
		$colors = array(
			'b2b_quote_color_primary'          => self::DEFAULT_PRIMARY,
			'b2b_quote_color_secondary'        => self::DEFAULT_SECONDARY,
			'b2b_quote_text_color'             => self::DEFAULT_TEXT,
			'b2b_quote_text_color_hover'       => self::DEFAULT_TEXT,
			'b2b_quote_border_color'           => self::DEFAULT_SECONDARY,
			'b2b_quote_social_bg_color'        => self::DEFAULT_SOCIAL_BG,
			'b2b_quote_social_bg_color_hover'  => self::DEFAULT_SOCIAL_BG_HOVER,
			'b2b_quote_social_text_color'      => self::DEFAULT_SOCIAL_TEXT,
		);

		foreach ( $colors as $option => $fallback ) {
			$stored = get_option( $option );

			if ( null === $stored || false === $stored ) {
				continue;
			}

			update_option( $option, self::sanitize_color( $stored, $fallback ) );
		}

		$integers = array(
			'b2b_quote_border_width'  => 0,
			'b2b_quote_border_radius' => 4,
			'b2b_quote_font_size'     => 16,
			'b2b_quote_font_weight'   => 700,
		);

		foreach ( $integers as $option => $fallback ) {
			$stored = get_option( $option );

			if ( null === $stored || false === $stored ) {
				continue;
			}

			update_option( $option, (string) ( is_numeric( $stored ) ? absint( $stored ) : $fallback ) );
		}

		$padding = get_option( 'b2b_quote_padding' );

		if ( false !== $padding && null !== $padding ) {
			update_option( 'b2b_quote_padding', self::sanitize_css_padding( $padding, self::DEFAULT_PADDING ) );
		}
	}

	/**
	 * Validate a hex colour.
	 *
	 * @param mixed  $value    Raw value.
	 * @param string $fallback Fallback colour.
	 * @return string
	 */
	public static function sanitize_color( $value, $fallback ) {
		$color = sanitize_hex_color( is_string( $value ) ? trim( $value ) : '' );

		return $color ? $color : $fallback;
	}

	/**
	 * Validate a CSS shorthand padding value.
	 *
	 * The 1.2.0 settings screen interpolated this free-text field straight into a
	 * <style> block, which allowed arbitrary CSS (and a closing </style> tag) to
	 * be injected into every page of the storefront.
	 *
	 * @param mixed  $value    Raw value.
	 * @param string $fallback Fallback value.
	 * @return string
	 */
	public static function sanitize_css_padding( $value, $fallback ) {
		$value = is_string( $value ) ? trim( preg_replace( '/\s+/', ' ', $value ) ) : '';

		if ( '' === $value ) {
			return $fallback;
		}

		if ( ! preg_match( '/^(?:\d{1,3}(?:\.\d{1,2})?(?:px|em|rem|%)?)(?: \d{1,3}(?:\.\d{1,2})?(?:px|em|rem|%)?){0,3}$/', $value ) ) {
			return $fallback;
		}

		return $value;
	}

	/**
	 * Read an integer option and return it as a pixel value.
	 *
	 * @param string $option   Option name.
	 * @param int    $fallback Fallback value.
	 * @return string
	 */
	private static function get_px( $option, $fallback ) {
		$value = get_option( $option, $fallback );

		return absint( is_numeric( $value ) ? $value : $fallback ) . 'px';
	}

	/**
	 * Read and validate a colour option.
	 *
	 * @param string $option   Option name.
	 * @param string $fallback Fallback colour.
	 * @return string
	 */
	private static function get_color( $option, $fallback ) {
		return self::sanitize_color( get_option( $option, $fallback ), $fallback );
	}

	/**
	 * Build the CSS custom properties for the storefront.
	 *
	 * Every value is validated first, so nothing user-supplied can break out of
	 * the declaration block.
	 *
	 * @return string
	 */
	public static function get_frontend_inline_css() {
		$variables = array(
			'--b2b-quote-primary'          => self::get_color( 'b2b_quote_color_primary', self::DEFAULT_PRIMARY ),
			'--b2b-quote-secondary'        => self::get_color( 'b2b_quote_color_secondary', self::DEFAULT_SECONDARY ),
			'--b2b-quote-text-color'       => self::get_color( 'b2b_quote_text_color', self::DEFAULT_TEXT ),
			'--b2b-quote-text-color-hover' => self::get_color( 'b2b_quote_text_color_hover', self::DEFAULT_TEXT ),
			'--b2b-quote-border-color'     => self::get_color( 'b2b_quote_border_color', self::DEFAULT_SECONDARY ),
			'--b2b-quote-border-width'     => self::get_px( 'b2b_quote_border_width', 0 ),
			'--b2b-quote-border-radius'    => self::get_px( 'b2b_quote_border_radius', 4 ),
			'--b2b-quote-font-size'        => self::get_px( 'b2b_quote_font_size', 16 ),
			'--b2b-quote-font-weight'      => (string) absint( get_option( 'b2b_quote_font_weight', 700 ) ),
			'--b2b-quote-padding'          => self::sanitize_css_padding( get_option( 'b2b_quote_padding', self::DEFAULT_PADDING ), self::DEFAULT_PADDING ),
			'--b2b-social-bg'              => self::get_color( 'b2b_quote_social_bg_color', self::DEFAULT_SOCIAL_BG ),
			'--b2b-social-bg-hover'        => self::get_color( 'b2b_quote_social_bg_color_hover', self::DEFAULT_SOCIAL_BG_HOVER ),
			'--b2b-social-text'            => self::get_color( 'b2b_quote_social_text_color', self::DEFAULT_SOCIAL_TEXT ),
		);

		$declarations = '';

		foreach ( $variables as $name => $value ) {
			$declarations .= $name . ':' . $value . ';';
		}

		return ':root{' . $declarations . '}';
	}
}
