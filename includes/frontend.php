<?php
/**
 * The front end: template tags, shortcodes, the basket panel and assets.
 * Everything printed into pages is the same for every visitor, so full
 * page caching keeps working; the basket lives in the browser and is
 * priced through the REST API.
 *
 * Template tags for themes:
 *   grow_shop_button( $post_id, $args )   Add-to-basket form.
 *   grow_shop_price( $post_id )           Price, with quantity pricing.
 *   grow_shop_basket_icon()               Basket button with count.
 *
 * Shortcodes: [grow_shop_button id=""], [grow_shop_price id=""],
 * [grow_shop_basket_icon], [grow_shop_basket] (basket on a page).
 *
 * @package GrowShop
 */

defined( 'ABSPATH' ) || exit;

/**
 * Print the add-to-basket form.
 *
 * @param int   $id   Post ID (default: current post).
 * @param array $args context (single | archive), qty (bool), price (bool), label.
 */
function grow_shop_button( $id = 0, $args = array() ) {
	echo grs_button_html( $id, $args ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
}

/**
 * The add-to-basket form.
 *
 * @param int   $id   Post ID.
 * @param array $args See grow_shop_button().
 * @return string
 */
function grs_button_html( $id = 0, $args = array() ) {
	$id = $id ? (int) $id : (int) get_the_ID();
	if ( ! $id || ! grow_shop_is_open() || ! grs_is_purchasable( $id ) ) {
		return '';
	}
	$conf = grs_type_conf( get_post_type( $id ) );
	$args = wp_parse_args(
		$args,
		array(
			'context' => 'single',
			'qty'     => null,
			'price'   => null,
			'label'   => $conf['button'],
		)
	);
	$single = 'single' === $args['context'];
	$qty    = null === $args['qty'] ? $single : (bool) $args['qty'];
	$price  = null === $args['price'] ? $single : (bool) $args['price'];

	if ( grs_tracks_stock( $id ) && (int) get_post_meta( $id, '_grs_stock', true ) < 1 ) {
		return '<p class="grs-soldout">' . esc_html__( 'Sold out', 'grow-shop' ) . '</p>';
	}

	$thumb   = get_post_thumbnail_id( $id );
	$options = grs_item_options( $id );
	$max     = max( 1, (int) grs_get( 'checkout', 'max_qty' ) );

	$out  = '<form class="grs-add grs-add--' . esc_attr( $args['context'] ) . '" data-grs-id="' . esc_attr( $id ) . '" data-grs-name="' . esc_attr( get_the_title( $id ) ) . '"' . ( $thumb ? ' data-grs-image="' . esc_url( (string) wp_get_attachment_image_url( $thumb, 'thumbnail' ) ) . '"' : '' ) . '>';
	if ( $price ) {
		$out .= grs_price_html( $id );
	}
	if ( $single ) {
		foreach ( $options as $g => $group ) {
			$fid  = 'grs-opt-' . $id . '-' . $g;
			$out .= '<p class="grs-add__field"><label for="' . esc_attr( $fid ) . '">' . esc_html( $group['name'] ) . '</label><select id="' . esc_attr( $fid ) . '" data-grs-group="' . esc_attr( $g ) . '">';
			foreach ( $group['values'] as $v => $value ) {
				$diff = (int) $value['diff'];
				$note = $diff ? ' (' . ( $diff > 0 ? '+' : '' ) . grs_money( $diff, false ) . ')' : '';
				$out .= '<option value="' . esc_attr( $v ) . '">' . esc_html( $value['label'] . $note ) . '</option>';
			}
			$out .= '</select></p>';
		}
	}
	$out .= '<div class="grs-add__row">';
	if ( $qty ) {
		$qid  = 'grs-qty-' . $id;
		$out .= '<label class="grs-add__qty"><span class="grs-sr">' . esc_html__( 'Quantity', 'grow-shop' ) . '</span>';
		$out .= '<button type="button" class="grs-step" data-grs-step="-1" aria-label="' . esc_attr__( 'One fewer', 'grow-shop' ) . '">&minus;</button>';
		$out .= '<input id="' . esc_attr( $qid ) . '" type="number" inputmode="numeric" min="1" max="' . esc_attr( $max ) . '" value="1" data-grs-qty>';
		$out .= '<button type="button" class="grs-step" data-grs-step="1" aria-label="' . esc_attr__( 'One more', 'grow-shop' ) . '">+</button></label>';
	}
	$out .= '<button type="submit" class="grs-add__button"' . ( grs_on_holiday() ? ' disabled' : '' ) . '>' . esc_html( $args['label'] ) . '</button>';
	$out .= '</div>';
	if ( grs_on_holiday() ) {
		$out .= '<p class="grs-add__note">' . esc_html( (string) grs_get( 'general', 'holiday_message' ) ) . '</p>';
	}
	$out .= '<p class="grs-add__done" aria-live="polite"></p>';
	$out .= '</form>';
	return (string) apply_filters( 'grow_shop_button_html', $out, $id, $args );
}

/**
 * Print the price.
 *
 * @param int $id Post ID.
 */
function grow_shop_price( $id = 0 ) {
	echo grs_price_html( $id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
}

/**
 * The price with any quantity pricing: "£2.50 each, £2 each for 20 or more".
 *
 * @param int $id Post ID.
 * @return string
 */
function grs_price_html( $id = 0 ) {
	$id = $id ? (int) $id : (int) get_the_ID();
	if ( ! $id || ! grs_type_conf( get_post_type( $id ) ) ) {
		return '';
	}
	return '<p class="grs-price">' . esc_html( grs_price_text( $id ) ) . '</p>';
}

/**
 * The price as plain text.
 *
 * @param int $id Post ID.
 * @return string
 */
function grs_price_text( $id ) {
	$table = grs_price_table( $id );
	$parts = array();
	foreach ( $table as $i => $row ) {
		$parts[] = 0 === $i
			/* translators: %s: price */
			? sprintf( __( '%s each', 'grow-shop' ), grs_money( $row[1] ) )
			/* translators: 1: price, 2: quantity */
			: sprintf( __( '%1$s each for %2$d or more', 'grow-shop' ), grs_money( $row[1] ), $row[0] );
	}
	$text = implode( ', ', $parts );
	$conf = grs_type_conf( get_post_type( $id ) );
	if ( count( $table ) > 1 && ! empty( $conf['mix'] ) ) {
		/* translators: %s: plural type name */
		$text .= ' ' . sprintf( __( '(mix and match any %s)', 'grow-shop' ), strtolower( grs_type_plural( get_post_type( $id ) ) ) );
	}
	return (string) apply_filters( 'grow_shop_price_text', $text, $id );
}

/**
 * A one-line postage summary for item pages: "UK: £5 per order" when
 * there's one zone and one method, otherwise "From £3, worked out in the
 * basket".
 *
 * @return string
 */
function grs_postage_text() {
	$zones = (array) grs_get( 'shipping', 'zones' );
	if ( ! $zones ) {
		return '';
	}
	$describe = function ( $m ) {
		switch ( $m['type'] ) {
			case 'per_item':
				/* translators: %s: amount */
				$text = sprintf( __( '%s per item', 'grow-shop' ), grs_money( (int) $m['amount'] ) );
				break;
			case 'bands':
				/* translators: %s: amount */
				$text = sprintf( __( 'from %s, by quantity', 'grow-shop' ), grs_money( (int) $m['amount'] ) );
				break;
			default:
				/* translators: %s: amount */
				$text = (int) $m['amount'] ? sprintf( __( '%s per order', 'grow-shop' ), grs_money( (int) $m['amount'] ) ) : __( 'free', 'grow-shop' );
		}
		if ( (int) $m['free_over'] > 0 ) {
			/* translators: %s: amount */
			$text .= ', ' . sprintf( __( 'free on orders over %s', 'grow-shop' ), grs_money( (int) $m['free_over'] ) );
		}
		return $text;
	};
	if ( 1 === count( $zones ) && 1 === count( (array) $zones[0]['methods'] ) ) {
		$text = $zones[0]['name'] . ': ' . $describe( $zones[0]['methods'][0] );
	} else {
		$min = null;
		foreach ( $zones as $z ) {
			foreach ( (array) $z['methods'] as $m ) {
				$min = null === $min ? (int) $m['amount'] : min( $min, (int) $m['amount'] );
			}
		}
		/* translators: %s: amount */
		$text = null === $min ? '' : sprintf( __( 'From %s, worked out in the basket', 'grow-shop' ), grs_money( $min ) );
	}
	return (string) apply_filters( 'grow_shop_postage_text', $text );
}

/**
 * Print the basket button.
 *
 * @param array $args class.
 */
function grow_shop_basket_icon( $args = array() ) {
	echo grs_basket_icon_html( $args ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
}

/**
 * The basket button with its count (filled in by JavaScript).
 *
 * @param array $args class.
 * @return string
 */
function grs_basket_icon_html( $args = array() ) {
	if ( ! grow_shop_is_open() ) {
		return '';
	}
	$class = 'grs-basket-toggle' . ( empty( $args['class'] ) ? '' : ' ' . $args['class'] );
	$label = (string) grs_get( 'appearance', 'basket_label' );
	$svg   = '<svg class="grs-basket-toggle__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M5 8h14l-1.2 11.2a1 1 0 0 1-1 .8H7.2a1 1 0 0 1-1-.8L5 8z"/><path d="M9 10V6a3 3 0 0 1 6 0v4"/></svg>';
	return '<button type="button" class="' . esc_attr( $class ) . '" aria-controls="grs-basket" aria-expanded="false">' . $svg . '<span class="grs-basket-toggle__label">' . esc_html( $label ) . '</span> <span class="grs-count" data-grs-count hidden>0</span></button>';
}

/**
 * Shortcodes.
 */
function grs_shortcodes() {
	add_shortcode(
		'grow_shop_button',
		function ( $atts ) {
			$atts = shortcode_atts( array( 'id' => 0, 'context' => 'single' ), $atts );
			return grs_button_html( (int) $atts['id'], array( 'context' => $atts['context'] ) );
		}
	);
	add_shortcode(
		'grow_shop_price',
		function ( $atts ) {
			$atts = shortcode_atts( array( 'id' => 0 ), $atts );
			return grs_price_html( (int) $atts['id'] );
		}
	);
	add_shortcode( 'grow_shop_basket_icon', 'grs_basket_icon_html' );
	add_shortcode(
		'grow_shop_basket',
		function () {
			if ( ! grow_shop_is_open() ) {
				return '';
			}
			$GLOBALS['grs_inline_basket'] = true;
			ob_start();
			$inline = true;
			include grs_template( 'basket.php' );
			return (string) ob_get_clean();
		}
	);
}
add_action( 'init', 'grs_shortcodes' );

/**
 * Put the button after the content on single items whose type is set to
 * automatic placement.
 *
 * @param string $content Content.
 * @return string
 */
function grs_auto_button( $content ) {
	if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$conf = grs_type_conf( get_post_type() );
	if ( ! $conf || 'auto' !== $conf['placement'] ) {
		return $content;
	}
	return $content . grs_button_html( get_the_ID() );
}
add_filter( 'the_content', 'grs_auto_button', 20 );

/**
 * Scripts and styles, only while the shop is open for this visitor.
 */
function grs_assets() {
	if ( ! grow_shop_is_open() ) {
		return;
	}
	if ( grs_get( 'appearance', 'stylesheet' ) ) {
		wp_enqueue_style( 'grow-shop', GRS_URL . 'assets/shop.css', array(), GRS_VERSION );
		$accent = sanitize_hex_color( (string) grs_get( 'appearance', 'accent' ) );
		if ( $accent ) {
			wp_add_inline_style( 'grow-shop', ':root{--grs-accent:' . $accent . ';}' );
		}
	}
	wp_enqueue_script( 'grow-shop', GRS_URL . 'assets/shop.js', array(), GRS_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );

	$basket_page = (int) grs_get( 'checkout', 'basket_page' );
	$config      = array(
		'rest'        => esc_url_raw( rest_url( 'grow-shop/v1/' ) ),
		'nonce'       => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
		'style'       => 'page' === grs_get( 'checkout', 'basket_style' ) && $basket_page ? 'page' : 'panel',
		'basketPage'  => $basket_page ? get_permalink( $basket_page ) : '',
		'storageKey'  => 'grow_shop_basket_' . substr( md5( home_url() ), 0, 8 ),
		'cancelled'   => (string) grs_get( 'checkout', 'cancelled' ),
		'thanks'      => (string) grs_get( 'checkout', 'thanks' ),
		'i18n'        => array(
			'added'     => __( 'Added to your basket.', 'grow-shop' ),
			'view'      => __( 'View basket', 'grow-shop' ),
			'empty'     => __( 'Your basket is empty.', 'grow-shop' ),
			'remove'    => __( 'Remove', 'grow-shop' ),
			'qty'       => __( 'Quantity', 'grow-shop' ),
			'each'      => __( 'each', 'grow-shop' ),
			'working'   => __( 'One moment…', 'grow-shop' ),
			'redirect'  => __( 'Taking you to secure payment…', 'grow-shop' ),
			'error'     => __( 'Something went wrong. Please try again.', 'grow-shop' ),
			'checking'  => __( 'Checking your payment…', 'grow-shop' ),
			'notYet'    => __( 'Your payment is still being confirmed. You\'ll get an email as soon as it is.', 'grow-shop' ),
			/* translators: %s: order number */
			'orderNo'   => __( 'Order number: %s', 'grow-shop' ),
		),
	);
	wp_add_inline_script( 'grow-shop', 'window.growShop=' . wp_json_encode( $config ) . ';', 'before' );
}
add_action( 'wp_enqueue_scripts', 'grs_assets' );

/**
 * The basket panel and, if set to automatic, the floating basket button.
 */
function grs_footer() {
	if ( ! grow_shop_is_open() ) {
		return;
	}
	if ( 'auto' === grs_get( 'appearance', 'icon' ) ) {
		echo '<div class="grs-float">' . grs_basket_icon_html() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	if ( empty( $GLOBALS['grs_inline_basket'] ) ) {
		$inline = false;
		include grs_template( 'basket.php' );
	}
}
add_action( 'wp_footer', 'grs_footer', 5 );
