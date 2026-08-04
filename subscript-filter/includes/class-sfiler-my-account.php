<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * "Subscriptions" tab under My Account: list, detail view, and self-service
 * actions (pause, resume/reactivate, cancel, change frequency, change payment method).
 */
class Sfiler_My_Account {

	const ENDPOINT = 'subscriptions';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_endpoint' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'add_menu_item' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'render_endpoint' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_actions' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function enqueue_assets() {
		if ( is_account_page() ) {
			wp_enqueue_style( 'sfiler-account', SFILER_PLUGIN_URL . 'assets/css/sfiler-account.css', array(), SFILER_VERSION );
		}
	}

	public static function add_endpoint() {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );

		// A newly registered endpoint 404s until WordPress's cached rewrite
		// rules are flushed. Activation already does this for fresh installs,
		// but a store that activated the plugin before this endpoint existed
		// needs a one-time flush too — this runs on the next page load and
		// then never again, guarded by the option below.
		if ( 'yes' !== get_option( 'sfiler_endpoint_flushed' ) ) {
			flush_rewrite_rules();
			update_option( 'sfiler_endpoint_flushed', 'yes' );
		}
	}

	public static function add_menu_item( $items ) {
		$new_items = array();

		foreach ( $items as $key => $label ) {
			$new_items[ $key ] = $label;
			if ( 'orders' === $key ) {
				$new_items[ self::ENDPOINT ] = __( 'Subscriptions', 'subscript-filter' );
			}
		}

		return $new_items;
	}

	private static function get_owned_subscription( $subscription_id ) {
		$subscription = Sfiler_Subscription::get( $subscription_id );

		if ( ! $subscription || (int) $subscription->customer_id !== get_current_user_id() ) {
			return null;
		}

		return $subscription;
	}

	public static function handle_actions() {
		if ( ! is_user_logged_in() || empty( $_REQUEST['sfiler_action'] ) || empty( $_REQUEST['subscription_id'] ) ) {
			return;
		}

		$subscription_id = absint( $_REQUEST['subscription_id'] );
		$action          = sanitize_text_field( wp_unslash( $_REQUEST['sfiler_action'] ) );

		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'sfiler_action_' . $subscription_id ) ) {
			return;
		}

		$subscription = self::get_owned_subscription( $subscription_id );

		if ( ! $subscription ) {
			return;
		}

		$redirect = wc_get_account_endpoint_url( self::ENDPOINT );

		switch ( $action ) {
			case 'cancel':
				Sfiler_Subscription::cancel( $subscription_id );
				wc_add_notice( __( 'Subscription cancelled.', 'subscript-filter' ) );
				break;

			case 'pause':
				Sfiler_Subscription::pause( $subscription_id );
				wc_add_notice( __( 'Subscription paused.', 'subscript-filter' ) );
				$redirect = self::get_view_url( $subscription_id );
				break;

			case 'resume':
			case 'reactivate':
				Sfiler_Subscription::resume( $subscription_id );
				wc_add_notice( __( 'Subscription reactivated.', 'subscript-filter' ) );
				$redirect = self::get_view_url( $subscription_id );
				break;

			case 'change_frequency':
				self::change_frequency( $subscription );
				$redirect = self::get_view_url( $subscription_id );
				break;

			case 'change_payment_method':
				self::change_payment_method( $subscription );
				$redirect = self::get_view_url( $subscription_id );
				break;
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	private static function change_frequency( $subscription ) {
		if ( empty( $_POST['frequency_index'] ) && '0' !== (string) ( $_POST['frequency_index'] ?? '' ) ) {
			return;
		}

		$frequencies = Sfiler_Product::get_frequencies( $subscription->product_id );
		$index       = absint( $_POST['frequency_index'] );

		if ( ! isset( $frequencies[ $index ] ) ) {
			return;
		}

		Sfiler_Subscription::update(
			$subscription->id,
			array(
				'interval_count' => $frequencies[ $index ]['count'],
				'interval_unit'  => $frequencies[ $index ]['unit'],
			)
		);

		Sfiler_Subscription::add_note(
			$subscription->id,
			sprintf( __( 'Customer changed billing frequency to %s.', 'subscript-filter' ), sfiler_format_interval( $frequencies[ $index ]['count'], $frequencies[ $index ]['unit'] ) )
		);

		wc_add_notice( __( 'Billing frequency updated. This takes effect on your next renewal.', 'subscript-filter' ) );
	}

	private static function change_payment_method( $subscription ) {
		if ( empty( $_POST['payment_token_id'] ) ) {
			return;
		}

		$token = WC_Payment_Tokens::get( absint( $_POST['payment_token_id'] ) );

		if ( ! $token || (int) $token->get_user_id() !== get_current_user_id() ) {
			return;
		}

		$stripe_customer_id = Sfiler_Stripe::resolve_customer_for_token( $token );

		Sfiler_Subscription::update(
			$subscription->id,
			array(
				'stripe_customer_id'       => $stripe_customer_id,
				'stripe_payment_method_id' => $token->get_token(),
			)
		);

		Sfiler_Subscription::add_note( $subscription->id, __( 'Customer updated the saved payment method for this subscription.', 'subscript-filter' ) );
		wc_add_notice( __( 'Payment method updated.', 'subscript-filter' ) );
	}

	public static function get_view_url( $subscription_id ) {
		return add_query_arg( 'id', $subscription_id, wc_get_account_endpoint_url( self::ENDPOINT ) );
	}

	public static function render_endpoint() {
		if ( ! empty( $_GET['id'] ) ) {
			self::render_detail( absint( $_GET['id'] ) );
			return;
		}

		$subscriptions = Sfiler_Subscription::get_for_customer( get_current_user_id() );
		$units         = sfiler_get_interval_units();
		include SFILER_PLUGIN_DIR . 'templates/myaccount/subscriptions.php';
	}

	private static function render_detail( $subscription_id ) {
		$subscription = self::get_owned_subscription( $subscription_id );

		if ( ! $subscription ) {
			echo '<p>' . esc_html__( 'Subscription not found.', 'subscript-filter' ) . '</p>';
			return;
		}

		$units       = sfiler_get_interval_units();
		$orders      = Sfiler_Order::get_orders_for_subscription( $subscription_id );
		$frequencies = Sfiler_Product::get_frequencies( $subscription->product_id );
		$tokens      = Sfiler_Stripe::get_customer_stripe_tokens( get_current_user_id() );

		include SFILER_PLUGIN_DIR . 'templates/myaccount/subscription-view.php';
	}
}
