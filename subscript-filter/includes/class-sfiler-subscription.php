<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD wrapper around the wp_sfiler_subscriptions table.
 */
class Sfiler_Subscription {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'sfiler_subscriptions';
	}

	public static function notes_table() {
		global $wpdb;
		return $wpdb->prefix . 'sfiler_subscription_notes';
	}

	public static function create( $args ) {
		global $wpdb;

		$now = current_time( 'mysql', true );

		$defaults = array(
			'customer_id'              => 0,
			'parent_order_id'          => 0,
			'product_id'               => 0,
			'variation_id'             => 0,
			'product_name'             => '',
			'line_total'               => 0,
			'currency'                 => get_woocommerce_currency(),
			'interval_count'           => 1,
			'interval_unit'            => 'month',
			'status'                   => 'active',
			'stripe_customer_id'       => '',
			'stripe_payment_method_id' => '',
			'start_date'               => $now,
			'next_payment_date'        => $now,
			'last_payment_date'        => null,
			'failed_payment_count'     => 0,
			'reminder_sent'            => 0,
			'created_at'               => $now,
			'updated_at'               => $now,
		);

		$data = wp_parse_args( $args, $defaults );

		$wpdb->insert( self::table(), $data );

		$id = (int) $wpdb->insert_id;

		self::add_note( $id, __( 'Subscription created.', 'subscript-filter' ) );

		return $id;
	}

	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	public static function get_for_customer( $customer_id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE customer_id = %d ORDER BY id DESC", $customer_id ) );
	}

	public static function get_due( $limit = 50 ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = 'active' AND next_payment_date <= %s ORDER BY next_payment_date ASC LIMIT %d",
				current_time( 'mysql', true ),
				$limit
			)
		);
	}

	public static function get_due_for_reminder( $days_before, $limit = 100 ) {
		global $wpdb;
		$table    = self::table();
		$target   = date( 'Y-m-d', strtotime( '+' . (int) $days_before . ' days', current_time( 'timestamp', true ) ) );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = 'active' AND reminder_sent = 0 AND DATE(next_payment_date) = %s LIMIT %d",
				$target,
				$limit
			)
		);
	}

	public static function update( $id, $data ) {
		global $wpdb;
		$data['updated_at'] = current_time( 'mysql', true );
		return $wpdb->update( self::table(), $data, array( 'id' => $id ) );
	}

	public static function update_status( $id, $status ) {
		self::update( $id, array( 'status' => $status ) );
		self::add_note( $id, sprintf( __( 'Status changed to %s.', 'subscript-filter' ), $status ) );
	}

	public static function record_payment_success( $id, $order_id ) {
		$subscription = self::get( $id );
		if ( ! $subscription ) {
			return;
		}

		$next = sfiler_calculate_next_payment_date( current_time( 'timestamp', true ), $subscription->interval_count, $subscription->interval_unit );

		self::update(
			$id,
			array(
				'last_payment_date'    => current_time( 'mysql', true ),
				'next_payment_date'    => $next,
				'failed_payment_count' => 0,
				'reminder_sent'        => 0,
			)
		);

		self::add_note( $id, sprintf( __( 'Renewal order #%1$d paid. Next payment date: %2$s.', 'subscript-filter' ), $order_id, $next ) );
	}

	public static function record_payment_failure( $id, $reason = '' ) {
		global $wpdb;
		$subscription = self::get( $id );
		if ( ! $subscription ) {
			return;
		}

		$attempts    = (int) $subscription->failed_payment_count + 1;
		$max_retries = (int) get_option( 'sfiler_max_retry_attempts', 3 );
		$retry_days  = (int) get_option( 'sfiler_retry_interval_days', 3 );

		$data = array( 'failed_payment_count' => $attempts );

		if ( $attempts >= $max_retries ) {
			$data['status'] = 'on-hold';
			self::add_note( $id, sprintf( __( 'Payment failed (%1$d/%2$d attempts). Subscription put on-hold. %3$s', 'subscript-filter' ), $attempts, $max_retries, $reason ) );
		} else {
			$data['next_payment_date'] = date( 'Y-m-d H:i:s', strtotime( '+' . $retry_days . ' days', current_time( 'timestamp', true ) ) );
			self::add_note( $id, sprintf( __( 'Payment failed (%1$d/%2$d attempts). Retrying on %3$s. %4$s', 'subscript-filter' ), $attempts, $max_retries, $data['next_payment_date'], $reason ) );
		}

		self::update( $id, $data );
	}

	public static function cancel( $id ) {
		self::update_status( $id, 'cancelled' );
	}

	public static function pause( $id ) {
		self::update_status( $id, 'on-hold' );
	}

	public static function resume( $id ) {
		$subscription = self::get( $id );
		if ( ! $subscription ) {
			return;
		}
		$next = sfiler_calculate_next_payment_date( current_time( 'timestamp', true ), $subscription->interval_count, $subscription->interval_unit );
		self::update( $id, array( 'status' => 'active', 'next_payment_date' => $next, 'failed_payment_count' => 0 ) );
		self::add_note( $id, __( 'Subscription resumed.', 'subscript-filter' ) );
	}

	public static function add_note( $id, $note ) {
		global $wpdb;
		$wpdb->insert(
			self::notes_table(),
			array(
				'subscription_id' => $id,
				'note'            => $note,
				'created_at'      => current_time( 'mysql', true ),
			)
		);
	}

	public static function get_notes( $id ) {
		global $wpdb;
		$table = self::notes_table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE subscription_id = %d ORDER BY id DESC", $id ) );
	}

	public static function count_by_status( $status ) {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status ) );
	}
}
