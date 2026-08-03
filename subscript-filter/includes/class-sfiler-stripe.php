<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Talks to the Stripe REST API directly (no SDK dependency) to charge saved
 * payment methods off-session for renewals. Card and Apple Pay both end up
 * stored as reusable Stripe payment methods, so both renew the same way.
 */
class Sfiler_Stripe {

	const API_BASE = 'https://api.stripe.com/v1';

	public static function get_secret_key() {
		$settings = get_option( 'woocommerce_stripe_settings', array() );

		if ( empty( $settings ) ) {
			return '';
		}

		$testmode = isset( $settings['testmode'] ) && 'yes' === $settings['testmode'];

		if ( $testmode ) {
			return isset( $settings['test_secret_key'] ) ? $settings['test_secret_key'] : '';
		}

		return isset( $settings['secret_key'] ) ? $settings['secret_key'] : '';
	}

	/**
	 * Determine the Stripe customer + reusable payment method attached to an order's customer.
	 *
	 * @return array{stripe_customer_id:string,payment_method_id:string}
	 */
	public static function get_customer_payment_method( $customer_id ) {
		$stripe_customer_id = get_user_meta( $customer_id, '_stripe_customer_id', true );
		$payment_method_id  = '';

		if ( class_exists( 'WC_Payment_Tokens' ) ) {
			$token = WC_Payment_Tokens::get_customer_default_token( $customer_id );

			if ( ! $token || 'stripe' !== strtolower( $token->get_gateway_id() ) ) {
				$tokens = WC_Payment_Tokens::get_customer_tokens( $customer_id, 'stripe' );
				$token  = ! empty( $tokens ) ? end( $tokens ) : null;
			}

			if ( $token ) {
				$payment_method_id = $token->get_token();
			}
		}

		return array(
			'stripe_customer_id' => $stripe_customer_id,
			'payment_method_id'  => $payment_method_id,
		);
	}

	/**
	 * Charge a subscription renewal off-session. Returns the Stripe PaymentIntent
	 * array on success, or throws an Exception with the Stripe error message on failure.
	 */
	public static function charge_renewal( $subscription ) {
		$secret_key = self::get_secret_key();

		if ( empty( $secret_key ) ) {
			throw new Exception( __( 'Stripe secret key is not configured.', 'subscript-filter' ) );
		}

		if ( empty( $subscription->stripe_customer_id ) || empty( $subscription->stripe_payment_method_id ) ) {
			throw new Exception( __( 'No saved Stripe payment method for this subscription.', 'subscript-filter' ) );
		}

		$amount = self::to_smallest_unit( (float) $subscription->line_total, $subscription->currency );

		$body = array(
			'amount'               => $amount,
			'currency'             => strtolower( $subscription->currency ),
			'customer'             => $subscription->stripe_customer_id,
			'payment_method'       => $subscription->stripe_payment_method_id,
			'off_session'          => 'true',
			'confirm'              => 'true',
			'description'          => sprintf( 'Subscript Filter renewal #%d', $subscription->id ),
			'metadata[sfiler_subscription_id]' => $subscription->id,
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
