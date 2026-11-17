<?php
/**
 * Packing slip. Override by copying to your-theme/grow-shop/packing-slip.php.
 *
 * Available: $id (order ID), $d (order data).
 *
 * @package GrowShop
 */

defined( 'ABSPATH' ) || exit;
$links = grs_legal_links();
?>
<!doctype html>
<html><head><meta charset="utf-8"><title><?php echo esc_html( grs_order_number( $id ) ); ?></title>
<style>
	body { font: 14px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #111; max-width: 720px; margin: 32px auto; padding: 0 24px; }
	h1 { font-size: 20px; margin: 0; }
	.top { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #111; padding-bottom: 12px; margin-bottom: 24px; }
	.addr { font-size: 16px; line-height: 1.45; margin: 0 0 28px; }
	table { width: 100%; border-collapse: collapse; }
	th, td { text-align: left; padding: 8px 6px; border-bottom: 1px solid #ddd; vertical-align: top; }
	th.n, td.n { text-align: right; width: 60px; }
	.tick { width: 28px; }
	.box { display: inline-block; width: 14px; height: 14px; border: 1px solid #111; }
	.foot { margin-top: 32px; color: #555; font-size: 12px; }
	@media print { .noprint { display: none; } body { margin: 0; } }
</style></head>
<body>
	<p class="noprint"><button onclick="window.print()"><?php esc_html_e( 'Print', 'grow-shop' ); ?></button></p>
	<div class="top">
		<div><h1><?php echo esc_html( grs_shop_name() ); ?></h1><?php echo esc_html( preg_replace( '#^https?://#', '', untrailingslashit( home_url() ) ) ); ?></div>
		<div style="text-align:right"><strong><?php echo esc_html( grs_order_number( $id ) ); ?></strong><br><?php echo esc_html( get_the_date( 'j F Y', $id ) ); ?></div>
	</div>
	<p class="addr"><?php echo implode( '<br>', array_map( 'esc_html', grs_address_lines( $d['address'] ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
	<table>
		<thead><tr><th class="tick"></th><th><?php esc_html_e( 'Item', 'grow-shop' ); ?></th><th class="n"><?php esc_html_e( 'Qty', 'grow-shop' ); ?></th></tr></thead>
		<tbody>
		<?php foreach ( $d['lines'] as $line ) : ?>
			<tr><td class="tick"><span class="box"></span></td><td><?php echo esc_html( $line['name'] ); ?><?php echo $line['options'] ? '<br><small>' . esc_html( $line['options'] ) . '</small>' : ''; ?></td><td class="n"><?php echo (int) $line['qty']; ?></td></tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<p><?php echo esc_html( sprintf( /* translators: %s: postage method */ __( 'Postage: %s', 'grow-shop' ), $d['method_name'] ) ); ?></p>
	<p class="foot"><?php esc_html_e( 'Thank you for your order.', 'grow-shop' ); ?>
	<?php foreach ( $links as $label => $url ) : ?>
		<br><?php echo esc_html( $label . ': ' . $url ); ?>
	<?php endforeach; ?>
	</p>
</body></html>
