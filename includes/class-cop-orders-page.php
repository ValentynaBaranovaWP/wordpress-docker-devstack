<?php

defined( 'ABSPATH' ) || exit;

class COP_Orders_Page {

	const PER_PAGE = 20;

	public static function init() {
		add_action( 'wp_ajax_cop_refresh_order_status', array( __CLASS__, 'ajax_refresh_status' ) );
	}

	public static function render() {
		if ( ! current_user_can( COP_Role::CAP_VIEW_OWN_ORDERS ) ) {
			wp_die( esc_html( 'Access denied.' ), 403 );
		}

		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;

		if ( $order_id > 0 ) {
			self::render_detail( $order_id );
		} else {
			self::render_list();
		}
	}

	private static function get_own_order_or_die( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order || (int) $order->get_customer_id() !== get_current_user_id() ) {
			COP_Audit_Log::log( COP_Audit_Log::EVENT_FOREIGN_ORDER, $order_id );
			wp_die(
				esc_html( 'Access denied: this order does not belong to your account.' ),
				esc_html( 'Access denied' ),
				array( 'response' => 403 )
			);
		}

		return $order;
	}

	private static function render_list() {
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

		$results = wc_get_orders(
			array(
				'type'        => 'shop_order',
				'customer_id' => get_current_user_id(),
				'limit'       => self::PER_PAGE,
				'page'        => $paged,
				'paginate'    => true,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);
		?>
		<div class="wrap cop-wrap">
			<h1>My Orders</h1>

			<?php if ( empty( $results->orders ) ) : ?>
				<div class="cop-card"><p>You have no orders yet.</p></div>
			<?php else : ?>
				<table class="widefat striped cop-table">
					<thead>
						<tr>
							<th>Order</th>
							<th>Date</th>
							<th>Status</th>
							<th>Items</th>
							<th>Total</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $results->orders as $order ) : ?>
							<tr>
								<td><strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong></td>
								<td><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></td>
								<td><?php self::status_badge( $order ); ?></td>
								<td><?php echo esc_html( $order->get_item_count() ); ?></td>
								<td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
								<td>
									<a class="button button-small" href="<?php echo esc_url( self::detail_url( $order->get_id() ) ); ?>">View</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php self::pagination( $paged, (int) $results->max_num_pages ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_detail( $order_id ) {
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'cop_view_order_' . $order_id ) ) {
			wp_safe_redirect( COP_Admin_Menu::cabinet_url() );
			exit;
		}

		$order = self::get_own_order_or_die( $order_id );
		?>
		<div class="wrap cop-wrap">
			<p><a href="<?php echo esc_url( COP_Admin_Menu::cabinet_url() ); ?>">&larr; Back to order list</a></p>

			<h1>Order #<?php echo esc_html( $order->get_order_number() ); ?></h1>

			<div class="cop-card cop-order-meta">
				<p>
					<span class="cop-label">Status:</span>
					<span id="cop-order-status" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>"><?php self::status_badge( $order ); ?></span>
					<button type="button" class="button button-small" id="cop-refresh-status">Refresh status</button>
				</p>
				<p>
					<span class="cop-label">Order date:</span>
					<?php echo esc_html( wc_format_datetime( $order->get_date_created(), get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?>
				</p>
			</div>

			<div class="cop-card">
				<h2>Order items</h2>
				<table class="widefat striped cop-table">
					<thead>
						<tr>
							<th>Product</th>
							<th>Quantity</th>
							<th>Total</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $order->get_items() as $item ) : ?>
							<tr>
								<td><?php echo esc_html( $item->get_name() ); ?></td>
								<td><?php echo esc_html( $item->get_quantity() ); ?></td>
								<td><?php echo wp_kses_post( wc_price( $item->get_total(), array( 'currency' => $order->get_currency() ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<tfoot>
						<?php foreach ( $order->get_order_item_totals() as $total_row ) : ?>
							<tr>
								<th colspan="2"><?php echo esc_html( $total_row['label'] ); ?></th>
								<td><?php echo wp_kses_post( $total_row['value'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tfoot>
				</table>
			</div>

			<div class="cop-columns">
				<div class="cop-card">
					<h2>Billing address</h2>
					<address><?php echo wp_kses_post( $order->get_formatted_billing_address( 'Not provided' ) ); ?></address>
				</div>
				<div class="cop-card">
					<h2>Shipping address</h2>
					<address><?php echo wp_kses_post( $order->get_formatted_shipping_address( 'Not provided' ) ); ?></address>
				</div>
			</div>

			<?php
			$notes = wc_get_order_notes(
				array(
					'order_id' => $order->get_id(),
					'type'     => 'customer',
				)
			);
			if ( $notes ) :
				?>
				<div class="cop-card">
					<h2>Order updates</h2>
					<ul class="cop-notes">
						<?php foreach ( $notes as $note ) : ?>
							<li>
								<span class="cop-note-date"><?php echo esc_html( wc_format_datetime( $note->date_created ) ); ?></span>
								<?php echo wp_kses_post( wpautop( $note->content ) ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function ajax_refresh_status() {
		check_ajax_referer( 'cop_order_status', 'nonce' );

		if ( ! current_user_can( COP_Role::CAP_VIEW_OWN_ORDERS ) ) {
			wp_send_json_error( array( 'message' => 'Access denied.' ), 403 );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$order    = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order || (int) $order->get_customer_id() !== get_current_user_id() ) {
			COP_Audit_Log::log( COP_Audit_Log::EVENT_FOREIGN_ORDER, $order_id );
			wp_send_json_error( array( 'message' => 'Access denied.' ), 403 );
		}

		wp_send_json_success(
			array(
				'status' => $order->get_status(),
				'label'  => wc_get_order_status_name( $order->get_status() ),
			)
		);
	}

	private static function detail_url( $order_id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'page'     => COP_Admin_Menu::PAGE_ORDERS,
					'order_id' => (int) $order_id,
				),
				admin_url( 'admin.php' )
			),
			'cop_view_order_' . (int) $order_id
		);
	}

	private static function status_badge( WC_Order $order ) {
		printf(
			'<span class="cop-status cop-status--%1$s">%2$s</span>',
			esc_attr( $order->get_status() ),
			esc_html( wc_get_order_status_name( $order->get_status() ) )
		);
	}

	private static function pagination( $current, $total_pages ) {
		if ( $total_pages <= 1 ) {
			return;
		}

		$links = paginate_links(
			array(
				'base'    => add_query_arg( 'paged', '%#%' ),
				'format'  => '',
				'current' => $current,
				'total'   => $total_pages,
				'type'    => 'plain',
			)
		);

		if ( $links ) {
			echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( $links ) . '</div></div>';
		}
	}
}
