=== Subscript Filter ===
Contributors: designfilters
Tags: woocommerce, subscriptions, stripe, apple pay, recurring payments
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
WC requires at least: 6.0
Stable tag: 1.0.0
License: GPLv2 or later

Adds subscribe-and-save recurring purchasing to existing WooCommerce simple
and variable products, billed through the store's existing Stripe gateway
(including Apple Pay).

== Description ==

Subscript Filter lets customers choose between a one-time purchase or a
recurring subscription (at a configurable discount and frequency) directly
on the product page, without changing the underlying product catalog.

Renewals are billed automatically via the Stripe payment method the
customer saved on their initial order — including Apple Pay, which Stripe
stores as a reusable card payment method under the hood, so it renews the
same way a saved card does.

= Requirements =

* WooCommerce
* WooCommerce Stripe Payment Gateway (official), configured with "Saved
  cards" / tokenization enabled so a reusable payment method exists to
  charge for renewals.

= Features =

* Per-product subscription toggle, discount percentage, and one or more
  billing frequencies (Product data > Subscript Filter tab).
* "Choose how to buy" box on the product page (one-time vs. subscribe &
  save), price and savings calculated live, including on variable products.
* Subscription record created automatically once the first order is paid.
* Daily renewal engine: charges the saved Stripe payment method off-session,
  creates a WooCommerce renewal order, retries failed payments, and emails
  the customer at each step.
* My Account > Subscriptions: customers can view, pause, resume, or cancel
  their own subscriptions.
* Admin > Subscript Filter: subscriptions list with manual retry/cancel,
  plus settings for retry attempts, retry interval, and reminder timing.

== Changelog ==

= 1.0.0 =
* Initial release.
