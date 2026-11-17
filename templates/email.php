<?php
/**
 * Email layout. Override by copying to your-theme/grow-shop/email.php.
 *
 * Available: $content (HTML body), $accent (hex), $logo (URL or ''),
 * $links (label => URL legal links, empty for shop emails).
 *
 * @package GrowShop
 */

defined( 'ABSPATH' ) || exit;
$font = '-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif';
?>
<!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>
<body style="margin:0;padding:0;background:#f4f3ef;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f3ef;">
<tr><td align="center" style="padding:28px 12px;">
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:580px;background:#ffffff;border-top:4px solid <?php echo esc_attr( $accent ); ?>;">
		<tr><td style="padding:28px 32px 8px;font-family:<?php echo esc_attr( $font ); ?>;">
			<?php if ( $logo ) : ?>
				<img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( grs_shop_name() ); ?>" style="max-width:200px;max-height:80px;height:auto;display:block;border:0;">
			<?php else : ?>
				<p style="margin:0;font-size:18px;font-weight:bold;color:<?php echo esc_attr( $accent ); ?>;"><?php echo esc_html( grs_shop_name() ); ?></p>
			<?php endif; ?>
		</td></tr>
		<tr><td style="padding:12px 32px 28px;font-family:<?php echo esc_attr( $font ); ?>;font-size:15px;line-height:1.6;color:#222;">
			<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- built and escaped in grs_fill(). ?>
		</td></tr>
		<?php if ( $links ) : ?>
			<tr><td style="padding:14px 32px 22px;border-top:1px solid #eee;font-family:<?php echo esc_attr( $font ); ?>;font-size:12px;color:#888;">
				<?php
				$parts = array();
				foreach ( $links as $label => $url ) {
					$parts[] = '<a href="' . esc_url( $url ) . '" style="color:#888;">' . esc_html( $label ) . '</a>';
				}
				echo implode( ' &nbsp;&middot;&nbsp; ', $parts ); // phpcs:ignore WordPress.Security.EscapeOutput
				?>
			</td></tr>
		<?php endif; ?>
	</table>
</td></tr>
</table>
</body></html>
