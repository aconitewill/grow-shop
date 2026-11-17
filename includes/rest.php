<?php
/**
 * REST endpoints. All POST, so Kinsta never caches them, and none needs a
 * nonce, so cached pages keep working:
 *   /grow-shop/v1/quote     Price a basket (read-only).
 *   /grow-shop/v1/checkout  Start a Stripe checkout (rate-limited).
 *   /grow-shop/v1/confirm   Thank-you page check with Stripe (backup to the webhook).
 *   /grow-shop/v1/webhook   Stripe's notifications (signature-checked).
 *
 * In "admins only" mode the browser sends the REST nonce (printed only on
 * logged-in pages, which are never cached) so WordPress knows who it is.
 *
 * @package GrowShop
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the routes.
 */
function grs_rest_routes() {
	$open = '__return_true';
	register_rest_route( 'grow-shop/v1', '/quote', array( 'methods' => 'POST', 'callback' => 'grs_rest_quote', 'permission_callback' => $open ) );
	register_rest_route( 'grow-shop/v1', '/checkout', array( 'methods' => 'POST', 'callback' => 'grs_rest_checkout', 'permission_callback' => $open ) );
	register_rest_route( 'grow-shop/v1', '/confirm', array( 'methods' => 'POST', 'callback' => 'grs_rest_confirm', 'permission_callback' => $open ) );
	register_rest_route( 'grow-shop/v1', '/webhook', array( 'methods' => 'POST', 'callback' => 'grs_rest_webhook', 'permission_callback' => $open ) );
}
add_action( 'rest_api_init', 'grs_rest_routes' );

/**
 * Refuse when the shop is off for this visitor.
 *
 * @return WP_REST_Response|null
 */
function grs_rest_closed() {
	if ( grow_shop_is_open() ) {
		return null;
	}
	return new WP_REST_Response( array( 'ok' => false, 'errors' => array( __( 'The shop is not open.', 'grow-shop' ) ) ), 403 );
}

/**
 * Price a basket.
 *
 * @param WP_REST_Request $req Request.
 * @return WP_REST_Response
 */
function grs_rest_quote( WP_REST_Request $req ) {
	$closed = grs_rest_closed();
	if ( $closed ) {
		return $closed;
	}
	$q = grs_quote( grs_clean_items( $req->get_param( 'items' ) ), (int) ( $req->get_param( 'zone' ) ?? -1 ), (int) ( $req->get_param( 'method' ) ?? -1 ) );
	$res = new WP_REST_Response( grs_quote_for_browser( $q ) );
	$res->header( 'Cache-Control', 'no-store' );
	return $res;
}

/**
 * Start a checkout.
 *
 * @param WP_REST_Request $req Request.
 * @return WP_REST_Response
 */
function grs_rest_checkout( WP_REST_Request $req ) {
	$closed = grs_rest_closed();
	if ( $closed ) {
		return $closed;
	}
	if ( grs_rate_limited( 'checkout', 20 ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'errors' => array( __( 'Too many tries. Please wait a few minutes.', 'grow-shop' ) ) ), 429 );
	}
	$result = grs_start_checkout(
		grs_clean_items( $req->get_param( 'items' ) ),
		(int) ( $req->get_param( 'zone' ) ?? -1 ),
		(int) ( $req->get_param( 'method' ) ?? -1 ),
		(string) $req->get_param( 'return' )
	);
	if ( is_wp_error( $result ) ) {
		$data    = $result->get_error_data();
		$message = __( 'Sorry, the checkout could not be started. Please try again in a moment.', 'grow-shop' );
		if ( 'grs_basket' === $result->get_error_code() ) {
			$message = $result->get_error_message();
		} elseif ( current_user_can( GRS_CAP ) ) {
			// Shop admins see the real reason (customers never do).
			/* translators: %s: the reason from Stripe or the shop */
			$message = sprintf( __( 'Checkout could not start (shown to admins only): %s', 'grow-shop' ), $result->get_error_message() );
		}
		$body = array(
			'ok'     => false,
			'errors' => array( $message ),
		);
		if ( is_array( $data ) && isset( $data['quote'] ) ) {
			$body['quote'] = grs_quote_for_browser( $data['quote'] );
		}
		return new WP_REST_Response( $body, 400 );
	}
	return new WP_REST_Response( array( 'ok' => true, 'url' => $result['url'] ) );
}

/**
 * The thank-you page asks: did this session pay? Records the order if the
 * webhook hasn't yet. Only says yes or no and the order number.
 *
 * @param WP_REST_Request $req Request.
 * @return WP_REST_Response
 */
function grs_rest_confirm( WP_REST_Request $req ) {
	$sid = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $req->get_param( 'session' ) );
	if ( ! preg_match( '/^cs_(test|live)_/', $sid ) || grs_rate_limited( 'confirm', 30 ) ) {
		return new WP_REST_Response( array( 'ok' => false ), 400 );
	}
	$mode    = 0 === strpos( $sid, 'cs_live_' ) ? 'live' : 'test';
	$session = grs_stripe( 'GET', 'checkout/sessions/' . $sid, array(), $mode );
	if ( is_wp_error( $session ) ) {
		return new WP_REST_Response( array( 'ok' => false ), 400 );
	}
	$id = grs_complete_session( $session );
	if ( ! $id || 'grs-pending' === get_post_status( $id ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'pending' => 'open' === ( $session['status'] ?? '' ) || 'unpaid' === ( $session['payment_status'] ?? '' ) ) );
	}
	return new WP_REST_Response(
		array(
			'ok'      => true,
			'number'  => grs_order_number( $id ),
			'message' => (string) grs_get( 'checkout', 'thanks' ),
		)
	);
}

/**
 * Stripe webhook.
 *
 * @param WP_REST_Request $req Request.
 * @return WP_REST_Response
 */
function grs_rest_webhook( WP_REST_Request $req ) {
	$payload = $req->get_body();
	if ( ! grs_stripe_verify( $payload, (string) $req->get_header( 'stripe_signature' ) ) ) {
		return new WP_REST_Response( array( 'received' => false ), 400 );
	}
	$event = json_decode( $payload, true );
	$obj   = $event['data']['object'] ?? array();
	switch ( $event['type'] ?? '' ) {
		case 'checkout.session.completed':
		case 'checkout.session.async_payment_succeeded':
			grs_complete_session( $obj );
			break;
		case 'checkout.session.expired':
		case 'checkout.session.async_payment_failed':
			$id = grs_order_for_session( $obj );
			if ( $id && 'grs-pending' === get_post_status( $id ) ) {
				wp_delete_post( $id, true );
			}
			break;
		case 'charge.refunded':
			grs_sync_charge_refund( $obj );
			break;
	}
	return new WP_REST_Response( array( 'received' => true ) );
}

/**
 * Per-visitor rate limit, by hashed IP, per hour.
 *
 * @param string $what  Bucket.
 * @param int    $limit Allowed per hour.
 * @return bool True when over the limit.
 */
function grs_rate_limited( $what, $limit ) {
	$ip  = isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) : ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
	$key = 'grs_rl_' . $what . '_' . substr( md5( $ip . wp_salt() ), 0, 16 );
	$n   = (int) get_transient( $key );
	if ( $n >= $limit ) {
		return true;
	}
	set_transient( $key, $n + 1, HOUR_IN_SECONDS );
	return false;
}
