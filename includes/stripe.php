<?php
/**
 * Stripe, through its HTTP API with wp_remote_request (no SDK). The shop
 * needs one restricted key per mode with:
 *   Checkout Sessions: write    (create and read checkouts)
 *   Refunds:           write    (refund from the order screen)
 *   Webhook Endpoints: write    (the Connect webhook button)
 *
 * @package GrowShop
 */

defined( 'ABSPATH' ) || exit;

/**
 * Call Stripe.
 *
 * @param string $method  GET | POST | DELETE.
 * @param string $path    e.g. checkout/sessions.
 * @param array  $params  Parameters (nested arrays are encoded Stripe's way).
 * @param string $mode    test | live, blank for the current mode.
 * @param string $idem    Idempotency key for POSTs.
 * @return array|WP_Error Decoded response.
 */
function grs_stripe( $method, $path, $params = array(), $mode = '', $idem = '' ) {
	$key = grs_stripe_key( $mode );
	if ( '' === $key ) {
		return new WP_Error( 'grs_no_key', __( 'No Stripe key is saved for this mode. Add one under Shop, Settings, Payments.', 'grow-shop' ) );
	}
	$url  = 'https://api.stripe.com/v1/' . ltrim( $path, '/' );
	$args = array(
		'method'  => $method,
		'timeout' => 30,
		'headers' => array(
			'Authorization'  => 'Bearer ' . $key,
			'Stripe-Version' => GRS_STRIPE_V,
			'User-Agent'     => 'GrowShop/' . GRS_VERSION . ' (WordPress)',
		),
	);
	if ( 'GET' === $method || 'DELETE' === $method ) {
		if ( $params ) {
			$url .= '?' . grs_stripe_encode( $params );
		}
	} else {
		$args['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
		$args['body']                    = grs_stripe_encode( $params );
		if ( $idem ) {
			$args['headers']['Idempotency-Key'] = $idem;
		}
	}
	$res = wp_remote_request( $url, $args );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
	if ( $code >= 400 || ! is_array( $body ) ) {
		$msg = is_array( $body ) && isset( $body['error']['message'] ) ? $body['error']['message'] : 'HTTP ' . $code;
		return new WP_Error( 'grs_stripe_' . $code, $msg, array( 'status' => $code ) );
	}
	return $body;
}

/**
 * Form-encode nested parameters: a[b][0]=x.
 *
 * @param array  $params Parameters.
 * @param string $prefix Key prefix.
 * @return string
 */
function grs_stripe_encode( $params, $prefix = '' ) {
	$parts = array();
	foreach ( $params as $k => $v ) {
		$key = '' === $prefix ? (string) $k : $prefix . '[' . $k . ']';
		if ( is_array( $v ) ) {
			$inner = grs_stripe_encode( $v, $key );
			if ( '' !== $inner ) {
				$parts[] = $inner;
			}
		} elseif ( null !== $v ) {
			if ( is_bool( $v ) ) {
				$v = $v ? 'true' : 'false';
			}
			$parts[] = rawurlencode( $key ) . '=' . rawurlencode( (string) $v );
		}
	}
	return implode( '&', $parts );
}

/**
 * Check a webhook's Stripe-Signature header against the saved secrets.
 *
 * @param string $payload Raw body.
 * @param string $header  Stripe-Signature.
 * @return bool
 */
function grs_stripe_verify( $payload, $header ) {
	$t    = 0;
	$sigs = array();
	foreach ( explode( ',', (string) $header ) as $part ) {
		$kv = explode( '=', trim( $part ), 2 );
		if ( 2 !== count( $kv ) ) {
			continue;
		}
		if ( 't' === $kv[0] ) {
			$t = (int) $kv[1];
		} elseif ( 'v1' === $kv[0] ) {
			$sigs[] = $kv[1];
		}
	}
	if ( ! $t || ! $sigs || abs( time() - $t ) > 300 ) {
		return false;
	}
	foreach ( array( 'test', 'live' ) as $mode ) {
		$wh = (array) grs_get( 'payments', 'wh_' . $mode );
		if ( empty( $wh['secret'] ) ) {
			continue;
		}
		$expected = hash_hmac( 'sha256', $t . '.' . $payload, $wh['secret'] );
		foreach ( $sigs as $sig ) {
			if ( hash_equals( $expected, $sig ) ) {
				return true;
			}
		}
	}
	return false;
}

/**
 * Where Stripe sends webhooks for this site.
 *
 * @return string
 */
function grs_webhook_url() {
	return rest_url( 'grow-shop/v1/webhook' );
}

/**
 * Events the shop listens to.
 *
 * @return string[]
 */
function grs_webhook_events() {
	return array(
		'checkout.session.completed',
		'checkout.session.async_payment_succeeded',
		'checkout.session.async_payment_failed',
		'checkout.session.expired',
		'charge.refunded',
	);
}

/**
 * Create (or replace) this site's webhook endpoint in Stripe and keep its
 * signing secret. Nothing to copy across by hand.
 *
 * @param string $mode test | live.
 * @return true|WP_Error
 */
function grs_stripe_connect_webhook( $mode ) {
	$old = (array) grs_get( 'payments', 'wh_' . $mode );
	$url = grs_webhook_url();

	// Remove our old endpoint, and any other endpoint pointing at this URL.
	$list = grs_stripe( 'GET', 'webhook_endpoints', array( 'limit' => 100 ), $mode );
	if ( is_wp_error( $list ) ) {
		return $list;
	}
	foreach ( (array) $list['data'] as $ep ) {
		if ( ( ! empty( $old['id'] ) && $ep['id'] === $old['id'] ) || $ep['url'] === $url ) {
			grs_stripe( 'DELETE', 'webhook_endpoints/' . $ep['id'], array(), $mode );
		}
	}

	$res = grs_stripe(
		'POST',
		'webhook_endpoints',
		array(
			'url'            => $url,
			'enabled_events' => grs_webhook_events(),
			'description'    => 'Grow Shop: ' . wp_parse_url( home_url(), PHP_URL_HOST ),
			'api_version'    => GRS_STRIPE_V,
		),
		$mode
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$raw                       = get_option( GRS_OPTION, array() );
	$payments                  = array_merge( grs_defaults()['payments'], (array) ( $raw['payments'] ?? array() ) );
	$payments[ 'wh_' . $mode ] = array(
		'id'     => $res['id'],
		'secret' => $res['secret'],
		'url'    => $url,
	);
	grs_save_group( 'payments', $payments );
	grs_log( sprintf( 'Webhook connected (%s): %s', $mode, $url ) );
	return true;
}

/**
 * Check a key: which mode it is, and whether each permission works.
 *
 * @param string $mode test | live.
 * @return array rows of array( label, ok, detail ).
 */
function grs_stripe_test( $mode ) {
	$key  = grs_stripe_key( $mode );
	$rows = array();
	if ( '' === $key ) {
		return array( array( __( 'Key', 'grow-shop' ), false, __( 'None saved.', 'grow-shop' ) ) );
	}
	$kind = preg_match( '/^(rk|sk)_(test|live)_/', $key, $m ) ? $m : null;
	if ( ! $kind ) {
		$rows[] = array( __( 'Key', 'grow-shop' ), false, __( 'That doesn\'t look like a Stripe secret or restricted key.', 'grow-shop' ) );
		return $rows;
	}
	$rows[] = array(
		__( 'Key', 'grow-shop' ),
		$kind[2] === $mode,
		$kind[2] === $mode
			? ( 'rk' === $kind[1] ? __( 'Restricted key, right mode.', 'grow-shop' ) : __( 'Full secret key. It works, but a restricted key is safer.', 'grow-shop' ) )
			/* translators: %s: test or live */
			: sprintf( __( 'This is a %s key, in the wrong box.', 'grow-shop' ), $kind[2] ),
	);

	$acct = grs_stripe( 'GET', 'account', array(), $mode );
	if ( ! is_wp_error( $acct ) ) {
		$name   = $acct['settings']['dashboard']['display_name'] ?? ( $acct['business_profile']['name'] ?? $acct['id'] );
		$rows[] = array( __( 'Account', 'grow-shop' ), true, $name . ( empty( $acct['charges_enabled'] ) ? ' ' . __( '(charges not enabled yet)', 'grow-shop' ) : '' ) );
	}

	$checks = array(
		__( 'Checkout Sessions', 'grow-shop' ) => 'checkout/sessions',
		__( 'Refunds', 'grow-shop' )           => 'refunds',
		__( 'Webhook Endpoints', 'grow-shop' ) => 'webhook_endpoints',
	);
	foreach ( $checks as $label => $path ) {
		$res    = grs_stripe( 'GET', $path, array( 'limit' => 1 ), $mode );
		$rows[] = array( $label, ! is_wp_error( $res ), is_wp_error( $res ) ? $res->get_error_message() : __( 'Allowed.', 'grow-shop' ) );
	}

	$wh     = (array) grs_get( 'payments', 'wh_' . $mode );
	$rows[] = array(
		__( 'Webhook', 'grow-shop' ),
		! empty( $wh['secret'] ) && grs_webhook_url() === ( $wh['url'] ?? '' ),
		empty( $wh['secret'] )
			? __( 'Not connected. Use Connect webhook.', 'grow-shop' )
			: ( grs_webhook_url() === ( $wh['url'] ?? '' ) ? __( 'Connected.', 'grow-shop' ) : __( 'Connected to a different address (the site moved?). Connect it again.', 'grow-shop' ) ),
	);
	return $rows;
}
