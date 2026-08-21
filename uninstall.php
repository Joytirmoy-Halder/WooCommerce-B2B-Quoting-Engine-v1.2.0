<?php
/**
 * Uninstall routine.
 *
 * 1.2.0 shipped no uninstaller, so the custom table, the generated quote page
 * and every option were left behind forever. Data is only removed when the
 * merchant explicitly opted in via the "Remove all data on uninstall" setting.
 *
 * @package WooB2BQuotingEngine
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove all plugin data for the current site.
 */
function b2b_quote_uninstall_site() {
	global $wpdb;

	if ( 'yes' !== get_option( 'b2b_quote_delete_data' ) ) {
		return;
	}

	$page_id = absint( get_option( 'b2b_quote_page_id' ) );

	if ( $page_id ) {
		wp_delete_post( $page_id, true );
	}

	$options = array(
		'b2b_quote_page_id',
		'b2b_quote_db_version',
		'b2b_quote_master_switch',
		'b2b_quote_categories',
		'b2b_quote_products',
		'b2b_quote_btn_text',
		'b2b_quote_admin_email',
		'b2b_quote_show_floating_cart',
		'b2b_quote_hide_quantity',
		'b2b_quote_delete_data',
		'b2b_quote_color_primary',
		'b2b_quote_color_secondary',
		'b2b_quote_text_color',
		'b2b_quote_text_color_hover',
		'b2b_quote_border_width',
		'b2b_quote_border_color',
		'b2b_quote_border_radius',
		'b2b_quote_padding',
		'b2b_quote_font_size',
		'b2b_quote_font_weight',
		'b2b_quote_social_whatsapp',
		'b2b_quote_social_messenger',
		'b2b_quote_social_telegram',
		'b2b_quote_social_viber',
		'b2b_quote_social_skype',
		'b2b_quote_social_line',
		'b2b_quote_social_bg_color',
		'b2b_quote_social_bg_color_hover',
		'b2b_quote_social_text_color',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	$table_name = $wpdb->prefix . 'b2b_quote_requests';

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Table name derives from $wpdb->prefix.
	$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- One-off cleanup of throttle transients.
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		 WHERE option_name LIKE '_transient_b2b_quote_throttle_%'
		    OR option_name LIKE '_transient_timeout_b2b_quote_throttle_%'"
	);
}

if ( is_multisite() ) {
	$b2b_quote_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $b2b_quote_site_ids as $b2b_quote_site_id ) {
		switch_to_blog( $b2b_quote_site_id );
		b2b_quote_uninstall_site();
		restore_current_blog();
	}
} else {
	b2b_quote_uninstall_site();
}
