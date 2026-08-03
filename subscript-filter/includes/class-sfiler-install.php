<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sfiler_Install {

	public static function install() {
		self::create_tables();
		self::add_default_options();

		if ( ! wp_next_scheduled( 'sfiler_process_renewals' ) ) {
			wp_schedule_event( time() + 300, 'daily', 'sfiler_process_renewals' );
		}

		update_option( 'sfiler_db_version', SFILER_VERSION );
	}

	private static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$table            = $wpdb->prefix . 'sfiler_subscriptions';

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_id BIGINT UNSIGNED NOT NULL,
			parent_order_id BIGINT UNSIGNED NOT NULL,
			product_id BIGINT UNSIGNED NOT NULL,
			variation_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			product_name VARCHAR(255) NOT NULL DEFAULT '',
			line_total DECIMAL(19,4) NOT NULL DEFAULT 0,
			currency VARCHAR(10) NOT NULL DEFAULT '',
			interval_count SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			interval_unit VARCHAR(10) NOT NULL DEFAULT 'month',
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			stripe_customer_id VARCHAR(255) NOT NULL DEFAULT '',
			stripe_payment_method_id VARCHAR(255) NOT NULL DEFAULT '',
			start_date DATETIME NOT NULL,
			next_payment_date DATETIME NOT NULL,
			last_payment_date DATETIME NULL,
			failed_payment_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			reminder_sent TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY customer_id (customer_id),
			KEY status (status),
			KEY next_payment_date (next_payment_date)
		) {$charset_collate};";

		dbDelta( $sql );

		$log_table = $wpdb->prefix . 'sfiler_subscription_notes';
		$sql_log   = "CREATE TABLE {$log_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			subscription_id BIGINT UNSIGNED NOT NULL,
			note TEXT NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY subscription_id (subscription_id)
		) {$charset_collate};";

		dbDelta( $sql_log );
	}

	private static function add_default_options() {
		add_option( 'sfiler_global_discount_percent', 10 );
		add_option( 'sfiler_max_retry_attempts', 3 );
		add_option( 'sfiler_retry_interval_days', 3 );
		add_option( 'sfiler_reminder_days_before', 3 );
	}
}
