<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Admin subscriptions list table: status filters, search, pagination.
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
			'id'           => __( 'ID', 'subscript-filter' ),
			'customer'     => __( 'Customer', 'subscript-filter' ),
			'product'      => __( 'Product', 'subscript-filter' ),
			'amount'       => __( 'Amount', 'subscript-filter' ),
			'interval'     => __( 'Interval', 'subscript-filter' ),
			'status'       => __( 'Status', 'subscript-filter' ),
			'next_payment' => __( 'Next payment', 'subscript-filter' ),
			'actions'      => __( 'Actions', 'subscript-filter' ),
		);
	}

	public function get_views() {
		$current = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
		$base_url = remove_query_arg( array( 'status', 'paged' ) );

		$views = array(
			'all' => sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
				esc_url( $base_url ),
				'' === $current ? 'current' : '',
				esc_html__( 'All', 'subscript-filter' ),
				Sfiler_Subscription::count_all()
			),
		);

		foreach ( sfiler_get_statuses() as $status_key => $status_label ) {
			$views[ $status_key ] = sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
				esc_url( add_query_arg( 'status', $status_key, $base_url ) ),
				$current === $status_key ? 'current' : '',
				esc_html( $status_label ),
				Sfiler_Subscription::count_by_status( $status_key )
			);
		}

		return $views;
	}

	public function prepare_items() {
		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$status       = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
		$search       = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

		$result = Sfiler_Subscription::query(
			array(
				'status'   => $status,
				'search'   => $search,
				'per_page' => $per_page,
				'page'     => $current_page,
			)
		);

		$this->items            = $result['items'];
		$this->_column_headers  = array( $this->get_columns(), array(), array() );

		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
			)
		);
	}

	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'id':
				return '<a href="' . esc_url( Sfiler_Admin::get_view_url( $item->id ) ) . '">#' . esc_html( $item->id ) . '</a>';
			case 'customer':
				$user = get_userdata( $item->customer_id );
				return $user ? esc_html( $user->display_name . ' (' . $user->user_email . ')' ) : esc_html__( 'Guest', 'subscript-filter' );
			case 'product':
				return esc_html( $item->product_name );
			case 'amount':
				return wc_price( $item->line_total, array( 'currency' => $item->currency ) );
			case 'interval':
				return esc_html( sfiler_format_interval( $item->interval_count, $item->interval_unit ) );
			case 'status':
				return '<span class="sfiler-status sfiler-status-' . esc_attr( $item->status ) . '">' . esc_html( ucfirst( str_replace( '-', ' ', $item->status ) ) ) . '</span>';
			case 'next_payment':
				return esc_html( date_i18n( get_option( 'date_format' ), strtotime( $item->next_payment_date ) ) );
			case 'actions':
				return $this->render_actions( $item );
			default:
				return '';
		}
	}

	private function render_actions( $item ) {
		$view_url   = Sfiler_Admin::get_view_url( $item->id );
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

		$actions   = array();
		$actions[] = '<a href="' . esc_url( $view_url ) . '">' . esc_html__( 'View', 'subscript-filter' ) . '</a>';
		$actions[] = '<a href="' . esc_url( $retry_url ) . '">' . esc_html__( 'Retry charge', 'subscript-filter' ) . '</a>';

		if ( 'cancelled' !== $item->status ) {
			$actions[] = '<a href="' . esc_url( $cancel_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Cancel this subscription?', 'subscript-filter' ) ) . '\');">' . esc_html__( 'Cancel', 'subscript-filter' ) . '</a>';
		}

		return implode( ' | ', $actions );
	}
}
