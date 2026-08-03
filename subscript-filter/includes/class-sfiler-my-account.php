<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * "Subscriptions" tab under My Account: list, cancel, and pause/resume.
 */
class Sfiler_My_Account {

	const ENDPOINT = 'subscriptions';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_endpoint' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'add_menu_item' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'render_endpoint' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_actions' ) );
	}

	public static function add_endpoint() {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
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

	public static function handle_actions() {
		if ( ! is_user_logged_in() || empty( $_GET['sfiler_action'] ) || empty( $_GET['subscription_id'] ) ) {
			return;
		}

		$subscription_id = absint( $_GET['subscription_id'] );
		$action          = sanitize_text_field( wp_unslash( $_GET['sfiler_action'] ) );

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'sfiler_action_' . $subscription_id ) ) {
			return;
		}

		$subscription = Sfiler_Subscription::get( $subscription_id );

		if ( ! $subscription || (int) $subscription->customer_id !== get_current_user_id() ) {
			return;
		}

		switch ( $action ) {
			case 'cancel':
				Sfiler_Subscription::cancel( $subscription_id );
				wc_add_notice( __( 'Subscription cancelled.', 'subscript-filter' ) );
				break;
			case 'pause':
				Sfiler_Subscription::pause( $subscription_id );
				wc_add_notice( __( 'Subscription paused.', 'subscript-filter' ) );
				break;
			case 'resume':
				Sfiler_Subscription::resume( $subscription_id );
				wc_add_notice( __( 'Subscription resumed.', 'subscript-filter' ) );
				break;
		}

		wp_safe_redirect( wc_get_account_endpoint_url( self::ENDPOINT ) );
		exit;
	}

	public static function render_endpoint() {
		$subscriptions = Sfiler_Subscription::get_for_customer( get_current_user_id() );
		$units         = sfiler_get_interval_units();
		include SFILER_PLUGIN_DIR . 'templates/my-account-subscriptions.php';
	}
}
