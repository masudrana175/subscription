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
		add_action( 'admin_post_sfiler_test_stripe_connection', array( __CLASS__, 'test_stripe_connection' ) );
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

		if ( Sfiler_Stripe::is_configured() ) {
			return;
		}
		?>
		<div class="notice notice-warning">
			<p>
				<?php
				printf(
					/* translators: %s: link to Subscript Filter settings */
					esc_html__( 'Subscript Filter: no Stripe secret key is configured yet, so renewals cannot be charged. %s', 'subscript-filter' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=sfiler-settings' ) ) . '">' . esc_html__( 'Add it now', 'subscript-filter' ) . '</a>'
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
		$default_enabled = get_option( 'sfiler_default_enabled', 'yes' );
		$global_discount = get_option( 'sfiler_global_discount_percent', 10 );
		$max_retry       = get_option( 'sfiler_max_retry_attempts', 3 );
		$retry_days      = get_option( 'sfiler_retry_interval_days', 3 );
		$reminder        = get_option( 'sfiler_reminder_days_before', 3 );
		$test_mode       = get_option( 'sfiler_stripe_test_mode', 'no' );
		$test_secret     = get_option( 'sfiler_stripe_test_secret_key', '' );
		$live_secret     = get_option( 'sfiler_stripe_live_secret_key', '' );
		$test_result_url = wp_nonce_url( add_query_arg( 'action', 'sfiler_test_stripe_connection', admin_url( 'admin-post.php' ) ), 'sfiler_test_stripe_connection' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Subscript Filter Settings', 'subscript-filter' ); ?></h1>

			<?php if ( isset( $_GET['stripe_test'] ) ) : ?>
				<div class="notice <?php echo 'ok' === $_GET['stripe_test'] ? 'notice-success' : 'notice-error'; ?>">
					<p>
						<?php
						if ( 'ok' === $_GET['stripe_test'] ) {
							esc_html_e( 'Stripe connection successful.', 'subscript-filter' );
						} else {
							echo esc_html( sprintf( __( 'Stripe connection failed: %s', 'subscript-filter' ), isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '' ) );
						}
						?>
					</p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sfiler_save_settings" />
				<?php wp_nonce_field( 'sfiler_save_settings' ); ?>

				<h2><?php esc_html_e( 'Subscription pricing', 'subscript-filter' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="sfiler_default_enabled"><?php esc_html_e( 'Offer subscriptions on all products', 'subscript-filter' ); ?></label></th>
						<td>
							<input type="checkbox" id="sfiler_default_enabled" name="sfiler_default_enabled" value="yes" <?php checked( $default_enabled, 'yes' ); ?> />
							<p class="description"><?php esc_html_e( 'When checked, every product shows the subscribe & save option by default. Turn it off for individual products on their own Subscriptions tab.', 'subscript-filter' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="sfiler_global_discount_percent"><?php esc_html_e( 'Default subscription discount (%)', 'subscript-filter' ); ?></label></th>
						<td>
							<input type="number" step="0.01" min="0" max="100" id="sfiler_global_discount_percent" name="sfiler_global_discount_percent" value="<?php echo esc_attr( $global_discount ); ?>" />
							<p class="description"><?php esc_html_e( 'Applied to every subscription-enabled product unless a product sets its own override on the Subscriptions tab.', 'subscript-filter' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Stripe API', 'subscript-filter' ); ?></h2>
				<?php if ( Sfiler_Stripe::payment_plugins_stripe_active() ) : ?>
					<p class="description" style="color:#1a7f37;">
						<?php esc_html_e( '"Payment Plugins for Stripe WooCommerce" was detected. Subscript Filter automatically uses its configured secret key and mode (test/live) — you do not need to fill in the fields below unless that plugin is deactivated or you want to override it.', 'subscript-filter' ); ?>
					</p>
				<?php else : ?>
					<p class="description">
						<?php esc_html_e( 'Renewals are charged directly through the Stripe API. Enter the secret key(s) from your Stripe Dashboard > Developers > API keys — this must be the same Stripe account your storefront checkout plugin uses.', 'subscript-filter' ); ?>
					</p>
				<?php endif; ?>
				<table class="form-table">
					<tr>
						<th><label for="sfiler_stripe_test_mode"><?php esc_html_e( 'Use test mode', 'subscript-filter' ); ?></label></th>
						<td><input type="checkbox" id="sfiler_stripe_test_mode" name="sfiler_stripe_test_mode" value="yes" <?php checked( $test_mode, 'yes' ); ?> /> <span class="description"><?php esc_html_e( 'Only used when the fallback keys below are active.', 'subscript-filter' ); ?></span></td>
					</tr>
					<tr>
						<th><label for="sfiler_stripe_test_secret_key"><?php esc_html_e( 'Fallback test secret key', 'subscript-filter' ); ?></label></th>
						<td><input type="password" autocomplete="off" class="regular-text" id="sfiler_stripe_test_secret_key" name="sfiler_stripe_test_secret_key" value="<?php echo esc_attr( $test_secret ); ?>" placeholder="sk_test_..." /></td>
					</tr>
					<tr>
						<th><label for="sfiler_stripe_live_secret_key"><?php esc_html_e( 'Fallback live secret key', 'subscript-filter' ); ?></label></th>
						<td><input type="password" autocomplete="off" class="regular-text" id="sfiler_stripe_live_secret_key" name="sfiler_stripe_live_secret_key" value="<?php echo esc_attr( $live_secret ); ?>" placeholder="sk_live_..." /></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Renewal behavior', 'subscript-filter' ); ?></h2>
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
				<?php submit_button( __( 'Save settings', 'subscript-filter' ) ); ?>
			</form>

			<p>
				<a class="button" href="<?php echo esc_url( $test_result_url ); ?>"><?php esc_html_e( 'Test Stripe connection', 'subscript-filter' ); ?></a>
				<span class="description"><?php esc_html_e( 'Uses whichever key matches the test-mode toggle above (save settings first).', 'subscript-filter' ); ?></span>
			</p>

			<p>
				<?php esc_html_e( 'Note: this key only needs "Payments" read/write scope for creating off-session PaymentIntents. It must belong to the same Stripe account your storefront checkout plugin uses, since customers and payment methods are shared across the account.', 'subscript-filter' ); ?>
			</p>
		</div>
		<?php
	}

	public static function save_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'sfiler_save_settings' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'subscript-filter' ) );
		}

		update_option( 'sfiler_default_enabled', isset( $_POST['sfiler_default_enabled'] ) ? 'yes' : 'no' );
		update_option( 'sfiler_global_discount_percent', wc_format_decimal( wp_unslash( $_POST['sfiler_global_discount_percent'] ?? 10 ) ) );
		update_option( 'sfiler_max_retry_attempts', max( 1, absint( $_POST['sfiler_max_retry_attempts'] ) ) );
		update_option( 'sfiler_retry_interval_days', max( 1, absint( $_POST['sfiler_retry_interval_days'] ) ) );
		update_option( 'sfiler_reminder_days_before', absint( $_POST['sfiler_reminder_days_before'] ) );
		update_option( 'sfiler_stripe_test_mode', isset( $_POST['sfiler_stripe_test_mode'] ) ? 'yes' : 'no' );
		update_option( 'sfiler_stripe_test_secret_key', sanitize_text_field( wp_unslash( $_POST['sfiler_stripe_test_secret_key'] ?? '' ) ) );
		update_option( 'sfiler_stripe_live_secret_key', sanitize_text_field( wp_unslash( $_POST['sfiler_stripe_live_secret_key'] ?? '' ) ) );

		wp_safe_redirect( add_query_arg( array( 'page' => 'sfiler-settings', 'updated' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function test_stripe_connection() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'sfiler_test_stripe_connection' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'subscript-filter' ) );
		}

		$secret_key = Sfiler_Stripe::get_secret_key();
		$result     = empty( $secret_key ) ? __( 'No secret key saved for the current mode.', 'subscript-filter' ) : Sfiler_Stripe::test_connection( $secret_key );

		$redirect_args = array(
			'page'        => 'sfiler-settings',
			'stripe_test' => true === $result ? 'ok' : 'fail',
		);

		if ( true !== $result ) {
			$redirect_args['message'] = $result;
		}

		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
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
