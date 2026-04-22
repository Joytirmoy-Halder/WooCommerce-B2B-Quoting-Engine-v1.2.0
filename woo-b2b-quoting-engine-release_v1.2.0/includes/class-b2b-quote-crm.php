<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class B2B_Quote_CRM {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_crm_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		
		// Handle form submissions in CRM
		add_action( 'admin_post_b2b_quote_update', array( $this, 'handle_quote_update' ) );
	}

	public function add_crm_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'B2B Quotes', 'woo-b2b-quote' ),
			__( 'B2B Quotes', 'woo-b2b-quote' ),
			'manage_woocommerce',
			'b2b-quotes-crm',
			array( $this, 'render_crm_dashboard' )
		);
	}

	public function enqueue_admin_scripts( $hook ) {
		if ( $hook !== 'woocommerce_page_b2b-quotes-crm' ) {
			return;
		}
		// Register a dummy CSS for Phase 4 color handling later
		wp_register_style( 'b2b-quote-admin-css', B2B_QUOTE_PLUGIN_URL . 'assets/css/b2b-quote-admin.css', array(), B2B_QUOTE_VERSION );
		wp_enqueue_style( 'b2b-quote-admin-css' );
	}

	public function render_crm_dashboard() {
		// Handle single view vs list view
		$view_id = isset( $_GET['quote_id'] ) ? absint( $_GET['quote_id'] ) : 0;

		if ( $view_id ) {
			$this->render_single_quote( $view_id );
		} else {
			$this->render_quotes_list();
		}
	}

	private function render_quotes_list() {
		global $wpdb;
		$table_name = B2B_Quote_DB::get_table_name();
		
		$quotes = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY created_at DESC" );
		
		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'B2B Quote Requests', 'woo-b2b-quote' ) . '</h1>';
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr><th>ID</th><th>Client Name</th><th>Company</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>';
		echo '<tbody>';
		
		if ( $quotes ) {
			foreach ( $quotes as $quote ) {
				$view_url = admin_url( 'admin.php?page=b2b-quotes-crm&quote_id=' . $quote->id );
				echo '<tr>';
				echo '<td>#' . esc_html( $quote->id ) . '</td>';
				echo '<td>' . esc_html( $quote->client_name ) . '<br><small>' . esc_html( $quote->client_email ) . '</small></td>';
				echo '<td>' . esc_html( $quote->client_company ) . '</td>';
				echo '<td>' . esc_html( ucfirst( $quote->status ) ) . '</td>';
				echo '<td>' . esc_html( $quote->created_at ) . '</td>';
				echo '<td><a href="' . esc_url( $view_url ) . '" class="button button-primary">View</a></td>';
				echo '</tr>';
			}
		} else {
			echo '<tr><td colspan="6">No quote requests found.</td></tr>';
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	private function render_single_quote( $quote_id ) {
		global $wpdb;
		$table_name = B2B_Quote_DB::get_table_name();
		$quote = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $quote_id ) );

		if ( ! $quote ) {
			echo '<div class="wrap"><div class="error"><p>Quote not found.</p></div></div>';
			return;
		}

		$quote_data = json_decode( $quote->quote_data, true );
		?>
		<div class="wrap b2b-quote-single-view">
			<h1>Quote Request #<?php echo esc_html( $quote->id ); ?></h1>
			
			<div class="b2b-quote-panels" style="display:flex; gap:20px; margin-top:20px;">
				<div class="b2b-quote-client-info" style="flex:1; background:#fff; padding:20px; border:1px solid #ccd0d4;">
					<h2>Client Details</h2>
					<p><strong>Name:</strong> <?php echo esc_html( $quote->client_name ); ?></p>
					<p><strong>Email:</strong> <a href="mailto:<?php echo esc_attr( $quote->client_email ); ?>"><?php echo esc_html( $quote->client_email ); ?></a></p>
					<p><strong>Company:</strong> <?php echo esc_html( $quote->client_company ); ?></p>
					<p><strong>Technical Reqs:</strong><br><?php echo nl2br( esc_html( $quote->technical_reqs ) ); ?></p>
				</div>

				<div style="flex:2; background:#fff; padding:20px; border:1px solid #ccd0d4;">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="b2b-quote-negotiation-form">
						<input type="hidden" name="action" value="b2b_quote_update">
						<input type="hidden" name="quote_id" value="<?php echo esc_attr( $quote->id ); ?>">
						<?php wp_nonce_field( 'b2b_quote_update_nonce', 'b2b_nonce' ); ?>

						<h2>Requested Products</h2>
						<table class="wp-list-table widefat fixed striped" style="margin-bottom:20px;">
							<thead>
								<tr>
									<th>Product</th>
									<th>Price</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $quote_data as $item ) : 
									$product = wc_get_product( $item['product_id'] );
									if ( ! $product ) continue;
								?>
								<tr>
									<td><?php echo esc_html( $product->get_name() ); ?></td>
									<td><?php echo wc_price( $product->get_price() ); ?></td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>

						<h2>Notes & Status</h2>
						<table class="form-table">
							<tr>
								<th><label for="status">Status</label></th>
								<td>
									<select name="status" id="status">
										<option value="pending" <?php selected( $quote->status, 'pending' ); ?>>Pending</option>
										<option value="waiting_approval" <?php selected( $quote->status, 'waiting_approval' ); ?>>Waiting for approval</option>
										<option value="accepted" <?php selected( $quote->status, 'accepted' ); ?>>Accepted</option>
										<option value="rejected" <?php selected( $quote->status, 'rejected' ); ?>>Rejected</option>
									</select>
								</td>
							</tr>
							<tr>
								<th><label for="negotiation_notes">Store Owner Notes</label></th>
								<td>
									<textarea name="negotiation_notes" id="negotiation_notes" rows="5" class="large-text"><?php echo esc_html( $quote->negotiation_notes ); ?></textarea>
									<p class="description">These notes are for your internal records.</p>
								</td>
							</tr>
						</table>
						
						<?php submit_button( 'Save Changes' ); ?>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	public function handle_quote_update() {
		if ( ! isset( $_POST['b2b_nonce'] ) || ! wp_verify_nonce( $_POST['b2b_nonce'], 'b2b_quote_update_nonce' ) ) {
			wp_die( 'Security check failed.' );
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Unauthorized.' );
		}

		$quote_id = absint( $_POST['quote_id'] );
		$status = sanitize_text_field( $_POST['status'] );
		$negotiation_notes = sanitize_textarea_field( $_POST['negotiation_notes'] );
		$negotiated_prices = isset( $_POST['negotiated_prices'] ) ? $_POST['negotiated_prices'] : array();

		global $wpdb;
		$table_name = B2B_Quote_DB::get_table_name();
		
		$quote = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $quote_id ) );
		if ( ! $quote ) {
			wp_die( 'Quote not found.' );
		}

		$quote_data = json_decode( $quote->quote_data, true );

		$wpdb->update(
			$table_name,
			array(
				'status' => $status,
				'negotiation_notes' => $negotiation_notes
			),
			array( 'id' => $quote_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		wp_redirect( admin_url( 'admin.php?page=b2b-quotes-crm&quote_id=' . $quote_id . '&updated=true' ) );
		exit;
	}
}
