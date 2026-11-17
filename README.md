# Grow Shop

A small shop for Grow Creative's WordPress sites, without WooCommerce. One plugin, every site, so a fix goes out once. Payment is on Stripe's hosted checkout page, so card details never touch the site.

Everything a shop owner changes is a setting under **Shop, Settings**:

- **Products**: tick any post type to sell it. Each type has a default price, a quantity pricing table (with a mix-and-match switch), options with price differences, stock (unlimited or counted) and the button wording. Each item can override its price, be marked not for sale, have its stock count, and switch options off, in a Shop box on its edit screen. Price and Stock columns, and bulk edit of price and for-sale.
- **Shipping**: zones (UK, Europe, everywhere else, or your own list of countries), each with one or more methods: flat per order, per item, or by quantity, with an optional free-over amount.
- **Payments**: test and live mode, a restricted Stripe key for each, **Test connection** and **Connect webhook** buttons.
- **Emails**: order confirmation, new order (to the shop), dispatched and refund, each switchable, with editable subject and message and placeholders. Logo, accent colour, test send.
- **Checkout**: slide-out basket or a basket page, most of one item per order, minimum order, phone number, thank-you and cancelled messages, Terms, Returns and Privacy pages.
- **Appearance**: wording, where the basket button goes, button colour, stylesheet on or off.
- **General**: Off, Admins only (preview) or On; holiday mode; shop name, currency, order number prefix.

**Orders** under Shop: statuses Paid (to dispatch), Dispatched, Refunded, Cancelled; an order screen with items, customer and address, a link to the payment in Stripe, notes, history, Mark dispatched (with tracking, emails the customer), Refund (full or part, through Stripe), Resend emails and a packing slip. CSV export, a dashboard widget and a count on the menu. WordPress's Export and Erase Personal Data tools find orders by email address.

The plan this implements: `sites/shop-module-plan.md` (4th November, 2026).

## How it works

- The basket is a list of IDs, options and quantities in the visitor's browser, so every page stays cache-safe on Kinsta. Every price, discount and postage cost is worked out on the server (`/wp-json/grow-shop/v1/quote`); the browser's numbers are never trusted.
- Checkout makes a hidden pending order and a Stripe Checkout Session limited to the chosen zone's countries. The order becomes Paid, with a number, only when Stripe confirms payment: through the webhook, or the thank-you page's own check with Stripe, whichever comes first, and only once. Unpaid checkouts are deleted after two days.
- Counted stock is held for 35 minutes while someone is at checkout.

## Stripe key

One restricted key per mode, with **Write** on Checkout Sessions, Charges and Refunds, and Webhook Endpoints, and nothing else. Paste it under Payments, save, then Connect webhook and Test connection. Keys can also be set in wp-config.php as `GROW_SHOP_STRIPE_TEST_KEY` and `GROW_SHOP_STRIPE_LIVE_KEY`.

## Putting it in a theme

With no theme changes, buttons appear after the content and a basket button floats in the corner. For control:

```php
<?php if ( function_exists( 'grow_shop_button' ) ) { grow_shop_button( $post_id, array( 'context' => 'single' ) ); } ?>
<?php if ( function_exists( 'grow_shop_basket_icon' ) ) { grow_shop_basket_icon(); } ?>
```

`grow_shop_price( $post_id )` prints "£2.50 each, £2 each for 20 or more". Shortcodes: `[grow_shop_button id=""]`, `[grow_shop_price id=""]`, `[grow_shop_basket_icon]`, `[grow_shop_basket]`.

Copy `templates/basket.php`, `email.php` or `packing-slip.php` into `your-theme/grow-shop/` to change them. Colours come from CSS variables (`--grs-accent`, `--grs-bg`, `--grs-text`, `--grs-rule`, `--grs-font`).

Filters: `grow_shop_purchasable` (extra rules for whether an item can be bought), `grow_shop_defaults`, `grow_shop_checkout_params`, `grow_shop_button_html`, `grow_shop_price_text`, `grow_shop_postage_text`, `grow_shop_roles`. Actions: `grow_shop_order_paid`, `grow_shop_order_dispatched`, `grow_shop_settings_saved`.

## Releases

Bump the version in the plugin header and `GRS_VERSION`, add a CHANGELOG entry, commit, then tag `v0.1.1` and push the tag. The workflow builds `grow-shop.zip` and attaches it to a GitHub release; sites see the update in Dashboard, Updates.
