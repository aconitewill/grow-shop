<?php
/**
 * The pricing engine. The basket in the browser is only a list of IDs,
 * options and quantities: every price, discount and postage cost is
 * worked out here, from the settings, for both the basket display and
 * the checkout. The browser's numbers are never trusted.
 *
 * @package GrowShop
 */

defined( 'ABSPATH' ) || exit;

/**
 * Clean a basket sent by the browser.
 *
 * @param mixed $items Raw items: array( array( id, qty, opts => array( group => value ) ) ).
 * @return array
 */
function grs_clean_items( $items ) {
	$out = array();
	foreach ( array_slice( (array) $items, 0, 100 ) as $item ) {
		if ( ! is_array( $item ) || empty( $item['id'] ) ) {
			continue;
		}
		$opts = array();
		foreach ( (array) ( $item['opts'] ?? array() ) as $g => $v ) {
			$opts[ (int) $g ] = (int) $v;
		}
		ksort( $opts );
		$out[] = array(
			'id'   => absint( $item['id'] ),
			'qty'  => max( 0, (int) ( $item['qty'] ?? 1 ) ),
			'opts' => $opts,
		);
	}
	return $out;
}

/**
 * Price a basket.
 *
 * @param array $items    Clean items (grs_clean_items()).
 * @param int   $zone     Shipping zone index, -1 for the first.
 * @param int   $method   Method index within the zone, -1 for the first.
 * @param int   $except_order Pending order to leave out of stock holds.
 * @return array lines, subtotal, shipping, total, zones, methods, notices, errors, ok.
 */
function grs_quote( $items, $zone = -1, $method = -1, $except_order = 0 ) {
	$max     = max( 1, (int) grs_get( 'checkout', 'max_qty' ) );
	$errors  = array();
	$notices = array();
	$lines   = array();

	// Merge lines that are the same item with the same options.
	$merged = array();
	foreach ( $items as $item ) {
		$key = $item['id'] . '|' . wp_json_encode( $item['opts'] );
		if ( isset( $merged[ $key ] ) ) {
			$merged[ $key ]['qty'] += $item['qty'];
		} else {
			$merged[ $key ] = $item;
		}
	}

	// First pass: what's valid, and the quantities that count toward tables.
	$valid       = array();
	$type_counts = array();
	$item_counts = array();
	foreach ( $merged as $key => $item ) {
		$id = $item['id'];
		if ( $item['qty'] < 1 ) {
			continue;
		}
		if ( ! grs_is_purchasable( $id ) ) {
			$title    = get_the_title( $id );
			$errors[] = $title
				/* translators: %s: item name */
				? sprintf( __( '%s is no longer available, so it has been taken out of your basket.', 'grow-shop' ), $title )
				: __( 'An item is no longer available, so it has been taken out of your basket.', 'grow-shop' );
			$lines[]  = array( 'key' => $key, 'id' => $id, 'remove' => true );
			continue;
		}
		$qty = min( $item['qty'], $max );
		if ( $qty < $item['qty'] ) {
			/* translators: 1: number, 2: item name */
			$notices[] = sprintf( __( 'You can order up to %1$d of %2$s at a time.', 'grow-shop' ), $max, get_the_title( $id ) );
		}

		// Options: every group needs a value the item offers; fill gaps with the first.
		$groups = grs_item_options( $id );
		$opts   = array();
		foreach ( $groups as $g => $group ) {
			$opts[ $g ] = isset( $item['opts'][ $g ], $group['values'][ $item['opts'][ $g ] ] ) ? $item['opts'][ $g ] : (int) array_key_first( $group['values'] );
		}

		$type                  = get_post_type( $id );
		$valid[ $key ]         = array( 'id' => $id, 'qty' => $qty, 'opts' => $opts, 'type' => $type, 'groups' => $groups );
		$type_counts[ $type ]  = ( $type_counts[ $type ] ?? 0 ) + $qty;
		$item_counts[ $id ]    = ( $item_counts[ $id ] ?? 0 ) + $qty;
	}

	// Stock, per item across option lines.
	foreach ( $item_counts as $id => $count ) {
		$available = grs_available( $id, $except_order );
		if ( $count > $available ) {
			$errors[] = 0 === $available
				/* translators: %s: item name */
				? sprintf( __( '%s has just sold out.', 'grow-shop' ), get_the_title( $id ) )
				/* translators: 1: number, 2: item name */
				: sprintf( __( 'Only %1$d of %2$s left. Please lower the quantity.', 'grow-shop' ), $available, get_the_title( $id ) );
		}
	}

	// Second pass: prices.
	$subtotal = 0;
	$count    = 0;
	foreach ( $valid as $key => $line ) {
		$id    = $line['id'];
		$conf  = grs_type_conf( $line['type'] );
		$basis = ! empty( $conf['mix'] ) ? $type_counts[ $line['type'] ] : $item_counts[ $id ];
		$unit  = grs_tier_price( $id, $basis );
		$base  = grs_base_price( $id );

		$labels = array();
		foreach ( $line['opts'] as $g => $v ) {
			$value    = $line['groups'][ $g ]['values'][ $v ];
			$unit    += (int) $value['diff'];
			$base    += (int) $value['diff'];
			$labels[] = $line['groups'][ $g ]['name'] . ': ' . $value['label'];
		}
		$unit  = max( 0, $unit );
		$total = $unit * $line['qty'];

		$thumb   = get_post_thumbnail_id( $id );
		$lines[] = array(
			'key'       => $key,
			'id'        => $id,
			'type'      => $line['type'],
			'name'      => wp_specialchars_decode( get_the_title( $id ), ENT_QUOTES ),
			'url'       => get_permalink( $id ),
			'image'     => $thumb ? (string) wp_get_attachment_image_url( $thumb, 'thumbnail' ) : '',
			'image_big' => $thumb ? (string) wp_get_attachment_image_url( $thumb, 'medium' ) : '',
			'opts'      => $line['opts'],
			'options'   => implode( ', ', $labels ),
			'qty'       => $line['qty'],
			'unit'      => $unit,
			'was'       => $base > $unit ? $base : 0,
			'total'     => $total,
			'max'       => min( $max, grs_available( $id, $except_order ) ),
		);
		$subtotal += $total;
		$count    += $line['qty'];
	}

	// Hints for the next quantity price, per mix-and-match type.
	foreach ( $type_counts as $type => $n ) {
		$conf = grs_type_conf( $type );
		if ( empty( $conf['mix'] ) ) {
			continue;
		}
		foreach ( (array) $conf['tiers'] as $tier ) {
			if ( (int) $tier[0] > $n ) {
				$notices[] = sprintf(
					/* translators: 1: how many more, 2: type name plural-ish, 3: price, 4: threshold */
					__( 'Add %1$d more to pay %3$s each (%4$d or more %2$s).', 'grow-shop' ),
					(int) $tier[0] - $n,
					strtolower( grs_type_plural( $type ) ),
					grs_money( (int) $tier[1] ),
					(int) $tier[0]
				);
				break;
			}
		}
	}

	// Postage.
	$zones     = (array) grs_get( 'shipping', 'zones' );
	$zone_list = array();
	foreach ( $zones as $i => $z ) {
		$zone_list[] = array( 'i' => $i, 'name' => $z['name'] );
	}
	if ( ! isset( $zones[ $zone ] ) ) {
		$zone = $zones ? (int) array_key_first( $zones ) : -1;
	}
	$methods  = array();
	$shipping = 0;
	if ( $zone >= 0 ) {
		foreach ( (array) $zones[ $zone ]['methods'] as $m => $def ) {
			$methods[] = array(
				'i'    => $m,
				'name' => $def['name'],
				'cost' => grs_method_cost( $def, $count, $subtotal ),
			);
		}
		$found = false;
		foreach ( $methods as $row ) {
			if ( $row['i'] === $method ) {
				$found = true;
			}
		}
		if ( ! $found ) {
			$method = $methods ? $methods[0]['i'] : -1;
		}
		foreach ( $methods as $row ) {
			if ( $row['i'] === $method ) {
				$shipping = $row['cost'];
			}
		}
	}
	if ( $count && ( $zone < 0 || $method < 0 ) ) {
		$errors[] = __( 'Postage is not set up yet, so orders can\'t be taken.', 'grow-shop' );
	}

	$min = (int) grs_get( 'checkout', 'min_order' );
	if ( $count && $min > 0 && $subtotal < $min ) {
		/* translators: %s: amount */
		$errors[] = sprintf( __( 'The minimum order is %s.', 'grow-shop' ), grs_money( $min ) );
	}
	if ( grs_on_holiday() ) {
		$errors[] = (string) grs_get( 'general', 'holiday_message' );
	}

	return array(
		'lines'    => $lines,
		'count'    => $count,
		'subtotal' => $subtotal,
		'zone'     => $zone,
		'method'   => $method,
		'shipping' => $shipping,
		'total'    => $subtotal + $shipping,
		'zones'    => $zone_list,
		'methods'  => $methods,
		'notices'  => array_values( array_unique( $notices ) ),
		'errors'   => array_values( array_unique( $errors ) ),
		'ok'       => $count > 0 && ! $errors,
	);
}

/**
 * Cost of one postage method for a basket.
 *
 * @param array $def      Method settings.
 * @param int   $count    Items in the basket.
 * @param int   $subtotal Goods total.
 * @return int
 */
function grs_method_cost( $def, $count, $subtotal ) {
	if ( (int) $def['free_over'] > 0 && $subtotal >= (int) $def['free_over'] ) {
		return 0;
	}
	switch ( $def['type'] ) {
		case 'per_item':
			return (int) $def['amount'] * $count;
		case 'bands':
			$cost = (int) $def['amount'];
			foreach ( (array) $def['bands'] as $band ) {
				if ( $count >= (int) $band[0] ) {
					$cost = (int) $band[1];
				}
			}
			return $cost;
		default:
			return (int) $def['amount'];
	}
}

/**
 * The plural of a type's name, for hints ("cards").
 *
 * @param string $type Post type.
 * @return string
 */
function grs_type_plural( $type ) {
	$obj = get_post_type_object( $type );
	return $obj ? $obj->labels->name : $type;
}

/**
 * The money fields of a quote, formatted for the browser.
 *
 * @param array $q Quote.
 * @return array
 */
function grs_quote_for_browser( $q ) {
	foreach ( $q['lines'] as &$line ) {
		if ( ! empty( $line['remove'] ) ) {
			continue;
		}
		$line['unit_text']  = grs_money( $line['unit'], false );
		$line['was_text']   = $line['was'] ? grs_money( $line['was'], false ) : '';
		$line['total_text'] = grs_money( $line['total'], false );
		$line['max']        = min( 999, (int) $line['max'] );
	}
	unset( $line );
	foreach ( $q['methods'] as &$m ) {
		$m['cost_text'] = $m['cost'] ? grs_money( $m['cost'], false ) : __( 'Free', 'grow-shop' );
	}
	unset( $m );
	$q['subtotal_text'] = grs_money( $q['subtotal'], false );
	$q['shipping_text'] = $q['shipping'] ? grs_money( $q['shipping'], false ) : __( 'Free', 'grow-shop' );
	$q['total_text']    = grs_money( $q['total'], false );
	return $q;
}
