<?php
/**
 * Orders: a private post type. A checkout makes a hidden "pending" post
 * holding the priced basket; it becomes an order (Paid, with a number)
 * only when Stripe confirms payment, through the webhook or the
 * thank-you page's check, whichever comes first. Pending posts that are
 * never paid are deleted, so abandoned baskets leave no clutter.
 *
 * @package GrowShop
 */

defined( 'ABSPATH' ) || exit;

/**
 * Order statuses shown in wp-admin.
 *
 * @return array slug => label
 */
function grs_statuses() {
	return array(
		'grs-paid'       => __( 'Paid', 'grow-shop' ),
		'grs-dispatched' => __( 'Dispatched', 'grow-shop' ),
		'grs-refunded'   => __( 'Refunded', 'grow-shop' ),
		'grs-cancelled'  => __( 'Cancelled', 'grow-shop' ),
	);
}

/**
 * Register the post type and statuses.
 */
function grs_register_orders() {
	register_post_type(
		GRS_ORDER,
		array(
			'labels'              => array(
				'name'               => __( 'Orders', 'grow-shop' ),
				'singular_name'      => __( 'Order', 'grow-shop' ),
				'menu_name'          => __( 'Shop', 'grow-shop' ),
				'all_items'          => __( 'Orders', 'grow-shop' ),
				'search_items'       => __( 'Search orders', 'grow-shop' ),
				'not_found'          => __( 'No orders yet.', 'grow-shop' ),
				'not_found_in_trash' => __( 'No orders in the bin.', 'grow-shop' ),
				'edit_item'          => __( 'Order', 'grow-shop' ),
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => false,
			'menu_icon'           => 'dashicons-cart',
			'menu_position'       => 26,
			'supports'            => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'query_var'           => false,
			'rewrite'             => false,
			'map_meta_cap'        => false,
			'capabilities'        => array(
				'edit_post'          => GRS_CAP,
				'read_post'          => GRS_CAP,
				'delete_post'        => GRS_CAP,
				'edit_posts'         => GRS_CAP,
				'edit_others_posts'  => GRS_CAP,
				'delete_posts'       => GRS_CAP,
				'delete_others_posts' => GRS_CAP,
				'publish_posts'      => GRS_CAP,
				'read_private_posts' => GRS_CAP,
				'create_posts'       => 'do_not_allow',
			),
		)
	);

	register_post_status(
		'grs-pending',
		array(
			'label'                     => __( 'Checkout in progress', 'grow-shop' ),
			'public'                    => false,
			'internal'                  => true,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => false,
			'show_in_admin_status_list' => false,
		)
	);
	foreach ( grs_statuses() as $slug => $label ) {
		register_post_status(
			$slug,
			array(
				'label'                     => $label,
				'public'                    => false,
				'protected'                 => true,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: count */
				'label_count'               => array( $label . ' <span class="count">(%s)</span>', $label . ' <span class="count">(%s)</span>' ),
			)
		);
	}
}
add_action( 'init', 'grs_register_orders' );

/**
 * An order's stored data, with every key present.
 *
 * @param int $id Order.
 * @return array
 */
function grs_order_data( $id ) {
	$data = get_post_meta( $id, '_grs_data', true );
	return array_merge(
		array(
			'lines'          => array(),
			'subtotal'       => 0,
			'shipping'       => 0,
			'total'          => 0,
			'currency'       => 'GBP',
			'zone_name'      => '',
			'method_name'    => '',
			'mode'           => 'test',
			'session'        => '',
			'payment_intent' => '',
			'customer'       => array( 'name' => '', 'email' => '', 'phone' => '' ),
			'address'        => array(),
			'refunded'       => 0,
			'refunds'        => array(),
			'tracking'       => '',
			'notes'          => array(),
			'log'            => array(),
			'paid_at'        => 0,
			'dispatched_at'  => 0,
			'return_url'     => '',
		),
		is_array( $data ) ? $data : array()
	);
}

/**
 * Save an order's data.
 *
 * @param int   $id   Order.
 * @param array $data Data.
 */
function grs_save_order_data( $id, $data ) {
	update_post_meta( $id, '_grs_data', $data );
}

/**
 * Add a line to an order's history.
 *
 * @param int    $id   Order.
 * @param string $text What happened.
 */
function grs_order_log( $id, $text ) {
	$data          = grs_order_data( $id );
	$data['log'][] = array( time(), $text );
	grs_save_order_data( $id, $data );
}

/**
 * The order number shown everywhere (prefix + number).
 *
 * @param int $id Order.
 * @return string
 */
function grs_order_number( $id ) {
	$n = get_post_meta( $id, '_grs_number', true );
	return $n ? (string) $n : '#' . $id;
}

/**
 * Take the next order number, safely when two orders land at once.
 *
 * @return string
 */
function grs_next_number() {
	global $wpdb;
	add_option( 'grow_shop_next_number', 1001, '', false );
	$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = LAST_INSERT_ID( option_value + 1 ) WHERE option_name = %s", 'grow_shop_next_number' ) );
	$next = (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' );
	wp_cache_delete( 'grow_shop_next_number', 'options' );
	wp_cache_delete( 'notoptions', 'options' );
	return (string) grs_get( 'general', 'prefix' ) . ( $next - 1 );
}

/**
 * Start a checkout: price the basket, keep it as a pending order, and
 * create a Stripe Checkout Session.
 *
 * @param array  $items      Clean items.
 * @param int    $zone       Zone index.
 * @param int    $method     Method index.
 * @param string $return_url Page to come back to (same site only).
 * @return array|WP_Error array( url ).
 */
function grs_start_checkout( $items, $zone, $method, $return_url ) {
	$q = grs_quote( $items, $zone, $method );
	if ( ! $q['ok'] ) {
		return new WP_Error( 'grs_basket', $q['errors'] ? implode( ' ', $q['errors'] ) : __( 'Your basket is empty.', 'grow-shop' ), array( 'quote' => $q ) );
	}
	$zones   = (array) grs_get( 'shipping', 'zones' );
	$zone_d  = $zones[ $q['zone'] ];
	$method_d = $zone_d['methods'][ $q['method'] ];
	$countries = grs_zone_countries( $q['zone'] );
	if ( ! $countries ) {
		return new WP_Error( 'grs_zone', __( 'That postage area has no countries set up.', 'grow-shop' ) );
	}

	$return_url = wp_validate_redirect( esc_url_raw( $return_url ), home_url( '/' ) );
	$return_url = remove_query_arg( array( 'grs', 'grs_session' ), $return_url );

	$lines = array();
	foreach ( $q['lines'] as $line ) {
		if ( empty( $line['remove'] ) ) {
			$lines[] = array_intersect_key( $line, array_flip( array( 'id', 'type', 'name', 'opts', 'options', 'qty', 'unit', 'total', 'image_big' ) ) );
		}
	}

	$order_id = wp_insert_post(
		array(
			'post_type'   => GRS_ORDER,
			'post_status' => 'grs-pending',
			'post_title'  => __( 'Checkout in progress', 'grow-shop' ),
		),
		true
	);
	if ( is_wp_error( $order_id ) ) {
		return $order_id;
	}
	$data = grs_order_data( $order_id );
	$data = array_merge(
		$data,
		array(
			'lines'       => $lines,
			'subtotal'    => $q['subtotal'],
			'shipping'    => $q['shipping'],
			'total'       => $q['total'],
			'currency'    => (string) grs_get( 'general', 'currency' ),
			'zone_name'   => $zone_d['name'],
			'method_name' => $method_d['name'],
			'mode'        => grs_mode(),
			'return_url'  => $return_url,
		)
	);
	grs_save_order_data( $order_id, $data );

	$currency   = strtolower( $data['currency'] );
	$line_items = array();
	foreach ( $lines as $line ) {
		$product = array( 'name' => $line['name'] . ( $line['options'] ? ' (' . $line['options'] . ')' : '' ) );
		if ( $line['image_big'] && 0 === strpos( $line['image_big'], 'https://' ) ) {
			$product['images'] = array( $line['image_big'] );
		}
		$label = grs_type_label( $line['type'] );
		if ( $label ) {
			$product['description'] = $label;
		}
		$line_items[] = array(
			'quantity'   => $line['qty'],
			'price_data' => array(
				'currency'     => $currency,
				'unit_amount'  => $line['unit'],
				'product_data' => $product,
			),
		);
	}

	$sep    = false === strpos( $return_url, '?' ) ? '?' : '&';
	$params = array(
		'mode'                        => 'payment',
		'line_items'                  => $line_items,
		'shipping_address_collection' => array( 'allowed_countries' => $countries ),
		'shipping_options'            => array(
			array(
				'shipping_rate_data' => array(
					'display_name' => $method_d['name'],
					'type'         => 'fixed_amount',
					'fixed_amount' => array(
						'amount'   => $q['shipping'],
						'currency' => $currency,
					),
				),
			),
		),
		'success_url'                 => $return_url . $sep . 'grs=thanks&grs_session={CHECKOUT_SESSION_ID}',
		'cancel_url'                  => $return_url . $sep . 'grs=cancelled',
		'client_reference_id'         => (string) $order_id,
		'metadata'                    => array( 'grs_order' => (string) $order_id, 'site' => (string) wp_parse_url( home_url(), PHP_URL_HOST ) ),
		'payment_intent_data'         => array(
			'description' => grs_shop_name() . ' order',
			'metadata'    => array( 'grs_order' => (string) $order_id ),
		),
		'expires_at'                  => time() + 31 * MINUTE_IN_SECONDS,
		'billing_address_collection'  => 'auto',
	);
	if ( grs_get( 'checkout', 'collect_phone' ) ) {
		$params['phone_number_collection'] = array( 'enabled' => 'true' );
	}
	$terms = (int) grs_get( 'checkout', 'terms_page' );
	if ( $terms && get_permalink( $terms ) ) {
		$params['custom_text'] = array(
			'submit' => array(
				/* translators: %s: link to terms */
				'message' => sprintf( __( 'By paying you agree to the terms of sale: %s', 'grow-shop' ), get_permalink( $terms ) ),
			),
		);
	}
	$params = (array) apply_filters( 'grow_shop_checkout_params', $params, $order_id, $data );

	$session = grs_stripe( 'POST', 'checkout/sessions', $params, '', 'grs-checkout-' . $order_id . '-' . wp_parse_url( home_url(), PHP_URL_HOST ) );
	if ( is_wp_error( $session ) ) {
		wp_delete_post( $order_id, true );
		grs_log( 'Checkout failed: ' . $session->get_error_message() );
		return $session;
	}
	$data            = grs_order_data( $order_id );
	$data['session'] = $session['id'];
	grs_save_order_data( $order_id, $data );
	update_post_meta( $order_id, '_grs_session', $session['id'] );
	return array( 'url' => $session['url'] );
}

/**
 * Find the pending or completed order behind a Checkout Session.
 *
 * @param array $session Stripe session object.
 * @return int Order ID or 0.
 */
function grs_order_for_session( $session ) {
	$id = isset( $session['metadata']['grs_order'] ) ? absint( $session['metadata']['grs_order'] ) : 0;
	if ( ! $id || GRS_ORDER !== get_post_type( $id ) || get_post_meta( $id, '_grs_session', true ) !== $session['id'] ) {
		return 0;
	}
	return $id;
}

/**
 * Turn a paid Checkout Session into an order, once. Safe to call from the
 * webhook and the thank-you page at the same moment: only the call that
 * moves the post from pending to paid does the work.
 *
 * @param array $session Stripe Checkout Session.
 * @return int Order ID, or 0 if not (yet) paid or not ours.
 */
function grs_complete_session( $session ) {
	global $wpdb;
	$id = grs_order_for_session( $session );
	if ( ! $id ) {
		return 0;
	}
	if ( ! in_array( $session['payment_status'] ?? '', array( 'paid', 'no_payment_required' ), true ) ) {
		return 0;
	}

	$won = $wpdb->update( $wpdb->posts, array( 'post_status' => 'grs-paid' ), array( 'ID' => $id, 'post_status' => 'grs-pending' ) );
	clean_post_cache( $id );
	if ( 1 !== (int) $won ) {
		return $id; // Already done by the other caller.
	}

	$data    = grs_order_data( $id );
	$details = (array) ( $session['customer_details'] ?? array() );
	$ship    = (array) ( $session['shipping_details'] ?? ( $session['collected_information']['shipping_details'] ?? array() ) );
	$addr    = (array) ( $ship['address'] ?? ( $details['address'] ?? array() ) );

	$data['customer']       = array(
		'name'  => sanitize_text_field( (string) ( $details['name'] ?? ( $ship['name'] ?? '' ) ) ),
		'email' => sanitize_email( (string) ( $details['email'] ?? '' ) ),
		'phone' => sanitize_text_field( (string) ( $details['phone'] ?? '' ) ),
	);
	$data['address']        = array(
		'name'        => sanitize_text_field( (string) ( $ship['name'] ?? $data['customer']['name'] ) ),
		'line1'       => sanitize_text_field( (string) ( $addr['line1'] ?? '' ) ),
		'line2'       => sanitize_text_field( (string) ( $addr['line2'] ?? '' ) ),
		'city'        => sanitize_text_field( (string) ( $addr['city'] ?? '' ) ),
		'state'       => sanitize_text_field( (string) ( $addr['state'] ?? '' ) ),
		'postal_code' => sanitize_text_field( (string) ( $addr['postal_code'] ?? '' ) ),
		'country'     => sanitize_text_field( (string) ( $addr['country'] ?? '' ) ),
	);
	$data['payment_intent'] = is_array( $session['payment_intent'] ?? null ) ? $session['payment_intent']['id'] : (string) ( $session['payment_intent'] ?? '' );
	$data['paid_at']        = time();
	$data['mode']           = empty( $session['livemode'] ) ? 'test' : 'live';
	if ( isset( $session['amount_total'] ) && (int) $session['amount_total'] !== (int) $data['total'] ) {
		/* translators: 1: Stripe amount, 2: expected amount */
		$data['log'][] = array( time(), sprintf( __( 'Stripe charged %1$s but the basket came to %2$s. Check the order.', 'grow-shop' ), grs_money( (int) $session['amount_total'], false ), grs_money( (int) $data['total'], false ) ) );
		$data['total'] = (int) $session['amount_total'];
	}
	$data['log'][] = array( time(), sprintf( 'Paid (%s mode).', $data['mode'] ) );

	$number = grs_next_number();
	update_post_meta( $id, '_grs_number', $number );
	update_post_meta( $id, '_grs_email', $data['customer']['email'] );
	update_post_meta( $id, '_grs_pi', $data['payment_intent'] );
	grs_save_order_data( $id, $data );

	$wpdb->update(
		$wpdb->posts,
		array(
			'post_title'    => $number . ' ' . $data['customer']['name'],
			'post_excerpt'  => $number . ' ' . $data['customer']['name'] . ' ' . $data['customer']['email'] . ' ' . $data['address']['postal_code'],
			'post_date'     => current_time( 'mysql' ),
			'post_date_gmt' => current_time( 'mysql', true ),
		),
		array( 'ID' => $id )
	);
	clean_post_cache( $id );

	// Stock.
	foreach ( $data['lines'] as $line ) {
		if ( grs_tracks_stock( $line['id'] ) ) {
			$stock = (int) get_post_meta( $line['id'], '_grs_stock', true );
			update_post_meta( $line['id'], '_grs_stock', max( 0, $stock - (int) $line['qty'] ) );
		}
	}

	grs_send_email( 'confirmation', $id );
	grs_send_email( 'new_order', $id );
	do_action( 'grow_shop_order_paid', $id, $data );
	return $id;
}

/**
 * Mark an order dispatched, optionally with tracking, and email the
 * customer.
 *
 * @param int    $id       Order.
 * @param string $tracking Tracking number or link.
 * @param bool   $email    Send the dispatch email.
 */
function grs_dispatch_order( $id, $tracking = '', $email = true ) {
	$data                  = grs_order_data( $id );
	$data['tracking']      = sanitize_text_field( $tracking );
	$data['dispatched_at'] = time();
	$data['log'][]         = array( time(), __( 'Marked dispatched.', 'grow-shop' ) . ( $data['tracking'] ? ' ' . __( 'Tracking:', 'grow-shop' ) . ' ' . $data['tracking'] : '' ) );
	grs_save_order_data( $id, $data );
	wp_update_post( array( 'ID' => $id, 'post_status' => 'grs-dispatched' ) );
	if ( $email ) {
		grs_send_email( 'dispatched', $id );
	}
	do_action( 'grow_shop_order_dispatched', $id );
}

/**
 * Refund all or part of an order through Stripe.
 *
 * @param int  $id      Order.
 * @param int  $amount  Minor units.
 * @param bool $restock Put the items back into stock (full refunds).
 * @param bool $email   Send the refund email.
 * @return true|WP_Error
 */
function grs_refund_order( $id, $amount, $restock = false, $email = true ) {
	$data = grs_order_data( $id );
	$left = (int) $data['total'] - (int) $data['refunded'];
	if ( ! $data['payment_intent'] ) {
		return new WP_Error( 'grs_no_payment', __( 'This order has no Stripe payment to refund.', 'grow-shop' ) );
	}
	if ( $amount < 1 || $amount > $left ) {
		/* translators: %s: amount */
		return new WP_Error( 'grs_amount', sprintf( __( 'Enter an amount up to %s.', 'grow-shop' ), grs_money( $left, false ) ) );
	}
	$res = grs_stripe(
		'POST',
		'refunds',
		array(
			'payment_intent' => $data['payment_intent'],
			'amount'         => $amount,
			'metadata'       => array( 'grs_order' => (string) $id ),
		),
		$data['mode'],
		'grs-refund-' . $id . '-' . ( count( $data['refunds'] ) + 1 ) . '-' . $amount
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$data['refunded']   += $amount;
	$data['refunds'][]   = array( 'id' => $res['id'], 'amount' => $amount, 'time' => time() );
	/* translators: %s: amount */
	$data['log'][]       = array( time(), sprintf( __( 'Refunded %s.', 'grow-shop' ), grs_money( $amount, false ) ) );
	$data['last_refund'] = $amount;
	if ( $restock ) {
		foreach ( $data['lines'] as $line ) {
			if ( grs_tracks_stock( $line['id'] ) ) {
				update_post_meta( $line['id'], '_grs_stock', (int) get_post_meta( $line['id'], '_grs_stock', true ) + (int) $line['qty'] );
			}
		}
		$data['log'][] = array( time(), __( 'Items put back into stock.', 'grow-shop' ) );
	}
	grs_save_order_data( $id, $data );
	if ( $data['refunded'] >= $data['total'] ) {
		wp_update_post( array( 'ID' => $id, 'post_status' => 'grs-refunded' ) );
	}
	if ( $email ) {
		grs_send_email( 'refunded', $id );
	}
	return true;
}

/**
 * A refund made in Stripe's dashboard: bring the order up to date. No
 * email, as the shop owner did it by hand there.
 *
 * @param array $charge Stripe charge.
 */
function grs_sync_charge_refund( $charge ) {
	$pi = is_array( $charge['payment_intent'] ?? null ) ? $charge['payment_intent']['id'] : (string) ( $charge['payment_intent'] ?? '' );
	if ( ! $pi ) {
		return;
	}
	$ids = get_posts(
		array(
			'post_type'   => GRS_ORDER,
			'post_status' => array_keys( grs_statuses() ),
			'meta_key'    => '_grs_pi', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'  => $pi, // phpcs:ignore WordPress.DB.SlowDBQuery
			'fields'      => 'ids',
			'numberposts' => 1,
		)
	);
	if ( ! $ids ) {
		return;
	}
	$id   = $ids[0];
	$data = grs_order_data( $id );
	$paid = (int) ( $charge['amount_refunded'] ?? 0 );
	if ( $paid <= (int) $data['refunded'] ) {
		return;
	}
	/* translators: %s: amount */
	$data['log'][]    = array( time(), sprintf( __( 'Refund of %s made in Stripe.', 'grow-shop' ), grs_money( $paid - (int) $data['refunded'], false ) ) );
	$data['refunded'] = $paid;
	grs_save_order_data( $id, $data );
	if ( $paid >= (int) $data['total'] ) {
		wp_update_post( array( 'ID' => $id, 'post_status' => 'grs-refunded' ) );
	}
}

/**
 * Hourly: delete checkouts that were never paid (after two days, long
 * after Stripe has expired them).
 */
function grs_hourly() {
	$old = get_posts(
		array(
			'post_type'      => GRS_ORDER,
			'post_status'    => 'grs-pending',
			'posts_per_page' => 100,
			'fields'         => 'ids',
			'date_query'     => array( array( 'before' => '2 days ago', 'column' => 'post_date_gmt' ) ),
		)
	);
	foreach ( $old as $id ) {
		wp_delete_post( $id, true );
	}
}
add_action( 'grs_hourly', 'grs_hourly' );

/**
 * The address as lines, for emails, the order screen and packing slips.
 *
 * @param array $address Address.
 * @return string[]
 */
function grs_address_lines( $address ) {
	$countries = grs_countries();
	$out       = array();
	// UK addresses don't need the county Stripe adds ("England").
	$keys = in_array( $address['country'] ?? '', array( 'GB', 'GG', 'JE', 'IM' ), true )
		? array( 'name', 'line1', 'line2', 'city', 'postal_code' )
		: array( 'name', 'line1', 'line2', 'city', 'state', 'postal_code' );
	foreach ( $keys as $k ) {
		if ( ! empty( $address[ $k ] ) ) {
			$out[] = $address[ $k ];
		}
	}
	if ( ! empty( $address['country'] ) ) {
		$out[] = $countries[ $address['country'] ] ?? $address['country'];
	}
	return $out;
}
