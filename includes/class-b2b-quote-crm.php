<?php
/**
 * Admin CRM for reviewing and negotiating quote requests.
 *
 * @package WooB2BQuotingEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Quote CRM screens.
 */
class B2B_Quote_CRM {

	const MENU_SLUG   = 'b2b-quotes-crm';
	const CAPABILITY  = 'manage_woocommerce';
	const NONCE_ACTION = 'b2b_quote_update_nonce';
	const PER_PAGE    = 20;

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_b2b_quote_update', array( $this, 'handle_quote_update' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add the WooCommerce submenu entry.
	 */
	public function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Quote Requests', 'woo-b2b-quote' ),
			__( 'Quote Requests', 'woo-b2b-quote' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Load the admin stylesheet on our screen only.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'woocommerce_page_' . self::MENU_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'b2b-quote-admin',
			B2B_QUOTE_PLUGIN_URL . 'assets/css/b2b-quote-admin.css',
			array(),
			B2B_QUOTE_VERSION
		);
	}

	/**
	 * Route between the list and single views.
	 */
	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view quote requests.', 'woo-b2b-quote' ), 403 );
		}

		$this->render_notice();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation parameter.
		$quote_id = isset( $_GET['quote_id'] ) ? absint( wp_unslash( $_GET['quote_id'] ) ) : 0;

		if ( $quote_id ) {
			$this->render_single( $quote_id );

			return;
		}

		$this->render_list();
	}

	/**
	 * Show the result of the last save.
	 */
	private function render_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display flag.
		$notice = isset( $_GET['b2b_notice'] ) ? sanitize_key( wp_unslash( $_GET['b2b_notice'] ) ) : '';

		$messages = array(
			'saved'        => array( 'success', __( 'Quote request updated.', 'woo-b2b-quote' ) ),
			'offer-sent'   => array( 'success', __( 'Quote request updated and the counter-offer email was sent.', 'woo-b2b-quote' ) ),
			'offer-failed' => array( 'warning', __( 'Quote request updated, but the counter-offer email could not be sent. Check your site email configuration.', 'woo-b2b-quote' ) ),
		);

		if ( ! isset( $messages[ $notice ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $messages[ $notice ][0] ),
			esc_html( $messages[ $notice ][1] )
		);
	}

	/**
	 * Format a stored GMT timestamp for display in the site timezone.
	 *
	 * @param string $gmt_datetime MySQL datetime in GMT.
	 * @return string
	 */
	private static function format_date( $gmt_datetime ) {
		if ( empty( $gmt_datetime ) ) {
			return '&mdash;';
		}

		$timestamp = strtotime( $gmt_datetime . ' UTC' );

		if ( ! $timestamp ) {
			return '&mdash;';
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}

	/**
	 * Paginated list of quote requests.
	 */
	private function render_list() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list filters.
		$status  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged   = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! B2B_Quote_DB::is_valid_status( $status ) ) {
			$status = '';
		}

		$args = array(
			'status'   => $status,
			'search'   => $search,
			'page'     => $paged,
			'per_page' => self::PER_PAGE,
		);

		$total  = B2B_Quote_DB::count_quotes( $args );
		$quotes = B2B_Quote_DB::get_quotes( $args );
		$pages  = max( 1, (int) ceil( $total / self::PER_PAGE ) );
		?>
		<div class="wrap b2b-quote-crm">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Quote Requests', 'woo-b2b-quote' ); ?></h1>
			<hr class="wp-header-end">

			<form method="get" class="b2b-quote-filters">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>">

				<label class="screen-reader-text" for="b2b-filter-status"><?php esc_html_e( 'Filter by status', 'woo-b2b-quote' ); ?></label>
				<select name="status" id="b2b-filter-status">
					<option value=""><?php esc_html_e( 'All statuses', 'woo-b2b-quote' ); ?></option>
					<?php foreach ( B2B_Quote_DB::get_statuses() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>

				<label class="screen-reader-text" for="b2b-filter-search"><?php esc_html_e( 'Search quote requests', 'woo-b2b-quote' ); ?></label>
				<input type="search" id="b2b-filter-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Name, email or company', 'woo-b2b-quote' ); ?>">

				<?php submit_button( __( 'Filter', 'woo-b2b-quote' ), 'secondary', '', false ); ?>
			</form>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col" class="b2b-col-id"><?php esc_html_e( 'ID', 'woo-b2b-quote' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Customer', 'woo-b2b-quote' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Company', 'woo-b2b-quote' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Items', 'woo-b2b-quote' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'woo-b2b-quote' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Order', 'woo-b2b-quote' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Received', 'woo-b2b-quote' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! $quotes ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'No quote requests found.', 'woo-b2b-quote' ); ?></td></tr>
				<?php else : ?>
					<?php
					foreach ( $quotes as $quote ) :
						$view_url = add_query_arg(
							array(
								'page'     => self::MENU_SLUG,
								'quote_id' => (int) $quote->id,
							),
							admin_url( 'admin.php' )
						);
						$items = B2B_Quote_DB::decode_items( $quote );
						?>
						<tr>
							<td><a href="<?php echo esc_url( $view_url ); ?>"><strong>#<?php echo esc_html( $quote->id ); ?></strong></a></td>
							<td>
								<a href="<?php echo esc_url( $view_url ); ?>"><?php echo esc_html( $quote->client_name ); ?></a><br>
								<a href="mailto:<?php echo esc_attr( $quote->client_email ); ?>"><?php echo esc_html( $quote->client_email ); ?></a>
							</td>
							<td><?php echo esc_html( $quote->client_company ? $quote->client_company : '—' ); ?></td>
							<td><?php echo esc_html( number_format_i18n( count( $items ) ) ); ?></td>
							<td><span class="b2b-status b2b-status--<?php echo esc_attr( $quote->status ); ?>"><?php echo esc_html( B2B_Quote_DB::get_status_label( $quote->status ) ); ?></span></td>
							<td>
								<?php if ( ! empty( $quote->order_id ) ) : ?>
									<a href="<?php echo esc_url( admin_url( 'post.php?post=' . absint( $quote->order_id ) . '&action=edit' ) ); ?>">#<?php echo esc_html( $quote->order_id ); ?></a>
								<?php else : ?>
									&mdash;
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( self::format_date( $quote->created_at ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $pages > 1 ) : ?>
				<div class="tablenav"><div class="tablenav-pages">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'current'   => $paged,
								'total'     => $pages,
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
							)
						)
					);
					?>
				</div></div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Single quote view with the negotiation form.
	 *
	 * @param int $quote_id Quote ID.
	 */
	private function render_single( $quote_id ) {
		$quote = B2B_Quote_DB::get_quote( $quote_id );

		if ( ! $quote ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'That quote request could not be found.', 'woo-b2b-quote' ) . '</p></div></div>';

			return;
		}

		$items     = B2B_Quote_DB::decode_items( $quote );
		$back_url  = add_query_arg( 'page', self::MENU_SLUG, admin_url( 'admin.php' ) );
		$has_token = '' !== (string) $quote->approval_token;
		?>
		<div class="wrap b2b-quote-crm">
			<h1 class="wp-heading-inline">
				<?php
				/* translators: %d: quote ID. */
				echo esc_html( sprintf( __( 'Quote Request #%d', 'woo-b2b-quote' ), (int) $quote->id ) );
				?>
			</h1>
			<a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action"><?php esc_html_e( 'Back to list', 'woo-b2b-quote' ); ?></a>
			<hr class="wp-header-end">

			<div class="b2b-quote-panels">
				<div class="b2b-quote-panel">
					<h2><?php esc_html_e( 'Customer', 'woo-b2b-quote' ); ?></h2>
					<table class="widefat striped">
						<tbody>
							<tr><th scope="row"><?php esc_html_e( 'Name', 'woo-b2b-quote' ); ?></th><td><?php echo esc_html( $quote->client_name ); ?></td></tr>
							<tr><th scope="row"><?php esc_html_e( 'Email', 'woo-b2b-quote' ); ?></th><td><a href="mailto:<?php echo esc_attr( $quote->client_email ); ?>"><?php echo esc_html( $quote->client_email ); ?></a></td></tr>
							<tr><th scope="row"><?php esc_html_e( 'Company', 'woo-b2b-quote' ); ?></th><td><?php echo esc_html( $quote->client_company ? $quote->client_company : '—' ); ?></td></tr>
							<tr><th scope="row"><?php esc_html_e( 'Received', 'woo-b2b-quote' ); ?></th><td><?php echo esc_html( self::format_date( $quote->created_at ) ); ?></td></tr>
							<tr><th scope="row"><?php esc_html_e( 'Last updated', 'woo-b2b-quote' ); ?></th><td><?php echo esc_html( self::format_date( $quote->updated_at ) ); ?></td></tr>
							<?php if ( ! empty( $quote->order_id ) ) : ?>
								<tr><th scope="row"><?php esc_html_e( 'Order', 'woo-b2b-quote' ); ?></th><td><a href="<?php echo esc_url( admin_url( 'post.php?post=' . absint( $quote->order_id ) . '&action=edit' ) ); ?>">#<?php echo esc_html( $quote->order_id ); ?></a></td></tr>
							<?php endif; ?>
						</tbody>
					</table>

					<?php if ( '' !== (string) $quote->technical_reqs ) : ?>
						<h3><?php esc_html_e( 'Customer requirements', 'woo-b2b-quote' ); ?></h3>
						<div class="b2b-quote-reqs"><?php echo wp_kses_post( wpautop( esc_html( $quote->technical_reqs ) ) ); ?></div>
					<?php endif; ?>
				</div>

				<div class="b2b-quote-panel">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="b2b_quote_update">
						<input type="hidden" name="quote_id" value="<?php echo esc_attr( $quote->id ); ?>">
						<?php wp_nonce_field( self::NONCE_ACTION, 'b2b_nonce' ); ?>

						<h2><?php esc_html_e( 'Line items and pricing', 'woo-b2b-quote' ); ?></h2>

						<table class="widefat striped b2b-quote-items">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Product', 'woo-b2b-quote' ); ?></th>
									<th scope="col"><?php esc_html_e( 'List price', 'woo-b2b-quote' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Quantity', 'woo-b2b-quote' ); ?></th>
									<th scope="col">
										<?php
										/* translators: %s: currency symbol. */
										echo esc_html( sprintf( __( 'Agreed unit price (%s)', 'woo-b2b-quote' ), get_woocommerce_currency_symbol() ) );
										?>
									</th>
								</tr>
							</thead>
							<tbody>
							<?php if ( ! $items ) : ?>
								<tr><td colspan="4"><?php esc_html_e( 'This request contains no readable line items.', 'woo-b2b-quote' ); ?></td></tr>
							<?php else : ?>
								<?php
								foreach ( $items as $product_id => $item ) :
									$product = wc_get_product( $product_id );
									?>
									<tr>
										<td>
											<?php if ( $product instanceof WC_Product ) : ?>
												<a href="<?php echo esc_url( get_edit_post_link( $product_id ) ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
												<?php if ( $product->get_sku() ) : ?>
													<br><small><?php echo esc_html( sprintf( /* translators: %s: product SKU. */ __( 'SKU: %s', 'woo-b2b-quote' ), $product->get_sku() ) ); ?></small>
												<?php endif; ?>
											<?php else : ?>
												<?php
												/* translators: %d: product ID. */
												echo esc_html( sprintf( __( 'Product #%d (deleted)', 'woo-b2b-quote' ), $product_id ) );
												?>
											<?php endif; ?>
										</td>
										<td><?php echo $product instanceof WC_Product ? wp_kses_post( wc_price( (float) $product->get_price() ) ) : '&mdash;'; ?></td>
										<td>
											<input type="number" min="1" step="1" class="small-text" name="quantities[<?php echo esc_attr( $product_id ); ?>]" value="<?php echo esc_attr( $item['quantity'] ); ?>">
										</td>
										<td>
											<input type="text" inputmode="decimal" class="wc_input_price" name="negotiated_prices[<?php echo esc_attr( $product_id ); ?>]" value="<?php echo esc_attr( null === $item['negotiated_price'] ? '' : wc_format_localized_price( $item['negotiated_price'] ) ); ?>" placeholder="<?php esc_attr_e( 'Leave blank for list price', 'woo-b2b-quote' ); ?>">
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
							</tbody>
						</table>

						<h2><?php esc_html_e( 'Negotiation', 'woo-b2b-quote' ); ?></h2>

						<table class="form-table" role="presentation">
							<tbody>
								<tr>
									<th scope="row"><label for="b2b-status"><?php esc_html_e( 'Status', 'woo-b2b-quote' ); ?></label></th>
									<td>
										<select name="status" id="b2b-status">
											<?php foreach ( B2B_Quote_DB::get_statuses() as $key => $label ) : ?>
												<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $quote->status, $key ); ?>><?php echo esc_html( $label ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="b2b-notes"><?php esc_html_e( 'Notes for the customer', 'woo-b2b-quote' ); ?></label></th>
									<td>
										<textarea id="b2b-notes" name="negotiation_notes" rows="5" class="large-text"><?php echo esc_textarea( (string) $quote->negotiation_notes ); ?></textarea>
										<p class="description"><?php esc_html_e( 'Included in the counter-offer email.', 'woo-b2b-quote' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Counter-offer email', 'woo-b2b-quote' ); ?></th>
									<td>
										<label for="b2b-send-offer">
											<input type="checkbox" id="b2b-send-offer" name="send_counter_offer" value="1">
											<?php esc_html_e( 'Email this quote to the customer with a one-click approval link', 'woo-b2b-quote' ); ?>
										</label>
										<p class="description">
											<?php esc_html_e( 'Requires the status to be set to “Waiting for approval”. A fresh single-use link is generated and any previous link stops working.', 'woo-b2b-quote' ); ?>
										</p>

										<?php if ( $has_token ) : ?>
											<p class="description b2b-quote-token">
												<strong><?php esc_html_e( 'Active approval link:', 'woo-b2b-quote' ); ?></strong><br>
												<code><?php echo esc_html( B2B_Quote_Emails::get_approval_url( $quote->approval_token ) ); ?></code>
											</p>
										<?php endif; ?>
									</td>
								</tr>
							</tbody>
						</table>

						<?php submit_button( __( 'Save quote request', 'woo-b2b-quote' ) ); ?>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Persist the negotiation form.
	 *
	 * 1.2.0 read $_POST keys with no isset() checks, accepted any status string,
	 * and read `negotiated_prices` without sanitising it before discarding it
	 * unused. All three are fixed here.
	 */
	public function handle_quote_update() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to edit quote requests.', 'woo-b2b-quote' ), 403 );
		}

		$nonce = isset( $_POST['b2b_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['b2b_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed. Please reload the page and try again.', 'woo-b2b-quote' ), 403 );
		}

		$quote_id = isset( $_POST['quote_id'] ) ? absint( wp_unslash( $_POST['quote_id'] ) ) : 0;
		$quote    = $quote_id ? B2B_Quote_DB::get_quote( $quote_id ) : null;

		if ( ! $quote ) {
			wp_die( esc_html__( 'That quote request no longer exists.', 'woo-b2b-quote' ), 404 );
		}

		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';

		if ( ! B2B_Quote_DB::is_valid_status( $status ) ) {
			$status = $quote->status;
		}

		$notes = isset( $_POST['negotiation_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['negotiation_notes'] ) ) : '';

		$items = B2B_Quote_DB::decode_items( $quote );

		$posted_prices = ( isset( $_POST['negotiated_prices'] ) && is_array( $_POST['negotiated_prices'] ) )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['negotiated_prices'] ) )
			: array();

		$posted_quantities = ( isset( $_POST['quantities'] ) && is_array( $_POST['quantities'] ) )
			? array_map( 'absint', wp_unslash( $_POST['quantities'] ) )
			: array();

		foreach ( $items as $product_id => $item ) {
			if ( isset( $posted_quantities[ $product_id ] ) ) {
				$items[ $product_id ]['quantity'] = max( 1, $posted_quantities[ $product_id ] );
			}

			if ( array_key_exists( $product_id, $posted_prices ) ) {
				$raw = trim( (string) $posted_prices[ $product_id ] );

				$items[ $product_id ]['negotiated_price'] = ( '' === $raw )
					? null
					: max( 0, (float) wc_format_decimal( $raw ) );
			}
		}

		$send_offer = ! empty( $_POST['send_counter_offer'] ) && B2B_Quote_DB::STATUS_WAITING_APPROVAL === $status;

		$update = array(
			'status'            => $status,
			'negotiation_notes' => $notes,
			'quote_data'        => wp_json_encode( array_values( $items ) ),
		);

		if ( $send_offer ) {
			// A fresh single-use token invalidates any link sent previously.
			$update['approval_token'] = B2B_Quote_DB::generate_token();
		} elseif ( B2B_Quote_DB::STATUS_WAITING_APPROVAL !== $status ) {
			$update['approval_token'] = '';
		}

		B2B_Quote_DB::update_quote( $quote_id, $update );

		$notice = 'saved';

		if ( $send_offer ) {
			$notice = B2B_Quote_Emails::send_client_counter_offer( $quote_id ) ? 'offer-sent' : 'offer-failed';
		}

		do_action( 'b2b_quote_updated', $quote_id, $status );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => self::MENU_SLUG,
					'quote_id'   => $quote_id,
					'b2b_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
