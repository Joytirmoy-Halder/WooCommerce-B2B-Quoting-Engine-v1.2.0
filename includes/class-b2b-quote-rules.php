<?php
/**
 * Decides which products may be quoted.
 *
 * Extracted from B2B_Quote_Frontend so the AJAX endpoints can apply exactly the
 * same rule set. In 1.2.0 the front end checked eligibility but the AJAX handler
 * did not, so any post ID could be pushed into the quote session.
 *
 * @package WooB2BQuotingEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Product eligibility rules.
 */
class B2B_Quote_Rules {

	/**
	 * Whether a product should show the quote interface.
	 *
	 * @param WC_Product|int $product Product object or ID.
	 * @return bool
	 */
	public static function is_product_quoteable( $product ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( absint( $product ) );
		}

		if ( ! $product instanceof WC_Product ) {
			return false;
		}

		return (bool) apply_filters( 'b2b_quote_is_product_quoteable', self::evaluate( $product ), $product );
	}

	/**
	 * Run the configured targeting rules against a product.
	 *
	 * @param WC_Product $product Product object.
	 * @return bool
	 */
	private static function evaluate( WC_Product $product ) {
		if ( 'yes' === get_option( 'b2b_quote_master_switch' ) ) {
			return true;
		}

		/*
		 * Variations carry their own ID, but the settings screen stores parent
		 * product IDs. Checking both means targeting a variable product now also
		 * covers its variations.
		 */
		$candidate_ids = array_values( array_filter( array( $product->get_id(), $product->get_parent_id() ) ) );

		$target_products = self::normalise_ids( get_option( 'b2b_quote_products', array() ) );

		if ( $target_products && array_intersect( $candidate_ids, $target_products ) ) {
			return true;
		}

		$target_categories = self::normalise_ids( get_option( 'b2b_quote_categories', array() ) );

		if ( $target_categories ) {
			foreach ( $candidate_ids as $candidate_id ) {
				if ( array_intersect( self::get_category_ids_with_ancestors( $candidate_id ), $target_categories ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Coerce a stored option into a clean list of positive integers.
	 *
	 * Handles both the array format saved by the multiselect field and the legacy
	 * comma separated string format.
	 *
	 * @param mixed $value Raw option value.
	 * @return int[]
	 */
	private static function normalise_ids( $value ) {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_values( array_unique( array_filter( array_map( 'absint', $value ) ) ) );
	}

	/**
	 * Product category IDs including every ancestor term.
	 *
	 * Selecting a parent category in the settings now correctly matches products
	 * that only live in one of its child categories.
	 *
	 * @param int $product_id Product ID.
	 * @return int[]
	 */
	private static function get_category_ids_with_ancestors( $product_id ) {
		$term_ids = wc_get_product_term_ids( $product_id, 'product_cat' );

		if ( ! is_array( $term_ids ) || ! $term_ids ) {
			return array();
		}

		$all = $term_ids;

		foreach ( $term_ids as $term_id ) {
			$ancestors = get_ancestors( $term_id, 'product_cat', 'taxonomy' );

			if ( $ancestors ) {
				$all = array_merge( $all, $ancestors );
			}
		}

		return array_values( array_unique( array_map( 'absint', $all ) ) );
	}
}
