<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Daily WP-Cron job: sends upcoming-renewal reminders, then attempts to
 * charge every subscription that is due.
 */
class Sfiler_Cron {

	public static function init() {
		add_action( 'sfiler_process_renewals', array( __CLASS__, 'process_due_renewals' ) );
		add_action( 'sfiler_process_renewals', array( __CLASS__, 'send_upcoming_reminders' ) );

		if ( ! wp_next_scheduled( 'sfiler_process_renewals' ) ) {
			wp_schedule_event( time() + 300, 'daily', 'sfiler_process_renewals' );
		}
	}

	public static function process_due_renewals() {
		$due = Sfiler_Subscription::get_due();

		foreach ( $due as $subscription ) {
			self::process_single_renewal( $subscription );
		}
	}

	public static function process_single_renewal( $subscription ) {
		$order = Sfiler_Order::create_renewal_order( $subscription );

		if ( is_wp_error( $order ) ) {
			// No order to charge against or record the failure on — bail without
			// touching Stripe, and leave the subscription due so the next cron
			// run tries again.
			Sfiler_Subscription::add_note( $subscription->id, sprintf( __( 'Could not create renewal order: %s', 'subscript-filter' ), $order->get_error_message() ) );
			return;
		}

		// Stable per billing-cycle: a retried cron run (or a request that
		// succeeded at Stripe but failed before we recorded it) reuses this
		// order's ID and gets the same PaymentIntent back instead of a new charge.
		$idempotency_key = 'sfiler_renewal_' . $subscription->id . '_' . $order->get_id();

		try {
			$intent = Sfiler_Stripe::charge_renewal( $subscription, $idempotency_key );

			$order->add_meta_data( '_sfiler_payment_intent_id', isset( $intent['id'] ) ? $intent['id'] : '', true );
			$order->save();
			$order->payment_complete( isset( $intent['id'] ) ? $intent['id'] : '' );

			Sfiler_Subscription::record_payment_success( $subscription->id, $order->get_id() );
			// Re-fetch: record_payment_success() just advanced next_payment_date,
			// and the email should show that new date, not the one that was
			// just due (which is still on the $subscription object passed in).
			Sfiler_Emails::send_renewal_success( Sfiler_Subscription::get( $subscription->id ), $order );
		} catch ( Exception $e ) {
			$order->update_status( 'failed', $e->getMessage() );

			Sfiler_Subscription::record_payment_failure( $subscription->id, $e->getMessage() );
			Sfiler_Emails::send_renewal_failed( $subscription, $e->getMessage() );
		}
	}

	public static function send_upcoming_reminders() {
		$days_before = (int) get_option( 'sfiler_reminder_days_before', 3 );
		$due_soon    = Sfiler_Subscription::get_due_for_reminder( $days_before );

		foreach ( $due_soon as $subscription ) {
			Sfiler_Emails::send_upcoming_reminder( $subscription );
			Sfiler_Subscription::update( $subscription->id, array( 'reminder_sent' => 1 ) );
		}
	}
}
