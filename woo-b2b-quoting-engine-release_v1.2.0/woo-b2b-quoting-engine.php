<?php
/**
 * Plugin Name: WooCommerce B2B Quoting Engine
 * Description: A beautifully simple, incredibly powerful way to let your customers request bulk or custom quotes instead of forcing them through a standard checkout. Built to work flawlessly with modern themes and page builders!
 * Version: 1.2.0
 * Author: Joytirmoy Halder Joyti
 * Author URI: https://github.com/Joytirmoy-Halder
 * Text Domain: woo-b2b-quote
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define Constants
define( 'B2B_QUOTE_VERSION', '1.2.0' );
define( 'B2B_QUOTE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'B2B_QUOTE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main Initialization Class
 */
class B2B_Quote_Engine {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
	}

	public function init() {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'wc_missing_notice' ) );
			return;
		}

		$this->includes();
		$this->init_classes();
		
		add_action( 'init', array( $this, 'ensure_quote_page_exists' ) );
	}

	public function ensure_quote_page_exists() {
		$page_id = get_option( 'b2b_quote_page_id' );
		if ( ! $page_id || ! get_post( $page_id ) ) {
			$new_page_id = wp_insert_post(
				array(
					'post_title'   => 'Quote Request Cart',
					'post_content' => '[b2b_quote_cart]',
					'post_status'  => 'publish',
					'post_type'    => 'page',
				)
			);
			if ( ! is_wp_error( $new_page_id ) ) {
				update_option( 'b2b_quote_page_id', $new_page_id );
			}
		}
	}

	private function includes() {
		require_once B2B_QUOTE_PLUGIN_DIR . 'includes/class-b2b-quote-db.php';
		require_once B2B_QUOTE_PLUGIN_DIR . 'includes/class-b2b-quote-frontend.php';
		require_once B2B_QUOTE_PLUGIN_DIR . 'includes/class-b2b-quote-ajax.php';
		require_once B2B_QUOTE_PLUGIN_DIR . 'includes/class-b2b-quote-shortcode.php';
		require_once B2B_QUOTE_PLUGIN_DIR . 'includes/class-b2b-quote-emails.php';
		require_once B2B_QUOTE_PLUGIN_DIR . 'includes/class-b2b-quote-crm.php';
		require_once B2B_QUOTE_PLUGIN_DIR . 'includes/class-b2b-quote-checkout.php';
		require_once B2B_QUOTE_PLUGIN_DIR . 'includes/class-b2b-quote-settings.php';
	}

	private function init_classes() {
		new B2B_Quote_DB();
		new B2B_Quote_Frontend();
		new B2B_Quote_Ajax();
		new B2B_Quote_Shortcode();
		new B2B_Quote_CRM();
		new B2B_Quote_Checkout();
		new B2B_Quote_Settings();
	}

	public function activate() {
		require_once B2B_QUOTE_PLUGIN_DIR . 'includes/class-b2b-quote-db.php';
		B2B_Quote_DB::create_table();
	}

	public function wc_missing_notice() {
		echo '<div class="error"><p><strong>WooCommerce B2B Quoting Engine</strong> requires WooCommerce to be installed and active.</p></div>';
	}
}

// Initialize the plugin
B2B_Quote_Engine::get_instance();
