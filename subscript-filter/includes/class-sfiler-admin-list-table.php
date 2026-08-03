<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Admin subscriptions list table.
 */
class Sfiler_Admin_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'subscription',
				'plural'   => 'subscriptions',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'id'          => __( 'ID', 'subscript-filter' ),
			'customer'    => __( 'Customer', 'subscript-filter' ),
			'product'     => __( 'Product', 'subscript-filter' ),
			'amount'      => __( 'Amount', 'subscript-filter' ),
			'interval'    => __( 'Interval', 'subscript-filter' ),
			'status'      => __( 'Status', 'subscript-filter' ),
			'next_payment' => __( 'Next payment', 'subscript-filter' ),
			'actions'     => __( 'Actions', 'subscript-filter' ),
		);
	}

	public function prepare_items() {
		global $wpdb;

		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$table        = Sfiler_Subscription::table();

		$total_items = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		$this->items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d",
				$per_page,
				( $current_page - 1 ) * $per_page
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), array() );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
			)
		);
	}

	public function column_default( $item, $column_name ) {
		$units = sfiler_get_interval_units();

		switch ( $column_name ) {
			case 'id':
				return esc_html( $item->id );
			case 'customer':
				$user = get_userdata( $item->customer_id );
				return $user ? esc_html( $user->display_name . ' (' . $user->user_email . ')' ) : esc_html__( 'Guest', 'subscript-filter' );
			case 'product':
				return esc_html( $item->product_name );
			case 'amount':
				return wc_price( $item->line_total, array( 'currency' => $item->currency ) );
			case 'interval':
				return esc_html( sprintf( '%d %s', $item->interval_count, strtolower( $units[ $item->interval_unit ] ) ) );
			case 'status':
				return esc_html( ucfirst( str_replace( '-', ' ', $item->status ) ) );
			case 'next_payment':
				return esc_html( date_i18n( get_option( 'date_format' ), strtotime( $item->next_payment_date ) ) );
			case 'actions':
				return $this->render_actions( $item );
			default:
				return '';
		}
	}

	private function render_actions( $item ) {
		$retry_url  = wp_nonce_url(
			add_query_arg(
				array(
					'action'          => 'sfiler_retry_subscription',
					'subscription_id' => $item->id,
				),
				admin_url( 'admin-post.php' )
			),
			'sfiler_retry_subscription'
		);

		$cancel_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'          => 'sfiler_cancel_subscription',
					'subscription_id' => $item->id,
				),
				admin_url( 'admin-post.php' )
			),
			'sfiler_cancel_subscription'
		);

		$actions = '<a href="' . esc_url( $retry_url ) . '">' . esc_html__( 'Retry charge', 'subscript-filter' ) . '</a>';

		if ( 'cancelled' !== $item->status ) {
			$actions .= ' | <a href="' . esc_url( $cancel_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Cancel this subscription?', 'subscript-filter' ) ) . '\');">' . esc_html__( 'Cancel', 'subscript-filter' ) . '</a>';
		}

		return $actions;
	}
}
