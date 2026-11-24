<?php
/**
 * Plugin Name:          WooCommerce B2B Quoting Engine
 * Plugin URI:           https://github.com/Joytirmoy-Halder/WooCommerce-B2B-Quoting-Engine-v1.2.0
 * Description:          Turn any WooCommerce catalogue into a B2B quoting engine. Customers build a quote request instead of checking out, you negotiate from a built-in CRM, send a counter-offer, and convert the approved quote straight into a WooCommerce order.
 * Version:              1.3.0
 * Author:               Joytirmoy Halder Joyti
 * Author URI:           https://github.com/Joytirmoy-Halder
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          woo-b2b-quote
 * Domain Path:          /languages
 * Requires at least:    6.0
 * Requires PHP:         7.4
 * WC requires at least: 7.0
 * WC tested up to:      9.4
 *
 * @package WooB2BQuotingEngine
 */

defined( 'ABSPATH' ) || exit;

define( 'B2B_QUOTE_VERSION', '1.3.0' );
define( 'B2B_QUOTE_DB_VERSION', '2' );
define( 'B2B_QUOTE_PLUGIN_FILE', __FILE__ );
define( 'B2B_QUOTE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'B2B_QUOTE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'B2B_QUOTE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin bootstrap.
 */
final class B2B_Quote_Engine {

	/**
	 * Singleton instance.
	 *
	 * @var B2B_Quote_Engine|null
	 */
	private static $instance = null;

	/**
	 * Instantiated feature classes, keyed by class name.
	 *
	 * @var array
	 */
	private $components = array();

	/**
	 * Retrieve the singleton instance.
	 *
	 * @return B2B_Quote_Engine
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Hook the bootstrap into WordPress.
	 */
	private function __construct() {
		add_action( 'before_woocommerce_init', array( $this, 'declare_feature_compatibility' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Declare compatibility with WooCommerce High Performance Order Storage.
	 *
	 * Without this WooCommerce flags the plugin as incompatible and can refuse
	 * to enable HPOS on the store.
	 */
	public function declare_feature_compatibility() {
		$features_util = 'Automattic\\WooCommerce\\Utilities\\FeaturesUtil';

		if ( ! class_exists( $features_util ) ) {
			return;
		}

		call_user_func( array( $features_util, 'declare_compatibility' ), 'custom_order_tables', B2B_QUOTE_PLUGIN_FILE, true );
		call_user_func( array( $features_util, 'declare_compatibility' ), 'cart_checkout_blocks', B2B_QUOTE_PLUGIN_FILE, true );
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'woo-b2b-quote', false, dirname( B2B_QUOTE_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Boot the plugin once all other plugins are available.
	 */
	public function init() {
		if ( ! $this->is_woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'render_missing_woocommerce_notice' ) );

			return;
		}

		$this->includes();

		B2B_Quote_DB::maybe_upgrade();

		foreach ( array(
			'B2B_Quote_Ajax',
			'B2B_Quote_Frontend',
			'B2B_Quote_Shortcode',
			'B2B_Quote_CRM',
			'B2B_Quote_Checkout',
			'B2B_Quote_Settings',
		) as $class_name ) {
			if ( class_exists( $class_name ) ) {
				$this->components[ $class_name ] = new $class_name();
			}
		}

		do_action( 'b2b_quote_loaded', $this );
	}

	/**
	 * Fetch a booted component.
	 *
	 * @param string $class_name Component class name.
	 * @return object|null
	 */
	public function get_component( $class_name ) {
		return isset( $this->components[ $class_name ] ) ? $this->components[ $class_name ] : null;
	}

	/**
	 * Determine whether WooCommerce is available.
	 *
	 * @return bool
	 */
	private function is_woocommerce_active() {
		return class_exists( 'WooCommerce' ) && function_exists( 'WC' );
	}

	/**
	 * Load class files.
	 */
	private function includes() {
		$files = array(
			'includes/class-b2b-quote-db.php',
			'includes/class-b2b-quote-rules.php',
			'includes/class-b2b-quote-emails.php',
			'includes/class-b2b-quote-ajax.php',
			'includes/class-b2b-quote-frontend.php',
			'includes/class-b2b-quote-shortcode.php',
			'includes/class-b2b-quote-crm.php',
			'includes/class-b2b-quote-checkout.php',
			'includes/class-b2b-quote-settings.php',
		);

		foreach ( $files as $file ) {
			require_once B2B_QUOTE_PLUGIN_DIR . $file;
		}
	}

	/**
	 * Admin notice shown when WooCommerce is missing.
	 */
	public function render_missing_woocommerce_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'WooCommerce B2B Quoting Engine requires WooCommerce to be installed and active.', 'woo-b2b-quote' )
		);
	}

	/**
	 * Activation routine: install the schema and make sure the quote page exists.
	 *
	 * Running this on activation only (rather than on every `init`) removes the
	 * per-request database writes that 1.2.0 performed on the front end.
	 */
	public static function activate() {
		require_once B2B_QUOTE_PLUGIN_DIR . 'includes/class-b2b-quote-db.php';

		B2B_Quote_DB::install();
		self::ensure_quote_page_exists();

		flush_rewrite_rules();
	}

	/**
	 * Deactivation routine.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Create the quote cart page if it is missing, trashed or deleted.
	 *
	 * @return int Page ID, or 0 on failure.
	 */
	public static function ensure_quote_page_exists() {
		$page_id = absint( get_option( 'b2b_quote_page_id' ) );

		if ( $page_id ) {
			$page = get_post( $page_id );

			if ( $page && 'page' === $page->post_type && 'trash' !== $page->post_status ) {
				return $page_id;
			}
		}

		$new_page_id = wp_insert_post(
			array(
				'post_title'   => __( 'Quote Request Cart', 'woo-b2b-quote' ),
				'post_content' => '[b2b_quote_cart]',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_author'  => get_current_user_id(),
			)
		);

		if ( is_wp_error( $new_page_id ) || ! $new_page_id ) {
			return 0;
		}

		update_option( 'b2b_quote_page_id', (int) $new_page_id );

		return (int) $new_page_id;
	}
}

register_activation_hook( __FILE__, array( 'B2B_Quote_Engine', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'B2B_Quote_Engine', 'deactivate' ) );

B2B_Quote_Engine::get_instance();
