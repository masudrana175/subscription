<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin menu + page dispatcher (list / view / new), settings, and row actions.
 */
class Sfiler_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_show_saved_cards_notice' ) );
		add_action( 'admin_post_sfiler_save_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_sfiler_retry_subscription', array( __CLASS__, 'retry_subscription' ) );
		add_action( 'admin_post_sfiler_cancel_subscription', array( __CLASS__, 'cancel_subscription' ) );
		add_action( 'admin_post_sfiler_update_subscription', array( __CLASS__, 'update_subscription' ) );
		add_action( 'admin_post_sfiler_create_subscription', array( __CLASS__, 'create_subscription' ) );
	}

	public static function add_menu() {
		add_menu_page(
			__( 'Subscript Filter', 'subscript-filter' ),
			__( 'Subscript Filter', 'subscript-filter' ),
			'manage_woocommerce',
			'sfiler-subscriptions',
			array( __CLASS__, 'render_subscriptions_page' ),
			'dashicons-update',
			56
		);

		add_submenu_page(
			'sfiler-subscriptions',
			__( 'Subscriptions', 'subscript-filter' ),
			__( 'Subscriptions', 'subscript-filter' ),
			'manage_woocommerce',
			'sfiler-subscriptions',
			array( __CLASS__, 'render_subscriptions_page' )
		);

		add_submenu_page(
			'sfiler-subscriptions',
			__( 'Add subscription', 'subscript-filter' ),
			__( 'Add subscription', 'subscript-filter' ),
			'manage_woocommerce',
			'sfiler-subscription-new',
			array( __CLASS__, 'render_new_page' )
		);

		add_submenu_page(
			'sfiler-subscriptions',
			__( 'Settings', 'subscript-filter' ),
			__( 'Settings', 'subscript-filter' ),
			'manage_woocommerce',
			'sfiler-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'sfiler-' ) ) {
			return;
		}

		wp_enqueue_style( 'sfiler-admin', SFILER_PLUGIN_URL . 'assets/css/sfiler-admin.css', array(), SFILER_VERSION );
	}

	public static function get_view_url( $subscription_id ) {
		return add_query_arg(
			array(
				'page' => 'sfiler-subscriptions',
				'view' => $subscription_id,
			),
			admin_url( 'admin.php' )
		);
	}

	public static function maybe_show_saved_cards_notice() {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'sfiler-' ) ) {
			return;
		}

		if ( sfiler_stripe_saved_cards_enabled() ) {
			return;
		}
		?>
		<div class="notice notice-warning">
			<p>
				<?php
				printf(
					/* translators: %s: link to Stripe settings */
					esc_html__( 'Subscript Filter: "Saved cards" is not enabled on the WooCommerce Stripe Gateway. Renewals cannot be charged without it. %s', 'subscript-filter' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe' ) ) . '">' . esc_html__( 'Fix this now', 'subscript-filter' ) . '</a>'
				);
				?>
			</p>
		</div>
		<?php
	}

	public static function render_subscriptions_page() {
		if ( ! empty( $_GET['view'] ) ) {
			self::render_view_page( absint( $_GET['view'] ) );
			return;
		}

		$list_table = new Sfiler_Admin_List_Table();
		$list_table->prepare_items();
		$export_url = wp_nonce_url( add_query_arg( 'action', 'sfiler_export_subscriptions', admin_url( 'admin-post.php' ) ), 'sfiler_export_subscriptions' );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Subscriptions', 'subscript-filter' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=sfiler-subscription-new' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add subscription', 'subscript-filter' ); ?></a>
			<a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action"><?php esc_html_e( 'Export CSV', 'subscript-filter' ); ?></a>
			<hr class="wp-header-end" />
			<form method="get">
				<input type="hidden" name="page" value="sfiler-subscriptions" />
				<?php
				$list_table->views();
				$list_table->search_box( __( 'Search subscriptions', 'subscript-filter' ), 'sfiler-search' );
				$list_table->display();
				?>
			</form>
		</div>
		<?php
	}

	public static function render_view_page( $subscription_id ) {
		$subscription = Sfiler_Subscription::get( $subscription_id );

		if ( ! $subscription ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Subscription not found.', 'subscript-filter' ) . '</p></div>';
			return;
		}

		$notes    = Sfiler_Subscription::get_notes( $subscription_id );
		$orders   = Sfiler_Order::get_orders_for_subscription( $subscription_id );
		$units    = sfiler_get_interval_units();
		$statuses = sfiler_get_statuses();

		include SFILER_PLUGIN_DIR . 'templates/admin/subscription-view.php';
	}

	public static function render_new_page() {
		include SFILER_PLUGIN_DIR . 'templates/admin/subscription-new.php';
	}

	public static function render_settings_page() {
		$max_retry  = get_option( 'sfiler_max_retry_attempts', 3 );
		$retry_days = get_option( 'sfiler_retry_interval_days', 3 );
		$reminder   = get_option( 'sfiler_reminder_days_before', 3 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Subscript Filter Settings', 'subscript-filter' ); ?></h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sfiler_save_settings" />
				<?php wp_nonce_field( 'sfiler_save_settings' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="sfiler_max_retry_attempts"><?php esc_html_e( 'Max retry attempts', 'subscript-filter' ); ?></label></th>
						<td><input type="number" min="1" id="sfiler_max_retry_attempts" name="sfiler_max_retry_attempts" value="<?php echo esc_attr( $max_retry ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="sfiler_retry_interval_days"><?php esc_html_e( 'Days between retries', 'subscript-filter' ); ?></label></th>
						<td><input type="number" min="1" id="sfiler_retry_interval_days" name="sfiler_retry_interval_days" value="<?php echo esc_attr( $retry_days ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="sfiler_reminder_days_before"><?php esc_html_e( 'Send renewal reminder (days before)', 'subscript-filter' ); ?></label></th>
						<td><input type="number" min="0" id="sfiler_reminder_days_before" name="sfiler_reminder_days_before" value="<?php echo esc_attr( $reminder ); ?>" /></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<p><?php esc_html_e( 'Stripe API keys are read from the existing WooCommerce Stripe Gateway settings; no separate configuration is needed here.', 'subscript-filter' ); ?></p>
		</div>
		<?php
	}

	public static function save_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'sfiler_save_settings' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'subscript-filter' ) );
		}

		update_option( 'sfiler_max_retry_attempts', max( 1, absint( $_POST['sfiler_max_retry_attempts'] ) ) );
		update_option( 'sfiler_retry_interval_days', max( 1, absint( $_POST['sfiler_retry_interval_days'] ) ) );
		update_option( 'sfiler_reminder_days_before', absint( $_POST['sfiler_reminder_days_before'] ) );

		wp_safe_redirect( add_query_arg( array( 'page' => 'sfiler-settings', 'updated' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function retry_subscription() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'sfiler_retry_subscription' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'subscript-filter' ) );
		}

		$subscription_id = absint( $_GET['subscription_id'] );
		$subscription     = Sfiler_Subscription::get( $subscription_id );

		if ( $subscription ) {
			Sfiler_Cron::process_single_renewal( $subscription );
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=sfiler-subscriptions' ) );
		exit;
	}

	public static function cancel_subscription() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'sfiler_cancel_subscription' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'subscript-filter' ) );
		}

		$subscription_id = absint( $_GET['subscription_id'] );
		Sfiler_Subscription::cancel( $subscription_id );

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=sfiler-subscriptions' ) );
		exit;
	}

	public static function update_subscription() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'sfiler_update_subscription' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'subscript-filter' ) );
		}

		$subscription_id = absint( $_POST['subscription_id'] );
		$valid_units      = array_keys( sfiler_get_interval_units() );
		$valid_statuses   = array_keys( sfiler_get_statuses() );
		$unit             = in_array( $_POST['interval_unit'], $valid_units, true ) ? $_POST['interval_unit'] : 'month';
		$status           = in_array( $_POST['status'], $valid_statuses, true ) ? $_POST['status'] : 'active';

		Sfiler_Subscription::update(
			$subscription_id,
			array(
				'line_total'               => wc_format_decimal( wp_unslash( $_POST['line_total'] ) ),
				'interval_count'           => max( 1, absint( $_POST['interval_count'] ) ),
				'interval_unit'            => $unit,
				'status'                   => $status,
				'next_payment_date'        => sanitize_text_field( wp_unslash( $_POST['next_payment_date'] ) ),
				'stripe_customer_id'       => sanitize_text_field( wp_unslash( $_POST['stripe_customer_id'] ) ),
				'stripe_payment_method_id' => sanitize_text_field( wp_unslash( $_POST['stripe_payment_method_id'] ) ),
			)
		);

		Sfiler_Subscription::add_note( $subscription_id, __( 'Subscription manually updated by an administrator.', 'subscript-filter' ) );

		wp_safe_redirect( self::get_view_url( $subscription_id ) );
		exit;
	}

	public static function create_subscription() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'sfiler_create_subscription' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'subscript-filter' ) );
		}

		$customer = get_user_by( 'email', sanitize_email( wp_unslash( $_POST['customer_email'] ) ) );

		if ( ! $customer ) {
			wp_safe_redirect( add_query_arg( 'error', 'customer_not_found', admin_url( 'admin.php?page=sfiler-subscription-new' ) ) );
			exit;
		}

		$product_id = absint( $_POST['product_id'] );
		$product    = wc_get_product( $product_id );

		if ( ! $product ) {
			wp_safe_redirect( add_query_arg( 'error', 'product_not_found', admin_url( 'admin.php?page=sfiler-subscription-new' ) ) );
			exit;
		}

		$valid_units = array_keys( sfiler_get_interval_units() );
		$unit        = in_array( $_POST['interval_unit'], $valid_units, true ) ? $_POST['interval_unit'] : 'month';
		$interval    = max( 1, absint( $_POST['interval_count'] ) );
		$next        = sanitize_text_field( wp_unslash( $_POST['next_payment_date'] ) );

		$payment_method = Sfiler_Stripe::get_customer_payment_method( $customer->ID );

		$subscription_id = Sfiler_Subscription::create(
			array(
				'customer_id'              => $customer->ID,
				'parent_order_id'          => 0,
				'product_id'               => $product_id,
				'product_name'             => $product->get_name(),
				'line_total'               => wc_format_decimal( wp_unslash( $_POST['line_total'] ) ),
				'currency'                 => get_woocommerce_currency(),
				'interval_count'           => $interval,
				'interval_unit'            => $unit,
				'stripe_customer_id'       => $payment_method['stripe_customer_id'],
				'stripe_payment_method_id' => $payment_method['payment_method_id'],
				'next_payment_date'        => $next,
			)
		);

		Sfiler_Subscription::add_note( $subscription_id, __( 'Subscription created manually by an administrator.', 'subscript-filter' ) );

		wp_safe_redirect( self::get_view_url( $subscription_id ) );
		exit;
	}
}
