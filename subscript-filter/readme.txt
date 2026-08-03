=== Subscript Filter ===
Contributors: designfilters
Tags: woocommerce, subscriptions, stripe, apple pay, recurring payments
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
WC requires at least: 6.0
Stable tag: 1.2.0
License: GPLv2 or later

Adds subscribe-and-save recurring purchasing to existing WooCommerce simple
and variable products, billed through the store's existing Stripe gateway
(including Apple Pay).

== Description ==

Subscript Filter lets customers choose between a one-time purchase or a
recurring subscription (at a configurable discount, sign-up fee, and
frequency) directly on the product page, without changing the underlying
product catalog.

Renewals are billed automatically via the Stripe payment method the
customer saved on their initial order — including Apple Pay, which Stripe
stores as a reusable card payment method under the hood, so it renews the
same way a saved card does.

= Requirements =

* WooCommerce
* A Stripe checkout plugin that saves reusable payment methods for logged-in
  customers (tested against Payment Plugins for Stripe WooCommerce; also
  works with the official WooCommerce Stripe Payment Gateway). Subscript
  Filter does not read that plugin's settings — it charges renewals through
  its own Stripe API key, configured on Subscript Filter > Settings, so it
  is not tied to any single checkout plugin's internal option names.
* The Stripe secret key entered in Settings must belong to the same Stripe
  account your storefront checkout uses (customers/payment methods are
  account-scoped).

= Plugin structure =

	subscript-filter.php              Bootstrap: constants, activation, includes
	includes/
	  class-sfiler-helpers.php        Shared helpers (interval units, statuses, date math)
	  class-sfiler-install.php        Activation: DB tables, default options
	  class-sfiler-subscription.php   Subscription data model / CRUD / queries
	  class-sfiler-product.php        Product data tab (enable, discount, sign-up fee, frequencies)
	  class-sfiler-frontend.php       Enqueues assets, renders the purchase-options box
	  class-sfiler-cart.php           Cart pricing, sign-up fees, order line item meta
	  class-sfiler-order.php          Creates subscriptions from paid orders, builds renewal orders
	  class-sfiler-stripe.php         Stripe REST API calls (customer token lookup, off-session charge)
	  class-sfiler-cron.php           Daily renewal + reminder engine
	  class-sfiler-emails.php         Renewal reminder/success/failure emails
	  class-sfiler-my-account.php     My Account subscriptions list + detail page + self-service actions
	  admin/
	    class-sfiler-admin.php            Admin menu, settings, view/edit/create controllers
	    class-sfiler-admin-list-table.php Subscriptions list table (filters, search, pagination)
	    class-sfiler-admin-export.php     CSV export
	templates/
	  frontend/purchase-options.php       "Choose how to buy" box
	  myaccount/subscriptions.php         Customer subscriptions list
	  myaccount/subscription-view.php     Customer subscription detail + actions
	  admin/subscription-view.php         Admin subscription detail/edit + activity log
	  admin/subscription-new.php          Admin manual subscription creation

= Features =

Product & storefront
* Per-product subscription toggle, discount percentage, sign-up fee, and
  one or more billing frequencies (Product data > Subscript Filter tab).
* "Choose how to buy" box on the product page (one-time vs. subscribe &
  save), price and savings calculated live, including on variable products.

Billing engine
* Subscription record created automatically once the first order is paid,
  capturing the customer's saved Stripe payment method (card or Apple Pay).
* Daily renewal engine: charges the saved Stripe payment method off-session,
  creates a WooCommerce renewal order, retries failed payments on a
  configurable schedule, and emails the customer at each step (upcoming
  reminder, success, failure).

Customer (My Account > Subscriptions)
* List of all subscriptions with status, amount, frequency, next payment.
* Detail page per subscription: full order history (initial + renewals),
  pause / reactivate / cancel, change billing frequency, and switch which
  saved payment method is charged.

Admin (Subscript Filter menu)
* Subscriptions list with status filter tabs, search, and pagination.
* Subscription detail/edit page: adjust amount, interval, next payment
  date, status, or the linked Stripe IDs; full activity log; linked orders.
* Manually create a subscription for any customer.
* Manual "retry charge" / "cancel" actions per subscription.
* CSV export of all subscriptions.
* Settings: max retry attempts, retry interval, reminder timing.
* Warning banner if the Stripe gateway's "Saved cards" option is off.

== Changelog ==

= 1.2.0 =
* Stripe secret key is now configured directly on Subscript Filter's own
  Settings page (test/live keys, test-mode toggle, "Test connection"
  button) instead of being read from another gateway plugin's option
  storage, so it works the same with Payment Plugins for Stripe WooCommerce,
  the official WooCommerce Stripe Gateway, or any similar plugin.
* Saved payment method lookup now matches any gateway ID containing
  "stripe" (not just the official plugin's exact ID), and resolves the
  Stripe customer ID by asking Stripe which customer a payment method is
  attached to, rather than guessing a plugin-specific user-meta key.
* Updated the "not configured" admin warning to point at Subscript Filter's
  own settings instead of a specific gateway's settings page.

= 1.1.0 =
* Restructured into includes/ (core) and includes/admin/ (admin-only), with
  templates split into frontend/myaccount/admin.
* Added sign-up fee support.
* Added admin subscription detail/edit page with activity log and linked orders.
* Added manual subscription creation, CSV export, and list table filters/search.
* Added customer subscription detail page: order history, reactivate,
  change frequency, change payment method.
* Added a Stripe "Saved cards" configuration warning in the admin screens.

= 1.0.0 =
* Initial release.
