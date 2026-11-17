<?php
/**
 * Emails: four templates edited under Shop, Settings, Emails, with
 * placeholders, wrapped in a simple branded layout (templates/email.php,
 * which a theme can override in grow-shop/email.php).
 *
 * @package GrowShop
 */

defined( 'ABSPATH' ) || exit;

/**
 * The emails, for the settings screen.
 *
 * @return array key => array( label, to whom )
 */
function grs_email_types() {
	return array(
		'confirmation' => array( __( 'Order confirmation', 'grow-shop' ), __( 'To the customer, when they pay.', 'grow-shop' ) ),
		'new_order'    => array( __( 'New order', 'grow-shop' ), __( 'To the shop, when an order is paid. Includes the packing list.', 'grow-shop' ) ),
		'dispatched'   => array( __( 'Dispatched', 'grow-shop' ), __( 'To the customer, when you mark the order dispatched.', 'grow-shop' ) ),
		'refunded'     => array( __( 'Refund', 'grow-shop' ), __( 'To the customer, when you refund from the order screen.', 'grow-shop' ) ),
	);
}

/**
 * Placeholders, for the settings screen.
 *
 * @return array placeholder => description
 */
function grs_placeholders() {
	return array(
		'{order_number}'   => __( 'e.g. AP-1001', 'grow-shop' ),
		'{first_name}'     => __( 'Customer\'s first name', 'grow-shop' ),
		'{customer_name}'  => __( 'Full name', 'grow-shop' ),
		'{customer_email}' => '',
		'{customer_phone}' => '',
		'{items}'          => __( 'Table of items, postage and total', 'grow-shop' ),
		'{total}'          => '',
		'{address}'        => __( 'Delivery address', 'grow-shop' ),
		'{postage}'        => __( 'Postage method', 'grow-shop' ),
		'{tracking}'       => __( 'Tracking line (blank if none)', 'grow-shop' ),
		'{refund_amount}'  => '',
		'{order_link}'     => __( 'Link to the order in wp-admin (shop emails only)', 'grow-shop' ),
		'{shop_name}'      => '',
		'{site_url}'       => '',
	);
}

/**
 * Send one of the emails for an order.
 *
 * @param string $type  confirmation | new_order | dispatched | refunded.
 * @param int    $id    Order.
 * @param string $to    Override recipient (test sends).
 * @return bool
 */
function grs_send_email( $type, $id, $to = '' ) {
	$tpl = grs_get( 'emails', 'templates' )[ $type ] ?? null;
	if ( ! $tpl || ( empty( $tpl['enabled'] ) && '' === $to ) ) {
		return false;
	}
	$data = grs_order_data( $id );
	$shop = 'new_order' === $type;
	if ( '' === $to ) {
		$to = $shop ? grs_notify_to() : $data['customer']['email'];
	}
	if ( ! $to ) {
		return false;
	}

	$subject = wp_strip_all_tags( grs_fill( (string) $tpl['subject'], $id, false ) );
	$body    = grs_email_html( grs_fill( (string) $tpl['body'], $id, true ), $shop );

	$headers = array( 'Content-Type: text/html; charset=UTF-8' );
	$from    = sanitize_email( (string) grs_get( 'emails', 'from_email' ) );
	if ( $from ) {
		$name      = trim( (string) grs_get( 'emails', 'from_name' ) );
		$name      = '' !== $name ? $name : grs_shop_name();
		$headers[] = sprintf( 'From: %s <%s>', str_replace( array( '<', '>', "\r", "\n", '"' ), '', $name ), $from );
	}
	if ( $shop && is_email( $data['customer']['email'] ) ) {
		$headers[] = sprintf( 'Reply-To: %s <%s>', str_replace( array( '<', '>', "\r", "\n", '"' ), '', $data['customer']['name'] ), $data['customer']['email'] );
	} elseif ( ! $shop ) {
		$reply = grs_parse_addresses( (string) grs_get( 'emails', 'reply_to' ) );
		$reply = $reply ? $reply : grs_notify_to();
		$headers[] = 'Reply-To: ' . $reply[0];
	}

	$ok = (bool) wp_mail( $to, $subject, $body, $headers );
	if ( get_post( $id ) && GRS_ORDER === get_post_type( $id ) ) {
		$labels = grs_email_types();
		grs_order_log(
			$id,
			sprintf(
				/* translators: 1: email name, 2: sent or failed, 3: recipients */
				__( '%1$s email %2$s to %3$s.', 'grow-shop' ),
				$labels[ $type ][0],
				$ok ? __( 'sent', 'grow-shop' ) : __( 'FAILED', 'grow-shop' ),
				implode( ', ', (array) $to )
			)
		);
	}
	return $ok;
}

/**
 * Fill in placeholders. In HTML mode the template text is escaped first,
 * then the placeholders become HTML.
 *
 * @param string $text Template text.
 * @param int    $id   Order.
 * @param bool   $html HTML output.
 * @return string
 */
function grs_fill( $text, $id, $html ) {
	$data  = grs_order_data( $id );
	$name  = trim( (string) $data['customer']['name'] );
	$first = '' !== $name ? strtok( $name, ' ' ) : __( 'there', 'grow-shop' );
	$addr  = grs_address_lines( $data['address'] );

	$tracking = '';
	if ( $data['tracking'] ) {
		$tracking = preg_match( '#^https?://#', $data['tracking'] )
			? ( $html ? '<a href="' . esc_url( $data['tracking'] ) . '">' . esc_html__( 'Track your parcel', 'grow-shop' ) . '</a>' : __( 'Track your parcel:', 'grow-shop' ) . ' ' . $data['tracking'] )
			: ( $html ? esc_html__( 'Tracking number:', 'grow-shop' ) . ' <strong>' . esc_html( $data['tracking'] ) . '</strong>' : __( 'Tracking number:', 'grow-shop' ) . ' ' . $data['tracking'] );
	}

	$plain = array(
		'{order_number}'   => grs_order_number( $id ),
		'{first_name}'     => $first,
		'{customer_name}'  => $name,
		'{customer_email}' => $data['customer']['email'],
		'{customer_phone}' => $data['customer']['phone'] ? $data['customer']['phone'] : '-',
		'{total}'          => grs_money( $data['total'], false ),
		'{postage}'        => $data['method_name'],
		'{refund_amount}'  => grs_money( (int) ( $data['last_refund'] ?? $data['refunded'] ), false ),
		'{shop_name}'      => grs_shop_name(),
		'{site_url}'       => home_url( '/' ),
		'{order_link}'     => admin_url( 'admin.php?page=grow-shop-order&id=' . $id ),
	);

	if ( ! $html ) {
		$plain['{items}']    = '';
		$plain['{address}']  = implode( ', ', $addr );
		$plain['{tracking}'] = $tracking;
		return strtr( $text, $plain );
	}

	$rich = array();
	foreach ( $plain as $k => $v ) {
		$rich[ $k ] = esc_html( $v );
	}
	$rich['{order_link}'] = '<a href="' . esc_url( $plain['{order_link}'] ) . '">' . esc_html__( 'Open the order', 'grow-shop' ) . '</a>';
	$rich['{site_url}']   = '<a href="' . esc_url( $plain['{site_url}'] ) . '">' . esc_html( preg_replace( '#^https?://#', '', untrailingslashit( $plain['{site_url}'] ) ) ) . '</a>';
	$rich['{items}']      = grs_items_table( $data );
	$rich['{address}']    = implode( '<br>', array_map( 'esc_html', $addr ) );
	$rich['{tracking}']   = $tracking;

	// Escape the owner's text, keep line breaks, then drop in the HTML.
	$out = nl2br( esc_html( $text ) );
	return strtr( $out, $rich );
}

/**
 * The items table for emails.
 *
 * @param array $data Order data.
 * @return string
 */
function grs_items_table( $data ) {
	$td  = 'padding:8px 0;border-bottom:1px solid #e6e6e6;vertical-align:top;';
	$out = '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:4px 0;">';
	foreach ( $data['lines'] as $line ) {
		$out .= '<tr><td style="' . $td . '">' . esc_html( $line['name'] );
		if ( $line['options'] ) {
			$out .= '<br><span style="color:#777;font-size:13px;">' . esc_html( $line['options'] ) . '</span>';
		}
		$out .= '</td><td style="' . $td . 'text-align:center;white-space:nowrap;padding:8px 12px;">' . (int) $line['qty'] . ' &times; ' . esc_html( grs_money( $line['unit'], false ) ) . '</td>';
		$out .= '<td style="' . $td . 'text-align:right;white-space:nowrap;">' . esc_html( grs_money( $line['total'], false ) ) . '</td></tr>';
	}
	$sum  = 'padding:6px 0;text-align:right;';
	$out .= '<tr><td colspan="2" style="' . $sum . '">' . esc_html__( 'Items', 'grow-shop' ) . '</td><td style="' . $sum . '">' . esc_html( grs_money( $data['subtotal'], false ) ) . '</td></tr>';
	$out .= '<tr><td colspan="2" style="' . $sum . '">' . esc_html( $data['method_name'] ? $data['method_name'] : __( 'Postage', 'grow-shop' ) ) . '</td><td style="' . $sum . '">' . esc_html( $data['shipping'] ? grs_money( $data['shipping'], false ) : __( 'Free', 'grow-shop' ) ) . '</td></tr>';
	$out .= '<tr><td colspan="2" style="' . $sum . 'font-weight:bold;">' . esc_html__( 'Total', 'grow-shop' ) . '</td><td style="' . $sum . 'font-weight:bold;">' . esc_html( grs_money( $data['total'], false ) ) . '</td></tr>';
	if ( $data['refunded'] ) {
		$out .= '<tr><td colspan="2" style="' . $sum . '">' . esc_html__( 'Refunded', 'grow-shop' ) . '</td><td style="' . $sum . '">-' . esc_html( grs_money( $data['refunded'], false ) ) . '</td></tr>';
	}
	$out .= '</table>';
	return $out;
}

/**
 * Wrap the body in the email layout.
 *
 * @param string $content HTML.
 * @param bool   $shop    Internal email (no legal footer).
 * @return string
 */
function grs_email_html( $content, $shop = false ) {
	$accent = sanitize_hex_color( (string) grs_get( 'emails', 'accent' ) );
	$accent = $accent ? $accent : '#3c4a3e';
	$logo   = (int) grs_get( 'emails', 'logo' ) ? wp_get_attachment_image_url( (int) grs_get( 'emails', 'logo' ), 'medium' ) : '';
	$links  = $shop ? array() : grs_legal_links();
	ob_start();
	include grs_template( 'email.php' );
	return (string) ob_get_clean();
}

/**
 * Legal pages chosen under Checkout: label => URL.
 *
 * @return array
 */
function grs_legal_links() {
	$out = array();
	$map = array(
		'terms_page'   => __( 'Terms of sale', 'grow-shop' ),
		'returns_page' => __( 'Returns', 'grow-shop' ),
		'privacy_page' => __( 'Privacy', 'grow-shop' ),
	);
	foreach ( $map as $key => $label ) {
		$page = (int) grs_get( 'checkout', $key );
		if ( $page && 'publish' === get_post_status( $page ) ) {
			$out[ $label ] = get_permalink( $page );
		}
	}
	return $out;
}
