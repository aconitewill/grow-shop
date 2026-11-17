<?php
/**
 * The Shop box on each sellable item, Price and Stock columns, and bulk
 * edit of price and for-sale status.
 *
 * @package GrowShop
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add the Shop box to sellable types.
 */
function grs_product_box() {
	foreach ( grs_sellable_types() as $type ) {
		add_meta_box( 'grs-shop', __( 'Shop', 'grow-shop' ), 'grs_render_product_box', $type, 'side', 'high' );
	}
}
add_action( 'add_meta_boxes', 'grs_product_box' );

/**
 * The box.
 *
 * @param WP_Post $post Post.
 */
function grs_render_product_box( $post ) {
	$conf     = grs_type_conf( $post->post_type );
	$override = get_post_meta( $post->ID, '_grs_price', true );
	$off      = (array) get_post_meta( $post->ID, '_grs_options_off', true );
	wp_nonce_field( 'grs_product', 'grs_product_nonce' );
	?>
	<p><label><input type="checkbox" name="grs_not_for_sale" value="1" <?php checked( (bool) get_post_meta( $post->ID, '_grs_not_for_sale', true ) ); ?>> <?php esc_html_e( 'Not for sale', 'grow-shop' ); ?></label></p>
	<p>
		<label for="grs-price"><?php esc_html_e( 'Price', 'grow-shop' ); ?></label><br>
		<input type="text" inputmode="decimal" id="grs-price" name="grs_price" class="small-text" value="<?php echo esc_attr( '' === (string) $override ? '' : grs_money_field( (int) $override ) ); ?>" placeholder="<?php echo esc_attr( grs_money_field( (int) $conf['price'] ) ); ?>">
		<span class="description"><?php echo esc_html( sprintf( /* translators: %s: default price */ __( 'Blank uses the default, %s, with quantity pricing.', 'grow-shop' ), grs_money( (int) $conf['price'], false ) ) ); ?></span>
	</p>
	<?php if ( 'count' === $conf['stock'] ) : ?>
		<p>
			<label for="grs-stock"><?php esc_html_e( 'In stock', 'grow-shop' ); ?></label><br>
			<input type="number" min="0" id="grs-stock" name="grs_stock" class="small-text" value="<?php echo esc_attr( (int) get_post_meta( $post->ID, '_grs_stock', true ) ); ?>">
			<?php $held = grs_held( $post->ID ); ?>
			<?php if ( $held ) : ?>
				<span class="description"><?php echo esc_html( sprintf( /* translators: %d: number */ __( '%d in checkouts right now', 'grow-shop' ), $held ) ); ?></span>
			<?php endif; ?>
		</p>
	<?php endif; ?>
	<?php foreach ( (array) $conf['options'] as $g => $group ) : ?>
		<fieldset class="grs-box-opts">
			<legend><?php echo esc_html( $group['name'] ); ?></legend>
			<?php foreach ( (array) $group['values'] as $v => $value ) : ?>
				<label style="display:block"><input type="checkbox" name="grs_opts_on[]" value="<?php echo esc_attr( $g . ':' . $v ); ?>" <?php checked( ! in_array( $g . ':' . $v, $off, true ) ); ?>> <?php echo esc_html( $value['label'] ); ?></label>
			<?php endforeach; ?>
			<input type="hidden" name="grs_opts_all[]" value="<?php echo esc_attr( implode( ',', array_map( function ( $v ) use ( $g ) { return $g . ':' . $v; }, array_keys( (array) $group['values'] ) ) ) ); ?>">
		</fieldset>
	<?php endforeach; ?>
	<p class="description"><a href="<?php echo esc_url( grs_settings_url( 'products' ) ); ?>"><?php esc_html_e( 'Defaults for all of them', 'grow-shop' ); ?></a></p>
	<?php
}

/**
 * Save the box, and bulk edit.
 *
 * @param int $post_id Post.
 */
function grs_save_product( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE || wp_is_post_revision( $post_id ) || ! grs_type_conf( get_post_type( $post_id ) ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	// phpcs:disable WordPress.Security.NonceVerification -- checked below / by core for bulk edit.

	// Bulk edit (core has checked the bulk-posts nonce).
	if ( isset( $_REQUEST['bulk_edit'], $_REQUEST['grs_bulk'] ) ) {
		$bulk  = (array) wp_unslash( $_REQUEST['grs_bulk'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$price = trim( (string) ( $bulk['price'] ?? '' ) );
		if ( 'default' === strtolower( $price ) ) {
			delete_post_meta( $post_id, '_grs_price' );
		} elseif ( '' !== $price ) {
			update_post_meta( $post_id, '_grs_price', grs_parse_money( $price ) );
		}
		$sale = (string) ( $bulk['sale'] ?? '' );
		if ( 'yes' === $sale ) {
			delete_post_meta( $post_id, '_grs_not_for_sale' );
		} elseif ( 'no' === $sale ) {
			update_post_meta( $post_id, '_grs_not_for_sale', 1 );
		}
		return;
	}

	if ( ! isset( $_POST['grs_product_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['grs_product_nonce'] ) ), 'grs_product' ) ) {
		return;
	}
	if ( empty( $_POST['grs_not_for_sale'] ) ) {
		delete_post_meta( $post_id, '_grs_not_for_sale' );
	} else {
		update_post_meta( $post_id, '_grs_not_for_sale', 1 );
	}
	$price = isset( $_POST['grs_price'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['grs_price'] ) ) ) : '';
	if ( '' === $price ) {
		delete_post_meta( $post_id, '_grs_price' );
	} else {
		update_post_meta( $post_id, '_grs_price', grs_parse_money( $price ) );
	}
	if ( isset( $_POST['grs_stock'] ) ) {
		update_post_meta( $post_id, '_grs_stock', max( 0, (int) $_POST['grs_stock'] ) );
	}
	if ( isset( $_POST['grs_opts_all'] ) ) {
		$all = array();
		foreach ( (array) wp_unslash( $_POST['grs_opts_all'] ) as $list ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$all = array_merge( $all, array_filter( explode( ',', sanitize_text_field( $list ) ) ) );
		}
		$on  = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['grs_opts_on'] ?? array() ) );
		$off = array_values( array_diff( $all, $on ) );
		if ( $off ) {
			update_post_meta( $post_id, '_grs_options_off', $off );
		} else {
			delete_post_meta( $post_id, '_grs_options_off' );
		}
	}
	// phpcs:enable
}
add_action( 'save_post', 'grs_save_product' );

/**
 * Columns on sellable types.
 */
function grs_product_columns_init() {
	foreach ( grs_sellable_types() as $type ) {
		add_filter( "manage_{$type}_posts_columns", 'grs_product_columns' );
		add_action( "manage_{$type}_posts_custom_column", 'grs_product_column', 10, 2 );
	}
}
add_action( 'admin_init', 'grs_product_columns_init' );

/**
 * Add Price (and Stock).
 *
 * @param array $cols Columns.
 * @return array
 */
function grs_product_columns( $cols ) {
	$out = array();
	foreach ( $cols as $k => $v ) {
		if ( 'date' === $k ) {
			$out['grs_price'] = __( 'Price', 'grow-shop' );
			if ( grs_tracks_stock_type( get_current_screen()->post_type ?? '' ) ) {
				$out['grs_stock'] = __( 'Stock', 'grow-shop' );
			}
		}
		$out[ $k ] = $v;
	}
	if ( ! isset( $out['grs_price'] ) ) {
		$out['grs_price'] = __( 'Price', 'grow-shop' );
	}
	return $out;
}

/**
 * Does a type count stock?
 *
 * @param string $type Post type.
 * @return bool
 */
function grs_tracks_stock_type( $type ) {
	$conf = grs_type_conf( $type );
	return $conf && 'count' === $conf['stock'];
}

/**
 * Column values.
 *
 * @param string $col Column.
 * @param int    $id  Post.
 */
function grs_product_column( $col, $id ) {
	if ( 'grs_price' === $col ) {
		if ( get_post_meta( $id, '_grs_not_for_sale', true ) ) {
			echo '<span class="grs-muted">' . esc_html__( 'Not for sale', 'grow-shop' ) . '</span>';
		} else {
			echo esc_html( grs_money( grs_base_price( $id ), false ) );
			echo grs_has_override( $id ) ? ' <span class="grs-muted">' . esc_html__( '(own price)', 'grow-shop' ) . '</span>' : '';
		}
	} elseif ( 'grs_stock' === $col ) {
		echo (int) get_post_meta( $id, '_grs_stock', true );
	}
}

/**
 * Bulk edit fields.
 *
 * @param string $col  Column.
 * @param string $type Post type.
 */
function grs_bulk_box( $col, $type ) {
	if ( 'grs_price' !== $col || ! grs_type_conf( $type ) ) {
		return;
	}
	?>
	<fieldset class="inline-edit-col-right"><div class="inline-edit-col">
		<span class="title"><?php esc_html_e( 'Shop', 'grow-shop' ); ?></span>
		<label class="inline-edit-group"><span class="title"><?php esc_html_e( 'Price', 'grow-shop' ); ?></span>
			<input type="text" name="grs_bulk[price]" value="" placeholder="<?php esc_attr_e( 'No change', 'grow-shop' ); ?>" style="width:8em"></label>
		<p class="description" style="margin:0 0 6px"><?php esc_html_e( 'Type "default" to go back to the default price.', 'grow-shop' ); ?></p>
		<label class="inline-edit-group"><span class="title"><?php esc_html_e( 'For sale', 'grow-shop' ); ?></span>
			<select name="grs_bulk[sale]">
				<option value=""><?php esc_html_e( 'No change', 'grow-shop' ); ?></option>
				<option value="yes"><?php esc_html_e( 'For sale', 'grow-shop' ); ?></option>
				<option value="no"><?php esc_html_e( 'Not for sale', 'grow-shop' ); ?></option>
			</select></label>
	</div></fieldset>
	<?php
}
add_action( 'bulk_edit_custom_box', 'grs_bulk_box', 10, 2 );
