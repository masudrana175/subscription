<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Streams all subscriptions as a CSV download.
 */
class Sfiler_Admin_Export {

	public static function init() {
		add_action( 'admin_post_sfiler_export_subscriptions', array( __CLASS__, 'export' ) );
	}

	public static function export() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'sfiler_export_subscriptions' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'subscript-filter' ) );
		}

		global $wpdb;
		$table = Sfiler_Subscription::table();
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=subscript-filter-subscriptions-' . gmdate( 'Y-m-d' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );

		if ( ! empty( $rows ) ) {
			fputcsv( $output, array_keys( $rows[0] ) );
			foreach ( $rows as $row ) {
				fputcsv( $output, $row );
			}
		} else {
			fputcsv( $output, array( 'id', 'customer_id', 'status' ) );
		}

		fclose( $output );
		exit;
	}
}
