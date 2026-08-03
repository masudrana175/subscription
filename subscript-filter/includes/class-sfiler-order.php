<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates a Sfiler_Subscription record for each subscription line item once
 * the initial order is paid, and builds renewal orders when the cron fires.
 */
class Sfiler_Order {

	public static function init() {
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'maybe_create_subscriptions' ) );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'maybe_create_subscriptions' ) );
	}

	public static function maybe_create_subscriptions( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		foreach ( $order->get_items() as $item_id => $item ) {
			if ( 'yes' !== $item->get_meta( '_sfiler_subscription' ) ) {
				continue;
			}

			if ( $item->get_meta( '_sfiler_subscription_id' ) ) {
				continue;
			}

			self::create_subscription_from_item( $order, $item, $item_id );
		}
	}

	private static function create_subscription_from_item( $order, $item, $item_id ) {
		$customer_id = $order->get_customer_id();

		if ( ! $customer_id ) {
			return;
		}

		$payment_method = Sfiler_Stripe::get_order_payment_method( $order );

		$interval_count = (int) $item->get_meta( '_sfiler_interval_count' );
		$interval_unit  = $item->get_meta( '_sfiler_interval_unit' );

		$next_payment = sfiler_calculate_next_payment_date( current_time( 'timestamp', true ), $interval_count, $interval_unit );

		$subscription_id = Sfiler_Subscription::create(
			array(
				'customer_id'              => $customer_id,
				'parent_order_id'          => $order->get_id(),
				'product_id'               => $item->get_product_id(),
				'variation_id'             => $item->get_variation_id(),
				'product_name'             => $item->get_name(),
				'line_total'               => $item->get_total() + $item->get_total_tax(),
				'currency'                 => $order->get_currency(),
				'interval_count'           => $interval_count,
				'interval_unit'            => $interval_unit,
				'stripe_customer_id'       => $payment_method['stripe_customer_id'],
				'stripe_payment_method_id' => $payment_method['payment_method_id'],
				'next_payment_date'        => $next_payment,
			)
		);

		$item->add_meta_data( '_sfiler_subscription_id', $subscription_id, true );
		$item->save();

		if ( empty( $payment_method['payment_method_id'] ) ) {
			Sfiler_Subscription::add_note( $subscription_id, __( 'Warning: no saved Stripe payment method found on the initial order. Renewals will fail until the customer adds one via My Account.', 'subscript-filter' ) );
		}
	}

	public static function get_orders_for_subscription( $subscription_id ) {
		return wc_get_orders(
			array(
				'limit'      => -1,
				'orderby'    => 'date',
				'order'      => 'DESC',
				'meta_key'   => '_sfiler_subscription_id',
				'meta_value' => $subscription_id,
			)
		);
	}

	public static function create_renewal_order( $subscription ) {
		$order = wc_create_order(
			array(
				'customer_id' => $subscription->customer_id,
			)
		);

		$product = wc_get_product( $subscription->variation_id ? $subscription->variation_id : $subscription->product_id );

		if ( $product ) {
			$order->add_product( $product, 1, array( 'subtotal' => $subscription->line_total, 'total' => $subscription->line_total ) );
		}

		$order->set_currency( $subscription->currency );
		$order->add_meta_data( '_sfiler_subscription_id', $subscription->id, true );
		$order->add_meta_data( '_sfiler_renewal_order', 'yes', true );
		$order->set_payment_method( 'stripe' );
		$order->calculate_totals( false );
		$order->save();

		return $order;
	}
}
