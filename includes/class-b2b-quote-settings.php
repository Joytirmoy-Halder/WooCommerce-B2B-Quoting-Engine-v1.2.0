<?php
/**
 * WooCommerce settings tab.
 *
 * Uses native WooCommerce settings sections and the documented
 * WC_Admin_Settings API. 1.2.0 rendered every field on one screen and then used
 * JavaScript to hide each `h2` in `#mainform` and rebuild fake tabs, which broke
 * whenever another plugin added a heading and relied on a hardcoded tab index.
 *
 * @package WooB2BQuotingEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings screen.
 */
class B2B_Quote_Settings {

	const TAB_ID = 'b2b_quoting';

	const DEFAULT_PRIMARY    = '#007cba';
	const DEFAULT_SECONDARY  = '#005a8c';
	const DEFAULT_TEXT       = '#ffffff';
	const DEFAULT_PADDING    = '10px 20px';
	const DEFAULT_SOCIAL_BG  = '#f0f0f0';
	const DEFAULT_SOCIAL_TXT = '#333333';

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
	 * Sections within the tab.
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
				$current_section === $id ? 'current' : '',
				esc_html( $label ),
				$last_key === $id ? '' : ' | '
			);
		}

		echo '</ul><br class="clear" />';
	}

	/**
	 * Render the fields for the active section.
	 */
	public function output_settings() {
		global $current_section;

		WC_Admin_Settings::output_fields( $this->get_settings( $current_section ) );
	}

	/**
	 * Save the fields for the active section.
	 */
	public function save_settings() {
		global $current_section;

		WC_Admin_Settings::save_fields( $this->get_settings( $current_section ) );

		$this->sanitize_stored_options();
	}

	/**
	 * Currently selected product IDs mapped to labels, for the search field.
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

			if ( ! $product_id ) {
				continue;
			}

			$product = wc_get_product( $product_id );

			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			$options[ $product_id ] = wp_strip_all_tags( $product->get_formatted_name() );
		}

		return $options;
	}

	/**
	 * Product category options.
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
	 * Field definitions per section.
	 *
	 * @param string $section Current section ID.
	 * @return array
	 */
	public function get_settings( $section = '' ) {
		switch ( $section ) {
			case 'design':
				$settings = $this->get_design_settings();
				break;

			case 'messaging':
				$settings = $this->get_messaging_settings();
				break;

			default:
				$settings = $this->get_general_settings();
				break;
		}

		return (array) apply_filters( 'b2b_quote_settings_fields', $settings, $section );
	}

	/**
	 * General section fields.
	 *
	 * @return array
	 */
	private function get_general_settings() {
		$quote_page_id = absint( get_option( 'b2b_quote_page_id' ) );
		$page_note     = '';

		if ( $quote_page_id && get_post( $quote_page_id ) ) {
			$page_note = sprintf(
				/* translators: %s: link to the quote cart page. */
				__( 'Quote requests are collected on %s. Add the [b2b_quote_cart] shortcode to any page to move it.', 'woo-b2b-quote' ),
				'<a href="' . esc_url( (string) get_edit_post_link( $quote_page_id ) ) . '">' . esc_html( (string) get_the_title( $quote_page_id ) ) . '</a>'
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
				'title'   => __( 'Enable for all products', 'woo-b2b-quote' ),
				'desc'    => __( 'Replace add-to-cart with a quote request across the whole catalogue', 'woo-b2b-quote' ),
				'id'      => 'b2b_quote_master_switch',
				'type'    => 'checkbox',
				'default' => 'no',
				'desc_tip' => __( 'When enabled, the category and product targeting below is ignored.', 'woo-b2b-quote' ),
			),
			array(
				'title'    => __( 'Quoteable categories', 'woo-b2b-quote' ),
				'id'       => 'b2b_quote_categories',
				'type'     => 'multiselect',
				'class'    => 'wc-enhanced-select',
				'css'      => 'min-width: 350px;',
				'options'  => $this->get_category_options(),
				'desc_tip' => __( 'Child categories are matched automatically, so selecting a parent covers everything beneath it.', 'woo-b2b-quote' ),
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
					'data-placeholder' => __( 'Search for a product…', 'woo-b2b-quote' ),
					'data-action'      => 'woocommerce_json_search_products_and_variations',
				),
			),
			array(
				'title'    => __( 'Button label', 'woo-b2b-quote' ),
				'id'       => 'b2b_quote_btn_text',
				'type'     => 'text',
				'default'  => __( 'Add to Quote Request', 'woo-b2b-quote' ),
				'css'      => 'min-width: 350px;',
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
	 * Design section fields.
	 *
	 * @return array
	 */
	private function get_design_settings() {
		return array(
			array(
				'title' => __( 'Button design', 'woo-b2b-quote' ),
				'type'  => 'title',
				'desc'  => __( 'These values are emitted as CSS custom properties, so your theme can override them.', 'woo-b2b-quote' ),
				'id'    => 'b2b_quote_design_section',
			),
			array(
				'type' => 'b2b_quote_preview',
				'id'   => 'b2b_quote_preview',
				'title' => __( 'Live preview', 'woo-b2b-quote' ),
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
				'desc_tip' => __( 'Up to four CSS length values, for example “10px 20px”. Invalid values fall back to the default.', 'woo-b2b-quote' ),
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
	 * Messaging section fields.
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
				'title'   => $platform['label'],
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
			'default' => '#e0e0e0',
		);

		$settings[] = array(
			'title'   => __( 'Button text colour', 'woo-b2b-quote' ),
			'id'      => 'b2b_quote_social_text_color',
			'type'    => 'color',
			'default' => self::DEFAULT_SOCIAL_TXT,
		);

		$settings[] = array(
			'type' => 'sectionend',
			'id'   => 'b2b_quote_messaging_section',
		);

		return $settings;
	}

	/**
	 * Custom field type: the live button preview.
	 *
	 * @param array $field Field definition.
	 */
	public function render_preview_field( $field ) {
		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php echo esc_html( isset( $field['title'