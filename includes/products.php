<?php
/**
 * What can be sold: post types ticked under Shop, Settings, Products, with
 * per-type defaults and per-item overrides stored on the post.
 *
 * Item meta:
 *   _grs_price        Price override in minor units ('' = the type's default).
 *   _grs_not_for_sale 1 = hidden from the shop.
 *   _grs_stock        Count, when the type tracks stock.
 *   _grs_options_off  Option values switched off for this item ("group:value").
 *
 * @package GrowShop
 */

defined( 'ABSPATH' ) || exit;

/**
 * Post types that can be sold.
 *
 * @return string[]
 */
function grs_sellable_types() {
	$out = array();
	foreach ( (array) grs_get( 'products' ) as $type => $conf ) {
		if ( ! empty( $conf['enabled'] ) && post_type_exists( $type ) ) {
			$out[] = $type;
		}
	}
	return $out;
}

/**
 * Settings for one post type, or null when it isn't sold.
 *
 * @param string $type Post type.
 * @return array|null
 */
function grs_type_conf( $type ) {
	$all = (array) grs_get( 'products' );
	if ( empty( $all[ $type ]['enabled'] ) ) {
		return null;
	}
	return $all[ $type ];
}

/**
 * The singular name of a type, for the basket ("Card").
 *
 * @param string $type Post type.
 * @return string
 */
function grs_type_label( $type ) {
	$conf = grs_type_conf( $type );
	if ( $conf && '' !== trim( (string) $conf['label'] ) ) {
		return $conf['label'];
	}
	$obj = get_post_type_object( $type );
	return $obj ? $obj->labels->singular_name : $type;
}

/**
 * Can this item be bought right now (ignoring stock and holiday mode)?
 * Themes add their own rules with the `grow_shop_purchasable` filter.
 *
 * @param int $id Post ID.
 * @return bool
 */
function grs_is_purchasable( $id ) {
	$post = get_post( $id );
	$ok   = $post && 'publish' === $post->post_status && empty( $post->post_password )
		&& grs_type_conf( $post->post_type ) && ! get_post_meta( $id, '_grs_not_for_sale', true );
	return (bool) apply_filters( 'grow_shop_purchasable', $ok, (int) $id );
}

/**
 * Has this item got its own price?
 *
 * @param int $id Post ID.
 * @return bool
 */
function grs_has_override( $id ) {
	return '' !== (string) get_post_meta( $id, '_grs_price', true );
}

/**
 * The item's single-unit price before quantity pricing and options.
 *
 * @param int $id Post ID.
 * @return int Minor units.
 */
function grs_base_price( $id ) {
	if ( grs_has_override( $id ) ) {
		return (int) get_post_meta( $id, '_grs_price', true );
	}
	$conf = grs_type_conf( get_post_type( $id ) );
	return $conf ? (int) $conf['price'] : 0;
}

/**
 * The unit price for a quantity, using the type's quantity pricing table.
 * Items with their own price keep it whatever the quantity.
 *
 * @param int $id    Post ID.
 * @param int $count Quantity that counts toward the table.
 * @return int
 */
function grs_tier_price( $id, $count ) {
	$price = grs_base_price( $id );
	if ( grs_has_override( $id ) ) {
		return $price;
	}
	$conf = grs_type_conf( get_post_type( $id ) );
	foreach ( $conf ? (array) $conf['tiers'] : array() as $tier ) {
		if ( $count >= (int) $tier[0] && (int) $tier[0] > 0 ) {
			$price = (int) $tier[1];
		}
	}
	return $price;
}

/**
 * The quantity table for display: array( array( min, price ) ), with the
 * single price first.
 *
 * @param int $id Post ID.
 * @return array
 */
function grs_price_table( $id ) {
	$rows = array( array( 1, grs_base_price( $id ) ) );
	if ( grs_has_override( $id ) ) {
		return $rows;
	}
	$conf = grs_type_conf( get_post_type( $id ) );
	foreach ( $conf ? (array) $conf['tiers'] : array() as $tier ) {
		if ( (int) $tier[0] > 1 ) {
			$rows[] = array( (int) $tier[0], (int) $tier[1] );
		}
	}
	return $rows;
}

/**
 * The options this item offers: the type's option groups, minus values
 * switched off on the item. Groups with no values left are dropped.
 *
 * @param int $id Post ID.
 * @return array group index => array( name, values => array( value index => array( label, diff ) ) )
 */
function grs_item_options( $id ) {
	$conf = grs_type_conf( get_post_type( $id ) );
	if ( ! $conf ) {
		return array();
	}
	$off = (array) get_post_meta( $id, '_grs_options_off', true );
	$out = array();
	foreach ( (array) $conf['options'] as $g => $group ) {
		$values = array();
		foreach ( (array) $group['values'] as $v => $value ) {
			if ( ! in_array( $g . ':' . $v, $off, true ) ) {
				$values[ $v ] = $value;
			}
		}
		if ( $values ) {
			$out[ $g ] = array(
				'name'   => $group['name'],
				'values' => $values,
			);
		}
	}
	return $out;
}

/**
 * Does this item's type track stock?
 *
 * @param int $id Post ID.
 * @return bool
 */
function grs_tracks_stock( $id ) {
	$conf = grs_type_conf( get_post_type( $id ) );
	return $conf && 'count' === $conf['stock'];
}

/**
 * How many can be bought now: the stock count minus what's held by
 * checkouts in progress (the last 35 minutes). PHP_INT_MAX when the type
 * doesn't track stock.
 *
 * @param int $id         Post ID.
 * @param int $except_order Pending order to leave out of the holds.
 * @return int
 */
function grs_available( $id, $except_order = 0 ) {
	if ( ! grs_tracks_stock( $id ) ) {
		return PHP_INT_MAX;
	}
	$stock = (int) get_post_meta( $id, '_grs_stock', true );
	return max( 0, $stock - grs_held( $id, $except_order ) );
}

/**
 * Quantity held for an item by checkouts started in the last 35 minutes.
 *
 * @param int $id           Post ID.
 * @param int $except_order Pending order to leave out.
 * @return int
 */
function grs_held( $id, $except_order = 0 ) {
	$pending = get_posts(
		array(
			'post_type'      => GRS_ORDER,
			'post_status'    => 'grs-pending',
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'date_query'     => array( array( 'after' => '35 minutes ago', 'column' => 'post_date_gmt' ) ),
			'exclude'        => $except_order ? array( $except_order ) : array(),
			'no_found_rows'  => true,
		)
	);
	$held = 0;
	foreach ( $pending as $order_id ) {
		$data = grs_order_data( $order_id );
		foreach ( (array) $data['lines'] as $line ) {
			if ( (int) $line['id'] === (int) $id ) {
				$held += (int) $line['qty'];
			}
		}
	}
	return $held;
}
