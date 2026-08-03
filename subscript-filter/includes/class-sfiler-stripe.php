<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Talks to the Stripe REST API directly (no SDK dependency) to charge saved
 * payment methods off-session for renewals. Card and Apple Pay both end up
 * stored as reusable Stripe payment methods, so both renew the same way.
 *
 * "Payment Plugins for Stripe WooCommerce" (woo-stripe-payment) exposes its
 * own public helper functions for exactly this — wc_stripe_get_secret_key()
 * and wc_stripe_get_customer_id() — which read its real settings/user-meta
 * storage directly, so we call those when the plugin is active instead of
 * guessing at option names. Subscript Filter's own Settings page (test/live
 * secret key) is kept as a fallback for stores running a different Stripe
 * checkout plugin, or if those functions aren't available for any reason.
 *
 * Saved payment method tokens are read via WC_Payment_Tokens (WooCommerce
 * core), matching any gateway ID containing "stripe" — this plugin registers
 * per-method gateway IDs like stripe_cc, stripe_applepay, stripe_googlepay.
 */
class Sfiler_Stripe {

	const API_BASE = 'https://api.stripe.com/v1';

	public static function payment_plugins_stripe_active() {
		return function_exists( 'wc_stripe_get_secret_key' );
	}

	public static function get_secret_key() {
		if ( self::payment_plugins_stripe_active() ) {
			$key = wc_stripe_get_secret_key();
			if ( ! empty( $key ) ) {
				return $key;
			}
		}

		$testmode = 'yes' === get_option( 'sfiler_stripe_test_mode', 'no' );

		if ( $testmode ) {
			return trim( get_option( 'sfiler_stripe_test_secret_key', '' ) );
		}

		return trim( get_option( 'sfiler_stripe_live_secret_key', '' ) );
	}

	public static function is_configured() {
		return '' !== self::get_secret_key();
	}

	/**
	 * Determine the payment method + customer that should be charged for
	 * renewals on a specific order. This is the preferred path when an
	 * initial order is available: "Payment Plugins for Stripe WooCommerce"
	 * (v3+) stores the exact payment method and customer used on that order
	 * as order meta (WC_Stripe_Constants::PAYMENT_METHOD_TOKEN /
	 * ::CUSTOMER_ID) — its own WooCommerce-Subscriptions renewal handler
	 * reads the same fields for the same reason: a customer can have
	 * multiple saved cards, so the token used for *this* order isn't
	 * necessarily their current default one.
	 *
	 * @return array{stripe_customer_id:string,payment_method_id:string}
	 */
	public static function get_order_payment_method( $order ) {
		if ( class_exists( 'WC_Stripe_Constants' ) ) {
			$payment_method_id  = trim( (string) $order->get_meta( WC_Stripe_Constants::PAYMENT_METHOD_TOKEN ) );
			$stripe_customer_id = trim( (string) $order->get_meta( WC_Stripe_Constants::CUSTOMER_ID ) );

			if ( $payment_method_id ) {
				if ( ! $stripe_customer_id ) {
					$stripe_customer_id = self::fetch_payment_method_customer( $payment_method_id );
				}

				return array(
					'stripe_customer_id' => $stripe_customer_id,
					'payment_method_id'  => $payment_method_id,
				);
			}
		}

		return self::get_customer_payment_method( $order->get_customer_id() );
	}

	/**
	 * Fallback: determine the reusable Stripe payment method attached to a
	 * customer (their current default/most recent saved method), regardless
	 * of which Stripe checkout plugin created it. Used when no specific
	 * order is available (e.g. an admin manually creating a subscription).
	 *
	 * @return array{stripe_customer_id:string,payment_method_id:string}
	 */
	public static function get_customer_payment_method( $customer_id ) {
		$payment_method_id  = '';
		$stripe_customer_id = '';

		if ( class_exists( 'WC_Payment_Tokens' ) ) {
			$token = WC_Payment_Tokens::get_customer_default_token( $customer_id );

			if ( ! $token || false === stripos( $token->get_gateway_id(), 'stripe' ) ) {
				$token = self::find_stripe_token( $customer_id );
			}

			if ( $token ) {
				$payment_method_id = $token->get_token();
			}
		}

		if ( self::payment_plugins_stripe_active() ) {
			$stripe_customer_id = wc_stripe_get_customer_id( $customer_id );
		}

		if ( ! $stripe_customer_id && $payment_method_id ) {
			$stripe_customer_id = self::fetch_payment_method_customer( $payment_method_id );
		}

		if ( ! $stripe_customer_id ) {
			$stripe_customer_id = get_user_meta( $customer_id, '_stripe_customer_id', true );
		}

		return array(
			'stripe_customer_id' => $stripe_customer_id,
			'payment_method_id'  => $payment_method_id,
		);
	}

	/**
	 * Resolve the Stripe customer ID attached to a specific WC_Payment_Token.
	 * Used when a customer explicitly picks a saved payment method to attach
	 * to a subscription.
	 */
	public static function resolve_customer_for_token( $token ) {
		if ( self::payment_plugins_stripe_active() ) {
			$stripe_customer_id = wc_stripe_get_customer_id( $token->get_user_id() );
			if ( ! empty( $stripe_customer_id ) ) {
				return $stripe_customer_id;
			}
		}

		$stripe_customer_id = self::fetch_payment_method_customer( $token->get_token() );

		if ( ! $stripe_customer_id ) {
			$stripe_customer_id = get_user_meta( $token->get_user_id(), '_stripe_customer_id', true );
		}

		return $stripe_customer_id;
	}

	private static function find_stripe_token( $customer_id ) {
		$all_tokens = WC_Payment_Tokens::get_customer_tokens( $customer_id );
		$stripe_tokens = array_filter(
			$all_tokens,
			function ( $token ) {
				return false !== stripos( $token->get_gateway_id(), 'stripe' );
			}
		);

		return ! empty( $stripe_tokens ) ? end( $stripe_tokens ) : null;
	}

	/**
	 * Ask Stripe which customer a payment method is currently attached to.
	 * More reliable than guessing a third-party plugin's user-meta key.
	 */
	private static function fetch_payment_method_customer( $payment_method_id ) {
		$secret_key = self::get_secret_key();

		if ( empty( $secret_key ) ) {
			return '';
		}

		$response = wp_remote_get(
			self::API_BASE . '/payment_methods/' . rawurlencode( $payment_method_id ),
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $secret_key,
				),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		return ! empty( $data['customer'] ) ? $data['customer'] : '';
	}

	/**
	 * Charge a subscription renewal off-session. Returns the Stripe PaymentIntent
	 * array on success, or throws an Exception with the Stripe error message on failure.
	 */
	public static function charge_renewal( $subscription ) {
		$secret_key = self::get_secret_key();

		if ( empty( $secret_key ) ) {
			throw new Exception( __( 'Stripe secret key is not configured in Subscript Filter settings.', 'subscript-filter' ) );
		}

		if ( empty( $subscription->stripe_customer_id ) || empty( $subscription->stripe_payment_method_id ) ) {
			throw new Exception( __( 'No saved Stripe payment method for this subscription.', 'subscript-filter' ) );
		}

		$amount = self::to_smallest_unit( (float) $subscription->line_total, $subscription->currency );

		$body = array(
			'amount'                            => $amount,
			'currency'                          => strtolower( $subscription->currency ),
			'customer'                          => $subscription->stripe_customer_id,
			'payment_method'                    => $subscription->stripe_payment_method_id,
			'off_session'                       => 'true',
			'confirm'                           => 'true',
			'description'                       => sprintf( 'Subscript Filter renewal #%d', $subscription->id ),
			'metadata[sfiler_subscription_id]'  => $subscription->id,
		);

		$response = wp_remote_post(
			self::API_BASE . '/payment_intents',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $secret_key,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => $body,
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new Exception( $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 || empty( $data['status'] ) ) {
			$message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown Stripe error.', 'subscript-filter' );
			throw new Exception( $message );
		}

		if ( 'succeeded' !== $data['status'] ) {
			throw new Exception( sprintf( __( 'Payment not completed, status: %s', 'subscript-filter' ), $data['status'] ) );
		}

		return $data;
	}

	/**
	 * Verify a secret key works by pinging the Stripe balance endpoint.
	 * Used by the "Test connection" button on the settings page.
	 */
	public static function test_connection( $secret_key ) {
		$response = wp_remote_get(
			self::API_BASE . '/balance',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . trim( $secret_key ),
				),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 === $code ) {
			return true;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return isset( $data['error']['message'] ) ? $data['error']['message'] : sprintf( __( 'Stripe returned HTTP %d.', 'subscript-filter' ), $code );
	}

	/**
	 * Stripe expects amounts in the smallest currency unit (cents). A handful
	 * of currencies are zero-decimal and must be passed as-is.
	 */
	public static function to_smallest_unit( $amount, $currency ) {
		$zero_decimal = array( 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' );

		if ( in_array( strtoupper( $currency ), $zero_decimal, true ) ) {
			return (int) round( $amount );
		}

		return (int) round( $amount * 100 );
	}
}
