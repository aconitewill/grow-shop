<?php
/**
 * Plugin Name:       Grow Shop
 * Description:       A small shop for any post type: quantity pricing, options, stock, shipping zones, editable emails, and payment on Stripe's hosted checkout. Everything a shop owner changes is a setting under Shop.
 * Version:           0.1.1
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Update URI:        https://github.com/aconitewill/grow-shop
 * Author:            Grow Creative
 * Author URI:        https://growcreative.com/
 * License:           GPL-2.0-or-later
 * Text Domain:       grow-shop
 *
 * @package GrowShop
 */

defined( 'ABSPATH' ) || exit;

const GRS_VERSION   = '0.1.1';
const GRS_FILE      = __FILE__;
const GRS_ORDER     = 'grow_order';
const GRS_CAP       = 'manage_grow_shop';
const GRS_OPTION    = 'grow_shop_settings';
const GRS_STRIPE_V  = '2024-06-20';

define( 'GRS_DIR', plugin_dir_path( __FILE__ ) );
define( 'GRS_URL', plugin_dir_url( __FILE__ ) );

require GRS_DIR . 'includes/settings.php';
require GRS_DIR . 'includes/countries.php';
require GRS_DIR . 'includes/products.php';
require GRS_DIR . 'includes/pricing.php';
require GRS_DIR . 'includes/stripe.php';
require GRS_DIR . 'includes/orders.php';
require GRS_DIR . 'includes/emails.php';
require GRS_DIR . 'includes/rest.php';
require GRS_DIR . 'includes/frontend.php';
require GRS_DIR . 'includes/privacy.php';

if ( is_admin() ) {
	require GRS_DIR . 'admin/settings.php';
	require GRS_DIR . 'admin/products.php';
	require GRS_DIR . 'admin/orders.php';
}

grs_update_checker();

/**
 * Activation: capability, cron, order numbers.
 */
function grs_activate() {
	grs_add_caps();
	grs_schedule();
	add_option( 'grow_shop_next_number', 1001, '', false );
	update_option( 'grow_shop_version', GRS_VERSION, false );
}
register_activation_hook( __FILE__, 'grs_activate' );

/**
 * Deactivation: stop the cron job. Orders and settings stay.
 */
function grs_deactivate() {
	wp_clear_scheduled_hook( 'grs_hourly' );
}
register_deactivation_hook( __FILE__, 'grs_deactivate' );

/**
 * Who can run the shop: administrators, plus any roles added by filter.
 */
function grs_add_caps() {
	foreach ( (array) apply_filters( 'grow_shop_roles', array( 'administrator' ) ) as $role_name ) {
		$role = get_role( $role_name );
		if ( $role && ! $role->has_cap( GRS_CAP ) ) {
			$role->add_cap( GRS_CAP );
		}
	}
}

/**
 * The hourly tidy-up of abandoned checkouts.
 */
function grs_schedule() {
	if ( ! wp_next_scheduled( 'grs_hourly' ) ) {
		wp_schedule_event( time() + 300, 'hourly', 'grs_hourly' );
	}
}

/**
 * One-off upgrade steps after an update. No hand work on the server.
 */
function grs_maybe_upgrade() {
	if ( GRS_VERSION === get_option( 'grow_shop_version', '' ) ) {
		return;
	}
	grs_add_caps();
	grs_schedule();
	add_option( 'grow_shop_next_number', 1001, '', false );
	update_option( 'grow_shop_version', GRS_VERSION, false );
}
add_action( 'init', 'grs_maybe_upgrade', 1 );

/**
 * Updates from GitHub releases (Plugin Update Checker, MIT, in lib/).
 * Off in a git checkout, so updating a development copy can't wipe its
 * .git, and off when GRS_DISABLE_UPDATES is true.
 */
function grs_update_checker() {
	$on = ! is_dir( GRS_DIR . '.git' ) && ! ( defined( 'GRS_DISABLE_UPDATES' ) && GRS_DISABLE_UPDATES );
	if ( ! apply_filters( 'grow_shop_update_checker', $on ) || ! is_readable( GRS_DIR . 'lib/plugin-update-checker/plugin-update-checker.php' ) ) {
		return;
	}
	require_once GRS_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
	$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker( 'https://github.com/aconitewill/grow-shop/', GRS_FILE, 'grow-shop' );
	$checker->getVcsApi()->enableReleaseAssets( '/^grow-shop\.zip$/' );
	$GLOBALS['grs_update_checker'] = $checker;
}
