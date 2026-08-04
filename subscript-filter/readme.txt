=== Subscript Filter ===
Contributors: designfilters
Tags: woocommerce, subscriptions, stripe, apple pay, recurring payments
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
WC requires at least: 6.0
Stable tag: 1.5.1
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
* A Stripe checkout plugin that saves reusable payment methods for logged-in
  customers. Verified against "Payment Plugins for Stripe WooCommerce"
  (woo-stripe-payment) v4.0.8 — when active, Subscript Filter automatically
  uses its wc_stripe_get_secret_key() / wc_stripe_get_customer_id() helpers
  and reads the exact payment method used on each order via its
  WC_Stripe_Constants::PAYMENT_METHOD_TOKEN / ::CUSTOMER_ID order meta (the
  same fields that plugin's own WooCommerce-Subscriptions renewal handler
  reads). Falls back to a manually-entered Stripe secret key (Subscript
  Filter > Settings) and WC_Payment_Tokens lookups for any other Stripe
  checkout plugin, including the official WooCommerce Stripe Gateway.
* The Stripe secret key entered in Settings must belong to the same Stripe
  account your storefront checkout uses (customers/payment methods are
  account-scoped).

= Plugin structure =

	subscript-filter.php              Bootstrap: constants, activation, includes
	includes/
	  class-sfiler-helpers.php        Shared helpers (interval units, statuses, date math)
	  class-sfiler-install.php        Activation: DB tables, default options
	  class-sfiler-subscription.php   Subscription data model / CRUD / queries
	  class-sfiler-product.php        Product data tab (enable, discount override, frequencies)
	  class-sfiler-frontend.php       Enqueues assets, renders the purchase-options box
	  class-sfiler-cart.php           Cart pricing, order line item meta
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
* Site-wide default subscription discount (Subscript Filter > Settings),
  optionally overridden per product (Product data > Subscriptions tab).
* Pick which billing frequencies a product offers from a preset list
  (every 1/2/3/6/12 months).
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

= 1.5.1 =
* Fixed the admin frequency checkboxes rendering broken/unstyled: nesting
  a `<label>` per checkbox inside a WooCommerce `.form-field` meant each
  one inherited WooCommerce's own `.form-field label { float:left;
  width:150px }` admin style, collapsing the layout. Reset explicitly for
  those nested labels.
* Applied the site's brand colors (#1f9ccf / #c74e9d) across the
  storefront purchase box, the admin frequency picker and buttons, and
  the My Account subscription pages, replacing the placeholder palette.

= 1.5.0 =
* Added a site-wide default subscription discount (Subscript Filter >
  Settings); per-product discount is now an optional override instead of
  a required field.
* Removed the sign-up fee feature (product field, cart fee, order meta).
* Billing frequency is now chosen from a preset checklist (every 1, 2, 3,
  6, or 12 months) instead of freeform count/unit rows, matching the
  reference design. The storefront frequency dropdown defaults to the
  longest configured frequency.
* Restyled the storefront purchase box (spacing, custom select, dark-mode
  support) and the admin Subscriptions tab/list/detail pages; added
  styling to the My Account subscriptions pages, which previously loaded
  no CSS at all. Status is now shown as a consistent colored pill
  everywhere (admin list, admin detail, My Account list and detail).
* General copy pass: clearer field labels and descriptions across the
  product settings, Settings page, and storefront box.

= 1.4.0 =
Bug-fix pass across the renewal engine, pricing, and admin/customer search —
found by a full code audit before production testing:

* **Double-charge risk removed.** The Stripe charge and renewal-order
  creation happened in the wrong order with no idempotency protection: if
  the HTTP call to Stripe succeeded but recording it failed, or a cron
  overlap re-ran the same subscription, the customer could be charged
  twice for one billing cycle. The renewal order is now created first (and
  its ID used as a stable per-cycle Stripe idempotency key), so a retry
  reuses the original charge result instead of creating a new one.
  `wc_create_order()` returning `WP_Error` is now also handled instead of
  fataling.
* **Discount no longer compounds.** `woocommerce_before_calculate_totals`
  can fire more than once per request; the subscription price was being
  discounted from the *already discounted* price on each extra firing,
  compounding it. Now always discounts from the stable regular price.
* **Product-page price now matches what checkout charges.** The purchase
  box was discounting from the current (possibly on-sale) price while the
  cart discounted from the regular price — a store sale could make the
  advertised subscribe price different from what the customer was actually
  charged. Both now use the regular price, on the initial page render and
  on variation change.
* **Customer payment-method picker was always empty.** It looked up saved
  cards with `WC_Payment_Tokens::get_customer_tokens( $id, 'stripe' )`,
  which matches only an exact gateway ID of "stripe" — but this plugin
  registers stripe_cc, stripe_applepay, etc., so the lookup never matched
  anything. Fixed to match any gateway ID containing "stripe", consistent
  with the rest of the plugin.
* **Admin customer search was silently broken.** It searched `get_users()`
  with a SQL LIKE-style `%term%` string, but `WP_User_Query`'s search
  syntax uses `*` as its wildcard, not `%` — so searching by customer name
  or email never returned a match. Fixed to use the correct wildcard.
* **Renewal success email showed the wrong next-payment date** — it used
  the subscription record from before the renewal, still holding the date
  that had just come due, instead of the newly calculated one.
* Hardened `Sfiler_Product::save()` against a malformed frequency POST
  payload that could otherwise fatal on `foreach`.

= 1.3.0 =
* Verified integration against the real "Payment Plugins for Stripe
  WooCommerce" (woo-stripe-payment) v4.0.8 source. Renewals now read the
  exact payment method + Stripe customer used on the subscription's
  initial order (WC_Stripe_Constants::PAYMENT_METHOD_TOKEN / ::CUSTOMER_ID
  order meta) instead of guessing from the customer's current default
  saved card, matching how that plugin's own WooCommerce-Subscriptions
  renewal handler resolves the same data.
* Secret key / customer ID now come from that plugin's own
  wc_stripe_get_secret_key() / wc_stripe_get_customer_id() helper
  functions when it's active, with Subscript Filter's manual settings kept
  as a fallback for any other Stripe checkout plugin.
* Settings page shows a green confirmation when the plugin is detected and
  labels the manual key fields as a fallback rather than the primary path.

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
