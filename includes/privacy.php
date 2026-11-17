<?php
/**
 * Privacy: orders appear in Tools, Export Personal Data, and Erase
 * Personal Data removes the person's details from their orders while
 * keeping the amounts (needed for the accounts).
 *
 * @package GrowShop
 */

defined( 'ABSPATH' ) || exit;

/**
 * Orders for an email address.
 *
 * @param string $email Email.
 * @param int    $page  Page.
 * @return int[]
 */
function grs_orders_for_email( $email, $page = 1 ) {
	return get_posts(
		array(
			'post_type'      => GRS_ORDER,
			'post_status'    => array_keys( grs_statuses() ),
			'meta_key'       => '_grs_email', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => $email, // phpcs:ignore WordPress.DB.SlowDBQuery
			'fields'         => 'ids',
			'posts_per_page' => 50,
			'paged'          => $page,
		)
	);
}

/**
 * Register the exporter and eraser.
 *
 * @param array $list Existing.
 * @return array
 */
function grs_register_exporter( $list ) {
	$list['grow-shop'] = array(
		'exporter_friendly_name' => __( 'Shop orders', 'grow-shop' ),
		'callback'               => 'grs_privacy_export',
	);
	return $list;
}
add_filter( 'wp_privacy_personal_data_exporters', 'grs_register_exporter' );

/**
 * Register the eraser.
 *
 * @param array $list Existing.
 * @return array
 */
function grs_register_eraser( $list ) {
	$list['grow-shop'] = array(
		'eraser_friendly_name' => __( 'Shop orders', 'grow-shop' ),
		'callback'             => 'grs_privacy_erase',
	);
	return $list;
}
add_filter( 'wp_privacy_personal_data_erasers', 'grs_register_eraser' );

/**
 * Export.
 *
 * @param string $email Email.
 * @param int    $page  Page.
 * @return array
 */
function grs_privacy_export( $email, $page = 1 ) {
	$ids  = grs_orders_for_email( $email, $page );
	$data = array();
	foreach ( $ids as $id ) {
		$o     = grs_order_data( $id );
		$items = array();
		foreach ( $o['lines'] as $line ) {
			$items[] = $line['qty'] . ' x ' . $line['name'] . ( $line['options'] ? ' (' . $line['options'] . ')' : '' );
		}
		$data[] = array(
			'group_id'    => 'grow-shop-orders',
			'group_label' => __( 'Shop orders', 'grow-shop' ),
			'item_id'     => 'grow-order-' . $id,
			'data'        => array(
				array( 'name' => __( 'Order', 'grow-shop' ), 'value' => grs_order_number( $id ) ),
				array( 'name' => __( 'Date', 'grow-shop' ), 'value' => get_the_date( 'j F Y', $id ) ),
				array( 'name' => __( 'Name', 'grow-shop' ), 'value' => $o['customer']['name'] ),
				array( 'name' => __( 'Email', 'grow-shop' ), 'value' => $o['customer']['email'] ),
				array( 'name' => __( 'Phone', 'grow-shop' ), 'value' => $o['customer']['phone'] ),
				array( 'name' => __( 'Address', 'grow-shop' ), 'value' => implode( ', ', grs_address_lines( $o['address'] ) ) ),
				array( 'name' => __( 'Items', 'grow-shop' ), 'value' => implode( '; ', $items ) ),
				array( 'name' => __( 'Total', 'grow-shop' ), 'value' => grs_money( $o['total'], false ) ),
			),
		);
	}
	return array( 'data' => $data, 'done' => count( $ids ) < 50 );
}

/**
 * Erase: blank the personal details, keep the order and its amounts.
 *
 * @param string $email Email.
 * @param int    $page  Page (always 1: erased orders drop out of the query).
 * @return array
 */
function grs_privacy_erase( $email, $page = 1 ) {
	$ids = grs_orders_for_email( $email, 1 );
	foreach ( $ids as $id ) {
		$o             = grs_order_data( $id );
		$o['customer'] = array( 'name' => __( '(erased)', 'grow-shop' ), 'email' => '', 'phone' => '' );
		$o['address']  = array( 'country' => $o['address']['country'] ?? '' );
		$o['tracking'] = '';
		$o['notes']    = array();
		$o['log'][]    = array( time(), __( 'Personal details erased on request.', 'grow-shop' ) );
		grs_save_order_data( $id, $o );
		delete_post_meta( $id, '_grs_email' );
		wp_update_post(
			array(
				'ID'           => $id,
				'post_title'   => grs_order_number( $id ),
				'post_excerpt' => grs_order_number( $id ),
			)
		);
	}
	return array(
		'items_removed'  => count( $ids ),
		'items_retained' => 0,
		'messages'       => $ids ? array( __( 'Shop orders kept for the accounts, with the personal details removed.', 'grow-shop' ) ) : array(),
		'done'           => count( $ids ) < 50,
	);
}
