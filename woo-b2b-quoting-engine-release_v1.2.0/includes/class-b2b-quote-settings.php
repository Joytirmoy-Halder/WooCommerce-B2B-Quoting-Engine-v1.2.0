<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class B2B_Quote_Settings {

	public function __construct() {
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
		add_action( 'woocommerce_settings_tabs_b2b_quoting', array( $this, 'settings_tab' ) );
		add_action( 'woocommerce_update_options_b2b_quoting', array( $this, 'update_settings' ) );
		
		// Output dynamic CSS based on settings
		add_action( 'wp_head', array( $this, 'output_dynamic_frontend_css' ) );
		add_action( 'admin_head', array( $this, 'output_dynamic_admin_css' ) );
		
		// Inject Live Visual Preview for Settings page
		add_action( 'admin_footer', array( $this, 'inject_live_preview_script' ) );
	}

	public function add_settings_tab( $settings_tabs ) {
		$settings_tabs['b2b_quoting'] = __( 'B2B Quoting', 'woo-b2b-quote' );
		return $settings_tabs;
	}

	public function settings_tab() {
		woocommerce_admin_fields( $this->get_settings() );
	}

	public function update_settings() {
		woocommerce_update_options( $this->get_settings() );
	}

	public function get_settings() {
		$settings = array(
			'section_title' => array(
				'name'     => __( 'B2B Quoting Settings', 'woo-b2b-quote' ),
				'type'     => 'title',
				'desc'     => '',
				'id'       => 'b2b_quote_settings_section_title'
			),
			'master_switch' => array(
				'name' => __( 'Enable Quoting Globally', 'woo-b2b-quote' ),
				'type' => 'checkbox',
				'desc' => __( 'If checked, all products will have the Add to Quote button.', 'woo-b2b-quote' ),
				'id'   => 'b2b_quote_master_switch'
			),
			'target_categories' => array(
				'name'    => __( 'Target Categories', 'woo-b2b-quote' ),
				'type'    => 'multiselect',
				'desc'    => __( 'Select specific categories to enable quoting. Ignored if global switch is on.', 'woo-b2b-quote' ),
				'id'      => 'b2b_quote_categories',
				'options' => $this->get_product_categories(),
				'class'   => 'wc-enhanced-select'
			),
			'target_products' => array(
				'name'        => __( 'Specific Target Products', 'woo-b2b-quote' ),
				'type'        => 'multiselect',
				'desc'        => __( 'Search and select specific products. Ignored if global switch or category covers it.', 'woo-b2b-quote' ),
				'id'          => 'b2b_quote_products',
				'options'     => $this->get_saved_products_options(),
				'class'       => 'wc-product-search',
				'custom_attributes' => array(
					'data-multiple' => 'true',
					'data-action'   => 'woocommerce_json_search_products_and_variations',
				)
			),
			'general_section_end' => array(
				'type' => 'sectionend',
				'id'   => 'b2b_quote_settings_general_end'
			),
			'design_title' => array(
				'name'     => __( 'Visual Design Studio', 'woo-b2b-quote' ),
				'type'     => 'title',
				'desc'     => __( 'Configure the appearance of the quoting buttons in real-time.', 'woo-b2b-quote' ),
				'id'       => 'b2b_quote_settings_design_title'
			),
			'btn_text' => array(
				'name'    => __( 'Button Text', 'woo-b2b-quote' ),
				'type'    => 'text',
				'desc'    => __( 'Text displayed on the Add to Quote button.', 'woo-b2b-quote' ),
				'id'      => 'b2b_quote_btn_text',
				'default' => 'Add to Quote Request'
			),
			'brand_color_primary' => array(
				'name'    => __( 'Background Color (Normal)', 'woo-b2b-quote' ),
				'type'    => 'color',
				'desc'    => __( 'Default background color.', 'woo-b2b-quote' ),
				'id'      => 'b2b_quote_color_primary',
				'default' => '#007cba'
			),
			'brand_color_secondary' => array(
				'name'    => __( 'Background Color (Hover)', 'woo-b2b-quote' ),
				'type'    => 'color',
				'desc'    => __( 'Background color when hovered over.', 'woo-b2b-quote' ),
				'id'      => 'b2b_quote_color_secondary',
				'default' => '#005a8c'
			),
			'btn_color_text' => array(
				'name'    => __( 'Text Color (Normal)', 'woo-b2b-quote' ),
				'type'    => 'color',
				'desc'    => __( 'Text color.', 'woo-b2b-quote' ),
				'id'      => 'b2b_quote_text_color',
				'default' => '#ffffff'
			),
			'btn_color_text_hover' => array(
				'name'    => __( 'Text Color (Hover)', 'woo-b2b-quote' ),
				'type'    => 'color',
				'desc'    => __( 'Text color when hovered over.', 'woo-b2b-quote' ),
				'id'      => 'b2b_quote_text_color_hover',
				'default' => '#ffffff'
			),
			'btn_border_width' => array(
				'name'    => __( 'Border Width (px)', 'woo-b2b-quote' ),
				'type'    => 'number',
				'desc'    => __( 'Thickness of the border. Set to 0 for none.', 'woo-b2b-quote' ),
				'id'      => 'b2b_quote_border_width',
				'default' => 0,
				'custom_attributes' => array( 'min' => 0, 'step' => 1 )
			),
			'btn_border_color' => array(
				'name'    => __( 'Border Color', 'woo-b2b-quote' ),
				'type'    => 'color',
				'desc'    => __( 'Color of the border.', 'woo-b2b-quote' ),
				'id'      => 'b2b_quote_border_color',
				'default' => '#007cba'
			),
			'btn_border_radius' => array(
				'name'    => __( 'Border Radius (px)', 'woo-b2b-quote' ),
				'type'    => 'number',
				'desc'    => __( 'Corner roundness (e.g., 4 or 50).', 'woo-b2b-quote' ),
				'id'      => 'b2b_quote_border_radius',
				'default' => 4,
				'custom_attributes' => array( 'min' => 0, 'step' => 1 )
			),
			'btn_padding' => array(
				'name'    => __( 'Padding', 'woo-b2b-quote' ),
				'type'    => 'text',
				'desc'    => __( 'CSS syntax padding (e.g., 10px 20px).', 'woo-b2b-quote' ),
				'id'      => 'b2b_quote_padding',
				'default' => '10px 20px'
			),
			'btn_font_size' => array(
				'name'    => __( 'Font Size (px)', 'woo-b2b-quote' ),
				'type'    => 'number',
				'desc'    => __( 'Size of the text.', 'woo-b2b-quote' ),
				'id'      => 'b2b_quote_font_size',
				'default' => 16,
				'custom_attributes' => array( 'min' => 8, 'step' => 1 )
			),
			'btn_font_weight' => array(
				'name'    => __( 'Font Weight', 'woo-b2b-quote' ),
				'type'    => 'select',
				'desc'    => __( 'Thickness of the text font.', 'woo-b2b-quote' ),
				'id'      => 'b2b_quote_font_weight',
				'options' => array(
					'300' => 'Light (300)',
					'400' => 'Normal (400)',
					'600' => 'Semi-Bold (600)',
					'700' => 'Bold (700)',
					'900' => 'Extra Bold (900)',
				),
				'default' => '700'
			),
			'design_section_end' => array(
				'type' => 'sectionend',
				'id'   => 'b2b_quote_settings_design_end'
			),
			'social_settings_title' => array(
				'name'     => __( 'Direct Message Ordering', 'woo-b2b-quote' ),
				'type'     => 'title',
				'desc'     => __( 'Configure social messaging platforms for direct orders. Fill out your target numbers and/or IDs below. The platform buttons will dynamically appear beneath your primary Quote button. If you leave a platform blank, it will remain hidden.', 'woo-b2b-quote' ),
				'id'       => 'b2b_quote_social_settings_title'
			),
			'social_whatsapp' => array(
				'name'    => __( 'WhatsApp Number', 'woo-b2b-quote' ),
				'type'    => 'text',
				'desc'    => __( 'Include country code without +, e.g., 1234567890', 'woo-b2b-quote' ),
				'id'      => 'b2b_quote_social_whatsapp',
				'default' => ''
			),
			'social_messenger' => array(
				'name'    => __( 'Messenger Page Username', 'woo-b2b-quote' ),
				'type'    => 'text',
				'desc'    => __( 'e.g., yourpageusername for m.me/yourpageusername', 'woo-b2b-quote' ),
				'id'      => 'b2b_quote_social_messenger',
				'default' => ''
			),
			'social_telegram' => array(
				'name'    => __( 'Telegram Username', 'woo-b2b-quote' ),
				'type'    => 'text',
				'desc'    => __( 'Without the @ symbol', 'woo-b2b-quote' ),
				'id'      => 'b2b_quote_social_telegram',
				'default' => ''
			),
			'social_viber' => array(
				'name'    => __( 'Viber Number', 'woo-b2b-quote' ),
				'type'    => 'text',
				'desc'    => __( 'Number with country code', 'woo-b2b-quote' ),
				'id'      => 'b2b_quote_social_viber',
				'default' => ''
			),
			'social_skype' => array(
				'name'    => __( 'Skype Username', 'woo-b2b-quote' ),
				'type'    => 'text',
				'desc'    => __( 'Your Skype handle', 'woo-b2b-quote' ),
				'id'      => 'b2b_quote_social_skype',
				'default' => ''
			),
			'social_line' => array(
				'name'    => __( 'Line ID', 'woo-b2b-quote' ),
				'type'    => 'text',
				'desc'    => __( 'Your LINE ID', 'woo-b2b-quote' ),
				'id'      => 'b2b_quote_social_line',
				'default' => ''
			),
			'social_styling_section_end' => array(
				'type' => 'sectionend',
				'id'   => 'b2b_quote_social_styling_end'
			),
			'social_style_title' => array(
				'name'     => __( 'Social Button Styling', 'woo-b2b-quote' ),
				'type'     => 'title',
				'desc'     => __( 'Customize the appearance of the social buttons below the quote button. If left blank, it will inherit the primary brand color.', 'woo-b2b-quote' ),
				'id'       => 'b2b_quote_social_style_title'
			),
			'social_bg_color' => array(
				'name'    => __( 'Social Background Color', 'woo-b2b-quote' ),
				'type'    => 'color',
				'id'      => 'b2b_quote_social_bg_color',
				'default' => ''
			),
			'social_bg_color_hover' => array(
				'name'    => __( 'Social Hover Background Color', 'woo-b2b-quote' ),
				'type'    => 'color',
				'id'      => 'b2b_quote_social_bg_color_hover',
				'default' => ''
			),
			'social_text_color' => array(
				'name'    => __( 'Social Text Color', 'woo-b2b-quote' ),
				'type'    => 'color',
				'id'      => 'b2b_quote_social_text_color',
				'default' => ''
			),
			'section_end' => array(
				'type' => 'sectionend',
				'id' => 'b2b_quote_settings_section_end'
			)
		);
		return apply_filters( 'b2b_quoting_settings', $settings );
	}

	private function get_product_categories() {
		$categories = get_terms( array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
		) );
		$options = array();
		if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
			foreach ( $categories as $category ) {
				$options[ $category->term_id ] = $category->name;
			}
		}
		return $options;
	}

	private function get_saved_products_options() {
		$saved = get_option( 'b2b_quote_products', array() );
		$options = array();
		if ( ! empty( $saved ) && is_array( $saved ) ) {
			foreach ( $saved as $product_id ) {
				$product = wc_get_product( $product_id );
				if ( $product ) {
					$options[ $product_id ] = wp_kses_post( $product->get_formatted_name() );
				}
			}
		}
		return $options;
	}
	
	public function output_dynamic_frontend_css() {
		$primary = get_option( 'b2b_quote_color_primary', '#007cba' );
		$secondary = get_option( 'b2b_quote_color_secondary', '#005a8c' );
		$text_color = get_option( 'b2b_quote_text_color', '#ffffff' );
		$text_color_hover = get_option( 'b2b_quote_text_color_hover', '#ffffff' );
		$border_width = get_option( 'b2b_quote_border_width', '0' );
		$border_color = get_option( 'b2b_quote_border_color', '#007cba' );
		$border_radius = get_option( 'b2b_quote_border_radius', '4' );
		$padding = get_option( 'b2b_quote_padding', '10px 20px' );
		$font_size = get_option( 'b2b_quote_font_size', '16' );
		$font_weight = get_option( 'b2b_quote_font_weight', '700' );

		$social_bg = get_option( 'b2b_quote_social_bg_color' );
		if ( empty( $social_bg ) ) $social_bg = $primary;
		
		$social_bg_hover = get_option( 'b2b_quote_social_bg_color_hover' );
		if ( empty( $social_bg_hover ) ) $social_bg_hover = $secondary;
		
		$social_text = get_option( 'b2b_quote_social_text_color' );
		if ( empty( $social_text ) ) $social_text = $text_color;

		echo "<style>
			:root {
				--b2b-quote-primary: {$primary};
				--b2b-quote-secondary: {$secondary};
				--b2b-quote-text-color: {$text_color};
				--b2b-quote-text-color-hover: {$text_color_hover};
				--b2b-quote-border-width: {$border_width}px;
				--b2b-quote-border-color: {$border_color};
				--b2b-quote-border-radius: {$border_radius}px;
				--b2b-quote-padding: {$padding};
				--b2b-quote-font-size: {$font_size}px;
				--b2b-quote-font-weight: {$font_weight};
				
				--b2b-social-bg: {$social_bg};
				--b2b-social-bg-hover: {$social_bg_hover};
				--b2b-social-text: {$social_text};
			}
			.b2b-add-to-quote, .b2b-submit-quote-btn {
				background-color: var(--b2b-quote-primary) !important;
				color: var(--b2b-quote-text-color) !important;
				border: var(--b2b-quote-border-width) solid var(--b2b-quote-border-color) !important;
				border-radius: var(--b2b-quote-border-radius) !important;
				padding: var(--b2b-quote-padding) !important;
				font-size: var(--b2b-quote-font-size) !important;
				font-weight: var(--b2b-quote-font-weight) !important;
				transition: all 0.3s ease !important;
				display: block; /* ensure blocks on responsive */
				text-align: center;
				width: 100%;
			}
			.b2b-add-to-quote:hover, .b2b-submit-quote-btn:hover {
				background-color: var(--b2b-quote-secondary) !important;
				color: var(--b2b-quote-text-color-hover) !important;
			}
			
			/* Social Component Styles */
			.b2b-social-order-wrap {
				display: flex;
				flex-wrap: wrap;
				gap: 10px;
				margin-top: 15px;
				width: 100%;
			}
			.b2b-social-btn {
				flex: 1 1 auto;
				background-color: var(--b2b-social-bg) !important;
				color: var(--b2b-social-text) !important;
				border-radius: var(--b2b-quote-border-radius) !important;
				padding: 10px !important;
				font-size: 14px !important;
				font-weight: 600 !important;
				text-align: center;
				cursor: pointer;
				transition: all 0.3s ease !important;
				display: inline-flex;
				align-items: center;
				justify-content: center;
				gap: 5px;
				border: none;
			}
			.b2b-social-btn:hover {
				background-color: var(--b2b-social-bg-hover) !important;
			}
			
			/* Inline Composer */
			.b2b-social-composer {
				width: 100%;
				display: none;
				margin-top: 10px;
				background: #f9f9f9;
				padding: 15px;
				border-radius: 6px;
				border: 1px solid #ddd;
				box-sizing: border-box;
			}
			.b2b-social-composer textarea {
				width: 100%;
				min-height: 80px;
				margin-bottom: 10px;
				border-radius: 4px;
				border: 1px solid #ccc;
				padding: 10px;
				font-family: inherit;
				font-size: 14px;
				box-sizing: border-box;
			}
			.b2b-social-composer h4 {
				margin-top: 0;
				margin-bottom: 10px;
				font-size: 14px;
				display: flex;
				align-items: center;
				gap: 5px;
			}
			.b2b-social-confirm-btn {
				background-color: #222 !important;
				color: #fff !important;
				width: 100%;
				padding: 10px !important;
				border-radius: 4px !important;
				text-align: center;
				cursor: pointer;
				border: none;
				font-weight: bold;
			}
		</style>";
	}

	public function output_dynamic_admin_css() {
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'wc-settings' ) return;
		if ( ! isset( $_GET['tab'] ) || $_GET['tab'] !== 'b2b_quoting' ) return;

		$primary = get_option( 'b2b_quote_color_primary', '#007cba' );
		echo "<style>
			:root {
				--b2b-quote-admin-primary: {$primary};
			}
			/* Modern Settings Card Container */
			#mainform {
				background: #fff;
				padding: 30px 40px 50px;
				border-radius: 12px;
				box-shadow: 0 4px 24px rgba(0,0,0,0.04);
				margin-top: 20px;
				border: 1px solid #e2e4e7;
			}
			
			/* Virtual Tabs - Modern Pills */
			ul.b2b-virtual-tabs {
				margin: -10px -40px 30px !important;
				padding: 20px 40px !important;
				background: #fcfcfc;
				border-bottom: 1px solid #eaeaea !important;
				border-radius: 12px 12px 0 0;
				display: flex !important;
				gap: 10px;
			}
			ul.b2b-virtual-tabs li {
				margin: 0 !important;
			}
			ul.b2b-virtual-tabs li a.b2b-vtab-link {
				display: block;
				padding: 10px 20px !important;
				background: #f0f0f1;
				color: #555 !important;
				border-radius: 50px;
				font-size: 14px !important;
				font-weight: 600 !important;
				transition: all 0.3s ease;
				border: none !important;
			}
			ul.b2b-virtual-tabs li a.b2b-vtab-link:hover {
				background: #e2e4e7;
				color: #111 !important;
			}
			ul.b2b-virtual-tabs li a.b2b-vtab-link.active-tab {
				background: var(--b2b-quote-admin-primary) !important;
				color: #fff !important;
				box-shadow: 0 4px 12px rgba(0,124,186,0.3);
			}

			/* Input Enhancements */
			table.form-table th {
				font-weight: 600 !important;
				color: #333;
			}
			table.form-table input[type=text], table.form-table input[type=number], table.form-table select {
				border-radius: 6px !important;
				padding: 6px 12px !important;
				border: 1px solid #ccd0d4 !important;
				box-shadow: 0 1px 2px rgba(0,0,0,0.02) inset !important;
				transition: border-color 0.2s ease, box-shadow 0.2s ease;
			}
			table.form-table input[type=text]:focus, table.form-table input[type=number]:focus, table.form-table select:focus {
				border-color: var(--b2b-quote-admin-primary) !important;
				box-shadow: 0 0 0 1px var(--b2b-quote-admin-primary) !important;
				outline: none;
			}

			/* Floating submit button logic */
			p.submit {
				margin-top: 40px !important;
				padding-top: 20px !important;
				border-top: 1px solid #eaeaea !important;
			}
			p.submit .button-primary {
				background: #111 !important;
				border-color: #111 !important;
				color: #fff !important;
				border-radius: 6px !important;
				padding: 5px 25px !important;
				font-weight: 600;
				font-size: 14px;
				transition: all 0.3s ease;
			}
			p.submit .button-primary:hover {
				background: var(--b2b-quote-admin-primary) !important;
				border-color: var(--b2b-quote-admin-primary) !important;
				transform: translateY(-1px);
			}

			/* --- Premium Color Pickers Organization --- */
			/* Restructure Color Table Rows for a cleaner grouped aesthetic */
			table.form-table tr:has(.wp-picker-container),
			table.form-table tr:has(.colorpickpreview) {
				background: #fafafa;
				border-radius: 8px;
				display: flex;
				flex-wrap: wrap;
				align-items: center;
				padding: 15px 20px;
				margin-bottom: 10px;
				border: 1px solid #f0f0f1;
			}
			table.form-table tr:has(.wp-picker-container) th,
			table.form-table tr:has(.colorpickpreview) th {
				width: 250px !important;
				padding: 0 !important;
				border: none;
			}
			table.form-table tr:has(.wp-picker-container) td,
			table.form-table tr:has(.colorpickpreview) td {
				padding: 0 !important;
				flex: 1;
				display: flex;
				align-items: center;
				gap: 15px;
			}

			/* WP Core Iris Color Picker Overrides */
			.wp-picker-container .wp-color-result {
				border-radius: 50% !important;
				width: 36px !important;
				height: 36px !important;
				box-shadow: 0 4px 12px rgba(0,0,0,0.08) !important;
				border: 3px solid #fff !important;
				margin: 0 !important;
				padding: 0 !important;
				overflow: hidden;
			}
			.wp-picker-container .wp-color-result-text {
				display: none !important; /* Hide Iris text inside button */
			}
			.wp-picker-container input[type=text].wp-color-picker {
				font-family: monospace !important;
				font-size: 13px !important;
				font-weight: bold;
				color: #333 !important;
				text-transform: uppercase;
				width: 100px !important;
				text-align: center;
				border-radius: 6px !important;
				border: 1px solid #dcdcdc !important;
				padding: 7px !important;
			}
			.wp-picker-container .wp-picker-input-wrap {
				display: flex !important;
				align-items: center;
				gap: 10px;
			}
			
			/* Legacy WC .colorpick fallback */
			td.forminp-color .colorpickpreview {
				border-radius: 50% !important;
				width: 36px !important;
				height: 36px !important;
				border: 3px solid #fff;
				box-shadow: 0 4px 12px rgba(0,0,0,0.08) !important;
				display: inline-block;
				vertical-align: middle;
			}
			td.forminp-color input.colorpick {
				font-family: monospace !important;
				font-size: 13px !important;
				font-weight: bold;
				color: #333 !important;
				text-transform: uppercase;
				width: 100px !important;
				text-align: center;
			}

			/* Description Typography within color rows */
			table.form-table tr td.forminp-color p.description,
			table.form-table tr td .wp-picker-container + p.description,
			table.form-table tr td span.description {
				margin: 0 !important;
				margin-left: 15px !important;
				font-style: italic;
				color: #888;
			}
		</style>";
	}

	public function inject_live_preview_script() {
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'wc-settings' ) {
			return;
		}
		if ( ! isset( $_GET['tab'] ) || $_GET['tab'] !== 'b2b_quoting' ) {
			return;
		}
		?>
		<style>
			#b2b-live-preview-box {
				position: fixed;
				right: 40px;
				top: 150px;
				width: 320px;
				background: #fff;
				padding: 25px;
				border: 1px solid #ccd0d4;
				border-radius: 8px;
				box-shadow: 0 10px 30px rgba(0,0,0,0.1);
				z-index: 9999;
				text-align: center;
			}
			#b2b-live-preview-box h3 { 
				margin-top: 0; 
				color: #333; 
				margin-bottom: 20px; 
				font-size: 16px; 
				border-bottom: 1px solid #eee; 
				padding-bottom: 10px;
			}
			#b2b_preview_btn { 
				cursor: pointer; 
				display: inline-block;
				text-decoration: none;
				transition: all 0.3s ease;
				box-sizing: border-box;
				max-width: 100%;
			}
		</style>
		<div id="b2b-live-preview-box">
			<h3>🎨 Live Button Preview</h3>
			<a href="#" id="b2b_preview_btn" onclick="return false;">Button Text</a>
			<p style="margin-top:20px; font-size:12px; color:#777;">Hover over the button to see your secondary colors in action.</p>
		</div>
		<script>
		jQuery(document).ready(function($) {
			function updatePreview() {
				var text = $('#b2b_quote_btn_text').val();
				var bg = $('#b2b_quote_color_primary').val();
				var bgHover = $('#b2b_quote_color_secondary').val();
				var color = $('#b2b_quote_text_color').val();
				var colorHover = $('#b2b_quote_text_color_hover').val();
				var bw = $('#b2b_quote_border_width').val();
				var bc = $('#b2b_quote_border_color').val();
				var br = $('#b2b_quote_border_radius').val();
				var pad = $('#b2b_quote_padding').val();
				var fz = $('#b2b_quote_font_size').val();
				var fw = $('#b2b_quote_font_weight').val();

				var btn = $('#b2b_preview_btn');
				if (btn.length === 0) return;

				btn.text(text || 'Add to Quote Request');
				
		// Normal state CSS injection
				btn.css({
					'background-color': bg,
					'color': color,
					'border': bw + 'px solid ' + bc,
					'border-radius': br + 'px',
					'padding': pad,
					'font-size': fz + 'px',
					'font-weight': fw
				});

				// Emulate hover states securely
				btn.off('mouseenter mouseleave');
				btn.on('mouseenter', function() {
					$(this).css({ 'background-color': bgHover, 'color': colorHover });
				}).on('mouseleave', function() {
					$(this).css({ 'background-color': bg, 'color': color });
				});
			}

			// Run on load and poll rapidly. Best way to circumvent WooCommerce color-picker event swallows.
			updatePreview();
			setInterval(updatePreview, 250); 
			
			// --- VIRTUAL SUB-TABS ROUTER ---
			var sections = [];
			
			// Scan native WooCommerce form for title groups dynamically using reliable nextUntil encapsulation
			$('#mainform h2').each(function(index) {
				var $h2 = $(this);
				// Exclude WooCommerce submit buttons and save blocks structurally from being hidden
				var $els = $h2.nextUntil('h2').not('.submit, p.submit, .woocommerce-save-button');
				var $all = $h2.add($els);
				
				sections.push({
					index: index,
					title: $h2.text(),
					elements: $all
				});
				
				$h2.hide(); // Hide the native headers to pave way for our virtual tabs
				$els.filter('p').css({ 'font-size': '14px', 'margin-bottom': '15px', 'color': '#555' });
			});
			
			if (sections.length > 1) {
				// Inject the Tab Nav Menu
				var navHtml = '<ul class="subsubsub b2b-virtual-tabs">';
				$.each(sections, function(i, s) {
					navHtml += '<li><a href="#" class="b2b-vtab-link" data-index="'+i+'">' + s.title + '</a></li>';
				});
				navHtml += '</ul><div style="clear:both;"></div>';
				
				// Safely inject the tab bar right before the first bundled block
				sections[0].elements.first().before(navHtml);
				
				// Re-route Clicks
				$(document).on('click', '.b2b-vtab-link', function(e) {
					e.preventDefault();
					var clickedIndex = $(this).data('index');
					
					// Restyle Active Nav
					$('.b2b-vtab-link').removeClass('active-tab');
					$(this).addClass('active-tab');
					
					// Toggle Setting Panes & Intelligent Live Preview Unmount
					$.each(sections, function(i, s) {
						if (i === clickedIndex) {
							s.elements.fadeIn(200);
							// Index 1 corresponds to "Visual Design Studio"
							if (clickedIndex === 1) {
								$('#b2b-live-preview-box').fadeIn(200);
							} else {
								$('#b2b-live-preview-box').hide();
							}
						} else {
							s.elements.hide();
						}
					});
				});
				
				// Boot sequence: Auto-click first tab
				$('.b2b-vtab-link[data-index="0"]').click();
			}
		});
		</script>
		<?php
	}
}
