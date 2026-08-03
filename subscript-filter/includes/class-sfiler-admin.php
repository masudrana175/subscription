<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin menu: subscriptions list + settings page.
 */
class Sfiler_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_post_sfiler_save_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_sfiler_retry_subscription', array( __CLASS__, 'retry_subscription' ) );
		add_action( 'admin_post_sfiler_cancel_subscription', array( __CLASS__, 'cancel_subscription' ) );
	}

	public static function add_menu() {
		$hook = add_menu_page(
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
			__( 'Settings', 'subscript-filter' ),
			__( 'Settings', 'subscript-filter' ),
			'manage_woocommerce',
			'sfiler-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function render_subscriptions_page() {
		$list_table = new Sfiler_Admin_List_Table();
		$list_table->prepare_items();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Subscriptions', 'subscript-filter' ); ?></h1>
			<form method="get">
				<input type="hidden" name="page" value="sfiler-subscriptions" />
				<?php $list_table->display(); ?>
			</form>
		</div>
		<?php
	}

	public static function render_settings_page() {
		$max_retry   = get_option( 'sfiler_max_retry_attempts', 3 );
		$retry_days  = get_option( 'sfiler_retry_interval_days', 3 );
		$reminder    = get_option( 'sfiler_reminder_days_before', 3 );
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
}
