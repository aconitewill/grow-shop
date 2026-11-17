<?php
/**
 * Orders in wp-admin: the list (core's, with our columns and status
 * views), a custom order screen with its own action forms, CSV export,
 * packing slips and a dashboard widget.
 *
 * @package GrowShop
 */

defined( 'ABSPATH' ) || exit;

/**
 * The hidden order screen.
 */
function grs_orders_menu() {
	add_submenu_page( '', __( 'Order', 'grow-shop' ), __( 'Order', 'grow-shop' ), GRS_CAP, 'grow-shop-order', 'grs_render_order' );
}
add_action( 'admin_menu', 'grs_orders_menu' );

/**
 * Order screen URL.
 *
 * @param int   $id   Order.
 * @param array $args Extra args.
 * @return string
 */
function grs_order_url( $id, $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => 'grow-shop-order', 'id' => (int) $id ), $args ), admin_url( 'admin.php' ) );
}

/**
 * Orders open on our screen, not the post editor.
 *
 * @param string $link Link.
 * @param int    $id   Post.
 * @return string
 */
function grs_order_edit_link( $link, $id ) {
	return GRS_ORDER === get_post_type( $id ) ? grs_order_url( $id ) : $link;
}
add_filter( 'get_edit_post_link', 'grs_order_edit_link', 10, 2 );

/**
 * Anyone reaching post.php for an order goes to the order screen.
 */
function grs_redirect_post_editor() {
	$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( $id && GRS_ORDER === get_post_type( $id ) ) {
		wp_safe_redirect( grs_order_url( $id ) );
		exit;
	}
}
add_action( 'load-post.php', 'grs_redirect_post_editor' );

/**
 * List columns.
 *
 * @param array $cols Columns.
 * @return array
 */
function grs_order_columns( $cols ) {
	return array(
		'cb'           => $cols['cb'] ?? '<input type="checkbox">',
		'grs_number'   => __( 'Order', 'grow-shop' ),
		'grs_date'     => __( 'Date', 'grow-shop' ),
		'grs_customer' => __( 'Customer', 'grow-shop' ),
		'grs_items'    => __( 'Items', 'grow-shop' ),
		'grs_total'    => __( 'Total', 'grow-shop' ),
		'grs_status'   => __( 'Status', 'grow-shop' ),
	);
}
add_filter( 'manage_' . GRS_ORDER . '_posts_columns', 'grs_order_columns' );

/**
 * Column values.
 *
 * @param string $col Column.
 * @param int    $id  Order.
 */
function grs_order_column( $col, $id ) {
	$d = grs_order_data( $id );
	switch ( $col ) {
		case 'grs_number':
			echo '<a class="row-title" href="' . esc_url( grs_order_url( $id ) ) . '"><strong>' . esc_html( grs_order_number( $id ) ) . '</strong></a>';
			if ( 'test' === $d['mode'] ) {
				echo ' <span class="grs-pill grs-pill--test">' . esc_html__( 'Test', 'grow-shop' ) . '</span>';
			}
			break;
		case 'grs_date':
			echo esc_html( get_the_date( 'j M Y, H:i', $id ) );
			break;
		case 'grs_customer':
			echo esc_html( $d['customer']['name'] );
			if ( $d['address']['postal_code'] ?? '' ) {
				echo '<br><span class="grs-muted">' . esc_html( $d['address']['postal_code'] . ( 'GB' !== ( $d['address']['country'] ?? '' ) ? ', ' . $d['address']['country'] : '' ) ) . '</span>';
			}
			break;
		case 'grs_items':
			$n     = 0;
			$names = array();
			foreach ( $d['lines'] as $line ) {
				$n      += (int) $line['qty'];
				$names[] = $line['qty'] . ' &times; ' . esc_html( $line['name'] );
			}
			echo count( $names ) <= 3 ? implode( '<br>', $names ) : esc_html( sprintf( /* translators: %d: count */ _n( '%d item', '%d items', $n, 'grow-shop' ), $n ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			break;
		case 'grs_total':
			echo esc_html( grs_money( $d['total'], false ) );
			if ( $d['refunded'] ) {
				echo '<br><span class="grs-muted">' . esc_html( sprintf( /* translators: %s: amount */ __( '%s refunded', 'grow-shop' ), grs_money( $d['refunded'], false ) ) ) . '</span>';
			}
			break;
		case 'grs_status':
			grs_status_pill( $id );
			break;
	}
}
add_action( 'manage_' . GRS_ORDER . '_posts_custom_column', 'grs_order_column', 10, 2 );

/**
 * A coloured status label.
 *
 * @param int $id Order.
 */
function grs_status_pill( $id ) {
	$status = get_post_status( $id );
	$labels = grs_statuses();
	$label  = 'grs-paid' === $status ? __( 'To dispatch', 'grow-shop' ) : ( $labels[ $status ] ?? $status );
	echo '<span class="grs-pill grs-pill--' . esc_attr( str_replace( 'grs-', '', $status ) ) . '">' . esc_html( $label ) . '</span>';
}

/**
 * Row actions: just View (and bin for cancelled or test orders).
 *
 * @param array   $actions Actions.
 * @param WP_Post $post    Post.
 * @return array
 */
function grs_order_row_actions( $actions, $post ) {
	if ( GRS_ORDER !== $post->post_type ) {
		return $actions;
	}
	$out = array( 'view' => '<a href="' . esc_url( grs_order_url( $post->ID ) ) . '">' . esc_html__( 'Open', 'grow-shop' ) . '</a>' );
	if ( isset( $actions['trash'] ) && ( 'grs-cancelled' === $post->post_status || 'test' === grs_order_data( $post->ID )['mode'] ) ) {
		$out['trash'] = $actions['trash'];
	}
	if ( isset( $actions['untrash'] ) ) {
		$out['untrash'] = $actions['untrash'];
	}
	if ( isset( $actions['delete'] ) ) {
		$out['delete'] = $actions['delete'];
	}
	return $out;
}
add_filter( 'post_row_actions', 'grs_order_row_actions', 10, 2 );

/**
 * No bulk edit for orders; keep "Move to bin".
 *
 * @param array $actions Actions.
 * @return array
 */
function grs_order_bulk_actions( $actions ) {
	unset( $actions['edit'] );
	return $actions;
}
add_filter( 'bulk_actions-edit-' . GRS_ORDER, 'grs_order_bulk_actions' );

/**
 * Export button above the list.
 *
 * @param string $which top | bottom.
 */
function grs_order_list_tools( $which ) {
	if ( 'top' !== $which || GRS_ORDER !== ( get_current_screen()->post_type ?? '' ) ) {
		return;
	}
	$status = isset( $_GET['post_status'] ) ? sanitize_key( wp_unslash( $_GET['post_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$url    = wp_nonce_url( add_query_arg( array( 'action' => 'grs_export', 'status' => $status ), admin_url( 'admin-post.php' ) ), 'grs_export' );
	echo '<div class="alignleft actions"><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Export CSV', 'grow-shop' ) . '</a></div>';
}
add_action( 'manage_posts_extra_tablenav', 'grs_order_list_tools' );

/**
 * Newest first by default.
 *
 * @param WP_Query $q Query.
 */
function grs_order_list_order( $q ) {
	if ( is_admin() && $q->is_main_query() && GRS_ORDER === $q->get( 'post_type' ) && ! $q->get( 'orderby' ) ) {
		$q->set( 'orderby', 'date' );
		$q->set( 'order', 'DESC' );
	}
}
add_action( 'pre_get_posts', 'grs_order_list_order' );

/**
 * The order screen.
 */
function grs_render_order() {
	$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! $id || GRS_ORDER !== get_post_type( $id ) || 'grs-pending' === get_post_status( $id ) ) {
		echo '<div class="wrap"><h1>' . esc_html__( 'Order not found', 'grow-shop' ) . '</h1></div>';
		return;
	}
	$d    = grs_order_data( $id );
	$note = get_transient( 'grs_notice_' . get_current_user_id() );
	delete_transient( 'grs_notice_' . get_current_user_id() );
	$left = (int) $d['total'] - (int) $d['refunded'];
	$stripe_url = $d['payment_intent'] ? 'https://dashboard.stripe.com/' . ( 'test' === $d['mode'] ? 'test/' : '' ) . 'payments/' . rawurlencode( $d['payment_intent'] ) : '';
	?>
	<div class="wrap grs-order">
		<p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . GRS_ORDER ) ); ?>">&larr; <?php esc_html_e( 'All orders', 'grow-shop' ); ?></a></p>
		<h1 class="wp-heading-inline"><?php echo esc_html( sprintf( /* translators: %s: order number */ __( 'Order %s', 'grow-shop' ), grs_order_number( $id ) ) ); ?></h1>
		<?php grs_status_pill( $id ); ?>
		<?php if ( 'test' === $d['mode'] ) : ?><span class="grs-pill grs-pill--test"><?php esc_html_e( 'Test payment', 'grow-shop' ); ?></span><?php endif; ?>
		<p class="grs-muted"><?php echo esc_html( sprintf( /* translators: %s: date */ __( 'Paid %s', 'grow-shop' ), wp_date( 'l j F Y, H:i', $d['paid_at'] ? $d['paid_at'] : get_post_time( 'U', true, $id ) ) ) ); ?></p>
		<?php if ( $note ) : ?>
			<div class="notice notice-<?php echo esc_attr( $note[0] ); ?> is-dismissible"><p><?php echo esc_html( $note[1] ); ?></p></div>
		<?php endif; ?>

		<div class="grs-order__grid">
			<div class="grs-order__main">
				<div class="grs-card">
					<h2><?php esc_html_e( 'Items', 'grow-shop' ); ?></h2>
					<table class="widefat grs-items">
						<thead><tr><th><?php esc_html_e( 'Item', 'grow-shop' ); ?></th><th class="num"><?php esc_html_e( 'Qty', 'grow-shop' ); ?></th><th class="num"><?php esc_html_e( 'Each', 'grow-shop' ); ?></th><th class="num"><?php esc_html_e( 'Total', 'grow-shop' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( $d['lines'] as $line ) : ?>
							<tr>
								<td>
									<?php if ( $line['id'] && get_post( $line['id'] ) ) : ?>
										<a href="<?php echo esc_url( (string) get_edit_post_link( $line['id'] ) ); ?>"><?php echo esc_html( $line['name'] ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $line['name'] ); ?>
									<?php endif; ?>
									<?php if ( $line['options'] ) : ?><br><span class="grs-muted"><?php echo esc_html( $line['options'] ); ?></span><?php endif; ?>
								</td>
								<td class="num"><?php echo (int) $line['qty']; ?></td>
								<td class="num"><?php echo esc_html( grs_money( $line['unit'], false ) ); ?></td>
								<td class="num"><?php echo esc_html( grs_money( $line['total'], false ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
						<tfoot>
							<tr><td colspan="3" class="num"><?php esc_html_e( 'Items', 'grow-shop' ); ?></td><td class="num"><?php echo esc_html( grs_money( $d['subtotal'], false ) ); ?></td></tr>
							<tr><td colspan="3" class="num"><?php echo esc_html( $d['method_name'] . ( $d['zone_name'] ? ' (' . $d['zone_name'] . ')' : '' ) ); ?></td><td class="num"><?php echo esc_html( grs_money( $d['shipping'], false ) ); ?></td></tr>
							<tr><th colspan="3" class="num"><?php esc_html_e( 'Total', 'grow-shop' ); ?></th><th class="num"><?php echo esc_html( grs_money( $d['total'], false ) ); ?></th></tr>
							<?php if ( $d['refunded'] ) : ?>
								<tr><td colspan="3" class="num"><?php esc_html_e( 'Refunded', 'grow-shop' ); ?></td><td class="num">-<?php echo esc_html( grs_money( $d['refunded'], false ) ); ?></td></tr>
							<?php endif; ?>
						</tfoot>
					</table>
					<?php if ( $stripe_url ) : ?>
						<p><a href="<?php echo esc_url( $stripe_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'See the payment in Stripe', 'grow-shop' ); ?> &nearr;</a></p>
					<?php endif; ?>
				</div>

				<div class="grs-card">
					<h2><?php esc_html_e( 'Notes', 'grow-shop' ); ?></h2>
					<?php foreach ( array_reverse( (array) $d['notes'] ) as $n ) : ?>
						<div class="grs-note"><p><?php echo nl2br( esc_html( $n[2] ) ); ?></p><span class="grs-muted"><?php echo esc_html( wp_date( 'j M Y H:i', $n[0] ) . ', ' . $n[1] ); ?></span></div>
					<?php endforeach; ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php grs_order_form_fields( $id, 'note' ); ?>
						<textarea name="note" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'Private note, never shown to the customer', 'grow-shop' ); ?>"></textarea>
						<?php submit_button( __( 'Add note', 'grow-shop' ), 'secondary', 'submit', false ); ?>
					</form>
				</div>

				<div class="grs-card">
					<h2><?php esc_html_e( 'History', 'grow-shop' ); ?></h2>
					<ul class="grs-log">
						<?php foreach ( array_reverse( (array) $d['log'] ) as $row ) : ?>
							<li><span><?php echo esc_html( wp_date( 'j M Y H:i', $row[0] ) ); ?></span> <?php echo esc_html( $row[1] ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>

			<div class="grs-order__side">
				<div class="grs-card">
					<h2><?php esc_html_e( 'Customer', 'grow-shop' ); ?></h2>
					<p><strong><?php echo esc_html( $d['customer']['name'] ); ?></strong><br>
					<?php if ( $d['customer']['email'] ) : ?><a href="mailto:<?php echo esc_attr( $d['customer']['email'] ); ?>"><?php echo esc_html( $d['customer']['email'] ); ?></a><br><?php endif; ?>
					<?php echo esc_html( $d['customer']['phone'] ); ?></p>
					<h3><?php esc_html_e( 'Post to', 'grow-shop' ); ?></h3>
					<p class="grs-address"><?php echo implode( '<br>', array_map( 'esc_html', grs_address_lines( $d['address'] ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
					<p><a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'grs_slip', 'id' => $id ), admin_url( 'admin-post.php' ) ), 'grs_slip_' . $id ) ); ?>" target="_blank"><?php esc_html_e( 'Print packing slip', 'grow-shop' ); ?></a></p>
				</div>

				<?php if ( in_array( get_post_status( $id ), array( 'grs-paid', 'grs-dispatched' ), true ) ) : ?>
					<div class="grs-card">
						<h2><?php echo 'grs-dispatched' === get_post_status( $id ) ? esc_html__( 'Dispatched', 'grow-shop' ) : esc_html__( 'Dispatch', 'grow-shop' ); ?></h2>
						<?php if ( $d['dispatched_at'] ) : ?>
							<p class="grs-muted"><?php echo esc_html( wp_date( 'j M Y', $d['dispatched_at'] ) . ( $d['tracking'] ? ', ' . $d['tracking'] : '' ) ); ?></p>
						<?php endif; ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php grs_order_form_fields( $id, 'dispatch' ); ?>
							<p><label><?php esc_html_e( 'Tracking number or link (optional)', 'grow-shop' ); ?><br><input type="text" name="tracking" class="widefat" value="<?php echo esc_attr( $d['tracking'] ); ?>"></label></p>
							<p><label><input type="checkbox" name="email" value="1" <?php checked( ! $d['dispatched_at'] ); ?>> <?php esc_html_e( 'Email the customer', 'grow-shop' ); ?></label></p>
							<?php submit_button( $d['dispatched_at'] ? __( 'Update', 'grow-shop' ) : __( 'Mark dispatched', 'grow-shop' ), 'primary', 'submit', false ); ?>
						</form>
					</div>
				<?php endif; ?>

				<?php if ( $left > 0 && $d['payment_intent'] ) : ?>
					<div class="grs-card">
						<h2><?php esc_html_e( 'Refund', 'grow-shop' ); ?></h2>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Refund this amount to the customer\'s card? This can\'t be undone.', 'grow-shop' ) ); ?>');">
							<?php grs_order_form_fields( $id, 'refund' ); ?>
							<p><label><?php esc_html_e( 'Amount', 'grow-shop' ); ?><br><input type="text" inputmode="decimal" name="amount" class="small-text" value="<?php echo esc_attr( grs_money_field( $left ) ); ?>"></label></p>
							<p><label><input type="checkbox" name="restock" value="1"> <?php esc_html_e( 'Put the items back into stock', 'grow-shop' ); ?></label><br>
							<label><input type="checkbox" name="email" value="1" checked> <?php esc_html_e( 'Email the customer', 'grow-shop' ); ?></label></p>
							<?php submit_button( __( 'Refund', 'grow-shop' ), 'secondary', 'submit', false ); ?>
						</form>
					</div>
				<?php endif; ?>

				<div class="grs-card">
					<h2><?php esc_html_e( 'More', 'grow-shop' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php grs_order_form_fields( $id, 'resend' ); ?>
						<select name="email">
							<?php foreach ( grs_email_types() as $type => $info ) : ?>
								<option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $info[0] ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php submit_button( __( 'Resend', 'grow-shop' ), 'secondary', 'submit', false ); ?>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px">
						<?php grs_order_form_fields( $id, 'status' ); ?>
						<select name="status">
							<?php foreach ( grs_statuses() as $slug => $label ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( get_post_status( $id ), $slug ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php submit_button( __( 'Change status', 'grow-shop' ), 'secondary', 'submit', false ); ?>
						<p class="description"><?php esc_html_e( 'Changes the label only: no email, no refund.', 'grow-shop' ); ?></p>
					</form>
				</div>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Hidden fields for an order action form.
 *
 * @param int    $id   Order.
 * @param string $what Action.
 */
function grs_order_form_fields( $id, $what ) {
	wp_nonce_field( 'grs_order_' . $what . '_' . $id );
	echo '<input type="hidden" name="action" value="grs_order_action"><input type="hidden" name="what" value="' . esc_attr( $what ) . '"><input type="hidden" name="id" value="' . esc_attr( $id ) . '">';
}

/**
 * Handle an order action.
 */
function grs_order_action() {
	$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	$what = isset( $_POST['what'] ) ? sanitize_key( wp_unslash( $_POST['what'] ) ) : '';
	grs_admin_check( 'grs_order_' . $what . '_' . $id );
	if ( GRS_ORDER !== get_post_type( $id ) ) {
		wp_die( esc_html__( 'Order not found.', 'grow-shop' ) );
	}
	$msg = array( 'success', __( 'Done.', 'grow-shop' ) );
	switch ( $what ) {
		case 'note':
			$text = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
			if ( '' !== $text ) {
				$d            = grs_order_data( $id );
				$d['notes'][] = array( time(), wp_get_current_user()->display_name, $text );
				grs_save_order_data( $id, $d );
				$msg = array( 'success', __( 'Note added.', 'grow-shop' ) );
			}
			break;
		case 'dispatch':
			grs_dispatch_order( $id, isset( $_POST['tracking'] ) ? sanitize_text_field( wp_unslash( $_POST['tracking'] ) ) : '', ! empty( $_POST['email'] ) );
			$msg = array( 'success', empty( $_POST['email'] ) ? __( 'Marked dispatched.', 'grow-shop' ) : __( 'Marked dispatched and the customer emailed.', 'grow-shop' ) );
			break;
		case 'refund':
			$res = grs_refund_order( $id, grs_parse_money( isset( $_POST['amount'] ) ? sanitize_text_field( wp_unslash( $_POST['amount'] ) ) : '' ), ! empty( $_POST['restock'] ), ! empty( $_POST['email'] ) );
			$msg = is_wp_error( $res ) ? array( 'error', __( 'Refund failed:', 'grow-shop' ) . ' ' . $res->get_error_message() ) : array( 'success', __( 'Refunded through Stripe.', 'grow-shop' ) );
			break;
		case 'resend':
			$type = isset( $_POST['email'] ) ? sanitize_key( wp_unslash( $_POST['email'] ) ) : '';
			$ok   = isset( grs_email_types()[ $type ] ) && grs_send_email( $type, $id, 'new_order' === $type ? '' : grs_order_data( $id )['customer']['email'] );
			$msg  = $ok ? array( 'success', __( 'Email sent.', 'grow-shop' ) ) : array( 'error', __( 'The email could not be sent.', 'grow-shop' ) );
			break;
		case 'status':
			$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
			if ( isset( grs_statuses()[ $status ] ) ) {
				wp_update_post( array( 'ID' => $id, 'post_status' => $status ) );
				/* translators: %s: status */
				grs_order_log( $id, sprintf( __( 'Status changed to %s by hand.', 'grow-shop' ), grs_statuses()[ $status ] ) );
			}
			break;
	}
	set_transient( 'grs_notice_' . get_current_user_id(), $msg, 300 );
	wp_safe_redirect( grs_order_url( $id ) );
	exit;
}
add_action( 'admin_post_grs_order_action', 'grs_order_action' );

/**
 * Packing slip: a plain printable page.
 */
function grs_packing_slip() {
	$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	check_admin_referer( 'grs_slip_' . $id );
	if ( ! current_user_can( GRS_CAP ) || GRS_ORDER !== get_post_type( $id ) ) {
		wp_die( esc_html__( 'Not allowed.', 'grow-shop' ) );
	}
	$d = grs_order_data( $id );
	include grs_template( 'packing-slip.php' );
	exit;
}
add_action( 'admin_post_grs_slip', 'grs_packing_slip' );

/**
 * CSV export of orders (one row per order).
 */
function grs_export_csv() {
	check_admin_referer( 'grs_export' );
	if ( ! current_user_can( GRS_CAP ) ) {
		wp_die( esc_html__( 'Not allowed.', 'grow-shop' ) );
	}
	$status   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
	$statuses = isset( grs_statuses()[ $status ] ) ? array( $status ) : array_keys( grs_statuses() );
	$ids      = get_posts( array( 'post_type' => GRS_ORDER, 'post_status' => $statuses, 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'date', 'order' => 'ASC' ) );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=orders-' . gmdate( 'Y-m-d' ) . '.csv' );
	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- so Excel reads UTF-8.
	fputcsv( $out, array( 'Order', 'Date', 'Status', 'Mode', 'Name', 'Email', 'Phone', 'Address', 'Postcode', 'Country', 'Items', 'Quantity', 'Items total', 'Postage method', 'Postage', 'Total', 'Refunded', 'Tracking', 'Stripe payment' ) );
	$labels = grs_statuses();
	foreach ( $ids as $id ) {
		$d     = grs_order_data( $id );
		$items = array();
		$qty   = 0;
		foreach ( $d['lines'] as $line ) {
			$items[] = $line['qty'] . ' x ' . $line['name'] . ( $line['options'] ? ' (' . $line['options'] . ')' : '' );
			$qty    += (int) $line['qty'];
		}
		$addr = $d['address'];
		fputcsv(
			$out,
			grs_csv_safe(
				array(
					grs_order_number( $id ),
					get_the_date( 'Y-m-d H:i', $id ),
					$labels[ get_post_status( $id ) ] ?? '',
					$d['mode'],
					$d['customer']['name'],
					$d['customer']['email'],
					$d['customer']['phone'],
					implode( ', ', array_filter( array( $addr['line1'] ?? '', $addr['line2'] ?? '', $addr['city'] ?? '', $addr['state'] ?? '' ) ) ),
					$addr['postal_code'] ?? '',
					$addr['country'] ?? '',
					implode( '; ', $items ),
					$qty,
					grs_money_field( $d['subtotal'] ),
					$d['method_name'],
					grs_money_field( $d['shipping'] ),
					grs_money_field( $d['total'] ),
					grs_money_field( $d['refunded'] ),
					$d['tracking'],
					$d['payment_intent'],
				)
			)
		);
	}
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}
add_action( 'admin_post_grs_export', 'grs_export_csv' );

/**
 * Stop spreadsheet formulas sneaking in through customer-typed text.
 *
 * @param array $row Values.
 * @return array
 */
function grs_csv_safe( $row ) {
	foreach ( $row as &$v ) {
		$v = (string) $v;
		if ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@' ), true ) && ! is_numeric( $v ) ) {
			$v = "'" . $v;
		}
	}
	return $row;
}

/**
 * Dashboard widget: orders waiting to be posted.
 */
function grs_dashboard_widget() {
	if ( ! current_user_can( GRS_CAP ) ) {
		return;
	}
	wp_add_dashboard_widget(
		'grs_dashboard',
		__( 'Shop', 'grow-shop' ),
		function () {
			$count = (int) ( wp_count_posts( GRS_ORDER )->{'grs-paid'} ?? 0 );
			$url   = admin_url( 'edit.php?post_type=' . GRS_ORDER . '&post_status=grs-paid' );
			echo '<p style="font-size:15px">';
			echo $count
				? '<a href="' . esc_url( $url ) . '"><strong>' . esc_html( sprintf( /* translators: %d: count */ _n( '%d order to dispatch', '%d orders to dispatch', $count, 'grow-shop' ), $count ) ) . '</strong></a>'
				: esc_html__( 'No orders waiting to be posted.', 'grow-shop' );
			echo '</p>';
			$mode = grs_get( 'general', 'mode' );
			if ( 'on' !== $mode ) {
				echo '<p class="description">' . esc_html( 'off' === $mode ? __( 'The shop is switched off.', 'grow-shop' ) : __( 'The shop is visible to admins only.', 'grow-shop' ) ) . '</p>';
			}
		}
	);
}
add_action( 'wp_dashboard_setup', 'grs_dashboard_widget' );

/**
 * A count bubble on the Shop menu for orders to dispatch.
 */
function grs_menu_bubble() {
	global $menu;
	$count = (int) ( wp_count_posts( GRS_ORDER )->{'grs-paid'} ?? 0 );
	if ( ! $count ) {
		return;
	}
	foreach ( (array) $menu as $i => $item ) {
		if ( 'edit.php?post_type=' . GRS_ORDER === ( $item[2] ?? '' ) ) {
			$menu[ $i ][0] .= ' <span class="awaiting-mod"><span class="pending-count">' . (int) $count . '</span></span>'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		}
	}
}
add_action( 'admin_menu', 'grs_menu_bubble', 99 );
