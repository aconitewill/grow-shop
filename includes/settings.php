<?php
/**
 * Settings: one option array with nested groups, merged over defaults.
 * Money is stored in minor units (pence) as integers everywhere.
 *
 * @package GrowShop
 */

defined( 'ABSPATH' ) || exit;

/**
 * Defaults for a new site. Filter `grow_shop_defaults` to seed a site.
 *
 * @return array
 */
function grs_defaults() {
	return (array) apply_filters(
		'grow_shop_defaults',
		array(
			'general'    => array(
				'mode'            => 'off',     // off | admins | on.
				'shop_name'       => '',        // Blank: the site title.
				'currency'        => 'GBP',
				'prefix'          => '',        // Order number prefix, e.g. AP-.
				'holiday'         => 0,
				'holiday_message' => 'The shop is closed for a short break. Orders will reopen soon.',
			),
			'products'   => array(),            // post type => grs_type_defaults() shape.
			'shipping'   => array(
				'zones' => array(
					array(
						'name'    => 'UK',
						'region'  => 'uk',
						'custom'  => '',
						'methods' => array(
							array(
								'name'      => 'Royal Mail',
								'type'      => 'flat',
								'amount'    => 500,
								'bands'     => array(),
								'free_over' => 0,
							),
						),
					),
				),
			),
			'payments'   => array(
				'mode'     => 'test',
				'key_test' => '',
				'key_live' => '',
				'wh_test'  => array(),       // id, secret, url.
				'wh_live'  => array(),
			),
			'emails'     => array(
				'notify_to'  => '',          // Shop notifications. Blank: site admin email.
				'from_name'  => '',
				'from_email' => '',
				'reply_to'   => '',          // Where customer replies go. Blank: first notify address.
				'logo'       => 0,           // Attachment ID.
				'accent'     => '#3c4a3e',
				'templates'  => grs_default_emails(),
			),
			'checkout'   => array(
				'basket_style'  => 'panel',  // panel | page.
				'basket_page'   => 0,
				'max_qty'       => 50,       // Most of one item per order.
				'min_order'     => 0,        // Minimum order value, minor units.
				'collect_phone' => 0,
				'thanks'        => "Thank you, your order is confirmed. We've emailed you a receipt.",
				'cancelled'     => 'Checkout was cancelled and nothing was charged. Your basket is still here.',
				'terms_page'    => 0,
				'returns_page'  => 0,
				'privacy_page'  => 0,
			),
			'appearance' => array(
				'basket_label' => 'Basket',
				'checkout_label' => 'Checkout',
				'icon'         => 'auto',     // auto | theme.
				'accent'       => '',         // Blank: the theme's colours.
				'stylesheet'   => 1,
			),
		)
	);
}

/**
 * Default emails. Placeholders are listed on the Emails tab.
 *
 * @return array key => enabled, subject, body.
 */
function grs_default_emails() {
	return array(
		'confirmation' => array(
			'enabled' => 1,
			'subject' => 'Thank you for your order {order_number}',
			'body'    => "Hi {first_name},\n\nThank you for your order. Here's what you ordered:\n\n{items}\n\nIt will be posted to:\n{address}\n\nWe'll email you again when it's on its way.\n\n{shop_name}",
		),
		'new_order'    => array(
			'enabled' => 1,
			'subject' => 'New order {order_number}: {total}',
			'body'    => "{customer_name} has placed an order.\n\n{items}\n\nPost to:\n{address}\n\nEmail: {customer_email}\nPhone: {customer_phone}\n\n{order_link}",
		),
		'dispatched'   => array(
			'enabled' => 1,
			'subject' => 'Your order {order_number} is on its way',
			'body'    => "Hi {first_name},\n\nGood news: your order has been posted.\n\n{tracking}\n\n{items}\n\nThank you,\n{shop_name}",
		),
		'refunded'     => array(
			'enabled' => 1,
			'subject' => 'Refund for order {order_number}',
			'body'    => "Hi {first_name},\n\nWe've refunded {refund_amount} for order {order_number}. It usually shows on your statement within 5 to 10 working days.\n\n{shop_name}",
		),
	);
}

/**
 * The shape of one sellable post type's settings.
 *
 * @return array
 */
function grs_type_defaults() {
	return array(
		'enabled'   => 0,
		'price'     => 0,
		'tiers'     => array(),        // array( array( min, price ) ), ascending.
		'mix'       => 1,              // Count tiers across every item of this type.
		'options'   => array(),        // array( array( name, values => array( array( label, diff ) ) ) ).
		'stock'     => 'unlimited',    // unlimited | count.
		'button'    => 'Add to basket',
		'placement' => 'auto',         // auto | theme.
		'label'     => '',             // Singular name used in the basket, blank = post type label.
	);
}

/**
 * All settings, merged over the defaults one level deep.
 *
 * @return array
 */
function grs_settings() {
	if ( isset( $GLOBALS['grs_settings_cache'] ) ) {
		return $GLOBALS['grs_settings_cache'];
	}
	$defaults = grs_defaults();
	$saved    = get_option( GRS_OPTION, array() );
	$saved    = is_array( $saved ) ? $saved : array();
	$s        = array();
	foreach ( $defaults as $group => $values ) {
		$s[ $group ] = isset( $saved[ $group ] ) && is_array( $saved[ $group ] ) ? array_merge( $values, $saved[ $group ] ) : $values;
	}
	foreach ( $s['products'] as $type => $conf ) {
		$s['products'][ $type ] = array_merge( grs_type_defaults(), (array) $conf );
	}
	$s['emails']['templates'] = array_replace_recursive( grs_default_emails(), (array) $s['emails']['templates'] );

	// Keys can come from wp-config.php instead, which wins over the database.
	if ( defined( 'GROW_SHOP_STRIPE_TEST_KEY' ) ) {
		$s['payments']['key_test'] = (string) GROW_SHOP_STRIPE_TEST_KEY;
	}
	if ( defined( 'GROW_SHOP_STRIPE_LIVE_KEY' ) ) {
		$s['payments']['key_live'] = (string) GROW_SHOP_STRIPE_LIVE_KEY;
	}

	$GLOBALS['grs_settings_cache'] = (array) apply_filters( 'grow_shop_settings', $s );
	return $GLOBALS['grs_settings_cache'];
}

/**
 * Forget the cached settings (after a save).
 */
function grs_settings_flush() {
	unset( $GLOBALS['grs_settings_cache'] );
}

/**
 * One setting: grs_get( 'general', 'mode' ).
 *
 * @param string $group Group.
 * @param string $key   Key, or empty for the whole group.
 * @return mixed
 */
function grs_get( $group, $key = '' ) {
	$s = grs_settings();
	if ( ! isset( $s[ $group ] ) ) {
		return null;
	}
	if ( '' === $key ) {
		return $s[ $group ];
	}
	return isset( $s[ $group ][ $key ] ) ? $s[ $group ][ $key ] : null;
}

/**
 * Save one group, replacing it.
 *
 * @param string $group  Group.
 * @param array  $values Values.
 */
function grs_save_group( $group, $values ) {
	$saved           = get_option( GRS_OPTION, array() );
	$saved           = is_array( $saved ) ? $saved : array();
	$saved[ $group ] = $values;
	update_option( GRS_OPTION, $saved, false );
	grs_settings_flush();
}

/**
 * The shop's name for emails and Stripe.
 *
 * @return string
 */
function grs_shop_name() {
	$name = trim( (string) grs_get( 'general', 'shop_name' ) );
	return '' !== $name ? $name : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
}

/**
 * Is the shop taking orders for this visitor? Off, admins-only preview
 * (logged-in pages are never cached on Kinsta), or on.
 *
 * @return bool
 */
function grow_shop_is_open() {
	$mode = grs_get( 'general', 'mode' );
	if ( 'on' === $mode ) {
		return true;
	}
	return 'admins' === $mode && current_user_can( GRS_CAP );
}

/**
 * Is holiday mode on (shop visible, new orders paused)?
 *
 * @return bool
 */
function grs_on_holiday() {
	return (bool) grs_get( 'general', 'holiday' );
}

/**
 * Currencies offered, with symbols. Zero-decimal currencies are left out
 * on purpose: every amount here is in hundredths.
 *
 * @return array code => array( symbol, label )
 */
function grs_currencies() {
	return array(
		'GBP' => array( '£', 'Pounds sterling (£)' ),
		'EUR' => array( '€', 'Euros (€)' ),
		'USD' => array( '$', 'US dollars ($)' ),
		'AUD' => array( 'A$', 'Australian dollars (A$)' ),
		'CAD' => array( 'C$', 'Canadian dollars (C$)' ),
		'NZD' => array( 'NZ$', 'New Zealand dollars (NZ$)' ),
	);
}

/**
 * Format minor units as money: 250 => £2.50, 500 => £5.
 *
 * @param int  $minor Amount in pence.
 * @param bool $trim  Drop .00 on whole amounts.
 * @return string
 */
function grs_money( $minor, $trim = true ) {
	$cur    = grs_currencies();
	$code   = (string) grs_get( 'general', 'currency' );
	$symbol = isset( $cur[ $code ] ) ? $cur[ $code ][0] : $code . ' ';
	$minor  = (int) $minor;
	$neg    = $minor < 0 ? '-' : '';
	$minor  = abs( $minor );
	$text   = ( $trim && 0 === $minor % 100 ) ? number_format( $minor / 100, 0 ) : number_format( $minor / 100, 2 );
	return $neg . $symbol . $text;
}

/**
 * Parse money typed by a person ("2.5", "£2.50", "2") into minor units.
 *
 * @param mixed $text Input.
 * @return int
 */
function grs_parse_money( $text ) {
	$text = preg_replace( '/[^0-9.\-]/', '', (string) $text );
	if ( '' === $text || '.' === $text || '-' === $text ) {
		return 0;
	}
	return (int) round( (float) $text * 100 );
}

/**
 * Minor units back to a plain decimal for a form field ("2.50"), or "".
 *
 * @param int  $minor Amount.
 * @param bool $blank_zero Show 0 as blank.
 * @return string
 */
function grs_money_field( $minor, $blank_zero = false ) {
	if ( $blank_zero && ! $minor ) {
		return '';
	}
	return number_format( (int) $minor / 100, 2, '.', '' );
}

/**
 * The Stripe mode in use: test or live.
 *
 * @return string
 */
function grs_mode() {
	return 'live' === grs_get( 'payments', 'mode' ) ? 'live' : 'test';
}

/**
 * The Stripe key for a mode.
 *
 * @param string $mode test | live, blank for the current mode.
 * @return string
 */
function grs_stripe_key( $mode = '' ) {
	$mode = $mode ? $mode : grs_mode();
	return trim( (string) grs_get( 'payments', 'key_' . $mode ) );
}

/**
 * Find a template, letting the theme override it in grow-shop/.
 *
 * @param string $name File name, e.g. basket.php.
 * @return string Path.
 */
function grs_template( $name ) {
	$theme = locate_template( 'grow-shop/' . $name );
	return $theme ? $theme : GRS_DIR . 'templates/' . $name;
}

/**
 * Addresses from a list separated by lines, commas or spaces.
 *
 * @param string $text Addresses.
 * @return string[]
 */
function grs_parse_addresses( $text ) {
	$list = array();
	foreach ( preg_split( '/[\s,;]+/', (string) $text ) as $addr ) {
		$addr = sanitize_email( $addr );
		if ( is_email( $addr ) ) {
			$list[] = $addr;
		}
	}
	return array_values( array_unique( $list ) );
}

/**
 * Where shop notifications go. Never printed on the front end.
 *
 * @return string[]
 */
function grs_notify_to() {
	$list = grs_parse_addresses( (string) grs_get( 'emails', 'notify_to' ) );
	return $list ? $list : array( get_option( 'admin_email' ) );
}

/**
 * Append to a capped log (for orders and the settings screen).
 *
 * @param string $message Message.
 */
function grs_log( $message ) {
	$log   = (array) get_option( 'grow_shop_log', array() );
	$log[] = array( time(), sanitize_text_field( $message ) );
	update_option( 'grow_shop_log', array_slice( $log, -100 ), false );
}
