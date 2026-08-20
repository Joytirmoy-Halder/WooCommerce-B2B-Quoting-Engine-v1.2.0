<?php
/**
 * Schema definition and data access for quote requests.
 *
 * All SQL for the custom table lives here so that every query is prepared in
 * exactly one place.
 *
 * @package WooB2BQuotingEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Quote request storage.
 */
class B2B_Quote_DB {

	const STATUS_PENDING          = 'pending';
	const STATUS_WAITING_APPROVAL = 'waiting_approval';
	const STATUS_ACCEPTED         = 'accepted';
	const STATUS_REJECTED         = 'rejected';

	/**
	 * Fully qualified table name.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'b2b_quote_requests';
	}

	/**
	 * Allowed statuses, mapped to translated labels.
	 *
	 * @return array
	 */
	public static function get_statuses() {
		return array(
			self::STATUS_PENDING          => __( 'Pending', 'woo-b2b-quote' ),
			self::STATUS_WAITING_APPROVAL => __( 'Waiting for approval', 'woo-b2b-quote' ),
			self::STATUS_ACCEPTED         => __( 'Accepted', 'woo-b2b-quote' ),
			self::STATUS_REJECTED         => __( 'Rejected', 'woo-b2b-quote' ),
		);
	}

	/**
	 * Whether the given status is one this plugin recognises.
	 *
	 * @param string $status Status key.
	 * @return bool
	 */
	public static function is_valid_status( $status ) {
		return is_string( $status ) && array_key_exists( $status, self::get_statuses() );
	}

	/**
	 * Human readable label for a status.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function get_status_label( $status ) {
		$statuses = self::get_statuses();

		return isset( $statuses[ $status ] ) ? $statuses[ $status ] : (string) $status;
	}

	/**
	 * Install or update the schema.
	 */
	public static function install() {
		self::create_table();
		update_option( 'b2b_quote_db_version', B2B_QUOTE_DB_VERSION );
	}

	/**
	 * Run the installer when the stored schema version is behind.
	 */
	public static function maybe_upgrade() {
		if ( (string) get_option( 'b2b_quote_db_version' ) !== (string) B2B_QUOTE_DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Create or migrate the quote requests table.
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		/*
		 * Two schema bugs from 1.2.0 are fixed here:
		 *
		 * 1. MySQL/MariaDB refuse a DEFAULT on TEXT/LONGTEXT columns (error 1101),
		 *    so `technical_reqs text DEFAULT '' NOT NULL` aborted table creation on
		 *    strict installs and the plugin silently had nowhere to store quotes.
		 *    Those columns are now nullable with no default.
		 * 2. dbDelta() cannot parse inline `--` comments inside the statement, so
		 *    the column list must stay comment free.
		 *
		 * dbDelta() also requires two spaces after PRIMARY KEY and lower-case
		 * column types, and every KEY it should manage must be declared here.
		 */
		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_name varchar(255) NOT NULL DEFAULT '',
			client_email varchar(255) NOT NULL DEFAULT '',
			client_company varchar(255) NOT NULL DEFAULT '',
			technical_reqs text NULL,
			quote_data longtext NULL,
			status varchar(50) NOT NULL DEFAULT 'pending',
			negotiation_notes text NULL,
			approval_token varchar(64) NOT NULL DEFAULT '',
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NULL DEFAULT NULL,
			updated_at datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY approval_token (approval_token),
			KEY client_email (client_email),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql );
	}

	/**
	 * Drop the table. Used by uninstall.php only.
	 */
	public static function drop_table() {
		global $wpdb;

		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name derives from $wpdb->prefix.
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}

	/**
	 * Generate a cryptographically random approval token.
	 *
	 * @return string
	 */
	public static function generate_token() {
		return wp_generate_password( 48, false, false );
	}

	/**
	 * Insert a new quote request.
	 *
	 * @param array $data Quote fields. `quote_data` should be an array of items.
	 * @return int Inserted row ID, or 0 on failure.
	 */
	public static function insert_quote( array $data ) {
		global $wpdb;

		$now = gmdate( 'Y-m-d H:i:s' );

		$row = array(
			'client_name'       => isset( $data['client_name'] ) ? (string) $data['client_name'] : '',
			'client_email'      => isset( $data['client_email'] ) ? (string) $data['client_email'] : '',
			'client_company'    => isset( $data['client_company'] ) ? (string) $data['client_company'] : '',
			'technical_reqs'    => isset( $data['technical_reqs'] ) ? (string) $data['technical_reqs'] : '',
			'quote_data'        => wp_json_encode( isset( $data['quote_data'] ) ? $data['quote_data'] : array() ),
			'status'            => self::STATUS_PENDING,
			'negotiation_notes' => '',
			'approval_token'    => '',
			'order_id'          => 0,
			'created_at'        => $now,
			'updated_at'        => $now,
		);

		$inserted = $wpdb->insert(
			self::get_table_name(),
			$row,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Fetch a single quote by ID.
	 *
	 * @param int $quote_id Quote ID.
	 * @return object|null
	 */
	public static function get_quote( $quote_id ) {
		global $wpdb;

		$quote_id = absint( $quote_id );

		if ( ! $quote_id ) {
			return null;
		}

		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name derives from $wpdb->prefix.
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $quote_id ) );
	}

	/**
	 * Fetch a quote by approval token, optionally restricted to given statuses.
	 *
	 * @param string $token    Approval token.
	 * @param array  $statuses Optional list of acceptable statuses.
	 * @return object|null
	 */
	public static function get_quote_by_token( $token, $statuses = array() ) {
		global $wpdb;

		$token = is_string( $token ) ? trim( $token ) : '';

		if ( '' === $token ) {
			return null;
		}

		$table_name = self::get_table_name();
		$sql        = "SELECT * FROM {$table_name} WHERE approval_token = %s";
		$params     = array( $token );

		$statuses = array_values( array_filter( (array) $statuses, array( __CLASS__, 'is_valid_status' ) ) );

		if ( $statuses ) {
			$sql   .= ' AND status IN (' . implode( ', ', array_fill( 0, count( $statuses ), '%s' ) ) . ')';
			$params = array_merge( $params, $statuses );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name derives from $wpdb->prefix.
		return $wpdb->get_row( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Update whitelisted columns on a quote.
	 *
	 * @param int   $quote_id Quote ID.
	 * @param array $fields   Column => value pairs.
	 * @return bool
	 */
	public static function update_quote( $quote_id, array $fields ) {
		global $wpdb;

		$quote_id = absint( $quote_id );

		if ( ! $quote_id ) {
			return false;
		}

		$allowed = array(
			'client_name'       => '%s',
			'client_email'      => '%s',
			'client_company'    => '%s',
			'technical_reqs'    => '%s',
			'quote_data'        => '%s',
			'status'            => '%s',
			'negotiation_notes' => '%s',
			'approval_token'    => '%s',
			'order_id'          => '%d',
		);

		$data    = array();
		$formats = array();

		foreach ( $fields as $key => $value ) {
			if ( ! isset( $allowed[ $key ] ) ) {
				continue;
			}

			if ( 'status' === $key && ! self::is_valid_status( $value ) ) {
				continue;
			}

			if ( 'quote_data' === $key && ! is_string( $value ) ) {
				$value = wp_json_encode( $value );
			}

			$data[ $key ] = $value;
			$formats[]    = $allowed[ $key ];
		}

		if ( ! $data ) {
			return false;
		}

		$data['updated_at'] = gmdate( 'Y-m-d H:i:s' );
		$formats[]          = '%s';

		return false !== $wpdb->update(
			self::get_table_name(),
			$data,
			array( 'id' => $quote_id ),
			$formats,
			array( '%d' )
		);
	}

	/**
	 * Delete a quote.
	 *
	 * @param int $quote_id Quote ID.
	 * @return bool
	 */
	public static function delete_quote( $quote_id ) {
		global $wpdb;

		$quote_id = absint( $quote_id );

		if ( ! $quote_id ) {
			return false;
		}

		return false !== $wpdb->delete( self::get_table_name(), array( 'id' => $quote_id ), array( '%d' ) );
	}

	/**
	 * Build a WHERE clause plus bound parameters from list arguments.
	 *
	 * @param array $args Query args.
	 * @return array [ where clause, params ]
	 */
	private static function build_where( array $args ) {
		global $wpdb;

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['status'] ) && self::is_valid_status( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where[]  = '( client_name LIKE %s OR client_email LIKE %s OR client_company LIKE %s )';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		return array( 'WHERE ' . implode( ' AND ', $where ), $params );
	}

	/**
	 * Count quotes matching the given filters.
	 *
	 * @param array $args Query args: status, search.
	 * @return int
	 */
	public static function count_quotes( array $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'status' => '',
				'search' => '',
			)
		);

		$table_name = self::get_table_name();

		list( $where, $params ) = self::build_where( $args );

		$sql = "SELECT COUNT(*) FROM {$table_name} {$where}";

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name derives from $wpdb->prefix.
		$count = $params ? $wpdb->get_var( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_var( $sql );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $count;
	}

	/**
	 * Fetch a paginated list of quotes.
	 *
	 * 1.2.0 selected every row with no LIMIT, which would exhaust memory on a
	 * busy store. Results are now always bounded.
	 *
	 * @param array $args Query args: status, search, per_page, page, orderby, order.
	 * @return array
	 */
	public static function get_quotes( array $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'status'   => '',
				'search'   => '',
				'per_page' => 20,
				'page'     => 1,
				'orderby'  => 'created_at',
				'order'    => 'DESC',
			)
		);

		$table_name = self::get_table_name();

		list( $where, $params ) = self::build_where( $args );

		$sortable = array( 'id', 'created_at', 'updated_at', 'status', 'client_name' );
		$orderby  = in_array( $args['orderby'], $sortable, true ) ? $args['orderby'] : 'created_at';
		$order    = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';

		$per_page = max( 1, min( 200, absint( $args['per_page'] ) ) );
		$offset   = max( 0, ( max( 1, absint( $args['page'] ) ) - 1 ) * $per_page );

		$sql      = "SELECT * FROM {$table_name} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$params[] = $per_page;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table, column and direction are whitelisted above.
		$results = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Decode and normalise the stored line items for a quote.
	 *
	 * Guards against malformed JSON, which previously caused a PHP warning and a
	 * fatal `foreach` on null in the CRM and checkout handlers.
	 *
	 * @param object|string $quote Quote row or raw JSON string.
	 * @return array Keyed by product ID.
	 */
	public static function decode_items( $quote ) {
		$raw = is_object( $quote ) ? $quote->quote_data : $quote;

		if ( empty( $raw ) || ! is_string( $raw ) ) {
			return array();
		}

		$items = json_decode( $raw, true );

		if ( ! is_array( $items ) ) {
			return array();
		}

		$clean = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || empty( $item['product_id'] ) ) {
				continue;
			}

			$product_id = absint( $item['product_id'] );

			if ( ! $product_id ) {
				continue;
			}

			$negotiated = null;

			if ( isset( $item['negotiated_price'] ) && '' !== $item['negotiated_price'] && null !== $item['negotiated_price'] ) {
				$negotiated = max( 0, (float) $item['negotiated_price'] );
			}

			$clean[ $product_id ] = array(
				'product_id'       => $product_id,
				'quantity'         => max( 1, absint( isset( $item['quantity'] ) ? $item['quantity'] : 1 ) ),
				'negotiated_price' => $negotiated,
			);
		}

		return $clean;
	}
}
