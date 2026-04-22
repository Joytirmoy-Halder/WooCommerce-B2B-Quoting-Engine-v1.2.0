<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class B2B_Quote_DB {

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'b2b_quote_requests';
	}

	/**
	 * Create the custom database table.
	 */
	public static function create_table() {
		global $wpdb;

		$table_name = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			client_name varchar(255) NOT NULL,
			client_email varchar(255) NOT NULL,
			client_company varchar(255) DEFAULT '' NOT NULL,
			technical_reqs text DEFAULT '' NOT NULL,
			quote_data longtext NOT NULL, -- JSON formatted product and price items
			status varchar(50) DEFAULT 'pending' NOT NULL,
			negotiation_notes text DEFAULT '' NOT NULL,
			approval_token varchar(255) DEFAULT '' NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	// Future CRUD operations will be integrated here...
}
