<?php
/**
 * Shop, Settings: General, Products, Shipping, Payments, Emails, Checkout,
 * Appearance. Each tab saves on its own. Repeating rows (quantity prices,
 * option groups, zones, methods) always show a spare blank row, so there's
 * no JavaScript to break; blank rows are ignored on save.
 *
 * @package GrowShop
 */

defined( 'ABSPATH' ) || exit;

/**
 * The tabs.
 *
 * @return array slug => label
 */
function grs_tabs() {
	return array(
		'general'    => __( 'General', 'grow-shop' ),
		'products'   => __( 'Products', 'grow-shop' ),
		'shipping'   => __( 'Shipping', 'grow-shop' ),
		'payments'   => __( 'Payments', 'grow-shop' ),
		'emails'     => __( 'Emails', 'grow-shop' ),
		'checkout'   => __( 'Checkout', 'grow-shop' ),
		'appearance' => __( 'Appearance', 'grow-shop' ),
	);
}

/**
 * Menu.
 */
function grs_settings_menu() {
	add_submenu_page( 'edit.php?post_type=' . GRS_ORDER, __( 'Shop settings', 'grow-shop' ), __( 'Settings', 'grow-shop' ), GRS_CAP, 'grow-shop-settings', 'grs_render_settings' );
}
add_action( 'admin_menu', 'grs_settings_menu' );

/**
 * Settings URL.
 *
 * @param string $tab  Tab.
 * @param array  $args Extra query args.
 * @return string
 */
function grs_settings_url( $tab = 'general', $args = array() ) {
	return add_query_arg( array_merge( array( 'post_type' => GRS_ORDER, 'page' => 'grow-shop-settings', 'tab' => $tab ), $args ), admin_url( 'edit.php' ) );
}

/**
 * Admin assets on our screens.
 *
 * @param string $hook Screen hook.
 */
function grs_admin_assets( $hook ) {
	$screen = get_current_screen();
	if ( ! $screen || ( false === strpos( (string) $hook, 'grow-shop' ) && GRS_ORDER !== $screen->post_type && ! in_array( $screen->post_type, grs_sellable_types(), true ) ) ) {
		return;
	}
	wp_enqueue_style( 'grow-shop-admin', GRS_URL . 'assets/admin.css', array(), GRS_VERSION );
	if ( false !== strpos( (string) $hook, 'grow-shop-settings' ) ) {
		wp_enqueue_media();
		wp_enqueue_script( 'grow-shop-admin', GRS_URL . 'assets/admin.js', array( 'jquery' ), GRS_VERSION, true );
	}
}
add_action( 'admin_enqueue_scripts', 'grs_admin_assets' );

/**
 * Check the request and the user.
 *
 * @param string $action Nonce action.
 */
function grs_admin_check( $action ) {
	check_admin_referer( $action );
	if ( ! current_user_can( GRS_CAP ) ) {
		wp_die( esc_html__( 'Not allowed.', 'grow-shop' ) );
	}
}

/**
 * Value from a posted array.
 *
 * @param array  $in  Input.
 * @param string $key Key.
 * @return string
 */
function grs_in( $in, $key ) {
	return isset( $in[ $key ] ) && ! is_array( $in[ $key ] ) ? trim( (string) $in[ $key ] ) : '';
}

/**
 * Rows of "number | money" from paired inputs, blank rows dropped, sorted.
 *
 * @param array $rows Posted rows of array( min, price ).
 * @return array
 */
function grs_clean_pairs( $rows ) {
	$out = array();
	foreach ( (array) $rows as $row ) {
		$min = isset( $row[0] ) ? (int) $row[0] : 0;
		if ( $min < 1 || ! isset( $row[1] ) || '' === trim( (string) $row[1] ) ) {
			continue;
		}
		$out[ $min ] = array( $min, grs_parse_money( $row[1] ) );
	}
	ksort( $out );
	return array_values( $out );
}

/**
 * Lines of "label | +1.50" into option values.
 *
 * @param string $text Text.
 * @return array
 */
function grs_parse_values( $text ) {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
		$parts = array_map( 'trim', explode( '|', $line, 2 ) );
		if ( '' === $parts[0] ) {
			continue;
		}
		$out[] = array(
			'label' => sanitize_text_field( $parts[0] ),
			'diff'  => isset( $parts[1] ) ? grs_parse_money( $parts[1] ) : 0,
		);
	}
	return $out;
}

/**
 * Lines of "quantity | price" (postage bands).
 *
 * @param string $text Text.
 * @return array
 */
function grs_parse_bands( $text ) {
	$rows = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
		$parts = array_map( 'trim', explode( '|', $line, 2 ) );
		if ( 2 === count( $parts ) ) {
			$rows[] = $parts;
		}
	}
	return grs_clean_pairs( $rows );
}

/**
 * Save a tab.
 */
function grs_save_settings() {
	grs_admin_check( 'grs_settings' );
	$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : '';
	$in  = isset( $_POST['grs'] ) && is_array( $_POST['grs'] ) ? wp_unslash( $_POST['grs'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per field below.

	switch ( $tab ) {
		case 'general':
			$cur = grs_currencies();
			grs_save_group(
				'general',
				array(
					'mode'            => in_array( grs_in( $in, 'mode' ), array( 'off', 'admins', 'on' ), true ) ? grs_in( $in, 'mode' ) : 'off',
					'shop_name'       => sanitize_text_field( grs_in( $in, 'shop_name' ) ),
					'currency'        => isset( $cur[ grs_in( $in, 'currency' ) ] ) ? grs_in( $in, 'currency' ) : 'GBP',
					'prefix'          => preg_replace( '/[^A-Za-z0-9\-_#]/', '', grs_in( $in, 'prefix' ) ),
					'holiday'         => empty( $in['holiday'] ) ? 0 : 1,
					'holiday_message' => sanitize_text_field( grs_in( $in, 'holiday_message' ) ),
				)
			);
			break;

		case 'products':
			$products = array();
			foreach ( (array) ( $in['products'] ?? array() ) as $type => $p ) {
				$type = sanitize_key( $type );
				if ( ! post_type_exists( $type ) ) {
					continue;
				}
				$options = array();
				foreach ( (array) ( $p['options'] ?? array() ) as $group ) {
					$name   = sanitize_text_field( grs_in( $group, 'name' ) );
					$values = grs_parse_values( grs_in( $group, 'values' ) );
					if ( '' !== $name && $values ) {
						$options[] = array( 'name' => $name, 'values' => $values );
					}
				}
				$products[ $type ] = array(
					'enabled'   => empty( $p['enabled'] ) ? 0 : 1,
					'label'     => sanitize_text_field( grs_in( $p, 'label' ) ),
					'price'     => grs_parse_money( grs_in( $p, 'price' ) ),
					'tiers'     => grs_clean_pairs( $p['tiers'] ?? array() ),
					'mix'       => empty( $p['mix'] ) ? 0 : 1,
					'options'   => $options,
					'stock'     => 'count' === grs_in( $p, 'stock' ) ? 'count' : 'unlimited',
					'button'    => sanitize_text_field( grs_in( $p, 'button' ) ) ?: __( 'Add to basket', 'grow-shop' ),
					'placement' => 'theme' === grs_in( $p, 'placement' ) ? 'theme' : 'auto',
				);
			}
			grs_save_group( 'products', $products );
			break;

		case 'shipping':
			$zones = array();
			foreach ( (array) ( $in['zones'] ?? array() ) as $z ) {
				$name = sanitize_text_field( grs_in( $z, 'name' ) );
				if ( '' === $name || ! empty( $z['delete'] ) ) {
					continue;
				}
				$methods = array();
				foreach ( (array) ( $z['methods'] ?? array() ) as $m ) {
					$mname = sanitize_text_field( grs_in( $m, 'name' ) );
					if ( '' === $mname || ! empty( $m['delete'] ) ) {
						continue;
					}
					$methods[] = array(
						'name'      => $mname,
						'type'      => in_array( grs_in( $m, 'type' ), array( 'flat', 'per_item', 'bands' ), true ) ? grs_in( $m, 'type' ) : 'flat',
						'amount'    => grs_parse_money( grs_in( $m, 'amount' ) ),
						'bands'     => grs_parse_bands( grs_in( $m, 'bands' ) ),
						'free_over' => grs_parse_money( grs_in( $m, 'free_over' ) ),
					);
				}
				$region  = array_key_exists( grs_in( $z, 'region' ), grs_regions() ) ? grs_in( $z, 'region' ) : 'uk';
				$zones[] = array(
					'name'    => $name,
					'region'  => $region,
					'custom'  => implode( ', ', grs_region_countries( 'custom', grs_in( $z, 'custom' ) ) ),
					'methods' => $methods,
				);
			}
			grs_save_group( 'shipping', array( 'zones' => $zones ) );
			break;

		case 'payments':
			// Start from what's stored, not grs_get(), so keys set in
			// wp-config.php are never copied into the database.
			$raw       = get_option( GRS_OPTION, array() );
			$p         = array_merge( grs_defaults()['payments'], (array) ( $raw['payments'] ?? array() ) );
			$p['mode'] = 'live' === grs_in( $in, 'mode' ) ? 'live' : 'test';
			foreach ( array( 'test', 'live' ) as $mode ) {
				$key = preg_replace( '/[^A-Za-z0-9_]/', '', grs_in( $in, 'key_' . $mode ) );
				if ( '' !== $key ) {
					$p[ 'key_' . $mode ] = $key;
				}
				if ( ! empty( $in[ 'forget_' . $mode ] ) ) {
					$p[ 'key_' . $mode ] = '';
					$p[ 'wh_' . $mode ]  = array();
				}
			}
			grs_save_group( 'payments', $p );
			break;

		case 'emails':
			$templates = array();
			foreach ( array_keys( grs_email_types() ) as $type ) {
				$t                  = (array) ( $in['templates'][ $type ] ?? array() );
				$templates[ $type ] = array(
					'enabled' => empty( $t['enabled'] ) ? 0 : 1,
					'subject' => sanitize_text_field( grs_in( $t, 'subject' ) ),
					'body'    => sanitize_textarea_field( grs_in( $t, 'body' ) ),
				);
			}
			grs_save_group(
				'emails',
				array(
					'notify_to'  => implode( ', ', grs_parse_addresses( grs_in( $in, 'notify_to' ) ) ),
					'from_name'  => sanitize_text_field( grs_in( $in, 'from_name' ) ),
					'from_email' => sanitize_email( grs_in( $in, 'from_email' ) ),
					'reply_to'   => sanitize_email( grs_in( $in, 'reply_to' ) ),
					'logo'       => absint( grs_in( $in, 'logo' ) ),
					'accent'     => sanitize_hex_color( grs_in( $in, 'accent' ) ) ?: '#3c4a3e',
					'templates'  => $templates,
				)
			);
			break;

		case 'checkout':
			grs_save_group(
				'checkout',
				array(
					'basket_style'  => 'page' === grs_in( $in, 'basket_style' ) ? 'page' : 'panel',
					'basket_page'   => absint( grs_in( $in, 'basket_page' ) ),
					'max_qty'       => max( 1, min( 999, (int) grs_in( $in, 'max_qty' ) ) ),
					'min_order'     => grs_parse_money( grs_in( $in, 'min_order' ) ),
					'collect_phone' => empty( $in['collect_phone'] ) ? 0 : 1,
					'thanks'        => sanitize_text_field( grs_in( $in, 'thanks' ) ),
					'cancelled'     => sanitize_text_field( grs_in( $in, 'cancelled' ) ),
					'terms_page'    => absint( grs_in( $in, 'terms_page' ) ),
					'returns_page'  => absint( grs_in( $in, 'returns_page' ) ),
					'privacy_page'  => absint( grs_in( $in, 'privacy_page' ) ),
				)
			);
			break;

		case 'appearance':
			grs_save_group(
				'appearance',
				array(
					'basket_label'   => sanitize_text_field( grs_in( $in, 'basket_label' ) ) ?: __( 'Basket', 'grow-shop' ),
					'checkout_label' => sanitize_text_field( grs_in( $in, 'checkout_label' ) ) ?: __( 'Checkout', 'grow-shop' ),
					'icon'           => 'theme' === grs_in( $in, 'icon' ) ? 'theme' : 'auto',
					'accent'         => (string) sanitize_hex_color( grs_in( $in, 'accent' ) ),
					'stylesheet'     => empty( $in['stylesheet'] ) ? 0 : 1,
				)
			);
			break;

		default:
			wp_die( esc_html__( 'Unknown tab.', 'grow-shop' ) );
	}
	do_action( 'grow_shop_settings_saved', $tab );
	wp_safe_redirect( grs_settings_url( $tab, array( 'saved' => 1 ) ) );
	exit;
}
add_action( 'admin_post_grs_save_settings', 'grs_save_settings' );

/**
 * Payments: test the connection.
 */
function grs_test_connection() {
	grs_admin_check( 'grs_stripe_tools' );
	$mode = isset( $_POST['mode'] ) && 'live' === $_POST['mode'] ? 'live' : 'test';
	set_transient( 'grs_test_result_' . get_current_user_id(), array( $mode, grs_stripe_test( $mode ) ), 300 );
	wp_safe_redirect( grs_settings_url( 'payments', array( 'tested' => $mode ) ) );
	exit;
}
add_action( 'admin_post_grs_test_connection', 'grs_test_connection' );

/**
 * Payments: create the webhook in Stripe.
 */
function grs_connect_webhook() {
	grs_admin_check( 'grs_stripe_tools' );
	$mode = isset( $_POST['mode'] ) && 'live' === $_POST['mode'] ? 'live' : 'test';
	$res  = grs_stripe_connect_webhook( $mode );
	set_transient( 'grs_notice_' . get_current_user_id(), is_wp_error( $res ) ? array( 'error', __( 'Stripe said:', 'grow-shop' ) . ' ' . $res->get_error_message() ) : array( 'success', __( 'Webhook connected. Stripe will now tell the site about every payment.', 'grow-shop' ) ), 300 );
	wp_safe_redirect( grs_settings_url( 'payments' ) );
	exit;
}
add_action( 'admin_post_grs_connect_webhook', 'grs_connect_webhook' );

/**
 * Emails: send a test of one email to the current user, using the latest
 * order, or made-up details if there are none yet.
 */
function grs_test_email() {
	grs_admin_check( 'grs_test_email' );
	$type  = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'confirmation';
	$to    = wp_get_current_user()->user_email;
	$last  = get_posts( array( 'post_type' => GRS_ORDER, 'post_status' => array_keys( grs_statuses() ), 'numberposts' => 1, 'fields' => 'ids' ) );
	$temp  = 0;
	$order = $last ? $last[0] : 0;
	if ( ! $order ) {
		$temp  = wp_insert_post( array( 'post_type' => GRS_ORDER, 'post_status' => 'grs-pending', 'post_title' => 'Email test' ) );
		$order = $temp;
		$types = grs_sellable_types();
		$item  = $types ? get_posts( array( 'post_type' => $types[0], 'numberposts' => 1 ) ) : array();
		$name  = $item ? get_the_title( $item[0] ) : __( 'Example item', 'grow-shop' );
		grs_save_order_data(
			$order,
			array_merge(
				grs_order_data( $order ),
				array(
					'lines'       => array( array( 'id' => 0, 'type' => '', 'name' => $name, 'opts' => array(), 'options' => '', 'qty' => 2, 'unit' => 250, 'total' => 500 ) ),
					'subtotal'    => 500,
					'shipping'    => 500,
					'total'       => 1000,
					'method_name' => __( 'Royal Mail', 'grow-shop' ),
					'customer'    => array( 'name' => 'Sam Example', 'email' => $to, 'phone' => '' ),
					'address'     => array( 'name' => 'Sam Example', 'line1' => '1 Example Street', 'city' => 'Keswick', 'postal_code' => 'CA12 5AA', 'country' => 'GB' ),
					'tracking'    => 'AB123456789GB',
					'last_refund' => 1000,
				)
			)
		);
		update_post_meta( $order, '_grs_number', grs_get( 'general', 'prefix' ) . '1000' );
	}
	$ok = grs_send_email( $type, $order, $to );
	if ( $temp ) {
		wp_delete_post( $temp, true );
	}
	/* translators: %s: email address */
	set_transient( 'grs_notice_' . get_current_user_id(), $ok ? array( 'success', sprintf( __( 'Test sent to %s.', 'grow-shop' ), $to ) ) : array( 'error', __( 'The test could not be sent. Check the site can send email.', 'grow-shop' ) ), 300 );
	wp_safe_redirect( grs_settings_url( 'emails' ) );
	exit;
}
add_action( 'admin_post_grs_test_email', 'grs_test_email' );

/**
 * Field helpers.
 *
 * @param string $name  Field name inside grs[].
 * @param mixed  $value Value.
 * @param string $class Class.
 * @param string $type  Input type.
 * @param string $extra Extra attributes (already escaped).
 */
function grs_input( $name, $value, $class = 'regular-text', $type = 'text', $extra = '' ) {
	printf( '<input type="%s" name="grs%s" value="%s" class="%s" %s>', esc_attr( $type ), esc_attr( $name ), esc_attr( (string) $value ), esc_attr( $class ), $extra ); // phpcs:ignore WordPress.Security.EscapeOutput
}

/**
 * A select.
 *
 * @param string $name    Field name.
 * @param array  $options value => label.
 * @param mixed  $current Current.
 */
function grs_select( $name, $options, $current ) {
	printf( '<select name="grs%s">', esc_attr( $name ) );
	foreach ( $options as $v => $label ) {
		printf( '<option value="%s" %s>%s</option>', esc_attr( $v ), selected( (string) $current, (string) $v, false ), esc_html( $label ) );
	}
	echo '</select>';
}

/**
 * A checkbox with label.
 *
 * @param string $name  Field name.
 * @param mixed  $value Current.
 * @param string $label Label.
 */
function grs_checkbox( $name, $value, $label ) {
	printf( '<label><input type="checkbox" name="grs%s" value="1" %s> %s</label>', esc_attr( $name ), checked( (bool) $value, true, false ), esc_html( $label ) );
}

/**
 * A page dropdown.
 *
 * @param string $name    Field name.
 * @param int    $current Page ID.
 */
function grs_page_select( $name, $current ) {
	wp_dropdown_pages(
		array(
			'name'              => 'grs' . $name, // phpcs:ignore
			'selected'          => (int) $current,
			'show_option_none'  => __( '(none)', 'grow-shop' ),
			'option_none_value' => 0,
			'post_status'       => array( 'publish', 'draft', 'private' ),
		)
	);
}

/**
 * A description line.
 *
 * @param string $text Text.
 */
function grs_desc( $text ) {
	echo '<p class="description">' . esc_html( $text ) . '</p>';
}

/**
 * The settings page.
 */
function grs_render_settings() {
	$tabs = grs_tabs();
	$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$tab  = isset( $tabs[ $tab ] ) ? $tab : 'general';
	$note = get_transient( 'grs_notice_' . get_current_user_id() );
	delete_transient( 'grs_notice_' . get_current_user_id() );
	?>
	<div class="wrap grs-settings">
		<h1><?php esc_html_e( 'Shop settings', 'grow-shop' ); ?></h1>
		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Saved. If prices or buttons changed, clear the site cache so visitors see them.', 'grow-shop' ); ?></p></div>
		<?php endif; ?>
		<?php if ( $note ) : ?>
			<div class="notice notice-<?php echo esc_attr( $note[0] ); ?> is-dismissible"><p><?php echo esc_html( $note[1] ); ?></p></div>
		<?php endif; ?>
		<?php grs_setup_checklist(); ?>
		<nav class="nav-tab-wrapper">
			<?php foreach ( $tabs as $slug => $label ) : ?>
				<a class="nav-tab <?php echo $slug === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( grs_settings_url( $slug ) ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="grs-form">
			<input type="hidden" name="action" value="grs_save_settings">
			<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
			<?php wp_nonce_field( 'grs_settings' ); ?>
			<?php call_user_func( 'grs_tab_' . $tab ); ?>
			<?php submit_button(); ?>
		</form>
		<?php
		if ( 'payments' === $tab ) {
			grs_payments_tools();
		}
		if ( 'emails' === $tab ) {
			grs_email_tools();
		}
		?>
	</div>
	<?php
}

/**
 * What's left before the shop can open.
 */
function grs_setup_checklist() {
	$todo = array();
	if ( ! grs_sellable_types() ) {
		$todo[] = array( 'products', __( 'Choose what you sell (Products).', 'grow-shop' ) );
	}
	if ( ! grs_get( 'shipping', 'zones' ) ) {
		$todo[] = array( 'shipping', __( 'Add at least one postage zone (Shipping).', 'grow-shop' ) );
	}
	if ( '' === grs_stripe_key() ) {
		$todo[] = array( 'payments', __( 'Add a Stripe key for the current mode (Payments).', 'grow-shop' ) );
	} elseif ( empty( grs_get( 'payments', 'wh_' . grs_mode() )['secret'] ) ) {
		$todo[] = array( 'payments', __( 'Connect the webhook (Payments).', 'grow-shop' ) );
	}
	if ( ! grs_legal_links() ) {
		$todo[] = array( 'checkout', __( 'Choose your Terms, Returns and Privacy pages (Checkout).', 'grow-shop' ) );
	}
	$mode = grs_get( 'general', 'mode' );
	echo '<div class="grs-status">';
	$labels = array( 'off' => __( 'Shop is off', 'grow-shop' ), 'admins' => __( 'Shop is visible to admins only', 'grow-shop' ), 'on' => __( 'Shop is open', 'grow-shop' ) );
	echo '<span class="grs-pill grs-pill--' . esc_attr( $mode ) . '">' . esc_html( $labels[ $mode ] ?? $mode ) . '</span> ';
	echo '<span class="grs-pill grs-pill--' . esc_attr( grs_mode() ) . '">' . esc_html( 'live' === grs_mode() ? __( 'Live payments', 'grow-shop' ) : __( 'Test payments', 'grow-shop' ) ) . '</span>';
	if ( grs_on_holiday() ) {
		echo ' <span class="grs-pill grs-pill--holiday">' . esc_html__( 'Holiday mode', 'grow-shop' ) . '</span>';
	}
	if ( $todo ) {
		echo '<ul class="grs-todo">';
		foreach ( $todo as $row ) {
			echo '<li><a href="' . esc_url( grs_settings_url( $row[0] ) ) . '">' . esc_html( $row[1] ) . '</a></li>';
		}
		echo '</ul>';
	}
	echo '</div>';
}

/**
 * General tab.
 */
function grs_tab_general() {
	$g = (array) grs_get( 'general' );
	?>
	<table class="form-table" role="presentation">
		<tr><th><?php esc_html_e( 'Shop', 'grow-shop' ); ?></th><td>
			<?php
			grs_select(
				'[mode]',
				array(
					'off'    => __( 'Off: nothing shows on the site', 'grow-shop' ),
					'admins' => __( 'Admins only: preview while logged in', 'grow-shop' ),
					'on'     => __( 'On: open to everyone', 'grow-shop' ),
				),
				$g['mode']
			);
			grs_desc( __( 'Clear the site cache after switching, so every page shows the change.', 'grow-shop' ) );
			?>
		</td></tr>
		<tr><th><?php esc_html_e( 'Holiday mode', 'grow-shop' ); ?></th><td>
			<?php grs_checkbox( '[holiday]', $g['holiday'], __( 'Pause new orders (prices and the basket still show)', 'grow-shop' ) ); ?>
			<p><?php grs_input( '[holiday_message]', $g['holiday_message'], 'large-text' ); ?></p>
			<?php grs_desc( __( 'The message shown while orders are paused.', 'grow-shop' ) ); ?>
		</td></tr>
		<tr><th><?php esc_html_e( 'Shop name', 'grow-shop' ); ?></th><td><?php grs_input( '[shop_name]', $g['shop_name'] ); grs_desc( __( 'Used in emails. Blank uses the site title.', 'grow-shop' ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Currency', 'grow-shop' ); ?></th><td>
			<?php
			$cur = array();
			foreach ( grs_currencies() as $code => $c ) {
				$cur[ $code ] = $c[1];
			}
			grs_select( '[currency]', $cur, $g['currency'] );
			?>
		</td></tr>
		<tr><th><?php esc_html_e( 'Order number prefix', 'grow-shop' ); ?></th><td><?php grs_input( '[prefix]', $g['prefix'], 'small-text' ); ?> <?php echo esc_html( sprintf( /* translators: %s: example */ __( 'Next order: %s', 'grow-shop' ), $g['prefix'] . (int) get_option( 'grow_shop_next_number', 1001 ) ) ); ?></td></tr>
	</table>
	<?php
}

/**
 * Products tab: one box per post type.
 */
function grs_tab_products() {
	$saved = (array) grs_get( 'products' );
	$types = get_post_types( array( 'show_ui' => true ), 'objects' );
	unset( $types['attachment'], $types[ GRS_ORDER ], $types['wp_block'], $types['wp_navigation'], $types['wp_template'], $types['wp_template_part'] );
	echo '<p>' . esc_html__( 'Tick what you sell. Each type has a default price, so you only edit an item when it\'s different (its Shop box, on its edit screen).', 'grow-shop' ) . '</p>';
	foreach ( $types as $type => $obj ) {
		if ( in_array( $type, array( 'ap_enquiry', 'grow_enquiry' ), true ) ) {
			continue;
		}
		$p    = array_merge( grs_type_defaults(), (array) ( $saved[ $type ] ?? array() ) );
		$base = '[products][' . $type . ']';
		?>
		<details class="grs-box" <?php echo $p['enabled'] ? 'open' : ''; ?>>
			<summary><strong><?php echo esc_html( $obj->labels->name ); ?></strong> <?php echo $p['enabled'] ? '<span class="grs-pill grs-pill--on">' . esc_html__( 'Sold', 'grow-shop' ) . '</span>' : ''; ?></summary>
			<table class="form-table" role="presentation">
				<tr><th><?php esc_html_e( 'Sell these', 'grow-shop' ); ?></th><td><?php grs_checkbox( $base . '[enabled]', $p['enabled'], sprintf( /* translators: %s: type */ __( '%s can be bought', 'grow-shop' ), $obj->labels->name ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Price', 'grow-shop' ); ?></th><td><?php grs_input( $base . '[price]', grs_money_field( $p['price'] ), 'small-text', 'text', 'inputmode="decimal"' ); ?> <?php grs_desc( __( 'The default for each one. An item can have its own price instead.', 'grow-shop' ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Quantity pricing', 'grow-shop' ); ?></th><td>
					<table class="grs-rows"><thead><tr><th><?php esc_html_e( 'From quantity', 'grow-shop' ); ?></th><th><?php esc_html_e( 'Price each', 'grow-shop' ); ?></th></tr></thead><tbody>
					<?php
					$rows   = (array) $p['tiers'];
					$rows[] = array( '', '' );
					$rows[] = array( '', '' );
					foreach ( $rows as $i => $row ) {
						echo '<tr><td>';
						grs_input( $base . '[tiers][' . $i . '][0]', $row[0], 'small-text', 'number', 'min="2"' );
						echo '</td><td>';
						grs_input( $base . '[tiers][' . $i . '][1]', '' === $row[1] ? '' : grs_money_field( $row[1] ), 'small-text', 'text', 'inputmode="decimal"' );
						echo '</td></tr>';
					}
					?>
					</tbody></table>
					<?php grs_checkbox( $base . '[mix]', $p['mix'], __( 'Mix and match: count every item of this type together', 'grow-shop' ) ); ?>
					<?php grs_desc( __( 'e.g. from 20, 2.00 each. Leave a row blank to remove it. Items with their own price keep it.', 'grow-shop' ) ); ?>
				</td></tr>
				<tr><th><?php esc_html_e( 'Options', 'grow-shop' ); ?></th><td>
					<?php
					$groups   = (array) $p['options'];
					$groups[] = array( 'name' => '', 'values' => array() );
					foreach ( $groups as $g => $group ) {
						$lines = array();
						foreach ( (array) $group['values'] as $v ) {
							$lines[] = $v['label'] . ( $v['diff'] ? ' | ' . ( $v['diff'] > 0 ? '+' : '' ) . grs_money_field( $v['diff'] ) : '' );
						}
						echo '<div class="grs-option">';
						grs_input( $base . '[options][' . $g . '][name]', $group['name'], 'regular-text', 'text', 'placeholder="' . esc_attr__( 'Option name, e.g. Size', 'grow-shop' ) . '"' );
						printf( '<textarea name="grs%s" rows="3" class="large-text code" placeholder="%s">%s</textarea>', esc_attr( $base . '[options][' . $g . '][values]' ), esc_attr__( "One choice per line. Add a price difference after a bar:\n6 inch square\n5 x 7 | +0.50", 'grow-shop' ), esc_textarea( implode( "\n", $lines ) ) );
						echo '</div>';
					}
					grs_desc( __( 'Clear the name to remove an option. Add new choices at the end of the list, so items keep the right ones switched off.', 'grow-shop' ) );
					?>
				</td></tr>
				<tr><th><?php esc_html_e( 'Stock', 'grow-shop' ); ?></th><td><?php grs_select( $base . '[stock]', array( 'unlimited' => __( 'Unlimited', 'grow-shop' ), 'count' => __( 'Count stock for each item', 'grow-shop' ) ), $p['stock'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Button', 'grow-shop' ); ?></th><td>
					<?php grs_input( $base . '[button]', $p['button'] ); ?>
					<p><?php grs_select( $base . '[placement]', array( 'auto' => __( 'Show it after the content automatically', 'grow-shop' ), 'theme' => __( 'The theme places it', 'grow-shop' ) ), $p['placement'] ); ?></p>
				</td></tr>
				<tr><th><?php esc_html_e( 'Name in the basket', 'grow-shop' ); ?></th><td><?php grs_input( $base . '[label]', $p['label'], 'regular-text', 'text', 'placeholder="' . esc_attr( $obj->labels->singular_name ) . '"' ); ?></td></tr>
			</table>
		</details>
		<?php
	}
}

/**
 * Shipping tab.
 */
function grs_tab_shipping() {
	$zones   = (array) grs_get( 'shipping', 'zones' );
	$zones[] = array( 'name' => '', 'region' => 'europe', 'custom' => '', 'methods' => array(), 'new' => true );
	echo '<p>' . esc_html__( 'Customers choose where they\'re sending to; Stripe then only accepts addresses in that zone. Each zone has one or more postage methods.', 'grow-shop' ) . '</p>';
	foreach ( $zones as $z => $zone ) {
		$base      = '[zones][' . $z . ']';
		$methods   = (array) $zone['methods'];
		$methods[] = array( 'name' => '', 'type' => 'flat', 'amount' => 0, 'bands' => array(), 'free_over' => 0, 'new' => true );
		?>
		<details class="grs-box" <?php echo empty( $zone['new'] ) ? 'open' : ''; ?>>
			<summary><strong><?php echo empty( $zone['new'] ) ? esc_html( $zone['name'] ) : esc_html__( '+ Add a zone', 'grow-shop' ); ?></strong></summary>
			<table class="form-table" role="presentation">
				<tr><th><?php esc_html_e( 'Zone name', 'grow-shop' ); ?></th><td><?php grs_input( $base . '[name]', $zone['name'], 'regular-text', 'text', 'placeholder="' . esc_attr__( 'e.g. Europe', 'grow-shop' ) . '"' ); ?> <?php if ( empty( $zone['new'] ) ) { grs_checkbox( $base . '[delete]', 0, __( 'Delete this zone', 'grow-shop' ) ); } ?></td></tr>
				<tr><th><?php esc_html_e( 'Countries', 'grow-shop' ); ?></th><td>
					<?php grs_select( $base . '[region]', grs_regions(), $zone['region'] ); ?>
					<p><?php grs_input( $base . '[custom]', $zone['custom'], 'large-text', 'text', 'placeholder="' . esc_attr__( 'For your own list: country codes, e.g. US, CA', 'grow-shop' ) . '"' ); ?></p>
					<?php
					if ( empty( $zone['new'] ) ) {
						$codes = grs_zone_countries( $z );
						/* translators: %d: number of countries */
						grs_desc( sprintf( _n( '%d country', '%d countries', count( $codes ), 'grow-shop' ), count( $codes ) ) . ( count( $codes ) <= 12 ? ': ' . implode( ', ', $codes ) : '' ) );
					}
					?>
				</td></tr>
				<tr><th><?php esc_html_e( 'Postage methods', 'grow-shop' ); ?></th><td>
					<?php foreach ( $methods as $m => $method ) : $mb = $base . '[methods][' . $m . ']'; ?>
						<fieldset class="grs-method">
							<p>
								<?php grs_input( $mb . '[name]', $method['name'], 'regular-text', 'text', 'placeholder="' . esc_attr( empty( $method['new'] ) ? '' : __( 'Add a method, e.g. Royal Mail Tracked', 'grow-shop' ) ) . '"' ); ?>
								<?php grs_select( $mb . '[type]', array( 'flat' => __( 'Flat rate per order', 'grow-shop' ), 'per_item' => __( 'Per item', 'grow-shop' ), 'bands' => __( 'By quantity', 'grow-shop' ) ), $method['type'] ); ?>
								<?php grs_input( $mb . '[amount]', grs_money_field( $method['amount'] ), 'small-text', 'text', 'inputmode="decimal"' ); ?>
								<?php if ( empty( $method['new'] ) ) { grs_checkbox( $mb . '[delete]', 0, __( 'Delete', 'grow-shop' ) ); } ?>
							</p>
							<p class="grs-method__more">
								<label><?php esc_html_e( 'Free when items come to', 'grow-shop' ); ?> <?php grs_input( $mb . '[free_over]', grs_money_field( $method['free_over'], true ), 'small-text', 'text', 'inputmode="decimal" placeholder="' . esc_attr__( 'never', 'grow-shop' ) . '"' ); ?></label>
							</p>
							<?php
							$bands = array();
							foreach ( (array) $method['bands'] as $b ) {
								$bands[] = $b[0] . ' | ' . grs_money_field( $b[1] );
							}
							?>
							<p class="grs-method__bands"><label><?php esc_html_e( 'By quantity: "from | price" per line (the amount above is for fewer)', 'grow-shop' ); ?><br>
							<textarea name="grs<?php echo esc_attr( $mb . '[bands]' ); ?>" rows="2" class="regular-text code" placeholder="<?php echo esc_attr( "10 | 7.50\n30 | 10.00" ); ?>"><?php echo esc_textarea( implode( "\n", $bands ) ); ?></textarea></label></p>
						</fieldset>
					<?php endforeach; ?>
				</td></tr>
			</table>
		</details>
		<?php
	}
}

/**
 * Payments tab.
 */
function grs_tab_payments() {
	$p = (array) grs_get( 'payments' );
	?>
	<table class="form-table" role="presentation">
		<tr><th><?php esc_html_e( 'Mode', 'grow-shop' ); ?></th><td>
			<label><input type="radio" name="grs[mode]" value="test" <?php checked( 'test', grs_mode() ); ?>> <?php esc_html_e( 'Test: no real money, use Stripe\'s test card 4242 4242 4242 4242', 'grow-shop' ); ?></label><br>
			<label><input type="radio" name="grs[mode]" value="live" <?php checked( 'live', grs_mode() ); ?>> <?php esc_html_e( 'Live: real payments', 'grow-shop' ); ?></label>
		</td></tr>
		<?php foreach ( array( 'test' => __( 'Test key', 'grow-shop' ), 'live' => __( 'Live key', 'grow-shop' ) ) as $mode => $label ) : ?>
			<?php $key = (string) $p[ 'key_' . $mode ]; ?>
			<tr><th><?php echo esc_html( $label ); ?></th><td>
				<?php if ( defined( 'GROW_SHOP_STRIPE_' . strtoupper( $mode ) . '_KEY' ) ) : ?>
					<p><?php esc_html_e( 'Set in wp-config.php.', 'grow-shop' ); ?></p>
				<?php else : ?>
					<input type="password" name="grs[key_<?php echo esc_attr( $mode ); ?>]" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo esc_attr( $key ? substr( $key, 0, 8 ) . '…' . substr( $key, -4 ) . ' ' . __( '(saved; leave blank to keep)', 'grow-shop' ) : ( 'test' === $mode ? 'rk_test_…' : 'rk_live_…' ) ); ?>">
					<?php if ( $key ) { echo ' '; grs_checkbox( '[forget_' . $mode . ']', 0, __( 'Remove', 'grow-shop' ) ); } ?>
				<?php endif; ?>
			</td></tr>
		<?php endforeach; ?>
	</table>
	<div class="grs-help">
		<p><strong><?php esc_html_e( 'Making a key in Stripe', 'grow-shop' ); ?></strong></p>
		<ol>
			<li><?php esc_html_e( 'In Stripe, go to Developers, API keys, and choose Create restricted key. Do it once in test mode and once in live mode.', 'grow-shop' ); ?></li>
			<li><?php esc_html_e( 'Name it after this site. Set these to Write and leave everything else as None: Checkout Sessions, Refunds, Webhook Endpoints.', 'grow-shop' ); ?></li>
			<li><?php esc_html_e( 'Paste it here, save, then use Connect webhook and Test connection below.', 'grow-shop' ); ?></li>
		</ol>
		<p class="description"><?php esc_html_e( 'A restricted key can take payments and refund them, and nothing else: it can\'t move money out or see bank details.', 'grow-shop' ); ?></p>
	</div>
	<?php
}

/**
 * Payments tools (outside the settings form).
 */
function grs_payments_tools() {
	$result = get_transient( 'grs_test_result_' . get_current_user_id() );
	delete_transient( 'grs_test_result_' . get_current_user_id() );
	echo '<h2>' . esc_html__( 'Connection', 'grow-shop' ) . '</h2>';
	echo '<p>' . esc_html__( 'Webhook address:', 'grow-shop' ) . ' <code>' . esc_html( grs_webhook_url() ) . '</code></p>';
	echo '<div class="grs-tools">';
	foreach ( array( 'test', 'live' ) as $mode ) {
		if ( '' === grs_stripe_key( $mode ) ) {
			continue;
		}
		$wh = (array) grs_get( 'payments', 'wh_' . $mode );
		echo '<div class="grs-tool"><h3>' . esc_html( 'live' === $mode ? __( 'Live', 'grow-shop' ) : __( 'Test', 'grow-shop' ) ) . '</h3>';
		echo '<p>' . ( empty( $wh['secret'] ) ? esc_html__( 'Webhook not connected.', 'grow-shop' ) : esc_html__( 'Webhook connected.', 'grow-shop' ) ) . '</p>';
		foreach ( array( 'grs_test_connection' => __( 'Test connection', 'grow-shop' ), 'grs_connect_webhook' => empty( $wh['secret'] ) ? __( 'Connect webhook', 'grow-shop' ) : __( 'Reconnect webhook', 'grow-shop' ) ) as $action => $label ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:8px">';
			wp_nonce_field( 'grs_stripe_tools' );
			echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '"><input type="hidden" name="mode" value="' . esc_attr( $mode ) . '">';
			submit_button( $label, 'secondary', 'submit', false );
			echo '</form>';
		}
		if ( $result && $result[0] === $mode ) {
			echo '<table class="widefat striped grs-test"><tbody>';
			foreach ( $result[1] as $row ) {
				echo '<tr><td>' . ( $row[1] ? '<span class="grs-ok">&#10003;</span>' : '<span class="grs-bad">&#10007;</span>' ) . ' ' . esc_html( $row[0] ) . '</td><td>' . esc_html( $row[2] ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
	}
	echo '</div>';
	$log = array_reverse( (array) get_option( 'grow_shop_log', array() ) );
	if ( $log ) {
		echo '<h2>' . esc_html__( 'Recent events', 'grow-shop' ) . '</h2><ul class="grs-log">';
		foreach ( array_slice( $log, 0, 15 ) as $row ) {
			echo '<li><span>' . esc_html( wp_date( 'j M Y H:i', $row[0] ) ) . '</span> ' . esc_html( $row[1] ) . '</li>';
		}
		echo '</ul>';
	}
}

/**
 * Emails tab.
 */
function grs_tab_emails() {
	$e    = (array) grs_get( 'emails' );
	$logo = (int) $e['logo'];
	?>
	<table class="form-table" role="presentation">
		<tr><th><?php esc_html_e( 'Send shop emails to', 'grow-shop' ); ?></th><td><?php grs_input( '[notify_to]', $e['notify_to'], 'large-text' ); grs_desc( __( 'New order notifications. Several addresses: separate with commas. Blank sends to the site admin email. Never shown on the site.', 'grow-shop' ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Customer replies go to', 'grow-shop' ); ?></th><td><?php grs_input( '[reply_to]', $e['reply_to'], 'regular-text', 'email' ); grs_desc( __( 'Blank uses the first address above.', 'grow-shop' ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'From', 'grow-shop' ); ?></th><td><?php grs_input( '[from_name]', $e['from_name'], 'regular-text', 'text', 'placeholder="' . esc_attr( grs_shop_name() ) . '"' ); ?> <?php grs_input( '[from_email]', $e['from_email'], 'regular-text', 'email', 'placeholder="' . esc_attr__( 'address', 'grow-shop' ) . '"' ); ?><?php grs_desc( __( 'Blank leaves it to the mail plugin. If set, it must be a sender the mail service has verified.', 'grow-shop' ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Logo', 'grow-shop' ); ?></th><td>
			<input type="hidden" name="grs[logo]" value="<?php echo esc_attr( $logo ); ?>" data-grs-logo>
			<span data-grs-logo-preview><?php echo $logo ? wp_get_attachment_image( $logo, 'thumbnail', false, array( 'style' => 'max-height:60px;width:auto' ) ) : ''; ?></span>
			<button type="button" class="button" data-grs-logo-pick><?php esc_html_e( 'Choose', 'grow-shop' ); ?></button>
			<button type="button" class="button-link" data-grs-logo-clear><?php esc_html_e( 'Remove', 'grow-shop' ); ?></button>
			<?php grs_desc( __( 'Without a logo the shop name is used.', 'grow-shop' ) ); ?>
		</td></tr>
		<tr><th><?php esc_html_e( 'Accent colour', 'grow-shop' ); ?></th><td><?php grs_input( '[accent]', $e['accent'], '', 'color' ); ?></td></tr>
	</table>
	<?php foreach ( grs_email_types() as $type => $info ) : $t = $e['templates'][ $type ]; $b = '[templates][' . $type . ']'; ?>
		<details class="grs-box">
			<summary><strong><?php echo esc_html( $info[0] ); ?></strong> <span class="description"><?php echo esc_html( $info[1] ); ?></span> <?php echo $t['enabled'] ? '' : '<span class="grs-pill">' . esc_html__( 'Off', 'grow-shop' ) . '</span>'; ?></summary>
			<p><?php grs_checkbox( $b . '[enabled]', $t['enabled'], __( 'Send this email', 'grow-shop' ) ); ?></p>
			<p><label><?php esc_html_e( 'Subject', 'grow-shop' ); ?><br><?php grs_input( $b . '[subject]', $t['subject'], 'large-text' ); ?></label></p>
			<p><label><?php esc_html_e( 'Message', 'grow-shop' ); ?><br><textarea name="grs<?php echo esc_attr( $b . '[body]' ); ?>" rows="10" class="large-text"><?php echo esc_textarea( $t['body'] ); ?></textarea></label></p>
		</details>
	<?php endforeach; ?>
	<h3><?php esc_html_e( 'Placeholders', 'grow-shop' ); ?></h3>
	<p class="grs-placeholders">
		<?php foreach ( grs_placeholders() as $ph => $desc ) : ?>
			<span><code><?php echo esc_html( $ph ); ?></code> <?php echo esc_html( $desc ); ?></span>
		<?php endforeach; ?>
	</p>
	<?php
}

/**
 * Email test sender (outside the settings form).
 */
function grs_email_tools() {
	?>
	<h2><?php esc_html_e( 'Send a test', 'grow-shop' ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'grs_test_email' ); ?>
		<input type="hidden" name="action" value="grs_test_email">
		<select name="type">
			<?php foreach ( grs_email_types() as $type => $info ) : ?>
				<option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $info[0] ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php submit_button( __( 'Send to me', 'grow-shop' ), 'secondary', 'submit', false ); ?>
		<p class="description"><?php esc_html_e( 'Uses the latest order, or made-up details if there isn\'t one. Save any changes first.', 'grow-shop' ); ?></p>
	</form>
	<?php
}

/**
 * Checkout tab.
 */
function grs_tab_checkout() {
	$c = (array) grs_get( 'checkout' );
	?>
	<table class="form-table" role="presentation">
		<tr><th><?php esc_html_e( 'Basket', 'grow-shop' ); ?></th><td>
			<?php grs_select( '[basket_style]', array( 'panel' => __( 'Slide-out panel', 'grow-shop' ), 'page' => __( 'A basket page', 'grow-shop' ) ), $c['basket_style'] ); ?>
			<p><?php grs_page_select( '[basket_page]', $c['basket_page'] ); ?></p>
			<?php grs_desc( __( 'For a basket page, choose a page that contains the [grow_shop_basket] shortcode.', 'grow-shop' ) ); ?>
		</td></tr>
		<tr><th><?php esc_html_e( 'Most of one item per order', 'grow-shop' ); ?></th><td><?php grs_input( '[max_qty]', $c['max_qty'], 'small-text', 'number', 'min="1" max="999"' ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Minimum order', 'grow-shop' ); ?></th><td><?php grs_input( '[min_order]', grs_money_field( $c['min_order'], true ), 'small-text', 'text', 'inputmode="decimal" placeholder="' . esc_attr__( 'none', 'grow-shop' ) . '"' ); ?> <?php grs_desc( __( 'Items only, before postage.', 'grow-shop' ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Phone number', 'grow-shop' ); ?></th><td><?php grs_checkbox( '[collect_phone]', $c['collect_phone'], __( 'Ask for a phone number at checkout', 'grow-shop' ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'After paying', 'grow-shop' ); ?></th><td><?php grs_input( '[thanks]', $c['thanks'], 'large-text' ); ?></td></tr>
		<tr><th><?php esc_html_e( 'If they cancel', 'grow-shop' ); ?></th><td><?php grs_input( '[cancelled]', $c['cancelled'], 'large-text' ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Terms of sale page', 'grow-shop' ); ?></th><td><?php grs_page_select( '[terms_page]', $c['terms_page'] ); ?> <?php grs_desc( __( 'Linked in the basket, on Stripe\'s page and in emails.', 'grow-shop' ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Returns page', 'grow-shop' ); ?></th><td><?php grs_page_select( '[returns_page]', $c['returns_page'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Privacy page', 'grow-shop' ); ?></th><td><?php grs_page_select( '[privacy_page]', $c['privacy_page'] ); ?></td></tr>
	</table>
	<?php
}

/**
 * Appearance tab.
 */
function grs_tab_appearance() {
	$a = (array) grs_get( 'appearance' );
	?>
	<table class="form-table" role="presentation">
		<tr><th><?php esc_html_e( 'Basket wording', 'grow-shop' ); ?></th><td><?php grs_input( '[basket_label]', $a['basket_label'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Checkout button', 'grow-shop' ); ?></th><td><?php grs_input( '[checkout_label]', $a['checkout_label'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Basket button', 'grow-shop' ); ?></th><td>
			<?php grs_select( '[icon]', array( 'auto' => __( 'Floating in the bottom corner', 'grow-shop' ), 'theme' => __( 'The theme places it (grow_shop_basket_icon)', 'grow-shop' ) ), $a['icon'] ); ?>
		</td></tr>
		<tr><th><?php esc_html_e( 'Button colour', 'grow-shop' ); ?></th><td><?php grs_input( '[accent]', $a['accent'], 'small-text', 'text', 'placeholder="#222222"' ); ?> <?php grs_desc( __( 'A hex colour, or blank to use the theme\'s.', 'grow-shop' ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Stylesheet', 'grow-shop' ); ?></th><td><?php grs_checkbox( '[stylesheet]', $a['stylesheet'], __( 'Use the shop\'s stylesheet (off for themes that style it themselves)', 'grow-shop' ) ); ?></td></tr>
	</table>
	<?php
}
