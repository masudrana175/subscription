<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plain wp_mail notifications for renewal lifecycle events.
 */
class Sfiler_Emails {

	public static function init() {
		// Hooked from Sfiler_Cron; nothing to bootstrap here beyond static senders.
	}

	private static function customer_email( $customer_id ) {
		$user = get_userdata( $customer_id );
		return $user ? $user->user_email : '';
	}

	public static function send_upcoming_reminder( $subscription ) {
		$to = self::customer_email( $subscription->customer_id );
		if ( ! $to ) {
			return;
		}

		$subject = sprintf( __( 'Your %s subscription renews soon', 'subscript-filter' ), get_bloginfo( 'name' ) );
		$body    = sprintf(
			__( "Hi,\n\nYour subscription for \"%1\$s\" will renew on %2\$s for %3\$s.\n\nNo action is needed — we'll charge your saved payment method automatically.\n\nManage your subscription: %4\$s", 'subscript-filter' ),
			$subscription->product_name,
			date_i18n( get_option( 'date_format' ), strtotime( $subscription->next_payment_date ) ),
			wc_price( $subscription->line_total, array( 'currency' => $subscription->currency ) ),
			wc_get_account_endpoint_url( 'subscriptions' )
		);

		wp_mail( $to, $subject, wp_strip_all_tags( $body ) );
	}

	public static function send_renewal_success( $subscription, $order ) {
		$to = self::customer_email( $subscription->customer_id );
		if ( ! $to ) {
			return;
		}

		$subject = sprintf( __( 'Your %s subscription renewed', 'subscript-filter' ), get_bloginfo( 'name' ) );
		$body    = sprintf(
			__( "Hi,\n\nWe've successfully renewed your subscription for \"%1\$s\". Order #%2\$d totaling %3\$s.\n\nNext renewal: %4\$s", 'subscript-filter' ),
			$subscription->product_name,
			$order->get_id(),
			wc_price( $subscription->line_total, array( 'currency' => $subscription->currency ) ),
			date_i18n( get_option( 'date_format' ), strtotime( $subscription->next_payment_date ) + 1 )
		);

		wp_mail( $to, $subject, wp_strip_all_tags( $body ) );
	}

	public static function send_renewal_failed( $subscription, $reason ) {
		$to = self::customer_email( $subscription->customer_id );
		if ( ! $to ) {
			return;
		}

		$subject = sprintf( __( 'Action needed: payment failed for your %s subscription', 'subscript-filter' ), get_bloginfo( 'name' ) );
		$body    = sprintf(
			__( "Hi,\n\nWe couldn't charge your saved payment method for your subscription to \"%1\$s\": %2\$s\n\nPlease update your payment method here: %3\$s", 'subscript-filter' ),
			$subscription->product_name,
			$reason,
			wc_get_account_endpoint_url( 'payment-methods' )
		);

		wp_mail( $to, $subject, wp_strip_all_tags( $body ) );
	}
}
