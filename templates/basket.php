<?php
/**
 * The basket: a slide-out panel (printed in the footer) or inline on a
 * page ([grow_shop_basket]). The shell is static so pages cache; shop.js
 * fills it in. Override by copying to your-theme/grow-shop/basket.php,
 * keeping the data-grs-* hooks.
 *
 * Available: $inline (bool).
 *
 * @package GrowShop
 */

defined( 'ABSPATH' ) || exit;
$links = grs_legal_links();
?>
<?php if ( ! $inline ) : ?>
<div class="grs-overlay" data-grs-close hidden></div>
<?php endif; ?>
<aside id="grs-basket" class="grs-basket <?php echo $inline ? 'grs-basket--inline' : 'grs-basket--panel'; ?>" <?php echo $inline ? '' : 'hidden role="dialog" aria-modal="true"'; ?> aria-labelledby="grs-basket-title" data-grs-basket>
	<header class="grs-basket__head">
		<h2 id="grs-basket-title" class="grs-basket__title"><?php echo esc_html( (string) grs_get( 'appearance', 'basket_label' ) ); ?></h2>
		<?php if ( ! $inline ) : ?>
			<button type="button" class="grs-basket__close" data-grs-close aria-label="<?php esc_attr_e( 'Close', 'grow-shop' ); ?>">&times;</button>
		<?php endif; ?>
	</header>

	<div class="grs-basket__message" data-grs-message hidden></div>

	<div class="grs-basket__body">
		<p class="grs-basket__empty" data-grs-empty><?php esc_html_e( 'Your basket is empty.', 'grow-shop' ); ?></p>
		<ul class="grs-lines" data-grs-lines></ul>
		<ul class="grs-notices" data-grs-notices></ul>
	</div>

	<footer class="grs-basket__foot" data-grs-foot hidden>
		<div class="grs-ship">
			<p class="grs-ship__field" data-grs-zone-wrap>
				<label for="grs-zone"><?php esc_html_e( 'Deliver to', 'grow-shop' ); ?></label>
				<select id="grs-zone" data-grs-zone></select>
			</p>
			<p class="grs-ship__field" data-grs-method-wrap>
				<label for="grs-method"><?php esc_html_e( 'Postage', 'grow-shop' ); ?></label>
				<select id="grs-method" data-grs-method></select>
			</p>
		</div>
		<dl class="grs-totals">
			<dt><?php esc_html_e( 'Items', 'grow-shop' ); ?></dt><dd data-grs-subtotal></dd>
			<dt><?php esc_html_e( 'Postage', 'grow-shop' ); ?></dt><dd data-grs-shipping></dd>
			<dt class="grs-totals__total"><?php esc_html_e( 'Total', 'grow-shop' ); ?></dt><dd class="grs-totals__total" data-grs-total></dd>
		</dl>
		<ul class="grs-errors" data-grs-errors></ul>
		<button type="button" class="grs-checkout" data-grs-checkout><?php echo esc_html( (string) grs_get( 'appearance', 'checkout_label' ) ); ?></button>
		<p class="grs-secure"><?php esc_html_e( 'You\'ll pay on Stripe\'s secure page. Card, Apple Pay and Google Pay.', 'grow-shop' ); ?></p>
		<?php if ( $links ) : ?>
			<p class="grs-legal">
				<?php
				$parts = array();
				foreach ( $links as $label => $url ) {
					$parts[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
				}
				echo implode( ' &middot; ', $parts ); // phpcs:ignore WordPress.Security.EscapeOutput
				?>
			</p>
		<?php endif; ?>
	</footer>
</aside>
