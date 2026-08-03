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
		try {
			Sfiler_Stripe::charge_renewal( $subscription );

			$order = Sfiler_Order::create_renewal_order( $subscription );
			$order->payment_complete();

			Sfiler_Subscription::record_payment_success( $subscription->id, $order->get_id() );
			Sfiler_Emails::send_renewal_success( $subscription, $order );
		} catch ( Exception $e ) {
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
